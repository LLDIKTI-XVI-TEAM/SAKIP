<?php

namespace App\Http\Requests\Sasaran;

use App\Models\SasaranStrategis;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSasaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SasaranStrategis|null $sasaran */
        $sasaran = $this->route('sasaran');

        return $sasaran !== null && Gate::allows('update', $sasaran);
    }

    /**
     * `kode` dan `urutan` server-managed (deret kode otomatis) sehingga tidak
     * dapat diubah lewat edit; ditolak bila dikirim klien.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kode' => ['prohibited'],
            'deskripsi' => ['required', 'string', 'max:2000'],
            'urutan' => ['prohibited'],
            'expected_updated_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.prohibited' => 'Kode sasaran dibangkitkan otomatis oleh sistem dan tidak dapat diubah.',
            'deskripsi.required' => 'Deskripsi sasaran wajib diisi.',
            'urutan.prohibited' => 'Urutan sasaran ditentukan otomatis oleh sistem dan tidak dapat diubah.',
            'expected_updated_at.required' => 'Timestamp versi wajib disertakan. Muat ulang halaman untuk mendapatkan data terkini.',
            'expected_updated_at.date' => 'Format timestamp versi tidak valid.',
        ];
    }
}
