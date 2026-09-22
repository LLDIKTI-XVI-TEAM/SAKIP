<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => function () use ($request) {
                $user = $request->user()?->fresh();
                $can = $this->capabilities($user);

                return [
                    'user' => $user ? ['id' => $user->id, 'nama' => $user->nama, 'email' => $user->email, 'is_active' => $user->is_active, 'role' => $user->is_active ? $user->roles()->value('kode') : null] : null,
                    'can' => [
                        'dashboard' => $can['dashboard'],
                        'pengukuran' => $can['pengukuran'],
                        'verifikasi' => $can['verifikasi'],
                        'aktivasi' => $can['aktivasi'],
                        'regulasi' => $can['regulasi'],
                        'assignRole' => $can['assignRole'],
                        'manageDeny' => $can['manageDeny'],
                        'unit' => $can['unit'],
                        'grant' => $can['grant'],
                        'renstra' => $can['renstra'],
                        'indikator' => $can['indikator'],
                        'rencanaAksi' => $can['rencanaAksi'],
                    ],
                ];
            },
            'can' => fn () => $this->capabilities($request->user()?->fresh()),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'message' => fn () => $request->session()->get('message'),
            ],
        ];
    }

    /** @return array<string, bool> */
    private function capabilities(?User $user): array
    {
        $capabilities = [
            'dashboard' => false,
            'pengukuran' => false,
            'verifikasi' => false,
            'aktivasi' => false,
            'assignRole' => false,
            'manageDeny' => false,
            'unit' => false,
            'grant' => false,
            'regulasi' => false,
            'regulasi:create' => false,
            'regulasi:read' => false,
            'regulasi:update' => false,
            'regulasi:delete' => false,
            'berkas:delete' => false,
            'renstra' => false,
            'indikator' => false,
            'rencanaAksi' => false,
        ];

        if ($user === null || ! $user->is_active) {
            return $capabilities;
        }

        $resolver = app(PermissionResolver::class);
        $regulasiRead = $resolver->allows($user, 'regulasi:read');
        $renstraRead = $resolver->allows($user, 'renstra:read');
        $indikatorRead = $resolver->allows($user, 'indikator:read');

        $permRA = Permission::where('kode', 'rencana_aksi:read')->where('aktif', true)->first();
        $hasGlobalDenyRA = $permRA ? DB::table('user_permission_denied')->where('user_id', $user->id)->where('permission_id', $permRA->id)->whereNull('unit_id')->exists() : true;
        $rencanaAksiRead = false;
        if (! $hasGlobalDenyRA && $permRA) {
            $hasRoleRA = DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
                ->where('user_roles.user_id', $user->id)
                ->where('roles.aktif', true)
                ->where('role_permissions.permission_id', $permRA->id)
                ->exists();

            if ($hasRoleRA) {
                $rencanaAksiRead = true;
            } else {
                $deniedUnits = DB::table('user_permission_denied')
                    ->where('user_id', $user->id)
                    ->where('permission_id', $permRA->id)
                    ->whereNotNull('unit_id')
                    ->pluck('unit_id')
                    ->all();

                $rencanaAksiRead = DB::table('user_permission_granted')
                    ->where('user_id', $user->id)
                    ->where('permission_id', $permRA->id)
                    ->whereNotNull('unit_id')
                    ->whereNotIn('unit_id', $deniedUnits)
                    ->exists();
            }
        }

        return [
            'dashboard' => $resolver->allows($user, 'dashboard:read'),
            'pengukuran' => $resolver->allows($user, 'pengukuran:read'),
            'verifikasi' => $resolver->allows($user, 'pengukuran:read')
                && ($resolver->allows($user, 'pengukuran:verifikasi')
                    || $resolver->allows($user, 'pengukuran:sahkan')
                    || $resolver->allows($user, 'pengukuran:kembalikan')),
            'aktivasi' => $resolver->allows($user, 'pengguna:read'),
            'assignRole' => $resolver->allows($user, 'pengguna:read')
                && $resolver->allows($user, 'akses:update'),
            'manageDeny' => $resolver->allows($user, 'akses:update'),
            'unit' => $resolver->allows($user, 'unit:read'),
            'grant' => $resolver->allows($user, 'akses:update')
                && $user->hasAnyRole(['admin', 'superadmin']),
            'regulasi' => $regulasiRead,
            'regulasi:create' => $resolver->allows($user, 'regulasi:create'),
            'regulasi:read' => $regulasiRead,
            'regulasi:update' => $resolver->allows($user, 'regulasi:update'),
            'regulasi:delete' => $resolver->allows($user, 'regulasi:delete'),
            'berkas:delete' => $resolver->allows($user, 'berkas:delete'),
            'renstra' => $renstraRead,
            'indikator' => $indikatorRead,
            'rencanaAksi' => $rencanaAksiRead,
        ];
    }
}
