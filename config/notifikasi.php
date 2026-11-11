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
    // Pesan EWS memuat jumlah dan tautan SAKIP; daftar kode indikator tidak diikutkan agar pesan WhatsApp tetap pendek.
    'template_ews' => 'Pengingat tenggat pengisian: {{jumlah}} indikator belum selesai. Batas pengisian {{jatuh_tempo_tampil}}. Isi di SAKIP: {{url}}',
    // Default sementara untuk rekap Perencanaan; rincian hanya per unit agar pesan WhatsApp tetap pendek.
    'template_rekap' => 'Rekap pengisian {{periode}} {{tahun}}, batas {{jatuh_tempo_tampil}}: {{belum_mengisi}} belum mengisi, {{draf}} draf, {{dikembalikan}} dikembalikan, {{diajukan}} sudah diajukan dari {{total}} indikator. {{rincian_unit}} Lihat di SAKIP: {{url}}',
    // Default sementara untuk pembukaan jendela; isi final menunggu penetapan pemilik layanan.
    'template_pembukaan' => 'Jendela pengisian {{periode}} {{tahun}} di SAKIP dibuka sampai {{selesai}}. Masuk ke SAKIP untuk mengisi: {{tautan}}',
];
