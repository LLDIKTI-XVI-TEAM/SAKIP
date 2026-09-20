<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_log';

    public $timestamps = false;

    protected $fillable = [
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

    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
            'nilai_lama' => 'array',
            'nilai_baru' => 'array',
            'dasar_izin' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
