<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RencanaAksi extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rencana_aksi';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_DIAJUKAN = 'diajukan';

    public const STATUS_DIVERIFIKASI = 'diverifikasi';

    public const STATUS_DIKEMBALIKAN = 'dikembalikan';

    public const STATUS_DISAHKAN = 'disahkan';

    /**
     * `snapshot_draf_id` adalah jepit konteks non-FK (revisi D7 sempit,
     * audit-safe, tanpa relasi otorisasi): snapshot terakhir yang
     * direkonsiliasi draf ini. Ditulis server saja, tak pernah dari request.
     */
    protected $fillable = ['indikator_id', 'tahun', 'unit_id', 'jadwal_tahunan_id', 'snapshot_draf_id', 'penanggung_jawab_id', 'uraian', 'status_alur', 'versi', 'alasan_revisi', 'alasan_deviasi_pk', 'created_by', 'disahkan_at', 'disahkan_by'];

    /** Nilai bawaan header baru (cermin default basis data untuk model belum tersimpan). */
    protected $attributes = [
        'status_alur' => self::STATUS_DRAFT,
        'versi' => 1,
    ];

    protected $casts = ['tahun' => 'integer', 'versi' => 'integer', 'disahkan_at' => 'datetime'];

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

    /** @return BelongsTo<JadwalTahunan, $this> */
    public function jadwalTahunan(): BelongsTo
    {
        return $this->belongsTo(JadwalTahunan::class, 'jadwal_tahunan_id');
    }

    /** PIC yang berlaku pada saat penyusunan (jejak historis, bukan hak berjalan). */
    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penanggung_jawab_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function disahkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disahkan_by');
    }

    /** @return HasMany<RencanaAksiTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(RencanaAksiTarget::class, 'rencana_aksi_id');
    }

    /** @return HasMany<RencanaAksiVersi, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(RencanaAksiVersi::class, 'rencana_aksi_id')->orderBy('nomor');
    }

    /** @return HasOne<RencanaAksiVersi, $this> */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(RencanaAksiVersi::class, 'rencana_aksi_id')->orderByDesc('nomor');
    }

    public function scopeDraft(Builder $query): void
    {
        $query->where('rencana_aksi.status_alur', self::STATUS_DRAFT);
    }

    public function scopeDisahkan(Builder $query): void
    {
        $query->where('rencana_aksi.status_alur', self::STATUS_DISAHKAN);
    }
}
