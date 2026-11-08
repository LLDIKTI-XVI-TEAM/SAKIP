<?php

namespace App\Http\Requests\PerjanjianKinerja;

use App\Actions\PerjanjianKinerja\SetujuiRevisiPerjanjianKinerja;
use App\Models\RenstraPk;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaSupport;
use App\Services\Storage\UploadLimits;
use App\Support\AuditReason;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Payload revisi PK: surat Eselon I, alasan, perubahan target opsional, lampiran file wajib, dua token, dan konfirmasi
 * pengukuran sebelumnya. Nilai dasar, periode mulai, dan kelayakan dihitung ulang Action di bawah lock.
 */
class StoreVersiPerjanjianKinerjaRequest extends FormRequest
{
    public const PESAN_IZIN = 'Anda tidak memiliki izin merevisi PK.';

    public const PESAN_FIELD = 'Permintaan memuat field yang tidak didukung.';

    /** @var array<string, PermissionDecision> */
    private array $initialDecisions = [];

    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor instanceof User) {
            return false;
        }
        foreach (SetujuiRevisiPerjanjianKinerja::IZIN as $code) {
            $this->initialDecisions[$code] = app(PermissionResolver::class)->resolve($actor, $code);
        }

        return collect($this->initialDecisions)->every(fn (PermissionDecision $decision): bool => $decision->allowed);
    }

    /** Aturan dipakai ulang di Action untuk caller selain HTTP. @return array<string, mixed> */
    public static function inputRules(): array
    {
        return [
            'expected_nomor_versi' => ['required', 'integer', 'min:0'],
            'expected_dasar' => ['required', 'string', 'size:64'],
            'nomor_surat' => ['required', 'string', 'max:255'],
            'tanggal_surat' => ['required', 'date'],
            'alasan' => ['required', 'string', 'max:1000', AuditReason::validate(...)],
            // Key `target` boleh absen: multipart tidak mengirim array kosong, dan revisi dokumen saja tidak membawa target.
            'target' => ['sometimes', 'array'],
            'target.*.indikator_id' => ['required', 'uuid', 'distinct:ignore_case'],
            'target.*.nilai' => ['required', 'string'],
            'lampiran' => ['required', 'array', 'min:1'],
            'lampiran.*.file' => ['required', ...UploadLimits::fileRules()],
            'lampiran.*.keterangan' => PerjanjianKinerjaSupport::lampiranRules()['lampiran.*.keterangan'],
            'konfirmasi_pengukuran_sebelumnya' => ['sometimes', 'boolean'],
        ];
    }

    public function rules(): array
    {
        return self::inputRules();
    }

    /** @return array<string, string> */
    public static function inputMessages(): array
    {
        return [
            'expected_nomor_versi.*' => 'Data versi PK tidak valid. Muat ulang halaman PK.',
            'expected_dasar.*' => 'Data target dasar PK tidak valid. Muat ulang halaman PK.',
            'nomor_surat.required' => 'Nomor surat persetujuan wajib diisi.',
            'nomor_surat.string' => 'Nomor surat persetujuan harus berupa teks.',
            'nomor_surat.max' => 'Nomor surat persetujuan maksimal :max karakter.',
            'tanggal_surat.required' => 'Tanggal surat persetujuan wajib diisi.',
            'tanggal_surat.date' => 'Tanggal surat persetujuan tidak valid.',
            'alasan.required' => 'Alasan revisi wajib diisi.',
            'alasan.string' => 'Alasan revisi harus berupa teks.',
            'alasan.max' => 'Alasan revisi maksimal :max karakter.',
            'target.array' => 'Daftar perubahan target tidak valid.',
            'target.*.indikator_id.required' => 'Indikator wajib dipilih.',
            'target.*.indikator_id.uuid' => 'Indikator tidak valid.',
            'target.*.indikator_id.distinct' => 'Indikator tidak boleh diisi lebih dari sekali.',
            'target.*.nilai.required' => 'Target indikator yang sudah dibekukan jadwal tidak boleh dikosongkan.',
            'target.*.nilai.string' => 'Gunakan angka desimal nonnegatif tanpa pemisah ribuan atau eksponen.',
            'lampiran.required' => 'Lampiran dokumen PK bertanda tangan wajib diunggah.',
            'lampiran.array' => 'Lampiran dokumen PK bertanda tangan wajib diunggah.',
            'lampiran.min' => 'Lampiran dokumen PK bertanda tangan wajib diunggah.',
            'lampiran.*.file.required' => 'Pilih file lampiran.',
            'lampiran.*.file.file' => 'Lampiran file tidak valid atau berkas belum diunggah.',
            'lampiran.*.file.max' => 'Ukuran file lampiran melebihi batas maksimum yang diizinkan.',
            'lampiran.*.file.mimes' => 'Format file lampiran tidak diizinkan.',
            'lampiran.*.file.extensions' => 'Ekstensi file lampiran tidak diizinkan.',
            'lampiran.*.keterangan.string' => 'Keterangan lampiran harus berupa teks.',
            'lampiran.*.keterangan.max' => 'Keterangan lampiran maksimal :max karakter.',
            'konfirmasi_pengukuran_sebelumnya.boolean' => 'Konfirmasi pengukuran sebelumnya tidak valid. Muat ulang halaman PK.',
        ];
    }

    public function messages(): array
    {
        return self::inputMessages();
    }

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if (self::fieldAsing($this->all(), ['_token', '_method'])) {
                $validator->errors()->add('revisi', self::PESAN_FIELD);
            }
        }];
    }

    /**
     * Key top-level di luar aturan, key item target selain indikator dan nilai, atau key item lampiran selain file
     * dan keterangan tidak boleh diterapkan diam-diam; mode lampiran selain file juga tertolak di sini.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $tambahan
     */
    public static function fieldAsing(array $data, array $tambahan = []): bool
    {
        $topLevel = array_keys(array_filter(self::inputRules(), fn (string $key): bool => ! str_contains($key, '.'), ARRAY_FILTER_USE_KEY));
        if (array_diff(array_keys($data), [...$topLevel, ...$tambahan]) !== []) {
            return true;
        }
        foreach (['target' => ['indikator_id', 'nilai'], 'lampiran' => ['file', 'keterangan']] as $daftar => $kunci) {
            foreach (is_array($data[$daftar] ?? null) ? $data[$daftar] : [] as $item) {
                if (! is_array($item) || array_diff(array_keys($item), $kunci) !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function failedAuthorization(): void
    {
        $this->recordRejection('izin_ditolak');
        throw new AuthorizationException(self::PESAN_IZIN);
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->recordRejection('input_tidak_valid', array_values(array_unique(array_map(
            fn (string $key): string => explode('.', $key)[0], $validator->errors()->keys()))));
        parent::failedValidation($validator);
    }

    /** @param  list<string>  $field */
    private function recordRejection(string $reason, array $field = []): void
    {
        $actor = $this->user();
        if (! $actor instanceof User) {
            return;
        }
        $id = $this->route('perjanjian_kinerja');
        $id = $id instanceof RenstraPk ? $id->id : $id;
        app(AuditLogger::class)->catat(actor: $actor, tindakan: 'renstra_pk_versi.setujui_ditolak', objekTipe: 'renstra_pk',
            objekId: is_string($id) && Str::isUuid($id) ? strtolower($id) : (string) Str::uuid(),
            nilaiBaru: ['tindakan_diminta' => 'renstra_pk_versi.setujui', 'alasan_penolakan' => $reason, 'field' => $field],
            dasarIzin: array_map(fn (PermissionDecision $decision): array => $decision->toAuditBasis(), $this->initialDecisions));
    }
}
