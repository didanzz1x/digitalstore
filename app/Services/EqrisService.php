<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper untuk eqris.com Payment Gateway.
 *
 * Mendukung 2 mode:
 *  - Orkut: hit /api/qr-orkut (pakai baseQrString) atau /api/qr-orkut-v2 (pakai username+token).
 *  - Gomerch: hit /api/gomerch-transaksi/qris (pakai merchantId+merchantPhone).
 *
 * Eqris TIDAK punya webhook → status pembayaran dipoll dari sisi server
 * (scheduler) dan dari sisi client (AJAX di halaman invoice). Mutasi Orkut
 * di-match by amount; Gomerch di-match by transactionId yang dikembalikan
 * saat generate QR.
 *
 * Referensi: https://eqris.com/api-docs/
 */
class EqrisService
{
    public function __construct(
        protected ?string $tokenKey = null,
        protected ?string $baseUrl = null,
    ) {
        $site = SiteSetting::current();
        $this->tokenKey ??= (string) ($site->eqris_token_key ?: config('eqris.token_key'));
        $this->baseUrl ??= rtrim((string) ($site->eqris_base_url ?: config('eqris.base_url', 'https://eqris.com')), '/');
    }

    public function isConfigured(): bool
    {
        return $this->tokenKey !== '' && $this->baseUrl !== '';
    }

    public function isOrkutConfigured(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }
        $site = SiteSetting::current();

        return ! empty($site->eqris_orkut_base_qr_string)
            || (! empty($site->eqris_orkut_username) && ! empty($site->eqris_orkut_token));
    }

    public function isGomerchConfigured(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }
        $site = SiteSetting::current();

        return ! empty($site->eqris_gomerch_merchant_id) && ! empty($site->eqris_gomerch_merchant_phone);
    }

    public function isMethodAvailable(string $method): bool
    {
        return match ($method) {
            Order::EQRIS_METHOD_ORKUT => $this->isOrkutConfigured(),
            Order::EQRIS_METHOD_GOMERCH => $this->isGomerchConfigured(),
            default => false,
        };
    }

    /**
     * Generate QR Eqris untuk satu Order. Hasil di-cache ke Order.payment_qr_string
     * supaya kita tidak hit API berulang.
     *
     * @return array{qr_string:?string, qris_image:?string, amount:int, transaction_id:?string}|null
     */
    public function createQrForOrder(Order $order): ?array
    {
        $method = $order->eqris_method ?: Order::EQRIS_METHOD_ORKUT;

        // Cache hit: kalau sudah pernah di-generate, return langsung dari Order.
        if (! empty($order->payment_qr_string)) {
            return [
                'qr_string' => $order->payment_qr_string,
                'qris_image' => null,
                'amount' => (int) ($order->eqris_qr_amount ?: $order->total_payment),
                'transaction_id' => $order->eqris_transaction_id,
                'method' => $method,
            ];
        }

        return match ($method) {
            Order::EQRIS_METHOD_ORKUT => $this->createOrkutQr($order),
            Order::EQRIS_METHOD_GOMERCH => $this->createGomerchQr($order),
            default => null,
        };
    }

    protected function createOrkutQr(Order $order): ?array
    {
        $site = SiteSetting::current();

        // Eqris match mutasi by exact amount → tambahkan suffix unique kecil
        // (mod 1000) supaya order paralel tidak bentrok. Suffix simpan ke
        // Order.eqris_qr_amount untuk dipakai di polling.
        $nominal = $this->amountForOrkut($order);

        // Prioritas: kalau ada base_qr_string, pakai /api/qr-orkut. Kalau tidak,
        // jatuh ke /api/qr-orkut-v2 yang butuh username+token.
        if (! empty($site->eqris_orkut_base_qr_string)) {
            $payload = [
                'baseQrString' => $site->eqris_orkut_base_qr_string,
                'nominal' => $nominal,
            ];
            $endpoint = '/api/qr-orkut';
        } elseif (! empty($site->eqris_orkut_username) && ! empty($site->eqris_orkut_token)) {
            $payload = [
                'username_orkut' => $site->eqris_orkut_username,
                'token_orkut' => $site->eqris_orkut_token,
                'nominal' => $nominal,
            ];
            $endpoint = '/api/qr-orkut-v2';
        } else {
            return null;
        }

        $response = $this->post($endpoint, $payload);
        if (! $response || ! ($response['status'] ?? false)) {
            Log::warning('Eqris Orkut QR generation failed', [
                'order_code' => $order->order_code,
                'response' => $response,
            ]);

            return null;
        }

        $qrString = (string) ($response['qrString'] ?? '');
        $qrisImage = (string) ($response['qris'] ?? '');

        $order->forceFill([
            'payment_qr_string' => $qrString,
            'eqris_qr_amount' => $nominal,
            'payment_method_requested' => Order::EQRIS_METHOD_ORKUT,
            'gateway' => Order::GATEWAY_EQRIS,
            'eqris_method' => Order::EQRIS_METHOD_ORKUT,
        ])->save();

        return [
            'qr_string' => $qrString,
            'qris_image' => $qrisImage,
            'amount' => $nominal,
            'transaction_id' => null,
            'method' => Order::EQRIS_METHOD_ORKUT,
        ];
    }

    protected function createGomerchQr(Order $order): ?array
    {
        $site = SiteSetting::current();

        $payload = [
            'merchantId' => (string) $site->eqris_gomerch_merchant_id,
            'merchantPhone' => (string) $site->eqris_gomerch_merchant_phone,
            'amount' => (int) $order->total_payment,
        ];

        $response = $this->post('/api/gomerch-transaksi/qris', $payload);
        if (! $response) {
            return null;
        }

        // Format respons Gomerch tidak sepenuhnya didokumentasikan di Swagger.
        // Coba beberapa path umum: response.data.qris / response.qris / response.qrString.
        $qrString = (string) ($response['qrString'] ?? $response['data']['qrString'] ?? $response['data']['qris_string'] ?? '');
        $qrisImage = (string) ($response['qris'] ?? $response['data']['qris'] ?? $response['data']['qrcode'] ?? '');
        $transactionId = (string) ($response['transactionId'] ?? $response['data']['transactionId'] ?? $response['data']['id'] ?? '');

        if ($qrString === '' && $qrisImage === '') {
            Log::warning('Eqris Gomerch QR response unexpected shape', [
                'order_code' => $order->order_code,
                'response' => $response,
            ]);
        }

        $order->forceFill([
            'payment_qr_string' => $qrString,
            'eqris_qr_amount' => (int) $order->total_payment,
            'eqris_transaction_id' => $transactionId !== '' ? $transactionId : null,
            'payment_method_requested' => Order::EQRIS_METHOD_GOMERCH,
            'gateway' => Order::GATEWAY_EQRIS,
            'eqris_method' => Order::EQRIS_METHOD_GOMERCH,
        ])->save();

        return [
            'qr_string' => $qrString ?: null,
            'qris_image' => $qrisImage ?: null,
            'amount' => (int) $order->total_payment,
            'transaction_id' => $transactionId ?: null,
            'method' => Order::EQRIS_METHOD_GOMERCH,
        ];
    }

    /**
     * Cek apakah order Orkut ini sudah dibayar dengan polling /api/mutasi-orkut-v2
     * dan match by amount + waktu (dalam 60 menit terakhir).
     */
    public function isOrkutPaid(Order $order): bool
    {
        $site = SiteSetting::current();
        if (empty($site->eqris_orkut_username) || empty($site->eqris_orkut_token)) {
            return false;
        }

        $response = $this->post('/api/mutasi-orkut-v2', [
            'username_orkut' => $site->eqris_orkut_username,
            'token_orkut' => $site->eqris_orkut_token,
        ]);

        if (! $response || empty($response['data']) || ! is_array($response['data'])) {
            return false;
        }

        $expectedAmount = (int) ($order->eqris_qr_amount ?: $order->total_payment);
        $orderCreatedAt = $order->created_at?->copy()->subMinutes(5);

        foreach ($response['data'] as $tx) {
            $txAmount = (int) ($tx['amount'] ?? 0);
            $txType = (string) ($tx['type'] ?? '');
            // CR (credit) = uang masuk. Skip yang bukan credit.
            if ($txType !== '' && $txType !== 'CR') {
                continue;
            }
            if ($txAmount !== $expectedAmount) {
                continue;
            }
            // Optional: cek timing kalau date tersedia.
            if ($orderCreatedAt && ! empty($tx['date'])) {
                try {
                    $txTime = \Carbon\Carbon::parse((string) $tx['date']);
                    if ($txTime->isBefore($orderCreatedAt)) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    // Format date mungkin berubah → ignore filter waktu.
                }
            }

            // Match. Simpan reff sebagai payment_ref biar terlacak.
            $reff = (string) ($tx['issuer_reff'] ?? $tx['buyer_reff'] ?? '');
            if ($reff !== '' && empty($order->payment_ref)) {
                $order->forceFill(['payment_ref' => $reff])->save();
            }

            return true;
        }

        return false;
    }

    /**
     * Cek status pembayaran Gomerch via /api/gomerch-transaksi/status
     * berdasarkan transactionId yang disimpan saat generate QR.
     */
    public function isGomerchPaid(Order $order): bool
    {
        if (empty($order->eqris_transaction_id)) {
            return false;
        }
        $site = SiteSetting::current();
        if (empty($site->eqris_gomerch_merchant_id) || empty($site->eqris_gomerch_merchant_phone)) {
            return false;
        }

        $response = $this->post('/api/gomerch-transaksi/status', [
            'merchantId' => (string) $site->eqris_gomerch_merchant_id,
            'merchantPhone' => (string) $site->eqris_gomerch_merchant_phone,
            'transactionId' => (string) $order->eqris_transaction_id,
        ]);

        if (! $response) {
            return false;
        }

        // Cari status field di payload — beberapa kemungkinan dari Gomerch.
        $status = strtolower((string) (
            $response['status']
            ?? $response['data']['status']
            ?? $response['data']['transaction_status']
            ?? ''
        ));

        return in_array($status, ['paid', 'success', 'completed', 'settled', 'success_paid'], true);
    }

    /**
     * Apakah order eqris ini sudah dibayar (tergantung method).
     */
    public function isOrderPaid(Order $order): bool
    {
        return match ($order->eqris_method) {
            Order::EQRIS_METHOD_ORKUT => $this->isOrkutPaid($order),
            Order::EQRIS_METHOD_GOMERCH => $this->isGomerchPaid($order),
            default => false,
        };
    }

    /**
     * Helper: hitung nominal QR untuk Orkut. Tambah suffix unik kecil ke
     * total_payment supaya 2 order paralel dengan harga sama tidak bentrok
     * saat match-mutasi-by-amount. Suffix pakai hash dari order_code mod 100.
     */
    protected function amountForOrkut(Order $order): int
    {
        $base = (int) $order->total_payment;
        if ($base <= 0) {
            return $base;
        }

        // Suffix 0..99 berdasarkan hash order_code → deterministik, tidak ubah
        // nominal user secara dramatis (max +99 rupiah). Skip untuk order
        // membership/wallet yang sudah punya path lain.
        $suffix = abs(crc32($order->order_code)) % 100;

        return $base + $suffix;
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function post(string $path, array $payload): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['tokenKey' => $this->tokenKey])
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl.$path, $payload);
        } catch (\Throwable $e) {
            Log::error('Eqris HTTP error', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Eqris non-success response', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json();
    }
}
