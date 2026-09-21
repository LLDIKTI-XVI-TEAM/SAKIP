<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ActivateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['alasan' => ['required', 'string', 'max:2000']];
    }

    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan aktivasi wajib diisi.',
            'alasan.string' => 'Alasan aktivasi harus berupa teks.',
            'alasan.max' => 'Alasan aktivasi maksimal 2.000 karakter.',
        ];
    }
}
