<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

class BuktiDukung extends Model
{
    use HasUuids;

    protected $table = 'berkas';

    public $timestamps = false;

    protected $fillable = ['jenis_berkas_id', 'berkasable_type', 'berkasable_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'path', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks', 'uploaded_by', 'created_at', 'dihapus_pada', 'dihapus_oleh'];

    protected $casts = ['ukuran_bytes' => 'integer', 'created_at' => 'datetime', 'dihapus_pada' => 'datetime'];

    /** Bukti kerja memilih ujung rantai koreksi; baris lama tetap tersedia bagi versi historis. */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('berkas.dihapus_pada')->whereNotExists(function (QueryBuilder $replacement): void {
            $replacement->selectRaw('1')->from('berkas as pengganti')
                ->whereColumn('pengganti.menggantikan_id', 'berkas.id')
                ->whereColumn('pengganti.berkasable_type', 'berkas.berkasable_type')
                ->whereColumn('pengganti.berkasable_id', 'berkas.berkasable_id')
                ->whereNull('pengganti.dihapus_pada');
        });
    }
}
