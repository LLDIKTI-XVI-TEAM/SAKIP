<?php

namespace App\Services\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Models\UserPermissionGrant;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Support\Collection;

/**
 * Bentuk tampilan Izin Unit per pengguna × unit (Q62), dipakai daftar dan halaman detail agar satu sumber.
 */
final class IzinUnitPresenter
{
    /** Relasi yang harus dimuat pada setiap grant sebelum dipetakan. */
    public const RELASI = [
        'user:id,nama,email,status',
        'user.roles:id,nama,kode,aktif',
        'permission:id,kode,keterangan,butuh_scope,aktif',
        'unit:id,nama,status',
        'diberikanOleh:id,nama',
    ];

    /**
     * @param  Collection<int, UserPermissionGrant>  $grants  seluruh grant unit milik satu pasangan pengguna-unit
     * @return array<string, mixed>
     */
    public static function kelompok(Collection $grants, bool $rinci = false): array
    {
        /** @var UserPermissionGrant $pertama */
        $pertama = $grants->first();
        $user = $pertama->user;
        $unit = $pertama->unit;
        $keputusan = $user ? app(PermissionResolver::class)->decideMany($user, $grants->pluck('permission.kode')->filter()->unique()->values()->all(), $pertama->unit_id) : [];

        return [
            'key' => "{$pertama->user_id}:{$pertama->unit_id}",
            'user_id' => $pertama->user_id,
            'user_name' => $user?->nama ?? '-',
            'user_email' => $user?->email ?? '-',
            'user_roles' => $user?->roles->map(fn (Role $role) => $role->aktif ? $role->nama : "{$role->nama} (nonaktif)")->all() ?? [],
            'unit_id' => $pertama->unit_id,
            'unit_nama' => $unit?->nama,
            // Diturunkan dari PermissionResolver: akun nonaktif, tanpa peran aktif, dan unit nonaktif menggugurkan seluruh kelompok; izin nonaktif dan deny eksplisit ditandai per butir.
            'tidak_berlaku' => array_keys(array_filter([
                'Akun nonaktif' => $user?->status !== 'aktif',
                'Tanpa peran aktif' => collect($keputusan)->contains('reason', 'no_role'),
                'Unit nonaktif' => $unit?->status !== 'aktif',
            ])),
            'izin' => $grants
                ->sortBy(fn (UserPermissionGrant $grant) => self::urutan($grant->permission?->kode))
                ->values()
                ->map(fn (UserPermissionGrant $grant) => [
                    'grant_id' => $grant->id,
                    'permission_id' => $grant->permission_id,
                    'label' => PermissionCatalog::LABEL_IZIN_UNIT[$grant->permission?->kode] ?? $grant->permission?->keterangan ?? $grant->permission?->kode,
                    'aktif' => (bool) $grant->permission?->aktif,
                    'ditolak' => ($keputusan[$grant->permission?->kode ?? '']['reason'] ?? null) === 'explicit_deny',
                    ...($rinci ? [
                        'alasan' => $grant->alasan,
                        'diberikan_oleh_nama' => $grant->diberikanOleh?->nama ?? '-',
                        'created_at' => $grant->created_at?->toISOString(),
                    ] : []),
                ])
                ->all(),
        ];
    }

    /**
     * Pilihan izin yang sah diberikan, urut katalog.
     *
     * @return list<array{id: string, kode: string, label: string}>
     */
    public static function pilihanIzin(): array
    {
        return Permission::where('butuh_scope', Permission::SCOPE_UNIT)
            ->where('aktif', true)
            ->whereIn('kode', PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS)
            ->select('id', 'kode')
            ->get()
            ->sortBy(fn (Permission $p) => self::urutan($p->kode))
            ->values()
            ->map(fn (Permission $p) => [
                'id' => $p->id,
                'kode' => $p->kode,
                'label' => PermissionCatalog::LABEL_IZIN_UNIT[$p->kode],
            ])
            ->all();
    }

    private static function urutan(?string $kode): int
    {
        $posisi = array_search($kode, PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS, true);

        return $posisi === false ? PHP_INT_MAX : $posisi;
    }
}
