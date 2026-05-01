<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SiteSetting;

/**
 * Memutuskan gateway dan metode pembayaran mana yang aktif berdasarkan
 * SiteSetting + capability service masing-masing. Dipakai oleh checkout
 * untuk render pilihan ke buyer dan validasi server-side.
 */
class PaymentGatewayManager
{
    public function __construct(
        protected PakasirService $pakasir,
        protected EqrisService $eqris,
    ) {}

    /**
     * Availability per gateway plus display metadata yang dipakai blade UI.
     *
     * @return array<string, array<string, mixed>>
     */
    public function availability(): array
    {
        $site = SiteSetting::current();

        return [
            Order::GATEWAY_PAKASIR => [
                'enabled' => (bool) ($site->pakasir_enabled ?? true) && $this->pakasir->isConfigured(),
                'configured' => $this->pakasir->isConfigured(),
                'label' => $site->pakasir_display_name ?: 'Pakasir',
                'subtitle' => $site->pakasir_subtitle ?: 'QRIS / VA / E-Wallet',
                'methods' => [
                    'qris' => ['enabled' => true, 'label' => 'QRIS'],
                    'va' => ['enabled' => ! ($site->pakasir_qris_only ?? false), 'label' => 'Virtual Account'],
                    'ewallet' => ['enabled' => ! ($site->pakasir_qris_only ?? false), 'label' => 'E-Wallet'],
                ],
                'qris_only' => (bool) ($site->pakasir_qris_only ?? false),
            ],
            Order::GATEWAY_EQRIS => [
                'enabled' => (bool) ($site->eqris_enabled ?? false) && $this->eqris->isConfigured(),
                'configured' => $this->eqris->isConfigured(),
                'label' => $site->eqris_display_name ?: 'Eqris',
                'subtitle' => $site->eqris_subtitle ?: 'QRIS Multi-Bank',
                'methods' => [
                    Order::EQRIS_METHOD_ORKUT => [
                        'enabled' => (bool) ($site->eqris_method_orkut_enabled ?? true) && $this->eqris->isOrkutConfigured(),
                        'label' => $site->eqris_orkut_display_name ?: 'Orkut (NobuBank)',
                        'subtitle' => $site->eqris_orkut_subtitle ?: 'QRIS via NobuBank',
                    ],
                    Order::EQRIS_METHOD_GOMERCH => [
                        'enabled' => (bool) ($site->eqris_method_gomerch_enabled ?? false) && $this->eqris->isGomerchConfigured(),
                        'label' => $site->eqris_gomerch_display_name ?: 'Gomerch (Multi-Bank)',
                        'subtitle' => $site->eqris_gomerch_subtitle ?: 'QRIS Multi-Bank dinamis',
                    ],
                ],
            ],
            Order::GATEWAY_WALLET => [
                'enabled' => (bool) ($site->wallet_checkout_enabled ?? true),
                'label' => $site->wallet_display_name ?: 'Saldo Akun',
                'subtitle' => $site->wallet_subtitle ?: 'Bebas fee — pakai saldo deposit',
                'members_only' => (bool) ($site->wallet_checkout_members_only ?? true),
            ],
        ];
    }

    /**
     * Daftar method Eqris yang aktif (untuk UI dropdown).
     *
     * @return array<int, array{code:string,label:string,subtitle:string}>
     */
    public function activeEqrisMethods(): array
    {
        $methods = $this->availability()[Order::GATEWAY_EQRIS]['methods'] ?? [];
        $out = [];
        foreach ($methods as $code => $meta) {
            if (! empty($meta['enabled'])) {
                $out[] = [
                    'code' => $code,
                    'label' => $meta['label'],
                    'subtitle' => $meta['subtitle'] ?? '',
                ];
            }
        }

        return $out;
    }

    /**
     * Gateway aktif yang BISA dipilih buyer (subset dari availability yang enabled).
     *
     * @return array<int, string>
     */
    public function activeGateways(): array
    {
        $a = $this->availability();
        $out = [];
        if ($a[Order::GATEWAY_PAKASIR]['enabled']) {
            $out[] = Order::GATEWAY_PAKASIR;
        }
        if ($a[Order::GATEWAY_EQRIS]['enabled']) {
            $out[] = Order::GATEWAY_EQRIS;
        }

        return $out;
    }

    /**
     * Pilih gateway default. Prioritas: setting `payment_default_gateway`,
     * fallback ke gateway aktif pertama.
     */
    public function defaultGateway(): ?string
    {
        $site = SiteSetting::current();
        $default = (string) ($site->payment_default_gateway ?: Order::GATEWAY_PAKASIR);
        $active = $this->activeGateways();
        if (in_array($default, $active, true)) {
            return $default;
        }

        return $active[0] ?? null;
    }

    /**
     * Validasi pilihan buyer. Return [gateway, method] yang valid, atau null
     * kalau tidak valid → caller bebas redirect dengan error.
     *
     * @return array{0:string, 1:?string}|null
     */
    public function resolve(?string $requestedGateway, ?string $requestedMethod = null): ?array
    {
        $a = $this->availability();
        $gateway = $requestedGateway ?: $this->defaultGateway();
        if (! $gateway) {
            return null;
        }

        if ($gateway === Order::GATEWAY_PAKASIR) {
            if (! $a[Order::GATEWAY_PAKASIR]['enabled']) {
                return null;
            }

            return [Order::GATEWAY_PAKASIR, null];
        }

        if ($gateway === Order::GATEWAY_EQRIS) {
            if (! $a[Order::GATEWAY_EQRIS]['enabled']) {
                return null;
            }
            $methods = $a[Order::GATEWAY_EQRIS]['methods'];
            $method = $requestedMethod;
            if (! $method || empty($methods[$method]['enabled'])) {
                // Pilih method aktif pertama.
                foreach ($methods as $m => $meta) {
                    if (! empty($meta['enabled'])) {
                        $method = $m;
                        break;
                    }
                }
            }
            if (! $method) {
                return null;
            }

            return [Order::GATEWAY_EQRIS, $method];
        }

        return null;
    }
}
