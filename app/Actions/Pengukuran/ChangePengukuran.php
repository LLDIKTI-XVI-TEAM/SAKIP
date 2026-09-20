<?php

namespace App\Actions\Pengukuran;

use App\Actions\Audit\WriteAuditLog;
use App\Models\BuktiDukung;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use App\Models\KinerjaSnapshot;
use App\Models\KlaimKegiatan;
use App\Models\PengukuranKinerja;
use App\Models\PengukuranKomponen;
use App\Models\PeriodeJadwal;
use App\Models\RiwayatPengukuran;
use App\Models\User;
use App\Policies\PengukuranKinerjaPolicy;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class ChangePengukuran
{
    public function __construct(private PermissionResolver $resolver, private WriteAuditLog $audit, private PengukuranKinerjaPolicy $policy,
        private CalculatePengukuran $calculator, private SubmissionPrerequisites $prerequisites, private EvaluateEvidence $evidence) {}

    /** Seluruh mutasi, versi beku, dan audit diserialkan pada header yang sama. */
    public function handle(User $actor, string $id, string $command, array $data): PengukuranKinerja
    {
        $permission = match ($command) {
            'draft','ajukan' => 'pengukuran:update','verifikasi' => 'pengukuran:verifikasi','sahkan' => 'pengukuran:sahkan','kembalikan' => 'pengukuran:kembalikan',default => throw new \InvalidArgumentException('Tindakan tidak dikenal.')
        };
        $path = null;
        $decision = null;
        try {
            return DB::transaction(function () use ($actor, $id, $command, $data, $permission, &$path, &$decision) {
                $actor = User::lockForUpdate()->findOrFail($actor->id);
                $p = PengukuranKinerja::lockForUpdate()->findOrFail($id);
                $snapshot = JadwalSnapshot::lockForUpdate()->findOrFail($p->jadwal_snapshot_id);
                $snapshot->setRelation('jadwal', JadwalTahunan::lockForUpdate()->findOrFail($snapshot->jadwal_id));
                PeriodeJadwal::where('jadwal_id', $snapshot->jadwal_id)->where('periode_id', $p->periode_id)->lockForUpdate()->first();
                $p->setRelation('jadwalSnapshot', $snapshot);
                $decision = $this->resolver->decide($actor, $permission, $p->targetUnitId());
                if (! $decision['allowed']) {
                    throw new AuthorizationException('Izin tindakan tidak tersedia atau telah dicabut.');
                }
                $errors = $this->policy->businessErrors($actor, $p, $command);
                if ($p->versi !== (int) $data['versi']) {
                    $errors[] = 'Data telah berubah. Muat ulang sebelum mengulangi tindakan.';
                }
                if ($errors) {
                    throw ValidationException::withMessages(['versi' => $errors]);
                }
                $before = $this->auditState($p);
                $reason = match ($command) {
                    'draft' => 'Menyimpan draf pengukuran.','ajukan' => 'Mengajukan pengukuran untuk reviu.','verifikasi' => 'Memverifikasi pengukuran.','sahkan' => 'Mengesahkan pengukuran.','kembalikan' => trim((string) ($data['catatan'] ?? ''))
                };
                if ($reason === '') {
                    throw ValidationException::withMessages(['catatan' => 'Alasan pengembalian wajib diisi.']);
                }
                $selfApproval = false;
                if (in_array($command, ['draft', 'ajukan'], true)) {
                    $definitions = $snapshot->komponen->map(fn ($c) => $c->only(['komponen_id', 'kode', 'label', 'peran', 'bobot', 'urutan']))->all();
                    if ($snapshot->tipe_perhitungan !== 'manual' && array_key_exists('nilai', $data)) {
                        throw ValidationException::withMessages(['nilai' => 'Nilai indikator nonmanual dihitung server dari komponen.']);
                    }
                    if ($snapshot->tipe_perhitungan === 'manual' && ! empty($data['komponen'])) {
                        throw ValidationException::withMessages(['komponen' => 'Indikator manual memakai nilai langsung tanpa komponen semu.']);
                    }
                    $values = [];
                    foreach ($data['komponen'] ?? [] as $value) {
                        if (array_key_exists($value['komponen_id'], $values)) {
                            throw ValidationException::withMessages(['komponen' => 'Komponen tidak boleh dikirim berulang.']);
                        }
                        $values[$value['komponen_id']] = $value['nilai'];
                    }
                    try {
                        $result = $this->calculator->handle($snapshot->tipe_perhitungan, $snapshot->presisi, $definitions, $values, isset($data['nilai']) ? (float) $data['nilai'] : null);
                    } catch (\InvalidArgumentException $exception) {
                        throw ValidationException::withMessages(['nilai' => $exception->getMessage()]);
                    }
                    foreach ($definitions as $definition) {
                        PengukuranKomponen::updateOrCreate(['pengukuran_id' => $p->id, 'komponen_id' => $definition['komponen_id']], ['nilai' => $values[$definition['komponen_id']] ?? null, 'updated_by' => $actor->id, 'updated_at' => now()]);
                    }
                    $p->fill([...$result, 'catatan' => $data['catatan'] ?? null, 'alasan_tidak_dapat_dihitung' => $data['alasan_tidak_dapat_dihitung'] ?? null]);
                    $this->appendEvidence($actor, $p, $data['bukti'] ?? null, $path, $decision);
                    if ($command === 'ajukan') {
                        $prerequisite = $this->prerequisites->handle($p, true);
                        if (! $prerequisite['siap']) {
                            throw ValidationException::withMessages(['pengajuan' => $prerequisite['alasan']]);
                        }
                        $pic = $p->effectivePic();
                        $provenance = [...$decision, 'unit_id' => $p->targetUnitId(), 'penugasan_id' => $pic?->id, 'pic_id' => $pic?->user_id];
                        $version = KinerjaSnapshot::create(['pengukuran_id' => $p->id, 'rencana_aksi_versi_id' => $prerequisite['rencana_aksi_versi']->id,
                            'jadwal_snapshot_id' => $snapshot->id, 'nomor' => (int) $p->versions()->max('nomor') + 1, 'diajukan_by' => $actor->id, 'diajukan_at' => now(),
                            'jalur_pengajuan' => $this->policy->usesPlanningPath($actor, $p) ? 'perencanaan' : 'pic', 'dasar_izin_pengajuan' => $provenance,
                            'snapshot' => $this->submissionPayload($p, $prerequisite)]);
                        $waivers = collect($prerequisite['persyaratan_bukti'])
                            ->filter(fn ($requirement) => $requirement['wajib'] && $requirement['pemenuhan']['mode_dikecualikan'] !== [])
                            ->map(fn ($requirement) => ['jenis_berkas_id' => $requirement['id'], 'nama' => $requirement['nama'],
                                'mode' => $requirement['pemenuhan']['mode_dikecualikan'], 'alasan' => $requirement['pemenuhan']['alasan_pengecualian']])->values()->all();
                        if ($waivers !== []) {
                            // Waiver dan versi harus berhasil bersama; kegagalan audit membatalkan pengajuan.
                            $this->writeAudit($actor, $p->id, 'berkas.tandai_tidak_dapat_dipenuhi', 'Kewajiban mode file dikecualikan karena unggahan dinonaktifkan.', $decision, null,
                                ['versi_pengajuan_id' => $version->id, 'nomor_pengajuan' => $version->nomor, 'pengecualian' => $waivers]);
                        }
                        $p->status_alur = 'diajukan';
                    } else {
                        $p->status_alur = 'draft';
                    }
                } elseif ($command === 'kembalikan') {
                    $p->status_alur = 'dikembalikan';
                } else {
                    $version = $p->latestVersion;
                    $selfApproval = $version->diajukan_by === $actor->id && $version->jalur_pengajuan === 'perencanaan';
                    if ($command === 'verifikasi') {
                        $p->status_alur = 'diverifikasi';
                    } else {
                        $version->update(['disahkan_by' => $actor->id, 'disahkan_at' => now()]);
                        $p->status_alur = 'disahkan';
                    }
                }
                $p->versi++;
                $p->save();
                $p->unsetRelation('latestVersion');
                if ($before['status_alur'] !== $p->status_alur) {
                    RiwayatPengukuran::create(['pengukuran_kinerja_id' => $p->id, 'user_id' => $actor->id, 'status_dari' => $before['status_alur'], 'status_ke' => $p->status_alur, 'catatan' => $reason]);
                }
                $after = [...$this->auditState($p), 'self_approval' => $selfApproval];
                $this->writeAudit($actor, $p->id, 'pengukuran.'.$command, $reason, $decision, $before, $after);

                return $p;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException) && PengukuranKinerja::whereKey($id)->exists()) {
                $this->writeAudit($actor, $id, 'pengukuran.ditolak', 'Tindakan '.$command.' ditolak.', $decision, null,
                    ['tindakan_diminta' => $command, 'jenis_penolakan' => $exception instanceof AuthorizationException ? 'otorisasi' : 'validasi_bisnis',
                        'alasan_penolakan' => $exception instanceof ValidationException ? $exception->errors() : $exception->getMessage()]);
            }
            throw $exception;
        }
    }

    private function appendEvidence(User $actor, PengukuranKinerja $p, ?array $data, ?string &$path, ?array &$denialDecision): void
    {
        if (! $data) {
            return;
        }
        $decision = $this->resolver->decide($actor, 'berkas:upload', $p->targetUnitId());
        if (in_array($decision['reason'], ['explicit_deny', 'unknown_permission', 'inactive_user', 'invalid_scope'], true)) {
            $denialDecision = $decision;
            throw new AuthorizationException('Izin unggah bukti telah dicabut.');
        }
        $settings = $this->evidence->settings();
        $requirement = null;
        if (! empty($data['jenis_berkas_id'])) {
            $requirement = JenisBerkas::whereKey($data['jenis_berkas_id'])->where('aktif', true)->where('tahap', 'pengukuran')
                ->where(fn ($q) => $q->whereNull('indikator_id')->orWhere('indikator_id', $p->indikator_id))->lockForUpdate()->first();
            if (! $requirement || ! $requirement->{'izinkan_'.$data['mode']}) {
                throw ValidationException::withMessages(['bukti.mode' => 'Mode atau persyaratan bukti tidak berlaku untuk pengukuran ini.']);
            }
        }
        $predecessor = null;
        $correctionReason = null;
        if (! empty($data['menggantikan_id'])) {
            $predecessor = $p->buktiDukungs()->current()->whereKey($data['menggantikan_id'])->lockForUpdate()->first();
            if (! $predecessor || $predecessor->jenis_berkas_id !== $requirement?->id) {
                throw ValidationException::withMessages(['bukti.menggantikan_id' => 'Bukti yang diganti harus masih berlaku pada pengukuran dan persyaratan yang sama.']);
            }
            $correctionReason = trim((string) ($data['alasan_koreksi'] ?? ''));
            if ($correctionReason === '') {
                throw ValidationException::withMessages(['bukti.alasan_koreksi' => 'Alasan koreksi bukti wajib diisi.']);
            }
        }
        // UUID baru dan pendahulu yang masih berlaku membentuk rantai tanpa siklus/fork di bawah lock header.
        $attributes = ['berkasable_type' => 'pengukuran', 'berkasable_id' => $p->id, 'jenis_berkas_id' => $requirement?->id,
            'menggantikan_id' => $predecessor?->id, 'alasan_koreksi' => $correctionReason,
            'mode' => $data['mode'], 'uploaded_by' => $actor->id, 'created_at' => now()];
        if ($data['mode'] === 'file') {
            if (! $settings['unggahan_aktif']) {
                throw ValidationException::withMessages(['bukti.file' => 'Unggahan file sedang dinonaktifkan. Mode tautan/teks tetap mengikuti persyaratannya.']);
            }
            $max = $requirement?->ukuran_maks_kb ?? $settings['ukuran_maks_kb'];
            $formats = $requirement?->format_diizinkan ?: $settings['format_diizinkan'];
            Validator::make($data, ['file' => ['required', 'file', 'max:'.$max, 'mimes:'.$formats]])->validate();
            $file = $data['file'];
            $path = $file->store('berkas', 'local');
            if (! is_string($path)) {
                throw new \RuntimeException('Penyimpanan bukti gagal.');
            }
            $attributes += ['nama_asli' => $file->getClientOriginalName(), 'path' => $path, 'mime' => $file->getMimeType(), 'ukuran_bytes' => $file->getSize()];
        } elseif ($data['mode'] === 'tautan') {
            $attributes['tautan'] = $data['tautan'];
        } else {
            $attributes['isi_teks'] = $data['isi_teks'];
        }
        BuktiDukung::create($attributes);
    }

    private function submissionPayload(PengukuranKinerja $p, array $prerequisite): array
    {
        $snapshot = $p->jadwalSnapshot;

        return [...$p->only(['indikator_id', 'tahun', 'periode_id', 'nilai', 'sumber_nilai', 'status_perhitungan', 'catatan', 'alasan_tidak_dapat_dihitung']),
            'indikator' => [...$snapshot->only(['nama', 'definisi', 'satuan', 'arah', 'tipe_perhitungan', 'presisi', 'desimal_tampilan']), 'id' => $p->indikator_id, 'kode' => $p->indikator->kode],
            'unit_kerja' => $snapshot->unit->only(['id', 'nama']), 'pic' => $p->effectivePic()?->pic?->only(['id', 'nama']),
            'periode' => $p->periode->only(['id', 'nama', 'urutan', 'is_nilai_akhir']), 'target' => $prerequisite['target'], 'target_pk' => $snapshot->target,
            'komponen' => $snapshot->komponen->map(fn ($c) => [...$c->only(['komponen_id', 'kode', 'label', 'peran', 'bobot']), 'nilai' => $p->komponen()->where('komponen_id', $c->komponen_id)->value('nilai')])->all(),
            'klaim' => $this->activityClaims($p, $prerequisite['rencana_aksi_versi']->rencana_aksi_id),
            'persyaratan_bukti' => $prerequisite['persyaratan_bukti'], 'bukti_dukungs' => $p->buktiDukungs()->current()->get()->map(fn ($b) => $b->only(['id', 'jenis_berkas_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'path', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks']))->all()];
    }

    /** Klaim dan narasi berasal dari kegiatan nyata pada periode ini, bukan salinan RA hidup. */
    private function activityClaims(PengukuranKinerja $p, string $planId): array
    {
        $claims = KlaimKegiatan::where('rencana_aksi_id', $planId)
            ->where(fn ($q) => $q->where('sumber_klaim', 'rencana_aksi')->orWhere('pengukuran_id', $p->id))->lockForUpdate()->get();
        $activities = Kegiatan::whereIn('id', $claims->pluck('kegiatan_id'))->lockForUpdate()->get()->keyBy('id');
        $evidence = BuktiDukung::where('berkasable_type', 'kegiatan')->whereIn('berkasable_id', $activities->where('periode_id', $p->periode_id)->keys())
            ->whereNull('dihapus_pada')->lockForUpdate()->get()->groupBy('berkasable_id');
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
                    'bukti_dukungs' => ($evidence->get($activity->id) ?? collect())->map(fn ($bukti) => $bukti->only(['id', 'jenis_berkas_id', 'mode', 'nama_asli', 'path', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks']))->all()]];
        }

        return $result;
    }

    private function auditState(PengukuranKinerja $p): array
    {
        return [...$p->only(['status_alur', 'versi', 'nilai', 'sumber_nilai', 'status_perhitungan', 'catatan', 'alasan_tidak_dapat_dihitung']),
            'versi_pengajuan' => $p->latestVersion?->only(['id', 'nomor', 'diajukan_by', 'jalur_pengajuan', 'dasar_izin_pengajuan', 'disahkan_by', 'disahkan_at']),
            'komponen' => $p->komponen()->orderBy('komponen_id')->get(['komponen_id', 'nilai'])->toArray(),
            'bukti_dukungs' => $p->buktiDukungs()->current()->orderBy('id')->get()->map(fn ($b) => [...$b->only(['id', 'jenis_berkas_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'mime', 'ukuran_bytes', 'tautan']), 'panjang_teks' => $b->isi_teks === null ? null : mb_strlen($b->isi_teks)])->all()];
    }

    private function writeAudit(User $actor, string $id, string $event, string $reason, ?array $decision, ?array $before, ?array $after): void
    {
        $this->audit->handle(['actor_type' => 'user', 'actor_id' => $actor->id, 'sumber' => 'manual', 'tindakan' => $event, 'objek_tipe' => 'pengukuran', 'objek_id' => $id,
            'alasan' => $reason, 'dasar_izin' => $decision, 'nilai_lama' => $before, 'nilai_baru' => $after]);
    }
}
