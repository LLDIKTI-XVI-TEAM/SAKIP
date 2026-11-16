<?php

namespace App\Http\Requests\Renstra;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Support\Str;

class StoreRenstraRequest extends RenstraMutationRequest
{
    private ?PermissionDecision $initialDecision = null;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }
        $this->initialDecision = app(PermissionResolver::class)->resolve($user, PermissionCodes::RENSTRA_CREATE);

        return $this->initialDecision->allowed && $this->relatedPermissionsAllowed($user);
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();

        if ($user instanceof User && $this->initialDecision !== null) {
            $decision = $this->deniedRelatedDecision
                ?? $this->initialDecision;
            $alasan = mb_substr(trim(AuditReason::sanitize($this->input('alasan'))), 0, 1000);

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'renstra.buat_ditolak',
                objekTipe: 'renstra',
                objekId: (string) Str::uuid(),
                nilaiBaru: [
                    'nama' => is_string($this->input('nama')) ? mb_substr(AuditReason::sanitize($this->input('nama')), 0, 255) : null,
                    'tahun_mulai' => $this->auditYear($this->input('tahun_mulai')),
                    'tahun_selesai' => $this->auditYear($this->input('tahun_selesai') ?? $this->input('tahun_akhir')),
                    ...($this->deniedRelatedReason === null ? [] : ['alasan_penolakan' => $this->deniedRelatedReason]),
                ],
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }

    /** Metadata sebelum validasi hanya menyimpan bentuk tahun sah, tanpa mengubah tipe input sah. */
    private function auditYear(mixed $value): int|float|string|null
    {
        if ((is_int($value) || is_float($value) || (is_string($value) && strlen($value) <= 32))
            && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]) !== false) {
            return $value;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->mutationRules(requireReason: false);
    }
}
