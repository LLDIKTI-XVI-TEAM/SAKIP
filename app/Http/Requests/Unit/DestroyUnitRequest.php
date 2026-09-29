<?php

namespace App\Http\Requests\Unit;

use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;

class DestroyUnitRequest extends FormRequest
{
    private PermissionDecision $decision;

    public function authorize(PermissionResolver $permissionResolver): bool
    {
        $actor = $this->user();
        abort_unless($actor, 401);
        $this->decision = $permissionResolver->resolve($actor, 'unit:delete');

        // Boolean meneruskan penolakan ke hook audit, termasuk jika payload malformed.
        return $this->decision->allowed && $actor->hasRole('superadmin');
    }

    protected function failedAuthorization(): void
    {
        app(AuditLogger::class)->catat(
            actor: $this->user(),
            tindakan: 'unit.hapus_ditolak',
            objekTipe: 'unit',
            objekId: $this->route('id'),
            nilaiLama: null,
            nilaiBaru: null,
            alasan: 'Hanya peran Superadmin yang berwenang menghapus unit organisasi.',
            dasarIzin: $this->decision->toAuditBasis(),
        );

        abort(403, 'Hanya peran Superadmin yang berwenang menghapus unit organisasi.');
    }

    public function initialDecision(): PermissionDecision
    {
        return $this->decision;
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
                'required', 'string', 'min:5', 'max:1000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (trim((string) $value) === '' || mb_strlen(trim((string) $value)) < 5) {
                        $fail('Alasan penghapusan unit minimal 5 karakter.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan penghapusan unit wajib diisi sebagai dasar audit.',
            'alasan.min' => 'Alasan penghapusan unit minimal 5 karakter.',
        ];
    }
}
