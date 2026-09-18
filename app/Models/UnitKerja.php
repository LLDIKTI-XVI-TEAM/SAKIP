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
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(UnitKerja::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'unit_kerja_id');
    }

    public function penugasanIndikators(): HasMany
    {
        return $this->hasMany(PenugasanIndikator::class, 'unit_kerja_id');
    }
}
