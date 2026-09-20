<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('waktu')->useCurrent();
            $table->string('tindakan', 100);
            $table->string('objek_tipe', 100);
            $table->string('objek_id', 100);
            $table->jsonb('nilai_lama')->nullable();
            $table->jsonb('nilai_baru')->nullable();
            $table->text('alasan')->nullable();
            $table->jsonb('dasar_izin')->nullable();
            $table->timestamps();

            $table->index(['objek_tipe', 'objek_id']);
            $table->index('tindakan');
            $table->index('waktu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
