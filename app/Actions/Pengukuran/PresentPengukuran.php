<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PresentPengukuran
{
    public function __construct(private SubmissionPrerequisites $prerequisites, private EvaluateEvidence $evidence) {}

    /**
     * Audit, target RA, dan PIC efektif dibaca sekaligus per halaman, tanpa query per baris.
     *
     * @param  Collection<int, PengukuranKinerja>  $rows
     */
    public function prepareSummary(Collection $rows): void
    {
        $rows->loadMissing(['latestVersion', 'jadwalSnapshot']);
        $pics = PenugasanIndikator::with('pic:id,nama')->whereIn('indikator_id', $rows->pluck('indikator_id'))
            ->whereDate('tanggal_mulai_berlaku', '<=', today())->distinct('indikator_id')->orderBy('indikator_id')
            ->orderByDesc('tanggal_mulai_berlaku')->orderByDesc('created_at')->get()->keyBy('indikator_id');
        $versions = $rows->map(fn ($row) => $row->latestVersion?->id)->filter()->all();
        $ids = $versions === [] ? [] : DB::table('audit_log')->where('objek_tipe', 'pengukuran')->whereIn('objek_id', $rows->modelKeys())
            ->where('tindakan', 'pengukuran.verifikasi')->where('nilai_baru->self_approval', true)
            ->whereIn(DB::raw("nilai_baru->'versi_pengajuan'->>'id'"), $versions)->pluck('objek_id')->all();
        $plans = RencanaAksi::whereIn('indikator_id', $rows->pluck('indikator_id'))->whereIn('tahun', $rows->pluck('tahun'))
            ->where('status_alur', 'disahkan')->get()->keyBy(fn ($plan) => $plan->indikator_id.':'.$plan->tahun);
        $planVersions = RencanaAksiVersi::whereIn('rencana_aksi_id', $plans->modelKeys())
            ->whereRaw('nomor = (select max(rv.nomor) from rencana_aksi_versi rv where rv.rencana_aksi_id = rencana_aksi_versi.rencana_aksi_id)')
            ->whereNotNull('disahkan_at')->whereNotNull('disahkan_by')->get()->keyBy('rencana_aksi_id');
        foreach ($rows as $row) {
            $row->setAttribute('summary_pic', $pics->get($row->indikator_id)?->pic?->only(['id', 'nama']));
            $row->setAttribute('self_verified', in_array($row->id, $ids, true));
            $plan = $plans->get($row->indikator_id.':'.$row->tahun);
            $planVersion = $plan ? $planVersions->get($plan->id) : null;
            $target = null;
            if ($planVersion && $plan->jadwal_snapshot_id === $row->jadwal_snapshot_id
                && $planVersion->jadwal_snapshot_id === $row->jadwal_snapshot_id && $plan->unit_id === $row->targetUnitId()) {
                $periodTarget = collect($planVersion->snapshot['target_periode'] ?? [])->firstWhere('periode_id', $row->periode_id);
                $target = $periodTarget['nilai'] ?? null;
            }
            $row->setAttribute('summary_target', $target);
        }
    }

    public function handle(PengukuranKinerja $p, User $actor, bool $detail = false): array
    {
        $p->loadMissing(['indikator', 'periode', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion']);
        $context = $p->jadwalSnapshot;
        $version = $p->latestVersion;
        $frozen = in_array($p->status_alur, ['diajukan', 'diverifikasi', 'disahkan'], true) ? $version?->snapshot : null;
        $data = ['id' => $p->id, 'versi' => $p->versi, 'status' => $p->status_alur, 'nomor_pengajuan' => $version?->nomor ?? 0, 'jalur_pengajuan' => $version?->jalur_pengajuan,
            'self_approval' => (bool) $p->getAttribute('self_verified') || $version && $version->jalur_pengajuan === 'perencanaan' && $version->disahkan_by === $version->diajukan_by,
            'nilai' => $frozen ? $frozen['nilai'] : $p->nilai, 'status_perhitungan' => $frozen ? $frozen['status_perhitungan'] : $p->status_perhitungan,
            'sumber_nilai' => $p->sumber_nilai === 'historis' ? 'historis' : ($context->tipe_perhitungan === 'manual' ? 'manual' : 'komponen'),
            'target' => $frozen ? $frozen['target'] : $p->getAttribute('summary_target'), 'catatan' => $frozen ? $frozen['catatan'] : $p->catatan,
            'alasan_tidak_dapat_dihitung' => $frozen ? $frozen['alasan_tidak_dapat_dihitung'] : $p->alasan_tidak_dapat_dihitung,
            'diajukan_pada' => $version?->diajukan_at?->toIso8601String(),
            'penugasan_indikator' => ['indikator_kinerja' => $frozen ? [...$frozen['indikator'], 'definisi_operasional' => $frozen['indikator']['definisi']] : [...$context->only(['nama', 'satuan', 'arah', 'tipe_perhitungan', 'presisi', 'desimal_tampilan']), 'kode' => $p->indikator->kode, 'definisi_operasional' => $context->definisi],
                'unit_kerja' => $frozen ? $frozen['unit_kerja'] : $context->unit->only(['id', 'nama']), 'pic' => $frozen ? $frozen['pic'] : ($detail ? $p->effectivePic()?->pic?->only(['id', 'nama']) : $p->getAttribute('summary_pic'))],
            'periode_jadwal' => ['id' => $p->periode_id, 'nama_periode' => $frozen ? $frozen['periode']['nama'] : $p->periode->nama, 'urutan' => $p->periode->urutan, 'tahun' => $p->tahun],
            'bukti_count' => $frozen ? count($frozen['bukti_dukungs']) : (int) ($p->bukti_dukungs_count ?? 0), 'bukti_dukungs' => [], 'komponen' => [], 'persyaratan_bukti' => [],
            'prasyarat' => ['siap' => false, 'alasan' => []], 'unggahan_aktif' => false,
            'can' => ['view' => true, 'update' => false, 'submit' => false, 'verify' => false, 'ratify' => false, 'return' => false, 'evidence' => false, 'uploadEvidence' => false]];
        if (! $detail) {
            return $data;
        }
        $can = [];
        foreach (['view' => 'view', 'update' => 'update', 'submit' => 'submit', 'verify' => 'verify', 'ratify' => 'ratify', 'return' => 'returnMeasurement', 'evidence' => 'viewEvidence', 'uploadEvidence' => 'uploadEvidence'] as $key => $ability) {
            $can[$key] = Gate::forUser($actor)->allows($ability, $p);
        }
        $data['can'] = $can;
        $data['unggahan_aktif'] = $this->evidence->settings()['unggahan_aktif'];
        $data['komponen'] = $frozen ? $frozen['komponen'] : $context->komponen->map(function ($c) use ($p) {
            $value = $p->komponen->firstWhere('komponen_id', $c->komponen_id)?->nilai;

            return [...$c->only(['komponen_id', 'kode', 'label', 'peran', 'bobot']), 'nilai' => $value];
        })->all();
        if ($frozen) {
            $data['prasyarat'] = ['siap' => true, 'alasan' => []];
            $data['persyaratan_bukti'] = $frozen['persyaratan_bukti'];
        } else {
            $requirements = $this->prerequisites->handle($p);
            $data['prasyarat'] = ['siap' => $requirements['siap'], 'alasan' => $requirements['alasan']];
            $data['persyaratan_bukti'] = $requirements['persyaratan_bukti'];
            $data['target'] = $requirements['target'];
        }
        if ($can['evidence']) {
            $items = $frozen ? $frozen['bukti_dukungs'] : $p->buktiDukungs()->current()->get()->map(fn ($b) => $b->only(['id', 'jenis_berkas_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks']))->all();
            $data['bukti_dukungs'] = array_map(fn ($b) => [...array_intersect_key($b, array_flip(['id', 'jenis_berkas_id', 'mode', 'nama_asli', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks'])),
                'menggantikan_id' => $b['menggantikan_id'] ?? null, 'alasan_koreksi' => $b['alasan_koreksi'] ?? null,
                'download_url' => $b['mode'] === 'file' ? route('pengukuran.bukti', ['id' => $p->id, 'buktiId' => $b['id']]) : ($b['tautan'] ?? null)], $items);
        }
        $data['riwayats'] = $p->riwayats()->with('user:id,nama')->limit(50)->get()->map(fn ($r) => [...$r->only(['id', 'status_dari', 'status_ke', 'catatan']), 'created_at' => $r->created_at->toIso8601String(), 'user' => $r->user?->only(['id', 'nama'])])->all();
        $this->prepareSummary(new Collection([$p]));
        $data['self_approval'] = $data['self_approval'] || (bool) $p->getAttribute('self_verified');
        $ratified = $p->ratifiedVersion;
        $data['snapshot'] = $ratified ? ['snapshot_hash' => hash('sha256', json_encode($ratified->snapshot, JSON_THROW_ON_ERROR)), 'nomor_pengajuan' => $ratified->nomor, 'disahkan_pada' => $ratified->disahkan_at->toIso8601String()] : null;

        return $data;
    }
}
