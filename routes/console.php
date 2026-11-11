<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler EWS berjalan tiap menit; gerbang jam kirim dilakukan oleh command agar
// hanya satu dispatch per hari bisnis. Lock bersama/onOneServer menangani konkurensi.
Schedule::command('notifikasi:ews')
    ->everyMinute()
    ->timezone(config('app.business_timezone'))
    ->withoutOverlapping()
    ->onOneServer();

// Rekap Perencanaan H-3/H-1 memakai pola yang sama dengan EWS: tiap menit, gerbang jam kirim di dalam command.
Schedule::command('notifikasi:rekap')
    ->everyMinute()
    ->timezone(config('app.business_timezone'))
    ->withoutOverlapping()
    ->onOneServer();

// Pembukaan jendela karena tanggal memakai pola yang sama: tiap menit, gerbang jam kirim di dalam command.
// Kunci pendek karena pembukaan yang terlewat tidak disusulkan; run ganda aman berkat kunci idempoten outbox.
Schedule::command('notifikasi:pembukaan')
    ->everyMinute()
    ->timezone(config('app.business_timezone'))
    ->withoutOverlapping(10)
    ->onOneServer();

// Item lonceng pembukaan karena tanggal: gerbang 00.05 WITA dan penanda harian ada di dalam command.
Schedule::command('notifikasi:lonceng-pembukaan')
    ->everyMinute()
    ->timezone(config('app.business_timezone'))
    ->withoutOverlapping(10)
    ->onOneServer();
