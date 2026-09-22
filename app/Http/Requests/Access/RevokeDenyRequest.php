<?php

namespace App\Http\Requests\Access;

use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;

class RevokeDenyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user()?->fresh();

        return $actor !== null && app(PermissionResolver::class)->allows($actor, 'akses:update');
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'max:2000'],
            'deny_id' => ['prohibited'], 'id' => ['prohibited'], 'user_id' => ['prohibited'],
            'permission_id' => ['prohibited'], 'unit_id' => ['prohibited'], 'actor_id' => ['prohibited'],
            'ditetapkan_oleh' => ['prohibited'], 'created_at' => ['prohibited'], 'audit_id' => ['prohibited'],
            'actor_type' => ['prohibited'], 'sumber' => ['prohibited'], 'waktu' => ['prohibited'],
            'dasar_izin' => ['prohibited'], 'is_active' => ['prohibited'], 'role_id' => ['prohibited'],
            'grant' => ['prohibited'], 'deny' => ['prohibited'], 'penanggung_jawab' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['alasan.required' => 'Alasan pencabutan deny wajib diisi.', 'alasan.max' => 'Alasan maksimal 2.000 karakter.'];
    }
}
