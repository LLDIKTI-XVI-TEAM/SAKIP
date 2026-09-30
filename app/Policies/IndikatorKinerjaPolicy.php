<?php

namespace App\Policies;

use App\Models\IndikatorKinerja;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Str;

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
        $decision = $this->permissionResolver->resolve($user, PermissionCodes::INDIKATOR_CREATE);

        if (! $decision->allowed) {
            $alasan = AlasanAudit::sanitasi(
                request()->input('alasan'),
                'Percobaan membuat indikator kinerja ditolak oleh sistem otorisasi.'
            );
            $this->auditLogger->catat(
                actor: $user,
                tindakan: 'indikator.buat_ditolak',
                objekTipe: 'indikator',
                objekId: (string) Str::uuid(),
                nilaiLama: null,
                nilaiBaru: null,
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );
        }

        return $this->response($decision);
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
        $alasan = AlasanAudit::sanitasi(
            request()->input('alasan'),
            "Percobaan {$tindakan} ditolak oleh sistem otorisasi."
        );

        $this->auditLogger->catat(
            actor: $user,
            tindakan: $tindakan,
            objekTipe: 'indikator',
            objekId: $indikator->id,
            nilaiLama: $indikator->withoutRelations()->toArray(),
            nilaiBaru: null,
            alasan: $alasan,
            dasarIzin: $decision->toAuditBasis(),
        );
    }
}
