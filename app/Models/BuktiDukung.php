<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BuktiDukung extends Model
{
    use HasFactory;

    protected $table = 'bukti_dukungs';

    protected $fillable = [
        'pengukuran_kinerja_id',
        'nama_file',
        'file_path',
        'tipe_file',
        'file_size',
        'url_tautan',
        'keterangan',
    ];

    protected $appends = ['download_url'];

    public function pengukuranKinerja(): BelongsTo
    {
        return $this->belongsTo(PengukuranKinerja::class, 'pengukuran_kinerja_id');
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if ($this->url_tautan) {
            return $this->url_tautan;
        }

        if ($this->file_path && Storage::disk('public')->exists($this->file_path)) {
            return Storage::disk('public')->url($this->file_path);
        }

        return null;
    }
}
