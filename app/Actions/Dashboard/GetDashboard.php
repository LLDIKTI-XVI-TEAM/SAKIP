<?php

namespace App\Actions\Dashboard;

use App\Actions\Pengukuran\PresentPengukuran;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\Renstra;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Support\Facades\DB;

class GetDashboard
{
    public function __construct(private PermissionResolver $permissions, private PresentPengukuran $present) {}

    /** Ringkasan dibatasi unit yang boleh dibaca; nilai lintas satuan tidak dirata-ratakan. */
    public function handle(User $actor): array
    {
        abort_unless($this->permissions->allows($actor, 'dashboard:read'), 403);
        $renstra = Renstra::where('is_aktif', true)->first();
        $jadwal = JadwalTahunan::when($renstra, fn ($q) => $q->where('renstra_id', $renstra->id))
            ->orderByRaw("case when status = 'aktif' then 0 else 1 end")->orderByDesc('tahun')->orderBy('id')->first();
        $windows = PeriodeJadwal::with('periode')->when($jadwal, fn ($q) => $q->where('jadwal_id', $jadwal->id));
        $window = $jadwal ? (clone $windows)->whereDate('pengisian_mulai', '<=', today())->whereDate('pengisian_selesai', '>=', today())->first() : null;
        $window ??= $jadwal ? $windows->orderByDesc('pengisian_mulai')->orderBy('id')->first() : null;
        $deniedUnits = DB::table('user_permission_denied')->join('permissions', 'permissions.id', '=', 'permission_id')
            ->where('user_id', $actor->id)->where('permissions.kode', 'dashboard:read')->whereNotNull('unit_id')->select('unit_id');
        $query = PengukuranKinerja::query()->when($jadwal, fn ($q) => $q->where('tahun', $jadwal->tahun)->whereHas('jadwalSnapshot', fn ($snapshot) => $snapshot->where('jadwal_id', $jadwal->id)))
            ->when($window, fn ($q) => $q->where('periode_id', $window->periode_id))
            ->whereNotIn(DB::raw(PengukuranKinerja::targetUnitSql()), $deniedUnits);
        $counts = (clone $query)->selectRaw('status_alur, count(*) as total')->groupBy('status_alur')->pluck('total', 'status_alur');
        $canRead = $this->permissions->allows($actor, 'pengukuran:read');
        $readDenies = DB::table('user_permission_denied')->join('permissions', 'permissions.id', '=', 'permission_id')
            ->where('user_id', $actor->id)->where('permissions.kode', 'pengukuran:read')->whereNotNull('unit_id')->pluck('unit_id')->all();
        $measurements = (clone $query)->with(['indikator', 'periode', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])
            ->orderByDesc('updated_at')->orderBy('id')->limit(20)->get();
        $this->present->prepareSummary($measurements);
        $rows = $measurements->map(function (PengukuranKinerja $item) use ($actor, $canRead, $readDenies) {
            $data = $this->present->handle($item, $actor);
            $indicator = $data['penugasan_indikator']['indikator_kinerja'];

            return [
                'id' => $data['id'], 'status' => $data['status'], 'nilai' => $data['nilai'], 'status_perhitungan' => $data['status_perhitungan'], 'self_approval' => $data['self_approval'],
                'satuan' => $indicator['satuan'], 'desimal_tampilan' => $indicator['desimal_tampilan'],
                'indikator' => ['kode' => $indicator['kode'], 'nama' => $indicator['nama']],
                'unit' => ['nama' => $data['penugasan_indikator']['unit_kerja']['nama']],
                'pic' => $data['penugasan_indikator']['pic'] ? ['nama' => $data['penugasan_indikator']['pic']['nama']] : null,
                'action' => $canRead && ! in_array($item->targetUnitId(), $readDenies, true) ? ['href' => route('pengukuran.edit', $item->id), 'label' => 'Lihat pengukuran'] : null,
            ];
        });
        $stats = ['total' => (int) $counts->sum()];
        foreach (['draft', 'diajukan', 'diverifikasi', 'dikembalikan', 'disahkan'] as $status) {
            $stats[$status] = (int) ($counts[$status] ?? 0);
        }

        return [
            'activeRenstra' => $renstra?->only(['id', 'nama', 'tahun_mulai', 'tahun_selesai']),
            'activePeriode' => $window ? ['id' => $window->periode_id, 'nama_periode' => $window->periode->nama.' '.$jadwal->tahun] : null,
            'stats' => $stats, 'pengukurans' => $rows,
        ];
    }
}
