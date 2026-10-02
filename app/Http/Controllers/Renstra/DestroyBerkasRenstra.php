<?php

namespace App\Http\Controllers\Renstra;

use App\Actions\Renstra\DeleteRenstraAttachmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Renstra\DestroyBerkasRenstraRequest;
use App\Models\Berkas;
use App\Models\Renstra;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DestroyBerkasRenstra extends Controller
{
    public function __invoke(DestroyBerkasRenstraRequest $request, Renstra $renstra, Berkas $berkas, DeleteRenstraAttachmentAction $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($actor, $renstra, $berkas, (string) $request->validated('alasan'));

        return back()->with('success', 'Lampiran dokumen Renstra berhasil dihapus.');
    }
}
