<?php

namespace App\Http\Middleware;

use App\Models\JadwalTahunan;
use App\Models\RencanaAksi;
use App\Models\RenstraPk;
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
                    'user' => $user ? ['id' => $user->id, 'nama' => $user->nama, 'email' => $user->email, 'status' => $user->status, 'role' => $user->status === 'aktif' ? $user->roles()->value('kode') : null] : null,
                    'can' => [
                        'dashboard' => $can['dashboard'],
                        'pengukuran' => $can['pengukuran'],
                        'verifikasi' => $can['verifikasi'],
                        'rencanaAksi' => $can['rencanaAksi'],
                        'aktivasi' => $can['aktivasi'],
                        'regulasi' => $can['regulasi'],
                        'renstra' => $can['renstra'],
                        'assignRole' => $can['assignRole'],
                        'manageDeny' => $can['manageDeny'],
                        'unit' => $can['unit'],
                        'grant' => $can['grant'],
                        'pengaturan' => $can['pengaturan'],
                        'pengaturan:update' => $can['pengaturan:update'],
                        'viewRolePermissions' => $can['viewRolePermissions'],
                        'viewEffectivePermissions' => $can['viewEffectivePermissions'],
                        'jenisBerkas' => $can['jenisBerkas'],
                        'storagePolicy' => $can['storagePolicy'],
                        'storagePolicyUpdate' => $can['storagePolicyUpdate'],
                        'sasaranIndikator' => $can['sasaranIndikator'],
                        'pk' => $can['pk'],
                        'periode' => $can['periode'],
                        'jadwal' => $can['jadwal'],
                        'pk:create' => $can['pk:create'],
                        'pk:update' => $can['pk:update'],
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
            'rencanaAksi' => false,
            'aktivasi' => false,
            'assignRole' => false,
            'manageDeny' => false,
            'unit' => false,
            'grant' => false,
            'viewRolePermissions' => false,
            'viewEffectivePermissions' => false,
            'regulasi' => false,
            'regulasi:create' => false,
            'regulasi:read' => false,
            'regulasi:update' => false,
            'regulasi:delete' => false,
            'renstra' => false,
            'renstra:create' => false,
            'renstra:read' => false,
            'renstra:update' => false,
            'renstra:delete' => false,
            'berkas:delete' => false,
            'pengaturan' => false,
            'pengaturan:update' => false,
            'jenisBerkas' => false,
            'storagePolicy' => false,
            'storagePolicyUpdate' => false,
            'sasaranIndikator' => false,
            'pk' => false,
            'periode' => false,
            'jadwal' => false,
            'pk:create' => false,
            'pk:update' => false,
        ];

        if ($user === null || $user->status !== 'aktif') {
            return $defaultCapabilities;
        }

        if ($request && $request->attributes->has('inertia_capabilities_'.$user->id)) {
            /** @var array<string, bool> */
            return $request->attributes->get('inertia_capabilities_'.$user->id);
        }

        // Satu keputusan batch untuk seluruh izin langsung; capability berbasis
        // Policy tetap memakai Policy masing-masing.
        $izin = app(PermissionResolver::class)->decideMany($user, [
            'dashboard:read', 'pengukuran:read', 'pengukuran:verifikasi', 'pengukuran:sahkan', 'pengukuran:kembalikan',
            'pengguna:read', 'akses:update', 'unit:read', 'delegasi:update',
            'regulasi:read', 'regulasi:create', 'regulasi:update', 'regulasi:delete',
            'renstra:read', 'renstra:create', 'renstra:update', 'renstra:delete',
            'berkas:delete', 'pengaturan:update', 'jenis_berkas:read', 'indikator:read',
            'periode:create', 'periode:update', 'pk:create', 'pk:update',
        ]);
        $boleh = fn (string $kode): bool => $izin[$kode]['allowed'];
        $regulasiRead = $boleh('regulasi:read');
        $renstraRead = $boleh('renstra:read');
        $pengaturanUpdate = $boleh('pengaturan:update');
        $jenisBerkasRead = $boleh('jenis_berkas:read');

        $computed = [
            'dashboard' => $boleh('dashboard:read'),
            'pengukuran' => $boleh('pengukuran:read'),
            'verifikasi' => $boleh('pengukuran:read')
                && ($boleh('pengukuran:verifikasi')
                    || $boleh('pengukuran:sahkan')
                    || $boleh('pengukuran:kembalikan')),
            'aktivasi' => $boleh('pengguna:read'),
            'assignRole' => $boleh('pengguna:read')
                && $boleh('akses:update'),
            'manageDeny' => $boleh('akses:update'),
            'unit' => $boleh('unit:read'),
            'grant' => $boleh('delegasi:update'),
            'viewRolePermissions' => app(RolePermissionPolicy::class)->decide($user)['allowed'],
            'viewEffectivePermissions' => $boleh('pengguna:read'),
            'regulasi' => $regulasiRead,
            'regulasi:create' => $boleh('regulasi:create'),
            'regulasi:read' => $regulasiRead,
            'regulasi:update' => $boleh('regulasi:update'),
            'regulasi:delete' => $boleh('regulasi:delete'),
            'renstra' => $renstraRead,
            'renstra:create' => $boleh('renstra:create'),
            'renstra:read' => $renstraRead,
            'renstra:update' => $boleh('renstra:update'),
            'renstra:delete' => $boleh('renstra:delete'),
            'berkas:delete' => $boleh('berkas:delete'),
            'pengaturan' => $pengaturanUpdate,
            'pengaturan:update' => $pengaturanUpdate,
            'jenisBerkas' => $jenisBerkasRead,
            'storagePolicy' => $pengaturanUpdate || $jenisBerkasRead,
            'storagePolicyUpdate' => $pengaturanUpdate,
            'sasaranIndikator' => $boleh('indikator:read'),
            'rencanaAksi' => $user->can('viewAny', RencanaAksi::class),
            'pk' => $user->can('viewAny', RenstraPk::class),
            'periode' => $boleh('periode:create') || $boleh('periode:update'),
            'jadwal' => $user->can('viewAny', JadwalTahunan::class),
            'pk:create' => $boleh('pk:create'),
            'pk:update' => $boleh('pk:update'),
        ];

        if ($request) {
            $request->attributes->set('inertia_capabilities_'.$user->id, $computed);
        }

        return $computed;
    }
}
