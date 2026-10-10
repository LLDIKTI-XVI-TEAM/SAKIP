<?php

namespace App\Services\RencanaAksi;

use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use Illuminate\Support\Collection;

/**
 * Konteks beku Rencana Aksi: snapshot jadwal terbaru per indikator, himpunan
 * komponen efektifnya, dan periode efektif yang diturunkan dari
 * `periode_mulai_id` snapshot.
 *
 * Dipisah agar jalur tulis (`SimpanTargetPeriode`, `EnsureDraftRencanaAksi`),
 * baca (`IndexRencanaAksi`), pratinjau (`PreviewTargetPeriode`), dan
 * `RekonsiliasiTargetDraf` memakai satu aturan efektivitas. Service ini hanya
 * membaca: penolakan bila snapshot tidak ada, transaksi, dan audit tetap
 * milik Action pemanggil. Mode kunci dipilih pemanggil; jalur tulis meminta
 * `FOR UPDATE` di dalam transaksinya, jalur baca tanpa kunci baris.
 */
class KonteksBekuRencanaAksi
{
    public function snapshotTerbaru(string $jadwalId, string $indikatorId, bool $kunci = false): ?JadwalSnapshot
    {
        return JadwalSnapshot::where('jadwal_id', $jadwalId)
            ->where('indikator_id', $indikatorId)
            ->orderByDesc('nomor_versi')
            ->when($kunci, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    /**
     * Komponen snapshot terurut sebagai baris `komponen_id`, `kode`, `label`,
     * `peran`, `bobot`, dan `urutan`.
     */
    public function komponen(JadwalSnapshot $snapshot, bool $kunci = false): Collection
    {
        return JadwalSnapshotKomponen::where('jadwal_snapshot_id', $snapshot->id)
            ->orderBy('urutan')
            ->orderBy('kode')
            ->when($kunci, fn ($query) => $query->lockForUpdate())
            ->get()
            ->map(fn (JadwalSnapshotKomponen $row): array => [
                'komponen_id' => (string) $row->komponen_id,
                'kode' => (string) $row->kode,
                'label' => (string) $row->label,
                'peran' => (string) $row->peran,
                'bobot' => (string) $row->bobot,
                'urutan' => (int) $row->urutan,
            ])
            ->values();
    }

    /**
     * Jendela periode jadwal, terurut urutan periode lalu ID agar stabil.
     *
     * @return Collection<int, PeriodeJadwal>
     */
    public function jendela(string $jadwalId, bool $kunci = false): Collection
    {
        return PeriodeJadwal::where('jadwal_id', $jadwalId)
            ->with('periode')
            ->when($kunci, fn ($query) => $query->lockForUpdate())
            ->get()
            ->sortBy(fn (PeriodeJadwal $row): string => sprintf('%010d-%s', $row->periode?->urutan ?? 0, (string) $row->periode_id))
            ->values();
    }

    /**
     * Periode efektif: jendela jadwal mulai dari `periode_mulai_id` snapshot.
     * Tahun master indikator sengaja diabaikan agar koreksi master ke atas
     * pasca-aktivasi tidak membuat periode yang sudah beku menjadi tak efektif.
     *
     * @param  Collection<int, PeriodeJadwal>  $jendela
     * @return Collection<int, string>
     */
    public function periodeEfektif(JadwalSnapshot $snapshot, Collection $jendela): Collection
    {
        $mulai = null;
        if (is_string($snapshot->periode_mulai_id)) {
            // Periode-mulai lazimnya anggota jendela yang periodenya sudah
            // dimuat; query hanya bila tidak ditemukan di sana.
            $mulai = $jendela->firstWhere('periode_id', $snapshot->periode_mulai_id)?->periode
                ?? Periode::whereKey($snapshot->periode_mulai_id)->first();
        }
        if ($mulai instanceof Periode) {
            $jendela = $jendela->filter(fn (PeriodeJadwal $row): bool => ($row->periode?->urutan ?? 0) >= $mulai->urutan);
        }

        return $jendela->map(fn (PeriodeJadwal $row): string => (string) $row->periode_id)->values();
    }
}
