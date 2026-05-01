<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\MembershipService;
use App\Services\OrderFulfillment;
use App\Services\PaymentGatewayManager;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function __construct(
        protected MembershipService $membership,
        protected PaymentGatewayManager $gateways,
    ) {}

    /**
     * GET /membership — landing page paket member.
     */
    public function show(): View|RedirectResponse
    {
        if (! MembershipService::isEnabled()) {
            return redirect()->route('home')->with('error', 'Membership belum diaktifkan.');
        }

        return view('membership', [
            'site' => SiteSetting::current(),
            'price' => MembershipService::price(),
            'durationDays' => MembershipService::durationDays(),
            'label' => MembershipService::label(),
            'gateways' => $this->gateways->availability(),
            'defaultGateway' => $this->gateways->defaultGateway(),
        ]);
    }

    /**
     * POST /membership/subscribe — buat order subscription + redirect ke invoice.
     */
    public function subscribe(Request $request): RedirectResponse
    {
        if (! MembershipService::isEnabled()) {
            return redirect()->route('home')->with('error', 'Membership belum diaktifkan.');
        }
        if (! Auth::check()) {
            return redirect()->route('login')->with('error', 'Login dulu untuk berlangganan member.');
        }

        $data = $request->validate([
            'gateway' => ['nullable', 'string', 'in:pakasir,eqris,wallet'],
            'eqris_method' => ['nullable', 'string', 'in:orkut,gomerch'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $payWithBalance = ($data['gateway'] ?? null) === Order::GATEWAY_WALLET;
        $resolvedGateway = null;
        $resolvedMethod = null;
        if (! $payWithBalance) {
            $resolved = $this->gateways->resolve($data['gateway'] ?? null, $data['eqris_method'] ?? null);
            if (! $resolved) {
                return back()->withErrors(['gateway' => 'Metode pembayaran tidak tersedia.']);
            }
            [$resolvedGateway, $resolvedMethod] = $resolved;
        } else {
            if ((int) $user->balance < MembershipService::price()) {
                return back()->withErrors(['gateway' => 'Saldo tidak cukup untuk subscribe member.']);
            }
            $resolvedGateway = Order::GATEWAY_WALLET;
        }

        $order = $this->membership->createSubscriptionOrder(
            user: $user,
            gateway: $resolvedGateway,
            eqrisMethod: $resolvedMethod,
            payWithBalance: $payWithBalance,
        );

        if ($payWithBalance) {
            return $this->processWalletPayment($order);
        }

        return redirect()
            ->route('invoice.show', $order->order_code)
            ->with('success', 'Order membership dibuat. Selesaikan pembayaran untuk aktifkan member.');
    }

    protected function processWalletPayment(Order $order): RedirectResponse
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
                    type: WalletTransaction::TYPE_MEMBERSHIP,
                    note: 'Bayar membership '.$order->order_code,
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
                'source' => 'membership_wallet',
            ]);

            return redirect()
                ->route('membership.show')
                ->with('success', 'Pembayaran berhasil. Status member kamu sudah aktif.');
        } catch (\Throwable $e) {
            $order->forceFill(['status' => Order::STATUS_FAILED])->save();
            \Log::error('Membership wallet payment failed', [
                'order' => $order->order_code,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['gateway' => 'Pembayaran via saldo gagal: '.$e->getMessage()]);
        }
    }
}
