<?php

namespace App\Services\Authorization;

final class RoleCatalog
{
    // Urutan deklarasi untuk pilihan UI; seed_urutan tidak menimpa data role existing.
    public const ROLES = [
        'superadmin' => ['nama' => 'Superadmin', 'seed_urutan' => 1],
        'admin' => ['nama' => 'Administrator', 'seed_urutan' => 2],
        'perencanaan' => ['nama' => 'Perencanaan', 'seed_urutan' => 3],
        'pic' => ['nama' => 'PIC', 'seed_urutan' => 6],
        'pimpinan' => ['nama' => 'Pimpinan', 'seed_urutan' => 4],
        'pegawai' => ['nama' => 'Pegawai', 'seed_urutan' => 5],
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::ROLES);
    }

    public static function contains(string $kode): bool
    {
        return array_key_exists($kode, self::ROLES);
    }
}
