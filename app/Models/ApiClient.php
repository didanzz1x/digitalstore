<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    protected $fillable = [
        'name',
        'api_key_hash',
        'api_key_prefix',
        'is_active',
        'allowed_ips',
        'rate_limit_per_minute',
        'last_used_at',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rate_limit_per_minute' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Generate raw API key + hash. Raw key hanya pernah dilihat sekali (saat dibuat),
     * lalu hanya hash yang disimpan ke DB.
     *
     * @return array{raw:string, hash:string, prefix:string}
     */
    public static function generateKey(): array
    {
        $raw = 'ak_'.Str::random(40);

        return [
            'raw' => $raw,
            'hash' => hash('sha256', $raw),
            'prefix' => substr($raw, 0, 8).'…',
        ];
    }

    /**
     * Cari ApiClient aktif berdasarkan raw API key dari header request.
     */
    public static function findByRawKey(string $rawKey): ?self
    {
        if ($rawKey === '') {
            return null;
        }

        $hash = hash('sha256', $rawKey);

        return self::where('api_key_hash', $hash)->where('is_active', true)->first();
    }

    /**
     * @return array<int, string>
     */
    public function allowedIpList(): array
    {
        if (empty($this->allowed_ips)) {
            return [];
        }
        $ips = preg_split('/[\s,]+/', (string) $this->allowed_ips, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($ips));
    }

    public function isIpAllowed(string $ip): bool
    {
        $allowed = $this->allowedIpList();
        if (empty($allowed)) {
            return true;
        }

        return in_array($ip, $allowed, true);
    }
}
