<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RencanaAksi extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_DIVERIFIKASI = 'diverifikasi';
    public const STATUS_DIKEMBALIKAN = 'dikembalikan';
    public const STATUS_DISAHKAN = 'disahkan';

    protected $table = 'rencana_aksi';

    public $timestamps = false;

    protected $fillable = [
        'indikator_id',
        'tahun',
        'unit_id',
        'jadwal_tahunan_id',
        'jadwal_snapshot_id',
        'penanggung_jawab_id',
        'uraian',
        'status_alur',
        'versi',
        'alasan_revisi',
        'created_by',
        'disahkan_at',
        'disahkan_by',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'versi' => 'integer',
        'disahkan_at' => 'datetime',
    ];

    public function targetUnitId(): ?string
    {
        return $this->unit_id ? (string) $this->unit_id : null;
    }

    public function isDisahkan(): bool
    {
        return $this->status_alur === self::STATUS_DISAHKAN;
    }

    /** @return BelongsTo<IndikatorKinerja, $this> */
    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerja::class, 'indikator_id');
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penanggung_jawab_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function pengesah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disahkan_by');
    }

    /** @return HasMany<Berkas, $this> */
    public function buktiDukungs(): HasMany
    {
        return $this->hasMany(Berkas::class, 'berkasable_id')
            ->where('berkasable_type', 'rencana_aksi');
    }

    /** @return HasMany<RencanaAksiVersi, $this> */
    public function versis(): HasMany
    {
        return $this->hasMany(RencanaAksiVersi::class, 'rencana_aksi_id');
    }
}

