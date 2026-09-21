<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodeJadwal extends Model
{
    use HasUuids;

    protected $table = 'jadwal_periode';

    public $timestamps = false;

    protected $fillable = ['jadwal_id', 'periode_id', 'pengisian_mulai', 'pengisian_selesai', 'reviu_mulai', 'reviu_selesai'];

    protected $casts = ['pengisian_mulai' => 'date', 'pengisian_selesai' => 'date', 'reviu_mulai' => 'date', 'reviu_selesai' => 'date'];

    /** @return BelongsTo<JadwalTahunan, $this> */
    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(JadwalTahunan::class, 'jadwal_id');
    }

    /** @return BelongsTo<Periode, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }
}
