<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class EnsureDraftRencanaAksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        $indikatorId = $this->input('indikator_id');
        if (! is_string($indikatorId) || ! Str::isUuid($indikatorId)) {
            return true;
        }

        $indikator = IndikatorKinerja::whereKey($indikatorId)->first();
        if (! $indikator instanceof IndikatorKinerja) {
            return true;
        }

        return Gate::allows('create', [RencanaAksi::class, $indikator]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'indikator_id' => ['required', 'uuid', 'exists:indikator_kinerjas,id'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'uuid' => 'Identitas :attribute tidak sah.',
            'exists' => 'Data :attribute tidak ditemukan.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
            'between' => 'Kolom :attribute berada di luar rentang tahun yang sah.',
        ];
    }
}
