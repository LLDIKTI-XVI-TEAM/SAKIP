<?php

namespace App\Http\Requests;

use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;

class DeleteJenisBerkasRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user()?->fresh();

        return $user !== null && app(PermissionResolver::class)->allows($user, 'jenis_berkas:delete');
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user()?->fresh();
        if ($user) {
            $id = (string) ($this->route('id') ?? $this->route('jenis_berkas') ?? '');
            $decision = app(PermissionResolver::class)->decide($user, 'jenis_berkas:delete');
            $rawAlasan = $this->input('alasan');
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== ''
                ? trim($rawAlasan)
                : 'Percobaan penghapusan persyaratan jenis berkas ditolak karena tidak memiliki izin.';

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'jenis_berkas.hapus_ditolak',
                objekTipe: 'jenis_berkas',
                objekId: $id,
                alasan: $alasan,
                dasarIzin: $decision,
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
