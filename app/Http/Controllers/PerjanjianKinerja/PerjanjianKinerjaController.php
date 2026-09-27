<?php

namespace App\Http\Controllers\PerjanjianKinerja;

use App\Http\Controllers\Controller;
use App\Http\Requests\PerjanjianKinerja\DestroyBerkasPerjanjianKinerjaRequest;
use App\Http\Requests\PerjanjianKinerja\StorePerjanjianKinerjaRequest;
use App\Http\Requests\PerjanjianKinerja\UpdatePerjanjianKinerjaRequest;
use App\Models\Berkas;
use App\Models\Pengaturan;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Services\RenstraPkService;
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
    /**
     * Menampilkan daftar Perjanjian Kinerja tahunan.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', RenstraPk::class);

        $query = RenstraPk::query()
            ->with([
                'renstra:id,kode,nama,tahun_mulai,tahun_selesai,is_aktif',
                'creator:id,nama',
                'berkas',
                'jadwalTahunan:id,renstra_pk_id,tahun,status',
            ])
            ->when($request->filled('renstra_id'), fn ($q) => $q->where('renstra_id', $request->string('renstra_id')))
            ->when($request->filled('tahun'), fn ($q) => $q->where('tahun', (int) $request->input('tahun')))
            ->when($request->filled('q'), fn ($q) => $q->where('nomor_pk', 'ilike', '%'.$request->string('q').'%'))
            ->orderByDesc('tahun')
            ->orderByDesc('created_at');

        $perjanjianKinerja = $query->paginate(15)->withQueryString();
        $renstras = Renstra::orderByDesc('tahun_mulai')->get(['id', 'kode', 'nama', 'tahun_mulai', 'tahun_selesai', 'is_aktif']);

        $user = $request->user();

        return Inertia::render('PerjanjianKinerja/Index', [
            'perjanjianKinerja' => $perjanjianKinerja,
            'renstras' => $renstras,
            'storageSettings' => $this->storageSettings(),
            'filters' => $request->only(['renstra_id', 'tahun', 'q']),
            'can' => [
                'create' => $user?->can('create', RenstraPk::class) ?? false,
                'update' => $user?->can('update', RenstraPk::class) ?? false,
            ],
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
    public function store(StorePerjanjianKinerjaRequest $request, RenstraPkService $service): RedirectResponse
    {
        $pk = $service->create($request->validated(), $request->user());

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

        $perjanjianKinerja->load([
            'renstra',
            'creator:id,nama',
            'berkas.pengunggah:id,nama',
            'jadwalTahunan',
        ]);

        $isJadwalAktif = $perjanjianKinerja->isJadwalAktif();
        $user = request()->user();

        return Inertia::render('PerjanjianKinerja/Show', [
            'pk' => $perjanjianKinerja,
            'is_jadwal_aktif' => $isJadwalAktif,
            'storageSettings' => $this->storageSettings(),
            'can' => [
                'update' => $user?->can('update', $perjanjianKinerja) ?? false,
                'delete_berkas' => ! $isJadwalAktif && ($user?->can('deleteBerkas', $perjanjianKinerja) ?? false),
            ],
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
        RenstraPkService $service,
    ): RedirectResponse {
        $service->update(
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
        RenstraPkService $service,
    ): RedirectResponse {
        $service->deleteBerkas(
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
        Gate::authorize('view', $perjanjianKinerja);

        abort_unless(
            $berkas->berkasable_id === $perjanjianKinerja->id
                && in_array($berkas->berkasable_type, ['renstra_pk', $perjanjianKinerja->getMorphClass()], true),
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

    /**
     * @return array{unggahan_aktif: bool, ukuran_maks_kb: int, format_diizinkan: string}
     */
    protected function storageSettings(): array
    {
        $settings = Pengaturan::whereIn('kunci', [
            'berkas.unggahan_aktif',
            'berkas.ukuran_maks_kb',
            'berkas.format_diizinkan',
        ])->pluck('nilai', 'kunci');

        return [
            'unggahan_aktif' => filter_var($settings->get('berkas.unggahan_aktif') ?? true, FILTER_VALIDATE_BOOLEAN),
            'ukuran_maks_kb' => (int) $settings->get('berkas.ukuran_maks_kb', 10240),
            'format_diizinkan' => (string) $settings->get('berkas.format_diizinkan', 'pdf,docx,xlsx,jpg,jpeg,png'),
        ];
    }
}
