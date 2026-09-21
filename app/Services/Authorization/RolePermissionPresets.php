<?php

namespace App\Services\Authorization;

use InvalidArgumentException;

final class RolePermissionPresets
{
    public static function hasDefinedPreset(string $role): bool
    {
        if (! RoleCatalog::contains($role)) {
            throw new InvalidArgumentException('Peran tidak dikenal.');
        }

        // PIC adalah identitas resmi; paket izin bawaannya belum diputuskan.
        return $role !== 'pic';
    }

    /** Preset hanya dipasang bootstrap teraudit; bukan sumber izin saat request. @return list<string> */
    public static function forRole(string $role): array
    {
        return match ($role) {
            'superadmin' => PermissionCatalog::codes(),
            'perencanaan' => array_values(array_diff(PermissionCatalog::codes(), ['unit:create', 'unit:read', 'unit:update', 'unit:delete', 'pengguna:read', 'akses:update', 'pengaturan:update', 'pengukuran:setujui'])),
            'admin' => ['pengguna:read', 'akses:update', 'unit:create', 'unit:read', 'unit:update', 'unit:delete', 'pengaturan:update', 'komponen:read', 'jenis_berkas:read', 'regulasi:read', 'audit:read', 'dashboard:read', 'laporan:read'],
            'pimpinan' => ['pengukuran:read', 'pengukuran:setujui', 'rencana_aksi:read', 'kegiatan:read', 'berkas:read', 'komponen:read', 'jenis_berkas:read', 'regulasi:read', 'dashboard:read', 'laporan:read', 'laporan:ekspor', 'audit:read'],
            'pegawai' => ['pengukuran:read', 'komponen:read', 'jenis_berkas:read', 'regulasi:read', 'dashboard:read'],
            'pic' => throw new InvalidArgumentException('Preset peran belum ditetapkan.'),
            default => throw new InvalidArgumentException('Peran tidak dikenal.'),
        };
    }
}
