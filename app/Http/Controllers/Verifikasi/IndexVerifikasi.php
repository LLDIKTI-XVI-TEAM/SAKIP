<?php

namespace App\Http\Controllers\Verifikasi;

use App\Actions\Pengukuran\PresentPengukuran;
use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IndexVerifikasi extends Controller
{
    public function __invoke(Request $request, PresentPengukuran $present, PermissionResolver $resolver): Response
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $actor = $request->user();
        $permissions = array_values(array_filter(['pengukuran:verifikasi', 'pengukuran:sahkan', 'pengukuran:kembalikan'], fn ($code) => $resolver->allows($actor, $code)));
        abort_if($permissions === [] || ! $resolver->allows($actor, 'pengukuran:read'), 403);
        $page = PengukuranKinerja::with(['indikator', 'periode', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])
            ->whereIn('status_alur', ['diajukan', 'diverifikasi'])
            ->where(function ($query) use ($actor, $permissions) {
                $query->whereNotIn(DB::raw(PengukuranKinerja::targetUnitSql()), $this->deniedUnits($actor->id, 'pengukuran:read'));
                $query->where(function ($allowed) use ($actor, $permissions) {
                    foreach ($permissions as $permission) {
                        $allowed->orWhereNotIn(DB::raw(PengukuranKinerja::targetUnitSql()), $this->deniedUnits($actor->id, $permission));
                    }
                });
            })->orderByDesc('updated_at')->withCount('buktiDukungs')->orderBy('id')->paginate(20)->withQueryString();

        $present->prepareSummary($page->getCollection());

        return Inertia::render('Verifikasi/Index', [
            'pengukurans' => $page->getCollection()->map(fn ($item) => $present->handle($item, $actor))->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
        ]);
    }

    private function deniedUnits(string $userId, string $permission): Builder
    {
        return DB::table('user_permission_denied')->join('permissions', 'permissions.id', '=', 'user_permission_denied.permission_id')
            ->where('user_id', $userId)->where('permissions.kode', $permission)->whereNotNull('unit_id')->select('unit_id');
    }
}
