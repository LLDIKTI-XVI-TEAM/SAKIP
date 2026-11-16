<?php

namespace App\Actions\Renstra;

use App\Models\Permission;
use App\Models\Renstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Renstra\RenstraAttachments;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteRenstraAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
        private readonly RenstraAttachments $attachments,
    ) {}

    /** Hapus draft tanpa dependensi; metadata dan audit atomik, file fisik sesudah commit. */
    public function handle(User $actor, Renstra $renstra, string $alasan): void
    {
        $result = DB::transaction(function () use ($actor, $renstra, $alasan): PermissionDecision|array|null {
            $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
            $actor->lockActiveRoles();
            Permission::whereIn('kode', [PermissionCodes::RENSTRA_DELETE, PermissionCodes::BERKAS_DELETE])->orderBy('id')->sharedLock()->get();
            $decision = $this->resolver->resolve($actor, PermissionCodes::RENSTRA_DELETE);
            if (! $decision->allowed) {
                $renstra->load(['berkas', 'regulasi']);
                $this->audit->catat(
                    actor: $actor, tindakan: 'renstra.hapus_ditolak', objekTipe: 'renstra', objekId: $renstra->id,
                    nilaiLama: $this->attachments->snapshot($renstra),
                    alasan: AuditReason::sanitize($alasan), dasarIzin: $decision->toAuditBasis(),
                );

                return $decision;
            }
            $renstraTerkini = Renstra::query()->lockForUpdate()->findOrFail($renstra->id);
            $renstraTerkini->setRelation('berkas', $renstraTerkini->berkas()->orderBy('id')->lockForUpdate()->get());
            $renstraTerkini->load('regulasi');

            if ($renstraTerkini->status !== Renstra::STATUS_DRAFT) {
                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'renstra.hapus_ditolak',
                    objekTipe: 'renstra',
                    objekId: $renstraTerkini->id,
                    nilaiLama: $this->attachments->snapshot($renstraTerkini),
                    nilaiBaru: [
                        'alasan_penolakan' => 'status_bukan_draft',
                        'status' => $renstraTerkini->status,
                    ],
                    alasan: AuditReason::sanitize($alasan),
                    dasarIzin: $decision->toAuditBasis(),
                );

                return [
                    'field' => 'renstra',
                    'pesan' => 'Hanya Renstra berstatus draft yang dapat dihapus.',
                ];
            }

            if ($renstraTerkini->sasaranStrategis()->exists() || $renstraTerkini->renstraPk()->exists() || $renstraTerkini->jadwalTahunan()->exists()) {
                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'renstra.hapus_ditolak',
                    objekTipe: 'renstra',
                    objekId: $renstraTerkini->id,
                    nilaiLama: $this->attachments->snapshot($renstraTerkini),
                    nilaiBaru: [
                        'alasan_penolakan' => 'memiliki_dependensi',
                        'has_sasaran' => $renstraTerkini->sasaranStrategis()->exists(),
                        'has_pk' => $renstraTerkini->renstraPk()->exists(),
                        'has_jadwal' => $renstraTerkini->jadwalTahunan()->exists(),
                    ],
                    alasan: AuditReason::sanitize($alasan),
                    dasarIzin: $decision->toAuditBasis(),
                );

                return [
                    'field' => 'renstra',
                    'pesan' => 'Renstra tidak dapat dihapus karena telah memiliki data sasaran, perjanjian kinerja, atau jadwal terkait.',
                ];
            }

            $hasBerkas = $renstraTerkini->berkas->isNotEmpty();
            $berkasDecision = null;
            if ($hasBerkas) {
                $berkasDecision = $this->resolver->resolve($actor, PermissionCodes::BERKAS_DELETE);
                if (! $berkasDecision->allowed) {
                    $this->audit->catat(
                        actor: $actor,
                        tindakan: 'renstra.hapus_ditolak',
                        objekTipe: 'renstra',
                        objekId: $renstraTerkini->id,
                        nilaiLama: $this->attachments->snapshot($renstraTerkini),
                        nilaiBaru: [
                            'alasan_penolakan' => 'berkas_delete_denied',
                        ],
                        alasan: AuditReason::sanitize($alasan),
                        dasarIzin: $berkasDecision->toAuditBasis(),
                    );

                    return [
                        'field' => 'renstra',
                        'pesan' => 'Renstra tidak dapat dihapus karena Anda tidak memiliki izin untuk menghapus lampiran berkas yang terkait.',
                    ];
                }
            }

            // Snapshot memakai kumpulan lampiran yang sudah terkunci.
            $nilaiLama = $this->attachments->snapshot($renstraTerkini);

            $paths = [];
            foreach ($renstraTerkini->berkas as $berkas) {
                if ($berkas->mode === 'file' && is_string($berkas->path)) {
                    $paths[] = $berkas->path;
                }

                $nilaiLamaBerkas = $this->attachments->metadataBerkasUntukAudit($berkas);

                $berkas->dihapus_oleh = $actor->id;
                $berkas->save();
                $berkas->delete();

                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'berkas.hapus',
                    objekTipe: 'berkas',
                    objekId: $berkas->id,
                    nilaiLama: $nilaiLamaBerkas,
                    alasan: $alasan,
                    dasarIzin: $berkasDecision ? $berkasDecision->toAuditBasis() : $decision->toAuditBasis(),
                );
            }

            $renstraTerkini->delete();

            $this->audit->catat(
                actor: $actor,
                tindakan: 'renstra.hapus',
                objekTipe: 'renstra',
                objekId: $renstraTerkini->id,
                nilaiLama: $nilaiLama,
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );

            if (! empty($paths)) {
                DB::afterCommit(fn () => $this->attachments->hapusFile($paths, 'renstra.hapus'));
            }

            return null;
        });
        if ($result instanceof PermissionDecision) {
            throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
        }
        if ($result !== null) {
            throw ValidationException::withMessages([$result['field'] => $result['pesan']]);
        }
    }
}
