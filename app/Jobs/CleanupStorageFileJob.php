<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class CleanupStorageFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah percobaan maksimal.
     */
    public int $tries = 3;

    /**
     * Waktu tunggu sebelum coba lagi dalam detik.
     */
    public int $backoff = 60;

    public function __construct(
        public string $path,
        public string $disk = 'local',
    ) {}

    public function handle(): void
    {
        $storage = Storage::disk($this->disk);

        if (! $storage->exists($this->path)) {
            return;
        }

        try {
            $deleted = $storage->delete($this->path);
            if (! $deleted && $storage->exists($this->path)) {
                throw new RuntimeException("Gagal menghapus file dari disk {$this->disk}: {$this->path}");
            }
        } catch (Throwable $e) {
            Log::warning("Gagal dalam job cleanup storage file ({$this->path}): ".$e->getMessage());
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Job cleanup storage file ({$this->path}) gagal setelah {$this->tries} kali percobaan: ".$exception->getMessage());
    }
}
