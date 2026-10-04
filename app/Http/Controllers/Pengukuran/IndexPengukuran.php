<?php

namespace App\Http\Controllers\Pengukuran;

use App\Actions\Pengukuran\PresentPengukuran;
use App\Http\Controllers\Controller;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\Renstra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IndexPengukuran extends Controller
{
    public function __invoke(Request $request, PresentPengukuran $present): Response
    {
        Gate::authorize('viewAny', PengukuranKinerja::class);
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,20,25,50,100'],
        ]);
        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 20, 25, 50, 100], true)) {
            $perPage = 10;
        }
        $actor = $request->user();
        $today = today(config('app.business_timezone'));
        $renstra = Renstra::where('is_aktif', true)->where('tahun_mulai', '<=', $today->year)->where('tahun_selesai', '>=', $today->year)
            ->orderByDesc('tahun_mulai')->orderBy('id')->first();
        $jadwal = $renstra ? JadwalTahunan::where('renstra_id', $renstra->id)->where('tahun', $today->year)
            ->orderByRaw("case when status = 'aktif' then 0 else 1 end")->orderBy('id')->first() : null;
        $periode = $jadwal ? PeriodeJadwal::with('periode')->where('jadwal_id', $jadwal->id)->whereDate('pengisian_mulai', '<=', $today)->orderByDesc('pengisian_mulai')->first() : null;
        $deniedUnits = DB::table('user_permission_denied')->join('permissions', 'permissions.id', '=', 'user_permission_denied.permission_id')
            ->where('user_id', $actor->id)->where('permissions.kode', 'pengukuran:read')->whereNotNull('unit_id')->select('unit_id');
        $page = PengukuranKinerja::with(['indikator', 'periode', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])
            ->when($periode, fn ($query) => $query->where('periode_id', $periode->periode_id)->where('tahun', $jadwal->tahun)
                ->whereHas('jadwalSnapshot', fn ($context) => $context->where('jadwal_id', $jadwal->id)),
                fn ($query) => $query->whereRaw('1 = 0'))
            ->whereNotIn(DB::raw(PengukuranKinerja::targetUnitSql()), $deniedUnits)
            ->withCount(['buktiDukungs' => fn ($query) => $query->current()])->orderBy('id')->paginate($perPage)->withQueryString();

        $present->prepareSummary($page->getCollection());

        return Inertia::render('Pengukuran/Index', [
            'periode' => $periode ? ['id' => $periode->periode_id, 'tahun' => $jadwal->tahun, 'urutan' => $periode->periode->urutan, 'nama_periode' => $periode->periode->nama] : null,
            'pengukurans' => $page->getCollection()->map(fn ($item) => $present->handle($item, $actor))->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'total' => $page->total(),
                'prev_page_url' => $page->previousPageUrl(),
                'next_page_url' => $page->nextPageUrl(),
                'links' => $page->linkCollection()->toArray(),
            ],
            'filters' => $request->only(['per_page', 'page']),
        ]);
    }
}
