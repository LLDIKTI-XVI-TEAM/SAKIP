<?php

namespace App\Http\Middleware;

use App\Actions\Kegiatan\SimpanKegiatan;
use App\Actions\Kegiatan\UbahStatusKegiatan;
use App\Models\Kegiatan;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKegiatanActorIsActive extends EnsureUserIsActive
{
    public function __construct(private readonly SimpanKegiatan $action, private readonly UbahStatusKegiatan $ubahStatus) {}

    /** Delegasikan audit ke use case sebelum guard mengirim respons penolakan. */
    protected function rejectInactive(Request $request, ?User $actor): Response
    {
        if ($actor) {
            $kegiatan = $request->route('kegiatan');
            if ($request->routeIs('kegiatan.status') && $kegiatan instanceof Kegiatan) {
                $this->ubahStatus->catatPenolakan($actor, $kegiatan, 'Akun tidak aktif saat mengubah status kegiatan.');
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
