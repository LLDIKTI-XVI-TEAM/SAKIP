<?php

namespace App\Actions\Jadwal;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PeriodeJadwal;
use App\Models\TargetKinerja;
use App\Models\Unit;
use App\Models\User;
use App\Policies\JadwalTahunanPolicy;
use App\Services\Authorization\PermissionResolver;
use App\Services\Jadwal\SelisihSnapshotMaster;
use App\Support\PermissionCodes;
use Illuminate\Support\Collection;

class ShowSnapshotIndikator
{
    public function __construct(private readonly JadwalTahunanPolicy $policy, private readonly PermissionResolver $resolver,
        private readonly SelisihSnapshotMaster $selisih) {}

    /**
     * Snapshot versi terbaru per indikator jadwal, riwayat versi, dan selisihnya terhadap data master (Q79 §79.1, §79.5).
     * Dibaca dengan izin detail jadwal; seperti detail jadwal, isi indikator (nama, target, formula) hanya dikirim kepada
     * pemegang `indikator:read`, sehingga aktor yang hanya berizin aktivasi/penutupan tidak menerima data tersebut.
     * Jumlah baris dibatasi indikator Renstra jadwal sehingga tidak dipaginasi.
     * Selisih adalah pratinjau: koreksi menghitung ulang dan menilai izin per kolom di bawah lock.
     *
     * @return array<string, mixed>
     */
    public function handle(User $actor, string $jadwalId): array
    {
        $this->policy->viewAny($actor)->authorize();
        $jadwal = JadwalTahunan::with(['renstra:id,nama', 'periode.periode'])->findOrFail(strtolower($jadwalId));
        $bacaIndikator = $this->resolver->resolve($actor, PermissionCodes::INDIKATOR_READ)->allowed;
        $versi = $bacaIndikator ? JadwalSnapshot::with(['komponen', 'periodeMulai:id,nama,urutan'])->where('jadwal_id', $jadwal->id)
            ->orderBy('indikator_id')->orderBy('nomor_versi')->get()->groupBy('indikator_id') : collect();
        $master = IndikatorKinerja::with(['komponen' => fn ($query) => $query->where('aktif', true)->orderBy('urutan')->orderBy('kode')])
            ->whereIn('id', $versi->keys())->get()->keyBy('id');
        $targets = TargetKinerja::whereIn('indikator_kinerja_id', $versi->keys())->where('tahun', $jadwal->tahun)->get()->keyBy('indikator_kinerja_id');
        $units = Unit::whereIn('id', $versi->flatten()->pluck('unit_id')->merge($master->pluck('unit_id'))->unique()->values())->pluck('nama', 'id');
        $diterbitkan = AuditLog::whereIn('tindakan', ['jadwal_snapshot.buat', 'jadwal_snapshot.koreksi'])->where('objek_tipe', 'jadwal_snapshot')
            ->whereIn('objek_id', $versi->flatten()->pluck('id'))->get(['objek_id', 'waktu'])->mapWithKeys(fn (AuditLog $log): array => [$log->objek_id => $log->waktu]);
        $bolehKoreksi = $bacaIndikator && $jadwal->status === 'aktif' && ! $jadwal->tahunDitutup()
            && collect(SelisihSnapshotMaster::IZIN)->contains(fn (string $kode): bool => $this->resolver->resolve($actor, $kode)->allowed);

        $indikator = $versi->map(function (Collection $daftar, string $indikatorId) use ($master, $targets, $units, $diterbitkan): array {
            /** @var JadwalSnapshot $terbaru */
            $terbaru = $daftar->last();
            $sumber = $master->get($indikatorId);
            $selisih = $sumber ? $this->selisih->hitung($terbaru, $sumber, $targets->get($indikatorId)) : ['kolom' => [], 'komponen' => null, 'izin' => []];

            return [
                'indikator_id' => $indikatorId,
                'kode' => $sumber?->kode,
                'nama' => $terbaru->nama,
                'satuan' => $terbaru->satuan,
                'desimal_tampilan' => (int) $terbaru->desimal_tampilan,
                'unit' => $units->get($terbaru->unit_id),
                'target' => SelisihSnapshotMaster::desimal($terbaru->getRawOriginal('target')),
                'snapshot_id' => $terbaru->id,
                'nomor_versi' => $terbaru->nomor_versi,
                'periode_mulai' => $terbaru->periodeMulai?->only(['id', 'nama', 'urutan']),
                'selisih' => [
                    // Unit ditampilkan dengan namanya; kolom lain tetap nilai mentah yang diberi label di klien.
                    'kolom' => array_map(fn (array $row): array => $row['kolom'] === 'unit_id'
                        ? [...$row, 'lama' => $units->get($row['lama']), 'baru' => $units->get($row['baru'])] : $row, $selisih['kolom']),
                    'komponen' => $selisih['komponen'],
                ],
                'riwayat' => $daftar->sortByDesc('nomor_versi')->map(fn (JadwalSnapshot $row): array => [
                    'snapshot_id' => $row->id, 'nomor_versi' => $row->nomor_versi, 'periode_mulai' => $row->periodeMulai?->nama,
                    'alasan_koreksi' => $row->alasan_koreksi, 'rujukan_koreksi' => $row->rujukan_koreksi,
                    'diterbitkan_pada' => $diterbitkan->get($row->id)?->toIso8601String(),
                ])->values()->all(),
            ];
        })->sortBy(fn (array $row): string => $row['kode'] ?? $row['nama'])->values()->all();

        return [
            'jadwal' => [...$jadwal->only(['id', 'tahun', 'status']), 'renstra' => $jadwal->renstra?->nama],
            'periode' => $jadwal->periode->sortBy(fn (PeriodeJadwal $window): int => $window->periode->urutan)
                ->map(fn (PeriodeJadwal $window): array => ['id' => $window->periode_id, 'nama' => $window->periode->nama, 'urutan' => $window->periode->urutan])->values()->all(),
            'indikator' => $indikator,
            'can' => ['koreksi' => $bolehKoreksi, 'baca_indikator' => $bacaIndikator],
        ];
    }
}
