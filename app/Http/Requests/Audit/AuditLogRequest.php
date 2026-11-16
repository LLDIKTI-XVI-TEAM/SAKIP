<?php

namespace App\Http\Requests\Audit;

use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;

class AuditLogRequest extends FormRequest
{
    /** Audit dibaca dengan izin global `audit:read`; deny yang cocok menang dan akun nonaktif fail-closed di resolver. */
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && app(PermissionResolver::class)->allows($actor->fresh(), PermissionCodes::AUDIT_READ);
    }

    public function rules(): array
    {
        return [
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'aktor' => ['nullable', 'string', 'max:100'],
            'tindakan' => ['nullable', 'string', 'max:100'],
            'objek_tipe' => ['nullable', 'string', 'max:100'],
            'objek_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'dari.date_format' => 'Tanggal awal tidak valid.',
            'sampai.date_format' => 'Tanggal akhir tidak valid.',
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            'aktor.max' => 'Pencarian aktor maksimal 100 karakter.',
            'tindakan.max' => 'Pencarian tindakan maksimal 100 karakter.',
            'objek_tipe.max' => 'Jenis objek maksimal 100 karakter.',
            'objek_id.uuid' => 'ID objek harus berupa UUID yang valid.',
            'page.min' => 'Halaman minimal 1.',
        ];
    }
}
