<?php

namespace App\Http\Controllers\Regulasi;

use App\Actions\Regulasi\DeleteRegulasiAttachmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Regulasi\DeleteBerkasRegulasiRequest;
use App\Models\Berkas;
use App\Models\Regulasi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DestroyBerkasRegulasi extends Controller
{
    public function __invoke(
        DeleteBerkasRegulasiRequest $request,
        Regulasi $regulasi,
        Berkas $berkas,
        DeleteRegulasiAttachmentAction $action,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        $action->handle(
            regulasi: $regulasi,
            berkas: $berkas,
            alasan: $request->validated()['alasan'],
            actor: $actor,
        );

        return back()->with('success', 'Lampiran berhasil dihapus dan jejak audit telah dicatat.');
    }
}
