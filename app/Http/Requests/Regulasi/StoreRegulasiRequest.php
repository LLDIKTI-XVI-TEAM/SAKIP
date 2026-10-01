<?php

namespace App\Http\Requests\Regulasi;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Support\Str;

/** Membuat induk beserta lampiran dengan izin regulasi:create; versi berasal dari server. */
class StoreRegulasiRequest extends RegulasiMutationRequest
{
    private ?PermissionDecision $initialDecision = null;

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }
        $this->initialDecision = app(PermissionResolver::class)->resolve($user, PermissionCodes::REGULASI_CREATE);

        return $this->initialDecision->allowed;
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        if ($user instanceof User && $this->initialDecision !== null) {
            // Authorization mendahului validasi; hanya alasan yang sudah aman masuk audit penolakan.
            $alasan = mb_substr(trim(AuditReason::sanitize($this->input('alasan'))), 0, 1000);
            app(AuditLogger::class)->catat(
                actor: $user, tindakan: 'regulasi.buat_ditolak', objekTipe: 'regulasi', objekId: (string) Str::uuid(),
                alasan: $alasan !== '' ? $alasan : 'Pembuatan regulasi ditolak karena izin efektif tidak mengizinkan tindakan ini.',
                dasarIzin: $this->initialDecision->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->mutationRules();
    }
}
