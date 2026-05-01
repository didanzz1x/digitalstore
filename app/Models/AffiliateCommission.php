<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    public const STATUS_CREDITED = 'credited';

    public const STATUS_REVERTED = 'reverted';

    protected $fillable = [
        'user_id',
        'referee_user_id',
        'order_id',
        'percent',
        'amount',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'percent' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
