<?php

namespace App\Http\Controllers\Kegiatan;

use App\Actions\Kegiatan\HapusKlaimKegiatan;
use App\Actions\Kegiatan\TambahKlaimKegiatan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kegiatan\HapusKlaimKegiatanRequest;
use App\Http\Requests\Kegiatan\TambahKlaimKegiatanRequest;
use App\Models\Kegiatan;
use Illuminate\Http\RedirectResponse;

class KlaimKegiatanController extends Controller
{
    public function store(TambahKlaimKegiatanRequest $request, Kegiatan $kegiatan, TambahKlaimKegiatan $action): RedirectResponse
    {
        $action->handle($request->user(), (string) $kegiatan->id, $request->validated());

        return back()->with('success', 'Klaim kegiatan berhasil ditambahkan.');
    }

    public function destroy(HapusKlaimKegiatanRequest $request, Kegiatan $kegiatan, string $klaim, HapusKlaimKegiatan $action): RedirectResponse
    {
        $action->handle($request->user(), (string) $kegiatan->id, $klaim, (string) $request->validated('alasan'));

        return back()->with('success', 'Klaim kegiatan berhasil dihapus.');
    }
}
