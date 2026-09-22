<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IndikatorKinerja extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'indikator_kinerjas';

    protected $fillable = [
        'sasaran_strategis_id',
        'regulasi_id',
        'kode',
        'nama',
        'definisi_operasional',
        'satuan',
        'tipe_perhitungan', 'unit_id', 'arah', 'presisi', 'desimal_tampilan', 'wajib_catatan',
        'jenis_agregasi',
        'is_aktif',
    ];

    protected $casts = [
        'is_aktif' => 'boolean', 'wajib_catatan' => 'boolean', 'presisi' => 'integer', 'desimal_tampilan' => 'integer',
    ];

    /** @return BelongsTo<SasaranStrategis, $this> */
    public function sasaranStrategis(): BelongsTo
    {
        return $this->belongsTo(SasaranStrategis::class, 'sasaran_strategis_id');
    }

    /** @return HasMany<TargetKinerja, $this> */
    public function targetKinerjas(): HasMany
    {
        return $this->hasMany(TargetKinerja::class, 'indikator_kinerja_id');
    }

    /** @return HasOne<TargetKinerja, $this> */
    public function targetTahun(int $tahun): HasOne
    {
        return $this->hasOne(TargetKinerja::class, 'indikator_kinerja_id')->where('tahun', $tahun);
    }

    /** @return HasMany<PenugasanIndikator, $this> */
    public function penugasanIndikators(): HasMany
    {
        return $this->hasMany(PenugasanIndikator::class, 'indikator_id');
    }

    /** @return HasMany<RencanaAksi, $this> */
    public function rencanaAksis(): HasMany
    {
        return $this->hasMany(RencanaAksi::class, 'indikator_id');
    }
}
