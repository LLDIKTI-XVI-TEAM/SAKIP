<?php

namespace App\Services\Pengukuran;

use App\Models\IndikatorKinerja;
use App\Models\PenugasanIndikator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DaftarKewajibanPengukuran
{
    /**
     * Satu baris per jadwal × indikator snapshot × periode jadwal terpilih. Versi efektif terbesar dipilih lebih dulu;
     * tanpa versi efektif, versi terendah menjadi identitas "Tidak berlaku". Header dicari dengan identitas unik
     * indikator × tahun × periode, lalu baris memakai snapshot yang dijepit header bila ada. Deny disaring per unit
     * snapshot baris (bukan unit master), dan indikator arsip tanpa header dikeluarkan.
     *
     * @param  list<string>  $periodeJadwalIds  id jadwal_periode terpilih
     * @param  Builder  $unitDitolak  subquery unit yang ditolak untuk izin baca halaman pemanggil
     */
    public function query(array $periodeJadwalIds, Builder $unitDitolak, string $today): Builder
    {
        $effective = 'pm.urutan <= pr.urutan';
        $selected = DB::table('jadwal_snapshot as s')->join('jadwal_periode as jp', 'jp.jadwal_id', '=', 's.jadwal_id')
            ->join('periode as pr', 'pr.id', '=', 'jp.periode_id')->join('periode as pm', 'pm.id', '=', 's.periode_mulai_id')
            ->join('jadwal_tahunan as j', 'j.id', '=', 's.jadwal_id')->whereIn('jp.id', $periodeJadwalIds)
            ->selectRaw("DISTINCT ON (s.jadwal_id, s.indikator_id, jp.id) s.jadwal_id, s.indikator_id, s.id as snapshot_id, jp.id as periode_jadwal_id, jp.periode_id, j.tahun, j.status, {$effective} as berlaku")
            ->orderBy('s.jadwal_id')->orderBy('s.indikator_id')->orderBy('jp.id')->orderByRaw("{$effective} DESC")
            ->orderByRaw("CASE WHEN {$effective} THEN -s.nomor_versi ELSE s.nomor_versi END");

        return DB::query()->fromSub($selected, 'k')
            ->join('indikator_kinerjas as i', 'i.id', '=', 'k.indikator_id')
            ->leftJoin('pengukuran_kinerjas as h', fn ($join) => $join->on('h.indikator_id', '=', 'k.indikator_id')->on('h.tahun', '=', 'k.tahun')->on('h.periode_id', '=', 'k.periode_id'))
            ->join('jadwal_snapshot as cs', DB::raw('COALESCE(h.jadwal_snapshot_id, k.snapshot_id)'), '=', 'cs.id')
            ->join('unit as u', 'u.id', '=', 'cs.unit_id')
            ->leftJoinSub(PenugasanIndikator::effectiveOn($today)->select('indikator_id', 'user_id'), 'pic', 'pic.indikator_id', '=', 'k.indikator_id')
            ->leftJoin('users as picu', 'picu.id', '=', 'pic.user_id')
            ->where(fn ($q) => $q->whereNotNull('h.id')->orWhere('i.status', '!=', IndikatorKinerja::STATUS_ARSIP))
            ->whereNotIn('cs.unit_id', $unitDitolak)
            ->select(['k.jadwal_id', 'k.indikator_id', 'k.periode_jadwal_id', 'k.periode_id', 'k.tahun', 'k.berlaku', 'h.id as pengukuran_id', 'cs.id as jadwal_snapshot_id',
                'cs.nama', 'cs.satuan', 'cs.desimal_tampilan', 'i.kode', 'u.id as unit_id', 'u.nama as unit_nama', 'pic.user_id as pic_id', 'picu.nama as pic_nama',
                'i.sasaran_strategis_id', 'k.status as jadwal_status', 'cs.arah', 'cs.target as target_pk'])
            ->orderByRaw("CASE WHEN k.status = 'aktif' THEN 0 ELSE 1 END")->orderBy('k.tahun')->orderBy('k.jadwal_id')->orderBy('i.kode')->orderBy('i.id');
    }
}
