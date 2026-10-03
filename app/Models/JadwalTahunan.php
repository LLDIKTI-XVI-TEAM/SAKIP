<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JadwalTahunan extends Model
{
    use HasUuids;

    protected $table = 'jadwal_tahunan';

    public $timestamps = false;

    protected $fillable = ['renstra_id', 'tahun', 'rencana_aksi_mulai', 'rencana_aksi_selesai', 'penutupan', 'status', 'renstra_pk_id', 'activated_at', 'closed_at', 'koreksi_mulai', 'koreksi_sampai', 'lingkup_koreksi'];

    protected $casts = ['tahun' => 'integer', 'rencana_aksi_mulai' => 'date', 'rencana_aksi_selesai' => 'date', 'penutupan' => 'date', 'revisi' => 'integer', 'pakai_persetujuan_pimpinan' => 'boolean', 'persetujuan_mulai' => 'date', 'persetujuan_selesai' => 'date', 'koreksi_mulai' => 'datetime', 'koreksi_sampai' => 'datetime', 'lingkup_koreksi' => 'array'];

    protected $appends = ['is_terkunci'];

    /** @return BelongsTo<Renstra, $this> */
    public function renstra(): BelongsTo
    {
        return $this->belongsTo(Renstra::class);
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
            'rencana_aksi_mulai' => $this->rencana_aksi_mulai?->format('Y-m-d'),
            'rencana_aksi_selesai' => $this->rencana_aksi_selesai?->format('Y-m-d'),
            'penutupan' => $this->penutupan?->format('Y-m-d'),
            'periode' => $this->periode->sortBy('periode_id')->map(fn (PeriodeJadwal $window): array => [
                'periode_id' => $window->periode_id,
                'pengisian_mulai' => $window->pengisian_mulai->format('Y-m-d'),
                'pengisian_selesai' => $window->pengisian_selesai->format('Y-m-d'),
                'reviu_mulai' => $window->reviu_mulai->format('Y-m-d'),
                'reviu_selesai' => $window->reviu_selesai->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function getIsTerkunciAttribute(): bool
    {
        return $this->status === 'aktif'
            || $this->status === 'ditutup'
            || $this->activated_at !== null;
    }
}
