<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // SHA-256 hash dari raw API key — raw key hanya pernah ditampilkan
            // sekali saat dibuat (mirip GitHub PAT) supaya kebocoran DB tidak
            // expose key pihak ke-3.
            $table->string('api_key_hash', 64)->unique();
            // Prefix raw key (8 char pertama) untuk identifikasi visual di admin.
            $table->string('api_key_prefix', 16)->nullable();
            $table->boolean('is_active')->default(true);
            // CSV / newline list of allowed IPs (kosong = semua IP boleh).
            $table->text('allowed_ips')->nullable();
            $table->integer('rate_limit_per_minute')->default(60);
            $table->timestamp('last_used_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
