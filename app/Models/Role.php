<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class Role extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['kode', 'nama', 'keterangan', 'is_sistem', 'urutan', 'aktif'];

    protected function casts(): array
    {
        return ['is_sistem' => 'boolean', 'aktif' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleting(function (Role $role): void {
            if ($role->is_sistem) {
                throw new LogicException('Peran bawaan tidak dapat dihapus.');
            }
        });
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')->withPivot(['id', 'created_at']);
    }
}
