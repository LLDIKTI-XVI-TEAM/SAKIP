<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBuktiRencanaAksi extends Controller
{
    /**
     * Unduh terstream dari disk privat; bukti milik induk lain atau non-file
     * dijawab 404 agar keberadaannya tidak bocor.
     */
    public function __invoke(RencanaAksi $rencanaAksi, string $bukti): StreamedResponse
    {
        Gate::authorize('viewEvidence', $rencanaAksi);

        $berkas = $rencanaAksi->buktiDukungs()->current()->whereKey($bukti)->where('mode', 'file')->first();
        abort_unless($berkas && is_string($berkas->path) && Storage::disk('local')->exists($berkas->path), 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        return $disk->download($berkas->path, $berkas->nama_asli ?? 'bukti', ['Cache-Control' => 'private, no-store']);
    }
}
