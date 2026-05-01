<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\EqrisService;
use App\Services\OrderFulfillment;
use App\Services\PakasirService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        protected PakasirService $pakasir,
        protected EqrisService $eqris,
    ) {}

    /**
     * GET /invoice/{order_code}
     * Halaman invoice publik — URL tidak dapat ditebak (order_code random).
     */
    public function show(Request $request, string $orderCode): View
    {
        $order = Order::with(['product', 'variant', 'stock', 'items.product', 'items.variant', 'items.stock'])
            ->where('order_code', $orderCode)
            ->firstOrFail();

        $this->refreshIfPaid($order);

        // Fetch QR string buat di-render di view (hanya saat masih pending).
        $qris = null;
        $eqrisQr = null;
        if ($order->isPending()) {
            $gateway = $order->gateway ?? Order::GATEWAY_PAKASIR;
            if ($gateway === Order::GATEWAY_PAKASIR && $this->pakasir->isConfigured()) {
                $qris = $this->pakasir->createQrisTransaction($order);
            } elseif ($gateway === Order::GATEWAY_EQRIS && $this->eqris->isConfigured()) {
                $eqrisQr = $this->eqris->createQrForOrder($order);
            }
        }

        return view('invoice', [
            'order' => $order,
            'credentials' => $this->decryptedCredentials($order),
            'itemCredentials' => $this->itemCredentials($order),
            'qris' => $qris,
            'eqrisQr' => $eqrisQr,
        ]);
    }

    /**
     * GET /invoice/{order_code}/check — polling endpoint untuk halaman invoice.
     * Return JSON {paid: bool, status: string}.
     */
    public function check(Request $request, string $orderCode): JsonResponse
    {
        $order = Order::where('order_code', $orderCode)->firstOrFail();
        $this->refreshIfPaid($order);

        return response()->json([
            'paid' => $order->isPaid(),
            'status' => $order->status,
        ]);
    }

    /**
     * On-demand polling status (Pakasir/Eqris) saat halaman invoice di-render
     * atau saat browser polling. Idempotent karena lewat OrderFulfillment.
     */
    protected function refreshIfPaid(Order $order): void
    {
        if (! $order->isPending()) {
            return;
        }
        $gateway = $order->gateway ?? Order::GATEWAY_PAKASIR;

        if ($gateway === Order::GATEWAY_PAKASIR && $this->pakasir->isConfigured()) {
            $detail = $this->pakasir->fetchTransactionDetail(
                $order->order_code,
                (int) $order->total_payment
            );
            if ($detail && ($detail['status'] ?? null) === 'completed') {
                app(OrderFulfillment::class)->markPaidAndAssignStock($order, [
                    'amount' => $detail['amount'] ?? $order->total_payment,
                    'payment_method' => $detail['payment_method'] ?? null,
                    'source' => 'invoice_poll',
                ]);
                $order->refresh();
            }
        } elseif ($gateway === Order::GATEWAY_EQRIS && $this->eqris->isConfigured()) {
            if ($this->eqris->isOrderPaid($order)) {
                app(OrderFulfillment::class)->markPaidAndAssignStock($order, [
                    'amount' => (int) $order->total_payment,
                    'payment_method' => 'eqris_'.($order->eqris_method ?? 'orkut'),
                    'source' => 'invoice_poll_eqris',
                ]);
                $order->refresh();
            }
        }
    }

    /**
     * Ekstrak kredensial akun dari stock yang sudah di-assign.
     * Laravel otomatis men-decrypt karena Stock punya cast 'encrypted'.
     *
     * @return array{email_or_phone:?string,password:?string,additional_info:?string}|null
     */
    protected function decryptedCredentials(Order $order): ?array
    {
        if (! $order->isPaid() || ! $order->stock) {
            return null;
        }

        return [
            'email_or_phone' => $order->stock->email_or_phone,
            'password' => $order->stock->password,
            'additional_info' => $order->stock->additional_info,
        ];
    }

    /**
     * Untuk order multi-item: ekstrak kredensial per OrderItem agar invoice bisa
     * tampilkan SEMUA akun yang sudah ter-assign (termasuk produk + variant
     * masing-masing).
     *
     * @return array<int, array{product:string, variant:string, qty:int, line_total:int, email_or_phone:?string, password:?string, additional_info:?string, delivered:bool}>
     */
    protected function itemCredentials(Order $order): array
    {
        if (! $order->isPaid() || $order->items->isEmpty()) {
            return [];
        }

        return $order->items->map(function ($item) {
            return [
                'product' => $item->product?->name ?? '—',
                'variant' => $item->variant?->name ?? '—',
                'qty' => max(1, (int) $item->qty),
                'line_total' => $item->lineTotal(),
                'email_or_phone' => $item->stock?->email_or_phone,
                'password' => $item->stock?->password,
                'additional_info' => $item->stock?->additional_info,
                'delivered' => $item->stock !== null,
            ];
        })->all();
    }
}
