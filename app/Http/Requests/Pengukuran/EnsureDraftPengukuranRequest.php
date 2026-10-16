<?php

namespace App\Http\Requests\Pengukuran;

use Illuminate\Foundation\Http\FormRequest;

class EnsureDraftPengukuranRequest extends FormRequest
{
    /** Replay header existing cukup izin baca; izin create diputuskan Action pada cabang pembentukan. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['jadwal_snapshot_id' => ['required', 'uuid'], 'periode_id' => ['required', 'uuid'],
            'indikator_id' => ['prohibited'], 'tahun' => ['prohibited'], 'unit_id' => ['prohibited'], 'sumber_nilai' => ['prohibited'],
            'status_alur' => ['prohibited'], 'status_perhitungan' => ['prohibited'], 'nilai' => ['prohibited'], 'versi' => ['prohibited'], 'created_by' => ['prohibited']];
    }

    public function messages(): array
    {
        return ['required' => 'Kolom :attribute wajib diisi.', 'uuid' => 'Identitas :attribute tidak sah.', 'prohibited' => 'Kolom :attribute ditentukan server.'];
    }
}
