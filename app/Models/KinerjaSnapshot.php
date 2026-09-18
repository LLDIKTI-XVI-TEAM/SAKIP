<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KinerjaSnapshot extends Model
{
    use HasFactory;

    protected $table = 'kinerja_snapshots';

    protected $fillable = [
        'pengukuran_kinerja_id',
        'snapshot_hash',
        'snapshot_data',
        'disahkan_oleh',
        'disahkan_pada',
    ];

    protected $casts = [
        'snapshot_data' => 'array',
        'disahkan_pada' => 'datetime',
    ];

    public function pengukuranKinerja(): BelongsTo
    {
        return $this->belongsTo(PengukuranKinerja::class, 'pengukuran_kinerja_id');
    }

    public function disahkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disahkan_oleh');
    }
}
