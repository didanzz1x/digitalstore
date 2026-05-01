<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const SOURCE_WEB = 'web';

    public const SOURCE_TELEGRAM = 'telegram';

    public const GATEWAY_PAKASIR = 'pakasir';

    public const GATEWAY_EQRIS = 'eqris';

    public const GATEWAY_WALLET = 'wallet';

    public const EQRIS_METHOD_ORKUT = 'orkut';

    public const EQRIS_METHOD_GOMERCH = 'gomerch';

    protected $fillable = [
        'order_code',
        'user_id',
        'product_id',
        'product_variant_id',
        'stock_id',
        'voucher_id',
        'customer_email',
        'customer_phone',
        'amount',
        'discount_amount',
        'fee',
        'total_payment',
        'payment_method',
        'payment_method_requested',
        'payment_ref',
        'payment_qr_string',
        'telegram_qr_message_id',
        'gateway',
        'eqris_method',
        'eqris_transaction_id',
        'eqris_qr_amount',
        'is_member_subscription',
        'referral_user_id',
        'affiliate_amount',
        'affiliate_credited',
        'pay_with_balance',
        'source',
        'status',
        'paid_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'discount_amount' => 'integer',
            'fee' => 'integer',
            'total_payment' => 'integer',
            'eqris_qr_amount' => 'integer',
            'affiliate_amount' => 'integer',
            'is_member_subscription' => 'boolean',
            'affiliate_credited' => 'boolean',
            'pay_with_balance' => 'boolean',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function referralUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referral_user_id');
    }

    public function memberSubscription(): HasOne
    {
        return $this->hasOne(MemberSubscription::class);
    }

    /**
     * True jika order ini hasil checkout cart (multi-varian dalam 1 transaksi).
     * Order single-item (instant checkout) cukup punya 1 OrderItem atau 0 (legacy).
     */
    public function isCart(): bool
    {
        return $this->items()->count() > 1;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Label readable buat kolom "source" — dipakai di admin/orders & invoice. */
    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_TELEGRAM => 'Telegram',
            self::SOURCE_WEB => 'Web',
            default => ucfirst((string) $this->source),
        };
    }

    /**
     * Generate order_code unik (AKH-YYYYMMDD-XXXXXX). Dipakai oleh checkout
     * web, cart, dan Telegram bot. Retry 5x di charset 36, fallback 10-char
     * supaya gak ada celah unique-constraint 500.
     */
    public static function generateOrderCode(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $code = 'AKH-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
            if (! self::where('order_code', $code)->exists()) {
                return $code;
            }
        }

        do {
            $fallback = 'AKH-'.now()->format('Ymd').'-'.strtoupper(Str::random(10));
        } while (self::where('order_code', $fallback)->exists());

        return $fallback;
    }
}
