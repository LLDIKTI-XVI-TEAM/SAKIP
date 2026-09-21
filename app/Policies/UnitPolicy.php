<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\Response;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return app(PermissionResolver::class)->allows($user, 'unit:read');
    }

    public function view(User $user, Unit $unit): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return app(PermissionResolver::class)->allows($user, 'unit:create');
    }

    public function update(User $user, Unit $unit): bool
    {
        return app(PermissionResolver::class)->allows($user, 'unit:update');
    }

    public function delete(User $user, Unit $unit): Response
    {
        if (! $unit->isDeletable()) {
            return Response::deny('Unit organisasi tidak dapat dihapus karena masih memiliki relasi dengan indikator kinerja, rencana aksi, kegiatan, atau izin terkait.');
        }

        if (! app(PermissionResolver::class)->allows($user, 'unit:delete') || ! $user->hasRole('superadmin')) {
            return Response::deny('Hanya peran Superadmin yang berwenang menghapus unit organisasi.');
        }

        return Response::allow();
    }
}
