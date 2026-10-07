<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PenugasanIndikator extends Model
{
    use HasUuids;

    protected $table = 'penanggung_jawab';

    public $timestamps = false;

    protected $fillable = ['indikator_id', 'user_id', 'tanggal_mulai_berlaku', 'ditetapkan_oleh', 'alasan', 'created_at'];

    protected $casts = ['tanggal_mulai_berlaku' => 'date', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Histori penanggung jawab tidak boleh diubah.');
        });
        static::deleting(function (): void {
            throw new LogicException('Histori penanggung jawab tidak boleh dihapus.');
        });
    }

    /**
     * Pilih pemenang seluruh histori dahulu; filter user pada query luar tidak
     * boleh membuat penugasan lama menjadi PJ efektif kembali.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        $winners = static::query()->select('penanggung_jawab.*')
            ->where('tanggal_mulai_berlaku', '<=', $date)
            ->distinct('indikator_id')->orderBy('indikator_id')->orderByDesc('tanggal_mulai_berlaku');

        return $query->fromSub($winners, 'penanggung_jawab');
    }

    /** @return BelongsTo<User, $this> */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function establishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh');
    }

    /** @return BelongsTo<IndikatorKinerja, $this> */
    public function indikatorKinerja(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerja::class, 'indikator_id');
    }
}
