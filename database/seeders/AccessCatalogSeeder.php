<?php

namespace Database\Seeders;

use App\Actions\Access\SyncRolePermissionPresets;
use Illuminate\Database\Seeder;

class AccessCatalogSeeder extends Seeder
{
    private const RELEASE = 'q32-2026-09-24';

    public function run(): void
    {
        $sync = app(SyncRolePermissionPresets::class);
        $this->command?->line(json_encode($sync->preview(), JSON_THROW_ON_ERROR));
        $events = $sync->handle(self::RELEASE, 'Penyelarasan lima role dan preset izin final.', 'cli:'.(gethostname() ?: 'unknown').':'.getmypid());
        $this->command?->info('Sinkronisasi akses selesai; '.$events.' perubahan teraudit.');
    }
}
