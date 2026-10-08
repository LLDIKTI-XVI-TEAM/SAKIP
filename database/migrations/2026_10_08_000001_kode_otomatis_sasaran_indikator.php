<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dukungan kode berurutan otomatis untuk Sasaran (`SS-<nomor>`) dan
 * Indikator (`IK-<nomor>`): kolom `urutan` sebagai cermin nomor kode beserta
 * penyelarasan nilai untuk baris yang sudah ada.
 *
 * Migrasi ini sengaja TIDAK memasang unique index `kode`. Constraint unik
 * dipisahkan ke migrasi berikutnya karena mensyaratkan data yang sudah bersih
 * (lihat `2026_10_08_000002_unique_kode_sasaran_indikator`), sehingga kolom
 * yang dibutuhkan fitur dapat diterapkan lebih dahulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->integer('urutan')->default(0);
        });

        $this->backfillUrutan('sasaran_strategis', 'SS');
        $this->backfillUrutan('indikator_kinerjas', 'IK');
    }

    /**
     * Catatan: backfill `urutan` tidak dibalik saat rollback karena nilai
     * urutan lama tidak disimpan.
     */
    public function down(): void
    {
        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });
    }

    /**
     * Selaraskan `urutan` dengan nomor pada kode yang mengikuti pola
     * `<prefix>-<digit>`. Kode legacy di luar pola (mis. `SS-RA-FIXTURE`,
     * `IKU-3`) dibiarkan apa adanya dan tidak dihitung sebagai bagian deret.
     */
    private function backfillUrutan(string $tabel, string $prefix): void
    {
        DB::table($tabel)
            ->where('kode', '~', '^'.$prefix.'-[0-9]+$')
            ->update([
                'urutan' => DB::raw("CAST(SUBSTRING(kode FROM '^".$prefix."-([0-9]+)$') AS INTEGER)"),
            ]);
    }
};
