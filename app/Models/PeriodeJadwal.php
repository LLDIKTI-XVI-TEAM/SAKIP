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

    protected $fillable = ['jadwal_id', 'periode_id', 'pengisian_mulai', 'pengisian_selesai'];

    protected $casts = ['pengisian_mulai' => 'date', 'pengisian_selesai' => 'date'];

    /** Jendela pengisian PIC jalur normal berjalan pada tanggal bisnis (inklusif pengisian_mulai..pengisian_selesai). */
    public function berjalanPada(string $tanggal): bool
    {
        return $tanggal >= $this->pengisian_mulai->toDateString() && $tanggal <= $this->pengisian_selesai->toDateString();
    }

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
