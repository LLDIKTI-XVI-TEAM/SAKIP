<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;

class StoreBuktiRencanaAksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $header = $this->route('rencanaAksi');

        return $header instanceof RencanaAksi && ($this->user()?->can('uploadEvidence', $header) ?? false);
    }

    /** Request unggah langsung yang ditolak tetap tercatat (Data Model: percobaan tindakan ditolak). */
    protected function failedAuthorization(): void
    {
        $header = $this->route('rencanaAksi');
        $user = $this->user();
        if ($user instanceof User && $header instanceof RencanaAksi) {
            app(GerbangBuktiRencanaAksi::class)->catatTolakTepi($user, $header, PermissionCodes::BERKAS_UPLOAD, 'berkas.unggah_ditolak');
        }

        parent::failedAuthorization();
    }

    /**
     * Sintaks per mode; batas ukuran/format file bergantung persyaratan
     * sehingga divalidasi di Action setelah baris `jenis_berkas` dikunci.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:file,tautan,teks'],
            'jenis_berkas_id' => ['nullable', 'uuid'],
            'file' => ['required_if:mode,file', 'nullable', 'file'],
            'tautan' => ['required_if:mode,tautan', 'nullable', 'string', 'url:http,https', 'max:2048'],
            'isi_teks' => ['required_if:mode,teks', 'nullable', 'string', 'max:65535'],
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
            'file.required_if' => 'Berkas bukti dukung wajib diunggah.',
            'tautan.required_if' => 'Tautan bukti dukung wajib diisi.',
            'tautan.url' => 'Tautan harus berskema http atau https.',
            'isi_teks.required_if' => 'Keterangan teks wajib diisi.',
        ];
    }
}
