<?php

namespace App\Console\Commands;

use App\Models\PeriodeJadwal;
use App\Services\Notifikasi\AntrekanNotifikasiEksternal;
use App\Services\Notifikasi\KonfigurasiNotifikasi;
use App\Services\Pengingat\SusunPengingatTenggat;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Mengantrekan pengingat tenggat pengisian PIC pada H-7/H-3/H-1.
 *
 * Perintah ini tipis: jam kirim dan daftar H dibaca dari konfigurasi notifikasi,
 * kandidat disusun oleh evaluator, lalu producer outbox menentukan kanal, kontak,
 * dan idempotensi. Scheduler berjalan tiap menit, sehingga gerbang jam kirim dan
 * penanda harian mencegah dispatch berulang pada satu hari bisnis. Kegagalan
 * antrean di-log sekali per hari bisnis dan menit-menit berikutnya berhenti tanpa
 * evaluasi ulang melalui kunci `gagal`, tanpa menulis penanda `diproses`.
 */
final class KirimPengingatEws extends Command
{
    protected $signature = 'notifikasi:ews';

    protected $description = 'Antrekan pengingat tenggat pengisian PIC pada H-7, H-3, dan H-1.';

    public function __construct(
        private KonfigurasiNotifikasi $konfigurasi,
        private SusunPengingatTenggat $susun,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Satu instan bisnis dipakai untuk time-gate, evaluator, dan TTL penanda harian.
        $saat = CarbonImmutable::now(config('app.business_timezone'));
        $tanggal = $saat->toDateString();
        $kunciGagal = 'notifikasi:ews:gagal:'.$tanggal;
        $jamKirim = $this->konfigurasi->jamKirim();

        if ($jamKirim === null) {
            // Scheduler tiap menit: cukup satu catatan log per hari bisnis.
            if (Cache::add('notifikasi:ews:tanpa_jam:'.$tanggal, true, $saat->endOfDay())) {
                Log::info('Notifikasi EWS tidak diantrekan: jam kirim belum ditetapkan.');
            }
            $this->info('Jam kirim belum ditetapkan; tidak ada pengingat yang diantrekan.');

            return self::SUCCESS;
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $jamKirim) !== 1) {
            // Nilai rusak diperlakukan fail-safe: tidak ada dispatch dan tidak ada spam log per menit.
            if (Cache::add('notifikasi:ews:jam_tidak_valid:'.$tanggal, true, $saat->endOfDay())) {
                Log::warning('Notifikasi EWS tidak diantrekan: format jam kirim tidak valid.', ['jam_kirim' => $jamKirim]);
            }
            $this->warn('Format jam kirim tidak valid; tidak ada pengingat yang diantrekan.');

            return self::SUCCESS;
        }

        if ($saat->format('H:i') < $jamKirim) {
            return self::SUCCESS;
        }

        if (Cache::has('notifikasi:ews:diproses:'.$tanggal)) {
            return self::SUCCESS;
        }

        $template = trim((string) config('notifikasi.template_ews', ''));
        if ($template === '') {
            Log::warning('Notifikasi EWS tidak diantrekan: template belum ditetapkan.');
            $this->warn('Template EWS belum ditetapkan; tidak ada pengingat yang diantrekan.');

            return self::SUCCESS;
        }

        // Kegagalan antrean hari ini: menit-menit berikutnya berhenti tanpa evaluasi
        // ulang; penanda diproses tetap tidak ditulis sehingga perbaikan hari yang
        // sama masih dapat dicoba bila kunci gagal dihapus operator.
        if (Cache::has($kunciGagal)) {
            return self::FAILURE;
        }

        $hasil = $this->susun->untukTanggal($saat, $this->konfigurasi->hMinusPic());

        if ($hasil['pesan'] === []) {
            $this->tandaiDiproses($tanggal, $saat);
            Log::info('Notifikasi EWS selesai dievaluasi tanpa kandidat.', [
                'tanggal' => $hasil['tanggal'],
                'diperiksa' => $hasil['diperiksa'],
            ]);
            $this->info("Pengingat EWS: {$hasil['diperiksa']} jadwal diperiksa, tidak ada kandidat.");

            return self::SUCCESS;
        }

        $diantrekan = 0;
        $antrekan = app(AntrekanNotifikasiEksternal::class);
        foreach ($hasil['pesan'] as $pesan) {
            $periode = PeriodeJadwal::find($pesan['periode_jadwal_id']);
            if ($periode === null) {
                Log::warning('Notifikasi EWS melewati periode yang tidak ditemukan.', [
                    'periode_jadwal_id' => $pesan['periode_jadwal_id'],
                ]);

                continue;
            }

            try {
                // Kanal aktif/kontak/idempotensi ditentukan producer; perintah tidak memilih kanal sendiri.
                $antrekan->antre(
                    kejadian: $pesan['kejadian'],
                    tujuan: $periode,
                    periode: $pesan['konteks_periode'],
                    penerima: [$pesan['user_id']],
                    kanal: ['email', 'whatsapp'],
                    template: $template,
                    parameter: $pesan['parameter'],
                );
            } catch (InvalidArgumentException $exception) {
                // Scheduler tiap menit: log dan evaluasi ulang dibatasi sekali per hari bisnis.
                if (Cache::add($kunciGagal, true, $saat->endOfDay())) {
                    Log::error('Notifikasi EWS gagal diantrekan.', ['pesan' => $exception->getMessage()]);
                }
                $this->error('Pengingat EWS gagal diantrekan.');

                // Tanpa penanda diproses: mencoba ulang hanya bila kunci gagal dihapus operator.
                return self::FAILURE;
            }

            $diantrekan++;
        }

        // Penanda hanya setelah seluruh loop sukses; kegagalan di atas membiarkan retry.
        $this->tandaiDiproses($tanggal, $saat);

        Log::info('Notifikasi EWS selesai diantrekan.', [
            'tanggal' => $hasil['tanggal'],
            'diperiksa' => $hasil['diperiksa'],
            'pesan' => count($hasil['pesan']),
            'diantrekan' => $diantrekan,
        ]);
        $this->info("Pengingat EWS: {$hasil['diperiksa']} jadwal diperiksa, ".count($hasil['pesan'])." pesan, $diantrekan diantrekan.");

        return self::SUCCESS;
    }

    private function tandaiDiproses(string $tanggal, CarbonImmutable $saat): void
    {
        Cache::put('notifikasi:ews:diproses:'.$tanggal, true, $saat->endOfDay());
    }
}
