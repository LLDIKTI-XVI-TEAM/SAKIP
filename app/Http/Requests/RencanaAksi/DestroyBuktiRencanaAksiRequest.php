<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;

class DestroyBuktiRencanaAksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $header = $this->route('rencanaAksi');

        return $header instanceof RencanaAksi && ($this->user()?->can('deleteEvidence', $header) ?? false);
    }

    /** Request hapus langsung yang ditolak tetap tercatat (Data Model: percobaan tindakan ditolak). */
    protected function failedAuthorization(): void
    {
        $header = $this->route('rencanaAksi');
        $user = $this->user();
        if ($user instanceof User && $header instanceof RencanaAksi) {
            app(GerbangBuktiRencanaAksi::class)->catatTolakTepi($user, $header, PermissionCodes::BERKAS_DELETE, 'berkas.hapus_ditolak');
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
