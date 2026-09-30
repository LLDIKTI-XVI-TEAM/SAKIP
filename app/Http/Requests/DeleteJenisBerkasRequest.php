<?php

namespace App\Http\Requests;

use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\AuditReason;
use Illuminate\Foundation\Http\FormRequest;

class DeleteJenisBerkasRequest extends FormRequest
{
    /** @var array{allowed: bool, permission: string, reason: string, roles: list<string>, grants: list<string>, denies: list<string>}|null */
    private ?array $authorizationDecision = null;

    public function authorize(): bool
    {
        $user = $this->user()?->fresh();

        $this->authorizationDecision = $user !== null
            ? app(PermissionResolver::class)->decide($user, 'jenis_berkas:delete')
            : null;

        return $this->authorizationDecision['allowed'] ?? false;
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user()?->fresh();
        if ($user && $this->authorizationDecision !== null) {
            $id = (string) ($this->route('id') ?? $this->route('jenis_berkas') ?? '');
            $rawAlasan = mb_substr(trim(AuditReason::sanitize($this->input('alasan'))), 0, 1000, 'UTF-8');
            $alasan = $rawAlasan !== ''
                ? $rawAlasan
                : 'Percobaan penghapusan persyaratan jenis berkas ditolak karena tidak memiliki izin.';

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'jenis_berkas.hapus_ditolak',
                objekTipe: 'jenis_berkas',
                objekId: $id,
                alasan: $alasan,
                dasarIzin: $this->authorizationDecision,
            );
        }

        parent::failedAuthorization();
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
            'expected_updated_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'expected_updated_at.date' => 'Format timestamp versi tidak valid.',
        ];
    }
}
