<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Pagination\LengthAwarePaginator;

class GetActivationPage
{
    public function __construct(private PermissionResolver $resolver) {}

    /**
     * Antrean akun nonaktif terlama lebih dulu bagi pembaca pengguna; tombol aktivasi mengikuti izin akses aktor.
     *
     * @return array{users: LengthAwarePaginator, canActivate: bool}
     */
    public function handle(User $actor): array
    {
        return [
            'users' => User::where('status', 'nonaktif')->select(['id', 'nama', 'email', 'created_at'])->orderBy('created_at')->orderBy('id')->paginate(20),
            'canActivate' => $this->resolver->allows($actor, 'akses:update'),
        ];
    }
}
