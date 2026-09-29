<?php

namespace App\Http\Requests\Access;

use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;

class RevokeUnitGrantRequest extends FormRequest
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
            objekId: $this->route('id'),
            nilaiLama: null,
            nilaiBaru: null,
            alasan: 'Anda tidak berwenang mengelola pencabutan izin unit.',
            dasarIzin: $this->decision->toAuditBasis(),
        );

        abort(403, 'Anda tidak berwenang mengelola pencabutan izin unit.');
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
            'alasan' => [
                'required',
                'string',
                'min:5',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (trim((string) $value) === '' || mb_strlen(trim((string) $value)) < 5) {
                        $fail('Alasan pencabutan izin tidak boleh kosong atau hanya berisi spasi.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan pencabutan izin wajib diisi sebagai dasar audit.',
            'alasan.min' => 'Alasan pencabutan izin minimal 5 karakter.',
        ];
    }
}
