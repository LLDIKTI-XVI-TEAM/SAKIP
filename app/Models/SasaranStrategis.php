<?php

namespace App\Models;

use Carbon\Carbon;
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

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected static function booted(): void
    {
        static::updating(function (SasaranStrategis $sasaran) {
            if ($sasaran->isDirty('renstra_id') && $sasaran->getOriginal('renstra_id') !== null) {
                if ($sasaran->indikatorKinerjas()->exists()) {
                    throw new \InvalidArgumentException('Sasaran strategis yang telah memiliki indikator kinerja tidak boleh dipindahkan ke Renstra lain.');
                }
            }
        });

        static::saving(function (SasaranStrategis $sasaran) {
            if ($sasaran->exists && ! $sasaran->isDirty()) {
                return;
            }
            $now = Carbon::now();
            if ($sasaran->exists && $sasaran->getOriginal('updated_at')) {
                $orig = Carbon::parse($sasaran->getOriginal('updated_at'));
                if ($now->lte($orig)) {
                    $now = $orig->copy()->addMicrosecond();
                }
            }
            $sasaran->updated_at = $now;
        });
    }

    /** @return BelongsTo<Renstra, $this> */
    public function renstra(): BelongsTo
    {
        return $this->belongsTo(Renstra::class, 'renstra_id');
    }

    public function indikatorKinerjas(): HasMany
    {
        return $this->hasMany(IndikatorKinerja::class, 'sasaran_strategis_id');
    }
}
