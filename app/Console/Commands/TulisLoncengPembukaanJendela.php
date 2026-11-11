<?php

namespace App\Console\Commands;

use App\Listeners\KirimNotifikasiInApp;
use App\Services\Notifikasi\AntrekanPembukaanJendela;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Jalur tanggal item lonceng pembukaan: menulis item "jendela pengisian dibuka" ke PJ efektif untuk jendela yang
 * `pengisian_mulai`-nya hari ini. Penulisnya sama dengan jalur aksi, sehingga penerima dan teksnya sama dan item tidak
 * pernah ganda per jendela, tanggal bisnis, dan penerima walau aksi dan perintah membuka jendela yang sama.
 *
 * Tidak bergantung jam kirim maupun saklar kanal, dan tidak mengantrekan WhatsApp/Email. Event pembukaan sengaja tidak
 * dipancarkan karena listener kanal eksternal ikut mendengarnya dan akan mengantre. Scheduler berjalan tiap menit;
 * perintah aktif mulai 00.05 WITA lalu memasang penanda harian. Galat per jendela atau per penerima dicatat penulis
 * item dan tidak diulang; pembukaan pada hari ketika perintah tidak berjalan tidak disusulkan.
 */
final class TulisLoncengPembukaanJendela extends Command
{
    protected $signature = 'notifikasi:lonceng-pembukaan';

    protected $description = 'Tulis item lonceng untuk jendela pengisian yang mulai hari ini.';

    public function handle(AntrekanPembukaanJendela $pembukaan, KirimNotifikasiInApp $lonceng): int
    {
        // Satu instan bisnis dipakai untuk gerbang jam, pemilihan jendela, dan masa berlaku penanda harian.
        $saat = CarbonImmutable::now(config('app.business_timezone'));
        $tanggal = $saat->toDateString();
        $penanda = 'notifikasi:lonceng_pembukaan:diproses:'.$tanggal;

        if ($saat->format('H:i') < '00:05' || Cache::has($penanda)) {
            return self::SUCCESS;
        }

        $jendela = $pembukaan->dibukaPada($saat);
        foreach ($jendela as $periode) {
            // Aturan lingkup sama dengan jalur WhatsApp/Email: di dalam sesi koreksi hanya indikator lingkup yang terbuka bagi PIC.
            $lingkup = $periode->jadwal->tahunDitutup($saat) ? $periode->jadwal->lingkup_koreksi['indikator_ids'] : null;
            $lonceng->tulisJendelaDibuka($periode->id, $lingkup, $tanggal);
        }

        Cache::put($penanda, true, $saat->endOfDay());
        Log::info('Lonceng pembukaan selesai ditulis.', ['tanggal' => $tanggal, 'jendela' => $jendela->count()]);
        $this->info("Lonceng pembukaan: {$jendela->count()} jendela mulai hari ini.");

        return self::SUCCESS;
    }
}
