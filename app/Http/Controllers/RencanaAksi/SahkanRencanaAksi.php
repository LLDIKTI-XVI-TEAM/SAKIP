<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\SahkanRencanaAksi as SahkanRencanaAksiAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RencanaAksi\SahkanRencanaAksiRequest;
use Illuminate\Http\RedirectResponse;

class SahkanRencanaAksi extends Controller
{
    public function __invoke(SahkanRencanaAksiRequest $request, string $id, SahkanRencanaAksiAction $action): RedirectResponse
    {
        $action->handle($request->user(), $id, $request->validated());

        return redirect()->back()->with('success', 'Rencana aksi disahkan dan versi pengajuan dipertahankan.');
    }
}
