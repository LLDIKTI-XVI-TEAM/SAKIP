<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class UserRole extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'role_id', 'diberikan_oleh', 'sumber_pemberian', 'audit_id'];

    protected static function booted(): void
    {
        static::creating(function (UserRole $assignment): void {
            if ($assignment->sumber_pemberian === 'sso_onboarding'
                && (! Role::whereKey($assignment->role_id)->where('kode', 'pegawai')->exists()
                    || ! User::whereKey($assignment->user_id)->where('is_active', false)->exists())) {
                throw new InvalidArgumentException('Onboarding hanya memberi peran Pegawai kepada akun belum aktif.');
            }
        });
    }
}
