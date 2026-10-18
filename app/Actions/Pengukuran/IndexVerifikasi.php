<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\Renstra;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class IndexVerifikasi
{
    public function __construct(private PresentPengukuran $present, private PermissionResolver $resolver) {}

    /** @param list<string> $permissions */
    public function handle(User $actor, array $permissions, array $input = []): array
    {
        $filters = ['status' => $input['status'] ?? 'diajukan'];
        foreach (['renstra_id', 'tahun', 'periode_id', 'unit_id'] as $key) {
            $filters[$key] = (string) ($input[$key] ?? '');
        }
        $query = $this->antrean($actor, $permissions)->with(['indikator', 'periode', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])
            ->where('status_alur', $filters['status']);
        if ($filters['renstra_id'] !== '') {
            $query->whereHas('jadwalSnapshot.jadwal', fn ($jadwal) => $jadwal->where('renstra_id', $filters['renstra_id']));
        }
        foreach (['tahun', 'periode_id'] as $key) {
            if ($filters[$key] !== '') {
                $query->where($key, $filters[$key]);
            }
        }
        if ($filters['unit_id'] !== '') {
            $query->whereRaw(PengukuranKinerja::targetUnitSql().' = ?', [$filters['unit_id']]);
        }
        $page = $query->orderByDesc('updated_at')->withCount('buktiDukungs')->orderBy('id')->paginate(20)->withQueryString();
        $this->present->prepareSummary($page->getCollection());
        $selected = [];
        foreach (['renstra', 'periode', 'unit'] as $jenis) {
            $id = $filters[$jenis.'_id'];
            $item = $id === '' ? null : $this->pilihanQuery($actor, $permissions, $jenis)->whereKey($id)->first(['id', 'nama']);
            $selected[$jenis.'_id'] = $item ? ['id' => $item->id, 'label' => $item->nama] : null;
        }

        return [
            'pengukurans' => $page->getCollection()->map(fn ($item) => $this->present->handle($item, $actor))->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
            'filters' => $filters, 'selected_filters' => $selected,
        ];
    }

    /** @param list<string> $permissions */
    public function pilihan(User $actor, array $permissions, array $input): array
    {
        $query = $this->pilihanQuery($actor, $permissions, $input['jenis']);
        if (($input['q'] ?? '') !== '') {
            $query->where('nama', 'ilike', '%'.addcslashes($input['q'], '\\%_').'%');
        }
        $page = $query->orderBy('nama')->orderBy('id')->simplePaginate(20, ['id', 'nama'], 'page', $input['page'] ?? 1);

        return ['data' => $page->getCollection()->map(fn ($item) => ['id' => $item->id, 'label' => $item->nama])->all(),
            'page' => $page->currentPage(), 'has_more' => $page->hasMorePages()];
    }

    /**
     * Lookup dan label terpilih memakai scope yang sama dengan antrean, termasuk deny pada unit beku.
     *
     * @return Builder<Unit>|Builder<Periode>|Builder<Renstra>
     */
    private function pilihanQuery(User $actor, array $permissions, string $jenis): Builder
    {
        $antrean = $this->antrean($actor, $permissions);

        return match ($jenis) {
            'unit' => Unit::query()->whereIn('id', $antrean->selectRaw(PengukuranKinerja::targetUnitSql())),
            'periode' => Periode::query()->whereIn('id', $antrean->select('periode_id')),
            'renstra' => Renstra::query()->whereIn('id', DB::table('jadwal_tahunan')
                ->whereIn('id', DB::table('jadwal_snapshot')->whereIn('id', $antrean->select('jadwal_snapshot_id'))->select('jadwal_id'))->select('renstra_id')),
        };
    }

    /**
     * @param  list<string>  $permissions
     * @return Builder<PengukuranKinerja>
     */
    private function antrean(User $actor, array $permissions): Builder
    {
        return PengukuranKinerja::query()->whereIn('status_alur', ['diajukan', 'diverifikasi'])
            ->whereNotIn(DB::raw(PengukuranKinerja::targetUnitSql()), $this->resolver->unitDitolak($actor, 'pengukuran:read'))
            ->where(function ($allowed) use ($actor, $permissions) {
                foreach ($permissions as $permission) {
                    $allowed->orWhereNotIn(DB::raw(PengukuranKinerja::targetUnitSql()), $this->resolver->unitDitolak($actor, $permission));
                }
            });
    }
}
