<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Entitas hulu adalah prasyarat nyata; migrasi tidak membuat jadwal/RA seolah sudah sah.
        Schema::create('renstra_pk', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('renstra_id')->constrained('renstras')->restrictOnDelete();
            $t->integer('tahun');
            $t->string('nomor_pk');
            $t->date('tanggal_pk');
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['renstra_id', 'tahun']);
        });
        Schema::create('periode', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('nama');
            $t->integer('urutan');
            $t->boolean('aktif')->default(true);
            $t->boolean('is_nilai_akhir')->default(false);
        });
        Schema::create('jadwal_tahunan', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('renstra_id')->constrained('renstras')->restrictOnDelete();
            $t->integer('tahun');
            $t->date('rencana_aksi_mulai')->nullable();
            $t->date('rencana_aksi_selesai')->nullable();
            $t->date('penutupan');
            $t->enum('status', ['draft', 'aktif', 'ditutup'])->default('draft');
            $t->foreignUuid('renstra_pk_id')->nullable()->constrained('renstra_pk')->restrictOnDelete();
            $t->timestamp('activated_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamp('koreksi_mulai')->nullable();
            $t->timestamp('koreksi_sampai')->nullable();
            $t->jsonb('lingkup_koreksi')->nullable();
        });
        DB::statement("CREATE UNIQUE INDEX jadwal_tahunan_aktif_unik ON jadwal_tahunan(renstra_id,tahun) WHERE status='aktif'");
        Schema::create('jadwal_periode', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('jadwal_id')->constrained('jadwal_tahunan')->restrictOnDelete();
            $t->foreignUuid('periode_id')->constrained('periode')->restrictOnDelete();
            $t->date('pengisian_mulai');
            $t->date('pengisian_selesai');
            $t->date('reviu_mulai');
            $t->date('reviu_selesai');
            $t->unique(['jadwal_id', 'periode_id']);
        });
        Schema::create('indikator_komponen', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('indikator_id')->constrained('indikator_kinerjas')->restrictOnDelete();
            $t->string('kode');
            $t->text('label');
            $t->enum('peran', ['pembilang', 'penyebut', 'penjumlah']);
            $t->decimal('bobot', 30, 12)->default(1);
            $t->integer('urutan');
            $t->string('satuan')->nullable();
            $t->boolean('aktif')->default(true);
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['indikator_id', 'kode']);
        });
        Schema::create('jadwal_snapshot', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('jadwal_id')->constrained('jadwal_tahunan')->restrictOnDelete();
            $t->foreignUuid('indikator_id')->constrained('indikator_kinerjas')->restrictOnDelete();
            $t->integer('nomor_versi')->default(1);
            $t->uuid('menggantikan_id')->nullable();
            $t->text('alasan_koreksi')->nullable();
            $t->text('rujukan_koreksi')->nullable();
            $t->foreignUuid('periode_mulai_id')->constrained('periode')->restrictOnDelete();
            $t->uuid('unit_id');
            $t->string('nama');
            $t->text('definisi')->nullable();
            $t->string('satuan');
            $t->smallInteger('presisi');
            $t->smallInteger('desimal_tampilan');
            $t->enum('arah', ['naik_baik', 'turun_baik']);
            $t->enum('tipe_perhitungan', ['manual', 'rasio_persen', 'penjumlahan']);
            $t->decimal('target', 30, 12)->nullable();
            $t->decimal('baseline', 30, 12)->nullable();
            $t->unique(['jadwal_id', 'indikator_id', 'nomor_versi']);
        });
        Schema::table('jadwal_snapshot', fn (Blueprint $t) => $t->foreign('menggantikan_id')->references('id')->on('jadwal_snapshot')->restrictOnDelete());
        Schema::create('jadwal_snapshot_komponen', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('jadwal_snapshot_id')->constrained('jadwal_snapshot')->restrictOnDelete();
            $t->foreignUuid('komponen_id')->constrained('indikator_komponen')->restrictOnDelete();
            $t->string('kode');
            $t->text('label');
            $t->enum('peran', ['pembilang', 'penyebut', 'penjumlah']);
            $t->decimal('bobot', 30, 12);
            $t->integer('urutan');
            $t->unique(['jadwal_snapshot_id', 'komponen_id']);
            $t->unique(['jadwal_snapshot_id', 'kode']);
        });
        Schema::create('penanggung_jawab', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('indikator_id')->constrained('indikator_kinerjas')->restrictOnDelete();
            $t->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $t->date('tanggal_mulai_berlaku');
            $t->foreignUuid('ditetapkan_oleh')->constrained('users')->restrictOnDelete();
            $t->text('alasan')->nullable();
            $t->timestamp('created_at');
            $t->index(['indikator_id', 'tanggal_mulai_berlaku']);
        });
        Schema::create('rencana_aksi', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('indikator_id')->constrained('indikator_kinerjas')->restrictOnDelete();
            $t->integer('tahun');
            $t->foreignUuid('unit_id')->constrained('unit')->restrictOnDelete();
            $t->foreignUuid('jadwal_tahunan_id')->constrained('jadwal_tahunan')->restrictOnDelete();
            $t->foreignUuid('jadwal_snapshot_id')->constrained('jadwal_snapshot')->restrictOnDelete();
            $t->foreignUuid('penanggung_jawab_id')->constrained('users')->restrictOnDelete();
            $t->text('uraian')->nullable();
            $t->enum('status_alur', ['draft', 'diajukan', 'diverifikasi', 'dikembalikan', 'disahkan'])->default('draft');
            $t->integer('versi')->default(1);
            $t->text('alasan_revisi')->nullable();
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->timestamp('disahkan_at')->nullable();
            $t->foreignUuid('disahkan_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->unique(['indikator_id', 'tahun']);
        });
        Schema::create('rencana_aksi_target', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('rencana_aksi_id')->constrained('rencana_aksi')->restrictOnDelete();
            $t->foreignUuid('periode_id')->constrained('periode')->restrictOnDelete();
            $t->foreignUuid('komponen_id')->nullable()->constrained('indikator_komponen')->restrictOnDelete();
            $t->decimal('nilai', 30, 12)->nullable();
            $t->text('keterangan')->nullable();
            $t->foreignUuid('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('updated_at');
        });
        DB::statement('CREATE UNIQUE INDEX ra_target_manual_unik ON rencana_aksi_target(rencana_aksi_id,periode_id) WHERE komponen_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX ra_target_komponen_unik ON rencana_aksi_target(rencana_aksi_id,periode_id,komponen_id) WHERE komponen_id IS NOT NULL');
        Schema::create('rencana_aksi_versi', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('rencana_aksi_id')->constrained('rencana_aksi')->restrictOnDelete();
            $t->foreignUuid('jadwal_snapshot_id')->constrained('jadwal_snapshot')->restrictOnDelete();
            $t->integer('nomor');
            $t->foreignUuid('diajukan_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('diajukan_at');
            $t->enum('jalur_pengajuan', ['pic', 'perencanaan']);
            $t->jsonb('dasar_izin_pengajuan');
            $t->jsonb('snapshot');
            $t->foreignUuid('disahkan_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('disahkan_at')->nullable();
            $t->unique(['rencana_aksi_id', 'nomor']);
        });
        Schema::create('jenis_berkas', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('nama');
            $t->enum('tahap', ['rencana_aksi', 'pengukuran', 'kegiatan']);
            $t->foreignUuid('indikator_id')->nullable()->constrained('indikator_kinerjas')->restrictOnDelete();
            $t->boolean('wajib')->default(false);
            $t->text('keterangan')->nullable();
            $t->boolean('izinkan_file')->default(true);
            $t->boolean('izinkan_tautan')->default(false);
            $t->boolean('izinkan_teks')->default(false);
            $t->boolean('semua_mode_wajib')->default(false);
            $t->integer('urutan')->default(0);
            $t->string('format_diizinkan')->nullable();
            $t->integer('ukuran_maks_kb')->nullable();
            $t->boolean('aktif')->default(true);
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('pengaturan', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('kunci')->unique();
            $t->text('nilai')->nullable();
            $t->string('tipe');
            $t->string('grup');
            $t->foreignUuid('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('updated_at', 6)->default(DB::raw('CURRENT_TIMESTAMP'));
        });
    }

    public function down(): void
    {
        foreach (['pengaturan', 'jenis_berkas', 'rencana_aksi_versi', 'rencana_aksi_target', 'rencana_aksi', 'penanggung_jawab', 'jadwal_snapshot_komponen', 'jadwal_snapshot', 'indikator_komponen', 'jadwal_periode', 'jadwal_tahunan', 'periode', 'renstra_pk'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
