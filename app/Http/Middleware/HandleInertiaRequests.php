<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Authorization\PermissionResolver;
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
                        'jenisBerkas' => $can['jenisBerkas'],
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
            'regulasi' => false,
            'regulasi:create' => false,
            'regulasi:read' => false,
            'regulasi:update' => false,
            'regulasi:delete' => false,
            'berkas:delete' => false,
            'jenisBerkas' => false,
            'jenis_berkas:create' => false,
            'jenis_berkas:read' => false,
            'jenis_berkas:update' => false,
            'jenis_berkas:delete' => false,
        ];

        if ($user === null || ! $user->is_active) {
            return $capabilities;
        }

        $resolver = app(PermissionResolver::class);
        $regulasiRead = $resolver->allows($user, 'regulasi:read');
        $jenisBerkasRead = $resolver->allows($user, 'jenis_berkas:read');

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
            'regulasi' => $regulasiRead,
            'regulasi:create' => $resolver->allows($user, 'regulasi:create'),
            'regulasi:read' => $regulasiRead,
            'regulasi:update' => $resolver->allows($user, 'regulasi:update'),
            'regulasi:delete' => $resolver->allows($user, 'regulasi:delete'),
            'berkas:delete' => $resolver->allows($user, 'berkas:delete'),
            'jenisBerkas' => $jenisBerkasRead,
            'jenis_berkas:create' => $resolver->allows($user, 'jenis_berkas:create'),
            'jenis_berkas:read' => $jenisBerkasRead,
            'jenis_berkas:update' => $resolver->allows($user, 'jenis_berkas:update'),
            'jenis_berkas:delete' => $resolver->allows($user, 'jenis_berkas:delete'),
        ];
    }
}
