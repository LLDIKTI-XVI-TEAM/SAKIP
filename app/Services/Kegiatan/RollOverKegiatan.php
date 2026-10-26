<?php

namespace App\Services\Kegiatan;

use App\Actions\Kegiatan\TambahKegiatanRencanaAksi;
use App\Models\Kegiatan;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Membentuk kegiatan lanjutan dari kegiatan yang ditunda (Q41 §41.4 butir 2, Q69 §69.5 butir 1).
 *
 * Dipisahkan sebagai satu titik pembentukan kegiatan lanjutan yang kelak dilengkapi klaim tahap
 * pengukuran kegiatan lanjutan (Q41 §41.4 butir 2, Q69 §69.5). Caller aktual: `UbahStatusKegiatan`.
 *
 * Dipanggil di dalam transaksi Action yang sudah mengunci kegiatan asal dan memvalidasi periode tujuan;
 * Service ini tidak membuka transaksi, tidak mengunci, tidak memeriksa izin, dan tidak menulis audit
 * (audit milik Action). Isi lanjutan memakai `TambahKegiatanRencanaAksi::FIELD_SALIN` ditambah unit dan
 * tahun; tanggal rencana, justifikasi, data pelaksanaan, klaim, dan berkas tidak disalin.
 */
class RollOverKegiatan
{
    public function buat(Kegiatan $asal, Periode $tujuan, User $aktor): Kegiatan
    {
        return Kegiatan::create(Arr::only($asal->getAttributes(), [...TambahKegiatanRencanaAksi::FIELD_SALIN, 'unit_id', 'tahun']) + [
            'periode_id' => $tujuan->id, 'status' => 'rencana', 'kegiatan_asal_id' => $asal->id, 'created_by' => $aktor->id,
        ]);
    }
}
