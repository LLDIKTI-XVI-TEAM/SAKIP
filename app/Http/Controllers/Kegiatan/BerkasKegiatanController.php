<?php

namespace App\Http\Controllers\Kegiatan;

use App\Actions\Kegiatan\HapusBerkasKegiatan;
use App\Actions\Kegiatan\TambahBerkasKegiatan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kegiatan\DestroyBerkasKegiatanRequest;
use App\Http\Requests\Kegiatan\StoreBerkasKegiatanRequest;
use App\Models\Kegiatan;
use App\Services\Kegiatan\PersyaratanPerencanaanKegiatan;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BerkasKegiatanController extends Controller
{
    public function store(StoreBerkasKegiatanRequest $request, Kegiatan $kegiatan, TambahBerkasKegiatan $action): RedirectResponse
    {
        $action->handle($request->user(), (string) $kegiatan->id, $request->validated());

        return back()->with('success', 'Berkas perencanaan kegiatan berhasil disimpan.');
    }

    public function destroy(DestroyBerkasKegiatanRequest $request, Kegiatan $kegiatan, string $berkas, HapusBerkasKegiatan $action): RedirectResponse
    {
        $action->handle($request->user(), (string) $kegiatan->id, $berkas, (string) $request->validated('alasan'));

        return back()->with('success', 'Berkas perencanaan kegiatan berhasil dihapus.');
    }

    /** Anggaran dan RAB mengikuti akses detail kegiatan unit (Q42 §42.5, Q54); berkas lain dijawab 404. */
    public function download(Kegiatan $kegiatan, string $berkas): StreamedResponse
    {
        Gate::authorize('view', $kegiatan);
        $bukti = PersyaratanPerencanaanKegiatan::buktiBerlaku((string) $kegiatan->id)->whereKey($berkas)->where('mode', 'file')->first();
        abort_unless($bukti && is_string($bukti->path) && Storage::disk('local')->exists($bukti->path), 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        return $disk->download($bukti->path, $bukti->nama_asli ?? 'berkas', ['Cache-Control' => 'private, no-store']);
    }
}
