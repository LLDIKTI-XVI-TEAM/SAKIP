<?php

namespace App\Http\Requests\Pengaturan;

use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class UpdateStoragePolicyRequest extends FormRequest
{
    /** @var array{allowed: bool, permission: string, reason: string, roles: list<string>, grants: list<string>, denies: list<string>}|null */
    private ?array $authorizationDecision = null;

    public function authorize(): bool
    {
        $user = $this->user();

        $this->authorizationDecision = $user !== null
            ? app(PermissionResolver::class)->decide($user, 'pengaturan:update')
            : null;

        return $this->authorizationDecision['allowed'] ?? false;
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        if ($user !== null && $this->authorizationDecision !== null) {
            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'pengaturan.ubah_ditolak',
                objekTipe: 'pengaturan',
                objekId: (string) Str::uuid(),
                alasan: 'Percobaan pembaruan kebijakan storage ditolak karena pengguna tidak memiliki izin pengaturan:update.',
                dasarIzin: $this->authorizationDecision,
            );
        }

        throw new AuthorizationException('Anda tidak memiliki wewenang untuk mengubah kebijakan storage aplikasi (memerlukan izin pengaturan:update).');
    }

    /**
     * Boolean mengikuti semantik transport existing; representasi ukuran tervalidasi tetap dipertahankan.
     *
     * @return array{berkas_unggahan_aktif: bool, berkas_ukuran_maks_kb: int|numeric-string, berkas_format_diizinkan: string, berkas_tautan_selalu_diizinkan: bool, expected_updated_at: string, expected_version: int, alasan: string}
     */
    public function mutationData(): array
    {
        $data = $this->validated();

        return [
            'berkas_unggahan_aktif' => $this->boolean('berkas_unggahan_aktif'),
            'berkas_ukuran_maks_kb' => $data['berkas_ukuran_maks_kb'],
            'berkas_format_diizinkan' => $data['berkas_format_diizinkan'],
            'berkas_tautan_selalu_diizinkan' => $this->boolean('berkas_tautan_selalu_diizinkan'),
            'expected_updated_at' => $data['expected_updated_at'],
            'expected_version' => (int) $data['expected_version'],
            'alasan' => $data['alasan'],
        ];
    }

    /**
     * Whitelist nama field payload transport yang diizinkan (Workflow.md §18, baris 1423-1424).
     *
     * @var list<string>
     */
    public const ALLOWED_FIELDS = [
        'berkas_unggahan_aktif',
        'berkas_ukuran_maks_kb',
        'berkas_format_diizinkan',
        'berkas_tautan_selalu_diizinkan',
        'expected_updated_at',
        'expected_version',
        'alasan',
        '_token',
        '_method',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'berkas_unggahan_aktif' => ['required', 'boolean'],
            'berkas_ukuran_maks_kb' => ['required', 'integer', 'min:100', 'max:102400'],
            'berkas_format_diizinkan' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(,[a-z0-9]+)*$/'],
            'berkas_tautan_selalu_diizinkan' => ['required', 'accepted'],
            'expected_updated_at' => ['required', 'string'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * Pastikan tidak ada field di luar whitelist yang dikirimkan.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unknownKeys = array_diff(array_keys($this->all()), self::ALLOWED_FIELDS);
            if (! empty($unknownKeys)) {
                foreach ($unknownKeys as $key) {
                    $validator->errors()->add($key, "Field '{$key}' tidak diizinkan pada pembaruan kebijakan storage.");
                }
            }
        });
    }

    /**
     * Normalisasi format ekstensi sebelum divalidasi.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('berkas_format_diizinkan') && is_string($this->input('berkas_format_diizinkan'))) {
            $raw = (string) $this->input('berkas_format_diizinkan');
            // Bersihkan spasi, titik awal pada ekstensi, dan ubah ke lowercase
            $tokens = array_filter(
                array_map(fn ($ext) => strtolower(ltrim(trim($ext), '.')), explode(',', $raw)),
                fn ($ext) => $ext !== ''
            );

            $this->merge([
                'berkas_format_diizinkan' => implode(',', array_unique($tokens)),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'berkas_unggahan_aktif' => 'saklar unggahan berkas',
            'berkas_ukuran_maks_kb' => 'batas ukuran maksimum (KB)',
            'berkas_format_diizinkan' => 'format berkas yang diizinkan',
            'berkas_tautan_selalu_diizinkan' => 'ketersediaan jalur tautan & teks',
            'expected_updated_at' => 'versi timestamp kebijakan',
            'expected_version' => 'versi sekuensial kebijakan',
            'alasan' => 'alasan audit',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'boolean' => ':Attribute harus bernilai benar atau salah.',
            'accepted' => ':Attribute harus selalu bernilai aktif (true) sebagai jaminan anti-blocking pengumpulan bukti.',
            'integer' => ':Attribute harus berupa angka bilangan bulat.',
            'min' => ':Attribute minimal :min karakter/KB.',
            'max' => ':Attribute maksimal :max karakter/KB.',
            'string' => ':Attribute harus berupa teks.',
            'regex' => 'Format file yang diizinkan hanya boleh berupa daftar ekstensi alfanumerik dipisahkan koma (contoh: pdf,docx,xlsx).',
        ];
    }
}
