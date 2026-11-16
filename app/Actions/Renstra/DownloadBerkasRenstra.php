<?php

namespace App\Actions\Renstra;

use App\Models\Berkas;
use App\Models\Renstra;
use Illuminate\Support\Facades\Storage;

class DownloadBerkasRenstra
{
    /**
     * Pilih lampiran file milik Renstra ini pada private disk; lampiran asing atau berkas fisik hilang dijawab 404
     * dengan pesan existing. Otorisasi Renstra dan lampiran tetap milik adapter.
     *
     * @return array{path: string, name: string}
     */
    public function handle(Renstra $renstra, Berkas $berkas): array
    {
        if ($berkas->berkasable_type !== $renstra->getMorphClass() || $berkas->berkasable_id !== $renstra->id) {
            abort(404, 'Lampiran tidak terkait dengan Renstra ini.');
        }

        if ($berkas->mode !== 'file' || empty($berkas->path) || ! Storage::disk('local')->exists($berkas->path)) {
            abort(404, 'File lampiran fisik tidak ditemukan pada storage.');
        }

        return ['path' => $berkas->path, 'name' => $berkas->nama_asli ?? 'lampiran-renstra'];
    }
}
