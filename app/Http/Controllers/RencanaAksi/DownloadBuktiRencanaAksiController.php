<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Http\Controllers\Controller;
use App\Models\Berkas;
use App\Models\RencanaAksi;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBuktiRencanaAksiController extends Controller
{
    /**
     * Mengunduh berkas fisik bukti dukung Rencana Aksi dari private storage.
     */
    public function __invoke(RencanaAksi $rencanaAksi, Berkas $bukti): StreamedResponse
    {
        Gate::authorize('downloadEvidence', $rencanaAksi);

        abort_unless(
            $bukti->berkasable_id === $rencanaAksi->id
                && in_array($bukti->berkasable_type, ['rencana_aksi', RencanaAksi::class], true),
            404,
            'Berkas bukan merupakan lampiran bukti dukung dari Rencana Aksi ini.'
        );

        abort_unless(
            $bukti->mode === 'file'
                && is_string($bukti->path)
                && Storage::disk('local')->exists($bukti->path),
            404,
            'File bukti fisik tidak ditemukan di penyimpanan privat.'
        );

        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk('local');

        return $storage->download($bukti->path, $bukti->nama_asli ?? 'bukti.dat');
    }
}
