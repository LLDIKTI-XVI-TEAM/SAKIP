<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Target Renstra hanya pembanding; capaian tetap memakai target tahunan PK. */
class TargetRenstra extends Model
{
    use HasUuids;

    protected $table = 'target_renstra';

    protected $fillable = ['indikator_id', 'tahun', 'nilai', 'updated_by'];

    protected $casts = [
        'tahun' => 'integer',
        'nilai' => 'decimal:12',
    ];
}
