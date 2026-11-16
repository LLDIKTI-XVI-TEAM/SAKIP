<?php

namespace App\Http\Controllers\Kegiatan;

use App\Actions\Kegiatan\FormKegiatanRencanaAksi;
use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use App\Models\RencanaAksi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EditKegiatanRencanaAksi extends Controller
{
    /**
     * Form ubah kegiatan dalam konteks Rencana Aksi pengklaimnya (Q74 §74.1).
     * `FormKegiatanRencanaAksi` menegakkan `kegiatan:read` + `kegiatan:update`
     * per unit (audit penolakan) sebelum memastikan kegiatan diklaim RA ini.
     */
    public function __invoke(Request $request, RencanaAksi $rencanaAksi, Kegiatan $kegiatan, FormKegiatanRencanaAksi $form): Response
    {
        return Inertia::render('RencanaAksi/KegiatanEdit', $form->handle($request->user(), $rencanaAksi, $kegiatan));
    }
}
