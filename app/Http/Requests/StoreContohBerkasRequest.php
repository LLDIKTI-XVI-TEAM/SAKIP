<?php

namespace App\Http\Requests;

use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;

class StoreContohBerkasRequest extends FormRequest
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
            app(AuditLogger::class)->catat(actor: $user, tindakan: 'berkas.unggah_ditolak', objekTipe: 'jenis_berkas', objekId: (string) $this->route('id'),
                alasan: 'Percobaan unggah contoh berkas ditolak karena tidak memiliki izin.', dasarIzin: $this->authorizationDecision);
        }

        parent::failedAuthorization();
    }

    /** Batas ukuran/format divalidasi Action dari batas teknis yang berlaku. */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'menggantikan_id' => ['nullable', 'uuid'],
            'alasan_koreksi' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['file.required' => 'Contoh berkas wajib diunggah.'];
    }
}
