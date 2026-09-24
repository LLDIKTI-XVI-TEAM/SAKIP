<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    use HasUuids;

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_UNIT = 'unit';

    protected $fillable = ['kode', 'entitas', 'aksi', 'keterangan', 'butuh_scope', 'sensitif', 'aktif'];

    protected function casts(): array
    {
        return ['sensitif' => 'boolean', 'aktif' => 'boolean'];
    }

    public function isUnitScoped(): bool
    {
        return $this->butuh_scope === self::SCOPE_UNIT;
    }

    /** @return HasMany<UserPermissionGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(UserPermissionGrant::class, 'permission_id');
    }
}
