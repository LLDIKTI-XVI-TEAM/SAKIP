<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetKinerja extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'target_kinerjas';

    protected $fillable = [
        'indikator_kinerja_id',
        'tahun',
        'target_tahunan',
        'target_tw1',
        'target_tw2',
        'target_tw3',
        'target_tw4',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'target_tahunan' => 'float',
        'target_tw1' => 'float',
        'target_tw2' => 'float',
        'target_tw3' => 'float',
        'target_tw4' => 'float',
    ];

    public function indikatorKinerja(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerja::class, 'indikator_kinerja_id');
    }

    public function getTargetTriwulan(int $tw): float
    {
        return match ($tw) {
            1 => (float) $this->target_tw1,
            2 => (float) $this->target_tw2,
            3 => (float) $this->target_tw3,
            4 => (float) $this->target_tw4,
            default => 0.0,
        };
    }
}
