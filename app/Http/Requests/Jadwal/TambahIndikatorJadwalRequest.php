<?php

namespace App\Http\Requests\Jadwal;

use App\Http\Requests\PeriodeJadwalMutationRequest;
use App\Support\AuditReason;
use App\Support\PermissionCodes;

/** Payload hanya pilihan indikator, periode mulai, dan alasan; isi snapshot, aktor, dan waktu ditentukan server. */
class TambahIndikatorJadwalRequest extends PeriodeJadwalMutationRequest
{
    public function permissionCode(): string
    {
        return PermissionCodes::JADWAL_BUKA_KEMBALI;
    }

    public function auditEvent(): string
    {
        return 'jadwal.tambah_indikator';
    }

    public function rules(): array
    {
        return [
            'indikator_id' => ['required', 'uuid'],
            'periode_mulai_id' => ['required', 'uuid'],
            'alasan' => ['required', 'string', 'max:1000', AuditReason::validate(...)],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'alasan.max' => ':attribute maksimal :max karakter.'];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'indikator_id' => 'Indikator', 'periode_mulai_id' => 'Periode mulai berlaku', 'alasan' => 'Alasan'];
    }
}
