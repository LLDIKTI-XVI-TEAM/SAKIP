<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\Berkas;
use App\Models\RencanaAksi;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;

class DestroyRencanaAksiEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ra = $this->route('rencana_aksi');
        if (is_string($ra)) {
            $ra = RencanaAksi::find($ra);
        }

        return $ra instanceof RencanaAksi && ($this->user()?->can('deleteEvidence', $ra) ?? false);
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $ra = $this->route('rencana_aksi');
        if (is_string($ra)) {
            $ra = RencanaAksi::find($ra);
        }

        $bukti = $this->route('bukti');
        if (is_string($bukti)) {
            $bukti = Berkas::find($bukti);
        }

        if ($user && $ra instanceof RencanaAksi) {
            $resolver = app(PermissionResolver::class);
            $raDecision = $resolver->resolve($user, PermissionCodes::RENCANA_AKSI_UPDATE, $ra->targetUnitId());
            $berkasDecision = $resolver->resolve($user, PermissionCodes::BERKAS_DELETE);

            $primaryDecision = ! $raDecision->allowed ? $raDecision : $berkasDecision;

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'berkas.hapus_ditolak',
                objekTipe: 'berkas',
                objekId: $bukti instanceof Berkas ? $bukti->id : $ra->id,
                nilaiLama: null,
                nilaiBaru: [
                    'alasan_penolakan' => ! $raDecision->allowed ? 'rencana_aksi_update_denied' : 'berkas_delete_denied',
                ],
                alasan: (string) ($this->input('alasan') ?: 'Percobaan penghapusan bukti dukung Rencana Aksi tanpa izin.'),
                dasarIzin: $primaryDecision->toAuditBasis(),
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
            'alasan' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan penghapusan bukti dukung wajib diisi.',
            'alasan.min' => 'Alasan penghapusan bukti dukung minimal 3 karakter.',
            'alasan.max' => 'Alasan penghapusan bukti dukung tidak boleh melebihi 1000 karakter.',
        ];
    }
}
