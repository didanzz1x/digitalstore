<?php

namespace App\Services;

use App\Models\AffiliateCommission;
use App\Models\AffiliateWithdrawal;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Audit;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service untuk semua operasi affiliate:
 *  - Tracking referral cookie saat user klik link ?ref=CODE
 *  - Generate referral_code untuk user baru
 *  - Credit komisi saat order PAID
 *  - Withdrawal (transfer ke saldo utama atau request ke bank)
 */
class AffiliateService
{
    public const COOKIE_NAME = 'ds_ref';

    /**
     * Aktif kalau setting affiliate_enabled = true.
     */
    public static function isEnabled(): bool
    {
        return (bool) (SiteSetting::current()->affiliate_enabled ?? false);
    }

    public static function cookieDays(): int
    {
        $days = (int) (SiteSetting::current()->affiliate_cookie_days ?? 30);

        return $days > 0 ? $days : 30;
    }

    /**
     * Build link referral lengkap untuk user (ke homepage).
     */
    public static function referralLinkFor(User $user): ?string
    {
        if (empty($user->referral_code)) {
            return null;
        }

        return rtrim((string) config('app.url'), '/').'/?ref='.$user->referral_code;
    }

    /**
     * Set cookie referral. Dipanggil saat ada query string ?ref=CODE di request.
     * Cookie tidak overwrite kalau sudah ada (first-touch attribution).
     */
    public static function trackCookie(string $code): \Symfony\Component\HttpFoundation\Cookie
    {
        $days = (int) (SiteSetting::current()->affiliate_cookie_days ?? 30);
        $minutes = max(60, $days * 24 * 60);

        return Cookie::make(
            name: self::COOKIE_NAME,
            value: $code,
            minutes: $minutes,
            path: '/',
            domain: null,
            secure: null,
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    /**
     * Resolve referrer dari cookie + (optional) query param. Return null kalau
     * code tidak valid atau pointing ke user sendiri.
     */
    public static function resolveReferrerFromRequest(\Illuminate\Http\Request $request, ?int $excludeUserId = null): ?User
    {
        $code = (string) ($request->query('ref', '') ?: $request->cookie(self::COOKIE_NAME, ''));
        if ($code === '') {
            return null;
        }

        $referrer = User::where('referral_code', $code)->first();
        if (! $referrer) {
            return null;
        }
        if ($excludeUserId && $referrer->id === $excludeUserId) {
            return null;
        }
        if ($referrer->is_banned) {
            return null;
        }

        return $referrer;
    }

    /**
     * Tetapkan referrer untuk user yang baru register. Idempotent:
     * referrer hanya bisa di-set sekali (first-touch dipertahankan).
     */
    public function attachReferrer(User $newUser, User $referrer): void
    {
        if ($newUser->id === $referrer->id) {
            return;
        }
        if ($newUser->referred_by_id) {
            return;
        }

        $newUser->forceFill(['referred_by_id' => $referrer->id])->save();
        Audit::log('affiliate.attached', $referrer, [
            'new_user_id' => $newUser->id,
            'new_user_email' => $newUser->email,
        ]);
    }

    /**
     * Hitung komisi (5% atau settingan) dari amount user-paid.
     */
    public function calculateCommission(int $amount): array
    {
        $site = SiteSetting::current();
        $percent = (float) ($site->affiliate_commission_percent ?: 5.0);
        $commission = (int) floor($amount * $percent / 100);

        return [
            'percent' => $percent,
            'amount' => max(0, $commission),
        ];
    }

    /**
     * Credit komisi affiliate untuk order yang baru jadi PAID. Idempotent:
     *  - Skip kalau settings affiliate_enabled = false.
     *  - Skip kalau order tidak punya referral_user_id.
     *  - Skip kalau order.is_member_subscription = true (jangan komisi membership).
     *  - Skip kalau settings lifetime=false dan referee sudah pernah ada order PAID sebelumnya.
     *  - Skip kalau commission untuk order ini sudah ada di affiliate_commissions.
     */
    public function creditForOrder(Order $order): ?AffiliateCommission
    {
        if (! self::isEnabled()) {
            return null;
        }
        if (! $order->isPaid()) {
            return null;
        }
        if (empty($order->referral_user_id)) {
            return null;
        }
        if ($order->is_member_subscription) {
            return null;
        }
        if ($order->affiliate_credited) {
            return null;
        }

        $site = SiteSetting::current();

        // Lifetime check: kalau bukan lifetime, hanya berlaku untuk order PAID
        // PERTAMA dari referee. Lihat apakah ada order paid lain dari user ini
        // sebelum order $order yang juga punya referral_user_id sama.
        if (! ($site->affiliate_lifetime ?? true) && $order->user_id) {
            $earlierPaid = Order::where('user_id', $order->user_id)
                ->where('status', Order::STATUS_PAID)
                ->where('id', '<', $order->id)
                ->whereNotNull('referral_user_id')
                ->exists();
            if ($earlierPaid) {
                return null;
            }
        }

        $base = (int) ($order->total_payment ?: $order->amount);
        if ($base <= 0) {
            return null;
        }

        $calc = $this->calculateCommission($base);
        if ($calc['amount'] <= 0) {
            return null;
        }

        return DB::transaction(function () use ($order, $calc) {
            // Lock referrer.
            $referrer = User::lockForUpdate()->find($order->referral_user_id);
            if (! $referrer) {
                return null;
            }

            // Idempotent: order_id unique di affiliate_commissions, sehingga
            // double-call akan throw → catch & skip.
            $existing = AffiliateCommission::where('order_id', $order->id)->first();
            if ($existing) {
                return $existing;
            }

            $referrer->forceFill([
                'affiliate_balance' => (int) $referrer->affiliate_balance + $calc['amount'],
            ])->save();

            $commission = AffiliateCommission::create([
                'user_id' => $referrer->id,
                'referee_user_id' => $order->user_id,
                'order_id' => $order->id,
                'percent' => $calc['percent'],
                'amount' => $calc['amount'],
                'status' => AffiliateCommission::STATUS_CREDITED,
                'note' => 'Komisi 5% dari order '.$order->order_code,
            ]);

            $order->forceFill([
                'affiliate_amount' => $calc['amount'],
                'affiliate_credited' => true,
            ])->save();

            Audit::log('affiliate.credited', $order, [
                'referrer_id' => $referrer->id,
                'amount' => $calc['amount'],
                'percent' => $calc['percent'],
            ]);

            return $commission;
        });
    }

    /**
     * Transfer affiliate_balance → balance utama (instant, tidak butuh approval admin).
     * Dipakai user yang ingin pakai komisi untuk belanja di store.
     */
    public function transferToWallet(User $user, int $amount): AffiliateWithdrawal
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount harus > 0');
        }

        return DB::transaction(function () use ($user, $amount) {
            $fresh = User::lockForUpdate()->find($user->id);
            $affBefore = (int) $fresh->affiliate_balance;
            if ($affBefore < $amount) {
                throw new InvalidArgumentException("Saldo affiliate tidak cukup: {$affBefore} < {$amount}");
            }

            $fresh->affiliate_balance = $affBefore - $amount;
            $fresh->save();

            // Credit ke balance utama via WalletService.
            $walletTx = WalletService::credit(
                user: $fresh,
                amount: $amount,
                type: WalletTransaction::TYPE_AFFILIATE_TRANSFER,
                note: 'Transfer dari saldo komisi affiliate',
            );

            $withdrawal = AffiliateWithdrawal::create([
                'user_id' => $fresh->id,
                'amount' => $amount,
                'method' => AffiliateWithdrawal::METHOD_WALLET,
                'status' => AffiliateWithdrawal::STATUS_PAID,
                'processed_at' => now(),
                'note' => 'Transfer otomatis ke saldo utama',
            ]);

            Audit::log('affiliate.transfer_to_wallet', $fresh, [
                'amount' => $amount,
                'wallet_tx_id' => $walletTx->id,
                'withdrawal_id' => $withdrawal->id,
            ]);

            return $withdrawal;
        });
    }

    /**
     * Buat request withdrawal ke bank (status pending, butuh admin approve).
     */
    public function requestBankWithdrawal(User $user, int $amount, array $bank, ?string $note = null): AffiliateWithdrawal
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount harus > 0');
        }
        $minWithdraw = (int) (SiteSetting::current()->affiliate_min_withdraw ?? 0);
        if ($minWithdraw > 0 && $amount < $minWithdraw) {
            throw new InvalidArgumentException("Minimum withdraw Rp {$minWithdraw}");
        }

        return DB::transaction(function () use ($user, $amount, $bank, $note) {
            $fresh = User::lockForUpdate()->find($user->id);
            $affBefore = (int) $fresh->affiliate_balance;
            if ($affBefore < $amount) {
                throw new InvalidArgumentException("Saldo affiliate tidak cukup: {$affBefore} < {$amount}");
            }
            // Saldo affiliate dipotong saat request — kalau admin reject, dikembalikan.
            $fresh->affiliate_balance = $affBefore - $amount;
            $fresh->save();

            $withdrawal = AffiliateWithdrawal::create([
                'user_id' => $fresh->id,
                'amount' => $amount,
                'method' => AffiliateWithdrawal::METHOD_BANK,
                'bank_name' => (string) ($bank['bank_name'] ?? ''),
                'bank_account_no' => (string) ($bank['bank_account_no'] ?? ''),
                'bank_account_name' => (string) ($bank['bank_account_name'] ?? ''),
                'note' => $note,
                'status' => AffiliateWithdrawal::STATUS_PENDING,
            ]);

            Audit::log('affiliate.withdraw_requested', $fresh, [
                'amount' => $amount,
                'withdrawal_id' => $withdrawal->id,
            ]);

            return $withdrawal;
        });
    }

    /**
     * Admin reject withdrawal: kembalikan saldo ke affiliate_balance.
     */
    public function rejectWithdrawal(AffiliateWithdrawal $withdrawal, User $admin, ?string $adminNote = null): AffiliateWithdrawal
    {
        if (! $withdrawal->isPending()) {
            throw new InvalidArgumentException('Withdrawal sudah diproses sebelumnya');
        }

        return DB::transaction(function () use ($withdrawal, $admin, $adminNote) {
            $fresh = User::lockForUpdate()->find($withdrawal->user_id);
            $fresh->affiliate_balance = (int) $fresh->affiliate_balance + (int) $withdrawal->amount;
            $fresh->save();

            $withdrawal->forceFill([
                'status' => AffiliateWithdrawal::STATUS_REJECTED,
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'admin_note' => $adminNote,
            ])->save();

            Audit::log('affiliate.withdraw_rejected', $fresh, [
                'amount' => $withdrawal->amount,
                'withdrawal_id' => $withdrawal->id,
                'admin_id' => $admin->id,
            ]);

            return $withdrawal;
        });
    }

    /**
     * Admin tandai withdrawal sebagai sudah dibayar (tidak refund saldo).
     */
    public function markWithdrawalPaid(AffiliateWithdrawal $withdrawal, User $admin, ?string $adminNote = null): AffiliateWithdrawal
    {
        $withdrawal->forceFill([
            'status' => AffiliateWithdrawal::STATUS_PAID,
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'admin_note' => $adminNote ?? $withdrawal->admin_note,
        ])->save();

        Audit::log('affiliate.withdraw_paid', $withdrawal->user, [
            'amount' => $withdrawal->amount,
            'withdrawal_id' => $withdrawal->id,
            'admin_id' => $admin->id,
        ]);

        return $withdrawal;
    }
}
