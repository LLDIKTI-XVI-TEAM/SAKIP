<?php

namespace App\Actions\PerjanjianKinerja;

use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaAttachmentService;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaSupport;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreatePerjanjianKinerja
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected PermissionResolver $permissionResolver,
        protected PerjanjianKinerjaAttachmentService $attachmentService,
    ) {}

    /**
     * Menjalankan use-case pembuatan data Perjanjian Kinerja beserta lampiran opsional.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): RenstraPk
    {
        $createDecision = $this->permissionResolver->resolve($actor, PermissionCodes::PK_CREATE);
        if (! $createDecision->allowed) {
            throw new AuthorizationException('Pengguna tidak memiliki izin untuk mencatat Perjanjian Kinerja.');
        }

        if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
            $uploadDecision = $this->permissionResolver->resolve($actor, PermissionCodes::BERKAS_UPLOAD);
            if (! $uploadDecision->allowed) {
                throw new AuthorizationException('Pengguna tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.');
            }
        }

        $renstra = Renstra::findOrFail($data['renstra_id']);
        $tahun = (int) $data['tahun'];

        if ($tahun < $renstra->tahun_mulai || $tahun > $renstra->tahun_selesai) {
            throw ValidationException::withMessages([
                'tahun' => "Tahun Perjanjian Kinerja ({$tahun}) harus berada dalam rentang tahun Renstra ({$renstra->tahun_mulai} - {$renstra->tahun_selesai}).",
            ]);
        }

        if (RenstraPk::where('renstra_id', $renstra->id)->where('tahun', $tahun)->exists()) {
            throw ValidationException::withMessages([
                'tahun' => 'Perjanjian Kinerja untuk Renstra dan tahun tersebut sudah terdaftar.',
            ]);
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($data, $tahun, $actor, &$storedPaths) {
                $requiredCodes = [PermissionCodes::PK_CREATE];
                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $requiredCodes[] = PermissionCodes::BERKAS_UPLOAD;
                }

                $lockedActor = PerjanjianKinerjaSupport::lockActorAndPermissions($actor, $requiredCodes);

                $createDecision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::PK_CREATE);
                if (! $createDecision->allowed) {
                    throw new AuthorizationException('Pengguna tidak memiliki izin untuk mencatat Perjanjian Kinerja.');
                }

                $uploadDecision = null;
                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $uploadDecision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::BERKAS_UPLOAD);
                    if (! $uploadDecision->allowed) {
                        throw new AuthorizationException('Pengguna tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.');
                    }
                }

                // Kunci Renstra dengan sharedLock untuk mencegah race condition perubahan rentang tahun Renstra
                $renstraLocked = Renstra::query()->whereKey($data['renstra_id'])->sharedLock()->firstOrFail();
                if ($tahun < $renstraLocked->tahun_mulai || $tahun > $renstraLocked->tahun_selesai) {
                    throw ValidationException::withMessages([
                        'tahun' => "Tahun Perjanjian Kinerja ({$tahun}) harus berada dalam rentang tahun Renstra ({$renstraLocked->tahun_mulai} - {$renstraLocked->tahun_selesai}).",
                    ]);
                }

                if (RenstraPk::where('renstra_id', $renstraLocked->id)->where('tahun', $tahun)->exists()) {
                    throw ValidationException::withMessages([
                        'tahun' => 'Perjanjian Kinerja untuk Renstra dan tahun tersebut sudah terdaftar.',
                    ]);
                }

                $pk = RenstraPk::create([
                    'renstra_id' => $renstraLocked->id,
                    'tahun' => $tahun,
                    'nomor_pk' => $data['nomor_pk'],
                    'tanggal_pk' => $data['tanggal_pk'],
                    'created_by' => $lockedActor->id,
                ]);

                if (! empty($data['lampiran']) && is_array($data['lampiran'])) {
                    $this->attachmentService->simpanLampiran($pk, $data['lampiran'], $lockedActor, $storedPaths, $uploadDecision);
                }

                $pk->load(['renstra', 'creator', 'berkas']);

                $this->auditLogger->catat(
                    actor: $lockedActor,
                    tindakan: 'renstra_pk.buat',
                    objekTipe: 'renstra_pk',
                    objekId: $pk->id,
                    nilaiBaru: $this->attachmentService->snapshot($pk),
                    dasarIzin: $createDecision->toAuditBasis(),
                );

                return $pk;
            });
        } catch (QueryException $exception) {
            $this->attachmentService->hapusFile($storedPaths);

            if ($this->adalahDuplikasiPk($exception)) {
                throw ValidationException::withMessages([
                    'tahun' => 'Perjanjian Kinerja untuk Renstra dan tahun tersebut sudah terdaftar.',
                ]);
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->attachmentService->hapusFile($storedPaths);

            throw $exception;
        }
    }

    /**
     * Memeriksa apakah QueryException disebabkan oleh pelanggaran unique constraint pada renstra_pk.
     */
    private function adalahDuplikasiPk(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return $sqlState === '23505'
            && (str_contains($exception->getMessage(), 'renstra_pk_renstra_id_tahun_unique') || str_contains($exception->getMessage(), 'renstra_pk'));
    }
}
