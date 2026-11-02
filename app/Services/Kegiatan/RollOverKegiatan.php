<?php

namespace App\Services\Kegiatan;

use App\Actions\Kegiatan\TambahKegiatanRencanaAksi;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\User;
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Membentuk kegiatan lanjutan dari kegiatan yang ditunda beserta klaim tahap pengukurannya (Q41 §41.4 butir 2,
 * Q69 §69.5) dan menilai kunci periode tujuan bagi PIC (Q69 §69.5 butir 2). Caller: `UbahStatusKegiatan`
 * (pembentukan, kunci, dan klaim jalur pertama di bawah lock), `EnsureDraftPengukuran` (klaim jalur kedua saat
 * baris pengukuran periode tujuan terbentuk), dan `FormKegiatan` (penyaringan pilihan agar UI tidak menawarkan
 * periode yang pasti ditolak).
 *
 * Mutasi dipanggil di dalam transaksi Action yang sudah mengunci kegiatan asal dan memvalidasi periode
 * tujuan; Service ini tidak membuka transaksi, tidak memeriksa izin, dan tidak menulis audit (audit milik
 * Action). Isi lanjutan memakai `TambahKegiatanRencanaAksi::FIELD_SALIN` ditambah unit dan tahun; tanggal
 * rencana, justifikasi, data pelaksanaan, klaim, dan berkas tidak disalin.
 *
 * Klaim lanjutan diteruskan dari seluruh klaim rantai asalnya (asal, asal dari asal, dan seterusnya; tahap RA maupun
 * pengukuran, satu per RA × komponen) agar rantai tetap tertaut ke RA yang sama walau kegiatan perantara belum
 * diklaim, hanya ke RA unit dan tahun yang sama dengan lanjutan (Data Model §2.26), dan selalu menunjuk baris
 * pengukuran indikator RA × tahun × periode lanjutan apa pun status alurnya; versi beku tidak disentuh.
 * shortcut: klaim yang ditambahkan ke asal setelah lanjutan terbentuk tidak diteruskan (di luar lingkup ISS-06.05);
 * tambahkan penerusan di `TambahKlaimKegiatan` bila PM memintanya.
 */
class RollOverKegiatan
{
    public function __construct(private readonly JendelaTulisRencanaAksi $jendela) {}

    /**
     * Menyerialkan pembentukan lanjutan dengan pembentukan baris pengukuran pada tahun × periode yang sama, sehingga
     * salah satu jalur selalu melihat hasil jalur lain dan tidak ada lanjutan yang tertinggal tanpa klaim. Kedua caller
     * mengambilnya sebelum mengunci header RA.
     */
    public static function kunciPeriode(int $tahun, string $periodeId): void
    {
        DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['sakip:kegiatan-lanjutan:'.$tahun.':'.strtolower($periodeId)]);
    }

    /**
     * Periode yang terkunci bagi PIC pada jadwal non-draf tahun kegiatan (Q41 §41.2), yaitu jadwal yang sama dengan
     * kunci tahun kegiatan (`GerbangBerkasKegiatan::tahunDitutup`), sehingga berlaku juga bagi kegiatan yang belum
     * diklaim. Jalur Perencanaan dikecualikan oleh pemanggil.
     *
     * @param  list<string>  $periodeIds
     * @return list<array{id: string, nama: string, batas: string, urutan: int}>
     */
    public function tujuanTerkunci(int $tahun, array $periodeIds): array
    {
        $hariIni = today(config('app.business_timezone'))->toDateString();

        return JadwalTahunan::where('tahun', $tahun)->where('status', '!=', 'draft')->orderBy('id')->get()
            ->flatMap(fn (JadwalTahunan $jadwal): array => $this->jendela->rincianTerkunci($jadwal, $periodeIds, $hariIni))
            ->unique('id')->values()->all();
    }

    /**
     * Pilihan status tampilan tanpa periode tujuan yang terkunci bagi PIC; Tunda tidak ditawarkan bila tidak ada
     * periode tujuan tersisa. Action tetap memeriksa ulang di bawah lock.
     *
     * @param  array{status: list<string>, periode_tujuan: list<array{id: string, nama: string}>}  $pilihan  Hasil `Kegiatan::pilihanStatus`.
     * @return array{status: list<string>, periode_tujuan: list<array{id: string, nama: string}>}
     */
    public function saringTujuan(Kegiatan $asal, array $pilihan, bool $jalurPerencanaan): array
    {
        if ($jalurPerencanaan || $pilihan['periode_tujuan'] === []) {
            return $pilihan;
        }
        $terkunci = array_column($this->tujuanTerkunci((int) $asal->tahun, array_column($pilihan['periode_tujuan'], 'id')), 'id');
        $tujuan = array_values(array_filter($pilihan['periode_tujuan'], fn (array $periode): bool => ! in_array($periode['id'], $terkunci, true)));

        return [
            'status' => $tujuan === [] ? array_values(array_diff($pilihan['status'], ['ditunda'])) : $pilihan['status'],
            'periode_tujuan' => $tujuan,
        ];
    }

    public function buat(Kegiatan $asal, Periode $tujuan, User $aktor): Kegiatan
    {
        return Kegiatan::create(Arr::only($asal->getAttributes(), [...TambahKegiatanRencanaAksi::FIELD_SALIN, 'unit_id', 'tahun']) + [
            'periode_id' => $tujuan->id, 'status' => 'rencana', 'kegiatan_asal_id' => $asal->id, 'created_by' => $aktor->id,
        ]);
    }

    /**
     * Klaim tahap pengukuran lanjutan untuk setiap RA pengklaim rantai asal yang baris pengukuran periode lanjutannya
     * sudah ada; `$hanya` membatasi ke satu baris pengukuran (jalur kedua). Klaim yang sudah ada dilewati (idempoten).
     *
     * @return list<KlaimKegiatan>
     */
    public function klaimkan(Kegiatan $lanjutan, User $aktor, ?PengukuranKinerja $hanya = null): array
    {
        $sumber = KlaimKegiatan::join('rencana_aksi', 'rencana_aksi.id', '=', 'klaim_kegiatan.rencana_aksi_id')
            ->whereIn('klaim_kegiatan.kegiatan_id', self::rantaiAsal((string) $lanjutan->id))->where('rencana_aksi.tahun', $lanjutan->tahun)
            ->where('rencana_aksi.unit_id', $lanjutan->unit_id)
            ->when($hanya, fn ($query) => $query->where('rencana_aksi.indikator_id', $hanya->indikator_id))
            ->orderBy('klaim_kegiatan.created_at')->orderBy('klaim_kegiatan.id')
            ->get(['klaim_kegiatan.rencana_aksi_id', 'klaim_kegiatan.komponen_id', 'klaim_kegiatan.arah_dampak', 'klaim_kegiatan.catatan', 'rencana_aksi.indikator_id'])
            ->unique(fn (KlaimKegiatan $klaim): string => $klaim->rencana_aksi_id.'|'.$klaim->komponen_id);

        $dibuat = [];
        foreach ($sumber as $klaim) {
            $pengukuranId = $hanya?->id ?? PengukuranKinerja::where('indikator_id', $klaim->getAttribute('indikator_id'))
                ->where('tahun', $lanjutan->tahun)->where('periode_id', $lanjutan->periode_id)->value('id');
            $ada = KlaimKegiatan::where('rencana_aksi_id', $klaim->rencana_aksi_id)->where('kegiatan_id', $lanjutan->id)
                ->when($klaim->komponen_id === null, fn ($query) => $query->whereNull('komponen_id'), fn ($query) => $query->where('komponen_id', $klaim->komponen_id))
                ->exists();
            if ($pengukuranId === null || $ada) {
                continue;
            }
            $dibuat[] = KlaimKegiatan::create([...$klaim->only(['rencana_aksi_id', 'komponen_id', 'arah_dampak', 'catatan']),
                'kegiatan_id' => $lanjutan->id, 'sumber_klaim' => 'pengukuran', 'pengukuran_id' => $pengukuranId,
                'created_by' => $aktor->id, 'created_at' => now()]);
        }

        return $dibuat;
    }

    /**
     * Klaim lanjutan yang tertunda karena baris pengukuran periodenya belum ada saat ditunda (jalur kedua). Unit, tahun,
     * dan periode lanjutan tidak dapat diubah (`SimpanKegiatan`), sehingga kandidat tidak perlu dikunci.
     *
     * @return list<array{klaim: KlaimKegiatan, kegiatan_asal_id: string}>
     */
    public function klaimTertunda(PengukuranKinerja $pengukuran, User $aktor): array
    {
        return Kegiatan::where('periode_id', $pengukuran->periode_id)->where('tahun', $pengukuran->tahun)->whereNotNull('kegiatan_asal_id')
            ->orderBy('id')->get()->flatMap(fn (Kegiatan $kegiatan): array => array_map(
                fn (KlaimKegiatan $klaim): array => ['klaim' => $klaim, 'kegiatan_asal_id' => (string) $kegiatan->kegiatan_asal_id],
                $this->klaimkan($kegiatan, $aktor, $pengukuran),
            ))->values()->all();
    }

    /**
     * Asal, asal dari asal, dan seterusnya (C → B → A); UNION berhenti pada baris yang sudah dikunjungi bila data membentuk siklus.
     *
     * @return list<string>
     */
    public static function rantaiAsal(string $kegiatanId): array
    {
        return array_column(DB::select(<<<'SQL'
            WITH RECURSIVE rantai (id) AS (
                SELECT kegiatan_asal_id FROM kegiatan WHERE id = ? AND kegiatan_asal_id IS NOT NULL
                UNION
                SELECT asal.kegiatan_asal_id FROM kegiatan AS asal JOIN rantai ON asal.id = rantai.id WHERE asal.kegiatan_asal_id IS NOT NULL
            )
            SELECT id FROM rantai
            SQL, [$kegiatanId]), 'id');
    }

    /**
     * Header RA yang mengklaim kegiatan atau rantai asalnya, terurut: tujuan klaim lanjutan yang dibentuk transisi `ditunda`.
     *
     * @return list<string>
     */
    public static function headerRantai(string $kegiatanId): array
    {
        return KlaimKegiatan::whereIn('kegiatan_id', [$kegiatanId, ...self::rantaiAsal($kegiatanId)])->distinct()->orderBy('rencana_aksi_id')
            ->pluck('rencana_aksi_id')->map(fn ($id): string => (string) $id)->all();
    }
}
