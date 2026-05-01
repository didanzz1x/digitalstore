<?php

namespace App\Http\Middleware;

use App\Services\AffiliateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set cookie referral (ds_ref) saat ada query string ?ref=KODE pada request masuk.
 * Cookie ini digunakan saat user daftar / checkout untuk attribution affiliate.
 */
class CaptureReferralCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! AffiliateService::isEnabled()) {
            return $response;
        }

        $code = $request->query('ref');
        if (is_string($code) && strlen($code) >= 4 && strlen($code) <= 32) {
            $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', $code) ?? '');
            if ($code !== '') {
                $days = AffiliateService::cookieDays();
                $cookie = Cookie::create(
                    name: AffiliateService::COOKIE_NAME,
                    value: $code,
                    expire: now()->addDays($days)->getTimestamp(),
                    path: '/',
                    secure: $request->isSecure(),
                    httpOnly: false,
                    sameSite: Cookie::SAMESITE_LAX,
                );
                $response->headers->setCookie($cookie);
            }
        }

        return $response;
    }
}
