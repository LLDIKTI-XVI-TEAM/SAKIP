<?php

namespace App\Http\Controllers\Kegiatan;

use App\Actions\Kegiatan\SimpanKegiatan;
use App\Actions\Kegiatan\UbahStatusKegiatan;
use App\Http\Controllers\Controller;
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

        // Form RA mengirim identitas header; muat ulang form dengan token versi baru agar simpan
        // berikutnya tidak ditolak sebagai data basi. Tanpa header, tetap kembali ke pengirim.
        $rencanaAksiId = $data['rencana_aksi_id'] ?? null;
        if (is_string($rencanaAksiId) && $rencanaAksiId !== '') {
            return redirect()->route('rencana-aksi.kegiatan.edit', [
                'rencanaAksi' => strtolower($rencanaAksiId), 'kegiatan' => (string) $kegiatan->id,
            ])->with('success', 'Rencana kegiatan berhasil diperbarui.');
        }

        // Kembali ke form pengirim agar konteks Rencana Aksi tetap; fallback redirect ke beranda.
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
}
