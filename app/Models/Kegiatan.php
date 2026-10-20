<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kegiatan extends Model
{
    use HasUuids;

    public const STATUSES = ['rencana', 'terlaksana', 'tidak_terlaksana', 'ditunda', 'batal'];

    public const RENCANA_FIELDS = ['unit_id', 'tahun', 'periode_id', 'nama', 'tujuan', 'sasaran_peserta', 'keterangan_peserta', 'lokasi', 'tanggal_rencana', 'anggaran'];

    protected $table = 'kegiatan';

    protected $fillable = ['unit_id', 'tahun', 'periode_id', 'nama', 'tujuan', 'sasaran_peserta', 'keterangan_peserta', 'lokasi', 'tanggal_rencana', 'anggaran', 'tanggal_realisasi', 'status', 'realisasi_peserta', 'justifikasi', 'kegiatan_asal_id', 'uraian_pelaksanaan', 'kendala', 'strategi_tindaklanjut', 'created_by'];

    protected $casts = ['tahun' => 'integer', 'sasaran_peserta' => 'integer', 'tanggal_rencana' => 'date', 'tanggal_realisasi' => 'date', 'anggaran' => 'decimal:0'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /** Kegiatan tidak bertaut jadwal: cakupan jadwal = diklaim Rencana Aksi jadwal itu atau belum diklaim sama sekali (Q64 §64.2 butir 3). */
    public function scopeDalamCakupanJadwal(Builder $query, JadwalTahunan $jadwal): Builder
    {
        return $query->where('kegiatan.tahun', $jadwal->tahun)->where(fn (Builder $q) => $q
            ->whereExists(fn ($sub) => $sub->from('klaim_kegiatan as kk')->join('rencana_aksi as ra', 'ra.id', '=', 'kk.rencana_aksi_id')
                ->whereColumn('kk.kegiatan_id', 'kegiatan.id')->where('ra.jadwal_tahunan_id', $jadwal->id))
            ->orWhereNotExists(fn ($sub) => $sub->from('klaim_kegiatan as kk')->whereColumn('kk.kegiatan_id', 'kegiatan.id')));
    }

    /** Proyeksi rencana untuk form dan audit; tidak mengekspos data pelaksanaan. */
    public function rencanaAttributes(): array
    {
        return array_replace($this->only(['id', ...self::RENCANA_FIELDS, 'status']), [
            'tanggal_rencana' => $this->tanggal_rencana?->format('Y-m-d'),
        ]);
    }

    /** Hash keadaan menghindari lost update, termasuk dua perubahan dalam detik yang sama. */
    public function versionToken(): string
    {
        return hash('sha256', json_encode($this->getRawOriginal(), JSON_THROW_ON_ERROR));
    }
}
