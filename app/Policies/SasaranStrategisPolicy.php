<?php

namespace App\Policies;

use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Str;

class SasaranStrategisPolicy
{
    public function __construct(
        private readonly PermissionResolver $permissionResolver,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function viewAny(User $user): Response
    {
        return $this->response($this->permissionResolver->resolve($user, PermissionCodes::INDIKATOR_READ));
    }

    public function view(User $user, SasaranStrategis $sasaran): Response
    {
        return $this->response($this->permissionResolver->resolve($user, PermissionCodes::INDIKATOR_READ));
    }

    public function create(User $user): Response
    {
        $decision = $this->permissionResolver->resolve($user, PermissionCodes::SASARAN_CREATE);

        if (! $decision->allowed) {
            $alasan = request()->input('alasan');
            $this->auditLogger->catat(
                actor: $user,
                tindakan: 'sasaran.buat_ditolak',
                objekTipe: 'sasaran',
                objekId: (string) Str::uuid(),
                nilaiLama: null,
                nilaiBaru: null,
                alasan: is_string($alasan) && trim($alasan) !== '' ? $alasan : 'Percobaan membuat sasaran strategis ditolak oleh sistem otorisasi.',
                dasarIzin: $decision->toAuditBasis(),
            );
        }

        return $this->response($decision);
    }

    public function update(User $user, SasaranStrategis $sasaran): Response
    {
        $decision = $this->permissionResolver->resolve($user, PermissionCodes::SASARAN_UPDATE);

        if (! $decision->allowed) {
            $this->catatPenolakan($user, $sasaran, 'sasaran.ubah_ditolak', $decision);
        }

        return $this->response($decision);
    }

    public function delete(User $user, SasaranStrategis $sasaran): Response
    {
        $decision = $this->permissionResolver->resolve($user, PermissionCodes::SASARAN_DELETE);

        if (! $decision->allowed) {
            $this->catatPenolakan($user, $sasaran, 'sasaran.hapus_ditolak', $decision);
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
        SasaranStrategis $sasaran,
        string $tindakan,
        PermissionDecision $decision,
    ): void {
        $alasan = request()->input('alasan');

        $this->auditLogger->catat(
            actor: $user,
            tindakan: $tindakan,
            objekTipe: 'sasaran',
            objekId: $sasaran->id,
            nilaiLama: $sasaran->withoutRelations()->toArray(),
            nilaiBaru: null,
            alasan: is_string($alasan) && trim($alasan) !== '' ? $alasan : "Percobaan {$tindakan} ditolak oleh sistem otorisasi.",
            dasarIzin: $decision->toAuditBasis(),
        );
    }
}
