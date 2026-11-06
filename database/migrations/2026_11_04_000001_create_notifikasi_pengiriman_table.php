<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi_pengiriman', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('kunci_idempoten', 64)->unique();
            $t->string('kejadian');
            $t->enum('objek_tipe', ['rencana_aksi', 'pengukuran']);
            $t->uuid('objek_id');
            $t->string('konteks_periode');
            $t->enum('kanal', ['email', 'whatsapp']);
            $t->foreignUuid('penerima_user_id')->constrained('users')->restrictOnDelete();
            $t->string('alamat')->nullable();
            $t->text('template');
            $t->jsonb('parameter');
            $t->string('driver');
            $t->boolean('simulasi');
            $t->enum('status', ['antre', 'diterima_provider', 'gagal', 'dibatalkan', 'perlu_rekonsiliasi']);
            $t->enum('kode_hasil', ['kanal_nonaktif', 'penerima_tidak_berhak', 'kontak_tidak_tersedia', 'perlu_rekonsiliasi', 'provider_sementara', 'provider_ditolak', 'percobaan_habis'])->nullable();
            $t->unsignedInteger('percobaan')->default(0);
            $t->unsignedInteger('percobaan_siklus')->default(0);
            $t->timestamp('dicoba_terakhir_pada')->nullable();
            $t->timestamp('coba_lagi_pada')->nullable();
            $t->string('rujukan_provider')->nullable();
            $t->timestamp('created_at');
            $t->timestamp('updated_at');
            $t->index(['status', 'dicoba_terakhir_pada']);
        });
        DB::statement('ALTER TABLE notifikasi_pengiriman ADD CONSTRAINT notifikasi_percobaan_nonnegatif CHECK (percobaan >= percobaan_siklus AND percobaan_siklus >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_pengiriman');
    }
};
