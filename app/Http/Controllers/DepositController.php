<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\PakasirService;
use App\Services\PaymentGatewayManager;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Self-service wallet top-up. User logged-in pilih nominal + gateway,
 * sistem buat Order dengan flag `is_wallet_topup=true`. Saat order PAID,
 * OrderFulfillment::markPaidAndAssignStock akan kredit saldo via WalletService.
 */
class DepositController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
    ) {}

    /** GET /akun/topup — form pilih nominal + gateway. */
    public function show(): View|RedirectResponse
    {
        $site = SiteSetting::current();
        if (! ($site->wallet_topup_enabled ?? true)) {
            return redirect()->route('account.index')->with('error', 'Top-up saldo belum diaktifkan.');
        }

        return view('account.topup', [
            'site' => $site,
            'gateways' => $this->gateways->availability(),
            'defaultGateway' => $this->gateways->defaultGateway(),
            'min' => (int) ($site->wallet_topup_min ?? 10000),
            'max' => (int) ($site->wallet_topup_max ?? 5000000),
        ]);
    }

    /** POST /akun/topup — buat Order top-up + redirect ke invoice. */
    public function store(Request $request): RedirectResponse
    {
        $site = SiteSetting::current();
        if (! ($site->wallet_topup_enabled ?? true)) {
            return redirect()->route('account.index')->with('error', 'Top-up saldo belum diaktifkan.');
        }

        $min = (int) ($site->wallet_topup_min ?? 10000);
        $max = (int) ($site->wallet_topup_max ?? 5000000);

        $data = $request->validate([
            'amount' => ['required', 'integer', "min:{$min}", "max:{$max}"],
            'gateway' => ['required', 'string', 'in:pakasir,eqris'],
            'eqris_method' => ['nullable', 'string', 'in:orkut,gomerch'],
        ]);

        $resolved = $this->gateways->resolve($data['gateway'], $data['eqris_method'] ?? null);
        if (! $resolved) {
            return back()->withInput()->withErrors([
                'gateway' => 'Metode pembayaran tidak tersedia. Silakan pilih ulang.',
            ]);
        }
        [$resolvedGateway, $resolvedMethod] = $resolved;

        /** @var User $user */
        $user = Auth::user();

        $order = DB::transaction(function () use ($user, $data, $resolvedGateway, $resolvedMethod) {
            return Order::create([
                'order_code' => Order::generateOrderCode(),
                'user_id' => $user->id,
                'product_id' => null,
                'product_variant_id' => null,
                'customer_email' => $user->email,
                'customer_phone' => $user->phone,
                'amount' => (int) $data['amount'],
                'discount_amount' => 0,
                'fee' => 0,
                'total_payment' => (int) $data['amount'],
                'gateway' => $resolvedGateway,
                'eqris_method' => $resolvedMethod,
                'is_wallet_topup' => true,
                'pay_with_balance' => false,
                'status' => Order::STATUS_PENDING,
                'source' => Order::SOURCE_WEB,
                'expired_at' => now()->addMinutes(PakasirService::orderExpiryMinutes()),
            ]);
        });

        Audit::log('wallet.topup_initiated', $order, [
            'amount' => (int) $data['amount'],
            'gateway' => $resolvedGateway,
        ]);

        return redirect()
            ->route('invoice.show', $order->order_code)
            ->with('success', 'Order top-up dibuat. Selesaikan pembayaran — saldo akan otomatis bertambah.');
    }
}
