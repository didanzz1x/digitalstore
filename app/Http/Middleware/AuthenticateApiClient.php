<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth public API via header X-API-KEY. Hit ApiClient::findByRawKey untuk
 * lookup hash + verifikasi. Validasi IP allowlist & rate limit per-client.
 */
class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $site = SiteSetting::current();
        if (! ($site->public_api_enabled ?? false)) {
            return $this->error('Public API tidak diaktifkan', 503);
        }

        $rawKey = (string) ($request->header('X-API-KEY')
            ?: $request->header('X-Api-Key')
            ?: $request->bearerToken()
            ?? '');

        if ($rawKey === '') {
            return $this->error('API key required (header X-API-KEY)', 401);
        }

        $client = ApiClient::findByRawKey($rawKey);
        if (! $client || ! $client->is_active) {
            return $this->error('API key tidak valid atau dinonaktifkan', 401);
        }

        if (! $client->isIpAllowed($request->ip())) {
            return $this->error('IP tidak diijinkan untuk API key ini', 403);
        }

        // Throttle per-client: gunakan rate_limit_per_minute kalau di-set,
        // fallback ke 60/menit. Pakai cache key per-client + minute window.
        $rate = (int) ($client->rate_limit_per_minute ?: 60);
        $key = 'api_client:'.$client->id.':'.now()->format('YmdHi');
        $count = (int) cache()->get($key, 0);
        if ($count >= $rate) {
            return $this->error('Rate limit terlampaui ('.$rate.'/menit)', 429);
        }
        cache()->put($key, $count + 1, 70);

        $client->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('api_client', $client);

        return $next($request);
    }

    protected function error(string $message, int $status): JsonResponse
    {
        return response()->json(['error' => $message], $status);
    }
}
