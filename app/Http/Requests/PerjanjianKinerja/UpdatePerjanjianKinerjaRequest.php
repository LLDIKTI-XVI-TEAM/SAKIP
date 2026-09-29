<?php

namespace App\Http\Requests\PerjanjianKinerja;

use App\Models\RenstraPk;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaSupport;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePerjanjianKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pk = $this->route('perjanjian_kinerja');
        if (! ($pk instanceof RenstraPk)) {
            return false;
        }

        $user = $this->user();
        if (! ($user?->can('update', $pk) ?? false)) {
            return false;
        }

        $lampiran = $this->input('lampiran');
        if (! empty($lampiran) && is_array($lampiran)) {
            return $user->can('uploadBerkas', $pk);
        }

        return true;
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $pk = $this->route('perjanjian_kinerja');

        if ($user && $pk instanceof RenstraPk) {
            $resolver = app(PermissionResolver::class);
            $updateDecision = $resolver->resolve($user, PermissionCodes::PK_UPDATE);

            $lampiran = $this->input('lampiran');
            $uploadDecision = null;
            if (! empty($lampiran) && is_array($lampiran)) {
                $uploadDecision = $resolver->resolve($user, PermissionCodes::BERKAS_UPLOAD);
            }

            $primaryDecision = ! $updateDecision->allowed ? $updateDecision : ($uploadDecision ?? $updateDecision);
            $rawAlasan = $this->input('alasan');
            $alasan = $this->sanitizeAlasan($rawAlasan);

            $nilaiLama = [
                'nomor_pk' => $pk->nomor_pk,
                'tanggal_pk' => $pk->tanggal_pk?->format('Y-m-d'),
            ];

            $nilaiBaru = array_filter([
                'nomor_pk' => $this->input('nomor_pk'),
                'tanggal_pk' => $this->input('tanggal_pk'),
                'alasan_penolakan' => ! $updateDecision->allowed ? 'pk_update_denied' : 'berkas_upload_denied',
            ], fn ($val) => $val !== null);

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'renstra_pk.ubah_ditolak',
                objekTipe: 'renstra_pk',
                objekId: $pk->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $nilaiBaru,
                alasan: $alasan,
                dasarIzin: $primaryDecision->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }

    protected function sanitizeAlasan(mixed $rawAlasan): ?string
    {
        $clean = PerjanjianKinerjaSupport::sanitizeAlasan($rawAlasan);

        return $clean !== '' ? $clean : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nomor_pk' => ['required', 'string', 'max:255'],
            'tanggal_pk' => ['required', 'date'],
            'alasan' => ['required', 'string', 'min:3', 'max:1000'],
            'expected_updated_at' => ['required', 'date'],
            ...PerjanjianKinerjaSupport::lampiranRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expected_updated_at.required' => 'Token versi Perjanjian Kinerja wajib disertakan.',
            'expected_updated_at.date' => 'Format timestamp versi tidak valid.',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => PerjanjianKinerjaSupport::validateLampiranAfter($this, $validator)];
    }
}
