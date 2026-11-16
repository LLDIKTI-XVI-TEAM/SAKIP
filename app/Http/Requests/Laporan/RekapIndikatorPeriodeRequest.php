<?php

namespace App\Http\Requests\Laporan;

use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RekapIndikatorPeriodeRequest extends FormRequest
{
    /** Gerbang laporan tetap berlaku sebelum lookup filter, termasuk request langsung. */
    public function authorize(): bool
    {
        return $this->user() !== null && app(PermissionResolver::class)->allows($this->user(), 'laporan:read');
    }

    /** Filter dibatasi jadwal/periode yang berelasi; UUID rusak tidak pernah mencapai query PostgreSQL. */
    public function rules(): array
    {
        return [
            'tahun' => ['nullable', 'integer', 'between:1,9999'],
            'jadwal_id' => ['nullable', 'uuid', Rule::exists('jadwal_tahunan', 'id')->whereIn('status', ['aktif', 'ditutup'])],
            'periode_id' => ['nullable', 'uuid', Rule::exists('jadwal_periode', 'periode_id')->when($this->filled('jadwal_id'), fn ($rule) => $rule->where('jadwal_id', $this->input('jadwal_id')))],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
