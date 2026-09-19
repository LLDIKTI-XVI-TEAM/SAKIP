<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeJadwal extends Model
{
    use HasFactory;

    protected $table = 'periode_jadwals';

    protected $fillable = [
        'tahun',
        'triwulan',
        'nama_periode',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'is_tahun_ditutup',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'triwulan' => 'integer',
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
        'is_tahun_ditutup' => 'boolean',
    ];

    public function pengukuranKinerjas(): HasMany
    {
        return $this->hasMany(PengukuranKinerja::class, 'periode_jadwal_id');
    }

    /**
     * Cek apakah periode saat ini aktif/buka dan belum melewati deadline
     */
    public function isAktifBuka(): bool
    {
        if ($this->status !== 'buka' || $this->is_tahun_ditutup) {
            return false;
        }

        $now = Carbon::now();

        return $now->between($this->tanggal_mulai, $this->tanggal_selesai);
    }
}
