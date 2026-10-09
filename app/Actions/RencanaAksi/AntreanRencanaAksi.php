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
     * Data halaman antrean/disahkan pengesahan rencana aksi siap dirender.
     *
     * Konteks beku dibaca dari snapshot versi pengajuan terbaru (header tidak
     * lagi menyimpan rujukan snapshot); baris dengan unit snapshot ≠ unit
     * header tidak konsisten dan disaring di database.
     *
     * @return array{rencanaAksis: list<array<string, mixed>>, pagination: array<string, mixed>, status: string}
     */
    public function handle(User $actor, string $status): array
    {
        $status = $status === 'disahkan' ? 'disahkan' : 'antrean';
        $page = RencanaAksi::with(['indikator', 'unit', 'penanggungJawab:id,nama', 'latestVersion.jadwalSnapshot', 'ratifiedVersion'])
            ->whereIn('status_alur', $status === 'disahkan' ? ['disahkan'] : ['diajukan', 'diverifikasi'])
            // Record dengan unit header ≠ unit snapshot versi TERBARU tidak konsisten dan ditolak
            // saat dibuka. Korelasi ke nomor maksimum wajib: whereHas pada hasOne ber-orderBy
            // tidak membatasi subquery existence ke versi terbaru (temuan review 2026-10-09).
            ->whereHas('latestVersion', fn (EloquentBuilder $query) => $query
                ->whereHas('jadwalSnapshot', fn (EloquentBuilder $snapshot) => $snapshot->whereColumn('jadwal_snapshot.unit_id', 'rencana_aksi.unit_id'))
                ->whereRaw('rencana_aksi_versi.nomor = (select max(v.nomor) from rencana_aksi_versi as v where v.rencana_aksi_id = rencana_aksi.id)'))
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
