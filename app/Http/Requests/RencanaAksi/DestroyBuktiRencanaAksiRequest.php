<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;

class DestroyBuktiRencanaAksiRequest extends FormRequest
{
    private ?PermissionDecision $tolak = null;

    public function authorize(): bool
    {
        $header = $this->route('rencanaAksi');
        $user = $this->user();
        if (! $user instanceof User || ! $header instanceof RencanaAksi) {
            return false;
        }

        // Satu keputusan izin dipakai untuk otorisasi sekaligus dasar audit penolakan.
        $this->tolak = app(GerbangBuktiRencanaAksi::class)->periksaIzin($user, $header, PermissionCodes::BERKAS_DELETE)['tolak'];

        return $this->tolak === null;
    }

    /** Request hapus langsung yang ditolak tetap tercatat (Data Model: percobaan tindakan ditolak). */
    protected function failedAuthorization(): void
    {
        $header = $this->route('rencanaAksi');
        $user = $this->user();
        if ($user instanceof User && $header instanceof RencanaAksi && $this->tolak instanceof PermissionDecision) {
            app(GerbangBuktiRencanaAksi::class)->catatTolakTepi($user, $header, $this->tolak, 'berkas.hapus_ditolak');
        }

        parent::failedAuthorization();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Teks rusak ditolak, bukan diganti diam-diam oleh sanitasi audit.
            'alasan' => ['required', 'string', 'min:3', 'max:1000', AuditReason::validate(...)],
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
