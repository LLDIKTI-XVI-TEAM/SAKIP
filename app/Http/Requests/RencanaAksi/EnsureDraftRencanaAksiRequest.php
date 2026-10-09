<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EnsureDraftRencanaAksiRequest extends FormRequest
{
    private ?IndikatorKinerja $indikator = null;

    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        $indikatorId = $this->input('indikator_id');
        if (! is_string($indikatorId) || ! Str::isUuid($indikatorId)) {
            return true;
        }

        $indikator = IndikatorKinerja::whereKey($indikatorId)->first();
        if (! $indikator instanceof IndikatorKinerja) {
            // 404 sebelum validasi payload, dengan maupun tanpa izin, mengikuti
            // SimpanTargetPeriodeRequest: indikator asing tidak lagi dibedakan
            // lewat respons validasi 422.
            abort(404);
        }
        $this->indikator = $indikator;

        return Gate::allows('create', [RencanaAksi::class, $indikator]);
    }

    /**
     * Percobaan buat yang ditolak otorisasi dicatat di sini, bukan di Policy.
     * Izin dihitung ulang hanya untuk dasar audit; respons tetap 403.
     * Otorisasi berjalan sebelum validasi, jadi `tahun` dicatat hanya bila
     * lolos aturan `tahun` request ini. Penolakan di dalam transaksi
     * `EnsureDraftRencanaAksi` diaudit Action itu sendiri.
     */
    protected function failedAuthorization(): void
    {
        $user = $this->user();
        if ($user instanceof User && $this->indikator instanceof IndikatorKinerja) {
            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'rencana_aksi.buat_ditolak',
                objekTipe: 'rencana_aksi',
                objekId: (string) Str::uuid(),
                nilaiBaru: ['indikator_id' => (string) $this->indikator->id, 'tahun' => $this->tahunSah()],
                alasan: AlasanAudit::sanitasi(null, 'Percobaan pembuatan rencana aksi ditolak oleh sistem otorisasi.'),
                dasarIzin: app(PermissionResolver::class)->resolve($user, PermissionCodes::RENCANA_AKSI_CREATE, (string) $this->indikator->unit_id)->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'indikator_id' => ['required', 'uuid'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'uuid' => 'Identitas :attribute tidak sah.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
            'between' => 'Kolom :attribute berada di luar rentang tahun yang sah.',
        ];
    }

    private function tahunSah(): ?int
    {
        return Validator::make($this->only('tahun'), ['tahun' => $this->rules()['tahun']])->passes()
            ? (int) $this->input('tahun')
            : null;
    }
}
