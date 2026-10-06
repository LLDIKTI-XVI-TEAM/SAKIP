<?php

namespace App\Http\Requests\Indikator;

use App\Models\IndikatorKinerja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyIndikatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var IndikatorKinerja|null $indikator */
        $indikator = $this->route('indikator');

        return $indikator !== null && Gate::allows('delete', $indikator);
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
            'alasan.required' => 'Alasan pengarsipan wajib diisi.',
            'alasan.min' => 'Alasan pengarsipan minimal 10 karakter.',
            'alasan.max' => 'Alasan pengarsipan maksimal 1000 karakter.',
        ];
    }
}
