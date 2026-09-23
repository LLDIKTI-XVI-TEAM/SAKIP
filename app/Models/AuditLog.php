<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    use HasUuids;

    protected $table = 'audit_log';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'actor_id',
        'actor_type',
        'sumber',
        'operator_reference',
        'runtime_identity',
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
            'waktu' => 'immutable_datetime',
            'nilai_lama' => 'array',
            'nilai_baru' => 'array',
            'dasar_izin' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit bersifat append-only.'));
        static::deleting(fn () => throw new LogicException('Audit bersifat append-only.'));
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
