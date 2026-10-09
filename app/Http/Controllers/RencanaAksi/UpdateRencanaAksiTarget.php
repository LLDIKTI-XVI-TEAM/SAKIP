<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\SimpanTargetPeriode;
use App\Http\Controllers\Controller;
use App\Http\Requests\RencanaAksi\SimpanTargetPeriodeRequest;
use Illuminate\Http\RedirectResponse;

class UpdateRencanaAksiTarget extends Controller
{
    public function __invoke(SimpanTargetPeriodeRequest $request, string $rencanaAksi, SimpanTargetPeriode $action): RedirectResponse
    {
        $data = $request->validated();
        $action->handle($request->user(), $rencanaAksi, $data);

        return redirect()->back()->with('success', 'Target rencana aksi berhasil disimpan.');
    }
}
