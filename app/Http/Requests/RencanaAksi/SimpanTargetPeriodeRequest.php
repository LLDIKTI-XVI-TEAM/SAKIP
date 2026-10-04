<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\RencanaAksi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SimpanTargetPeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        $id = $this->route('rencanaAksi');
        if (! is_string($id)) {
            return false;
        }

        $header = RencanaAksi::whereKey($id)->first();
        if (! $header instanceof RencanaAksi) {
            return true;
        }

        return Gate::allows('update', $header);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_versi' => ['required', 'integer', 'min:1'],
            'uraian' => ['nullable', 'string', 'max:10000'],
            'alasan_deviasi_pk' => ['nullable', 'string', 'max:10000'],
            'targets' => ['required', 'array', 'min:1', 'max:100'],
            'targets.*.periode_id' => ['required', 'uuid', 'exists:periode,id'],
            'targets.*.komponen_id' => ['nullable', 'uuid', 'exists:indikator_komponen,id'],
            'targets.*.nilai' => ['present', 'nullable', 'numeric', 'between:-999999999999999999,999999999999999999'],
            'targets.*.keterangan' => ['nullable', 'string', 'max:10000'],
            'status_alur' => ['prohibited'],
            'versi' => ['prohibited'],
            'unit_id' => ['prohibited'],
            'indikator_id' => ['prohibited'],
            'tahun' => ['prohibited'],
            'jadwal_tahunan_id' => ['prohibited'],
            'penanggung_jawab_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'disahkan_at' => ['prohibited'],
            'disahkan_by' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'present' => 'Kolom :attribute harus dikirim; gunakan nilai kosong untuk target yang belum diisi.',
            'nullable' => 'Kolom :attribute boleh dikosongkan.',
            'numeric' => 'Kolom :attribute harus berupa angka.',
            'between' => 'Nilai :attribute melebihi kapasitas penyimpanan.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
            'min' => 'Kolom :attribute tidak memenuhi nilai minimum.',
            'max' => 'Kolom :attribute melebihi batas yang diizinkan.',
            'uuid' => 'Identitas :attribute tidak sah.',
            'exists' => 'Data :attribute tidak ditemukan.',
            'array' => 'Struktur :attribute tidak sah.',
            'prohibited' => 'Kolom :attribute ditentukan server.',
            'string' => 'Kolom :attribute harus berupa teks.',
        ];
    }
}
