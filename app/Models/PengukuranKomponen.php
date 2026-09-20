<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PengukuranKomponen extends Model
{
    use HasUuids;

    protected $table = 'pengukuran_komponen';

    public $timestamps = false;

    protected $fillable = ['pengukuran_id', 'komponen_id', 'nilai', 'updated_by', 'updated_at'];

    protected $casts = ['nilai' => 'float'];
}
