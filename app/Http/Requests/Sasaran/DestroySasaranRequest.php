<?php

namespace App\Http\Requests\Sasaran;

use App\Models\SasaranStrategis;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroySasaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SasaranStrategis|null $sasaran */
        $sasaran = $this->route('sasaran');

        return $sasaran !== null && Gate::allows('delete', $sasaran);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan penghapusan wajib diisi.',
            'alasan.min' => 'Alasan penghapusan minimal 10 karakter.',
            'alasan.max' => 'Alasan penghapusan maksimal 1000 karakter.',
        ];
    }
}
