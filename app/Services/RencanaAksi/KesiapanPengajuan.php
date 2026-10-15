<?php

namespace App\Services\RencanaAksi;

use App\Actions\Pengukuran\CalculatePengukuran;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use App\Support\DeviasiTargetPk;
use Illuminate\Support\Carbon;
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
 * transaksi, tidak mengunci, tidak mengaudit, dan tidak membaca database:
 * semua state (definisi snapshot, jendela, target, hasil evaluator bukti)
 * diberikan caller, yang menentukan mode kunci.
 */
class KesiapanPengajuan
{
    /** Tahun RA data awal yang dicatat Perencanaan (Q32.8, Q46 §46.1 butir 3). */
    public const TAHUN_DATA_AWAL = 2026;

    public function __construct(private readonly CalculatePengukuran $calculator) {}

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
    public function barisPeriode(string $tipe, int $presisi, Collection $definisi, Collection $jendela, Collection $efektifIds, Collection $petaTarget): array
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
                $skor = $this->skor($tipe, $presisi, $definisi, array_column($komponen, 'nilai', 'komponen_id'));
                $keterangan = null;
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
     * untuk RA data awal 2026 yang diajukan lewat jalur Perencanaan (Q46
     * §46.1 butir 3, Q32.8) dan RA yang seluruh periode efektifnya sudah
     * lampau saat aktivasi jadwal (Q46 §46.1 butir 1). Kegiatan pada periode
     * lampau tetap dihitung bila ada.
     *
     * @param  Collection<int, PeriodeJadwal>  $jendela
     * @param  Collection<int, string>  $periodeEfektif
     */
    public function alasanKegiatan(int $jumlahKlaim, int $tahun, bool $jalurPerencanaan, JadwalTahunan $jadwal, Collection $jendela, Collection $periodeEfektif): ?string
    {
        if ($jumlahKlaim > 0) {
            return null;
        }
        // Disetujui PM (PR #111): "data awal" dikenali dari tahun 2026 dan jalur Perencanaan, tanpa kolom penanda.
        if ($tahun === self::TAHUN_DATA_AWAL && $jalurPerencanaan) {
            return null;
        }
        // Periode lampau mengikuti JadwalActivationReadiness: pengisian selesai sebelum tanggal aktivasi jadwal.
        $aktivasi = $jadwal->activated_at === null ? null : Carbon::parse($jadwal->activated_at)->setTimezone(config('app.business_timezone'))->toDateString();
        $lampau = $aktivasi === null ? collect() : $jendela
            ->filter(fn (PeriodeJadwal $row): bool => $row->pengisian_selesai !== null && $row->pengisian_selesai->toDateString() < $aktivasi)
            ->map(fn (PeriodeJadwal $row): string => (string) $row->periode_id);
        if ($periodeEfektif->isNotEmpty() && $periodeEfektif->diff($lampau)->isEmpty()) {
            return null;
        }

        return 'Rencana aksi wajib memiliki minimal satu kegiatan yang diklaim pada tahap rencana aksi.';
    }

    /**
     * Klaim tahap RA yang tidak lagi cocok dengan konteks beku terbaru
     * (snapshot koreksi dapat terbit setelah klaim dibuat): unit dan tahun
     * kegiatan sama dengan header, periode termasuk periode efektif, komponen
     * anggota snapshot. Dipakai gerbang pengajuan dan kesiapan halaman.
     *
     * @param  iterable<KlaimKegiatan>  $klaim
     * @param  Collection<string, Kegiatan>  $kegiatan  Diindeks ID kegiatan.
     * @param  Collection<int, string>  $periodeEfektif
     * @param  list<string>  $komponenIds
     * @return list<string>
     */
    public function alasanKlaim(iterable $klaim, Collection $kegiatan, RencanaAksi $header, Collection $periodeEfektif, array $komponenIds): array
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

        return $alasan;
    }

    /**
     * Alasan pengajuan ditolak dengan teks yang sama untuk kartu kesiapan dan
     * gerbang server. Hanya `terhitung` yang lengkap: `belum_diisi` dan
     * `tidak_dapat_dihitung` (termasuk penyebut nol) menolak. Deviasi PK yang
     * tidak dapat dinilai (target PK null) tidak memerlukan alasan.
     *
     * @param  list<array{periode_nama: ?string, status_perhitungan: string}>  $baris
     * @param  array{status: string}  $deviasi  Keluaran `DeviasiTargetPk::bandingkan`.
     * @param  list<array<string, mixed>>  $persyaratan  Keluaran `EvaluateEvidence::untuk`.
     * @return list<string>
     */
    public function alasanTolak(array $baris, array $deviasi, ?string $alasanDeviasi, array $persyaratan): array
    {
        $alasan = [];
        if ($baris === []) {
            $alasan[] = 'Tidak ada periode efektif untuk indikator ini.';
        }
        foreach ($baris as $row) {
            if ($row['status_perhitungan'] === 'belum_diisi') {
                $alasan[] = "Target periode {$row['periode_nama']} belum lengkap.";
            } elseif ($row['status_perhitungan'] !== 'terhitung') {
                $alasan[] = "Target periode {$row['periode_nama']} tidak dapat dihitung; perbaiki target sebelum mengajukan.";
            }
        }
        if ($deviasi['status'] === DeviasiTargetPk::DEVIASI && trim((string) $alasanDeviasi) === '') {
            $alasan[] = 'Skor periode terakhir berbeda dari target PK; alasan deviasi wajib diisi.';
        }
        foreach ($persyaratan as $item) {
            if ($item['wajib'] && ! $item['pemenuhan']['terpenuhi']) {
                // Cermin SubmissionPrerequisites: persyaratan tanpa mode aktif
                // tidak dapat dipenuhi pengaju, jadi arahkan ke Tim Perencanaan.
                $alasan[] = ! $item['izinkan_file'] && ! $item['izinkan_tautan'] && ! $item['izinkan_teks']
                    ? 'Persyaratan bukti '.$item['nama'].' tidak memiliki mode aktif. Hubungi Tim Perencanaan.'
                    : 'Bukti wajib belum lengkap: '.$item['nama'].'.';
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
}
