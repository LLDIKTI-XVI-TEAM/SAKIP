<?php

namespace App\Policies;

use App\Models\Berkas;
use App\Models\Regulasi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Regulasi\RegulasiAttachments;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\Response;

class RegulasiPolicy
{
    public function __construct(
        private readonly PermissionResolver $permissionResolver,
        private readonly AuditLogger $auditLogger,
        private readonly RegulasiAttachments $attachments,
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

    public function deleteAttachment(User $user, Regulasi $regulasi, Berkas $berkas): Response
    {
        $decision = $this->permissionResolver->resolve($user, PermissionCodes::BERKAS_DELETE);

        if (! $decision->allowed) {
            $this->catatPenolakanLampiran($user, $berkas, $decision);
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
        // authorize() mendahului validasi; sanitasi alasan tidak boleh mengganti penolakan menjadi 422/redirect.
        $alasan = mb_substr(trim(AuditReason::sanitize(request()->input('alasan'))), 0, 1000);

        $this->auditLogger->catat(
            actor: $user,
            tindakan: $tindakan,
            objekTipe: 'regulasi',
            objekId: $regulasi->id,
            nilaiLama: $regulasi->withoutRelations()->toArray(),
            alasan: trim($alasan) !== '' ? $alasan : 'Tindakan regulasi ditolak karena izin tidak efektif.',
            dasarIzin: $decision->toAuditBasis(),
        );
    }

    private function catatPenolakanLampiran(
        User $user,
        Berkas $berkas,
        PermissionDecision $decision,
    ): void {
        $alasan = mb_substr(trim(AuditReason::sanitize(request()->input('alasan'))), 0, 1000);

        $this->auditLogger->catat(
            actor: $user,
            tindakan: 'berkas.hapus_ditolak',
            objekTipe: 'berkas',
            objekId: $berkas->id,
            nilaiLama: $this->attachments->metadataBerkasUntukAudit($berkas),
            alasan: trim($alasan) !== '' ? $alasan : 'Penghapusan lampiran regulasi ditolak karena izin tidak efektif.',
            dasarIzin: $decision->toAuditBasis(),
        );
    }
}
