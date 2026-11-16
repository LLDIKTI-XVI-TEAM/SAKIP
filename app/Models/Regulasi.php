<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $jenis
 * @property string $nomor
 * @property int $tahun
 * @property string $tentang
 * @property Carbon|null $tanggal
 * @property string|null $tautan_sumber
 * @property string|null $catatan
 * @property bool $aktif
 * @property int $versi
 * @property string $created_by
 * @property-read User|null $pembuat
 * @property-read Collection<int, Berkas> $berkas
 */
class Regulasi extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'regulasi';

    protected $attributes = [
        'versi' => 1,
    ];

    protected $fillable = [
        'jenis',
        'nomor',
        'tahun',
        'tentang',
        'tanggal',
        'tautan_sumber',
        'catatan',
        'aktif',
        'versi',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'tanggal' => 'date',
            'aktif' => 'boolean',
            'versi' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphMany<Berkas, $this> */
    public function berkas(): MorphMany
    {
        return $this->morphMany(Berkas::class, 'berkasable');
    }

    /** @return HasMany<Renstra, $this> */
    public function renstras(): HasMany
    {
        return $this->hasMany(Renstra::class, 'regulasi_id');
    }

    /** @return HasMany<IndikatorKinerja, $this> */
    public function indikatorKinerjas(): HasMany
    {
        return $this->hasMany(IndikatorKinerja::class, 'regulasi_id');
    }

    /**
     * Penghapus induk maupun lampiran memakai guard yang sama setelah lock induk diperoleh.
     *
     * @return array{jumlah_renstra_aktif: int, jumlah_indikator_aktif: int}
     */
    public function lockReferenceCounts(): array
    {
        // Mengunci semua rujukan, bukan hanya yang aktif, agar status/rujukan tidak berubah
        // setelah guard dievaluasi. Lock regulasi induk menahan insert rujukan baru via FK.
        // Indikator sebelum Renstra mengikuti pembaca/penulis target (Indikator → Sasaran → Renstra).
        // Regulasi induk tetap dikunci lebih dahulu, seperti writer rujukan; tiap kelompok terurut.
        $indikatorKinerjas = $this->indikatorKinerjas()
            ->select(['id', 'status'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $renstras = $this->renstras()
            ->select(['id', 'is_aktif'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return [
            'jumlah_renstra_aktif' => $renstras->where('is_aktif', true)->count(),
            'jumlah_indikator_aktif' => $indikatorKinerjas->where('status', 'aktif')->count(),
        ];
    }
}
