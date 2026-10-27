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
     * Kegiatan tanpa klaim diarahkan ke daftar Rencana Aksi dengan penjelasan.
     */
    public function edit(Request $request, Kegiatan $kegiatan, AlihkanKegiatanLamaKeRencanaAksi $alihkan): RedirectResponse
    {
        $rencanaAksiId = $alihkan->handle($request->user(), $kegiatan);

        if ($rencanaAksiId === null) {
            return redirect()->route('rencana-aksi.index')->with('message', 'Kegiatan belum tertaut ke Rencana Aksi.');
        }

        // Anchor baris mengikuti penanda daftar kegiatan agar pengguna langsung melihat kegiatannya.
        return redirect()->to(route('rencana-aksi.kegiatan.index', ['rencanaAksi' => $rencanaAksiId]).'#kegiatan-'.$kegiatan->id);
    }
}
