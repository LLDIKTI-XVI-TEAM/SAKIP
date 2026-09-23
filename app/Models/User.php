<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, HasUuids;

    protected $fillable = ['keycloak_id', 'nama', 'email', 'nomor_telepon', 'is_active'];

    protected $hidden = ['keycloak_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['id', 'sumber_pemberian', 'diberikan_oleh', 'audit_id', 'created_at']);
    }

    /** Pemeriksaan label peran bukan pengganti resolver permission. */
    public function hasRole(string $kode): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(function (Role $role) use ($kode): bool {
                if ($role->kode !== $kode) {
                    return false;
                }
                $aktif = $role->getAttribute('aktif');

                return $aktif !== null ? (bool) $aktif : (bool) Role::whereKey($role->id)->value('aktif');
            });
        }

        return $this->roles()->where('kode', $kode)->where('roles.aktif', true)->exists();
    }

    /**
     * @param  list<string>  $kodes
     */
    public function hasAnyRole(array $kodes): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(function (Role $role) use ($kodes): bool {
                if (! in_array($role->kode, $kodes, true)) {
                    return false;
                }
                $aktif = $role->getAttribute('aktif');

                return $aktif !== null ? (bool) $aktif : (bool) Role::whereKey($role->id)->value('aktif');
            });
        }

        return $this->roles()->whereIn('kode', $kodes)->where('roles.aktif', true)->exists();
    }

    /** @return HasMany<PenugasanIndikator, $this> */
    public function penugasanIndikators(): HasMany
    {
        return $this->hasMany(PenugasanIndikator::class, 'user_id');
    }

    /** @return HasMany<UserPermissionGrant, $this> */
    public function permissionGrants(): HasMany
    {
        return $this->hasMany(UserPermissionGrant::class, 'user_id');
    }
}
