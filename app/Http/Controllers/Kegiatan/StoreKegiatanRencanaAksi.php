<?php

namespace App\Http\Controllers\Kegiatan;

use App\Actions\Kegiatan\TambahKegiatanRencanaAksi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kegiatan\TambahKegiatanRencanaAksiRequest;
use App\Models\RencanaAksi;
use Illuminate\Http\RedirectResponse;

class StoreKegiatanRencanaAksi extends Controller
{
    public function __invoke(TambahKegiatanRencanaAksiRequest $request, RencanaAksi $rencanaAksi, TambahKegiatanRencanaAksi $action): RedirectResponse
    {
        $jumlah = count($action->handle($request->user(), (string) $rencanaAksi->id, $request->validated()));

        return back()->with('success', $jumlah > 1 ? "{$jumlah} kegiatan berhasil ditambahkan ke rencana aksi." : 'Kegiatan berhasil ditambahkan ke rencana aksi.');
    }
}
