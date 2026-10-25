<?php

namespace App\Http\Requests\JenisBerkas;

use App\Models\JenisBerkas;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Storage\UploadLimits;
use App\Support\AuditReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateJenisBerkasRequest extends FormRequest
{
    /** @var array{allowed: bool, permission: string, reason: string, roles: list<string>, grants: list<string>, denies: list<string>}|null */
    private ?array $authorizationDecision = null;

    public function authorize(): bool
    {
        $user = $this->user()?->fresh();

        $this->authorizationDecision = $user !== null
            ? app(PermissionResolver::class)->decide($user, 'jenis_berkas:update')
            : null;

        return $this->authorizationDecision['allowed'] ?? false;
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user()?->fresh();
        if ($user && $this->authorizationDecision !== null) {
            $id = (string) ($this->route('id') ?? $this->route('jenis_berkas') ?? '');
            $rawAlasan = mb_substr(trim(AuditReason::sanitize($this->input('alasan'))), 0, 1000, 'UTF-8');
            $alasan = $rawAlasan !== ''
                ? $rawAlasan
                : 'Percobaan pembaruan persyaratan jenis berkas ditolak karena tidak memiliki izin.';

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'jenis_berkas.ubah_ditolak',
                objekTipe: 'jenis_berkas',
                objekId: $id,
                alasan: $alasan,
                dasarIzin: $this->authorizationDecision,
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
            'tahap' => ['required', 'in:rencana_aksi,pengukuran,kegiatan_perencanaan'],
            // Penanda hanya bermakna pada tahap perencanaan kegiatan (Q42 §42.3 butir 3); tahap lain selalu false.
            'hanya_kegiatan_beranggaran' => ['exclude_unless:tahap,kegiatan_perencanaan', 'boolean'],
            'indikator_id' => [
                'nullable',
                'uuid',
                Rule::exists('indikator_kinerjas', 'id')->where(function ($query) use ($currentId) {
                    $currentIndikatorId = JenisBerkas::where('id', $currentId)->value('indikator_id');
                    $query->where('status', 'aktif');
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
            'ukuran_maks_kb' => ['nullable', 'integer', 'min:100', 'max:'.UploadLimits::maxKb()],
            'aktif' => ['sometimes', 'boolean'],
            'alasan' => ['nullable', 'string', 'max:1000', AuditReason::validate(...)],
            'expected_updated_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'tahap.in' => 'Tahap Kegiatan (SPJ) belum didukung karena gerbang buktinya belum diimplementasikan.',
            'indikator_id.exists' => 'Indikator kinerja yang dipilih tidak valid atau sudah diarsipkan.',
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

        });
    }
}
