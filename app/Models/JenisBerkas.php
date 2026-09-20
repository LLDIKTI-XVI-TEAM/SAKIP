<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JenisBerkas extends Model
{
    use HasUuids;

    protected $table = 'jenis_berkas';

    protected $fillable = [
        'id',
        'nama',
        'tahap',
        'indikator_id',
        'wajib',
        'keterangan',
        'izinkan_file',
        'izinkan_tautan',
        'izinkan_teks',
        'semua_mode_wajib',
        'urutan',
        'format_diizinkan',
        'ukuran_maks_kb',
        'aktif',
        'created_by',
    ];

    protected $casts = [
        'wajib' => 'boolean',
        'izinkan_file' => 'boolean',
        'izinkan_tautan' => 'boolean',
        'izinkan_teks' => 'boolean',
        'semua_mode_wajib' => 'boolean',
        'urutan' => 'integer',
        'ukuran_maks_kb' => 'integer',
        'aktif' => 'boolean',
    ];

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerja::class, 'indikator_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
