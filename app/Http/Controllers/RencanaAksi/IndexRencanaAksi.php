<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\PresentRencanaAksi;
use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IndexRencanaAksi extends Controller
{
    public function __invoke(Request $request, PresentRencanaAksi $present, PermissionResolver $resolver): Response
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:antrean,disahkan'],
        ]);
        $actor = $request->user();
        // Akses lihat longgar: cukup rencana_aksi:read. Sahkan tetap di Gate per-item/detail + POST.
        abort_unless($resolver->allows($actor, 'rencana_aksi:read'), 403);
        $status = $request->query('status') === 'disahkan' ? 'disahkan' : 'antrean';
        $page = RencanaAksi::with(['indikator', 'unit', 'penanggungJawab:id,nama', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])
            ->whereIn('status_alur', $status === 'disahkan' ? ['disahkan'] : ['diajukan', 'diverifikasi'])
            // Record dengan unit header ≠ unit snapshot tidak konsisten dan ditolak saat dibuka.
            ->whereHas('jadwalSnapshot', fn (EloquentBuilder $query) => $query->whereColumn('jadwal_snapshot.unit_id', 'rencana_aksi.unit_id'))
            ->whereNotIn('unit_id', $this->deniedUnits($actor->id, 'rencana_aksi:read'))
            // Antrean mengikuti waktu pengajuan versi terbaru; kolom updated_at header tidak dipelihara.
            ->orderByDesc(
                RencanaAksiVersi::query()
                    ->select('diajukan_at')
                    ->whereColumn('rencana_aksi_versi.rencana_aksi_id', 'rencana_aksi.id')
                    ->orderByDesc('nomor')
                    ->limit(1)
            )
            ->orderBy('id')->withCount('buktiDukungs')->paginate(20)->withQueryString();

        return Inertia::render('RencanaAksi/Index', [
            'rencanaAksis' => $page->getCollection()->map(fn ($item) => $present->handle($item, $actor))->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
            'status' => $status,
        ]);
    }

    private function deniedUnits(string $userId, string $permission): Builder
    {
        return DB::table('user_permission_denied')->join('permissions', 'permissions.id', '=', 'user_permission_denied.permission_id')
            ->where('user_id', $userId)->where('permissions.kode', $permission)->whereNotNull('unit_id')->select('unit_id');
    }
}
