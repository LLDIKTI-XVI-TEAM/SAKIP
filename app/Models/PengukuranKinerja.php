<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PengukuranKinerja extends Model
{
    use HasFactory;

    protected $table = 'pengukuran_kinerjas';

    protected $fillable = [
        'penugasan_indikator_id',
        'periode_jadwal_id',
        'target',
        'realisasi',
        'capaian_persen',
        'status',
        'kendala',
        'tindak_lanjut',
        'strategi',
        'diajukan_pada',
        'diverifikasi_oleh',
        'diverifikasi_pada',
        'disahkan_oleh',
        'disahkan_pada',
    ];

    protected $casts = [
        'target' => 'float',
        'realisasi' => 'float',
        'capaian_persen' => 'float',
        'diajukan_pada' => 'datetime',
        'diverifikasi_pada' => 'datetime',
        'disahkan_pada' => 'datetime',
    ];

    /** @return BelongsTo<PenugasanIndikator, $this> */
    public function penugasanIndikator(): BelongsTo
    {
        return $this->belongsTo(PenugasanIndikator::class, 'penugasan_indikator_id');
    }

    /** @return BelongsTo<PeriodeJadwal, $this> */
    public function periodeJadwal(): BelongsTo
    {
        return $this->belongsTo(PeriodeJadwal::class, 'periode_jadwal_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function pengesah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disahkan_oleh');
    }

    /** @return HasMany<BuktiDukung, $this> */
    public function buktiDukungs(): HasMany
    {
        return $this->hasMany(BuktiDukung::class, 'pengukuran_kinerja_id');
    }

    /** @return HasMany<RiwayatPengukuran, $this> */
    public function riwayats(): HasMany
    {
        return $this->hasMany(RiwayatPengukuran::class, 'pengukuran_kinerja_id')->latest();
    }

    /** @return HasOne<KinerjaSnapshot, $this> */
    public function snapshot(): HasOne
    {
        return $this->hasOne(KinerjaSnapshot::class, 'pengukuran_kinerja_id');
    }

    /**
     * Hitung capaian kinerja otomatis berdasarkan tipe perhitungan indikator
     */
    public static function hitungCapaian(float $target, ?float $realisasi, string $tipePerhitungan = 'naik_baik'): ?float
    {
        if ($realisasi === null) {
            return null;
        }

        if ($target <= 0) {
            return $realisasi > 0 ? 100.0 : 0.0;
        }

        if ($tipePerhitungan === 'turun_baik') {
            // Rumus turun_baik: ((2 * target - realisasi) / target) * 100
            $capaian = ((2 * $target - $realisasi) / $target) * 100;
        } else {
            // Rumus naik_baik: (realisasi / target) * 100
            $capaian = ($realisasi / $target) * 100;
        }

        return round(max(0, $capaian), 2);
    }
}
