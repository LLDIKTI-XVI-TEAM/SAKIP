<?php

namespace App\Http\Requests;

use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateJenisBerkasRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user()?->fresh();

        return $user !== null && app(PermissionResolver::class)->allows($user, 'jenis_berkas:update');
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user()?->fresh();
        if ($user) {
            $id = (string) ($this->route('id') ?? $this->route('jenis_berkas') ?? '');
            $decision = app(PermissionResolver::class)->decide($user, 'jenis_berkas:update');
            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'jenis_berkas.ubah_ditolak',
                objekTipe: 'jenis_berkas',
                objekId: $id,
                alasan: $this->input('alasan') ?: 'Percobaan pembaruan persyaratan jenis berkas ditolak karena tidak memiliki izin.',
                dasarIzin: $decision,
            );
        }

        parent::failedAuthorization();
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'tahap' => ['required', 'in:rencana_aksi,pengukuran,kegiatan'],
            'indikator_id' => ['nullable', 'uuid', 'exists:indikator_kinerjas,id'],
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
            'expected_updated_at' => ['nullable', 'string'],
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
