<?php

namespace App\Actions\Dashboard;

use App\Actions\Pengukuran\PresentPengukuran;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Support\Facades\DB;

class GetDashboard
{
    private const STATUSES = ['draft', 'diajukan', 'diverifikasi', 'dikembalikan', 'disahkan'];

    public function __construct(private PermissionResolver $permissions, private PresentPengukuran $present, private ResolveDashboardKonteks $konteks) {}

    /**
     * Ringkasan dibatasi unit yang boleh dibaca; nilai lintas satuan tidak dirata-ratakan.
     *
     * @param  array<string, mixed>  $filter  query tervalidasi IndexDashboardRequest
     */
    public function handle(User $actor, array $filter): array
    {
        $today = today(config('app.business_timezone'))->toDateString();
        $konteks = $this->konteks->handle($actor, $filter, $today);
        [$renstra, $jadwal, $window] = [$konteks['renstra'], $konteks['jadwal'], $konteks['periodeJadwal']];
        $schedule = $konteks['semuaPeriode'];
        $deniedUnits = $konteks['unitDitolak'];
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
            'tahun' => $konteks['tahun'],
            'filter' => $konteks['filter'],
            'pilihan' => $konteks['pilihan'],
            'activeRenstra' => $renstra?->only(['id', 'nama', 'tahun_mulai', 'tahun_selesai']),
            'activeJadwal' => $jadwal?->only(['tahun', 'status']),
            'activePeriode' => $window ? [
                'id' => $window->periode_id,
                'nama_periode' => $window->periode->nama.' '.$jadwal->tahun,
                'status' => $konteks['periodeAktif'] ? 'aktif' : 'terakhir',
            ] : null,
            'stats' => $tally($window?->periode_id),
            'progresPeriode' => $schedule->map(fn (PeriodeJadwal $item) => ['periode_id' => $item->periode_id, 'nama' => $item->periode->nama, ...$tally($item->periode_id)])->values()->all(),
            'pengukurans' => $rows,
        ];
    }
}
