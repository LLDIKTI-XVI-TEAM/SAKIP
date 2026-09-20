<?php

namespace App\Policies;

use App\Models\Regulasi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\Response;

class RegulasiPolicy
{
    public function __construct(
        private readonly PermissionResolver $permissionResolver,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function viewAny(User $user): Response
    {
        return $this->response($this->permissionResolver->resolve($user, PermissionCodes::REGULASI_READ));
    }

    public function view(User $user, Regulasi $regulasi): Response
    {
        return $this->response($this->permissionResolver->resolve($user, PermissionCodes::REGULASI_READ));
    }

    public function create(User $user): Response
    {
        return $this->response($this->permissionResolver->resolve($user, PermissionCodes::REGULASI_CREATE));
    }

    public function update(User $user, Regulasi $regulasi): Response
    {
        $decision = $this->permissionResolver->resolve($user, PermissionCodes::REGULASI_UPDATE);

        if (! $decision->allowed) {
            $this->catatPenolakan($user, $regulasi, 'regulasi.ubah_ditolak', $decision);
        }

        return $this->response($decision);
    }

    public function delete(User $user, Regulasi $regulasi): Response
    {
        $decision = $this->permissionResolver->resolve($user, PermissionCodes::REGULASI_DELETE);

        if (! $decision->allowed) {
            $this->catatPenolakan($user, $regulasi, 'regulasi.hapus_ditolak', $decision);
        }

        return $this->response($decision);
    }

    public function deleteAttachment(User $user, Regulasi $regulasi): Response
    {
        $decision = $this->permissionResolver->resolve($user, PermissionCodes::BERKAS_DELETE);

        if (! $decision->allowed) {
            $this->catatPenolakan($user, $regulasi, 'berkas.hapus_ditolak', $decision);
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
        Regulasi $regulasi,
        string $tindakan,
        PermissionDecision $decision,
    ): void {
        $alasan = request()->input('alasan');

        $this->auditLogger->catat(
            actor: $user,
            tindakan: $tindakan,
            objekTipe: 'regulasi',
            objekId: $regulasi->id,
            nilaiLama: $regulasi->withoutRelations()->toArray(),
            alasan: is_string($alasan) ? $alasan : null,
            dasarIzin: $decision->toAuditBasis(),
        );
    }
}
