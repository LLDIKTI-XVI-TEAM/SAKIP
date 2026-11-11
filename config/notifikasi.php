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
    // Default sementara untuk pengingat tenggat; isi final menunggu penetapan pemilik layanan.
    'template_ews' => 'Pengingat tenggat pengisian: {{jumlah}} indikator belum selesai. Batas pengisian {{jatuh_tempo}}.',
    // Default sementara untuk pembukaan jendela; isi final menunggu penetapan pemilik layanan.
    'template_pembukaan' => 'Jendela pengisian {{periode}} {{tahun}} di SAKIP dibuka sampai {{selesai}}. Masuk ke SAKIP untuk mengisi: {{tautan}}',
];
