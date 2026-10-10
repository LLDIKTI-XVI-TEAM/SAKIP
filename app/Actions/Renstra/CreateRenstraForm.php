<?php

namespace App\Actions\Renstra;

use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\User;

class CreateRenstraForm
{
    /**
     * Pilihan regulasi aktif dan capability unggah untuk formulir Renstra baru; daftar regulasi kosong bila aktor tidak boleh membacanya.
     *
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $dapatBacaRegulasi = $user->can('viewAny', Regulasi::class);

        $regulasiPilihan = $dapatBacaRegulasi
            ? Regulasi::query()
                ->where('aktif', true)
                ->orderBy('tahun', 'desc')
                ->orderBy('nomor')
                ->get(['id', 'jenis', 'nomor', 'tahun', 'tentang'])
            : [];

        return [
            'regulasiPilihan' => $regulasiPilihan,
            'can' => [
                'uploadAttachment' => $user->can('uploadAttachment', Renstra::class),
                'readRegulasi' => $dapatBacaRegulasi,
            ],
        ];
    }
}
