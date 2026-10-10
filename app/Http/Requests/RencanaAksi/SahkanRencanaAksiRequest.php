<?php

namespace App\Http\Requests\RencanaAksi;

use Illuminate\Foundation\Http\FormRequest;

class SahkanRencanaAksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['versi' => ['required', 'integer', 'min:1'],
            'status_alur' => ['prohibited'], 'status' => ['prohibited'], 'self_approval' => ['prohibited'],
            'disahkan_by' => ['prohibited'], 'disahkan_at' => ['prohibited'],
            'diajukan_by' => ['prohibited'], 'jalur_pengajuan' => ['prohibited'], 'jadwal_snapshot_id' => ['prohibited']];
    }

    public function messages(): array
    {
        return ['versi.required' => 'Versi data wajib dikirim. Muat ulang halaman.', 'versi.integer' => 'Versi data tidak sah.', 'versi.min' => 'Versi data tidak sah.',
            'prohibited' => 'Kolom :attribute ditentukan server.'];
    }
}
