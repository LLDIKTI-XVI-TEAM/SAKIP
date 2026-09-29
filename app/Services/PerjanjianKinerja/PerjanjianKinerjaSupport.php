<?php

namespace App\Services\PerjanjianKinerja;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class PerjanjianKinerjaSupport
{
    /**
     * Membersihkan string alasan audit dari byte NUL dan karakter kontrol ilegal.
     */
    public static function sanitizeAlasan(mixed $rawAlasan): string
    {
        if (! is_string($rawAlasan)) {
            return '';
        }

        // Pastikan encoding UTF-8 valid untuk mencegah SQLSTATE[22021]
        $clean = mb_convert_encoding($rawAlasan, 'UTF-8', 'UTF-8');

        // Hapus byte NUL untuk mencegah exception PostgreSQL SQLSTATE[22P05]
        $clean = str_replace("\0", '', $clean);

        // Hapus karakter kontrol yang tidak dapat dicetak, pertahankan newline dan tab
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean)
            ?? preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean)
            ?? '';

        $clean = trim($clean);

        if ($clean === '') {
            return '';
        }

        return mb_substr($clean, 0, 1000, 'UTF-8');
    }

    /**
     * Mengunci aktor, relasi peran, peran aktif, dan permission terkait secara deterministik
     * untuk mencegah race condition / TOCTOU pada mutasi wewenang concurrent.
     *
     * @param  list<string>  $permissionCodes
     */
    public static function lockActorAndPermissions(User $actor, array $permissionCodes): User
    {
        $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        if ($lockedActor->status !== 'aktif') {
            throw new AuthorizationException('Pengguna tidak aktif.');
        }

        $roleIds = DB::table('user_roles')
            ->where('user_id', $lockedActor->id)
            ->lockForUpdate()
            ->pluck('role_id')
            ->all();

        if ($roleIds !== []) {
            Role::query()->whereIn('id', array_unique($roleIds))->orderBy('id')->sharedLock()->get();
        }

        if ($permissionCodes !== []) {
            Permission::query()->whereIn('kode', array_unique($permissionCodes))->orderBy('id')->sharedLock()->get();
        }

        return $lockedActor;
    }
}
