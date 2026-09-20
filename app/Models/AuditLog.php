<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

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

    protected $casts = [
        'waktu' => 'datetime',
        'nilai_lama' => 'array',
        'nilai_baru' => 'array',
        'dasar_izin' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
