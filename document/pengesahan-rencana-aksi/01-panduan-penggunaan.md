# Panduan Penggunaan: Pengesahan Rencana Aksi

> Referensi: US-05.05 (AC-1 s.d. AC-6). Berlaku untuk Fase Awal SAKIP.

## 1. Ringkasan

Pengesahan Rencana Aksi adalah langkah terakhir yang mengubah usulan yang sudah diverifikasi menjadi dokumen resmi yang berlaku operasional. Tindakan ini hanya dilakukan oleh Tim Perencanaan atau Superadmin yang memegang izin pengesahan dan tercatat dalam jejak audit. Hasilnya, versi pengajuan yang disahkan dibekukan dan dipertahankan sebagai dokumen resmi, sedangkan bukti dukung yang dirujuknya tidak boleh lagi dihapus.

## 2. Istilah Kunci

**Rencana Aksi (RA).** Rencana kerja tahunan sebuah unit untuk mendukung indikator kinerja tertentu. Satu RA selalu terikat pada satu tahun, satu unit, dan satu indikator.

**Versi pengajuan.** Setiap kali RA diajukan, sistem menyimpan salinan beku isi pengajuan tersebut beserta siapa pengajunya dan melalui jalur apa. Yang disahkan adalah versi beku ini, bukan draf yang masih bisa berubah.

**Jalur PIC vs jalur Perencanaan.** Ini bukan jabatan, melainkan cara pengajuan masuk ke sistem. Jalur PIC berarti pengajuan dilakukan oleh petugas unit berdasarkan penugasan dan izin khusus unit tersebut. Jalur Perencanaan berarti pengajuan dilakukan oleh Tim Perencanaan yang berwenang mengisi atas nama unit mana pun.

**Kaitan dengan Q32.** Sejak keputusan final Q32 tanggal 24 September 2026, SAKIP hanya mengenal lima peran sistem: superadmin, admin, perencanaan, pimpinan, dan pegawai. Tidak ada peran bernama PIC. Kata PIC di dokumen ini selalu berarti konteks operasional — seseorang yang ditugaskan pada indikator dan diberi izin kelola unit — bukan peran sistem.

**F1 (larangan keras jalur PIC).** Aturan pemisahan tugas: siapa pun yang mengajukan lewat jalur PIC tidak boleh menyetujui pengajuannya sendiri, baik pada tahap verifikasi maupun pengesahan.

**F2 dan self_approval (pengecualian jalur Perencanaan).** Jika Tim Perencanaan mengajukan atas nama unit lalu menyetujui sendiri pengajuannya, sistem mengizinkan sepanjang izinnya masih berlaku, tetapi menandai kejadian itu sebagai self_approval dalam jejak audit agar dapat diperiksa kemudian.

## 3. Alur End-to-End

```
Draft → Diajukan → Diverifikasi → Disahkan
              ↘ Dikembalikan (revisi) ↩ Diajukan kembali
```

**Draft → Diajukan.** Dilakukan oleh petugas unit melalui jalur PIC (memerlukan izin kelola untuk unit tersebut) atau oleh Tim Perencanaan melalui jalur Perencanaan. Pada titik ini sistem mencatat siapa pengaju dan jalur apa yang dipakai, dan catatan ini tidak bisa diubah kemudian.

**Diajukan → Diverifikasi.** Dilakukan oleh Tim Perencanaan atau Superadmin pemegang izin verifikasi. Isi versi pengajuan diperiksa kebenarannya. Jika ada yang kurang, RA dikembalikan sebagai revisi agar unit memperbaikinya dan mengajukan ulang sebagai versi baru.

**Diajukan/Diverifikasi → Dikembalikan.** Jalur revisi. RA kembali ke unit untuk diperbaiki, lalu alur mengulang dari pengajuan.

**Diverifikasi → Disahkan.** Inilah pengesahan yang dibahas dokumen ini. Hanya dilakukan oleh pemegang izin bernama rencana_aksi:sahkan, yaitu Tim Perencanaan atau Superadmin. Tombol Sahkan rencana aksi hanya muncul bila status RA sudah Diverifikasi dan sistem menilai pengguna saat ini berhak mengesahkan.

**Di mana peran Pimpinan?** Pada Fase Awal, Pimpinan hanya membaca. Pimpinan dapat melihat status dan isi RA yang disahkan sebagai bahan pemantauan, tetapi tidak menekan tombol verifikasi maupun pengesahan.

## 4. Aturan F1 dan F2 dalam Bahasa Sehari-hari

Intinya: pengusul dari unit tidak boleh menjadi hakim atas usulannya sendiri, sedangkan Tim Perencanaan diberi kelonggaran terbatas yang tetap diawasi.

**Contoh 1 — Budi ditolak (F1).** Budi adalah petugas unit yang mengajukan RA lewat jalur PIC. Ketika Budi membuka RA yang sudah Diverifikasi dan menekan Sahkan, sistem menolak dengan pesan: Pengaju jalur PIC tidak boleh menyetujui pengajuannya sendiri. Budi harus meminta rekan Perencanaan lain yang berizin untuk mengesahkan. Aturan ini berlaku mutlak dan tidak bisa dimatikan oleh siapa pun, termasuk Superadmin.

**Contoh 2 — Sari diizinkan dengan catatan (F2).** Sari adalah staf Perencanaan. Karena unit terlambat mengisi, Sari mengajukan RA atas nama unit tersebut melalui jalur Perencanaan, lalu Sari juga yang mengesahkannya setelah verifikasi. Sistem mengizinkan karena jalurnya adalah jalur Perencanaan, tetapi mencatat kejadian itu sebagai self_approval dalam jejak audit. Kelonggaran ini disengaja agar pekerjaan tidak macet saat personel Perencanaan terbatas.

**Contoh 3 — izin dicabut tetap ditolak.** Misalkan Sari mengajukan lewat jalur Perencanaan, tetapi sebelum sempat mengesahkan, izin pengesahannya dicabut. Ketika Sari mencoba mengesahkan, sistem menolak dengan pesan: Izin tindakan tidak tersedia atau telah dicabut. Artinya F2 bukan jalan pintas: tanpa izin yang masih berlaku, atau bila ada penolakan akses, pengesahan tetap gagal.

## 5. Syarat Sebelum Tombol Sahkan Berhasil

Agar penekanan tombol Konfirmasi pada dialog pengesahan berhasil, semua syarat berikut harus terpenuhi:

1. **Status harus Diverifikasi.** Jika status masih Draft, Diajukan, Dikembalikan, atau sudah Disahkan, sistem menolak dengan pesan: Status rencana aksi tidak sesuai untuk tindakan ini.
2. **Versi harus yang terbaru (anti-stale).** Dialog pengesahan menampilkan nomor pengajuan dan nomor versi data. Bila orang lain mengubah RA setelah halaman dibuka, sistem menolak dengan pesan: Data telah berubah. Muat ulang sebelum mengulangi tindakan. Solusinya: tutup dialog, muat ulang halaman, periksa status terbaru, lalu ulangi bila masih Diverifikasi.
3. **Tahun belum ditutup.** Jika jadwal tahunan sudah melewati tanggal penutupan, pengesahan ditolak dengan pesan: Tahun sudah ditutup; diperlukan sesi koreksi resmi yang mencakup rencana aksi ini. Hubungi Tim Perencanaan untuk memastikan apakah ada sesi koreksi resmi yang mencakup RA tersebut.
4. **Konteks data cocok.** Unit RA harus cocok dengan unit snapshot jadwal, renstra jadwal harus cocok dengan indikator, unit harus masih aktif, jadwal tahunan harus aktif, dan versi pengajuan yang direviu harus tersedia dan cocok. Bila tidak, muncul pesan seperti: Unit rencana aksi tidak cocok dengan snapshot jadwal, Renstra jadwal tidak cocok dengan indikator, Unit organisasi rencana aksi berstatus nonaktif, Jadwal tahunan harus aktif, atau Versi pengajuan yang direviu belum tersedia atau tidak cocok.

## 6. Bukti Dukung Pasca-Sah

Setelah RA disahkan, bukti dukung yang dirujuk versi resmi dilindungi. Upaya menghapusnya ditolak dengan pesan: Bukti yang dirujuk versi resmi tidak boleh dihapus. Ini sesuai janji AC-6 pada US-05.05: dokumen resmi tidak boleh kehilangan bukti pendukungnya secara diam-diam. Koreksi setelah sah tidak dilakukan dengan menghapus bukti, melainkan melalui mekanisme buka-kembali resmi.

## 7. Fitur yang Dibuat untuk Pengesahan

* **Aksi pengesahan** melalui rute bernama rencana-aksi.sahkan. Setelah berhasil, sistem menampilkan pesan: Rencana aksi disahkan dan versi pengajuan dipertahankan. Status RA berubah menjadi Disahkan dan halaman menampilkan penanda: Rencana aksi telah disahkan beserta penjelasan bahwa versi pengajuan beku dipertahankan sebagai dokumen resmi.
* **Dialog konfirmasi Sahkan rencana aksi resmi.** Dialog menampilkan nomor pengajuan dan nomor versi data yang akan disahkan, meminta penekanan tombol Konfirmasi, serta menyediakan tombol Batal. Selama proses berjalan tombol dikunci agar tidak terkirim ganda, dan bila koneksi terputus atau hasil belum pasti, dialog meminta pengguna memeriksa status RA sebelum mencoba kembali.
* **Pesan error spesifik per kondisi.** Setiap kegagalan memberi pesan sesuai penyebabnya: izin dicabut, larangan F1 untuk pengaju jalur PIC, status tidak sesuai, data berubah sehingga perlu muat ulang, tahun ditutup, atau ketidakcocokan snapshot, unit, renstra, dan versi. Setiap upaya — baik berhasil maupun ditolak — dicatat dalam jejak audit beserta alasan penolakannya.

## 8. FAQ

**1. Mengapa saya ditolak dengan pesan tidak boleh menyetujui sendiri?**
Karena Anda tercatat sebagai pengaju versi tersebut melalui jalur PIC. Aturan F1 melarang pengaju jalur PIC menyetujui pengajuannya sendiri. Minta Tim Perencanaan atau Superadmin lain yang berizin untuk mengesahkan.

**2. Mengapa rekan Perencanaan bisa mengesahkan pengajuannya sendiri?**
Karena pengajuannya masuk melalui jalur Perencanaan, bukan jalur PIC. Aturan F2 mengizinkannya selama izinnya masih berlaku, dan kejadian itu ditandai self_approval dalam jejak audit.

**3. Mengapa saya diminta memuat ulang halaman?**
Karena data RA berubah setelah halaman Anda dibuka — misalnya ada pengajuan ulang atau perubahan versi. Muat ulang untuk mendapatkan versi terbaru, pastikan status masih Diverifikasi, lalu ulangi pengesahan.

**4. Tahun sudah ditutup, siapa yang dihubungi?**
Hubungi Tim Perencanaan. Pengesahan setelah penutupan hanya bisa berjalan di dalam sesi koreksi resmi yang mencakup RA Anda. Di luar sesi itu, pengesahan tetap ditolak.

**5. Tombol Sahkan tidak muncul, padahal saya merasa berwenang?**
Tombol hanya muncul bila dua hal terpenuhi sekaligus: status RA adalah Diverifikasi dan sistem menilai Anda memegang izin pengesahan yang masih berlaku untuk unit tersebut. Jika salah satu tidak terpenuhi, tombol disembunyikan. Periksa status RA dan izin Anda ke admin akses.

**6. Apakah Pimpinan bisa mengesahkan?**
Pada Fase Awal, tidak. Pimpinan bersifat baca-saja untuk alur ini dan memantau hasil yang sudah disahkan.

**7. Bukti pendukung salah unggah setelah sah, bagaimana memperbaikinya?**
Jangan menghapus bukti yang dirujuk versi resmi karena sistem akan menolak. Ajukan perbaikan melalui mekanisme buka-kembali resmi agar koreksi tercatat sebagai versi baru tanpa menghilangkan histori.

## 9. Batasan yang Disengaja (Non-Goal)

* **Verifikasi adalah alur lain.** Dokumen ini hanya membahas pengesahan dari Diverifikasi menjadi Disahkan. Tata cara memeriksa dan memverifikasi usulan diatur dalam alur verifikasi tersendiri.
* **Buka-kembali milik ISS-05.06.** Membatalkan atau membuka kembali RA yang sudah disahkan — misalnya untuk koreksi resmi — bukan bagian panduan ini dan memiliki aturan serta izin tersendiri.
* **Pimpinan read-only pada Fase Awal.** Keterlibatan persetujuan oleh Pimpinan belum menjadi bagian alur ini. Pimpinan memantau hasil pengesahan tanpa melakukan transisi status.
