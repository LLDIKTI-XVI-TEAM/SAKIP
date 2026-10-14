<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kegiatan extends Model
{
    use HasUuids;

    public const STATUSES = ['rencana', 'terlaksana', 'tidak_terlaksana', 'ditunda', 'batal'];

    public const RENCANA_FIELDS = ['unit_id', 'tahun', 'periode_id', 'nama', 'tujuan', 'sasaran_peserta', 'keterangan_peserta', 'lokasi', 'tanggal_rencana'];

    protected $table = 'kegiatan';

    protected $fillable = ['unit_id', 'tahun', 'periode_id', 'nama', 'tujuan', 'sasaran_peserta', 'keterangan_peserta', 'lokasi', 'tanggal_rencana', 'tanggal_realisasi', 'status', 'realisasi_peserta', 'justifikasi', 'kegiatan_asal_id', 'uraian_pelaksanaan', 'kendala', 'strategi_tindaklanjut', 'created_by'];

    protected $casts = ['tahun' => 'integer', 'sasaran_peserta' => 'integer', 'tanggal_rencana' => 'date', 'tanggal_realisasi' => 'date'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
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
