<?php

namespace App\Actions\PerjanjianKinerja;

use App\Models\RenstraPk;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaAttachmentService;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaSupport;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdatePerjanjianKinerja
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected PermissionResolver $permissionResolver,
        protected PerjanjianKinerjaAttachmentService $attachmentService,
    ) {}

    /**
     * Menjalankan use-case pembaruan metadata dan lampiran Perjanjian Kinerja secara teraudit.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(RenstraPk $pk, array $data, string $alasan, User $actor): RenstraPk
    {
        $alasan = PerjanjianKinerjaSupport::sanitizeAlasan($alasan);
        if ($alasan === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan perubahan Perjanjian Kinerja wajib diisi.',
            ]);
        }

        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::PK_UPDATE);
        if (! $decision->allowed) {
            throw new AuthorizationException('Pengguna tidak memiliki izin untuk memperbarui Perjanjian Kinerja.');
        }

        if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
            $uploadDecision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_UPLOAD);
            if (! $uploadDecision->allowed) {
                throw new AuthorizationException('Pengguna tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.');
            }
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($pk, $data, $alasan, $actor, &$storedPaths) {
                $requiredCodes = [PermissionCodes::PK_UPDATE];
                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $requiredCodes[] = PermissionCodes::BERKAS_UPLOAD;
                }

                $lockedActor = PerjanjianKinerjaSupport::lockActorAndPermissions($actor, $requiredCodes);

                $decision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::PK_UPDATE);
                if (! $decision->allowed) {
                    throw new AuthorizationException('Pengguna tidak memiliki izin untuk memperbarui Perjanjian Kinerja.');
                }

                $uploadDecision = null;
                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $uploadDecision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::BERKAS_UPLOAD);
                    if (! $uploadDecision->allowed) {
                        throw new AuthorizationException('Pengguna tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.');
                    }
                }

                /** @var RenstraPk $pkLocked */
                $pkLocked = RenstraPk::where('id', $pk->id)->lockForUpdate()->firstOrFail();
                $pkLocked->load(['berkas']);
                $nilaiLama = $this->attachmentService->snapshot($pkLocked);

                if (array_key_exists('nomor_pk', $data)) {
                    $pkLocked->nomor_pk = $data['nomor_pk'];
                }
                if (array_key_exists('tanggal_pk', $data)) {
                    $pkLocked->tanggal_pk = $data['tanggal_pk'];
                }
                $pkLocked->save();

                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $this->attachmentService->simpanLampiran($pkLocked, $data['lampiran'], $lockedActor, $storedPaths, $uploadDecision, $decision);
                }

                $pkLocked = $pkLocked->fresh(['renstra', 'creator', 'berkas']);
                $nilaiBaru = $this->attachmentService->snapshot($pkLocked);

                $this->auditLogger->catat(
                    actor: $lockedActor,
                    tindakan: 'renstra_pk.ubah',
                    objekTipe: 'renstra_pk',
                    objekId: $pkLocked->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: $nilaiBaru,
                    alasan: $alasan,
                    dasarIzin: $decision->toAuditBasis(),
                );

                return $pkLocked;
            });
        } catch (Throwable $exception) {
            $this->attachmentService->hapusFile($storedPaths);

            throw $exception;
        }
    }
}
