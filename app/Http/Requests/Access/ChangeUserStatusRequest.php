<?php

namespace App\Http\Requests\Access;

use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;

class ChangeUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user()?->fresh();
        $permissions = app(PermissionResolver::class);

        return $user !== null && $permissions->allows($user, 'pengguna:read') && $permissions->allows($user, 'akses:update');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:aktif,nonaktif'],
            'expected_status' => ['required', 'in:aktif,nonaktif'],
            'alasan' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.*' => 'Pilih status akun yang valid.',
            'expected_status.*' => 'Data status tidak valid. Muat ulang data sebelum menyimpan.',
            'alasan.required' => 'Alasan perubahan status wajib diisi.',
            'alasan.max' => 'Alasan maksimal 2.000 karakter.',
        ];
    }
}
