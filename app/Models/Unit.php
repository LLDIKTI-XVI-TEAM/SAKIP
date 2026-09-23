<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $nama
 * @property string $status
 * @property string $created_by
 * @property Carbon $created_at
 * @property int|null $indikators_count
 * @property int|null $rencana_aksis_count
 * @property int|null $kegiatans_count
 * @property int|null $permission_grants_count
 * @property int|null $permission_denies_count
 * @property int|null $jadwal_snapshots_count
 * @property-read User|null $creator
 * @property-read Collection<int, UserPermissionDeny> $permissionDenies
 * @property-read Collection<int, JadwalSnapshot> $jadwalSnapshots
 */
class Unit extends Model
{
    use HasUuids;

    protected $table = 'unit';

    public const UPDATED_AT = null;

    protected $fillable = ['nama', 'status', 'created_by'];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<IndikatorKinerja, $this> */
    public function indikators(): HasMany
    {
        return $this->hasMany(IndikatorKinerja::class, 'unit_id');
    }

    /** @return HasMany<RencanaAksi, $this> */
    public function rencanaAksis(): HasMany
    {
        return $this->hasMany(RencanaAksi::class, 'unit_id');
    }

    /** @return HasMany<Kegiatan, $this> */
    public function kegiatans(): HasMany
    {
        return $this->hasMany(Kegiatan::class, 'unit_id');
    }

    /** @return HasMany<UserPermissionGrant, $this> */
    public function permissionGrants(): HasMany
    {
        return $this->hasMany(UserPermissionGrant::class, 'unit_id');
    }

    /** @return HasMany<UserPermissionDeny, $this> */
    public function permissionDenies(): HasMany
    {
        return $this->hasMany(UserPermissionDeny::class, 'unit_id');
    }

    /** @return HasMany<JadwalSnapshot, $this> */
    public function jadwalSnapshots(): HasMany
    {
        return $this->hasMany(JadwalSnapshot::class, 'unit_id');
    }

    /**
     * Memeriksa apakah unit dapat dihapus (delete guard PRD §7.7).
     * Unit yang memiliki relasi dengan indikator, rencana aksi, kegiatan, snapshot jadwal, grant, atau denial dilarang dihapus.
     */
    public function isDeletable(): bool
    {
        if ($this->indikators_count !== null
            && $this->rencana_aksis_count !== null
            && $this->kegiatans_count !== null
            && $this->permission_grants_count !== null
            && $this->permission_denies_count !== null
            && $this->jadwal_snapshots_count !== null) {
            return ($this->indikators_count + $this->rencana_aksis_count + $this->kegiatans_count + $this->permission_grants_count + $this->permission_denies_count + $this->jadwal_snapshots_count) === 0;
        }

        if ($this->indikators()->exists()) {
            return false;
        }

        if ($this->rencanaAksis()->exists()) {
            return false;
        }

        if ($this->kegiatans()->exists()) {
            return false;
        }

        if ($this->permissionGrants()->exists()) {
            return false;
        }

        if ($this->permissionDenies()->exists()) {
            return false;
        }

        if ($this->jadwalSnapshots()->exists()) {
            return false;
        }

        return true;
    }
}
