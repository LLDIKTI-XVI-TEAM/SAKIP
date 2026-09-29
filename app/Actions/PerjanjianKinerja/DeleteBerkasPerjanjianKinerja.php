<?php

namespace App\Actions\PerjanjianKinerja;

use App\Exceptions\ReauthorizationDenialException;
use App\Jobs\CleanupStorageFileJob;
use App\Models\Berkas;
use App\Models\JadwalTahunan;
use App\Models\RenstraPk;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaAttachmentService;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaSupport;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class DeleteBerkasPerjanjianKinerja
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected PermissionResolver $permissionResolver,
        protected PerjanjianKinerjaAttachmentService $attachmentService,
    ) {}

    /**
     * Menjalankan use-case penghapusan lampiran Perjanjian Kinerja dengan guard imutabilitas jadwal aktif.
     */
    public function handle(RenstraPk $pk, Berkas $berkas, string $alasan, User $actor): void
    {
        if ($berkas->berkasable_id !== $pk->id || ! in_array($berkas->berkasable_type, ['renstra_pk', RenstraPk::class], true)) {
            throw new RuntimeException('Berkas bukan merupakan lampiran dari Perjanjian Kinerja ini.');
        }

        $alasan = PerjanjianKinerjaSupport::sanitizeAlasan($alasan);
        if ($alasan === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan penghapusan lampiran wajib diisi.',
            ]);
        }

        $pkUpdateDecision = $this->permissionResolver->resolve($actor, PermissionCodes::PK_UPDATE);
        $berkasDeleteDecision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_DELETE);

        if (! $pkUpdateDecision->allowed || ! $berkasDeleteDecision->allowed) {
            $deniedDecision = ! $pkUpdateDecision->allowed ? $pkUpdateDecision : $berkasDeleteDecision;
            $denialReason = ! $pkUpdateDecision->allowed ? 'pk_update_denied' : 'berkas_delete_denied';

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'berkas.hapus_ditolak',
                objekTipe: 'berkas',
                objekId: $berkas->id,
                nilaiLama: $this->attachmentService->metadataBerkasUntukAudit($berkas),
                nilaiBaru: ['alasan_penolakan' => $denialReason],
                alasan: $alasan,
                dasarIzin: $deniedDecision->toAuditBasis(),
            );

            throw new AuthorizationException('Pengguna tidak memiliki izin untuk menghapus lampiran Perjanjian Kinerja.');
        }

        $path = null;

        try {
            $penolakan = DB::transaction(function () use ($pk, $berkas, $actor, $alasan, &$path): ?string {
                $lockedActor = PerjanjianKinerjaSupport::lockActorAndPermissions($actor, [
                    PermissionCodes::PK_UPDATE,
                    PermissionCodes::BERKAS_DELETE,
                ]);

                $pkUpdateDecision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::PK_UPDATE);
                $berkasDeleteDecision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::BERKAS_DELETE);

                if (! $pkUpdateDecision->allowed) {
                    throw new ReauthorizationDenialException(
                        $pkUpdateDecision,
                        'pk_update_denied',
                        'Pengguna tidak memiliki izin untuk memperbarui Perjanjian Kinerja.'
                    );
                }

                if (! $berkasDeleteDecision->allowed) {
                    throw new ReauthorizationDenialException(
                        $berkasDeleteDecision,
                        'berkas_delete_denied',
                        'Pengguna tidak memiliki izin untuk menghapus lampiran berkas.'
                    );
                }

                /** @var RenstraPk $pkLocked */
                $pkLocked = RenstraPk::where('id', $pk->id)->lockForUpdate()->firstOrFail();

                // Kunci baris jadwal_tahunan terkait untuk mencegah race condition / TOCTOU aktivasi jadwal
                $jadwalTerkait = JadwalTahunan::where(function ($q) use ($pkLocked) {
                    $q->where('renstra_pk_id', $pkLocked->id)
                        ->orWhere(fn ($sub) => $sub->where('renstra_id', $pkLocked->renstra_id)->where('tahun', $pkLocked->tahun));
                })->lockForUpdate()->get();

                $isJadwalMengunci = $jadwalTerkait->contains(function ($j) {
                    return $j->status === 'aktif'
                        || $j->status === 'ditutup'
                        || ! is_null($j->activated_at);
                });

                if ($isJadwalMengunci) {
                    $this->auditLogger->catat(
                        actor: $lockedActor,
                        tindakan: 'berkas.hapus_ditolak',
                        objekTipe: 'berkas',
                        objekId: $berkas->id,
                        nilaiLama: $this->attachmentService->metadataBerkasUntukAudit($berkas),
                        nilaiBaru: ['alasan_penolakan' => 'jadwal_tahunan_aktif'],
                        alasan: $alasan,
                        dasarIzin: $berkasDeleteDecision->toAuditBasis(),
                    );

                    return 'jadwal_tahunan_aktif';
                }

                /** @var Berkas|null $berkasLocked */
                $berkasLocked = Berkas::where('id', $berkas->id)
                    ->where('berkasable_id', $pkLocked->id)
                    ->whereIn('berkasable_type', ['renstra_pk', RenstraPk::class])
                    ->whereNull('dihapus_pada')
                    ->lockForUpdate()
                    ->first();

                if (! $berkasLocked) {
                    throw ValidationException::withMessages([
                        'berkas' => 'Lampiran berkas sudah dihapus atau tidak ditemukan.',
                    ]);
                }

                $path = $berkasLocked->mode === 'file' && is_string($berkasLocked->path) ? $berkasLocked->path : null;
                $nilaiLama = $this->attachmentService->metadataBerkasUntukAudit($berkasLocked);

                $berkasLocked->dihapus_oleh = $lockedActor->id;
                $berkasLocked->save();
                $berkasLocked->delete();

                $this->auditLogger->catat(
                    actor: $lockedActor,
                    tindakan: 'berkas.hapus',
                    objekTipe: 'berkas',
                    objekId: $berkasLocked->id,
                    nilaiLama: $nilaiLama,
                    alasan: $alasan,
                    dasarIzin: $berkasDeleteDecision->toAuditBasis(),
                );

                return null;
            });
        } catch (ReauthorizationDenialException $exception) {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'berkas.hapus_ditolak',
                objekTipe: 'berkas',
                objekId: $berkas->id,
                nilaiLama: $this->attachmentService->metadataBerkasUntukAudit($berkas),
                nilaiBaru: ['alasan_penolakan' => $exception->denialReason],
                alasan: $alasan,
                dasarIzin: $exception->decision->toAuditBasis(),
            );

            throw new AuthorizationException($exception->getMessage());
        }

        if ($penolakan === 'jadwal_tahunan_aktif') {
            throw ValidationException::withMessages([
                'berkas' => 'Lampiran Perjanjian Kinerja tidak dapat dihapus karena Jadwal Tahunan sudah aktif.',
            ]);
        }

        if ($path !== null) {
            DB::afterCommit(function () use ($path) {
                try {
                    $deleted = Storage::disk('local')->delete($path);
                    if (! $deleted && Storage::disk('local')->exists($path)) {
                        Log::warning('Storage::delete() mengembalikan false untuk lampiran PK, menjadwalkan CleanupStorageFileJob.', ['path' => $path]);
                        CleanupStorageFileJob::dispatch($path, 'local');
                    }
                } catch (Throwable $e) {
                    Log::warning('Gagal menghapus file lampiran PK dari storage setelah commit: '.$e->getMessage(), ['path' => $path]);
                    CleanupStorageFileJob::dispatch($path, 'local');
                }
            });
        }
    }
}
