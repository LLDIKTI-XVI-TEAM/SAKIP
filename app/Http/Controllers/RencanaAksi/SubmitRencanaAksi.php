<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\AjukanRencanaAksi;
use App\Http\Controllers\Controller;
use App\Http\Requests\RencanaAksi\AjukanRencanaAksiRequest;
use Illuminate\Http\RedirectResponse;

class SubmitRencanaAksi extends Controller
{
    public function __invoke(AjukanRencanaAksiRequest $request, string $rencanaAksi, AjukanRencanaAksi $action): RedirectResponse
    {
        $action->handle($request->user(), $rencanaAksi, $request->validated());

        return redirect()->back()->with('success', 'Rencana aksi berhasil diajukan untuk verifikasi.');
    }
}
