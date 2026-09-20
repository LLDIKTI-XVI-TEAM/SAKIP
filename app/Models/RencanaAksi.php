<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $indikator_kinerja_id
 * @property int $unit_kerja_id
 * @property int $tahun
 * @property int|null $penanggung_jawab_id
 * @property string $nama_rencana_aksi
 * @property string|null $uraian
 * @property string|null $target_triwulan_1
 * @property string|null $target_triwulan_2
 * @property string|null $target_triwulan_3
 * @property string|null $target_triwulan_4
 * @property string $status_alur
 * @property string|null $alasan_revisi
 * @property Carbon|null $disahkan_at
 * @property int|null $disahkan_by
 * @property int|null $created_by
 * @property-read IndikatorKinerja $indikatorKinerja
 * @property-read UnitKerja $unitKerja
 * @property-read User|null $penanggungJawab
 * @property-read User|null $disahkanBy
 * @property-read User|null $creator
 */
class RencanaAksi extends Model
{
    use HasFactory;

    protected $table = 'rencana_aksis';

    protected $fillable = [
        'indikator_kinerja_id',
        'unit_kerja_id',
        'tahun',
        'penanggung_jawab_id',
        'nama_rencana_aksi',
        'uraian',
        'target_triwulan_1',
        'target_triwulan_2',
        'target_triwulan_3',
        'target_triwulan_4',
        'status_alur',
        'alasan_revisi',
        'disahkan_at',
        'disahkan_by',
        'created_by',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'disahkan_at' => 'datetime',
    ];

    /** @return BelongsTo<IndikatorKinerja, $this> */
    public function indikatorKinerja(): BelongsTo
    {
        return $this->belongsTo(IndikatorKinerja::class, 'indikator_kinerja_id');
    }

    /** @return BelongsTo<UnitKerja, $this> */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    /** @return BelongsTo<User, $this> */
    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penanggung_jawab_id');
    }

    /** @return BelongsTo<User, $this> */
    public function disahkanBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disahkan_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDisahkan(): bool
    {
        return $this->status_alur === 'disahkan';
    }
}
