<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasUuids;

    protected $table = 'unit';

    public const UPDATED_AT = null;

    protected $fillable = ['nama', 'status', 'created_by'];

    /** @return HasMany<PenugasanIndikator, $this> */
    public function penugasanIndikators(): HasMany
    {
        return $this->hasMany(PenugasanIndikator::class, 'unit_id');
    }
}
