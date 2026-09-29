<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $renstra_id
 * @property int $tahun
 * @property string $nomor_pk
 * @property Carbon $tanggal_pk
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Renstra $renstra
 * @property-read User $creator
 * @property-read Collection<int, Berkas> $berkas
 * @property-read JadwalTahunan|null $jadwalTahunan
 */
class RenstraPk extends Model
{
    use HasUuids;

    protected $table = 'renstra_pk';

    public $timestamps = true;

    protected $fillable = [
        'renstra_id',
        'tahun',
        'nomor_pk',
        'tanggal_pk',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'tanggal_pk' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Renstra, $this> */
    public function renstra(): BelongsTo
    {
        return $this->belongsTo(Renstra::class, 'renstra_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphMany<Berkas, $this> */
    public function berkas(): MorphMany
    {
        return $this->morphMany(Berkas::class, 'berkasable')
            ->whereNull('dihapus_pada');
    }

    /** @return HasOne<JadwalTahunan, $this> */
    public function jadwalTahunan(): HasOne
    {
        return $this->hasOne(JadwalTahunan::class, 'renstra_pk_id')
            ->orderByRaw("CASE WHEN status = 'aktif' THEN 0 WHEN status = 'ditutup' THEN 1 ELSE 2 END")
            ->orderByDesc('activated_at')
            ->orderByDesc('closed_at')
            ->orderByDesc('penutupan')
            ->orderByDesc('id');
    }

    public function resolveJadwalTahunan(): ?JadwalTahunan
    {
        if ($this->relationLoaded('jadwalTahunan') && $this->jadwalTahunan !== null) {
            return $this->jadwalTahunan;
        }

        $jadwal = $this->jadwalTahunan()->first();
        if ($jadwal !== null) {
            return $jadwal;
        }

        return JadwalTahunan::where(function ($q) {
            $q->where('renstra_pk_id', $this->id)
                ->orWhere(fn ($sub) => $sub->where('renstra_id', $this->renstra_id)->where('tahun', $this->tahun));
        })
            ->orderByRaw("CASE WHEN status = 'aktif' THEN 0 WHEN status = 'ditutup' THEN 1 ELSE 2 END")
            ->orderByDesc('activated_at')
            ->orderByDesc('closed_at')
            ->orderByDesc('penutupan')
            ->orderByDesc('id')
            ->first();
    }

    public function isJadwalAktif(): bool
    {
        return $this->resolveJadwalTahunan()?->status === 'aktif';
    }

    public function isJadwalTerkunci(): bool
    {
        $jadwal = $this->resolveJadwalTahunan();

        return $jadwal?->is_terkunci ?? false;
    }

    public function jadwalStatus(): ?string
    {
        return $this->resolveJadwalTahunan()?->status;
    }

    public function getMorphClass(): string
    {
        return 'renstra_pk';
    }
}
