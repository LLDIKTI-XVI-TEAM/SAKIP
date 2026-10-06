<?php

namespace App\Http\Requests\Indikator;

use App\Models\IndikatorKinerja;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Kinerja\KomponenMutationService;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;

class ChangeIndicatorFormulaRequest extends FormRequest
{
    private ?PermissionDecision $decision = null;

    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }
        $this->decision = app(PermissionResolver::class)->resolve($this->user(), 'komponen:read');

        return $this->decision->allowed;
    }

    protected function failedAuthorization(): void
    {
        $indikator = $this->route('indikator');
        if ($this->user() !== null && $this->decision !== null && $indikator instanceof IndikatorKinerja) {
            app(AuditLogger::class)->catat(actor: $this->user(), tindakan: 'indikator.ubah_ditolak',
                objekTipe: 'indikator', objekId: $indikator->id,
                alasan: 'Penyimpanan definisi ditolak karena tidak memiliki izin membaca komponen.',
                dasarIzin: $this->decision->toAuditBasis());
        }
        parent::failedAuthorization();
    }

    public function rules(): array
    {
        return array_merge(app(KomponenMutationService::class)->aturanDefinisi(), [
            'tipe_perhitungan' => ['required', 'in:manual,rasio_persen,penjumlahan'],
            'presisi' => ['sometimes', 'integer', 'between:0,4'],
            'komponen' => ['present', 'array', 'max:50'],
            'expected_updated_at' => ['required', 'date'],
        ]);
    }

    public function messages(): array
    {
        return app(KomponenMutationService::class)->pesanBersarang();
    }
}
