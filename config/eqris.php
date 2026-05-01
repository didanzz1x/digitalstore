<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Eqris Payment Gateway
    |--------------------------------------------------------------------------
    | Docs: https://eqris.com/api-docs/
    |
    | Konfigurasi default untuk Eqris (Orkut + Gomerch). Nilai disini hanya
    | dipakai sebagai fallback kalau Site Settings di admin kosong.
    | Disarankan: simpan kredensial via admin → DB encrypted.
    */

    'base_url' => env('EQRIS_BASE_URL', 'https://eqris.com'),

    'token_key' => env('EQRIS_TOKEN_KEY'),

    'orkut' => [
        'username' => env('EQRIS_ORKUT_USERNAME'),
        'token' => env('EQRIS_ORKUT_TOKEN'),
        'base_qr_string' => env('EQRIS_ORKUT_BASE_QR_STRING'),
    ],

    'gomerch' => [
        'merchant_id' => env('EQRIS_GOMERCH_MERCHANT_ID'),
        'merchant_phone' => env('EQRIS_GOMERCH_MERCHANT_PHONE'),
    ],

    /*
    | Polling: Eqris tidak punya webhook, kita match by amount + waktu
    | dari /api/mutasi-orkut-v2. Window dalam menit, default 60.
    */
    'polling_window_minutes' => (int) env('EQRIS_POLLING_WINDOW_MINUTES', 60),

];
