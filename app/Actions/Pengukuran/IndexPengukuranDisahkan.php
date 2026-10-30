<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\Renstra;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Antrean pengukuran `disahkan` untuk koreksi resmi (Plan 6.3, keputusan K1):
 * halaman terpisah `/verifikasi/disahkan`; antrean reviu `/verifikasi` tidak berubah.
 * Baris hanya memuat metadata dan tautan detail, tanpa ability/policy per baris, agar tidak ada N+1.
 */
class IndexPengukuranDisahkan
{
    private const PERMISSION = 'pengukuran:buka_kembali';

    public function __construct(private PresentPengukuran $present, private PermissionResolver $resolver) {}

    /** @return array<string, mixed> */
    public function handle(User $actor, array $input = []): array
    {
        // Gerbang fail-closed: tanpa izin buka-kembali halaman ini tidak pernah dibuka.
        $this->pastikanBoleh($actor);

        $filters = [];
        foreach (['renstra_id', 'tahun', 'periode_id', 'unit_id'] as $key) {
            $filters[$key] = (string) ($input[$key] ?? '');
        }

        $query = $this->dasar($actor)->with(['indikator', 'periode', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'ratifiedVersion']);
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

        $page = $query->orderByDesc('updated_at')->orderBy('id')->withCount('buktiDukungs')->paginate(20)->withQueryString();
        $this->present->prepareSummary($page->getCollection());

        $selected = [];
        foreach (['renstra', 'periode', 'unit'] as $jenis) {
            $id = $filters[$jenis.'_id'];
            $item = $id === '' ? null : $this->pilihanQuery($actor, $jenis)->whereKey($id)->first(['id', 'nama']);
            $selected[$jenis.'_id'] = $item ? ['id' => $item->id, 'label' => $item->nama] : null;
        }

        return [
            'pengukurans' => $page->getCollection()->map(fn (PengukuranKinerja $item) => [
                ...$this->present->handle($item, $actor),
                // PresentPengukuran tanpa `detail` tidak mengirim `snapshot`; antrean koreksi tetap butuh identitas versi resmi.
                'versi_resmi' => $item->ratifiedVersion ? [
                    'id' => $item->ratifiedVersion->id,
                    'nomor' => $item->ratifiedVersion->nomor,
                    'disahkan_pada' => $item->ratifiedVersion->disahkan_at?->toIso8601String(),
                ] : null,
            ])->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
            'filters' => $filters, 'selected_filters' => $selected,
            'can' => ['buka_kembali' => true],
        ];
    }

    /** @return array{data: list<array{id: string, label: string}>, page: int, has_more: bool} */
    public function pilihan(User $actor, array $input): array
    {
        $this->pastikanBoleh($actor);

        $query = $this->pilihanQuery($actor, $input['jenis']);
        if (($input['q'] ?? '') !== '') {
            $query->where('nama', 'ilike', '%'.addcslashes($input['q'], '\\%_').'%');
        }
        $page = $query->orderBy('nama')->orderBy('id')->simplePaginate(20, ['id', 'nama'], 'page', $input['page'] ?? 1);

        return ['data' => $page->getCollection()->map(fn ($item) => ['id' => $item->id, 'label' => $item->nama])->all(),
            'page' => $page->currentPage(), 'has_more' => $page->hasMorePages()];
    }

    private function pastikanBoleh(User $actor): void
    {
        if (! $this->resolver->allows($actor, self::PERMISSION)) {
            throw new AuthorizationException('Anda tidak memiliki izin membuka kembali pengukuran.');
        }
    }

    /**
     * Lookup dan label terpilih memakai scope yang sama dengan antrean, termasuk deny per unit.
     *
     * @return Builder<Unit>|Builder<Periode>|Builder<Renstra>
     */
    private function pilihanQuery(User $actor, string $jenis): Builder
    {
        $dasar = $this->dasar($actor);

        return match ($jenis) {
            'unit' => Unit::query()->whereIn('id', $dasar->selectRaw(PengukuranKinerja::targetUnitSql())),
            'periode' => Periode::query()->whereIn('id', $dasar->select('periode_id')),
            'renstra' => Renstra::query()->whereIn('id', DB::table('jadwal_tahunan')
                ->whereIn('id', DB::table('jadwal_snapshot')->whereIn('id', $dasar->select('jadwal_snapshot_id'))->select('jadwal_id'))->select('renstra_id')),
        };
    }

    /**
     * Dasar daftar dan lookup: hanya pengukuran `disahkan` pada jadwal yang layak dikoreksi,
     * dengan deny per unit yang menang juga di daftar.
     *
     * @return Builder<PengukuranKinerja>
     */
    private function dasar(User $actor): Builder
    {
        $query = PengukuranKinerja::query()
            ->where('status_alur', 'disahkan')
            ->whereNotIn(DB::raw(PengukuranKinerja::targetUnitSql()), $this->resolver->unitDitolak($actor, self::PERMISSION));

        return $this->batasiJadwalLayak($query);
    }

    /**
     * Kelayakan jadwal untuk koreksi (Plan 6.3 DoD; Q35 §35.2, AC-4): jadwal `aktif` yang `penutupan`-nya
     * belum lewat pada tanggal bisnis, atau jadwal yang sedang di dalam sesi koreksi resmi yang mencakup
     * `pengukuran`. Filter ini hanya level jadwal dan berjalan di SQL tanpa query per baris; kecocokan
     * indikator × periode × lingkup tetap milik `PengukuranKinerjaPolicy` saat detail dibuka, sehingga
     * baris yang tampil di sini belum tentu boleh dibuka kembali.
     *
     * @param  Builder<PengukuranKinerja>  $query
     * @return Builder<PengukuranKinerja>
     */
    private function batasiJadwalLayak(Builder $query): Builder
    {
        $hariIni = today(config('app.business_timezone'))->toDateString();
        $sekarang = now();

        return $query->whereHas('jadwalSnapshot.jadwal', fn ($jadwal) => $jadwal
            ->where('status', 'aktif')
            ->where(fn ($layak) => $layak
                ->where('penutupan', '>=', $hariIni)
                ->orWhere(fn ($koreksi) => $koreksi
                    ->where('koreksi_mulai', '<=', $sekarang)
                    ->where('koreksi_sampai', '>=', $sekarang)
                    // `lingkup_koreksi` jsonb: array `jenis_objek` harus memuat "pengukuran".
                    ->whereRaw("lingkup_koreksi->'jenis_objek' @> '\"pengukuran\"'::jsonb"))));
    }
}
