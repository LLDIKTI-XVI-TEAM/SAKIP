<?php

namespace App\Http\Requests\Jadwal;

use App\Http\Requests\PeriodeJadwalMutationRequest;
use App\Support\AuditReason;
use App\Support\PermissionCodes;

/** Payload aktivasi hanya token kalender, korelasi operasi, dan alasan; status/PK/snapshot/aktor ditentukan server. */
class ActivateJadwalRequest extends PeriodeJadwalMutationRequest
{
    public function permissionCode(): string
    {
        return PermissionCodes::JADWAL_AKTIVASI;
    }

    public function auditEvent(): string
    {
        return 'jadwal.aktivasi';
    }

    /** @return array<string, mixed> */
    public static function inputRules(): array
    {
        return [
            'expected_revisi' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'operation_id' => ['required', 'uuid'],
            'alasan' => ['required', 'string', 'max:1000', AuditReason::validate(...)],
        ];
    }

    public function rules(): array
    {
        return self::inputRules();
    }

    public function messages(): array
    {
        return [...parent::messages(), 'alasan.max' => ':attribute maksimal :max karakter.'];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'expected_revisi' => 'Revisi jadwal', 'operation_id' => 'Identitas operasi', 'alasan' => 'Alasan'];
    }
}
