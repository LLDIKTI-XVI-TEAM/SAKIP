<?php

namespace App\Actions\RencanaAksi;

use App\Actions\Audit\WriteAuditLog;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Policies\RencanaAksiPolicy;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SahkanRencanaAksi
{
    public function __construct(private PermissionResolver $resolver, private WriteAuditLog $audit, private RencanaAksiPolicy $policy) {}

    /** Pengesahan diverifikasi→disahkan; versi beku dan audit diserialkan pada header yang sama. */
    public function handle(User $actor, string $id, array $data): RencanaAksi
    {
        $permission = 'rencana_aksi:sahkan';
        $decision = null;
        try {
            return DB::transaction(function () use ($actor, $id, $data, $permission, &$decision) {
                $actor = User::lockForUpdate()->findOrFail($actor->id);
                $ra = RencanaAksi::lockForUpdate()->findOrFail($id);
                $snapshot = JadwalSnapshot::lockForUpdate()->findOrFail($ra->jadwal_snapshot_id);
                $snapshot->setRelation('jadwal', JadwalTahunan::lockForUpdate()->findOrFail($snapshot->jadwal_id));
                $ra->setRelation('jadwalSnapshot', $snapshot);
                $decision = $this->resolver->decide($actor, $permission, $ra->targetUnitId());
                if (! $decision['allowed']) {
                    throw new AuthorizationException('Izin tindakan tidak tersedia atau telah dicabut.');
                }
                $errors = $this->policy->businessErrors($actor, $ra);
                if ($ra->versi !== (int) $data['versi']) {
                    $errors[] = 'Data telah berubah. Muat ulang sebelum mengulangi tindakan.';
                }
                if ($errors) {
                    throw ValidationException::withMessages(['versi' => $errors]);
                }
                $before = $this->auditState($ra);
                $reason = 'Mengesahkan rencana aksi.';
                $version = $ra->latestVersion;
                // F2: jalur Perencanaan boleh disahkan pengaju sendiri; tandai self_approval di audit.
                $selfApproval = $version->diajukan_by === $actor->id && $version->jalur_pengajuan === 'perencanaan';
                $version->update(['disahkan_by' => $actor->id, 'disahkan_at' => now()]);
                $ra->status_alur = 'disahkan';
                $ra->disahkan_by = $actor->id;
                $ra->disahkan_at = now();
                $ra->versi++;
                $ra->save();
                $ra->unsetRelation('latestVersion');
                $after = [...$this->auditState($ra), 'self_approval' => $selfApproval];
                $this->writeAudit($actor, $ra->id, 'rencana_aksi.sahkan', $reason, $decision, $before, $after);

                return $ra;
            });
        } catch (Throwable $exception) {
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException) && RencanaAksi::whereKey($id)->exists()) {
                $this->writeAudit($actor, $id, 'rencana_aksi.ditolak', 'Tindakan sahkan ditolak.', $decision, null,
                    ['tindakan_diminta' => 'sahkan', 'jenis_penolakan' => $exception instanceof AuthorizationException ? 'otorisasi' : 'validasi_bisnis',
                        'alasan_penolakan' => $exception instanceof ValidationException ? $exception->errors() : $exception->getMessage()]);
            }
            throw $exception;
        }
    }

    private function auditState(RencanaAksi $ra): array
    {
        return [...$ra->only(['status_alur', 'versi']),
            'versi_pengajuan' => $ra->latestVersion?->only(['id', 'nomor', 'diajukan_by', 'jalur_pengajuan', 'dasar_izin_pengajuan', 'disahkan_by', 'disahkan_at'])];
    }

    private function writeAudit(User $actor, string $id, string $event, string $reason, ?array $decision, ?array $before, ?array $after): void
    {
        $this->audit->handle(['actor_type' => 'user', 'actor_id' => $actor->id, 'sumber' => 'manual', 'tindakan' => $event, 'objek_tipe' => 'rencana_aksi', 'objek_id' => $id,
            'alasan' => $reason, 'dasar_izin' => $decision, 'nilai_lama' => $before, 'nilai_baru' => $after]);
    }
}
