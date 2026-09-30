<?php

namespace App\Actions\JenisBerkas;

use App\Models\JenisBerkas;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\JenisBerkas\JenisBerkasWarnings;
use App\Support\AuditReason;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateBatasTeknisJenisBerkasAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $auditLogger,
        private readonly JenisBerkasWarnings $warnings,
    ) {}

    /**
     * Data hasil validasi; key opsional tetap dibedakan dari nilai null.
     *
     * @param array{
     *     format_diizinkan?: string|null,
     *     ukuran_maks_kb?: int|numeric-string|null,
     *     alasan: string,
     *     expected_updated_at: string,
     * } $data
     */
    public function handle(User $actor, string $id, array $data): ?string
    {
        $formatWarning = null;
        $denied = DB::transaction(function () use (&$actor, $id, $data, &$formatWarning): ?array {
            // Selaras dengan writer akses: pengguna, role aktif, lalu permission berurutan UUID.
            $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
            $actor->lockActiveRoles();
            Permission::whereIn('kode', ['pengaturan:update'])->orderBy('id')->sharedLock()->get();
            $decision = $this->resolver->decide($actor, 'pengaturan:update');
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

            $nilaiLama = $jb->toArray();
            $alasan = $data['alasan'];
            unset($data['alasan'], $data['expected_updated_at']);

            if (array_key_exists('format_diizinkan', $data) && $data['format_diizinkan'] !== $nilaiLama['format_diizinkan']) {
                $formatWarning = $this->warnings->forFormatChange($jb, $data['format_diizinkan']);
            }

            $jb->fill($data);

            if (! $jb->isDirty()) {
                return null;
            }

            $jb->save();

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'jenis_berkas.batas_teknis_ubah',
                objekTipe: 'jenis_berkas',
                objekId: $jb->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $jb->fresh()->toArray(),
                alasan: $alasan,
                dasarIzin: $decision
            );

            return null;
        });

        if ($denied !== null) {
            $alasan = AuditReason::sanitize($data['alasan']);
            $this->auditLogger->catat(
                actor: $actor, tindakan: 'jenis_berkas.batas_teknis_ubah_ditolak', objekTipe: 'jenis_berkas', objekId: $id,
                alasan: trim($alasan) !== '' ? $alasan : 'Percobaan pembaruan batas teknis jenis berkas ditolak karena tidak memiliki izin pengaturan:update.', dasarIzin: $denied,
            );
            abort(403);
        }

        return $formatWarning;
    }
}
