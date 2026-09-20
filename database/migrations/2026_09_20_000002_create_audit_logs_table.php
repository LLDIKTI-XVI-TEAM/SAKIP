<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('actor_id')->constrained('users');
            $table->timestamp('waktu');
            $table->string('tindakan', 100);
            $table->string('objek_tipe', 50);
            $table->string('objek_id', 36);
            $table->jsonb('nilai_lama')->nullable();
            $table->jsonb('nilai_baru')->nullable();
            $table->text('alasan')->nullable();
            $table->jsonb('dasar_izin')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
