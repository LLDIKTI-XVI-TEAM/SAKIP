<?php

namespace App\Actions\Renstra;

use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\User;

class EditRenstraForm
{
    /**
     * Baseline editor Renstra: data saat ini, token state, pilihan regulasi aktif plus regulasi terpilih (meski nonaktif),
     * dan capability unggah lampiran. Edit Renstra nonaktif/diarsipkan sudah ditolak Policy, sehingga capability ini
     * berlaku untuk draft maupun aktif (lampiran revisi).
     *
     * @return array<string, mixed>
     */
    public function handle(User $user, Renstra $renstra): array
    {
        $dapatBacaRegulasi = $user->can('viewAny', Regulasi::class);

        if ($dapatBacaRegulasi) {
            $renstra->load('regulasi');
            $regulasiPilihan = Regulasi::query()
                ->where('aktif', true)
                ->orWhere('id', $renstra->regulasi_id)
                ->orderBy('tahun', 'desc')
                ->orderBy('nomor')
                ->get(['id', 'jenis', 'nomor', 'tahun', 'tentang']);
        } else {
            $renstra->unsetRelation('regulasi');
            $regulasiPilihan = [];
        }

        $canUploadAttachment = $user->can('uploadAttachment', $renstra);

        return [
            'renstra' => [
                'id' => $renstra->id,
                'kode' => $renstra->kode,
                'nama' => $renstra->nama,
                'tahun_mulai' => $renstra->tahun_mulai,
                'tahun_selesai' => $renstra->tahun_selesai,
                'status' => $renstra->status,
                'is_aktif' => $renstra->is_aktif,
                'deskripsi' => $renstra->deskripsi,
                'dasar_hukum' => $renstra->dasar_hukum,
                'regulasi_id' => $dapatBacaRegulasi ? $renstra->regulasi_id : null,
                'regulasi' => $dapatBacaRegulasi && $renstra->regulasi ? $renstra->regulasi->only(['id', 'jenis', 'nomor', 'tahun', 'tentang']) : null,
                'pembuat' => null,
                'created_at' => $renstra->created_at?->toISOString(),
                'updated_at' => $renstra->updated_at?->toISOString(),
            ],
            'expected_state' => $renstra->stateToken(),
            'regulasiPilihan' => $regulasiPilihan,
            'can' => [
                'uploadAttachment' => $canUploadAttachment,
                'readRegulasi' => $dapatBacaRegulasi,
            ],
        ];
    }
}
