<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Draf revisi target Renstra aktif yang menjadi histori bernomor saat disimpan (Q71). */
class RevisiTargetRenstra extends Model
{
    use HasUuids;

    public const STATUS_DRAF = 'draf';

    public const STATUS_DISIMPAN = 'disimpan';

    protected $table = 'revisi_target_renstra';

    protected $fillable = ['renstra_id', 'status', 'nomor', 'justifikasi', 'versi', 'dibuat_oleh', 'disimpan_oleh', 'disimpan_pada'];

    protected $casts = [
        'nomor' => 'integer',
        'versi' => 'integer',
        'disimpan_pada' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function penyimpan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disimpan_oleh');
    }

    /** @return HasMany<RevisiTargetRenstraNilai, $this> */
    public function nilai(): HasMany
    {
        return $this->hasMany(RevisiTargetRenstraNilai::class, 'revisi_id');
    }
}
