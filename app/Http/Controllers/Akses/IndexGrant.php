<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UserPermissionGranted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IndexGrant extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('akses:update');

        $grants = UserPermissionGranted::with([
            'user:id,name,email,unit_kerja_id',
            'user.roles:id,name',
            'permission:id,name,kode,keterangan,butuh_scope',
            'unitKerja:id,kode,nama,singkatan',
            'diberikanOleh:id,name',
        ])
            ->latest()
            ->get()
            ->map(fn (UserPermissionGranted $grant) => [
                'id' => $grant->id,
                'user_id' => $grant->user_id,
                'user_name' => $grant->user->name,
                'user_email' => $grant->user->email,
                'user_roles' => $grant->user->roles->pluck('name')->all(),
                'permission_id' => $grant->permission_id,
                'permission_kode' => $grant->permission->kode ?? $grant->permission->name,
                'permission_keterangan' => $grant->permission->keterangan,
                'unit_id' => $grant->unit_id,
                'unit_kode' => $grant->unitKerja?->kode,
                'unit_nama' => $grant->unitKerja?->nama,
                'alasan' => $grant->alasan,
                'diberikan_oleh_nama' => $grant->diberikanOleh->name,
                'created_at' => $grant->created_at->format('d M Y H:i'),
            ]);

        $users = User::where('is_active', true)
            ->with('roles:id,name')
            ->select('id', 'name', 'email', 'unit_kerja_id')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
            ]);

        $units = UnitKerja::where('is_active', true)
            ->select('id', 'kode', 'nama', 'singkatan')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();

        $unitPermissions = Permission::where('butuh_scope', Permission::SCOPE_UNIT)
            ->where('aktif', true)
            ->select('id', 'name', 'kode', 'entitas', 'aksi', 'keterangan')
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'kode' => $p->kode ?? $p->name,
                'keterangan' => $p->keterangan,
            ]);

        return Inertia::render('Akses/GrantIndex', [
            'grants' => $grants,
            'users' => $users,
            'units' => $units,
            'unitPermissions' => $unitPermissions,
            'can' => [
                'create_grant' => true,
                'revoke_grant' => true,
            ],
        ]);
    }
}
