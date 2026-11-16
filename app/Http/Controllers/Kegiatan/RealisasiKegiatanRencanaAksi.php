<?php

namespace App\Http\Controllers\Kegiatan;

use App\Actions\Kegiatan\FormKegiatanRencanaAksi;
use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use App\Models\RencanaAksi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RealisasiKegiatanRencanaAksi extends Controller
{
    /**
     * Halaman catat realisasi kegiatan, terpisah dari form rencana (Q74 §74.2
     * butir 1). Memakai `FormKegiatanRencanaAksi` yang sama dengan halaman ubah
     * sehingga otorisasi per unit, audit penolakan, dan cek klaim RA tidak
     * bercabang di antara kedua halaman.
     */
    public function __invoke(Request $request, RencanaAksi $rencanaAksi, Kegiatan $kegiatan, FormKegiatanRencanaAksi $form): Response
    {
        return Inertia::render('RencanaAksi/KegiatanRealisasi', $form->handle($request->user(), $rencanaAksi, $kegiatan));
    }
}
