<?php

namespace App\Console\Commands;

use App\Jobs\KirimNotifikasiEksternal;
use App\Models\NotifikasiPengiriman;
use App\Services\Notifikasi\AntreanNotifikasi;
use Illuminate\Bus\UniqueLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AntreUlangNotifikasi extends Command
{
    protected $signature = 'notifikasi:antre-ulang';

    protected $description = 'Pulihkan baris antre tanpa job; tidak mengulang hasil ambigu atau kegagalan.';

    public function handle(AntreanNotifikasi $antrean): int
    {
        $count = 0;
        NotifikasiPengiriman::where('status', 'antre')->where('created_at', '<=', now()->subMinutes(max(1, (int) config('notifikasi.pemulihan_menit', 10))))->select('id')->chunkById(100, function ($rows) use ($antrean, &$count) {
            foreach ($rows as $row) {
                DB::transaction(function () use ($row, $antrean, &$count) {
                    $p = NotifikasiPengiriman::lockForUpdate()->findOrFail($row->id);
                    if ($p->status !== 'antre' || $p->coba_lagi_pada?->isFuture() || $antrean->memilikiJob($p->id)) {
                        return;
                    }
                    // Upaya yang belum pasti sudah berstatus rekonsiliasi; antre berarti aman dicoba.
                    $job = new KirimNotifikasiEksternal($p->id);
                    (new UniqueLock($job->uniqueVia()))->release($job);
                    $antrean->dispatch($p->id);
                    $count++;
                });
            }
        });
        $this->info('Baris dipulihkan: '.$count);

        return self::SUCCESS;
    }
}
