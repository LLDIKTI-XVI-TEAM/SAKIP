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
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class UpdateRegulasiAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
        private readonly RegulasiAttachments $attachments,
    ) {}

    /**
     * Versi diperiksa pada induk terkunci; no-op tetap merupakan revisi yang diaudit.
     *
     * @param array{
     *     jenis: 'kepmen'|'permen'|'perpres'|'keputusan_lainnya', nomor: string,
     *     tahun: int|numeric-string, tentang: string, tanggal?: string|null,
     *     tautan_sumber?: string|null, catatan?: string|null, aktif: bool|0|1|'0'|'1',
     *     alasan: string, versi: int|numeric-string,
     *     lampiran?: array<array-key, array{mode: 'file'|'tautan'|'teks', file?: UploadedFile, tautan?: string, isi_teks?: string}>
     * } $data
     */
    public function handle(User $actor, Regulasi $regulasi, array $data): Regulasi
    {
        $storedPaths = [];
        try {
            $result = DB::transaction(function () use ($actor, $regulasi, $data, &$storedPaths): Regulasi|PermissionDecision|false {
                $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
                $actor->lockActiveRoles();
                Permission::where('kode', PermissionCodes::REGULASI_UPDATE)->orderBy('id')->sharedLock()->get();
                $decision = $this->resolver->resolve($actor, PermissionCodes::REGULASI_UPDATE);
                $alasanPenolakan = AuditReason::sanitize($data['alasan']);
                if (! $decision->allowed) {
                    $regulasi->load('berkas');
                    $this->audit->catat(
                        actor: $actor, tindakan: 'regulasi.ubah_ditolak', objekTipe: 'regulasi', objekId: $regulasi->id,
                        nilaiLama: $this->attachments->snapshot($regulasi),
                        alasan: trim($alasanPenolakan) !== '' ? $alasanPenolakan : 'Pembaruan regulasi ditolak karena izin tidak efektif.',
                        dasarIzin: $decision->toAuditBasis(),
                    );

                    return $decision;
                }

                $current = Regulasi::with('berkas')->lockForUpdate()->findOrFail($regulasi->id);
                $nilaiLama = $this->attachments->snapshot($current);
                if ($current->versi !== (int) $data['versi']) {
                    // Simpan snapshot yang sama dengan keputusan konflik sebelum lock dilepas.
                    $this->audit->catat(
                        actor: $actor, tindakan: 'regulasi.ubah_ditolak', objekTipe: 'regulasi', objekId: $current->id,
                        nilaiLama: $nilaiLama,
                        nilaiBaru: ['alasan_penolakan' => 'versi_usang', 'versi_dikirim' => (int) $data['versi'], 'versi_saat_ini' => $current->versi],
                        alasan: trim($alasanPenolakan) !== '' ? $alasanPenolakan : 'Pembaruan regulasi ditolak karena versi usang.',
                        dasarIzin: $decision->toAuditBasis(),
                    );

                    return false;
                }

                $current->update([
                    ...Arr::only($data, ['jenis', 'nomor', 'tahun', 'tentang', 'tanggal', 'tautan_sumber', 'catatan', 'aktif']),
                    'versi' => $current->versi + 1,
                ]);
                $this->attachments->simpanLampiran($current, $data['lampiran'] ?? [], $actor, $decision, $storedPaths);
                $current->refresh()->load('berkas');
                $this->audit->catat(
                    actor: $actor, tindakan: 'regulasi.ubah', objekTipe: 'regulasi', objekId: $current->id,
                    nilaiLama: $nilaiLama, nilaiBaru: $this->attachments->snapshot($current),
                    alasan: $data['alasan'], dasarIzin: $decision->toAuditBasis(),
                );

                return $current;
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

        // Exception respons berada sesudah commit agar audit penolakan tidak ikut rollback.
        if ($result instanceof PermissionDecision) {
            throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
        }
        if ($result === false) {
            throw new ConflictHttpException('Dasar aturan telah diubah oleh pengguna lain. Muat ulang data terbaru sebelum menyimpan perubahan.');
        }

        return $result;
    }
}
