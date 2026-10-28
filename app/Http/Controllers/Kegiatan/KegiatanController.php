<?php

namespace App\Http\Controllers\Kegiatan;

use App\Actions\Kegiatan\CatatRealisasiKegiatan;
use App\Actions\Kegiatan\SimpanKegiatan;
use App\Actions\Kegiatan\UbahStatusKegiatan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kegiatan\CatatRealisasiKegiatanRequest;
use App\Http\Requests\Kegiatan\SimpanKegiatanRequest;
use App\Http\Requests\Kegiatan\UbahStatusKegiatanRequest;
use App\Models\Kegiatan;
use Illuminate\Http\RedirectResponse;

class KegiatanController extends Controller
{
    /** URL ubah lama dipertahankan; form dikirim dari halaman kegiatan Rencana Aksi. */
    public function update(SimpanKegiatanRequest $request, Kegiatan $kegiatan, SimpanKegiatan $action): RedirectResponse
    {
        $data = $request->validated();
        $action->handle($request->user(), $data, (string) $kegiatan->id);

        // Form pengirim memakai `preserveState: 'errors'`: sukses mereset token form, konflik mempertahankan
        // draf dan pesan galat, sehingga tidak perlu memuat ulang form dengan token versi baru di sini.
        return back()->with('success', 'Rencana kegiatan berhasil diperbarui.');
    }

    public function ubahStatus(UbahStatusKegiatanRequest $request, Kegiatan $kegiatan, UbahStatusKegiatan $action): RedirectResponse
    {
        $hasil = $action->handle($request->user(), $kegiatan, $request->validated());

        // Kembali ke halaman asal aksi (daftar berfilter atau form) agar konteks pengguna tetap.
        return back()->with('success', match ($hasil['status']) {
            'ditunda' => "Kegiatan ditunda ke {$hasil['periode_tujuan']}; kegiatan lanjutan dibuat.",
            'tidak_terlaksana' => 'Kegiatan ditandai tidak terlaksana.',
            default => 'Kegiatan dibatalkan.',
        });
    }

    public function realisasi(CatatRealisasiKegiatanRequest $request, Kegiatan $kegiatan, CatatRealisasiKegiatan $action): RedirectResponse
    {
        $hasil = $action->handle($request->user(), $kegiatan, $request->validated());

        return back()->with('success', $hasil['terlaksana'] ? 'Kegiatan ditandai terlaksana.' : 'Realisasi kegiatan disimpan.');
    }
}
