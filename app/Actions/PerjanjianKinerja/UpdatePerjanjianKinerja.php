<?php

namespace App\Actions\PerjanjianKinerja;

use App\Models\RenstraPk;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaAttachmentService;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaSupport;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
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

                if (array_key_exists('expected_updated_at', $data)) {
                    $expectedUpdatedAt = (string) $data['expected_updated_at'];
                    $currentTimestamp = $pkLocked->updated_at ?? $pkLocked->created_at;
                    try {
                        $expectedIso = Carbon::parse($expectedUpdatedAt)->toISOString();
                        $currentIso = $currentTimestamp !== null ? $currentTimestamp->toISOString() : null;
                        if ($currentIso === null || $currentIso !== $expectedIso) {
                            throw ValidationException::withMessages([
                                'konflik' => 'Data Perjanjian Kinerja telah diperbarui oleh pengguna lain. Silakan muat ulang halaman untuk melihat perubahan terkini.',
                            ]);
                        }
                    } catch (Throwable $e) {
                        if ($e instanceof ValidationException) {
                            throw $e;
                        }
                        throw ValidationException::withMessages([
                            'expected_updated_at' => 'Format timestamp versi tidak valid.',
                        ]);
                    }
                }

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
