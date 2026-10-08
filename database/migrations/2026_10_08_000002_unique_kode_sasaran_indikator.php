<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lapisan pertahanan kedua untuk deret kode berurutan: unique index pada
 * `sasaran_strategis.kode` dan `indikator_kinerjas.kode`.
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

        Schema::table('sasaran_strategis', function (Blueprint $table) {
            $table->unique('kode', 'sasaran_strategis_kode_unik');
        });

        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->unique('kode', 'indikator_kinerjas_kode_unik');
        });
    }

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
