<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BuktiDukung extends Model
{
    use HasUuids;

    protected $table = 'berkas';

    public $timestamps = false;

    protected $fillable = ['jenis_berkas_id', 'berkasable_type', 'berkasable_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'path', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks', 'uploaded_by', 'created_at', 'dihapus_pada', 'dihapus_oleh'];

    protected $casts = ['ukuran_bytes' => 'integer', 'created_at' => 'datetime', 'dihapus_pada' => 'datetime'];
}
