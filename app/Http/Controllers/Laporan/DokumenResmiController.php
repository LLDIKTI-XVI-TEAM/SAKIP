<?php

namespace App\Http\Controllers\Laporan;

use App\Actions\Laporan\TampilkanDokumenResmi;
use App\Actions\Laporan\UnduhArsipDokumenResmi;
use App\Actions\Laporan\UnggahArsipDokumenResmi;
use App\Http\Requests\Laporan\UnggahArsipDokumenResmiRequest;
use App\Services\Laporan\ArsipDokumenResmi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DokumenResmiController
{
    public function index(Request $request, TampilkanDokumenResmi $action): Response
    {
        app(ArsipDokumenResmi::class)->izinkan($request->user(), 'laporan:ekspor');
        $filter = $request->validate(['jadwal_id' => ['nullable', 'uuid'], 'periode_id' => ['nullable', 'uuid'], 'jenis' => ['nullable', 'in:ra,triwulan'], 'page' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('Laporan/DokumenResmi', $action->handle($request->user(), $filter));
    }

    public function store(UnggahArsipDokumenResmiRequest $request, UnggahArsipDokumenResmi $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());

        return back()->with('success', 'Arsip dokumen resmi berhasil diunggah.');
    }

    public function download(Request $request, string $berkas, UnduhArsipDokumenResmi $action): StreamedResponse
    {
        return $action->handle($request->user(), $berkas);
    }
}
