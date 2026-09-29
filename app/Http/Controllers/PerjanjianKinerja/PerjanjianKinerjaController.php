<?php

namespace App\Http\Controllers\PerjanjianKinerja;

use App\Actions\PerjanjianKinerja\CreatePerjanjianKinerja;
use App\Actions\PerjanjianKinerja\DeleteBerkasPerjanjianKinerja;
use App\Actions\PerjanjianKinerja\UpdatePerjanjianKinerja;
use App\Http\Controllers\Controller;
use App\Http\Requests\PerjanjianKinerja\DestroyBerkasPerjanjianKinerjaRequest;
use App\Http\Requests\PerjanjianKinerja\StorePerjanjianKinerjaRequest;
use App\Http\Requests\PerjanjianKinerja\UpdatePerjanjianKinerjaRequest;
use App\Models\Berkas;
use App\Models\RenstraPk;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaQueryService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerjanjianKinerjaController extends Controller
{
    public function __construct(
        protected PerjanjianKinerjaQueryService $queryService,
    ) {}

    /**
     * Menampilkan daftar Perjanjian Kinerja tahunan.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', RenstraPk::class);

        $filters = $request->validate([
            'renstra_id' => ['nullable', 'uuid', 'exists:renstras,id'],
            'tahun' => ['nullable', 'integer', 'between:1900,2100'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return Inertia::render('PerjanjianKinerja/Index', [
            'perjanjianKinerja' => $this->queryService->paginateIndex($filters),
            'renstras' => $this->queryService->getRenstraOptions(),
            'storageSettings' => $this->queryService->getStorageSettings(),
            'filters' => $request->only(['renstra_id', 'tahun', 'q']),
            'can' => $this->queryService->resolveIndexCapabilities($request->user()),
        ]);
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
    public function show(RenstraPk $perjanjianKinerja): Response
    {
        Gate::authorize('view', $perjanjianKinerja);

        $user = request()->user();
        $detail = $this->queryService->presentDetail($perjanjianKinerja, $user);

        return Inertia::render('PerjanjianKinerja/Show', [
            'pk' => $detail['pk'],
            'jadwal_status' => $detail['jadwal_status'],
            'is_jadwal_aktif' => $detail['is_jadwal_aktif'],
            'is_jadwal_terkunci' => $detail['is_jadwal_terkunci'],
            'storageSettings' => $this->queryService->getStorageSettings(),
            'can' => $this->queryService->resolveShowCapabilities($user, $perjanjianKinerja, $detail['is_jadwal_terkunci']),
        ]);
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
    public function downloadBerkas(RenstraPk $perjanjianKinerja, Berkas $berkas): StreamedResponse
    {
        Gate::authorize('downloadBerkas', $perjanjianKinerja);

        abort_unless(
            $berkas->berkasable_id === $perjanjianKinerja->id
                && in_array($berkas->berkasable_type, ['renstra_pk', RenstraPk::class], true),
            404,
        );

        abort_unless(
            $berkas->mode === 'file'
                && is_string($berkas->path)
                && Storage::disk('local')->exists($berkas->path),
            404,
        );

        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk('local');

        return $storage->download($berkas->path, $berkas->nama_asli);
    }
}
