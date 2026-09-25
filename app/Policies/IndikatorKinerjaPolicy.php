<?php

namespace App\Policies;

use App\Models\IndikatorKinerja;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\Response;

class IndikatorKinerjaPolicy
{
    public function __construct(
        private readonly PermissionResolver $permissionResolver,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function viewAny(User $user): Response
    {
        return $this->response($this->permissionResolver->resolve($user, PermissionCodes::INDIKATOR_READ));
    }

    public function view(User $user, IndikatorKinerja $indikator): Response
    {
        return $this->response($this->permissionResolver->resolve($user, PermissionCodes::INDIKATOR_READ));
    }

    public function create(User $user): Response
    {
        return $this->response($this->permissionResolver->resolve($user, PermissionCodes::INDIKATOR_CREATE));
    }

    public function update(User $user, IndikatorKinerja $indikator): Response
    {
        $decision = $this->permissionResolver->resolve($user, PermissionCodes::INDIKATOR_UPDATE);

        if (! $decision->allowed) {
            $this->catatPenolakan($user, $indikator, 'indikator.ubah_ditolak', $decision);
        }

        return $this->response($decision);
    }

    public function delete(User $user, IndikatorKinerja $indikator): Response
    {
        $decision = $this->permissionResolver->resolve($user, PermissionCodes::INDIKATOR_DELETE);

        if (! $decision->allowed) {
            $this->catatPenolakan($user, $indikator, 'indikator.hapus_ditolak', $decision);
        }

        return $this->response($decision);
    }

    private function response(PermissionDecision $decision): Response
    {
        return $decision->allowed
            ? Response::allow()
            : Response::deny('Anda tidak memiliki izin yang efektif untuk melakukan tindakan ini.');
    }

    private function catatPenolakan(
        User $user,
        IndikatorKinerja $indikator,
        string $tindakan,
        PermissionDecision $decision,
    ): void {
        $alasan = request()->input('alasan');

        $this->auditLogger->catat(
            actor: $user,
            tindakan: $tindakan,
            objekTipe: 'indikator',
            objekId: $indikator->id,
            nilaiLama: $indikator->withoutRelations()->toArray(),
            alasan: is_string($alasan) ? $alasan : null,
            dasarIzin: $decision->toAuditBasis(),
        );
    }
}
