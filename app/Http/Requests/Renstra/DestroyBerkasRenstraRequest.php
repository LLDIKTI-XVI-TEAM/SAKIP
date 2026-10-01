<?php

namespace App\Http\Requests\Renstra;

use App\Models\Berkas;
use App\Models\Renstra;
use App\Models\User;
use App\Policies\RenstraPolicy;
use App\Services\AuditLogger;
use App\Support\AuditReason;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;

class DestroyBerkasRenstraRequest extends FormRequest
{
    private ?PermissionDecision $initialDecision = null;

    public function authorize(): bool
    {
        $renstra = $this->route('renstra');
        $berkas = $this->route('berkas');

        if (! ($renstra instanceof Renstra) || ! ($berkas instanceof Berkas)) {
            return false;
        }

        if ($berkas->berkasable_type !== $renstra->getMorphClass() || $berkas->berkasable_id !== $renstra->id) {
            abort(404, 'Lampiran tidak terkait dengan Renstra ini.');
        }

        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }
        $this->initialDecision = app(RenstraPolicy::class)->deleteAttachmentDecision($user);

        return $this->initialDecision->allowed;
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $berkas = $this->route('berkas');

        if ($user instanceof User && $berkas instanceof Berkas && $this->initialDecision !== null) {
            $decision = $this->initialDecision;

            $alasan = mb_substr(trim(AuditReason::sanitize($this->input('alasan'))), 0, 1000);

            $metadata = [
                'id' => $berkas->id,
                'mode' => $berkas->mode,
                'jenis_berkas_id' => $berkas->jenis_berkas_id,
            ];

            if ($berkas->mode === 'file') {
                $metadata += [
                    'nama_asli' => $berkas->nama_asli,
                    'mime' => $berkas->mime,
                    'ukuran_bytes' => $berkas->ukuran_bytes,
                ];
            } elseif ($berkas->mode === 'tautan') {
                $metadata['tautan'] = $berkas->tautan;
            } else {
                $metadata['panjang_teks'] = mb_strlen((string) $berkas->isi_teks);
            }

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'berkas.hapus_ditolak',
                objekTipe: 'berkas',
                objekId: $berkas->id,
                nilaiLama: $metadata,
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
            'alasan.required' => 'Alasan penghapusan lampiran wajib diisi.',
            'alasan.min' => 'Alasan penghapusan lampiran minimal 5 karakter.',
        ];
    }
}
