<?php

namespace App\Http\Requests\Indikator;

use App\Models\IndikatorKomponen;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class DestroyIndikatorKomponenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user()?->fresh();

        return $user !== null && app(PermissionResolver::class)->allows($user, 'komponen:delete');
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user()?->fresh();
        if ($user) {
            $komponen = $this->route('komponen');
            $komponenId = (string) ($komponen instanceof IndikatorKomponen ? $komponen->id : ($komponen ?? Str::uuid()));
            $decision = app(PermissionResolver::class)->decide($user, 'komponen:delete');
            $rawAlasan = $this->input('alasan');
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== ''
                ? trim($rawAlasan)
                : 'Percobaan penghapusan komponen indikator ditolak karena tidak memiliki izin.';

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'komponen.hapus_ditolak',
                objekTipe: 'indikator_komponen',
                objekId: $komponenId,
                alasan: $alasan,
                dasarIzin: $decision,
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
            'alasan.required' => 'Alasan penghapusan komponen wajib diisi.',
            'alasan.min' => 'Alasan penghapusan komponen minimal 5 karakter.',
            'alasan.max' => 'Alasan penghapusan komponen maksimal 1000 karakter.',
        ];
    }
}
