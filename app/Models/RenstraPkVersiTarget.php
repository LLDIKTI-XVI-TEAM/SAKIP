<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Target satu indikator aktif pada satu versi PK; setiap versi memuat seluruh indikator aktif
 * sehingga dapat dibaca utuh tanpa versi lain.
 *
 * @property string $id
 * @property string $renstra_pk_versi_id
 * @property string $indikator_id
 * @property string $nilai
 * @property-read IndikatorKinerja $indikator
 */
class RenstraPkVersiTarget extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'renstra_pk_versi_target';

    /** @return BelongsTo<IndikatorKinerja, $this> */
    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerja::class, 'indikator_id');
    }
}
