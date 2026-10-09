<?php

namespace App\Actions\RencanaAksi;

use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use App\Support\PermissionCodes;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AntreanRencanaAksi
{
    public function __construct(private PresentRencanaAksi $present) {}

    /**
     * Data halaman daftar rencana aksi (antrean/disahkan) siap dirender.
     *
     * @return array{rencanaAksis: list<array<string, mixed>>, pagination: array<string, mixed>, status: string}
     */
    public function handle(User $actor, string $status): array
    {
        $status = $status === 'disahkan' ? 'disahkan' : 'antrean';
        $page = RencanaAksi::with(['indikator', 'unit', 'penanggungJawab:id,nama', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])
            ->whereIn('status_alur', $status === 'disahkan' ? ['disahkan'] : ['diajukan', 'diverifikasi'])
            // Record dengan unit header ≠ unit snapshot tidak konsisten dan ditolak saat dibuka.
            ->whereHas('jadwalSnapshot', fn (EloquentBuilder $query) => $query->whereColumn('jadwal_snapshot.unit_id', 'rencana_aksi.unit_id'))
            ->whereNotIn('unit_id', $this->deniedUnits($actor->id, PermissionCodes::RENCANA_AKSI_READ))
            // Antrean mengikuti waktu pengajuan versi terbaru; kolom updated_at header tidak dipelihara.
            ->orderByDesc(
                RencanaAksiVersi::query()
                    ->select('diajukan_at')
                    ->whereColumn('rencana_aksi_versi.rencana_aksi_id', 'rencana_aksi.id')
                    ->orderByDesc('nomor')
                    ->limit(1)
            )
            ->orderBy('id')->withCount('buktiDukungs')->paginate(20)->withQueryString();

        return [
            'rencanaAksis' => $page->getCollection()->map(fn ($item) => $this->present->handle($item, $actor))->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
            'status' => $status,
        ];
    }

    private function deniedUnits(string $userId, string $permission): Builder
    {
        return DB::table('user_permission_denied')->join('permissions', 'permissions.id', '=', 'user_permission_denied.permission_id')
            ->where('user_id', $userId)->where('permissions.kode', $permission)->whereNotNull('unit_id')->select('unit_id');
    }
}
