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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:50'],
            'deskripsi' => ['required', 'string', 'max:2000'],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'expected_updated_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.required' => 'Kode sasaran wajib diisi.',
            'deskripsi.required' => 'Deskripsi sasaran wajib diisi.',
            'expected_updated_at.required' => 'Timestamp versi wajib disertakan. Muat ulang halaman untuk mendapatkan data terkini.',
            'expected_updated_at.date' => 'Format timestamp versi tidak valid.',
        ];
    }
}
