<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RencanaAksi extends Model
{
    use HasUuids;

    protected $table = 'rencana_aksi';

    public $timestamps = false;

    protected $fillable = ['indikator_id', 'tahun', 'unit_id', 'jadwal_tahunan_id', 'jadwal_snapshot_id', 'penanggung_jawab_id', 'uraian', 'status_alur', 'versi', 'alasan_revisi', 'created_by', 'disahkan_at', 'disahkan_by'];

    protected $casts = ['tahun' => 'integer', 'versi' => 'integer', 'disahkan_at' => 'datetime'];

    /** @return BelongsTo<IndikatorKinerja, $this> */
    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerja::class, 'indikator_id');
    }

    /** @return BelongsTo<JadwalSnapshot, $this> */
    public function jadwalSnapshot(): BelongsTo
    {
        return $this->belongsTo(JadwalSnapshot::class, 'jadwal_snapshot_id');
    }

    /** @return BelongsTo<JadwalTahunan, $this> */
    public function jadwalTahunan(): BelongsTo
    {
        return $this->belongsTo(JadwalTahunan::class, 'jadwal_tahunan_id');
    }

    /** @return HasMany<RencanaAksiVersi, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(RencanaAksiVersi::class, 'rencana_aksi_id')->orderBy('nomor');
    }

    /** @return HasOne<RencanaAksiVersi, $this> */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(RencanaAksiVersi::class, 'rencana_aksi_id')->orderByDesc('nomor')->limit(1);
    }

    /** @return HasOne<RencanaAksiVersi, $this> */
    public function ratifiedVersion(): HasOne
    {
        return $this->hasOne(RencanaAksiVersi::class, 'rencana_aksi_id')->whereNotNull('disahkan_at')->orderByDesc('nomor')->limit(1);
    }

    public function targetUnitId(): string
    {
        return $this->unit_id;
    }
}
