<?php

namespace App\Http\Requests\Renstra;

use App\Models\Renstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyRenstraRequest extends FormRequest
{
    public function authorize(): bool
    {
        $renstra = $this->route('renstra');

        return $renstra instanceof Renstra
            ? Gate::allows('delete', $renstra)
            : false;
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $renstra = $this->route('renstra');

        if ($user instanceof User && $renstra instanceof Renstra) {
            $decision = app(PermissionResolver::class)->resolve($user, PermissionCodes::RENSTRA_DELETE);
            $rawAlasan = $this->input('alasan');
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== '' ? trim($rawAlasan) : null;

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
