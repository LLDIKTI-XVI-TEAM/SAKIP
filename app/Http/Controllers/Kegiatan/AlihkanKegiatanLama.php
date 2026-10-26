<?php

namespace App\Http\Controllers\Kegiatan;

use App\Actions\Kegiatan\AlihkanKegiatanLamaKeRencanaAksi;
use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlihkanKegiatanLama extends Controller
{
    /** Daftar kegiatan global tidak lagi punya halaman; pengelolaan berpindah ke Rencana Aksi (Q74 §74.1). */
    public function index(): RedirectResponse
    {
        return redirect()->route('rencana-aksi.index');
    }

    /**
     * Tautan ubah lama diarahkan ke halaman kegiatan Rencana Aksi pemilik klaim
     * tertua. Use case memeriksa `kegiatan:read` pada unit kegiatan lebih dulu:
     * tanpa izin dijawab 403 (audit `kegiatan.baca_ditolak`) dan klaim tidak
     * pernah dibaca, sehingga header `Location` tidak membocorkan penautan RA.
     */
    public function edit(Request $request, Kegiatan $kegiatan, AlihkanKegiatanLamaKeRencanaAksi $alihkan): RedirectResponse
    {
        return $alihkan->handle($request->user(), $kegiatan);
    }
}
