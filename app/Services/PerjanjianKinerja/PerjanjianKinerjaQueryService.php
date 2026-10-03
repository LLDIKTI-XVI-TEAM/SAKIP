<?php

namespace App\Services\PerjanjianKinerja;

use App\Models\Berkas;
use App\Models\JadwalTahunan;
use App\Models\Pengaturan;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class PerjanjianKinerjaQueryService
{
    /**
     * Membangun query, menerapkan filter, melakukan pagination, dan memproyeksikan data Index PK.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateIndex(array $filters): LengthAwarePaginator
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

        $perPage = isset($filters['per_page']) && in_array((int) $filters['per_page'], [10, 25, 50, 100], true)
            ? (int) $filters['per_page']
            : 10;

        /** @var LengthAwarePaginator<int, RenstraPk> $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

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

    /**
     * Mengambil daftar pilihan Renstra untuk filter dropdown.
     *
     * @return list<array<string, mixed>>
     */
    public function getRenstraOptions(): array
    {
        return Renstra::orderByDesc('tahun_mulai')
            ->get(['id', 'kode', 'nama', 'tahun_mulai', 'tahun_selesai', 'is_aktif'])
            ->map(fn (Renstra $r) => [
                'id' => $r->id,
                'kode' => $r->kode,
                'nama' => $r->nama,
                'tahun_mulai' => $r->tahun_mulai,
                'tahun_selesai' => $r->tahun_selesai,
                'is_aktif' => (bool) $r->is_aktif,
            ])
            ->all();
    }

    /**
     * Memproyeksikan detail Perjanjian Kinerja beserta status jadwal dan lampiran legal secara aman.
     *
     * @return array{pk: array<string, mixed>, jadwal_status: string|null, is_jadwal_aktif: bool, is_jadwal_terkunci: bool, can_read_berkas: bool}
     */
    public function presentDetail(RenstraPk $perjanjianKinerja, ?User $user): array
    {
        $perjanjianKinerja->load([
            'renstra',
            'creator:id,nama',
            'berkas.pengunggah:id,nama',
            'jadwalTahunan',
        ]);

        $jadwal = $perjanjianKinerja->resolveJadwalTahunan();
        $isJadwalAktif = $jadwal?->status === 'aktif';
        $isJadwalTerkunci = $jadwal?->is_terkunci ?? false;
        $jadwalStatus = $jadwal?->status;
        $canReadBerkas = $user?->can('downloadBerkas', $perjanjianKinerja) ?? false;

        $pkData = [
            'id' => $perjanjianKinerja->id,
            'renstra_id' => $perjanjianKinerja->renstra_id,
            'tahun' => $perjanjianKinerja->tahun,
            'nomor_pk' => $perjanjianKinerja->nomor_pk,
            'tanggal_pk' => $perjanjianKinerja->tanggal_pk?->format('Y-m-d'),
            'created_at' => $perjanjianKinerja->created_at?->toISOString(),
            'updated_at' => $perjanjianKinerja->updated_at?->toISOString(),
            'renstra' => $perjanjianKinerja->renstra ? [
                'id' => $perjanjianKinerja->renstra->id,
                'kode' => $perjanjianKinerja->renstra->kode,
                'nama' => $perjanjianKinerja->renstra->nama,
                'tahun_mulai' => $perjanjianKinerja->renstra->tahun_mulai,
                'tahun_selesai' => $perjanjianKinerja->renstra->tahun_selesai,
                'is_aktif' => (bool) $perjanjianKinerja->renstra->is_aktif,
            ] : null,
            'creator' => $perjanjianKinerja->creator ? [
                'id' => $perjanjianKinerja->creator->id,
                'nama' => $perjanjianKinerja->creator->nama,
            ] : null,
            'jadwal_tahunan' => $jadwal ? [
                'id' => $jadwal->id,
                'renstra_id' => $jadwal->renstra_id,
                'renstra_pk_id' => $jadwal->renstra_pk_id,
                'tahun' => $jadwal->tahun,
                'status' => $jadwal->status,
                'activated_at' => $jadwal->activated_at,
                'is_terkunci' => $jadwal->is_terkunci,
            ] : null,
            'berkas' => $perjanjianKinerja->berkas->map(function (Berkas $b) use ($canReadBerkas) {
                $item = [
                    'id' => $b->id,
                    'mode' => $b->mode,
                    'nama_asli' => $b->nama_asli,
                    'mime' => $b->mime,
                    'ukuran_bytes' => $b->ukuran_bytes,
                    'created_at' => $b->dibuat_pada?->toISOString() ?? $b->created_at?->toISOString(),
                    'pengunggah' => $b->pengunggah ? [
                        'id' => $b->pengunggah->id,
                        'nama' => $b->pengunggah->nama,
                    ] : null,
                ];

                if ($canReadBerkas) {
                    $item['tautan'] = $b->tautan;
                    $item['isi_teks'] = $b->isi_teks;
                }

                return $item;
            })->values()->all(),
        ];

        return [
            'pk' => $pkData,
            'jadwal_status' => $jadwalStatus,
            'is_jadwal_aktif' => $isJadwalAktif,
            'is_jadwal_terkunci' => $isJadwalTerkunci,
            'can_read_berkas' => $canReadBerkas,
        ];
    }

    /**
     * Menyusun kapabilitas akses untuk halaman Index PK.
     *
     * @return array<string, bool>
     */
    public function resolveIndexCapabilities(?User $user): array
    {
        return [
            'create' => $user?->can('create', RenstraPk::class) ?? false,
            'update' => $user?->can('update', RenstraPk::class) ?? false,
            'upload_berkas' => $user?->can('uploadBerkas', RenstraPk::class) ?? false,
        ];
    }

    /**
     * Menyusun kapabilitas akses untuk halaman Show PK.
     *
     * @return array<string, bool>
     */
    public function resolveShowCapabilities(?User $user, RenstraPk $perjanjianKinerja, bool $isJadwalTerkunci): array
    {
        return [
            'update' => $user?->can('update', $perjanjianKinerja) ?? false,
            'delete_berkas' => ! $isJadwalTerkunci && ($user?->can('deleteBerkas', $perjanjianKinerja) ?? false),
            'read_berkas' => $user?->can('downloadBerkas', $perjanjianKinerja) ?? false,
            'upload_berkas' => $user?->can('uploadBerkas', $perjanjianKinerja) ?? false,
        ];
    }

    /**
     * Mengambil setelan batas teknis berkas untuk props frontend.
     *
     * @return array{unggahan_aktif: bool, ukuran_maks_kb: int, format_diizinkan: string}
     */
    public function getStorageSettings(): array
    {
        $settings = Pengaturan::whereIn('kunci', [
            'berkas.unggahan_aktif',
            'berkas.ukuran_maks_kb',
            'berkas.format_diizinkan',
        ])->pluck('nilai', 'kunci');

        return [
            'unggahan_aktif' => filter_var($settings->get('berkas.unggahan_aktif') ?? true, FILTER_VALIDATE_BOOLEAN),
            'ukuran_maks_kb' => (int) $settings->get('berkas.ukuran_maks_kb', 10240),
            'format_diizinkan' => (string) $settings->get('berkas.format_diizinkan', 'pdf,docx,xlsx,jpg,jpeg,png'),
        ];
    }
}
