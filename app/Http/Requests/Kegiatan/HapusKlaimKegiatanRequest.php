<?php

namespace App\Http\Requests\Kegiatan;

use App\Models\Kegiatan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Kegiatan\GerbangKlaimKegiatan;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Hapus klaim tahap RA wajib beralasan (PRD §16.5). Aturan pembuat klaim
 * atau Perencanaan serta status RA diperiksa di bawah lock oleh `HapusKlaimKegiatan`.
 */
class HapusKlaimKegiatanRequest extends FormRequest
{
    private ?PermissionDecision $tolak = null;

    public function authorize(): bool
    {
        $kegiatan = $this->route('kegiatan');
        $user = $this->user();
        if (! $user instanceof User || ! $kegiatan instanceof Kegiatan) {
            return false;
        }
        $unit = (string) $kegiatan->unit_id;
        $this->tolak = app(GerbangKlaimKegiatan::class)->periksaIzin($user, $unit, $unit, PermissionCodes::KEGIATAN_UPDATE)['tolak'];

        return $this->tolak === null;
    }

    /** Request langsung yang ditolak tetap tercatat (percobaan tindakan ditolak). */
    protected function failedAuthorization(): void
    {
        $kegiatan = $this->route('kegiatan');
        $user = $this->user();
        if ($user instanceof User && $kegiatan instanceof Kegiatan && $this->tolak instanceof PermissionDecision) {
            app(AuditLogger::class)->catat(
                actor: $user, tindakan: 'klaim_kegiatan.hapus_ditolak', objekTipe: 'klaim_kegiatan', objekId: (string) $this->route('klaim'),
                alasan: 'Percobaan hapus klaim kegiatan ditolak oleh sistem otorisasi.', dasarIzin: $this->tolak->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Teks rusak ditolak, bukan diganti diam-diam oleh sanitasi audit.
            'alasan' => ['required', 'string', 'min:3', 'max:1000', AuditReason::validate(...)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan penghapusan klaim wajib diisi.',
            'alasan.min' => 'Alasan penghapusan klaim minimal 3 karakter.',
            'alasan.max' => 'Alasan penghapusan klaim tidak boleh melebihi 1000 karakter.',
        ];
    }
}
