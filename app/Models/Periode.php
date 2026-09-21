<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Periode extends Model
{
    use HasUuids;

    protected $table = 'periode';

    public $timestamps = false;

    protected $fillable = ['nama', 'urutan', 'aktif', 'is_nilai_akhir'];

    protected $casts = ['urutan' => 'integer', 'aktif' => 'boolean', 'is_nilai_akhir' => 'boolean'];
}
