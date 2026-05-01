<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Kode referral unik per user — di-generate auto saat register / via command backfill.
            $table->string('referral_code', 16)->nullable()->unique()->after('balance');

            // Siapa yang me-refer user ini (untuk komisi affiliate).
            $table->foreignId('referred_by_id')
                ->nullable()
                ->after('referral_code')
                ->constrained('users')
                ->nullOnDelete();

            // Saldo komisi affiliate (DIPISAH dari saldo utama supaya bisa di-withdraw terpisah
            // atau di-transfer ke saldo utama secara eksplisit).
            $table->integer('affiliate_balance')->default(0)->after('referred_by_id');

            // Member berbayar — aktif sampai expires_at, atau forever kalau null.
            $table->boolean('is_member')->default(false)->after('affiliate_balance');
            $table->timestamp('member_expires_at')->nullable()->after('is_member');

            $table->index('referred_by_id');
            $table->index(['is_member', 'member_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by_id']);
            $table->dropIndex(['referred_by_id']);
            $table->dropIndex(['is_member', 'member_expires_at']);
            $table->dropColumn([
                'referral_code',
                'referred_by_id',
                'affiliate_balance',
                'is_member',
                'member_expires_at',
            ]);
        });
    }
};
