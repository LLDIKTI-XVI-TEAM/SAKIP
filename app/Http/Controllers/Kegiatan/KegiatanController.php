<?php

namespace App\Http\Controllers\Kegiatan;

use App\Actions\Kegiatan\DaftarBerkasPerencanaanKegiatan;
use App\Actions\Kegiatan\DaftarKegiatan;
use App\Actions\Kegiatan\DaftarKlaimKegiatan;
use App\Actions\Kegiatan\FormKegiatan;
use App\Actions\Kegiatan\SimpanKegiatan;
use App\Actions\Kegiatan\UbahStatusKegiatan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kegiatan\DaftarKegiatanRequest;
use App\Http\Requests\Kegiatan\SimpanKegiatanRequest;
use App\Http\Requests\Kegiatan\UbahStatusKegiatanRequest;
use App\Models\Kegiatan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KegiatanController extends Controller
{
    public function index(DaftarKegiatanRequest $request, DaftarKegiatan $action): Response
    {
        return Inertia::render('Kegiatan/Index', $action->handle($request->user(), $request->validated()));
    }

    public function edit(Request $request, Kegiatan $kegiatan, FormKegiatan $action, DaftarKlaimKegiatan $klaim, DaftarBerkasPerencanaanKegiatan $berkas): Response
    {
        // Form dievaluasi lebih dulu agar penolakan akses tetap dijawab FormKegiatan.
        $props = $action->handle($request->user(), $kegiatan);

        return Inertia::render('Kegiatan/Form', $props + [
            'klaim' => $klaim->handle($request->user(), $kegiatan),
            'berkasPerencanaan' => $berkas->handle($request->user(), $kegiatan),
        ]);
    }

    public function store(SimpanKegiatanRequest $request, SimpanKegiatan $action): RedirectResponse
    {
        $kegiatan = $action->handle($request->user(), $request->validated());

        // Kembali ke tahun kegiatan agar hasil simpan tetap terlihat di daftar.
        return redirect()->route('kegiatan.index', ['tahun' => $kegiatan->tahun])->with('success', 'Rencana kegiatan berhasil dicatat.');
    }

    public function update(SimpanKegiatanRequest $request, Kegiatan $kegiatan, SimpanKegiatan $action): RedirectResponse
    {
        $kegiatan = $action->handle($request->user(), $request->validated(), (string) $kegiatan->id);

        return redirect()->route('kegiatan.index', ['tahun' => $kegiatan->tahun])->with('success', 'Rencana kegiatan berhasil diperbarui.');
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
