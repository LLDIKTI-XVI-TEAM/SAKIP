<?php

namespace App\Services\RencanaAksi;

use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use Illuminate\Support\Collection;

/**
 * Rekonsiliasi transisi snapshot pada target draf Rencana Aksi: menandai
 * baris yang tak efektif pada versi snapshot mana pun antara jepit draf dan
 * snapshot terbaru.
 *
 * Dipisah karena dipakai tiga Action dengan peran berbeda: `IndexRencanaAksi`
 * dan `PreviewTargetPeriode` menyaring baris basi tanpa efek samping,
 * sedangkan `SimpanTargetPeriode` menghapusnya dan mengauditnya di dalam
 * transaksinya. Service ini hanya membaca; penghapusan, audit, dan kunci
 * baris tetap milik Action pemanggil (jalur tulis sudah mengunci header,
 * snapshot, dan jendela periode sebelum memanggilnya).
 */
class RekonsiliasiTargetDraf
{
    public function __construct(
        private readonly KonteksBekuRencanaAksi $konteks,
    ) {}

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
     * (draf lawas/pembuatan langsung).
     *
     * Predikat efektif per versi memakai aturan `KonteksBekuRencanaAksi`
     * yang sama dengan Simpan/Index/Preview (tipe, himpunan komponen,
     * periode-mulai snapshot).
     *
     * @return array{pin_nomor: int, aktual_nomor: int, kunci: list<string>}
     */
    public function rekonsiliasi(RencanaAksi $header, JadwalSnapshot $snapshot): array
    {
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

        $jendela = $this->konteks->jendela((string) $snapshot->jadwal_id);

        $konteks = [];
        foreach ($antara as $versi) {
            $tipe = (string) $versi->tipe_perhitungan;
            $konteks[] = [
                'periode' => $this->konteks->periodeEfektif($versi, $jendela)->all(),
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
}
