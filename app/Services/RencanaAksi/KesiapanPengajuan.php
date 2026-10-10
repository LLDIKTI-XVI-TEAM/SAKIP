<?php

namespace App\Services\RencanaAksi;

use App\Actions\Pengukuran\CalculatePengukuran;
use App\Models\JadwalSnapshot;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksiTarget;
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
 * Dipisah karena dipakai dua caller dengan mode berbeda: `IndexRencanaAksi`
 * menampilkan kesiapan tanpa kunci, sedangkan `AjukanRencanaAksi`
 * mengevaluasi ulang di bawah kunci tepat sebelum membekukan versi. Dengan
 * satu sumber, alasan yang tampil di halaman sama dengan alasan penolakan
 * server, dan skor yang tampil (`IndexRencanaAksi`, `PreviewTargetPeriode`
 * lewat `skor`) sama dengan skor yang dibekukan. Service ini tidak membuka
 * transaksi, tidak mengunci, tidak mengaudit, dan tidak membaca database:
 * semua state (definisi snapshot, jendela, target, hasil evaluator bukti)
 * diberikan caller, yang menentukan mode kunci.
 */
class KesiapanPengajuan
{
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
                : $this->calculator->handle($tipe, $presisi, $definisi->map(fn (array $item): array => [
                    'komponen_id' => $item['komponen_id'],
                    'peran' => $item['peran'],
                    'bobot' => $item['bobot'],
                ])->all(), $nilai, null);
        } catch (InvalidArgumentException|ValidationException) {
            return ['nilai' => null, 'status_perhitungan' => 'tidak_dapat_dihitung'];
        }

        return ['nilai' => $hitung['nilai'] === null ? null : (string) $hitung['nilai'], 'status_perhitungan' => (string) $hitung['status_perhitungan']];
    }
}
