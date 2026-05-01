<?php

namespace App\Http\Controllers;

use App\Models\AffiliateCommission;
use App\Models\AffiliateWithdrawal;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AffiliateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    public function __construct(protected AffiliateService $affiliate) {}

    /**
     * GET /akun/affiliate — dashboard affiliate untuk user login.
     */
    public function dashboard(): View|RedirectResponse
    {
        if (! AffiliateService::isEnabled()) {
            return redirect()->route('account.index')
                ->with('error', 'Program affiliate belum diaktifkan.');
        }

        /** @var User $user */
        $user = Auth::user();
        $user->ensureReferralCode();

        $referrals = User::where('referred_by_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'name', 'email', 'created_at']);

        $commissions = AffiliateCommission::where('user_id', $user->id)
            ->with(['order:id,order_code,total_payment,status,paid_at', 'referee:id,name,email'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $withdrawals = AffiliateWithdrawal::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $totalEarned = (int) AffiliateCommission::where('user_id', $user->id)
            ->where('status', AffiliateCommission::STATUS_CREDITED)
            ->sum('amount');

        $site = SiteSetting::current();

        return view('account.affiliate', [
            'user' => $user,
            'referralLink' => AffiliateService::referralLinkFor($user),
            'referrals' => $referrals,
            'commissions' => $commissions,
            'withdrawals' => $withdrawals,
            'totalEarned' => $totalEarned,
            'site' => $site,
            'percent' => (float) ($site->affiliate_commission_percent ?: 5.0),
            'minWithdraw' => (int) ($site->affiliate_min_withdraw ?: 0),
            'bankWithdrawEnabled' => (bool) ($site->affiliate_bank_withdraw_enabled ?? true),
        ]);
    }

    /**
     * POST /akun/affiliate/transfer — transfer affiliate_balance → balance utama.
     */
    public function transfer(Request $request): RedirectResponse
    {
        if (! AffiliateService::isEnabled()) {
            return redirect()->route('account.index')->with('error', 'Program affiliate belum diaktifkan.');
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        try {
            $this->affiliate->transferToWallet($user, (int) $data['amount']);

            return back()->with('success', 'Berhasil transfer Rp '.number_format((int) $data['amount'], 0, ',', '.').' ke saldo utama.');
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }
    }

    /**
     * POST /akun/affiliate/withdraw — request bank withdrawal.
     */
    public function withdraw(Request $request): RedirectResponse
    {
        if (! AffiliateService::isEnabled()) {
            return redirect()->route('account.index')->with('error', 'Program affiliate belum diaktifkan.');
        }
        $site = SiteSetting::current();
        if (! ($site->affiliate_bank_withdraw_enabled ?? true)) {
            return back()->with('error', 'Withdraw via bank belum diaktifkan oleh admin.');
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'bank_name' => ['required', 'string', 'max:64'],
            'bank_account_no' => ['required', 'string', 'max:32'],
            'bank_account_name' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        try {
            $this->affiliate->requestBankWithdrawal($user, (int) $data['amount'], [
                'bank_name' => $data['bank_name'],
                'bank_account_no' => $data['bank_account_no'],
                'bank_account_name' => $data['bank_account_name'],
            ], $data['note'] ?? null);

            return back()->with('success', 'Permintaan withdraw dibuat. Admin akan memproses dalam 1×24 jam.');
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }
    }
}
