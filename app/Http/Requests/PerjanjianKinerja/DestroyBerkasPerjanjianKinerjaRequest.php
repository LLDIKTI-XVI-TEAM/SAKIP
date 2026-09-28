<?php

namespace App\Http\Requests\PerjanjianKinerja;

use App\Models\Berkas;
use App\Models\RenstraPk;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;

class DestroyBerkasPerjanjianKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pk = $this->route('perjanjian_kinerja');
        $berkas = $this->route('berkas');

        return $pk instanceof RenstraPk
            && $berkas instanceof Berkas
            && in_array($berkas->berkasable_type, ['renstra_pk', RenstraPk::class], true)
            && $berkas->berkasable_id === $pk->id
            && ($this->user()?->can('deleteBerkas', $pk) ?? false);
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

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $berkas = $this->route('berkas');

        if ($user && $berkas instanceof Berkas) {
            $resolver = app(PermissionResolver::class);
            $updateDecision = $resolver->resolve($user, PermissionCodes::PK_UPDATE);
            $deleteDecision = $resolver->resolve($user, PermissionCodes::BERKAS_DELETE);

            $primaryDecision = ! $updateDecision->allowed ? $updateDecision : $deleteDecision;
            $alasan = $this->input('alasan');

            $metadata = [
                'id' => $berkas->id,
                'mode' => $berkas->mode,
                'nama_asli' => $berkas->nama_asli,
            ];

            if ($berkas->mode === 'file') {
                $metadata += [
                    'mime' => $berkas->mime,
                    'ukuran_bytes' => $berkas->ukuran_bytes,
                ];
            } elseif ($berkas->mode === 'tautan') {
                $metadata += [
                    'tautan' => $berkas->tautan,
                ];
            } else {
                $metadata += [
                    'panjang_teks' => mb_strlen((string) $berkas->isi_teks),
                ];
            }

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'berkas.hapus_ditolak',
                objekTipe: 'berkas',
                objekId: $berkas->id,
                nilaiLama: $metadata,
                nilaiBaru: ['alasan_penolakan' => 'tidak_memiliki_izin'],
                alasan: is_string($alasan) && trim($alasan) !== '' ? trim($alasan) : null,
                dasarIzin: $primaryDecision->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }
}
