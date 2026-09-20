<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IndikatorKinerja extends Model
{
    use HasFactory;

    protected $table = 'indikator_kinerjas';

    protected $fillable = [
        'sasaran_strategis_id',
        'regulasi_id',
        'kode',
        'nama',
        'definisi_operasional',
        'satuan',
        'tipe_perhitungan',
        'jenis_agregasi',
        'is_aktif',
    ];

    protected $casts = [
        'is_aktif' => 'boolean',
    ];

    public function regulasi(): BelongsTo
    {
        return $this->belongsTo(Regulasi::class, 'regulasi_id');
    }

    public function sasaranStrategis(): BelongsTo
    {
        return $this->belongsTo(SasaranStrategis::class, 'sasaran_strategis_id');
    }

    public function targetKinerjas(): HasMany
    {
        return $this->hasMany(TargetKinerja::class, 'indikator_kinerja_id');
    }

    public function targetTahun(int $tahun): HasOne
    {
        return $this->hasOne(TargetKinerja::class, 'indikator_kinerja_id')->where('tahun', $tahun);
    }

    public function penugasanIndikators(): HasMany
    {
        return $this->hasMany(PenugasanIndikator::class, 'indikator_kinerja_id');
    }
}
