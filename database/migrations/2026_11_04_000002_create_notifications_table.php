<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel notifikasi in-app bawaan Laravel. Pemilik hanya `User` (uuid, FK ke `users`),
     * sehingga morph bigint dari stub diganti; `data` jsonb memuat judul, ringkasan, dan route tujuan.
     * Index gabungan melayani hitungan belum dibaca per pemilik pada setiap request.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->foreignUuid('notifiable_id')->constrained('users');
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
