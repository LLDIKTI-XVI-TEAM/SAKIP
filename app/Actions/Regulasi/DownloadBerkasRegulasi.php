<?php

namespace App\Actions\Regulasi;

use App\Models\Berkas;
use App\Models\Regulasi;
use Illuminate\Support\Facades\Storage;

class DownloadBerkasRegulasi
{
    /**
     * Pilih lampiran file milik regulasi ini pada private disk; lampiran asing, nonfile, atau tanpa berkas fisik dijawab 404
     * agar keberadaan lampiran regulasi lain tidak bocor. Otorisasi induk tetap milik adapter.
     *
     * @return array{path: string, name: string}
     */
    public function handle(Regulasi $regulasi, Berkas $berkas): array
    {
        abort_unless(
            $berkas->berkasable_type === $regulasi->getMorphClass()
                && $berkas->berkasable_id === $regulasi->id
                && $berkas->mode === 'file'
                && is_string($berkas->path),
            404,
        );

        abort_unless(Storage::disk('local')->exists($berkas->path), 404);

        return ['path' => $berkas->path, 'name' => $berkas->nama_asli ?: 'lampiran-regulasi'];
    }
}
