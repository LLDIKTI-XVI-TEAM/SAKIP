<?php

namespace App\Http\Requests\PerjanjianKinerja;

use App\Models\RenstraPk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePerjanjianKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RenstraPk::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'renstra_id' => ['required', 'uuid', 'exists:renstras,id'],
            'tahun' => ['required', 'integer'],
            'nomor_pk' => ['required', 'string', 'max:255'],
            'tanggal_pk' => ['required', 'date'],
            'lampiran' => ['nullable', 'array'],
            'lampiran.*.mode' => ['required_with:lampiran', Rule::in(['file', 'tautan', 'teks'])],
            'lampiran.*.file' => ['nullable', 'file'],
            'lampiran.*.tautan' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'lampiran.*.isi_teks' => ['nullable', 'string', 'max:10000'],
            'lampiran.*.nama_asli' => ['nullable', 'string', 'max:255'],
        ];
    }
}
