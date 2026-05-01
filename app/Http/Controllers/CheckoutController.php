<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Voucher;
use App\Models\WalletTransaction;
use App\Services\AffiliateService;
use App\Services\OrderFulfillment;
use App\Services\PakasirService;
use App\Services\PaymentGatewayManager;
use App\Services\WalletService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected PakasirService $pakasir,
        protected PaymentGatewayManager $gateways,
    ) {}

    /**
     * GET /checkout/{product}/{variant}
     * Halaman form checkout instan (tanpa login).
     */
    public function show(Product $product, ProductVariant $variant): View|RedirectResponse
    {
        abort_if($variant->product_id !== $product->id, 404);

        $available = $variant->availableStocks()->count();

        // Hanya block kalau VARIAN ini set auto-send dan stoknya nol.
        // Varian manual boleh tetap di-checkout walau stocknya kosong —
        // admin akan input akun manual setelah PAID.
        if ($available <= 0 && $variant->isAutoSend()) {
            return redirect()
                ->route('products.show', $product)
                ->with('error', 'Mohon maaf, stok untuk varian ini sedang kosong.');
        }

        return view('checkout', [
            'product' => $product,
            'variant' => $variant,
            'available' => $available,
            'gateways' => $this->gateways->availability(),
            'defaultGateway' => $this->gateways->defaultGateway(),
            'walletEligible' => $this->isWalletEligible(),
        ]);
    }

    /**
     * POST /checkout
     * Validasi input, kunci harga di server, buat Order, redirect ke Pakasir.
     */
    public function store(Request $request): RedirectResponse
    {
        // Tolak user banned (defensif — login juga sudah block, tapi kalau sesi
        // masih hidup saat di-ban, harus tetap di-block di sini).
        if (auth()->check() && auth()->user()->is_banned) {
            auth()->logout();

            return redirect()->route('login')->with('error', 'Akun Anda di-banned. Tidak bisa checkout.');
        }

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\- ]+$/'],
            'voucher_code' => ['nullable', 'string', 'max:64'],
            'gateway' => ['nullable', 'string', 'in:pakasir,eqris,wallet'],
            'eqris_method' => ['nullable', 'string', 'in:orkut,gomerch'],
        ]);

        /** @var ProductVariant $variant */
        $variant = ProductVariant::with('product')->findOrFail($data['product_variant_id']);

        abort_if(
            $variant->product_id !== (int) $data['product_id'],
            422,
            'Varian tidak cocok dengan produk.'
        );

        // Hitung harga ULANG di server — jangan percaya input client.
        // Pakai harga flashsale kalau sedang aktif untuk varian ini.
        $amount = $variant->effectivePrice();
        $discount = 0;
        $voucher = null;
        if (! empty($data['voucher_code'])) {
            $voucher = Voucher::active()
                ->whereRaw('LOWER(code) = ?', [strtolower(trim($data['voucher_code']))])
                ->first();

            if (! $voucher) {
                return back()->withInput()->withErrors([
                    'voucher_code' => 'Kode voucher tidak ditemukan atau sudah kadaluarsa.',
                ]);
            }
            $discount = $voucher->discountFor($amount);
            if ($discount <= 0) {
                return back()->withInput()->withErrors([
                    'voucher_code' => 'Voucher tidak memenuhi syarat (cek minimal pembelian / sisa kuota).',
                ]);
            }
        }

        $fee = 0;
        $total = max(0, $amount - $discount) + $fee;
        $userId = Auth::id();

        // Resolve gateway pilihan buyer.
        $payWithBalance = ($data['gateway'] ?? null) === Order::GATEWAY_WALLET;
        $resolvedGateway = null;
        $resolvedMethod = null;
        if (! $payWithBalance) {
            $resolved = $this->gateways->resolve($data['gateway'] ?? null, $data['eqris_method'] ?? null);
            if (! $resolved) {
                return back()->withInput()->withErrors([
                    'gateway' => 'Metode pembayaran yang dipilih tidak tersedia. Silakan pilih ulang.',
                ]);
            }
            [$resolvedGateway, $resolvedMethod] = $resolved;
        } else {
            // Validasi wallet eligibility.
            if (! $this->isWalletEligible()) {
                return back()->withInput()->withErrors([
                    'gateway' => 'Pembayaran via saldo hanya untuk member aktif (kalau opsi member-only diaktifkan).',
                ]);
            }
            if ($total > (int) Auth::user()->balance) {
                return back()->withInput()->withErrors([
                    'gateway' => 'Saldo tidak cukup untuk membayar order ini.',
                ]);
            }
            $resolvedGateway = Order::GATEWAY_WALLET;
        }

        // Resolve referrer dari cookie/query string saat checkout. Untuk user
        // yang sudah punya referrer terdaftar, gunakan itu; kalau belum,
        // ambil dari cookie (first-touch).
        $referrerId = $this->resolveReferrerId($request, $userId);

        $order = DB::transaction(function () use ($variant, $data, $amount, $discount, $fee, $total, $userId, $voucher, $resolvedGateway, $resolvedMethod, $payWithBalance, $referrerId) {
            $order = Order::create([
                'order_code' => Order::generateOrderCode(),
                'user_id' => $userId, // null untuk guest
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'voucher_id' => $voucher?->id,
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'amount' => $amount,
                'discount_amount' => $discount,
                'fee' => $fee,
                'total_payment' => $total,
                'gateway' => $resolvedGateway,
                'eqris_method' => $resolvedMethod,
                'pay_with_balance' => $payWithBalance,
                'referral_user_id' => $referrerId,
                'status' => Order::STATUS_PENDING,
                'source' => Order::SOURCE_WEB,
                'expired_at' => now()->addMinutes(
                    PakasirService::orderExpiryMinutes()
                ),
            ]);

            // Sinkron OrderItem (unified fulfillment path) — single-item juga
            // punya 1 baris OrderItem agar service fulfillment konsisten antara
            // checkout instan dan checkout cart multi-item.
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'qty' => 1,
                'unit_price' => $amount,
            ]);

            if ($voucher) {
                Voucher::where('id', $voucher->id)->increment('used_count');
            }

            return $order;
        });

        Audit::log('order.created', $order, [
            'amount' => $amount,
            'variant' => $variant->name,
            'product' => $variant->product?->name,
            'gateway' => $resolvedGateway,
        ]);

        // Pembayaran via saldo — langsung debit + tandai PAID + assign stok.
        if ($payWithBalance) {
            return $this->handleWalletPayment($order);
        }

        // Redirect ke invoice publik kita sendiri — QRIS akan di-render di
        // halaman tersebut sesuai gateway yang dipilih.
        return redirect()
            ->route('invoice.show', $order->order_code)
            ->with('success', 'Order berhasil dibuat. Scan QRIS di bawah untuk membayar.');
    }

    /**
     * Eligibility wallet checkout: harus login + (opsional) harus active member.
     */
    protected function isWalletEligible(): bool
    {
        if (! Auth::check()) {
            return false;
        }
        $site = SiteSetting::current();
        if (! ($site->wallet_checkout_enabled ?? true)) {
            return false;
        }
        if ($site->wallet_checkout_members_only ?? true) {
            /** @var User $user */
            $user = Auth::user();
            if (! $user->isActiveMember()) {
                return false;
            }
        }

        return true;
    }

    protected function resolveReferrerId(Request $request, ?int $userId): ?int
    {
        if (! AffiliateService::isEnabled()) {
            return null;
        }

        // User yg sudah login — prioritas pakai user.referred_by_id (sudah di-attach saat register).
        if ($userId) {
            $user = User::find($userId);
            if ($user && $user->referred_by_id) {
                return (int) $user->referred_by_id;
            }
        }

        $referrer = AffiliateService::resolveReferrerFromRequest($request, $userId);

        return $referrer?->id;
    }

    protected function handleWalletPayment(Order $order): RedirectResponse
    {
        try {
            DB::transaction(function () use ($order) {
                /** @var User $user */
                $user = User::lockForUpdate()->find($order->user_id);
                $total = (int) $order->total_payment;
                if ((int) $user->balance < $total) {
                    throw new \InvalidArgumentException('Saldo tidak cukup');
                }

                WalletService::debit(
                    user: $user,
                    amount: $total,
                    type: WalletTransaction::TYPE_SPEND,
                    note: 'Bayar order '.$order->order_code,
                    orderId: $order->id,
                );

                $order->forceFill([
                    'payment_method' => Order::GATEWAY_WALLET,
                    'gateway' => Order::GATEWAY_WALLET,
                ])->save();
            });

            app(OrderFulfillment::class)->markPaidAndAssignStock($order, [
                'amount' => (int) $order->total_payment,
                'payment_method' => Order::GATEWAY_WALLET,
                'source' => 'wallet_checkout',
            ]);

            return redirect()
                ->route('invoice.show', $order->order_code)
                ->with('success', 'Pembayaran via saldo berhasil. Akun akan dikirim sebentar lagi.');
        } catch (\Throwable $e) {
            $order->forceFill(['status' => Order::STATUS_FAILED])->save();
            \Log::error('Wallet checkout failed', [
                'order' => $order->order_code,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('invoice.show', $order->order_code)
                ->with('error', 'Pembayaran via saldo gagal: '.$e->getMessage());
        }
    }
}
