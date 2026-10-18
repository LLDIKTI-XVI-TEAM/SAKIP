<?php

namespace App\Http\Requests\Kegiatan;

use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Kegiatan\GerbangKlaimKegiatan;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi sintaks tambah kegiatan dari halaman RA. Unit dan tahun kegiatan
 * diturunkan dari header RA, bukan input; status, kunci periode, dan izin di
 * bawah lock diperiksa ulang oleh `TambahKegiatanRencanaAksi`.
 */
class TambahKegiatanRencanaAksiRequest extends FormRequest
{
    private const TEKS_AMAN = 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u';

    private ?PermissionDecision $tolak = null;

    public function authorize(): bool
    {
        $header = $this->route('rencanaAksi');
        $user = $this->user();
        if (! $user instanceof User || ! $header instanceof RencanaAksi) {
            return false;
        }
        $unit = (string) $header->unit_id;
        $this->tolak = app(GerbangKlaimKegiatan::class)->periksaIzin($user, $unit, $unit, PermissionCodes::KEGIATAN_CREATE)['tolak'];

        return $this->tolak === null;
    }

    /** Request langsung yang ditolak tetap tercatat (percobaan tindakan ditolak). */
    protected function failedAuthorization(): void
    {
        $header = $this->route('rencanaAksi');
        $user = $this->user();
        if ($user instanceof User && $header instanceof RencanaAksi && $this->tolak instanceof PermissionDecision) {
            app(AuditLogger::class)->catat(
                actor: $user, tindakan: 'kegiatan.buat_ditolak', objekTipe: 'rencana_aksi', objekId: (string) $header->id,
                alasan: 'Percobaan menambah kegiatan dari rencana aksi ditolak oleh sistem otorisasi.', dasarIzin: $this->tolak->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'periode_id' => ['required', 'uuid'],
            'salin_ke_periode_ids' => ['nullable', 'array', 'max:20'],
            'salin_ke_periode_ids.*' => ['uuid', 'distinct', 'different:periode_id'],
            'nama' => ['required', 'string', 'max:500', self::TEKS_AMAN],
            'tujuan' => ['nullable', 'string', 'max:10000', self::TEKS_AMAN],
            'sasaran_peserta' => ['nullable', 'integer', 'between:0,2147483647'],
            'keterangan_peserta' => ['nullable', 'string', 'max:255', self::TEKS_AMAN],
            'lokasi' => ['nullable', 'string', 'max:255', self::TEKS_AMAN],
            'tanggal_rencana' => ['nullable', 'date_format:Y-m-d'],
            'komponen_id' => ['nullable', 'uuid'],
            'arah_dampak' => ['required', 'in:menambah,mengurangi'],
            'catatan' => ['nullable', 'string', 'max:255', self::TEKS_AMAN],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'periode_id.required' => 'Pilih periode kegiatan.',
            'salin_ke_periode_ids.*.different' => 'Periode salinan harus berbeda dari periode kegiatan.',
            'salin_ke_periode_ids.*.distinct' => 'Periode salinan tidak boleh berulang.',
            'nama.required' => 'Nama kegiatan wajib diisi.',
            'sasaran_peserta.between' => 'Sasaran peserta harus berupa jumlah nonnegatif yang dapat disimpan.',
            'arah_dampak.in' => 'Arah dampak harus menambah atau mengurangi.',
            'catatan.max' => 'Catatan klaim tidak boleh melebihi 255 karakter.',
            '*.not_regex' => 'Teks mengandung karakter kontrol yang tidak dapat disimpan.',
        ];
    }
}
