<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateWithdrawal extends Model
{
    public const METHOD_WALLET = 'wallet';

    public const METHOD_BANK = 'bank';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'user_id',
        'amount',
        'method',
        'bank_name',
        'bank_account_no',
        'bank_account_name',
        'note',
        'status',
        'processed_by',
        'processed_at',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Menunggu Proses',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_PAID => 'Sudah Dibayar',
            default => $this->status,
        };
    }
}
