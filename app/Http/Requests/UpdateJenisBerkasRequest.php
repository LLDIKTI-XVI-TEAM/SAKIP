<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateJenisBerkasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('jenis_berkas:update');
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'tahap' => ['required', 'in:rencana_aksi,pengukuran,kegiatan'],
            'indikator_id' => ['nullable', 'exists:indikator_kinerjas,id'],
            'wajib' => ['boolean'],
            'keterangan' => ['nullable', 'string'],
            'izinkan_file' => ['boolean'],
            'izinkan_tautan' => ['boolean'],
            'izinkan_teks' => ['boolean'],
            'semua_mode_wajib' => ['boolean'],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'format_diizinkan' => ['nullable', 'string', 'max:255'],
            'ukuran_maks_kb' => ['nullable', 'integer', 'min:100'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $izinkanFile = filter_var($this->input('izinkan_file', false), FILTER_VALIDATE_BOOLEAN);
            $izinkanTautan = filter_var($this->input('izinkan_tautan', false), FILTER_VALIDATE_BOOLEAN);
            $izinkanTeks = filter_var($this->input('izinkan_teks', false), FILTER_VALIDATE_BOOLEAN);

            if (! $izinkanFile && ! $izinkanTautan && ! $izinkanTeks) {
                $validator->errors()->add('modes', 'Minimal satu mode bukti (file, tautan, atau teks) harus diizinkan.');
            }
        });
    }
}
