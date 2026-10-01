<?php

namespace App\Actions\Regulasi;

use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Regulasi\RegulasiAttachments;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
            return DB::transaction(function () use ($actor, $data, &$storedPaths): Regulasi {
                // Urutan selaras dengan writer akses, termasuk deny yang belum memiliki baris.
                $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
                $actor->lockActiveRoles();
                Permission::where('kode', PermissionCodes::REGULASI_CREATE)->orderBy('id')->sharedLock()->get();
                $decision = $this->resolver->resolve($actor, PermissionCodes::REGULASI_CREATE);
                if (! $decision->allowed) {
                    // Create belum memiliki kontrak event penolakan.
                    throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
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
    }
}
