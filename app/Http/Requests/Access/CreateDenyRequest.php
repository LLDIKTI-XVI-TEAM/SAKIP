<?php

namespace App\Http\Requests\Access;

use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;

class CreateDenyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user()?->fresh();

        return $actor !== null && app(PermissionResolver::class)->allows($actor, 'akses:update');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid'], 'permission_id' => ['required', 'uuid'],
            'unit_id' => ['present', 'nullable', 'uuid'], 'alasan' => ['required', 'string', 'max:2000'],
            'id' => ['prohibited'], 'actor_id' => ['prohibited'], 'ditetapkan_oleh' => ['prohibited'],
            'created_at' => ['prohibited'], 'audit_id' => ['prohibited'], 'actor_type' => ['prohibited'],
            'sumber' => ['prohibited'], 'waktu' => ['prohibited'], 'dasar_izin' => ['prohibited'],
            'is_active' => ['prohibited'], 'role_id' => ['prohibited'], 'grant' => ['prohibited'],
            'deny' => ['prohibited'], 'penanggung_jawab' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Pilih pengguna.', 'user_id.uuid' => 'Pilih pengguna yang valid.',
            'permission_id.required' => 'Pilih izin.', 'permission_id.uuid' => 'Pilih izin yang valid.',
            'unit_id.*' => 'Pilih cakupan yang valid.',
            'alasan.required' => 'Alasan pembatasan wajib diisi.', 'alasan.max' => 'Alasan maksimal 2.000 karakter.',
        ];
    }
}
