<?php

namespace App\Services\Authorization;

use App\Models\Role;
use Illuminate\Support\Facades\DB;

class RolePermissionState
{
    /**
     * Pemanggil memegang lock role dan metadata permission dalam transaksi.
     * Count audit append-only menangkap ABA bahkan ketika set kembali kosong.
     *
     * @return array{token: string, permissions: list<array{pivot_id: string, id: string, kode: string, keterangan: ?string, butuh_scope: string, aktif: bool, known: bool}>, codes: list<string>}
     */
    public function capture(Role $role): array
    {
        $permissions = DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_id', $role->id)->orderBy('permissions.id')->orderBy('role_permissions.id')
            ->get(['role_permissions.id as pivot_id', 'permissions.id', 'kode', 'keterangan', 'butuh_scope', 'aktif'])
            ->map(fn ($row) => [
                'pivot_id' => $row->pivot_id, 'id' => $row->id, 'kode' => $row->kode,
                'keterangan' => $row->keterangan, 'butuh_scope' => $row->butuh_scope,
                'aktif' => (bool) $row->aktif, 'known' => in_array($row->kode, PermissionCatalog::codes(), true),
            ])->all();
        $count = DB::table('audit_log')->where('objek_tipe', 'roles')->where('objek_id', $role->id)
            ->where('tindakan', 'role_permissions.ubah')->count();
        $canonical = ['role-permission-state-v1', $role->id, $role->kode, $role->aktif, $count,
            array_map(fn (array $row) => [$row['pivot_id'], $row['id'], $row['kode'], $row['butuh_scope'], $row['aktif'], $row['known']], $permissions)];
        $codes = array_column($permissions, 'kode');
        sort($codes, SORT_STRING);

        return ['token' => hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR)), 'permissions' => $permissions, 'codes' => $codes];
    }
}
