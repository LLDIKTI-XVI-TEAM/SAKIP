<?php

namespace App\Services\RencanaAksi;

use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use Illuminate\Support\Collection;

class RekonsiliasiTargetDraf
{
    /**
     * Menelusuri versi snapshot antara jepit draf dan snapshot terbaru.
     *
     * Baris tersimpan dianggap basi bila dimensinya (periode_id +
     * komponen_id) tidak efektif pada SATU PUN versi antara — termasuk
     * versi yang dilewati tanpa penyimpanan (v1 → v2 tanpa save → v3).
     * Pembersihan pasca-POST saja tak menjangkau jendela
     * tanpa-simpan itu; di sinilah nilai lama bangkit kembali.
     *
     * Jepit (`rencana_aksi.snapshot_draf_id`, non-FK)
     * menandai "terakhir direkonsiliasi di bawah snapshot X". NULL
     * dibaca fail-closed sebagai "telusuri seluruh versi sejak awal"
     * bila snapshot ada (draf lawas/pembuatan langsung), dan diabaikan
     * bila konteks memang tanpa snapshot.
     *
     * Predikat efektif per versi cermin `SimpanTargetPeriode` (tipe,
     * himpunan komponen, periode-mulai snapshot). Tahun
     * master live sengaja diabaikan di sini — bila snapshot ada,
     * `periode_mulai_id` snapshot adalah satu-satunya sumber
     * efektivitas; tahun master hanya untuk konteks tanpa snapshot
     * (cermin ketiga jalur Simpan/Index/Preview). Metode ini selalu
     * berjalan dengan snapshot sehingga tanpa gerbang tahun.
     *
     * @return array{pin_nomor: int, aktual_nomor: int|null, kunci: list<string>}
     */
    public function rekonsiliasi(RencanaAksi $header, ?JadwalSnapshot $snapshot): array
    {
        if (! $snapshot instanceof JadwalSnapshot) {
            return ['pin_nomor' => 0, 'aktual_nomor' => null, 'kunci' => []];
        }

        $aktualNomor = (int) $snapshot->nomor_versi;
        $pinNomor = $this->nomorJepit($header);
        if ($pinNomor >= $aktualNomor) {
            return ['pin_nomor' => $pinNomor, 'aktual_nomor' => $aktualNomor, 'kunci' => []];
        }

        $antara = JadwalSnapshot::where('jadwal_id', $snapshot->jadwal_id)
            ->where('indikator_id', $snapshot->indikator_id)
            ->where('nomor_versi', '>', $pinNomor)
            ->where('nomor_versi', '<=', $aktualNomor)
            ->orderBy('nomor_versi')
            ->get();
        if ($antara->isEmpty()) {
            return ['pin_nomor' => $pinNomor, 'aktual_nomor' => $aktualNomor, 'kunci' => []];
        }

        $tersimpan = RencanaAksiTarget::where('rencana_aksi_id', $header->id)
            ->get(['periode_id', 'komponen_id']);
        if ($tersimpan->isEmpty()) {
            return ['pin_nomor' => $pinNomor, 'aktual_nomor' => $aktualNomor, 'kunci' => []];
        }

        $jendela = PeriodeJadwal::where('jadwal_id', $snapshot->jadwal_id)
            ->with('periode')
            ->get()
            ->sortBy(fn (PeriodeJadwal $row): int => $row->periode?->urutan ?? 0)
            ->values();

        $konteks = [];
        foreach ($antara as $versi) {
            $tipe = (string) $versi->tipe_perhitungan;
            $konteks[] = [
                'periode' => $this->periodeEfektifVersi($versi, $jendela),
                'tipe' => $tipe,
                'komponen' => $tipe === 'manual'
                    ? []
                    : JadwalSnapshotKomponen::where('jadwal_snapshot_id', $versi->id)->pluck('komponen_id')->map(fn ($id): string => (string) $id)->all(),
            ];
        }

        $basi = [];
        foreach ($tersimpan as $baris) {
            $periodeId = (string) $baris->periode_id;
            $komponenId = $baris->komponen_id === null ? null : (string) $baris->komponen_id;
            foreach ($konteks as $lihat) {
                if (! $this->efektifPadaKonteks($lihat, $periodeId, $komponenId)) {
                    $basi[] = $this->kunciDimensi($periodeId, $komponenId);
                    break;
                }
            }
        }

        return ['pin_nomor' => $pinNomor, 'aktual_nomor' => $aktualNomor, 'kunci' => array_values(array_unique($basi))];
    }

    /**
     * Menyaring peta target berkelompok dari baris basi transisi.
     *
     * Baris basi diperlakukan seolah tak ada (sel kosong), bukan
     * dihapus di sini — penghapusan + audit milik jalur tulis
     * (`SimpanTargetPeriode`), sehingga baca tetap murni tanpa
     * efek samping.
     *
     * @param  Collection<string, Collection<string, RencanaAksiTarget>>  $peta
     * @param  list<string>  $basi
     * @return Collection<string, Collection<string, RencanaAksiTarget>>
     */
    public function saringPetaBasi(Collection $peta, array $basi): Collection
    {
        if ($basi === []) {
            return $peta;
        }

        $hitam = array_flip($basi);

        return $peta->map(function (Collection $rows, string $periodeId) use ($hitam): Collection {
            return $rows->reject(function (RencanaAksiTarget $row) use ($hitam, $periodeId): bool {
                $kunci = $this->kunciDimensi($periodeId, $row->komponen_id === null ? null : (string) $row->komponen_id);

                return isset($hitam[$kunci]);
            });
        });
    }

    public function kunciDimensi(string $periodeId, ?string $komponenId): string
    {
        return $periodeId.'::'.($komponenId ?? 'manual');
    }

    private function nomorJepit(RencanaAksi $header): int
    {
        $jepit = $header->snapshot_draf_id;
        if (! is_string($jepit) || $jepit === '') {
            return 0;
        }

        return (int) (JadwalSnapshot::whereKey($jepit)->value('nomor_versi') ?? 0);
    }

    /**
     * Tanpa cek tahun master — konteks ini selalu
     * bersnapshot sehingga `periode_mulai_id` versi adalah sumbernya.
     *
     * @param  array{periode: list<string>, tipe: string, komponen: list<string>}  $lihat
     */
    private function efektifPadaKonteks(array $lihat, string $periodeId, ?string $komponenId): bool
    {
        if (! in_array($periodeId, $lihat['periode'], true)) {
            return false;
        }

        if ($lihat['tipe'] === 'manual') {
            return $komponenId === null;
        }

        return $komponenId !== null && in_array($komponenId, $lihat['komponen'], true);
    }

    /**
     * Himpunan periode efektif satu versi: jendela jadwal minus periode
     * pra-berlaku (cermin jalur tulis).
     *
     * @param  Collection<int, PeriodeJadwal>  $jendela
     * @return list<string>
     */
    private function periodeEfektifVersi(JadwalSnapshot $versi, Collection $jendela): array
    {
        if (is_string($versi->periode_mulai_id)) {
            $mulai = Periode::whereKey($versi->periode_mulai_id)->first();
            if ($mulai instanceof Periode) {
                return $jendela
                    ->filter(fn (PeriodeJadwal $row): bool => ($row->periode?->urutan ?? 0) >= $mulai->urutan)
                    ->map(fn (PeriodeJadwal $row): string => (string) $row->periode_id)
                    ->values()
                    ->all();
            }
        }

        return $jendela->map(fn (PeriodeJadwal $row): string => (string) $row->periode_id)->values()->all();
    }
}
