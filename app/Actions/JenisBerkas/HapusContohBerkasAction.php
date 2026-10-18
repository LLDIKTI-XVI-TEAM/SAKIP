<?php

namespace App\Actions\JenisBerkas;

use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use App\Support\AlasanAudit;
use Illuminate\Support\Facades\DB;

/**
 * Hapus contoh berkas kapan saja dengan alasan wajib (Q42 §42.3 butir 4,
 * Q43 §43.2 butir 2): soft delete, file fisik dipertahankan.
 */
class HapusContohBerkasAction
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, string $id, string $contohId, string $alasan): void
    {
        $denied = DB::transaction(function () use (&$actor, $id, $contohId, $alasan): ?array {
            $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
            $actor->lockActiveRoles();
            Permission::where('kode', 'jenis_berkas:update')->sharedLock()->get();
            $decision = $this->resolver->decide($actor, 'jenis_berkas:update');
            if (! $decision['allowed']) {
                return $decision;
            }

            $jenis = JenisBerkas::whereKey($id)->lockForUpdate()->firstOrFail();
            // Contoh persyaratan lain dijawab 404 agar keberadaannya tidak bocor.
            /** @var BuktiDukung $contoh */
            $contoh = BuktiDukung::where('berkasable_type', 'jenis_berkas')->where('berkasable_id', $jenis->id)
                ->current()->whereKey($contohId)->lockForUpdate()->firstOrFail();
            $contoh->update(['dihapus_pada' => now(), 'dihapus_oleh' => $actor->id]);

            $this->audit->catat(
                actor: $actor,
                tindakan: 'berkas.hapus',
                objekTipe: 'berkas',
                objekId: (string) $contoh->id,
                nilaiLama: [...GerbangBuktiRencanaAksi::metadataAudit($contoh), 'contoh_untuk_jenis_berkas_id' => (string) $jenis->id],
                nilaiBaru: ['dihapus_pada' => $contoh->dihapus_pada?->toIso8601String()],
                alasan: AlasanAudit::sanitasi($alasan, 'Penghapusan contoh berkas persyaratan.'),
                dasarIzin: $decision,
            );

            return null;
        });

        if ($denied !== null) {
            $this->audit->catat(actor: $actor, tindakan: 'berkas.hapus_ditolak', objekTipe: 'jenis_berkas', objekId: $id,
                alasan: 'Percobaan hapus contoh berkas ditolak karena tidak memiliki izin.', dasarIzin: $denied);
            abort(403);
        }
    }
}
