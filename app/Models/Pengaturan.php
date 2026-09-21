<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Pengaturan extends Model
{
    use HasUuids;

    protected $table = 'pengaturan';

    public $timestamps = false;

    protected $fillable = ['kunci', 'nilai', 'tipe', 'grup', 'updated_by', 'updated_at'];

    protected $casts = [];
}
