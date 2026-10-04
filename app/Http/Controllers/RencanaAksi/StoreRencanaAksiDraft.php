<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\EnsureDraftRencanaAksi;
use App\Http\Controllers\Controller;
use App\Http\Requests\RencanaAksi\EnsureDraftRencanaAksiRequest;
use Illuminate\Http\RedirectResponse;

class StoreRencanaAksiDraft extends Controller
{
    public function __invoke(EnsureDraftRencanaAksiRequest $request, EnsureDraftRencanaAksi $action): RedirectResponse
    {
        $data = $request->validated();
        $action->handle($request->user(), (string) $data['indikator_id'], (int) $data['tahun']);

        return redirect()->back()->with('success', 'Draf rencana aksi berhasil disiapkan.');
    }
}
