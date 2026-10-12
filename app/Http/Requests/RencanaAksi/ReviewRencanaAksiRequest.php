<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\RencanaAksi;
use App\Services\Authorization\PermissionResolver;
use App\Services\RencanaAksi\GerbangReviewRencanaAksi;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ReviewRencanaAksiRequest extends FormRequest
{
    /** @var array<string, mixed>|null */
    private ?array $dasarIzin = null;

    public function authorize(): bool
    {
        $header = RencanaAksi::findOrFail($this->route('rencanaAksi'));
        $permission = $this->aksi() === 'kembalikan' ? PermissionCodes::RENCANA_AKSI_KEMBALIKAN : PermissionCodes::RENCANA_AKSI_VERIFIKASI;
        $decision = app(PermissionResolver::class)->resolve($this->user(), $permission, (string) $header->unit_id);
        $this->dasarIzin = $decision->toAuditBasis();

        return $decision->allowed;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['expected_versi' => ['required', 'integer', 'min:1'], 'versi_pengajuan_id' => ['required', 'uuid']];
        if ($this->aksi() === 'kembalikan') {
            $rules['alasan'] = ['required', 'string', 'max:1000', AuditReason::validate(...)];
        }

        return $rules;
    }

    protected function failedAuthorization(): void
    {
        $e = new AuthorizationException('Izin review rencana aksi tidak tersedia atau telah dicabut.');
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
        return $this->routeIs('rencana-aksi.kembalikan') ? 'kembalikan' : 'verifikasi';
    }

    private function catat(AuthorizationException|ValidationException $e): void
    {
        $header = RencanaAksi::findOrFail($this->route('rencanaAksi'));
        app(GerbangReviewRencanaAksi::class)->catatPenolakan($this->user(), $header->id, $this->aksi(), ['dasar_izin' => $this->dasarIzin], $e);
    }
}
