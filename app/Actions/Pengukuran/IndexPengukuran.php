<?php

namespace App\Actions\Pengukuran;

use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\Renstra;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Support\Facades\DB;

class IndexPengukuran
{
    public function __construct(private PresentPengukuran $present, private PermissionResolver $resolver) {}

    /**
     * Daftar pengukuran periode berjalan: Renstra aktif tahun ini → jadwal tahun ini (aktif diutamakan) → periode pengisian
     * terakhir yang sudah dimulai. Unit yang di-deny `pengukuran:read` disaring di database (deny menang atas izin global).
     *
     * @return array<string, mixed>
     */
    public function handle(User $actor): array
    {
        $today = today(config('app.business_timezone'));
        $renstra = Renstra::where('is_aktif', true)->where('tahun_mulai', '<=', $today->year)->where('tahun_selesai', '>=', $today->year)
            ->orderByDesc('tahun_mulai')->orderBy('id')->first();
        $jadwal = $renstra ? JadwalTahunan::where('renstra_id', $renstra->id)->where('tahun', $today->year)
            ->orderByRaw("case when status = 'aktif' then 0 else 1 end")->orderBy('id')->first() : null;
        $periode = $jadwal ? PeriodeJadwal::with('periode')->where('jadwal_id', $jadwal->id)->whereDate('pengisian_mulai', '<=', $today)->orderByDesc('pengisian_mulai')->first() : null;
        $deniedUnits = $this->resolver->unitDitolak($actor, 'pengukuran:read');
        $page = PengukuranKinerja::with(['indikator', 'periode', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])
            ->when($periode, fn ($query) => $query->where('periode_id', $periode->periode_id)->where('tahun', $jadwal->tahun)
                ->whereHas('jadwalSnapshot', fn ($context) => $context->where('jadwal_id', $jadwal->id)),
                fn ($query) => $query->whereRaw('1 = 0'))
            ->whereNotIn(DB::raw(PengukuranKinerja::targetUnitSql()), $deniedUnits)
            ->withCount(['buktiDukungs' => fn ($query) => $query->current()])->orderBy('id')->paginate(20)->withQueryString();

        $this->present->prepareSummary($page->getCollection());

        return [
            'periode' => $periode ? ['id' => $periode->periode_id, 'tahun' => $jadwal->tahun, 'urutan' => $periode->periode->urutan, 'nama_periode' => $periode->periode->nama] : null,
            'pengukurans' => $page->getCollection()->map(fn ($item) => $this->present->handle($item, $actor))->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
        ];
    }
}
