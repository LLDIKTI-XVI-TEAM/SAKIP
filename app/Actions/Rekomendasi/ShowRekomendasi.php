<?php

namespace App\Actions\Rekomendasi;

use App\Models\PengukuranKinerja;
use App\Models\RekomendasiPimpinan;
use App\Models\User;
use App\Policies\PengukuranKinerjaPolicy;
use App\Services\Authorization\PermissionResolver;
use App\Services\Rekomendasi\RekomendasiBerlaku;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ShowRekomendasi
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly RekomendasiBerlaku $berlaku,
        private readonly PengukuranKinerjaPolicy $pengukuranPolicy,
    ) {}

    /**
     * Detail tetap menunjuk record yang diminta; status berlaku dan rangkaian dihitung untuk kombinasi yang sama.
     * Izin detail memakai unit snapshot, sedangkan narasi pengukuran memiliki pemeriksaan akses tersendiri.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function handle(User $actor, RekomendasiPimpinan $row, array $query): array
    {
        $this->authorize($actor);
        $context = DB::table('jadwal_snapshot as s')->join('jadwal_tahunan as j', 'j.id', '=', 's.jadwal_id')
            ->join('jadwal_periode as jp', 'jp.jadwal_id', '=', 'j.id')->join('periode as p', 'p.id', '=', 'jp.periode_id')
            ->join('periode as mulai', 'mulai.id', '=', 's.periode_mulai_id')
            ->join('indikator_kinerjas as i', 'i.id', '=', 's.indikator_id')->join('unit as u', 'u.id', '=', 's.unit_id')
            ->where('s.indikator_id', $row->indikator_id)->where('j.tahun', $row->tahun)->where('p.id', $row->periode_id)
            ->whereColumn('mulai.urutan', '<=', 'p.urutan')->orderByDesc('s.nomor_versi')
            ->first(['s.nama', 's.unit_id', 'i.kode', 'u.nama as unit', 'j.id as jadwal_id', 'p.nama as periode', 'p.urutan']);
        abort_unless($context, 404);
        $this->authorize($actor, $context->unit_id);
        Validator::make($query, ['riwayat_page' => ['nullable', 'integer', 'min:1']])->validate();

        $berlakuId = $this->berlaku->untuk($row->indikator_id, $row->tahun, $row->periode_id)?->id;
        $row->load(['penyusun:id,nama', 'penyetuju:id,nama']);
        // Satu draf tidak membatasi jumlah revisi disetujui; seluruh rangkaian tetap tersedia lewat pagination.
        $riwayat = RekomendasiPimpinan::with(['penyusun:id,nama', 'penyetuju:id,nama'])
            ->where($row->only(['indikator_id', 'tahun', 'periode_id']))
            ->orderByRaw('disetujui_pada ASC NULLS LAST')->orderBy('id')->paginate(20, ['*'], 'riwayat_page')
            ->through(fn (RekomendasiPimpinan $r) => $this->tampilan($r, $berlakuId));
        $pengukuran = $this->berikutnya($row, $context);
        $bolehBaca = $pengukuran
            ? $this->pengukuranPolicy->view($actor, $pengukuran)
            : $this->resolver->allows($actor, 'pengukuran:read', $context->unit_id);

        return [
            'rekomendasi' => $this->tampilan($row, $berlakuId),
            'indikator' => ['kode' => $context->kode, 'nama' => $context->nama, 'unit' => $context->unit],
            'periode' => ['nama' => $context->periode, 'tahun' => $row->tahun],
            'berlaku_url' => $berlakuId ? '/rekomendasi/'.$berlakuId : null,
            'kelola_url' => '/rekomendasi?'.http_build_query(['jadwal_id' => $context->jadwal_id, 'periode_id' => $row->periode_id]),
            'riwayat' => $riwayat,
            'can' => ['viewFollowUp' => $bolehBaca],
            ...($bolehBaca ? ['tindak_lanjut' => $pengukuran ? $this->tindakLanjut($row, $pengukuran) : null] : []),
        ];
    }

    private function authorize(User $actor, ?string $unitId = null): void
    {
        if (! $this->resolver->allows($actor, PermissionCodes::REKOMENDASI_TETAPKAN, $unitId)) {
            throw new AuthorizationException('Anda tidak memiliki izin membaca rekomendasi pimpinan ini.');
        }
    }

    /** @return array<string, mixed> */
    private function tampilan(RekomendasiPimpinan $row, ?string $berlakuId): array
    {
        return [
            ...$row->only(['id', 'isi', 'rujukan_sumber', 'disetujui_oleh_role']),
            'status_tampilan' => $row->status === RekomendasiPimpinan::STATUS_DRAF ? 'draf' : ($row->id === $berlakuId ? 'berlaku' : 'digantikan'),
            'penyusun' => $row->penyusun->nama, 'created_at' => $row->created_at->toIso8601String(),
            'penyetuju' => $row->penyetuju?->nama, 'disetujui_pada' => $row->disetujui_pada?->toIso8601String(),
            'persetujuan_cadangan' => $row->isCadangan(),
        ];
    }

    /** Kalender menentukan periode berikutnya sebelum mencari header, sehingga periode tanpa data tidak dilompati. */
    private function berikutnya(RekomendasiPimpinan $row, object $context): ?PengukuranKinerja
    {
        $periode = DB::table('jadwal_periode as jp')->join('jadwal_tahunan as j', 'j.id', '=', 'jp.jadwal_id')->join('periode as p', 'p.id', '=', 'jp.periode_id')
            ->where(fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('j.id', $context->jadwal_id)->where('p.urutan', '>', $context->urutan))
                ->orWhere(fn (Builder $q) => $q->where('j.tahun', $row->tahun + 1)->whereIn('j.status', ['aktif', 'ditutup'])
                    ->whereExists(fn (Builder $s) => $s->from('jadwal_snapshot as s')->whereColumn('s.jadwal_id', 'j.id')->where('s.indikator_id', $row->indikator_id))))
            ->orderBy('j.tahun')->orderBy('p.urutan')->first(['j.id as jadwal_id', 'j.tahun', 'jp.periode_id']);

        return $periode ? PengukuranKinerja::with(['jadwalSnapshot', 'periode'])
            ->where(['indikator_id' => $row->indikator_id, 'tahun' => $periode->tahun, 'periode_id' => $periode->periode_id])
            ->whereHas('jadwalSnapshot', fn ($q) => $q->where('jadwal_id', $periode->jadwal_id))->first() : null;
    }

    /** Teks hanya berasal dari versi yang membekukan ID ini; status header diberi label terpisah oleh UI. @return array<string, mixed>|null */
    private function tindakLanjut(RekomendasiPimpinan $row, PengukuranKinerja $p): ?array
    {
        if ($row->status !== RekomendasiPimpinan::STATUS_DISETUJUI) {
            return null;
        }
        $versi = $p->versions()->where('snapshot->rekomendasi_sebelumnya->id', $row->id)->reorder()->orderByDesc('nomor')->first();
        $isi = $versi?->snapshot['tindak_lanjut_rekomendasi'] ?? null;
        if (! is_string($isi) || trim($isi) === '') {
            return null;
        }

        return ['isi' => $isi, 'nomor' => $versi->nomor, 'diajukan_pada' => $versi->diajukan_at->toIso8601String(),
            'status_pengukuran' => $p->status_alur, 'pengajuan_terdahulu' => $versi->nomor < $p->versions()->max('nomor'),
            'periode' => $p->periode->nama, 'tahun' => $p->tahun];
    }
}
