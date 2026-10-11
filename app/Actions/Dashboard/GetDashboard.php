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
    private const STATUSES = ['draft', 'diajukan', 'diverifikasi', 'dikembalikan', 'disahkan'];

    public function __construct(private PermissionResolver $permissions, private PresentPengukuran $present) {}

    /** Ringkasan dibatasi unit yang boleh dibaca; nilai lintas satuan tidak dirata-ratakan. */
    public function handle(User $actor): array
    {
        abort_unless($this->permissions->allows($actor, 'dashboard:read'), 403);
        $today = today(config('app.business_timezone'));
        $year = $today->year;
        $renstra = Renstra::where('is_aktif', true)->where('tahun_mulai', '<=', $year)->where('tahun_selesai', '>=', $year)
            ->orderByDesc('tahun_mulai')->orderBy('id')->first();
        $jadwal = $renstra ? JadwalTahunan::where('renstra_id', $renstra->id)
            ->where('tahun', $year)->orderByRaw("case when status = 'aktif' then 0 else 1 end")->orderBy('id')->first() : null;
        $windows = PeriodeJadwal::with('periode')->when($jadwal, fn ($q) => $q->where('jadwal_id', $jadwal->id));
        $schedule = $jadwal ? (clone $windows)->orderBy('pengisian_mulai')->orderBy('id')->get() : collect();
        $activeWindow = $jadwal ? (clone $windows)->whereDate('pengisian_mulai', '<=', $today)->whereDate('pengisian_selesai', '>=', $today)->first() : null;
        $window = $activeWindow ?? ($jadwal ? $windows->whereDate('pengisian_mulai', '<=', $today)->orderByDesc('pengisian_mulai')->orderBy('id')->first() : null);
        $deniedUnits = $this->permissions->unitDitolak($actor, 'dashboard:read');
        $scheduleQuery = PengukuranKinerja::query()->when($jadwal,
            fn ($q) => $q->where('tahun', $jadwal->tahun)->whereHas('jadwalSnapshot', fn ($snapshot) => $snapshot->where('jadwal_id', $jadwal->id)),
            fn ($q) => $q->whereRaw('1 = 0'))
            ->whereNotIn(DB::raw(PengukuranKinerja::targetUnitSql()), $deniedUnits);
        // Satu kueri hitungan periode × status melayani ringkasan periode berjalan dan grafik progres seluruh periode jadwal.
        $counts = (clone $scheduleQuery)->selectRaw('periode_id, status_alur, count(*) as total')->groupBy('periode_id', 'status_alur')->get()
            ->groupBy('periode_id')->map(fn ($rows) => $rows->pluck('total', 'status_alur'));
        $tally = function (?string $periodeId) use ($counts): array {
            $row = $periodeId === null ? collect() : $counts->get($periodeId, collect());
            $result = ['total' => (int) $row->sum()];
            foreach (self::STATUSES as $status) {
                $result[$status] = (int) ($row[$status] ?? 0);
            }

            return $result;
        };
        $canRead = $this->permissions->allows($actor, 'pengukuran:read');
        $readDenies = $this->permissions->unitDitolak($actor, 'pengukuran:read')->pluck('unit_id')->all();
        $measurements = (clone $scheduleQuery)->when($window, fn ($q) => $q->where('periode_id', $window->periode_id), fn ($q) => $q->whereRaw('1 = 0'))
            ->with(['indikator', 'periode', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])
            ->orderByDesc('updated_at')->orderBy('id')->limit(20)->get();
        $this->present->prepareSummary($measurements);
        $rows = $measurements->map(function (PengukuranKinerja $item) use ($actor, $canRead, $readDenies) {
            $data = $this->present->handle($item, $actor);
            $indicator = $data['penugasan_indikator']['indikator_kinerja'];

            return [
                'id' => $data['id'], 'status' => $data['status'], 'nilai' => $data['nilai'], 'status_perhitungan' => $data['status_perhitungan'], 'self_approval' => $data['self_approval'],
                'reviu_terlambat' => $data['reviu_terlambat'],
                'satuan' => $indicator['satuan'], 'desimal_tampilan' => $indicator['desimal_tampilan'],
                'indikator' => ['kode' => $indicator['kode'], 'nama' => $indicator['nama']],
                'unit' => ['nama' => $data['penugasan_indikator']['unit_kerja']['nama']],
                'pic' => $data['penugasan_indikator']['pic'] ? ['nama' => $data['penugasan_indikator']['pic']['nama']] : null,
                'action' => $canRead && ! in_array($item->targetUnitId(), $readDenies, true) ? ['href' => route('pengukuran.edit', $item->id), 'label' => 'Lihat pengukuran'] : null,
            ];
        });

        return [
            'tahun' => $year,
            'activeRenstra' => $renstra?->only(['id', 'nama', 'tahun_mulai', 'tahun_selesai']),
            'activeJadwal' => $jadwal?->only(['tahun', 'status']),
            'activePeriode' => $window ? [
                'id' => $window->periode_id,
                'nama_periode' => $window->periode->nama.' '.$jadwal->tahun,
                'status' => $activeWindow ? 'aktif' : 'terakhir',
            ] : null,
            'stats' => $tally($window?->periode_id),
            'progresPeriode' => $schedule->map(fn (PeriodeJadwal $item) => ['periode_id' => $item->periode_id, 'nama' => $item->periode->nama, ...$tally($item->periode_id)])->values()->all(),
            'pengukurans' => $rows,
        ];
    }
}
