<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Renstra\DestroyBerkasRenstraRequest;
use App\Models\Berkas;
use App\Models\Renstra;
use App\Models\User;
use App\Services\RenstraService;
use Illuminate\Http\RedirectResponse;

class DestroyBerkasRenstra extends Controller
{
    public function __invoke(DestroyBerkasRenstraRequest $request, Renstra $renstra, Berkas $berkas, RenstraService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $service->deleteAttachment($renstra, $berkas, (string) $request->input('alasan'), $actor);

        return back()->with('success', 'Lampiran dokumen Renstra berhasil dihapus.');
    }
}
