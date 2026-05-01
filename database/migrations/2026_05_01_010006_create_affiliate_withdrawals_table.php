<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('amount');
            // method: 'wallet' (transfer ke saldo utama, instant) atau 'bank' (manual disburse).
            $table->string('method', 20);
            // Detail bank (untuk method=bank).
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no', 64)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->text('note')->nullable();
            // Status: pending | approved | rejected | paid
            $table->string('status', 20)->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_withdrawals');
    }
};
