<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenugasanIndikator extends Model
{
    use HasUuids;

    protected $table = 'penanggung_jawab';

    public $timestamps = false;

    protected $fillable = ['indikator_id', 'user_id', 'tanggal_mulai_berlaku', 'ditetapkan_oleh', 'alasan', 'created_at'];

    protected $casts = ['tanggal_mulai_berlaku' => 'date'];

    /** @return BelongsTo<User, $this> */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<IndikatorKinerja, $this> */
    public function indikatorKinerja(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerja::class, 'indikator_id');
    }
}
