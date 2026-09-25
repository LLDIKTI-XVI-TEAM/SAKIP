<?php

namespace App\Http\Requests\Renstra;

use App\Models\Renstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StoreRenstraRequest extends RenstraMutationRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Renstra::class);
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();

        if ($user instanceof User) {
            $decision = app(PermissionResolver::class)->resolve($user, PermissionCodes::RENSTRA_CREATE);
            $rawAlasan = $this->input('alasan');
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== '' ? trim($rawAlasan) : null;

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'renstra.buat_ditolak',
                objekTipe: 'renstra',
                objekId: (string) Str::uuid(),
                nilaiBaru: [
                    'nama' => $this->input('nama'),
                    'tahun_mulai' => $this->input('tahun_mulai'),
                    'tahun_selesai' => $this->input('tahun_selesai') ?? $this->input('tahun_akhir'),
                ],
                alasan: $alasan,
                dasarIzin: $decision->toAuditBasis(),
            );
        }

        parent::failedAuthorization();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->mutationRules(requireReason: false);
    }
}
