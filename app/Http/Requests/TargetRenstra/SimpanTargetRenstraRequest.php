<?php

namespace App\Http\Requests\TargetRenstra;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** Validasi sintaks saja; tahun, presisi, dan state dinilai Action dari data terkunci. Target Renstra tidak memakai alasan. */
class SimpanTargetRenstraRequest extends FormRequest
{
    /** Field kontrak; nama aturan wildcard seperti `nilai.*` bukan field yang sah. */
    public const FIELDS = ['nilai', 'expected_state'];

    /** @var array<string, PermissionDecision> */
    private array $initialDecisions = [];

    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor instanceof User) {
            return false;
        }
        foreach ([PermissionCodes::INDIKATOR_READ, PermissionCodes::TARGET_UPDATE] as $code) {
            $this->initialDecisions[$code] = app(PermissionResolver::class)->resolve($actor, $code);
        }

        return $this->initialDecisions[PermissionCodes::INDIKATOR_READ]->allowed
            && $this->initialDecisions[PermissionCodes::TARGET_UPDATE]->allowed;
    }

    /** Aturan dipakai ulang di Action untuk caller selain HTTP. Number JSON ditolak agar nilai tidak pernah melewati float. @return array<string, mixed> */
    public static function inputRules(): array
    {
        return ['nilai' => ['required', 'array', 'min:1'], 'nilai.*' => ['nullable', 'string', 'max:40'],
            'expected_state' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']];
    }

    public function rules(): array
    {
        return self::inputRules();
    }

    /** @return array<string, string> */
    public static function inputMessages(): array
    {
        return [
            'nilai.required' => 'Data target belum lengkap. Muat ulang form.',
            'nilai.array' => 'Data target belum lengkap. Muat ulang form.',
            'nilai.min' => 'Data target belum lengkap. Muat ulang form.',
            'nilai.*.string' => 'Nilai target harus berupa teks angka desimal.',
            'nilai.*.max' => 'Angka target terlalu panjang.',
            'expected_state.required' => 'Data form tidak valid. Muat ulang form.',
            'expected_state.string' => 'Data form tidak valid. Muat ulang form.',
            'expected_state.regex' => 'Data form tidak valid. Muat ulang form.',
        ];
    }

    public function messages(): array
    {
        return self::inputMessages();
    }

    /** Field di luar kontrak, termasuk alasan, tidak boleh diterapkan diam-diam. */
    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if (array_diff(array_keys($this->all()), [...self::FIELDS, '_token', '_method']) !== []) {
                $validator->errors()->add('nilai', 'Permintaan memuat field yang tidak didukung.');
            }
        }];
    }

    protected function failedAuthorization(): void
    {
        $this->recordRejection('izin_ditolak');
        parent::failedAuthorization();
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->recordRejection('input_tidak_valid');
        parent::failedValidation($validator);
    }

    private function recordRejection(string $reason): void
    {
        $actor = $this->user();
        if (! $actor instanceof User) {
            return;
        }
        $basis = [];
        foreach ($this->initialDecisions as $code => $decision) {
            $basis[$code] = $decision->toAuditBasis();
        }
        app(AuditLogger::class)->catat(actor: $actor, tindakan: 'target_renstra.simpan_ditolak', objekTipe: 'indikator', objekId: strtolower((string) $this->route('indikator')),
            nilaiBaru: ['alasan_penolakan' => $reason], dasarIzin: $basis);
    }
}
