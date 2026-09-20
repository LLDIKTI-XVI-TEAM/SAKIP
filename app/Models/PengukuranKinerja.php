<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PengukuranKinerja extends Model
{
    use HasUuids;

    protected $table = 'pengukuran_kinerjas';

    protected $fillable = ['indikator_id', 'tahun', 'periode_id', 'jadwal_snapshot_id', 'nilai', 'sumber_nilai', 'status_perhitungan', 'alasan_tidak_dapat_dihitung', 'alasan_historis', 'sumber_historis', 'catatan', 'status_alur', 'versi', 'created_by'];

    protected $casts = ['tahun' => 'integer', 'nilai' => 'float', 'versi' => 'integer'];

    /** @return BelongsTo<IndikatorKinerja, $this> */
    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerja::class, 'indikator_id');
    }

    /** @return BelongsTo<Periode, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }

    /** @return BelongsTo<JadwalSnapshot, $this> */
    public function jadwalSnapshot(): BelongsTo
    {
        return $this->belongsTo(JadwalSnapshot::class, 'jadwal_snapshot_id');
    }

    /** @return HasMany<PengukuranKomponen, $this> */
    public function komponen(): HasMany
    {
        return $this->hasMany(PengukuranKomponen::class, 'pengukuran_id');
    }

    /** @return HasMany<KinerjaSnapshot, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(KinerjaSnapshot::class, 'pengukuran_id')->orderBy('nomor');
    }

    /** @return HasOne<KinerjaSnapshot, $this> */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(KinerjaSnapshot::class, 'pengukuran_id')->orderByDesc('nomor')->limit(1);
    }

    /** @return HasOne<KinerjaSnapshot, $this> */
    public function ratifiedVersion(): HasOne
    {
        return $this->hasOne(KinerjaSnapshot::class, 'pengukuran_id')->whereNotNull('disahkan_at')->orderByDesc('nomor')->limit(1);
    }

    /** @return HasMany<BuktiDukung, $this> */
    public function buktiDukungs(): HasMany
    {
        return $this->hasMany(BuktiDukung::class, 'berkasable_id')->where('berkasable_type', 'pengukuran')->whereNull('dihapus_pada');
    }

    /** @return HasMany<RiwayatPengukuran, $this> */
    public function riwayats(): HasMany
    {
        return $this->hasMany(RiwayatPengukuran::class, 'pengukuran_kinerja_id')->latest();
    }

    public function targetUnitId(): string
    {
        return $this->jadwalSnapshot->unit_id;
    }

    public static function targetUnitSql(): string
    {
        return '(select unit_id from jadwal_snapshot where jadwal_snapshot.id = pengukuran_kinerjas.jadwal_snapshot_id)';
    }

    /** Hak PIC mengikuti riwayat efektif, bukan pembuat header atau PIC lama. */
    public function effectivePic(): ?PenugasanIndikator
    {
        return PenugasanIndikator::with('pic')->where('indikator_id', $this->indikator_id)
            ->whereDate('tanggal_mulai_berlaku', '<=', today())->orderByDesc('tanggal_mulai_berlaku')->orderByDesc('created_at')->first();
    }
}
