<?php

namespace App\Actions\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PenugasanIndikator;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Support\Facades\DB;

class DaftarRencanaAksi
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly JendelaTulisRencanaAksi $jendela,
    ) {}

    /**
     * Daftar indikator × tahun pada jadwal aktif sebagai titik masuk Rencana Aksi.
     *
     * Baris berasal dari snapshot jadwal aktif, karena hanya indikator yang
     * dibekukan saat aktivasi yang dapat dibuatkan rencana aksi. Unit baris
     * adalah unit header bila sudah ada header, selain itu unit snapshot
     * terbaru; unit master yang berpindah setelah aktivasi tidak dipakai.
     * Unit itu yang ditampilkan dan yang disaring terhadap deny
     * `rencana_aksi:read` di database (deny menang; izin baca global sudah
     * diperiksa controller). PJ tetap PJ efektif hari ini. Indikator yang PJ
     * efektifnya pengguna ini tampil paling atas. Aksi "buat" mensyaratkan
     * indikator bukan arsip, unit aktif, PJ efektif, izin unit, dan
     * `JendelaTulisRencanaAksi`, sumber aturan yang sama dengan
     * `EnsureDraftRencanaAksi`, sehingga tombol tidak ditawarkan untuk
     * pembuatan yang pasti ditolak (dan diaudit sebagai penolakan), termasuk
     * indikator yang pindah unit setelah aktivasi: unit snapshot terbaru wajib
     * sama dengan unit master, cermin `pastikanSnapshotTersedia`.
     *
     * @return array{daftar: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function handle(User $actor): array
    {
        $hariIni = today(config('app.business_timezone'))->toDateString();
        $jadwalAktif = JadwalTahunan::where('status', 'aktif')->get()->keyBy('id');
        $unitDitolak = $this->resolver->unitDitolak($actor, PermissionCodes::RENCANA_AKSI_READ);

        $halaman = IndikatorKinerja::query()
            ->select('indikator_kinerjas.*', 'snap.jadwal_id', 'snap.unit_id as snap_unit_id', 'j.tahun as tahun_jadwal', 'ra.id as rencana_aksi_id', 'ra.status_alur', 'pj.user_id as pj_user_id', 'pju.nama as pj_nama', 'unit_baris.nama as unit_baris_nama')
            // Satu baris per (jadwal, indikator): snapshot versi terbaru.
            ->joinSub(JadwalSnapshot::query()->selectRaw('DISTINCT ON (jadwal_id, indikator_id) jadwal_id, indikator_id, unit_id')->whereIn('jadwal_id', $jadwalAktif->keys())->orderBy('jadwal_id')->orderBy('indikator_id')->orderByDesc('nomor_versi'), 'snap', 'snap.indikator_id', '=', 'indikator_kinerjas.id')
            ->join('jadwal_tahunan as j', 'j.id', '=', 'snap.jadwal_id')
            ->leftJoin('rencana_aksi as ra', fn ($join) => $join->on('ra.indikator_id', '=', 'indikator_kinerjas.id')->on('ra.tahun', '=', 'j.tahun'))
            ->leftJoinSub(PenugasanIndikator::effectiveOn($hariIni)->select('indikator_id', 'user_id'), 'pj', 'pj.indikator_id', '=', 'indikator_kinerjas.id')
            ->leftJoin('users as pju', 'pju.id', '=', 'pj.user_id')
            ->join('unit as unit_baris', DB::raw('COALESCE(ra.unit_id, snap.unit_id)'), '=', 'unit_baris.id')
            ->whereNotIn(DB::raw('COALESCE(ra.unit_id, snap.unit_id)'), $unitDitolak)
            ->orderByRaw('CASE WHEN pj.user_id = ? THEN 0 ELSE 1 END', [$actor->id])
            ->orderBy('j.tahun')
            ->orderBy('indikator_kinerjas.kode')
            ->orderBy('indikator_kinerjas.id')
            ->with('unit:id,nama,status')
            ->paginate(20)
            ->withQueryString();

        /** @var array<string, PermissionDecision> $izinBuat */
        $izinBuat = [];
        $daftar = $halaman->getCollection()->map(function (IndikatorKinerja $baris) use ($actor, $jadwalAktif, &$izinBuat): array {
            $adaHeader = $baris->getAttribute('rencana_aksi_id') !== null;
            $buat = false;
            $unitBeku = (string) $baris->getAttribute('snap_unit_id');
            if (! $adaHeader && ! $baris->isArsip() && $baris->unit?->status === 'aktif' && $baris->getAttribute('pj_user_id') !== null
                && $unitBeku === (string) $baris->unit_id) {
                $unitId = (string) $baris->unit_id;
                $keputusan = $izinBuat[$unitId] ??= $this->resolver->resolve($actor, PermissionCodes::RENCANA_AKSI_CREATE, $unitId);
                $buat = $keputusan->allowed
                    && $this->jendela->alasanTolak($actor, $keputusan, $baris, $jadwalAktif[$baris->getAttribute('jadwal_id')], 'pembuatan', [], (string) $baris->getAttribute('pj_user_id')) === null;
            }

            return [
                'indikator_id' => $baris->id,
                'kode' => $baris->kode,
                'nama' => $baris->nama,
                'unit_nama' => $baris->getAttribute('unit_baris_nama'),
                'tahun' => (int) $baris->getAttribute('tahun_jadwal'),
                'pj_nama' => $baris->getAttribute('pj_nama'),
                'milik_saya' => (string) $baris->getAttribute('pj_user_id') === (string) $actor->id,
                'rencana_aksi' => $adaHeader ? ['id' => $baris->getAttribute('rencana_aksi_id'), 'status_alur' => $baris->getAttribute('status_alur')] : null,
                'can' => ['create' => $buat],
            ];
        })->values()->all();

        return [
            'daftar' => $daftar,
            'pagination' => ['current_page' => $halaman->currentPage(), 'last_page' => $halaman->lastPage(), 'total' => $halaman->total(), 'prev_page_url' => $halaman->previousPageUrl(), 'next_page_url' => $halaman->nextPageUrl()],
        ];
    }
}
