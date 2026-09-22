<?php

namespace App\Http\Requests\Pengaturan;

use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\PengaturanService;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class UpdatePengaturanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->is_active) {
            return false;
        }

        return app(PermissionResolver::class)->allows($user, PermissionCodes::PENGATURAN_UPDATE);
    }

    protected function prepareForValidation(): void
    {
        $this->replace(Arr::undot($this->all()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'alasan' => ['nullable', 'string', 'max:255'],
        ];

        foreach (PengaturanService::WHITELIST as $kunci => $meta) {
            $rules[$kunci] = $meta['aturan'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $allowedKeys = array_merge(array_keys(PengaturanService::WHITELIST), ['alasan']);
            $inputKeys = array_keys(Arr::dot($this->all()));

            $disallowed = array_diff($inputKeys, $allowedKeys);
            foreach ($disallowed as $key) {
                $validator->errors()->add(
                    $key,
                    "Kunci pengaturan '{$key}' tidak diizinkan untuk diubah."
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'instansi.nama.required' => 'Nama instansi wajib diisi.',
            'instansi.nama.max' => 'Nama instansi maksimal 255 karakter.',
            'instansi.surel.email' => 'Format surel instansi tidak valid.',
            'instansi.laman.url' => 'Format tautan laman resmi instansi harus berupa URL yang valid (cth: https://...).',
            'aplikasi.nama.required' => 'Nama aplikasi wajib diisi.',
            'aplikasi.label_unit.required' => 'Label unit kerja wajib diisi.',
            'tampilan.zona_waktu.required' => 'Zona waktu wajib dipilih.',
            'tampilan.zona_waktu.in' => 'Pilihan zona waktu tidak valid.',
            'tampilan.format_tanggal.required' => 'Format tanggal wajib dipilih.',
            'tampilan.format_tanggal.in' => 'Pilihan format tanggal tidak valid.',
            'tampilan.format_angka.required' => 'Format angka wajib dipilih.',
            'tampilan.format_angka.in' => 'Pilihan format angka tidak valid.',
        ];
    }
}
