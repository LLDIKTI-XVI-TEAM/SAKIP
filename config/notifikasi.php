<?php

return [
    'kanal' => [
        'email' => ['aktif' => env('SAKIP_NOTIFIKASI_EMAIL_AKTIF', false) === true, 'driver' => env('SAKIP_NOTIFIKASI_EMAIL_DRIVER', 'log')],
        'whatsapp' => ['aktif' => env('SAKIP_NOTIFIKASI_WHATSAPP_AKTIF', false) === true, 'driver' => env('SAKIP_NOTIFIKASI_WHATSAPP_DRIVER', 'log')],
    ],
    'pemulihan_menit' => 10,
];
