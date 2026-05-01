<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_REFUND = 'refund';

    public const TYPE_SPEND = 'spend';

    public const TYPE_ADMIN_TOPUP = 'admin_topup';

    public const TYPE_ADMIN_DEDUCT = 'admin_deduct';

    public const TYPE_AFFILIATE_TRANSFER = 'affiliate_transfer';

    public const TYPE_MEMBERSHIP = 'membership';

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'note',
        'admin_id',
        'order_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_DEPOSIT => 'Deposit',
            self::TYPE_REFUND => 'Refund',
            self::TYPE_SPEND => 'Bayar Pakai Saldo',
            self::TYPE_ADMIN_TOPUP => 'Top-up Admin',
            self::TYPE_ADMIN_DEDUCT => 'Pengurangan Admin',
            self::TYPE_AFFILIATE_TRANSFER => 'Transfer dari Komisi Affiliate',
            self::TYPE_MEMBERSHIP => 'Pembayaran Membership',
            default => $this->type,
        };
    }
}
