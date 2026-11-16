<?php

namespace App\Http\Requests\Kegiatan;

use App\Models\Kegiatan;
use App\Models\User;
use App\Services\Kegiatan\GerbangBerkasKegiatan;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;

class DestroyBerkasKegiatanRequest extends FormRequest
{
    private ?PermissionDecision $tolak = null;

    public function authorize(): bool
    {
        $kegiatan = $this->route('kegiatan');
        $user = $this->user();
        if (! $user instanceof User || ! $kegiatan instanceof Kegiatan) {
            return false;
        }
        $this->tolak = app(GerbangBerkasKegiatan::class)->periksaIzin($user, $kegiatan, PermissionCodes::BERKAS_DELETE)['tolak'];

        return $this->tolak === null;
    }

    protected function failedAuthorization(): void
    {
        $kegiatan = $this->route('kegiatan');
        $user = $this->user();
        if ($user instanceof User && $kegiatan instanceof Kegiatan && $this->tolak instanceof PermissionDecision) {
            app(GerbangBerkasKegiatan::class)->catatTolakTepi($user, $kegiatan, $this->tolak, 'berkas.hapus_ditolak');
        }

        parent::failedAuthorization();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Penghapusan berkas wajib alasan (Q43 §43.2 butir 2).
        return ['alasan' => ['required', 'string', 'min:3', 'max:1000', AuditReason::validate(...)]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan penghapusan berkas wajib diisi.',
            'alasan.min' => 'Alasan penghapusan berkas minimal 3 karakter.',
        ];
    }
}
