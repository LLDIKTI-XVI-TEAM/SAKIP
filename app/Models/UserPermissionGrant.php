<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string $user_id
 * @property string $permission_id
 * @property string|null $unit_id
 * @property string $alasan
 * @property string $diberikan_oleh
 * @property Carbon|null $created_at
 * @property-read User|null $user
 * @property-read Permission|null $permission
 * @property-read Unit|null $unit
 * @property-read User|null $diberikanOleh
 */
class UserPermissionGrant extends Model
{
    use HasUuids;

    protected $table = 'user_permission_granted';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'permission_id', 'unit_id', 'alasan', 'diberikan_oleh'];

    protected static function booted(): void
    {
        static::saving(function (UserPermissionGrant $grant): void {
            $permission = Permission::find($grant->permission_id);
            if (! $permission || ! $permission->aktif
                || ($permission->butuh_scope === 'unit') !== ($grant->unit_id !== null)
                || trim((string) $grant->alasan) === '') {
                throw new InvalidArgumentException('Grant memerlukan permission aktif, scope yang sesuai, dan alasan.');
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Permission, $this> */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function diberikanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diberikan_oleh');
    }
}
