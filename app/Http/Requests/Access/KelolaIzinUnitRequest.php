<?php

namespace App\Http\Requests\Access;

use App\Models\Permission;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KelolaIzinUnitRequest extends FormRequest
{
    private PermissionDecision $decision;

    public function authorize(PermissionResolver $permissionResolver): bool
    {
        $actor = $this->user();
        abort_unless($actor, 401);
        $this->decision = $permissionResolver->resolve($actor, PermissionCodes::DELEGASI_UPDATE);

        // Boolean menjaga hook denial audit tetap berjalan sebelum validasi payload.
        return $this->decision->allowed;
    }

    protected function failedAuthorization(): void
    {
        app(AuditLogger::class)->catat(
            actor: $this->user(),
            tindakan: 'user_permission_granted.ditolak',
            objekTipe: 'user_permission_granted',
            objekId: (string) Str::uuid(),
            nilaiLama: null,
            nilaiBaru: null,
            alasan: 'Anda tidak berwenang mengelola izin unit.',
            dasarIzin: $this->decision->toAuditBasis(),
        );

        abort(403, 'Anda tidak berwenang mengelola izin unit.');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('alasan'))) {
            $this->merge(['alasan' => trim($this->input('alasan'))]);
        }
    }

    public function rules(): array
    {
        $maks = 'max:'.count(PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS);

        return [
            // Pengguna dan unit nonaktif tetap diterima agar izin lamanya dapat dicabut; penambahan diperiksa di Action.
            'user_id' => ['required', 'bail', 'uuid', Rule::exists('users', 'id')],
            'unit_id' => ['required', 'bail', 'uuid', Rule::exists('unit', 'id')],
            'permission_ids' => ['present', 'array', $maks],
            'permission_ids.*' => [
                'bail',
                'uuid',
                'distinct',
                Rule::exists('permissions', 'id')->where(
                    fn ($query) => $query->where('butuh_scope', Permission::SCOPE_UNIT)
                        ->whereIn('kode', PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS)
                ),
            ],
            'expected_permission_ids' => ['present', 'array', $maks],
            'expected_permission_ids.*' => ['uuid'],
            'alasan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Pengguna wajib dipilih.',
            'user_id.uuid' => 'Format ID pengguna tidak valid.',
            'user_id.exists' => 'Pengguna tidak ditemukan.',
            'unit_id.required' => 'Unit wajib dipilih.',
            'unit_id.uuid' => 'Format ID unit tidak valid.',
            'unit_id.exists' => 'Unit tidak ditemukan.',
            'permission_ids.present' => 'Daftar izin wajib dikirim.',
            'permission_ids.array' => 'Daftar izin tidak valid.',
            'permission_ids.max' => 'Pilih paling banyak tujuh izin.',
            'permission_ids.*.uuid' => 'Format ID izin tidak valid.',
            'permission_ids.*.distinct' => 'Izin yang sama dipilih lebih dari sekali.',
            'permission_ids.*.exists' => 'Izin ini tidak dapat diberikan sebagai izin unit.',
            'expected_permission_ids.*' => 'Data izin tidak valid. Muat ulang data sebelum menyimpan.',
            'expected_permission_ids.present' => 'Data izin tidak valid. Muat ulang data sebelum menyimpan.',
            'expected_permission_ids.array' => 'Data izin tidak valid. Muat ulang data sebelum menyimpan.',
            'expected_permission_ids.max' => 'Data izin tidak valid. Muat ulang data sebelum menyimpan.',
            'alasan.string' => 'Alasan harus berupa teks.',
            'alasan.max' => 'Alasan maksimal 1.000 karakter.',
        ];
    }
}
