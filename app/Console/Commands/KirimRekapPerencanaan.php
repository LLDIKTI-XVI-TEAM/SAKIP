<?php

namespace App\Console\Commands;

use App\Services\Authorization\PermissionResolver;
use App\Services\Notifikasi\AntrekanNotifikasiEksternal;
use App\Services\Notifikasi\KonfigurasiNotifikasi;
use App\Services\Notifikasi\PenerimaNotifikasi;
use App\Services\Pengingat\SusunRekapPerencanaan;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Mengantrekan rekap progres pengisian bagi penerima Perencanaan pada H-3 dan H-1.
 *
 * Perintah ini tipis: jendela dan angka disusun {@see SusunRekapPerencanaan}, penerima ditentukan
 * {@see PenerimaNotifikasi::penerimaRekap()}, lalu producer outbox menentukan kanal, kontak, dan idempotensi.
 * Gerbang jam kirim, penanda harian, dan penanganan gagal antre mengikuti `notifikasi:ews`.
 */
final class KirimRekapPerencanaan extends Command
{
    protected $signature = 'notifikasi:rekap';

    protected $description = 'Antrekan rekap progres pengisian bagi Tim Perencanaan pada H-3 dan H-1.';

    public function handle(
        KonfigurasiNotifikasi $konfigurasi,
        SusunRekapPerencanaan $susun,
        PenerimaNotifikasi $penerima,
        PermissionResolver $izin,
        AntrekanNotifikasiEksternal $antrekan,
    ): int {
        // Satu instan bisnis dipakai untuk gerbang jam, pemilihan jendela, angka rekap, dan penanda harian.
        $saat = CarbonImmutable::now(config('app.business_timezone'));
        $tanggal = $saat->toDateString();
        $kunciGagal = 'notifikasi:rekap:gagal:'.$tanggal;
        $jamKirim = $konfigurasi->jamKirim();

        if ($jamKirim === null) {
            // Scheduler tiap menit: cukup satu catatan log per hari bisnis.
            if (Cache::add('notifikasi:rekap:tanpa_jam:'.$tanggal, true, $saat->endOfDay())) {
                Log::info('Rekap Perencanaan tidak diantrekan: jam kirim belum ditetapkan.');
            }
            $this->info('Jam kirim belum ditetapkan; tidak ada rekap yang diantrekan.');

            return self::SUCCESS;
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $jamKirim) !== 1) {
            if (Cache::add('notifikasi:rekap:jam_tidak_valid:'.$tanggal, true, $saat->endOfDay())) {
                Log::warning('Rekap Perencanaan tidak diantrekan: format jam kirim tidak valid.', ['jam_kirim' => $jamKirim]);
            }
            $this->warn('Format jam kirim tidak valid; tidak ada rekap yang diantrekan.');

            return self::SUCCESS;
        }

        if ($saat->format('H:i') < $jamKirim || Cache::has('notifikasi:rekap:diproses:'.$tanggal)) {
            return self::SUCCESS;
        }

        $template = trim((string) config('notifikasi.template_rekap', ''));
        if ($template === '') {
            if (Cache::add('notifikasi:rekap:tanpa_template:'.$tanggal, true, $saat->endOfDay())) {
                Log::warning('Rekap Perencanaan tidak diantrekan: template belum ditetapkan.');
            }
            $this->warn('Template rekap belum ditetapkan; tidak ada rekap yang diantrekan.');

            return self::SUCCESS;
        }

        // Kegagalan antrean hari ini: menit berikutnya berhenti tanpa evaluasi ulang sampai kunci gagal dihapus operator.
        if (Cache::has($kunciGagal)) {
            return self::FAILURE;
        }

        $jendela = $susun->periodeUntuk($saat, $konfigurasi->hMinusRekap());
        $akun = $jendela === [] ? [] : $penerima->penerimaRekap();
        $baris = 0;
        foreach ($jendela as ['periode' => $periode, 'h' => $h]) {
            foreach ($akun as $user) {
                try {
                    // Angka dihitung per penerima karena unit yang ditolak baca berbeda antarakun, sama dengan Dashboard.
                    $baris += $antrekan->antre(
                        kejadian: 'rekap:h'.$h,
                        tujuan: $periode,
                        periode: $periode->id.':'.$periode->pengisian_selesai->toDateString(),
                        penerima: [$user->id],
                        kanal: ['email', 'whatsapp'],
                        template: $template,
                        parameter: [
                            ...$susun->parameter($periode, $h, $izin->unitDitolak($user, 'dashboard:read'), $saat),
                            'cakupan_unit' => $penerima->cakupanRekap($user),
                        ],
                    )->count();
                } catch (InvalidArgumentException $galat) {
                    if (Cache::add($kunciGagal, true, $saat->endOfDay())) {
                        Log::error('Rekap Perencanaan gagal diantrekan.', ['pesan' => $galat->getMessage()]);
                    }
                    $this->error('Rekap Perencanaan gagal diantrekan.');

                    // Tanpa penanda diproses: mencoba ulang hanya bila kunci gagal dihapus operator.
                    return self::FAILURE;
                }
            }
        }

        // Penanda hanya setelah seluruh loop sukses; kunci idempoten outbox mencegah baris ganda bila diulang.
        Cache::put('notifikasi:rekap:diproses:'.$tanggal, true, $saat->endOfDay());
        Log::info('Rekap Perencanaan selesai dievaluasi.', ['tanggal' => $tanggal, 'jendela' => count($jendela), 'penerima' => count($akun), 'baris' => $baris]);
        $this->info('Rekap Perencanaan: '.count($jendela).' jendela, '.count($akun)." penerima, $baris baris outbox.");

        return self::SUCCESS;
    }
}
