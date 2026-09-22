<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RencanaAksi extends Model
{
    use HasUuids;

    protected $table = 'rencana_aksi';

    public $timestamps = false;

    protected $fillable = ['indikator_id', 'tahun', 'unit_id', 'jadwal_tahunan_id', 'jadwal_snapshot_id', 'penanggung_jawab_id', 'uraian', 'status_alur', 'versi', 'alasan_revisi', 'created_by', 'disahkan_at', 'disahkan_by'];

    protected $casts = ['tahun' => 'integer', 'versi' => 'integer', 'disahkan_at' => 'datetime'];
}
