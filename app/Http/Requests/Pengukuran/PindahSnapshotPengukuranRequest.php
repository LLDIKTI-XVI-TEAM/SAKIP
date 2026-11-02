<?php

namespace App\Http\Requests\Pengukuran;

use App\Actions\Pengukuran\PengukuranMutationSteps;
use App\Models\PengukuranKinerja;
use App\Services\Authorization\PermissionResolver;
use App\Support\AuditReason;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class PindahSnapshotPengukuranRequest extends FormRequest
{
    /** @var array<string, mixed>|null */
    private ?array $dasarIzin = null;

    public function authorize(): bool
    {
        $p = PengukuranKinerja::with('jadwalSnapshot')->findOrFail(strtolower($this->route('id')));
        $this->dasarIzin = app(PermissionResolver::class)->decide($this->user(), 'pengukuran:update', $p->targetUnitId());

        return $this->dasarIzin['allowed'];
    }

    public function rules(): array
    {
        return ['versi' => ['required', 'integer', 'min:1'], 'expected_snapshot_id' => ['required', 'uuid'],
            'expected_snapshot_versi' => ['required', 'integer', 'min:1'],
            'alasan' => ['nullable', 'string', 'max:1000', AuditReason::validate(...)],
            'jadwal_snapshot_id' => ['prohibited'], 'status_alur' => ['prohibited'], 'komponen' => ['prohibited'], 'nilai' => ['prohibited']];
    }

    protected function failedAuthorization(): void
    {
        $e = new AuthorizationException('Izin tindakan tidak tersedia atau telah dicabut.');
        $this->catat($e);
        throw $e;
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->catat(new ValidationException($validator));
        parent::failedValidation($validator);
    }

    private function catat(AuthorizationException|ValidationException $e): void
    {
        // Penolakan Request tidak mencapai Action, tetapi tetap memakai writer audit yang sama.
        app(PengukuranMutationSteps::class)->compensate($this->user(), strtolower($this->route('id')), 'pindah_snapshot', null, $this->dasarIzin, $e);
    }

    public function messages(): array
    {
        return ['alasan.string' => 'Alasan harus berupa teks.', 'alasan.max' => 'Alasan maksimal 1000 karakter.',
            'versi.required' => 'Versi data wajib dikirim. Muat ulang halaman.', 'versi.integer' => 'Versi data tidak sah.', 'versi.min' => 'Versi data tidak sah.',
            'expected_snapshot_id.required' => 'Data snapshot tujuan wajib dikirim. Muat ulang halaman.',
            'expected_snapshot_id.uuid' => 'Data snapshot tujuan tidak sah. Muat ulang halaman.',
            'expected_snapshot_versi.required' => 'Versi snapshot tujuan wajib dikirim. Muat ulang halaman.',
            'expected_snapshot_versi.integer' => 'Versi snapshot tujuan tidak sah. Muat ulang halaman.',
            'expected_snapshot_versi.min' => 'Versi snapshot tujuan tidak sah. Muat ulang halaman.',
            'prohibited' => 'Kolom :attribute ditentukan server.'];
    }
}
