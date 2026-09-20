<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $permission_id
 * @property int|null $unit_id
 * @property string $alasan
 * @property int $diberikan_oleh
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Permission $permission
 * @property-read UnitKerja|null $unitKerja
 * @property-read User $diberikanOleh
 */
class UserPermissionGranted extends Model
{
    use HasFactory;

    protected $table = 'user_permission_granted';

    protected $fillable = [
        'user_id',
        'permission_id',
        'unit_id',
        'alasan',
        'diberikan_oleh',
    ];

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

    /** @return BelongsTo<UnitKerja, $this> */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function diberikanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diberikan_oleh');
    }
}
