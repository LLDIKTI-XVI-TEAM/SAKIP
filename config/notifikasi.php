<?php

return [
    // Jika Q4 direvisi, periksa juga ambang urgensi banner di config/pengingat.php.
    'h_minus' => ['pic' => [7, 3, 1], 'rekap' => [3, 1]],
    'kanal' => [
        'email' => ['aktif' => env('SAKIP_NOTIFIKASI_EMAIL_AKTIF', false) === true, 'driver' => env('SAKIP_NOTIFIKASI_EMAIL_DRIVER', 'log')],
        'whatsapp' => ['aktif' => env('SAKIP_NOTIFIKASI_WHATSAPP_AKTIF', false) === true, 'driver' => env('SAKIP_NOTIFIKASI_WHATSAPP_DRIVER', 'log')],
    ],
    'pemulihan_menit' => 10,
    'template_pengembalian' => 'Perbaikan diperlukan: {{judul}}. {{alasan}} {{url}}',
];
