<?php

namespace App\Http\Requests\Renstra;

use App\Models\Renstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\Gate;

class UpdateRenstraRequest extends RenstraMutationRequest
{
    public function authorize(): bool
    {
        $renstra = $this->route('renstra');
        $user = $this->user();

        return $renstra instanceof Renstra
            && $user instanceof User
            && Gate::allows('update', $renstra)
            && $this->relatedPermissionsAllowed($user, $renstra);
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $renstra = $this->route('renstra');

        if ($user instanceof User && $renstra instanceof Renstra) {
            $decision = $this->deniedRelatedDecision
                ?? app(PermissionResolver::class)->resolve($user, PermissionCodes::RENSTRA_UPDATE);
            $rawAlasan = $this->input('alasan');
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== '' ? trim($rawAlasan) : null;

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
