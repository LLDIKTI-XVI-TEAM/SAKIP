<?php

namespace App\Http\Requests\Renstra;

use App\Models\Renstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;

class DestroyRenstraRequest extends FormRequest
{
    private ?PermissionDecision $initialDecision = null;

    public function authorize(): bool
    {
        $renstra = $this->route('renstra');

        $user = $this->user();
        if (! $renstra instanceof Renstra || ! $user instanceof User) {
            return false;
        }
        $this->initialDecision = app(PermissionResolver::class)->resolve($user, PermissionCodes::RENSTRA_DELETE);

        return $this->initialDecision->allowed;
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $renstra = $this->route('renstra');

        if ($user instanceof User && $renstra instanceof Renstra && $this->initialDecision !== null) {
            $decision = $this->initialDecision;
            $alasan = mb_substr(trim(AuditReason::sanitize($this->input('alasan'))), 0, 1000);

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'renstra.hapus_ditolak',
                objekTipe: 'renstra',
                objekId: $renstra->id,
                nilaiLama: $renstra->withoutRelations()->toArray(),
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan penghapusan Renstra wajib diisi.',
            'alasan.min' => 'Alasan penghapusan minimal 5 karakter.',
        ];
    }
}
