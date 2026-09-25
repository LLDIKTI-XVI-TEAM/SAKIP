<?php

namespace App\Http\Requests\Renstra;

use App\Models\Berkas;
use App\Models\Renstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyBerkasRenstraRequest extends FormRequest
{
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

        return Gate::allows('deleteAttachment', [$renstra, $berkas]);
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $berkas = $this->route('berkas');

        if ($user instanceof User && $berkas instanceof Berkas) {
            $resolver = app(PermissionResolver::class);
            $parentDelete = $resolver->resolve($user, PermissionCodes::RENSTRA_DELETE);
            $parentUpdate = $resolver->resolve($user, PermissionCodes::RENSTRA_UPDATE);

            if (! $parentDelete->allowed && ! $parentUpdate->allowed) {
                $decision = $parentDelete;
            } else {
                $decision = $resolver->resolve($user, PermissionCodes::BERKAS_DELETE);
            }

            $rawAlasan = $this->input('alasan');
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== '' ? trim($rawAlasan) : null;

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
