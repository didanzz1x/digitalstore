<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            // ─── Paid Membership ────────────────────────────────────────────
            $table->boolean('membership_enabled')->default(false)->after('eqris_method_gomerch_enabled');
            $table->string('membership_label')->default('Member Premium')->after('membership_enabled');
            $table->integer('membership_price')->default(50000)->after('membership_label');
            $table->integer('membership_duration_days')->default(30)->after('membership_price');
            $table->text('membership_benefits_html')->nullable()->after('membership_duration_days');

            // ─── Wallet Checkout (fee-free khusus member) ───────────────────
            $table->boolean('wallet_checkout_enabled')->default(true)->after('membership_benefits_html');
            $table->boolean('wallet_checkout_members_only')->default(true)->after('wallet_checkout_enabled');

            // ─── Affiliate ──────────────────────────────────────────────────
            $table->boolean('affiliate_enabled')->default(false)->after('wallet_checkout_members_only');
            $table->decimal('affiliate_commission_percent', 5, 2)->default(5.00)->after('affiliate_enabled');
            $table->integer('affiliate_min_withdraw')->default(50000)->after('affiliate_commission_percent');
            $table->integer('affiliate_cookie_days')->default(30)->after('affiliate_min_withdraw');
            // Boleh withdraw uang ke bank (admin approve) — kalau false, hanya boleh transfer ke saldo utama.
            $table->boolean('affiliate_bank_withdraw_enabled')->default(true)->after('affiliate_cookie_days');
            // Komisi seumur hidup (true) atau hanya pembelian pertama (false).
            $table->boolean('affiliate_lifetime')->default(true)->after('affiliate_bank_withdraw_enabled');

            // ─── Public API (untuk integrasi pihak ke-3) ────────────────────
            $table->boolean('public_api_enabled')->default(false)->after('affiliate_lifetime');
            $table->text('public_api_docs_html')->nullable()->after('public_api_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};
