<?php

namespace App\Http\Requests\Kegiatan;

use App\Models\Kegiatan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DaftarKegiatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Kegiatan::class);
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
