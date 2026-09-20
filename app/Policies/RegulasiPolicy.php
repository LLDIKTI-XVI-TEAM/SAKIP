<?php

namespace App\Policies;

use App\Models\Berkas;
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

    private function catatPenolakanLampiran(
        User $user,
        Berkas $berkas,
        PermissionDecision $decision,
    ): void {
        $alasan = request()->input('alasan');

        $metadata = [
            'id' => $berkas->id,
            'mode' => $berkas->mode,
            'jenis_berkas_id' => $berkas->jenis_berkas_id,
        ];

        if ($berkas->mode === 'file') {
            $metadata += [
                'nama_asli' => $berkas->nama_asli,
                'mime' => $berkas->mime,
                'ukuran_bytes' => $berkas->ukuran_bytes,
            ];
        } elseif ($berkas->mode === 'tautan') {
            $metadata['tautan'] = $berkas->tautan;
        } else {
            $metadata['panjang_teks'] = mb_strlen((string) $berkas->isi_teks);
        }

        $this->auditLogger->catat(
            actor: $user,
            tindakan: 'berkas.hapus_ditolak',
            objekTipe: 'berkas',
            objekId: $berkas->id,
            nilaiLama: $metadata,
            alasan: is_string($alasan) ? $alasan : null,
            dasarIzin: $decision->toAuditBasis(),
        );
    }
}
