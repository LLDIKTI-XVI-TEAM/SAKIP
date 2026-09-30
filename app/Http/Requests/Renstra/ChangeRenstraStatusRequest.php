<?php

namespace App\Http\Requests\Renstra;

use App\Models\Renstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ChangeRenstraStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->route('renstra') instanceof Renstra
            && Gate::allows('update', $this->route('renstra'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['expected_state' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']];
    }

    public function messages(): array
    {
        return [
            'expected_state.required' => 'Data Renstra belum tersedia. Muat data terbaru.',
            'expected_state.string' => 'Data Renstra tidak valid. Muat data terbaru.',
            'expected_state.regex' => 'Data Renstra tidak valid. Muat data terbaru.',
        ];
    }

    protected function failedAuthorization(): void
    {
        $this->auditDenial('izin_ditolak');
        parent::failedAuthorization();
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->auditDenial('validasi_input');
        parent::failedValidation($validator);
    }

    /** Payload tidak tepercaya tidak ikut masuk audit penolakan awal. */
    private function auditDenial(string $reason): void
    {
        $actor = $this->user();
        $renstra = $this->route('renstra');
        if ($actor instanceof User && $renstra instanceof Renstra) {
            app(AuditLogger::class)->catat(
                actor: $actor, tindakan: 'renstra.lifecycle_ditolak', objekTipe: 'renstra', objekId: $renstra->id,
                nilaiLama: $renstra->masterAttributes(), nilaiBaru: ['hasil' => 'ditolak', 'alasan_penolakan' => $reason, 'route' => $this->route()?->getName()],
                alasan: 'Permintaan perubahan status Renstra ditolak.',
                dasarIzin: app(PermissionResolver::class)->resolve($actor, PermissionCodes::RENSTRA_UPDATE)->toAuditBasis(),
            );
        }
    }
}
