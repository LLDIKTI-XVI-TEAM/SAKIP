<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class JadwalSnapshotKomponen extends Model
{
    use HasUuids;

    protected $table = 'jadwal_snapshot_komponen';

    public $timestamps = false;

    protected $fillable = ['jadwal_snapshot_id', 'komponen_id', 'kode', 'label', 'peran', 'bobot', 'urutan'];

    protected $casts = ['bobot' => 'decimal:12', 'urutan' => 'integer'];
}
