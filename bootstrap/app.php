<?php

use App\Http\Middleware\CaptureReferralCookie;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the reverse proxy (devinapps tunnel / production load balancer)
        // sehingga Laravel tahu skema asli (https) & host asli — penting agar
        // asset URL & redirect tidak balik ke http://localhost.
        $middleware->trustProxies(at: '*');

        // Pasang security headers ke SEMUA response web & api.
        $middleware->append(SecurityHeaders::class);

        // Set cookie referral saat ada ?ref=KODE di query string.
        $middleware->web(append: [CaptureReferralCookie::class]);

        // Webhook Pakasir datang dari luar (tidak ada sesi), jadi CSRF harus di-skip
        // khusus untuk path ini — validasi dilakukan via Transaction Detail API.
        $middleware->validateCsrfTokens(except: [
            'webhooks/pakasir',
            'webhooks/fonnte',
            'webhooks/telegram/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
