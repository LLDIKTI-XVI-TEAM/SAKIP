<?php

namespace App\Actions\JenisBerkas;

use App\Models\JenisBerkas;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\AuditReason;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteJenisBerkasAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Data hasil validasi; key opsional tetap dibedakan dari nilai null.
     *
     * @param array{
     *     alasan: string,
     *     expected_updated_at: string,
     * } $data
     */
    public function handle(User $actor, string $id, array $data): void
    {
        $denied = DB::transaction(function () use (&$actor, $id, $data): ?array {
            // Selaras dengan writer akses: pengguna, role aktif, lalu permission berurutan UUID.
            $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
            $actor->lockActiveRoles();
            Permission::whereIn('kode', ['jenis_berkas:delete'])->orderBy('id')->sharedLock()->get();
            $decision = $this->resolver->decide($actor, 'jenis_berkas:delete');
            if (! $decision['allowed']) {
                return $decision;
            }

            $jb = JenisBerkas::where('id', $id)->lockForUpdate()->firstOrFail();

            $expectedUpdatedAt = (string) $data['expected_updated_at'];
            $currentTimestamp = $jb->updated_at ?? $jb->created_at;
            try {
                $expectedIso = Carbon::parse($expectedUpdatedAt)->toISOString();
                $currentIso = $currentTimestamp !== null ? $currentTimestamp->toISOString() : null;
                if ($currentIso === null || $currentIso !== $expectedIso) {
                    throw ValidationException::withMessages([
                        'konflik' => 'Data persyaratan telah diperbarui oleh pengguna lain. Silakan muat ulang halaman sebelum menghapus.',
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

            if (DB::table('berkas')->where('jenis_berkas_id', $jb->id)->exists()) {
                throw ValidationException::withMessages([
                    'alasan' => 'Persyaratan jenis berkas ini tidak dapat dihapus karena telah digunakan pada berkas bukti dukung. Anda dapat menonaktifkannya melalui opsi ubah.',
                ]);
            }

            $nilaiLama = $jb->toArray();
            $alasan = $data['alasan'];

            $jb->delete();

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'jenis_berkas.hapus',
                objekTipe: 'jenis_berkas',
                objekId: $id,
                nilaiLama: $nilaiLama,
                nilaiBaru: null,
                alasan: $alasan,
                dasarIzin: $decision
            );

            return null;
        });

        if ($denied !== null) {
            $alasan = AuditReason::sanitize($data['alasan']);
            $this->auditLogger->catat(
                actor: $actor, tindakan: 'jenis_berkas.hapus_ditolak', objekTipe: 'jenis_berkas', objekId: $id,
                alasan: trim($alasan) !== '' ? $alasan : 'Percobaan penghapusan persyaratan jenis berkas ditolak karena tidak memiliki izin.', dasarIzin: $denied,
            );
            abort(403);
        }
    }
}
