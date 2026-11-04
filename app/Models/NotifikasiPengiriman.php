<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/** Outbox internal; serialisasi publik hanya melalui pembaca status.
 * @property CarbonImmutable|null $coba_lagi_pada
 * @property CarbonImmutable|null $dicoba_terakhir_pada
 */
class NotifikasiPengiriman extends Model
{
    protected $table = 'notifikasi_pengiriman';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'kunci_idempoten', 'kejadian', 'objek_tipe', 'objek_id', 'konteks_periode', 'kanal', 'penerima_user_id', 'alamat', 'template', 'parameter', 'driver', 'simulasi', 'status', 'kode_hasil', 'percobaan', 'percobaan_siklus', 'dicoba_terakhir_pada', 'coba_lagi_pada', 'rujukan_provider', 'created_at', 'updated_at'];

    protected $hidden = ['alamat', 'template', 'parameter'];

    protected function casts(): array
    {
        return ['parameter' => 'array', 'simulasi' => 'boolean', 'percobaan' => 'integer', 'percobaan_siklus' => 'integer', 'dicoba_terakhir_pada' => 'immutable_datetime', 'coba_lagi_pada' => 'immutable_datetime'];
    }
}
