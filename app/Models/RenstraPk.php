<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RenstraPk extends Model
{
    use HasUuids;

    protected $table = 'renstra_pk';

    public $timestamps = false;

    protected $fillable = ['renstra_id', 'tahun', 'nomor_pk', 'tanggal_pk', 'created_by'];

    protected $casts = ['tahun' => 'integer', 'tanggal_pk' => 'date'];

    /** @return MorphMany<Berkas, $this> */
    public function berkas(): MorphMany
    {
        return $this->morphMany(Berkas::class, 'berkasable');
    }
}
