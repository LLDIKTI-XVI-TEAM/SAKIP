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
