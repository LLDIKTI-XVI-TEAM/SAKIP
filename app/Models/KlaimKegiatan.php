<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class KlaimKegiatan extends Model
{
    use HasUuids;

    protected $table = 'klaim_kegiatan';

    public $timestamps = false;

    protected $fillable = ['rencana_aksi_id', 'kegiatan_id', 'komponen_id', 'arah_dampak', 'catatan', 'sumber_klaim', 'pengukuran_id', 'created_by', 'created_at'];
}
