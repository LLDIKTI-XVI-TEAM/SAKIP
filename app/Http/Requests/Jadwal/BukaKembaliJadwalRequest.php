<?php

namespace App\Http\Requests\Jadwal;

use App\Http\Requests\PeriodeJadwalMutationRequest;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use Illuminate\Validation\Rule;

/** Scope eksplisit dan waktu lokal WITA; keanggotaan diperiksa ulang setelah jadwal dikunci. */
class BukaKembaliJadwalRequest extends PeriodeJadwalMutationRequest
{
    public function permissionCode(): string
    {
        return PermissionCodes::JADWAL_BUKA_KEMBALI;
    }

    public function auditEvent(): string
    {
        return 'jadwal.buka_kembali';
    }

    public function rules(): array
    {
        return [
            'expected_revisi' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'alasan' => ['required', 'string', 'max:1000', AuditReason::validate(...)],
            'koreksi_mulai' => ['required', 'date_format:Y-m-d\TH:i'],
            'koreksi_sampai' => ['required', 'date_format:Y-m-d\TH:i', 'after:koreksi_mulai'],
            'lingkup_koreksi' => ['required', 'array:jenis_objek,indikator_ids,periode_ids'],
            'lingkup_koreksi.jenis_objek' => ['required', 'array', 'list', 'min:1', 'max:2'],
            'lingkup_koreksi.jenis_objek.*' => ['required', 'string', 'distinct', Rule::in(['rencana_aksi', 'pengukuran'])],
            'lingkup_koreksi.indikator_ids' => ['required', 'array', 'list', 'min:1'],
            'lingkup_koreksi.indikator_ids.*' => ['required', 'uuid', 'distinct:ignore_case'],
            'lingkup_koreksi.periode_ids' => ['required', 'array', 'list', 'min:1', 'max:12'],
            'lingkup_koreksi.periode_ids.*' => ['required', 'uuid', 'distinct:ignore_case'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'date_format' => ':attribute harus berupa tanggal dan jam yang valid.',
            'after' => 'Akhir sesi harus setelah awal sesi.', 'alasan.max' => ':attribute maksimal :max karakter.'];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'expected_revisi' => 'Revisi jadwal', 'alasan' => 'Alasan',
            'koreksi_mulai' => 'Awal sesi koreksi', 'koreksi_sampai' => 'Akhir sesi koreksi',
            'lingkup_koreksi' => 'Lingkup koreksi', 'lingkup_koreksi.jenis_objek' => 'Jenis data',
            'lingkup_koreksi.indikator_ids' => 'Indikator', 'lingkup_koreksi.periode_ids' => 'Periode yang dikoreksi'];
    }
}
