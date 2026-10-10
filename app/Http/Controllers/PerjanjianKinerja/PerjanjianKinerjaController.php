<?php

namespace App\Http\Controllers\PerjanjianKinerja;

use App\Actions\PerjanjianKinerja\CreatePerjanjianKinerja;
use App\Actions\PerjanjianKinerja\DeleteBerkasPerjanjianKinerja;
use App\Actions\PerjanjianKinerja\DownloadBerkasPerjanjianKinerja;
use App\Actions\PerjanjianKinerja\IndexPerjanjianKinerja;
use App\Actions\PerjanjianKinerja\ShowPerjanjianKinerja;
use App\Actions\PerjanjianKinerja\UpdatePerjanjianKinerja;
use App\Http\Controllers\Controller;
use App\Http\Requests\PerjanjianKinerja\DestroyBerkasPerjanjianKinerjaRequest;
use App\Http\Requests\PerjanjianKinerja\StorePerjanjianKinerjaRequest;
use App\Http\Requests\PerjanjianKinerja\UpdatePerjanjianKinerjaRequest;
use App\Models\Berkas;
use App\Models\RenstraPk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerjanjianKinerjaController extends Controller
{
    /**
     * Menampilkan daftar Perjanjian Kinerja tahunan.
     */
    public function index(Request $request, IndexPerjanjianKinerja $action): Response
    {
        Gate::authorize('viewAny', RenstraPk::class);

        $filters = $request->validate([
            'renstra_id' => ['nullable', 'uuid', 'exists:renstras,id'],
            'tahun' => ['nullable', 'integer', 'between:1900,2100'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return Inertia::render('PerjanjianKinerja/Index', $action->handle($request->user(), $filters, $request->only(['renstra_id', 'tahun', 'q'])));
    }

    /**
     * Menampilkan formulir pencatatan Perjanjian Kinerja baru.
     */
    public function create(): RedirectResponse
    {
        Gate::authorize('create', RenstraPk::class);

        return redirect()->route('perjanjian-kinerja.index');
    }

    /**
     * Menyimpan data Perjanjian Kinerja baru beserta lampiran.
     */
    public function store(StorePerjanjianKinerjaRequest $request, CreatePerjanjianKinerja $action): RedirectResponse
    {
        $pk = $action->handle($request->validated(), $request->user());

        return redirect()
            ->route('perjanjian-kinerja.show', $pk)
            ->with('success', 'Perjanjian Kinerja berhasil dicatat.');
    }

    /**
     * Menampilkan detail Perjanjian Kinerja dan daftar lampiran legalnya.
     */
    public function show(Request $request, RenstraPk $perjanjianKinerja, ShowPerjanjianKinerja $action): Response
    {
        Gate::authorize('view', $perjanjianKinerja);

        return Inertia::render('PerjanjianKinerja/Show', $action->handle($request->user(), $perjanjianKinerja));
    }

    /**
     * Menampilkan formulir pengubahan data Perjanjian Kinerja.
     */
    public function edit(RenstraPk $perjanjianKinerja): RedirectResponse
    {
        Gate::authorize('update', $perjanjianKinerja);

        return redirect()->route('perjanjian-kinerja.show', $perjanjianKinerja);
    }

    /**
     * Memperbarui metadata dan lampiran Perjanjian Kinerja.
     */
    public function update(
        UpdatePerjanjianKinerjaRequest $request,
        RenstraPk $perjanjianKinerja,
        UpdatePerjanjianKinerja $action,
    ): RedirectResponse {
        $action->handle(
            $perjanjianKinerja,
            $request->validated(),
            $request->validated('alasan'),
            $request->user(),
        );

        return redirect()
            ->route('perjanjian-kinerja.show', $perjanjianKinerja)
            ->with('success', 'Perjanjian Kinerja berhasil diperbarui.');
    }

    /**
     * Menghapus lampiran berkas Perjanjian Kinerja dengan validasi guard imutabilitas.
     */
    public function destroyBerkas(
        DestroyBerkasPerjanjianKinerjaRequest $request,
        RenstraPk $perjanjianKinerja,
        Berkas $berkas,
        DeleteBerkasPerjanjianKinerja $action,
    ): RedirectResponse {
        $action->handle(
            $perjanjianKinerja,
            $berkas,
            $request->validated('alasan'),
            $request->user(),
        );

        return back()->with('success', 'Lampiran Perjanjian Kinerja berhasil dihapus.');
    }

    /**
     * Mengunduh file lampiran dokumen Perjanjian Kinerja dari private storage.
     */
    public function downloadBerkas(RenstraPk $perjanjianKinerja, Berkas $berkas, DownloadBerkasPerjanjianKinerja $action): StreamedResponse
    {
        Gate::authorize('downloadBerkas', $perjanjianKinerja);
        $file = $action->handle($perjanjianKinerja, $berkas);

        return Storage::disk('local')->download($file['path'], $file['name']);
    }
}
