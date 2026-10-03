<?php

namespace App\Http\Requests\Indikator;

use App\Models\IndikatorKinerja;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Kinerja\KomponenMutationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreIndikatorKomponenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user()?->fresh();

        return $user !== null && app(PermissionResolver::class)->allows($user, 'komponen:create');
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user()?->fresh();
        if ($user) {
            $decision = app(PermissionResolver::class)->decide($user, 'komponen:create');
            $rawAlasan = $this->input('alasan');
            $kode = is_string($this->input('kode')) ? trim($this->input('kode')) : '';
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== ''
                ? trim($rawAlasan)
                : 'Percobaan penambahan komponen indikator ditolak karena tidak memiliki izin.'.($kode !== '' ? ' (Kode: '.$kode.')' : '');

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'komponen.buat_ditolak',
                objekTipe: 'indikator_komponen',
                objekId: (string) Str::uuid(),
                alasan: $alasan,
                dasarIzin: $decision,
            );
        }

        parent::failedAuthorization();
    }

    /**
     * Aturan sintaks delegasi ke validator bersama agar tidak drift dengan
     * jalur transisi formula (bentuk error key flat dipertahankan).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $indikator = $this->route('indikator');
        $indikatorId = $indikator instanceof IndikatorKinerja ? $indikator->id : (is_string($indikator) ? $indikator : null);

        return array_merge(
            app(KomponenMutationService::class)->aturanItem($indikatorId),
            ['expected_updated_at' => ['required', 'date']]
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $indikator = $this->route('indikator');
            $indikatorModel = $indikator instanceof IndikatorKinerja ? $indikator : IndikatorKinerja::find($indikator);

            if ($indikatorModel?->tipe_perhitungan === 'manual') {
                $validator->errors()->add('indikator', 'Indikator bertipe manual tidak menggunakan komponen perhitungan.');
            }

            app(KomponenMutationService::class)->tambahErrorPenyebutBilaNol(
                $validator,
                ['peran' => $this->input('peran'), 'bobot' => $this->input('bobot')],
                'bobot'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(
            app(KomponenMutationService::class)->pesanItem(),
            [
                'expected_updated_at.required' => 'Timestamp versi wajib disertakan. Muat ulang halaman untuk mendapatkan data terkini.',
                'expected_updated_at.date' => 'Format timestamp versi tidak valid.',
            ]
        );
    }
}
