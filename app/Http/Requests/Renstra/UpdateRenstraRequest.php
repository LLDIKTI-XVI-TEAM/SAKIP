<?php

namespace App\Http\Requests\Renstra;

use App\Models\Renstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Gate;

class UpdateRenstraRequest extends RenstraMutationRequest
{
    public function authorize(): bool
    {
        $renstra = $this->route('renstra');
        $user = $this->user();

        return $renstra instanceof Renstra
            && $user instanceof User
            && Gate::allows('update', $renstra)
            && $this->relatedPermissionsAllowed($user);
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $renstra = $this->route('renstra');

        if ($user instanceof User && $renstra instanceof Renstra) {
            $decision = $this->deniedRelatedDecision
                ?? app(PermissionResolver::class)->resolve($user, PermissionCodes::RENSTRA_UPDATE);
            $rawAlasan = $this->input('alasan');
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== '' ? mb_substr(trim($rawAlasan), 0, 1000) : null;

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'renstra.ubah_ditolak',
                objekTipe: 'renstra',
                objekId: $renstra->id,
                nilaiLama: $renstra->masterAttributes(),
                nilaiBaru: $this->deniedRelatedReason === null ? null : ['alasan_penolakan' => $this->deniedRelatedReason],
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
        $renstra = $this->route('renstra');
        $isAktif = $renstra instanceof Renstra && ($renstra->status === Renstra::STATUS_AKTIF || $renstra->is_aktif);

        return $this->mutationRules(requireReason: $isAktif) + [
            'expected_state' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'nomor_kebijakan' => [$isAktif ? 'required' : 'nullable', 'string', 'max:255'],
            'tanggal_kebijakan' => [$isAktif ? 'required' : 'nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'expected_state.required' => 'Data Renstra belum tersedia. Muat data terbaru.',
            'expected_state.regex' => 'Data Renstra tidak valid. Muat data terbaru.',
            'nomor_kebijakan.required' => 'Nomor kebijakan/Kepmen wajib diisi.',
            'nomor_kebijakan.max' => 'Nomor kebijakan maksimal 255 karakter.',
            'tanggal_kebijakan.required' => 'Tanggal kebijakan/Kepmen wajib diisi.',
            'tanggal_kebijakan.date_format' => 'Tanggal kebijakan harus berupa tanggal kalender yang valid.',
        ];
    }

    /** Audit input gagal tanpa menyimpan payload mentah atau token state. */
    protected function failedValidation(Validator $validator): void
    {
        $actor = $this->user();
        $renstra = $this->route('renstra');
        if ($actor instanceof User && $renstra instanceof Renstra) {
            app(AuditLogger::class)->catat(
                actor: $actor,
                tindakan: 'renstra.ubah_ditolak',
                objekTipe: 'renstra',
                objekId: $renstra->id,
                nilaiLama: $renstra->masterAttributes(),
                nilaiBaru: ['field_tidak_valid' => array_keys($validator->errors()->messages())],
                alasan: 'Validasi input perubahan Renstra ditolak.',
                dasarIzin: app(PermissionResolver::class)->resolve($actor, PermissionCodes::RENSTRA_UPDATE)->toAuditBasis(),
            );
        }
        parent::failedValidation($validator);
    }
}
