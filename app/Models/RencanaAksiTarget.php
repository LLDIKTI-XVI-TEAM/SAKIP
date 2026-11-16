<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RencanaAksiTarget extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rencana_aksi_target';

    public $timestamps = false;

    protected $fillable = ['rencana_aksi_id', 'periode_id', 'komponen_id', 'nilai', 'keterangan', 'updated_by', 'updated_at'];

    protected $casts = ['nilai' => 'decimal:12', 'updated_at' => 'datetime'];

    /** @return BelongsTo<RencanaAksi, $this> */
    public function rencanaAksi(): BelongsTo
    {
        return $this->belongsTo(RencanaAksi::class, 'rencana_aksi_id');
    }

    /** @return BelongsTo<Periode, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }

    /** Baris manual memakai NULL (tanpa komponen semu); nonmanual wajib terisi. */
    public function komponen(): BelongsTo
    {
        return $this->belongsTo(IndikatorKomponen::class, 'komponen_id');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Baris target langsung indikator bertipe manual (satu per periode). */
    public function scopeManual(Builder $query): void
    {
        $query->whereNull('rencana_aksi_target.komponen_id');
    }

    /** Baris target per komponen efektif indikator nonmanual. */
    public function scopeBerkomponen(Builder $query): void
    {
        $query->whereNotNull('rencana_aksi_target.komponen_id');
    }
}
