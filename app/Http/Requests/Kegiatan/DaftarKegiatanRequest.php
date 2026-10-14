<?php

namespace App\Http\Requests\Kegiatan;

use App\Actions\Kegiatan\DaftarKegiatan;
use App\Models\Kegiatan;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DaftarKegiatanRequest extends FormRequest
{
    private ?PermissionDecision $keputusan = null;

    public function authorize(): bool
    {
        $this->keputusan = app(PermissionResolver::class)->resolve($this->user(), 'kegiatan:read');

        return $this->keputusan->allowed;
    }

    /** Penolakan halaman memakai dasar izin server, bukan filter unit dari klien. */
    protected function failedAuthorization(): void
    {
        if ($this->keputusan) {
            app(DaftarKegiatan::class)->catatPenolakan($this->user(), $this->keputusan);
        }
        parent::failedAuthorization();
    }

    public function rules(): array
    {
        return [
            'tahun' => ['nullable', 'integer', 'between:1,9999'],
            'unit_id' => ['nullable', 'uuid', 'exists:unit,id'],
            'periode_id' => ['nullable', 'uuid', 'exists:periode,id'],
            'status' => ['nullable', Rule::in(Kegiatan::STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
