<?php

namespace App\Http\Controllers\JenisBerkas;

use App\Actions\JenisBerkas\CreateJenisBerkasAction;
use App\Actions\JenisBerkas\DeleteJenisBerkasAction;
use App\Actions\JenisBerkas\GetJenisBerkasPage;
use App\Actions\JenisBerkas\HapusContohBerkasAction;
use App\Actions\JenisBerkas\UnggahContohBerkasAction;
use App\Actions\JenisBerkas\UpdateJenisBerkasAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteJenisBerkasRequest;
use App\Http\Requests\DestroyContohBerkasRequest;
use App\Http\Requests\StoreContohBerkasRequest;
use App\Http\Requests\StoreJenisBerkasRequest;
use App\Http\Requests\UpdateJenisBerkasRequest;
use App\Models\BuktiDukung;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JenisBerkasController extends Controller
{
    public function index(Request $request, GetJenisBerkasPage $action): Response
    {
        return Inertia::render('JenisBerkas/Index', $action->handle($request->user()));
    }

    public function store(StoreJenisBerkasRequest $request, CreateJenisBerkasAction $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());

        return redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil ditambahkan.');
    }

    public function update(UpdateJenisBerkasRequest $request, string $id, UpdateJenisBerkasAction $action): RedirectResponse
    {
        $action->handle($request->user(), $id, $request->validated());

        return redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil diperbarui.');
    }

    public function destroy(DeleteJenisBerkasRequest $request, string $id, DeleteJenisBerkasAction $action): RedirectResponse
    {
        $action->handle($request->user(), $id, $request->validated());
        $redirect = redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil dihapus.');

        return $redirect;
    }

    public function storeContoh(StoreContohBerkasRequest $request, string $id, UnggahContohBerkasAction $action): RedirectResponse
    {
        $action->handle($request->user(), $id, $request->validated());

        return back()->with('success', 'Contoh berkas berhasil disimpan.');
    }

    public function destroyContoh(DestroyContohBerkasRequest $request, string $id, string $contoh, HapusContohBerkasAction $action): RedirectResponse
    {
        $action->handle($request->user(), $id, $contoh, (string) $request->validated('alasan'));

        return back()->with('success', 'Contoh berkas berhasil dihapus.');
    }

    /** Contoh berkas generik dapat diunduh pemegang `jenis_berkas:read` lewat disk privat (Q44 §44.1 butir 4). */
    public function downloadContoh(string $id, string $contoh): StreamedResponse
    {
        Gate::authorize('jenis_berkas:read');
        $berkas = BuktiDukung::where('berkasable_type', 'jenis_berkas')->where('berkasable_id', $id)->current()->whereKey($contoh)->where('mode', 'file')->first();
        abort_unless($berkas && is_string($berkas->path) && Storage::disk('local')->exists($berkas->path), 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        return $disk->download($berkas->path, $berkas->nama_asli ?? 'contoh', ['Cache-Control' => 'private, no-store']);
    }
}
