<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SasaranStrategis extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sasaran_strategis';

    protected $fillable = [
        'renstra_id',
        'kode',
        'deskripsi',
        'urutan',
    ];

    protected $casts = [
        'urutan' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (SasaranStrategis $sasaran) {
            if ($sasaran->isDirty('renstra_id') && $sasaran->getOriginal('renstra_id') !== null) {
                if ($sasaran->indikatorKinerjas()->exists()) {
                    throw new \InvalidArgumentException('Sasaran strategis yang telah memiliki indikator kinerja tidak boleh dipindahkan ke Renstra lain.');
                }
            }
        });
    }

    public function renstra(): BelongsTo
    {
        return $this->belongsTo(Renstra::class, 'renstra_id');
    }

    public function indikatorKinerjas(): HasMany
    {
        return $this->hasMany(IndikatorKinerja::class, 'sasaran_strategis_id');
    }
}
