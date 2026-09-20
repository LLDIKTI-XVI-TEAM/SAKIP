<?php

namespace App\Policies;

use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UnitKerjaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermissionOrRole($user, 'unit:read');
    }

    public function view(User $user, UnitKerja $unit): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->checkPermissionOrRole($user, 'unit:create');
    }

    public function update(User $user, UnitKerja $unit): bool
    {
        return $this->checkPermissionOrRole($user, 'unit:update');
    }

    public function delete(User $user, UnitKerja $unit): Response
    {
        // AC-2: Unit yang masih memiliki relasi dilarang dihapus oleh siapa pun
        if (! $unit->isDeletable()) {
            return Response::deny('Unit organisasi tidak dapat dihapus karena masih memiliki relasi dengan indikator kinerja, struktur bawahan, atau pegawai terkait.');
        }

        // AC-3: Penghapusan unit kosong hanya dapat dilakukan oleh Superadmin
        if (! $user->hasRole('superadmin')) {
            return Response::deny('Hanya peran Superadmin yang berwenang menghapus unit organisasi.');
        }

        return Response::allow();
    }

    private function checkPermissionOrRole(User $user, string $permission): bool
    {
        if ($user->hasRole('superadmin') || $user->hasRole('admin')) {
            return true;
        }

        try {
            return $user->hasPermissionTo($permission);
        } catch (\Throwable) {
            return false;
        }
    }
}
