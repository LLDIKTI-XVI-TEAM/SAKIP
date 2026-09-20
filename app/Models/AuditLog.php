<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'id',
        'actor_id',
        'waktu',
        'tindakan',
        'objek_tipe',
        'objek_id',
        'nilai_lama',
        'nilai_baru',
        'alasan',
        'dasar_izin',
    ];

    protected $casts = [
        'waktu' => 'datetime',
        'nilai_lama' => 'array',
        'nilai_baru' => 'array',
        'dasar_izin' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
