<?php

namespace App\Http\Requests\Sasaran;

use App\Models\SasaranStrategis;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSasaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', SasaranStrategis::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'renstra_id' => ['required', 'uuid', 'exists:renstras,id'],
            'kode' => ['required', 'string', 'max:50'],
            'deskripsi' => ['required', 'string', 'max:2000'],
            'urutan' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'renstra_id.required' => 'Renstra wajib dipilih.',
            'renstra_id.exists' => 'Renstra yang dipilih tidak valid.',
            'kode.required' => 'Kode sasaran wajib diisi.',
            'deskripsi.required' => 'Deskripsi sasaran wajib diisi.',
        ];
    }
}
