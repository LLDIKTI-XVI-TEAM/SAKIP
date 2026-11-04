<?php

namespace App\Services\Notifikasi;

use App\Jobs\KirimNotifikasiEksternal;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use LogicException;

class AntreanNotifikasi
{
    /** Enqueue harus selesai sebelum transaksi outbox berakhir. */
    public function dispatch(string $id): void
    {
        $queue = Queue::connection('notifikasi');
        if (! $queue instanceof DatabaseQueue || $queue->getDatabase() !== DB::connection() || DB::transactionLevel() === 0) {
            throw new LogicException('Antrean notifikasi memerlukan transaksi koneksi aplikasi yang sama.');
        }
        $pending = KirimNotifikasiEksternal::dispatch($id);
        unset($pending);
    }

    /** Delayed, reserved, dan failed tetap melindungi baris dari recovery ganda. */
    public function memilikiJob(string $id): bool
    {
        foreach (['jobs', 'failed_jobs'] as $table) {
            if (DB::table($table)->where('queue', 'notifikasi')->whereRaw("payload::jsonb->>'notifikasi_pengiriman_id' = ?", [$id])->exists()) {
                return true;
            }
        }

        return false;
    }
}
