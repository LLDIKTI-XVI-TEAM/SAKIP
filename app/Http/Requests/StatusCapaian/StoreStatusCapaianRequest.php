<?php

namespace App\Http\Requests\StatusCapaian;

use App\Http\Requests\PeriodeJadwalMutationRequest;
use App\Models\StatusCapaian;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use Illuminate\Validation\Rule;

/**
 * Bentuk permintaan penetapan status capaian. Penetap, unit target, dan sumber selalu ditentukan server;
 * klien hanya mengirim status pilihan, versi pengukuran yang dirujuk, token `versi` header, dan alasan opsional.
 * Alasan tetap opsional (Q78 §78.1 butir 4) tetapi format dan panjangnya memakai kontrak teks audit yang sama
 * dengan mutasi lain (`AuditReason`, 1000 karakter) agar isi yang tidak sah tidak pernah masuk jejak audit.
 * Syarat versi disahkan dan pengecualian AC-5 tetap diperiksa Action setelah baris terkunci.
 */
class StoreStatusCapaianRequest extends PeriodeJadwalMutationRequest
{
    public function permissionCode(): string
    {
        return PermissionCodes::STATUS_CAPAIAN_UPDATE;
    }

    public function auditEvent(): string
    {
        return 'status_capaian.tetapkan';
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([StatusCapaian::STATUS_TERCAPAI, StatusCapaian::STATUS_BELUM_TERCAPAI])],
            'pengukuran_versi_id' => ['required', 'uuid'],
            'versi' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'alasan' => ['nullable', 'string', 'max:1000', AuditReason::validate(...)],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'alasan.max' => ':attribute maksimal :max karakter.'];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'status' => 'Status capaian', 'pengukuran_versi_id' => 'Versi pengukuran',
            'versi' => 'Versi data', 'alasan' => 'Alasan'];
    }
}
