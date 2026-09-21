<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IndikatorKomponen extends Model
{
    use HasUuids;

    protected $table = 'indikator_komponen';

    public $timestamps = false;

    protected $fillable = ['indikator_id', 'kode', 'label', 'peran', 'bobot', 'urutan', 'satuan', 'aktif', 'created_by'];

    protected $casts = ['bobot' => 'float', 'urutan' => 'integer', 'aktif' => 'boolean'];
}
