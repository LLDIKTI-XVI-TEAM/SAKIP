<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\PresentRencanaAksi;
use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IndexRencanaAksi extends Controller
{
    public function __invoke(Request $request, PresentRencanaAksi $present, PermissionResolver $resolver): Response
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $actor = $request->user();
        // Akses lihat longgar: cukup rencana_aksi:read. Sahkan tetap di Gate per-item/detail + POST.
        abort_unless($resolver->allows($actor, 'rencana_aksi:read'), 403);
        $page = RencanaAksi::with(['indikator', 'unit', 'penanggungJawab:id,nama', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])
            ->whereIn('status_alur', ['diajukan', 'diverifikasi'])
            ->whereNotIn('unit_id', $this->deniedUnits($actor->id, 'rencana_aksi:read'))
            ->orderByDesc('updated_at')->orderBy('id')->withCount('buktiDukungs')->paginate(20)->withQueryString();

        return Inertia::render('RencanaAksi/Index', [
            'rencanaAksis' => $page->getCollection()->map(fn ($item) => $present->handle($item, $actor))->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
        ]);
    }

    private function deniedUnits(string $userId, string $permission): Builder
    {
        return DB::table('user_permission_denied')->join('permissions', 'permissions.id', '=', 'user_permission_denied.permission_id')
            ->where('user_id', $userId)->where('permissions.kode', $permission)->whereNotNull('unit_id')->select('unit_id');
    }
}
