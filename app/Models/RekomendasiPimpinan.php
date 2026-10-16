<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekomendasiPimpinan extends Model
{
    use HasUuids;

    protected $table = 'rekomendasi_pimpinan';

    public $timestamps = false;

    public const STATUS_DRAF = 'draf';

    public const STATUS_DISETUJUI = 'disetujui';

    /** Kode peran penyetuju utama; peran lain menandai persetujuan cadangan (Q51 §51.2 butir 2). */
    public const PERAN_PENYETUJU_UTAMA = 'pimpinan';

    protected $fillable = ['indikator_id', 'tahun', 'periode_id', 'isi', 'ditetapkan_oleh', 'status', 'disetujui_oleh', 'disetujui_oleh_role', 'disetujui_pada', 'rujukan_sumber', 'versi', 'created_at'];

    protected $attributes = ['status' => self::STATUS_DRAF, 'versi' => 1];

    protected $casts = ['tahun' => 'integer', 'versi' => 'integer', 'disetujui_pada' => 'datetime', 'created_at' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function penyusun(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function isCadangan(): bool
    {
        return $this->status === self::STATUS_DISETUJUI && $this->disetujui_oleh_role !== self::PERAN_PENYETUJU_UTAMA;
    }
}
