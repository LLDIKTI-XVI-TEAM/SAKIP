<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Status capaian manual satu versi pengukuran yang telah disahkan (Data Model §2.21, Workflow §16).
 * Tabel append-only: revisi menulis baris baru, dan yang berlaku adalah baris terbaru per versi.
 */
class StatusCapaian extends Model
{
    use HasUuids;

    protected $table = 'status_capaian';

    public $timestamps = false;

    public const STATUS_TERCAPAI = 'tercapai';

    public const STATUS_BELUM_TERCAPAI = 'belum_tercapai';

    public const SUMBER_MANUAL = 'manual';

    /** Fase lanjutan: hanya kolom skema, belum ada jalur pengisian otomatis (Data Model §6). */
    public const SUMBER_DATA_SUMBER = 'data_sumber';

    protected $fillable = ['pengukuran_id', 'pengukuran_versi_id', 'status', 'sumber', 'ditetapkan_oleh', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    /**
     * Kolom `created_at` bertipe `timestamp(6)` (AC-4). Format ini dipakai Eloquent untuk mengikat nilai saat
     * insert dan membaca balik baris, sehingga urutan "berlaku" tidak kehilangan mikrodetik.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @return BelongsTo<PengukuranKinerja, $this> */
    public function pengukuran(): BelongsTo
    {
        return $this->belongsTo(PengukuranKinerja::class, 'pengukuran_id');
    }

    /** @return BelongsTo<KinerjaSnapshot, $this> */
    public function versi(): BelongsTo
    {
        return $this->belongsTo(KinerjaSnapshot::class, 'pengukuran_versi_id');
    }

    /** @return BelongsTo<User, $this> */
    public function ditetapkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh');
    }

    /**
     * Status yang berlaku pada satu atau beberapa versi pengukuran: baris terbaru menurut `created_at`, tie-break `id`
     * (Data Model §2.21), sehingga elemen pertama tiap versi adalah yang berlaku. Riwayat penetapan sebelumnya tetap
     * tersimpan dan tidak dihapus. Bentuk larik dipakai daftar agar satu halaman tidak menjadi N+1.
     *
     * @param  string|list<string>  $versiIds
     */
    public function scopeBerlaku(Builder $query, string|array $versiIds): void
    {
        $query->whereIn('pengukuran_versi_id', (array) $versiIds)->orderByDesc('created_at')->orderByDesc('id');
    }
}
