<?php

namespace App\Http\Requests\Jadwal;

use App\Http\Requests\PeriodeJadwalMutationRequest;
use App\Support\AuditReason;
use App\Support\PermissionCodes;

/** Payload revisi hanya tanggal jendela per periode anggota, token kalender, dan alasan; status, tahun, dan penutupan tidak dapat dikirim. */
class RevisiJendelaPicRequest extends PeriodeJadwalMutationRequest
{
    public function permissionCode(): string
    {
        return PermissionCodes::JADWAL_UPDATE;
    }

    public function auditEvent(): string
    {
        return 'jadwal.revisi_jendela';
    }

    public function rules(): array
    {
        return [
            'expected_revisi' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'alasan' => ['required', 'string', 'max:1000', AuditReason::validate(...)],
            'periode' => ['required', 'array', 'list', 'min:1', 'max:12'],
            'periode.*' => ['required', 'array:periode_id,pengisian_mulai,pengisian_selesai'],
            'periode.*.periode_id' => ['required', 'uuid', 'distinct:ignore_case'],
            'periode.*.pengisian_mulai' => ['required', 'date_format:Y-m-d'],
            'periode.*.pengisian_selesai' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'alasan.max' => ':attribute maksimal :max karakter.'];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'expected_revisi' => 'Revisi jadwal', 'alasan' => 'Alasan'];
    }
}
