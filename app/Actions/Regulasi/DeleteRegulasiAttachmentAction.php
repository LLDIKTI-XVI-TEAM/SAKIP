<?php

namespace App\Actions\Regulasi;

use App\Models\Berkas;
use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Regulasi\RegulasiAttachments;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteRegulasiAttachmentAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
        private readonly RegulasiAttachments $attachments,
    ) {}

    /** Berkas harus tetap milik induk terkunci; berkas:delete merupakan izin alur hapus lampiran. */
    public function handle(User $actor, Regulasi $regulasi, Berkas $berkas, string $alasan): void
    {
        $result = DB::transaction(function () use ($actor, $regulasi, $berkas, $alasan): PermissionDecision|array|null {
            $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
            $actor->lockActiveRoles();
            Permission::where('kode', PermissionCodes::BERKAS_DELETE)->orderBy('id')->sharedLock()->get();
            $decision = $this->resolver->resolve($actor, PermissionCodes::BERKAS_DELETE);
            $alasanPenolakan = AuditReason::sanitize($alasan);
            if (! $decision->allowed) {
                $this->audit->catat(
                    actor: $actor, tindakan: 'berkas.hapus_ditolak', objekTipe: 'berkas', objekId: $berkas->id,
                    nilaiLama: $this->attachments->metadataBerkasUntukAudit($berkas),
                    alasan: trim($alasanPenolakan) !== '' ? $alasanPenolakan : 'Penghapusan lampiran regulasi ditolak karena izin tidak efektif.',
                    dasarIzin: $decision->toAuditBasis(),
                );

                return $decision;
            }

            $current = Regulasi::lockForUpdate()->findOrFail($regulasi->id);
            $currentFile = Berkas::lockForUpdate()->findOrFail($berkas->id);
            if ($currentFile->berkasable_type !== $current->getMorphClass() || $currentFile->berkasable_id !== $current->id) {
                throw new AuthorizationException('Lampiran bukan milik regulasi yang dituju.');
            }
            $references = $current->lockReferenceCounts();
            if ($references['jumlah_renstra_aktif'] > 0 || $references['jumlah_indikator_aktif'] > 0) {
                $this->audit->catat(
                    actor: $actor, tindakan: 'berkas.hapus_ditolak', objekTipe: 'berkas', objekId: $currentFile->id,
                    nilaiLama: $this->attachments->metadataBerkasUntukAudit($currentFile),
                    nilaiBaru: ['alasan_penolakan' => 'regulasi_masih_dirujuk_data_aktif', ...$references],
                    alasan: trim($alasanPenolakan) !== '' ? $alasanPenolakan : 'Penghapusan lampiran ditolak karena regulasi masih dirujuk data aktif.',
                    dasarIzin: $decision->toAuditBasis(),
                );

                return $references;
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
                DB::afterCommit(fn () => $this->attachments->hapusFile([$path], 'regulasi.hapus_lampiran'));
            }

            return null;
        });

        if ($result instanceof PermissionDecision) {
            throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
        }
        if ($result !== null) {
            throw ValidationException::withMessages(['berkas' => 'Lampiran tidak dapat dihapus karena regulasi masih dirujuk oleh Renstra atau Indikator aktif.']);
        }
    }
}
