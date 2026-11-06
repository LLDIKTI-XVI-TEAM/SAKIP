# Konfigurasi dan status notifikasi eksternal — ISS-13.04

Konfigurasi/status mengikuti refinement PM Q1–Q6 yang diteruskan pengguna pada 7 November 2026, menggantikan bagian terkait keputusan grilling 6 November. #228 dapat selesai dengan provider `log` dan kedua kanal mati secara default setelah verifikasi kontrak terbaru. Pengelolaan template dikeluarkan dari scope/DoD #228; template bawaan menjadi tanggung jawab masing-masing producer dan pengelolaan melalui UI menjadi pekerjaan terpisah setelah provider dipilih. Butir Template pesan pada Kesiapan Infrastruktur §3 tetap terbuka.

## Konfigurasi aplikasi

Pemegang `pengaturan:update` membuka tab Notifikasi pada `/pengaturan`. Saklar WhatsApp/Email memakai tipe boolean, disimpan sebagai `1`/`0`, default mati. Seeder `PengaturanSeeder` idempoten dan mempertahankan nilai existing. Tiga kunci grup `notifikasi` adalah dua saklar dan `notifikasi.jam_kirim` (`HH:MM` WITA atau kosong). Jam kosong dikembalikan accessor sebagai NULL; producer scheduler harus menahan dispatch sampai jam ditetapkan. Tidak menetapkan jam 08.00 atau jadwal lain. Grup ini tidak masuk shared props.

Hari pengiriman mengikuti Q4 dan bukan parameter yang dapat diubah melalui `pengaturan`. `config/notifikasi.php` menyediakan `h_minus.pic = [7,3,1]` dan `h_minus.rekap = [3,1]`; `KonfigurasiNotifikasi::hMinusPic()` dan `hMinusRekap()` membacanya untuk producer dan tampilan baca-saja. Baris lama `notifikasi.h_minus`, bila ada, tidak dibaca atau dimutasi; tidak dihapus oleh penyesuaian ini. Perubahan hari memerlukan keputusan yang merevisi Q4 dan konfirmasi Perencanaan. Tidak menerima daftar bebas.

`KonfigurasiNotifikasi` tetap menjadi pembaca saklar efektif tanpa cache dan menyediakan `jamKirim()`. Database mengalahkan fallback deployment untuk saklar. Sebelum memasang default pada lingkungan yang sudah memakai saklar environment, periksa nilai efektif: baris baru default `0` mengalahkan fallback environment aktif. Seeder tidak dijalankan otomatis oleh konfigurasi ini; tidak memerlukan migration/reset database development.

Fallback yang sudah tersedia: `SAKIP_NOTIFIKASI_EMAIL_AKTIF`, `SAKIP_NOTIFIKASI_WHATSAPP_AKTIF` (default false), serta `SAKIP_NOTIFIKASI_EMAIL_DRIVER`, `SAKIP_NOTIFIKASI_WHATSAPP_DRIVER` (default log). Credential dan driver/adapter tidak masuk whitelist/form Setelan. Tidak menambah kunci credential spekulatif; credential spesifik hanya ditambahkan melalui environment/secret management ketika provider nyata dipilih. Proyeksi mode per kanal hanya memuat penanda simulasi dari driver, tanpa nilai credential atau konfigurasi lengkap.

Provider yang tersedia hanya `log`; driver lain tetap ditolak fondasi. Saklar boleh aktif untuk simulasi yang diberi label jelas dan tidak mengirim WA/Email nyata. Dependensi merge #228 adalah ISS-12.07 merge; provider nyata hanya untuk aktivasi Q80 §80.1. Seluruh gate Kesiapan Infrastruktur §3 tetap harus `Siap berbukti` sebelum aktivasi nyata. Test lokal tidak mengubah status kesiapan.

## Status dan batas integrasi

`/pengaturan/notifikasi/pengiriman` memakai `PembacaStatusPengiriman`, permission yang sama, filter status/kanal/ID penerima/rentang tanggal WITA, serta pagination 25. Halaman terpisah dari form Setelan. Nama penerima diambil sebagai proyeksi server terbatas untuk halaman aktif, tanpa alamat, template, parameter, credential atau rincian keputusan akses. **Simulasi diterima** dan accepted bukan delivered serta bukan bukti kesiapan pengiriman nyata. Tidak menyediakan retry atau aksi operasional UI; operator mengikuti `php artisan queue:retry <uuid>` dengan batasan dalam catatan rilis ISS-12.07.

Kanal mati membatalkan enqueue dengan `status=dibatalkan`, `kode_hasil=kanal_nonaktif` tanpa job; worker membaca ulang saklar sebelum upaya berikutnya. Pembatalan terminal dan perubahan template tidak memutasi snapshot pesan lama. TEST-3 membuktikan kanal dimatikan lewat Setelan sebelum memanggil `AntrekanNotifikasiEksternal::antre`; regresi pengembalian RA/Pengukuran existing diperiksa secara terpisah. Integrasi pengembalian→outbox, dengan status domain tetap `dikembalikan` dan outbox `dibatalkan`, dibuktikan #229 (ISS-12.06). #228 tidak mengklaim pengujian alur lengkap.

## Verifikasi

Coverage yang disesuaikan: TEST-1 dua accessor, penolakan kunci H-minus/credential asing, mode dari driver dan nama penerima pada props, serta TEST-3 Setelan→enqueue tanpa job baru. Hasil pelaksanaan terbaru dan gate dicatat pada laporan QA luar repository; catatan ini tidak menggantikan bukti run. Checkbox kontrak yang berubah hanya dicentang setelah verifikasi. Hasil lokal bukan CI remote, UAT, merge, deployment atau aktivasi provider nyata.
