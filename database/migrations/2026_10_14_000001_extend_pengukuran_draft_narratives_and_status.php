<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Status eksplisit "belum dapat diukur" dan empat narasi draft pengukuran. CHECK lama diganti, bukan migration enum
 * awal yang diubah; data existing tidak dikonversi.
 */
return new class extends Migration
{
    private const NARASI = ['progres_kegiatan', 'kendala_masalah', 'strategi_tindak_lanjut', 'tindak_lanjut_rekomendasi'];

    public function up(): void
    {
        // Kolom ditulis eksplisit agar terbaca analisis skema (Larastan), bukan hanya runtime.
        Schema::table('pengukuran_kinerjas', function ($table): void {
            $table->text('progres_kegiatan')->nullable();
            $table->text('kendala_masalah')->nullable();
            $table->text('strategi_tindak_lanjut')->nullable();
            $table->text('tindak_lanjut_rekomendasi')->nullable();
        });
        $this->replaceChecks(['belum_diisi', 'terhitung', 'tidak_dapat_dihitung', 'belum_dapat_diukur'], ['belum_diisi', 'tidak_dapat_dihitung', 'belum_dapat_diukur']);
    }

    public function down(): void
    {
        // Downgrade tidak boleh menghapus narasi/status baru diam-diam atau menurunkannya menjadi belum_diisi.
        $used = DB::table('pengukuran_kinerjas')->where('status_perhitungan', 'belum_dapat_diukur')
            ->orWhereRaw('COALESCE('.implode(', ', self::NARASI).') IS NOT NULL')->exists();
        if ($used) {
            throw new RuntimeException('Rollback ditolak: status belum_dapat_diukur atau narasi pengukuran masih terpakai.');
        }
        $this->replaceChecks(['belum_diisi', 'terhitung', 'tidak_dapat_dihitung'], ['belum_diisi', 'tidak_dapat_dihitung']);
        Schema::table('pengukuran_kinerjas', fn ($table) => $table->dropColumn(self::NARASI));
    }

    /** Nama CHECK enum dibuat Laravel saat create table; nilai_status_cocok dari migration pengukuran awal. */
    private function replaceChecks(array $statuses, array $nullStatuses): void
    {
        $list = fn (array $values) => implode(',', array_map(fn ($value) => "'{$value}'", $values));
        DB::statement('ALTER TABLE pengukuran_kinerjas DROP CONSTRAINT pengukuran_kinerjas_status_perhitungan_check, DROP CONSTRAINT nilai_status_cocok');
        DB::statement('ALTER TABLE pengukuran_kinerjas ADD CONSTRAINT pengukuran_kinerjas_status_perhitungan_check CHECK (status_perhitungan IN ('.$list($statuses).'))');
        DB::statement("ALTER TABLE pengukuran_kinerjas ADD CONSTRAINT nilai_status_cocok CHECK ((status_perhitungan = 'terhitung' AND nilai IS NOT NULL) OR (status_perhitungan IN (".$list($nullStatuses).') AND nilai IS NULL))');
    }
};
