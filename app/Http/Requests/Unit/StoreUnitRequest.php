<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('create', Unit::class);

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nama'))) {
            $this->merge(['nama' => trim($this->input('nama'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nama' => [
                'required', 'string', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $trimmed = trim((string) $value);
                    if ($trimmed === '') {
                        $fail('Nama unit organisasi tidak boleh kosong atau hanya berisi spasi.');

                        return;
                    }
                    if (Unit::whereRaw('LOWER(nama) = ?', [mb_strtolower($trimmed)])->exists()) {
                        $fail('Nama unit organisasi sudah digunakan.');
                    }
                },
            ],
            'status' => ['nullable', 'in:aktif,nonaktif'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama unit organisasi wajib diisi.',
            'nama.max' => 'Nama unit organisasi maksimal 255 karakter.',
        ];
    }
}
