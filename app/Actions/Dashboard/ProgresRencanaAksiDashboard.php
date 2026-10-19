<?php

namespace App\Actions\Dashboard;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use Illuminate\Support\Facades\DB;

class ProgresRencanaAksiDashboard
{
    private const ALUR = ['draft', 'diajukan', 'diverifikasi', 'dikembalikan', 'disahkan'];

    /**
     * Hitungan Rencana Aksi per status alur dari daftar indikator snapshot jadwal terpilih, sehingga indikator yang
     * belum memiliki RA terhitung "belum disusun"; indikator arsip tidak dihitung.
     *
     * @param  array<string, mixed>  $konteks  hasil ResolveDashboardKonteks
     * @return array<string, int>
     */
    public function handle(array $konteks): array
    {
        $jadwal = $konteks['jadwal'];
        $counts = $jadwal === null ? collect() : DB::query()
            // Satu baris per indikator: snapshot versi terbaru pada jadwal terpilih.
            ->fromSub(JadwalSnapshot::query()->selectRaw('DISTINCT ON (indikator_id) indikator_id, unit_id')->where('jadwal_id', $jadwal->id)
                ->orderBy('indikator_id')->orderByDesc('nomor_versi'), 'snap')
            ->join('indikator_kinerjas as i', 'i.id', '=', 'snap.indikator_id')
            ->leftJoin('rencana_aksi as ra', fn ($join) => $join->on('ra.indikator_id', '=', 'snap.indikator_id')->where('ra.tahun', $jadwal->tahun))
            ->where('i.status', '!=', IndikatorKinerja::STATUS_ARSIP)
            ->whereNotIn('snap.unit_id', $konteks['unitDitolak'])
            ->when($konteks['filter']['sasaran_id'], fn ($q, $id) => $q->where('i.sasaran_strategis_id', $id))
            ->when($konteks['filter']['unit_id'], fn ($q, $id) => $q->where('snap.unit_id', $id))
            ->selectRaw("COALESCE(ra.status_alur::text, 'belum_disusun') as status_ra, count(*) as total")
            ->groupByRaw("COALESCE(ra.status_alur::text, 'belum_disusun')")->pluck('total', 'status_ra');

        return collect(['belum_disusun', ...self::ALUR])->mapWithKeys(fn ($status) => [$status => (int) $counts->get($status, 0)])->all();
    }
}
