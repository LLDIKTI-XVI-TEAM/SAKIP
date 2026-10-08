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
 * Urutan langkah disengaja: **pre-check duplikat → simpan nilai lama →
 * backfill → unique index**. Pre-check berjalan sebelum langkah yang mengubah
 * data, sehingga database dengan kode duplikat berhenti tanpa `urutan` yang
 * sudah ditimpa. Nilai `urutan` lama disimpan ke tabel backup supaya `down()`
 * benar-benar memulihkan urutan yang diatur pengguna saat release di-rollback
 * (F6 Review10) — tanpa itu aplikasi lama akan mengurutkan tampilan
 * berdasarkan kolom yang sudah ditimpa.
 *
 * Migrasi ini SENGAJA berhenti tanpa mengubah apa pun bila menemukan kode
 * duplikat — tidak menghapus, menggabungkan, atau memilih pemenang — karena
 * penyelesaian data adalah keputusan eksplisit pemilik data (kebijakan
 * penghentian rollout yang sama dengan addendum keunikan jadwal).
 */
return new class extends Migration
{
    private const TABEL_BACKUP = '_backup_urutan_kode_otomatis';

    public function up(): void
    {
        $this->pastikanTidakAdaKodeDuplikat();

        Schema::create(self::TABEL_BACKUP, function (Blueprint $table) {
            $table->string('tabel', 40);
            $table->uuid('id');
            $table->integer('urutan_lama');
            $table->primary(['tabel', 'id']);
        });

        $this->simpanUrutanLama('sasaran_strategis', 'SS');
        $this->simpanUrutanLama('indikator_kinerjas', 'IK');

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
     * Memulihkan `urutan` yang ditimpa backfill, lalu melepas indeks unik.
     * Baris yang dibuat setelah migrasi tidak ada di tabel backup sehingga
     * tidak tersentuh.
     */
    public function down(): void
    {
        $this->pulihkanUrutanLama('sasaran_strategis');
        $this->pulihkanUrutanLama('indikator_kinerjas');

        Schema::dropIfExists(self::TABEL_BACKUP);

        Schema::table('sasaran_strategis', function (Blueprint $table) {
            $table->dropUnique('sasaran_strategis_kode_unik');
        });

        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->dropUnique('indikator_kinerjas_kode_unik');
        });
    }

    /**
     * Catat nilai `urutan` sebelum ditimpa, hanya untuk baris yang akan
     * disentuh backfill (kode mengikuti pola `<prefix>-<digit>`).
     */
    private function simpanUrutanLama(string $tabel, string $prefix): void
    {
        DB::statement(
            'INSERT INTO '.self::TABEL_BACKUP.' (tabel, id, urutan_lama) SELECT ?, id, urutan FROM '.$tabel.' WHERE kode ~ ?',
            [$tabel, '^'.$prefix.'-[0-9]+$'],
        );
    }

    private function pulihkanUrutanLama(string $tabel): void
    {
        if (! Schema::hasTable(self::TABEL_BACKUP)) {
            return;
        }

        DB::statement(
            'UPDATE '.$tabel.' AS t SET urutan = b.urutan_lama FROM '.self::TABEL_BACKUP.' AS b WHERE b.tabel = ? AND b.id = t.id',
            [$tabel],
        );
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
