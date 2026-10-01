<?php

namespace App\Http\Requests\Renstra;

use App\Models\Renstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;

class UpdateRenstraRequest extends RenstraMutationRequest
{
    private ?PermissionDecision $initialDecision = null;

    public function authorize(): bool
    {
        $renstra = $this->route('renstra');
        $user = $this->user();

        if (! $renstra instanceof Renstra || ! $user instanceof User) {
            return false;
        }
        $this->initialDecision = app(PermissionResolver::class)->resolve($user, PermissionCodes::RENSTRA_UPDATE);

        return $this->initialDecision->allowed && $this->relatedPermissionsAllowed($user);
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $renstra = $this->route('renstra');

        if ($user instanceof User && $renstra instanceof Renstra && $this->initialDecision !== null) {
            $decision = $this->deniedRelatedDecision
                ?? $this->initialDecision;
            $alasan = mb_substr(trim(AuditReason::sanitize($this->input('alasan'))), 0, 1000);

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'renstra.ubah_ditolak',
                objekTipe: 'renstra',
                objekId: $renstra->id,
                nilaiLama: $renstra->withoutRelations()->toArray(),
                nilaiBaru: $this->deniedRelatedReason === null ? null : ['alasan_penolakan' => $this->deniedRelatedReason],
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $renstra = $this->route('renstra');
        $isAktif = $renstra instanceof Renstra && ($renstra->status === Renstra::STATUS_AKTIF || $renstra->is_aktif);

        return $this->mutationRules(requireReason: $isAktif);
    }
}
