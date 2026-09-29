<?php

namespace App\Http\Requests\PerjanjianKinerja;

use App\Models\RenstraPk;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaSupport;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StorePerjanjianKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! ($user?->can('create', RenstraPk::class) ?? false)) {
            return false;
        }

        $lampiran = $this->input('lampiran');
        if (! empty($lampiran) && is_array($lampiran)) {
            return $user->can('uploadBerkas', RenstraPk::class);
        }

        return true;
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();

        if ($user) {
            $resolver = app(PermissionResolver::class);
            $createDecision = $resolver->resolve($user, PermissionCodes::PK_CREATE);

            $lampiran = $this->input('lampiran');
            $uploadDecision = null;
            if (! empty($lampiran) && is_array($lampiran)) {
                $uploadDecision = $resolver->resolve($user, PermissionCodes::BERKAS_UPLOAD);
            }

            $primaryDecision = ! $createDecision->allowed ? $createDecision : ($uploadDecision ?? $createDecision);
            $rawAlasan = $this->input('alasan');
            $alasan = $this->sanitizeAlasan($rawAlasan);

            $nilaiBaru = array_filter([
                'renstra_id' => $this->input('renstra_id'),
                'tahun' => $this->input('tahun'),
                'nomor_pk' => $this->input('nomor_pk'),
                'tanggal_pk' => $this->input('tanggal_pk'),
                'alasan_penolakan' => ! $createDecision->allowed ? 'pk_create_denied' : 'berkas_upload_denied',
            ], fn ($val) => $val !== null);

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'renstra_pk.buat_ditolak',
                objekTipe: 'renstra_pk',
                objekId: (string) Str::uuid(),
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
            'renstra_id' => ['required', 'uuid', 'exists:renstras,id'],
            'tahun' => ['required', 'integer'],
            'nomor_pk' => ['required', 'string', 'max:255'],
            'tanggal_pk' => ['required', 'date'],
            ...PerjanjianKinerjaSupport::lampiranRules(),
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
