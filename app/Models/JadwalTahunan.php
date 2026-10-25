<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JadwalTahunan extends Model
{
    use HasUuids;

    protected $table = 'jadwal_tahunan';

    public $timestamps = false;

    protected $fillable = ['renstra_id', 'tahun', 'penutupan', 'status', 'renstra_pk_id', 'activated_at', 'closed_at', 'koreksi_mulai', 'koreksi_sampai', 'lingkup_koreksi'];

    protected $casts = ['tahun' => 'integer', 'penutupan' => 'date', 'revisi' => 'integer', 'pakai_persetujuan_pimpinan' => 'boolean', 'persetujuan_mulai' => 'date', 'persetujuan_selesai' => 'date', 'koreksi_mulai' => 'datetime', 'koreksi_sampai' => 'datetime', 'lingkup_koreksi' => 'array'];

    protected $appends = ['is_terkunci'];

    /** @return BelongsTo<Renstra, $this> */
    public function renstra(): BelongsTo
    {
        return $this->belongsTo(Renstra::class);
    }

    /**
     * Jadwal yang terkait langsung ke PK atau ke Renstra+tahun yang sama (fallback legacy).
     *
     * @param  Builder<self>  $query
     */
    public function scopeTerkaitPk(Builder $query, RenstraPk $pk): void
    {
        $query->where(function (Builder $sub) use ($pk) {
            $sub->where('renstra_pk_id', $pk->id)
                ->orWhere(fn (Builder $legacy) => $legacy->where('renstra_id', $pk->renstra_id)->where('tahun', $pk->tahun));
        });
    }

    /**
     * Jadwal yang membuat PK tidak lagi boleh diubah tanpa alasan: aktif, ditutup, atau pernah diaktifkan.
     *
     * @param  Builder<self>  $query
     */
    public function scopeMengunciPk(Builder $query): void
    {
        $query->where(function (Builder $sub) {
            $sub->whereIn('status', ['aktif', 'ditutup'])
                ->orWhereNotNull('activated_at');
        });
    }

    /** @return HasMany<PeriodeJadwal, $this> */
    public function periode(): HasMany
    {
        return $this->hasMany(PeriodeJadwal::class, 'jadwal_id');
    }

    /** Snapshot allowlist kalender; pemanggil menjaga koherensi parent dan children dalam transaksi. @return array<string, mixed> */
    public function draftAttributes(): array
    {
        return [
            ...$this->only(['id', 'renstra_id', 'tahun', 'status', 'revisi']),
            'penutupan' => $this->penutupan?->format('Y-m-d'),
            'periode' => $this->periode->sortBy('periode_id')->map(fn (PeriodeJadwal $window): array => [
                'periode_id' => $window->periode_id,
                'pengisian_mulai' => $window->pengisian_mulai->format('Y-m-d'),
                'pengisian_selesai' => $window->pengisian_selesai->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    /** Aktivasi pertama sudah terjadi dan jadwal masih aktif; dasar replay no-op dan blocker sudah_aktif. */
    public function isActivated(): bool
    {
        return $this->status === 'aktif' && $this->activated_at !== null;
    }

    /** Tahun terkunci bila ditutup resmi (bisa sebelum tanggal) atau tanggal `penutupan` lewat pada tanggal bisnis. */
    public function tahunDitutup(?CarbonInterface $pada = null): bool
    {
        if ($this->status === 'ditutup') {
            return true;
        }
        $penutupan = $this->penutupan?->toDateString();

        return is_string($penutupan) && ($pada ?? now())->copy()->setTimezone(config('app.business_timezone'))->toDateString() > $penutupan;
    }

    public function getIsTerkunciAttribute(): bool
    {
        return $this->status === 'aktif'
            || $this->status === 'ditutup'
            || $this->activated_at !== null;
    }
}
