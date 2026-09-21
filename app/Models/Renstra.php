<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Renstra extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'renstras';

    protected $fillable = [
        'kode',
        'nama',
        'tahun_mulai',
        'tahun_selesai',
        'deskripsi',
        'dasar_hukum',
        'is_aktif',
    ];

    protected $casts = [
        'tahun_mulai' => 'integer',
        'tahun_selesai' => 'integer',
        'is_aktif' => 'boolean',
    ];

    public function sasaranStrategis(): HasMany
    {
        return $this->hasMany(SasaranStrategis::class, 'renstra_id')->orderBy('urutan');
    }
}
