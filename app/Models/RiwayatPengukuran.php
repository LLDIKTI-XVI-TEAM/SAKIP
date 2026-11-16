<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPengukuran extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'riwayat_pengukurans';

    protected $fillable = [
        'pengukuran_kinerja_id',
        'user_id',
        'status_dari',
        'status_ke',
        'catatan',
    ];

    public function pengukuranKinerja(): BelongsTo
    {
        return $this->belongsTo(PengukuranKinerja::class, 'pengukuran_kinerja_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
