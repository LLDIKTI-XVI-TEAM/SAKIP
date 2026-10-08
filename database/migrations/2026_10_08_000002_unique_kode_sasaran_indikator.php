<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penyiapan data + lapisan pertahanan kedua untuk deret kode berurutan:
 * penyelarasan `urutan` dengan nomor kode, lalu unique index pada
 * `sasaran_strategis.kode` dan `indikator_kinerjas.kode`.
 *
 * Urutan langkah disengaja: **pre-check duplikat → backfill → unique index**.
 * Pre-check berjalan sebelum langkah yang mengubah data, sehingga database
 * dengan kode duplikat berhenti tanpa `urutan` yang sudah ditimpa — backfill
 * bersifat tak dapat dibalik karena nilai lama tidak disimpan.
 *
 * Migrasi ini SENGAJA berhenti tanpa mengubah apa pun bila menemukan kode
 * duplikat — tidak menghapus, menggabungkan, atau memilih pemenang — karena
 * penyelesaian data adalah keputusan eksplisit pemilik data (kebijakan
 * penghentian rollout yang sama dengan addendum keunikan jadwal).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->pastikanTidakAdaKodeDuplikat();

        $this->backfillUrutan('sasaran_strategis', 'SS');
        $this->backfillUrutan('indikator_kinerjas', 'IK');

        Schema::table('sasaran_strategis', function (Blueprint $table) {
            $table->unique('kode', 'sasaran_strategis_kode_unik');
        });

        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->unique('kode', 'indikator_kinerjas_kode_unik');
        });
    }

    /**
     * Catatan: backfill `urutan` tidak dibalik saat rollback karena nilai
     * urutan lama tidak disimpan.
     */
    public function down(): void
    {
        Schema::table('sasaran_strategis', function (Blueprint $table) {
            $table->dropUnique('sasaran_strategis_kode_unik');
        });

        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->dropUnique('indikator_kinerjas_kode_unik');
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

    /**
     * Hentikan migrasi bila ada kode duplikat pada salah satu tabel master.
     */
    private function pastikanTidakAdaKodeDuplikat(): void
    {
        foreach (['sasaran_strategis', 'indikator_kinerjas'] as $tabel) {
            $duplikat = DB::table($tabel)
                ->select('kode')
                ->groupBy('kode')
                ->havingRaw('COUNT(*) > 1')
                ->orderBy('kode')
                ->pluck('kode')
                ->all();

            if ($duplikat !== []) {
                throw new RuntimeException(
                    "Rollout dihentikan: tabel {$tabel} memuat kode duplikat (".implode(', ', $duplikat).'). '
                    .'Selesaikan duplikat tersebut secara eksplisit sebelum indeks unik kode dipasang; '
                    .'migrasi ini tidak menghapus, menggabungkan, atau memilih pemenang.'
                );
            }
        }
    }
};
