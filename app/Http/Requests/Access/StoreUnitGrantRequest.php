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

class StoreUnitGrantRequest extends FormRequest
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
            alasan: 'Anda tidak berwenang mengelola pemberian izin unit.',
            dasarIzin: $this->decision->toAuditBasis(),
        );

        abort(403, 'Anda tidak berwenang mengelola pemberian izin unit.');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('alasan'))) {
            $this->merge(['alasan' => trim($this->input('alasan'))]);
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'bail',
                'uuid',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', 'aktif')),
            ],
            'permission_ids' => ['required', 'array', 'max:'.count(PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS)],
            'permission_ids.*' => [
                'bail',
                'uuid',
                'distinct',
                Rule::exists('permissions', 'id')->where(
                    fn ($query) => $query->where('aktif', true)
                        ->where('butuh_scope', Permission::SCOPE_UNIT)
                        ->whereIn('kode', PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS)
                ),
            ],
            'unit_id' => [
                'required',
                'bail',
                'uuid',
                Rule::exists('unit', 'id')->where(fn ($query) => $query->where('status', 'aktif')),
            ],
            // Opsional (Q43 §43.3 butir 1); kosong diisi alasan baku oleh CreateUnitGrantAction.
            'alasan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Pengguna wajib dipilih.',
            'user_id.uuid' => 'Format ID pengguna tidak valid.',
            'user_id.exists' => 'Pengguna tidak ditemukan atau akunnya nonaktif.',
            'permission_ids.required' => 'Pilih minimal satu izin.',
            'permission_ids.array' => 'Pilih minimal satu izin.',
            'permission_ids.max' => 'Pilih paling banyak tujuh izin.',
            'permission_ids.*.uuid' => 'Format ID izin tidak valid.',
            'permission_ids.*.distinct' => 'Izin yang sama dipilih lebih dari sekali.',
            'permission_ids.*.exists' => 'Izin ini tidak dapat diberikan sebagai izin unit.',
            'unit_id.required' => 'Unit wajib dipilih.',
            'unit_id.uuid' => 'Format ID unit tidak valid.',
            'unit_id.exists' => 'Unit tidak ditemukan atau berstatus nonaktif.',
            'alasan.string' => 'Alasan pemberian izin harus berupa teks.',
            'alasan.max' => 'Alasan pemberian izin maksimal 1.000 karakter.',
        ];
    }
}
