<?php

namespace App\Services\Kegiatan;

use App\Actions\Kegiatan\TambahKegiatanRencanaAksi;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Membentuk kegiatan lanjutan dari kegiatan yang ditunda beserta klaim tahap pengukurannya
 * (Q41 §41.4 butir 2, Q69 §69.5). Caller: `UbahStatusKegiatan` (pembentukan dan klaim jalur pertama)
 * dan `EnsureDraftPengukuran` (klaim jalur kedua saat baris pengukuran periode tujuan terbentuk).
 *
 * Mutasi dipanggil di dalam transaksi Action yang sudah mengunci kegiatan asal dan memvalidasi periode tujuan;
 * Service ini tidak membuka transaksi, tidak memeriksa izin, dan tidak menulis audit (audit milik Action).
 * Kunci periode tujuan bagi PIC (`tujuanTerkunci`, `saringTujuan`) dipakai bersama oleh Action dan tampilan
 * (`DaftarKegiatan`, `FormKegiatan`) agar periode yang pasti ditolak tidak ditawarkan.
 * Isi lanjutan memakai `TambahKegiatanRencanaAksi::FIELD_SALIN` ditambah unit dan tahun; tanggal rencana,
 * justifikasi, data pelaksanaan, klaim, dan berkas tidak disalin.
 *
 * Klaim lanjutan diteruskan dari seluruh klaim rantai asalnya (asal, asal dari asal, dan seterusnya; tahap RA
 * maupun pengukuran, satu per RA × komponen) agar rantai lanjutan tetap tertaut ke RA yang sama walau kegiatan
 * perantara belum diklaim, hanya ke RA unit yang sama dengan lanjutan (Data Model §2.26), dan selalu menunjuk
 * baris pengukuran indikator RA × tahun × periode lanjutan apa pun status alurnya; versi beku tidak disentuh.
 */
class RollOverKegiatan
{
    public function __construct(private readonly JendelaTulisRencanaAksi $jendela) {}

    /**
     * Menyerialkan pembentukan lanjutan dengan pembentukan baris pengukuran pada tahun × periode yang sama,
     * sehingga salah satu jalur selalu melihat hasil jalur lain dan tidak ada lanjutan yang tertinggal tanpa klaim.
     * Caller transisi mengambilnya sebelum mengunci header RA; caller pengukuran sesudah barisnya dibuat.
     */
    public static function kunciPeriode(int $tahun, string $periodeId): void
    {
        DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['sakip:kegiatan-lanjutan:'.$tahun.':'.strtolower($periodeId)]);
    }

    /**
     * Periode tujuan yang terkunci bagi PIC pada jadwal RA mana pun yang mengklaim kegiatan asal (Q69 §69.5 butir 2,
     * Q41 §41.2), terurut per jadwal lalu urutan periode. Jalur Perencanaan dikecualikan oleh pemanggil; tanpa RA
     * pengklaim tidak ada RA yang menerima klaim lanjutan, sehingga tidak ada kunci periode.
     *
     * @param  list<string>  $periodeIds
     * @return list<array{id: string, nama: string, batas: string, urutan: int}>
     */
    public function tujuanTerkunci(Kegiatan $asal, array $periodeIds): array
    {
        $hariIni = today(config('app.business_timezone'))->toDateString();

        return JadwalTahunan::whereIn('id', RencanaAksi::whereIn('id', KlaimKegiatan::where('kegiatan_id', $asal->id)->select('rencana_aksi_id'))
            ->select('jadwal_tahunan_id'))->orderBy('id')->get()
            ->flatMap(fn (JadwalTahunan $jadwal): array => $this->jendela->rincianTerkunci($jadwal, $periodeIds, $hariIni))->values()->all();
    }

    /**
     * Periode terkunci bagi PIC per kegiatan untuk satu halaman daftar: satu query jadwal pengklaim, lalu satu penilaian per jadwal,
     * sehingga jumlah query tidak tumbuh per baris. Kegiatan tanpa RA pengklaim tidak muncul (tanpa kunci).
     *
     * @param  list<string>  $kegiatanIds
     * @param  list<string>  $periodeIds
     * @return array<string, list<string>>
     */
    public function periodeTerkunciPerKegiatan(array $kegiatanIds, array $periodeIds): array
    {
        $pengklaim = KlaimKegiatan::join('rencana_aksi', 'rencana_aksi.id', '=', 'klaim_kegiatan.rencana_aksi_id')
            ->whereIn('klaim_kegiatan.kegiatan_id', $kegiatanIds)->distinct()->get(['klaim_kegiatan.kegiatan_id', 'rencana_aksi.jadwal_tahunan_id']);
        if ($pengklaim->isEmpty()) {
            return [];
        }
        $hariIni = today(config('app.business_timezone'))->toDateString();
        $perJadwal = JadwalTahunan::whereIn('id', $pengklaim->pluck('jadwal_tahunan_id')->unique()->values()->all())->get()
            ->mapWithKeys(fn (JadwalTahunan $jadwal): array => [(string) $jadwal->id => array_column($this->jendela->rincianTerkunci($jadwal, $periodeIds, $hariIni), 'id')]);

        $hasil = [];
        foreach ($pengklaim as $baris) {
            $id = (string) $baris->kegiatan_id;
            $hasil[$id] = array_values(array_unique([...($hasil[$id] ?? []), ...($perJadwal[(string) $baris->getAttribute('jadwal_tahunan_id')] ?? [])]));
        }

        return $hasil;
    }

    /**
     * Pilihan status tampilan tanpa periode tujuan yang terkunci bagi PIC; Tunda tidak ditawarkan bila tidak ada
     * periode tujuan tersisa. Action tetap memeriksa ulang di bawah lock.
     *
     * @param  array{status: list<string>, periode_tujuan: list<array{id: string, nama: string}>}  $pilihan  Hasil `Kegiatan::pilihanStatus`.
     * @param  list<string>|null  $terkunci  Periode terkunci yang sudah dihitung per halaman (`periodeTerkunciPerKegiatan`); null dihitung di sini.
     * @return array{status: list<string>, periode_tujuan: list<array{id: string, nama: string}>}
     */
    public function saringTujuan(Kegiatan $asal, array $pilihan, bool $jalurPerencanaan, ?array $terkunci = null): array
    {
        if ($jalurPerencanaan || ! in_array('ditunda', $pilihan['status'], true)) {
            return $pilihan;
        }
        $terkunci ??= array_column($this->tujuanTerkunci($asal, array_column($pilihan['periode_tujuan'], 'id')), 'id');
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
     * Klaim tahap pengukuran lanjutan untuk setiap RA pengklaim kegiatan asal yang baris pengukuran periode
     * lanjutannya sudah ada; `$hanya` membatasi ke satu baris pengukuran (jalur kedua).
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

    /**
     * Klaim asal yang ditambahkan setelah lanjutannya terbentuk (Q69 §69.3 butir 3) diteruskan ke setiap lanjutan dalam rantainya yang
     * baris pengukuran indikator RA × tahun × periodenya sudah ada; tanpa ini klaim hanya terbentuk pada transisi dan pembentukan baris.
     * Dipanggil di bawah lock header RA dan kegiatan asal oleh gerbang klaim; lanjutan dikunci bersama seperti jalur kedua.
     *
     * @return list<array{klaim: KlaimKegiatan, kegiatan_asal_id: string}>
     */
    public function klaimTurunan(Kegiatan $asal, RencanaAksi $header, User $aktor): array
    {
        $turunan = array_column(DB::select(<<<'SQL'
            WITH RECURSIVE turunan (id) AS (
                SELECT id FROM kegiatan WHERE kegiatan_asal_id = ?
                UNION
                SELECT lanjutan.id FROM kegiatan AS lanjutan JOIN turunan ON lanjutan.kegiatan_asal_id = turunan.id
            )
            SELECT id FROM turunan
            SQL, [$asal->id]), 'id');
        if ($turunan === []) {
            return [];
        }

        return Kegiatan::whereIn('id', $turunan)->orderBy('id')->sharedLock()->get()->flatMap(function (Kegiatan $lanjutan) use ($header, $aktor): array {
            $pengukuran = PengukuranKinerja::where('indikator_id', $header->indikator_id)->where('tahun', $lanjutan->tahun)
                ->where('periode_id', $lanjutan->periode_id)->first();

            return $pengukuran === null ? [] : array_map(
                fn (KlaimKegiatan $klaim): array => ['klaim' => $klaim, 'kegiatan_asal_id' => (string) $lanjutan->kegiatan_asal_id],
                $this->klaimkan($lanjutan, $aktor, $pengukuran),
            );
        })->values()->all();
    }

    /**
     * Klaim lanjutan yang tertunda karena baris pengukuran periodenya belum ada saat ditunda (jalur kedua).
     * Lanjutan dikunci bersama (`FOR SHARE`) agar unitnya tidak dipindahkan `SimpanKegiatan` sebelum klaim tersimpan;
     * sesudah itu klaim yang ada menolak pemindahan.
     *
     * @return list<array{klaim: KlaimKegiatan, kegiatan_asal_id: string}>
     */
    public function klaimTertunda(PengukuranKinerja $pengukuran, User $aktor): array
    {
        $lanjutan = Kegiatan::where('periode_id', $pengukuran->periode_id)->where('tahun', $pengukuran->tahun)->whereNotNull('kegiatan_asal_id')
            ->orderBy('id')->sharedLock()->get();

        return $lanjutan->flatMap(fn (Kegiatan $kegiatan): array => array_map(
            fn (KlaimKegiatan $klaim): array => ['klaim' => $klaim, 'kegiatan_asal_id' => (string) $kegiatan->kegiatan_asal_id],
            $this->klaimkan($kegiatan, $aktor, $pengukuran),
        ))->values()->all();
    }
}
