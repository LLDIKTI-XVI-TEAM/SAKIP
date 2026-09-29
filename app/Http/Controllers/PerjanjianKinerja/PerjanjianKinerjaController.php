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
use App\Models\Pengaturan;
use App\Models\Renstra;
use App\Models\RenstraPk;
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

        $filters = $request->validate([
            'renstra_id' => ['nullable', 'uuid', 'exists:renstras,id'],
            'tahun' => ['nullable', 'integer', 'between:1900,2100'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = RenstraPk::query()
            ->with([
                'renstra:id,kode,nama,tahun_mulai,tahun_selesai,is_aktif',
                'creator:id,nama',
                'jadwalTahunan:id,renstra_id,renstra_pk_id,tahun,status,activated_at',
            ])
            ->withCount('berkas')
            ->when(! empty($filters['renstra_id']), fn ($q) => $q->where('renstra_id', $filters['renstra_id']))
            ->when(! empty($filters['tahun']), fn ($q) => $q->where('tahun', $filters['tahun']))
            ->when(! empty($filters['q']), fn ($q) => $q->where('nomor_pk', 'ilike', '%'.$filters['q'].'%'))
            ->orderByDesc('tahun')
            ->orderByDesc('created_at');

        $perjanjianKinerja = $query->paginate(15)->withQueryString()->through(fn (RenstraPk $pk) => [
            'id' => $pk->id,
            'renstra_id' => $pk->renstra_id,
            'tahun' => $pk->tahun,
            'nomor_pk' => $pk->nomor_pk,
            'tanggal_pk' => $pk->tanggal_pk?->format('Y-m-d'),
            'created_at' => $pk->created_at?->toISOString(),
            'updated_at' => $pk->updated_at?->toISOString(),
            'berkas_count' => (int) ($pk->berkas_count ?? 0),
            'renstra' => $pk->renstra ? [
                'id' => $pk->renstra->id,
                'kode' => $pk->renstra->kode,
                'nama' => $pk->renstra->nama,
                'tahun_mulai' => $pk->renstra->tahun_mulai,
                'tahun_selesai' => $pk->renstra->tahun_selesai,
                'is_aktif' => (bool) $pk->renstra->is_aktif,
            ] : null,
            'creator' => $pk->creator ? [
                'id' => $pk->creator->id,
                'nama' => $pk->creator->nama,
            ] : null,
            'jadwal_tahunan' => $pk->jadwalTahunan ? [
                'id' => $pk->jadwalTahunan->id,
                'renstra_id' => $pk->jadwalTahunan->renstra_id,
                'renstra_pk_id' => $pk->jadwalTahunan->renstra_pk_id,
                'tahun' => $pk->jadwalTahunan->tahun,
                'status' => $pk->jadwalTahunan->status,
                'activated_at' => $pk->jadwalTahunan->activated_at,
                'is_terkunci' => $pk->jadwalTahunan->is_terkunci,
            ] : null,
        ]);

        $renstras = Renstra::orderByDesc('tahun_mulai')
            ->get(['id', 'kode', 'nama', 'tahun_mulai', 'tahun_selesai', 'is_aktif'])
            ->map(fn (Renstra $r) => [
                'id' => $r->id,
                'kode' => $r->kode,
                'nama' => $r->nama,
                'tahun_mulai' => $r->tahun_mulai,
                'tahun_selesai' => $r->tahun_selesai,
                'is_aktif' => (bool) $r->is_aktif,
            ])
            ->all();

        $user = $request->user();

        return Inertia::render('PerjanjianKinerja/Index', [
            'perjanjianKinerja' => $perjanjianKinerja,
            'renstras' => $renstras,
            'storageSettings' => $this->storageSettings(),
            'filters' => $request->only(['renstra_id', 'tahun', 'q']),
            'can' => [
                'create' => $user?->can('create', RenstraPk::class) ?? false,
                'update' => $user?->can('update', RenstraPk::class) ?? false,
                'upload_berkas' => $user?->can('uploadBerkas', RenstraPk::class) ?? false,
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

        $perjanjianKinerja->load([
            'renstra',
            'creator:id,nama',
            'berkas.pengunggah:id,nama',
            'jadwalTahunan',
        ]);

        $isJadwalAktif = $perjanjianKinerja->isJadwalAktif();
        $isJadwalTerkunci = $perjanjianKinerja->isJadwalTerkunci();
        $jadwalStatus = $perjanjianKinerja->jadwalStatus();
        $user = request()->user();
        $canReadBerkas = $user?->can('downloadBerkas', $perjanjianKinerja) ?? false;

        $pkData = [
            'id' => $perjanjianKinerja->id,
            'renstra_id' => $perjanjianKinerja->renstra_id,
            'tahun' => $perjanjianKinerja->tahun,
            'nomor_pk' => $perjanjianKinerja->nomor_pk,
            'tanggal_pk' => $perjanjianKinerja->tanggal_pk?->format('Y-m-d'),
            'created_at' => $perjanjianKinerja->created_at?->toISOString(),
            'updated_at' => $perjanjianKinerja->updated_at?->toISOString(),
            'renstra' => $perjanjianKinerja->renstra ? [
                'id' => $perjanjianKinerja->renstra->id,
                'kode' => $perjanjianKinerja->renstra->kode,
                'nama' => $perjanjianKinerja->renstra->nama,
                'tahun_mulai' => $perjanjianKinerja->renstra->tahun_mulai,
                'tahun_selesai' => $perjanjianKinerja->renstra->tahun_selesai,
                'is_aktif' => (bool) $perjanjianKinerja->renstra->is_aktif,
            ] : null,
            'creator' => $perjanjianKinerja->creator ? [
                'id' => $perjanjianKinerja->creator->id,
                'nama' => $perjanjianKinerja->creator->nama,
            ] : null,
            'jadwal_tahunan' => $perjanjianKinerja->jadwalTahunan ? [
                'id' => $perjanjianKinerja->jadwalTahunan->id,
                'renstra_id' => $perjanjianKinerja->jadwalTahunan->renstra_id,
                'renstra_pk_id' => $perjanjianKinerja->jadwalTahunan->renstra_pk_id,
                'tahun' => $perjanjianKinerja->jadwalTahunan->tahun,
                'status' => $perjanjianKinerja->jadwalTahunan->status,
                'activated_at' => $perjanjianKinerja->jadwalTahunan->activated_at,
                'is_terkunci' => $perjanjianKinerja->jadwalTahunan->is_terkunci,
            ] : null,
            'berkas' => $perjanjianKinerja->berkas->map(function (Berkas $b) use ($canReadBerkas) {
                $item = [
                    'id' => $b->id,
                    'mode' => $b->mode,
                    'nama_asli' => $b->nama_asli,
                    'mime' => $b->mime,
                    'ukuran_bytes' => $b->ukuran_bytes,
                    'created_at' => $b->dibuat_pada?->toISOString() ?? $b->created_at?->toISOString(),
                    'pengunggah' => $b->pengunggah ? [
                        'id' => $b->pengunggah->id,
                        'nama' => $b->pengunggah->nama,
                    ] : null,
                ];

                if ($canReadBerkas) {
                    $item['tautan'] = $b->tautan;
                    $item['isi_teks'] = $b->isi_teks;
                }

                return $item;
            })->values()->all(),
        ];

        return Inertia::render('PerjanjianKinerja/Show', [
            'pk' => $pkData,
            'jadwal_status' => $jadwalStatus,
            'is_jadwal_aktif' => $isJadwalAktif,
            'is_jadwal_terkunci' => $isJadwalTerkunci,
            'storageSettings' => $this->storageSettings(),
            'can' => [
                'update' => $user?->can('update', $perjanjianKinerja) ?? false,
                'delete_berkas' => ! $isJadwalTerkunci && ($user?->can('deleteBerkas', $perjanjianKinerja) ?? false),
                'read_berkas' => $canReadBerkas,
                'upload_berkas' => $user?->can('uploadBerkas', $perjanjianKinerja) ?? false,
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
