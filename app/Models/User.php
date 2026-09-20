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
        return $this->roles()->where('kode', $kode)->exists();
    }

    /** @return HasMany<PenugasanIndikator, $this> */
    public function penugasanIndikators(): HasMany
    {
        return $this->hasMany(PenugasanIndikator::class, 'user_id');
    }
}
