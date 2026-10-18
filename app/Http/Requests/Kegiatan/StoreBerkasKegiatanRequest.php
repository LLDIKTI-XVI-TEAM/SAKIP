<?php

namespace App\Http\Requests\Kegiatan;

use App\Models\Kegiatan;
use App\Models\User;
use App\Services\Kegiatan\GerbangBerkasKegiatan;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;

class StoreBerkasKegiatanRequest extends FormRequest
{
    private ?PermissionDecision $tolak = null;

    public function authorize(): bool
    {
        $kegiatan = $this->route('kegiatan');
        $user = $this->user();
        if (! $user instanceof User || ! $kegiatan instanceof Kegiatan) {
            return false;
        }
        $this->tolak = app(GerbangBerkasKegiatan::class)->periksaIzin($user, $kegiatan, PermissionCodes::BERKAS_UPLOAD)['tolak'];

        return $this->tolak === null;
    }

    protected function failedAuthorization(): void
    {
        $kegiatan = $this->route('kegiatan');
        $user = $this->user();
        if ($user instanceof User && $kegiatan instanceof Kegiatan && $this->tolak instanceof PermissionDecision) {
            app(GerbangBerkasKegiatan::class)->catatTolakTepi($user, $kegiatan, $this->tolak, 'berkas.unggah_ditolak');
        }

        parent::failedAuthorization();
    }

    /**
     * Sintaks per mode; batas file bergantung persyaratan sehingga divalidasi Action setelah persyaratan dikunci.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:file,tautan,teks'],
            'jenis_berkas_id' => ['required', 'uuid'],
            'file' => ['exclude_unless:mode,file', 'required', 'file'],
            'tautan' => ['exclude_unless:mode,tautan', 'required', 'string', 'url:http,https', 'max:2048'],
            'isi_teks' => ['exclude_unless:mode,teks', 'required', 'string', 'max:10000', AuditReason::validate(...)],
            'menggantikan_id' => ['nullable', 'uuid'],
            'alasan_koreksi' => ['nullable', 'string', 'max:1000', AuditReason::validate(...)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mode.required' => 'Mode berkas wajib dipilih.',
            'mode.in' => 'Mode berkas harus berupa salah satu dari: file, tautan, teks.',
            'jenis_berkas_id.required' => 'Pilih persyaratan yang dipenuhi.',
            'file.required' => 'Berkas wajib diunggah.',
            'tautan.required' => 'Tautan wajib diisi.',
            'tautan.url' => 'Tautan harus berskema http atau https.',
            'isi_teks.required' => 'Keterangan teks wajib diisi.',
        ];
    }
}
