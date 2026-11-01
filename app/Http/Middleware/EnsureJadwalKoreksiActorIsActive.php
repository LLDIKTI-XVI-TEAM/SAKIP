<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Audit penolakan akun nonaktif sebelum guard sesi mengirim respons pemulihan existing. */
class EnsureJadwalKoreksiActorIsActive extends EnsureUserIsActive
{
    protected function rejectInactive(Request $request, ?User $actor): Response
    {
        if ($actor) {
            $decision = app(PermissionResolver::class)->resolve($actor, PermissionCodes::JADWAL_BUKA_KEMBALI);
            app(AuditLogger::class)->catat(actor: $actor, tindakan: 'jadwal.buka_kembali_ditolak', objekTipe: 'jadwal',
                objekId: (string) $request->route('jadwal'), nilaiBaru: ['alasan_penolakan' => 'izin_ditolak'], dasarIzin: $decision->toAuditBasis());
        }

        return parent::rejectInactive($request, $actor);
    }
}
