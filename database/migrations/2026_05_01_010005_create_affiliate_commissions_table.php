<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            // Referrer (yang dapat komisi).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Referee (user yang melakukan transaksi → memicu komisi). Bisa null untuk guest.
            $table->foreignId('referee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // Persen komisi saat dihitung (di-snapshot supaya history tidak berubah kalau admin
            // mengubah persentase di kemudian hari).
            $table->decimal('percent', 5, 2)->default(5.00);
            $table->integer('amount');
            $table->string('status', 20)->default('credited'); // credited | reverted
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
            $table->unique(['order_id']); // Hindari double-credit untuk satu order.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
};
