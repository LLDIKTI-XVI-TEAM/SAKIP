<?php

namespace App\Http\Requests\Jadwal;

use App\Support\PermissionCodes;

class UpdateJadwalRequest extends StoreJadwalRequest
{
    public function permissionCode(): string
    {
        return PermissionCodes::JADWAL_UPDATE;
    }

    public function auditEvent(): string
    {
        return 'jadwal.ubah';
    }

    public function rules(): array
    {
        return self::inputRules(true);
    }
}
