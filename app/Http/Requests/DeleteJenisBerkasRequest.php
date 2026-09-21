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
            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'jenis_berkas.hapus_ditolak',
                objekTipe: 'jenis_berkas',
                objekId: $id,
                alasan: $this->input('alasan') ?: 'Percobaan penghapusan persyaratan jenis berkas ditolak karena tidak memiliki izin.',
                dasarIzin: $decision,
            );
        }

        parent::failedAuthorization();
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
