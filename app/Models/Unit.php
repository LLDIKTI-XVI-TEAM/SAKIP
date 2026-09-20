<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasUuids;

    protected $table = 'unit';

    public const UPDATED_AT = null;

    protected $fillable = ['nama', 'status', 'created_by'];
}
