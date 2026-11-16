<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\PindahkanUnitRencanaAksi;
use App\Http\Controllers\Controller;
use App\Http\Requests\RencanaAksi\PindahUnitRencanaAksiRequest;
use Illuminate\Http\RedirectResponse;

class PindahUnitRencanaAksi extends Controller
{
    public function __invoke(PindahUnitRencanaAksiRequest $request, string $rencanaAksi, PindahkanUnitRencanaAksi $action): RedirectResponse
    {
        $action->handle($request->user(), $rencanaAksi, $request->validated());

        return redirect()->route('rencana-aksi.index')->with('success', 'Unit rencana aksi dipindahkan. Lanjutkan dengan koreksi snapshot indikator.');
    }
}
