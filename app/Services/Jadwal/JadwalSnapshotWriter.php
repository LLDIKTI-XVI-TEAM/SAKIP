<?php

namespace App\Services\Jadwal;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JadwalTahunan;
use App\Models\TargetKinerja;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Str;

/**
 * Membekukan satu pasangan jadwal × indikator menjadi snapshot versi 1, atau menerbitkan versi koreksinya,
 * beserta komponen dan audit.
 *
 * Dipisah karena dipakai bersama oleh ActivateJadwal (seluruh indikator baru saat aktivasi),
 * TambahIndikatorJadwal (satu indikator pada jadwal aktif), dan KoreksiSnapshot (versi pengganti),
 * agar isi beku dan bentuk audit tidak bercabang.
 * Service tidak memeriksa izin, tidak membuka transaksi, tidak mengunci, dan tidak menilai kelayakan.
 * Caller memegang transaksi serta lock indikator, target, dan komponen aktif, dan sudah memastikan
 * indikator dapat dibekukan serta target tidak null. `$indikator->komponen` harus berisi komponen
 * aktif yang sudah dimuat dan terurut. Nilai decimal disalin sebagai string mentah numeric(30,12).
 */
class JadwalSnapshotWriter
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Snapshot baru, komponen, finalisasi komposisi, dan audit `jadwal_snapshot.buat`; mengembalikan ID snapshot.
     *
     * @param  array<string, mixed>  $dasarIzin
     */
    public function bekukan(User $actor, JadwalTahunan $jadwal, IndikatorKinerja $indikator, TargetKinerja $target,
        string $periodeMulaiId, string $alasan, array $dasarIzin): string
    {
        [$snapshot, $komponen] = $this->sisipkan(['jadwal_id' => $jadwal->id, 'nomor_versi' => 1], $indikator, $target, $periodeMulaiId);
        $this->audit->catat(actor: $actor, tindakan: 'jadwal_snapshot.buat', objekTipe: 'jadwal_snapshot', objekId: $snapshot->id,
            nilaiBaru: $this->konteksAudit($snapshot, $komponen), alasan: $alasan, dasarIzin: $dasarIzin);

        return $snapshot->id;
    }

    /**
     * Versi pengganti `nomor_versi + 1` berisi data master terkini (Q35 §35.1, Q79 §79.1) beserta komponen,
     * finalisasi, dan audit `jadwal_snapshot.koreksi` berisi konteks versi lama dan baru; versi lama tidak disentuh.
     * Caller sudah mengunci `$pendahulu` sebagai versi terbaru pasangan jadwal × indikator.
     *
     * @param  array<string, mixed>  $dasarIzin
     */
    public function terbitkanKoreksi(User $actor, JadwalSnapshot $pendahulu, IndikatorKinerja $indikator, TargetKinerja $target,
        string $periodeMulaiId, string $alasan, string $rujukan, array $dasarIzin): string
    {
        [$snapshot, $komponen] = $this->sisipkan(['jadwal_id' => $pendahulu->jadwal_id, 'nomor_versi' => $pendahulu->nomor_versi + 1,
            'menggantikan_id' => $pendahulu->id, 'alasan_koreksi' => $alasan, 'rujukan_koreksi' => $rujukan], $indikator, $target, $periodeMulaiId);
        $komponenLama = $pendahulu->komponen->map(fn (JadwalSnapshotKomponen $row): array => [
            'komponen_id' => $row->komponen_id, 'kode' => $row->kode, 'label' => $row->label, 'peran' => $row->peran, 'bobot' => $row->getRawOriginal('bobot'), 'urutan' => $row->urutan,
        ])->all();
        // Definisi dan label komponen ikut dicatat karena keduanya termasuk selisih yang dapat dikoreksi.
        $this->audit->catat(actor: $actor, tindakan: 'jadwal_snapshot.koreksi', objekTipe: 'jadwal_snapshot', objekId: $snapshot->id,
            nilaiLama: [...$this->konteksAudit($pendahulu, $komponenLama, (bool) $pendahulu->komposisi_final, denganLabel: true), 'definisi' => $pendahulu->definisi],
            nilaiBaru: [...$this->konteksAudit($snapshot, $komponen, denganLabel: true), 'definisi' => $snapshot->definisi,
                'menggantikan_id' => $pendahulu->id, 'rujukan_koreksi' => $rujukan],
            alasan: $alasan, dasarIzin: $dasarIzin);

        return $snapshot->id;
    }

    /**
     * @param  array<string, mixed>  $versi
     * @return array{0: JadwalSnapshot, 1: list<array<string, mixed>>}
     */
    private function sisipkan(array $versi, IndikatorKinerja $indikator, TargetKinerja $target, string $periodeMulaiId): array
    {
        $snapshot = JadwalSnapshot::create([
            ...$versi, 'indikator_id' => $indikator->id, 'periode_mulai_id' => $periodeMulaiId,
            'unit_id' => $indikator->unit_id, 'nama' => $indikator->nama, 'definisi' => $indikator->definisi_operasional, 'satuan' => $indikator->satuan,
            'presisi' => $indikator->presisi, 'desimal_tampilan' => $indikator->desimal_tampilan, 'arah' => $indikator->arah,
            'tipe_perhitungan' => $indikator->tipe_perhitungan, 'dinilai_tahunan' => $indikator->dinilai_tahunan,
            'target' => $target->getRawOriginal('target_tahunan'), 'baseline' => $target->getRawOriginal('baseline'),
        ]);
        $komponen = $indikator->komponen->map(fn (IndikatorKomponen $row): array => [
            'komponen_id' => $row->id, 'kode' => $row->kode, 'label' => $row->label, 'peran' => $row->peran, 'bobot' => $row->getRawOriginal('bobot'), 'urutan' => $row->urutan,
        ])->values()->all();
        // Satu INSERT per snapshot agar lock yang dipegang caller tidak tertahan oleh round-trip per komponen.
        JadwalSnapshotKomponen::insert(array_map(fn (array $row): array => ['id' => (string) Str::uuid(), 'jadwal_snapshot_id' => $snapshot->id, ...$row], $komponen));
        // Finalisasi komposisi: komposisi beku sejak terbit. Setelah flag ini true,
        // guard INSERT menolak child tambahan sehingga rumus yang dilihat pembaca RA tidak dapat
        // berubah tanpa versi baru. Hanya kolom flag yang berubah, jadi guard UPDATE (yang
        // membandingkan seluruh kolom beku lain) meloloskannya.
        JadwalSnapshot::whereKey($snapshot->id)->update(['komposisi_final' => true]);

        return [$snapshot, $komponen];
    }

    /**
     * @param  list<array<string, mixed>>  $komponen
     * @return array<string, mixed>
     */
    private function konteksAudit(JadwalSnapshot $snapshot, array $komponen, bool $final = true, bool $denganLabel = false): array
    {
        return [...$snapshot->only(['jadwal_id', 'indikator_id', 'nomor_versi', 'periode_mulai_id', 'unit_id', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'dinilai_tahunan']),
            'komposisi_final' => $final,
            'target' => $snapshot->getRawOriginal('target'), 'baseline' => $snapshot->getRawOriginal('baseline'),
            'komponen' => $denganLabel ? $komponen : array_map(fn (array $row): array => array_diff_key($row, ['label' => true]), $komponen)];
    }
}
