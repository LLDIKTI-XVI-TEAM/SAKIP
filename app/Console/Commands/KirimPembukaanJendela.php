<?php

namespace App\Console\Commands;

use App\Services\Notifikasi\AntrekanPembukaanJendela;
use App\Services\Notifikasi\KonfigurasiNotifikasi;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Jalur tanggal broadcast pembukaan: mengantrekan pemberitahuan untuk jendela pengisian yang `pengisian_mulai`-nya
 * hari ini. Pembukaan oleh aksi resmi ditangani listener dan memakai producer yang sama, sehingga kunci idempoten
 * outbox mencegah pesan ganda pada hari yang sama.
 *
 * Scheduler berjalan tiap menit; gerbang jam kirim dan penanda harian mengikuti `notifikasi:ews`. Pembukaan pada
 * hari ketika perintah tidak berjalan tidak disusulkan.
 */
final class KirimPembukaanJendela extends Command
{
    protected $signature = 'notifikasi:pembukaan';

    protected $description = 'Antrekan pemberitahuan pembukaan jendela pengisian yang mulai hari ini.';

    public function handle(KonfigurasiNotifikasi $konfigurasi, AntrekanPembukaanJendela $pembukaan): int
    {
        // Satu instan bisnis dipakai untuk gerbang jam, pemilihan jendela, dan masa berlaku penanda harian.
        $saat = CarbonImmutable::now(config('app.business_timezone'));
        $tanggal = $saat->toDateString();
        $jamKirim = $konfigurasi->jamKirim();

        if ($jamKirim === null) {
            if (Cache::add('notifikasi:pembukaan:tanpa_jam:'.$tanggal, true, $saat->endOfDay())) {
                Log::info('Notifikasi pembukaan tidak diantrekan: jam kirim belum ditetapkan.');
            }
            $this->info('Jam kirim belum ditetapkan; tidak ada pemberitahuan pembukaan yang diantrekan.');

            return self::SUCCESS;
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $jamKirim) !== 1) {
            if (Cache::add('notifikasi:pembukaan:jam_tidak_valid:'.$tanggal, true, $saat->endOfDay())) {
                Log::warning('Notifikasi pembukaan tidak diantrekan: format jam kirim tidak valid.', ['jam_kirim' => $jamKirim]);
            }
            $this->warn('Format jam kirim tidak valid; tidak ada pemberitahuan pembukaan yang diantrekan.');

            return self::SUCCESS;
        }

        if ($saat->format('H:i') < $jamKirim || Cache::has('notifikasi:pembukaan:diproses:'.$tanggal)) {
            return self::SUCCESS;
        }

        if (trim((string) config('notifikasi.template_pembukaan', '')) === '') {
            Log::warning('Notifikasi pembukaan tidak diantrekan: template belum ditetapkan.');
            $this->warn('Template pembukaan belum ditetapkan; tidak ada pemberitahuan pembukaan yang diantrekan.');

            return self::SUCCESS;
        }

        $jendela = $pembukaan->dibukaPada($saat);
        $baris = 0;
        $gagal = false;
        foreach ($jendela as $periode) {
            try {
                // Di dalam sesi koreksi hanya indikator lingkup yang terbuka bagi PIC, sama dengan event revisi jendela.
                $baris += $pembukaan->antre($periode, $periode->jadwal->tahunDitutup($saat) ? $periode->jadwal->lingkup_koreksi['indikator_ids'] : null, $tanggal)->count();
            } catch (Throwable $galat) {
                // Jendela lain tetap diproses agar satu galat tidak menahan seluruh pembukaan hari itu.
                Log::error('Notifikasi pembukaan gagal diantrekan.', ['periode_jadwal_id' => $periode->id, 'galat' => $galat::class]);
                $gagal = true;
            }
        }

        if ($gagal) {
            $this->error('Sebagian pemberitahuan pembukaan gagal diantrekan; akan dicoba lagi.');

            // Tanpa penanda: menit berikutnya mencoba lagi, dan kunci idempoten mencegah baris ganda.
            return self::FAILURE;
        }

        Cache::put('notifikasi:pembukaan:diproses:'.$tanggal, true, $saat->endOfDay());
        Log::info('Notifikasi pembukaan selesai diantrekan.', ['tanggal' => $tanggal, 'jendela' => $jendela->count(), 'baris' => $baris]);
        $this->info("Pembukaan jendela: {$jendela->count()} jendela mulai hari ini, $baris baris outbox.");

        return self::SUCCESS;
    }
}
