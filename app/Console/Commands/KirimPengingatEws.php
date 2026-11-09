<?php

namespace App\Console\Commands;

use App\Models\PeriodeJadwal;
use App\Services\Notifikasi\AntrekanNotifikasiEksternal;
use App\Services\Notifikasi\KonfigurasiNotifikasi;
use App\Services\Pengingat\SusunPengingatTenggat;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Mengantrekan pengingat tenggat pengisian PIC pada H-7/H-3/H-1.
 *
 * Perintah ini tipis: jam kirim dan daftar H dibaca dari konfigurasi notifikasi,
 * kandidat disusun oleh evaluator, lalu producer outbox menentukan kanal, kontak,
 * dan idempotensi. Pendaftaran scheduler mengikuti pola registrasi bersama agar
 * tidak ada dua pola cron yang berbeda.
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
        if ($this->konfigurasi->jamKirim() === null) {
            Log::info('Notifikasi EWS tidak diantrekan: jam kirim belum ditetapkan.');
            $this->info('Jam kirim belum ditetapkan; tidak ada pengingat yang diantrekan.');

            return self::SUCCESS;
        }

        $template = trim((string) config('notifikasi.template_ews', ''));
        if ($template === '') {
            Log::warning('Notifikasi EWS tidak diantrekan: template belum ditetapkan.');
            $this->warn('Template EWS belum ditetapkan; tidak ada pengingat yang diantrekan.');

            return self::SUCCESS;
        }

        $hasil = $this->susun->untukTanggal(
            CarbonImmutable::now(config('app.business_timezone')),
            $this->konfigurasi->hMinusPic(),
        );

        if ($hasil['pesan'] === []) {
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
                Log::error('Notifikasi EWS belum dapat diantrekan: fondasi tujuan jadwal_periode belum tersedia.', [
                    'pesan' => $exception->getMessage(),
                ]);
                $this->error('Fondasi outbox jadwal_periode belum tersedia; tidak ada pengingat yang diantrekan.');

                return self::FAILURE;
            }

            $diantrekan++;
        }

        Log::info('Notifikasi EWS selesai diantrekan.', [
            'tanggal' => $hasil['tanggal'],
            'diperiksa' => $hasil['diperiksa'],
            'pesan' => count($hasil['pesan']),
            'diantrekan' => $diantrekan,
        ]);
        $this->info("Pengingat EWS: {$hasil['diperiksa']} jadwal diperiksa, ".count($hasil['pesan'])." pesan, $diantrekan diantrekan.");

        return self::SUCCESS;
    }
}
