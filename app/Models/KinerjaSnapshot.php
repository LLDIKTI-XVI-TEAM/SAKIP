<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class KinerjaSnapshot extends Model
{
    // Model existing kini menunjuk satu-satunya versi pengukuran kanonis, bukan salinan arsip kedua.
    use HasUuids;

    protected $table = 'pengukuran_versi';

    public $timestamps = false;

    protected $fillable = ['pengukuran_id', 'rencana_aksi_versi_id', 'jadwal_snapshot_id', 'nomor', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan', 'disahkan_by', 'disahkan_at', 'dasar_izin_pengajuan', 'snapshot'];

    protected $casts = ['nomor' => 'integer', 'snapshot' => 'array', 'dasar_izin_pengajuan' => 'array', 'diajukan_at' => 'datetime', 'disahkan_at' => 'datetime'];
}
