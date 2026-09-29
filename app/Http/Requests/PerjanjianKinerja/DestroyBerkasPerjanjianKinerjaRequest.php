<?php

namespace App\Http\Requests\PerjanjianKinerja;

use App\Models\Berkas;
use App\Models\RenstraPk;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaSupport;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;

class DestroyBerkasPerjanjianKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pk = $this->route('perjanjian_kinerja');
        $user = $this->user();

        // Pemeriksaan kapabilitas otorisasi dilakukan pertama kali untuk mencegah ownership oracle.
        if (! $user || ! $user->can('deleteBerkas', $pk)) {
            return false;
        }

        $berkas = $this->route('berkas');

        // Untuk aktor yang berwenang, ketidaksesuaian kepemilikan menghasilkan 404 Not Found.
        if (! $pk instanceof RenstraPk
            || ! $berkas instanceof Berkas
            || $berkas->berkasable_id !== $pk->id
            || ! in_array($berkas->berkasable_type, ['renstra_pk', RenstraPk::class], true)) {
            abort(404, 'Lampiran tidak ditemukan untuk Perjanjian Kinerja ini.');
        }

        return true;
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
        $pk = $this->route('perjanjian_kinerja');
        $berkas = $this->route('berkas');

        // Audit penolakan izin hanya dicatat jika berkas benar-benar milik PK terkait.
        // Aktor tanpa izin tetap menerima respons 403, tetapi audit penolakan tidak dicatat untuk berkas asing.
        if ($user && $pk instanceof RenstraPk && $berkas instanceof Berkas
            && $berkas->berkasable_id === $pk->id
            && in_array($berkas->berkasable_type, ['renstra_pk', RenstraPk::class], true)) {
            $resolver = app(PermissionResolver::class);
            $updateDecision = $resolver->resolve($user, PermissionCodes::PK_UPDATE);
            $deleteDecision = $resolver->resolve($user, PermissionCodes::BERKAS_DELETE);

            $primaryDecision = ! $updateDecision->allowed ? $updateDecision : $deleteDecision;
            $rawAlasan = $this->input('alasan');
            $alasan = $this->sanitizeAlasan($rawAlasan);

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
                alasan: $alasan,
                dasarIzin: $primaryDecision->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }

    protected function sanitizeAlasan(mixed $rawAlasan): string
    {
        $clean = PerjanjianKinerjaSupport::sanitizeAlasan($rawAlasan);

        return $clean !== '' ? $clean : 'Tidak memiliki otorisasi';
    }
}
