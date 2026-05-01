<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            // Master toggle untuk Pakasir.
            $table->boolean('pakasir_enabled')->default(true)->after('pakasir_base_url');

            // Master toggle Eqris (kompatibel dengan dokumentasi: https://eqris.com/api-docs/).
            $table->boolean('eqris_enabled')->default(false)->after('pakasir_enabled');

            // Default gateway saat user belum pilih (atau hanya 1 yang aktif).
            // Nilai: 'pakasir' atau 'eqris'.
            $table->string('payment_default_gateway', 20)->default('pakasir')->after('eqris_enabled');

            // Eqris credentials (token API + Base QR + Orkut credentials + Gomerch).
            $table->string('eqris_base_url')->default('https://eqris.com')->after('payment_default_gateway');
            $table->text('eqris_token_key')->nullable()->after('eqris_base_url');

            // Orkut subset
            $table->string('eqris_orkut_username')->nullable()->after('eqris_token_key');
            $table->text('eqris_orkut_token')->nullable()->after('eqris_orkut_username');
            $table->text('eqris_orkut_base_qr_string')->nullable()->after('eqris_orkut_token');

            // Gomerch subset
            $table->string('eqris_gomerch_merchant_id')->nullable()->after('eqris_orkut_base_qr_string');
            $table->string('eqris_gomerch_merchant_phone', 32)->nullable()->after('eqris_gomerch_merchant_id');

            // Toggle metode yang ditampilkan ke buyer.
            $table->boolean('eqris_method_orkut_enabled')->default(true)->after('eqris_gomerch_merchant_phone');
            $table->boolean('eqris_method_gomerch_enabled')->default(false)->after('eqris_method_orkut_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};
