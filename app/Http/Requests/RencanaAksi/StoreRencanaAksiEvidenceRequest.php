<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\RencanaAksi;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;

class StoreRencanaAksiEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ra = $this->route('rencana_aksi');

        return $ra instanceof RencanaAksi && ($this->user()?->can('uploadEvidence', $ra) ?? false);
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $ra = $this->route('rencana_aksi');

        if ($user && $ra instanceof RencanaAksi) {
            $resolver = app(PermissionResolver::class);
            $raDecision = $resolver->resolve($user, PermissionCodes::RENCANA_AKSI_UPDATE, $ra->targetUnitId());
            $berkasDecision = $resolver->resolve($user, PermissionCodes::BERKAS_UPLOAD);

            $primaryDecision = ! $raDecision->allowed ? $raDecision : $berkasDecision;

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'berkas.unggah_ditolak',
                objekTipe: 'rencana_aksi',
                objekId: $ra->id,
                nilaiLama: null,
                nilaiBaru: [
                    'alasan_penolakan' => ! $raDecision->allowed ? 'rencana_aksi_update_denied' : 'berkas_upload_denied',
                ],
                alasan: 'Percobaan penambahan bukti dukung pada Rencana Aksi tanpa izin yang memadai.',
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
            'mode' => ['required', 'string', 'in:file,tautan,teks'],
            'jenis_berkas_id' => ['nullable', 'uuid', 'exists:jenis_berkas,id'],
            'menggantikan_id' => ['nullable', 'uuid', 'exists:berkas,id'],
            'alasan_koreksi' => ['nullable', 'string', 'max:1000'],
            'file' => ['nullable', 'file'],
            'tautan' => ['nullable', 'string', 'max:2048'],
            'isi_teks' => ['nullable', 'string', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.required' => 'Mode bukti dukung wajib dipilih.',
            'mode.in' => 'Mode bukti dukung harus berupa salah satu dari: file, tautan, teks.',
            'jenis_berkas_id.exists' => 'Persyaratan jenis berkas tidak valid.',
            'menggantikan_id.exists' => 'Bukti pendahulu tidak ditemukan.',
            'alasan_koreksi.max' => 'Alasan koreksi tidak boleh melebihi 1000 karakter.',
        ];
    }
}
