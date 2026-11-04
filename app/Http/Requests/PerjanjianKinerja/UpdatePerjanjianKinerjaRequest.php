<?php

namespace App\Http\Requests\PerjanjianKinerja;

use App\Models\RenstraPk;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaSupport;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePerjanjianKinerjaRequest extends FormRequest
{
    /** Penolakan pratinjau tidak mencapai Action, sehingga dicatat sebelum respons validasi dikirim. */
    protected function failedValidation(ValidatorContract $validator): void
    {
        $pk = $this->route('perjanjian_kinerja');
        $actor = $this->user();
        if ($actor && $pk instanceof RenstraPk && array_intersect(['alasan', 'rujukan'], $validator->errors()->keys()) !== []) {
            $decision = app(PermissionResolver::class)->resolve($actor, PermissionCodes::PK_UPDATE);
            app(AuditLogger::class)->catat(actor: $actor, tindakan: 'renstra_pk.ubah_ditolak',
                objekTipe: 'renstra_pk', objekId: $pk->id,
                nilaiBaru: ['alasan_penolakan' => 'input_tidak_valid', 'kolom' => $validator->errors()->keys()],
                alasan: $this->sanitizeAlasan($this->input('alasan')), dasarIzin: $decision->toAuditBasis());
        }
        parent::failedValidation($validator);
    }

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

            $nilaiBaru = PerjanjianKinerjaSupport::boundDeniedMetadata([
                'nomor_pk' => $this->input('nomor_pk'),
                'tanggal_pk' => $this->input('tanggal_pk'),
                'alasan_penolakan' => ! $updateDecision->allowed ? 'pk_update_denied' : 'berkas_upload_denied',
            ]);

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
            'nomor_pk' => ['nullable', 'string', 'max:255'],
            'tanggal_pk' => ['required', 'date'],
            // Opsional sebelum PK dipakai jadwal aktif; sesudahnya wajib (Q43 §43.2 butir 3, §43.3 butir 3).
            'alasan' => ['nullable', 'string', 'max:1000', AuditReason::validate(...)],
            'rujukan' => ['nullable', 'string', 'max:255', AuditReason::validate(...)],
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
        return [
            function (Validator $validator) {
                $pk = $this->route('perjanjian_kinerja');
                $alasan = PerjanjianKinerjaSupport::sanitizeAlasan($this->input('alasan'));

                // Pratinjau batas mutasi: Action memeriksa ulang di dalam transaksi setelah baris jadwal dikunci.
                if ($pk instanceof RenstraPk && $alasan === '' && $pk->dipakaiJadwalAktif()) {
                    $validator->errors()->add('alasan', 'Alasan perubahan Perjanjian Kinerja wajib diisi karena PK sudah dipakai jadwal aktif.');
                }
                if ($pk instanceof RenstraPk && ! $validator->errors()->hasAny(['nomor_pk', 'tanggal_pk'])
                    && $pk->dipakaiJadwalAktif() && PerjanjianKinerjaSupport::metadataBerubah($pk, $this->only(['nomor_pk', 'tanggal_pk']))
                    && trim(AuditReason::sanitize($this->input('rujukan'))) === '') {
                    $validator->errors()->add('rujukan', 'Rujukan dokumen resmi wajib diisi karena nomor atau tanggal PK yang sudah dipakai diubah.');
                }
            },
            function (Validator $validator) {
                $lampiran = (array) $this->input('lampiran', []);
                $files = [];
                foreach (array_keys($lampiran) as $index) {
                    $files[$index] = $this->file("lampiran.{$index}.file");
                }
                PerjanjianKinerjaSupport::validateLampiranItems($lampiran, $files, $validator);
            },
        ];
    }
}
