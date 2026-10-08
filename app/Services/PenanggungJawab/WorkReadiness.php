<?php

namespace App\Services\PenanggungJawab;

use App\Models\User;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;

class WorkReadiness
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /** @return array{complete:bool,permissions:list<array{permission:string,label:string,allowed:bool,reason:string}>,missing:list<string>,available:list<string>} */
    public function forUser(User $user, string $unitId): array
    {
        $labels = [
            'pengukuran:create' => 'Mengisi pengukuran', 'pengukuran:update' => 'Mengubah pengukuran',
            'rencana_aksi:create' => 'Menyusun rencana aksi', 'rencana_aksi:update' => 'Mengubah rencana aksi',
            'rencana_aksi:ajukan' => 'Mengajukan rencana aksi',
            'kegiatan:create' => 'Membuat kegiatan', 'kegiatan:update' => 'Mengubah kegiatan',
        ];
        $permissions = [];
        $missing = [];
        $available = [];
        $decisions = $this->resolver->decideMany($user, PermissionCatalog::UNIT_SCOPED, $unitId);
        foreach (PermissionCatalog::UNIT_SCOPED as $code) {
            $decision = $decisions[$code];
            $permissions[] = ['permission' => $code, 'label' => $labels[$code], 'allowed' => $decision['allowed'], 'reason' => $decision['reason']];
            if ($decision['allowed']) {
                $available[] = $code;
            } else {
                $missing[] = $code;
            }
        }

        return ['complete' => $missing === [], 'permissions' => $permissions, 'missing' => $missing, 'available' => $available];
    }
}
