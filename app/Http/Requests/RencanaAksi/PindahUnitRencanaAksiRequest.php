<?php

namespace App\Http\Requests\RencanaAksi;

use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use App\Support\PermissionCodes;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PindahUnitRencanaAksiRequest extends FormRequest
{
    private ?RencanaAksi $header = null;

    /** @var array<string, mixed>|null */
    private ?array $dasarIzin = null;

    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor instanceof User) {
            return false;
        }
        $this->header = RencanaAksi::with('indikator')->findOrFail($this->route('rencanaAksi'));
        $resolver = app(PermissionResolver::class);
        $asal = $resolver->resolve($actor, PermissionCodes::RENCANA_AKSI_UPDATE, (string) $this->header->unit_id);
        $tujuan = $resolver->resolve($actor, PermissionCodes::RENCANA_AKSI_UPDATE, (string) $this->header->indikator->unit_id);
        $this->dasarIzin = ['unit_asal' => $asal->toAuditBasis(), 'unit_tujuan' => $tujuan->toAuditBasis()];
        $jendela = app(JendelaTulisRencanaAksi::class);

        return $jendela->jalurPerencanaan($asal) && $jendela->jalurPerencanaan($tujuan);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'expected_versi' => ['required', 'integer', 'min:1'],
            'alasan' => ['required', 'string', 'max:1000'],
            'unit_id' => ['prohibited'],
        ];
    }

    protected function failedAuthorization(): void
    {
        $this->catatPenolakan('Izin pemindahan pada kedua unit tidak tersedia.');
        parent::failedAuthorization();
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->catatPenolakan('Data pemindahan unit tidak valid.');
        parent::failedValidation($validator);
    }

    private function catatPenolakan(string $alasan): void
    {
        $actor = $this->user();
        if ($actor instanceof User && $this->header instanceof RencanaAksi) {
            app(AuditLogger::class)->catat(actor: $actor, tindakan: 'rencana_aksi.pindah_unit_ditolak', objekTipe: 'rencana_aksi',
                objekId: (string) $this->header->id, alasan: $alasan, dasarIzin: $this->dasarIzin);
        }
    }
}
