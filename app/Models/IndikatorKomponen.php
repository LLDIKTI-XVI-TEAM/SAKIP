<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndikatorKomponen extends Model
{
    use HasUuids;

    protected $table = 'indikator_komponen';

    public $timestamps = true;

    protected $fillable = [
        'indikator_id',
        'kode',
        'label',
        'peran',
        'bobot',
        'urutan',
        'satuan',
        'aktif',
        'created_by',
    ];

    protected $casts = [
        'bobot' => 'decimal:12',
        'urutan' => 'integer',
        'aktif' => 'boolean',
    ];

    /** @return BelongsTo<IndikatorKinerja, $this> */
    public function indikator(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerja::class, 'indikator_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
