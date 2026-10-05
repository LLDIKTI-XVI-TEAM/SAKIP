<?php

namespace App\Http\Requests\TargetTahunan;

use App\Models\User;
use App\Policies\TargetKinerjaPolicy;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** Validasi sintaks saja; kewajiban metadata koreksi ditentukan Action dari perubahan aktual terkunci. */
class SaveTargetTahunanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && app(TargetKinerjaPolicy::class)->update($this->user())->allowed();
    }

    /** Aturan dipakai ulang di Action untuk caller selain HTTP. @return array<string, mixed> */
    public static function inputRules(): array
    {
        return ['baseline' => ['present', 'nullable', 'string', 'max:1000'], 'target_tahunan' => ['present', 'nullable', 'string', 'max:1000'],
            'expected_state' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'], 'operation_id' => ['required', 'uuid'],
            'alasan' => ['nullable', 'string', 'max:1000', AuditReason::validate(...)], 'rujukan_sumber' => ['nullable', 'string', 'max:1000', AuditReason::validate(...)]];
    }

    public function rules(): array
    {
        return self::inputRules();
    }

    /** Pesan form dan caller Action tetap sama, termasuk metadata koreksi kondisional. @return array<string, string> */
    public static function inputMessages(): array
    {
        return [
            'baseline.present' => 'Data baseline belum lengkap. Muat ulang form.',
            'baseline.string' => 'Isi baseline dengan angka yang valid.',
            'baseline.max' => 'Angka baseline terlalu panjang.',
            'target_tahunan.present' => 'Data target belum lengkap. Muat ulang form.',
            'target_tahunan.string' => 'Isi target dengan angka yang valid.',
            'target_tahunan.max' => 'Angka target terlalu panjang.',
            'expected_state.required' => 'Data form tidak valid. Muat ulang form.',
            'expected_state.string' => 'Data form tidak valid. Muat ulang form.',
            'expected_state.regex' => 'Data form tidak valid. Muat ulang form.',
            'operation_id.required' => 'Permintaan simpan tidak valid. Muat ulang form.',
            'operation_id.uuid' => 'Permintaan simpan tidak valid. Muat ulang form.',
            'alasan.required' => 'Tuliskan alasan perubahan.',
            'alasan.string' => 'Alasan harus berupa teks.',
            'alasan.max' => 'Alasan maksimal :max karakter.',
            'rujukan_sumber.required' => 'Cantumkan rujukan sumber perubahan.',
            'rujukan_sumber.string' => 'Rujukan sumber harus berupa teks.',
            'rujukan_sumber.max' => 'Rujukan sumber maksimal :max karakter.',
        ];
    }

    public function messages(): array
    {
        return self::inputMessages();
    }

    /** Metadata/aktor/TW di luar kontrak tidak boleh diterapkan diam-diam. */
    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if (array_diff(array_keys($this->all()), [...array_keys(self::inputRules()), '_token', '_method']) !== []) {
                $validator->errors()->add('target_tahunan', 'Permintaan memuat field yang tidak didukung.');
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
        foreach ([PermissionCodes::INDIKATOR_READ, PermissionCodes::TARGET_UPDATE] as $code) {
            $basis[$code] = app(PermissionResolver::class)->resolve($actor, $code)->toAuditBasis();
        }
        app(AuditLogger::class)->catat(actor: $actor, tindakan: 'target_tahunan.simpan_ditolak', objekTipe: 'indikator', objekId: strtolower((string) $this->route('indikator')),
            nilaiBaru: ['tahun' => (int) $this->route('tahun'), 'alasan_penolakan' => $reason], dasarIzin: $basis);
    }
}
