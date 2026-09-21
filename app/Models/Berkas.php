<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $jenis_berkas_id
 * @property string $berkasable_type
 * @property string $berkasable_id
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

    protected function casts(): array
    {
        return [
            'ukuran_bytes' => 'integer',
            'created_at' => 'datetime',
            'dihapus_pada' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function berkasable(): MorphTo
    {
        return $this->morphTo();
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
