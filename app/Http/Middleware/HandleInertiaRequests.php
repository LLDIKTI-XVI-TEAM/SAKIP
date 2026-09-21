<?php

namespace App\Http\Middleware;

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
                $resolver = app(PermissionResolver::class);
                $active = $user && $user->is_active;

                return [
                    'user' => $user ? ['id' => $user->id, 'nama' => $user->nama, 'email' => $user->email, 'is_active' => $user->is_active, 'role' => $active ? $user->roles()->value('kode') : null] : null,
                    'can' => [
                        'dashboard' => $active && $resolver->allows($user, 'dashboard:read'),
                        'pengukuran' => $active && $resolver->allows($user, 'pengukuran:read'),
                        'verifikasi' => $active && $resolver->allows($user, 'pengukuran:read') && ($resolver->allows($user, 'pengukuran:verifikasi') || $resolver->allows($user, 'pengukuran:sahkan') || $resolver->allows($user, 'pengukuran:kembalikan')),
                        'aktivasi' => $active && $resolver->allows($user, 'pengguna:read'),
                        'assignRole' => $active && $resolver->allows($user, 'pengguna:read') && $resolver->allows($user, 'akses:update'),
                    ],
                ];
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'message' => fn () => $request->session()->get('message'),
            ],
        ];
    }
}
