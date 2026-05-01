<?php

namespace App\Services;

use App\Models\MemberSubscription;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk subscription member berbayar.
 */
class MembershipService
{
    public static function isEnabled(): bool
    {
        return (bool) (SiteSetting::current()->membership_enabled ?? false);
    }

    public static function price(): int
    {
        return (int) (SiteSetting::current()->membership_price ?? 0);
    }

    public static function durationDays(): int
    {
        return max(1, (int) (SiteSetting::current()->membership_duration_days ?? 30));
    }

    public static function label(): string
    {
        return (string) (SiteSetting::current()->membership_label ?? 'Member Premium');
    }

    /**
     * Aktifkan membership setelah order PAID (idempotent).
     * Dipanggil dari OrderFulfillment saat order_member_subscription PAID.
     */
    public function activateFromOrder(Order $order): ?MemberSubscription
    {
        if (! $order->is_member_subscription) {
            return null;
        }
        if (! $order->user_id) {
            return null;
        }
        if (! $order->isPaid()) {
            return null;
        }

        return DB::transaction(function () use ($order) {
            $existing = MemberSubscription::where('order_id', $order->id)->first();
            if ($existing) {
                return $existing;
            }

            /** @var User $user */
            $user = User::lockForUpdate()->find($order->user_id);
            if (! $user) {
                return null;
            }

            $duration = self::durationDays();

            // Kalau user sudah aktif, perpanjang dari expires_at sekarang;
            // kalau sudah expired/non-member, mulai dari now().
            $startedAt = now();
            $startsFromExpiry = $user->is_member && $user->member_expires_at && $user->member_expires_at->isFuture();
            $expiresAt = $startsFromExpiry
                ? Carbon::parse($user->member_expires_at)->addDays($duration)
                : Carbon::parse($startedAt)->addDays($duration);

            $sub = MemberSubscription::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'price' => (int) $order->total_payment,
                'duration_days' => $duration,
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'status' => MemberSubscription::STATUS_ACTIVE,
            ]);

            $user->forceFill([
                'is_member' => true,
                'member_expires_at' => $expiresAt,
            ])->save();

            Audit::log('membership.activated', $order, [
                'user_id' => $user->id,
                'expires_at' => $expiresAt->toIso8601String(),
                'subscription_id' => $sub->id,
            ]);

            return $sub;
        });
    }

    /**
     * Buat Order baru untuk subscription membership (single-item virtual).
     * Tidak butuh produk fisik — order ini akan jadi PAID via gateway/wallet
     * sama seperti order biasa.
     */
    public function createSubscriptionOrder(User $user, ?string $gateway, ?string $eqrisMethod = null, bool $payWithBalance = false): Order
    {
        $price = self::price();
        if ($price <= 0) {
            throw new \InvalidArgumentException('Harga membership belum di-set di admin.');
        }

        return DB::transaction(function () use ($user, $price, $gateway, $eqrisMethod, $payWithBalance) {
            return Order::create([
                'order_code' => Order::generateOrderCode(),
                'user_id' => $user->id,
                'product_id' => null,
                'product_variant_id' => null,
                'customer_email' => $user->email,
                'customer_phone' => $user->phone,
                'amount' => $price,
                'discount_amount' => 0,
                'fee' => 0,
                'total_payment' => $price,
                'gateway' => $gateway,
                'eqris_method' => $eqrisMethod,
                'is_member_subscription' => true,
                'pay_with_balance' => $payWithBalance,
                'status' => Order::STATUS_PENDING,
                'source' => Order::SOURCE_WEB,
                'expired_at' => now()->addMinutes(PakasirService::orderExpiryMinutes()),
            ]);
        });
    }

    /**
     * Mark expired memberships sebagai expired di DB (dipanggil scheduler).
     */
    public function expireOverdue(): int
    {
        $now = now();
        $count = 0;

        $expiringSubs = MemberSubscription::where('status', MemberSubscription::STATUS_ACTIVE)
            ->where('expires_at', '<=', $now)
            ->get();

        foreach ($expiringSubs as $sub) {
            $sub->forceFill(['status' => MemberSubscription::STATUS_EXPIRED])->save();
            $count++;
        }

        // Update users yang member-nya sudah expired dan tidak ada sub aktif lain.
        User::where('is_member', true)
            ->where('member_expires_at', '<=', $now)
            ->whereDoesntHave('memberSubscriptions', function ($q) {
                $q->where('status', MemberSubscription::STATUS_ACTIVE);
            })
            ->update(['is_member' => false]);

        return $count;
    }
}
