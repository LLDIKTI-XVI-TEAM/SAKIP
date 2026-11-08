<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Versi PK append-only (dijaga trigger database). Versi 1 adalah PK awal yang dibentuk saat
 * revisi pertama disetujui: tanpa surat dan penyetuju, dengan daftar id lampiran induk PK yang
 * dibekukan saat itu. Versi berikutnya adalah revisi dengan surat Eselon I, alasan, dan lampiran sendiri.
 *
 * @property string $id
 * @property string $renstra_pk_id
 * @property int $nomor_versi
 * @property string|null $nomor_surat
 * @property Carbon|null $tanggal_surat
 * @property string|null $periode_mulai_id
 * @property string|null $alasan
 * @property string|null $disetujui_oleh
 * @property Carbon|null $disetujui_pada
 * @property list<string>|null $berkas_awal_ids
 * @property Carbon $created_at
 * @property-read Collection<int, RenstraPkVersiTarget> $target
 * @property-read Collection<int, Berkas> $berkas
 * @property-read Periode|null $periodeMulai
 * @property-read User|null $penyetuju
 */
class RenstraPkVersi extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'renstra_pk_versi';

    protected $fillable = ['renstra_pk_id', 'nomor_versi', 'nomor_surat', 'tanggal_surat', 'periode_mulai_id', 'alasan', 'disetujui_oleh', 'disetujui_pada', 'berkas_awal_ids'];

    protected function casts(): array
    {
        return [
            'nomor_versi' => 'integer',
            'tanggal_surat' => 'date',
            'disetujui_pada' => 'datetime',
            'berkas_awal_ids' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return HasMany<RenstraPkVersiTarget, $this> */
    public function target(): HasMany
    {
        return $this->hasMany(RenstraPkVersiTarget::class, 'renstra_pk_versi_id');
    }

    /** @return MorphMany<Berkas, $this> */
    public function berkas(): MorphMany
    {
        return $this->morphMany(Berkas::class, 'berkasable')
            ->whereNull('dihapus_pada');
    }

    /** @return BelongsTo<Periode, $this> */
    public function periodeMulai(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_mulai_id');
    }

    /** @return BelongsTo<User, $this> */
    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }
}
