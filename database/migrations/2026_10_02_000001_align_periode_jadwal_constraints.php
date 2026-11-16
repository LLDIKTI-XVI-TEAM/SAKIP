<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rollout berhenti pada data tidak konsisten; tidak memilih atau menormalisasi riwayat otomatis.
        if (DB::table('periode')->exists() && DB::table('periode')->where('aktif', true)->where('is_nilai_akhir', true)->count() !== 1) {
            throw new RuntimeException('Konfigurasi periode existing harus memiliki tepat satu final aktif sebelum migrasi.');
        }
        if (DB::table('jadwal_tahunan')->select('renstra_id', 'tahun')->groupBy('renstra_id', 'tahun')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Pasangan Renstra dan tahun duplikat harus diselesaikan sebelum migrasi.');
        }
        if (DB::table('jadwal_periode')->join('periode', 'periode.id', '=', 'jadwal_periode.periode_id')
            ->select('jadwal_id', 'urutan')->groupBy('jadwal_id', 'urutan')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Urutan periode pilihan duplikat harus diselesaikan sebelum migrasi.');
        }
        Schema::table('periode', fn (Blueprint $table) => $table->integer('revisi')->default(1));
        Schema::table('jadwal_tahunan', function (Blueprint $table): void {
            $table->integer('revisi')->default(1);
            $table->boolean('pakai_persetujuan_pimpinan')->default(false);
            $table->date('persetujuan_mulai')->nullable();
            $table->date('persetujuan_selesai')->nullable();
            $table->unique(['renstra_id', 'tahun'], 'jadwal_tahunan_renstra_tahun_unik');
        });
        DB::statement('DROP INDEX jadwal_tahunan_aktif_unik');
        DB::statement('CREATE UNIQUE INDEX periode_final_aktif_unik ON periode (is_nilai_akhir) WHERE aktif AND is_nilai_akhir');
        DB::statement('ALTER TABLE jadwal_periode ADD CONSTRAINT jadwal_periode_urutan_tanggal CHECK (pengisian_mulai <= pengisian_selesai AND pengisian_selesai <= reviu_mulai AND reviu_mulai <= reviu_selesai)');
        DB::statement('ALTER TABLE jadwal_tahunan ADD CONSTRAINT jadwal_tahunan_urutan_ra CHECK (rencana_aksi_mulai IS NULL OR rencana_aksi_selesai IS NULL OR rencana_aksi_mulai <= rencana_aksi_selesai)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jadwal_periode DROP CONSTRAINT jadwal_periode_urutan_tanggal');
        DB::statement('ALTER TABLE jadwal_tahunan DROP CONSTRAINT jadwal_tahunan_urutan_ra');
        DB::statement('DROP INDEX periode_final_aktif_unik');
        DB::statement("CREATE UNIQUE INDEX jadwal_tahunan_aktif_unik ON jadwal_tahunan (renstra_id, tahun) WHERE status = 'aktif'");
        Schema::table('jadwal_tahunan', function (Blueprint $table): void {
            $table->dropUnique('jadwal_tahunan_renstra_tahun_unik');
            $table->dropColumn(['revisi', 'pakai_persetujuan_pimpinan', 'persetujuan_mulai', 'persetujuan_selesai']);
        });
        Schema::table('periode', fn (Blueprint $table) => $table->dropColumn('revisi'));
    }
};
