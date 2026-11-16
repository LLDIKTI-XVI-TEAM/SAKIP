<?php

namespace App\Http\Middleware;

use App\Models\Kegiatan;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun nonaktif ditolak sebelum FormRequest/Action klaim berjalan, sehingga
 * percobaan mengubah daftar kegiatan tahap RA dicatat di sini dengan event
 * yang sama dengan penolakan di Action.
 */
class EnsureKlaimKegiatanActorIsActive extends EnsureUserIsActive
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PermissionResolver $resolver,
    ) {}

    protected function rejectInactive(Request $request, ?User $actor): Response
    {
        $kegiatan = $request->route('kegiatan');
        $header = $request->route('rencanaAksi');
        $target = match ($request->route()?->getName()) {
            'kegiatan.klaim.store' => $kegiatan instanceof Kegiatan
                ? ['klaim_kegiatan.tambah_ditolak', 'kegiatan', (string) $kegiatan->id, PermissionCodes::KEGIATAN_UPDATE, (string) $kegiatan->unit_id] : null,
            'kegiatan.klaim.destroy' => $kegiatan instanceof Kegiatan
                ? ['klaim_kegiatan.hapus_ditolak', 'klaim_kegiatan', (string) $request->route('klaim'), PermissionCodes::KEGIATAN_UPDATE, (string) $kegiatan->unit_id] : null,
            'rencana-aksi.kegiatan.store' => $header instanceof RencanaAksi
                ? ['kegiatan.buat_ditolak', 'rencana_aksi', (string) $header->id, PermissionCodes::KEGIATAN_CREATE, (string) $header->unit_id] : null,
            default => null,
        };
        if ($actor && $target !== null) {
            [$tindakan, $objekTipe, $objekId, $izin, $unitId] = $target;
            $this->audit->catat(
                actor: $actor, tindakan: $tindakan, objekTipe: $objekTipe, objekId: $objekId,
                alasan: 'Akun tidak aktif saat mengubah daftar kegiatan rencana aksi.',
                dasarIzin: $this->resolver->resolve($actor, $izin, $unitId)->toAuditBasis(),
            );
        }

        return parent::rejectInactive($request, $actor);
    }
}
