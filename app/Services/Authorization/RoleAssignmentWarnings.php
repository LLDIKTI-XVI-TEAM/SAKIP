<?php

namespace App\Services\Authorization;

use Illuminate\Support\Facades\DB;

/**
 * Membaca snapshot PJ efektif untuk daftar pengguna dan mutasi Assign Role.
 * Query dipakai ulang; transaksi mutasi tetap dimiliki Action.
 */
class RoleAssignmentWarnings
{
    /**
     * Pilih PJ efektif lintas seluruh pengguna dahulu agar histori target tidak menjadi pemenang palsu.
     *
     * @param  list<string>  $userIds
     * @return array<string, bool>
     */
    public function forUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $winners = DB::table('penanggung_jawab')->selectRaw('distinct on (indikator_id) user_id')
            ->where('tanggal_mulai_berlaku', '<=', today(config('app.business_timezone'))->toDateString())
            ->orderBy('indikator_id')->orderByDesc('tanggal_mulai_berlaku')->orderByDesc('created_at');
        $active = DB::query()->fromSub($winners, 'effective_pj')->whereIn('user_id', $userIds)->distinct()->pluck('user_id')->all();

        return array_replace(array_fill_keys($userIds, false), array_fill_keys($active, true));
    }
}
