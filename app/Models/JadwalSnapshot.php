<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JadwalSnapshot extends Model
{
    use HasUuids;

    protected $table = 'jadwal_snapshot';

    public $timestamps = false;

    protected $fillable = ['jadwal_id', 'indikator_id', 'nomor_versi', 'menggantikan_id', 'alasan_koreksi', 'rujukan_koreksi', 'periode_mulai_id', 'unit_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target', 'baseline'];

    protected $casts = ['presisi' => 'integer', 'desimal_tampilan' => 'integer', 'target' => 'decimal:12', 'baseline' => 'decimal:12', 'nomor_versi' => 'integer'];

    /** @return BelongsTo<JadwalTahunan, $this> */
    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(JadwalTahunan::class, 'jadwal_id');
    }

    /** @return BelongsTo<Periode, $this> */
    public function periodeMulai(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_mulai_id');
    }

    /** @return HasMany<JadwalSnapshotKomponen, $this> */
    public function komponen(): HasMany
    {
        return $this->hasMany(JadwalSnapshotKomponen::class, 'jadwal_snapshot_id')->orderBy('urutan')->orderBy('kode');
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
