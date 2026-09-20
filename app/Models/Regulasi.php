<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $jenis
 * @property string $nomor
 * @property int $tahun
 * @property string $tentang
 * @property Carbon|null $tanggal
 * @property string|null $tautan_sumber
 * @property string|null $catatan
 * @property bool $aktif
 * @property int $versi
 * @property int $created_by
 * @property-read User|null $pembuat
 * @property-read Collection<int, Berkas> $berkas
 */
class Regulasi extends Model
{
    use HasFactory;

    protected $table = 'regulasi';

    protected $attributes = [
        'versi' => 1,
    ];

    protected $fillable = [
        'jenis',
        'nomor',
        'tahun',
        'tentang',
        'tanggal',
        'tautan_sumber',
        'catatan',
        'aktif',
        'versi',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'tanggal' => 'date',
            'aktif' => 'boolean',
            'versi' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphMany<Berkas, $this> */
    public function berkas(): MorphMany
    {
        return $this->morphMany(Berkas::class, 'berkasable');
    }

    /** @return HasMany<Renstra, $this> */
    public function renstras(): HasMany
    {
        return $this->hasMany(Renstra::class, 'regulasi_id');
    }

    /** @return HasMany<IndikatorKinerja, $this> */
    public function indikatorKinerjas(): HasMany
    {
        return $this->hasMany(IndikatorKinerja::class, 'regulasi_id');
    }
}
