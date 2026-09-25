<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Policies\RolePermissionPolicy;
use App\Services\Authorization\PermissionResolver;
use App\Services\PengaturanService;
use Illuminate\Http\Request;
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
                $can = $this->capabilities($user, $request);

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
                        'pengaturan' => $can['pengaturan'],
                        'pengaturan:update' => $can['pengaturan:update'],
                        'manageRolePermissions' => $can['manageRolePermissions'],
                        'jenisBerkas' => $can['jenisBerkas'],
                        'storagePolicy' => $can['storagePolicy'],
                        'storagePolicyUpdate' => $can['storagePolicyUpdate'],
                    ],
                ];
            },
            'can' => fn () => $this->capabilities($request->user()?->fresh(), $request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'message' => fn () => $request->session()->get('message'),
            ],
            'pengaturan' => fn () => app(PengaturanService::class)->allValues(),
        ];
    }

    /** @return array<string, bool> */
    private function capabilities(?User $user, ?Request $request = null): array
    {
        $defaultCapabilities = [
            'dashboard' => false,
            'pengukuran' => false,
            'verifikasi' => false,
            'aktivasi' => false,
            'assignRole' => false,
            'manageDeny' => false,
            'unit' => false,
            'grant' => false,
            'manageRolePermissions' => false,
            'regulasi' => false,
            'regulasi:create' => false,
            'regulasi:read' => false,
            'regulasi:update' => false,
            'regulasi:delete' => false,
            'berkas:delete' => false,
            'pengaturan' => false,
            'pengaturan:update' => false,
            'jenisBerkas' => false,
            'storagePolicy' => false,
            'storagePolicyUpdate' => false,
        ];

        if ($user === null || ! $user->is_active) {
            return $defaultCapabilities;
        }

        if ($request && $request->attributes->has('inertia_capabilities_'.$user->id)) {
            /** @var array<string, bool> */
            return $request->attributes->get('inertia_capabilities_'.$user->id);
        }

        $resolver = app(PermissionResolver::class);
        $regulasiRead = $resolver->allows($user, 'regulasi:read');
        $pengaturanUpdate = $resolver->allows($user, 'pengaturan:update');
        $jenisBerkasRead = $resolver->allows($user, 'jenis_berkas:read');

        $computed = [
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
            'manageRolePermissions' => app(RolePermissionPolicy::class)->decide($user)['allowed'],
            'regulasi' => $regulasiRead,
            'regulasi:create' => $resolver->allows($user, 'regulasi:create'),
            'regulasi:read' => $regulasiRead,
            'regulasi:update' => $resolver->allows($user, 'regulasi:update'),
            'regulasi:delete' => $resolver->allows($user, 'regulasi:delete'),
            'berkas:delete' => $resolver->allows($user, 'berkas:delete'),
            'pengaturan' => $pengaturanUpdate,
            'pengaturan:update' => $pengaturanUpdate,
            'jenisBerkas' => $jenisBerkasRead,
            'storagePolicy' => $pengaturanUpdate || $jenisBerkasRead,
            'storagePolicyUpdate' => $pengaturanUpdate,
        ];

        if ($request) {
            $request->attributes->set('inertia_capabilities_'.$user->id, $computed);
        }

        return $computed;
    }
}
