<?php

namespace App\Http\Requests;

use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJenisBerkasRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user()?->fresh();

        return $user !== null && app(PermissionResolver::class)->allows($user, 'jenis_berkas:create');
    }

    protected function prepareForValidation(): void
    {
        $merges = [];

        if (! $this->has('urutan') || $this->input('urutan') === null || $this->input('urutan') === '') {
            $merges['urutan'] = 0;
        }

        if ($this->has('format_diizinkan')) {
            $format = $this->input('format_diizinkan');
            if (is_string($format)) {
                $tokens = array_filter(array_map('trim', explode(',', strtolower($format))), fn ($t) => $t !== '');
                $merges['format_diizinkan'] = ! empty($tokens) ? implode(',', $tokens) : null;
            }
        }

        if (! empty($merges)) {
            $this->merge($merges);
        }
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'tahap' => ['required', 'in:rencana_aksi,pengukuran,kegiatan'],
            'indikator_id' => [
                'nullable',
                'uuid',
                Rule::exists('indikator_kinerjas', 'id')->where(function ($query) {
                    $query->where('is_aktif', true);
                }),
            ],
            'wajib' => ['boolean'],
            'keterangan' => ['nullable', 'string'],
            'izinkan_file' => ['boolean'],
            'izinkan_tautan' => ['boolean'],
            'izinkan_teks' => ['boolean'],
            'semua_mode_wajib' => ['boolean'],
            'urutan' => ['integer', 'min:0', 'max:2147483647'],
            'format_diizinkan' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(,[a-z0-9]+)*$/'],
            'ukuran_maks_kb' => ['nullable', 'integer', 'min:100', 'max:2147483647'],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'indikator_id.exists' => 'Indikator kinerja yang dipilih tidak valid atau sudah dinonaktifkan.',
            'format_diizinkan.regex' => 'Format file yang diizinkan harus berupa daftar ekstensi tanpa spasi atau titik dan dipisahkan dengan koma (contoh: pdf,docx,xlsx).',
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
