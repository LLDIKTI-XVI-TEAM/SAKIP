<?php

namespace App\Services\Perencanaan;

use App\Models\IndikatorKinerja;
use Illuminate\Validation\ValidationException;

/**
 * Guard service-layer: pembuatan pengukuran/rencana aksi baru ditolak
 * untuk indikator berstatus arsip.
 *
 * Berlaku untuk seluruh peran tanpa pengecualian (termasuk Perencanaan
 * dan Superadmin): arsip bersifat final dan never-delete mutlak
 * (ADR 0003). Pemanggil wajib meneruskan baris indikator yang baru
 * dikunci/dimuat ulang agar keputusan memakai state terkini, bukan
 * snapshot basi. Endpoint create pengukuran/rencana aksi yang lahir
 * kemudian wajib memanggil guard ini sebelum mutasi.
 */
class IndikatorArsipGuard
{
    /**
     * Memastikan indikator boleh dibuatkan data operasional baru.
     *
     * @param  'pengukuran'|'rencana_aksi'  $jenis  Jenis data operasional yang hendak dibuat.
     *
     * @throws ValidationException 422 bila indikator berstatus arsip.
     */
    public function pastikanDapatDibuatkan(IndikatorKinerja $indikator, string $jenis): void
    {
        if (! $indikator->isArsip()) {
            return;
        }

        $label = $jenis === 'rencana_aksi' ? 'rencana aksi' : 'pengukuran';

        throw ValidationException::withMessages([
            'indikator_id' => "Indikator '{$indikator->kode}' telah diarsipkan sehingga tidak dapat dibuatkan {$label} baru.",
        ]);
    }
}
