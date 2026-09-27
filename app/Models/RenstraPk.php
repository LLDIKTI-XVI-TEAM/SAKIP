<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RenstraPk extends Model
{
    use HasUuids;

    protected $table = 'renstra_pk';

    public $timestamps = true;

    protected $fillable = [
        'renstra_id',
        'tahun',
        'nomor_pk',
        'tanggal_pk',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'tanggal_pk' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Renstra, $this> */
    public function renstra(): BelongsTo
    {
        return $this->belongsTo(Renstra::class, 'renstra_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphMany<Berkas, $this> */
    public function berkas(): MorphMany
    {
        return $this->morphMany(Berkas::class, 'berkasable')->whereNull('dihapus_pada');
    }

    /** @return HasOne<JadwalTahunan, $this> */
    public function jadwalTahunan(): HasOne
    {
        return $this->hasOne(JadwalTahunan::class, 'renstra_pk_id');
    }

    public function getMorphClass(): string
    {
        return 'renstra_pk';
    }
}
