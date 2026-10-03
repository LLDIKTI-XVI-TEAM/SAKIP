<?php

namespace App\Http\Requests\Access;

use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EffectivePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && app(PermissionResolver::class)->allows($actor->fresh(), 'pengguna:read');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['bail', 'nullable', 'uuid', Rule::exists('users', 'id')],
            'unit_id' => ['bail', 'nullable', 'uuid', Rule::exists('unit', 'id')],
            'q' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', Rule::in(['global', 'unit'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'diagnostic_page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.uuid' => 'Pilih pengguna yang valid.',
            'user_id.exists' => 'Pengguna tidak ditemukan.',
            'unit_id.uuid' => 'Pilih unit yang valid.',
            'unit_id.exists' => 'Unit tidak ditemukan.',
            'q.max' => 'Pencarian maksimal 100 karakter.',
            'scope.in' => 'Pilih scope Global atau Unit.',
            'page.min' => 'Halaman minimal 1.',
            'diagnostic_page.min' => 'Halaman diagnosis minimal 1.',
        ];
    }
}
