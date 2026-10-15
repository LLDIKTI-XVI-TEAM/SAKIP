<?php

namespace App\Http\Requests\Jadwal;

use App\Http\Requests\PeriodeJadwalMutationRequest;
use App\Support\AuditReason;
use App\Support\PermissionCodes;

/** Payload penutupan hanya token kalender dan alasan; status, waktu, dan aktor ditentukan server. */
class TutupJadwalRequest extends PeriodeJadwalMutationRequest
{
    public function permissionCode(): string
    {
        return PermissionCodes::JADWAL_TUTUP;
    }

    public function auditEvent(): string
    {
        return 'jadwal.tutup';
    }

    public function rules(): array
    {
        return [
            'expected_revisi' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'alasan' => ['required', 'string', 'max:1000', AuditReason::validate(...)],
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
