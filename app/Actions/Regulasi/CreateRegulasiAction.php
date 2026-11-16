<?php

namespace App\Actions\Regulasi;

use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Regulasi\RegulasiAttachments;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateRegulasiAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
        private readonly RegulasiAttachments $attachments,
    ) {}

    /**
     * Pembuatan dan audit atomik; hanya file baru yang dikompensasi bila transaksi gagal.
     * Izin regulasi:create juga menjadi dasar unggahan lampiran pada alur ini.
     *
     * @param array{
     *     jenis: 'kepmen'|'permen'|'perpres'|'keputusan_lainnya', nomor: string,
     *     tahun: int|numeric-string, tentang: string, tanggal?: string|null,
     *     tautan_sumber?: string|null, catatan?: string|null, aktif: bool|0|1|'0'|'1',
     *     alasan?: string|null,
     *     lampiran?: array<array-key, array{mode: 'file'|'tautan'|'teks', file?: UploadedFile, tautan?: string, isi_teks?: string}>
     * } $data
     */
    public function handle(User $actor, array $data): Regulasi
    {
        $storedPaths = [];
        try {
            $result = DB::transaction(function () use ($actor, $data, &$storedPaths): Regulasi|PermissionDecision {
                // Urutan selaras dengan writer akses, termasuk deny yang belum memiliki baris.
                $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
                $actor->lockActiveRoles();
                Permission::where('kode', PermissionCodes::REGULASI_CREATE)->orderBy('id')->sharedLock()->get();
                $decision = $this->resolver->resolve($actor, PermissionCodes::REGULASI_CREATE);
                if (! $decision->allowed) {
                    $alasan = mb_substr(trim(AuditReason::sanitize($data['alasan'] ?? null)), 0, 1000);
                    $this->audit->catat(
                        actor: $actor, tindakan: 'regulasi.buat_ditolak', objekTipe: 'regulasi', objekId: (string) Str::uuid(),
                        alasan: $alasan !== '' ? $alasan : 'Pembuatan regulasi ditolak karena izin efektif tidak mengizinkan tindakan ini.',
                        dasarIzin: $decision->toAuditBasis(),
                    );

                    return $decision;
                }

                $regulasi = Regulasi::create([
                    ...Arr::only($data, ['jenis', 'nomor', 'tahun', 'tentang', 'tanggal', 'tautan_sumber', 'catatan', 'aktif']),
                    'created_by' => $actor->id,
                ]);
                $this->attachments->simpanLampiran($regulasi, $data['lampiran'] ?? [], $actor, $decision, $storedPaths);
                $regulasi->load('berkas');
                $this->audit->catat(
                    actor: $actor, tindakan: 'regulasi.buat', objekTipe: 'regulasi', objekId: $regulasi->id,
                    nilaiBaru: $this->attachments->snapshot($regulasi), dasarIzin: $decision->toAuditBasis(),
                );

                return $regulasi;
            });
        } catch (Throwable $exception) {
            $this->attachments->hapusFile($storedPaths, 'regulasi.kompensasi_unggahan');
            if ($exception instanceof QueryException
                && ($exception->errorInfo[0] ?? $exception->getCode()) === '23505'
                && str_contains($exception->getMessage(), 'regulasi_jenis_nomor_tahun_unique')) {
                throw ValidationException::withMessages(['nomor' => 'Kombinasi jenis, nomor, dan tahun regulasi sudah terdaftar.']);
            }

            throw $exception;
        }

        // Commit audit penolakan dahulu agar exception otorisasi tidak menggulung buktinya.
        if ($result instanceof PermissionDecision) {
            throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
        }

        return $result;
    }
}
