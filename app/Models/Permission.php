<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasUuids;

    protected $fillable = ['kode', 'entitas', 'aksi', 'keterangan', 'butuh_scope', 'sensitif', 'aktif'];

    protected function casts(): array
    {
        return ['sensitif' => 'boolean', 'aktif' => 'boolean'];
    }
}
