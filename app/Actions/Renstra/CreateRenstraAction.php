<?php

namespace App\Actions\Renstra;

use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Renstra\RenstraAttachments;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateRenstraAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
        private readonly RenstraAttachments $attachments,
    ) {}

    /**
     * Izin dan audit diputuskan dalam transaksi yang sama dengan pembuatan Renstra draft.
     *
     * @param array{nama: string, tahun_mulai: int|numeric-string, kode?: string|null,
     *     tahun_selesai?: int|numeric-string, tahun_akhir?: int|numeric-string,
     *     deskripsi?: string|null, keterangan?: string|null, dasar_hukum?: string|null,
     *     regulasi_id?: string|null, alasan?: string|null,
     *     lampiran?: array<array-key, array{mode: 'file'|'tautan'|'teks', file?: UploadedFile, tautan?: string, isi_teks?: string}>
     * } $data
     */
    public function handle(User $actor, array $data): Renstra
    {
        $storedPaths = [];
        try {
            $result = DB::transaction(function () use ($actor, $data, &$storedPaths): Renstra|PermissionDecision {
                $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
                $actor->lockActiveRoles();
                $permissions = [PermissionCodes::RENSTRA_CREATE];
                if (! empty($data['lampiran'])) {
                    $permissions[] = PermissionCodes::BERKAS_UPLOAD;
                }
                if (array_key_exists('regulasi_id', $data)) {
                    $permissions[] = PermissionCodes::REGULASI_READ;
                }
                Permission::whereIn('kode', $permissions)->orderBy('id')->sharedLock()->get();
                $decision = $this->resolver->resolve($actor, PermissionCodes::RENSTRA_CREATE);
                $uploadDecision = null;
                $denied = $decision->allowed ? null : $decision;
                $denialReason = null;
                if ($denied === null && ! empty($data['lampiran'])) {
                    $uploadDecision = $this->resolver->resolve($actor, PermissionCodes::BERKAS_UPLOAD);
                    if (! $uploadDecision->allowed) {
                        $denied = $uploadDecision;
                        $denialReason = 'berkas_upload_denied';
                    }
                }
                if ($denied === null && array_key_exists('regulasi_id', $data)) {
                    $referenceDecision = $this->resolver->resolve($actor, PermissionCodes::REGULASI_READ);
                    if (! $referenceDecision->allowed) {
                        $denied = $referenceDecision;
                        $denialReason = 'regulasi_read_denied';
                    }
                }
                if ($denied !== null) {
                    $this->audit->catat(
                        actor: $actor, tindakan: 'renstra.buat_ditolak', objekTipe: 'renstra', objekId: (string) Str::uuid(),
                        nilaiBaru: $denialReason === null ? [
                            'nama' => $data['nama'], 'tahun_mulai' => $data['tahun_mulai'],
                            'tahun_selesai' => $data['tahun_selesai'] ?? $data['tahun_akhir'] ?? null,
                        ] : ['alasan_penolakan' => $denialReason],
                        alasan: AuditReason::sanitize($data['alasan'] ?? null), dasarIzin: $denied->toAuditBasis(),
                    );

                    return $denied;
                }

                $start = (int) $data['tahun_mulai'];
                $end = (int) ($data['tahun_selesai'] ?? $data['tahun_akhir'] ?? $start);
                if ($end < $start) {
                    throw ValidationException::withMessages([
                        'tahun_akhir' => 'Rentang tahun tidak valid: tahun selesai/akhir harus lebih besar atau sama dengan tahun mulai.',
                        'tahun_selesai' => 'Rentang tahun tidak valid: tahun selesai/akhir harus lebih besar atau sama dengan tahun mulai.',
                    ]);
                }
                // Kunci Regulasi sebelum membuat Renstra agar keberadaan dan status rujukan tetap sah hingga commit.
                if (isset($data['regulasi_id'])) {
                    $regulasi = Regulasi::whereKey($data['regulasi_id'])->sharedLock()->first();
                    if ($regulasi === null || ! $regulasi->aktif) {
                        throw ValidationException::withMessages(['regulasi_id' => 'Dasar aturan regulasi yang dipilih tidak ditemukan.']);
                    }
                }
                $renstra = Renstra::create([
                    'kode' => $data['kode'] ?? 'RENSTRA-'.$start.'-'.$end,
                    'nama' => $data['nama'], 'tahun_mulai' => $start, 'tahun_selesai' => $end,
                    'deskripsi' => $data['deskripsi'] ?? $data['keterangan'] ?? null,
                    'dasar_hukum' => $data['dasar_hukum'] ?? null,
                    'regulasi_id' => $data['regulasi_id'] ?? null,
                    'status' => Renstra::STATUS_DRAFT, 'is_aktif' => false, 'created_by' => $actor->id,
                ]);
                if ($uploadDecision !== null) {
                    $this->attachments->simpanLampiran($renstra, $data['lampiran'], $actor, $uploadDecision, $storedPaths);
                }
                $renstra->load(['berkas', 'regulasi']);
                $this->audit->catat(
                    actor: $actor, tindakan: 'renstra.buat', objekTipe: 'renstra', objekId: $renstra->id,
                    nilaiBaru: $this->attachments->snapshot($renstra), dasarIzin: $decision->toAuditBasis(),
                );

                return $renstra;
            });
        } catch (Throwable $exception) {
            $this->attachments->hapusFile($storedPaths, 'renstra.kompensasi_unggahan');
            if ($exception instanceof QueryException && (str_contains($exception->getMessage(), 'renstras_kode_unique') || str_contains($exception->getMessage(), '23505'))) {
                throw ValidationException::withMessages(['kode' => 'Kode Renstra sudah terdaftar pada sistem.']);
            }
            throw $exception;
        }

        // Penolakan dikembalikan dahulu agar audit tidak ikut dibatalkan oleh exception otorisasi.
        if ($result instanceof PermissionDecision) {
            throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
        }

        return $result;
    }
}
