<?php

namespace App\Actions\Access;

use App\Models\Permission;
use App\Models\UserPermissionGrant;
use App\Services\Access\IzinUnitPresenter;

class ShowGrantUnit
{
    /**
     * Rincian Izin Unit satu pengguna pada satu unit (Q60); pasangan tanpa izin unit dianggap tidak ada.
     *
     * @return array<string, mixed>
     */
    public function handle(string $userId, string $unitId): array
    {
        $grants = UserPermissionGrant::with(IzinUnitPresenter::RELASI)
            ->where('user_id', $userId)
            ->where('unit_id', $unitId)
            ->whereHas('permission', fn ($q) => $q->where('butuh_scope', Permission::SCOPE_UNIT))
            ->get();

        abort_if($grants->isEmpty(), 404);

        return [
            'kelompok' => IzinUnitPresenter::kelompok($grants, rinci: true),
            'unitPermissions' => IzinUnitPresenter::pilihanIzin(),
            'can' => [
                'kelola_grant' => true,
                'revoke_grant' => true,
            ],
        ];
    }
}
