<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Gateway yang dipakai untuk pembayaran. Null untuk order legacy.
            // Nilai: 'pakasir', 'eqris', 'wallet'.
            $table->string('gateway', 20)->nullable()->after('payment_method');

            // Method spesifik di Eqris: 'orkut' atau 'gomerch'. Null kalau bukan eqris.
            $table->string('eqris_method', 20)->nullable()->after('gateway');

            // Untuk Gomerch: transactionId (dari response API).
            $table->string('eqris_transaction_id')->nullable()->after('eqris_method');

            // Nominal final yang dipakai saat generate QR Eqris (nominal di QR
            // bisa ditambah unique-suffix supaya match ke mutasi mudah).
            $table->integer('eqris_qr_amount')->nullable()->after('eqris_transaction_id');

            // Order ini untuk membership subscription (bukan produk biasa).
            $table->boolean('is_member_subscription')->default(false)->after('eqris_qr_amount');

            // Affiliate tracking: siapa referrer-nya saat order ini dibuat.
            $table->foreignId('referral_user_id')
                ->nullable()
                ->after('is_member_subscription')
                ->constrained('users')
                ->nullOnDelete();
            $table->integer('affiliate_amount')->default(0)->after('referral_user_id');
            $table->boolean('affiliate_credited')->default(false)->after('affiliate_amount');

            // Pembayaran via saldo (tidak via gateway eksternal).
            $table->boolean('pay_with_balance')->default(false)->after('affiliate_credited');

            $table->index('gateway');
            $table->index(['referral_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['referral_user_id']);
            $table->dropIndex(['gateway']);
            $table->dropIndex(['referral_user_id', 'created_at']);
            $table->dropColumn([
                'gateway',
                'eqris_method',
                'eqris_transaction_id',
                'eqris_qr_amount',
                'is_member_subscription',
                'referral_user_id',
                'affiliate_amount',
                'affiliate_credited',
                'pay_with_balance',
            ]);
        });
    }
};
