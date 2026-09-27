<?php

namespace App\Http\Requests\PerjanjianKinerja;

use App\Models\RenstraPk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePerjanjianKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pk = $this->route('perjanjian_kinerja');

        return $pk instanceof RenstraPk
            ? ($this->user()?->can('update', $pk) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nomor_pk' => ['required', 'string', 'max:255'],
            'tanggal_pk' => ['required', 'date'],
            'alasan' => ['required', 'string', 'min:3', 'max:1000'],
            'lampiran' => ['nullable', 'array'],
            'lampiran.*.mode' => ['required_with:lampiran', Rule::in(['file', 'tautan', 'teks'])],
            'lampiran.*.file' => ['nullable', 'file'],
            'lampiran.*.tautan' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'lampiran.*.isi_teks' => ['nullable', 'string', 'max:10000'],
            'lampiran.*.nama_asli' => ['nullable', 'string', 'max:255'],
        ];
    }
}
