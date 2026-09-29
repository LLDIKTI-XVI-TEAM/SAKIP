<?php

namespace App\Http\Requests\Access;

use App\Models\Permission;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionCatalog;
use App\Services\PermissionResolver;
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
            'permission_id' => [
                'required',
                'bail',
                'uuid',
                Rule::exists('permissions', 'id')->where(
                    fn ($query) => $query->where('aktif', true)
                        ->where('butuh_scope', Permission::SCOPE_UNIT)
                        ->whereIn('kode', PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS)
                ),
            ],
            'unit_id' => [
                'nullable',
                'bail',
                'uuid',
                Rule::exists('unit', 'id')->where(fn ($query) => $query->where('status', 'aktif')),
            ],
            'alasan' => [
                'required',
                'string',
                'min:5',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (trim((string) $value) === '' || mb_strlen(trim((string) $value)) < 5) {
                        $fail('Alasan pemberian grant tidak boleh kosong atau hanya berisi spasi.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Pengguna target wajib dipilih.',
            'user_id.uuid' => 'Format ID pengguna tidak valid.',
            'user_id.exists' => 'Pengguna target tidak ditemukan atau berstatus nonaktif.',
            'permission_id.required' => 'Permission wajib dipilih.',
            'permission_id.uuid' => 'Format ID permission tidak valid.',
            'permission_id.exists' => 'Permission tidak ditemukan dalam katalog.',
            'unit_id.uuid' => 'Format ID unit tidak valid.',
            'unit_id.exists' => 'Unit target tidak ditemukan atau berstatus nonaktif.',
            'alasan.required' => 'Alasan pemberian grant wajib diisi sebagai dasar audit.',
            'alasan.min' => 'Alasan pemberian grant minimal 5 karakter.',
        ];
    }
}
