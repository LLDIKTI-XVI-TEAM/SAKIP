<?php

namespace App\Actions\Dashboard;

use App\Models\Kegiatan;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Database\Eloquent\Builder;

class KegiatanDashboard
{
    private const BATAS_DAFTAR = 20;

    public function __construct(private PermissionResolver $permissions) {}

    /**
     * Status kegiatan periode terpilih dari data kerja (belum diverifikasi, Q45 §45.3). Hitungan mengikuti akses dashboard;
     * nama kegiatan terlambat hanya untuk pembaca kegiatan pada unit berizin (Q54 §54.1).
     *
     * @param  array<string, mixed>  $konteks  hasil ResolveDashboardKonteks
     * @return array{per_status: array<string, int>, terlambat_jumlah: int, per_iku: list<array<string, mixed>>, terlambat: list<array<string, mixed>>|null}
     */
    public function handle(User $actor, array $konteks, string $today): array
    {
        $bolehBaca = $this->permissions->allows($actor, PermissionCodes::KEGIATAN_READ);
        $jadwal = $konteks['jadwal'];
        $periode = $konteks['periodeJadwal'];
        if ($jadwal === null || $periode === null) {
            return ['per_status' => array_fill_keys(Kegiatan::STATUSES, 0), 'terlambat_jumlah' => 0, 'per_iku' => [], 'terlambat' => $bolehBaca ? [] : null];
        }
        $sasaran = $konteks['filter']['sasaran_id'];
        $base = Kegiatan::dalamCakupanJadwal($jadwal)->where('kegiatan.periode_id', $periode->periode_id)
            ->whereNotIn('kegiatan.unit_id', $konteks['unitDitolak'])
            ->when($konteks['filter']['unit_id'], fn (Builder $q, $id) => $q->where('kegiatan.unit_id', $id))
            ->when($sasaran, fn (Builder $q, $id) => $q->whereExists(fn ($sub) => $sub->from('klaim_kegiatan as kk')
                ->join('rencana_aksi as ra', 'ra.id', '=', 'kk.rencana_aksi_id')->join('indikator_kinerjas as i', 'i.id', '=', 'ra.indikator_id')
                ->whereColumn('kk.kegiatan_id', 'kegiatan.id')->where('ra.jadwal_tahunan_id', $jadwal->id)->where('i.sasaran_strategis_id', $id)));
        $perStatus = (clone $base)->toBase()->selectRaw('kegiatan.status, count(*) as total')->groupBy('kegiatan.status')->pluck('total', 'status');
        $terlambat = (clone $base)->where('kegiatan.status', 'rencana')->whereDate('kegiatan.tanggal_rencana', '<', $today);
        // Kegiatan bersama dihitung di setiap IKU yang mengklaimnya; total per status di atas tetap dari kegiatan unik.
        $perIku = (clone $base)->toBase()->join('klaim_kegiatan as kk', 'kk.kegiatan_id', '=', 'kegiatan.id')
            ->join('rencana_aksi as ra', 'ra.id', '=', 'kk.rencana_aksi_id')->join('indikator_kinerjas as i', 'i.id', '=', 'ra.indikator_id')
            ->where('ra.jadwal_tahunan_id', $jadwal->id)
            ->when($sasaran, fn ($q, $id) => $q->where('i.sasaran_strategis_id', $id))
            ->selectRaw('i.id, i.kode, i.nama, kegiatan.status, count(distinct kegiatan.id) as total')
            ->groupBy('i.id', 'i.kode', 'i.nama', 'kegiatan.status')->orderBy('i.kode')->get()->groupBy('id')
            ->map(fn ($rows) => ['id' => $rows->first()->id, 'kode' => $rows->first()->kode, 'nama' => $rows->first()->nama,
                'jumlah' => array_merge(array_fill_keys(Kegiatan::STATUSES, 0), $rows->mapWithKeys(fn ($row) => [$row->status => (int) $row->total])->all())])
            ->values()->all();

        return [
            'per_status' => array_merge(array_fill_keys(Kegiatan::STATUSES, 0), $perStatus->map(fn ($total) => (int) $total)->all()),
            'terlambat_jumlah' => (clone $terlambat)->count(),
            'per_iku' => $perIku,
            'terlambat' => ! $bolehBaca ? null : (clone $terlambat)->join('unit', 'unit.id', '=', 'kegiatan.unit_id')
                ->whereNotIn('kegiatan.unit_id', $this->permissions->unitDitolak($actor, PermissionCodes::KEGIATAN_READ))
                ->when($this->permissions->unitTerbaca($actor, PermissionCodes::KEGIATAN_READ), fn (Builder $q, $terbaca) => $q->whereIn('kegiatan.unit_id', $terbaca))
                ->orderBy('kegiatan.tanggal_rencana')->orderBy('kegiatan.nama')->limit(self::BATAS_DAFTAR)
                ->get(['kegiatan.id', 'kegiatan.nama', 'unit.nama as unit', 'kegiatan.tanggal_rencana'])
                ->map(fn (Kegiatan $k) => ['id' => $k->id, 'nama' => $k->nama, 'unit' => $k->getAttribute('unit'), 'tanggal_rencana' => $k->tanggal_rencana?->toDateString()])->all(),
        ];
    }
}
