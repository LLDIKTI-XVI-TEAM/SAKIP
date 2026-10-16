<?php

namespace App\Services\Authorization;

final class RoleCatalog
{
    // Urutan deklarasi untuk pilihan UI; seed_urutan tidak menimpa data role existing.
    public const ROLES = [
        'superadmin' => ['nama' => 'Superadmin', 'seed_urutan' => 1],
        'admin' => ['nama' => 'Administrator', 'seed_urutan' => 2],
        'perencanaan' => ['nama' => 'Perencanaan', 'seed_urutan' => 3],
        'pimpinan' => ['nama' => 'Pimpinan', 'seed_urutan' => 4],
        'pegawai' => ['nama' => 'Pegawai', 'seed_urutan' => 5],
    ];

    /** Peran yang membaca detail Rencana Aksi dan Kegiatan seluruh unit (Q54 §54.1). */
    public const PEMBACA_SELURUH_UNIT = ['superadmin', 'perencanaan', 'pimpinan'];

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
