<?php

namespace App\Actions\Renstra;

use App\Models\Renstra;
use App\Models\User;
use App\Services\RenstraService;
use Illuminate\Support\Arr;

class UpdateRenstra
{
    public function __construct(private readonly RenstraService $service) {}

    /**
     * Petakan input master/rujukan dan outcome tanpa menggandakan transaksi/audit milik Service.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(Renstra $renstra, array $data, User $actor): bool
    {
        $payload = Arr::only($data, [
            'kode', 'nama', 'tahun_mulai', 'tahun_selesai', 'tahun_akhir', 'deskripsi', 'keterangan',
            'dasar_hukum', 'regulasi_id', 'lampiran', 'alasan', 'nomor_kebijakan', 'tanggal_kebijakan', 'expected_state',
        ]);
        $result = $this->service->update($renstra, $payload, $actor);

        return $result->wasChanged() || ! empty($payload['lampiran']);
    }
}
