<?php

namespace App\Support;

final class PermissionCodes
{
    public const REGULASI_CREATE = 'regulasi:create';

    public const REGULASI_READ = 'regulasi:read';

    public const REGULASI_UPDATE = 'regulasi:update';

    public const REGULASI_DELETE = 'regulasi:delete';

    public const BERKAS_DELETE = 'berkas:delete';

    /** @return list<string> */
    public static function regulasi(): array
    {
        return [
            self::REGULASI_CREATE,
            self::REGULASI_READ,
            self::REGULASI_UPDATE,
            self::REGULASI_DELETE,
        ];
    }

    /** @return list<string> */
    public static function berkas(): array
    {
        return [self::BERKAS_DELETE];
    }
}
