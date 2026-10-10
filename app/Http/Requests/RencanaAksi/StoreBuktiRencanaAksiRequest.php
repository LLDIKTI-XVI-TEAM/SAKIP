<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;

class StoreBuktiRencanaAksiRequest extends FormRequest
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
        $this->tolak = app(GerbangBuktiRencanaAksi::class)->periksaIzin($user, $header, PermissionCodes::BERKAS_UPLOAD)['tolak'];

        return $this->tolak === null;
    }

    /** Request unggah langsung yang ditolak tetap tercatat (Data Model: percobaan tindakan ditolak). */
    protected function failedAuthorization(): void
    {
        $header = $this->route('rencanaAksi');
        $user = $this->user();
        if ($user instanceof User && $header instanceof RencanaAksi && $this->tolak instanceof PermissionDecision) {
            app(GerbangBuktiRencanaAksi::class)->catatTolakTepi($user, $header, $this->tolak, 'berkas.unggah_ditolak');
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
            // Field mode lain dibuang (pola Regulasi) agar nilai tertinggal tidak memicu 422 pada field tersembunyi.
            'file' => ['exclude_unless:mode,file', 'required', 'file'],
            'tautan' => ['exclude_unless:mode,tautan', 'required', 'string', 'url:http,https', 'max:2048'],
            // Batas sama dengan bukti Pengukuran/Regulasi; karakter kontrol ditolak sebelum mencapai PostgreSQL.
            'isi_teks' => ['exclude_unless:mode,teks', 'required', 'string', 'max:10000', AuditReason::validate(...)],
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
            'file.required' => 'Berkas bukti dukung wajib diunggah.',
            'tautan.required' => 'Tautan bukti dukung wajib diisi.',
            'tautan.url' => 'Tautan harus berskema http atau https.',
            'isi_teks.required' => 'Keterangan teks wajib diisi.',
        ];
    }
}
