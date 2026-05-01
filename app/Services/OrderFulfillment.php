<?php

namespace App\Services;

use App\Models\Flashsale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah Order menjadi PAID secara atomik + assign stok yang available.
 * Semua pemanggil WAJIB lewat sini agar idempotent dan race-free.
 */
class OrderFulfillment
{
    /**
     * @param  array<string,mixed>  $context  Detail untuk audit log (metode bayar,
     *                                        amount asli dari gateway, source, dll).
     * @param  bool  $allowFromTerminalStates  Bila true, izinkan transisi dari status
     *                                         non-pending/non-paid (cancelled, refunded,
     *                                         expired, failed) ke PAID. Hanya boleh
     *                                         dipakai dari admin EditOrder yang merupakan
     *                                         override eksplisit.
     * @return bool true jika order jadi PAID (termasuk kasus stok habis),
     *              false jika order tidak bisa diproses.
     */
    public function markPaidAndAssignStock(
        Order $order,
        array $context = [],
        bool $allowFromTerminalStates = false,
    ): bool {
        $assignedStock = false;
        $transitionedToPaid = false;
        $result = DB::transaction(function () use ($order, $context, $allowFromTerminalStates, &$assignedStock, &$transitionedToPaid) {
            /** @var Order $locked */
            $locked = Order::lockForUpdate()->find($order->id);
            if (! $locked) {
                return false;
            }

            $wasAlreadyPaid = $locked->isPaid();
            $items = $locked->items()->lockForUpdate()->get();

            // Idempotent: kalau sudah paid DAN stok sudah ter-assign, tidak ada yang perlu dikerjakan.
            if ($wasAlreadyPaid && $this->stockAlreadyAssigned($locked, $items)) {
                return true;
            }

            // Guard race condition (TOCTOU): pemanggil mengecek status di luar
            // transaction — bisa berubah saat verifikasi via API berlangsung
            // (Pakasir sampai ~10 detik). Setelah lock, tolak transisi dari
            // status terminal (cancelled/refunded/expired/failed) kecuali admin
            // memberi override eksplisit lewat parameter $allowFromTerminalStates.
            if (! $locked->isPending() && ! $wasAlreadyPaid && ! $allowFromTerminalStates) {
                Audit::log('order.transition_blocked', $locked, array_merge($context, [
                    'current_status' => $locked->status,
                    'reason' => 'not_pending_or_paid',
                ]));

                return false;
            }

            if (! $wasAlreadyPaid) {
                $locked->status = Order::STATUS_PAID;
                $locked->paid_at = now();
                $transitionedToPaid = true;
            }
            if (! empty($context['payment_method'])) {
                $locked->payment_method = (string) $context['payment_method'];
            }

            // Assign stok untuk setiap OrderItem (multi-item path) — atau fallback
            // ke single-item legacy path jika order tidak punya items.
            if ($items->isNotEmpty()) {
                $assignedStock = $this->assignStockForItems($locked, $items, $wasAlreadyPaid);
            } else {
                $assignedStock = $this->assignStockLegacy($locked, $wasAlreadyPaid);
            }

            $locked->save();

            // Counter flashsale + sold_count produk hanya di-increment sekali,
            // saat transisi pertama kali ke PAID. Hindari double-count saat
            // re-run untuk rescue stock assignment.
            if (! $wasAlreadyPaid) {
                if ($items->isNotEmpty()) {
                    foreach ($items as $item) {
                        $this->incrementCounters($item->product_id, $item->product_variant_id, $item->unit_price, max(1, (int) $item->qty));
                    }
                } else {
                    $this->incrementCounters($locked->product_id, $locked->product_variant_id, (int) $locked->amount, 1);
                }

                Audit::log('order.paid', $locked, $context);
            }

            return true;
        });

        // Notif admin via Telegram saat transisi ke PAID — di luar transaction
        // supaya HTTP call tidak block lock DB. Trigger sekali saja, tidak ulang
        // pada idempotent retry (saat order sudah PAID dari sebelumnya).
        if ($result && $transitionedToPaid) {
            app(TelegramBotService::class)->notifyAdminOrderPaid($order->fresh());
        }

        // Side effects post-PAID (di luar transaction utama biar tidak block):
        //  - Aktifkan membership kalau order ini subscription.
        //  - Credit saldo wallet kalau order ini self-service top-up.
        //  - Aktifkan akun pending kalau order ini register paywall.
        //  - Credit komisi affiliate ke referrer kalau order ini punya referral_user_id.
        if ($result && $transitionedToPaid) {
            $fresh = $order->fresh();
            if ($fresh) {
                if ($fresh->is_member_subscription) {
                    try {
                        app(MembershipService::class)->activateFromOrder($fresh);
                    } catch (\Throwable $e) {
                        \Log::error('Membership activation failed', [
                            'order_id' => $fresh->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($fresh->is_wallet_topup && $fresh->user_id) {
                    try {
                        $user = User::find($fresh->user_id);
                        if ($user) {
                            // Idempotent: only credit kalau belum ada transaction utk order ini.
                            $exists = WalletTransaction::where('order_id', $fresh->id)
                                ->where('type', WalletTransaction::TYPE_DEPOSIT)
                                ->exists();
                            if (! $exists) {
                                WalletService::credit(
                                    user: $user,
                                    amount: (int) $fresh->total_payment,
                                    type: WalletTransaction::TYPE_DEPOSIT,
                                    note: 'Top up via '.($fresh->gateway ?: 'gateway'),
                                    orderId: $fresh->id,
                                );
                            }
                        }
                    } catch (\Throwable $e) {
                        \Log::error('Wallet topup credit failed', [
                            'order_id' => $fresh->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($fresh->is_register_activation && $fresh->user_id) {
                    try {
                        $user = User::find($fresh->user_id);
                        if ($user && $user->is_pending_activation) {
                            $user->forceFill([
                                'is_pending_activation' => false,
                                'email_verified_at' => $user->email_verified_at ?? now(),
                            ])->save();
                            Audit::log('user.register_activated', $user, ['order_id' => $fresh->id]);
                        }
                    } catch (\Throwable $e) {
                        \Log::error('Register activation failed', [
                            'order_id' => $fresh->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                try {
                    app(AffiliateService::class)->creditForOrder($fresh);
                } catch (\Throwable $e) {
                    \Log::error('Affiliate commission credit failed', [
                        'order_id' => $fresh->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Auto-kirim kredensial via Fonnte WA — di luar transaction supaya HTTP call
        // tidak block lock DB. Service handle exception sendiri (return false, gak throw).
        if ($result && $assignedStock) {
            $fresh = $order->fresh(['stock', 'product', 'variant', 'user', 'items.stock', 'items.product', 'items.variant']);
            app(FonnteWhatsApp::class)->sendCredentials($fresh);

            // Auto-kirim ke Telegram juga kalau user linked.
            if ($fresh && $fresh->user && $fresh->user->telegram_chat_id) {
                try {
                    app(TelegramBotService::class)->sendCredentialsForOrder($fresh);
                } catch (\Throwable $e) {
                    \Log::error('Telegram sendCredentials failed', ['order' => $fresh->id, 'error' => $e->getMessage()]);
                }
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    protected function stockAlreadyAssigned(Order $order, $items): bool
    {
        if ($items->isEmpty()) {
            return (bool) $order->stock_id;
        }

        // Cukup salah satu item belum terassign (dan masih ada stok yang bisa
        // diassign saat rescue) maka belum dianggap selesai. Untuk idempotent
        // sederhana: anggap selesai jika SEMUA item sudah punya stock_id ATAU
        // tidak punya stok (manual delivery).
        return $items->every(fn (OrderItem $i) => $i->stock_id !== null || $i->fulfilled_at !== null);
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    protected function assignStockForItems(Order $order, $items, bool $wasAlreadyPaid): bool
    {
        $anyAssigned = false;
        foreach ($items as $item) {
            if ($item->stock_id) {
                $anyAssigned = true;

                continue;
            }

            $stock = Stock::where('product_variant_id', $item->product_variant_id)
                ->where('is_sold', false)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($stock) {
                $stock->is_sold = true;
                $stock->sold_at = now();
                $stock->save();

                $item->stock_id = $stock->id;
                $item->fulfilled_at = now();
                $item->save();

                $anyAssigned = true;

                Audit::log('stock.delivered', $order, [
                    'stock_id' => $stock->id,
                    'variant_id' => $item->product_variant_id,
                    'order_item_id' => $item->id,
                ]);
            } else {
                Audit::log('stock.out_of_stock', $order, [
                    'variant_id' => $item->product_variant_id,
                    'order_item_id' => $item->id,
                    'note' => 'Pembayaran sukses tapi stok otomatis kosong.',
                ]);
            }
        }

        // Untuk single-item order yang juga punya 1 OrderItem, sinkron-kan
        // Order.stock_id ke item.stock_id supaya kompatibel dengan kode lama
        // yang masih membaca Order.stock_id (mis. Filament resource).
        if ($items->count() === 1 && $items->first()->stock_id && empty($order->stock_id)) {
            $order->stock_id = $items->first()->stock_id;
        }

        return $anyAssigned;
    }

    protected function assignStockLegacy(Order $order, bool $wasAlreadyPaid): bool
    {
        if (empty($order->product_variant_id)) {
            return false;
        }

        $stock = Stock::where('product_variant_id', $order->product_variant_id)
            ->where('is_sold', false)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            Audit::log('stock.out_of_stock', $order, [
                'variant_id' => $order->product_variant_id,
                'note' => 'Pembayaran sukses tapi stok otomatis kosong.',
            ]);

            return false;
        }

        $stock->is_sold = true;
        $stock->sold_at = now();
        $stock->save();

        $order->stock_id = $stock->id;

        Audit::log('stock.delivered', $order, [
            'stock_id' => $stock->id,
            'variant_id' => $order->product_variant_id,
        ]);

        return true;
    }

    protected function incrementCounters(?int $productId, ?int $variantId, int $amount, int $qty): void
    {
        if ($variantId) {
            $fs = Flashsale::active()
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();
            if ($fs && $amount === (int) $fs->flash_price) {
                $fs->increment('sold', $qty);
            }
        }
        if ($productId) {
            Product::whereKey($productId)->increment('sold_count', $qty);
        }
    }
}
