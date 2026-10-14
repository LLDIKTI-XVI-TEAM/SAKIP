<?php

namespace App\Http\Controllers\Kegiatan;

use App\Actions\Kegiatan\DaftarKegiatan;
use App\Actions\Kegiatan\FormKegiatan;
use App\Actions\Kegiatan\SimpanKegiatan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kegiatan\DaftarKegiatanRequest;
use App\Http\Requests\Kegiatan\SimpanKegiatanRequest;
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

    public function create(Request $request, FormKegiatan $action): Response
    {
        return Inertia::render('Kegiatan/Form', $action->handle($request->user()));
    }

    public function edit(Request $request, Kegiatan $kegiatan, FormKegiatan $action): Response
    {
        return Inertia::render('Kegiatan/Form', $action->handle($request->user(), $kegiatan));
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
}
