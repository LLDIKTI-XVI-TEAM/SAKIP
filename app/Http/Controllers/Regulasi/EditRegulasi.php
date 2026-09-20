<?php

namespace App\Http\Controllers\Regulasi;

use App\Http\Controllers\Controller;
use App\Models\Berkas;
use App\Models\Regulasi;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EditRegulasi extends Controller
{
    public function __invoke(Regulasi $regulasi): Response
    {
        Gate::authorize('update', $regulasi);
        $regulasi->load('berkas');

        return Inertia::render('Regulasi/Edit', [
            'regulasi' => [
                'id' => $regulasi->id,
                'jenis' => $regulasi->jenis,
                'nomor' => $regulasi->nomor,
                'tahun' => $regulasi->tahun,
                'tentang' => $regulasi->tentang,
                'tanggal' => $regulasi->tanggal?->format('Y-m-d'),
                'tautan_sumber' => $regulasi->tautan_sumber,
                'catatan' => $regulasi->catatan,
                'aktif' => $regulasi->aktif,
                'berkas' => $regulasi->berkas->map(fn (Berkas $berkas) => [
                    'id' => $berkas->id,
                    'mode' => $berkas->mode,
                    'nama_asli' => $berkas->nama_asli,
                    'mime' => $berkas->mime,
                    'ukuran_bytes' => $berkas->ukuran_bytes,
                    'tautan' => $berkas->tautan,
                    'isi_teks' => $berkas->isi_teks,
                    'download_url' => $berkas->mode === 'file'
                        ? route('regulasi.berkas.download', [$regulasi, $berkas])
                        : null,
                ])->values(),
            ],
        ]);
    }
}
