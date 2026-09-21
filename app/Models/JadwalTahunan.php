<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class JadwalTahunan extends Model
{
    use HasUuids;

    protected $table = 'jadwal_tahunan';

    public $timestamps = false;

    protected $fillable = ['renstra_id', 'tahun', 'rencana_aksi_mulai', 'rencana_aksi_selesai', 'penutupan', 'status', 'renstra_pk_id', 'activated_at', 'closed_at', 'koreksi_mulai', 'koreksi_sampai', 'lingkup_koreksi'];

    protected $casts = ['tahun' => 'integer', 'penutupan' => 'date', 'koreksi_mulai' => 'datetime', 'koreksi_sampai' => 'datetime', 'lingkup_koreksi' => 'array'];
}
