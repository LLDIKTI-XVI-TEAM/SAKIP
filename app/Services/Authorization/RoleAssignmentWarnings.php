<?php

namespace App\Services\Authorization;

use App\Models\PenugasanIndikator;

/** Membaca PJ efektif tanpa mengubah assignment saat mutasi peran. */
class RoleAssignmentWarnings
{
    /**
     * @param  list<string>  $userIds
     * @return array<string, bool>
     */
    public function forUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $active = PenugasanIndikator::effectiveOn(today(config('app.business_timezone'))->toDateString())
            ->whereIn('user_id', $userIds)->distinct()->pluck('user_id')->all();

        return array_replace(array_fill_keys($userIds, false), array_fill_keys($active, true));
    }
}
