<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** @property string $alasan */
class UserPermissionDeny extends Model
{
    use HasUuids;

    protected $table = 'user_permission_denied';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'permission_id', 'unit_id', 'alasan', 'ditetapkan_oleh'];

    protected static function booted(): void
    {
        static::saving(function (UserPermissionDeny $deny): void {
            if (trim((string) $deny->alasan) === '') {
                throw new InvalidArgumentException('Pencabutan izin memerlukan alasan.');
            }
        });
    }
}
