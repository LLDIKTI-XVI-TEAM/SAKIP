<?php

namespace App\Http\Requests;

use App\Models\JenisBerkas;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBatasTeknisJenisBerkasRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user()?->fresh();

        return $user !== null && app(PermissionResolver::class)->allows($user, 'pengaturan:update');
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user()?->fresh();
        if ($user) {
            $id = (string) ($this->route('id') ?? '');
            $decision = app(PermissionResolver::class)->decide($user, 'pengaturan:update');
            $rawAlasan = $this->input('alasan');
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== ''
                ? trim($rawAlasan)
                : 'Percobaan pembaruan batas teknis jenis berkas ditolak karena tidak memiliki izin pengaturan:update.';

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'jenis_berkas.batas_teknis_ubah_ditolak',
                objekTipe: 'jenis_berkas',
                objekId: $id,
                alasan: $alasan,
                dasarIzin: $decision,
            );
        }

        parent::failedAuthorization();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('format_diizinkan')) {
            $format = $this->input('format_diizinkan');
            if (is_string($format)) {
                $tokens = array_filter(array_map('trim', explode(',', strtolower($format))), fn ($t) => $t !== '');
                $this->merge([
                    'format_diizinkan' => ! empty($tokens) ? implode(',', $tokens) : null,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'format_diizinkan' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(,[a-z0-9]+)*$/'],
            'ukuran_maks_kb' => ['nullable', 'integer', 'min:100', 'max:2147483647'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
            'expected_updated_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'format_diizinkan.regex' => 'Format file yang diizinkan harus berupa daftar ekstensi tanpa spasi atau titik dan dipisahkan dengan koma (contoh: pdf,docx,xlsx).',
            'expected_updated_at.date' => 'Format timestamp versi tidak valid.',
            'ukuran_maks_kb.min' => 'Batas ukuran berkas minimal 100 KB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // SAKIP - Workflow.md:1480-1483 & 1513: Kolom substantif DITOLAK pada jalur batas teknis
            $substantiveFields = [
                'nama',
                'tahap',
                'indikator_id',
                'wajib',
                'keterangan',
                'izinkan_file',
                'izinkan_tautan',
                'izinkan_teks',
                'semua_mode_wajib',
                'urutan',
                'aktif',
            ];

            foreach ($substantiveFields as $field) {
                if ($this->has($field)) {
                    $validator->errors()->add($field, 'Kolom substantif bukan wewenang pembaruan batas teknis.');
                }
            }

            // SAKIP - Workflow.md:1487 & 1517: format_diizinkan WAJIB terisi bila izinkan_file = true
            $id = (string) ($this->route('id') ?? '');
            if ($id !== '') {
                $jb = JenisBerkas::find($id);
                if ($jb && $jb->izinkan_file && $this->has('format_diizinkan')) {
                    $format = $this->input('format_diizinkan');
                    if ($format === null || ! is_string($format) || trim($format) === '') {
                        $validator->errors()->add('format_diizinkan', 'Format file wajib diisi jika mode file diizinkan pada persyaratan ini.');
                    }
                }
            }
        });
    }
}
