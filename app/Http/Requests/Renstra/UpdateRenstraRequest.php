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

        return $renstra instanceof Renstra
            ? Gate::allows('update', $renstra)
            : false;
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $renstra = $this->route('renstra');

        if ($user instanceof User && $renstra instanceof Renstra) {
            $decision = app(PermissionResolver::class)->resolve($user, PermissionCodes::RENSTRA_UPDATE);
            $rawAlasan = $this->input('alasan');
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== '' ? trim($rawAlasan) : null;

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'renstra.ubah_ditolak',
                objekTipe: 'renstra',
                objekId: $renstra->id,
                nilaiLama: $renstra->withoutRelations()->toArray(),
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
