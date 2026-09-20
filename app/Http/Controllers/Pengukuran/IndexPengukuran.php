<?php

namespace App\Http\Controllers\Pengukuran;

use App\Actions\Pengukuran\PresentPengukuran;
use App\Http\Controllers\Controller;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
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
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $actor = $request->user();
        $today = today(config('app.business_timezone'));
        $jadwal = JadwalTahunan::where('tahun', $today->year)
            ->orderByRaw("case when status = 'aktif' then 0 else 1 end")->orderBy('id')->first();
        $periode = $jadwal ? PeriodeJadwal::with('periode')->where('jadwal_id', $jadwal->id)->whereDate('pengisian_mulai', '<=', $today)->orderByDesc('pengisian_mulai')->first() : null;
        $deniedUnits = DB::table('user_permission_denied')->join('permissions', 'permissions.id', '=', 'user_permission_denied.permission_id')
            ->where('user_id', $actor->id)->where('permissions.kode', 'pengukuran:read')->whereNotNull('unit_id')->select('unit_id');
        $page = PengukuranKinerja::with(['indikator', 'periode', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])
            ->when($periode, fn ($query) => $query->where('periode_id', $periode->periode_id)->where('tahun', $jadwal->tahun)
                ->whereHas('jadwalSnapshot', fn ($context) => $context->where('jadwal_id', $jadwal->id)),
                fn ($query) => $query->whereRaw('1 = 0'))
            ->whereNotIn(DB::raw(PengukuranKinerja::targetUnitSql()), $deniedUnits)
            ->withCount(['buktiDukungs' => fn ($query) => $query->current()])->orderBy('id')->paginate(20)->withQueryString();

        $present->prepareSummary($page->getCollection());

        return Inertia::render('Pengukuran/Index', [
            'periode' => $periode ? ['id' => $periode->periode_id, 'tahun' => $jadwal->tahun, 'urutan' => $periode->periode->urutan, 'nama_periode' => $periode->periode->nama] : null,
            'pengukurans' => $page->getCollection()->map(fn ($item) => $present->handle($item, $actor))->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
        ]);
    }
}
