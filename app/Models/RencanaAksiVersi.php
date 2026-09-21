<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RencanaAksiVersi extends Model
{
    use HasUuids;

    protected $table = 'rencana_aksi_versi';

    public $timestamps = false;

    protected $fillable = ['rencana_aksi_id', 'jadwal_snapshot_id', 'nomor', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan', 'disahkan_by', 'disahkan_at', 'dasar_izin_pengajuan', 'snapshot'];

    protected $casts = ['nomor' => 'integer', 'snapshot' => 'array', 'dasar_izin_pengajuan' => 'array', 'diajukan_at' => 'datetime', 'disahkan_at' => 'datetime'];

    /** @return BelongsTo<RencanaAksi, $this> */
    public function rencanaAksi(): BelongsTo
    {
        return $this->belongsTo(RencanaAksi::class, 'rencana_aksi_id');
    }
}
