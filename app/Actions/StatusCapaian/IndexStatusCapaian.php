<?php

namespace App\Actions\StatusCapaian;

use App\Actions\Laporan\HitungCapaianPeriode;
use App\Actions\Pengukuran\CalculatePengukuran;
use App\Models\PengukuranKinerja;
use App\Models\StatusCapaian;
use App\Models\User;
use App\Policies\PengukuranKinerjaPolicy;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Halaman verifikasi status capaian menyajikan dua koleksi dari satu kunjungan:
 * - `kandidat`: pengukuran `disahkan` yang versi sah terakhirnya belum memiliki status berlaku dan bukan kondisi
 *   yang dilarang Q47 (periode tanpa target, `belum_dapat_diukur`, `tidak_dapat_dihitung`);
 * - `sudah_ditetapkan`: pengukuran `disahkan` yang versi sah terakhirnya sudah memiliki status berlaku, yaitu pintu
 *   masuk revisi (AC-4). Kondisi AC-5 tidak menyaring koleksi ini agar riwayat tetap terbaca; tombol revisi tetap
 *   dimatikan policy untuk versi yang tidak boleh menerima penetapan.
 * Seluruh angka diambil dari snapshot versi beku, bukan hitungan ulang rencana aksi hidup; daftar dan filter AC-5
 * memakai kondisi yang sama dengan `PengukuranKinerjaPolicy::statusCapaianErrors`.
 */
class IndexStatusCapaian
{
    /**
     * Koleksi revisi dibatasi satu halaman terbaru. Pagination kedua akan berebut parameter `page` yang sama dengan
     * daftar kandidat, sehingga batas sederhana lebih jujur daripada paginator yang tidak dapat dibuka ulang.
     */
    private const BATAS_SUDAH_DITETAPKAN = 20;

    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly PengukuranKinerjaPolicy $policy,
        private readonly HitungCapaianPeriode $capaian,
        private readonly CalculatePengukuran $hitung,
    ) {}

    /** @return array<string, mixed> */
    public function handle(User $actor): array
    {
        // Gerbang halaman fail-closed: tanpa izin ini halaman maupun endpoint tulis tidak pernah dibuka.
        $bolehMenetapkan = $this->resolver->allows($actor, PermissionCodes::STATUS_CAPAIAN_UPDATE);
        if (! $bolehMenetapkan) {
            throw new AuthorizationException('Anda tidak memiliki izin menetapkan status capaian.');
        }

        $kandidat = $this->dasar($actor)
            ->whereExists(fn (QueryBuilder $exists) => $this->versiSahTerakhir($exists, function (QueryBuilder $versi): void {
                // Versi sah terakhir belum punya status berlaku dan bukan kondisi yang dilarang Q47.
                $versi->whereNotExists(fn (QueryBuilder $status) => $status->selectRaw('1')->from('status_capaian as sc')
                    ->whereColumn('sc.pengukuran_versi_id', 'versi_sah.id'))
                    // Predikat AC-5 dibaca dari JSON versi beku dengan urutan yang sama seperti policy: tanpa target lebih dulu.
                    // Perbandingan teks dipakai agar nilai JSON yang tidak persis boolean tidak menggagalkan query daftar.
                    ->whereRaw("COALESCE(versi_sah.snapshot->>'tanpa_target_periode', 'false') <> 'true'")
                    ->whereRaw("COALESCE(versi_sah.snapshot->>'status_perhitungan', '') NOT IN ('belum_dapat_diukur', 'tidak_dapat_dihitung')");
            }))
            ->with($this->relasi())
            ->orderByDesc('pengukuran_kinerjas.tahun')
            ->orderBy('ind.kode')
            ->orderBy('pengukuran_kinerjas.periode_id')
            ->orderBy('pengukuran_kinerjas.id')
            ->paginate(20)
            ->withQueryString();

        $sudahDitetapkan = $this->dasar($actor)
            ->whereExists(fn (QueryBuilder $exists) => $this->versiSahTerakhir($exists, fn (QueryBuilder $versi) => $versi
                ->whereExists(fn (QueryBuilder $status) => $status->selectRaw('1')->from('status_capaian as sc')
                    ->whereColumn('sc.pengukuran_versi_id', 'versi_sah.id'))))
            ->with($this->relasi())
            // Urutan memakai waktu status berlaku terbaru per pengukuran, dibaca di database agar batas koleksi menyaring
            // baris yang benar; subquery select juga menjadi kunci urut yang deterministik bersama `id`.
            ->selectSub($this->statusTerakhirSql(), 'status_terakhir_pada')
            ->orderByDesc('status_terakhir_pada')
            ->orderByDesc('pengukuran_kinerjas.id')
            ->limit(self::BATAS_SUDAH_DITETAPKAN)
            ->get();

        $rows = $kandidat->getCollection()->concat($sudahDitetapkan);
        $units = $rows->map(fn (PengukuranKinerja $row) => $row->targetUnitId())->unique()->values()->all();
        $izin = $units === [] ? [] : $this->resolver->decideManyForUnits($actor, [PermissionCodes::STATUS_CAPAIAN_UPDATE], $units);
        $statusPerVersi = $this->statusBerlaku($sudahDitetapkan
            ->map(fn (PengukuranKinerja $p) => $p->ratifiedVersion?->id)->filter()->unique()->values()->all());

        return [
            'kandidat' => $kandidat->getCollection()->map(fn (PengukuranKinerja $row) => $this->baris($row, $izin))->all(),
            'sudah_ditetapkan' => $sudahDitetapkan->map(function (PengukuranKinerja $row) use ($izin, $statusPerVersi): array {
                $versiId = $row->ratifiedVersion?->id;

                return $this->baris($row, $izin, $versiId === null ? null : $statusPerVersi->get($versiId));
            })->values()->all(),
            'pagination' => [
                'current_page' => $kandidat->currentPage(), 'last_page' => $kandidat->lastPage(), 'total' => $kandidat->total(),
                'prev_page_url' => $kandidat->previousPageUrl(), 'next_page_url' => $kandidat->nextPageUrl(),
            ],
            'can' => ['tetapkan' => $bolehMenetapkan],
        ];
    }

    /**
     * Dasar kedua koleksi: pengukuran yang sudah disahkan, dengan deny per unit yang menang juga pada daftar,
     * sejalan dengan keputusan resolver pada tindakan tulis.
     *
     * @return EloquentBuilder<PengukuranKinerja>
     */
    private function dasar(User $actor): EloquentBuilder
    {
        return PengukuranKinerja::query()
            ->join('indikator_kinerjas as ind', 'ind.id', '=', 'pengukuran_kinerjas.indikator_id')
            ->select('pengukuran_kinerjas.*')
            ->where('pengukuran_kinerjas.status_alur', 'disahkan')
            ->whereNotIn(DB::raw(PengukuranKinerja::targetUnitSql()), $this->resolver->unitDitolak($actor, PermissionCodes::STATUS_CAPAIAN_UPDATE));
    }

    /**
     * Relasi yang dipakai kedua koleksi. Riwayat dimuat sekali per halaman, bukan per baris; urutan `created_at`
     * terbaru lalu `id` sama dengan scope `StatusCapaian::berlaku`.
     *
     * @return array<int|string, mixed>
     */
    private function relasi(): array
    {
        return [
            'ratifiedVersion',
            'jadwalSnapshot:id,unit_id',
            'jadwalSnapshot.unit:id,nama',
            'periode:id,nama,urutan',
            'statusCapaian' => fn ($query) => $query
                ->with(['ditetapkanOleh:id,nama', 'versi:id,nomor'])
                ->orderByDesc('created_at')->orderByDesc('id'),
        ];
    }

    /**
     * Membatasi EXISTS pada versi sah terakhir, yaitu versi yang sudah disahkan dengan nomor tertinggi; `$syarat`
     * menambahkan predikat pada alias `versi_sah` (sudah/belum punya status, kondisi AC-5). Versi koreksi baru tidak
     * mewarisi status versi sebelumnya.
     *
     * @param  Closure(QueryBuilder): void  $syarat
     */
    private function versiSahTerakhir(QueryBuilder $exists, Closure $syarat): void
    {
        $exists->selectRaw('1')->from('pengukuran_versi as versi_sah')
            ->whereColumn('versi_sah.pengukuran_id', 'pengukuran_kinerjas.id')
            ->whereNotNull('versi_sah.disahkan_at')
            ->whereNotExists(fn (QueryBuilder $baru) => $baru->selectRaw('1')->from('pengukuran_versi as versi_baru')
                ->whereColumn('versi_baru.pengukuran_id', 'versi_sah.pengukuran_id')
                ->whereNotNull('versi_baru.disahkan_at')
                ->whereColumn('versi_baru.nomor', '>', 'versi_sah.nomor'));

        $syarat($exists);
    }

    /** Waktu status berlaku terbaru pada versi sah terakhir, dipakai hanya sebagai kunci urut koleksi revisi. */
    private function statusTerakhirSql(): QueryBuilder
    {
        $versiSah = DB::table('pengukuran_versi as vs')
            ->select('vs.id')
            ->whereColumn('vs.pengukuran_id', 'pengukuran_kinerjas.id')
            ->whereNotNull('vs.disahkan_at')
            ->orderByDesc('vs.nomor')
            ->limit(1);

        return DB::table('status_capaian as sc')
            ->select('sc.created_at')
            ->whereIn('sc.pengukuran_versi_id', $versiSah)
            ->orderByDesc('sc.created_at')
            ->orderByDesc('sc.id')
            ->limit(1);
    }

    /**
     * Status berlaku per versi untuk satu halaman dalam satu query, memakai scope `berlaku` yang sama dengan konsumen
     * lain sehingga definisi "berlaku" tidak ditulis ulang di sini.
     *
     * @param  list<string>  $versiIds
     * @return Collection<string, StatusCapaian>
     */
    private function statusBerlaku(array $versiIds): Collection
    {
        if ($versiIds === []) {
            return collect();
        }

        $berlaku = collect();
        foreach (StatusCapaian::query()->berlaku($versiIds)->with(['ditetapkanOleh:id,nama', 'versi:id,nomor'])->get() as $status) {
            // Hasil sudah urut "berlaku"; kemunculan pertama tiap versi adalah baris yang berlaku.
            if (! $berlaku->has($status->pengukuran_versi_id)) {
                $berlaku->put($status->pengukuran_versi_id, $status);
            }
        }

        return $berlaku;
    }

    /**
     * Allowlist props: tidak ada payload kerja, dasar izin, atau nilai turunan rencana aksi hidup.
     *
     * `$berlaku` hanya diisi untuk koleksi revisi; baris kandidat tidak mengirim `status_berlaku` karena menurut
     * definisinya belum ada status berlaku pada versi sah terakhir.
     *
     * @param  array<string, array<string, array{allowed: bool, permission: string, reason: string, roles: list<string>, grants: list<string>, denies: list<string>}>>  $izin
     * @return array<string, mixed>
     */
    private function baris(PengukuranKinerja $p, array $izin, ?StatusCapaian $berlaku = null): array
    {
        $versi = $p->ratifiedVersion;
        $frozen = $versi?->snapshot ?? [];
        $indikator = is_array($frozen['indikator'] ?? null) ? $frozen['indikator'] : [];
        $unitKerja = is_array($frozen['unit_kerja'] ?? null) ? $frozen['unit_kerja'] : [];
        $periodeBeku = is_array($frozen['periode'] ?? null) ? $frozen['periode'] : [];
        $nilai = $this->teks($frozen['nilai'] ?? null);
        $target = $this->teks($frozen['target'] ?? null);
        $targetPk = $this->teks($frozen['target_pk'] ?? null);
        $tanpaTarget = (bool) ($frozen['tanpa_target_periode'] ?? false);
        $arah = $indikator['arah'] ?? null;
        $unitId = $p->targetUnitId();
        // Gerbang yang sama dengan Action: izin unit dan versi sah yang lolos `statusCapaianErrors`.
        $bolehUnit = ($izin[$unitId][PermissionCodes::STATUS_CAPAIAN_UPDATE]['allowed'] ?? false)
            && $versi !== null && $this->policy->statusCapaianErrors($p, $versi) === [];

        $row = [
            'id' => $p->id,
            // Token konkurensi header, dikirim ulang form agar penetapan bersamaan terdeteksi sebagai konflik.
            'versi' => $p->versi,
            'tahun' => $p->tahun,
            'versi_pengukuran' => $versi === null ? null : [
                'id' => $versi->id, 'nomor' => $versi->nomor, 'disahkan_pada' => $versi->disahkan_at?->toIso8601String(),
            ],
            'indikator' => [
                'kode' => $indikator['kode'] ?? null, 'nama' => $indikator['nama'] ?? null, 'satuan' => $indikator['satuan'] ?? null,
                'presisi' => isset($indikator['presisi']) ? (int) $indikator['presisi'] : null,
                'desimal_tampilan' => isset($indikator['desimal_tampilan']) ? (int) $indikator['desimal_tampilan'] : null,
            ],
            'unit' => ['id' => $unitId, 'nama' => $unitKerja['nama'] ?? $p->jadwalSnapshot?->unit?->nama],
            'periode' => [
                'id' => $p->periode_id, 'nama' => $periodeBeku['nama'] ?? $p->periode?->nama, 'urutan' => $p->periode?->urutan,
            ],
            'nilai' => $nilai,
            'status_perhitungan' => $frozen['status_perhitungan'] ?? null,
            'target' => $target,
            'target_pk' => $targetPk,
            // Rasio hanya bermakna pada target positif dan arah yang dikenal; selain itu tidak dihitung.
            'capaian_persen' => $tanpaTarget || ! in_array($arah, ['naik_baik', 'turun_baik'], true)
                ? null : $this->capaian->handle($nilai, $target, (string) $arah),
            'selisih_target_pk' => $this->hitung->differenceFromPk($nilai, $targetPk),
            'riwayat' => $p->statusCapaian->map(fn (StatusCapaian $status) => [
                'id' => $status->id, 'status' => $status->status, 'sumber' => $status->sumber,
                'versi_id' => $status->pengukuran_versi_id, 'versi_nomor' => $status->versi?->nomor,
                'penetap' => $status->ditetapkanOleh?->nama, 'ditetapkan_pada' => $status->created_at?->toIso8601String(),
            ])->values()->all(),
            // Penetapan hanya untuk versi tanpa status berlaku; revisi hanya untuk versi yang sudah punya status berlaku.
            'can' => $berlaku === null
                ? ['tetapkan' => $bolehUnit, 'revisi' => false]
                : ['tetapkan' => false, 'revisi' => $bolehUnit],
        ];

        if ($berlaku !== null) {
            $row['status_berlaku'] = [
                'id' => $berlaku->id,
                'status' => $berlaku->status,
                'sumber' => $berlaku->sumber,
                'ditetapkan_pada' => $berlaku->created_at?->toIso8601String(),
                'penetap' => $berlaku->ditetapkanOleh?->nama,
                'versi_id' => $berlaku->pengukuran_versi_id,
                'versi_nomor' => $berlaku->versi?->nomor,
            ];
        }

        return $row;
    }

    /** Angka snapshot tersimpan sebagai desimal; null tetap berbeda dari nol. */
    private function teks(mixed $nilai): ?string
    {
        return $nilai === null ? null : (string) $nilai;
    }
}
