<?php

namespace App\Actions\PerjanjianKinerja;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\JadwalTahunan;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class IndexPerjanjianKinerja
{
    public function __construct(private EvaluateEvidence $evidence) {}

    /**
     * Daftar PK tahunan dengan filter renstra/tahun/nomor, pilihan Renstra, batas teknis berkas, dan capability aktor.
     * `$filters` tervalidasi untuk query; `$rawFilters` dikembalikan apa adanya sebagai state form existing.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $rawFilters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters, array $rawFilters): array
    {
        return [
            'perjanjianKinerja' => $this->paginate($filters),
            'renstras' => Renstra::orderByDesc('tahun_mulai')
                ->get(['id', 'kode', 'nama', 'tahun_mulai', 'tahun_selesai', 'is_aktif'])
                ->map(fn (Renstra $r) => [
                    'id' => $r->id,
                    'kode' => $r->kode,
                    'nama' => $r->nama,
                    'tahun_mulai' => $r->tahun_mulai,
                    'tahun_selesai' => $r->tahun_selesai,
                    'is_aktif' => (bool) $r->is_aktif,
                ])
                ->all(),
            'storageSettings' => $this->evidence->settings(),
            'filters' => $rawFilters,
            'can' => [
                'create' => $user->can('create', RenstraPk::class),
                'update' => $user->can('update', RenstraPk::class),
                'upload_berkas' => $user->can('uploadBerkas', RenstraPk::class),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginate(array $filters): LengthAwarePaginator
    {
        $query = RenstraPk::query()
            ->with([
                'renstra:id,kode,nama,tahun_mulai,tahun_selesai,is_aktif',
                'creator:id,nama',
                'jadwalTahunan:id,renstra_id,renstra_pk_id,tahun,status,activated_at',
            ])
            ->withCount('berkas')
            ->when(! empty($filters['renstra_id']), fn ($q) => $q->where('renstra_id', $filters['renstra_id']))
            ->when(! empty($filters['tahun']), fn ($q) => $q->where('tahun', $filters['tahun']))
            ->when(! empty($filters['q']), fn ($q) => $q->where('nomor_pk', 'ilike', '%'.$filters['q'].'%'))
            ->orderByDesc('tahun')
            ->orderByDesc('created_at');

        /** @var LengthAwarePaginator<int, RenstraPk> $paginator */
        $paginator = $query->paginate(15)->withQueryString();

        // Resolusi fallback jadwal tahunan legacy untuk baris yang tidak memiliki relasi langsung renstra_pk_id.
        // Dilakukan dalam satu batch query terikat untuk mencegah N+1 problem dengan tetap menjaga ranking deterministik yang identik.
        $missingItems = $paginator->getCollection()->filter(fn (RenstraPk $pk) => $pk->jadwalTahunan === null);
        if ($missingItems->isNotEmpty()) {
            $fallbackJadwals = JadwalTahunan::where(function ($q) use ($missingItems) {
                foreach ($missingItems as $pk) {
                    $q->orWhere(function ($sub) use ($pk) {
                        $sub->where('renstra_id', $pk->renstra_id)
                            ->where('tahun', $pk->tahun);
                    });
                }
            })
                ->orderByRaw("CASE WHEN status = 'aktif' THEN 0 WHEN status = 'ditutup' THEN 1 ELSE 2 END")
                ->orderByDesc('activated_at')
                ->orderByDesc('closed_at')
                ->orderByDesc('penutupan')
                ->orderByDesc('id')
                ->get(['id', 'renstra_id', 'renstra_pk_id', 'tahun', 'status', 'activated_at'])
                ->groupBy(fn (JadwalTahunan $j) => $j->renstra_id.'_'.$j->tahun);

            foreach ($missingItems as $pk) {
                $key = $pk->renstra_id.'_'.$pk->tahun;
                $topJadwal = $fallbackJadwals->get($key)?->first();
                if ($topJadwal !== null) {
                    $pk->setRelation('jadwalTahunan', $topJadwal);
                }
            }
        }

        /** @var LengthAwarePaginator<int, array<string, mixed>> $result */
        $result = $paginator->through(fn (RenstraPk $pk) => [
            'id' => $pk->id,
            'renstra_id' => $pk->renstra_id,
            'tahun' => $pk->tahun,
            'nomor_pk' => $pk->nomor_pk,
            'tanggal_pk' => $pk->tanggal_pk?->format('Y-m-d'),
            'created_at' => $pk->created_at?->toISOString(),
            'updated_at' => $pk->updated_at?->toISOString(),
            'berkas_count' => (int) ($pk->berkas_count ?? 0),
            'renstra' => $pk->renstra ? [
                'id' => $pk->renstra->id,
                'kode' => $pk->renstra->kode,
                'nama' => $pk->renstra->nama,
                'tahun_mulai' => $pk->renstra->tahun_mulai,
                'tahun_selesai' => $pk->renstra->tahun_selesai,
                'is_aktif' => (bool) $pk->renstra->is_aktif,
            ] : null,
            'creator' => $pk->creator ? [
                'id' => $pk->creator->id,
                'nama' => $pk->creator->nama,
            ] : null,
            'jadwal_tahunan' => $pk->jadwalTahunan ? [
                'id' => $pk->jadwalTahunan->id,
                'renstra_id' => $pk->jadwalTahunan->renstra_id,
                'renstra_pk_id' => $pk->jadwalTahunan->renstra_pk_id,
                'tahun' => $pk->jadwalTahunan->tahun,
                'status' => $pk->jadwalTahunan->status,
                'activated_at' => $pk->jadwalTahunan->activated_at,
                'is_terkunci' => $pk->jadwalTahunan->is_terkunci,
            ] : null,
        ]);

        return $result;
    }
}
