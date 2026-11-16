<?php

namespace App\Actions\Regulasi;

use App\Models\Berkas;
use App\Models\Regulasi;

class PresentRegulasi
{
    /**
     * Detail regulasi beserta lampiran untuk halaman baca dan editor; editor menambahkan `versi` sebagai token optimistic lock.
     * Tautan unduh hanya untuk lampiran mode file; isi teks/tautan dikirim apa adanya.
     *
     * @return array<string, mixed>
     */
    public function handle(Regulasi $regulasi, bool $editor = false): array
    {
        $regulasi->load('berkas');
        $detail = [
            'id' => $regulasi->id,
            'jenis' => $regulasi->jenis,
            'nomor' => $regulasi->nomor,
            'tahun' => $regulasi->tahun,
            'tentang' => $regulasi->tentang,
            'tanggal' => $regulasi->tanggal?->format('Y-m-d'),
            'tautan_sumber' => $regulasi->tautan_sumber,
            'catatan' => $regulasi->catatan,
            'aktif' => $regulasi->aktif,
        ];
        if ($editor) {
            $detail['versi'] = $regulasi->versi;
        }
        $detail['berkas'] = $regulasi->berkas->map(fn (Berkas $berkas) => [
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
        ])->values();

        return ['regulasi' => $detail];
    }
}
