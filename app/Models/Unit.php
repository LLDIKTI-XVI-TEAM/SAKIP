<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Memeriksa apakah unit dapat dihapus (delete guard PRD §7.7).
     * Unit yang memiliki relasi dengan indikator, rencana aksi, kegiatan, atau grant dilarang dihapus.
     */
    public function isDeletable(): bool
    {
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

        return true;
    }
}
