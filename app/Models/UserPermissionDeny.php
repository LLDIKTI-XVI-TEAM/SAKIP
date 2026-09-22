<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property string $user_id
 * @property string $permission_id
 * @property string|null $unit_id
 * @property string $alasan
 * @property string $ditetapkan_oleh
 * @property Carbon $created_at
 */
class UserPermissionDeny extends Model
{
    use HasUuids;

    protected $table = 'user_permission_denied';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'permission_id', 'unit_id', 'alasan', 'ditetapkan_oleh'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Permission, $this> */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function penetap(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh');
    }

    /** Snapshot referensi tersimpan, termasuk permission legacy; tidak difilter katalog/keaktifan. @return array<string, mixed> */
    public function auditSnapshot(): array
    {
        return [
            'id' => $this->id, 'user_id' => $this->user_id, 'permission_id' => $this->permission_id,
            'permission_kode' => $this->permission->kode, 'butuh_scope' => $this->permission->butuh_scope,
            'unit_id' => $this->unit_id, 'unit_nama' => $this->unit?->nama, 'alasan' => $this->alasan,
            'ditetapkan_oleh' => $this->ditetapkan_oleh, 'created_at' => $this->created_at->toISOString(),
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (UserPermissionDeny $deny): void {
            if (trim((string) $deny->alasan) === '') {
                throw new InvalidArgumentException('Pencabutan izin memerlukan alasan.');
            }
        });
    }
}
