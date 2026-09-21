<?php

namespace App\Http\Requests\Access;

use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;

class AssignRoleRequest extends FormRequest
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
            'role_id' => ['required', 'uuid'],
            'alasan' => ['required', 'string', 'max:2000'],
            'expected_assignment' => ['present', 'nullable', 'array:id,role_id,audit_id', 'required_array_keys:id,role_id,audit_id'],
            'expected_assignment.id' => ['required_with:expected_assignment', 'uuid'],
            'expected_assignment.role_id' => ['required_with:expected_assignment', 'uuid'],
            'expected_assignment.audit_id' => ['nullable', 'uuid'],
            'actor_id' => ['prohibited'], 'diberikan_oleh' => ['prohibited'],
            'audit_id' => ['prohibited'], 'sumber_pemberian' => ['prohibited'],
            'actor_type' => ['prohibited'], 'sumber' => ['prohibited'],
            'waktu' => ['prohibited'], 'dasar_izin' => ['prohibited'],
            'is_active' => ['prohibited'], 'grant' => ['prohibited'],
            'deny' => ['prohibited'], 'penanggung_jawab' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.required' => 'Pilih peran tujuan.',
            'role_id.uuid' => 'Pilih peran yang valid.',
            'alasan.required' => 'Alasan penetapan peran wajib diisi.',
            'alasan.max' => 'Alasan maksimal 2.000 karakter.',
            'expected_assignment.*' => 'Data peran tidak valid. Muat ulang data sebelum menyimpan.',
        ];
    }
}
