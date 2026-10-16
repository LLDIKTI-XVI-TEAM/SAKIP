<?php

namespace App\Http\Requests\Rekomendasi;

use App\Http\Requests\PeriodeJadwalMutationRequest;
use App\Models\RekomendasiPimpinan;
use App\Support\PermissionCodes;
use Illuminate\Validation\Rule;

/** Penyusun, penyetuju, peran, dan waktu ditentukan server; klien hanya mengirim isi, target status, token draf, dan rekomendasi berlaku yang dilihat. */
class TetapkanRekomendasiRequest extends PeriodeJadwalMutationRequest
{
    public function permissionCode(): string
    {
        return PermissionCodes::REKOMENDASI_TETAPKAN;
    }

    public function auditEvent(): string
    {
        return 'rekomendasi_pimpinan.tetapkan';
    }

    public function rules(): array
    {
        return [
            'indikator_id' => ['required', 'uuid'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'periode_id' => ['required', 'uuid'],
            'status' => ['required', Rule::in([RekomendasiPimpinan::STATUS_DRAF, RekomendasiPimpinan::STATUS_DISETUJUI])],
            'isi' => ['required', 'string', 'max:10000'],
            'rujukan_sumber' => ['nullable', 'string', 'max:1000'],
            'versi_draf' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'berlaku_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'isi.max' => ':attribute maksimal :max karakter.', 'rujukan_sumber.max' => ':attribute maksimal :max karakter.'];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'indikator_id' => 'Indikator', 'periode_id' => 'Periode', 'status' => 'Status rekomendasi',
            'isi' => 'Isi rekomendasi', 'rujukan_sumber' => 'Rujukan sumber', 'versi_draf' => 'Versi draf', 'berlaku_id' => 'Rekomendasi berlaku'];
    }
}
