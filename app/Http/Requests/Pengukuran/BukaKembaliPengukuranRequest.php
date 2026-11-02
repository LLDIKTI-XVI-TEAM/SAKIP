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

class BukaKembaliPengukuranRequest extends FormRequest
{
    /** @var array<string, mixed>|null */
    private ?array $dasarIzin = null;

    public function authorize(): bool
    {
        $p = PengukuranKinerja::with('jadwalSnapshot')->findOrFail($this->route('id'));
        $this->dasarIzin = app(PermissionResolver::class)->decide($this->user(), 'pengukuran:buka_kembali', $p->targetUnitId());

        return $this->dasarIzin['allowed'];
    }

    public function rules(): array
    {
        // Alasan menjadi riwayat dan alasan audit; teks kontrol ditolak di sini agar error jatuh ke field pengguna.
        return ['versi' => ['required', 'integer', 'min:1'], 'alasan' => ['required', 'string', 'max:1000', AuditReason::validate(...)],
            'status_alur' => ['prohibited'], 'status' => ['prohibited'], 'self_approval' => ['prohibited']];
    }

    protected function failedAuthorization(): void
    {
        $e = new AuthorizationException('Izin buka kembali pengukuran tidak tersedia atau telah dicabut.');
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
        // Request yang ditolak tidak mencapai Action; writer yang sama menjaga satu audit per percobaan.
        app(PengukuranMutationSteps::class)->compensate($this->user(), $this->route('id'), 'buka_kembali', null, $this->dasarIzin, $e);
    }

    public function messages(): array
    {
        return ['alasan.required' => 'Alasan buka kembali wajib diisi.', 'alasan.string' => 'Alasan harus berupa teks.',
            'alasan.max' => 'Alasan maksimal 1000 karakter.',
            'versi.required' => 'Versi data wajib dikirim. Muat ulang halaman.', 'versi.integer' => 'Versi data tidak sah.', 'versi.min' => 'Versi data tidak sah.',
            'prohibited' => 'Kolom :attribute ditentukan server.'];
    }
}
