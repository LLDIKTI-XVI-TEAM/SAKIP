<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu sel indikator × tahun pada revisi; `nilai_lama` baru diisi saat revisi disimpan. */
class RevisiTargetRenstraNilai extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'revisi_target_renstra_nilai';

    protected $fillable = ['revisi_id', 'indikator_id', 'tahun', 'nilai_lama', 'nilai_baru'];

    protected $casts = [
        'tahun' => 'integer',
        'nilai_lama' => 'decimal:12',
        'nilai_baru' => 'decimal:12',
    ];

    /** @return BelongsTo<IndikatorKinerja, $this> */
    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerja::class, 'indikator_id');
    }
}
