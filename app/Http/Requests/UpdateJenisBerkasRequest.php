<?php

namespace App\Http\Requests;

use App\Models\JenisBerkas;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            $rawAlasan = $this->input('alasan');
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== ''
                ? trim($rawAlasan)
                : 'Percobaan pembaruan persyaratan jenis berkas ditolak karena tidak memiliki izin.';

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'jenis_berkas.ubah_ditolak',
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
        $currentId = (string) ($this->route('id') ?? $this->route('jenis_berkas') ?? '');

        return [
            'nama' => ['required', 'string', 'max:255'],
            'tahap' => ['required', 'in:pengukuran'],
            'indikator_id' => [
                'nullable',
                'uuid',
                Rule::exists('indikator_kinerjas', 'id')->where(function ($query) use ($currentId) {
                    $currentIndikatorId = JenisBerkas::where('id', $currentId)->value('indikator_id');
                    $query->where('is_aktif', true);
                    if ($currentIndikatorId) {
                        $query->orWhere('id', $currentIndikatorId);
                    }
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
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
            'expected_updated_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'tahap.in' => 'Tahap saat ini hanya mendukung Pengukuran Kinerja karena gerbang bukti tahap lain belum diimplementasikan.',
            'indikator_id.exists' => 'Indikator kinerja yang dipilih tidak valid atau sudah dinonaktifkan.',
            'format_diizinkan.regex' => 'Format file yang diizinkan harus berupa daftar ekstensi tanpa spasi atau titik dan dipisahkan dengan koma (contoh: pdf,docx,xlsx).',
            'expected_updated_at.date' => 'Format timestamp versi tidak valid.',
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

            // Pemisahan kewenangan (document/SAKIP - Workflow.md:1527-1529):
            // Format dan ukuran unggahan per persyaratan adalah wewenang pemegang pengaturan:update.
            $currentId = (string) ($this->route('id') ?? $this->route('jenis_berkas') ?? '');
            $currentRecord = $currentId ? JenisBerkas::find($currentId) : null;

            if ($currentRecord) {
                $newFormat = $this->input('format_diizinkan');
                if ($newFormat === '') {
                    $newFormat = null;
                }
                $currentFormat = $currentRecord->format_diizinkan !== null ? (string) $currentRecord->format_diizinkan : null;

                $newSize = $this->input('ukuran_maks_kb');
                if ($newSize === '' || $newSize === null) {
                    $newSize = null;
                } else {
                    $newSize = (int) $newSize;
                }
                $currentSize = $currentRecord->ukuran_maks_kb !== null ? (int) $currentRecord->ukuran_maks_kb : null;

                $formatChanged = $this->has('format_diizinkan') && $newFormat !== $currentFormat;
                $sizeChanged = $this->has('ukuran_maks_kb') && $newSize !== $currentSize;

                if ($formatChanged || $sizeChanged) {
                    $user = $this->user()?->fresh();
                    $canManageSettings = $user !== null && app(PermissionResolver::class)->allows($user, 'pengaturan:update');
                    if (! $canManageSettings) {
                        if ($formatChanged) {
                            $validator->errors()->add('format_diizinkan', 'Perubahan batas format unggahan memerlukan izin pengaturan:update.');
                        }
                        if ($sizeChanged) {
                            $validator->errors()->add('ukuran_maks_kb', 'Perubahan batas ukuran unggahan memerlukan izin pengaturan:update.');
                        }
                    }
                }
            }
        });
    }
}
