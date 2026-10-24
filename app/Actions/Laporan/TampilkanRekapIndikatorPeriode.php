<?php

namespace App\Actions\Laporan;

use App\Actions\Pengukuran\CalculatePengukuran;
use App\Actions\Pengukuran\EvaluateEvidence;
use App\Actions\Pengukuran\PresentPengukuran;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\KinerjaSnapshot;
use App\Models\PenugasanIndikator;
use App\Models\RekomendasiPimpinan;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TampilkanRekapIndikatorPeriode
{
    public function __construct(
        private readonly PermissionResolver $izin,
        private readonly PresentPengukuran $present,
        private readonly CalculatePengukuran $hitung,
        private readonly HitungCapaianPeriode $capaian,
        private readonly EvaluateEvidence $bukti,
    ) {}

    /** Satu potret baca untuk kalender, versi resmi, dan ACL; header kerja tidak pernah menjadi sumber angka. */
    public function handle(User $actor, array $filter): array
    {
        abort_unless($this->izin->allows($actor, 'laporan:read'), 403);

        return DB::transaction(function () use ($actor, $filter): array {
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            }
            $jadwalQuery = JadwalTahunan::with('renstra:id,nama')->whereIn('status', ['aktif', 'ditutup']);
            $pilihan = (clone $jadwalQuery)->orderByDesc('tahun')->orderBy('id')->limit(100)->get();
            $jadwal = isset($filter['jadwal_id'])
                ? (clone $jadwalQuery)->findOrFail($filter['jadwal_id'])
                : (clone $jadwalQuery)->when(isset($filter['tahun']), fn ($q) => $q->where('tahun', $filter['tahun']))->orderByDesc('tahun')->orderBy('id')->first();
            if ($jadwal && ! $pilihan->contains('id', $jadwal->id)) {
                $pilihan->push($jadwal);
            }
            $periode = $jadwal ? $jadwal->periode()->with('periode')->get()->sortBy('periode.urutan')->values() : collect();
            if (isset($filter['periode_id'])) {
                abort_unless($periode->contains('periode_id', $filter['periode_id']), 422, 'Periode tidak termasuk jadwal terpilih.');
            }
            $rows = $this->query($jadwal?->id)->whereNotIn(DB::raw('COALESCE(rs.unit_id, s.unit_id)'), $this->izin->unitDitolak($actor, 'laporan:read'))
                ->when(isset($filter['periode_id']), fn (Builder $q) => $q->where('p.id', $filter['periode_id']))
                ->orderBy('s.nama')->orderBy('s.indikator_id')->orderBy('p.urutan')->orderBy('p.id')->paginate(20)->withQueryString();
            $items = collect($rows->items());
            $versions = KinerjaSnapshot::whereIn('id', $items->pluck('versi_id')->filter())->get()->keyBy('id');
            $contexts = JadwalSnapshot::with(['unit:id,nama', 'komponen'])->whereIn('id', $items->pluck('snapshot_id')->merge($versions->pluck('jadwal_snapshot_id')))->get()->keyBy('id');
            $ids = $items->pluck('indikator_id')->unique()->all();
            $plans = RencanaAksiVersi::query()->join('rencana_aksi as ra', 'ra.id', '=', 'rencana_aksi_versi.rencana_aksi_id')
                ->where('ra.jadwal_tahunan_id', $jadwal?->id)->whereIn('ra.indikator_id', $ids)->whereNotNull('rencana_aksi_versi.disahkan_at')
                ->whereIn('rencana_aksi_versi.jadwal_snapshot_id', $contexts->modelKeys())
                ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('rencana_aksi_versi as baru')->whereColumn('baru.rencana_aksi_id', 'rencana_aksi_versi.rencana_aksi_id')
                    ->whereColumn('baru.jadwal_snapshot_id', 'rencana_aksi_versi.jadwal_snapshot_id')->whereNotNull('baru.disahkan_at')->whereColumn('baru.nomor', '>', 'rencana_aksi_versi.nomor'))
                ->get(['rencana_aksi_versi.*', 'ra.indikator_id'])->keyBy(fn ($p) => $p->getAttribute('indikator_id').':'.$p->jadwal_snapshot_id);
            $paired = RencanaAksiVersi::whereIn('id', $versions->pluck('rencana_aksi_versi_id')->filter())->whereNotNull('disahkan_at')->get()->keyBy('id');
            $indicators = IndikatorKinerja::with('sasaranStrategis:id,kode,deskripsi')->whereIn('id', $ids)->get()->keyBy('id');
            $pics = PenugasanIndikator::effectiveOn(today(config('app.business_timezone'))->toDateString())->with('pic:id,nama')->whereIn('indikator_id', $ids)->get()->keyBy('indikator_id');
            $recommendations = RekomendasiPimpinan::whereIn('indikator_id', $ids)->where('tahun', $jadwal?->tahun)
                ->whereIn('periode_id', $items->pluck('periode_id'))->where('status', 'disetujui')
                ->distinct('indikator_id', 'periode_id')->orderBy('indikator_id')->orderBy('periode_id')->orderByDesc('disetujui_pada')->orderByDesc('id')
                ->get(['id', 'indikator_id', 'periode_id', 'isi', 'status', 'disetujui_pada', 'disetujui_oleh_role'])->keyBy(fn ($r) => $r->indikator_id.':'.$r->periode_id);
            $capabilities = $this->izin->decideManyForUnits($actor, ['kegiatan:read'], $items->pluck('unit_id')->unique()->all());
            $data = $items->map(function ($row) use ($versions, $contexts, $plans, $paired, $indicators, $pics, $recommendations, $capabilities): array {
                $version = $versions->get($row->versi_id);
                $context = $contexts->get($version?->jadwal_snapshot_id ?? $row->snapshot_id);
                $plan = $version ? $paired->get($version->rencana_aksi_versi_id) : $plans->get($row->indikator_id.':'.$context->id);
                // RA lain atau konteks baru tidak boleh mengisi target komponen histori yang tidak berpasangan.
                if ($plan && $plan->jadwal_snapshot_id !== $context->id) {
                    $plan = null;
                }

                return $this->baris($row, $version, $context, $plan, $indicators->get($row->indikator_id),
                    $pics->get($row->indikator_id)?->pic?->only(['id', 'nama']), $recommendations->get($row->indikator_id.':'.$row->periode_id),
                    $capabilities[$row->unit_id]['kegiatan:read']['allowed'] ?? false);
            })->all();

            return [
                'filters' => ['jadwal_id' => $jadwal?->id ?? '', 'periode_id' => $filter['periode_id'] ?? ''],
                'jadwal' => $jadwal ? [...$jadwal->only(['id', 'tahun', 'status']), 'renstra' => $jadwal->renstra->nama] : null,
                'pilihan_jadwal' => $pilihan->map(fn ($j) => ['id' => $j->id, 'label' => $j->tahun.' · '.$j->renstra->nama])->all(),
                'pilihan_periode' => $periode->map(fn ($p) => ['id' => $p->periode_id, 'nama' => $p->periode->nama])->all(),
                'baris' => $data,
                'pagination' => Arr::only($rows->toArray(), ['links', 'from', 'to', 'total']),
            ];
        });
    }

    /** Kalender menentukan kewajiban; versi konteks dipilih per periode dan versi pengukuran sah per nomor. */
    private function query(?string $jadwalId): Builder
    {
        return DB::table('jadwal_snapshot as s')->join('periode as mulai', 'mulai.id', '=', 's.periode_mulai_id')
            ->join('jadwal_periode as jp', 'jp.jadwal_id', '=', 's.jadwal_id')->join('periode as p', 'p.id', '=', 'jp.periode_id')
            ->leftJoin('pengukuran_kinerjas as m', function ($join): void {
                $join->on('m.indikator_id', '=', 's.indikator_id')->on('m.periode_id', '=', 'p.id')
                    ->whereExists(fn (Builder $q) => $q->selectRaw('1')->from('jadwal_snapshot as ms')->whereColumn('ms.id', 'm.jadwal_snapshot_id')->whereColumn('ms.jadwal_id', 's.jadwal_id'));
            })
            ->leftJoin('pengukuran_versi as resmi', function ($join): void {
                $join->on('resmi.pengukuran_id', '=', 'm.id')->whereNotNull('resmi.disahkan_at')
                    ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('pengukuran_versi as baru')->whereColumn('baru.pengukuran_id', 'resmi.pengukuran_id')->whereNotNull('baru.disahkan_at')->whereColumn('baru.nomor', '>', 'resmi.nomor'));
            })
            ->leftJoin('jadwal_snapshot as rs', 'rs.id', '=', 'resmi.jadwal_snapshot_id')
            ->where('s.jadwal_id', $jadwalId)->whereColumn('p.urutan', '>=', 'mulai.urutan')
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('jadwal_snapshot as koreksi')->join('periode as efektif', 'efektif.id', '=', 'koreksi.periode_mulai_id')
                ->whereColumn('koreksi.jadwal_id', 's.jadwal_id')->whereColumn('koreksi.indikator_id', 's.indikator_id')->whereColumn('koreksi.nomor_versi', '>', 's.nomor_versi')->whereColumn('efektif.urutan', '<=', 'p.urutan'))
            ->select(['s.id as snapshot_id', 's.indikator_id', 'p.id as periode_id', 'p.nama as periode_nama', 'p.urutan as periode_urutan', 'resmi.id as versi_id'])
            ->selectRaw('COALESCE(rs.unit_id, s.unit_id) as unit_id');
    }

    /** Allowlist props menjaga data kerja, provenance izin, dan isi/path bukti tetap di server. */
    private function baris(object $row, ?KinerjaSnapshot $version, JadwalSnapshot $context, ?RencanaAksiVersi $plan, ?IndikatorKinerja $indicator, ?array $pic, ?RekomendasiPimpinan $recommendation, bool $canReadActivities): array
    {
        $frozen = $version?->snapshot ?? [];
        $realisasi = $version ? $this->present->realisasiVersi($version) : null;
        $targetRa = collect($plan?->snapshot['target_periode'] ?? [])->firstWhere('periode_id', $row->periode_id);
        $target = $version ? ($frozen['target'] ?? null) : ($targetRa['nilai'] ?? null);
        $tanpaTarget = $version ? (bool) ($frozen['tanpa_target_periode'] ?? false) : ($targetRa['status_perhitungan'] ?? null) === 'tidak_ditargetkan';
        $meta = $version ? Arr::only($frozen['indikator'] ?? [], ['kode', 'nama', 'satuan', 'arah', 'definisi', 'tipe_perhitungan', 'presisi', 'desimal_tampilan'])
            : [...$context->only(['nama', 'satuan', 'arah', 'definisi', 'tipe_perhitungan', 'presisi', 'desimal_tampilan']), 'kode' => $indicator?->kode];
        $meta = ['id' => $row->indikator_id, 'kode' => null, 'nama' => null, 'satuan' => null, 'arah' => $context->arah, 'tipe_perhitungan' => $context->tipe_perhitungan, 'presisi' => $context->presisi, 'desimal_tampilan' => $context->desimal_tampilan, 'definisi' => null, ...$meta];
        $status = ! $version ? 'belum_disahkan' : match ($realisasi['status_perhitungan']) {
            'belum_dapat_diukur' => 'belum_dapat_diukur',
            'tidak_dapat_dihitung' => 'tidak_dapat_dihitung',
            'terhitung' => $tanpaTarget ? 'tanpa_target_periode' : 'disahkan',
            default => 'konteks_tidak_lengkap',
        };
        $nilai = ($realisasi['status_perhitungan'] ?? null) === 'terhitung' && $realisasi['nilai'] !== null ? (string) $realisasi['nilai'] : null;
        $keteranganCapaian = $version && $nilai !== null && ! $tanpaTarget && $target !== null && BigDecimal::of((string) $target)->isLessThanOrEqualTo('0')
            ? 'Tidak dihitung: target periode 0 atau negatif' : null;
        $pk = $version ? ($frozen['target_pk'] ?? $context->target) : $context->target;
        $sasaran = $frozen['sasaran'] ?? $plan?->snapshot['sasaran'] ?? null;
        if (! $version && ! $plan) {
            $sasaran = $indicator?->sasaranStrategis?->only(['id', 'kode', 'deskripsi']);
        }
        $targetKomponen = collect($targetRa['komponen'] ?? [])->keyBy('komponen_id');
        $definitions = $version ? ($frozen['komponen'] ?? []) : $context->komponen->toArray();
        $components = array_map(fn (array $c) => [
            ...Arr::only($c, ['komponen_id', 'kode', 'label', 'peran', 'bobot']),
            'target' => isset($targetKomponen[$c['komponen_id']]['nilai']) ? (string) $targetKomponen[$c['komponen_id']]['nilai'] : null,
            'realisasi' => $version && isset($c['nilai']) ? (string) $c['nilai'] : null,
        ], $definitions);

        return [
            'id' => $row->indikator_id.':'.$row->periode_id,
            'versi' => $version ? ['id' => $version->id, 'nomor' => $version->nomor, 'disahkan_pada' => $version->disahkan_at->toIso8601String()] : null,
            'periode' => ['id' => $row->periode_id, 'nama' => $version ? ($frozen['periode']['nama'] ?? 'Tidak tercatat pada versi ini')
                : ($targetRa ? ($targetRa['periode_nama'] ?? 'Tidak tercatat pada versi ini') : $row->periode_nama), 'urutan' => $row->periode_urutan],
            'indikator' => $meta, 'sasaran' => $sasaran ? Arr::only($sasaran, ['id', 'kode', 'deskripsi']) : null,
            'unit' => ['id' => $context->unit_id, 'nama' => $version ? ($frozen['unit_kerja']['nama'] ?? null) : $context->unit?->nama],
            'pic' => $version ? (isset($frozen['pic']) ? Arr::only($frozen['pic'], ['id', 'nama']) : null) : $pic,
            'pic_saat_ini' => $pic,
            'baseline' => $context->baseline, 'target_pk' => $pk === null ? null : (string) $pk,
            'target_periode' => $target === null ? null : (string) $target, 'tanpa_target_periode' => $tanpaTarget,
            'nilai' => $nilai, 'status_perhitungan' => $realisasi['status_perhitungan'] ?? null, 'status_laporan' => $status,
            'capaian_persen' => $version && ! $tanpaTarget ? $this->capaian->handle($nilai, $target, $meta['arah']) : null,
            'keterangan_capaian' => $keteranganCapaian,
            'status_capaian' => $version && ($tanpaTarget || $status === 'belum_dapat_diukur') ? 'Tidak dinilai' : 'Belum ditetapkan',
            'selisih_target_pk' => $this->hitung->differenceFromPk($nilai, $pk),
            'alasan_tidak_dapat_dihitung' => $realisasi['alasan_tidak_dapat_dihitung'] ?? null,
            'komponen' => $components,
            'narasi' => $version ? array_replace(array_fill_keys(['progres_kegiatan', 'kendala_masalah', 'strategi_tindak_lanjut', 'tindak_lanjut_rekomendasi'], null), Arr::only($frozen, ['progres_kegiatan', 'kendala_masalah', 'strategi_tindak_lanjut', 'tindak_lanjut_rekomendasi'])) : null,
            'kegiatan' => $version && $canReadActivities && array_key_exists('klaim', $frozen) ? $this->kegiatan($frozen['klaim']) : null,
            'bukti' => $version ? $this->statusBukti($frozen) : null,
            'rekomendasi' => $recommendation ? ['id' => $recommendation->id, 'isi' => $recommendation->isi, 'disetujui_pada' => $recommendation->disetujui_pada->toIso8601String(), 'cadangan' => $recommendation->isCadangan()] : null,
            'rekomendasi_sebelumnya' => isset($frozen['rekomendasi_sebelumnya']) ? Arr::only($frozen['rekomendasi_sebelumnya'], ['isi', 'tahun', 'periode_nama']) : null,
            'can' => ['lihat_kegiatan' => $canReadActivities],
        ];
    }

    /** Key hilang berbeda dari anggaran null atau nol; RA dan kegiatan hidup bukan fallback anggaran. */
    private function kegiatan(array $claims): array
    {
        return array_map(function (array $claim): array {
            $activity = $claim['kegiatan'] ?? [];

            return [
                'id' => $claim['id'] ?? $claim['kegiatan_id'] ?? null,
                ...Arr::only($activity, ['nama', 'tujuan', 'status', 'uraian_pelaksanaan', 'kendala', 'strategi_tindaklanjut']),
                'anggaran_tercatat' => array_key_exists('anggaran', $activity),
                'anggaran' => isset($activity['anggaran']) ? (string) $activity['anggaran'] : null,
                'kegiatan_bersama' => $claim['kegiatan_bersama'] ?? null,
                'sumber_klaim' => $claim['sumber_klaim'] ?? null,
            ];
        }, $claims);
    }

    /** Status memakai evaluasi yang dibekukan, tanpa membaca ulang persyaratan atau mengirim isi bukti. */
    private function statusBukti(array $frozen): array
    {
        $requirements = $frozen['persyaratan_bukti'] ?? null;
        if (! is_array($requirements)) {
            return ['status' => 'tidak_tercatat', 'histori_tidak_dapat_dipenuhi' => false];
        }
        $summary = $this->bukti->ringkasan($requirements);

        return ['status' => $summary['lengkap'] ? 'lengkap' : 'belum_lengkap', 'total' => $summary['total_wajib'], 'terpenuhi' => $summary['terpenuhi_wajib'],
            'histori_tidak_dapat_dipenuhi' => collect($requirements)->contains(fn ($r) => (bool) ($r['pemenuhan']['tidak_dapat_dipenuhi'] ?? false))];
    }
}
