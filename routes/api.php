<?php

use App\Http\Controllers\Api\PublicProductController;
use App\Http\Controllers\InvoiceController;
use App\Http\Middleware\AuthenticateApiClient;
use Illuminate\Support\Facades\Route;

// ===== Public REST API (X-API-KEY) =====
Route::middleware([AuthenticateApiClient::class, 'throttle:120,1'])
    ->prefix('v1')
    ->group(function () {
        Route::get('/products', [PublicProductController::class, 'index']);
        Route::get('/products/{id}', [PublicProductController::class, 'show'])->whereNumber('id');
        Route::get('/categories', [PublicProductController::class, 'categories']);
    });

// ===== Invoice payment polling (no auth, but throttled & order_code is unguessable) =====
Route::get('/invoice/{orderCode}/check', [InvoiceController::class, 'check'])
    ->middleware('throttle:60,1')
    ->name('api.invoice.check');
