<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @property string $id
 * @property string|null $jenis_berkas_id
 * @property string $berkasable_type
 * @property string $berkasable_id
 * @property string|null $menggantikan_id
 * @property string|null $alasan_koreksi
 * @property string $mode
 * @property string|null $nama_asli
 * @property string|null $path
 * @property string|null $mime
 * @property int|null $ukuran_bytes
 * @property string|null $tautan
 * @property string|null $isi_teks
 * @property string $uploaded_by
 * @property string|null $dihapus_oleh
 */
class Berkas extends Model
{
    use HasUuids, SoftDeletes;

    public const DELETED_AT = 'dihapus_pada';

    public const UPDATED_AT = null;

    protected $table = 'berkas';

    protected $fillable = [
        'jenis_berkas_id',
        'berkasable_type',
        'berkasable_id',
        'menggantikan_id',
        'alasan_koreksi',
        'mode',
        'nama_asli',
        'path',
        'mime',
        'ukuran_bytes',
        'tautan',
        'isi_teks',
        'uploaded_by',
        'dihapus_oleh',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'path',
    ];

    protected function casts(): array
    {
        return [
            'ukuran_bytes' => 'integer',
            'created_at' => 'datetime',
            'dihapus_pada' => 'datetime',
        ];
    }

    /**
     * Bukti kerja memilih ujung rantai koreksi; baris lama tetap tersedia bagi versi historis.
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('berkas.dihapus_pada')->whereNotExists(function (QueryBuilder $replacement): void {
            $replacement->selectRaw('1')->from('berkas as pengganti')
                ->whereColumn('pengganti.menggantikan_id', 'berkas.id')
                ->whereColumn('pengganti.berkasable_type', 'berkas.berkasable_type')
                ->whereColumn('pengganti.berkasable_id', 'berkas.berkasable_id')
                ->whereNull('pengganti.dihapus_pada');
        });
    }

    /** @return MorphTo<Model, $this> */
    public function berkasable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<JenisBerkas, $this> */
    public function jenisBerkas(): BelongsTo
    {
        return $this->belongsTo(JenisBerkas::class, 'jenis_berkas_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function penghapus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dihapus_oleh');
    }
}

