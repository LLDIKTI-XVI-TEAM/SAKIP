<?php

namespace App\Actions\PerjanjianKinerja;

use App\Models\Berkas;
use App\Models\RenstraPk;
use App\Models\RenstraPkVersi;
use Illuminate\Support\Facades\Storage;

class DownloadBerkasPerjanjianKinerja
{
    /**
     * Pilih lampiran file milik PK ini pada private disk: lampiran induk PK (PK awal; morph legacy `renstra_pk` maupun
     * FQCN diterima) atau lampiran versi revisi milik PK ini (PK akhir). Lampiran asing, nonfile, atau tanpa berkas
     * fisik dijawab 404. Otorisasi unduh tetap milik adapter.
     *
     * @return array{path: string, name: string|null}
     */
    public function handle(RenstraPk $perjanjianKinerja, Berkas $berkas): array
    {
        abort_unless(
            ($berkas->berkasable_id === $perjanjianKinerja->id && in_array($berkas->berkasable_type, ['renstra_pk', RenstraPk::class], true))
                || ($berkas->berkasable_type === 'renstra_pk_versi'
                    && RenstraPkVersi::whereKey($berkas->berkasable_id)->where('renstra_pk_id', $perjanjianKinerja->id)->exists()),
            404,
        );

        abort_unless(
            $berkas->mode === 'file'
                && is_string($berkas->path)
                && Storage::disk('local')->exists($berkas->path),
            404,
        );

        return ['path' => $berkas->path, 'name' => $berkas->nama_asli];
    }
}
