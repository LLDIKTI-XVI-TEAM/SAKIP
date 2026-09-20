<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @property string $permission_id
 * @property string|null $unit_id
 * @property string $alasan
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
}
