<?php

namespace App\Actions\Renstra;

use App\Models\Berkas;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\User;
use App\Policies\RenstraPolicy;
use App\Services\AuditLogger;
use App\Services\Renstra\RenstraAttachments;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteRenstraAttachmentAction
{
    public function __construct(
        private readonly RenstraPolicy $policy,
        private readonly AuditLogger $audit,
        private readonly RenstraAttachments $attachments,
    ) {}

    /** Kedua gerbang parent dan berkas tetap berlaku; lampiran dipilih hanya dari induk yang terkunci. */
    public function handle(User $actor, Renstra $renstra, Berkas $berkas, string $alasan): void
    {
        $result = DB::transaction(function () use ($actor, $renstra, $berkas, $alasan): PermissionDecision|array|null {
            $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
            $actor->lockActiveRoles();
            Permission::whereIn('kode', [PermissionCodes::RENSTRA_DELETE, PermissionCodes::RENSTRA_UPDATE, PermissionCodes::BERKAS_DELETE])->orderBy('id')->sharedLock()->get();
            $decision = $this->policy->deleteAttachmentDecision($actor);
            if (! $decision->allowed) {
                $this->audit->catat(
                    actor: $actor, tindakan: 'berkas.hapus_ditolak', objekTipe: 'berkas', objekId: $berkas->id,
                    nilaiLama: $this->attachments->metadataBerkasUntukAudit($berkas),
                    alasan: AuditReason::sanitize($alasan), dasarIzin: $decision->toAuditBasis(),
                );

                return $decision;
            }

            $current = Renstra::lockForUpdate()->findOrFail($renstra->id);
            // Scope morph diterapkan sebelum lock: request yang salah tidak mengunci berkas induk lain.
            $currentFile = $current->berkas()->whereKey($berkas->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== Renstra::STATUS_DRAFT) {
                $this->audit->catat(
                    actor: $actor, tindakan: 'berkas.hapus_ditolak', objekTipe: 'berkas', objekId: $currentFile->id,
                    nilaiLama: $this->attachments->metadataBerkasUntukAudit($currentFile),
                    nilaiBaru: ['alasan_penolakan' => 'renstra_bukan_draft_lampiran_imutabel', 'status_renstra' => $current->status],
                    alasan: AuditReason::sanitize($alasan), dasarIzin: $decision->toAuditBasis(),
                );

                return ['berkas' => 'Lampiran Renstra hanya dapat dihapus pada status draft karena telah mencapai batas imutabilitas.'];
            }
            $path = $currentFile->mode === 'file' && is_string($currentFile->path) ? $currentFile->path : null;
            $metadata = $this->attachments->metadataBerkasUntukAudit($currentFile);
            $currentFile->dihapus_oleh = $actor->id;
            $currentFile->save();
            $currentFile->delete();
            $this->audit->catat(
                actor: $actor, tindakan: 'berkas.hapus', objekTipe: 'berkas', objekId: $currentFile->id,
                nilaiLama: $metadata, alasan: $alasan, dasarIzin: $decision->toAuditBasis(),
            );
            if ($path !== null) {
                DB::afterCommit(fn () => $this->attachments->hapusFile([$path], 'renstra.hapus_lampiran'));
            }

            return null;
        });
        if ($result instanceof PermissionDecision) {
            throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
        }
        if ($result !== null) {
            throw ValidationException::withMessages($result);
        }
    }
}
