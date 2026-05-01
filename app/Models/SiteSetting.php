<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'store_name',
        'tagline',
        'logo_path',
        'brand_color',
        'accent_color',
        'hero_title',
        'hero_subtitle',
        'contact_email',
        'wa_number',
        'wa_default_message',
        'instagram_url',
        'tiktok_url',
        'telegram_url',
        'facebook_url',
        'whatsapp_channel_url',
        'how_to_order_html',
        'terms_html',
        'about_html',
        'footer_about',
        'support_hours',
        'fonnte_api_key',
        'fonnte_auto_send_credentials',
        'fonnte_credentials_template',
        'fonnte_admin_number',
        'fonnte_webhook_secret',
        'pakasir_project',
        'pakasir_api_key',
        'pakasir_qris_only',
        'pakasir_order_expiry_minutes',
        'pakasir_base_url',
        'pakasir_enabled',
        'eqris_enabled',
        'payment_default_gateway',
        'eqris_base_url',
        'eqris_token_key',
        'eqris_orkut_username',
        'eqris_orkut_token',
        'eqris_orkut_base_qr_string',
        'eqris_gomerch_merchant_id',
        'eqris_gomerch_merchant_phone',
        'eqris_method_orkut_enabled',
        'eqris_method_gomerch_enabled',
        'membership_enabled',
        'membership_label',
        'membership_price',
        'membership_duration_days',
        'membership_benefits_html',
        'wallet_checkout_enabled',
        'wallet_checkout_members_only',
        'affiliate_enabled',
        'affiliate_commission_percent',
        'affiliate_min_withdraw',
        'affiliate_cookie_days',
        'affiliate_bank_withdraw_enabled',
        'affiliate_lifetime',
        'public_api_enabled',
        'public_api_docs_html',
        'pakasir_display_name',
        'pakasir_subtitle',
        'eqris_display_name',
        'eqris_subtitle',
        'eqris_orkut_display_name',
        'eqris_orkut_subtitle',
        'eqris_gomerch_display_name',
        'eqris_gomerch_subtitle',
        'wallet_display_name',
        'wallet_subtitle',
        'register_paywall_enabled',
        'register_paywall_price',
        'register_paywall_label',
        'register_paywall_description',
        'wallet_topup_enabled',
        'wallet_topup_min',
        'wallet_topup_max',
        'fake_sold_enabled',
        'floating_notif_enabled',
        'floating_notif_use_real',
        'floating_notif_use_fake',
        'floating_notif_interval_min',
        'floating_notif_interval_max',
        'seo_meta_title',
        'seo_meta_description',
        'seo_meta_keywords',
        'seo_og_image_path',
        'seo_canonical_url',
        'seo_robots',
    ];

    /** Sensitive credentials (Fonnte/Pakasir/Eqris API key) disimpan terenkripsi AES-256-CBC. */
    protected function casts(): array
    {
        return [
            'fonnte_api_key' => 'encrypted',
            'fonnte_auto_send_credentials' => 'boolean',
            'pakasir_api_key' => 'encrypted',
            'pakasir_qris_only' => 'boolean',
            'pakasir_order_expiry_minutes' => 'integer',
            'pakasir_enabled' => 'boolean',
            'eqris_enabled' => 'boolean',
            'eqris_token_key' => 'encrypted',
            'eqris_orkut_token' => 'encrypted',
            'eqris_orkut_base_qr_string' => 'encrypted',
            'eqris_method_orkut_enabled' => 'boolean',
            'eqris_method_gomerch_enabled' => 'boolean',
            'membership_enabled' => 'boolean',
            'membership_price' => 'integer',
            'membership_duration_days' => 'integer',
            'wallet_checkout_enabled' => 'boolean',
            'wallet_checkout_members_only' => 'boolean',
            'affiliate_enabled' => 'boolean',
            'affiliate_commission_percent' => 'decimal:2',
            'affiliate_min_withdraw' => 'integer',
            'affiliate_cookie_days' => 'integer',
            'affiliate_bank_withdraw_enabled' => 'boolean',
            'affiliate_lifetime' => 'boolean',
            'public_api_enabled' => 'boolean',
            'register_paywall_enabled' => 'boolean',
            'register_paywall_price' => 'integer',
            'wallet_topup_enabled' => 'boolean',
            'wallet_topup_min' => 'integer',
            'wallet_topup_max' => 'integer',
            'fake_sold_enabled' => 'boolean',
            'floating_notif_enabled' => 'boolean',
            'floating_notif_use_real' => 'boolean',
            'floating_notif_use_fake' => 'boolean',
            'floating_notif_interval_min' => 'integer',
            'floating_notif_interval_max' => 'integer',
        ];
    }

    /** Cache per-request supaya SiteSetting hanya di-query 1x. */
    protected static ?self $instance = null;

    /** Singleton pattern: ambil row pertama, atau buat default. */
    public static function current(): self
    {
        return self::$instance ??= static::firstOrCreate(['id' => 1], [
            'store_name' => config('app.name', 'Akhpremium Store'),
        ]);
    }

    /** Reset cache (dipanggil otomatis saat row ter-update). */
    public static function clearCache(): void
    {
        self::$instance = null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }

    public function waLink(?string $message = null): ?string
    {
        if (! $this->wa_number) {
            return null;
        }
        $msg = $message ?? $this->wa_default_message ?? 'Halo admin, saya butuh bantuan.';

        return 'https://wa.me/'.$this->wa_number.'?text='.rawurlencode($msg);
    }
}
