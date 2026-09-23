<?php

namespace App\Http\Requests\Pengaturan;

use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStoragePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(PermissionResolver::class)->allows($user, 'pengaturan:update');
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Anda tidak memiliki wewenang untuk mengubah kebijakan storage aplikasi (memerlukan izin pengaturan:update).');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'berkas_unggahan_aktif' => ['required', 'boolean'],
            'berkas_ukuran_maks_kb' => ['required', 'integer', 'min:100', 'max:102400'],
            'berkas_format_diizinkan' => ['required', 'string', 'max:255'],
            'berkas_tautan_selalu_diizinkan' => ['required', 'boolean'],
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * Normalisasi format ekstensi sebelum divalidasi.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('berkas_format_diizinkan') && is_string($this->input('berkas_format_diizinkan'))) {
            $raw = (string) $this->input('berkas_format_diizinkan');
            // Bersihkan spasi, titik awal pada ekstensi, dan ubah ke lowercase
            $tokens = array_filter(
                array_map(fn ($ext) => strtolower(ltrim(trim($ext), '.')), explode(',', $raw)),
                fn ($ext) => $ext !== ''
            );

            $this->merge([
                'berkas_format_diizinkan' => implode(',', array_unique($tokens)),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'berkas_unggahan_aktif' => 'saklar unggahan berkas',
            'berkas_ukuran_maks_kb' => 'batas ukuran maksimum (KB)',
            'berkas_format_diizinkan' => 'format berkas yang diizinkan',
            'berkas_tautan_selalu_diizinkan' => 'ketersediaan jalur tautan & teks',
            'alasan' => 'alasan audit',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'boolean' => ':Attribute harus bernilai benar atau salah.',
            'integer' => ':Attribute harus berupa angka bilangan bulat.',
            'min' => ':Attribute minimal :min karakter/KB.',
            'max' => ':Attribute maksimal :max karakter/KB.',
            'string' => ':Attribute harus berupa teks.',
        ];
    }
}
