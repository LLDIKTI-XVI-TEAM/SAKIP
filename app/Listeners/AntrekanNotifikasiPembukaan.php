<?php

namespace App\Listeners;

use App\Events\JendelaPengisianDibuka;
use App\Models\PeriodeJadwal;
use App\Services\Notifikasi\AntrekanPembukaanJendela;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Jalur aksi resmi broadcast pembukaan: jendela yang dibuka oleh aktivasi jadwal atau revisi jendela PIC langsung
 * diantrekan ke outbox eksternal setelah commit, tanpa menunggu jam kirim (jam kirim hanya mengatur jalur terjadwal).
 * Ditemukan lewat event discovery (method `handle*`); item in-app dari event yang sama ditulis listener lain.
 */
class AntrekanNotifikasiPembukaan
{
    public function __construct(private readonly AntrekanPembukaanJendela $pembukaan) {}

    public function handleJendelaPengisianDibuka(JendelaPengisianDibuka $event): void
    {
        foreach ($event->periodeJadwalIds as $periodeJadwalId) {
            try {
                $this->pembukaan->antre(PeriodeJadwal::findOrFail($periodeJadwalId), $event->indikatorIds, $event->tanggalBisnis);
            } catch (Throwable $e) {
                $this->catat($periodeJadwalId, $e);
            }
        }
    }

    /**
     * Listener berjalan setelah commit tetapi masih di dalam pemanggilan `DB::transaction()` Action, jadi tidak ada
     * Throwable yang boleh lolos, termasuk dari logger; cadangannya `error_log`. Log tanpa alamat atau isi pesan.
     */
    private function catat(string $periodeJadwalId, Throwable $galat): void
    {
        try {
            Log::error('Notifikasi eksternal gagal diantrekan.', ['jenis' => 'jadwal.jendela_dibuka', 'objek_id' => $periodeJadwalId, 'galat' => $galat::class]);
        } catch (Throwable) {
            error_log(sprintf('Notifikasi eksternal gagal dicatat: jenis=jadwal.jendela_dibuka objek_id=%s galat=%s', $periodeJadwalId, $galat::class));
        }
    }
}
