<?php

namespace App\Actions\Regulasi;

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

class DeleteRegulasiAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
        private readonly RegulasiAttachments $attachments,
    ) {}

    /** Rujukan aktif mencegah hapus induk; file fisik baru dihapus sesudah metadata dan audit committed. */
    public function handle(User $actor, Regulasi $regulasi, string $alasan): void
    {
        $result = DB::transaction(function () use ($actor, $regulasi, $alasan): PermissionDecision|array|null {
            $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
            $actor->lockActiveRoles();
            Permission::where('kode', PermissionCodes::REGULASI_DELETE)->orderBy('id')->sharedLock()->get();
            $decision = $this->resolver->resolve($actor, PermissionCodes::REGULASI_DELETE);
            $alasanPenolakan = AuditReason::sanitize($alasan);
            if (! $decision->allowed) {
                $this->audit->catat(
                    actor: $actor, tindakan: 'regulasi.hapus_ditolak', objekTipe: 'regulasi', objekId: $regulasi->id,
                    nilaiLama: $regulasi->withoutRelations()->toArray(),
                    alasan: trim($alasanPenolakan) !== '' ? $alasanPenolakan : 'Penghapusan regulasi ditolak karena izin tidak efektif.',
                    dasarIzin: $decision->toAuditBasis(),
                );

                return $decision;
            }

            $current = Regulasi::lockForUpdate()->findOrFail($regulasi->id);
            $references = $current->lockReferenceCounts();
            if ($references['jumlah_renstra_aktif'] > 0 || $references['jumlah_indikator_aktif'] > 0) {
                $this->audit->catat(
                    actor: $actor, tindakan: 'regulasi.hapus_ditolak', objekTipe: 'regulasi', objekId: $current->id,
                    nilaiLama: $current->withoutRelations()->toArray(),
                    nilaiBaru: ['alasan_penolakan' => 'masih_dirujuk_data_aktif', ...$references],
                    alasan: trim($alasanPenolakan) !== '' ? $alasanPenolakan : 'Penghapusan regulasi ditolak karena masih dirujuk data aktif.',
                    dasarIzin: $decision->toAuditBasis(),
                );

                return $references;
            }

            $current->load('berkas');
            $nilaiLama = $this->attachments->snapshot($current);
            $paths = $current->berkas->where('mode', 'file')->pluck('path')
                ->filter(fn ($path) => is_string($path) && $path !== '')->values()->all();
            foreach ($current->berkas as $berkas) {
                $metadata = $this->attachments->metadataBerkasUntukAudit($berkas);
                $berkas->dihapus_oleh = $actor->id;
                $berkas->save();
                $berkas->delete();
                $this->audit->catat(
                    actor: $actor, tindakan: 'berkas.hapus', objekTipe: 'berkas', objekId: $berkas->id,
                    nilaiLama: $metadata, alasan: $alasan, dasarIzin: $decision->toAuditBasis(),
                );
            }
            $this->audit->catat(
                actor: $actor, tindakan: 'regulasi.hapus', objekTipe: 'regulasi', objekId: $current->id,
                nilaiLama: $nilaiLama, alasan: $alasan, dasarIzin: $decision->toAuditBasis(),
            );
            $current->delete();
            DB::afterCommit(fn () => $this->attachments->hapusFile($paths, 'regulasi.hapus'));

            return null;
        });

        // Penolakan dikembalikan sesudah commit agar jejak audit tetap tersimpan.
        if ($result instanceof PermissionDecision) {
            throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
        }
        if ($result !== null) {
            throw ValidationException::withMessages(['regulasi' => 'Regulasi tidak dapat dihapus karena masih dirujuk oleh Renstra atau Indikator aktif.']);
        }
    }
}
