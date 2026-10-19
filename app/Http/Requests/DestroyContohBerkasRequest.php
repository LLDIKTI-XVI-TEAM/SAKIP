<?php

namespace App\Http\Requests;

use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\AuditReason;
use Illuminate\Foundation\Http\FormRequest;

class DestroyContohBerkasRequest extends FormRequest
{
    /** @var array{allowed: bool, permission: string, reason: string, roles: list<string>, grants: list<string>, denies: list<string>}|null */
    private ?array $authorizationDecision = null;

    public function authorize(): bool
    {
        $user = $this->user()?->fresh();
        $this->authorizationDecision = $user !== null ? app(PermissionResolver::class)->decide($user, 'jenis_berkas:update') : null;

        return $this->authorizationDecision['allowed'] ?? false;
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user()?->fresh();
        if ($user && $this->authorizationDecision !== null) {
            app(AuditLogger::class)->catat(actor: $user, tindakan: 'berkas.hapus_ditolak', objekTipe: 'jenis_berkas', objekId: (string) $this->route('id'),
                alasan: 'Percobaan hapus contoh berkas ditolak karena tidak memiliki izin.', dasarIzin: $this->authorizationDecision);
        }

        parent::failedAuthorization();
    }

    /** Penghapusan berkas wajib alasan (Q43 §43.2 butir 2). */
    public function rules(): array
    {
        return ['alasan' => ['required', 'string', 'min:5', 'max:1000', AuditReason::validate(...)]];
    }

    public function messages(): array
    {
        return ['alasan.required' => 'Alasan penghapusan contoh berkas wajib diisi.'];
    }
}
