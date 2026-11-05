# Fondasi notifikasi eksternal — ISS-12.07

Refinement #227: keputusan PM 4 November 2026 untuk Q1–Q8, dilengkapi [keputusan PM 5 November 2026 untuk Q9–Q10](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/248#issuecomment-6637902148). Kode lokal tidak mengaktifkan WhatsApp atau Email: kedua kanal mati dan provider hanya `log`. Seluruh Kesiapan Infrastruktur §3 tetap harus `Siap berbukti` sebelum provider nyata diaktifkan. Khusus WhatsApp, sumber nomor kontak belum tersedia.

## Operasional

Fallback deployment memakai `SAKIP_NOTIFIKASI_EMAIL_AKTIF`, `SAKIP_NOTIFIKASI_WHATSAPP_AKTIF` (default `false`) dan `SAKIP_NOTIFIKASI_EMAIL_DRIVER`, `SAKIP_NOTIFIKASI_WHATSAPP_DRIVER` (default `log`). Hanya adapter `log` yang tersedia; menambah nilai konfigurasi bukan instalasi provider nyata atau bukti kesiapan Q80. Credential provider spesifik ditambahkan melalui environment saat provider dipilih, bukan melalui `pengaturan`.

- Jalankan `php artisan queue:work notifikasi --queue=notifikasi --timeout=60`. Koneksi `notifikasi` memakai tabel `jobs` pada koneksi aplikasi, dengan `after_commit=false` dan `retry_after=90`. Jangan mengganti driver/koneksi tanpa meninjau atomisitas outbox–job.
- `php artisan notifikasi:antre-ulang` memulihkan baris antre berusia lebih dari `notifikasi.pemulihan_menit` (default 10), tanpa job tertunda/berjalan/gagal terkait dan setelah jeda retry berakhir. Tidak memulihkan status terminal atau hasil ambigu.
- `php artisan queue:retry <uuid>` membuka kembali hanya `gagal/percobaan_habis`; status yang sudah diterima, dibatalkan, gagal permanen, atau perlu rekonsiliasi tetap terlindungi. `percobaan` tetap kumulatif; hanya retry manual tersebut mereset `percobaan_siklus`. Recovery job mempertahankan hitungan siklus, sehingga batas otomatis tetap empat upaya dengan jeda minimum 60/300/1800 detik. Jeda provider yang lebih panjang dihormati, termasuk setelah retry manual. Job yang diambil sebelum `coba_lagi_pada` dijadwalkan ulang sampai batas tunggu, tanpa panggilan provider.
- Batas empat upaya kirim memakai `percobaan_siklus` persisten, bukan jumlah reservasi job. Reservasi yang hanya menunggu tidak menghabiskan jatah tersebut (`tries=0`); exception penyimpanan tetap dibatasi oleh `maxExceptions=4` bawaan Laravel. Jika worker mati setelah menyimpan `gagal/percobaan_habis` tetapi sebelum memanggil `fail()`, saat job database masih tersedia untuk redelivery, redelivery mencatat kegagalan itu tanpa memanggil provider lagi, sehingga `queue:retry` tetap tersedia.
- Saklar dibaca pada awal tiap upaya dari dua kunci boolean `notifikasi.kanal_email_aktif` dan `notifikasi.kanal_whatsapp_aktif`, dengan fallback konfigurasi. Pengelolaan UI/whitelist kunci tersebut milik ISS-13.04; tidak ada UI baru pada issue ini. Panggilan yang sudah berlangsung tidak ditarik.
- Status `perlu_rekonsiliasi` dipersist sebelum provider dipanggil. Hasil pasti menutup penanda tersebut; crash atau exception tak dikenal mempertahankannya. Provider `log` tidak melakukan rekonsiliasi atau kirim ulang atas hasil ambigu.
- Semua keluaran adapter wajib berupa klasifikasi aman. Kegagalan sementara berarti dipastikan belum diterima, termasuk rate limit sesuai kontrak provider; timeout bebas tidak dianggap aman diulang. Payload job hanya ID dan metadata korelasi, tanpa alamat/template/parameter.
- Kegagalan SQL saat enqueue di-rollback lalu keluar sebagai kode `notifikasi.gangguan_penyimpanan` tanpa exception asli. Reporter tidak menerima query/bindings yang memuat snapshot kontak atau pesan.

## Kontrak integrasi

`AntrekanNotifikasiEksternal::antre(kejadian, tujuan, periode, penerima, kanal, template, parameter)` menerima penerima tepercaya dari producer; tidak menduplikasi resolver PJ. Tujuan awal yang didukung adalah Rencana Aksi dan Pengukuran. Jenis tujuan lain dan aturan agregat EWS/rekap ditambahkan bersama producer pemiliknya, tanpa bypass Policy.

Producer memakai identitas kejadian stabil; kejadian pengembalian berikutnya dan slot H-minus berikutnya berbeda. UUID objek dan penerima diambil kembali dari baris tersimpan sebelum membentuk kunci, sehingga variasi penulisan UUID tidak menggandakan pesan. Alamat, template, parameter, driver, dan simulasi tidak berubah pada replay. Parameter harus dipilih producer dan bebas rahasia, termasuk nilainya; pemeriksaan nama kunci bukan penyamaran nilai bebas.

Waktu outbox disimpan sebagai `timestamp` tanpa zona dengan nilai UTC, sesuai Data Model §2.39 dan zona aplikasi. Penjadwalan retry tidak bergantung pada zona waktu sesi PostgreSQL. Penyajian rentang tanggal tetap memakai WITA.

Atomisitas di sini adalah outbox–job. Kejadian yang hilang sebelum producer memanggil service tetap tanggung jawab integrasi ISS-12.06/12.04. Item in-app memerlukan ISS-12.02; tidak diklaim tersedia dari fondasi ini.

`PembacaStatusPengiriman::handle(actor, filter)` adalah kontrak untuk halaman ISS-13.04, berizin `pengaturan:update`. Filter: `status`, `kanal`, `penerima` (UUID), `mulai`, `selesai`, `page`; pagination 25. Tanggal filter memakai WITA (hari akhir inklusif), dengan batas query dikonversi ke UTC. Hasil tidak memuat alamat, template, parameter, atau rincian keputusan akses. Simulasi berlabel **Simulasi diterima**, bukan delivery dan tidak boleh dihitung dalam pengiriman nyata/bukti kesiapan.

## Verifikasi

Test berada di `tests/Feature/Notifikasi/NotifikasiEksternalTest.php` dan `tests/Integration/Notifikasi/NotifikasiConcurrencyTest.php`. Hasil eksekusi serta batas environment dicatat pada laporan kerja lokal/PR. Checklist readiness, CI, UAT, dan deployment tidak dicentang berdasarkan source test.
