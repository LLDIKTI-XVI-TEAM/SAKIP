<?php

namespace App\Models;

use App\Services\Authorization\RoleCatalog;
use Carbon\Carbon;
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
        'status',
        'tahun_mulai_berlaku',
        'created_by',
        'created_by_role',
    ];

    public const PROVENANCE_LEGACY_UNKNOWN = 'legacy_unknown';

    /** Nilai lifecycle indikator (pengganti flag aktif lama yang di-sunset, ADR 0003). */
    public const STATUS_AKTIF = 'aktif';

    public const STATUS_ARSIP = 'arsip';

    /**
     * Peran yang diizinkan untuk pembuatan indikator baru via model (hanya peran resmi).
     * Sentinel legacy_unknown tidak diizinkan untuk pembuatan baru.
     *
     * @return list<string>
     */
    public static function creatableRoles(): array
    {
        return RoleCatalog::codes();
    }

    /**
     * Seluruh nilai provenance yang sah tersimpan di basis data (termasuk sentinel legacy).
     *
     * @return list<string>
     */
    public static function validProvenanceRoles(): array
    {
        return [...RoleCatalog::codes(), self::PROVENANCE_LEGACY_UNKNOWN];
    }

    protected $casts = [
        'wajib_catatan' => 'boolean', 'presisi' => 'integer', 'desimal_tampilan' => 'integer',
        'tahun_mulai_berlaku' => 'integer',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** Nilai bawaan lifecycle indikator baru. */
    protected $attributes = [
        'status' => 'aktif',
    ];

    /** True bila indikator telah diarsipkan dan tidak menerima kerja operasional baru. */
    public function isArsip(): bool
    {
        return $this->status === self::STATUS_ARSIP;
    }

    protected static function booted(): void
    {
        static::creating(function (IndikatorKinerja $indikator) {
            if (empty($indikator->created_by_role) || ! in_array($indikator->created_by_role, self::creatableRoles(), true)) {
                throw new \InvalidArgumentException("Indikator kinerja baru wajib menyertakan 'created_by_role' yang sah dari katalog peran resmi.");
            }
        });

        static::updating(function (IndikatorKinerja $indikator) {
            if ($indikator->isDirty('created_by_role')) {
                throw new \LogicException("Atribut 'created_by_role' bersifat immutable dan tidak boleh diubah setelah indikator dibuat.");
            }
        });

        static::saving(function (IndikatorKinerja $indikator) {
            if ($indikator->exists && ! $indikator->isDirty()) {
                return;
            }
            $now = Carbon::now();
            if ($indikator->exists && $indikator->getOriginal('updated_at')) {
                $orig = Carbon::parse($indikator->getOriginal('updated_at'));
                if ($now->lte($orig)) {
                    $now = $orig->copy()->addMicrosecond();
                }
            }
            $indikator->updated_at = $now;
        });
    }

    /** @return BelongsTo<SasaranStrategis, $this> */
    public function sasaranStrategis(): BelongsTo
    {
        return $this->belongsTo(SasaranStrategis::class, 'sasaran_strategis_id');
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /** @return BelongsTo<Regulasi, $this> */
    public function regulasi(): BelongsTo
    {
        return $this->belongsTo(Regulasi::class, 'regulasi_id');
    }

    /** @return HasMany<IndikatorKomponen, $this> */
    public function komponen(): HasMany
    {
        return $this->hasMany(IndikatorKomponen::class, 'indikator_id')->orderBy('urutan');
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
}
