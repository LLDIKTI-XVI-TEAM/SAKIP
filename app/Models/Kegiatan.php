<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Kegiatan extends Model
{
    use HasUuids;

    protected $table = 'kegiatan';

    protected $fillable = ['unit_id', 'tahun', 'periode_id', 'nama', 'tujuan', 'sasaran_peserta', 'keterangan_peserta', 'lokasi', 'tanggal_rencana', 'tanggal_realisasi', 'status', 'realisasi_peserta', 'justifikasi', 'kegiatan_asal_id', 'uraian_pelaksanaan', 'kendala', 'strategi_tindaklanjut', 'created_by'];

    protected $casts = ['tahun' => 'integer', 'tanggal_rencana' => 'date', 'tanggal_realisasi' => 'date'];
}
