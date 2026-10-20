<?php

namespace App\Actions\Pengukuran;

use App\Models\BuktiDukung;
use App\Models\Kegiatan;
use App\Models\KinerjaSnapshot;
use App\Models\KlaimKegiatan;
use App\Models\PengukuranKinerja;
use App\Models\User;
use App\Policies\PengukuranKinerjaPolicy;
use App\Services\Rekomendasi\RekomendasiBerlaku;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubmitPengukuran
{
    public function __construct(private PengukuranMutationSteps $steps, private PengukuranKinerjaPolicy $policy, private SubmissionPrerequisites $prerequisites, private RekomendasiBerlaku $rekomendasi) {}

    /**
     * Pengajuan menyimpan input lalu membekukan satu versi: nilai, target, snapshot, pengaju/jalur/dasar izin, PIC efektif,
     * bukti, klaim, dan persyaratan saat ini. Waiver, riwayat, dan audit berhasil atau batal bersama versi.
     */
    public function handle(User $actor, string $id, array $data): PengukuranKinerja
    {
        $path = null;
        $decision = null;
        try {
            return DB::transaction(function () use ($actor, $id, $data, &$path, &$decision) {
                [$actor, $p, $snapshot, $period, $before, $plan] = $this->steps->lock($actor, $id, 'pengukuran:update', 'ajukan', (int) $data['versi'], $decision);
                // Kunci sampai commit; hasil yang sama dipakai untuk token, gerbang, dan versi beku.
                $rekomendasi = $this->rekomendasi->tampilanSebelum($p, kunci: true);
                $dilihat = isset($data['rekomendasi_sebelumnya_id']) ? strtolower($data['rekomendasi_sebelumnya_id']) : null;
                if (($rekomendasi['id'] ?? null) !== $dilihat) {
                    throw ValidationException::withMessages(['rekomendasi_sebelumnya' => 'Rekomendasi periode sebelumnya telah berubah. Muat ulang halaman, periksa tindak lanjut, lalu ajukan kembali.'])->status(409);
                }
                $this->steps->storeInput($actor, $p, $snapshot, $data, $path, $decision);
                $prerequisite = $this->prerequisites->handle($p, $rekomendasi, true, $plan, $period);
                if (! $prerequisite['siap']) {
                    throw ValidationException::withMessages($prerequisite['errors']);
                }
                $pic = $p->effectivePic();
                $provenance = [...$decision, 'unit_id' => $p->targetUnitId(), 'penugasan_id' => $pic?->id, 'pic_id' => $pic?->user_id];
                // Jalur mengikuti sumber allow saat pengajuan; perubahan role/PJ belakangan tidak menulis ulang sejarah ini.
                $version = KinerjaSnapshot::create(['pengukuran_id' => $p->id, 'rencana_aksi_versi_id' => $prerequisite['rencana_aksi_versi']?->id,
                    'jadwal_snapshot_id' => $snapshot->id, 'nomor' => (int) $p->versions()->max('nomor') + 1, 'diajukan_by' => $actor->id, 'diajukan_at' => now(),
                    'jalur_pengajuan' => $this->policy->usesPlanningPath($actor, $p) ? 'perencanaan' : 'pic', 'dasar_izin_pengajuan' => $provenance,
                    'snapshot' => $this->payload($p, $prerequisite, $rekomendasi)]);
                $p->status_alur = 'diajukan';

                return $this->steps->finish($actor, $p, 'pengukuran.ajukan', 'Mengajukan pengukuran untuk reviu.', $decision, $before, ['self_approval' => false]);
            });
        } catch (Throwable $exception) {
            $this->steps->compensate($actor, $id, 'ajukan', $path, $decision, $exception);
            throw $exception;
        }
    }

    private function payload(PengukuranKinerja $p, array $prerequisite, ?array $rekomendasi): array
    {
        $snapshot = $p->jadwalSnapshot;

        return [...$p->only(['indikator_id', 'tahun', 'periode_id', 'nilai', 'sumber_nilai', 'status_perhitungan', 'catatan', 'alasan_tidak_dapat_dihitung', ...PengukuranKinerja::NARASI]),
            'indikator' => [...$snapshot->only(['nama', 'definisi', 'satuan', 'arah', 'tipe_perhitungan', 'presisi', 'desimal_tampilan']), 'id' => $p->indikator_id, 'kode' => $p->indikator->kode],
            'unit_kerja' => $snapshot->unit->only(['id', 'nama']), 'pic' => $p->effectivePic()?->pic?->only(['id', 'nama']),
            'periode' => $p->periode->only(['id', 'nama', 'urutan', 'is_nilai_akhir']), 'target' => $prerequisite['target'], 'tanpa_target_periode' => $prerequisite['tanpa_target_periode'], 'target_pk' => $snapshot->target,
            'rekomendasi_sebelumnya' => $rekomendasi,
            'komponen' => $snapshot->komponen->map(fn ($c) => [...$c->only(['komponen_id', 'kode', 'label', 'peran', 'bobot']), 'nilai' => $p->komponen()->where('komponen_id', $c->komponen_id)->value('nilai')])->all(),
            'klaim' => $this->activityClaims($p, $prerequisite['rencana_aksi_id']),
            'persyaratan_bukti' => $prerequisite['persyaratan_bukti'], 'bukti_dukungs' => $p->buktiDukungs()->current()->get()->map(fn ($b) => $b->only(['id', 'jenis_berkas_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'path', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks']))->all()];
    }

    /** Klaim dan narasi berasal dari kegiatan nyata pada periode ini, bukan salinan RA hidup. */
    private function activityClaims(PengukuranKinerja $p, ?string $planId): array
    {
        if ($planId === null) {
            return [];
        }
        $claims = KlaimKegiatan::where('rencana_aksi_id', $planId)
            ->where(fn ($q) => $q->where('sumber_klaim', 'rencana_aksi')->orWhere('pengukuran_id', $p->id))->lockForUpdate()->get();
        $activities = Kegiatan::whereIn('id', $claims->pluck('kegiatan_id'))->lockForUpdate()->get()->keyBy('id');
        $evidence = BuktiDukung::where('berkasable_type', 'kegiatan')->whereIn('berkasable_id', $activities->where('periode_id', $p->periode_id)->keys())
            ->current()->lockForUpdate()->get()->groupBy('berkasable_id');
        $components = $p->jadwalSnapshot->komponen->pluck('komponen_id')->all();
        $result = [];
        foreach ($claims as $claim) {
            $activity = $activities->get($claim->kegiatan_id);
            if (! $activity || $activity->unit_id !== $p->targetUnitId() || $activity->tahun !== $p->tahun || ($claim->komponen_id && ! in_array($claim->komponen_id, $components, true))) {
                throw ValidationException::withMessages(['pengajuan' => 'Klaim kegiatan tidak cocok dengan konteks indikator/tahun/unit.']);
            }
            if ($activity->periode_id !== $p->periode_id) {
                continue;
            }
            $result[] = [...$claim->only(['id', 'kegiatan_id', 'komponen_id', 'arah_dampak', 'catatan', 'sumber_klaim']),
                'kegiatan' => [...$activity->only(['id', 'periode_id', 'nama', 'tujuan', 'status', 'tanggal_rencana', 'tanggal_realisasi', 'sasaran_peserta', 'realisasi_peserta', 'justifikasi', 'uraian_pelaksanaan', 'kendala', 'strategi_tindaklanjut']),
                    'bukti_dukungs' => ($evidence->get($activity->id) ?? collect())->map(fn ($bukti) => $bukti->only(['id', 'jenis_berkas_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'path', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks']))->all()]];
        }

        return $result;
    }
}
