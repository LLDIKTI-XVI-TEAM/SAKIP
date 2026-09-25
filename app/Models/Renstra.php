<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property string $id
 * @property string $kode
 * @property string $nama
 * @property int $tahun_mulai
 * @property int $tahun_selesai
 * @property int $tahun_akhir
 * @property string|null $deskripsi
 * @property string|null $keterangan
 * @property string|null $dasar_hukum
 * @property string $status
 * @property bool $is_aktif
 * @property string|null $regulasi_id
 * @property string|null $created_by
 * @property-read User|null $pembuat
 * @property-read Regulasi|null $regulasi
 * @property-read Collection<int, Berkas> $berkas
 * @property-read Collection<int, SasaranStrategis> $sasaranStrategis
 * @property-read Collection<int, RenstraPk> $renstraPk
 * @property-read Collection<int, JadwalTahunan> $jadwalTahunan
 */
class Renstra extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_NONAKTIF = 'nonaktif';

    public const STATUS_DIARSIPKAN = 'diarsipkan';

    protected $table = 'renstras';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'is_aktif' => false,
    ];

    protected $fillable = [
        'regulasi_id',
        'created_by',
        'kode',
        'nama',
        'tahun_mulai',
        'tahun_selesai',
        'tahun_akhir',
        'deskripsi',
        'keterangan',
        'dasar_hukum',
        'status',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'tahun_mulai' => 'integer',
            'tahun_selesai' => 'integer',
            'is_aktif' => 'boolean',
            'status' => 'string',
        ];
    }

    /**
     * Memastikan kompatibilitas antara tahun_akhir dan tahun_selesai di basis data.
     */
    protected function tahunAkhir(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value, array $attributes) => $attributes['tahun_selesai'] ?? $value,
            set: fn (?int $value) => ['tahun_selesai' => $value],
        );
    }

    /**
     * Memastikan kompatibilitas antara keterangan dan deskripsi di basis data.
     */
    protected function keterangan(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes) => $attributes['deskripsi'] ?? $value,
            set: fn (?string $value) => ['deskripsi' => $value],
        );
    }

    /**
     * Menjaga keselarasan is_aktif dengan status operasional Renstra.
     */
    public function setStatusAttribute(?string $value): void
    {
        $status = $value ?? self::STATUS_DRAFT;
        $this->attributes['status'] = $status;
        $this->attributes['is_aktif'] = ($status === self::STATUS_AKTIF);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function regulasi(): BelongsTo
    {
        return $this->belongsTo(Regulasi::class, 'regulasi_id');
    }

    public function berkas(): MorphMany
    {
        return $this->morphMany(Berkas::class, 'berkasable');
    }

    public function sasaranStrategis(): HasMany
    {
        return $this->hasMany(SasaranStrategis::class, 'renstra_id')->orderBy('urutan');
    }

    public function renstraPk(): HasMany
    {
        return $this->hasMany(RenstraPk::class, 'renstra_id');
    }

    public function jadwalTahunan(): HasMany
    {
        return $this->hasMany(JadwalTahunan::class, 'renstra_id');
    }
}
