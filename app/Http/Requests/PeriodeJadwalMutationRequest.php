<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/** Audit penolakan input/izin sebelum Action; tidak membaca target maupun menyimpan payload mentah. */
abstract class PeriodeJadwalMutationRequest extends FormRequest
{
    abstract public function permissionCode(): string;

    abstract public function auditEvent(): string;

    /** @return array<string, mixed> */
    abstract public function rules(): array;

    /** Pesan lokal fitur juga dipakai validator Action agar penolakan tetap terbaca tanpa katalog bahasa global. */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'boolean' => ':attribute harus berupa pilihan ya atau tidak.',
            'between' => ':attribute harus berada antara :min dan :max.',
            'date_format' => ':attribute harus berupa tanggal valid dengan format YYYY-MM-DD.',
            'uuid' => 'Pilihan :attribute tidak valid. Pilih kembali data yang tersedia.',
            'distinct' => ':attribute tidak boleh dipilih lebih dari sekali.',
            'different' => ':attribute harus berbeda dari :other.',
            'array' => 'Format :attribute tidak sesuai. Periksa kembali pilihan Anda.',
            'list' => ':attribute harus berupa daftar pilihan berurutan.',
            'in' => 'Pilihan :attribute tidak tersedia.',
            'periode.min' => ':attribute harus memuat minimal :min pilihan.',
            'min' => ':attribute minimal :min.',
            'nama.max' => ':attribute maksimal :max karakter.',
            'q.max' => ':attribute maksimal :max karakter.',
            'max' => ':attribute maksimal :max.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama' => 'Nama periode',
            'urutan' => 'Urutan',
            'aktif' => 'Status aktif',
            'is_nilai_akhir' => 'Penanda nilai akhir',
            'renstra_id' => 'Renstra',
            'tahun' => 'Tahun',
            'rencana_aksi_mulai' => 'Tanggal mulai Rencana Aksi',
            'rencana_aksi_selesai' => 'Tanggal selesai Rencana Aksi',
            'penutupan' => 'Tanggal penutupan',
            'periode' => 'Periode pilihan',
            'periode.*' => 'Jendela periode',
            'periode.*.periode_id' => 'Periode pilihan',
            'periode.*.periode_revisi' => 'Revisi periode',
            'periode.*.pengisian_mulai' => 'Tanggal mulai pengisian',
            'periode.*.pengisian_selesai' => 'Tanggal selesai pengisian',
            'periode.*.reviu_mulai' => 'Tanggal mulai review',
            'periode.*.reviu_selesai' => 'Tanggal selesai review',
            'revisi' => 'Revisi data',
            'periode_lama_id' => 'Periode nilai akhir saat ini',
            'periode_pengganti_id' => 'Periode pengganti',
            'revisi_lama' => 'Revisi periode nilai akhir saat ini',
            'revisi_pengganti' => 'Revisi periode pengganti',
            'q' => 'Pencarian',
            'page' => 'Halaman',
            'status' => 'Status',
            'sort' => 'Urutan daftar',
        ];
    }

    public function authorize(): bool
    {
        return $this->user() instanceof User && app(PermissionResolver::class)->allows($this->user(), $this->permissionCode());
    }

    /** Menolak field di luar kontrak, termasuk key yang nilainya null. */
    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            $allowed = [...array_map(fn (string $field): string => explode('.', $field)[0], array_keys($this->rules())), '_token', '_method'];
            if (array_diff(array_keys($this->all()), $allowed) !== []) {
                $validator->errors()->add(strtok($this->auditEvent(), '.'), 'Permintaan memuat field yang tidak didukung.');
            }
        }];
    }

    protected function failedAuthorization(): void
    {
        $this->recordRejection('izin_ditolak');
        parent::failedAuthorization();
    }

    protected function failedValidation(Validator $validator): void
    {
        $known = array_keys($this->rules());
        $fields = array_values(array_unique(array_filter(array_map(fn (string $field): string => explode('.', $field)[0], array_keys($validator->errors()->messages())), fn (string $field): bool => in_array($field, $known, true))));
        $this->recordRejection('input_tidak_valid', $fields);
        parent::failedValidation($validator);
    }

    /** @param list<string> $fields */
    private function recordRejection(string $reason, array $fields = []): void
    {
        $actor = $this->user();
        if (! $actor instanceof User) {
            return;
        }
        $domain = strtok($this->auditEvent(), '.');
        $id = $this->route($domain);
        app(AuditLogger::class)->catat(actor: $actor, tindakan: $this->auditEvent().'_ditolak', objekTipe: $domain,
            objekId: is_string($id) && Str::isUuid($id) ? strtolower($id) : (string) Str::uuid(),
            nilaiBaru: ['alasan_penolakan' => $reason, 'field_tidak_valid' => $fields],
            dasarIzin: app(PermissionResolver::class)->resolve($actor, $this->permissionCode())->toAuditBasis());
    }
}
