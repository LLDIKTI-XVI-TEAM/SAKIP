<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $kode
 * @property string|null $entitas
 * @property string|null $aksi
 * @property string|null $keterangan
 * @property string $butuh_scope
 * @property bool $sensitif
 * @property bool $aktif
 */
class Permission extends SpatiePermission
{
    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_UNIT = 'unit';

    /**
     * 9 permission yang wajib ber-scope unit saat diberikan via grant eksplisit.
     *
     * @var list<string>
     */
    public const UNIT_SCOPED_PERMISSIONS = [
        'pengukuran:create',
        'pengukuran:update',
        'rencana_aksi:read',
        'rencana_aksi:create',
        'rencana_aksi:update',
        'rencana_aksi:ajukan',
        'kegiatan:read',
        'kegiatan:create',
        'kegiatan:update',
    ];

    protected $fillable = [
        'name',
        'guard_name',
        'kode',
        'entitas',
        'aksi',
        'keterangan',
        'butuh_scope',
        'sensitif',
        'aktif',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'sensitif' => 'boolean',
            'aktif' => 'boolean',
        ]);
    }

    public function isUnitScoped(): bool
    {
        return $this->butuh_scope === self::SCOPE_UNIT;
    }

    /** @return HasMany<UserPermissionGranted, $this> */
    public function grantedUsers(): HasMany
    {
        return $this->hasMany(UserPermissionGranted::class, 'permission_id');
    }
}
