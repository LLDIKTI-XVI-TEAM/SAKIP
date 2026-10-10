<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Support\Facades\DB;

class IndexVerifikasi
{
    public function __construct(private PresentPengukuran $present, private PermissionResolver $resolver) {}

    /**
     * Antrean reviu (diajukan/diverifikasi) untuk aktor yang sudah terbukti memegang minimal satu izin reviu global.
     * Baris disaring di database: unit yang di-deny `pengukuran:read` dibuang, dan unit harus lolos minimal satu izin reviu yang dimiliki.
     *
     * @param  list<string>  $permissions  kode izin reviu yang dimiliki aktor secara global
     * @return array<string, mixed>
     */
    public function handle(User $actor, array $permissions): array
    {
        $page = PengukuranKinerja::with(['indikator', 'periode', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])
            ->whereIn('status_alur', ['diajukan', 'diverifikasi'])
            ->where(function ($query) use ($actor, $permissions) {
                $query->whereNotIn(DB::raw(PengukuranKinerja::targetUnitSql()), $this->resolver->unitDitolak($actor, 'pengukuran:read'));
                $query->where(function ($allowed) use ($actor, $permissions) {
                    foreach ($permissions as $permission) {
                        $allowed->orWhereNotIn(DB::raw(PengukuranKinerja::targetUnitSql()), $this->resolver->unitDitolak($actor, $permission));
                    }
                });
            })->orderByDesc('updated_at')->withCount('buktiDukungs')->orderBy('id')->paginate(20)->withQueryString();

        $this->present->prepareSummary($page->getCollection());

        return [
            'pengukurans' => $page->getCollection()->map(fn ($item) => $this->present->handle($item, $actor))->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
        ];
    }
}
