<?php

namespace App\Http\Requests\Access;

use App\Policies\RolePermissionPolicy;
use Illuminate\Foundation\Http\FormRequest;

class ChangeRolePermissionRequest extends FormRequest
{
    /** HTTP gate tidak menulis audit; Action memeriksa ulang state terkunci. */
    public function authorize(): bool
    {
        $actor = $this->user()?->fresh();

        return $actor !== null && app(RolePermissionPolicy::class)->decide($actor)['allowed'];
    }

    public function rules(): array
    {
        return [
            'permission_id' => ['required', 'uuid'], 'operation' => ['required', 'in:add,revoke'],
            'alasan' => ['required', 'string', 'max:2000'], 'expected_state' => ['required', 'regex:/\A[a-f0-9]{64}\z/'],
            'actor_id' => ['prohibited'], 'actor_type' => ['prohibited'], 'sumber' => ['prohibited'],
            'audit_id' => ['prohibited'], 'dasar_izin' => ['prohibited'], 'nilai_lama' => ['prohibited'],
            'nilai_baru' => ['prohibited'], 'unit_id' => ['prohibited'], 'role_id' => ['prohibited'],
            'is_active' => ['prohibited'], 'grant' => ['prohibited'], 'deny' => ['prohibited'],
            'penanggung_jawab' => ['prohibited'], 'receipt' => ['prohibited'], 'status' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'permission_id.*' => 'Pilih izin yang valid.', 'operation.*' => 'Pilih tambah atau cabut izin.',
            'alasan.required' => 'Alasan perubahan izin peran wajib diisi.', 'alasan.max' => 'Alasan maksimal 2.000 karakter.',
            'expected_state.*' => 'Data izin peran tidak valid. Muat ulang sebelum menyimpan.',
        ];
    }
}
