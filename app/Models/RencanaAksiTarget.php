<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RencanaAksiTarget extends Model
{
    use HasUuids;

    protected $table = 'rencana_aksi_target';

    public $timestamps = false;

    protected $fillable = ['rencana_aksi_id', 'periode_id', 'komponen_id', 'nilai', 'keterangan', 'updated_by', 'updated_at'];

    protected $casts = ['nilai' => 'float'];

    /** @return BelongsTo<Periode, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }

    /** @return BelongsTo<RencanaAksi, $this> */
    public function rencanaAksi(): BelongsTo
    {
        return $this->belongsTo(RencanaAksi::class, 'rencana_aksi_id');
    }
}
