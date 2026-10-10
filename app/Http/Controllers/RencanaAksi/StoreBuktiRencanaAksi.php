<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\TambahBuktiRencanaAksi;
use App\Http\Controllers\Controller;
use App\Http\Requests\RencanaAksi\StoreBuktiRencanaAksiRequest;
use App\Models\RencanaAksi;
use Illuminate\Http\RedirectResponse;

class StoreBuktiRencanaAksi extends Controller
{
    public function __invoke(StoreBuktiRencanaAksiRequest $request, RencanaAksi $rencanaAksi, TambahBuktiRencanaAksi $action): RedirectResponse
    {
        $action->handle($request->user(), (string) $rencanaAksi->id, $request->validated());

        return back()->with('success', 'Bukti dukung rencana aksi berhasil disimpan.');
    }
}
