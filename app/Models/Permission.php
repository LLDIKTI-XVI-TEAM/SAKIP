<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property string|null $entitas
 * @property string|null $aksi
 * @property string $butuh_scope
 * @property bool $sensitif
 * @property bool $aktif
 * @property string|null $keterangan
 */
class Permission extends SpatiePermission {}
