<?php

namespace App\Jobs;

use App\Models\NotifikasiPengiriman;
use App\Services\Notifikasi\PengirimNotifikasiEksternal;
use Illuminate\Bus\Queueable;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class KirimNotifikasiEksternal implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Reservasi yang hanya menunggu tidak memakai jatah kirim atau exception.
    public int $tries = 0;

    public int $maxExceptions = 4;

    public int $timeout = 60;

    public int $uniqueFor = 7200;

    public array $backoff = [60, 300, 1800];

    public function __construct(public string $pengirimanId)
    {
        $this->onConnection('notifikasi')->onQueue('notifikasi')->beforeCommit();
    }

    public function uniqueId(): string
    {
        return $this->pengirimanId;
    }

    /** Lock ikut rollback transaksi aplikasi, tanpa override koneksi cache deployment. */
    public function uniqueVia(): Repository
    {
        return new Repository(new DatabaseStore(DB::connection(), 'cache', 'notifikasi:', 'cache_locks'));
    }

    public function displayName(): string
    {
        return self::class.':'.$this->pengirimanId;
    }

    public function handle(PengirimNotifikasiEksternal $pengirim): void
    {
        try {
            $jeda = $pengirim->kirim($this->pengirimanId, 4);
        } catch (Throwable) {
            throw new RuntimeException('notifikasi.gangguan_penyimpanan');
        }
        // Redelivery setelah crash sebelum fail() tetap mencatat failed_jobs.
        if (NotifikasiPengiriman::whereKey($this->pengirimanId)->where('status', 'gagal')->where('kode_hasil', 'percobaan_habis')->exists()) {
            $this->fail(new RuntimeException('notifikasi.percobaan_habis'));
        } elseif ($jeda !== null) {
            $this->release($jeda);
        }
    }

    /** Callback terlambat tidak mengubah accepted, pembatalan, atau hasil ambigu. */
    public function failed(?Throwable $exception): void
    {
        NotifikasiPengiriman::whereKey($this->pengirimanId)->where('status', 'antre')
            ->update(['status' => 'gagal', 'kode_hasil' => 'percobaan_habis']);
    }
}
