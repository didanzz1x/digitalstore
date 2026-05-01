<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\FloatingNotificationController;
use App\Http\Controllers\FonnteWebhookController;
use App\Http\Controllers\FrontController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\PakasirWebhookController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TelegramBotController;
use Illuminate\Support\Facades\Route;

// ======== Publik (frontend toko) ========
Route::get('/', [FrontController::class, 'index'])->name('home');
Route::get('/produk/{product}', [FrontController::class, 'show'])->name('products.show');

// Halaman info
Route::get('/cara-pemesanan', [FrontController::class, 'howToOrder'])->name('pages.how-to-order');
Route::get('/faq', [FrontController::class, 'faq'])->name('pages.faq');
Route::get('/ketentuan-order', [FrontController::class, 'terms'])->name('pages.terms');
Route::get('/artikel', [FrontController::class, 'articleIndex'])->name('articles.index');
Route::get('/artikel/{article:slug}', [FrontController::class, 'articleShow'])->name('articles.show');
Route::get('/cek-invoice', [FrontController::class, 'cekInvoice'])->name('pages.cek-invoice');

// Checkout instan — tanpa keranjang.
Route::get('/checkout/{product}/{variant}', [CheckoutController::class, 'show'])
    ->name('checkout.show');

Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:10,1') // maks 10 order/menit per IP
    ->name('checkout.store');

// Halaman invoice publik (akses via order_code random, tidak dapat ditebak).
Route::get('/invoice/{orderCode}', [InvoiceController::class, 'show'])
    ->name('invoice.show');

// Polling status pembayaran via web (untuk halaman invoice). Versi /api/* ada di routes/api.php.
Route::get('/invoice/{orderCode}/check', [InvoiceController::class, 'check'])
    ->middleware('throttle:60,1')
    ->name('invoice.check');

// Membership landing & subscribe.
Route::get('/membership', [MembershipController::class, 'show'])->name('membership.show');
Route::post('/membership/subscribe', [MembershipController::class, 'subscribe'])
    ->middleware(['auth', 'throttle:5,1'])
    ->name('membership.subscribe');

// Halaman docs API publik (free read).
Route::view('/api-docs', 'api-docs')->name('api.docs');

// Webhook dari Pakasir.
Route::post('/webhooks/pakasir', [PakasirWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.pakasir');

// Webhook dari Fonnte (incoming message dari customer).
Route::post('/webhooks/fonnte', [FonnteWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.fonnte');

// Webhook dari Telegram Bot — secret di URL untuk auth.
Route::post('/webhooks/telegram/{secret}', [TelegramBotController::class, 'webhook'])
    ->middleware('throttle:300,1')
    ->name('webhooks.telegram');

// ======== Auth (login / register / lupa password) ========
// Rate limit ketat utk cegah brute-force.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:3,1')
        ->name('register.attempt');

    Route::get('/lupa-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/lupa-password', [AuthController::class, 'sendResetLink'])
        ->middleware('throttle:2,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ======== Akun (dashboard + history) ========
Route::middleware('auth')->prefix('akun')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::get('/orders', [AccountController::class, 'orders'])->name('orders.index');
    Route::get('/profil', [AccountController::class, 'profile'])->name('profile');
    Route::post('/profil', [AccountController::class, 'updateProfile'])->name('profile.update');

    // Review produk untuk order yg sudah PAID
    Route::get('/orders/{orderCode}/review', [ReviewController::class, 'create'])->name('reviews.create');
    Route::post('/orders/{orderCode}/review', [ReviewController::class, 'store'])
        ->middleware('throttle:10,1')->name('reviews.store');

    // Linking Telegram Bot ke akun
    Route::get('/telegram', [TelegramBotController::class, 'showLinkPage'])->name('telegram.show');
    Route::post('/telegram/generate', [TelegramBotController::class, 'generateToken'])->name('telegram.generate');
    Route::post('/telegram/unlink', [TelegramBotController::class, 'unlink'])->name('telegram.unlink');

    // Affiliate dashboard.
    Route::get('/affiliate', [AffiliateController::class, 'dashboard'])->name('affiliate.dashboard');
    Route::post('/affiliate/transfer', [AffiliateController::class, 'transfer'])
        ->middleware('throttle:6,1')->name('affiliate.transfer');
    Route::post('/affiliate/withdraw', [AffiliateController::class, 'withdraw'])
        ->middleware('throttle:6,1')->name('affiliate.withdraw');

    // Self-service wallet top-up.
    Route::get('/topup', [DepositController::class, 'show'])->name('topup.show');
    Route::post('/topup', [DepositController::class, 'store'])
        ->middleware('throttle:6,1')->name('topup.store');
});

// ======== Cart (hanya user login — guest pakai checkout instan) ========
Route::middleware('auth')->prefix('keranjang')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::get('/summary', [CartController::class, 'summary'])->name('summary');
    Route::post('/add', [CartController::class, 'add'])->middleware('throttle:30,1')->name('add');
    Route::post('/checkout', [CartController::class, 'checkoutAll'])
        ->middleware('throttle:10,1')->name('checkout');
    Route::patch('/{item}', [CartController::class, 'update'])->name('update');
    Route::delete('/{item}', [CartController::class, 'destroy'])->name('destroy');
});

// ======== Floating notification feed (publik, JSON) ========
Route::get('/api/floating-notifications', [FloatingNotificationController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('api.floating-notifications');
