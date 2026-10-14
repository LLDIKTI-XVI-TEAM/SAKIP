# PRD — SAKIP LLDIKTI Wilayah XVI
## Product Requirements Document

> **Klarifikasi final LLDIKTI Wilayah XVI — 24 September 2026**  
> Bagian ini adalah kontrak terbaru dan **menggantikan keputusan Q31 atau teks lama yang bertentangan**. Role bawaan SAKIP berjumlah **lima**: `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`. **PIC bukan role sistem**; PIC adalah konteks operasional yang dibentuk oleh penugasan dan grant unit. Detail keputusan dicatat sebagai **Q32** pada dokumen Keputusan Penyelarasan.


**Nama proyek:** SAKIP (Sistem Akuntabilitas Kinerja) — LLDIKTI Wilayah XVI
**Sifat proyek:** Aplikasi baru, terpisah dari sistem informasi lain yang sudah berjalan di LLDIKTI Wilayah XVI


> **Q32 aktif:** Q31 dipertahankan hanya sebagai histori pada dokumen Keputusan Penyelarasan. Seluruh kontrak implementasi PRD ini menggunakan klarifikasi final 24 September 2026.

> **Q39–Q51 (13 Oktober 2026):** keputusan hasil rapat asistensi LLDIKTI 13 Oktober 2026 (Q39–Q46) dan keputusan PM dari analisis dokumen resmi 2026 — Renstra 2025–2029, Perjanjian Kinerja 2026, Rencana Aksi 2026, serta Laporan Kinerja Triwulan I dan II 2026 (Q47–Q51) — diselaraskan ke PRD ini. Keduanya menjadi baseline kerja tim sampai konfirmasi tertulis Tim Perencanaan diterima (status: menunggu); koreksi dicatat pada Q terkait di dokumen Keputusan Penyelarasan lalu diselaraskan ulang. Rujukan "(Q39)" s.d. "(Q51)" pada teks menunjuk keputusan tersebut. Seluruhnya adalah keputusan requirement; status implementasi: belum, kecuali disebut lain.

---

## 1. Ringkasan

SAKIP adalah aplikasi internal untuk menyusun rencana aksi, mencatat kegiatan pendukung, serta mencatat, memverifikasi, dan mengesahkan pengukuran indikator kinerja LLDIKTI Wilayah XVI. Indikator yang diukur diturunkan dari Kepmendiktisaintek 358/M/KEP/2025, dengan target tahunan resmi (target PK) mengacu pada Perjanjian Kinerja (PK) yang ditandatangani setiap tahun, sedangkan target per tahun dalam Renstra (target Renstra) menjadi pembanding (Q39). Aplikasi ini menggantikan pencatatan manual (spreadsheet/dokumen lepas) dengan satu sistem yang:

- Menjaga rantai kinerja tetap tertelusur: Kepmen → Renstra → Sasaran → Indikator (+ target Renstra) → PK (target PK; baseline sebagai data pendukung, Q50) → Jadwal Tahunan → Rencana Aksi → Kegiatan → Pengukuran → Status Capaian → Rekomendasi Pimpinan.
- Menyusun rencana aksi per indikator per tahun — target dipecah ke level periode dan diinput pada level komponen, bersifat kumulatif antarperiode, beserta daftar kegiatan per periode dan anggarannya (Q42). Rencana aksi dapat disusun sepanjang tahun, dengan periode yang jendela pengisiannya sudah lewat terkunci bagi PIC, dan harus disahkan sebelum pengukuran diajukan (Q41).
- Mencatat kegiatan yang dilaksanakan tiap periode — termasuk anggaran dan RAB-nya (Q42) — dan mengaitkannya (klaim) ke rencana aksi/komponen indikator yang didukungnya, sehingga narasi pengukuran (progres kegiatan, kendala/masalah, dan tindak lanjut/strategi) tersaji pada rekapitulasi bersama daftar kegiatan yang mendukungnya (Q45).
- Menyediakan mesin perhitungan indikator berbasis komponen: untuk indikator bertipe rasio atau penjumlahan, nilai capaian tidak lagi diketik sebagai satu angka jadi, melainkan diturunkan sistem dari nilai komponen pembentuknya (mis. pembilang dan penyebut suatu rasio) sesuai definisi yang dikelola Perencanaan — tanpa hardcode per indikator.
- Memberi kejelasan siapa bertanggung jawab mengisi data untuk indikator apa, kapan jendela pengisian pengukuran per periode — yang sekaligus mengunci periode tersebut pada rencana aksi bagi PIC (Q41) — dan siapa yang dikecualikan dari batas waktu itu.
- Menegakkan alur kerja berjenjang pada rencana aksi maupun pengukuran (Draft → Diajukan → Diverifikasi → Disahkan, dengan Dikembalikan sebagai jalur revisi), disertai gerbang kelengkapan — rencana aksi harus disahkan lebih dulu, seluruh komponen aktif harus terisi kecuali realisasi belum dapat diukur (Q47), narasi pengukuran wajib terisi (Q45), dan bukti dukung wajib tahap pengukuran harus terpenuhi (unggahan file, tautan, atau keterangan teks, sesuai mode yang diizinkan) — sebelum pengukuran dapat diajukan; rencana aksi digerbangi kelengkapan target serta kegiatan beserta RAB-nya (Q42), dan kegiatan mendapat gerbang serupa sebelum dinyatakan terlaksana.
- Menyediakan audit trail penuh atas setiap perubahan data kinerja, termasuk metadata (siapa, kapan, serta alasan untuk tindakan yang mewajibkannya — Q43), termasuk percobaan tindakan yang ditolak oleh gerbang kelengkapan.
- Menghasilkan dokumen resmi lembaga — Rencana Aksi tahunan dan Laporan Kinerja Triwulan — dari data yang disahkan, serta menyimpan arsip PDF bertanda tangannya (Q48).
- Menjaga integritas historis lewat snapshot: begitu suatu periode pelaporan diaktifkan, konteks pengukuran (nama indikator, definisi, satuan, presisi, target, arah penilaian, cara hitung, dan definisi komponennya) dibekukan sehingga perubahan pada master data di kemudian hari tidak mengubah riwayat capaian tahun-tahun sebelumnya.
- Menyediakan jalur koreksi dan penambahan data yang terkendali dan teraudit — baik sebelum maupun sesudah suatu tahun ditutup — tanpa membuka celah menimpa data historis diam-diam.

Dokumen ini mendefinisikan cakupan MVP (Fase Awal) yang dibangun lebih dulu, dan menandai secara eksplisit bagian yang ditunda ke Fase Lanjutan agar cakupan tidak ambigu saat implementasi maupun saat evaluasi hasil kerja.

---

## 2. Konteks Regulasi & Rantai Kinerja

### 2.1 Dasar Regulasi

- **Kepmendiktisaintek 358/M/KEP/2025** — sumber indikator kinerja yang wajib diukur oleh LLDIKTI, menjadi acuan definisi dan daftar indikator yang dimuat dalam Renstra.
- **Perjanjian Kinerja (PK)** — dokumen tahunan yang ditandatangani pimpinan, memuat target tahunan resmi atas indikator-indikator dalam Renstra. PK adalah rujukan legal-formal atas target PK yang menjadi acuan pengukuran; target per tahun dalam dokumen Renstra (target Renstra) dicatat terpisah sebagai pembanding dan tidak dipakai menghitung capaian (Q39). PK dapat direvisi sebagai versi PK baru (Q40, §12.7).
- Dokumen turunan lain (Renstra internal LLDIKTI XVI, jadwal pelaporan triwulanan/semesteran/tahunan) mengikuti siklus manajemen kinerja instansi pemerintah pada umumnya (rencana strategis → PK → rencana aksi → pengukuran → pelaporan → reviu/evaluasi).
- Dasar aturan yang melandasi Renstra dan indikator dicatat sebagai entitas terstruktur **`regulasi`** (§9) — bukan sekadar teks bebas — sehingga rujukan Kepmen/Permen/Perpres/keputusan lain dapat ditelusuri per Renstra maupun per indikator, dilengkapi lampiran dokumen sumbernya.

### 2.2 Rantai Kinerja (Chain of Accountability)

```
Kepmendiktisaintek 358/M/KEP/2025
        │  (mendefinisikan indikator wajib)
        ▼
      Renstra  ──────────────► Sasaran ──────────────► Indikator (+ komponen & cara hitung)
        │                                                   │
        │ (per tahun)                                       │ (per tahun Renstra)
        ▼                                                   ▼
   Perjanjian Kinerja (PK)  ◄──(pembanding)─────  Target Renstra
   (target PK per indikator; baseline = data pendukung, Q50)
        │
        │ (mengesahkan target resmi tahun berjalan; revisi berversi, Q40)
        ▼
   Jadwal Tahunan (aktivasi → snapshot cara hitung & komponen)
        │
        ▼
   Rencana Aksi per Indikator (target per periode, per komponen + daftar kegiatan per periode)
        │
        ▼
   Kegiatan per Periode (+ anggaran/RAB) ──(klaim)──► mendukung Rencana Aksi/komponen
        │
        ▼
   Pengukuran per Periode (input komponen + narasi + berkas pertanggungjawaban)
        │
        ▼
   Status Capaian ──► Rekomendasi Pimpinan (disetujui, Q51) ──► Dashboard / Laporan / dokumen resmi (Q48)
```

Setiap indikator harus dapat ditelusuri balik ke Sasaran, Renstra, dan pada akhirnya ke Kepmen yang melandasinya. Setiap nilai capaian harus dapat ditelusuri ke target PK yang berlaku untuk periode tersebut (via snapshot jadwal, termasuk versi snapshot hasil revisi PK — lihat §12, Q40), ke rencana aksi yang telah disahkan untuk indikator dan tahun tersebut (§14), dan — bila indikator bertipe rasio/penjumlahan — ke nilai komponen pembentuknya (§17).

### 2.3 Mengapa Sistem Ini Dibutuhkan

- Pencatatan manual rawan versi ganda, tidak ada jejak siapa mengubah apa, dan sulit direkonsiliasi saat evaluasi internal (Itjen) maupun eksternal (Kemen PANRB).
- Perhitungan skor indikator berbentuk rasio atau gabungan komponen selama ini dilakukan manual di luar sistem, rawan kesalahan rumus dan sulit diperiksa ulang; sementara rencana kerja dan realisasi kegiatan tidak terhubung eksplisit dengan indikator yang didukungnya, sehingga narasi progres/kendala/tindak lanjut ditulis ulang secara manual setiap periode.
- Tidak ada mekanisme baku untuk menandai bahwa suatu angka "sudah final/sah" versus "masih draft/dalam reviu".
- Perubahan struktur organisasi (unit, penanggung jawab) selama ini tidak terekam sebagai riwayat, menyulitkan audit lintas tahun.

### 2.4 Glosarium Istilah Dokumen Resmi

Dokumen resmi kinerja LLDIKTI Wilayah XVI memakai beberapa istilah dengan arti yang berbeda satu sama lain dan berbeda dari entitas SAKIP. Glosarium ini menjadi acuan agar istilah tersebut tidak tertukar di aplikasi, dokumen proyek, maupun keluaran (ketentuan pelengkap pada pengantar Q47–Q51 di dokumen Keputusan Penyelarasan).

| Istilah | Dokumen asal | Arti | Kaitan dengan SAKIP |
|---|---|---|---|
| Program | Renstra 2025–2029 | Tingkat program dalam struktur perencanaan yang dimuat Renstra | Tidak dimodelkan sebagai entitas SAKIP; tidak sama dengan Renstra, Sasaran, maupun Indikator SAKIP |
| Kegiatan berkode 7733 dan 7735 | Perjanjian Kinerja 2026 | Kegiatan anggaran pada DIPA yang dicantumkan pada PK beserta anggarannya | Tidak dikelola SAKIP karena SAKIP tidak mengelola struktur anggaran DIPA (Q42 §42.2 butir 3, Q48 §48.1 butir 3); **bukan** entitas `kegiatan` SAKIP (§15). PK perubahan yang hanya mengubah anggaran dicatat sebagai lampiran tambahan PK (Q50 §50.7) |
| Rincian Output | Rencana Aksi 2026; Laporan Kinerja Triwulan | Rincian keluaran anggaran DIPA: tabel Rincian Output pada Rencana Aksi dan realisasi anggaran per Rincian Output pada laporan triwulan | Disusun di luar SAKIP dan tidak termasuk keluaran SAKIP (Q48 §48.1 butir 3) |
| Rencana Kegiatan/Aktivitas | Rencana Aksi 2026 | Kegiatan per indikator per triwulan | Inilah `kegiatan` SAKIP (§15): ditambahkan dari halaman Rencana Aksi per periode dan diklaim ke Rencana Aksi (§16, Q42 §42.1), dengan satu angka anggaran per kegiatan (Q42 §42.2) |

**Kode sasaran dan indikator (Q50 §50.4):** kode pada PK, Rencana Aksi, dan Laporan Kinerja Triwulan menjadi kode sasaran (**S1–S4**) dan indikator (**IKU 1.1–IKU 4.1**) di SAKIP; nomor IKU pada Renstra (IKU 1–8) dicatat sebagai keterangan pada definisi operasional indikator, tanpa kolom baru (§11).

| Nomor Renstra 2025–2029 | Kode SAKIP (PK, Rencana Aksi, laporan triwulan) | Indikator | Satuan |
|---|---|---|---|
| IKU 1 | IKU 1.1 | Keunggulan layanan LLDIKTI | % |
| IKU 2 | IKU 1.2 | Arsitektur Perguruan Tinggi Swasta (PTS) | % |
| IKU 3 | IKU 1.3 | Tata kelola LLDIKTI yang berkualitas dan berintegritas | Nilai |
| IKU 4 | IKU 2.1 | Fasilitasi peningkatan mutu pendidikan pada PTS oleh LLDIKTI | % |
| IKU 5 | IKU 2.2 | Pencegahan dan penanganan kekerasan, narkoba, dan korupsi | % |
| IKU 6 | IKU 3.1 | Fasilitasi pengembangan kemahasiswaan dan prestasi oleh LLDIKTI | % |
| IKU 7 | IKU 3.2 | Jumlah dosen PTS yang meningkat jabatan fungsionalnya | Orang |
| IKU 8 | IKU 3.3 | Fasilitasi peningkatan kinerja penelitian, publikasi, pengabdian kepada masyarakat, dan kemitraan PTS | % |
| — | IKU 4.1 (sasaran S4) | Nilai Kinerja Anggaran atas Pelaksanaan RKA-K/L | Nilai |

Sasaran S4 dan IKU 4.1 hanya ada di PK dan dicatat di bawah Renstra 2025–2029 tanpa target Renstra (Q50 §50.5). Rujukan lama yang memakai nomor Renstra tetap dibaca menurut tabel di atas: "IKU 3" pada §17.11.1 adalah IKU 1.3, dan "IKU 8" pada §17.11.2 adalah IKU 3.3 (Q50 §50.4 butir 3).

**Istilah SAKIP yang berpasangan dengan dokumen resmi:**

- **Target Renstra** — target per tahun dalam rentang Renstra per indikator; pembanding, bukan acuan capaian (§11, Q39).
- **Target PK** — target tahun berjalan pada Perjanjian Kinerja, disimpan di `target_tahunan` dengan nilai penuh; acuan target periode Rencana Aksi dan capaian (§11, §14.10, Q39, Q50). **PK awal** dan **PK akhir** adalah versi pertama dan versi yang berlaku pada periode nilai akhir setelah revisi PK berversi (§12.7, Q40).
- **Dinilai tahunan** — penanda indikator yang nilainya baru tersedia pada periode nilai akhir (§11, Q47 §47.1).
- **Tanpa target periode** — periode yang tidak ditargetkan pada indikator dinilai tahunan; berbeda dari target `0` (§14.3, Q47 §47.2–§47.3).
- **Belum dapat diukur** — realisasi yang nilainya belum tersedia pada periode tersebut; berbeda dari realisasi `0` dan dari "tidak dapat dihitung" (§20, Q47 §47.4).
- **Selisih terhadap target PK** — realisasi − target PK dalam satuan indikator, dibaca menurut `arah` (§21, Q47 §47.3).
- **Kegiatan bersama** — kegiatan yang diklaim lebih dari satu IKU; anggarannya tampil penuh di setiap IKU, tetapi dihitung sekali pada total per unit dan per tahun (§15.1, Q42 §42.2 butir 4).
- **Rencana Aksi tahunan** dan **Laporan Kinerja Triwulan** — dokumen gabungan seluruh indikator untuk satu lembaga yang dihasilkan SAKIP dalam Excel lalu ditandatangani elektronik di luar aplikasi (§24.1, Q48).

---

## 3. Tujuan Produk

1. Menyediakan satu sumber kebenaran (single source of truth) untuk data pengukuran kinerja LLDIKTI Wilayah XVI, tertaut langsung ke Renstra dan PK yang berlaku.
2. Menegakkan alur validasi berjenjang — baik pada penyusunan rencana aksi maupun pengukuran — sehingga data yang disajikan di dashboard/laporan telah melalui verifikasi dan pengesahan, bukan input mentah yang belum diperiksa.
3. Memberikan kejelasan tanggung jawab pengisian data per indikator per periode — termasuk jendela waktu pengisian pengukuran yang berbeda per periode (yang sekaligus mengunci periode itu pada rencana aksi bagi PIC; rencana aksi tidak lagi memiliki jendela penyusunan, Q41), siapa yang terikat tenggat itu, dan riwayat pergantian penanggung jawab.
4. Menjamin integritas historis data kinerja lintas tahun melalui mekanisme snapshot yang idempoten dan imutabel — mencakup pula cara hitung dan definisi komponen indikator — sehingga perubahan master data di masa depan tidak mendistorsi capaian yang telah disahkan.
5. Mencatat audit trail lengkap atas seluruh peristiwa penting (perubahan hak akses, perubahan Renstra/indikator, alur rencana aksi dan pengukuran, perubahan definisi komponen dan persyaratan berkas, koreksi setelah pengesahan, status capaian, rekomendasi Pimpinan, perubahan setelan aplikasi, percobaan penghapusan) untuk kebutuhan akuntabilitas dan pemeriksaan.
6. Menyediakan visibilitas capaian kinerja secara real-time (dashboard) bagi pimpinan tanpa perlu menunggu rekap manual.
7. Menyediakan jalur koreksi dan penambahan data yang jelas dan terkendali — baik untuk kesalahan yang ditemukan sebelum penutupan tahun, kebutuhan menambah indikator IKU baru di tengah tahun, maupun pengisian data historis (backfill) untuk tahun yang telah lampau.
8. Menyediakan mesin perhitungan indikator berbasis komponen yang data-driven, sehingga perubahan cara hitung akibat revisi Kepmen dapat dilakukan lewat pengelolaan definisi komponen di aplikasi, dalam batas satu tingkat perhitungan (§17.10), tanpa memerlukan perubahan kode.
9. Menautkan kegiatan operasional — beserta anggarannya (Q42) — ke rencana aksi/indikator secara eksplisit lewat klaim kegiatan, sehingga narasi capaian per pengukuran (progres kegiatan, kendala/masalah, tindak lanjut/strategi; Q45) tersaji di dalam sistem bersama daftar kegiatan yang mendukungnya, bukan laporan naratif yang disusun terpisah.

---

## 4. Prinsip Produk

1. Dashboard hanya menghitung data yang sudah **Disahkan** sebagai capaian resmi. Data berstatus Draft, Diajukan, Diverifikasi, atau Dikembalikan — baik pada Pengukuran maupun Rencana Aksi — tetap terlihat dalam konteks kerja (mis. daftar tugas verifikator), tapi tidak masuk ke ringkasan kinerja. Dashboard juga menampilkan status kegiatan per periode secara real-time dengan label "belum diverifikasi" (Q45, §23); tampilan itu bukan capaian resmi.
2. Pengukuran, Rencana Aksi, dan Kegiatan yang sudah memiliki nilai atau catatan tidak dihapus permanen. Perubahan status — termasuk pembatalan — direkam sebagai transisi status di audit log, bukan sebagai penghapusan baris. Kegiatan yang tidak terlaksana diberi status dan justifikasi, bukan dihapus (§15.2).
3. Snapshot membekukan konteks pengukuran, bukan menghapus fleksibilitas master data. Indikator, target, dan definisi komponen boleh terus dikembangkan dari tahun ke tahun; snapshot hanya memastikan pengukuran tahun berjalan tetap merujuk definisi, target, arah penilaian, dan cara hitung yang berlaku saat periode itu diaktifkan. Perubahan konteks resmi di tengah tahun hanya masuk lewat versi snapshot baru yang teraudit — koreksi (Q35) atau revisi PK berversi yang berlaku mulai periode pertama yang belum ditutup (Q40, §12.7) — dengan versi lama tetap utuh. Baris snapshot yang telah dirujuk oleh pengukuran bersifat abadi — tidak ada restatement data historis.
4. Hak akses memakai model RBAC hidup: peran (`roles`) memuat permission sebagai data (`role_permissions`) yang dievaluasi setiap permintaan, dilengkapi pemberian izin tambahan per unit (`user_permission_granted`) dan pencabutan izin eksplisit (`user_permission_denials`) yang menang atas segala pemberian izin — bukan role yang di-hardcode maupun salinan baris izin statis per pengguna. Mekanisme ini menjadi gate akses backend sejak awal, dievaluasi penuh di server (bukan di klien), meski UI pengelolaannya masih disederhanakan pada Fase Awal (§7). Pengecualian yang disengaja: permission pengisian pengukuran dan rencana aksi milik peran Perencanaan bersifat global (tanpa scope unit) karena bersumber dari isi peran yang selalu global, sebab Perencanaan bertindak sebagai penjaga integritas data lintas unit, bukan pemilik satu unit tertentu.
5. Beberapa alur — misalnya approval Pimpinan atas pengukuran — sengaja disederhanakan pada Fase Awal, tapi skema data tetap dirancang penuh dari awal supaya penambahan alur lanjutan nanti tidak membutuhkan migrasi data besar atau berisiko. Rekomendasi Pimpinan (§22) disusun sebagai draf oleh Perencanaan lalu disetujui Pimpinan — yang dapat mengubah isinya lebih dulu — atau ditulis langsung oleh Pimpinan; hanya rekomendasi berstatus disetujui yang berlaku, dan persetujuan oleh Perencanaan hanya jalur cadangan yang diberi penanda (Q45, Q51). Approval Pimpinan atas pengukuran tetap belum dibangun.
6. Setiap aksi tercatat di audit log (aktor, waktu, tindakan, nilai lama dan baru, serta `dasar_izin` untuk permission sensitif), tetapi alasan tertulis hanya diwajibkan bila memberi informasi yang tidak dapat dibaca dari data (Q43). Alasan **wajib** untuk: pengembalian dan buka-kembali (Rencana Aksi, pengukuran, jadwal); penghapusan data atau berkas (termasuk klaim, unit, dan indikator); koreksi atau revisi data yang sudah sah (koreksi PK, revisi PK, revisi target Renstra saat Renstra aktif, dan koreksi snapshot); perubahan akses (penetapan dan pergantian peran, penonaktifan dan pengaktifan kembali akun, pembatasan izin dan pencabutannya, serta pencabutan grant); serta justifikasi kegiatan `tidak_terlaksana`/`ditunda`/`batal`, alasan deviasi terhadap target PK, dan alasan target PK di bawah target Renstra. Kewajiban alasan lain dari keputusan sebelumnya yang tidak dibuat opsional oleh Q43 — mis. revisi atribut Renstra akibat Kepmen IKU baru (§10.5) dan perubahan definisi komponen indikator — tetap berlaku. Alasan **opsional** untuk: pemberian grant unit (server mengisi alasan baku bila kosong), perubahan target PK, perubahan persyaratan berkas, pindah unit indikator, aktivasi jadwal, perubahan setelan aplikasi, dan perubahan PK sebelum PK dipakai jadwal aktif. Kolom alasan pada penetapan dan pergantian Penanggung Jawab dihapus dari form.
7. Deadline pengisian pengukuran per periode bersifat mutlak bagi penanggung jawab (PIC) di unit, dan lewatnya `pengisian_selesai` suatu periode sekaligus mengunci target dan daftar kegiatan periode itu pada Rencana Aksi bagi PIC (Q41). Rencana Aksi tidak memiliki jendela penyusunan: PIC dapat menyusun, mengubah, dan mengajukannya sejak jadwal tahun tersebut aktif sampai `penutupan` (Q41, menggantikan jendela penyusunan rencana aksi). Perencanaan dikecualikan dari jendela pengisian maupun kunci periode sampai `penutupan` karena berperan menjaga kelengkapan dan kebenaran data sampai tahun ditutup.
8. Penghapusan bukan jalur koreksi. Kesalahan yang perlu dibetulkan — baik pada pengukuran, rencana aksi, jadwal, maupun master data — diselesaikan melalui transisi status, mekanisme buka kembali, atau baris riwayat baru; tidak pernah dengan menimpa/menghapus data lama secara diam-diam.
9. Nilai komponen adalah satuan input yang sebenarnya bagi indikator bertipe rasio/penjumlahan; skor akhir indikator untuk kedua tipe itu adalah **nilai turunan** hasil hitungan sistem, bukan angka yang diketik langsung — memindahkan risiko salah hitung manual ke satu tempat yang konsisten dan dapat diaudit, sekaligus menjaga rumus tetap sama antarperiode.
10. Pengajuan — baik rencana aksi maupun pengukuran — ditolak sistem bila prasyarat kelengkapan belum terpenuhi (rencana aksi disahkan, komponen terisi penuh kecuali realisasi berstatus belum dapat diukur (Q47), narasi pengukuran dan tindak lanjut atas rekomendasi yang disetujui terisi (Q45, Q51), bukti dukung wajib terpenuhi sesuai mode yang diizinkan; khusus rencana aksi juga target setiap periode terisi kecuali periode tanpa target pada indikator dinilai tahunan (Q47), minimal satu kegiatan, RAB untuk kegiatan beranggaran lebih dari 0, dan persyaratan tahap `kegiatan_perencanaan` (Q42)). Gerbang serupa berlaku bagi kegiatan sebelum dinyatakan terlaksana (§15.6). Gerbang-gerbang ini adalah kontrol kualitas data yang ditegakkan otomatis, bukan sekadar imbauan SOP — kecuali pada periode lampau (§12.6) yang sengaja dikecualikan dari seluruh gerbang tersebut, termasuk gerbang baru Q42–Q45 (Q46 §46.1 butir 1; kunci per periode Rencana Aksi bukan bagian pengecualian ini, §12.3), karena tujuannya memang mengisi data historis yang lazimnya tidak melalui proses normal pada masanya. Ketersediaan mode bukti dukung tidak memacetkan alur: sejak Q44 unggahan file selalu aktif, sehingga penanda `tidak_dapat_dipenuhi` akibat unggahan dinonaktifkan tidak lagi terbentuk; penanda yang sudah tercatat tetap menjadi histori (§18.7).
11. Klaim kegiatan adalah dokumentasi keterkaitan, bukan mesin hitung. Mengaitkan kegiatan ke rencana aksi/komponen tidak mengubah nilai komponen secara otomatis; nilai komponen tetap diisi manual oleh PIC untuk menghindari risiko penghitungan ganda (mis. satu pihak sasaran yang muncul di beberapa kegiatan tetap dihitung satu kali).

---

## 5. Ruang Lingkup

### 5.1 Termasuk (Fase Awal — MVP)

- Autentikasi via Keycloak (SSO existing LLDIKTI) menggunakan client baru khusus SAKIP.
- Katalog **lima role resmi** SAKIP: `superadmin`, `admin`, `perencanaan`, `pic`, `pimpinan`, dan `pegawai`; satu pengguna tetap memegang tepat satu role pada Fase Awal. Preset permission final PIC operasional (bukan role) mengikuti keputusan Q31 dan tetap OPEN sampai dikonfirmasi.
- Pengelolaan **Dasar Aturan** (`regulasi`): CRUD produk hukum (Kepmen/Permen/Perpres/keputusan lainnya) beserta lampiran dokumen sumbernya, dirujuk oleh Renstra dan/atau Indikator (§9).
- Manajemen Renstra (CRUD, siklus status draft/aktif/nonaktif/diarsipkan) beserta Sasaran dan Indikator turunannya, termasuk target Renstra per indikator per tahun Renstra sebagai pembanding (Q39), revisi Renstra akibat terbitnya Kepmen IKU baru (edit in place, teraudit; revisi target Renstra saat Renstra aktif wajib beralasan dan tampil sebagai riwayat revisi, Q40), rujukan `regulasi_id`, dan lampiran dokumen Renstra (lampiran revisi tetap dapat ditambahkan setelah Renstra aktif, Q40).
- Pengelolaan Perjanjian Kinerja (renstra_pk) per tahun per Renstra, termasuk isian target PK per indikator aktif dengan target Renstra sebagai pembanding (Q39) dan baseline sebagai data pendukung (Q50 §50.3), nomor PK opsional (Q50 §50.1), koreksi beralasan, lampiran dokumen PK — termasuk lampiran tambahan untuk PK perubahan yang hanya mengubah anggaran (Q50 §50.7) — dan revisi PK berversi (Q40; dijadwalkan pada tahap 3, Q46).
- Pengelolaan dua lapis target (Q39): **target Renstra** per indikator per tahun Renstra (diisi di Sasaran & Indikator, hanya pembanding) dan **target PK** (`target_tahunan`, disimpan dengan nilai penuh, Q50 §50.2) per indikator per tahun beserta kolom baseline sebagai acuan penyusunan, diinput Perencanaan di halaman Perjanjian Kinerja; PJ/PIC membaca target PK sebagai baca-saja.
- Kode sasaran dan indikator mengikuti PK (S1–S4, IKU 1.1–IKU 4.1; §2.4, Q50) dan penanda indikator **dinilai tahunan** (Q47).
- Pengelolaan definisi Komponen Indikator (`indikator_komponen`) dan mesin perhitungan otomatis berbasis komponen (`tipe_perhitungan`: rasio_persen, penjumlahan, manual) — pembilang majemuk berbobot, maksimal satu tingkat perhitungan.
- Pengelolaan Periode (mis. Triwulan I–IV, Semester, Tahunan), Jadwal Tahunan (level tahun; tanpa jendela penyusunan Rencana Aksi sejak Q41), dan Jadwal Periode (jendela pengisian/reviu per periode di dalam suatu jadwal tahunan), termasuk aktivasi jadwal yang memicu pembuatan snapshot otomatis dan idempoten (termasuk snapshot cara hitung dan definisi komponen).
- Mekanisme buka kembali jadwal (`jadwal:buka_kembali`) sebagai jalur standar untuk: koreksi setelah penutupan, penambahan indikator IKU baru di tengah tahun, dan pengisian periode lampau 2026.
- Penugasan dan riwayat Penanggung Jawab per indikator.
- Penyusunan dan pengesahan Rencana Aksi per indikator per tahun: target dipecah per periode per komponen, bersifat kumulatif, beserta daftar kegiatan per periode (Q42); target periode boleh kosong untuk indikator dinilai tahunan pada periode sebelum periode nilai akhir (Q47); dapat disusun sejak jadwal aktif sampai `penutupan` dengan kunci per periode bagi PIC (Q41); melalui alur Draft → Diajukan → Diverifikasi → Disahkan (dengan Dikembalikan sebagai jalur revisi dan `rencana_aksi:buka_kembali` sebagai jalur koreksi setelah pengesahan).
- Penyusunan Kegiatan per periode per unit (status rencana/terlaksana/tidak terlaksana/ditunda/batal, termasuk penggeseran periode; satu angka anggaran per kegiatan beserta RAB untuk kegiatan beranggaran lebih dari 0 dan penjumlahan anggaran per periode/Rencana Aksi/IKU, Q42; nama hingga 500 karakter dan tujuan opsional, Q49) dan Klaim Kegiatan yang mengaitkan kegiatan ke Rencana Aksi/komponen indikator, baik pada tahap penyusunan rencana aksi (kegiatan ditambahkan dari halaman Rencana Aksi, Q42, dan dapat disalin ke periode lain, Q49) maupun tahap pengisian pengukuran.
- Pengelolaan persyaratan Bukti Dukung (`jenis_berkas`) per tahap (rencana aksi, pengukuran, **kegiatan**, atau **`kegiatan_perencanaan`** — Q42), dengan tiga mode pemenuhan yang dapat dikombinasikan per persyaratan — file, tautan, dan teks — serta format dan ukuran maksimum per persyaratan yang ditetapkan Perencanaan (Q44), beserta gerbang kelengkapan sebelum pengajuan/perubahan status, dan penyimpanan bukti (`berkas`) pada enam jenis induk: Rencana Aksi, Pengukuran, Kegiatan, Renstra, Perjanjian Kinerja (`renstra_pk`), dan Dasar Aturan (`regulasi`); persyaratan juga dapat dilampiri contoh berkas melalui induk `jenis_berkas` (Q42), dan arsip PDF bertanda tangan dokumen resmi disimpan pada induk `jadwal_tahunan`/`jadwal_periode` (Q48).
- Alur pengukuran linear 4 status efektif pada Fase Awal: Draft → Diajukan → Diverifikasi → Disahkan (dengan opsi Dikembalikan sebagai jalur revisi), digerbangi kelengkapan rencana aksi, komponen, narasi pengukuran dan tindak lanjut atas rekomendasi yang disetujui (Q45, Q51), dan berkas wajib (§19.4), dengan realisasi yang dapat berstatus belum dapat diukur (Q47), termasuk jalur pengembalian khusus setelah pengesahan (`pengukuran:buka_kembali`) sebelum jadwal ditutup.
- Penetapan Status Capaian secara manual oleh Perencanaan/Superadmin; tidak tersedia untuk pengukuran pada periode tanpa target atau berstatus belum dapat diukur (Q47 §47.3 butir 4).
- Rekomendasi Pimpinan atas indikator × periode: draf disusun Perencanaan dan disetujui Pimpinan, atau ditulis langsung Pimpinan; persetujuan oleh Perencanaan sebagai cadangan diberi penanda (Q45, Q51). Dibangun bersama ISS-09.01 pada tahap 2 (Q46 §46.3, diperbarui Q51).
- Rekapitulasi indikator × periode yang menggabungkan target PK, target periode (rencana aksi), capaian atau selisih terhadap target PK (Q47), nilai tiap komponen, daftar kegiatan yang diklaim beserta status dan anggarannya (Q42), narasi pengukuran — progres kegiatan, kendala/masalah, tindak lanjut/strategi (Q45) —, dan rekomendasi Pimpinan yang disetujui (Q51) dalam satu tampilan/ekspor.
- Dashboard ringkas berbasis ApexCharts menampilkan capaian tersahkan, progres rencana aksi, status kegiatan real-time berlabel "belum diverifikasi" (Q45), total anggaran per IKU dengan drill-down sampai kegiatan (Q42), serta status "Tanpa target periode", "Belum dapat diukur", dan selisih terhadap target PK (Q47).
- Laporan tabular dengan ekspor ke Excel.
- Keluaran dokumen resmi **Rencana Aksi tahunan** dan **Laporan Kinerja Triwulan** dalam Excel dari data yang disahkan, beserta unggah kembali PDF bertanda tangan dengan permission `laporan:unggah` (Q48; US/ISS-10.05, tahap 2).
- Audit log penuh atas seluruh peristiwa yang disebutkan di §25, sejak hari pertama.
- Model hak akses RBAC (peran, katalog permission, isi peran, pemberian izin per unit, pencabutan izin) sebagai gate akses backend yang dievaluasi saat request dengan presedens deny menang, dengan UI pengelolaan yang disederhanakan (lihat §7.5).
- Alert kontekstual dasar (tenggat pengisian per periode, pengukuran/rencana aksi dikembalikan, kelengkapan berkas, dsb; tenggat penyusunan rencana aksi dihapus sejak Q41).
- Modul Setelan Aplikasi (§26): identitas instansi/aplikasi, label unit, preferensi tampilan/laporan — dikelola lewat tabel `pengaturan` key-value, terbatas hanya pada teks & preferensi presentasional. Menu Kebijakan Storage dan halaman "Batas unggahan berkas" dihapus (Q44): format dan ukuran maksimum per persyaratan ditetapkan Perencanaan di Persyaratan Berkas, dan batas teknis unggahan ditetapkan konfigurasi server (§18.9).
- Seed data pengembangan/testing (bukan data produksi) via seeder Laravel.

### 5.2 Tidak Termasuk / Ditunda ke Fase Lanjutan

- **Approval Pimpinan** dalam alur pengesahan pengukuran (kolom pendukung tersedia di skema, tetapi UI/alur belum dibangun) — Pimpinan tetap tidak masuk ke dalam alur rencana aksi maupun pengukuran pada Fase Awal; keterlibatannya terbatas pada pembacaan data serta penulisan dan persetujuan Rekomendasi Pimpinan (Q45, Q51, §22.2).
- **Formula perhitungan bertingkat** — mesin perhitungan Fase Awal hanya mendukung satu tingkat perhitungan (rasio atau penjumlahan komponen, dengan pembilang majemuk dan berbobot, §17.10). Formula yang menggabungkan beberapa sub-skor menjadi skor komposit (mis. Nilai SAKIP dan Nilai Zona Integritas yang seharusnya diturunkan dari sub-skor berbobot) tidak dibangun otomatis; indikator semacam ini memakai `tipe_perhitungan = manual`.
- **Ekspor PDF** rekap laporan dan **ekspor gambar grafik** dashboard (Fase Awal hanya ekspor tabel ke Excel). Keluaran dokumen resmi Q48 juga dibuat dalam Excel; PDF bertanda tangan yang dibuat di luar aplikasi tetap dapat diunggah kembali sebagai arsip (§24.1, Q48).
- **Pengisian otomatis status capaian dari sistem lain** (data_sumber) — Fase Awal seluruh status capaian diisi manual.
- **UI matrix permission penuh** (mencentang seluruh katalog permission per pengguna) — Fase Awal hanya tiga form terbatas (assign peran, kelola grant izin per unit, kelola deny izin) beserta halaman "Jelaskan izin pengguna".
- **Impor data massal** (dari Excel/sistem lain) — di luar cakupan pada seluruh fase yang direncanakan saat ini, kecuali diputuskan lain di kemudian hari.
- **Revisi target tahunan PK di tengah tahun** *(digantikan Q40)* — semula ditunda karena revisi target tahunan hanya efektif di antara tahun. Sejak Q40, PK dapat direvisi sebagai versi PK baru yang berlaku mulai periode pertama yang belum ditutup (§12.7) dan dijadwalkan pada tahap 3 (Q46), sehingga butir ini tidak lagi ditunda. Jalur normal untuk target tidak tercapai tetap justifikasi tertulis, bukan revisi target; target periode (komponen) pada Rencana Aksi tetap direvisi lewat `rencana_aksi:buka_kembali` (§14.6) untuk periode yang belum terkunci (Q41).
- **Modul anggaran kegiatan penuh** *(sebagian digantikan Q42)* — input, tampilan, dan penjumlahan satu angka anggaran per kegiatan beserta RAB kini termasuk cakupan (§15.1). Yang tetap tidak dikelola: rincian realisasi belanja, pagu induk, rekonsiliasi anggaran, template RAB buatan aplikasi, serta struktur anggaran DIPA — tabel Rincian Output pada Rencana Aksi dan realisasi anggaran per Rincian Output pada laporan triwulan tetap disusun di luar SAKIP (Q48 §48.1 butir 3, §2.4).
- **Pembuatan dokumen Perjanjian Kinerja** — tidak dihasilkan SAKIP karena ditandatangani bersama Direktur Jenderal; SAKIP mencatat isi target dan lampirannya (Q48 §48.1 butir 4, §14.10).
- **Versi penuh Renstra** (sasaran dan indikator ikut berversi) — tidak dibangun saat ini; penggantian Renstra karena kebijakan kementerian ditangani dengan Renstra baru (Q40, §10.5).
- **Usulan target PK oleh PJ di dalam aplikasi** — tidak dibangun; target PK diinput Perencanaan setelah angka disepakati dengan tim kerja di luar aplikasi (Q39, §11).
- **Tampilan daftar berkas terunggah** (semacam file manager) — backlog tahap 3 (Q44 §44.1 butir 3, Q46 §46.3, §18.9).
- Integrasi dengan sistem informasi lain milik LLDIKTI (tidak dibahas dalam dokumen ini).
- Detail repository/deployment/infrastruktur VPS — dibahas dalam dokumen tersendiri.

---

## 6. Arsitektur & Stack Teknis

| Komponen | Pilihan | Catatan |
|---|---|---|
| Backend framework | **Laravel 13** | Struktur MVC standar; routing, controller, validasi, dan otorisasi berada di sisi Laravel sebagai aplikasi monolith |
| Frontend | **Inertia 3 + React 19 + TypeScript + Tailwind 4** (+ komponen shadcn/ui) | Halaman React berada di `resources/js/pages`; routing & controller tetap milik Laravel, **tanpa API terpisah**. Interaktivitas (form, tabel, modal, unggah berkas, perhitungan nilai komponen secara langsung di layar) ditangani komponen React |
| Basis data | **PostgreSQL** | Digunakan sebagai satu-satunya penyimpanan relasional aplikasi |
| Testing | **Pest** (backend) + **Vitest + React Testing Library** (komponen React) | Pest untuk unit & feature test sisi Laravel; Vitest + React Testing Library untuk komponen React yang memuat logika tampilan (perhitungan komponen di layar, filter, validasi klien). Setiap kriteria penerimaan pada §32 idealnya punya test terkait |
| Chart library | **ApexCharts** (via `react-apexcharts`) | Dirender di sisi klien untuk dashboard visualisasi (§23) |
| Autentikasi | **Keycloak** (instance existing LLDIKTI) via **client_id baru khusus SAKIP** | Integrasi memakai **Laravel Socialite** + paket **SocialiteProviders/Keycloak**; pola **OAuth2/OIDC Authorization Code Flow berbasis session** — bukan pola JWT bearer/API guard (mis. `laravel-keycloak-guard`) |
| Deployment | VPS existing LLDIKTI | Detail konfigurasi, domain, dan proses rilis dibahas pada dokumen terpisah, tidak termasuk cakupan PRD ini |

Prinsip arsitektural:

- SAKIP adalah proyek baru yang terpisah secara kode maupun basis data dari sistem informasi LLDIKTI lain yang sudah berjalan — tidak ada tabel atau skema yang dibagi dengan sistem lama.
- Seluruh logika otorisasi (permission gate) dan gerbang kelengkapan (rencana aksi, komponen, berkas) divalidasi di sisi server (Form Request, Policy, dan service layer) — bukan hanya disembunyikan di UI. Halaman React tidak memegang aturan bisnis: ia mengirim permintaan ke endpoint Laravel, sehingga gerbang tidak dapat dilewati dengan memodifikasi tampilan. Konsistensi inilah alasan pemakaian Inertia (routing & controller tetap sisi server) alih-alih API terpisah.
- Sesi login mengikuti siklus OIDC standar: redirect ke Keycloak → callback → pembuatan/pemetaan record `users` lokal berbasis `keycloak_id` → sesi Laravel dibentuk. SAKIP sendiri tidak menyimpan password. Kustomisasi tampilan halaman login (branding, logo) berada di level realm Keycloak dan di luar cakupan aplikasi SAKIP — lihat juga §26.
- Berkas yang diunggah disimpan di disk VPS, diakses lewat route ber-permission (streamed download), bukan URL publik statis — lihat §18.9.

---

## 7. Hak Akses: Peran, Permission, Grant, dan Deny

### 7.1 Model Dasar

SAKIP memakai **RBAC + explicit grant + explicit deny** yang dievaluasi pada setiap request. Baseline final LLDIKTI 24 September 2026:

1. `permissions` — katalog permission source-controlled dan di-seed.
2. `roles` — **5 role bawaan**: `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`.
3. `role_permissions` — preset permission global per role; **tidak memiliki `unit_id`**.
4. `user_roles` — zero-or-one role utama per user pada Fase Awal. User baru hasil JIT SSO boleh belum memiliki baris role sampai Admin menetapkannya.
5. `user_permission_granted` — grant eksplisit, termasuk grant per unit untuk hak kerja operasional.
6. `user_permission_denials` — deny eksplisit global/per-unit; deny selalu menang.

**PIC bukan role sistem.** Istilah PIC pada workflow berarti pengguna operasional yang ditugaskan dan memiliki hak kerja yang sesuai. Role, assignment `penanggung_jawab`, dan grant unit adalah tiga data yang berbeda.

### 7.2 Katalog Permission dan Scope

Katalog permission didefinisikan sebagai konstanta aplikasi dan disinkronkan melalui seeder. Permission tidak dikenal/tidak aktif harus fail closed.

Hanya **7 permission** yang `butuh_scope = unit`:

```text
pengukuran:create
pengukuran:update
rencana_aksi:create
rencana_aksi:update
rencana_aksi:ajukan
kegiatan:create
kegiatan:update
```

Permission baca `pengukuran:read`, `rencana_aksi:read`, `kegiatan:read`, `komponen:read`, `jenis_berkas:read`, `regulasi:read`, dan `dashboard:read` bersifat global sesuai preset role yang berlaku.

Tambahan permission governance:

- `delegasi:update` — mengelola create/revoke Grant Unit; preset: Perencanaan, Admin, Superadmin.
- `akses:update` — Assign Peran dan Explicit Deny.
- `pengguna:read` — melihat pengguna serta halaman **Peran & Izin** read-only.

| Entitas | Aksi utama |
|---|---|
| Renstra | `renstra:create`, `renstra:read`, `renstra:update`, `renstra:delete` |
| Sasaran | `sasaran:create`, `sasaran:update`, `sasaran:delete` |
| Indikator | `indikator:create`, `indikator:read`, `indikator:update`, `indikator:delete` |
| Target | `target:update` |
| PK | `pk:create`, `pk:update` |
| Regulasi | `regulasi:create`, `regulasi:read`, `regulasi:update`, `regulasi:delete` |
| Periode/Jadwal | `periode:create`, `periode:update`, `jadwal:create`, `jadwal:update`, `jadwal:aktivasi`, `jadwal:tutup`, `jadwal:buka_kembali` |
| Penanggung Jawab | `penanggung_jawab:update` |
| Rencana Aksi | `rencana_aksi:read`, `rencana_aksi:create`, `rencana_aksi:update`, `rencana_aksi:ajukan`, `rencana_aksi:verifikasi`, `rencana_aksi:kembalikan`, `rencana_aksi:sahkan`, `rencana_aksi:buka_kembali` |
| Kegiatan | `kegiatan:read`, `kegiatan:create`, `kegiatan:update`, `kegiatan:delete` |
| Komponen | `komponen:create`, `komponen:read`, `komponen:update`, `komponen:delete` |
| Jenis/Berkas | `jenis_berkas:create`, `jenis_berkas:read`, `jenis_berkas:update`, `jenis_berkas:delete`, `berkas:read`, `berkas:upload`, `berkas:delete` |
| Pengukuran | `pengukuran:create`, `pengukuran:update`, `pengukuran:read`, `pengukuran:verifikasi`, `pengukuran:kembalikan`, `pengukuran:sahkan`, `pengukuran:buka_kembali`, `pengukuran:setujui` |
| Status/Rekomendasi | `status_capaian:update`, `rekomendasi:tetapkan` |
| Unit | `unit:create`, `unit:read`, `unit:update`, `unit:delete` |
| Pengguna/Akses | `pengguna:read`, `akses:update`, `delegasi:update` |
| Dashboard/Laporan | `dashboard:read`, `laporan:read`, `laporan:ekspor`, `laporan:unggah` |
| Audit/Pengaturan | `audit:read`, `pengaturan:update` |

Catatan Q39–Q51:

- `laporan:unggah` **(baru, Q48 §48.2 butir 2)** — mengunggah PDF bertanda tangan Rencana Aksi tahunan dan Laporan Kinerja Triwulan; preset Perencanaan dan Superadmin. Permission ini **tidak bertanda sensitif** karena unggahan bersifat append-only dan tidak mengubah data; unggahan tetap tercatat di audit (§25.1). Pembuatan keluaran dan unduhan arsipnya memakai `laporan:ekspor` (preset Perencanaan, Pimpinan, dan Superadmin; Q48 §48.1 butir 6). Lampiran PK tetap mengikuti permission PK.
- `rekomendasi:tetapkan` dipegang preset Pimpinan dan Perencanaan; simpan draf dan setujui dibedakan oleh status rekomendasi, bukan oleh permission (Q45 §45.2, Q51 §51.2).
- Target Renstra dan target PK sama-sama disimpan dengan `target:update`; tidak ada permission baru untuk target (Q39 §39.3 butir 3). Penanda indikator dinilai tahunan (Q47 §47.1) diubah dengan `indikator:update` (US/ISS-05.07).

### 7.3 Isi Peran (`role_permissions`)

Preset role adalah **source-controlled**. Tidak ada UI yang mengubah isi role, termasuk untuk Superadmin.

- perubahan preset dilakukan melalui rilis kode + seeder;
- seeder harus idempoten;
- bila ada delta, audit menyimpan daftar before/after dan alasan/sumber rilis;
- permission unit-scoped tidak boleh dijadikan role-global untuk menggantikan Grant Unit.

Baseline role:

| Peran | Baseline kewenangan |
|---|---|
| **Superadmin** | Akses teknis penuh/break-glass sesuai katalog; seluruh aksi tetap tunduk audit dan business guard yang tidak dapat dibypass hanya karena role. |
| **Admin** | Pengelolaan teknis pengguna/unit/setelan/akses sesuai permission preset; tidak memperoleh kewenangan substantif kinerja secara otomatis. Memiliki `delegasi:update` untuk administrasi grant unit. |
| **Perencanaan** | Kewenangan substantif perencanaan, jadwal, review/pengesahan sesuai katalog, termasuk `delegasi:update` dan `laporan:unggah` (Q48); `rekomendasi:tetapkan` dipakai untuk menyusun draf Rekomendasi Pimpinan dan persetujuan cadangan bertanda (Q51); permission kerja yang berasal dari role bersifat global. |
| **Pimpinan** | Baseline read-only/monitoring sesuai permission preset, ditambah `rekomendasi:tetapkan` untuk menulis dan menyetujui Rekomendasi Pimpinan (Q45, Q51); approval fase lanjutan tidak dianggap aktif hanya karena role. |
| **Pegawai** | Hak baca: `pengukuran:read`, `rencana_aksi:read`, `kegiatan:read`, `komponen:read`, `jenis_berkas:read`, `regulasi:read`, `dashboard:read`; akses berkas mengikuti ownership/parent authorization. Hak kerja unit tidak berasal dari role Pegawai, melainkan Grant Unit. |

### 7.4 Algoritma Resolusi Izin

Resolver server-side menerima user, permission, dan unit opsional:

1. permission harus dikenal dan aktif;
2. user harus terautentikasi dan, untuk route aplikasi aktif, berstatus `aktif`;
3. kumpulkan allow global dari `role_permissions` bila user memiliki role;
4. kumpulkan grant yang cocok;
5. evaluasi explicit deny — **deny menang**;
6. untuk permission unit-scoped, unit request harus cocok dengan grant yang sah kecuali allow global memang berasal dari role yang secara eksplisit memiliki kewenangan global;
7. setelah permission lolos, jalankan business guard: status data, assignment, jendela waktu, F1/F2, penutupan, dan rule domain lain.

User tanpa role menghasilkan **nol permission** (fail closed).

### 7.5 Grant Unit dan PIC Operasional

PIC operasional dibentuk oleh kombinasi konteks bisnis, bukan role baru:

```text
user aktif
+ assignment penanggung_jawab bila relevan
+ grant unit yang diperlukan
+ jendela/status/business guard valid
```

Grant unit hanya menerima 7 permission unit-scoped pada §7.2. Form Grant digerbangi `delegasi:update` dan tersedia untuk Perencanaan, Admin, Superadmin sesuai permission efektif. Grant tidak mengubah role dan tidak otomatis membuat assignment PJ. Alasan pemberian grant bersifat opsional: kolom alasan tetap `NOT NULL` dan server mengisi alasan baku bila pengguna mengosongkannya (Q43); pencabutan grant tetap wajib beralasan (§7.6).

### 7.6 Penanggung Jawab dan Role

`penanggung_jawab` adalah assignment historis user terhadap indikator. User aktif mana pun dapat ditunjuk; assignment tidak memberi permission.

Jika calon PJ belum mempunyai hak isi yang cocok, UI memberi **peringatan, bukan blokir**. Sistem menyediakan daftar **PJ aktif tanpa hak isi** untuk ditindaklanjuti Perencanaan.

Perubahan role tidak mengakhiri assignment PJ dan tidak mencabut grant. Pencabutan grant harus eksplisit dan beralasan.

### 7.7 UI Pengelolaan Akses

Fase Awal menyediakan:

1. Assign Peran — 5 role final; gate `pengguna:read` + `akses:update`.
2. Grant Unit — gate `delegasi:update`; alasan pemberian opsional (server mengisi alasan baku bila kosong), sedangkan pencabutan tetap wajib beralasan (Q43).
3. Explicit Deny — gate `akses:update`.
4. Jelaskan Izin Pengguna — read/explain effective permission.
5. **Peran & Izin** — read-only; gate `pengguna:read`.

Tidak ada editor `role_permissions` melalui browser.

### 7.8 Unit

Unit adalah scope organisasi untuk indikator, kegiatan, grant, deny, laporan, dan business guard. Tidak boleh membuat role per-unit seperti `pic_akademik`; cakupan unit tetap direpresentasikan pada grant dan data domain.

## 8. Struktur Informasi

Hierarki informasi utama dalam SAKIP, dari yang paling strategis ke paling operasional:

1. **Renstra** — payung strategis 1 (satu) rentang tahun (mis. 2025–2029), berisi banyak Sasaran.
2. **Sasaran** — tujuan strategis di bawah Renstra, memiliki urutan tampil, berisi banyak Indikator; berkode S1–S4 mengikuti PK (Q50 §50.4).
3. **Indikator** — unit ukur kinerja konkret, dimiliki oleh tepat satu Unit, berkode IKU 1.1–IKU 4.1 mengikuti PK dengan nomor IKU Renstra sebagai keterangan (Q50 §50.4, §2.4), memiliki arah penilaian (`arah`), cara hitung (`tipe_perhitungan`), penanda dinilai tahunan (Q47), rujukan dasar aturan (`regulasi_id`), target Renstra per tahun Renstra sebagai pembanding (Q39), Penanggung Jawab (riwayat), dan — bila bukan `manual` — daftar Komponen Indikator yang menyusun rumus perhitungannya.
4. **Perjanjian Kinerja (PK)** — dokumen resmi per tahun per Renstra yang mengesahkan target-target tahun tersebut: memuat target PK per indikator aktif (disimpan di `target_tahunan` dengan nilai penuh, Q39, Q50 §50.2), dengan baseline ditampilkan di halaman PK sebagai data pendukung, bukan isi dokumen PK (Q50 §50.3); nomor PK opsional (Q50 §50.1); dapat dilampiri dokumen pendukung, dan dapat direvisi sebagai versi PK baru (Q40).
5. **Dasar Aturan (`regulasi`)** — produk hukum (Kepmen/Permen/Perpres/keputusan lainnya) yang menjadi acuan penyusunan Renstra dan/atau indikator, dirujuk lewat `renstra.regulasi_id`/`indikator.regulasi_id`, dan dapat dilampiri dokumen sumbernya.
6. **Jadwal Tahunan** — bingkai tahun tertentu dalam satu Renstra, memuat status siklus dan penutupan (tanpa jendela penyusunan Rencana Aksi, Q41); saat diaktifkan menghasilkan Snapshot Jadwal.
7. **Jadwal Periode** — daftar periode yang diharapkan pada tahun tersebut beserta jendela pengisian dan reviu masing-masing (mis. Triwulan I–IV punya jendela berbeda-beda dalam satu jadwal tahunan yang sama).
8. **Snapshot Jadwal** — salinan beku konteks indikator (nama, definisi, satuan, presisi, target PK, arah, cara hitung, penanda dinilai tahunan (Q47), dan definisi komponen) pada saat jadwal diaktifkan; menjadi rujukan Pengukuran, bukan master langsung.
9. **Rencana Aksi** — rencana capaian per indikator per tahun yang memuat target per periode per komponen (kumulatif) dan daftar kegiatan per periode (Q42); target periode boleh kosong untuk indikator dinilai tahunan pada periode sebelum periode nilai akhir (Q47); dapat disusun sejak jadwal aktif sampai `penutupan` dengan kunci per periode bagi PIC (Q41), dan harus disahkan sebelum pengukuran diajukan.
10. **Kegiatan** — aktivitas per unit per periode yang dilaksanakan untuk mendukung capaian indikator, mencatat satu angka anggaran (rupiah) dan RAB bila anggaran lebih dari 0 (Q42), dengan nama hingga 500 karakter dan tujuan opsional (Q49); dapat diklaim ke satu atau lebih Rencana Aksi/komponen lewat Klaim Kegiatan.
11. **Pengukuran** — nilai capaian aktual per Indikator, per Tahun, per Periode, yang melalui alur status dan memuat narasi wajib progres kegiatan, kendala/masalah, serta tindak lanjut/strategi (Q45); realisasinya dapat berstatus belum dapat diukur (Q47); untuk indikator berkomponen, nilai capaian diturunkan dari nilai komponen (Pengukuran Komponen).
12. **Berkas** — bukti dukung (mode file, tautan, atau teks) yang menempel pada enam jenis induk: Rencana Aksi, Pengukuran, Kegiatan, Renstra, Perjanjian Kinerja, atau Dasar Aturan, sesuai persyaratan (Jenis Berkas) yang ditetapkan Perencanaan untuk tiga induk pertama, atau sebagai lampiran bebas untuk ketiga induk lainnya. Persyaratan juga dapat dilampiri contoh berkas (mis. contoh RAB) melalui induk `jenis_berkas` (Q42), dan arsip PDF bertanda tangan dokumen resmi disimpan pada induk `jadwal_tahunan`/`jadwal_periode` (Q48).
13. **Status Capaian** — penilaian akhir (Tercapai/Belum Tercapai) atas suatu Pengukuran; tidak tersedia untuk pengukuran pada periode tanpa target atau berstatus belum dapat diukur (Q47 §47.3 butir 4).
14. **Rekomendasi Pimpinan** — catatan evaluasi atas indikator × periode; draf disusun Perencanaan dan disetujui Pimpinan, atau ditulis langsung Pimpinan; hanya rekomendasi disetujui yang berlaku (Q45, Q51).
15. **Dokumen resmi** — Rencana Aksi tahunan (per jadwal tahunan) dan Laporan Kinerja Triwulan (per periode) yang dihasilkan SAKIP dari data yang disahkan, beserta arsip PDF bertanda tangannya (Q48).

---

## 9. Dasar Aturan (Regulasi)

### 9.1 Filosofi

Dasar hukum yang melandasi Renstra dan indikator dicatat sebagai **entitas terstruktur**, bukan sekadar teks bebas pada kolom `renstra.dasar_hukum`. Tujuannya: (a) satu produk hukum (mis. Kepmendiktisaintek 358/M/KEP/2025) cukup dicatat sekali dan dirujuk berulang oleh Renstra maupun oleh banyak indikator, tanpa mengetik ulang nomor/tahun/tautan sumbernya; (b) dokumen sumber (salinan PDF/tautan resmi) dapat dilampirkan langsung pada baris dasar aturan, bukan tersebar di berkas lepas; (c) penelusuran "indikator ini berdasar produk hukum yang mana" menjadi query langsung, bukan pencarian teks.

### 9.2 Tabel `regulasi`

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `jenis` | enum(`kepmen`, `permen`, `perpres`, `keputusan_lainnya`) | not null | Jenis produk hukum |
| `nomor` | varchar | not null | Nomor dokumen |
| `tahun` | int | not null | Tahun penetapan |
| `tentang` | text | not null | Pokok pengaturan |
| `tanggal` | date | nullable | Tanggal penetapan |
| `tautan_sumber` | varchar(2048) | nullable | Sumber resmi (mis. JDIH atau laman kementerian) |
| `catatan` | text | nullable | |
| `aktif` | boolean | not null, default `true` | |
| `created_by` | uuid | FK → users.id | |
| `created_at`, `updated_at` | timestamp | not null | |

Constraint: **`unique(jenis, nomor, tahun)`** — satu produk hukum tidak tercatat berulang untuk kombinasi jenis/nomor/tahun yang sama.

### 9.3 Rujukan dari Renstra dan Indikator

- **`renstra.regulasi_id`** (FK → regulasi, nullable) — rujukan terstruktur ke dasar aturan penyusunan Renstra. Kolom `renstra.dasar_hukum` (text, wajib diisi sebelum Renstra dapat diaktifkan, §10.2) **tetap ada** sebagai ringkasan yang dibaca cepat pada layar Renstra — kedua kolom saling melengkapi, bukan saling menggantikan: `dasar_hukum` untuk keterbacaan cepat, `regulasi_id` untuk ketertelusuran terstruktur dan lampiran dokumen.
- **`indikator.regulasi_id`** (FK → regulasi, nullable) — dasar aturan per indikator kinerja (mis. produk hukum yang secara spesifik menetapkan IKU tersebut), sehingga dasar hukum dapat ditelusuri per indikator tanpa mengunggah dokumen yang sama berulang kali untuk tiap indikator yang bersumber dari produk hukum yang sama.

### 9.4 Lampiran Dokumen Dasar Aturan

Baris `regulasi` dapat dilampiri dokumen sumber lewat entitas `berkas` (§18.4) dengan `berkasable_type = regulasi` — mode file, tautan, atau teks, sama seperti induk lain. Lampiran regulasi tidak digerbangi oleh gerbang kelengkapan mana pun (bukan persyaratan bergerbang, tanpa keterkaitan ke `jenis_berkas`) — sifatnya arsip dokumen sumber, dilampirkan bebas oleh pemegang `regulasi:create`/`update`.

### 9.5 Imutabilitas Lampiran Regulasi

Lampiran `regulasi` tidak dapat dihapus selama regulasi yang bersangkutan masih dirujuk oleh Renstra atau indikator berstatus **aktif** (§18.8) — mencegah hilangnya dokumen sumber yang sedang menjadi rujukan aktif. Setelah regulasi tidak lagi dirujuk oleh Renstra/indikator aktif mana pun (mis. Renstra sudah diarsipkan, atau indikator sudah diganti rujukannya), lampiran dapat dihapus oleh pemegang `regulasi:delete`, tercatat di `audit_log`.

### 9.6 Halaman "Dasar Aturan"

Halaman kerja tersendiri untuk CRUD `regulasi` beserta lampirannya: daftar produk hukum (jenis, nomor, tahun, tentang, status aktif), formulir tambah/ubah, dan panel lampiran dokumen. Menu Dasar Aturan berada pada grup **Master Data & Referensi** karena dirujuk seluruh modul (Q44); permission tidak berubah dan menu tetap tampil berdasarkan `can.*` dari server. Halaman Renstra menampilkan rujukan `regulasi_id` beserta ringkasan `dasar_hukum` dan lampiran dokumen Renstra (§10.6); halaman Perjanjian Kinerja menampilkan lampiran dokumen PK beserta `nomor_pk`/`tanggal_pk` (§14.10; `nomor_pk` opsional, Q50 §50.1).

### 9.7 Hak Akses

Permission **`regulasi:create`**, **`regulasi:read`**, **`regulasi:update`**, **`regulasi:delete`** (`update` dan `delete` bertanda `sensitif = true`, §7.2). Isi peran (§7.3): **Perencanaan** dan **Superadmin** memegang keempat aksi; **Admin**, **Pimpinan**, dan **Pegawai** memegang **`regulasi:read`** saja — dasar aturan tampil di halaman Renstra/indikator agar konteksnya terbaca oleh siapa pun yang berhak membuka halaman itu, tanpa memberi wewenang substantif kepada Admin atas isi dasar aturan. Hak bawaan role **PIC** atas `regulasi:read` tetap **OPEN** mengikuti penetapan preset permission Q31.

### 9.8 Audit

Pembuatan, perubahan, dan penghapusan `regulasi` (`nilai_lama`/`nilai_baru` + `alasan` untuk `update`/`delete`), perubahan `renstra.regulasi_id`/`indikator.regulasi_id`, serta pengiriman/penghapusan lampiran dokumen dasar aturan seluruhnya tercatat di `audit_log` (§25.1).

---

## 10. Siklus Renstra & Validasi Aktivasi

### 10.1 Status Renstra

`draft → aktif → nonaktif/diarsipkan`

- **Draft**: Renstra sedang disusun (Sasaran/Indikator dapat bebas diubah), belum mengikat jadwal operasional.
- **Aktif**: Renstra berlaku dan dapat dijadikan rujukan jadwal tahunan/rencana aksi/pengukuran.
- **Nonaktif**: Renstra tidak lagi menerima jadwal baru, namun data historis tetap dapat dibaca.
- **Diarsipkan**: status akhir, hanya untuk keperluan riwayat/audit.

### 10.2 Validasi Sebelum Aktivasi

Sebelum Renstra dapat dipindahkan ke status `aktif`, sistem wajib memvalidasi:

- `dasar_hukum` (teks rujukan regulasi) telah diisi — wajib, tidak boleh kosong. Rujukan terstruktur `regulasi_id` (§9.3) bersifat opsional pada validasi aktivasi — dianjurkan diisi, tetapi tidak memblokir aktivasi bila belum ada baris `regulasi` yang sesuai tercatat.
- Tidak ada Renstra `aktif` lain dengan rentang tahun yang beririsan (constraint pada level aplikasi dan/atau basis data).
- Renstra memiliki minimal satu Sasaran, dan tiap Sasaran idealnya memiliki minimal satu Indikator (divalidasi sebagai peringatan kuat, ditegaskan dalam SOP operasional Perencanaan).

### 10.3 Mengapa Validasi Ini Kritis

Fase Awal menerapkan jadwal_snapshot penuh (§12.5): aktivasi jadwal tahunan yang merujuk suatu Renstra membekukan kondisi master data indikator — termasuk definisi komponen dan cara hitungnya — saat itu juga ke dalam snapshot. Kesalahan pada Renstra/indikator/komponen — nama salah ketik, satuan keliru, target belum diisi, definisi komponen belum lengkap — yang belum diperbaiki sebelum jadwal diaktifkan ikut terbekukan dan sulit dikoreksi tanpa prosedur ulang (§34). Validasi kelengkapan Renstra sebelum aktivasi jadwal perlu ditegakkan sebagai gerbang kualitas yang ketat, bukan sekadar formalitas administratif.

### 10.4 Larangan Nonaktivasi Selama Jadwal Aktif

Renstra **tidak dapat dinonaktifkan** selama masih ada `jadwal_tahunan` berstatus `aktif` yang merujuknya. Jadwal aktif itu harus ditutup (`jadwal:tutup`) terlebih dahulu sebelum Renstra dapat dipindah ke status `nonaktif`. Aturan ini mencegah situasi ambigu di mana pengukuran sedang berjalan di bawah Renstra yang secara status sudah dianggap tidak berlaku.

### 10.5 Revisi Renstra Akibat Kepmen IKU Baru

Ketika Kepmendiktisaintek yang menjadi dasar Renstra direvisi (mis. daftar IKU berubah), penyesuaian dilakukan sebagai berikut, bukan dengan membuat Renstra baru:

- **Edit in place** pada baris `renstra` yang sama — nama, dasar_hukum, `regulasi_id`, atau atribut lain diperbarui langsung. Perubahan tercatat di `audit_log` (`nilai_lama`/`nilai_baru`) dengan `alasan` memuat nomor dan tanggal Kepmen yang menjadi dasar revisi; bila produk hukum baru belum tercatat di `regulasi`, Perencanaan mencatatnya lebih dulu lewat halaman Dasar Aturan (§9.6) sebelum menautkannya.
- **IKU yang dihapus dari Kepmen** → indikator terkait di-**arsipkan** (`indikator.status = arsip`), **bukan dihapus**. Data pengukuran lama tetap utuh dan tetap muncul di laporan historis, dengan penanda status arsip (lihat §11).
- **IKU baru** → dibuatkan indikator baru beserta definisi komponen (bila bertipe rasio/penjumlahan), target PK (`target_tahunan`) tahun berjalan yang diisi di halaman PK — dengan keterangan "target Renstra belum ada" bila indikator belum memiliki target Renstra (Q39) — dan revisi PK yang relevan (revisi berversi, §12.7, Q40), lalu dimasukkan ke jadwal tahun berjalan melalui `jadwal:buka_kembali` (§12.6) — ini adalah mekanisme standar penambahan indikator di tengah tahun, termasuk untuk mengisi periode yang tersisa pada tahun tersebut. Indikator yang masuk lewat jalur ini tetap memerlukan penyusunan dan pengesahan Rencana Aksi tersendiri (§14) sebelum pengukuran periode-periode yang tersisa dapat diajukan.
- Indikator baru yang dibuat setelah jadwal aktif namun **tanpa** melewati `jadwal:buka_kembali` baru terukur mulai jadwal tahun berikutnya — ini adalah konsekuensi desain yang disadari, bukan celah.
- **Target Renstra** (§11, Q39) yang diubah saat Renstra berstatus `aktif` wajib beralasan (dasar revisi) dan tercatat di `audit_log` dengan nilai lama dan baru; catatan tersebut ditampilkan sebagai riwayat revisi target Renstra. Bila target terlampaui sebelum akhir periode Renstra, target tahun-tahun berikutnya dinaikkan dengan cara ini (Q40).
- **Versi penuh Renstra** (sasaran dan indikator ikut berversi) tidak dibangun; atribut Renstra tetap direvisi in place seperti di atas. Penggantian Renstra secara keseluruhan karena kebijakan kementerian ditangani dengan Renstra baru, dan Renstra lama dinonaktifkan sesuai aturan yang berlaku (§10.4) (Q40).

### 10.6 Lampiran Dokumen Renstra

Halaman Renstra menampilkan panel lampiran dokumen (`berkas` dengan `berkasable_type = renstra`, §18.4) — dokumen pendukung penyusunan Renstra (mis. salinan Kepmen, naskah akademik, hasil rapat penetapan), mode file/tautan/teks. **Lampiran renstra tidak dapat dihapus setelah Renstra berstatus `aktif`** (§18.8) — sebelum status itu tercapai, Perencanaan/pengunggah dapat menghapus lampiran; penghapusan tercatat di `audit_log`. **Lampiran revisi tetap dapat ditambahkan setelah Renstra `aktif`** (Q40); larangan sejak status itu hanya berlaku untuk penghapusan. Lampiran revisi hanya dapat ditambahkan pada Renstra berstatus `draft` atau `aktif`; Renstra `nonaktif` atau diarsipkan tidak menerima lampiran baru (Q40 §40.2 butir 5).

---

## 11. Pengelolaan Indikator

- Setiap Indikator dimiliki oleh tepat satu Unit (`indikator.unit_id NOT NULL`) — menentukan siapa yang berhak mengajukan permission `pengukuran:create`/`update`, `rencana_aksi:create`/`update`/`ajukan` untuk indikator tersebut.
- Atribut kunci: `nama`, `definisi`, `satuan`, `presisi` (jumlah digit desimal disimpan), `desimal_tampilan` (jumlah digit desimal ditampilkan — dapat berbeda dari presisi penyimpanan untuk keperluan pembulatan tampilan), `tahun_mulai_berlaku`, `arah`, `tipe_perhitungan`, **`regulasi_id`** (FK → regulasi, nullable — dasar aturan spesifik indikator ini, §9.3).
- **Kode sasaran dan indikator (Q50 §50.4):** kode mengikuti PK, Rencana Aksi, dan Laporan Kinerja Triwulan — sasaran **S1–S4** dan indikator **IKU 1.1–IKU 4.1** (pemetaan pada §2.4). Nomor IKU pada Renstra (IKU 1–8) dicatat sebagai keterangan pada definisi operasional indikator; tidak ada kolom baru. Sasaran S4 dan IKU 4.1 (Nilai Kinerja Anggaran atas Pelaksanaan RKA-K/L) hanya ada di PK dan dicatat di bawah Renstra 2025–2029 tanpa target Renstra (Q50 §50.5).
- **`dinilai_tahunan`** (boolean, default `false`; label UI "Dinilai tahunan"; Q47 §47.1; US/ISS-05.07) — penanda indikator yang nilainya baru tersedia pada periode nilai akhir (`periode.is_nilai_akhir`, §12.1). Perencanaan mengubah penanda ini di Sasaran & Indikator dan perubahannya tercatat di audit. Penanda ikut dibekukan pada snapshot jadwal (§12.5); perubahan setelah jadwal aktif mengikuti koreksi snapshot (Q35). Penanda tidak mengubah tipe perhitungan, formula, maupun komponen indikator, tetapi membolehkan target Rencana Aksi kosong pada periode sebelum periode nilai akhir (§14.3, §14.7). Data awal 2026: hanya IKU 1.3 yang ditandai; IKU 4.1 tidak ditandai karena nilainya tersedia tiap triwulan.
- **`arah`** — enum(`naik_baik`, `turun_baik`), default `naik_baik` — menyatakan interpretasi nilai indikator: `naik_baik` berarti nilai yang lebih tinggi menandakan kinerja lebih baik (mis. jumlah lulusan bersertifikat), sedangkan `turun_baik` berarti nilai yang lebih rendah menandakan kinerja lebih baik (mis. rata-rata masa tunggu penyelesaian layanan). Kolom ini dibangun sejak MVP dan disalin ke `jadwal_snapshot` sebagai bagian konteks beku (§12.5), sehingga evaluasi arah nilai pengukuran selalu memakai arah yang berlaku saat periode itu diaktifkan, dan dihitung atas nilai turunan bila indikator berkomponen (§17.6). Sejak Q45 pemburukan nilai tidak lagi memicu catatan wajib (§20); `arah` juga menentukan arah peringatan target PK terhadap target Renstra (Q39) dan cara membaca selisih terhadap target PK (§21, Q47 §47.3).
- **`tipe_perhitungan`** — enum(`rasio_persen`, `penjumlahan`, `manual`), not null, default `manual` — menentukan bagaimana `pengukuran.nilai` dihasilkan. Untuk `rasio_persen` dan `penjumlahan`, indikator wajib memiliki baris `indikator_komponen` aktif yang memenuhi syarat minimal perannya (§17.3); untuk `manual`, komponen tidak wajib dan nilai diketik langsung, sama seperti perilaku sebelum ronde ini. Mesin perhitungan lengkap dibahas di §17.
- Status Indikator: `aktif` atau `arsip`. Indikator berstatus `arsip` tidak lagi muncul sebagai target pengisian baru — `pengukuran:create` dan `rencana_aksi:create` untuk indikator tersebut **ditolak sistem** — namun data historis tetap tersimpan dan dapat ditelusuri di laporan.
- Flag `wajib_catatan` *(disembunyikan, Q45)* — semula, jika `true`, sistem mewajibkan pengisian kolom catatan pada Pengukuran indikator ini setiap kali diajukan. Sejak Q45 kewajiban tersebut digantikan narasi wajib per pengukuran (§20): saklar `wajib_catatan` disembunyikan dari form indikator tanpa menghapus kolomnya dan tidak lagi menjadi syarat pengajuan.
- Perpindahan indikator antar unit adalah operasi terkendali: memerlukan permission `indikator:update`, dan wajib tercatat di audit log (nilai lama dan baru dari `unit_id`) karena berdampak pada siapa yang berhak mengisi rencana aksi/pengukuran ke depan; alasan perpindahan bersifat opsional (Q43 §43.3 butir 3).
- Penghapusan indikator (`indikator:delete`) tunduk pada aturan integritas: indikator yang telah memiliki data pengukuran atau rencana aksi tidak dihapus secara fisik; wajib diarsipkan. Percobaan penghapusan tetap dicatat di audit log (lihat §25).
- **Dua lapis target (Q39; US/ISS-02.10 dan US/ISS-02.11):**
  - **Target Renstra** — data per indikator per tahun dalam rentang Renstra, diisi sekali per Renstra di halaman Sasaran & Indikator sesuai dokumen Renstra, dengan domain nilai yang sama dengan Q33 (nullable, finite, nonnegatif, `0` sah). Target Renstra hanya menjadi pembanding dan batas bawah target PK; tidak dipakai menghitung capaian. Revisinya saat Renstra `aktif` mengikuti §10.5 (Q40).
  - **Target PK** — disimpan di `target_tahunan` (domain nilai Q33), memuat baseline dan target tahun berjalan per indikator, dan diinput **Perencanaan** di halaman Perjanjian Kinerja (§14.10) setelah angka disepakati dengan tim kerja di luar aplikasi; PJ dan PIC melihatnya sebagai baca-saja. Target PK disimpan dengan **nilai penuh** yang dipakai pengukuran (mis. 94,05; 80,95; 92,86), bukan nilai tercetak pada dokumen PK yang dibulatkan (94; 81; 92,9); status capaian dan selisih memakai nilai tersimpan, sedangkan tampilan mengikuti `desimal_tampilan` (Q50 §50.2). Target Renstra dan target PK sama-sama disimpan dengan permission `target:update` (Q39 §39.3 butir 3). Target tahun berjalan tidak lagi diisi di halaman Sasaran & Indikator. Snapshot (§12.5), rekonsiliasi Rencana Aksi (§14.5), dan pengukuran tetap membaca target PK melalui snapshot; capaian dinilai terhadap target PK, bukan target Renstra.
  - Saat target PK diisi, target Renstra tahun yang sama ditampilkan di sampingnya. Untuk indikator `naik_baik`, target PK di bawah target Renstra memunculkan **peringatan dan alasan wajib**; untuk indikator `turun_baik`, peringatan muncul bila target PK di atas target Renstra. Sistem tidak menolak nilai tersebut karena PK revisi yang sah dapat lebih rendah; alasan deviasi disimpan bersama target PK (`target_tahunan.alasan_deviasi_renstra`) dan tercatat di audit. Anjuran "minimal sama atau naik satu poin" ditampilkan sebagai panduan, bukan aturan sistem. Indikator tanpa target Renstra (mis. IKU baru di tengah periode Renstra, atau IKU 4.1 yang hanya ada di PK — Q50 §50.5) tetap dapat diberi target PK dengan keterangan "target Renstra belum ada".
- `target_tahunan` (target PK) mendapat kolom baru **`baseline`** (numeric, nullable) — nilai capaian tahun sebelumnya yang dipakai sebagai acuan saat menyusun target tahun berjalan. Kolom ini disalin ke `jadwal_snapshot` (§12.5) karena halaman PK dan rekapitulasi indikator × periode (§22.4) menampilkan kolom "Baseline" yang dipakai berulang di setiap periode. Baseline **bukan isi dokumen PK**; halaman PK menampilkannya sebagai data pendukung (Q50 §50.3).

---

## 12. Periode & Jadwal

### 12.1 Periode (Master)

`periode` adalah master global yang mendefinisikan satuan waktu pelaporan dalam satu tahun (mis. Triwulan I, Triwulan II, Triwulan III, Triwulan IV, atau Semester I/II, atau Tahunan), masing-masing memiliki `urutan` tampil dan flag `aktif`. Tepat satu periode per konfigurasi ditandai `is_nilai_akhir = true` — periode yang dianggap sebagai representasi capaian akhir tahun (mis. Triwulan IV/Tahunan) untuk keperluan rekap tahunan pada dashboard/laporan. Nilai pada periode dengan `is_nilai_akhir = true` **diisi manual** oleh penanggung jawab/Perencanaan — sistem tidak menghitung agregasi otomatis dari periode-periode sebelumnya.

Setup awal pada tabel master kosong menyediakan Triwulan I–IV aktif dengan urutan 1–4 dan Triwulan IV sebagai nilai akhir, sesuai [addendum seed ISS-03.01](SAKIP%20-%20Keputusan%20Penyelarasan.md). Seed tidak mengubah konfigurasi yang sudah ada; pengelolaan berikutnya tetap melalui UI dengan aturan master periode yang berlaku.

### 12.2 Jadwal Tahunan (Level Tahun)

Setiap kombinasi Renstra + Tahun memiliki satu `jadwal_tahunan` yang membingkai tahun tersebut:

- `rencana_aksi_mulai`, `rencana_aksi_selesai` (date, nullable) — *tidak dipakai lagi (Q41)*. Semula jendela penyusunan dan pengesahan Rencana Aksi tingkat tahun dengan deadline mutlak bagi PIC. Sejak Q41 kedua kolom tidak menjadi batas PIC dan dihapus dari form jadwal; kolomnya dibiarkan nullable sampai dihapus pada migrasi lanjutan. PIC menyusun, mengubah, dan mengajukan Rencana Aksi sejak jadwal aktif sampai `penutupan`, dengan kunci per periode (§12.3); **Perencanaan dikecualikan** dari kunci periode (permission global tanpa scope unit, dibatasi hanya oleh `penutupan`).
- `penutupan` — tanggal batas akhir seluruh aktivitas jadwal tahun tersebut, termasuk penulisan Rencana Aksi oleh PIC (Q41) serta pengecualian Perencanaan pada jendela pengisian periode dan kunci periode Rencana Aksi (§7.1, §12.3). Penutupan jadwal hanya menghentikan mutasi data kinerja: unggahan arsip PDF bertanda tangan Rencana Aksi tahunan dan Laporan Kinerja Triwulan tetap dapat dilakukan setelah jadwal ditutup karena tidak mengubah data, status, maupun snapshot (Q48 §48.2 butir 4–5; §24.1).
- `status`: `draft → aktif → ditutup`.
- `pakai_persetujuan_pimpinan` (boolean, default `false`) dan `persetujuan_mulai`/`persetujuan_selesai` — kolom disiapkan untuk Fase Lanjutan; pada Fase Awal selalu `false` dan tidak dipakai dalam alur (lihat §19.2).
- `renstra_pk_id` — wajib diisi saat aktivasi; lihat gerbang validasi §12.4.
- `activated_at` / `closed_at` — stempel waktu otomatis saat transisi status terjadi; nilai ini selalu mencatat waktu aktivasi/penutupan yang sebenarnya, termasuk untuk jadwal retroaktif (§12.6).
- Constraint: unique (`renstra_id`, `tahun`) lintas seluruh status (`draft`, `aktif`, `ditutup`), sesuai [addendum ISS-03.01](SAKIP%20-%20Keputusan%20Penyelarasan.md). Jika pasangan sudah memiliki jadwal, gunakan jadwal tersebut dengan hak edit sesuai status dan permission; tidak membuat jadwal kedua. Keunikan berlaku per pasangan Renstra–tahun, bukan per tahun lintas semua Renstra.

Jendela waktu pengisian dan reviu pengukuran **tidak berada** di `jadwal_tahunan` — jendela itu dipecah per periode pada tabel `jadwal_periode` (§12.3), karena periode berbeda (mis. Triwulan I vs Triwulan IV) lazimnya punya tenggat yang berbeda pula. Rencana Aksi tidak memiliki jendela tersendiri (Q41); kunci per periode Rencana Aksi bagi PIC mengikuti `pengisian_selesai` pada `jadwal_periode` (§12.3).

Transisi status jadwal:

```
draft ──aktivasi──► aktif ──tutup──► ditutup
                       ▲                │
                       └────buka_kembali┘
```

Permission terkait: `jadwal:create`, `jadwal:update`, `jadwal:aktivasi`, `jadwal:tutup`, `jadwal:buka_kembali`.

### 12.3 Jadwal Periode (Jendela per Periode)

Tabel pivot `jadwal_periode` (jadwal_tahunan × periode) mendaftar periode-periode yang diharapkan diisi pada tahun itu (mis. Triwulan I–IV, atau Semester I–II), masing-masing dengan jendela waktunya sendiri:

- `jadwal_id`, `periode_id` — pasangan kunci.
- `pengisian_mulai`, `pengisian_selesai` — jendela pengisian oleh penanggung jawab (PIC) untuk periode tersebut.
- `reviu_mulai`, `reviu_selesai` — jendela verifikasi/pengesahan oleh Perencanaan untuk periode tersebut.

Aturan penegakan:

- Deadline `pengisian_selesai` bersifat **mutlak bagi PIC** (pengguna yang memperoleh `pengukuran:create`/`update` lewat grant scope unit, §7.1, §7.5). Setelah `pengisian_selesai` periode berjalan terlewati, PIC tidak dapat lagi membuat, mengubah, atau mengajukan pengukuran untuk periode tersebut.
- **Perencanaan dikecualikan** dari batas jendela periode — dapat mengisi/mengubah pengukuran sampai `penutupan` jadwal tahunan (§12.2), karena permission pengisiannya bersifat global (§7.1).
- **Kunci per periode pada Rencana Aksi (Q41; US/ISS-05.07):** periode P terkunci bagi PIC pada Rencana Aksi setelah `pengisian_selesai` periode P terlewati (tanggal bisnis Asia/Makassar). Setiap perubahan Rencana Aksi oleh PIC — simpan draf, pengajuan, dan versi setelah dikembalikan atau dibuka kembali — tidak boleh mengubah target maupun daftar kegiatan periode yang terkunci; pelanggaran ditolak sebagai validasi bisnis (422) dan diaudit. Perencanaan dikecualikan sampai `penutupan`, sama seperti pengecualian pada jendela pengisian. Periode lampau pada saat aktivasi jadwal (§12.6) sudah terkunci bagi PIC sejak aktivasi. Kunci mengikuti nilai `pengisian_selesai` yang berlaku, termasuk hasil revisi resmi jendela pengisian (Q18, ISS-03.06): perpanjangan jendela pengisian periode P ikut menggeser kunci Rencana Aksi periode P bagi PIC (Q41 §41.2 butir 5). Dalam sesi koreksi setelah penutupan, PIC hanya dapat mengubah Rencana Aksi pada periode yang jendela pengisiannya dibuka kembali secara resmi (ISS-14.03, Q19); di luar itu koreksi Rencana Aksi dilakukan Perencanaan (Q41 §41.2 butir 6).
- Status tampilan "Belum mengisi"/"Tidak mengisi" pada dashboard (§23) dihitung per kombinasi **indikator × periode yang diharapkan** dari `jadwal_periode` — bukan dari tanggal generik. Indikator berstatus `arsip` tidak dihitung sebagai kewajiban pengisian.

Rencana Aksi disusun **setelah** daftar `jadwal_periode` pada jadwal tahunan tersusun (target per periode butuh daftar periodenya). Syarat lama "sebelum jendela pengisian periode pertama dibuka" **digantikan Q41**: PIC dapat menyusun dan mengajukan Rencana Aksi kapan pun sejak jadwal aktif sampai `penutupan`, dalam batas kunci per periode di atas. Yang tetap mutlak adalah Rencana Aksi harus disahkan sebelum pengukuran diajukan (§14.9).

### 12.4 Gerbang Validasi Aktivasi Jadwal

Aktivasi jadwal (`jadwal:aktivasi`) **ditolak** jika salah satu dari empat syarat berikut tidak terpenuhi:

1. `renstra_pk` untuk pasangan (renstra, tahun) belum tercatat — target yang diukur harus berlandaskan PK yang sah.
2. Ada indikator aktif pada Renstra tersebut yang belum memiliki target PK (`target_tahunan`, diisi di halaman PK — Q39) untuk tahun yang bersangkutan.
3. `jadwal.tahun` berada di luar rentang `[tahun_mulai, tahun_akhir]` Renstra.
4. **`renstra_pk` tahun tersebut belum memiliki minimal satu lampiran dokumen** (`berkas` dengan `berkasable_type = renstra_pk`, mode file/tautan/teks bebas — §18.4, §14.10). Pengecualian anti-macet lama — gerbang ditandai `tidak_dapat_dipenuhi` bila unggahan file dimatikan pada setelan aplikasi — **tidak lagi terjadi** karena sejak Q44 unggahan file selalu aktif; penanda yang sudah tercatat tetap menjadi histori (§18.7).

Keempat gerbang ini dievaluasi di service layer sebagai validasi utama. Sebagai lapisan pertahanan kedua, direkomendasikan PostgreSQL exclusion constraint (`EXCLUDE USING gist` + ekstensi `btree_gist`) untuk menegakkan aturan rentang tahun Renstra yang tidak boleh beririsan pada level basis data, sebagai jaring pengaman tambahan di luar validasi aplikasi. Aktivasi jadwal tidak digerbangi oleh kelengkapan Rencana Aksi — Rencana Aksi baru disusun setelah jadwal aktif (§14.1); gerbang kelengkapan Rencana Aksi berlaku pada pengajuan Pengukuran (§19.4), bukan pada aktivasi jadwal.

### 12.5 Snapshot Saat Aktivasi (Fase Awal — Fondasi MVP)

Saat jadwal berpindah status ke `aktif` (aksi `jadwal:aktivasi`) — termasuk saat `jadwal:buka_kembali` dijalankan atas jadwal yang sudah pernah aktif sebelumnya — sistem secara otomatis (dipicu oleh logika aplikasi; imutabilitasnya dijaga trigger basis data) membuat baris `jadwal_snapshot` untuk setiap indikator aktif yang relevan pada Renstra tersebut, menyalin:

- Referensi ke indikator master (`indikator_id`) untuk ketertelusuran.
- `unit_id`, `nama`, `definisi`, `satuan`, `presisi`, `desimal_tampilan`, `arah`, `tipe_perhitungan`, `baseline` — salinan beku dari kondisi master saat itu.
- `dinilai_tahunan` — salinan beku penanda indikator dinilai tahunan (§11); gerbang target periode Rencana Aksi (§14.7) membaca penanda dari snapshot, dan perubahan setelah jadwal aktif mengikuti koreksi snapshot (Q47 §47.1 butir 2).
- `target` — salinan nilai target PK (`target_tahunan`) untuk tahun jadwal tersebut (nullable jika target belum diisi saat aktivasi — kondisi ini sudah dicegah oleh gerbang §12.4 poin 2 untuk indikator aktif pada aktivasi awal).

Untuk indikator bertipe `tipe_perhitungan` selain `manual`, sistem juga membuat baris anak **`jadwal_snapshot_komponen`** (`jadwal_snapshot_id`, `kode`, `label`, `peran`, `bobot`, `urutan`) yang membekukan definisi komponen indikator (§17.9) pada saat aktivasi, sehingga perubahan definisi komponen di tengah tahun tidak mengubah makna data historis yang sudah dirujuk pengukuran.

Aturan pembentukan snapshot:

- **Idempoten** — baik pada aktivasi awal maupun pada `jadwal:buka_kembali`, sistem hanya membuat baris `jadwal_snapshot` (dan `jadwal_snapshot_komponen` anaknya) untuk pasangan (`jadwal_id`, `indikator_id`) yang **belum ada**. Baris lama tidak pernah ditimpa.
- **Beku sejak terbit (Q35 §35.1)** — begitu jadwal bertransisi ke `aktif`, baris snapshot (beserta baris komponennya) tidak dapat lagi diubah, baik sudah maupun belum dirujuk pengukuran atau rencana aksi. Koreksi konteks selalu dibuat sebagai versi snapshot baru (`nomor_versi` + 1, `menggantikan_id`, alasan, dan rujukan resmi) yang teraudit; versi lama tetap utuh. Revisi PK berversi yang mengubah target memakai mekanisme yang sama (§12.7, Q40). Tidak ada restatement data historis dalam bentuk apa pun.
- Baris `audit_log` untuk pembuatan snapshot memakai `actor_id` = pengguna Perencanaan yang menjalankan aksi aktivasi — jejaknya menempel pada aksi manusia yang memicunya, bukan pada proses sistem anonim.

Setiap `pengukuran` merujuk ke `jadwal_snapshot_id`, bukan langsung ke `indikator_id` master, untuk keperluan tampilan (nama, satuan, target pembanding, arah, cara hitung). Keputusan ini masuk sebagai fondasi MVP sejak Fase Awal, bukan fitur yang ditunda — tanpa snapshot, perubahan pada master indikator maupun definisi komponennya di masa depan akan mendistorsi tampilan capaian tahun-tahun yang sudah disahkan.

Implikasi operasional dari keputusan ini dijabarkan di §34 (Catatan Risiko).

### 12.6 Penggunaan Pertama 2026, Periode Lampau, dan Koreksi

Penggunaan pertama SAKIP adalah **Jadwal Tahunan 2026**. Master periode operasional 2026 adalah TW I, TW II, TW III, dan TW IV.

- TW I–III 2026: saat jadwal diaktifkan, jendela pengisiannya sudah lewat sehingga berstatus **periode lampau** dan pengisian dilakukan Perencanaan (Q46 §46.1 butir 2; semula hanya TW I–II).
- TW IV 2026: triwulan pertama yang berjalan melalui workflow normal PIC operasional, dengan pelaporan awal Januari 2027 (Q46 §46.1 butir 2; semula TW III dan TW IV, diperbarui Q46).
- 2025: **tidak dibuat sebagai baris pengukuran/jadwal historis**; hanya digunakan sebagai baseline indikator.

Sistem **tidak memiliki flag/kolom khusus `is_backfill`**. Status periode lampau dihitung dari jendela pengisian terhadap waktu aktivasi jadwal. Tahun aktif juga tidak di-hardcode; tahun aktif adalah tahun dengan `jadwal_tahunan.status = aktif`.

Pada periode lampau, ketika flow aktivasi dijalankan:

- gerbang RA telah disahkan dikecualikan;
- kelengkapan komponen dikecualikan;
- bukti wajib dikecualikan;
- gerbang baru Q42–Q45 dikecualikan — RA minimal satu kegiatan, RAB, persyaratan tahap `kegiatan_perencanaan`, narasi wajib, dan tindak lanjut rekomendasi (Q46 §46.1 butir 1, sebagaimana dikoreksi 13 Oktober 2026). Kunci per periode Rencana Aksi (Q41 §41.2) bukan bagian pengecualian ini: periode lampau terkunci bagi PIC sejak aktivasi, sedangkan Perencanaan yang mengisinya memang tidak terikat kunci (Q41 §41.2 butir 3–4); setiap issue yang menambah gerbang Q42–Q45 menyertakan pengecualian periode lampau beserta test-nya, dan issue kunci per triwulan (Q41) menyertakan test bahwa periode lampau terkunci bagi PIC (Q46 §46.1 butir 4);
- target periode lampau boleh kosong (Q47 §47.2 butir 4);
- gerbang PK tahun berjalan tetap wajib;
- Perencanaan melakukan pengisian.

**Data awal 2026 (Q46 §46.1 butir 3 dan 6; ISS-15.01, dikerjakan di akhir tahap 1):**

- Perencanaan mengisi data awal dari dokumen resmi 2026 — Renstra 2025–2029, Perjanjian Kinerja 2026, Rencana Aksi 2026, serta Laporan Kinerja Triwulan I dan II 2026: target PK dengan nilai penuh (Q50 §50.2), target periode dan kegiatan Rencana Aksi, serta realisasi, narasi, dan Rekomendasi Pimpinan TW I–II. Rekomendasi TW I–II dari laporan triwulan yang sudah ditandatangani Pimpinan dicatat Perencanaan langsung berstatus `disetujui`, dengan penanda persetujuan cadangan dan rujukan laporan sumbernya (`rekomendasi_pimpinan.rujukan_sumber`; Q51 §51.1 butir 5). Periode TW III juga diisi Perencanaan sebagai periode lampau.
- Kode sasaran dan indikator mengikuti PK (S1–S4, IKU 1.1–IKU 4.1), dengan S4/IKU 4.1 dicatat di bawah Renstra 2025–2029 tanpa target Renstra (Q50 §50.4–§50.5, §2.4).
- Target TW I–III yang tertulis 0 pada Rencana Aksi 2026 dicatat **kosong** (tidak ditargetkan) untuk semua indikator, karena laporan triwulan tidak menilai realisasi terhadap angka tersebut (Q47 §47.2 butir 4). Hanya IKU 1.3 yang ditandai dinilai tahunan (Q47 §47.1 butir 4).
- Rencana Aksi 2026 yang dicatat Perencanaan sebagai data awal berstatus disahkan sebelum pengajuan TW IV (Q32.8, diperbarui Q46) dan dikecualikan dari gerbang kegiatan minimal dan RAB; kegiatan TW IV ditambahkan lewat klaim tahap pengukuran atau buka-kembali RA oleh Perencanaan (Q46 §46.1 butir 3). Kegiatan data awal dan kegiatan pada periode lampau boleh tanpa anggaran (tercatat "belum diisi", Q42 §42.2 butir 2), karena Rencana Aksi resmi hanya memuat anggaran per Rincian Output (Q46 §46.1 butir 3).
- Data yang belum dikonfirmasi Perencanaan mengikuti asumsi kerja pada bagian tindak lanjut dokumen Keputusan Penyelarasan; asumsi kerja hanya dipakai untuk data uji dan rancangan data awal, dan **tidak dipakai di produksi** sebelum dikonfirmasi Perencanaan.

Koreksi setelah penutupan tetap memakai mekanisme buka-kembali beraudit. Dalam sesi koreksi tersebut, PIC hanya dapat mengubah Rencana Aksi pada periode yang jendela pengisiannya dibuka kembali secara resmi (Q19); di luar itu koreksi Rencana Aksi dilakukan Perencanaan (Q41 §41.2 butir 6). Mekanisme versioning/snapshot tidak boleh memalsukan tanggal aktivasi historis.

### 12.7 Revisi PK Berversi dan Revisi Target (Q40)

> **Diperbarui Q40 dan Q50 (13 Oktober 2026):** asumsi lama bahwa target tahunan PK tidak direvisi di tengah tahun (revisi hanya efektif di antara tahun) dan bahwa nomor/tanggal surat persetujuan Eselon I dicantumkan pada kolom `alasan` **digantikan** oleh revisi PK berversi di bawah. Revisi PK berversi (US/ISS-02.12) dikerjakan pada tahap 3 (Q46 §46.3); status implementasi: belum.

- Revisi PK jarang terjadi — umumnya pada akhir tahun setelah persetujuan Eselon I di kementerian pusat. Di aplikasi, revisi disetujui **Perencanaan** (Q40 §40.1 butir 1).
- Setiap revisi membentuk **versi PK baru** yang menyimpan nomor versi, nomor dan tanggal surat persetujuan Eselon I sebagai isian tersendiri, periode mulai berlaku, lampiran dokumen PK bertanda tangan, dan target per indikator; versi sebelumnya tidak diubah. PK awal dan PK akhir sama-sama disimpan dan dapat diunduh karena keduanya menjadi lampiran laporan kinerja (Q40 §40.1 butir 2–3).
- Revisi berlaku mulai **periode pertama yang belum ditutup**; periode yang sudah dilaporkan tetap memakai target versi lama. Persetujuan revisi yang mengubah target menerbitkan versi `jadwal_snapshot` baru (§12.5, Q35 §35.1) dengan `periode_mulai_id` periode tersebut dan `rujukan_koreksi` surat Eselon I. Revisi yang hanya mengganti dokumen (mis. karena perubahan anggaran) tidak menerbitkan versi snapshot (Q40 §40.1 butir 4–5).
- Capaian akhir tahun dinilai terhadap target **PK akhir**, yaitu versi yang berlaku pada periode nilai akhir (Q40 §40.1 butir 6).
- Rencana Aksi tidak diubah otomatis: selisih target periode terakhir dengan target PK baru tampil sebagai deviasi yang wajib dijelaskan (ADR-0008), dan target periode yang belum terkunci disesuaikan lewat buka-kembali (§14.5, §14.6; Q40 §40.1 butir 7).
- Koreksi salah ketik nomor atau tanggal PK tetap memakai jalur koreksi teraudit dan beralasan (ISS-02.09); perubahan target resmi memakai revisi berversi (Q40 §40.1 butir 8).
- Prasyarat implementasi: alur koreksi snapshot berversi (ISS-03.03). Revisi PK berversi harus siap sebelum revisi PK pertama yang mengubah target (Q40 §40.1 butir 9, Q46 §46.3).
- **Sebelum revisi berversi tersedia (Q50 §50.7):** PK perubahan yang hanya mengubah anggaran diunggah sebagai **lampiran tambahan** pada PK tahun tersebut beserta keterangan; target dan snapshot tidak berubah (§14.10). Bila PK perubahan yang mengubah target terbit sebelum ISS-02.12 selesai, penanganannya ditetapkan PM sebagai keputusan baru.
- Jalur normal untuk target tahunan yang tidak tercapai tetap **justifikasi tertulis**, bukan revisi target. Justifikasi hidup di dalam sistem lewat narasi pengukuran wajib (§20; semula catatan wajib saat nilai memburuk dan flag `wajib_catatan`, digantikan Q45) dan `status_capaian = belum_tercapai` (§21), bukan tersebar di korespondensi terpisah.
- Target PK adalah komitmen legal-formal yang hanya berubah lewat revisi PK berversi, sedangkan target periode (komponen) pada Rencana Aksi adalah rencana kerja operasional yang dapat direvisi di tengah tahun lewat `rencana_aksi:buka_kembali` (§14.6) untuk periode yang belum terkunci (Q41).
- Koreksi salah input di luar waktu normal (mis. salah ketik target yang sudah terbekukan) memakai versi snapshot pengganti yang teraudit (§12.5, Q35 §35.1), bukan fitur revisi target tersendiri.

---

## 13. Penanggung Jawab Indikator

Penanggung Jawab adalah **assignment historis**, bukan role. Entitas mencatat indikator, user, tanggal mulai berlaku, aktor penetap, dan alasan. Sejak Q43, field alasan dihapus dari form penetapan dan pergantian PJ; kolom basis datanya tetap nullable (Q43 §43.3 butir 4; US/ISS-13.05).

Aturan final Q32:

1. user target harus ada dan berstatus aktif;
2. tidak ada syarat PIC operasional (bukan role) karena role tersebut tidak ada;
3. Perencanaan dan Superadmin dapat menetapkan PJ sesuai permission efektif;
4. assignment tidak memberikan permission;
5. hak kerja pada unit indikator tetap dinilai oleh PermissionResolver dari role/grant yang sah dan explicit deny;
6. calon PJ dengan izin kerja belum lengkap menimbulkan **warning**, bukan blokir;
7. sistem menyediakan daftar **PJ aktif tanpa hak isi**;
8. perubahan role user tidak mengakhiri assignment;
9. revoke hak isi harus eksplisit, beralasan, dan teraudit.

Penanggung jawab efektif pada tanggal T adalah assignment dengan `tanggal_mulai_berlaku` terbesar yang `<= T`. Histori lama tidak dihapus atau ditimpa ketika terjadi pergantian.

Kontrak dasar histori dan resolusi — Plan §4.3–4.4 / Issue #54: pergantian menambah histori tanpa menimpa baris lama. Kewajiban alasan pergantian **digantikan Q43** §43.3 butir 4 (alasan tidak lagi diminta di form).

Monitoring menilai seluruh tujuh permission scoped untuk unit indikator: `pengukuran:create/update`, `rencana_aksi:create/update/ajukan`, dan `kegiatan:create/update`. Tampilkan izin tersedia dan kurang menurut PermissionResolver saat ini; explicit deny menang dan akun nonaktif fail-closed. Diagnosis tidak menggantikan pemeriksaan setiap aksi.

**Aturan PJ — Diratifikasi Q34; butir 1 (pergantian di tanggal yang sama) diimplementasikan pada [#70](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/70):**

- **PJ-01/PJ-02:** tanggal mutasi lampau/mendatang diperbolehkan tanpa mengubah data historis yang sudah dibekukan; pergantian PJ pada tanggal yang sama diperbolehkan dengan tepat satu PJ efektif menurut tanggal efektif lalu urutan penugasan (`urutan`).
- **PJ-03/PJ-04:** no-op ditolak, mantan PJ boleh kembali sesudah pergantian nyata, dan assignment mendatang existing dipertahankan.
- **PJ-05:** mutasi ditolak saat indikator `arsip`, Renstra `diarsipkan`, atau unit `nonaktif`; histori tetap tersimpan dan terbaca sesuai akses.

Rujukan keputusan: **Q34 §34.1** pada [Keputusan Penyelarasan](SAKIP%20-%20Keputusan%20Penyelarasan.md). Evidence dan dampak per aturan dirujuk pada [matriks provenance ISS-04.01](SAKIP%20-%20User%20Issues.md#status-review-dan-traceability-iss-0401--8-oktober-2026).

Untuk initial setup 2026, Rencana Aksi yang sudah disusun Perencanaan harus dicatat sebagai **disahkan** sebelum pengajuan TW IV (Q32.8, diperbarui Q46; semula sebelum pengajuan TW III). Workflow PIC operasional normal untuk RA baru dimulai dari siklus berikutnya (2027).

## 14. Rencana Aksi

### 14.1 Posisi dalam Siklus

Rencana Aksi adalah tahap baru yang disisipkan antara aktivasi Jadwal Tahunan dan pengajuan Pengukuran periode pertama. Alur lengkapnya:

Renstra (+ target Renstra) → Perjanjian Kinerja (PK; target PK) → Jadwal Tahunan + daftar periode (`jadwal_periode`) → **penyusunan & pengesahan Rencana Aksi per indikator** (target per periode + kegiatan per periode beserta anggaran, RAB, dan klaimnya — Q42) → pelaksanaan Kegiatan + klaim tahap pengukuran → Pengukuran per periode (komponen + narasi + berkas) → verifikasi/pengesahan → Rekomendasi Pimpinan (draf → disetujui, Q51) → Status Capaian → dashboard/laporan dan dokumen resmi (Q48).

Rencana Aksi disusun **setelah** daftar periode pada jadwal tahunan tersusun (target triwulan membutuhkan daftar periodenya lebih dulu). Syarat lama "sebelum jendela pengisian periode pertama dibuka" **digantikan Q41** §41.1: PIC efektif dengan grant yang sah dapat menyusun, mengubah, dan mengajukan Rencana Aksi sejak jadwal tahun tersebut aktif sampai `penutupan`, sedangkan periode yang `pengisian_selesai` berlakunya — termasuk hasil revisi resmi jendela pengisian (Q18) — sudah lewat terkunci bagi PIC (§12.3; Q41 §41.2 butir 5). Rencana Aksi disusun **sekali per (indikator × tahun)**, bukan per periode — target seluruh periode dalam tahun itu disusun di dalam satu baris Rencana Aksi.

**Gerbang keras:** pengajuan Pengukuran untuk periode P pada indikator I **ditolak** selama `rencana_aksi` untuk (I × tahun) belum berstatus `disahkan` (lihat §19.4; tetap berlaku, Q41 §41.3 butir 1), kecuali pada periode lampau (§12.6).

### 14.2 Header Rencana Aksi (`rencana_aksi`)

Tabel `rencana_aksi` menyimpan header rencana per indikator per tahun:

- `id`, `indikator_id` (FK → indikator), `tahun` (int).
- `unit_id` (FK → unit) — salinan unit pemilik indikator pada saat penyusunan.
- `jadwal_tahunan_id` (FK → jadwal_tahunan).
- `penanggung_jawab_id` (FK → users) — PIC efektif pada saat penyusunan (§13).
- `uraian` (text, nullable).
- `status_alur` — enum(`draft`, `diajukan`, `diverifikasi`, `dikembalikan`, `disahkan`), default `draft`.
- `versi` (int, default 1) — optimistic locking, mengikuti pola `pengukuran.versi` (§27).
- `alasan_revisi` (text, nullable).
- `created_by`, `created_at`, `updated_at`, `disahkan_at`, `disahkan_by`.

Constraint: **`unique(indikator_id, tahun)`** — satu Rencana Aksi per indikator per tahun.

### 14.3 Target per Periode per Komponen (`rencana_aksi_target`)

Target dituangkan sebagai baris-baris pada tabel `rencana_aksi_target`, satu baris per kombinasi periode × komponen:

- `id`, `rencana_aksi_id` (FK), `periode_id` (FK → periode), `komponen_id` (FK → `indikator_komponen`).
- `nilai` (numeric, **nullable**) — `0` adalah nilai sah, `null` berarti belum diisi. Untuk indikator **dinilai tahunan** (§11), target pada periode sebelum periode nilai akhir boleh tetap kosong saat pengajuan; kosong di sini berarti **tidak ditargetkan** (label UI "Tanpa target periode") dan berbeda dari target `0`, yang tetap sah bila memang ditargetkan nol (Q47 §47.2). Pada versi Rencana Aksi yang dibekukan, periode tanpa target dicatat dengan status `tidak_ditargetkan`, bukan `belum_diisi`, sehingga laporan dapat membedakannya dari target yang belum diisi (Q47 §47.2 butir 5).
- `keterangan` (text, nullable).
- `updated_by`, `updated_at`.

Constraint: **`unique(rencana_aksi_id, periode_id, komponen_id)`**.

**Target diinput pada level komponen, bukan skor final.** PIC mengisi angka komponen (mis. `n` = jumlah responden puas, `t` = total layanan) per periode; sistem menampilkan **perkiraan skor** hasil hitungan komponen itu memakai mesin perhitungan yang sama dengan §17. Perkiraan skor ini adalah nilai turunan tampilan — tidak disimpan sebagai input tersendiri.

### 14.4 Sifat Kumulatif Target

**Target triwulan bersifat kumulatif**: nilai periode berjalan mencakup periode sebelumnya (mis. target Triwulan II = capaian Januari–Juni, bukan April–Juni saja). Sistem memberi **peringatan (bukan blokir)** bila nilai komponen suatu periode lebih kecil daripada nilai periode sebelumnya pada komponen yang sama; ketentuan ini tetap berlaku setelah Q41 (§41.3 butir 3). Periode tanpa target (Q47 §47.2) tidak memicu peringatan ini.

### 14.5 Rekonsiliasi dengan Target PK Tahunan

Total target komponen pada periode terakhir memang seharusnya setara dengan target PK tahunan indikator tersebut. Bila tidak setara, sistem menampilkan **peringatan dan mewajibkan alasan** pada saat pengajuan Rencana Aksi (bukan memblokir pengajuan), karena:

- target tahunan PK tetap hanya dapat diubah lewat revisi PK resmi, yaitu revisi PK berversi (§12.7, Q40) — Rencana Aksi tidak berwenang mengubahnya; dan
- deviasi antara rencana kerja dan target resmi wajib terlihat di layar dan tercatat di audit, bukan disesuaikan diam-diam.

Alasan deviasi ini (ADR-0008) wajib saat pengajuan, bukan saat simpan draf, dan tetap berlaku setelah Q41 (§41.3 butir 4). Untuk indikator dinilai tahunan, target periode nilai akhir tetap wajib dan direkonsiliasi dengan target PK (Q47 §47.2 butir 1). Bila revisi PK berversi mengubah target, Rencana Aksi tidak diubah otomatis: selisih target periode terakhir dengan target PK baru tampil sebagai deviasi yang wajib dijelaskan, dan target periode yang belum terkunci disesuaikan lewat buka-kembali (§14.6; Q40 §40.1 butir 7).

### 14.6 Alur Status Rencana Aksi

Status alur Rencana Aksi mengikuti pola yang sama dengan Pengukuran:

```
Draft ──ajukan──► Diajukan ──verifikasi──► Diverifikasi ──sahkan──► Disahkan
                    ▲   │                       │
                    │   └──────kembalikan───────┤
                    │                           ▼
                    └────────ajukan─────── Dikembalikan
```

`Dikembalikan` (alasan wajib) berfungsi sebagai jalur revisi dari `Diajukan`/`Diverifikasi`: rencana aksi disunting di tempat lalu diajukan ulang sebagai versi baru (Q36.1 butir 2). Versi yang dibekukan saat pengajuan mencatat periode tanpa target dengan status `tidak_ditargetkan` (§14.3, Q47 §47.2 butir 5) beserta total anggaran yang tidak berubah sesudahnya (Q42 §42.2 butir 6). Untuk koreksi setelah pengesahan, tersedia permission khusus **`rencana_aksi:buka_kembali`** (Perencanaan/Superadmin; alasan wajib; tersedia sebelum `jadwal_tahunan.penutupan` atau setelah `penutupan` di dalam sesi koreksi resmi yang mencakup `rencana_aksi` dan indikator tersebut, sesuai [Keputusan PM — PR #100](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/100#issuecomment-6161432383)) untuk transisi `Disahkan → Dikembalikan` — pola yang identik dengan `pengukuran:buka_kembali` (§19.5).

**Buka-kembali dan kunci periode (Q41 §41.2, §41.4; US/ISS-05.06):** versi Rencana Aksi oleh PIC setelah dikembalikan atau dibuka kembali tetap tidak boleh mengubah target maupun daftar kegiatan periode yang terkunci (§12.3). `rencana_aksi:buka_kembali` dipakai bila target periode yang belum terkunci perlu diubah — termasuk penyesuaian setelah revisi PK berversi (§14.5) — bukan untuk memindahkan kegiatan yang mundur; kegiatan yang mundur ditandai `ditunda` dan dilanjutkan sebagai kegiatan lanjutan tanpa membuka kembali Rencana Aksi (§15.2). Untuk Rencana Aksi 2026 yang dicatat sebagai data awal, kegiatan TW IV ditambahkan lewat klaim tahap pengukuran atau buka-kembali Rencana Aksi oleh Perencanaan (Q46 §46.1 butir 3). Dalam sesi koreksi setelah penutupan, PIC hanya dapat mengubah Rencana Aksi pada periode yang jendela pengisiannya dibuka kembali secara resmi (ISS-14.03, Q19); di luar itu koreksi Rencana Aksi dilakukan Perencanaan (Q41 §41.2 butir 6).

**Pengesahan oleh Perencanaan (Q41 §41.3 butir 2):** Rencana Aksi tetap diverifikasi dan disahkan Perencanaan. Perencanaan boleh mengesahkan Rencana Aksi yang diinputnya sendiri dengan penanda self-approval (Q15, F2); pemisahan tugas ketat berlaku pada jalur PIC (F1).

### 14.7 Gerbang Kelengkapan saat Pengajuan

Pengajuan Rencana Aksi (`Draft → Diajukan`) **ditolak** bila:

1. ada komponen aktif milik indikator tersebut yang belum memiliki target pada salah satu periode yang diharapkan menurut `jadwal_periode` tahun itu — kecuali periode sebelum periode nilai akhir pada indikator yang dinilai tahunan menurut snapshot, yang boleh tanpa target (Q47 §47.2 butir 1; indikator lain tetap wajib bertarget di setiap periode, Q47 §47.2 butir 2);
2. tidak ada **minimal satu kegiatan** yang diklaim pada tahap Rencana Aksi; tidak setiap periode wajib berisi kegiatan (Q42 §42.1 butir 3);
3. ada kegiatan berklaim tahap Rencana Aksi yang belum memenuhi persyaratan wajib tahap `kegiatan_perencanaan` — persyaratan global ditambah persyaratan khusus indikator Rencana Aksi tersebut (Q42 §42.4 butir 4) — termasuk RAB (Excel atau PDF) untuk kegiatan beranggaran lebih dari 0 melalui persyaratan bertanda `hanya_kegiatan_beranggaran` (Q42 §42.3 butir 1 dan 3, §42.4 butir 1; §15.6, §18.6); penolakan menyebut kegiatan dan persyaratan yang kurang;
4. ada persyaratan bukti dukung wajib tahap `rencana_aksi` yang belum terpenuhi (§18.6);
5. pengajuan oleh PIC mengubah target atau daftar kegiatan periode yang terkunci (§12.3, Q41 §41.2 butir 2) — ditolak sebagai validasi bisnis (422) dan diaudit.

Ini adalah pemeriksaan kelengkapan, bukan sekadar catatan peringatan. Deviasi target periode terakhir terhadap target PK tidak menolak pengajuan tetapi wajib dijelaskan (§14.5), dan target kumulatif yang turun tetap berupa peringatan (§14.4). Gerbang kegiatan minimal, RAB, dan persyaratan tahap `kegiatan_perencanaan` dikecualikan untuk periode lampau dan untuk Rencana Aksi 2026 yang dicatat Perencanaan sebagai data awal (§12.6; Q46 §46.1 butir 1 dan 3).

### 14.8 Penanggung Jawab Rencana Aksi

PIC Rencana Aksi adalah PIC indikator yang ditunjuk Perencanaan lewat `penanggung_jawab` (§13) — tidak ada penugasan terpisah untuk Rencana Aksi. Bila PIC indikator berganti di tengah tahun, `rencana_aksi.penanggung_jawab_id` tetap mencatat PIC yang berlaku saat penyusunan (jejak historis), sementara hak pengisian aktualnya mengikuti `penanggung_jawab` yang berlaku pada tanggal acuan.

### 14.9 Gerbang Rencana Aksi terhadap Pengukuran

Selama Rencana Aksi (indikator × tahun) belum berstatus `disahkan`, seluruh permintaan pengajuan Pengukuran pada indikator dan tahun tersebut ditolak sistem (§19.4); gerbang ini tetap berlaku meski Rencana Aksi tidak lagi memiliki jendela penyusunan (Q41 §41.3 butir 1). Percobaan yang ditolak karena gerbang ini tetap tercatat di audit log (§25.1).

### 14.10 Halaman Perjanjian Kinerja, Lampiran Dokumen PK, dan Gerbang Aktivasi Jadwal

Halaman Perjanjian Kinerja bukan sekadar tempat unggah dokumen (Q39 §39.1 butir 1; US/ISS-02.11). Halaman ini memuat:

- isian target PK tahun berjalan untuk setiap indikator aktif Renstra, dikelompokkan per sasaran, dengan target Renstra tahun yang sama sebagai pembanding (§11, Q39). Target PK disimpan dengan nilai penuh dan ditampilkan mengikuti `desimal_tampilan` (Q50 §50.2);
- baseline sebagai **data pendukung**, bukan isi dokumen PK (Q50 §50.3);
- `nomor_pk` (**opsional**, karena PK 2026 tidak bernomor; PK tetap dikenali dari Renstra dan tahun serta tanggal PK, Q50 §50.1) dan `tanggal_pk`, tanpa validasi bahwa tanggal PK harus setelah tanggal penetapan Renstra (Q50 §50.6);
- lampiran dokumen PK (`berkas` dengan `berkasable_type = renstra_pk`, §18.4) — salinan naskah PK yang ditandatangani, mode file/tautan/teks.

Dokumen PK tidak dihasilkan SAKIP karena ditandatangani bersama Direktur Jenderal; SAKIP mencatat isi target dan lampirannya (Q48 §48.1 butir 4). Perubahan PK sebelum PK dipakai jadwal aktif boleh tanpa alasan (Q43 §43.3 butir 3); sesudahnya, koreksi PK wajib beralasan (Q43 §43.2 butir 3; §12.7).

**Aktivasi Jadwal Tahunan mensyaratkan minimal satu lampiran dokumen pada `renstra_pk` tahun tersebut** sebagai gerbang keempat (§12.4), dengan mode bebas. Pengecualian anti-macet lama — gerbang ditandai `tidak_dapat_dipenuhi` bila unggahan file dimatikan pada setelan aplikasi — **dihapus Q44** karena unggahan file selalu aktif; penanda yang sudah tercatat tetap menjadi histori (§18.7, §18.9).

**PK perubahan yang hanya mengubah anggaran** diunggah sebagai **lampiran tambahan** pada PK tahun tersebut beserta keterangan (`berkas.keterangan`, mis. "PK perubahan anggaran"; Q48 §48.2 butir 6); target dan snapshot tidak berubah (Q50 §50.7 butir 1). PK perubahan yang mengubah target memakai revisi PK berversi (§12.7, Q40); setelah revisi berversi tersedia, lampiran PK disimpan per versi sehingga PK awal dan PK akhir sama-sama dapat diunduh (Q40 §40.1 butir 3, Q48 §48.2 butir 1).

**Lampiran `renstra_pk` tidak dapat dihapus setelah Jadwal Tahunan tahun itu berstatus `aktif`** (§18.8) — sebelum jadwal tahun tersebut aktif, Perencanaan/pengunggah dapat menghapus lampiran dengan alasan (Q43 §43.2 butir 2); penghapusan tercatat di `audit_log`.

---

## 15. Kegiatan

### 15.1 Definisi & Kepemilikan

Kegiatan disusun per **periode** (mis. Triwulan I) dan terikat tahun; kegiatan dimiliki oleh satu `unit` (unit pemilik indikator/unit pelaksana), dibuat oleh PIC unit itu (lewat grant per unit) atau Perencanaan (global, lewat isi peran). Kegiatan **tidak** dimiliki oleh satu Rencana Aksi/indikator — satu kegiatan dapat diklaim oleh lebih dari satu Rencana Aksi (dan lebih dari satu indikator) lewat tabel klaim (§16).

Tabel `kegiatan` menyimpan:

- `id`, `unit_id` (FK → unit, not null), `tahun` (int, not null), `periode_id` (FK → periode; periode **rencana** pelaksanaan, not null).
- `nama` (string, hingga 500 karakter — Q49 §49.1), `tujuan` (text, **opsional** — Q49 §49.1), `sasaran_peserta` (int, nullable — target jumlah peserta), `keterangan_peserta` (varchar, nullable — mis. "tim penyusun SPMI dari 30 PTS"), `lokasi` (varchar, nullable).
- `tanggal_rencana` (date, nullable), `tanggal_realisasi` (date, nullable).
- `anggaran` (numeric, rupiah, nonnegatif) — **wajib diisi** saat kegiatan dibuat atau diubah; `0` sah untuk kegiatan tanpa anggaran, dan kegiatan beranggaran nol tetap dapat diklaim. `null` hanya ada pada data sebelum Q42, ditampilkan "belum diisi", dan tidak dihitung sebagai 0 (Q42 §42.2 butir 1–2; semula kolom nullable yang tidak diinput pada MVP — Q28, digantikan Q42). Pengecualian: kegiatan data awal 2026 dan kegiatan pada periode lampau boleh tanpa anggaran (tercatat "belum diisi"), karena Rencana Aksi resmi hanya memuat anggaran per Rincian Output (Q46 §46.1 butir 3, §12.6).
- `status` — enum(`rencana`, `terlaksana`, `tidak_terlaksana`, `ditunda`, `batal`), default `rencana`.
- `realisasi_peserta` (int, nullable).
- `justifikasi` (text, nullable — **wajib** bila status `tidak_terlaksana`, `ditunda`, atau `batal`).
- `kegiatan_asal_id` (uuid, nullable, FK → kegiatan.id) — menandai kegiatan lanjutan hasil geser periode.
- `uraian_pelaksanaan` (text, nullable), `kendala` (text, nullable), `strategi_tindaklanjut` (text, nullable) — narasi per kegiatan yang opsional (§15.3, Q45).
- `created_by`, `created_at`, `updated_at`.

**Kegiatan sebagai bagian Rencana Aksi (Q42 §42.1; US/ISS-06.02):** Rencana Aksi memuat daftar kegiatan per periode, bukan hanya angka. Kegiatan ditambahkan dari halaman Rencana Aksi per periode; aksi ini membuat kegiatan (per unit × periode) sekaligus klaimnya ke Rencana Aksi dengan `sumber_klaim = rencana_aksi` (§16). Struktur kegiatan dan klaim tidak berubah: satu kegiatan tetap dapat diklaim lebih dari satu Rencana Aksi/IKU dalam unit yang sama, dan klaim lintas unit tetap ditolak (§16.4). Kegiatan tidak dapat ditambahkan pada periode yang terkunci bagi PIC (§12.3, Q41).

**Salin kegiatan ke periode lain (Q49 §49.2):** saat menambah kegiatan dari halaman Rencana Aksi, PIC dapat menyalinnya ke satu atau lebih periode lain pada Rencana Aksi yang sama. Setiap salinan menjadi kegiatan tersendiri per unit × periode dengan klaim `sumber_klaim = rencana_aksi` sendiri; nama, tujuan, dan anggaran ikut disalin dan dapat diubah per kegiatan. Berkas, termasuk RAB, tidak ikut disalin, dan persyaratan tahap perencanaan kegiatan diperiksa untuk setiap kegiatan. Salinan hanya dapat dibuat untuk periode yang belum terkunci.

**Penjumlahan anggaran (Q42 §42.2 butir 3–6):** anggaran dijumlahkan otomatis per periode, per Rencana Aksi, dan per IKU. Kegiatan yang diklaim lebih dari satu IKU ditampilkan penuh di setiap IKU dengan penanda **"kegiatan bersama"**, sedangkan total per unit dan per tahun dihitung dari **kegiatan unik** agar tidak terhitung ganda; alternatif "satu kegiatan hanya untuk satu IKU" masih menunggu konfirmasi Perencanaan. Kegiatan asal yang `ditunda` dan sudah memiliki kegiatan lanjutan tidak dihitung dalam total (§15.2), dan total yang sudah dibekukan pada versi Rencana Aksi tidak berubah (Q42 §42.2 butir 6). Dashboard menampilkan total anggaran per IKU dengan drill-down sampai kegiatan (§23). Rincian realisasi belanja, pagu induk, dan rekonsiliasi anggaran tidak dikelola.

**Akses detail kegiatan (Q12, Q42 §42.5, Q49 §49.3):** detail kegiatan — termasuk anggaran per kegiatan dan berkas RAB — tetap dibatasi unit karena nama kegiatan dapat memuat nama orang atau kasus; Perencanaan dan Pimpinan dapat melihat anggaran dan RAB seluruh unit. Total anggaran per IKU pada dashboard mengikuti akses dashboard.

### 15.2 Status & Siklus Hidup Kegiatan

**Kegiatan yang tidak terlaksana tidak dihapus** — statusnya diubah menjadi `tidak_terlaksana` (atau `ditunda`/`batal`) **dengan justifikasi wajib**. Bila kegiatan digeser ke periode berikutnya, dibuat **baris kegiatan baru** pada periode tujuan dengan `kegiatan_asal_id` menunjuk kegiatan asal. Konsekuensi yang disadari: kegiatan yang sama dapat tampil di dua periode (periode asal dan periode tujuan) — keduanya sah, dibedakan oleh status dan tautan `kegiatan_asal_id`.

**Kegiatan yang mundur ke periode lain (Q41 §41.4; US/ISS-06.04, 06.05):** saat pelaporan, PIC menandai kegiatan yang tidak dilaksanakan sesuai rencana sebagai `ditunda` dengan justifikasi dan periode tujuan. Sistem membuat kegiatan lanjutan pada periode tujuan yang tertaut ke kegiatan asal (`kegiatan_asal_id`), dan kegiatan lanjutan itu diklaim ke Rencana Aksi pada tahap pengukuran (`sumber_klaim = pengukuran`). Rencana Aksi yang sudah disahkan **tidak dibuka kembali** hanya untuk memindahkan kegiatan; justifikasi kegiatan menjadi catatan resmi yang dilihat Perencanaan saat verifikasi pengukuran. Kegiatan lanjutan menyalin anggaran kegiatan asal, dan anggarannya dapat diubah; kegiatan asal yang `ditunda` dan sudah memiliki kegiatan lanjutan tidak dihitung dalam total anggaran, sehingga anggarannya hanya terhitung pada kegiatan lanjutan (Q42 §42.2 butir 6). Kegiatan lanjutan hanya dapat ditujukan ke periode lain pada tahun yang sama; kegiatan yang mundur ke tahun berikutnya dicatat sebagai kegiatan baru pada Rencana Aksi tahun itu. Berkas kegiatan asal, termasuk RAB, tidak disalin, dan persyaratan tahap perencanaan kegiatan tidak diperiksa ulang untuk kegiatan lanjutan yang diklaim pada tahap pengukuran (Q41 §41.4 butir 5).

### 15.3 Narasi Kegiatan & Rekapitulasi

> **Diperbarui Q45 (13 Oktober 2026):** kolom "Progress Kegiatan / Kendala dan Masalah / Strategi Tindak Lanjut" pada rekapitulasi semula dihasilkan otomatis dari narasi per kegiatan; sejak Q45 sumbernya adalah narasi wajib per pengukuran (§20).

Narasi per kegiatan (`uraian_pelaksanaan`, `kendala`, `strategi_tindaklanjut`) tetap tersedia dan **opsional** (Q45 §45.1 butir 2). Kolom "Progress Kegiatan / Kendala dan Masalah / Strategi Tindak Lanjut" pada rekapitulasi per indikator × periode (§22.4) diambil dari narasi pengukuran indikator × periode tersebut (§20) dan ditampilkan bersama daftar kegiatan yang diklaim pada Rencana Aksi indikator itu (§16) beserta status dan narasi per kegiatan bila diisi.

### 15.4 Pembaruan & Batas Waktu

Kegiatan dapat diperbarui (status, realisasi, narasi) oleh PIC unitnya atau Perencanaan selama `jadwal_tahunan` belum `penutupan`; setelah penutupan hanya lewat `jadwal:buka_kembali`. Status kegiatan (terlaksana, tidak terlaksana, ditunda) dapat diperbarui sepanjang periode berjalan, misalnya setiap bulan; tidak ada entitas bulanan tersendiri (Q45 §45.1 butir 4). Dashboard menampilkan status tersebut secara real-time dengan label "belum diverifikasi" (§23, Q45 §45.3).

### 15.5 Kegiatan Gagal Tetap Dapat Diklaim

Kegiatan berstatus `batal`/`tidak_terlaksana` **tetap boleh diklaim** — kegiatan yang diklaim tapi gagal adalah bukti mengapa komponen tidak bergerak sesuai rencana. Statusnya ikut ditampilkan di rekapitulasi.

### 15.6 Gerbang Kelengkapan Bukti Dukung sebelum Terlaksana

**Persyaratan dokumen kegiatan dua tahap (Q42 §42.4; US/ISS-06.08):**

1. **Tahap perencanaan kegiatan** (`jenis_berkas.tahap = kegiatan_perencanaan`, mis. RAB) berlaku per kegiatan dan diperiksa saat Rencana Aksi diajukan, untuk setiap kegiatan yang diklaim pada tahap Rencana Aksi (§14.7).
2. **Tahap pelaksanaan kegiatan** (mis. undangan, dokumentasi, SP) memakai tahap `kegiatan` dan diperiksa saat kegiatan ditandai `terlaksana` (di bawah); Q22 tetap berlaku.
3. Kedua tahap ditetapkan Perencanaan di Persyaratan Berkas (§18.2–§18.3).

**RAB (Q42 §42.3):** RAB wajib diunggah untuk kegiatan dengan anggaran **lebih dari 0**, dalam format Excel atau PDF; kegiatan beranggaran 0 tidak wajib RAB. Persyaratan RAB ditandai `hanya_kegiatan_beranggaran` sehingga hanya diperiksa untuk kegiatan beranggaran lebih dari 0, sedangkan persyaratan lain pada tahap perencanaan kegiatan tetap berlaku untuk setiap kegiatan (Q42 §42.3 butir 3, §18.2). Aplikasi tidak membuat template RAB; Perencanaan dapat melampirkan **contoh berkas** generik pada persyaratan RAB, lalu PIC mengunduh contoh tersebut dan mengunggah RAB miliknya (§18.2).

Transisi status `rencana → terlaksana` **ditolak sistem** bila ada `jenis_berkas` aktif bertanda `wajib` bertahap `kegiatan` untuk kegiatan tersebut yang belum terpenuhi (§18.6) — pengertian "terpenuhi" mengikuti mode bukti yang dipilih (file terunggah, tautan terisi, atau teks terisi) dan aturan `semua_mode_wajib` pada persyaratan terkait (§18.5). Transisi ke `tidak_terlaksana`, `ditunda`, atau `batal` **tidak** memerlukan bukti pelaksanaan apa pun — syaratnya tetap hanya `justifikasi` (§15.1), karena kegiatan yang gagal dilaksanakan memang tidak memiliki pertanggungjawaban pelaksanaan (SPJ) untuk dilampirkan. Percobaan transisi ke `terlaksana` yang ditolak gerbang ini tercatat di audit log (§25.1).

Setelah status kegiatan berpindah ke `terlaksana`, bukti dukung yang menempel padanya tunduk pada aturan imutabilitas (§18.8) — tidak dapat dihapus atau diganti.

---

## 16. Klaim Kegiatan

### 16.1 Tabel `klaim_kegiatan`

- `id`, `rencana_aksi_id` (FK), `kegiatan_id` (FK).
- `komponen_id` (FK → `indikator_komponen`, **nullable** — kegiatan boleh diklaim sebagai pendukung Rencana Aksi tanpa menunjuk komponen tertentu).
- `arah_dampak` — enum(`menambah`, `mengurangi`), default `menambah`.
- `catatan` (varchar, nullable).
- `sumber_klaim` — enum(`rencana_aksi`, `pengukuran`).
- `pengukuran_id` (FK → pengukuran, nullable — diisi bila klaim dilakukan pada tahap pengukuran).
- `created_by`, `created_at`.

Constraint: unique pada (`rencana_aksi_id`, `kegiatan_id`, `komponen_id`) — implementasi index unik memakai `COALESCE(komponen_id, ...)` karena PostgreSQL memperlakukan NULL sebagai nilai berbeda pada unique constraint biasa.

### 16.2 Makna Klaim

Klaim menyatakan **kegiatan mana yang mendukung Rencana Aksi/indikator dan berdampak pada komponen mana** (menambah atau mengurangi). Klaim boleh dibuat **saat penyusunan Rencana Aksi** maupun **saat pengisian Pengukuran**; keduanya menulis ke tabel yang sama, dibedakan oleh `sumber_klaim` — jejaknya tercatat untuk audit.

- **Tahap Rencana Aksi (Q42 §42.1 butir 2):** kegiatan ditambahkan dari halaman Rencana Aksi per periode, dan aksi tersebut membuat kegiatan sekaligus klaimnya dengan `sumber_klaim = rencana_aksi` (§15.1); salinan kegiatan ke periode lain masing-masing memiliki klaim sendiri (Q49 §49.2). Klaim tahap ini membentuk daftar kegiatan per periode pada Rencana Aksi, sehingga PIC tidak dapat menambah atau menghapusnya pada periode yang terkunci (§12.3, Q41 §41.2 butir 2).
- **Tahap pengukuran:** kegiatan lanjutan dari kegiatan yang `ditunda` diklaim dengan `sumber_klaim = pengukuran` (§15.2, Q41 §41.4 butir 2).
- **Kegiatan bersama (Q42 §42.2 butir 4):** kegiatan yang diklaim lebih dari satu IKU ditampilkan penuh di setiap IKU dengan penanda "kegiatan bersama"; total anggaran per unit dan per tahun dihitung dari kegiatan unik (§15.1).

### 16.3 Klaim Tidak Mengubah Nilai Komponen Otomatis

**Klaim tidak mengubah nilai komponen secara otomatis.** Nilai komponen tetap diisi manual oleh PIC — menjumlahkan kegiatan secara otomatis berisiko penghitungan ganda (mis. satu PTS yang hadir di tiga kegiatan tetap harus dihitung satu kali pada komponen "jumlah PTS yang menerima fasilitasi"). Fungsi klaim: (a) dokumentasi dukungan dan arah dampak, (b) bahan rekapitulasi otomatis (§15.3, §22.4), (c) pengingat di layar pengisian — layar pengukuran menampilkan daftar kegiatan terkait periode itu sebagai pembanding saat PIC mengisi angka komponen.

### 16.4 Validasi Lintas Unit

`kegiatan.unit_id` harus berada pada unit yang sama dengan unit Rencana Aksi/indikator; klaim lintas unit ditolak sistem.

### 16.5 Penghapusan Klaim

Klaim dapat dihapus oleh PIC pembuat klaim atau Perencanaan selama induknya (Rencana Aksi) belum `disahkan`; penghapusan wajib beralasan (Q43 §43.2 butir 2) dan tercatat di `audit_log`. PIC tidak dapat menghapus klaim tahap Rencana Aksi pada periode yang terkunci (§12.3, Q41).

---

## 17. Komponen Indikator & Mesin Perhitungan

### 17.1 Filosofi: Perhitungan Data-Driven

Definisi perhitungan indikator bersifat **data-driven**, bukan hardcode per indikator. Cara hitung suatu indikator ditentukan oleh kombinasi `indikator.tipe_perhitungan` dan baris-baris `indikator_komponen` miliknya, yang dapat dikelola lewat aplikasi — termasuk saat Kepmen IKU dari kementerian berubah — tanpa penyesuaian kode. **Tipe perhitungan dan komponen adalah data, bukan aturan yang ditanam di kode**: menambah, mengubah, atau mengganti komponen untuk tahun berikutnya cukup lewat permission `komponen:create`/`read`/`update`/`delete` (§17.8) yang dipegang Perencanaan/Superadmin — tidak pernah memerlukan rilis kode aplikasi. Pemetaan contoh pengisian untuk delapan indikator tahun 2026 (delapan IKU Renstra; PK 2026 menambah IKU 4.1, Q50 §50.5) didokumentasikan sebagai lampiran (§17.11) berstatus **contoh pengisian awal**, bukan aturan kode.

### 17.2 Tabel `indikator_komponen`

- `id`, `indikator_id` (FK).
- `kode` (varchar — mis. `n`, `t`, `a`, `b`).
- `label` (text — mis. "n = respon pengguna layanan yang puas").
- `peran` — enum(`pembilang`, `penyebut`, `penjumlah`).
- `bobot` (numeric, not null, default 1).
- `urutan` (int), `satuan` (varchar, nullable), `aktif` (boolean, default true).
- `created_by`, `created_at`, `updated_at`.

Constraint: **`unique(indikator_id, kode)`**.

### 17.3 Tipe Perhitungan Indikator

Kolom `indikator.tipe_perhitungan` — enum(`rasio_persen`, `penjumlahan`, `manual`), not null, default `manual`:

- **`rasio_persen`**: nilai = (Σ(komponen `pembilang`ᵢ × bobotᵢ) ÷ (komponen `penyebut` × bobot)) × 100. Varian multi-suku seperti `(a + b) / t × 100` maupun berbobot seperti `Σ(nᵢ × kᵢ) / t × 100` tertutup oleh bentuk ini.
- **`penjumlahan`**: nilai = Σ(komponen `penjumlah`ᵢ × bobotᵢ) — mis. IKU jumlah dosen naik jabatan fungsional (Lektor + Lektor Kepala + Guru Besar).
- **`manual`**: nilai diketik langsung, komponen tidak wajib.

Validasi definisi: `rasio_persen` wajib punya **≥1 komponen `pembilang` aktif dan tepat 1 komponen `penyebut` aktif**; `penjumlahan` wajib punya **≥1 komponen `penjumlah` aktif**. Penyimpanan indikator dengan tipe itu ditolak sistem bila syarat ini tidak terpenuhi.

### 17.4 Pembagian Nol & Pembulatan

- Pembagian nol (penyebut bernilai 0) → nilai **tidak dapat dihitung** (disimpan `null` dengan `status_perhitungan = tidak_dapat_dihitung`, ditampilkan sebagai "tidak dapat dihitung"), bukan `0` dan bukan galat sistem. Status ini berbeda dari realisasi **belum dapat diukur** (`status_perhitungan = belum_dapat_diukur`, §20, Q47 §47.4).
- Pembulatan: nilai disimpan sesuai `indikator.presisi` dan ditampilkan sesuai `indikator.desimal_tampilan` (kolom lama tetap berlaku, §11).

### 17.5 Tabel `pengukuran_komponen`

- `id`, `pengukuran_id` (FK), `komponen_id` (FK), `nilai` (numeric, nullable), `updated_by`, `updated_at`.

Constraint: **`unique(pengukuran_id, komponen_id)`**.

### 17.6 Nilai Turunan pada Pengukuran

`pengukuran.nilai` menjadi **nilai turunan** bila `indikator.tipe_perhitungan` ≠ `manual`: dihitung sistem dari `pengukuran_komponen`, tidak dapat diketik PIC (read-only di UI). Kolom baru `sumber_nilai` — enum(`komponen`, `manual`) — mencatat asalnya untuk audit. Untuk `manual`, perilaku lama berlaku (`nilai` diketik, `0` sah, `null` = belum diisi).

### 17.7 Gerbang Kelengkapan Komponen

Pengajuan Pengukuran **ditolak** bila ada komponen aktif yang nilainya (`pengukuran_komponen.nilai`) masih `null` (kelengkapan komponen), di samping narasi pengukuran wajib (§20, Q45; semula aturan catatan wajib berbasis `arah` dan `wajib_catatan`, digantikan Q45) — lihat gerbang lengkap di §19.4. Gerbang kelengkapan komponen dikecualikan bila realisasi berstatus `belum_dapat_diukur` (§20, Q47 §47.4 butir 1) dan pada periode lampau (§12.6).

### 17.8 Pengelolaan Definisi Komponen

**Pengelolaan definisi komponen menggunakan permission `komponen:create`/`update`/`delete`** (Perencanaan dan Superadmin; `komponen:read` juga dimiliki Admin, Pimpinan, dan Pegawai agar label komponen tetap tampil di halaman yang berhak mereka buka; hak bawaan role PIC atas `komponen:read` tetap **OPEN** mengikuti preset permission Q31). Definisi komponen dapat diubah/ditambah lewat aplikasi — mis. saat Kepmen IKU dari kementerian berubah — **tanpa** penyesuaian hardcode kode aplikasi.

### 17.9 Pembekuan Cara Hitung di Snapshot

`jadwal_snapshot` ikut membekukan cara hitung: kolom baru `tipe_perhitungan`, `baseline` (numeric, nullable), `satuan`, plus tabel anak baru **`jadwal_snapshot_komponen`** (`jadwal_snapshot_id`, `kode`, `label`, `peran`, `bobot`, `urutan`) — lihat detail pembentukannya di §12.5. Baris snapshot tetap idempoten dan imutabel setelah dirujuk pengukuran, sehingga perubahan definisi komponen di tengah tahun tidak mengubah makna data historis.

### 17.10 Batas Mesin Perhitungan: Satu Tingkat

**Batas sadar Fase Awal:** mesin perhitungan mendukung **satu tingkat** perhitungan (rasio atau penjumlahan, dengan pembilang berjumlah banyak dan berbobot). Formula **bertingkat** (sub-skor → nilai komposit → digabung lagi) **tidak** dibangun; indikator semacam IKU "Predikat SAKIP dan Zona Integritas" memakai `tipe_perhitungan = manual` (Nilai SAKIP dan Nilai ZI diketik langsung sebagai angka, tanpa diturunkan ke sub-skor komponen penyusunnya). Bila kementerian menuntut formula bertingkat di kemudian hari, itu adalah perubahan yang membutuhkan pengembangan lanjutan — bukan sesuatu yang dapat dikonfigurasi dari layar pada Fase Awal. Batas ini yang membuat lampiran pemetaan pada §17.11 mencatat IKU 3 dengan `tipe_perhitungan = manual` alih-alih menjabarkan sub-skornya sebagai komponen.

### 17.11 Lampiran: Pemetaan Indikator 2026 ke Definisi Komponen

> **Catatan Q47 dan Q50 (13 Oktober 2026):** lampiran ini memakai nomor IKU Renstra. Menurut kode PK yang dipakai SAKIP (§2.4, Q50 §50.4), "IKU 3" adalah **IKU 1.3** dan ditandai dinilai tahunan (Q47 §47.1), sedangkan "IKU 8" adalah **IKU 3.3**. PK 2026 memuat sembilan indikator, termasuk IKU 4.1 pada sasaran S4 (Q50 §50.5). Renstra 2025–2029 tidak memuat formula, komponen, satuan, maupun baseline; formula indikator 2026 lainnya masih berupa asumsi kerja di dokumen Keputusan Penyelarasan yang hanya dipakai untuk data uji, dan komponen tidak disetel di produksi sebelum dikonfirmasi Perencanaan. Definisi, arah, dan target 2027–2029 IKU 3.3 pada Renstra juga masih menunggu klarifikasi Perencanaan.

#### 17.11.1 IKU 3 — Formula Final

IKU 3 memakai **dua skor input**:

| Kode | Label | Koefisien |
|---|---|---:|
| `sakip` | Skor SAKIP | 0,5 |
| `zi_wbk` | Skor ZI-WBK | 0,5 |

Formula final:

```text
(sakip + zi_wbk) / 2
```

Empat subskor lama (`perencanaan_kinerja`, `pengukuran_kinerja`, `pelaporan_kinerja`, `evaluasi_internal`) **bukan input terpisah IKU 3** dalam kontrak final ini.

Baseline IKU 3 tahun 2025 = **74,2** dan disimpan apa adanya karena berasal dari Nilai SAKIP saja. Target PK 2026 = **76,25** dan memakai basis gabungan SAKIP + ZI-WBK. Keduanya memiliki cakupan berbeda sehingga selisihnya tidak boleh ditampilkan sebagai tren/kenaikan kinerja.

Data TW II yang tidak boleh dianggap realisasi:

- `66,395` = skor komposit target revisi;
- `87,08` = hasil formula workbook yang rusak.

#### 17.11.2 IKU 8 — Formula Final

IKU 8 bertipe `rasio_persen`:

```text
n / t × 100%
```

Komponen `t` berlabel **“total publikasi seluruh PTS wilayah kerja”**. Angka 84 tidak boleh menjadi default/konstanta penyebut IKU 8. Nilai aktual `t` diisi Perencanaan dari sumber resmi. Target PK 59,5 dipakai apa adanya.

#### 17.11.3 Fixture dan Automated Test

Fixture test wajib membuktikan:

1. IKU 3 hanya memakai dua input final dan menghasilkan rata-rata berbobot 0,5 + 0,5;
2. baseline 74,2 dan target 76,25 tidak otomatis menghasilkan label tren;
3. 66,395 dan 87,08 tidak di-seed sebagai realisasi;
4. IKU 8 menghitung `n/t × 100%` tanpa hardcode 84;
5. division-by-zero mengikuti rule §17.4.

## 18. Bukti Dukung (Berkas Persyaratan)

### 18.1 Prinsip: Persyaratan Ditentukan Perencanaan, Cara Pemenuhan Dipilih PIC

Seluruh persyaratan bukti dukung ditetapkan **Tim Perencanaan** lewat permission `jenis_berkas:create`/`update`/`delete` (§7.2); PIC tidak dapat mengubah persyaratan, hanya memenuhinya. Persyaratan bersifat **fleksibel per kebutuhan**: Perencanaan menentukan (a) pada tahap apa persyaratan itu wajib, (b) **mode bukti yang diizinkan**, dan (c) apakah wajib atau opsional — tidak ada daftar mode yang di-hardcode per jenis persyaratan.

Sistem mengenal tiga mode bukti dukung: **`file`** (unggahan langsung), **`tautan`** (URL/tautan ke dokumen yang disimpan di tempat lain), dan **`teks`** (keterangan tertulis). Satu persyaratan dapat mengizinkan lebih dari satu mode; PIC memilih di antara yang diizinkan saat memenuhinya. Tujuannya memberi keleluasaan operasional sekaligus menjaga kapasitas penyimpanan — Perencanaan dapat menetapkan bahwa persyaratan tertentu cukup berupa tautan atau teks, dan hanya persyaratan yang memang perlu arsip fisik yang mewajibkan unggahan file.

**Kewenangan atas bukti dukung (diperbarui Q44):** substansi persyaratan (nama, tahap, wajib/opsional, mode yang diizinkan, `semua_mode_wajib`, per indikator) **beserta format dan ukuran maksimum per persyaratan** milik **Perencanaan**, ditetapkan di halaman Persyaratan Berkas yang berada pada grup menu **Perencanaan Kinerja** (Q44 §44.1 butir 1, §44.3 butir 2). Batas teknis unggahan ditegakkan server dari konfigurasi aplikasi, bukan dari UI (§18.9). Pembagian lama — kebijakan teknis unggahan (saklar unggahan, format default, ukuran maksimum default, dan halaman "Batas unggahan berkas") dipegang Admin/Superadmin lewat `pengaturan:update` — **dihapus Q44**. Permission tidak berubah, dan menu tetap tampil berdasarkan `can.*` dari server (Q44 §44.3 butir 3).

### 18.2 Tabel `jenis_berkas`

Persyaratan bukti dukung didefinisikan **Tim Perencanaan** lewat tabel `jenis_berkas`:

- `id`, `nama` (string).
- `tahap` — enum(`rencana_aksi`, `pengukuran`, **`kegiatan`**, **`kegiatan_perencanaan`**), not null. Nilai `kegiatan` menampung persyaratan pertanggungjawaban pelaksanaan kegiatan (SPJ); nilai `kegiatan_perencanaan` (baru, Q42 §42.4 butir 3) menampung persyaratan tahap perencanaan kegiatan, mis. RAB.
- `hanya_kegiatan_beranggaran` (boolean, not null, default `false`; baru, Q42 §42.3 butir 3) — bila `true`, persyaratan tahap `kegiatan_perencanaan` hanya diperiksa untuk kegiatan dengan anggaran lebih dari 0. Persyaratan RAB memakai penanda ini; persyaratan lain pada tahap yang sama tetap berlaku untuk setiap kegiatan.
- `indikator_id` (FK → indikator, **nullable** — `null` berarti berlaku untuk semua indikator).
- `wajib` (boolean, default false), `keterangan` (text, nullable).
- `izinkan_file` (boolean, not null, default `true`) — mode unggahan file diizinkan.
- `izinkan_tautan` (boolean, not null, default `false`) — mode tautan (URL) diizinkan.
- `izinkan_teks` (boolean, not null, default `false`) — mode keterangan teks diizinkan.
- `semua_mode_wajib` (boolean, not null, default `false`) — `false` berarti cukup **minimal satu** mode yang diizinkan terisi; `true` berarti **seluruh** mode yang diizinkan harus terisi.
- `urutan` (int, not null, default 0) — urutan tampil pada daftar persyaratan.
- `format_diizinkan` (varchar, nullable — mis. `pdf,docx,xlsx,jpg,png`) — hanya bermakna bila `izinkan_file = true`; ditetapkan Perencanaan lewat form persyaratan. Bila kosong, server memakai format default dari konfigurasi aplikasi (§18.9). Jalur ubah lewat halaman "Batas unggahan berkas" dihapus Q44.
- `ukuran_maks_kb` (int, nullable) — hanya bermakna bila `izinkan_file = true`; ditetapkan Perencanaan lewat form persyaratan dan **tidak boleh melebihi 102400** (100 MB), divalidasi saat persyaratan disimpan dan saat unggah. Bila kosong, server memakai default 10240 (10 MB) dari konfigurasi aplikasi (Q44 §44.2 butir 1; semula fallback dari `pengaturan` dan dapat diubah lewat halaman "Batas unggahan berkas", dihapus Q44).
- `aktif` (boolean, default true), `created_by`, `created_at`, `updated_at`.

**Validasi saat penyimpanan (Perencanaan):**

- Minimal satu dari `izinkan_file`/`izinkan_tautan`/`izinkan_teks` bernilai `true` — penyimpanan ditolak bila ketiganya `false`.
- `format_diizinkan` dan `ukuran_maks_kb` tetap tersimpan apa adanya tetapi tidak dipakai bila `izinkan_file = false`.
- `ukuran_maks_kb` di atas 102400 ditolak (Q44 §44.2 butir 1).
- Peringatan lama "persyaratan berpotensi tidak dapat dipenuhi" saat unggahan file dinonaktifkan pada setelan aplikasi **dihapus Q44**, karena unggahan file selalu aktif (§18.7).
- Alasan perubahan persyaratan bersifat opsional (Q43 §43.3 butir 3).

Sejak Q44, `format_diizinkan` dan `ukuran_maks_kb` hanya diubah Perencanaan lewat form persyaratan; jalur halaman "Batas unggahan berkas" milik Admin/Superadmin (§26.4) dihapus.

**Contoh berkas (Q42 §42.3 butir 2 dan 4, §42.4 butir 3):** persyaratan dapat dilampiri contoh berkas generik (mis. contoh RAB) melalui `berkas` berinduk `jenis_berkas` (§18.4); PIC mengunduh contoh tersebut lalu mengunggah berkas miliknya. Contoh berkas **bukan bukti** pemenuhan persyaratan: Perencanaan dapat menambah, mengganti, atau menghapusnya kapan saja, penghapusannya wajib alasan (Q43 §43.2 butir 2), dan contoh berkas tidak dibekukan ke versi pengajuan.

### 18.3 Tahap Persyaratan

**`tahap` adalah pilihan Perencanaan saat menetapkan persyaratan**: persyaratan bertahap `rencana_aksi` wajib dipenuhi saat penyusunan/pengajuan Rencana Aksi; persyaratan bertahap `pengukuran` wajib dipenuhi saat pengisian/pengajuan Pengukuran; persyaratan bertahap `kegiatan` (tahap pelaksanaan kegiatan) wajib dipenuhi sebelum status Kegiatan berpindah ke `terlaksana` (§15.6); persyaratan bertahap `kegiatan_perencanaan` (tahap perencanaan kegiatan, mis. RAB) berlaku per kegiatan dan diperiksa saat Rencana Aksi diajukan untuk setiap kegiatan yang diklaim pada tahap Rencana Aksi (§14.7, §15.6; Q42 §42.4) — kecuali persyaratan bertanda `hanya_kegiatan_beranggaran`, yang hanya diperiksa untuk kegiatan beranggaran lebih dari 0 (Q42 §42.3 butir 3). Satu indikator dapat memiliki persyaratan pada keempat tahap sekaligus.

### 18.4 Mode Bukti pada Tabel `berkas`

Tabel `berkas` menjadi wadah bukti dukung untuk **enam jenis induk** awal: `rencana_aksi`, `pengukuran`, `kegiatan`, **`renstra`**, **`renstra_pk`**, dan **`regulasi`**. Induk baru (rancangan): **`jenis_berkas`** untuk contoh berkas persyaratan (Q42 §42.4 butir 3), serta **`jadwal_tahunan`** dan **`jadwal_periode`** untuk arsip PDF bertanda tangan Rencana Aksi tahunan dan Laporan Kinerja Triwulan (Q48 §48.2 butir 1). Lampiran PK per versi mengikuti revisi PK berversi (Q40, Q48 §48.2 butir 1; tahap 3). Struktur:

- `id`, `jenis_berkas_id` (FK, **nullable** — `null` = lampiran bebas di luar daftar persyaratan; persyaratan `jenis_berkas` hanya berlaku untuk tiga induk pertama — `renstra`/`renstra_pk`/`regulasi` dan induk baru di atas selalu berupa lampiran bebas, §9.4, §10.6, §14.10).
- `berkasable_type` (`rencana_aksi` / `pengukuran` / `kegiatan` / `renstra` / `renstra_pk` / `regulasi`, ditambah `jenis_berkas` / `jadwal_tahunan` / `jadwal_periode`), `berkasable_id` (uuid).
- `mode` — enum(`file`, `tautan`, `teks`), not null — cara bukti dikirim. Mode dan seluruh aturan mode (§18.5) berlaku sama untuk seluruh induk.
- `tautan` (varchar 2048, nullable) — **wajib diisi** bila `mode = tautan`; divalidasi berskema `http`/`https`.
- `isi_teks` (text, nullable) — **wajib diisi** bila `mode = teks`.
- `nama_asli`, `path`, `mime`, `ukuran_bytes` — **nullable**; wajib hanya bila `mode = file`, bernilai `NULL` pada mode lain.
- `keterangan` (text, nullable; baru, Q48 §48.2 butir 6) — keterangan berkas yang bukan pengganti, mis. "Rencana Aksi perubahan" atau "PK perubahan anggaran"; dipakai juga oleh lampiran tambahan PK (Q50 §50.7). Keterangan penggantian tetap memakai `alasan_koreksi` pada berkas pengganti (Q25).
- `uploaded_by`, `created_at`.
- `dihapus_pada` (timestamp, nullable), `dihapus_oleh` (FK → users, nullable).

Satu persyaratan dapat dipenuhi oleh **lebih dari satu baris** `berkas` (mis. laporan sebagai file dan dokumentasi sebagai tautan), terutama bila `semua_mode_wajib = true`.

### 18.5 Validasi Mode & Kombinasi Pemenuhan

Mode yang dipilih PIC saat mengirim bukti **harus** termasuk mode yang diizinkan pada `jenis_berkas` terkait (`izinkan_file`/`izinkan_tautan`/`izinkan_teks`); permintaan dengan mode di luar daftar ditolak sistem. Lampiran bebas (tanpa `jenis_berkas_id`) boleh memakai mode apa pun.

Kelengkapan suatu persyaratan mengikuti `semua_mode_wajib` (§18.2): bila `false`, persyaratan dianggap terpenuhi begitu **minimal satu** mode yang diizinkan telah terisi (file terunggah, tautan tervalidasi, atau teks terisi); bila `true`, **seluruh** mode yang diizinkan pada persyaratan itu harus terisi. Aturan ini berlaku seragam pada seluruh gerbang kelengkapan (§18.6).

### 18.6 Gerbang Kelengkapan

- **Rencana Aksi & Pengukuran** (tidak berubah dari pola lama): transisi `draft → diajukan` **ditolak** bila ada `jenis_berkas` aktif bertanda `wajib` pada tahap tersebut yang belum terpenuhi untuk induknya — dengan pengertian "terpenuhi" kini mengikuti mode (§18.5), bukan semata unggahan file.
- **Perencanaan kegiatan (Q42 §42.4 butir 1 dan 4)**: pengajuan Rencana Aksi **ditolak** bila ada kegiatan berklaim tahap Rencana Aksi yang belum memenuhi `jenis_berkas` aktif bertanda `wajib` bertahap `kegiatan_perencanaan`, termasuk RAB untuk kegiatan beranggaran lebih dari 0 (§14.7, §15.6). Persyaratan yang diperiksa saat Rencana Aksi indikator X diajukan adalah persyaratan global ditambah persyaratan khusus indikator X; untuk kegiatan bersama, persyaratan khusus indikator lain diperiksa saat Rencana Aksi indikator tersebut diajukan.
- **Kegiatan**: transisi status `rencana → terlaksana` **ditolak** bila ada `jenis_berkas` aktif bertanda `wajib` bertahap `kegiatan` yang belum terpenuhi untuk kegiatan tersebut — lihat detail dan pengecualian justifikasi di §15.6.
- Cabang anti-macet unggahan — gerbang menghormati penanda `tidak_dapat_dipenuhi` akibat unggahan dimatikan — **dihapus Q44**, karena unggahan file selalu aktif (§18.7); seluruh gerbang di atas berlaku penuh.
- Gerbang bukti dukung dan gerbang perencanaan kegiatan dikecualikan untuk periode lampau (§12.6; Q46 §46.1 butir 1).
- Percobaan pengajuan/transisi yang ditolak salah satu gerbang ini tercatat di audit log (§25.1).

### 18.7 Aturan Anti-Macet: Penanda `tidak_dapat_dipenuhi` *(digantikan Q44)*

> **Digantikan Q44 (13 Oktober 2026):** saklar global unggahan dihapus sehingga unggahan file **selalu aktif**. Penanda `tidak_dapat_dipenuhi` akibat unggahan dimatikan tidak lagi terbentuk, dan cabang anti-macet pada gerbang lampiran PK, pengajuan Rencana Aksi, pengajuan pengukuran, dan penambahan bukti dihapus (Q44 §44.1 butir 2; US/ISS-13.06). Penanda yang sudah tercatat tetap tersimpan dan tampil sebagai **histori** pada rekapitulasi (§22.4).

Aturan semula (histori): bila unggahan file dinonaktifkan pada setelan aplikasi sementara suatu persyaratan hanya mengizinkan mode `file`, persyaratan itu ditandai `tidak_dapat_dipenuhi`, tercatat di `audit_log`, tampil pada rekapitulasi dan halaman kerja Perencanaan, dan tidak memblokir gerbang kelengkapan mana pun.

### 18.8 Imutabilitas & Penghapusan

**Imutabilitas mengikuti status induk, ditegakkan seragam untuk seluruh mode (`file`/`tautan`/`teks`) pada seluruh jenis induk:**

- lampiran **`rencana_aksi`**/**`pengukuran`** hanya dapat ditambah, diganti, atau dihapus selama induknya berstatus `draft`/`dikembalikan`; sejak `diajukan` koreksi bukti dilakukan melalui pengembalian beralasan, dan setelah `disahkan` lampiran imutabel (Q36);
- lampiran **`kegiatan`** tidak dapat dihapus setelah kegiatan berstatus `terlaksana` (§15.6); berkas yang dipakai memenuhi persyaratan tahap `kegiatan_perencanaan` (mis. RAB) tidak dapat dihapus sejak Rencana Aksi yang mengklaim kegiatan tersebut diajukan — koreksinya lewat pengembalian Rencana Aksi atau berkas pengganti (Q25), mengikuti pola bukti Rencana Aksi (Q36; Q42 §42.4 butir 5);
- lampiran **`renstra`** tidak dapat dihapus setelah Renstra berstatus `aktif` (§10.6), tetapi lampiran revisi tetap dapat ditambahkan selama Renstra berstatus `draft` atau `aktif` (Q40 §40.2 butir 2 dan 5);
- lampiran **`renstra_pk`** tidak dapat dihapus setelah Jadwal Tahunan tahun itu berstatus `aktif` (§14.10), tetapi lampiran tambahan untuk PK perubahan yang hanya mengubah anggaran tetap dapat ditambahkan (Q50 §50.7); setelah revisi PK berversi tersedia, lampiran versi sebelumnya tidak diubah (Q40 §40.1 butir 2);
- lampiran **`regulasi`** tidak dapat dihapus selama regulasi masih dirujuk oleh Renstra atau indikator aktif (§9.5);
- arsip dokumen resmi pada **`jadwal_tahunan`**/**`jadwal_periode`** tidak pernah dihapus; salah unggah dikoreksi dengan mengunggah berkas pengganti beserta keterangan, mengikuti pola append-only (Q48 §48.2 butir 3, Q25). Satu induk boleh memiliki lebih dari satu berkas, mis. Rencana Aksi tahunan awal dan perubahannya;
- contoh berkas pada **`jenis_berkas`** bukan bukti pemenuhan persyaratan dan tidak tunduk pada batas imutabilitas: Perencanaan dapat menambah, mengganti, atau menghapusnya kapan saja dengan alasan wajib saat menghapus, dan contoh berkas tidak dibekukan ke versi pengajuan (Q42 §42.3 butir 4).

Sebelum batas masing-masing tercapai, Perencanaan (dan pengunggah) dapat menghapus lampiran (untuk `rencana_aksi`/`pengukuran`: hanya selama induk `draft`/`dikembalikan`, Q36) dengan alasan wajib (Q43 §43.2 butir 2); setiap penghapusan tercatat di `audit_log` (soft delete lewat `dihapus_pada`). Setelah `penutupan` jadwal, koreksi atas lampiran `rencana_aksi`/`pengukuran`/`kegiatan` hanya lewat `jadwal:buka_kembali`. Pengecualian: unggahan arsip dokumen resmi pada `jadwal_tahunan`/`jadwal_periode` tetap dapat dilakukan setelah jadwal ditutup karena tidak mengubah data, status, maupun snapshot (Q48 §48.2 butir 4–5).

### 18.9 Batas Teknis Unggahan dan Penyimpanan Berkas (Q44)

> **Digantikan Q44 (13 Oktober 2026):** bagian ini semula berjudul "Kebijakan Storage pada Setelan Aplikasi" dan memuat kunci grup `berkas` pada tabel `pengaturan` (`berkas.unggahan_aktif`, `berkas.ukuran_maks_kb`, `berkas.format_diizinkan`, `berkas.tautan_selalu_diizinkan`) yang diubah pemegang `pengaturan:update`, beserta panel read-only "Penggunaan penyimpanan bukti dukung". Menu Kebijakan Storage (`/pengaturan/storage`), halaman "Batas unggahan berkas" (§26.4), saklar global unggahan, dan panel penggunaan penyimpanan **dihapus**; kunci grup `berkas` tidak lagi dibaca maupun dikelola UI dan hanya tersisa sebagai histori (Q44 §44.1; US/ISS-13.06, menggantikan US/ISS-11.06 yang dibatalkan).

- **Unggahan file selalu aktif** — tidak ada saklar unggahan (Q44 §44.1 butir 2).
- **Batas teknis ditegakkan server dari konfigurasi aplikasi, bukan dari UI** (Q44 §44.2):
  - default **10 MB** (10240 KB) dan format `pdf,docx,xlsx,jpg,jpeg,png` untuk lampiran tanpa persyaratan (Renstra, PK, Dasar Aturan, lampiran bebas kegiatan) dan untuk persyaratan yang mengosongkan format atau ukuran;
  - batas atas **100 MB** (102400 KB) per berkas yang tidak dapat dilampaui `jenis_berkas.ukuran_maks_kb`, divalidasi saat persyaratan disimpan dan saat unggah.
- Nilai tersebut sama dengan default dan batas maksimum halaman Kebijakan Storage sebelumnya; perubahannya dilakukan melalui konfigurasi deployment dan dicatat pada catatan rilis (Q44 §44.2 butir 2–3).
- Format dan ukuran maksimum per persyaratan ditetapkan Perencanaan di Persyaratan Berkas (§18.2) dan menimpa default konfigurasi. Daftar mode yang diizinkan per persyaratan tetap milik `jenis_berkas` (Perencanaan).
- Tampilan daftar berkas terunggah (semacam file manager) masuk backlog tahap 3 (Q44 §44.1 butir 3, Q46 §46.3).

**Batas tegas:** penyimpanan file fisik tetap privat di disk VPS (`storage/app/berkas/...`), diakses lewat route ber-permission (streamed download) — **bukan** URL publik (Q44 §44.1 butir 4).

### 18.10 Lampiran Bebas pada Kegiatan

Lampiran di level `kegiatan` tetap tersedia sebagai **lampiran bebas** (bukan persyaratan bergerbang, tanpa `jenis_berkas_id`) — bukti pelaksanaan kegiatan (daftar hadir, dokumentasi) menempel pada kegiatannya dalam mode apa pun, dan ikut tampil di rekapitulasi indikator × periode (§22.4). Unggahan file lampiran bebas mengikuti batas teknis default dari konfigurasi (§18.9, Q44 §44.2).

---

## 19. Alur Pengukuran

### 19.1 Status Alur (Fase Awal — 4 Status Efektif + 1 Jalur Revisi)

```
Draft ──ajukan──► Diajukan ──verifikasi──► Diverifikasi ──sahkan──► Disahkan
  ▲                    │                        │
  │                    │      kembalikan         │
  └────────────────────┴────────────────────────┘
```

| Status | Deskripsi | Siapa dapat mengubah |
|---|---|---|
| **Draft** | Nilai/catatan (atau nilai komponen) sedang disusun oleh penanggung jawab, belum diajukan | Pemegang `pengukuran:create`/`update` yang berhak atas indikator terkait (PIC lewat grant per unit, atau Perencanaan secara global lewat isi peran) |
| **Diajukan** | Penanggung jawab telah mengajukan data untuk direviu — lolos gerbang kelengkapan §19.4 | Transisi via `pengukuran:create`/`update` (aksi ajukan); tidak dapat diubah isinya langsung tanpa dikembalikan dulu |
| **Diverifikasi** | Perencanaan telah memeriksa dan menyatakan data secara teknis benar | Perencanaan (`pengukuran:verifikasi`) |
| **Dikembalikan** | Perencanaan mengembalikan data ke penanggung jawab untuk diperbaiki, **wajib menyertakan alasan** | Perencanaan (`pengukuran:kembalikan` dari Diajukan/Diverifikasi, atau `pengukuran:buka_kembali` dari Disahkan — §19.5); mengembalikan status secara efektif ke alur Draft bagi penanggung jawab |
| **Disahkan** | Status final — data ini yang dipakai dashboard/laporan sebagai capaian resmi | Perencanaan (`pengukuran:sahkan`) |

Pada Fase Awal, Perencanaan langsung mengesahkan setelah verifikasi, tanpa tahap approval Pimpinan. Urutan penuhnya: `Draft → Diajukan → Diverifikasi → Disahkan`, dengan `Dikembalikan` sebagai jalur balik dari `Diajukan`, `Diverifikasi`, atau `Disahkan` menuju revisi oleh penanggung jawab.

**Peringatan verifikasi (ketentuan pelengkap pada pengantar Q47–Q51; US/ISS-08.01):** saat Perencanaan memverifikasi pengukuran, sistem memperingatkan bila realisasi atau narasi pengukuran sama persis dengan periode sebelumnya. Peringatan ini tidak memblokir verifikasi. Saat verifikasi, Perencanaan juga menilai alasan realisasi belum dapat diukur (§20) dan dapat mengembalikan pengukuran bila alasannya tidak dapat diterima (Q47 §47.4 butir 2).

### 19.2 Approval Pimpinan (Fase Lanjutan — Belum Dibangun)

Permission `pengukuran:setujui` dan isi peran Pimpinan sudah didefinisikan di katalog (§7.2–7.3) untuk mengakomodasi Fase Lanjutan, tapi UI dan alur approval-nya belum dibangun. Pimpinan pada Fase Awal hanya memegang akses baca (dashboard, laporan, ekspor, audit, Rencana Aksi, Kegiatan, Berkas, Rekomendasi Pimpinan) dan tidak berperan dalam mengesahkan atau menyetujui Rencana Aksi maupun Pengukuran apa pun. Di luar akses baca tersebut, Pimpinan menulis dan menyetujui Rekomendasi Pimpinan melalui `rekomendasi:tetapkan` (§22.2; Q45, Q51).

### 19.3 Aturan Perubahan

- `pengukuran:create`/`pengukuran:update` untuk PIC diberikan lewat grant (`user_permission_granted`) yang wajib di-scope ke `unit_id` sesuai dengan `indikator.unit_id` terkait, dan tunduk pada batas jendela pengisian periode (§12.3). Untuk Perencanaan, permission ini bersumber dari isi peran — bersifat global dan hanya tunduk pada `penutupan` jadwal tahunan (§12.2).
- Transisi `diajukan → diverifikasi` dan `diverifikasi → disahkan` tunduk pada pemisahan tugas (§7.6): PIC yang mengajukan pengukurannya sendiri tidak dapat memverifikasi/mengesahkannya; Perencanaan yang mengisi atas nama unit diizinkan mengesahkan pengisiannya sendiri, dengan penanda `self_approval` tercatat di audit.
- Untuk indikator berkomponen, input dilakukan pada level `pengukuran_komponen` (§17.5); `pengukuran.nilai` ikut terisi otomatis sebagai nilai turunan (§17.6) dan bersifat read-only bagi PIC.
- Perubahan pada baris `pengukuran` yang sudah memiliki nilai menaikkan kolom `versi` (optimistic locking, lihat §27).
- Data pengukuran dengan nilai/catatan terisi tidak dapat dihapus permanen — pembatalan diwujudkan sebagai transisi status atau baris histori, bukan penghapusan baris (§4 prinsip 2).

### 19.4 Gerbang Keras Sebelum Pengajuan

Pengajuan Pengukuran (`Draft → Diajukan`) **ditolak sistem** bila salah satu syarat berikut tidak terpenuhi:

1. `rencana_aksi` untuk (indikator, tahun) belum berstatus `disahkan` (§14.9; tetap berlaku, Q41 §41.3 butir 1).
2. Ada `indikator_komponen` aktif milik indikator tersebut yang nilainya (`pengukuran_komponen.nilai`) masih `null` (§17.7) — dikecualikan bila realisasi berstatus `belum_dapat_diukur` (§20, Q47 §47.4 butir 1). Pada periode nilai akhir, status `belum_dapat_diukur` tidak dapat dipakai: realisasi wajib terisi, kecuali tidak dapat dihitung menurut Q9 (Q47 §47.4 butir 4).
3. Ada `jenis_berkas` aktif bertanda `wajib` pada tahap `pengukuran` untuk indikator tersebut yang belum terpenuhi untuk pengukuran ini (§18.6).
4. Narasi `progres_kegiatan`, `kendala_masalah`, atau `strategi_tindak_lanjut` kosong, atau `tindak_lanjut_rekomendasi` kosong padahal terdapat Rekomendasi Pimpinan berstatus `disetujui` pada periode sebelumnya untuk indikator yang sama (§20; Q45 §45.1, §45.2 butir 3; Q51 §51.1 butir 2).

Gerbang-gerbang ini **dikecualikan pada periode lampau** (§12.6; semula disebut jadwal retroaktif/backfill data historis), termasuk gerbang narasi dan tindak lanjut rekomendasi (Q46 §46.1 butir 1) — tujuan pengisian periode lampau memang mengisi data historis yang lazimnya tidak melalui proses Rencana Aksi/berkas normal pada masanya. Percobaan pengajuan yang ditolak oleh salah satu gerbang ini tetap dicatat di audit log (§25.1).

### 19.5 Koreksi Setelah Pengesahan

- **Sebelum penutupan jadwal**: Perencanaan/Superadmin dapat mengembalikan pengukuran yang sudah **Disahkan** ke status **Dikembalikan** melalui permission `pengukuran:buka_kembali`, dengan **alasan wajib** diisi. Jalur ini hanya tersedia sebelum `penutupan` jadwal tahunan tercapai.
- **Setelah penutupan jadwal**: satu-satunya jalur koreksi adalah `jadwal:buka_kembali` (§12.6) — membuka jadwal (`ditutup → aktif`), melakukan koreksi yang diperlukan (termasuk membuka kembali pengukuran individual bila perlu), lalu menutup ulang jadwal (`jadwal:tutup`). Seluruh rangkaian ini tercatat penuh di audit log.

---

## 20. Nilai, Narasi Pengukuran, dan Catatan

> **Diperbarui Q45 dan Q47 (13 Oktober 2026):** kewajiban `catatan` saat nilai memburuk atau saat `wajib_catatan = true` digantikan narasi pengukuran wajib (Q45 §45.1), dan realisasi dapat berstatus belum dapat diukur (Q47 §47.4). Status implementasi: belum (US/ISS-07.01, 07.03).

- `nilai` bertipe numerik, **nullable**: `null` berarti "belum diisi", sedangkan `0` adalah nilai sah (capaian nol adalah data valid, bukan representasi kekosongan). Untuk indikator dengan `tipe_perhitungan` selain `manual`, `nilai` bersifat read-only di UI karena diturunkan dari komponen (§17.6); PIC mengisi angka pada level komponen, bukan pada `nilai` langsung.
- **Realisasi belum dapat diukur (Q47 §47.4):** bila nilai suatu periode belum tersedia, pengukuran dapat diajukan dengan `status_perhitungan = belum_dapat_diukur` dan `nilai` kosong — nilai enum keempat di samping `belum_diisi`, `terhitung`, dan `tidak_dapat_dihitung` (§17.4) — bukan disimpulkan dari nilai kosong; label UI "Belum dapat diukur". Gerbang kelengkapan komponen dikecualikan (§17.7, §19.4). Narasi pengukuran tetap wajib dan menjelaskan mengapa nilai belum tersedia; Perencanaan menilainya saat verifikasi dan dapat mengembalikan pengukuran bila alasannya tidak dapat diterima. Status ini berbeda dari realisasi `0` (nilai sah) dan dari "tidak dapat dihitung" karena penyebut nol (Q9); dashboard dan rekap menampilkannya sebagai "Belum dapat diukur" dan tidak menghitungnya sebagai capaian 0. Pada periode nilai akhir, realisasi wajib terisi, kecuali tidak dapat dihitung menurut Q9.
- **Narasi pengukuran wajib (Q45 §45.1):** setiap pengukuran (indikator × periode) wajib memuat narasi **progres kegiatan** (`progres_kegiatan`), **kendala/masalah** (`kendala_masalah`), dan **tindak lanjut/strategi penyelesaian** (`strategi_tindak_lanjut`) saat diajukan; narasi diisi per indikator per periode. Narasi per kegiatan (§15.3) tetap tersedia dan opsional.
- **Tindak lanjut atas rekomendasi (Q45 §45.2 butir 3, Q51 §51.1 butir 2):** bila terdapat Rekomendasi Pimpinan berstatus `disetujui` pada periode N untuk indikator tersebut, pengukuran periode N+1 wajib memuat `tindak_lanjut_rekomendasi` saat diajukan. Rekomendasi berstatus draf tidak memicu kewajiban ini. Kewajiban berlaku lintas tahun: rekomendasi pada periode terakhir suatu tahun ditindaklanjuti pada periode pertama jadwal tahun berikutnya bila indikatornya masih diukur (Q51 §51.1 butir 4).
- **`catatan`** bertipe teks, nullable, dan menjadi keterangan tambahan **opsional** (Q45 §45.1 butir 3). Kewajiban lama — catatan wajib saat pengajuan bila nilai **memburuk menurut `indikator.arah`** dibandingkan pengukuran berstatus Disahkan terakhir secara kronologis (nilai stagnan dan pengukuran pertama tanpa pembanding tidak memicu kewajiban; perbandingan atas nilai turunan untuk indikator berkomponen), atau bila indikator memiliki flag `wajib_catatan = true` — **digantikan Q45** oleh narasi wajib di atas. Saklar `wajib_catatan` disembunyikan dari form indikator tanpa menghapus kolomnya (§11).
- Gerbang narasi dan tindak lanjut rekomendasi dikecualikan untuk periode lampau (§12.6; Q46 §46.1 butir 1).
- Validasi ini ditegakkan di sisi server (Form Request, Policy, dan service layer), bukan hanya di UI.

---

## 21. Status Capaian

### 21.1 Definisi

`status_capaian` menyatakan penilaian akhir atas satu baris `pengukuran`: `tercapai` atau `belum_tercapai`, dengan kolom `sumber` bernilai `manual` atau `data_sumber`. Penetapan status capaian adalah momen terpisah dari pengesahan pengukuran (`Disahkan`) — pengukuran dapat berstatus Disahkan tanpa status capaian aktif untuk sementara waktu, sampai Perencanaan/Superadmin menetapkannya secara manual.

### 21.2 Aturan

- Maksimal 1 status capaian aktif per pengukuran pada satu waktu; penetapan status baru tidak menghapus status lama — status lama tetap sebagai riwayat (soft replace, ditandai bukan-aktif, bukan dihapus).
- `ditetapkan_oleh` bernilai `null` hanya jika sumber adalah `data_sumber` (ditetapkan otomatis oleh sistem, bukan manusia).
- Permission: `status_capaian:update`.
- **Periode tanpa target (Q47 §47.3):** pada periode yang tidak ditargetkan (§14.3), capaian persen dan status capaian tidak dihitung. Tampilan menunjukkan realisasi, target PK, dan **selisih terhadap target PK** — realisasi − target PK dalam satuan indikator, yang tandanya dibaca menurut `arah` (§11).
- **Realisasi belum dapat diukur (Q47 §47.4):** pengukuran berstatus `belum_dapat_diukur` (§20) tidak dihitung sebagai capaian 0; tampilannya "Belum dapat diukur".
- Penetapan status capaian manual **tidak tersedia** untuk pengukuran pada periode tanpa target maupun pengukuran berstatus `belum_dapat_diukur` (Q47 §47.3 butir 4).
- Selisih terhadap target PK juga ditampilkan pada periode bertarget sebagai informasi tambahan; status capaian resmi tetap mengikuti aturan bagian ini (Q47 §47.3 butir 2).
- Status capaian dan selisih memakai target PK bernilai penuh yang disimpan (mis. 94,05), bukan nilai tercetak yang dibulatkan; tampilan mengikuti `desimal_tampilan` (Q50 §50.2).
- Capaian akhir tahun dinilai terhadap target **PK akhir**, yaitu versi PK yang berlaku pada periode nilai akhir (Q40 §40.1 butir 6; §12.7).

### 21.3 Fase Awal vs Fase Lanjutan

- **(Fase Awal)**: seluruh `status_capaian` diisi manual oleh pengguna berperan Perencanaan atau Superadmin melalui UI (`sumber = 'manual'` untuk semua baris). Tidak ada jalur otomatis apa pun yang mengisi status capaian pada Fase Awal.
- **(Fase Lanjutan — belum dibangun)**: kolom `sumber = 'data_sumber'` dan jalur integrasi otomatis dari sistem lain untuk mengisi status capaian tanpa intervensi manual. Kolom skema sudah disiapkan sejak MVP agar penambahan jalur ini tidak memerlukan migrasi besar.

---

## 22. Rekomendasi Pimpinan

### 22.1 Tabel `rekomendasi_pimpinan`

- `id`, `indikator_id` (FK), `tahun` (int), `periode_id` (FK).
- `isi` (text), `ditetapkan_oleh` (FK → users), `created_at`.
- `status` — enum(`draf`, `disetujui`) (rancangan Q51).
- `disetujui_oleh` (FK → users, nullable), `disetujui_oleh_role` (salinan kode peran penyetuju, untuk penanda persetujuan cadangan), `disetujui_pada` (timestamp, nullable) (rancangan Q51 §51.3).
- `rujukan_sumber` (nullable; rancangan Q51 §51.1 butir 5) — rujukan laporan triwulan sumber untuk rekomendasi data awal.

Pola "aktif": baris terbaru **berstatus `disetujui`** per (`indikator_id`, `tahun`, `periode_id`) adalah yang berlaku; baris lama tetap sebagai riwayat (soft replace, tidak dihapus) — sama seperti `status_capaian` (§21.2). Perubahan rekomendasi yang sudah disetujui membentuk baris baru dan melalui persetujuan yang sama (Q51 §51.2 butir 3). Draf tidak berlaku dan tidak tampil bagi PIC (Q51 §51.1 butir 2).

### 22.2 Pengisi dan Persetujuan

> **Digantikan Q45 dan Q51 (13 Oktober 2026):** ketentuan lama "pengisinya pada Fase Awal adalah Perencanaan, Pimpinan read-only" tidak berlaku lagi. Dibangun bersama US/ISS-09.01 pada tahap 2 agar Laporan Kinerja Triwulan TW IV 2026 memuat rekomendasi (Q46 §46.3, Q51 §51.3); status implementasi: belum.

1. Perencanaan menyusun **draf** rekomendasi per indikator × periode. Pimpinan **menyetujui** draf — dan dapat mengubah isinya sebelum menyetujui — atau menulis rekomendasi langsung; rekomendasi tetap baru berlaku setelah berstatus `disetujui` (Q51 §51.1 butir 1–2).
2. Permission tetap `rekomendasi:tetapkan` pada preset **Pimpinan** (diberikan melalui seeder, Q45 §45.2 butir 1) dan **Perencanaan**. Simpan draf dan setujui dibedakan oleh status rekomendasi, bukan oleh permission (Q51 §51.2 butir 1).
3. Persetujuan oleh Perencanaan adalah jalur **cadangan** bila Pimpinan berhalangan; persetujuan tersebut diberi penanda (dari `disetujui_oleh_role`) dan tercatat di audit. Penanda yang sama berlaku untuk persetujuan oleh peran lain selain Pimpinan, termasuk Superadmin (Q51 §51.2 butir 2).
4. Hanya rekomendasi **disetujui** yang berlaku: tampil pada pengukuran periode berikutnya dan Laporan Kinerja Triwulan (§24.1, Q48), serta memicu tindak lanjut wajib pada pengukuran periode berikutnya (§20; Q45 §45.2 butir 3).
5. Kewajiban tindak lanjut berlaku **lintas tahun**: rekomendasi pada periode terakhir suatu tahun ditindaklanjuti pada periode pertama jadwal tahun berikutnya bila indikatornya masih diukur (Q51 §51.1 butir 4).
6. Rekomendasi tetap opsional per indikator per periode (Q51 §51.1 butir 3).
7. Data awal 2026: rekomendasi TW I–II dari laporan triwulan yang sudah ditandatangani Pimpinan dicatat Perencanaan langsung berstatus `disetujui`, dengan penanda persetujuan cadangan dan rujukan laporan sumbernya pada kolom rancangan `rujukan_sumber` (Q51 §51.1 butir 5, §12.6).

### 22.3 Independensi dari Status Pengukuran

Rekomendasi Pimpinan melekat pada **indikator × periode**, terpisah dari `pengukuran` — dapat diisi setelah rapat evaluasi triwulan tanpa menunggu Pengukuran periode itu berstatus `disahkan`.

### 22.4 Rekapitulasi Indikator × Periode

**Rekapitulasi indikator × periode** (setara format kerja yang dipakai saat ini) wajib dapat ditampilkan dan diekspor, memuat: PIC, nama indikator, formula/cara hitung, baseline, target PK tahunan, target periode (Rencana Aksi; "Tanpa target periode" bila tidak ditargetkan, Q47), capaian periode atau "Belum dapat diukur" (Q47 §47.4), selisih terhadap target PK (Q47 §47.3), **nilai tiap komponen** (target dan realisasi), narasi pengukuran — progres kegiatan, kendala/masalah, tindak lanjut/strategi, dan tindak lanjut atas rekomendasi (§20, Q45) —, daftar kegiatan yang diklaim beserta status, anggaran, dan penanda "kegiatan bersama" (§15.1, Q42), narasi `uraian_pelaksanaan`/`kendala`/`strategi_tindaklanjut` per kegiatan bila diisi (opsional, Q45), dan Rekomendasi Pimpinan yang disetujui (§22.2, Q51). Anggaran per kegiatan mengikuti akses detail kegiatan (§15.1, Q42 §42.5). Penanda persyaratan bukti dukung `tidak_dapat_dipenuhi` (§18.7) hanya tampil sebagai histori karena tidak lagi terbentuk sejak Q44. Seluruh nilai turunan ditampilkan bersama angka pembentuknya, sehingga rekapitulasi tetap dapat ditelusuri kembali ke angka mentahnya. Dokumen resmi Laporan Kinerja Triwulan disusun dari data yang sama (§24.1, Q48).

---

## 23. Dashboard & Visualisasi

- Dashboard menampilkan ringkasan capaian berbasis data yang telah **Disahkan** (§19.1), tidak mencampur data Draft/Diajukan/Diverifikasi ke dalam angka capaian resmi.
- Visualisasi dibangun dengan ApexCharts (Fase Awal): grafik batang/garis perbandingan target vs realisasi per indikator/periode, ringkasan status capaian (Tercapai/Belum Tercapai) per Sasaran/Renstra.
- Panel tambahan: progres Rencana Aksi per indikator (status alur Draft/Diajukan/Diverifikasi/Disahkan), jumlah Kegiatan per status per periode (termasuk hitungan kegiatan `batal`/`tidak_terlaksana`), dan perbandingan target periode (Rencana Aksi) dengan realisasi komponen.
- **Status kegiatan real-time (Q45 §45.3):** dashboard menampilkan status kegiatan per periode secara real-time dengan label **"belum diverifikasi"**, dan memberi peringatan pada kegiatan yang melewati `tanggal_rencana` tetapi belum `terlaksana`. Angka capaian resmi tetap hanya dari data yang disahkan (§4 prinsip 1).
- **Anggaran per IKU (Q42 §42.2 butir 5, §42.5):** total anggaran per IKU ditampilkan dengan drill-down sampai kegiatan; total mengikuti akses dashboard, sedangkan anggaran per kegiatan pada drill-down mengikuti akses detail kegiatan (§15.1). Kegiatan bersama tampil penuh di setiap IKU yang mengklaimnya; kegiatan asal yang `ditunda` dan sudah memiliki kegiatan lanjutan tidak dihitung dalam total, dan total yang sudah dibekukan pada versi Rencana Aksi tidak berubah (Q42 §42.2 butir 6).
- **Selisih terhadap target PK (Q47 §47.3):** ditampilkan pada periode tanpa target sebagai pengganti capaian persen, dan pada periode bertarget sebagai informasi tambahan (§21.2).
- Status Capaian yang ditampilkan pada dashboard mencakup 7 kemungkinan tampilan (kombinasi status alur pengukuran dan status capaian; dua tampilan terakhir ditambahkan Q47):

| Status Tampilan | Kondisi |
|---|---|
| **Tercapai** | Pengukuran berstatus Disahkan dan `status_capaian.status = tercapai` |
| **Belum tercapai** | Pengukuran berstatus Disahkan dan `status_capaian.status = belum_tercapai` |
| **Belum ditetapkan** | Pengukuran Disahkan tetapi belum ada baris `status_capaian` aktif, di luar dua kondisi Q47 di bawah |
| **Belum mengisi** | Tidak ada baris `pengukuran` sama sekali untuk kombinasi indikator × periode yang diharapkan menurut `jadwal_periode` (§12.3) pada jadwal aktif. Indikator berstatus `arsip` tidak dihitung. |
| **Tidak mengisi** | Ada baris `pengukuran` namun tetap berstatus Draft melewati `pengisian_selesai` periode yang bersangkutan pada `jadwal_periode` (dianggap gagal mengisi tepat waktu) |
| **Tanpa target periode** | Pengukuran Disahkan pada periode yang tidak ditargetkan (§14.3) dan realisasinya tidak berstatus belum dapat diukur; capaian persen dan status capaian tidak dihitung, ditampilkan realisasi, target PK, dan selisih terhadap target PK — bukan "Belum tercapai" (Q47 §47.3) |
| **Belum dapat diukur** | Pengukuran Disahkan dengan `status_perhitungan = belum_dapat_diukur` (§20); tidak dihitung sebagai capaian 0 (Q47 §47.4) |

- Bila periode tanpa target dan realisasi belum dapat diukur berlaku pada pengukuran yang sama (mis. IKU 1.3 pada TW I–III), yang tampil "Belum dapat diukur" (Q47 §47.3 butir 3).
- Dashboard dapat difilter berdasarkan Renstra, Tahun, Periode, Sasaran, dan Unit.
- Akses dashboard: pada preset lama `dashboard:read` dimiliki Superadmin, Admin, Perencanaan, Pimpinan, dan Pegawai. Karena PIC operasional (bukan role) baru ditambahkan melalui Q31 sementara preset permission-nya belum dikonfirmasi, hak bawaan `dashboard:read` untuk PIC tetap **OPEN** dan tidak boleh diasumsikan.

---

## 24. Laporan & Ekspor

### 24.1 Fase Awal

- Laporan tabular (daftar pengukuran dengan filter Renstra/Tahun/Periode/Sasaran/Unit/Status) dapat diekspor ke format Excel — ini satu-satunya format ekspor pada Fase Awal.
- Rekapitulasi indikator × periode (§22.4) — satu baris per komponen, disertai narasi pengukuran, daftar kegiatan, dan Rekomendasi Pimpinan yang disetujui (Q45, Q51) — dapat ditampilkan dan diekspor Excel dengan format yang setara dengan format kerja yang dipakai saat ini.
- **Dokumen resmi (Q48; US/ISS-10.05, tahap 2):** SAKIP menghasilkan dua dokumen gabungan seluruh indikator untuk satu lembaga:
  - **Rencana Aksi tahunan** per jadwal tahunan — sasaran, indikator, satuan, target PK, target per periode seluruh indikator, serta daftar kegiatan per indikator per periode dari Rencana Aksi yang disahkan;
  - **Laporan Kinerja Triwulan** per periode — target PK, target periode, realisasi, dan capaian atau selisih terhadap target PK (Q47 §47.3) per indikator; analisis per indikator dari narasi pengukuran (§20, Q45 §45.1); dan Rekomendasi Pimpinan yang disetujui (§22.2, Q51). Status kegiatan per indikator dimuat sebagai lampiran tambahan.
  - Isi diambil dari data yang disahkan. Indikator yang Rencana Aksi atau pengukurannya belum disahkan tetap tercantum dengan keterangan **"belum disahkan"** tanpa angka resmi (Q48 §48.1 butir 2). Periode tanpa target dan realisasi belum dapat diukur memakai keterangan yang sama dengan dashboard ("Tanpa target periode", "Belum dapat diukur"; Q47).
  - Format awal Excel, mengikuti ekspor Fase Awal dan contoh keluaran yang disahkan Perencanaan (Q29); keluaran PDF tetap fase lanjutan (§24.2). Bila pembuatan berkas memerlukan pustaka baru, keputusannya dicatat sebagai ADR (Q48 §48.3).
  - Tabel Rincian Output dan realisasi anggaran per Rincian Output tetap disusun di luar SAKIP, dan dokumen PK tidak dihasilkan SAKIP (Q48 §48.1 butir 3–4, §2.4).
  - Keluaran dibuat dan diunduh pemegang `laporan:ekspor` (preset Perencanaan, Pimpinan, dan Superadmin; Q48 §48.1 butir 6). Detail kegiatan tetap dibatasi unit karena nama kegiatan dapat memuat nama orang atau kasus, sedangkan Rencana Aksi tahunan — yang memuat daftar kegiatan seluruh indikator — hanya dibuat dan diunduh pemegang `laporan:ekspor` (Q49 §49.3).
- **Unggah PDF bertanda tangan (Q48 §48.2):** dokumen ditandatangani elektronik di luar aplikasi, lalu PDF-nya diunggah kembali ke SAKIP sebagai arsip resmi:
  - Rencana Aksi tahunan pada jadwal tahunan (boleh lebih dari satu berkas, mis. Rencana Aksi awal dan perubahan) dan Laporan Kinerja Triwulan pada periode jadwal (§18.4); PK tetap diunggah pada lampiran PK, dan per versi setelah revisi berversi tersedia (§14.10, Q40);
  - arsip Rencana Aksi tahunan dan Laporan Kinerja Triwulan hanya menerima PDF, dengan ukuran mengikuti batas teknis unggahan (Q48 §48.2 butir 7, §18.9, Q44 §44.2);
  - keterangan berkas yang bukan pengganti (mis. "Rencana Aksi perubahan") disimpan pada `berkas.keterangan`, sedangkan `alasan_koreksi` khusus untuk berkas pengganti (Q48 §48.2 butir 6);
  - unggahan Rencana Aksi tahunan dan Laporan Kinerja Triwulan dilakukan Perencanaan dengan permission baru **`laporan:unggah`** (§7.2); unduhannya mengikuti `laporan:ekspor`; lampiran PK tetap mengikuti permission PK;
  - berkas yang sudah diunggah tidak dihapus; salah unggah dikoreksi dengan mengunggah berkas pengganti beserta keterangan, mengikuti pola append-only (Q25);
  - unggahan tidak mengubah data, status, maupun snapshot, dan tercatat di audit (§25.1); karena itu unggahan arsip **tetap dapat dilakukan setelah jadwal tahunan ditutup** — penutupan jadwal (ISS-14.01) hanya menghentikan mutasi data kinerja (Q48 §48.2 butir 4–5, §12.2).
- Permission: `laporan:read`, `laporan:ekspor`, dan `laporan:unggah` (Q48).

### 24.2 Fase Lanjutan (Belum Dibangun)

- Ekspor rekap ke PDF (mis. untuk lampiran laporan formal/LKj), termasuk keluaran PDF dokumen resmi Q48 (Q48 §48.1 butir 5). Unggah PDF bertanda tangan yang dibuat di luar aplikasi sudah termasuk Fase Awal (§24.1).
- Ekspor gambar grafik dashboard (mis. PNG chart ApexCharts) untuk disisipkan ke dokumen presentasi/laporan.

---

## 25. Audit & Histori

### 25.1 Cakupan Pencatatan (Full Sejak Fase Awal)

Audit log tidak dipersempit pada Fase Awal — seluruh peristiwa berikut dicatat sejak hari pertama:

- Perubahan isi peran (`role_permissions`), penetapan/pergantian peran pengguna (`user_roles`), pemberian izin (`user_permission_granted`), dan pencabutan izin (`user_permission_denials`) — seluruhnya lewat permission `akses:update` — serta penonaktifan dan pengaktifan kembali akun (Q38). Pemberian Grant Unit tanpa alasan tercatat dengan alasan baku dari server (Q43 §43.3 butir 1).
- Pembuatan/perubahan/penghapusan `regulasi` (`nilai_lama`/`nilai_baru` dan `alasan` untuk `update`/`delete`), perubahan `renstra.regulasi_id`/`indikator.regulasi_id`, dan pengiriman/penghapusan lampiran dokumen dasar aturan (§9.8).
- CRUD dan perubahan status Renstra, termasuk revisi in-place akibat Kepmen IKU baru (`dasar_hukum`, `regulasi_id`, atau atribut lain, dengan `alasan` memuat rujukan Kepmen — §10.5), serta pengiriman/penghapusan lampiran dokumen Renstra (§10.6).
- CRUD Sasaran dan Indikator, termasuk perpindahan indikator antar unit, perubahan `tipe_perhitungan` indikator, perubahan penanda `dinilai_tahunan` (Q47 §47.1), perubahan `regulasi_id`, dan pengarsipan indikator akibat IKU dihapus dari Kepmen.
- Pengisian dan koreksi Perjanjian Kinerja (koreksi sesudah PK dipakai jadwal aktif wajib beralasan; perubahan sebelumnya boleh tanpa alasan, Q43), pengiriman/penghapusan lampiran dokumen PK termasuk lampiran tambahan PK perubahan anggaran (§14.10, Q50 §50.7), serta penerbitan versi PK hasil revisi berversi (§12.7, Q40; tahap 3).
- Perubahan target PK (`target_tahunan`, termasuk `alasan_deviasi_renstra`) dan target Renstra — perubahan target Renstra saat Renstra `aktif` wajib beralasan dan tampil sebagai riwayat revisi target Renstra (§10.5, §11; Q39, Q40 §40.2).
- Penugasan dan pergantian Penanggung Jawab (alasan tidak lagi diminta di form, Q43 §43.3 butir 4).
- Pembuatan/perubahan/pengajuan/verifikasi/pengembalian/pengesahan/buka-kembali `rencana_aksi`.
- Pembuatan/perubahan/penghapusan/perubahan-status Kegiatan (termasuk perubahan `anggaran` (Q42), salinan ke periode lain (Q49), penandaan geser periode dan kegiatan lanjutan (Q41 §41.4), pengisian justifikasi, dan transisi ke `terlaksana` yang digerbangi kelengkapan bukti dukung tahap `kegiatan`, §15.6).
- Penambahan dan penghapusan Klaim Kegiatan.
- Penambahan/perubahan/nonaktivasi Komponen Indikator (nilai lama/baru).
- Penetapan/perubahan persyaratan `jenis_berkas` — termasuk perubahan tahap (termasuk `kegiatan_perencanaan`), mode bukti yang diizinkan (`izinkan_file`/`izinkan_tautan`/`izinkan_teks`), `semua_mode_wajib`, `format_diizinkan`, `ukuran_maks_kb`, dan contoh berkas — dengan `nilai_lama`/`nilai_baru` dan `alasan` bila diisi (opsional sejak Q43 §43.3 butir 3).
- Pengiriman bukti dukung (`berkas`), mencatat `mode` dan sumbernya: untuk mode `file` — nama asli, ukuran, mime; untuk mode `tautan` — tautan tujuan; untuk mode `teks` — panjang teks (bukan salinan isinya). Penghapusan bukti dukung turut tercatat.
- Unggahan arsip PDF bertanda tangan Rencana Aksi tahunan dan Laporan Kinerja Triwulan beserta berkas penggantinya (Q48 §48.2). `laporan:unggah` tidak bertanda sensitif, tetapi setiap unggahan tetap tercatat di audit (Q48 §48.2 butir 2 dan 4).
- *(Histori, digantikan Q44)* Penandaan persyaratan sebagai `tidak_dapat_dipenuhi` (§18.7), perubahan kunci setelan grup `berkas`, dan perubahan `format_diizinkan`/`ukuran_maks_kb` lewat halaman "Batas unggahan berkas" — tidak lagi terjadi karena saklar unggahan, kunci grup `berkas`, dan halaman tersebut dihapus; baris audit lama tetap tersimpan.
- Penyusunan draf, perubahan, dan persetujuan Rekomendasi Pimpinan, termasuk penanda persetujuan cadangan oleh Perencanaan (Q51 §51.2).
- Seluruh transisi status pada alur Pengukuran (ajukan, verifikasi, kembalikan beserta alasan, sahkan, buka kembali setelah pengesahan beserta alasan).
- Aktivasi, penutupan, dan buka kembali Jadwal Tahunan, termasuk pembuatan baris `jadwal_snapshot` dan `jadwal_snapshot_komponen` (dengan `actor_id` menempel pada pelaku aktivasi).
- Penetapan/penggantian Status Capaian.
- Percobaan tindakan yang ditolak sistem — termasuk gerbang Rencana Aksi belum disahkan, komponen belum lengkap, narasi pengukuran atau tindak lanjut rekomendasi kosong (Q45), bukti dukung wajib belum terpenuhi (rencana aksi/pengukuran/kegiatan/perencanaan kegiatan), Rencana Aksi tanpa kegiatan atau tanpa RAB (Q42), perubahan periode terkunci oleh PIC (Q41 §41.2 butir 2), penolakan akibat resolusi izin (fail closed atau deny yang cocok, §7.4), dan percobaan penghapusan yang ditolak — serta penghapusan yang berhasil dilakukan (mis. unit kosong yang dihapus Superadmin).
- Pembuatan dan penggunaan Unit (create/update/delete).
- Perubahan setelan aplikasi (`pengaturan:update` — nilai lama/baru per kunci, lihat §26.5). Kunci grup `berkas` tidak lagi dikelola UI sehingga tidak diaudit sebagai setelan; perubahan batas teknis unggahan dicatat pada catatan rilis konfigurasi deployment (§18.9, Q44 §44.2 butir 3).

### 25.2 Struktur Pencatatan

Setiap baris `audit_log` menyimpan: `actor_id`, `waktu`, `tindakan`, `objek_tipe`, `objek_id`, `nilai_lama` (JSON, nullable), `nilai_baru` (JSON, nullable), `alasan` (teks, wajib hanya untuk tindakan yang tercantum pada §4 prinsip 6 sesuai Q43 §43.2 — antara lain pengembalian dan buka-kembali, penghapusan data atau berkas, koreksi atau revisi data yang sudah sah termasuk koreksi PK dan revisi Renstra, perubahan akses, serta justifikasi kegiatan dan alasan deviasi; opsional untuk tindakan pada Q43 §43.3, dengan alasan baku dari server untuk pemberian Grant Unit tanpa alasan; pergantian penanggung jawab tidak lagi meminta alasan), dan **`dasar_izin`** (JSON, nullable) — khusus aksi dengan `permissions.sensitif = true` (§7.2), memuat daftar sumber izin yang mengizinkan aksi tersebut (peran mana/grant mana) atau deny yang memicu penolakan (§7.4). Transisi verifikasi/pengesahan Pengukuran yang dilakukan Perencanaan atas pengisiannya sendiri menyertakan penanda **`self_approval`** (§7.6). Perubahan definisi komponen dan persyaratan `jenis_berkas` (termasuk mode bukti dan `semua_mode_wajib`) dicatat dengan `nilai_lama`/`nilai_baru` dan `alasan` (opsional untuk persyaratan berkas sejak Q43 §43.3 butir 3) — inilah jejak yang menjaga data historis tetap dapat dipertanggungjawabkan meski formula dan persyaratan bukti dukung dapat diubah dari layar. Persetujuan Rekomendasi Pimpinan oleh Perencanaan sebagai cadangan menyertakan penanda persetujuan cadangan (Q51 §51.2 butir 2).

### 25.3 Sifat Append-Only

Tabel `audit_log` append-only — tidak ada endpoint untuk mengubah atau menghapus baris audit, baik melalui UI maupun API internal.

### 25.4 Visibilitas per Role

- `audit:read` dimiliki Superadmin, Perencanaan, dan Pimpinan.
- Pegawai tidak memiliki akses baca audit log secara umum pada Fase Awal (tidak termasuk dalam isi peran `pegawai`). Hak bawaan role PIC atas `audit:read` belum dikonfirmasi dan tetap **OPEN** pada Q31.

---

## 26. Setelan Aplikasi

### 26.1 Tabel `pengaturan`

Modul bergaya key-value untuk parameter aplikasi yang bersifat presentasional, disimpan pada tabel `pengaturan`: `kunci` (unique), `nilai`, `tipe`, `grup`, `updated_by`, `updated_at`.

### 26.2 Nilai Default & Pembacaan

Nilai default di-seed saat instalasi aplikasi. Pembacaan nilai dilakukan lewat accessor dengan cache, sehingga tidak membebani setiap render halaman dengan query berulang ke tabel `pengaturan`.

### 26.3 Kunci Awal

- Identitas instansi: nama instansi, alamat, telepon, surel, laman, logo.
- Identitas aplikasi: nama aplikasi, label unit (memungkinkan penyebutan "Unit" diganti istilah lain di UI tanpa mengubah struktur data — lihat §7.5).
- Preferensi tampilan/laporan: zona waktu, format tanggal, format angka, header/footer ekspor.
- *(Dihapus Q44)* Batas unggahan berkas pada grup kunci `berkas` beserta panel read-only "Penggunaan penyimpanan bukti dukung" tidak lagi menjadi bagian Setelan Aplikasi. Default format dan ukuran unggahan kini berasal dari konfigurasi aplikasi di server (§18.9); kunci grup `berkas` yang sudah tercatat hanya tersisa sebagai histori.

### 26.4 Halaman "Batas Unggahan Berkas" *(dihapus Q44)*

> **Dihapus Q44 (13 Oktober 2026; US/ISS-13.06):** halaman ini dan menu Kebijakan Storage tidak lagi tersedia, dan route lamanya tidak lagi melayani halaman tersebut (Q44 §44.1 butir 1). Format dan ukuran maksimum per persyaratan ditetapkan **Perencanaan** lewat form Persyaratan Berkas (§18.2); batas teknis default dan batas atas 100 MB ditegakkan server dari konfigurasi aplikasi (§18.9). Riwayat audit perubahan lewat halaman ini tetap tersimpan.

Ketentuan semula (histori): halaman di dalam Setelan Aplikasi, digerbangi `pengaturan:update` (Admin dan Superadmin), yang mendaftar seluruh `jenis_berkas` dan hanya menerima perubahan `format_diizinkan` dan `ukuran_maks_kb` per persyaratan, dengan validasi ukuran minimal, peringatan bila daftar format menyempit, dan audit per persyaratan. Pembagian kewenangan lama — kebijakan teknis unggahan milik Admin/Superadmin, substansi persyaratan milik Perencanaan — tidak berlaku lagi.

### 26.5 Hak Akses

Permission `pengaturan:update` dimiliki **Admin dan Superadmin** (§7.3) — tidak ada peran lain yang memilikinya lewat isi peran maupun dianjurkan mendapatkannya secara eksplisit. Pembacaan nilai setelan terjadi otomatis saat render halaman, tanpa memerlukan permission baca khusus.

### 26.6 Audit

Setiap perubahan setelan tercatat di `audit_log` (nilai lama/baru per kunci) — masuk ke daftar peristiwa teraudit (§25.1).

### 26.7 Batas Tegas

Yang boleh dikelola secara dinamis lewat modul ini **hanya teks dan preferensi presentasional**. Sejak Q44, modul ini tidak lagi memuat kunci grup `berkas` maupun halaman "Batas unggahan berkas" (§26.3–§26.4): batas teknis unggahan ditetapkan konfigurasi aplikasi di server (§18.9), sedangkan format dan ukuran maksimum per persyaratan ditetapkan Perencanaan di Persyaratan Berkas (§18.2). Modul ini secara eksplisit **bukan** tempat mengubah enum/status, nama permission, atau aturan bisnis apa pun — perubahan semacam itu tetap memerlukan perubahan kode dan migrasi basis data, bukan lewat UI setelan. Daftar mode bukti yang diizinkan per persyaratan dan seluruh kolom substantif lainnya tetap milik `jenis_berkas` (Perencanaan), bukan milik modul ini. Alasan perubahan setelan bersifat opsional (Q43 §43.3 butir 3). Kustomisasi tampilan halaman login Keycloak (branding, logo login) berada di luar cakupan aplikasi SAKIP — diatur di level realm Keycloak, bukan lewat modul ini.

---

## 27. Integritas Data

- **Optimistic locking**: kolom `versi` pada `pengukuran` dan `rencana_aksi` dinaikkan setiap kali baris diubah. Permintaan update yang menyertakan versi usang ditolak (mencegah dua pengguna menimpa perubahan satu sama lain tanpa disadari) dan mengharuskan klien memuat ulang data terbaru sebelum mencoba lagi.
- **Unique constraint kunci**:
  - `pengukuran`: unique(`indikator_id`, `tahun`, `periode_id`) — mencegah duplikasi baris pengukuran untuk kombinasi yang sama.
  - `pengukuran_komponen`: unique(`pengukuran_id`, `komponen_id`).
  - `rencana_aksi`: unique(`indikator_id`, `tahun`).
  - `rencana_aksi_target`: unique(`rencana_aksi_id`, `periode_id`, `komponen_id`).
  - `indikator_komponen`: unique(`indikator_id`, `kode`).
  - `klaim_kegiatan`: unique pada (`rencana_aksi_id`, `kegiatan_id`, `komponen_id`) memakai index dengan `COALESCE(komponen_id, ...)` karena PostgreSQL memperlakukan NULL sebagai nilai berbeda.
  - `target_tahunan`: unique(`indikator_id`, `tahun`).
  - `renstra_pk`: unique(`renstra_id`, `tahun`) — karena `nomor_pk` opsional, PK dikenali dari pasangan ini dan tanggal PK (Q50 §50.1).
  - `regulasi`: unique(`jenis`, `nomor`, `tahun`).
  - `jadwal_tahunan`: unique(`renstra_id`, `tahun`) lintas seluruh status (`draft`, `aktif`, `ditutup`), sesuai §12.2 dan addendum ISS-03.01.
  - `jadwal_periode`: unique(`jadwal_id`, `periode_id`).
  - `jadwal_snapshot`: unique(`jadwal_id`, `indikator_id`) — menegakkan idempotensi pembentukan snapshot (§12.5); `jadwal_snapshot_komponen` mengikuti idempotensi induknya.
  - `permissions.kode`: unique.
  - `role_permissions`: unique(`role_id`, `permission_id`).
  - `user_roles`: unique(`user_id`) — satu peran per pengguna pada Fase Awal.
  - `user_permission_granted`: unique(`user_id`, `permission_id`, `unit_id`) — implementasi index unik memakai `COALESCE(unit_id, ...)` karena PostgreSQL memperlakukan NULL sebagai nilai berbeda.
  - `user_permission_denials`: unique(`user_id`, `permission_id`, `unit_id`) dengan pola `COALESCE` yang sama.
  - `users.keycloak_id`: unique — satu identitas Keycloak terhubung ke satu akun SAKIP.
  - `pengaturan.kunci`: unique.
- Anjuran PostgreSQL exclusion constraint (`EXCLUDE USING gist` + `btree_gist`) untuk rentang tahun Renstra yang beririsan tetap berlaku sebagai lapisan pertahanan kedua (§12.4); validasi utama ditegakkan di service layer.
- Tidak ada penghapusan permanen untuk data bermakna (pengukuran/rencana aksi dengan nilai/catatan, kegiatan yang sudah berjalan, riwayat penanggung jawab, riwayat status capaian, riwayat PK, baris snapshot yang telah dirujuk, berkas yang telah diunggah pada induk yang belum disahkan mengikuti soft delete) — lihat §4 prinsip 2 dan 3.
- Renstra dengan rentang tahun beririsan tidak boleh sama-sama berstatus `aktif` (§10.2); Renstra tidak dapat dinonaktifkan selama ada jadwal aktif yang merujuknya (§10.4).

---

## 28. Alert Kontekstual

Pada Fase Awal, alert kontekstual mencakup notifikasi/tampilan dalam aplikasi (bukan email/push eksternal, kecuali diputuskan lain kemudian) untuk:

- *(Dihapus Q41)* Tenggat penyusunan Rencana Aksi (`rencana_aksi_mulai/selesai` pada `jadwal_tahunan`, §12.2) tidak lagi dipakai karena Rencana Aksi tidak memiliki jendela penyusunan; kunci periode Rencana Aksi bagi PIC mengikuti tenggat `pengisian_selesai` di bawah.
- Tenggat `pengisian_selesai` per periode (`jadwal_periode`, §12.3) yang mendekat/terlewati bagi PIC yang belum mengisi (status "Belum mengisi"/"Tidak mengisi", lihat §23).
- Rencana Aksi atau Pengukuran yang dikembalikan — notifikasi ke penanggung jawab terkait beserta alasan pengembalian, baik jalur normal maupun buka kembali setelah pengesahan.
- Rencana Aksi atau Pengukuran yang telah diajukan dan menunggu verifikasi — notifikasi/daftar tugas untuk Perencanaan.
- Kelengkapan bukti dukung wajib (rencana aksi/pengukuran/kegiatan) yang belum terpenuhi menjelang batas waktu pengajuan/perubahan status.
- *(Dihapus Q44)* Alert persyaratan bukti dukung yang ditandai `tidak_dapat_dipenuhi` (§18.7) tidak lagi diperlukan karena unggahan file selalu aktif dan penanda tersebut tidak lagi terbentuk.
- Kegiatan berstatus `tidak_terlaksana`/`ditunda`/`batal` yang belum diisi justifikasinya.
- Kegiatan yang melewati `tanggal_rencana` tetapi belum `terlaksana` — diberi peringatan pada dashboard (§23, Q45 §45.3).
- PK tahun berjalan yang belum tercatat menjelang jendela aktivasi jadwal — pengingat bagi Perencanaan agar tidak terhambat validasi aktivasi (§10.3, §12.4).

---

## 29. Model Data (Ringkasan)

Model data lengkap (ERD dan detail kolom) didokumentasikan secara terpisah pada **SAKIP - Data Model.md**. Ringkasan kelompok entitas pada Fase Awal:

- **Identitas & Akses**: `users`, `unit`, `roles`, `permissions`, `role_permissions`, `user_roles`, `user_permission_granted`, `user_permission_denials`.
- **Dasar Aturan**: `regulasi`.
- **Strategi & Kinerja**: `renstra`, `renstra_pk`, `sasaran`, `indikator`, `target_tahunan` (target PK), serta rancangan `target_renstra` (Q39) dan `renstra_pk_versi`/`renstra_pk_versi_target` (revisi PK berversi, Q40; tahap 3).
- **Periode & Jadwal**: `periode`, `jadwal_tahunan`, `jadwal_periode`, `jadwal_snapshot`, `jadwal_snapshot_komponen`.
- **Rencana & Kegiatan**: `rencana_aksi`, `rencana_aksi_target`, `kegiatan`, `klaim_kegiatan`.
- **Komponen & Perhitungan**: `indikator_komponen`, `pengukuran_komponen`.
- **Berkas**: `jenis_berkas`, `berkas` (induk baru `jenis_berkas`, `jadwal_tahunan`, dan `jadwal_periode`; Q42, Q48).
- **Eksekusi & Akuntabilitas**: `penanggung_jawab`, `pengukuran`, `status_capaian`, `rekomendasi_pimpinan`, `audit_log`.
- **Konfigurasi**: `pengaturan`.
- **Kolom baru atau berubah Q39–Q51 (rancangan; rincian di Data Model):** `target_tahunan.alasan_deviasi_renstra` (Q39); `renstra_pk.nomor_pk` opsional (Q50); `indikator.dinilai_tahunan` beserta salinannya pada `jadwal_snapshot` (Q47); nilai `status_perhitungan = belum_dapat_diukur` dan narasi `progres_kegiatan`, `kendala_masalah`, `strategi_tindak_lanjut`, `tindak_lanjut_rekomendasi` pada `pengukuran` (Q45, Q47); `kegiatan.anggaran` wajib untuk data baru, `nama` hingga 500 karakter, dan `tujuan` nullable (Q42, Q49); `jenis_berkas.tahap` = `kegiatan_perencanaan` dan `jenis_berkas.hanya_kegiatan_beranggaran` (Q42); `berkas.keterangan` (Q48); `rekomendasi_pimpinan.status`, `disetujui_oleh`, `disetujui_oleh_role`, `disetujui_pada`, `rujukan_sumber` (Q51). Kolom yang tidak dipakai lagi tetapi dipertahankan: `jadwal_tahunan.rencana_aksi_mulai/selesai` (Q41), kunci grup `berkas` pada `pengaturan` (Q44), dan saklar `indikator.wajib_catatan` yang disembunyikan (Q45).
- **Katalog permission:** `laporan:unggah` baru (Q48).

---

## 30. Modul Produk

Urutan modul mengikuti prioritas pembangunan (§33):

1. **Autentikasi & Akses** — login Keycloak, pengelolaan peran, katalog permission, grant izin per unit, dan deny izin.
2. **Dasar Aturan** — CRUD `regulasi` (Kepmen/Permen/Perpres/keputusan lainnya) beserta lampiran dokumen sumbernya.
3. **Master Renstra** — Renstra (termasuk rujukan `regulasi_id`, lampiran dokumen Renstra, dan lampiran revisi setelah aktif), Sasaran, Indikator (termasuk kode mengikuti PK, `tipe_perhitungan`, penanda dinilai tahunan, `regulasi_id`, dan definisi Komponen Indikator), target Renstra beserta riwayat revisinya, PK (termasuk isian target PK, baseline sebagai data pendukung, dan lampiran dokumen PK), serta revisi PK berversi pada tahap 3 (Q39, Q40, Q47, Q50).
4. **Periode & Jadwal** — Periode master, Jadwal Tahunan (termasuk gerbang lampiran PK; tanpa jendela Rencana Aksi sejak Q41), Jadwal Periode, aktivasi & snapshot (termasuk snapshot komponen), buka kembali.
5. **Penugasan** — Penanggung Jawab per indikator.
6. **Rencana Aksi** — penyusunan target per periode per komponen beserta daftar kegiatan per periode, kunci per periode bagi PIC, periode tanpa target untuk indikator dinilai tahunan, ajukan, verifikasi, kembalikan, sahkan, buka kembali (Q41, Q42, Q47).
7. **Kegiatan & Klaim Kegiatan** — pencatatan kegiatan per unit per periode dari halaman Rencana Aksi beserta anggaran, RAB, salin ke periode lain, penjumlahan anggaran dengan penanda kegiatan bersama, kegiatan lanjutan, dan klaim ke Rencana Aksi/komponen (Q41, Q42, Q49).
8. **Bukti Dukung (Berkas Persyaratan)** — pengelolaan `jenis_berkas` (tiga mode bukti; tahap rencana aksi/pengukuran/kegiatan/`kegiatan_perencanaan`; format dan ukuran per persyaratan; contoh berkas) pada grup menu Perencanaan Kinerja, pengiriman dan pengelolaan `berkas` pada seluruh jenis induk, dengan batas teknis unggahan dari konfigurasi server (Q42, Q44; kebijakan storage grup `berkas` dihapus Q44).
9. **Pengukuran** — input komponen (atau nilai manual), realisasi belum dapat diukur, narasi wajib dan tindak lanjut rekomendasi, ajukan (digerbangi §19.4), verifikasi, kembalikan, sahkan, buka kembali setelah pengesahan (Q45, Q47).
10. **Reviu & Pengesahan** — antarmuka kerja Perencanaan untuk memproses antrean Diajukan/Diverifikasi (Rencana Aksi maupun Pengukuran) beserta peringatan verifikasi, penetapan Status Capaian, dan penyusunan draf Rekomendasi Pimpinan; persetujuan rekomendasi oleh Pimpinan, dengan Perencanaan sebagai cadangan bertanda (Q51).
11. **Dashboard** — visualisasi ApexCharts, filter, status tampilan (§23) termasuk "Tanpa target periode" dan "Belum dapat diukur", panel Rencana Aksi & Kegiatan, status kegiatan real-time, dan total anggaran per IKU (Q42, Q45, Q47).
12. **Laporan & Ekspor** — tabel terfilter, rekapitulasi indikator × periode, ekspor Excel, serta keluaran Rencana Aksi tahunan dan Laporan Kinerja Triwulan beserta unggah PDF bertanda tangan (Q48).
13. **Audit & Histori** — pencarian dan tampilan `audit_log`.
14. **Setelan Aplikasi** — identitas instansi/aplikasi, label unit, preferensi tampilan/laporan (§26). Kebijakan storage grup `berkas` dan halaman "Batas unggahan berkas" dihapus Q44.

---

## 31. Alur Penggunaan Utama (Fase Awal)

Ringkasan naratif (diagram lengkap ada di SAKIP - Workflow.md):

1. Pengguna login via Keycloak (SSO).
2. Perencanaan mencatat Dasar Aturan (`regulasi`) yang relevan — mis. Kepmendiktisaintek 358/M/KEP/2025 — beserta lampiran dokumen sumbernya.
3. Perencanaan menyusun Renstra (dasar hukum, rentang tahun, rujukan `regulasi_id`) beserta Sasaran dan Indikator (termasuk kode mengikuti PK, arah penilaian, cara hitung/`tipe_perhitungan` tiap indikator, penanda dinilai tahunan bila relevan, `regulasi_id` per indikator bila relevan, beserta definisi Komponen Indikator bila bukan `manual`), lalu mengisi target Renstra per tahun (Q39, Q47, Q50).
4. Perencanaan mencatat Perjanjian Kinerja (PK) untuk tahun berjalan beserta target PK per indikator dengan nilai penuh (target Renstra tampil sebagai pembanding; peringatan dan alasan wajib bila target PK di bawah target Renstra menurut `arah`), baseline sebagai data pendukung, dan lampiran dokumennya; nomor PK opsional (Q39, Q50).
5. Perencanaan mengaktifkan Renstra (lolos validasi §10.2).
6. Perencanaan membuat Jadwal Tahunan beserta Jadwal Periode (jendela pengisian/reviu per periode yang diharapkan pada tahun itu), merujuk PK tahun tersebut; jendela penyusunan Rencana Aksi tidak lagi diisi (Q41).
7. Perencanaan mengaktifkan Jadwal Tahunan — lolos empat gerbang validasi (§12.4), termasuk gerbang lampiran dokumen PK (§14.10) — aktivasi memicu pembuatan `jadwal_snapshot` beserta `jadwal_snapshot_komponen` secara idempoten untuk seluruh indikator terkait.
8. Perencanaan/Superadmin menugaskan Penanggung Jawab per indikator (jika belum ada dari periode sebelumnya, atau terjadi pergantian).
9. Perencanaan menetapkan persyaratan Bukti Dukung (`jenis_berkas`) per tahap (Rencana Aksi/Pengukuran/Kegiatan/`kegiatan_perencanaan`) bila diperlukan, termasuk mode bukti yang diizinkan (file/tautan/teks), format dan ukuran maksimum per persyaratan, serta contoh berkas (mis. contoh RAB), di halaman Persyaratan Berkas; server menegakkan batas teknis unggahan dari konfigurasi (Q42, Q44; halaman "Batas unggahan berkas" milik Admin/Superadmin dihapus Q44).
10. Sejak jadwal tahun tersebut aktif sampai `penutupan`, PIC menyusun Rencana Aksi indikatornya (Q41): mengisi target per periode per komponen (kumulatif; periode sebelum periode nilai akhir boleh tanpa target untuk indikator dinilai tahunan, Q47), menambahkan kegiatan per periode dari halaman Rencana Aksi beserta anggarannya — dapat disalin ke periode lain — dan mengunggah RAB untuk kegiatan beranggaran lebih dari 0 (Q42, Q49), memenuhi persyaratan bukti dukung wajib tahap Rencana Aksi dan tahap perencanaan kegiatan bila ada, lalu mengajukannya; periode yang `pengisian_selesai`-nya sudah lewat terkunci bagi PIC. Perencanaan memverifikasi dan mengesahkannya.
11. PIC memperbarui status Kegiatan sepanjang periode (Q45 §45.1 butir 4); kegiatan yang mundur ditandai `ditunda` dengan justifikasi dan periode tujuan, lalu kegiatan lanjutannya diklaim pada tahap pengukuran tanpa membuka kembali Rencana Aksi (Q41 §41.4). Saat kegiatan dinyatakan `terlaksana`, sistem memeriksa kelengkapan bukti dukung wajib bertahap `kegiatan` (§15.6).
12. Penanggung jawab (PIC) mengisi nilai komponen (atau nilai manual, atau menandai realisasi belum dapat diukur, Q47) beserta narasi pengukuran dan tindak lanjut atas rekomendasi yang disetujui periode sebelumnya (Q45, Q51), memenuhi persyaratan bukti dukung wajib tahap Pengukuran bila ada, lalu mengajukannya (Diajukan) dalam jendela `pengisian_mulai`–`pengisian_selesai` periode yang bersangkutan — tenggat ini mutlak bagi PIC, dan pengajuan hanya berhasil bila lolos gerbang §19.4 (Rencana Aksi disahkan, komponen lengkap, narasi terisi, bukti dukung wajib terpenuhi).
13. Perencanaan memverifikasi (Diverifikasi) — dengan peringatan bila realisasi atau narasi sama persis dengan periode sebelumnya — atau mengembalikan (Dikembalikan, dengan alasan) dalam jendela reviu periode tersebut; Perencanaan sendiri dapat mengisi/mengoreksi Rencana Aksi maupun Pengukuran kapan pun sampai `penutupan` jadwal tahunan.
14. Perencanaan mengesahkan (Disahkan) — tanpa tahap approval Pimpinan pada Fase Awal.
15. Perencanaan/Superadmin menetapkan Status Capaian secara manual atas pengukuran yang telah Disahkan (tidak tersedia untuk periode tanpa target atau realisasi belum dapat diukur, Q47 §47.3 butir 4); Perencanaan menyusun draf Rekomendasi Pimpinan atas indikator × periode terkait, lalu Pimpinan menyetujuinya atau menulis rekomendasi langsung (Q51).
16. Dashboard dan Laporan menampilkan capaian terkini secara otomatis begitu status berubah menjadi Disahkan dan Status Capaian ditetapkan; rekapitulasi indikator × periode dapat diekspor kapan pun.
17. Pimpinan memantau melalui dashboard dan laporan (akses baca + ekspor Excel, termasuk pembacaan Rekomendasi Pimpinan) dan menyetujui Rekomendasi Pimpinan, tanpa melakukan aksi persetujuan atas Rencana Aksi maupun Pengukuran pada Fase Awal.
18. Bila diperlukan, Perencanaan mengoreksi Rencana Aksi/Pengukuran yang sudah Disahkan lewat `rencana_aksi:buka_kembali`/`pengukuran:buka_kembali` (sebelum penutupan) atau `jadwal:buka_kembali` (setelah penutupan), atau menambahkan indikator IKU baru atau koreksi data melalui `jadwal:buka_kembali` — seluruhnya teraudit.
19. Pemegang `laporan:ekspor` membuat Rencana Aksi tahunan dan Laporan Kinerja Triwulan dalam Excel; dokumen ditandatangani elektronik di luar aplikasi, lalu Perencanaan mengunggah PDF bertanda tangan dengan `laporan:unggah` — juga setelah jadwal tahunan ditutup, karena unggahan tidak mengubah data (Q48 §48.2 butir 5; §24.1).

---

## 32. Kriteria Penerimaan (Fase Awal)

Kriteria berikut harus terpenuhi agar MVP dianggap layak rilis:

1. **Autentikasi**: Pengguna dapat login melalui Keycloak dan sesi Laravel terbentuk berbasis `keycloak_id`; percobaan akses tanpa sesi valid diarahkan ke login.
2. **Otorisasi granular**: Permintaan terhadap aksi apa pun ditolak (403) bila algoritma resolusi izin (§7.4) menghasilkan tolak — termasuk saat kode permission tidak dikenal (fail closed), saat ditemukan deny yang cocok (presedens deny menang atas allow), atau saat tidak ada allow yang cocok; permintaan `pengukuran:create`/`update`/`rencana_aksi:create`/`update`/`ajukan`/`kegiatan:create`/`update` oleh PIC ditolak jika tidak ada grant yang cocok dengan `unit_id` target, sementara permintaan yang sama oleh Perencanaan diizinkan tanpa syarat unit karena bersumber dari isi peran (selalu global).
3. **Validasi aktivasi Renstra**: Sistem menolak aktivasi Renstra tanpa `dasar_hukum` terisi, dan menolak aktivasi jika terjadi irisan rentang tahun dengan Renstra aktif lain.
4. **Larangan nonaktivasi Renstra**: Sistem menolak permintaan menonaktifkan Renstra selama masih ada `jadwal_tahunan` berstatus aktif yang merujuknya.
5. **Empat gerbang aktivasi Jadwal**: Sistem menolak aktivasi Jadwal Tahunan jika (a) `renstra_pk` tahun tersebut belum tercatat, (b) ada indikator aktif tanpa target PK (`target_tahunan`) tahun itu (Q39), (c) tahun jadwal berada di luar rentang tahun Renstra, atau (d) `renstra_pk` tahun tersebut belum memiliki minimal satu lampiran dokumen (§12.4, §14.10). Pengecualian anti-macet lama pada gerbang (d) saat unggahan file dimatikan dihapus Q44 karena unggahan file selalu aktif.
6. **Snapshot otomatis, idempoten, dan imutabel**: Saat Jadwal diaktifkan (termasuk lewat buka kembali), baris `jadwal_snapshot` beserta `jadwal_snapshot_komponen` terbentuk otomatis untuk setiap indikator relevan yang belum punya baris snapshot (tidak pernah menimpa yang sudah ada); baris yang telah dirujuk pengukuran pertamanya tidak dapat diubah lagi.
7. **Rencana Aksi lengkap sebelum diajukan**: Sistem menolak pengajuan Rencana Aksi bila ada komponen aktif yang belum memiliki target pada salah satu periode yang diharapkan — kecuali periode sebelum periode nilai akhir pada indikator dinilai tahunan menurut snapshot (Q47) — bila tidak ada minimal satu kegiatan berklaim tahap Rencana Aksi, atau bila ada kegiatan yang belum memenuhi persyaratan wajib tahap `kegiatan_perencanaan`, termasuk RAB untuk kegiatan beranggaran lebih dari 0 (Q42; §14.7). Gerbang kegiatan dan RAB dikecualikan untuk periode lampau dan Rencana Aksi 2026 data awal (Q46).
8. **Gerbang keras Pengukuran**: Sistem menolak pengajuan Pengukuran bila Rencana Aksi (indikator × tahun) belum `disahkan`, bila ada komponen aktif dengan nilai `null` (kecuali realisasi berstatus `belum_dapat_diukur`, Q47), bila narasi pengukuran atau tindak lanjut atas rekomendasi yang disetujui pada periode sebelumnya kosong (Q45, Q51), atau bila ada persyaratan bukti dukung wajib tahap pengukuran yang belum terpenuhi (mode file/tautan/teks sesuai yang diizinkan) — kecuali pada periode lampau (§12.6; semula disebut jadwal retroaktif).
9. **Gerbang keras Kegiatan**: Sistem menolak transisi status Kegiatan `rencana → terlaksana` bila ada persyaratan bukti dukung wajib bertahap `kegiatan` yang belum terpenuhi (§15.6); pengecualian lama bagi persyaratan bertanda `tidak_dapat_dipenuhi` dihapus Q44. Transisi ke `tidak_terlaksana`/`ditunda`/`batal` hanya mensyaratkan `justifikasi` terisi.
10. **Mesin perhitungan komponen**: Untuk indikator bertipe `rasio_persen`/`penjumlahan`, sistem menghitung `pengukuran.nilai` secara otomatis dan benar dari `pengukuran_komponen` sesuai definisi `indikator_komponen` (peran dan bobot), termasuk penanganan pembagian nol sebagai "tidak dapat dihitung".
11. **Deadline pengisian per periode dan kunci periode Rencana Aksi** *(diperbarui Q41)*: PIC ditolak sistem saat mencoba membuat/mengubah/mengajukan Pengukuran setelah `pengisian_selesai` periode yang bersangkutan terlewati, dan saat menyimpan draf, mengajukan, atau menyimpan versi baru Rencana Aksi yang mengubah target atau daftar kegiatan periode yang `pengisian_selesai`-nya sudah lewat (422, teraudit); di luar periode terkunci, PIC dapat menyusun dan mengajukan Rencana Aksi sejak jadwal aktif sampai `penutupan` tanpa memeriksa jendela penyusunan (`rencana_aksi_selesai` tidak lagi dibaca). Perencanaan tetap dapat melakukannya sampai `penutupan` jadwal tahunan.
12. **Alur linear Rencana Aksi dan Pengukuran**: Transisi status pada kedua entitas hanya dapat mengikuti urutan yang diizinkan (Draft→Diajukan→Diverifikasi→Disahkan, dengan Dikembalikan sebagai jalur balik dari Diajukan/Diverifikasi/Disahkan); transisi di luar urutan ini ditolak sistem.
13. **Buka kembali setelah pengesahan**: Sebelum `penutupan`, `pengukuran:buka_kembali`/`rencana_aksi:buka_kembali` berhasil memindahkan status Disahkan ke Dikembalikan dengan alasan wajib; setelah `penutupan`, permintaan yang sama ditolak dan hanya `jadwal:buka_kembali` yang dapat membuka jalur koreksi.
14. **Validasi narasi pengukuran** *(digantikan Q45; semula validasi catatan wajib berbasis arah dan `wajib_catatan`)*: Pengajuan pengukuran ditolak jika `progres_kegiatan`, `kendala_masalah`, atau `strategi_tindak_lanjut` kosong, atau jika `tindak_lanjut_rekomendasi` kosong padahal terdapat rekomendasi berstatus disetujui pada periode sebelumnya (Q45, Q51); `catatan` kosong tidak lagi menyebabkan penolakan, dan saklar `wajib_catatan` tidak tampil pada form indikator. Gerbang ini dikecualikan untuk periode lampau (Q46).
15. **Klaim kegiatan tidak mengubah nilai komponen**: Menambah/menghapus baris `klaim_kegiatan` tidak mengubah nilai `pengukuran_komponen`/`rencana_aksi_target` mana pun secara otomatis.
16. **Optimistic locking**: Update pengukuran/rencana aksi dengan nomor `versi` usang ditolak dengan pesan konflik yang jelas.
17. **Tidak ada penghapusan permanen**: Percobaan menghapus pengukuran/rencana aksi yang memiliki nilai/catatan, baris snapshot yang telah dirujuk, atau bukti dukung pada induk yang sudah berada dalam keadaan sah (Disahkan/`terlaksana`), ditolak sistem dan/atau tercatat sebagai percobaan di audit log.
18. **Audit lengkap**: Setiap peristiwa pada §25.1 menghasilkan baris `audit_log` yang dapat dicari dan ditelusuri oleh pengguna dengan `audit:read`.
19. **Dashboard hanya data sah**: Angka capaian pada dashboard hanya dihitung dari pengukuran berstatus Disahkan; status "Belum mengisi"/"Tidak mengisi" dihitung per kombinasi indikator × periode dari `jadwal_periode`, mengecualikan indikator arsip. Status kegiatan real-time tampil berlabel "belum diverifikasi" dan tidak masuk angka capaian resmi (Q45); periode tanpa target tampil "Tanpa target periode" dan realisasi belum dapat diukur tampil "Belum dapat diukur", keduanya tidak dihitung sebagai "Belum tercapai" atau capaian 0 (Q47).
20. **Ekspor Excel**: Laporan tabular terfilter, rekapitulasi indikator × periode, serta Rencana Aksi tahunan dan Laporan Kinerja Triwulan (Q48) dapat diekspor ke berkas Excel yang valid dan dapat dibuka.
21. **Tanpa approval Pimpinan atas pengukuran**: Tidak ada langkah wajib yang memerlukan aksi `pengukuran:setujui` agar Pengukuran mencapai status Disahkan pada Fase Awal. Rekomendasi Pimpinan disusun sebagai draf oleh Perencanaan dan disetujui Pimpinan, atau ditulis langsung Pimpinan (semula diisi Perencanaan; digantikan Q45 dan Q51).
22. **Status capaian manual**: Seluruh baris `status_capaian` yang dibuat pada Fase Awal memiliki `sumber = 'manual'` dan `ditetapkan_oleh` terisi.
23. **UI akses terbatas**: Hanya tiga form mutasi akses (Assign Peran, Grant Unit, Explicit Deny) beserta halaman “Jelaskan Izin Pengguna” dan halaman “Peran & Izin” read-only yang tersedia. Tidak ada editor `role_permissions` dari UI.
24. **Setelan aplikasi terbatas dan teraudit** *(diperbarui Q44)*: Hanya Superadmin dan Admin yang dapat mengubah `pengaturan`; setiap perubahan menghasilkan baris `audit_log` dengan nilai lama/baru; tidak ada kunci `pengaturan` yang mengendalikan enum/status/permission/aturan bisnis maupun kolom substantif persyaratan bukti dukung. Menu Kebijakan Storage, halaman "Batas unggahan berkas", dan kunci grup `berkas` tidak lagi tersedia di UI (Q44).
25. **Seed data**: Skrip seeder Laravel dapat dijalankan pada lingkungan pengembangan/testing dan menghasilkan minimal 1 Renstra, Sasaran, beberapa Indikator (termasuk minimal satu indikator berkomponen), Target, 1 PK, 1 Jadwal Tahunan beserta Jadwal Periode-nya, Rencana Aksi yang disahkan, dan Kegiatan yang valid untuk pengujian alur end-to-end, serta minimal 1 user uji untuk masing-masing dari lima role (`superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`) serta minimal 1 user aktif tanpa role untuk menguji onboarding (Q38; semula user nonaktif tanpa role). Asumsi kerja data awal 2026 hanya dipakai untuk data uji (§12.6).
26. **Presedens deny**: Sistem menolak permintaan otorisasi bila ditemukan baris `user_permission_denials` yang cocok (unit maupun global), terlepas dari peran atau grant apa pun yang dimiliki pengguna.
27. **Fail closed**: Sistem menolak permintaan otorisasi untuk kode permission yang tidak terdaftar aktif di tabel `permissions`.
28. **Dasar izin tercatat**: Setiap aksi dengan `permissions.sensitif = true` menghasilkan baris `audit_log` yang memuat `dasar_izin` (sumber allow yang mengizinkan, atau deny yang menolak).
29. **Pemisahan tugas F1/F2**: Sistem menolak transisi verifikasi/pengesahan Pengukuran oleh aktor yang sama dengan `pengukuran.created_by` pada jalur PIC; transisi yang sama oleh Perencanaan atas pengisiannya sendiri berhasil namun tercatat dengan penanda `self_approval`.
30. **Halaman "Jelaskan izin pengguna"**: Untuk sembarang pengguna yang dipilih, halaman ini menampilkan daftar izin efektifnya per unit beserta asal (peran/grant) dan deny yang berlaku, konsisten dengan hasil algoritma resolusi izin (§7.4).
31. **Validasi mode bukti dukung**: Penyimpanan `jenis_berkas` ditolak bila ketiga `izinkan_file`/`izinkan_tautan`/`izinkan_teks` bernilai `false`; pengiriman bukti (`berkas`) dengan `mode` di luar yang diizinkan pada persyaratannya ditolak sistem; bukti bermode `tautan` tanpa skema `http`/`https` atau bukti bermode `teks`/`tautan` tanpa `isi_teks`/`tautan` terisi ditolak.
32. **Unggahan selalu aktif** *(digantikan Q44; semula "Penanda `tidak_dapat_dipenuhi` tidak memblokir" bila `berkas.unggahan_aktif = false`)*: Unggahan file selalu aktif tanpa saklar; penanda `tidak_dapat_dipenuhi` akibat unggahan dimatikan tidak lagi dibuat, penanda lama tetap tampil sebagai histori, dan seluruh gerbang kelengkapan berlaku penuh tanpa cabang anti-macet unggahan (§18.6, §18.7).
33. **Entitas `regulasi` dan rujukannya**: Penyimpanan `regulasi` ditolak bila kombinasi (`jenis`, `nomor`, `tahun`) sudah ada (`unique`); lampiran `regulasi` ditolak dihapus selama regulasi masih dirujuk oleh Renstra atau indikator aktif; perubahan `renstra.regulasi_id`/`indikator.regulasi_id` tercatat di `audit_log`.
34. **Lampiran enam induk**: Pengiriman/penghapusan `berkas` berhasil untuk keenam nilai `berkasable_type` (`rencana_aksi`/`pengukuran`/`kegiatan`/`renstra`/`renstra_pk`/`regulasi`); penghapusan ditolak sistem setelah induk masing-masing melewati batas imutabilitasnya (§18.8). Induk baru `jenis_berkas` (contoh berkas, Q42) serta `jadwal_tahunan`/`jadwal_periode` (arsip dokumen resmi, Q48) tersedia; arsip dokumen resmi tidak dapat dihapus.
35. **Batas teknis unggahan** *(digantikan Q44; semula "Halaman 'Batas unggahan berkas' terbatas")*: Menu Kebijakan Storage dan halaman "Batas unggahan berkas" tidak tersedia; lampiran tanpa persyaratan atau persyaratan yang mengosongkan format/ukuran memakai default konfigurasi 10 MB dan `pdf,docx,xlsx,jpg,jpeg,png`; persyaratan dengan `ukuran_maks_kb` di atas 102400 ditolak saat disimpan, dan unggahan yang melebihi batas atas ditolak (§18.2, §18.9).
36. **Lampiran pemetaan indikator sebagai fixture**: Kedelapan baris pada §17.11.1, saat diinput sebagai `indikator_komponen` dengan angka contoh 2026, menghasilkan nilai turunan yang sama persis dengan hitungan manual pada kolom "Contoh angka 2026" — dipakai sebagai test mesin perhitungan (§17.11.3), bukan nilai yang di-hardcode pada kode.
37. **Lima role resmi (Q32)**: Tabel `roles` memuat tepat lima kode sistem (`superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`); Form Assign Peran menampilkan kelima pilihan dan user dapat ditetapkan ke PIC operasional (bukan role) tanpa melanggar constraint satu role per user. Kriteria ini **tidak** menetapkan preset permission PIC operasional (bukan role) maupun eligibility `penanggung_jawab`, karena keduanya masih OPEN sampai ada konfirmasi resmi.
38. **Dua lapis target (Q39, Q50)**: Target PK diinput di halaman PK oleh pemegang `target:update` dan baca-saja bagi PJ/PIC; target Renstra tahun yang sama tampil sebagai pembanding; target PK di bawah target Renstra (`naik_baik`) atau di atasnya (`turun_baik`) memunculkan peringatan dan wajib alasan tanpa menolak penyimpanan; capaian dihitung terhadap target PK. PK tanpa nomor diterima, target PK tersimpan dengan nilai penuh, baseline tampil sebagai data pendukung, dan tanggal PK sebelum penetapan Renstra tidak ditolak.
39. **Anggaran dan RAB (Q42, Q49)**: Kegiatan baru atau yang diubah tanpa `anggaran` ditolak — kecuali kegiatan data awal 2026 dan kegiatan periode lampau (Q46 §46.1 butir 3) — anggaran negatif ditolak, dan `0` diterima; nama kegiatan hingga 500 karakter diterima dan `tujuan` boleh kosong; persyaratan bertanda `hanya_kegiatan_beranggaran` (RAB) hanya diperiksa untuk kegiatan beranggaran lebih dari 0; total anggaran per periode, per Rencana Aksi, dan per IKU tampil dengan penanda "kegiatan bersama" dan total per unit/tahun dari kegiatan unik, tanpa menghitung kegiatan asal `ditunda` yang sudah memiliki kegiatan lanjutan; salinan kegiatan ke periode lain tidak menyalin berkas dan tidak dapat dibuat untuk periode yang terkunci.
40. **Kewajiban alasan (Q43)**: Aksi pada §4 prinsip 6 yang wajib beralasan ditolak tanpa alasan; aksi beralasan opsional diterima tanpa alasan dengan UI "Alasan (opsional)"; Grant Unit tanpa alasan tersimpan dengan alasan baku dari server; form Penanggung Jawab tidak menampilkan field alasan; audit tetap mencatat aktor, waktu, tindakan, nilai lama/baru, dan `dasar_izin`.
41. **Dinilai tahunan dan belum dapat diukur (Q47)**: Penanda dinilai tahunan tersimpan, teraudit, dan dibekukan di snapshot; target periode kosong hanya diterima untuk indikator tersebut pada periode sebelum periode nilai akhir dan dibedakan dari `0`; pengukuran berstatus `belum_dapat_diukur` dapat diajukan tanpa kelengkapan komponen tetapi dengan narasi, tidak dihitung sebagai capaian 0, dan ditolak pada periode nilai akhir kecuali tidak dapat dihitung menurut Q9.
42. **Dokumen resmi (Q48)**: Pemegang `laporan:ekspor` dapat membuat Rencana Aksi tahunan dan Laporan Kinerja Triwulan dari data yang disahkan dengan keterangan "belum disahkan" untuk indikator yang belum sah; pengguna tanpa `laporan:ekspor` ditolak (403); PDF bertanda tangan hanya dapat diunggah pemegang `laporan:unggah`, tidak dapat dihapus, tidak mengubah data/status/snapshot, tercatat di audit, dan tetap dapat diunggah setelah jadwal tahunan ditutup.
43. **Rekomendasi Pimpinan (Q51)**: Draf rekomendasi tidak tampil bagi PIC dan tidak memicu tindak lanjut; hanya rekomendasi berstatus disetujui yang tampil pada pengukuran periode berikutnya dan Laporan Kinerja Triwulan; persetujuan oleh Perencanaan diberi penanda cadangan dan tercatat di audit; perubahan atas rekomendasi yang sudah disetujui membentuk baris baru melalui persetujuan yang sama.
44. **Periode lampau 2026 (Q46)**: Saat Jadwal Tahunan 2026 diaktifkan, TW I–III berstatus periode lampau yang diisi Perencanaan dan terkunci bagi PIC, sedangkan TW IV berjalan normal; gerbang Q42–Q45 (kegiatan minimal, RAB, persyaratan tahap perencanaan kegiatan, narasi wajib, dan tindak lanjut rekomendasi) dikecualikan untuk periode lampau, sementara gerbang PK tetap wajib; kegiatan data awal dan kegiatan periode lampau boleh tanpa anggaran (Q46 §46.1 butir 1 dan 3).


---

## 33. Prioritas Versi Awal (Urutan Pembangunan)

Urutan modul mengikuti §30, dengan alasan dependency teknis:

1. Autentikasi & Akses — fondasi wajib sebelum modul lain dapat diuji dengan pengguna berperan.
2. Dasar Aturan — bergantung pada Autentikasi & Akses (permission `regulasi:*`); dibangun lebih dulu karena Master Renstra merujuknya lewat `regulasi_id`.
3. Master Renstra (Renstra, Sasaran, Indikator termasuk komponen, target Renstra, PK beserta target PK) — bergantung pada Dasar Aturan; data dasar yang menjadi rujukan seluruh modul berikutnya.
4. Periode & Jadwal (termasuk Jadwal Periode, snapshot beserta komponennya, buka kembali, gerbang lampiran PK; jendela Rencana Aksi dihapus Q41) — bergantung pada Renstra + PK + lampiran `renstra_pk`.
5. Penugasan — bergantung pada Indikator.
6. Rencana Aksi — bergantung pada Jadwal aktif (snapshot komponen) + Penugasan.
7. Kegiatan & Klaim Kegiatan — bergantung pada Rencana Aksi (untuk klaim) dan Unit.
8. Berkas Persyaratan (Bukti Dukung) — bergantung pada definisi `jenis_berkas` dari Perencanaan; digunakan oleh Rencana Aksi, Pengukuran, Kegiatan, Renstra, Perjanjian Kinerja, dan Dasar Aturan.
9. Pengukuran — bergantung pada Rencana Aksi disahkan, Jadwal aktif (snapshot), dan Berkas Persyaratan.
10. Reviu & Pengesahan — bergantung pada Rencana Aksi dan Pengukuran.
11. Dashboard — bergantung pada Pengukuran, Rencana Aksi, Kegiatan, dan Status Capaian.
12. Laporan & Ekspor — bergantung pada Pengukuran, Rencana Aksi, Kegiatan, dan Dashboard.
13. Audit & Histori — dibangun paralel sejak modul pertama (audit log dicatat sejak Autentikasi & Akses), namun UI pencarian/tampilannya dituntaskan setelah modul inti berjalan.
14. Setelan Aplikasi (halaman "Batas unggahan berkas" dihapus Q44) — tidak memiliki dependency berat ke modul lain; ditempatkan terakhir karena bersifat administratif dan tidak menghambat alur inti pengukuran.

**Urutan pengerjaan setelah Q39–Q51 (Q46 §46.3, diperbarui Q51):** Tim Perencanaan meminta fondasi PK → Rencana Aksi → Kegiatan dimatangkan sebelum verifikasi/pengesahan pengukuran dan fitur tambahan (Q46 §46.2). Issue yang tidak disebut pada tabel mengikuti rencana sprint yang berlaku.

| Tahap | Batas | Cakupan |
|---|---|---|
| 1 | Sebelum integrasi 9 November 2026 (Q2) | Q38 (ISS-01.08); Q39 dan Q50 (ISS-02.10, ISS-02.11); lampiran revisi dan riwayat target Renstra (Q40 §40.2, ISS-02.10); Q41 dan Q47 (ISS-05.07, serta ISS-05.06 yang sedang berjalan); Q42 dan Q49 (ISS-06.01, ISS-06.02, ISS-06.08); Q43 (ISS-13.05); Q44 (ISS-13.06); pengecualian periode lampau (Q46 §46.1); di akhir tahap, penyiapan data awal 2026 (ISS-15.01) |
| 2 | Sebelum pelaporan TW IV 2026 (awal Januari 2027) | Pengukuran inti: ISS-07.01, ISS-07.03 (dengan narasi Q45 §45.1 dan tampilan Q47), ISS-07.06, ISS-08.01, ISS-08.02; pelaksanaan kegiatan: ISS-06.03, ISS-06.04, ISS-06.05; Rekomendasi Pimpinan: ISS-09.01 (Q45, Q51); dashboard dan rekap: ISS-10.01, ISS-10.02; keluaran dokumen resmi: ISS-10.05 (Q48); penutupan jadwal: ISS-14.01 |
| 3 | Sesudah tahap 2; revisi PK harus siap sebelum revisi PK pertama yang mengubah target | ISS-03.03 dan ISS-02.12 (revisi PK berversi); simulasi target; tampilan daftar berkas; versi penuh Renstra; ISS-13.03 |

Detail pemecahan task granular per fitur ada di **SAKIP - Plan Pengembangan.md**.

---

## 34. Catatan Risiko

1. **Stale requirement Q31:** kode/dokumen lama dapat masih menganggap `pic` sebagai role. Mitigasi: Q32 wajib menjadi baseline; test katalog role harus tepat 5.
2. **Grant scope bocor:** permission scoped dapat tanpa sengaja menjadi global. Mitigasi: katalog final hanya 7 scoped, gate Grant Unit `delegasi:update`, test cross-unit wajib.
3. **Onboarding memberi akses terlalu awal:** user JIT tidak boleh otomatis mendapat role. Mitigasi: tanpa role + fail closed (`no_role`); sejak Q38 akun JIT langsung `aktif`, tetapi tetap nol permission sampai role ditetapkan.
4. **Logout SSO terlalu agresif:** realm dipakai aplikasi lain. Mitigasi: logout lokal default; end-session hanya aksi eksplisit terpisah.
5. **Interpretasi data IKU salah:** 66,395/87,08 dapat keliru dianggap realisasi dan 84 keliru dipakai pada IKU 8. Mitigasi: fixture final Q32 dan automated test formula/data awal.
6. **Periode lampau disalahimplementasikan sebagai backfill generik:** mitigasi: status dihitung dari jendela, tanpa `is_backfill`; 2025 hanya baseline.
7. **PJ dianggap permission:** mitigasi: assignment, grant, dan role tetap terpisah; warning PJ tanpa hak isi.
8. **Keputusan Q38–Q51 belum dikonfirmasi tertulis:** Q39–Q46 berasal dari rapat asistensi dan Q47–Q51 ditetapkan PM dari dokumen resmi 2026. Mitigasi: dipakai sebagai baseline kerja; koreksi Tim Perencanaan dicatat pada Q terkait lalu diselaraskan ulang ke dokumen turunan.
9. **Asumsi kerja data awal 2026 terpakai di produksi:** formula, unit/PJ, dan nilai yang belum dikonfirmasi dapat terbawa ke produksi. Mitigasi: asumsi kerja hanya untuk data uji dan rancangan data awal (ISS-15.01); komponen tidak disetel di produksi sebelum dikonfirmasi Perencanaan (§12.6, §17.11).
10. **Target PK tercetak dibulatkan dipakai sebagai acuan:** PK 2026 mencetak target yang dibulatkan (94; 81; 92,9). Mitigasi: target PK disimpan dengan nilai penuh yang dipakai laporan (Q50 §50.2); konfirmasi nilai resmi menunggu Perencanaan.
11. **Periode tanpa target atau realisasi belum tersedia terbaca sebagai kegagalan:** mitigasi: target kosong dibedakan dari `0`, status `belum_dapat_diukur` eksplisit, dan label "Tanpa target periode"/"Belum dapat diukur" pada dashboard, rekap, dan dokumen resmi (Q47).
12. **Revisi PK yang mengubah target terbit sebelum revisi berversi siap:** mitigasi: revisi PK berversi (tahap 3) harus siap sebelum revisi PK pertama yang mengubah target; PK perubahan yang hanya mengubah anggaran cukup menjadi lampiran tambahan; bila revisi target terbit lebih dulu, penanganannya ditetapkan PM sebagai keputusan baru (Q50 §50.7).
13. **Data pribadi pada nama kegiatan:** nama kegiatan dapat memuat nama orang atau kasus. Mitigasi: detail kegiatan tetap dibatasi unit, dan Rencana Aksi tahunan hanya dibuat dan diunduh pemegang `laporan:ekspor` (Q49 §49.3).

---
