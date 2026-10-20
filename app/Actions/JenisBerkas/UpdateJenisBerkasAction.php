<?php

namespace App\Actions\JenisBerkas;

use App\Models\JenisBerkas;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Storage\UploadLimits;
use App\Support\AuditReason;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateJenisBerkasAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Data hasil validasi; key opsional tetap dibedakan dari nilai null.
     *
     * @param array{
     *     nama: string,
     *     tahap: 'rencana_aksi'|'pengukuran'|'kegiatan_perencanaan',
     *     indikator_id?: string|null,
     *     wajib?: bool|0|1|'0'|'1',
     *     keterangan?: string|null,
     *     izinkan_file?: bool|0|1|'0'|'1',
     *     izinkan_tautan?: bool|0|1|'0'|'1',
     *     izinkan_teks?: bool|0|1|'0'|'1',
     *     semua_mode_wajib?: bool|0|1|'0'|'1',
     *     urutan?: int|numeric-string,
     *     format_diizinkan?: string|null,
     *     ukuran_maks_kb?: int|numeric-string|null,
     *     aktif?: bool|0|1|'0'|'1',
     *     alasan?: string|null,
     *     expected_updated_at: string,
     * } $data
     */
    public function handle(User $actor, string $id, array $data): void
    {
        $denied = DB::transaction(function () use (&$actor, $id, $data): ?array {
            $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
            $actor->lockActiveRoles();
            Permission::where('kode', 'jenis_berkas:update')->sharedLock()->get();
            $decision = $this->resolver->decide($actor, 'jenis_berkas:update');
            if (! $decision['allowed']) {
                return $decision;
            }
            $jb = JenisBerkas::whereKey($id)->lockForUpdate()->firstOrFail();
            $expectedUpdatedAt = (string) $data['expected_updated_at'];
            $currentTimestamp = $jb->updated_at ?? $jb->created_at;
            try {
                $expectedIso = Carbon::parse($expectedUpdatedAt)->toISOString();
                $currentIso = $currentTimestamp !== null ? $currentTimestamp->toISOString() : null;
                if ($currentIso === null || $currentIso !== $expectedIso) {
                    throw ValidationException::withMessages([
                        'konflik' => 'Data persyaratan telah diperbarui oleh pengguna lain. Silakan muat ulang halaman untuk melihat perubahan terkini.',
                    ]);
                }
            } catch (\Exception $e) {
                if ($e instanceof ValidationException) {
                    throw $e;
                }
                throw ValidationException::withMessages([
                    'expected_updated_at' => 'Format timestamp versi tidak valid.',
                ]);
            }

            Validator::make($data, ['ukuran_maks_kb' => ['nullable', 'integer', 'min:100', 'max:'.UploadLimits::maxKb()]])->validate();
            $nilaiLama = $jb->toArray();
            $alasan = AuditReason::sanitize($data['alasan'] ?? null);
            if (trim($alasan) === '') {
                $alasan = 'Perubahan persyaratan berkas tanpa alasan tambahan.';
            }
            unset($data['alasan'], $data['expected_updated_at']);
            $jb->fill($data);
            if (! $jb->isDirty()) {
                return null;
            }
            $jb->save();
            $this->auditLogger->catat(
                actor: $actor, tindakan: 'jenis_berkas.ubah', objekTipe: 'jenis_berkas', objekId: $jb->id,
                nilaiLama: $nilaiLama, nilaiBaru: $jb->fresh()->toArray(), alasan: $alasan, dasarIzin: $decision,
            );

            return null;
        });

        if ($denied !== null) {
            $alasan = AuditReason::sanitize($data['alasan'] ?? null);
            $this->auditLogger->catat(
                actor: $actor, tindakan: 'jenis_berkas.ubah_ditolak', objekTipe: 'jenis_berkas', objekId: $id,
                alasan: trim($alasan) !== '' ? $alasan : 'Percobaan pembaruan persyaratan jenis berkas ditolak karena tidak memiliki izin.', dasarIzin: $denied,
            );
            abort(403);
        }
    }
}
