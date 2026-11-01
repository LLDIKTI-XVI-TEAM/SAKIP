<?php

namespace App\Http\Requests\Jadwal;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Jadwal\SelisihSnapshotMaster;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Payload koreksi hanya periode mulai, alasan, rujukan, dan token versi; nilai konteks disalin server dari data master
 * (Q79 §79.1). Izin per kelompok kolom bergantung pada selisih terkunci, sehingga di sini cukup izin baca indikator
 * ditambah salah satu izin koreksi.
 */
class KoreksiSnapshotRequest extends FormRequest
{
    /** @var array<string, PermissionDecision> */
    private array $initialDecisions = [];

    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor instanceof User) {
            return false;
        }
        foreach ([PermissionCodes::INDIKATOR_READ, ...SelisihSnapshotMaster::IZIN] as $code) {
            $this->initialDecisions[$code] = app(PermissionResolver::class)->resolve($actor, $code);
        }

        return $this->initialDecisions[PermissionCodes::INDIKATOR_READ]->allowed
            && collect(SelisihSnapshotMaster::IZIN)->contains(fn (string $code): bool => $this->initialDecisions[$code]->allowed);
    }

    /** Aturan dipakai ulang di Action untuk caller selain HTTP. @return array<string, mixed> */
    public static function inputRules(): array
    {
        return [
            'periode_mulai_id' => ['required', 'uuid'],
            'alasan_koreksi' => ['required', 'string', 'max:1000', AuditReason::validate(...)],
            'rujukan_koreksi' => ['required', 'string', 'max:1000', AuditReason::validate(...)],
            'expected_snapshot_id' => ['required', 'uuid'],
            'expected_snapshot_versi' => ['required', 'integer', 'min:1'],
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
            'periode_mulai_id.required' => 'Periode mulai berlaku wajib dipilih.',
            'periode_mulai_id.uuid' => 'Pilihan periode mulai berlaku tidak valid. Pilih kembali periode yang tersedia.',
            'alasan_koreksi.required' => 'Alasan koreksi wajib diisi.',
            'alasan_koreksi.string' => 'Alasan koreksi harus berupa teks.',
            'alasan_koreksi.max' => 'Alasan koreksi maksimal :max karakter.',
            'rujukan_koreksi.required' => 'Rujukan resmi koreksi wajib diisi.',
            'rujukan_koreksi.string' => 'Rujukan resmi koreksi harus berupa teks.',
            'rujukan_koreksi.max' => 'Rujukan resmi koreksi maksimal :max karakter.',
            'expected_snapshot_id.required' => 'Data snapshot tidak lengkap. Muat ulang halaman snapshot.',
            'expected_snapshot_id.uuid' => 'Data snapshot tidak valid. Muat ulang halaman snapshot.',
            'expected_snapshot_versi.required' => 'Data snapshot tidak lengkap. Muat ulang halaman snapshot.',
            'expected_snapshot_versi.integer' => 'Data snapshot tidak valid. Muat ulang halaman snapshot.',
            'expected_snapshot_versi.min' => 'Data snapshot tidak valid. Muat ulang halaman snapshot.',
        ];
    }

    public function messages(): array
    {
        return self::inputMessages();
    }

    /** Nilai konteks, aktor, atau versi tujuan dari klien tidak boleh diterapkan diam-diam. */
    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if (array_diff(array_keys($this->all()), [...array_keys(self::inputRules()), '_token', '_method']) !== []) {
                $validator->errors()->add('snapshot', 'Permintaan memuat field yang tidak didukung.');
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
        $this->recordRejection('input_tidak_valid');
        parent::failedValidation($validator);
    }

    private function recordRejection(string $reason): void
    {
        $actor = $this->user();
        if (! $actor instanceof User) {
            return;
        }
        $id = $this->route('snapshot');
        app(AuditLogger::class)->catat(actor: $actor, tindakan: 'jadwal_snapshot.koreksi_ditolak', objekTipe: 'jadwal_snapshot',
            objekId: is_string($id) && Str::isUuid($id) ? strtolower($id) : (string) Str::uuid(),
            nilaiBaru: ['jadwal_id' => strtolower((string) $this->route('jadwal')), 'alasan_penolakan' => $reason],
            dasarIzin: array_map(fn (PermissionDecision $decision): array => $decision->toAuditBasis(), $this->initialDecisions));
    }
}
