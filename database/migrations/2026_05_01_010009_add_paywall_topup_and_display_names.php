<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('pakasir_display_name')->nullable();
            $table->string('pakasir_subtitle')->nullable();
            $table->string('eqris_display_name')->nullable();
            $table->string('eqris_subtitle')->nullable();
            $table->string('eqris_orkut_display_name')->nullable();
            $table->string('eqris_orkut_subtitle')->nullable();
            $table->string('eqris_gomerch_display_name')->nullable();
            $table->string('eqris_gomerch_subtitle')->nullable();
            $table->string('wallet_display_name')->nullable();
            $table->string('wallet_subtitle')->nullable();

            $table->boolean('register_paywall_enabled')->default(false);
            $table->integer('register_paywall_price')->default(0);
            $table->string('register_paywall_label')->nullable();
            $table->text('register_paywall_description')->nullable();

            $table->boolean('wallet_topup_enabled')->default(true);
            $table->integer('wallet_topup_min')->default(10000);
            $table->integer('wallet_topup_max')->default(5000000);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_wallet_topup')->default(false)->after('is_member_subscription');
            $table->boolean('is_register_activation')->default(false)->after('is_wallet_topup');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_pending_activation')->default(false)->after('is_member');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_pending_activation');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['is_wallet_topup', 'is_register_activation']);
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};
