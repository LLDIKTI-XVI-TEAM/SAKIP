<?php

namespace App\Http\Middleware;

use App\Actions\Kegiatan\CatatRealisasiKegiatan;
use App\Actions\Kegiatan\SimpanKegiatan;
use App\Actions\Kegiatan\UbahStatusKegiatan;
use App\Models\Kegiatan;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKegiatanActorIsActive extends EnsureUserIsActive
{
    public function __construct(
        private readonly SimpanKegiatan $action,
        private readonly UbahStatusKegiatan $ubahStatus,
        private readonly CatatRealisasiKegiatan $realisasi,
        private readonly AuditLogger $audit,
        private readonly PermissionResolver $resolver,
    ) {}

    /** Delegasikan audit ke use case sebelum guard mengirim respons penolakan. */
    protected function rejectInactive(Request $request, ?User $actor): Response
    {
        if ($actor) {
            $kegiatan = $request->route('kegiatan');
            $header = $request->route('rencanaAksi');
            if ($request->routeIs('kegiatan.status') && $kegiatan instanceof Kegiatan) {
                $this->ubahStatus->catatPenolakan($actor, $kegiatan, 'Akun tidak aktif saat mengubah status kegiatan.');
            } elseif ($request->routeIs('kegiatan.realisasi') && $kegiatan instanceof Kegiatan) {
                $this->realisasi->catatPenolakan($actor, $kegiatan, 'Akun tidak aktif saat mencatat realisasi kegiatan.');
            } elseif ($request->routeIs('rencana-aksi.kegiatan.index') && $header instanceof RencanaAksi) {
                // Halaman baca dicatat sebagai percobaan membaca, bukan percobaan menyimpan (fallback di bawah).
                $this->audit->catat(
                    actor: $actor, tindakan: 'kegiatan.baca_ditolak', objekTipe: 'rencana_aksi', objekId: (string) $header->id,
                    alasan: 'Akun tidak aktif saat membuka halaman kegiatan rencana aksi.',
                    dasarIzin: $this->resolver->resolve($actor, PermissionCodes::KEGIATAN_READ, (string) $header->unit_id)->toAuditBasis(),
                );
            } elseif ($request->routeIs('rencana-aksi.kegiatan.edit', 'rencana-aksi.kegiatan.realisasi') && $kegiatan instanceof Kegiatan) {
                // Form rencana dan realisasi memakai gerbang `kegiatan:update` yang sama dengan FormKegiatan.
                $this->audit->catat(
                    actor: $actor, tindakan: 'kegiatan.form_ditolak', objekTipe: 'kegiatan', objekId: (string) $kegiatan->id,
                    alasan: $request->routeIs('rencana-aksi.kegiatan.realisasi')
                        ? 'Akun tidak aktif saat membuka form realisasi kegiatan.' : 'Akun tidak aktif saat membuka form rencana kegiatan.',
                    dasarIzin: $this->resolver->resolve($actor, PermissionCodes::KEGIATAN_UPDATE, (string) $kegiatan->unit_id)->toAuditBasis(),
                );
            } else {
                $this->action->catatPenolakan(
                    $actor, $kegiatan instanceof Kegiatan ? $kegiatan : null, $request->input('unit_id'),
                    'Akun tidak aktif saat menyimpan rencana kegiatan.',
                );
            }
        }

        return parent::rejectInactive($request, $actor);
    }
}
