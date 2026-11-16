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

class ReviewPengukuranRequest extends FormRequest
{
    /** @var array<string, mixed>|null */
    private ?array $dasarIzin = null;

    public function authorize(): bool
    {
        $p = PengukuranKinerja::with('jadwalSnapshot')->findOrFail($this->route('id'));
        $this->dasarIzin = app(PermissionResolver::class)->decide($this->user(), 'pengukuran:'.$this->aksi(), $p->targetUnitId());

        return $this->dasarIzin['allowed'];
    }

    public function rules(): array
    {
        // Catatan menjadi riwayat dan alasan audit; teks kontrol ditolak di sini agar error jatuh ke field pengguna, bukan ke audit.
        return ['versi' => ['required', 'integer', 'min:1'], 'catatan' => [$this->routeIs('verifikasi.kembalikan') ? 'required' : 'nullable', 'string', 'max:10000', AuditReason::validate(...)],
            'status_alur' => ['prohibited'], 'status' => ['prohibited'], 'self_approval' => ['prohibited']];
    }

    protected function failedAuthorization(): void
    {
        $e = new AuthorizationException('Izin reviu pengukuran tidak tersedia atau telah dicabut.');
        $this->catat($e);
        throw $e;
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->catat(new ValidationException($validator));
        parent::failedValidation($validator);
    }

    private function aksi(): string
    {
        return match (true) {
            $this->routeIs('verifikasi.kembalikan') => 'kembalikan',
            $this->routeIs('verifikasi.sahkan') => 'sahkan',
            default => 'verifikasi',
        };
    }

    private function catat(AuthorizationException|ValidationException $e): void
    {
        // Request yang ditolak tidak mencapai Action; writer yang sama menjaga satu audit per percobaan.
        app(PengukuranMutationSteps::class)->compensate($this->user(), $this->route('id'), $this->aksi(), null, $this->dasarIzin, $e);
    }

    public function messages(): array
    {
        return ['catatan.required' => 'Alasan pengembalian wajib diisi.', 'catatan.string' => 'Alasan harus berupa teks.', 'catatan.max' => 'Alasan maksimal 10000 karakter.',
            'versi.required' => 'Versi data wajib dikirim. Muat ulang halaman.', 'versi.integer' => 'Versi data tidak sah.', 'versi.min' => 'Versi data tidak sah.',
            'prohibited' => 'Kolom :attribute ditentukan server.'];
    }
}
