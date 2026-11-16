<?php

namespace App\Services\RencanaAksi;

use App\Actions\Pengukuran\CalculatePengukuran;
use App\Models\BuktiDukung;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use App\Services\Kegiatan\PersyaratanPerencanaanKegiatan;
use App\Support\DeviasiTargetPk;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Sumber tunggal kesiapan pengajuan Rencana Aksi: baris target per periode
 * efektif dalam bentuk yang dibekukan ke `rencana_aksi_versi.snapshot`, dan
 * daftar alasan tolak (target belum lengkap/tidak dapat dihitung, deviasi PK
 * tanpa alasan, bukti wajib belum terpenuhi).
 *
 * Dipisah karena dipakai tiga caller: `IndexRencanaAksi` menampilkan
 * kesiapan tanpa kunci, `AjukanRencanaAksi` mengevaluasi ulang di bawah kunci
 * tepat sebelum membekukan versi, dan `PreviewTargetPeriode` memakai
 * `petaTarget` serta `skor` untuk pratinjau. Dengan satu sumber, alasan yang
 * tampil di halaman sama dengan alasan penolakan server, dan skor yang tampil
 * sama dengan skor yang dibekukan. Service ini tidak membuka
 * transaksi, tidak mengunci, dan tidak mengaudit. Kecuali
 * `perencanaanKegiatan`, yang membaca persyaratan bukti kegiatan lewat
 * `PersyaratanPerencanaanKegiatan`, semua state (definisi snapshot, jendela,
 * periode terkunci bagi aktor, target, hasil evaluator bukti) diberikan
 * caller, yang menentukan mode kunci.
 */
class KesiapanPengajuan
{
    /** Tahun RA data awal yang dicatat Perencanaan (Q32.8, Q46 §46.1 butir 3). */
    public const TAHUN_DATA_AWAL = 2026;

    public function __construct(
        private readonly CalculatePengukuran $calculator,
        private readonly PersyaratanPerencanaanKegiatan $persyaratanKegiatan,
    ) {}

    /**
     * Target tersimpan dikelompokkan per periode lalu per komponen
     * (`manual` untuk baris manual `komponen_id = NULL`).
     *
     * @param  Collection<int, RencanaAksiTarget>  $targets
     * @return Collection<string, Collection<string, RencanaAksiTarget>>
     */
    public function petaTarget(Collection $targets): Collection
    {
        return $targets
            ->groupBy(fn (RencanaAksiTarget $row): string => (string) $row->periode_id)
            ->map(fn (Collection $rows): Collection => $rows->keyBy(fn (RencanaAksiTarget $row): string => $row->komponen_id === null ? 'manual' : (string) $row->komponen_id));
    }

    /**
     * Baris periode efektif, urut periode, dengan `nilai` keluaran engine
     * sehingga pembaca versi beku yang menghitung ulang memperoleh angka sama.
     * Manual: `komponen = []` dan `keterangan` milik sel manual. Nonmanual:
     * `keterangan = null` di baris dan `komponen[]` memuat seluruh definisi
     * snapshot, termasuk yang belum diisi (`nilai = null`).
     *
     * @param  Collection<int, array{komponen_id: string, kode: string, label: string, peran: string, bobot: string, urutan: int}>  $definisi
     * @param  Collection<int, PeriodeJadwal>  $jendela  Terurut periode (`KonteksBekuRencanaAksi::jendela`).
     * @param  Collection<int, string>  $efektifIds
     * @param  Collection<string, Collection<string, RencanaAksiTarget>>  $petaTarget
     * @return list<array{periode_id: string, periode_nama: ?string, periode_urutan: int, nilai: ?string, status_perhitungan: string, keterangan: ?string, komponen: list<array{komponen_id: string, kode: string, label: string, nilai: ?string, keterangan: ?string}>}>
     */
    public function barisPeriode(string $tipe, int $presisi, Collection $definisi, Collection $jendela, Collection $efektifIds, Collection $petaTarget, JadwalSnapshot $snapshot): array
    {
        $hasil = [];
        foreach ($jendela as $window) {
            $periodeId = (string) $window->periode_id;
            if (! $efektifIds->contains($periodeId)) {
                continue;
            }

            $sel = $petaTarget->get($periodeId) ?? collect();
            if ($tipe === 'manual') {
                $manual = $sel->get('manual');
                $nilai = [$manual?->nilai];
                $skor = $this->skor($tipe, $presisi, $definisi, ['manual' => $manual?->nilai]);
                $keterangan = $manual?->keterangan;
                $komponen = [];
            } else {
                $komponen = $definisi->map(function (array $item) use ($sel): array {
                    $row = $sel->get($item['komponen_id']);

                    return [
                        'komponen_id' => $item['komponen_id'],
                        'kode' => $item['kode'],
                        'label' => $item['label'],
                        'nilai' => $row?->nilai === null ? null : (string) $row->nilai,
                        'keterangan' => $row?->keterangan,
                    ];
                })->values()->all();
                $nilai = array_column($komponen, 'nilai', 'komponen_id');
                $skor = $this->skor($tipe, $presisi, $definisi, $nilai);
                $keterangan = null;
            }

            if ($skor['status_perhitungan'] === 'belum_diisi' && $nilai !== []
                && array_filter($nilai, fn ($value): bool => $value !== null) === []
                && PeriodeTanpaTarget::bolehTanpaTarget($snapshot, $window, $jendela)) {
                $skor['status_perhitungan'] = 'tidak_ditargetkan';
            }

            $hasil[] = [
                'periode_id' => $periodeId,
                'periode_nama' => $window->periode?->nama,
                'periode_urutan' => (int) ($window->periode?->urutan ?? 0),
                'nilai' => $skor['nilai'],
                'status_perhitungan' => $skor['status_perhitungan'],
                'keterangan' => $keterangan,
                'komponen' => $komponen,
            ];
        }

        return $hasil;
    }

    /**
     * Gerbang minimal satu kegiatan yang diklaim pada tahap RA (Q42 §42.1
     * butir 3); tidak setiap periode wajib berisi kegiatan. Dikecualikan
     * hanya pada jalur Perencanaan lewat `jalurLampauDikecualikan` (Q63);
     * PIC tidak pernah dikecualikan. Kegiatan pada periode lampau tetap
     * dihitung bila ada.
     */
    public function alasanKegiatan(int $jumlahKlaim, int $tahun, bool $jalurPerencanaan): ?string
    {
        if ($jumlahKlaim > 0 || $this->jalurLampauDikecualikan($tahun, $jalurPerencanaan)) {
            return null;
        }

        return 'Rencana aksi wajib memiliki minimal satu kegiatan yang diklaim pada tahap rencana aksi.';
    }

    /**
     * Penanda jalur penerapan Q63 untuk gerbang pengajuan RA: hanya RA data
     * awal 2026 (tahun 2026 lewat jalur Perencanaan, tanpa kolom penanda) yang
     * dikecualikan. RA seluruh-periode-lampau jadwal 2026 tercakup oleh
     * definisi ini; tahun lain tidak pernah dikecualikan (lihat juga
     * `PeriodeTanpaTarget::isPeriodeLampau` yang mensyaratkan jadwal 2026).
     * PIC tidak pernah dikecualikan karena jalur Perencanaan wajib. Dipakai
     * bersama gerbang bukti wajib, gerbang kegiatan minimal, dan props panel
     * bukti halaman RA.
     */
    public function jalurLampauDikecualikan(int $tahun, bool $jalurPerencanaan): bool
    {
        return $jalurPerencanaan && $tahun === self::TAHUN_DATA_AWAL;
    }

    /**
     * Klaim tahap RA yang tidak lagi cocok dengan konteks beku terbaru
     * (snapshot koreksi dapat terbit setelah klaim dibuat): unit dan tahun
     * kegiatan sama dengan header, periode termasuk periode efektif, komponen
     * anggota snapshot. Dipakai gerbang pengajuan dan kesiapan halaman.
     * Tanpa izin baca kegiatan pada unit, nama kegiatan tidak disebut.
     *
     * @param  iterable<KlaimKegiatan>  $klaim
     * @param  Collection<string, Kegiatan>  $kegiatan  Diindeks ID kegiatan.
     * @param  Collection<int, string>  $periodeEfektif
     * @param  list<string>  $komponenIds
     * @return list<string>
     */
    public function alasanKlaim(iterable $klaim, Collection $kegiatan, RencanaAksi $header, Collection $periodeEfektif, array $komponenIds, bool $tampilNama): array
    {
        $alasan = [];
        foreach ($klaim as $item) {
            $sumber = $kegiatan->get($item->kegiatan_id);
            if ($sumber === null || (string) $sumber->unit_id !== (string) $header->unit_id || (int) $sumber->tahun !== (int) $header->tahun
                || ! $periodeEfektif->contains((string) $sumber->periode_id)
                || ($item->komponen_id !== null && ! in_array((string) $item->komponen_id, $komponenIds, true))) {
                $alasan[] = 'Klaim kegiatan "'.($sumber->nama ?? $item->kegiatan_id).'" tidak cocok dengan konteks beku terbaru rencana aksi; hapus atau perbaiki klaim tersebut.';
            }
        }
        if (! $tampilNama && $alasan !== []) {
            return [count($alasan).' klaim kegiatan tidak cocok dengan konteks beku terbaru rencana aksi; hapus atau perbaiki klaim tersebut.'];
        }

        return $alasan;
    }

    /**
     * Alasan pengajuan ditolak dengan teks yang sama untuk kartu kesiapan dan
     * gerbang server. `terhitung` dan pengecualian `tidak_ditargetkan` lengkap; `belum_diisi` dan
     * `tidak_dapat_dihitung` (termasuk penyebut nol) menolak. Deviasi PK yang
     * tidak dapat dinilai (target PK null) tidak memerlukan alasan. Bukti
     * wajib dikecualikan hanya pada jalur Perencanaan (Q63; Q32.7, Q46 §46.1
     * butir 1, Workflow §15 butir 4: "berkas wajib dikecualikan"),
     * sementara rekonsiliasi target PK tetap berlaku di semua jalur.
     * Target kosong pada periode yang sudah terkunci bagi aktor tidak dapat
     * dilengkapi aktor itu, jadi pesannya mengarahkan ke Tim Perencanaan.
     *
     * @param  list<array{periode_id: string, periode_nama: ?string, status_perhitungan: string}>  $baris
     * @param  array{status: string}  $deviasi  Keluaran `DeviasiTargetPk::bandingkan`.
     * @param  list<array<string, mixed>>  $persyaratan  Keluaran `EvaluateEvidence::untuk`.
     * @param  list<string>  $periodeTerkunci  `periode_id` yang terkunci bagi aktor; kosong pada jalur Perencanaan.
     * @return list<string>
     */
    public function alasanTolak(array $baris, array $deviasi, ?string $alasanDeviasi, array $persyaratan, int $tahun, bool $jalurPerencanaan, array $periodeTerkunci): array
    {
        $alasan = [];
        if ($baris === []) {
            $alasan[] = 'Tidak ada periode efektif untuk indikator ini.';
        }
        foreach ($baris as $row) {
            if ($row['status_perhitungan'] === 'belum_diisi') {
                $alasan[] = in_array($row['periode_id'], $periodeTerkunci, true)
                    ? "Target periode {$row['periode_nama']} belum lengkap. Periode ini terkunci; target diisi Tim Perencanaan."
                    : "Target periode {$row['periode_nama']} belum lengkap.";
            } elseif (! in_array($row['status_perhitungan'], ['terhitung', 'tidak_ditargetkan'], true)) {
                $alasan[] = "Target periode {$row['periode_nama']} tidak dapat dihitung; perbaiki target sebelum mengajukan.";
            }
        }
        if ($deviasi['status'] === DeviasiTargetPk::DEVIASI && trim((string) $alasanDeviasi) === '') {
            $alasan[] = 'Skor periode terakhir berbeda dari target PK; alasan deviasi wajib diisi.';
        }
        if (! $this->jalurLampauDikecualikan($tahun, $jalurPerencanaan)) {
            foreach ($persyaratan as $item) {
                if ($item['wajib'] && ! $item['pemenuhan']['terpenuhi']) {
                    // Cermin SubmissionPrerequisites: persyaratan tanpa mode aktif
                    // tidak dapat dipenuhi pengaju, jadi arahkan ke Tim Perencanaan.
                    $alasan[] = ! $item['izinkan_file'] && ! $item['izinkan_tautan'] && ! $item['izinkan_teks']
                        ? 'Persyaratan bukti '.$item['nama'].' tidak memiliki mode aktif. Hubungi Tim Perencanaan.'
                        : 'Bukti wajib belum lengkap: '.$item['nama'].'.';
                }
            }
        }

        return $alasan;
    }

    /**
     * Deviasi skor periode efektif terakhir terhadap target PK beku snapshot.
     *
     * @param  list<array{nilai: ?string}>  $baris  Keluaran `barisPeriode`.
     * @return array{status: string, skor: ?string, target_pk: ?string, presisi: int}
     */
    public function deviasi(array $baris, JadwalSnapshot $snapshot): array
    {
        return DeviasiTargetPk::bandingkan($baris === [] ? null : $baris[array_key_last($baris)]['nilai'], $snapshot->target, (int) $snapshot->presisi);
    }

    /**
     * Skor satu periode dari nilai sel (`manual`, atau per `komponen_id`)
     * lewat mesin hitung bersama. Kegagalan hitung (data drift, presisi di
     * luar kapasitas) menjadi `tidak_dapat_dihitung`, bukan galat halaman;
     * gerbang pengajuan menolaknya.
     *
     * @param  Collection<int, array{komponen_id: string, peran: string, bobot: string}>  $definisi
     * @param  array<string, string|int|float|null>  $nilai
     * @return array{nilai: ?string, status_perhitungan: string}
     */
    public function skor(string $tipe, int $presisi, Collection $definisi, array $nilai): array
    {
        try {
            $hitung = $tipe === 'manual'
                ? $this->calculator->handle($tipe, $presisi, [], [], $nilai['manual'] ?? null)
                : $this->calculator->handle($tipe, $presisi, $definisi->all(), $nilai, null);
        } catch (InvalidArgumentException|ValidationException) {
            return ['nilai' => null, 'status_perhitungan' => 'tidak_dapat_dihitung'];
        }

        return ['nilai' => $hitung['nilai'] === null ? null : (string) $hitung['nilai'], 'status_perhitungan' => (string) $hitung['status_perhitungan']];
    }

    /**
     * Bentuk beku klaim tahap RA (Data Model §2.34): data kegiatan disalin agar
     * perubahan kegiatan setelah pengajuan tidak mengubah versi. Evaluasi
     * persyaratan tahap perencanaan kegiatan dibekukan terpisah
     * (`perencanaanKegiatan`).
     *
     * @param  Collection<int, KlaimKegiatan>  $klaim
     * @param  Collection<string, Kegiatan>  $kegiatan
     * @return list<array<string, mixed>>
     */
    public function klaimBeku(Collection $klaim, Collection $kegiatan): array
    {
        return $klaim->map(function (KlaimKegiatan $item) use ($kegiatan): array {
            /** @var Kegiatan $sumber */
            $sumber = $kegiatan->get($item->kegiatan_id);

            return [
                ...$item->only(['id', 'kegiatan_id', 'komponen_id', 'arah_dampak', 'catatan']),
                'periode_id' => (string) $sumber->periode_id,
                'kegiatan' => [
                    'nama' => $sumber->nama,
                    'tujuan' => $sumber->tujuan,
                    'anggaran' => $sumber->anggaran === null ? null : (string) $sumber->anggaran,
                    'status' => $sumber->status,
                ],
            ];
        })->values()->all();
    }

    /**
     * Gerbang perencanaan kegiatan saat RA diajukan (Q42 §42.2 butir 2,
     * §42.3–§42.4): setiap kegiatan berklaim tahap RA wajib sudah beranggaran
     * dan memenuhi persyaratan wajib tahap `kegiatan_perencanaan` (global +
     * khusus indikator RA ini). Dikecualikan untuk RA data awal 2026 lewat
     * jalur Perencanaan dan kegiatan pada periode lampau (Q46 §46.1). Tanpa
     * izin baca kegiatan pada unit, nama kegiatan tidak disebut.
     *
     * @param  Collection<string, Kegiatan>  $kegiatan  Kegiatan berklaim tahap RA, diindeks ID.
     * @param  Collection<int, PeriodeJadwal>  $jendela
     * @param  Collection<string, Collection<int, BuktiDukung>>  $bukti  Bukti berlaku per ID kegiatan.
     * @return array{alasan: list<string>, evaluasi: list<array{kegiatan_id: string, dikecualikan: bool, persyaratan: list<array<string, mixed>>}>}
     */
    public function perencanaanKegiatan(Collection $kegiatan, string $indikatorId, int $tahun, bool $jalurPerencanaan, JadwalTahunan $jadwal, Collection $jendela, Collection $bukti, bool $tampilNama): array
    {
        $dataAwal = $tahun === self::TAHUN_DATA_AWAL && $jalurPerencanaan;
        $lampau = $jendela->filter(fn (PeriodeJadwal $row): bool => PeriodeTanpaTarget::isPeriodeLampau($jadwal, $row))
            ->map(fn (PeriodeJadwal $row): string => (string) $row->periode_id)->all();
        $alasan = [];
        $evaluasi = [];
        $bermasalah = 0;
        foreach ($kegiatan->sortBy('id') as $item) {
            $dikecualikan = $dataAwal || in_array((string) $item->periode_id, $lampau, true);
            $hasil = $this->persyaratanKegiatan->evaluasi($item, [$indikatorId], $bukti->get((string) $item->id, collect()));
            $evaluasi[] = ['kegiatan_id' => (string) $item->id, 'dikecualikan' => $dikecualikan, 'persyaratan' => $hasil];
            if ($dikecualikan) {
                continue;
            }
            $kurang = PersyaratanPerencanaanKegiatan::kurang($hasil);
            if ($item->anggaran === null) {
                $alasan[] = "Anggaran kegiatan \"{$item->nama}\" belum diisi.";
            }
            if ($kurang !== []) {
                $alasan[] = "Kegiatan \"{$item->nama}\" belum memenuhi persyaratan perencanaan kegiatan: ".implode(', ', $kurang).'.';
            }
            $bermasalah += $item->anggaran === null || $kurang !== [] ? 1 : 0;
        }
        if (! $tampilNama && $bermasalah > 0) {
            $alasan = ["{$bermasalah} kegiatan belum beranggaran atau belum memenuhi persyaratan perencanaan kegiatan."];
        }

        return ['alasan' => $alasan, 'evaluasi' => $evaluasi];
    }
}
