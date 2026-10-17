<?php

namespace App\Http\Requests\Kegiatan;

use App\Models\Kegiatan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Kegiatan\GerbangKlaimKegiatan;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi sintaks klaim tahap RA. Izin awal dinilai pada unit kegiatan
 * (tepercaya dari route); unit/tahun/periode/komponen RA serta izin di
 * bawah lock diperiksa ulang oleh `TambahKlaimKegiatan`.
 */
class TambahKlaimKegiatanRequest extends FormRequest
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
                actor: $user, tindakan: 'klaim_kegiatan.tambah_ditolak', objekTipe: 'kegiatan', objekId: (string) $kegiatan->id,
                alasan: 'Percobaan klaim kegiatan ditolak oleh sistem otorisasi.', dasarIzin: $this->tolak->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rencana_aksi_id' => ['required', 'uuid'],
            'komponen_id' => ['nullable', 'uuid'],
            'arah_dampak' => ['required', 'in:menambah,mengurangi'],
            'catatan' => ['nullable', 'string', 'max:255', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rencana_aksi_id.required' => 'Pilih rencana aksi yang diklaim.',
            'arah_dampak.in' => 'Arah dampak harus menambah atau mengurangi.',
            'catatan.max' => 'Catatan klaim tidak boleh melebihi 255 karakter.',
            '*.not_regex' => 'Teks mengandung karakter kontrol yang tidak dapat disimpan.',
        ];
    }
}
