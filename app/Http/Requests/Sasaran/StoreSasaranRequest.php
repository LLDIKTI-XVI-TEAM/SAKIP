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
     * `kode` dan `urutan` dibangkitkan server-side dari deret kode otomatis,
     * sehingga ditolak bila dikirim klien (fail-loud, bukan diabaikan diam-diam).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'renstra_id' => ['required', 'uuid', 'exists:renstras,id'],
            'kode' => ['prohibited'],
            'deskripsi' => ['required', 'string', 'max:2000'],
            'urutan' => ['prohibited'],
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
            'kode.prohibited' => 'Kode sasaran dibangkitkan otomatis oleh sistem dan tidak boleh dikirim dari klien.',
            'deskripsi.required' => 'Deskripsi sasaran wajib diisi.',
            'urutan.prohibited' => 'Urutan sasaran ditentukan otomatis oleh sistem dan tidak boleh dikirim dari klien.',
        ];
    }
}
