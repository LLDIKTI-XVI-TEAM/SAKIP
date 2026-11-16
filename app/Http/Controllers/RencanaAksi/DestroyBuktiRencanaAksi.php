<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\HapusBuktiRencanaAksi;
use App\Http\Controllers\Controller;
use App\Http\Requests\RencanaAksi\DestroyBuktiRencanaAksiRequest;
use App\Models\RencanaAksi;
use Illuminate\Http\RedirectResponse;

class DestroyBuktiRencanaAksi extends Controller
{
    public function __invoke(DestroyBuktiRencanaAksiRequest $request, RencanaAksi $rencanaAksi, string $bukti, HapusBuktiRencanaAksi $action): RedirectResponse
    {
        $action->handle($request->user(), (string) $rencanaAksi->id, $bukti, (string) $request->validated('alasan'));

        return back()->with('success', 'Bukti dukung rencana aksi berhasil dihapus.');
    }
}
