<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitKerja extends Model
{
    use HasFactory;

    protected $table = 'unit_kerjas';

    protected $fillable = [
        'kode',
        'nama',
        'singkatan',
        'parent_id',
        'urutan',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<UnitKerja, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'parent_id');
    }

    /** @return HasMany<UnitKerja, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(UnitKerja::class, 'parent_id');
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'unit_kerja_id');
    }

    /** @return HasMany<PenugasanIndikator, $this> */
    public function penugasanIndikators(): HasMany
    {
        return $this->hasMany(PenugasanIndikator::class, 'unit_kerja_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Memeriksa apakah unit kerja dapat dihapus (delete guard).
     * Unit yang memiliki keterkaitan dengan indikator, penugasan, bawahan, atau user tidak boleh dihapus.
     */
    public function isDeletable(): bool
    {
        if ($this->penugasanIndikators()->exists()) {
            return false;
        }

        if ($this->children()->exists()) {
            return false;
        }

        if ($this->users()->exists()) {
            return false;
        }

        return true;
    }
}
