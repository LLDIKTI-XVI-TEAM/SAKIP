<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenugasanIndikator extends Model
{
    use HasFactory;

    protected $table = 'penugasan_indikators';

    protected $fillable = [
        'indikator_kinerja_id',
        'unit_kerja_id',
        'user_id',
        'tahun',
        'is_active',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'is_active' => 'boolean',
    ];

    public function indikatorKinerja(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerja::class, 'indikator_kinerja_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pengukurans(): HasMany
    {
        return $this->hasMany(PengukuranKinerja::class, 'penugasan_indikator_id');
    }
}
