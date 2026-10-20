<?php

namespace App\Actions\Dashboard;

use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\PeriodeJadwal;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ResolveDashboardKonteks
{
    private const STATUS_JADWAL = ['aktif', 'ditutup'];

    public function __construct(private PermissionResolver $permissions) {}

    /**
     * Konteks dashboard dari filter tervalidasi: Renstra, tahun, jadwal (aktif lalu ditutup, tidak pernah draf), periode yang
     * sudah dibuka, serta pilihan sasaran dan unit. Nilai di luar pilihan ditolak agar request langsung tidak membuka data lain.
     *
     * @param  array{renstra_id?: ?string, tahun?: ?int, periode_id?: ?string, sasaran_id?: ?string, unit_id?: ?string}  $filter
     * @return array<string, mixed>
     */
    public function handle(User $actor, array $filter, string $today): array
    {
        $unitDitolak = $this->permissions->unitDitolak($actor, PermissionCodes::DASHBOARD_READ);
        $year = (int) substr($today, 0, 4);
        $renstraOptions = Renstra::whereIn('id', JadwalTahunan::whereIn('status', self::STATUS_JADWAL)->select('renstra_id'))
            ->orderByDesc('tahun_mulai')->orderBy('id')->get(['id', 'nama', 'tahun_mulai', 'tahun_selesai']);
        $renstraAktif = Renstra::where('is_aktif', true)->where('tahun_mulai', '<=', $year)->where('tahun_selesai', '>=', $year)->orderByDesc('tahun_mulai')->orderBy('id')->first();
        // Renstra aktif tanpa jadwal aktif/ditutup tetap ditawarkan agar pengguna dapat berpindah dari dan ke Renstra itu.
        if ($renstraAktif && ! $renstraOptions->contains('id', $renstraAktif->id)) {
            $renstraOptions->prepend($renstraAktif);
        }
        $renstra = isset($filter['renstra_id'])
            ? $renstraOptions->firstWhere('id', strtolower($filter['renstra_id'])) ?? $this->tolak('renstra_id', 'Pilih Renstra yang tersedia.')
            : $renstraAktif;

        $schedules = $renstra ? JadwalTahunan::where('renstra_id', $renstra->id)->whereIn('status', self::STATUS_JADWAL)
            ->orderByRaw("CASE WHEN status = 'aktif' THEN 0 ELSE 1 END")->orderBy('id')->get() : collect();
        $years = $schedules->pluck('tahun')->unique()->sortDesc()->values();
        if (isset($filter['tahun']) && ! $years->contains((int) $filter['tahun'])) {
            $this->tolak('tahun', 'Pilih tahun yang memiliki jadwal pada Renstra terpilih.');
        }
        $tahun = isset($filter['tahun']) ? (int) $filter['tahun'] : ($years->contains($year) ? $year : $years->first());
        $jadwal = $tahun === null ? null : $schedules->firstWhere('tahun', $tahun);

        $semuaPeriode = $jadwal ? PeriodeJadwal::with('periode')->where('jadwal_id', $jadwal->id)->get()->sortBy('periode.urutan')->values() : collect();
        // Jadwal ditutup berhenti pada periode yang sudah dimulai saat penutupan, sama dengan halaman Pengukuran.
        $batas = $jadwal?->status === 'ditutup' && $jadwal->closed_at
            ? min($today, Carbon::parse($jadwal->closed_at)->timezone(config('app.business_timezone'))->toDateString()) : $today;
        $periodeDibuka = $semuaPeriode->filter(fn (PeriodeJadwal $p) => $p->pengisian_mulai->toDateString() <= $batas)->values();
        // Jadwal ditutup tidak lagi memiliki jendela pengisian yang berjalan.
        $aktif = $jadwal?->status === 'ditutup' ? null : $periodeDibuka->first(fn (PeriodeJadwal $p) => $p->pengisian_selesai->toDateString() >= $today);
        $periodeJadwal = isset($filter['periode_id'])
            ? $periodeDibuka->firstWhere('periode_id', strtolower($filter['periode_id'])) ?? $this->tolak('periode_id', 'Pilih periode jadwal yang sudah dibuka.')
            : $aktif ?? $periodeDibuka->sortByDesc(fn (PeriodeJadwal $p) => $p->pengisian_mulai->toDateString())->first();

        $snapshot = JadwalSnapshot::where('jadwal_id', $jadwal?->id);
        $sasaranOptions = $jadwal ? SasaranStrategis::whereIn('id', (clone $snapshot)->join('indikator_kinerjas as i', 'i.id', '=', 'jadwal_snapshot.indikator_id')
            ->select('i.sasaran_strategis_id'))->orderBy('urutan')->orderBy('kode')->get(['id', 'kode', 'deskripsi']) : collect();
        // Unit pemilik kegiatan dalam cakupan jadwal ikut ditawarkan agar agregat panel kegiatan dapat disaring (Q64 §64.2 butir 4).
        $unitOptions = $jadwal ? Unit::where(fn ($q) => $q->whereIn('id', (clone $snapshot)->select('unit_id'))->orWhereIn('id', Kegiatan::dalamCakupanJadwal($jadwal)->select('unit_id')))
            ->whereNotIn('id', $unitDitolak)->orderBy('nama')->get(['id', 'nama']) : collect();
        $sasaranId = $this->pilih($filter, 'sasaran_id', $sasaranOptions->pluck('id')->all(), 'Pilih sasaran pada jadwal terpilih.');
        $unitId = $this->pilih($filter, 'unit_id', $unitOptions->pluck('id')->all(), 'Pilih unit yang dapat Anda lihat pada jadwal terpilih.');

        return [
            'renstra' => $renstra, 'jadwal' => $jadwal, 'periodeJadwal' => $periodeJadwal, 'periodeAktif' => $periodeJadwal !== null && $periodeJadwal === $aktif,
            'periodeDibuka' => $periodeDibuka, 'semuaPeriode' => $semuaPeriode, 'unitDitolak' => $unitDitolak, 'tahun' => $tahun ?? $year, 'batas' => $batas,
            'filter' => ['renstra_id' => $renstra?->id, 'tahun' => $tahun, 'periode_id' => $periodeJadwal?->periode_id, 'sasaran_id' => $sasaranId, 'unit_id' => $unitId],
            'pilihan' => [
                'renstra' => $renstraOptions->map->only(['id', 'nama'])->values()->all(),
                'tahun' => $years->all(),
                'periode' => $periodeDibuka->map(fn (PeriodeJadwal $p) => ['id' => $p->periode_id, 'nama' => $p->periode->nama])->all(),
                'sasaran' => $sasaranOptions->map->only(['id', 'kode', 'deskripsi'])->values()->all(),
                'unit' => $unitOptions->map->only(['id', 'nama'])->values()->all(),
            ],
        ];
    }

    /** @param  list<string>  $allowed */
    private function pilih(array $filter, string $key, array $allowed, string $message): ?string
    {
        if (! isset($filter[$key])) {
            return null;
        }

        return in_array(strtolower($filter[$key]), $allowed, true) ? strtolower($filter[$key]) : $this->tolak($key, $message);
    }

    private function tolak(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message])->redirectTo(route('dashboard'));
    }
}
