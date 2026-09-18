# PRD — SAKIP LLDIKTI Wilayah XVI
## Product Requirements Document

**Nama proyek:** SAKIP (Sistem Akuntabilitas Kinerja) — LLDIKTI Wilayah XVI
**Sifat proyek:** Aplikasi baru, terpisah dari sistem informasi lain yang sudah berjalan di LLDIKTI Wilayah XVI

---

## 1. Ringkasan

SAKIP adalah aplikasi internal untuk menyusun rencana aksi, mencatat kegiatan pendukung, serta mencatat, memverifikasi, dan mengesahkan pengukuran indikator kinerja LLDIKTI Wilayah XVI. Indikator yang diukur diturunkan dari Kepmendiktisaintek 358/M/KEP/2025, dengan target tahunan mengacu pada Perjanjian Kinerja (PK) yang ditandatangani setiap tahun. Aplikasi ini menggantikan pencatatan manual (spreadsheet/dokumen lepas) dengan satu sistem yang:

- Menjaga rantai kinerja tetap tertelusur: Kepmen → Renstra → Sasaran → Indikator → Target Tahunan → PK → Jadwal Tahunan → Rencana Aksi → Kegiatan → Pengukuran → Status Capaian → Rekomendasi Pimpinan.
- Menyusun rencana aksi per indikator per tahun sebelum pengukuran periode berjalan dimulai — target dipecah ke level periode dan diinput pada level komponen, bersifat kumulatif antarperiode.
- Mencatat kegiatan yang dilaksanakan tiap periode dan mengaitkannya (klaim) ke rencana aksi/komponen indikator yang didukungnya, sehingga narasi progres, kendala, dan strategi tindak lanjut pada rekapitulasi tersusun otomatis dari daftar kegiatan, bukan diketik ulang sebagai satu blok panjang.
- Menyediakan mesin perhitungan indikator berbasis komponen: untuk indikator bertipe rasio atau penjumlahan, nilai capaian tidak lagi diketik sebagai satu angka jadi, melainkan diturunkan sistem dari nilai komponen pembentuknya (mis. pembilang dan penyebut suatu rasio) sesuai definisi yang dikelola Perencanaan — tanpa hardcode per indikator.
- Memberi kejelasan siapa bertanggung jawab mengisi data untuk indikator apa, kapan jendela penyusunan rencana aksi dan pengisian pengukuran per periode, dan siapa yang dikecualikan dari batas waktu itu.
- Menegakkan alur kerja berjenjang pada rencana aksi maupun pengukuran (Draft → Diajukan → Diverifikasi → Disahkan, dengan Dikembalikan sebagai jalur revisi), disertai gerbang kelengkapan — rencana aksi harus disahkan lebih dulu, seluruh komponen aktif harus terisi, dan bukti dukung wajib tahap pengukuran harus terpenuhi (unggahan file, tautan, atau keterangan teks, sesuai mode yang diizinkan) — sebelum pengukuran dapat diajukan; kegiatan mendapat gerbang serupa sebelum dinyatakan terlaksana.
- Menyediakan audit trail penuh atas setiap perubahan data kinerja, termasuk metadata (siapa, kapan, alasan) untuk tindakan sensitif, termasuk percobaan tindakan yang ditolak oleh gerbang kelengkapan.
- Menjaga integritas historis lewat snapshot: begitu suatu periode pelaporan diaktifkan, konteks pengukuran (nama indikator, definisi, satuan, presisi, target, arah penilaian, cara hitung, dan definisi komponennya) dibekukan sehingga perubahan pada master data di kemudian hari tidak mengubah riwayat capaian tahun-tahun sebelumnya.
- Menyediakan jalur koreksi dan penambahan data yang terkendali dan teraudit — baik sebelum maupun sesudah suatu tahun ditutup — tanpa membuka celah menimpa data historis diam-diam.

Dokumen ini mendefinisikan cakupan MVP (Fase Awal) yang dibangun lebih dulu, dan menandai secara eksplisit bagian yang ditunda ke Fase Lanjutan agar cakupan tidak ambigu saat implementasi maupun saat evaluasi hasil kerja.

---

## 2. Konteks Regulasi & Rantai Kinerja

### 2.1 Dasar Regulasi

- **Kepmendiktisaintek 358/M/KEP/2025** — sumber indikator kinerja yang wajib diukur oleh LLDIKTI, menjadi acuan definisi dan daftar indikator yang dimuat dalam Renstra.
- **Perjanjian Kinerja (PK)** — dokumen tahunan yang ditandatangani pimpinan, memuat target tahunan resmi atas indikator-indikator dalam Renstra. PK adalah rujukan legal-formal atas nilai target yang tercatat dalam sistem.
- Dokumen turunan lain (Renstra internal LLDIKTI XVI, jadwal pelaporan triwulanan/semesteran/tahunan) mengikuti siklus manajemen kinerja instansi pemerintah pada umumnya (rencana strategis → PK → rencana aksi → pengukuran → pelaporan → reviu/evaluasi).
- Dasar aturan yang melandasi Renstra dan indikator dicatat sebagai entitas terstruktur **`regulasi`** (§9) — bukan sekadar teks bebas — sehingga rujukan Kepmen/Permen/Perpres/keputusan lain dapat ditelusuri per Renstra maupun per indikator, dilengkapi lampiran dokumen sumbernya.

### 2.2 Rantai Kinerja (Chain of Accountability)

```
Kepmendiktisaintek 358/M/KEP/2025
        │  (mendefinisikan indikator wajib)
        ▼
      Renstra  ──────────────► Sasaran ──────────────► Indikator (+ komponen & cara hitung)
        │                                                   │
        │ (per tahun)                                       │ (per tahun)
        ▼                                                   ▼
   Perjanjian Kinerja (PK)  ◄───────────────────  Target Tahunan
        │
        │ (mengesahkan target resmi tahun berjalan)
        ▼
   Jadwal Tahunan (aktivasi → snapshot cara hitung & komponen)
        │
        ▼
   Rencana Aksi per Indikator (target per periode, per komponen)
        │
        ▼
   Kegiatan per Periode ──(klaim)──► mendukung Rencana Aksi/komponen
        │
        ▼
   Pengukuran per Periode (input komponen + berkas pertanggungjawaban)
        │
        ▼
   Status Capaian ──► Rekomendasi Pimpinan ──► Dashboard / Laporan
```

Setiap indikator harus dapat ditelusuri balik ke Sasaran, Renstra, dan pada akhirnya ke Kepmen yang melandasinya. Setiap nilai capaian harus dapat ditelusuri ke target tahunan yang sah pada saat pengukuran dilakukan (via snapshot jadwal, lihat §12), ke rencana aksi yang telah disahkan untuk indikator dan tahun tersebut (§14), dan — bila indikator bertipe rasio/penjumlahan — ke nilai komponen pembentuknya (§17).

### 2.3 Mengapa Sistem Ini Dibutuhkan

- Pencatatan manual rawan versi ganda, tidak ada jejak siapa mengubah apa, dan sulit direkonsiliasi saat evaluasi internal (Itjen) maupun eksternal (Kemen PANRB).
- Perhitungan skor indikator berbentuk rasio atau gabungan komponen selama ini dilakukan manual di luar sistem, rawan kesalahan rumus dan sulit diperiksa ulang; sementara rencana kerja dan realisasi kegiatan tidak terhubung eksplisit dengan indikator yang didukungnya, sehingga narasi progres/kendala/tindak lanjut ditulis ulang secara manual setiap periode.
- Tidak ada mekanisme baku untuk menandai bahwa suatu angka "sudah final/sah" versus "masih draft/dalam reviu".
- Perubahan struktur organisasi (unit, penanggung jawab) selama ini tidak terekam sebagai riwayat, menyulitkan audit lintas tahun.

---

## 3. Tujuan Produk

1. Menyediakan satu sumber kebenaran (single source of truth) untuk data pengukuran kinerja LLDIKTI Wilayah XVI, tertaut langsung ke Renstra dan PK yang berlaku.
2. Menegakkan alur validasi berjenjang — baik pada penyusunan rencana aksi maupun pengukuran — sehingga data yang disajikan di dashboard/laporan telah melalui verifikasi dan pengesahan, bukan input mentah yang belum diperiksa.
3. Memberikan kejelasan tanggung jawab pengisian data per indikator per periode — termasuk jendela waktu penyusunan rencana aksi dan pengisian pengukuran yang berbeda per periode, siapa yang terikat tenggat itu, dan riwayat pergantian penanggung jawab.
4. Menjamin integritas historis data kinerja lintas tahun melalui mekanisme snapshot yang idempoten dan imutabel — mencakup pula cara hitung dan definisi komponen indikator — sehingga perubahan master data di masa depan tidak mendistorsi capaian yang telah disahkan.
5. Mencatat audit trail lengkap atas seluruh peristiwa penting (perubahan hak akses, perubahan Renstra/indikator, alur rencana aksi dan pengukuran, perubahan definisi komponen dan persyaratan berkas, koreksi setelah pengesahan, status capaian, rekomendasi Pimpinan, perubahan setelan aplikasi, percobaan penghapusan) untuk kebutuhan akuntabilitas dan pemeriksaan.
6. Menyediakan visibilitas capaian kinerja secara real-time (dashboard) bagi pimpinan tanpa perlu menunggu rekap manual.
7. Menyediakan jalur koreksi dan penambahan data yang jelas dan terkendali — baik untuk kesalahan yang ditemukan sebelum penutupan tahun, kebutuhan menambah indikator IKU baru di tengah tahun, maupun pengisian data historis (backfill) untuk tahun yang telah lampau.
8. Menyediakan mesin perhitungan indikator berbasis komponen yang data-driven, sehingga perubahan cara hitung akibat revisi Kepmen dapat dilakukan lewat pengelolaan definisi komponen di aplikasi, dalam batas satu tingkat perhitungan (§17.10), tanpa memerlukan perubahan kode.
9. Menautkan kegiatan operasional ke rencana aksi/indikator secara eksplisit lewat klaim kegiatan, sehingga narasi capaian (progres, kendala, strategi tindak lanjut) tersusun otomatis dari data kegiatan yang tercatat di sistem, bukan laporan naratif yang disusun terpisah.

---

## 4. Prinsip Produk

1. Dashboard hanya menghitung data yang sudah **Disahkan** sebagai capaian resmi. Data berstatus Draft, Diajukan, Diverifikasi, atau Dikembalikan — baik pada Pengukuran maupun Rencana Aksi — tetap terlihat dalam konteks kerja (mis. daftar tugas verifikator), tapi tidak masuk ke ringkasan kinerja.
2. Pengukuran, Rencana Aksi, dan Kegiatan yang sudah memiliki nilai atau catatan tidak dihapus permanen. Perubahan status — termasuk pembatalan — direkam sebagai transisi status di audit log, bukan sebagai penghapusan baris. Kegiatan yang tidak terlaksana diberi status dan justifikasi, bukan dihapus (§15.2).
3. Snapshot membekukan konteks pengukuran, bukan menghapus fleksibilitas master data. Indikator, target, dan definisi komponen boleh terus dikembangkan dari tahun ke tahun; snapshot hanya memastikan pengukuran tahun berjalan tetap merujuk definisi, target, arah penilaian, dan cara hitung yang berlaku saat periode itu diaktifkan. Baris snapshot yang telah dirujuk oleh pengukuran bersifat abadi — tidak ada restatement data historis.
4. Hak akses memakai model RBAC hidup: peran (`roles`) memuat permission sebagai data (`role_permissions`) yang dievaluasi setiap permintaan, dilengkapi pemberian izin tambahan per unit (`user_permission_granted`) dan pencabutan izin eksplisit (`user_permission_denied`) yang menang atas segala pemberian izin — bukan role yang di-hardcode maupun salinan baris izin statis per pengguna. Mekanisme ini menjadi gate akses backend sejak awal, dievaluasi penuh di server (bukan di klien), meski UI pengelolaannya masih disederhanakan pada Fase Awal (§7). Pengecualian yang disengaja: permission pengisian pengukuran dan rencana aksi milik peran Perencanaan bersifat global (tanpa scope unit) karena bersumber dari isi peran yang selalu global, sebab Perencanaan bertindak sebagai penjaga integritas data lintas unit, bukan pemilik satu unit tertentu.
5. Beberapa alur — misalnya approval Pimpinan atas pengukuran — sengaja disederhanakan pada Fase Awal, tapi skema data tetap dirancang penuh dari awal supaya penambahan alur lanjutan nanti tidak membutuhkan migrasi data besar atau berisiko. Rekomendasi Pimpinan (§22) tetap diisi pada Fase Awal, tetapi oleh Perencanaan — bukan oleh Pimpinan sendiri — karena Pimpinan belum masuk ke alur kerja aplikasi.
6. Tindakan sensitif seperti koreksi PK, pengembalian pengukuran atau rencana aksi (termasuk pengembalian setelah pengesahan), penggantian penanggung jawab, revisi Renstra, perubahan definisi komponen indikator, perubahan persyaratan berkas, penghapusan berkas/klaim, perubahan setelan aplikasi, dan penghapusan unit/indikator wajib menyertakan alasan tertulis (bila relevan) dan tercatat di audit log.
7. Deadline pengisian bersifat mutlak bagi penanggung jawab (PIC) di unit — baik untuk jendela penyusunan rencana aksi maupun jendela pengisian pengukuran per periode. Perencanaan dikecualikan dari kedua batas ini karena berperan menjaga kelengkapan dan kebenaran data sampai tahun ditutup.
8. Penghapusan bukan jalur koreksi. Kesalahan yang perlu dibetulkan — baik pada pengukuran, rencana aksi, jadwal, maupun master data — diselesaikan melalui transisi status, mekanisme buka kembali, atau baris riwayat baru; tidak pernah dengan menimpa/menghapus data lama secara diam-diam.
9. Nilai komponen adalah satuan input yang sebenarnya bagi indikator bertipe rasio/penjumlahan; skor akhir indikator untuk kedua tipe itu adalah **nilai turunan** hasil hitungan sistem, bukan angka yang diketik langsung — memindahkan risiko salah hitung manual ke satu tempat yang konsisten dan dapat diaudit, sekaligus menjaga rumus tetap sama antarperiode.
10. Pengajuan — baik rencana aksi maupun pengukuran — ditolak sistem bila prasyarat kelengkapan belum terpenuhi (rencana aksi disahkan, komponen terisi penuh, bukti dukung wajib terpenuhi sesuai mode yang diizinkan). Gerbang serupa berlaku bagi kegiatan sebelum dinyatakan terlaksana (§15.6). Gerbang-gerbang ini adalah kontrol kualitas data yang ditegakkan otomatis, bukan sekadar imbauan SOP — kecuali pada jadwal retroaktif (backfill data historis, §12.6) yang sengaja dikecualikan dari seluruh gerbang tersebut karena tujuannya memang mengisi data historis yang lazimnya tidak melalui proses normal pada masanya. Ketersediaan mode bukti dukung tidak boleh memacetkan alur: persyaratan yang hanya mengizinkan mode file namun unggahan sedang dinonaktifkan pada setelan aplikasi ditandai `tidak_dapat_dipenuhi` dan tidak memblokir gerbang mana pun (§18.5).
11. Klaim kegiatan adalah dokumentasi keterkaitan, bukan mesin hitung. Mengaitkan kegiatan ke rencana aksi/komponen tidak mengubah nilai komponen secara otomatis; nilai komponen tetap diisi manual oleh PIC untuk menghindari risiko penghitungan ganda (mis. satu pihak sasaran yang muncul di beberapa kegiatan tetap dihitung satu kali).

---

## 5. Ruang Lingkup

### 5.1 Termasuk (Fase Awal — MVP)

- Autentikasi via Keycloak (SSO existing LLDIKTI) menggunakan client baru khusus SAKIP.
- Pengelolaan **Dasar Aturan** (`regulasi`): CRUD produk hukum (Kepmen/Permen/Perpres/keputusan lainnya) beserta lampiran dokumen sumbernya, dirujuk oleh Renstra dan/atau Indikator (§9).
- Manajemen Renstra (CRUD, siklus status draft/aktif/nonaktif/diarsipkan) beserta Sasaran dan Indikator turunannya, termasuk revisi Renstra akibat terbitnya Kepmen IKU baru (edit in place, teraudit), rujukan `regulasi_id`, dan lampiran dokumen Renstra.
- Pengelolaan Perjanjian Kinerja (renstra_pk) per tahun per Renstra, termasuk koreksi beralasan dan lampiran dokumen PK.
- Pengelolaan Target Tahunan per indikator per tahun, termasuk kolom baseline sebagai acuan penyusunan.
- Pengelolaan definisi Komponen Indikator (`indikator_komponen`) dan mesin perhitungan otomatis berbasis komponen (`tipe_perhitungan`: rasio_persen, penjumlahan, manual) — pembilang majemuk berbobot, maksimal satu tingkat perhitungan.
- Pengelolaan Periode (mis. Triwulan I–IV, Semester, Tahunan), Jadwal Tahunan (level tahun, termasuk jendela penyusunan Rencana Aksi), dan Jadwal Periode (jendela pengisian/reviu per periode di dalam suatu jadwal tahunan), termasuk aktivasi jadwal yang memicu pembuatan snapshot otomatis dan idempoten (termasuk snapshot cara hitung dan definisi komponen).
- Mekanisme buka kembali jadwal (`jadwal:buka_kembali`) sebagai jalur standar untuk: koreksi setelah penutupan, penambahan indikator IKU baru di tengah tahun, dan backfill data historis via jadwal retroaktif.
- Penugasan dan riwayat Penanggung Jawab per indikator.
- Penyusunan dan pengesahan Rencana Aksi per indikator per tahun: target dipecah per periode per komponen, bersifat kumulatif, melalui alur Draft → Diajukan → Diverifikasi → Disahkan (dengan Dikembalikan sebagai jalur revisi dan `rencana_aksi:buka_kembali` sebagai jalur koreksi setelah pengesahan).
- Penyusunan Kegiatan per periode per unit (status rencana/terlaksana/tidak terlaksana/ditunda/batal, termasuk penggeseran periode) dan Klaim Kegiatan yang mengaitkan kegiatan ke Rencana Aksi/komponen indikator, baik pada tahap penyusunan rencana aksi maupun tahap pengisian pengukuran.
- Pengelolaan persyaratan Bukti Dukung (`jenis_berkas`) per tahap (rencana aksi, pengukuran, atau **kegiatan**), dengan tiga mode pemenuhan yang dapat dikombinasikan per persyaratan — file, tautan, dan teks — beserta gerbang kelengkapan sebelum pengajuan/perubahan status, dan penyimpanan bukti (`berkas`) pada enam jenis induk: Rencana Aksi, Pengukuran, Kegiatan, Renstra, Perjanjian Kinerja (`renstra_pk`), dan Dasar Aturan (`regulasi`).
- Alur pengukuran linear 4 status efektif pada Fase Awal: Draft → Diajukan → Diverifikasi → Disahkan (dengan opsi Dikembalikan sebagai jalur revisi), digerbangi kelengkapan rencana aksi, komponen, dan berkas wajib (§19.4), termasuk jalur pengembalian khusus setelah pengesahan (`pengukuran:buka_kembali`) sebelum jadwal ditutup.
- Penetapan Status Capaian secara manual oleh Perencanaan/Superadmin.
- Penetapan Rekomendasi Pimpinan atas indikator × periode oleh Perencanaan pada Fase Awal.
- Rekapitulasi indikator × periode yang menggabungkan target PK, target periode (rencana aksi), capaian, nilai tiap komponen, daftar kegiatan yang diklaim beserta statusnya, narasi progres/kendala/tindak lanjut, dan rekomendasi Pimpinan dalam satu tampilan/ekspor.
- Dashboard ringkas berbasis ApexCharts menampilkan capaian tersahkan, progres rencana aksi, dan status kegiatan.
- Laporan tabular dengan ekspor ke Excel.
- Audit log penuh atas seluruh peristiwa yang disebutkan di §25, sejak hari pertama.
- Model hak akses RBAC (peran, katalog permission, isi peran, pemberian izin per unit, pencabutan izin) sebagai gate akses backend yang dievaluasi saat request dengan presedens deny menang, dengan UI pengelolaan yang disederhanakan (lihat §7.5).
- Alert kontekstual dasar (tenggat penyusunan rencana aksi, tenggat pengisian per periode, pengukuran/rencana aksi dikembalikan, kelengkapan berkas, dsb).
- Modul Setelan Aplikasi (§26): identitas instansi/aplikasi, label unit, preferensi tampilan/laporan — dikelola lewat tabel `pengaturan` key-value, terbatas hanya pada teks & preferensi presentasional — beserta halaman **"Batas unggahan berkas"** untuk mengubah `format_diizinkan`/`ukuran_maks_kb` per persyaratan bukti dukung (§26.4).
- Seed data pengembangan/testing (bukan data produksi) via seeder Laravel.

### 5.2 Tidak Termasuk / Ditunda ke Fase Lanjutan

- **Approval Pimpinan** dalam alur pengesahan pengukuran (kolom pendukung tersedia di skema, tetapi UI/alur belum dibangun) — Pimpinan tetap tidak masuk ke dalam alur rencana aksi maupun pengukuran pada Fase Awal; keterlibatannya terbatas pada pembacaan data dan hasil Rekomendasi Pimpinan yang diisikan Perencanaan (§22.2).
- **Formula perhitungan bertingkat** — mesin perhitungan Fase Awal hanya mendukung satu tingkat perhitungan (rasio atau penjumlahan komponen, dengan pembilang majemuk dan berbobot, §17.10). Formula yang menggabungkan beberapa sub-skor menjadi skor komposit (mis. Nilai SAKIP dan Nilai Zona Integritas yang seharusnya diturunkan dari sub-skor berbobot) tidak dibangun otomatis; indikator semacam ini memakai `tipe_perhitungan = manual`.
- **Ekspor PDF** rekap laporan dan **ekspor gambar grafik** dashboard (Fase Awal hanya ekspor tabel ke Excel).
- **Pengisian otomatis status capaian dari sistem lain** (data_sumber) — Fase Awal seluruh status capaian diisi manual.
- **UI matrix permission penuh** (mencentang seluruh katalog permission per pengguna) — Fase Awal hanya tiga form terbatas (assign peran, kelola grant izin per unit, kelola deny izin) beserta halaman "Jelaskan izin pengguna".
- **Impor data massal** (dari Excel/sistem lain) — di luar cakupan pada seluruh fase yang direncanakan saat ini, kecuali diputuskan lain di kemudian hari.
- **Revisi target tahunan PK di tengah tahun** — revisi target tahunan hanya efektif di antara tahun (lihat §12.7); jalur normal untuk target tidak tercapai adalah justifikasi tertulis, bukan revisi target. Ketentuan ini berlaku khusus untuk target tahunan PK — target periode (komponen) pada Rencana Aksi memiliki mekanisme revisi tersendiri di tengah tahun lewat `rencana_aksi:buka_kembali` (§14.6).
- **Modul anggaran kegiatan penuh** — kolom `kegiatan.anggaran` disiapkan pada skema untuk kebutuhan pencatatan sederhana, tetapi modul pelaporan/rekonsiliasi anggaran belum dibangun pada Fase Awal.
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

Hak akses SAKIP memakai **RBAC (Role-Based Access Control) dengan grant dan deny**, dievaluasi hidup pada setiap permintaan — bukan disalin ke baris izin statis per pengguna saat akun dibuat. Enam tabel menyusun model ini:

1. **`permissions`** — katalog permission (§7.2), sumbernya konstanta aplikasi yang di-seed ke basis data.
2. **`roles`** — lima peran bawaan (§7.3): `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`.
3. **`role_permissions`** — isi tiap peran sebagai data (§7.3): daftar permission yang melekat pada suatu peran. Permission yang berasal dari peran **selalu bersifat global** — tabel ini tidak memiliki kolom `unit_id`.
4. **`user_roles`** — peran yang dipegang tiap pengguna. Pada Fase Awal, satu pengguna memegang **tepat satu** peran (`unique(user_id)`); struktur pivot ini disiapkan agar multi-peran dapat dibuka di Fase Lanjutan hanya dengan melepas constraint tersebut.
5. **`user_permission_granted`** — pemberian izin tambahan di luar isi peran, opsional di-scope ke satu `unit_id` (`NULL` = global). Ini satu-satunya tempat scope unit hidup dalam model ini — mis. hak isi pengukuran/rencana aksi/kegiatan milik PIC diberikan di sini, bukan lewat isi peran.
6. **`user_permission_denied`** — pencabutan izin eksplisit, opsional di-scope ke satu `unit_id` (`NULL` = pencabutan menyeluruh). Dapat mencabut permission yang berasal dari peran maupun dari grant.

Permintaan otorisasi dijawab lewat algoritma resolusi izin (§7.4): himpunan izin dari peran + grant dibandingkan terhadap himpunan pencabutan, dengan **presedens deny menang** — bila ada pencabutan yang cocok, permintaan ditolak terlepas dari peran atau grant apa pun yang dimiliki pengguna. Pembedaan "tidak pernah diberi" dari "sengaja dicabut" ini dibutuhkan saat evaluasi AKIP/ZI mempertanyakan mengapa seseorang tidak dapat melakukan sesuatu meski perannya memungkinkan.

Sejumlah permission memiliki aturan scope kondisional (kolom `permissions.butuh_scope = unit`, §7.2), bukan seragam — polanya sama pada ketiganya:

- **`pengukuran:create`/`pengukuran:update`** — hak PIC diberikan lewat grant per unit (§7.5), **wajib** mengisi `unit_id` (tidak boleh NULL) dan tunduk pada batas jendela pengisian periode (§12.3). Hak Perencanaan bersumber dari isi peran `perencanaan` — selalu global — dan tidak tunduk pada jendela periode, hanya pada `penutupan` jadwal tahunan.
- **`rencana_aksi:create`/`rencana_aksi:update`/`rencana_aksi:ajukan`** — mengikuti pola identik: grant per unit dan dibatasi jendela `rencana_aksi_mulai`/`rencana_aksi_selesai` (§12.2) bagi PIC; global lewat isi peran dan dibatasi `penutupan` saja bagi Perencanaan.
- **`kegiatan:create`/`kegiatan:update`** — grant per unit bagi PIC (unit pelaksana kegiatan), global lewat isi peran bagi Perencanaan, tanpa jendela waktu eksplisit selain batas `penutupan` jadwal tahunan.

Seluruh permission lain bersifat `global` (`butuh_scope = global`) dan tidak pernah menerima `unit_id` pada grant maupun deny-nya.

### 7.2 Katalog Permission Lengkap

Katalog permission didefinisikan sebagai **konstanta aplikasi** (kode permission bertipe aman, tidak bisa salah ketik), lalu **di-seed** ke tabel `permissions`. Tabel hanya dibaca aplikasi; penambahan atau penghapusan permission dilakukan lewat rilis kode dan seeder — **bukan** lewat UI. Setiap baris permission memiliki kolom `butuh_scope` (`global` atau `unit`) dan `sensitif` (boolean) selain kolom `kode`/`entitas`/`aksi`/`keterangan` dasarnya:

- `butuh_scope = unit` hanya untuk tujuh permission: `pengukuran:create`, `pengukuran:update`, `rencana_aksi:create`, `rencana_aksi:update`, `rencana_aksi:ajukan`, `kegiatan:create`, `kegiatan:update`. Permission lain seluruhnya `global`.
- `sensitif = true` untuk: `pengukuran:sahkan`, `pengukuran:buka_kembali`, `pengukuran:verifikasi`, `rencana_aksi:verifikasi`, `rencana_aksi:sahkan`, `rencana_aksi:buka_kembali`, `jadwal:aktivasi`, `jadwal:tutup`, `jadwal:buka_kembali`, `status_capaian:update`, `rekomendasi:tetapkan`, `komponen:update`, `komponen:delete`, `jenis_berkas:update`, `jenis_berkas:delete`, `regulasi:update`, `regulasi:delete`, `akses:update`, `pengaturan:update`, `berkas:delete`, `kegiatan:delete` — aksi dengan permission ini wajib mencatat **dasar izin** di `audit_log` (§7.4, §25.2).

| Entitas | Aksi tersedia |
|---|---|
| Renstra | `renstra:create`, `renstra:read`, `renstra:update`, `renstra:delete` |
| Sasaran | `sasaran:create`, `sasaran:update`, `sasaran:delete` |
| Indikator | `indikator:create`, `indikator:read`, `indikator:update`, `indikator:delete` |
| Target | `target:update` |
| PK (Perjanjian Kinerja) | `pk:create`, `pk:update` |
| Dasar Aturan | `regulasi:create`, `regulasi:read`, `regulasi:update`, `regulasi:delete` |
| Periode & Jadwal | `periode:create`, `periode:update`, `jadwal:create`, `jadwal:update`, `jadwal:aktivasi`, `jadwal:tutup`, `jadwal:buka_kembali` |
| Penugasan | `penanggung_jawab:update` |
| Rencana Aksi | `rencana_aksi:read`, `rencana_aksi:create` *(scoped unit untuk PIC, global untuk Perencanaan)*, `rencana_aksi:update` *(idem)*, `rencana_aksi:ajukan` *(idem)*, `rencana_aksi:verifikasi`, `rencana_aksi:kembalikan`, `rencana_aksi:sahkan`, `rencana_aksi:buka_kembali` |
| Kegiatan | `kegiatan:read`, `kegiatan:create` *(scoped unit untuk PIC, global untuk Perencanaan)*, `kegiatan:update` *(idem)*, `kegiatan:delete` |
| Komponen Indikator | `komponen:create`, `komponen:read`, `komponen:update`, `komponen:delete` |
| Berkas Persyaratan | `jenis_berkas:create`, `jenis_berkas:read`, `jenis_berkas:update`, `jenis_berkas:delete`, `berkas:read`, `berkas:upload`, `berkas:delete` |
| Alur Pengukuran | `pengukuran:create` *(scoped unit untuk PIC, global untuk Perencanaan)*, `pengukuran:update` *(idem)*, `pengukuran:read`, `pengukuran:verifikasi`, `pengukuran:kembalikan`, `pengukuran:sahkan`, `pengukuran:buka_kembali`, `pengukuran:setujui` *(Fase Lanjutan)* |
| Status Capaian | `status_capaian:update` |
| Rekomendasi Pimpinan | `rekomendasi:tetapkan` |
| Unit | `unit:create`, `unit:read`, `unit:update`, `unit:delete` |
| Pengguna & Akses | `pengguna:read`, `akses:update` |
| Dashboard & Laporan | `dashboard:read`, `laporan:read`, `laporan:ekspor` |
| Audit | `audit:read` |
| Setelan Aplikasi | `pengaturan:update` |

Catatan perbedaan dua permission koreksi: `pengukuran:kembalikan`/`rencana_aksi:kembalikan` menangani jalur normal (Diajukan/Diverifikasi → Dikembalikan) sebagai bagian alur reviu biasa; `pengukuran:buka_kembali`/`rencana_aksi:buka_kembali` menangani jalur khusus (Disahkan → Dikembalikan) — lihat §19.5 dan §14.6.

### 7.3 Isi Peran (`role_permissions`)

Isi tiap peran adalah **data**, disimpan sebagai baris `role_permissions` (pasangan `role_id`–`permission_id`, `unique(role_id, permission_id)`), bukan daftar hardcode di kode aplikasi. Perubahan isi peran berlaku langsung bagi seluruh pemegangnya begitu disimpan — konsekuensi yang disadari, sehingga setiap perubahan **wajib tercatat di `audit_log`** dengan `nilai_lama`/`nilai_baru` (daftar permission sebelum/sesudah) dan `alasan`.

Lima peran bawaan (`roles.is_sistem = true`, tidak dapat dihapus) dan isi permission masing-masing:

| Peran | Permission yang diberikan |
|---|---|
| **Superadmin** | Seluruh permission di katalog (§7.2), tanpa kecuali, termasuk `pengaturan:update`, `komponen:*`, `jenis_berkas:*`, `regulasi:*`, `rekomendasi:tetapkan`. Diposisikan sebagai akses penuh/*break-glass* untuk keperluan teknis darurat (mis. pemulihan konfigurasi, penanganan insiden akses) — bukan akun operasional harian. Tindakan substantif apa pun yang dilakukan lewat Superadmin (mis. mengubah data kinerja, menyahkan pengukuran) bersifat pengecualian dan tetap sepenuhnya ter-audit seperti peran lain. |
| **Admin** | `pengguna:read`, `akses:update`, `unit:create`, `unit:read`, `unit:update`, `unit:delete`, `pengaturan:update`, `komponen:read`, `jenis_berkas:read`, `regulasi:read`, `audit:read`, `dashboard:read`, `laporan:read`. **Tidak** termasuk: seluruh `renstra:*`/`sasaran:*`/`indikator:*`/`target:update`/`pk:*`/`periode:*`/`jadwal:*`, `penanggung_jawab:update`, permission alur Rencana Aksi (`rencana_aksi:*`), Kegiatan (`kegiatan:*`), `komponen:create`/`update`/`delete`, `jenis_berkas:create`/`update`/`delete`, `regulasi:create`/`update`/`delete`, `berkas:*`, permission alur Pengukuran (`create`/`update`/`verifikasi`/`kembalikan`/`sahkan`/`buka_kembali`), `status_capaian:update`, `rekomendasi:tetapkan`, dan `laporan:ekspor`. Peran ini mengelola akun pengguna, unit, dan setelan aplikasi (termasuk halaman "Batas unggahan berkas", §26.4) — bukan data kinerja dan bukan substansi persyaratan bukti dukung atau dasar aturan. |
| **Perencanaan** | `renstra:*`, `sasaran:*`, `indikator:*`, `target:update`, `pk:*`, `regulasi:*`, `periode:*`, `jadwal:*`, `penanggung_jawab:update`, seluruh `rencana_aksi:*` **(global — isi peran tidak memiliki kolom `unit_id`, untuk `create`/`update`/`ajukan`)**, `kegiatan:read`/`create`/`update`/`delete` **(global)**, `komponen:*`, `jenis_berkas:*`, `berkas:read`/`upload`/`delete`, `pengukuran:create`/`pengukuran:update` **(global)**, `pengukuran:read`, `pengukuran:verifikasi`, `pengukuran:kembalikan`, `pengukuran:sahkan`, `pengukuran:buka_kembali`, `status_capaian:update`, `rekomendasi:tetapkan`, `dashboard:read`, `laporan:read`, `laporan:ekspor`, `audit:read` |
| **Pimpinan** | `pengukuran:read`, `pengukuran:setujui` *(disiapkan untuk Fase Lanjutan, belum dipakai pada alur Fase Awal — lihat §19.2)*, `rencana_aksi:read`, `kegiatan:read`, `komponen:read`, `jenis_berkas:read`, `regulasi:read`, `berkas:read`, pembacaan Rekomendasi Pimpinan, `dashboard:read`, `laporan:read`, `laporan:ekspor`, `audit:read` |
| **Pegawai** | `pengukuran:read`, `komponen:read`, `jenis_berkas:read`, `regulasi:read`, `dashboard:read`. Hak `pengukuran:create`/`update`, `rencana_aksi:read`/`create`/`update`/`ajukan`, dan `kegiatan:read`/`create`/`update` **tidak** termasuk dalam isi peran ini — harus diberikan eksplisit per unit lewat grant (§7.5), dan tunduk pada batas jendela masing-masing (§12.2, §12.3) |

Pada Fase Awal, Pimpinan tidak berperan aktif dalam alur pengesahan rencana aksi maupun pengukuran — perannya murni pemantauan (dashboard, laporan, audit read-only, serta pembacaan Rekomendasi Pimpinan yang diisikan Perencanaan). Permission `pengukuran:setujui` tetap didefinisikan di katalog dan di isi peran Pimpinan agar skema serta kode sudah siap saat Fase Lanjutan mengaktifkan alur approval, meski belum ada UI yang memanggilnya sekarang.

Pemisahan Admin dari Perencanaan menegakkan segregasi tugas (*segregation of duties*): pengelola akun, unit, dan setelan aplikasi tidak otomatis memegang wewenang substantif atas data kinerja (Renstra, indikator, komponen, target, rencana aksi, kegiatan, pengukuran, dasar aturan). Permission `pengaturan:update` dimiliki **Admin dan Superadmin** — bukan Superadmin saja (§26.4) — dan mencakup kewenangan mengubah halaman "Batas unggahan berkas" (§26.4), tetap tanpa wewenang atas substansi persyaratan (`jenis_berkas:*`) maupun dasar aturan (`regulasi:*`) yang tetap dipegang Perencanaan.

### 7.4 Algoritma Resolusi Izin

Setiap permintaan otorisasi menjawab pertanyaan **"boleh(kode_permission, unit_target?)"** untuk aktor yang sedang login. Pertanyaan tanpa `unit_target` hanya sah diajukan untuk permission bertipe `global`. Resolusi dijalankan sebagai berikut:

1. **Fail closed.** Bila tidak ada baris `permissions` aktif dengan `kode` tersebut, jawabannya langsung **tolak** — kode permission yang tidak dikenal tidak pernah dianggap "diizinkan secara default".
2. **Susun himpunan allow**: (a) seluruh permission dari peran pengguna (`user_roles` → `role_permissions`), diperlakukan **global**; (b) baris `user_permission_granted` milik pengguna yang cocok.
3. **Susun himpunan deny**: baris `user_permission_denied` milik pengguna yang cocok.
4. **Pencocokan scope.** Untuk pertanyaan dengan `unit_target = U`: deny cocok bila `unit_id IS NULL` **atau** `unit_id = U`; grant cocok bila `unit_id = U`. Untuk pertanyaan tanpa `unit_target`: deny ber-`unit_id` **tidak** menghalangi (izin untuk unit lain tetap berlaku); deny dengan `unit_id IS NULL` selalu menghalangi.
5. **Presedens: deny menang.** Ada deny yang cocok → **tolak**. Tidak ada deny yang cocok tetapi ada allow yang cocok → **izinkan**. Tidak ada allow yang cocok → **tolak**.
6. **Terpisah dari validasi bisnis.** Jendela waktu (periode, rencana aksi) dan kepemilikan unit adalah validasi bisnis yang berjalan **setelah** izin dinyatakan "boleh" — bukan bagian dari resolusi izin. Izin menjawab "apakah boleh"; validasi bisnis menjawab "apakah masih dalam waktunya, untuk record yang benar, dan lewat gerbang kelengkapan yang benar" (§12.3, §14.7, §19.4).
7. **Tidak ada evaluasi izin di sisi klien.** Resolusi hanya dijalankan di server (Policy/Gate/service layer Laravel). Halaman React menyembunyikan tombol berdasarkan hasil yang dikirim server, tetapi server tetap mengevaluasi ulang setiap permintaan — modifikasi tampilan di klien tidak pernah melewati gerbang ini.

Untuk aksi dengan `permissions.sensitif = true` (§7.2), hasil resolusi — sumber allow yang mengizinkan (peran mana/grant mana) atau deny yang memicu penolakan — dicatat sebagai **`dasar_izin`** pada `audit_log` (§25.2).

### 7.5 UI Pengelolaan Akses (Fase Awal)

Pada Fase Awal, UI pengelolaan akses disederhanakan menjadi tiga form dan satu halaman:

1. **Assign Peran** — Superadmin/Admin memilih pengguna dan menetapkan satu peran (Superadmin/Admin/Perencanaan/Pimpinan/Pegawai). Menyimpan baris di `user_roles` (constraint `unique(user_id)` pada Fase Awal).
2. **Kelola Grant Izin per Unit** — Superadmin/Admin memilih pengguna, permission bertipe `unit` (`pengukuran:create`/`update`, `rencana_aksi:create`/`update`/`ajukan`, `kegiatan:create`/`update`), unit target, dan alasan (wajib), lalu menyimpan baris `user_permission_granted`. Form ini adalah satu-satunya jalur baku pemberian hak isi pengukuran, rencana aksi, dan kegiatan bagi Pegawai/PIC — konsisten dengan pola bahwa seluruh hak isi data kinerja tingkat unit diberikan lewat satu form grant, bukan form terpisah per entitas. `berkas:read`/`upload`/`delete` mengikuti otomatis lewat kepemilikan induk (rencana aksi/pengukuran/kegiatan) yang menjadi hak akses PIC unit tersebut, tanpa baris grant tersendiri.
3. **Kelola Deny Izin** — Superadmin/Admin memilih pengguna, permission (global atau ber-unit), unit target (opsional — kosong berarti pencabutan menyeluruh), dan alasan (wajib), lalu menyimpan baris `user_permission_denied`.
4. **"Jelaskan izin pengguna"** — pilih pengguna, sistem menampilkan daftar izin efektif per unit lengkap dengan **asal tiap izin** (peran/grant) dan deny yang berlaku. Halaman ini digerbangi permission `pengguna:read` (Admin/Superadmin) — tidak ada permission baru yang dibuat untuk halaman ini.

Ketiga form digerbangi permission `akses:update`, yang hanya dimiliki Superadmin dan Admin — konsisten dengan segregasi tugas pada §7.3: pengelolaan akun/akses terpisah dari kewenangan atas data kinerja yang dipegang Perencanaan.

Di luar form-form ini, permission individual ad-hoc (kasus edge di luar isi peran standar) ditulis lewat seeder atau query manual database oleh tim teknis pada Fase Awal, bukan lewat UI. UI matrix permission penuh — mencentang bebas seluruh permission per pengguna — ditunda ke Fase Lanjutan.

### 7.6 Pemisahan Tugas

Dua aturan bisnis keras berikut ditegakkan di luar resolusi izin (§7.4) — tidak dapat "dinonaktifkan" lewat pemberian permission apa pun, karena keduanya membandingkan identitas aktor terhadap data, bukan sekadar memeriksa kepemilikan permission:

- **Jalur PIC tidak boleh menilai pekerjaannya sendiri.** Pengaju Pengukuran yang mengisi lewat grant scope unit (jalur PIC) **tidak dapat** memverifikasi atau mengesahkan Pengukuran yang sama — ditegakkan dengan membandingkan aktor terhadap `pengukuran.created_by` pada transisi `diajukan → diverifikasi` dan `diverifikasi → disahkan` (§19.1, §19.5).
- **Jalur Perencanaan boleh mengesahkan sendiri, dengan penanda.** Perencanaan yang mengisi Pengukuran atas nama unit (permission global, dipakai saat tenggat PIC terlewat atau untuk backfill, §12.6) **diizinkan** memverifikasi/mengesahkan pengukuran yang diisinya sendiri. Aksi ini diberi penanda **`self_approval`** di `audit_log` dan tampil sebagai penanda pada dashboard/laporan Perencanaan (§23, §25.2) — bukan disembunyikan. Alasan keputusan ini: bila jalur ini diblokir sepenuhnya, LLDIKTI XVI wajib menugaskan minimal dua akun Perencanaan agar pengisian yang terlambat masih dapat disahkan — kondisi yang belum tentu terpenuhi pada Fase Awal.

Kedua aturan ini tidak menggantikan resolusi izin (§7.4): aktor tetap harus memiliki permission `pengukuran:verifikasi`/`pengukuran:sahkan` terlebih dahulu sebelum aturan pemisahan tugas ini dievaluasi.

### 7.7 Unit

`unit` menggantikan istilah "tim kerja" pada seluruh lapisan sistem — nama tabel, kolom FK (`unit_id` pada `indikator`, `jadwal_snapshot`, `user_permission_granted`, `user_permission_denied`, `rencana_aksi`, `kegiatan`), permission (`unit:create/read/update/delete`), audit event (`unit.hapus`, dst.), dan seluruh teks aplikasi.

Definisi baku: `unit` adalah kelompok organisasi pemilik indikator dan penentu scope grant/deny permission pengukuran/rencana aksi/kegiatan — **bukan** satuan ukur (itu `indikator.satuan`). Penamaan sengaja tidak memakai "unit kerja" agar tidak bertabrakan dengan kosakata evaluasi ZI/SAKIP yang sudah punya makna berbeda. Label tampilan di UI (mis. jika institusi ingin menyebutnya "Bagian"/"Bidang") dapat disetel lewat modul Setelan Aplikasi (§7.7, §26.3) tanpa mengubah struktur data.

- `unit` adalah master global (bukan sub-organisasi bertingkat), tanpa tabel keanggotaan eksplisit — keterkaitan pengguna ke unit terjadi melalui scope pada `user_permission_granted.unit_id` (dan pencabutannya pada `user_permission_denied.unit_id`), dan keterkaitan indikator/kegiatan/rencana aksi ke unit melalui kolom `unit_id` masing-masing.
- Unit berstatus `aktif` atau `nonaktif`.
- Unit yang masih memiliki indikator, rencana aksi, atau kegiatan terkait tidak dapat dihapus.
- Unit kosong (tanpa keterkaitan) dapat dihapus, hanya oleh Superadmin.
- Perpindahan indikator antar unit adalah tindakan yang dicatat di audit log (lihat §25).

---

## 8. Struktur Informasi

Hierarki informasi utama dalam SAKIP, dari yang paling strategis ke paling operasional:

1. **Renstra** — payung strategis 1 (satu) rentang tahun (mis. 2025–2029), berisi banyak Sasaran.
2. **Sasaran** — tujuan strategis di bawah Renstra, memiliki urutan tampil, berisi banyak Indikator.
3. **Indikator** — unit ukur kinerja konkret, dimiliki oleh tepat satu Unit, memiliki arah penilaian (`arah`), cara hitung (`tipe_perhitungan`), rujukan dasar aturan (`regulasi_id`), Target Tahunan per tahun, Penanggung Jawab (riwayat), dan — bila bukan `manual` — daftar Komponen Indikator yang menyusun rumus perhitungannya.
4. **Perjanjian Kinerja (PK)** — dokumen resmi per tahun per Renstra yang mengesahkan target-target tahun tersebut, dapat dilampiri dokumen pendukung.
5. **Dasar Aturan (`regulasi`)** — produk hukum (Kepmen/Permen/Perpres/keputusan lainnya) yang menjadi acuan penyusunan Renstra dan/atau indikator, dirujuk lewat `renstra.regulasi_id`/`indikator.regulasi_id`, dan dapat dilampiri dokumen sumbernya.
6. **Jadwal Tahunan** — bingkai tahun tertentu dalam satu Renstra, memuat status siklus, jendela penyusunan Rencana Aksi, dan penutupan; saat diaktifkan menghasilkan Snapshot Jadwal.
7. **Jadwal Periode** — daftar periode yang diharapkan pada tahun tersebut beserta jendela pengisian dan reviu masing-masing (mis. Triwulan I–IV punya jendela berbeda-beda dalam satu jadwal tahunan yang sama).
8. **Snapshot Jadwal** — salinan beku konteks indikator (nama, definisi, satuan, presisi, target, arah, cara hitung, dan definisi komponen) pada saat jadwal diaktifkan; menjadi rujukan Pengukuran, bukan master langsung.
9. **Rencana Aksi** — rencana capaian per indikator per tahun, disusun dan disahkan sebelum pengukuran periode pertama diajukan; target dipecah per periode per komponen dan bersifat kumulatif.
10. **Kegiatan** — aktivitas per unit per periode yang dilaksanakan untuk mendukung capaian indikator; dapat diklaim ke satu atau lebih Rencana Aksi/komponen lewat Klaim Kegiatan.
11. **Pengukuran** — nilai capaian aktual per Indikator, per Tahun, per Periode, yang melalui alur status; untuk indikator berkomponen, nilai capaian diturunkan dari nilai komponen (Pengukuran Komponen).
12. **Berkas** — bukti dukung (mode file, tautan, atau teks) yang menempel pada enam jenis induk: Rencana Aksi, Pengukuran, Kegiatan, Renstra, Perjanjian Kinerja, atau Dasar Aturan, sesuai persyaratan (Jenis Berkas) yang ditetapkan Perencanaan untuk tiga induk pertama, atau sebagai lampiran bebas untuk ketiga induk lainnya.
13. **Status Capaian** — penilaian akhir (Tercapai/Belum Tercapai) atas suatu Pengukuran.
14. **Rekomendasi Pimpinan** — catatan evaluasi atas indikator × periode, diisi Perencanaan pada Fase Awal.

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

Halaman kerja tersendiri untuk CRUD `regulasi` beserta lampirannya: daftar produk hukum (jenis, nomor, tahun, tentang, status aktif), formulir tambah/ubah, dan panel lampiran dokumen. Halaman Renstra menampilkan rujukan `regulasi_id` beserta ringkasan `dasar_hukum` dan lampiran dokumen Renstra (§10.6); halaman Perjanjian Kinerja menampilkan lampiran dokumen PK beserta `nomor_pk`/`tanggal_pk` (§14.2).

### 9.7 Hak Akses

Permission **`regulasi:create`**, **`regulasi:read`**, **`regulasi:update`**, **`regulasi:delete`** (`update` dan `delete` bertanda `sensitif = true`, §7.2). Isi peran (§7.3): **Perencanaan** dan **Superadmin** memegang keempat aksi; **Admin**, **Pimpinan**, dan **Pegawai** memegang **`regulasi:read`** saja — dasar aturan tampil di halaman Renstra/indikator agar konteksnya terbaca oleh siapa pun yang berhak membuka halaman itu, tanpa memberi wewenang substantif kepada Admin atas isi dasar aturan.

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
- **IKU baru** → dibuatkan indikator baru beserta definisi komponen (bila bertipe rasio/penjumlahan), `target_tahunan` tahun berjalan, dan revisi PK yang relevan, lalu dimasukkan ke jadwal tahun berjalan melalui `jadwal:buka_kembali` (§12.6) — ini adalah mekanisme standar penambahan indikator di tengah tahun, termasuk untuk mengisi periode yang tersisa pada tahun tersebut. Indikator yang masuk lewat jalur ini tetap memerlukan penyusunan dan pengesahan Rencana Aksi tersendiri (§14) sebelum pengukuran periode-periode yang tersisa dapat diajukan.
- Indikator baru yang dibuat setelah jadwal aktif namun **tanpa** melewati `jadwal:buka_kembali` baru terukur mulai jadwal tahun berikutnya — ini adalah konsekuensi desain yang disadari, bukan celah.

### 10.6 Lampiran Dokumen Renstra

Halaman Renstra menampilkan panel lampiran dokumen (`berkas` dengan `berkasable_type = renstra`, §18.4) — dokumen pendukung penyusunan Renstra (mis. salinan Kepmen, naskah akademik, hasil rapat penetapan), mode file/tautan/teks. **Lampiran renstra tidak dapat dihapus setelah Renstra berstatus `aktif`** (§18.8) — sebelum status itu tercapai, Perencanaan/pengunggah dapat menghapus lampiran; penghapusan tercatat di `audit_log`.

---

## 11. Pengelolaan Indikator

- Setiap Indikator dimiliki oleh tepat satu Unit (`indikator.unit_id NOT NULL`) — menentukan siapa yang berhak mengajukan permission `pengukuran:create`/`update`, `rencana_aksi:create`/`update`/`ajukan` untuk indikator tersebut.
- Atribut kunci: `nama`, `definisi`, `satuan`, `presisi` (jumlah digit desimal disimpan), `desimal_tampilan` (jumlah digit desimal ditampilkan — dapat berbeda dari presisi penyimpanan untuk keperluan pembulatan tampilan), `tahun_mulai_berlaku`, `arah`, `tipe_perhitungan`, **`regulasi_id`** (FK → regulasi, nullable — dasar aturan spesifik indikator ini, §9.3).
- **`arah`** — enum(`naik_baik`, `turun_baik`), default `naik_baik` — menyatakan interpretasi nilai indikator: `naik_baik` berarti nilai yang lebih tinggi menandakan kinerja lebih baik (mis. jumlah lulusan bersertifikat), sedangkan `turun_baik` berarti nilai yang lebih rendah menandakan kinerja lebih baik (mis. rata-rata masa tunggu penyelesaian layanan). Kolom ini dibangun sejak MVP dan disalin ke `jadwal_snapshot` sebagai bagian konteks beku (§12.5), sehingga evaluasi pemburukan nilai (§20) selalu memakai arah yang berlaku saat periode itu diaktifkan, dan dihitung atas nilai turunan bila indikator berkomponen (§17.6).
- **`tipe_perhitungan`** — enum(`rasio_persen`, `penjumlahan`, `manual`), not null, default `manual` — menentukan bagaimana `pengukuran.nilai` dihasilkan. Untuk `rasio_persen` dan `penjumlahan`, indikator wajib memiliki baris `indikator_komponen` aktif yang memenuhi syarat minimal perannya (§17.3); untuk `manual`, komponen tidak wajib dan nilai diketik langsung, sama seperti perilaku sebelum ronde ini. Mesin perhitungan lengkap dibahas di §17.
- Status Indikator: `aktif` atau `arsip`. Indikator berstatus `arsip` tidak lagi muncul sebagai target pengisian baru — `pengukuran:create` dan `rencana_aksi:create` untuk indikator tersebut **ditolak sistem** — namun data historis tetap tersimpan dan dapat ditelusuri di laporan.
- Flag `wajib_catatan` — jika `true`, sistem mewajibkan pengisian kolom catatan pada Pengukuran indikator ini setiap kali diajukan (terlepas dari kondisi nilai naik/turun).
- Perpindahan indikator antar unit adalah operasi terkendali: memerlukan permission `indikator:update`, dan wajib tercatat di audit log (nilai lama dan baru dari `unit_id`) karena berdampak pada siapa yang berhak mengisi rencana aksi/pengukuran ke depan.
- Penghapusan indikator (`indikator:delete`) tunduk pada aturan integritas: indikator yang telah memiliki data pengukuran atau rencana aksi tidak dihapus secara fisik; wajib diarsipkan. Percobaan penghapusan tetap dicatat di audit log (lihat §25).
- `target_tahunan` mendapat kolom baru **`baseline`** (numeric, nullable) — nilai capaian tahun sebelumnya yang dipakai sebagai acuan saat menyusun target tahun berjalan. Kolom ini disalin ke `jadwal_snapshot` (§12.5) karena dokumen PK dan rekapitulasi indikator × periode (§22.4) menampilkan kolom "Baseline" yang dipakai berulang di setiap periode.

---

## 12. Periode & Jadwal

### 12.1 Periode (Master)

`periode` adalah master global yang mendefinisikan satuan waktu pelaporan dalam satu tahun (mis. Triwulan I, Triwulan II, Triwulan III, Triwulan IV, atau Semester I/II, atau Tahunan), masing-masing memiliki `urutan` tampil dan flag `aktif`. Tepat satu periode per konfigurasi ditandai `is_nilai_akhir = true` — periode yang dianggap sebagai representasi capaian akhir tahun (mis. Triwulan IV/Tahunan) untuk keperluan rekap tahunan pada dashboard/laporan. Nilai pada periode dengan `is_nilai_akhir = true` **diisi manual** oleh penanggung jawab/Perencanaan — sistem tidak menghitung agregasi otomatis dari periode-periode sebelumnya.

### 12.2 Jadwal Tahunan (Level Tahun)

Setiap kombinasi Renstra + Tahun memiliki satu `jadwal_tahunan` yang membingkai tahun tersebut:

- `rencana_aksi_mulai`, `rencana_aksi_selesai` (date, nullable) — jendela penyusunan dan pengesahan Rencana Aksi, bersifat jendela tingkat tahun (bukan per periode), karena Rencana Aksi disusun sekali per (indikator × tahun), bukan per periode. Pola penegakannya sama seperti pengisian pengukuran: deadline **mutlak bagi PIC**; **Perencanaan dikecualikan** (permission global tanpa scope unit, dibatasi hanya oleh `penutupan`).
- `penutupan` — tanggal batas akhir seluruh aktivitas jadwal tahun tersebut, termasuk pengecualian Perencanaan pada jendela Rencana Aksi maupun jendela pengisian periode (§7.1, §12.3).
- `status`: `draft → aktif → ditutup`.
- `pakai_persetujuan_pimpinan` (boolean, default `false`) dan `persetujuan_mulai`/`persetujuan_selesai` — kolom disiapkan untuk Fase Lanjutan; pada Fase Awal selalu `false` dan tidak dipakai dalam alur (lihat §19.2).
- `renstra_pk_id` — wajib diisi saat aktivasi; lihat gerbang validasi §12.4.
- `activated_at` / `closed_at` — stempel waktu otomatis saat transisi status terjadi; nilai ini selalu mencatat waktu aktivasi/penutupan yang sebenarnya, termasuk untuk jadwal retroaktif (§12.6).
- Constraint: unique per tahun di antara jadwal berstatus aktif untuk Renstra yang sama (tidak boleh dua jadwal aktif tumpang tindih tahun yang sama pada satu Renstra).

Jendela waktu pengisian dan reviu pengukuran **tidak berada** di `jadwal_tahunan` — jendela itu dipecah per periode pada tabel `jadwal_periode` (§12.3), karena periode berbeda (mis. Triwulan I vs Triwulan IV) lazimnya punya tenggat yang berbeda pula. Jendela Rencana Aksi sebaliknya memang berada di level tahun karena disusun sekali per tahun.

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
- Status tampilan "Belum mengisi"/"Tidak mengisi" pada dashboard (§23) dihitung per kombinasi **indikator × periode yang diharapkan** dari `jadwal_periode` — bukan dari tanggal generik. Indikator berstatus `arsip` tidak dihitung sebagai kewajiban pengisian.

Rencana Aksi disusun **setelah** daftar `jadwal_periode` pada jadwal tahunan tersusun (target per periode butuh daftar periodenya), dan **sebelum** jendela pengisian periode pertama dibuka. Urutan ini adalah bagian dari alur Rencana Aksi (§14.1), bukan sekadar rekomendasi operasional.

### 12.4 Gerbang Validasi Aktivasi Jadwal

Aktivasi jadwal (`jadwal:aktivasi`) **ditolak** jika salah satu dari empat syarat berikut tidak terpenuhi:

1. `renstra_pk` untuk pasangan (renstra, tahun) belum tercatat — target yang diukur harus berlandaskan PK yang sah.
2. Ada indikator aktif pada Renstra tersebut yang belum memiliki `target_tahunan` untuk tahun yang bersangkutan.
3. `jadwal.tahun` berada di luar rentang `[tahun_mulai, tahun_akhir]` Renstra.
4. **`renstra_pk` tahun tersebut belum memiliki minimal satu lampiran dokumen** (`berkas` dengan `berkasable_type = renstra_pk`, mode file/tautan/teks bebas — §18.4, §14.10). Syarat ini sengaja dibuat bebas mode agar tidak memacetkan alur: bila unggahan file dimatikan pada setelan aplikasi sementara belum ada lampiran mode lain, gerbang ini ditandai `tidak_dapat_dipenuhi` (§18.7) dan **tidak** menghalangi aktivasi — konsisten dengan aturan anti-macet yang berlaku pada gerbang bukti dukung lain.

Keempat gerbang ini dievaluasi di service layer sebagai validasi utama. Sebagai lapisan pertahanan kedua, direkomendasikan PostgreSQL exclusion constraint (`EXCLUDE USING gist` + ekstensi `btree_gist`) untuk menegakkan aturan rentang tahun Renstra yang tidak boleh beririsan pada level basis data, sebagai jaring pengaman tambahan di luar validasi aplikasi. Aktivasi jadwal tidak digerbangi oleh kelengkapan Rencana Aksi — Rencana Aksi baru disusun setelah jadwal aktif (§14.1); gerbang kelengkapan Rencana Aksi berlaku pada pengajuan Pengukuran (§19.4), bukan pada aktivasi jadwal.

### 12.5 Snapshot Saat Aktivasi (Fase Awal — Fondasi MVP)

Saat jadwal berpindah status ke `aktif` (aksi `jadwal:aktivasi`) — termasuk saat `jadwal:buka_kembali` dijalankan atas jadwal yang sudah pernah aktif sebelumnya — sistem secara otomatis (dipicu oleh logika aplikasi, bukan trigger basis data) membuat baris `jadwal_snapshot` untuk setiap indikator aktif yang relevan pada Renstra tersebut, menyalin:

- Referensi ke indikator master (`indikator_id`) untuk ketertelusuran.
- `unit_id`, `nama`, `definisi`, `satuan`, `presisi`, `desimal_tampilan`, `arah`, `tipe_perhitungan`, `baseline` — salinan beku dari kondisi master saat itu.
- `target` — salinan nilai `target_tahunan` untuk tahun jadwal tersebut (nullable jika target belum diisi saat aktivasi — kondisi ini sudah dicegah oleh gerbang §12.4 poin 2 untuk indikator aktif pada aktivasi awal).

Untuk indikator bertipe `tipe_perhitungan` selain `manual`, sistem juga membuat baris anak **`jadwal_snapshot_komponen`** (`jadwal_snapshot_id`, `kode`, `label`, `peran`, `bobot`, `urutan`) yang membekukan definisi komponen indikator (§17.9) pada saat aktivasi, sehingga perubahan definisi komponen di tengah tahun tidak mengubah makna data historis yang sudah dirujuk pengukuran.

Aturan pembentukan snapshot:

- **Idempoten** — baik pada aktivasi awal maupun pada `jadwal:buka_kembali`, sistem hanya membuat baris `jadwal_snapshot` (dan `jadwal_snapshot_komponen` anaknya) untuk pasangan (`jadwal_id`, `indikator_id`) yang **belum ada**. Baris lama tidak pernah ditimpa.
- **Abadi setelah dirujuk** — begitu suatu baris snapshot dirujuk oleh pengukuran pertamanya, baris itu (beserta baris komponennya) tidak dapat lagi diubah. Sebelum dirujuk pengukuran mana pun, baris snapshot boleh dikoreksi hanya selama jadwal berstatus `aktif` (mis. setelah `jadwal:buka_kembali`), dan koreksi itu wajib teraudit. Tidak ada restatement data historis dalam bentuk apa pun.
- Baris `audit_log` untuk pembuatan snapshot memakai `actor_id` = pengguna Perencanaan yang menjalankan aksi aktivasi — jejaknya menempel pada aksi manusia yang memicunya, bukan pada proses sistem anonim.

Setiap `pengukuran` merujuk ke `jadwal_snapshot_id`, bukan langsung ke `indikator_id` master, untuk keperluan tampilan (nama, satuan, target pembanding, arah, cara hitung). Keputusan ini masuk sebagai fondasi MVP sejak Fase Awal, bukan fitur yang ditunda — tanpa snapshot, perubahan pada master indikator maupun definisi komponennya di masa depan akan mendistorsi tampilan capaian tahun-tahun yang sudah disahkan.

Implikasi operasional dari keputusan ini dijabarkan di §34 (Catatan Risiko).

### 12.6 Buka Kembali Jadwal: Koreksi, Penambahan Indikator, dan Backfill

`jadwal:buka_kembali` (`ditutup → aktif`) adalah satu-satunya jalur setelah penutupan untuk melakukan hal berikut, seluruhnya teraudit:

- **Koreksi data setelah penutupan** — jadwal dibuka kembali, data pengukuran/snapshot yang relevan dikoreksi, lalu `jadwal:tutup` dijalankan kembali untuk membekukannya. Sebelum penutupan, koreksi status pengesahan pengukuran cukup memakai `pengukuran:buka_kembali` (§19.5), dan koreksi status pengesahan rencana aksi cukup memakai `rencana_aksi:buka_kembali` (§14.6), tanpa perlu membuka jadwal.
- **Penambahan indikator IKU baru di tengah tahun** (§10.5) — mekanisme standar, bukan pengecualian: snapshot baru dibuat secara idempoten hanya untuk indikator yang belum memiliki baris snapshot, sehingga indikator lama yang sudah berjalan tidak terganggu. Indikator baru ini tetap memerlukan Rencana Aksi tersendiri sebelum pengukurannya dapat diajukan.
- **Backfill data historis** — Perencanaan membuat **jadwal retroaktif** untuk tahun lampau di dalam rentang Renstra (dengan `renstra_pk` tahun tersebut wajib dicatat lebih dulu), mengaktifkannya, lalu mengisi sendiri pengukurannya. PIC otomatis terkunci dari jadwal ini karena deadline periode-periode di dalamnya sudah lewat (§12.3). Jadwal retroaktif **dikecualikan** dari gerbang kelengkapan rencana aksi/komponen/berkas pada pengajuan pengukuran (§19.4) — tujuannya memang mengisi data historis yang lazimnya tidak melalui proses rencana aksi/berkas normal pada masanya. `activated_at` tetap mencatat waktu aktivasi yang sebenarnya (jujur, bukan tanggal retroaktif yang direkayasa).

Revisi target antar-tahun (mis. menaikkan target 2027–2029 setelah 2026 terlampaui) tidak memerlukan `jadwal:buka_kembali` — cukup mengubah master `target_tahunan`; snapshot tahun tersebut baru terbentuk saat aktivasi jadwal tahun itu dan otomatis membawa nilai revisi. Tahun yang sudah beku (snapshot sudah terbentuk dan dirujuk) tidak tersentuh oleh revisi ini.

### 12.7 Asumsi Operasional Revisi Target & PK

- Revisi PK jarang terjadi — biasanya di akhir tahun dan disetujui Eselon 1 di kementerian pusat, bukan rutinitas tahunan.
- Jalur normal untuk target tahunan yang tidak tercapai adalah **justifikasi tertulis** (argumen tertulis), bukan revisi target. Jalur ini didukung penuh oleh §20 (catatan wajib saat nilai memburuk / flag `wajib_catatan`) dan `status_capaian = belum_tercapai` (§21) — justifikasi hidup di dalam sistem dan tertelusur, bukan tersebar di korespondensi terpisah.
- Tidak ada mekanisme revisi **target tahunan PK** di tengah tahun pada Fase Awal. Revisi target tahunan efektif di antara tahun: jadwal tahun N ditutup → PK/target direvisi (dicatat via `pk:update` dan perubahan `target_tahunan`, wajib beralasan) → jadwal tahun N+1 diaktifkan dan snapshot menyalin target revisi. Ketentuan ini khusus untuk target tahunan PK — target periode (komponen) pada Rencana Aksi memiliki mekanisme revisi tersendiri di tengah tahun lewat `rencana_aksi:buka_kembali` (§14.6), karena keduanya adalah dua lapisan target yang berbeda: target tahunan PK adalah komitmen legal-formal, sedangkan target periode pada Rencana Aksi adalah rencana kerja operasional yang wajar direvisi bila kondisi lapangan berubah.
- Koreksi di luar waktu normal (mis. salah ketik target yang sudah terbekukan) memakai prosedur §12.6: koreksi manual baris snapshot yang ter-audit, bukan fitur revisi target tersendiri.
- Saat koreksi PK dilakukan, nomor/tanggal surat persetujuan Eselon 1 dicantumkan pada kolom `alasan` agar jejak audit sistem tersambung ke otorisasi eksternalnya.

---

## 13. Penanggung Jawab Indikator

- Setiap Indikator memiliki riwayat penanggung jawab (`penanggung_jawab`), bukan satu kolom tunggal — setiap baris memiliki `tanggal_mulai_berlaku`.
- Penugasan efektif pada suatu tanggal acuan dihitung sebagai baris dengan `tanggal_mulai_berlaku` terbaru yang ≤ tanggal acuan. Ini memungkinkan penelusuran "siapa penanggung jawab indikator X pada tanggal Y" secara historis akurat, termasuk untuk periode yang sudah lewat.
- Riwayat penanggung jawab tidak pernah dihapus — pergantian dicatat sebagai baris baru.
- Pergantian penanggung jawab wajib mengisi `alasan` (nullable secara skema hanya untuk penugasan pertama kali; wajib diisi pada aplikasi setiap kali terjadi pergantian atas indikator yang sudah punya penanggung jawab sebelumnya).
- Permission: `penanggung_jawab:update`.
- **PIC Rencana Aksi = PIC indikator** yang tercatat pada `penanggung_jawab` — tidak ada penugasan penanggung jawab terpisah untuk Rencana Aksi (§14.8). Bila penanggung jawab indikator berganti di tengah tahun, hak pengisian Rencana Aksi mengikuti penanggung jawab yang berlaku pada tanggal acuan, sementara `rencana_aksi.penanggung_jawab_id` mencatat PIC yang berlaku saat rencana aksi disusun sebagai jejak historis.

---

## 14. Rencana Aksi

### 14.1 Posisi dalam Siklus

Rencana Aksi adalah tahap baru yang disisipkan antara aktivasi Jadwal Tahunan dan pengajuan Pengukuran periode pertama. Alur lengkapnya:

Renstra → Perjanjian Kinerja (PK) → Jadwal Tahunan + daftar periode (`jadwal_periode`) → **penyusunan & pengesahan Rencana Aksi per indikator** → penyusunan Kegiatan per periode + klaim kegiatan → Pengukuran per periode (komponen + berkas) → verifikasi/pengesahan → Rekomendasi Pimpinan → Status Capaian → dashboard/laporan.

Rencana Aksi disusun **setelah** daftar periode pada jadwal tahunan tersusun (target triwulan membutuhkan daftar periodenya lebih dulu), dan **sebelum** jendela pengisian periode pertama dibuka. Rencana Aksi disusun **sekali per (indikator × tahun)**, bukan per periode — target seluruh periode dalam tahun itu disusun sekaligus di dalam satu baris Rencana Aksi.

**Gerbang keras:** pengajuan Pengukuran untuk periode P pada indikator I **ditolak** selama `rencana_aksi` untuk (I × tahun) belum berstatus `disahkan` (lihat §19.4), kecuali pada jadwal retroaktif (§12.6).

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
- `nilai` (numeric, **nullable**) — `0` adalah nilai sah, `null` berarti belum diisi.
- `keterangan` (text, nullable).
- `updated_by`, `updated_at`.

Constraint: **`unique(rencana_aksi_id, periode_id, komponen_id)`**.

**Target diinput pada level komponen, bukan skor final.** PIC mengisi angka komponen (mis. `n` = jumlah responden puas, `t` = total layanan) per periode; sistem menampilkan **perkiraan skor** hasil hitungan komponen itu memakai mesin perhitungan yang sama dengan §17. Perkiraan skor ini adalah nilai turunan tampilan — tidak disimpan sebagai input tersendiri.

### 14.4 Sifat Kumulatif Target

**Target triwulan bersifat kumulatif**: nilai periode berjalan mencakup periode sebelumnya (mis. target Triwulan II = capaian Januari–Juni, bukan April–Juni saja). Sistem memberi **peringatan (bukan blokir)** bila nilai komponen suatu periode lebih kecil daripada nilai periode sebelumnya pada komponen yang sama.

### 14.5 Rekonsiliasi dengan Target PK Tahunan

Total target komponen pada periode terakhir memang seharusnya setara dengan target PK tahunan indikator tersebut. Bila tidak setara, sistem menampilkan **peringatan dan mewajibkan alasan** pada saat pengajuan Rencana Aksi (bukan memblokir pengajuan), karena:

- target tahunan PK tetap hanya dapat diubah lewat revisi PK resmi (§12.7) — Rencana Aksi tidak berwenang mengubahnya; dan
- deviasi antara rencana kerja dan target resmi wajib terlihat di layar dan tercatat di audit, bukan disesuaikan diam-diam.

### 14.6 Alur Status Rencana Aksi

Status alur Rencana Aksi mengikuti pola yang sama dengan Pengukuran:

```
Draft ──ajukan──► Diajukan ──verifikasi──► Diverifikasi ──sahkan──► Disahkan
  ▲                    │                        │
  │                    │      kembalikan         │
  └────────────────────┴────────────────────────┘
```

`Dikembalikan` (alasan wajib) berfungsi sebagai jalur revisi dari `Diajukan`/`Diverifikasi`. Untuk koreksi setelah pengesahan, tersedia permission khusus **`rencana_aksi:buka_kembali`** (Perencanaan/Superadmin; alasan wajib; hanya tersedia sebelum `jadwal_tahunan.penutupan`) untuk transisi `Disahkan → Dikembalikan` — pola yang identik dengan `pengukuran:buka_kembali` (§19.5).

### 14.7 Gerbang Kelengkapan saat Pengajuan

Pengajuan Rencana Aksi (`Draft → Diajukan`) **ditolak** bila ada komponen aktif milik indikator tersebut yang belum memiliki target pada salah satu periode yang diharapkan menurut `jadwal_periode` tahun itu — ini adalah pemeriksaan kelengkapan, bukan sekadar catatan peringatan.

### 14.8 Penanggung Jawab Rencana Aksi

PIC Rencana Aksi adalah PIC indikator yang ditunjuk Perencanaan lewat `penanggung_jawab` (§13) — tidak ada penugasan terpisah untuk Rencana Aksi. Bila PIC indikator berganti di tengah tahun, `rencana_aksi.penanggung_jawab_id` tetap mencatat PIC yang berlaku saat penyusunan (jejak historis), sementara hak pengisian aktualnya mengikuti `penanggung_jawab` yang berlaku pada tanggal acuan.

### 14.9 Gerbang Rencana Aksi terhadap Pengukuran

Selama Rencana Aksi (indikator × tahun) belum berstatus `disahkan`, seluruh permintaan pengajuan Pengukuran pada indikator dan tahun tersebut ditolak sistem (§19.4). Percobaan yang ditolak karena gerbang ini tetap tercatat di audit log (§25.1).

### 14.10 Lampiran Dokumen Perjanjian Kinerja dan Gerbang Aktivasi Jadwal

Halaman Perjanjian Kinerja menampilkan lampiran dokumen PK (`berkas` dengan `berkasable_type = renstra_pk`, §18.4) beserta `nomor_pk`/`tanggal_pk` — salinan naskah PK yang ditandatangani, mode file/tautan/teks. **Aktivasi Jadwal Tahunan mensyaratkan minimal satu lampiran dokumen pada `renstra_pk` tahun tersebut** sebagai gerbang keempat (§12.4) — mode bebas sehingga persyaratan ini tidak dapat memacetkan alur: bila unggahan file dimatikan pada setelan aplikasi (§18.9) sementara belum ada lampiran mode lain, gerbang ini ditandai `tidak_dapat_dipenuhi` (§18.7) dan tidak menghalangi aktivasi.

**Lampiran `renstra_pk` tidak dapat dihapus setelah Jadwal Tahunan tahun itu berstatus `aktif`** (§18.8) — sebelum jadwal tahun tersebut aktif, Perencanaan/pengunggah dapat menghapus lampiran; penghapusan tercatat di `audit_log`.

---

## 15. Kegiatan

### 15.1 Definisi & Kepemilikan

Kegiatan disusun per **periode** (mis. Triwulan I) dan terikat tahun; kegiatan dimiliki oleh satu `unit` (unit pemilik indikator/unit pelaksana), dibuat oleh PIC unit itu (lewat grant per unit) atau Perencanaan (global, lewat isi peran). Kegiatan **tidak** dimiliki oleh satu Rencana Aksi/indikator — satu kegiatan dapat diklaim oleh lebih dari satu Rencana Aksi (dan lebih dari satu indikator) lewat tabel klaim (§16).

Tabel `kegiatan` menyimpan:

- `id`, `unit_id` (FK → unit, not null), `tahun` (int, not null), `periode_id` (FK → periode; periode **rencana** pelaksanaan, not null).
- `nama` (string), `tujuan` (text), `sasaran_peserta` (int, nullable — target jumlah peserta), `keterangan_peserta` (varchar, nullable — mis. "tim penyusun SPMI dari 30 PTS"), `lokasi` (varchar, nullable).
- `tanggal_rencana` (date, nullable), `tanggal_realisasi` (date, nullable), `anggaran` (numeric, nullable).
- `status` — enum(`rencana`, `terlaksana`, `tidak_terlaksana`, `ditunda`, `batal`), default `rencana`.
- `realisasi_peserta` (int, nullable).
- `justifikasi` (text, nullable — **wajib** bila status `tidak_terlaksana`, `ditunda`, atau `batal`).
- `kegiatan_asal_id` (uuid, nullable, FK → kegiatan.id) — menandai kegiatan lanjutan hasil geser periode.
- `uraian_pelaksanaan` (text, nullable), `kendala` (text, nullable), `strategi_tindaklanjut` (text, nullable).
- `created_by`, `created_at`, `updated_at`.

### 15.2 Status & Siklus Hidup Kegiatan

**Kegiatan yang tidak terlaksana tidak dihapus** — statusnya diubah menjadi `tidak_terlaksana` (atau `ditunda`/`batal`) **dengan justifikasi wajib**. Bila kegiatan digeser ke periode berikutnya, dibuat **baris kegiatan baru** pada periode tujuan dengan `kegiatan_asal_id` menunjuk kegiatan asal. Konsekuensi yang disadari: kegiatan yang sama dapat tampil di dua periode (periode asal dan periode tujuan) — keduanya sah, dibedakan oleh status dan tautan `kegiatan_asal_id`.

### 15.3 Narasi Kegiatan & Rekapitulasi Otomatis

Narasi `uraian_pelaksanaan`, `kendala`, `strategi_tindaklanjut` diisi per kegiatan. Kolom "Progress Kegiatan / Kendala dan Masalah / Strategi Tindak Lanjut" pada rekapitulasi per indikator × periode (§22.4) **dihasilkan otomatis** dari daftar kegiatan yang diklaim pada Rencana Aksi indikator itu (§16), bukan diketik ulang sebagai satu blok narasi panjang.

### 15.4 Pembaruan & Batas Waktu

Kegiatan dapat diperbarui (status, realisasi, narasi) oleh PIC unitnya atau Perencanaan selama `jadwal_tahunan` belum `penutupan`; setelah penutupan hanya lewat `jadwal:buka_kembali`.

### 15.5 Kegiatan Gagal Tetap Dapat Diklaim

Kegiatan berstatus `batal`/`tidak_terlaksana` **tetap boleh diklaim** — kegiatan yang diklaim tapi gagal adalah bukti mengapa komponen tidak bergerak sesuai rencana. Statusnya ikut ditampilkan di rekapitulasi.

### 15.6 Gerbang Kelengkapan Bukti Dukung sebelum Terlaksana

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

### 16.3 Klaim Tidak Mengubah Nilai Komponen Otomatis

**Klaim tidak mengubah nilai komponen secara otomatis.** Nilai komponen tetap diisi manual oleh PIC — menjumlahkan kegiatan secara otomatis berisiko penghitungan ganda (mis. satu PTS yang hadir di tiga kegiatan tetap harus dihitung satu kali pada komponen "jumlah PTS yang menerima fasilitasi"). Fungsi klaim: (a) dokumentasi dukungan dan arah dampak, (b) bahan rekapitulasi otomatis (§15.3, §22.4), (c) pengingat di layar pengisian — layar pengukuran menampilkan daftar kegiatan terkait periode itu sebagai pembanding saat PIC mengisi angka komponen.

### 16.4 Validasi Lintas Unit

`kegiatan.unit_id` harus berada pada unit yang sama dengan unit Rencana Aksi/indikator; klaim lintas unit ditolak sistem.

### 16.5 Penghapusan Klaim

Klaim dapat dihapus oleh PIC pembuat klaim atau Perencanaan selama induknya (Rencana Aksi) belum `disahkan`; penghapusan tercatat di `audit_log`.

---

## 17. Komponen Indikator & Mesin Perhitungan

### 17.1 Filosofi: Perhitungan Data-Driven

Definisi perhitungan indikator bersifat **data-driven**, bukan hardcode per indikator. Cara hitung suatu indikator ditentukan oleh kombinasi `indikator.tipe_perhitungan` dan baris-baris `indikator_komponen` miliknya, yang dapat dikelola lewat aplikasi — termasuk saat Kepmen IKU dari kementerian berubah — tanpa penyesuaian kode. **Tipe perhitungan dan komponen adalah data, bukan aturan yang ditanam di kode**: menambah, mengubah, atau mengganti komponen untuk tahun berikutnya cukup lewat permission `komponen:create`/`read`/`update`/`delete` (§17.8) yang dipegang Perencanaan/Superadmin — tidak pernah memerlukan rilis kode aplikasi. Pemetaan contoh pengisian untuk delapan indikator tahun 2026 didokumentasikan sebagai lampiran (§17.11) berstatus **contoh pengisian awal**, bukan aturan kode.

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

- Pembagian nol (penyebut bernilai 0) → nilai **tidak dapat dihitung** (disimpan `null`, ditampilkan sebagai "tidak dapat dihitung"), bukan `0` dan bukan galat sistem.
- Pembulatan: nilai disimpan sesuai `indikator.presisi` dan ditampilkan sesuai `indikator.desimal_tampilan` (kolom lama tetap berlaku, §11).

### 17.5 Tabel `pengukuran_komponen`

- `id`, `pengukuran_id` (FK), `komponen_id` (FK), `nilai` (numeric, nullable), `updated_by`, `updated_at`.

Constraint: **`unique(pengukuran_id, komponen_id)`**.

### 17.6 Nilai Turunan pada Pengukuran

`pengukuran.nilai` menjadi **nilai turunan** bila `indikator.tipe_perhitungan` ≠ `manual`: dihitung sistem dari `pengukuran_komponen`, tidak dapat diketik PIC (read-only di UI). Kolom baru `sumber_nilai` — enum(`komponen`, `manual`) — mencatat asalnya untuk audit. Untuk `manual`, perilaku lama berlaku (`nilai` diketik, `0` sah, `null` = belum diisi).

### 17.7 Gerbang Kelengkapan Komponen

Pengajuan Pengukuran **ditolak** bila ada komponen aktif yang nilainya (`pengukuran_komponen.nilai`) masih `null` (kelengkapan komponen), di samping aturan catatan wajib berbasis `arah` dan `wajib_catatan` (§20) — lihat gerbang lengkap di §19.4.

### 17.8 Pengelolaan Definisi Komponen

**Pengelolaan definisi komponen menggunakan permission `komponen:create`/`update`/`delete`** (Perencanaan dan Superadmin; `komponen:read` juga dimiliki Admin, Pimpinan, dan Pegawai agar label komponen tetap tampil di halaman yang berhak mereka buka). Definisi komponen dapat diubah/ditambah lewat aplikasi — mis. saat Kepmen IKU dari kementerian berubah — **tanpa** penyesuaian hardcode kode aplikasi.

### 17.9 Pembekuan Cara Hitung di Snapshot

`jadwal_snapshot` ikut membekukan cara hitung: kolom baru `tipe_perhitungan`, `baseline` (numeric, nullable), `satuan`, plus tabel anak baru **`jadwal_snapshot_komponen`** (`jadwal_snapshot_id`, `kode`, `label`, `peran`, `bobot`, `urutan`) — lihat detail pembentukannya di §12.5. Baris snapshot tetap idempoten dan imutabel setelah dirujuk pengukuran, sehingga perubahan definisi komponen di tengah tahun tidak mengubah makna data historis.

### 17.10 Batas Mesin Perhitungan: Satu Tingkat

**Batas sadar Fase Awal:** mesin perhitungan mendukung **satu tingkat** perhitungan (rasio atau penjumlahan, dengan pembilang berjumlah banyak dan berbobot). Formula **bertingkat** (sub-skor → nilai komposit → digabung lagi) **tidak** dibangun; indikator semacam IKU "Predikat SAKIP dan Zona Integritas" memakai `tipe_perhitungan = manual` (Nilai SAKIP dan Nilai ZI diketik langsung sebagai angka, tanpa diturunkan ke sub-skor komponen penyusunnya). Bila kementerian menuntut formula bertingkat di kemudian hari, itu adalah perubahan yang membutuhkan pengembangan lanjutan — bukan sesuatu yang dapat dikonfigurasi dari layar pada Fase Awal. Batas ini yang membuat lampiran pemetaan pada §17.11 mencatat IKU 3 dengan `tipe_perhitungan = manual` alih-alih menjabarkan sub-skornya sebagai komponen.

### 17.11 Lampiran: Pemetaan Indikator 2026 ke Definisi Komponen

**Status lampiran ini adalah contoh pengisian awal, bukan aturan yang ditanam di kode.** Tabel berikut bersumber dari file kerja pengukuran kinerja institusi tahun 2026, disediakan sebagai (a) acuan pengisian awal `indikator_komponen` oleh Perencanaan saat data induk mulai dimasukkan ke aplikasi, dan (b) fixture test bagi mesin perhitungan (§17.11.3) — **bukan** logika yang dihardcode ke kode aplikasi. Tipe perhitungan dan komponen setiap indikator tetap sepenuhnya data-driven (§17.1): baris pada tabel ini dapat diubah, ditambah, atau diganti oleh Perencanaan lewat `komponen:create`/`update`/`delete` kapan pun tanpa rilis kode, termasuk untuk tahun-tahun setelah 2026.

#### 17.11.1 Tabel Pemetaan

| IKU | Tipe | Komponen (kode — label — peran — bobot) | Formula | Contoh angka 2026 |
|---|---|---|---|---|
| 1. Keunggulan Layanan | `rasio_persen` | `n` — jumlah respon pengguna layanan yang puas — pembilang — 1; `t` — total respon pengguna layanan — penyebut — 1 | `n / t × 100` | n=254, t=255 → 99,60784314 |
| 2. Arsitektur PTS | `rasio_persen` | `a` — jumlah PTS terakreditasi — pembilang — 1; `b` — jumlah PTS yang melakukan penyatuan/penggabungan — pembilang — 1; `t` — total PTS wilayah kerja — penyebut — 1 | `(a + b) / t × 100` | a=79, b=0, t=84 → 94,04761905 |
| 3. Predikat SAKIP dan Zona Integrasi (WBK/WBBM) | `penjumlahan` | `sakip` — Nilai SAKIP — penjumlah — 0,5; `zi` — Nilai ZI WBK/WBBM — penjumlah — 0,5 | `(sakip × 0,5) + (zi × 0,5)` | sakip=77,5; zi=75 → 76,25 |
| 4. Fasilitasi Peningkatan Mutu PTS oleh LLDIKTI | `rasio_persen` | `n` — jumlah PTS yang menerima fasilitasi peningkatan mutu — pembilang — 1; `t` — total PTS wilayah kerja — penyebut — 1 | `n / t × 100` | n=50, t=84 → 59,52380952 |
| 5. Pencegahan dan Penanganan Anti Kekerasan, Anti Narkoba, dan Anti Korupsi | `rasio_persen` | `n` — jumlah PTS yang memiliki kebijakan anti kekerasan/narkoba/korupsi — pembilang — 1; `t` — total PTS wilayah kerja — penyebut — 1 | `n / t × 100` | n=78, t=84 → 92,85714286 |
| 6. Fasilitasi Pengembangan Kemahasiswaan dan Prestasi oleh LLDIKTI | `rasio_persen` | `n` — jumlah PTS yang menerima fasilitasi peningkatan mutu layanan kemahasiswaan — pembilang — 1; `t` — total PTS wilayah kerja — penyebut — 1 | `n / t × 100` | n=68, t=84 → 80,95238095 |
| 7. Jumlah Dosen PTS yang Meningkat Jabatan Fungsionalnya | `penjumlahan` | `lektor` — jumlah dosen naik ke Lektor — penjumlah — 1; `lektor_kepala` — Lektor Kepala — penjumlah — 1; `guru_besar` — Guru Besar — penjumlah — 1 | `lektor + lektor_kepala + guru_besar` | 99 + 24 + 1 = 124 |
| 8. Fasilitasi Peningkatan Kinerja Penelitian, Publikasi, dan Kemitraan PTS | `rasio_persen` | `n` — jumlah PTS yang memperoleh fasilitasi — pembilang — 1; `t` — penyebut (perlu penegasan definisi) — penyebut — 1 | `n / t × 100` | n=50, t=84 → 59,52380952 |

#### 17.11.2 Catatan Penting

- **IKU 3:** sub-skor di dalam Nilai SAKIP (Perencanaan Kinerja 30, Pengukuran Kinerja 30, Pelaporan Kinerja 15, Evaluasi Internal 25) **tidak** dimodelkan sebagai komponen — konsekuensi langsung dari batas satu tingkat perhitungan (§17.10); Nilai SAKIP diinput sebagai satu angka. Bila kelak ingin dirinci, mesin yang sama sudah menampungkannya lewat komponen berbobot tanpa perubahan struktur data.
- **IKU 7:** komponen Tenaga Pengajar dan Asisten Ahli hanya berfungsi sebagai baseline dan **tidak** masuk penjumlahan target.
- **IKU 8:** label penyebut pada sumber kerja institusi berbunyi "total publikasi", tetapi angka yang dipakai adalah 84 — sama dengan total PTS wilayah kerja. Definisi penyebut ini **wajib ditegaskan Tim Perencanaan** sebelum dipakai produksi; sampai ada penegasan, definisi komponen dicatat sebagai "perlu penegasan" pada `indikator_komponen.label`.
- **Varian berbobot multi-pembilang** (`Σ(nᵢ × kᵢ) / t × 100`, mis. bobot 1 / 0,75 / 0,5 / 0,25 per jenis capaian) tertampung oleh `rasio_persen` dengan bobot komponen (§17.3) — dicatat sebagai kemampuan mesin yang tersedia tanpa pengembangan lanjutan, bukan indikator aktif pada lampiran ini.

#### 17.11.3 Pemakaian sebagai Fixture Test

Lampiran ini menjadi acuan fixture test mesin perhitungan: setiap baris pada §17.11.1 diuji dengan angka contoh 2026 dan hasilnya harus sama persis dengan hitungan manual, mencakup penanganan pembagian nol (§17.4) dan pembulatan sesuai `presisi`/`desimal_tampilan` (§10). Status lampiran tetap **contoh pengisian awal** — perubahan definisi komponen di kemudian hari (mis. penegasan definisi IKU 8) tidak memerlukan perubahan pada bab ini maupun pada kode mesin perhitungan, hanya pada baris `indikator_komponen` yang bersangkutan.

---

## 18. Bukti Dukung (Berkas Persyaratan)

### 18.1 Prinsip: Persyaratan Ditentukan Perencanaan, Cara Pemenuhan Dipilih PIC

Seluruh persyaratan bukti dukung ditetapkan **Tim Perencanaan** lewat permission `jenis_berkas:create`/`update`/`delete` (§7.2); PIC tidak dapat mengubah persyaratan, hanya memenuhinya. Persyaratan bersifat **fleksibel per kebutuhan**: Perencanaan menentukan (a) pada tahap apa persyaratan itu wajib, (b) **mode bukti yang diizinkan**, dan (c) apakah wajib atau opsional — tidak ada daftar mode yang di-hardcode per jenis persyaratan.

Sistem mengenal tiga mode bukti dukung: **`file`** (unggahan langsung), **`tautan`** (URL/tautan ke dokumen yang disimpan di tempat lain), dan **`teks`** (keterangan tertulis). Satu persyaratan dapat mengizinkan lebih dari satu mode; PIC memilih di antara yang diizinkan saat memenuhinya. Tujuannya memberi keleluasaan operasional sekaligus menjaga kapasitas penyimpanan — Perencanaan (dan pemegang kebijakan unggahan, §18.9) dapat menetapkan bahwa persyaratan tertentu cukup berupa tautan atau teks, dan hanya persyaratan yang memang perlu arsip fisik yang mewajibkan unggahan file.

**Pembagian kewenangan atas bukti dukung:** substansi persyaratan (nama, tahap, wajib/opsional, mode yang diizinkan, `semua_mode_wajib`, per indikator) milik **Perencanaan**; kebijakan teknis unggahan (saklar unggahan, format default, ukuran maksimum default — §18.9) milik pemegang `pengaturan:update`, yaitu **Admin dan Superadmin**. Dengan begitu Admin dapat mengakomodasi perubahan kebijakan jenis berkas yang boleh diunggah tanpa memperoleh wewenang substantif atas persyaratan kinerja; Admin juga memegang `jenis_berkas:read` untuk melihat daftar persyaratan saat menilai kebutuhan penyimpanan.

### 18.2 Tabel `jenis_berkas`

Persyaratan bukti dukung didefinisikan **Tim Perencanaan** lewat tabel `jenis_berkas`:

- `id`, `nama` (string).
- `tahap` — enum(`rencana_aksi`, `pengukuran`, **`kegiatan`**), not null. Nilai `kegiatan` menampung persyaratan pertanggungjawaban pelaksanaan kegiatan (SPJ).
- `indikator_id` (FK → indikator, **nullable** — `null` berarti berlaku untuk semua indikator).
- `wajib` (boolean, default false), `keterangan` (text, nullable).
- `izinkan_file` (boolean, not null, default `true`) — mode unggahan file diizinkan.
- `izinkan_tautan` (boolean, not null, default `false`) — mode tautan (URL) diizinkan.
- `izinkan_teks` (boolean, not null, default `false`) — mode keterangan teks diizinkan.
- `semua_mode_wajib` (boolean, not null, default `false`) — `false` berarti cukup **minimal satu** mode yang diizinkan terisi; `true` berarti **seluruh** mode yang diizinkan harus terisi.
- `urutan` (int, not null, default 0) — urutan tampil pada daftar persyaratan.
- `format_diizinkan` (varchar, nullable — mis. `pdf,docx,xlsx,jpg,png`) — hanya bermakna bila `izinkan_file = true`; dapat diubah lewat form persyaratan (Perencanaan) **atau** lewat halaman "Batas unggahan berkas" (§26.4, kewenangan Admin/Superadmin) — keduanya menulis ke kolom yang sama.
- `ukuran_maks_kb` (int, nullable; bila kosong memakai nilai default dari `pengaturan`) — hanya bermakna bila `izinkan_file = true`; idem — dapat diubah lewat form persyaratan **atau** halaman "Batas unggahan berkas" (§26.4).
- `aktif` (boolean, default true), `created_by`, `created_at`, `updated_at`.

**Validasi saat penyimpanan (Perencanaan):**

- Minimal satu dari `izinkan_file`/`izinkan_tautan`/`izinkan_teks` bernilai `true` — penyimpanan ditolak bila ketiganya `false`.
- `format_diizinkan` dan `ukuran_maks_kb` tetap tersimpan apa adanya tetapi tidak dipakai bila `izinkan_file = false`.
- Bila `wajib = true` dan hanya mode `file` yang diizinkan sementara unggahan file sedang dinonaktifkan pada setelan aplikasi (§18.9), sistem menampilkan **peringatan** saat penyimpanan: persyaratan tersebut berpotensi tidak dapat dipenuhi (lihat §18.7).

**Pembagian kewenangan atas dua kolom ini** ditegaskan di §26.4: substansi persyaratan (seluruh kolom lain di atas) tetap milik Perencanaan; hanya `format_diizinkan` dan `ukuran_maks_kb` yang dapat pula diubah lewat jalur kebijakan teknis unggahan yang dipegang Admin/Superadmin.

### 18.3 Tahap Persyaratan

**`tahap` adalah pilihan Perencanaan saat menetapkan persyaratan**: persyaratan bertahap `rencana_aksi` wajib dipenuhi saat penyusunan/pengajuan Rencana Aksi; persyaratan bertahap `pengukuran` wajib dipenuhi saat pengisian/pengajuan Pengukuran; persyaratan bertahap `kegiatan` wajib dipenuhi sebelum status Kegiatan berpindah ke `terlaksana` (§15.6). Satu indikator dapat memiliki persyaratan pada ketiga tahap sekaligus.

### 18.4 Mode Bukti pada Tabel `berkas`

Tabel `berkas` menjadi wadah bukti dukung untuk **enam jenis induk**: `rencana_aksi`, `pengukuran`, `kegiatan`, **`renstra`**, **`renstra_pk`**, dan **`regulasi`** — dengan struktur:

- `id`, `jenis_berkas_id` (FK, **nullable** — `null` = lampiran bebas di luar daftar persyaratan; persyaratan `jenis_berkas` hanya berlaku untuk tiga induk pertama — `renstra`/`renstra_pk`/`regulasi` selalu berupa lampiran bebas, §9.4, §10.6, §14.10).
- `berkasable_type` (`rencana_aksi` / `pengukuran` / `kegiatan` / `renstra` / `renstra_pk` / `regulasi`), `berkasable_id` (uuid).
- `mode` — enum(`file`, `tautan`, `teks`), not null — cara bukti dikirim. Mode dan seluruh aturan mode (§18.5) berlaku sama untuk keenam induk.
- `tautan` (varchar 2048, nullable) — **wajib diisi** bila `mode = tautan`; divalidasi berskema `http`/`https`.
- `isi_teks` (text, nullable) — **wajib diisi** bila `mode = teks`.
- `nama_asli`, `path`, `mime`, `ukuran_bytes` — **nullable**; wajib hanya bila `mode = file`, bernilai `NULL` pada mode lain.
- `uploaded_by`, `created_at`.
- `dihapus_pada` (timestamp, nullable), `dihapus_oleh` (FK → users, nullable).

Satu persyaratan dapat dipenuhi oleh **lebih dari satu baris** `berkas` (mis. laporan sebagai file dan dokumentasi sebagai tautan), terutama bila `semua_mode_wajib = true`.

### 18.5 Validasi Mode & Kombinasi Pemenuhan

Mode yang dipilih PIC saat mengirim bukti **harus** termasuk mode yang diizinkan pada `jenis_berkas` terkait (`izinkan_file`/`izinkan_tautan`/`izinkan_teks`); permintaan dengan mode di luar daftar ditolak sistem. Lampiran bebas (tanpa `jenis_berkas_id`) boleh memakai mode apa pun.

Kelengkapan suatu persyaratan mengikuti `semua_mode_wajib` (§18.2): bila `false`, persyaratan dianggap terpenuhi begitu **minimal satu** mode yang diizinkan telah terisi (file terunggah, tautan tervalidasi, atau teks terisi); bila `true`, **seluruh** mode yang diizinkan pada persyaratan itu harus terisi. Aturan ini berlaku seragam pada seluruh gerbang kelengkapan (§18.6).

### 18.6 Gerbang Kelengkapan

- **Rencana Aksi & Pengukuran** (tidak berubah dari pola lama): transisi `draft → diajukan` **ditolak** bila ada `jenis_berkas` aktif bertanda `wajib` pada tahap tersebut yang belum terpenuhi untuk induknya — dengan pengertian "terpenuhi" kini mengikuti mode (§18.5), bukan semata unggahan file.
- **Kegiatan**: transisi status `rencana → terlaksana` **ditolak** bila ada `jenis_berkas` aktif bertanda `wajib` bertahap `kegiatan` yang belum terpenuhi untuk kegiatan tersebut — lihat detail dan pengecualian justifikasi di §15.6.
- Ketiga gerbang di atas menghormati penanda `tidak_dapat_dipenuhi` (§18.7): persyaratan yang ditandai demikian tidak memblokir gerbang mana pun.
- Percobaan pengajuan/transisi yang ditolak salah satu gerbang ini tercatat di audit log (§25.1).

### 18.7 Aturan Anti-Macet: Penanda `tidak_dapat_dipenuhi`

**Ketersediaan mode tidak boleh memacetkan alur.** Bila unggahan file dinonaktifkan pada setelan aplikasi (`berkas.unggahan_aktif = false`, §18.9) sementara suatu persyaratan hanya mengizinkan mode `file` (`izinkan_tautan = false` dan `izinkan_teks = false`), persyaratan itu ditandai **`tidak_dapat_dipenuhi`**:

- Pemenuhannya **tidak** memblokir gerbang kelengkapan (§18.6) — kegiatan/rencana aksi/pengukuran tetap dapat diajukan/dilanjutkan meski persyaratan ini belum terpenuhi.
- Penanda ini tercatat di `audit_log`.
- Penanda ini tampil pada rekapitulasi (§22.4) serta halaman kerja Perencanaan, sehingga tetap terlihat dan dapat diperbaiki — Perencanaan dapat menambahkan mode `tautan`/`teks` pada persyaratan tersebut, atau pemegang `pengaturan:update` mengaktifkan kembali unggahan file.

### 18.8 Imutabilitas & Penghapusan

**Imutabilitas mengikuti status induk, ditegakkan seragam untuk seluruh mode (`file`/`tautan`/`teks`) pada keenam jenis induk:**

- lampiran **`rencana_aksi`**/**`pengukuran`** tidak dapat dihapus setelah induknya berstatus `disahkan`;
- lampiran **`kegiatan`** tidak dapat dihapus setelah kegiatan berstatus `terlaksana` (§15.6);
- lampiran **`renstra`** tidak dapat dihapus setelah Renstra berstatus `aktif` (§10.6);
- lampiran **`renstra_pk`** tidak dapat dihapus setelah Jadwal Tahunan tahun itu berstatus `aktif` (§14.10);
- lampiran **`regulasi`** tidak dapat dihapus selama regulasi masih dirujuk oleh Renstra atau indikator aktif (§9.5).

Sebelum batas masing-masing tercapai, Perencanaan (dan pengunggah) dapat menghapus lampiran; setiap penghapusan tercatat di `audit_log` (soft delete lewat `dihapus_pada`). Setelah `penutupan` jadwal, koreksi atas lampiran `rencana_aksi`/`pengukuran`/`kegiatan` hanya lewat `jadwal:buka_kembali`.

### 18.9 Kebijakan Storage pada Setelan Aplikasi

Kunci baru pada tabel `pengaturan`, grup **`berkas`**:

- `berkas.unggahan_aktif` (boolean, default `true`) — saklar utama mode unggahan file di seluruh aplikasi.
- `berkas.ukuran_maks_kb` (integer, default `10240`) — batas ukuran default bila `jenis_berkas.ukuran_maks_kb` kosong.
- `berkas.format_diizinkan` (teks, default `pdf,docx,xlsx,jpg,jpeg,png`) — daftar format default bila `jenis_berkas.format_diizinkan` kosong.
- `berkas.tautan_selalu_diizinkan` (boolean, default `true`) — menandai bahwa mode tautan dan teks selalu tersedia sebagai jalur alternatif tanpa memakai kapasitas penyimpanan.

Nilai pada `jenis_berkas` **menimpa** default setelan ini; setelan hanya berlaku sebagai fallback dan sebagai saklar kebijakan tingkat aplikasi. Perubahan kunci-kunci ini tercatat di `audit_log` (nilai lama/baru) seperti kunci setelan lain, dan hanya dapat diubah pemegang `pengaturan:update` (§26.4).

Halaman Setelan Aplikasi menampilkan panel **read-only "Penggunaan penyimpanan bukti dukung"**: jumlah berkas mode file, total `ukuran_bytes`, dan jumlah bukti mode tautan/teks — supaya keputusan "mana yang perlu diunggah, mana yang cukup tautan" diambil berdasarkan angka, bukan perkiraan (§26.3).

**Batas tegas:** kunci di grup `berkas` adalah **preferensi operasional**, bukan aturan bisnis. Daftar mode yang diizinkan per persyaratan tetap milik `jenis_berkas` (Perencanaan), bukan milik setelan. Penyimpanan file fisik tetap di disk VPS (`storage/app/berkas/...`), diakses lewat route ber-permission (streamed download) — **bukan** URL publik.

### 18.10 Lampiran Bebas pada Kegiatan

Lampiran di level `kegiatan` tetap tersedia sebagai **lampiran bebas** (bukan persyaratan bergerbang, tanpa `jenis_berkas_id`) — bukti pelaksanaan kegiatan (daftar hadir, dokumentasi) menempel pada kegiatannya dalam mode apa pun, dan ikut tampil di rekapitulasi indikator × periode (§22.4).

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

### 19.2 Approval Pimpinan (Fase Lanjutan — Belum Dibangun)

Permission `pengukuran:setujui` dan isi peran Pimpinan sudah didefinisikan di katalog (§7.2–7.3) untuk mengakomodasi Fase Lanjutan, tapi UI dan alur approval-nya belum dibangun. Pimpinan pada Fase Awal hanya memegang akses baca (dashboard, laporan, ekspor, audit, Rencana Aksi, Kegiatan, Berkas, Rekomendasi Pimpinan) dan tidak berperan dalam mengesahkan atau menyetujui Rencana Aksi maupun Pengukuran apa pun.

### 19.3 Aturan Perubahan

- `pengukuran:create`/`pengukuran:update` untuk PIC (Pegawai) diberikan lewat grant (`user_permission_granted`) yang wajib di-scope ke `unit_id` sesuai dengan `indikator.unit_id` terkait, dan tunduk pada batas jendela pengisian periode (§12.3). Untuk Perencanaan, permission ini bersumber dari isi peran — bersifat global dan hanya tunduk pada `penutupan` jadwal tahunan (§12.2).
- Transisi `diajukan → diverifikasi` dan `diverifikasi → disahkan` tunduk pada pemisahan tugas (§7.6): PIC yang mengajukan pengukurannya sendiri tidak dapat memverifikasi/mengesahkannya; Perencanaan yang mengisi atas nama unit diizinkan mengesahkan pengisiannya sendiri, dengan penanda `self_approval` tercatat di audit.
- Untuk indikator berkomponen, input dilakukan pada level `pengukuran_komponen` (§17.5); `pengukuran.nilai` ikut terisi otomatis sebagai nilai turunan (§17.6) dan bersifat read-only bagi PIC.
- Perubahan pada baris `pengukuran` yang sudah memiliki nilai menaikkan kolom `versi` (optimistic locking, lihat §27).
- Data pengukuran dengan nilai/catatan terisi tidak dapat dihapus permanen — pembatalan diwujudkan sebagai transisi status atau baris histori, bukan penghapusan baris (§4 prinsip 2).

### 19.4 Gerbang Keras Sebelum Pengajuan

Pengajuan Pengukuran (`Draft → Diajukan`) **ditolak sistem** bila salah satu dari tiga syarat berikut tidak terpenuhi:

1. `rencana_aksi` untuk (indikator, tahun) belum berstatus `disahkan` (§14.9).
2. Ada `indikator_komponen` aktif milik indikator tersebut yang nilainya (`pengukuran_komponen.nilai`) masih `null` (§17.7).
3. Ada `jenis_berkas` aktif bertanda `wajib` pada tahap `pengukuran` untuk indikator tersebut yang belum terpenuhi untuk pengukuran ini (§18.6).

Ketiga gerbang ini **dikecualikan pada jadwal retroaktif** (backfill data historis, §12.6) — tujuan jadwal retroaktif memang mengisi data historis yang lazimnya tidak melalui proses Rencana Aksi/berkas normal pada masanya. Percobaan pengajuan yang ditolak oleh salah satu gerbang ini tetap dicatat di audit log (§25.1).

### 19.5 Koreksi Setelah Pengesahan

- **Sebelum penutupan jadwal**: Perencanaan/Superadmin dapat mengembalikan pengukuran yang sudah **Disahkan** ke status **Dikembalikan** melalui permission `pengukuran:buka_kembali`, dengan **alasan wajib** diisi. Jalur ini hanya tersedia sebelum `penutupan` jadwal tahunan tercapai.
- **Setelah penutupan jadwal**: satu-satunya jalur koreksi adalah `jadwal:buka_kembali` (§12.6) — membuka jadwal (`ditutup → aktif`), melakukan koreksi yang diperlukan (termasuk membuka kembali pengukuran individual bila perlu), lalu menutup ulang jadwal (`jadwal:tutup`). Seluruh rangkaian ini tercatat penuh di audit log.

---

## 20. Nilai & Catatan

- `nilai` bertipe numerik, **nullable**: `null` berarti "belum diisi", sedangkan `0` adalah nilai sah (capaian nol adalah data valid, bukan representasi kekosongan). Untuk indikator dengan `tipe_perhitungan` selain `manual`, `nilai` bersifat read-only di UI karena diturunkan dari komponen (§17.6); PIC mengisi angka pada level komponen, bukan pada `nilai` langsung.
- `catatan` bertipe teks, nullable secara default, namun wajib diisi saat pengajuan (`pengukuran:create`/`update` menuju status Diajukan) jika salah satu kondisi berikut terpenuhi:
  1. Nilai pada periode ini **memburuk menurut `indikator.arah`** (§11) dibandingkan **pengukuran berstatus Disahkan terakhir secara kronologis** untuk indikator yang sama — nilai yang stagnan (sama persis) **tidak** memicu kewajiban ini. Pengukuran pertama suatu indikator, yang belum memiliki pembanding Disahkan sebelumnya, tidak wajib mengisi catatan atas dasar aturan ini. Untuk indikator berkomponen, perbandingan ini dilakukan atas **nilai turunan** hasil hitungan komponen.
  2. Indikator memiliki flag `wajib_catatan = true` (§11) — mewajibkan catatan pada setiap pengajuan tanpa syarat pemburukan.
- Validasi ini ditegakkan di sisi server (Form Request, Policy, dan service layer), bukan hanya di UI.

---

## 21. Status Capaian

### 21.1 Definisi

`status_capaian` menyatakan penilaian akhir atas satu baris `pengukuran`: `tercapai` atau `belum_tercapai`, dengan kolom `sumber` bernilai `manual` atau `data_sumber`. Penetapan status capaian adalah momen terpisah dari pengesahan pengukuran (`Disahkan`) — pengukuran dapat berstatus Disahkan tanpa status capaian aktif untuk sementara waktu, sampai Perencanaan/Superadmin menetapkannya secara manual.

### 21.2 Aturan

- Maksimal 1 status capaian aktif per pengukuran pada satu waktu; penetapan status baru tidak menghapus status lama — status lama tetap sebagai riwayat (soft replace, ditandai bukan-aktif, bukan dihapus).
- `ditetapkan_oleh` bernilai `null` hanya jika sumber adalah `data_sumber` (ditetapkan otomatis oleh sistem, bukan manusia).
- Permission: `status_capaian:update`.

### 21.3 Fase Awal vs Fase Lanjutan

- **(Fase Awal)**: seluruh `status_capaian` diisi manual oleh pengguna berperan Perencanaan atau Superadmin melalui UI (`sumber = 'manual'` untuk semua baris). Tidak ada jalur otomatis apa pun yang mengisi status capaian pada Fase Awal.
- **(Fase Lanjutan — belum dibangun)**: kolom `sumber = 'data_sumber'` dan jalur integrasi otomatis dari sistem lain untuk mengisi status capaian tanpa intervensi manual. Kolom skema sudah disiapkan sejak MVP agar penambahan jalur ini tidak memerlukan migrasi besar.

---

## 22. Rekomendasi Pimpinan

### 22.1 Tabel `rekomendasi_pimpinan`

- `id`, `indikator_id` (FK), `tahun` (int), `periode_id` (FK).
- `isi` (text), `ditetapkan_oleh` (FK → users), `created_at`.

Pola "aktif": baris terbaru per (`indikator_id`, `tahun`, `periode_id`) adalah yang berlaku; baris lama tetap sebagai riwayat (soft replace, tidak dihapus) — sama seperti `status_capaian` (§21.2).

### 22.2 Pengisi pada Fase Awal

**Pengisinya pada Fase Awal adalah Perencanaan** (permission `rekomendasi:tetapkan`), bukan Pimpinan — karena alur approval/persetujuan Pimpinan masih ditunda ke Fase Lanjutan (§19.2) dan Pimpinan belum masuk ke dalam flow aplikasi. Pimpinan tetap read-only atas data ini.

### 22.3 Independensi dari Status Pengukuran

Rekomendasi Pimpinan melekat pada **indikator × periode**, terpisah dari `pengukuran` — dapat diisi setelah rapat evaluasi triwulan tanpa menunggu Pengukuran periode itu berstatus `disahkan`.

### 22.4 Rekapitulasi Indikator × Periode

**Rekapitulasi indikator × periode** (setara format kerja yang dipakai saat ini) wajib dapat ditampilkan dan diekspor, memuat: PIC, nama indikator, formula/cara hitung, baseline, target PK tahunan, target periode (Rencana Aksi), capaian periode, **nilai tiap komponen** (target dan realisasi), daftar kegiatan yang diklaim beserta statusnya, narasi `uraian_pelaksanaan`/`kendala`/`strategi_tindaklanjut` per kegiatan, Rekomendasi Pimpinan, dan penanda persyaratan bukti dukung yang berstatus `tidak_dapat_dipenuhi` (§18.7) agar Perencanaan dapat menindaklanjutinya. Seluruh nilai turunan ditampilkan bersama angka pembentuknya, sehingga rekapitulasi tetap dapat ditelusuri kembali ke angka mentahnya.

---

## 23. Dashboard & Visualisasi

- Dashboard menampilkan ringkasan capaian berbasis data yang telah **Disahkan** (§19.1), tidak mencampur data Draft/Diajukan/Diverifikasi ke dalam angka capaian resmi.
- Visualisasi dibangun dengan ApexCharts (Fase Awal): grafik batang/garis perbandingan target vs realisasi per indikator/periode, ringkasan status capaian (Tercapai/Belum Tercapai) per Sasaran/Renstra.
- Panel tambahan: progres Rencana Aksi per indikator (status alur Draft/Diajukan/Diverifikasi/Disahkan), jumlah Kegiatan per status per periode (termasuk hitungan kegiatan `batal`/`tidak_terlaksana`), dan perbandingan target periode (Rencana Aksi) dengan realisasi komponen.
- Status Capaian yang ditampilkan pada dashboard mencakup 5 kemungkinan tampilan (kombinasi status alur pengukuran dan status capaian):

| Status Tampilan | Kondisi |
|---|---|
| **Tercapai** | Pengukuran berstatus Disahkan dan `status_capaian.status = tercapai` |
| **Belum tercapai** | Pengukuran berstatus Disahkan dan `status_capaian.status = belum_tercapai` |
| **Belum ditetapkan** | Pengukuran Disahkan tetapi belum ada baris `status_capaian` aktif |
| **Belum mengisi** | Tidak ada baris `pengukuran` sama sekali untuk kombinasi indikator × periode yang diharapkan menurut `jadwal_periode` (§12.3) pada jadwal aktif. Indikator berstatus `arsip` tidak dihitung. |
| **Tidak mengisi** | Ada baris `pengukuran` namun tetap berstatus Draft melewati `pengisian_selesai` periode yang bersangkutan pada `jadwal_periode` (dianggap gagal mengisi tepat waktu) |

- Dashboard dapat difilter berdasarkan Renstra, Tahun, Periode, Sasaran, dan Unit.
- Akses dashboard: `dashboard:read` (dimiliki seluruh peran, termasuk Pegawai — sesuai §7.3).

---

## 24. Laporan & Ekspor

### 24.1 Fase Awal

- Laporan tabular (daftar pengukuran dengan filter Renstra/Tahun/Periode/Sasaran/Unit/Status) dapat diekspor ke format Excel — ini satu-satunya format ekspor pada Fase Awal.
- Rekapitulasi indikator × periode (§22.4) — satu baris per komponen, disertai narasi kegiatan dan Rekomendasi Pimpinan — dapat ditampilkan dan diekspor Excel dengan format yang setara dengan format kerja yang dipakai saat ini.
- Permission: `laporan:read`, `laporan:ekspor`.

### 24.2 Fase Lanjutan (Belum Dibangun)

- Ekspor rekap ke PDF (mis. untuk lampiran laporan formal/LKj).
- Ekspor gambar grafik dashboard (mis. PNG chart ApexCharts) untuk disisipkan ke dokumen presentasi/laporan.

---

## 25. Audit & Histori

### 25.1 Cakupan Pencatatan (Full Sejak Fase Awal)

Audit log tidak dipersempit pada Fase Awal — seluruh peristiwa berikut dicatat sejak hari pertama:

- Perubahan isi peran (`role_permissions`), penetapan/pergantian peran pengguna (`user_roles`), pemberian izin (`user_permission_granted`), dan pencabutan izin (`user_permission_denied`) — seluruhnya lewat permission `akses:update`.
- Pembuatan/perubahan/penghapusan `regulasi` (`nilai_lama`/`nilai_baru` dan `alasan` untuk `update`/`delete`), perubahan `renstra.regulasi_id`/`indikator.regulasi_id`, dan pengiriman/penghapusan lampiran dokumen dasar aturan (§9.8).
- CRUD dan perubahan status Renstra, termasuk revisi in-place akibat Kepmen IKU baru (`dasar_hukum`, `regulasi_id`, atau atribut lain, dengan `alasan` memuat rujukan Kepmen — §10.5), serta pengiriman/penghapusan lampiran dokumen Renstra (§10.6).
- CRUD Sasaran dan Indikator, termasuk perpindahan indikator antar unit, perubahan `tipe_perhitungan` indikator, perubahan `regulasi_id`, dan pengarsipan indikator akibat IKU dihapus dari Kepmen.
- Pengisian dan koreksi Perjanjian Kinerja (setiap koreksi wajib beralasan), serta pengiriman/penghapusan lampiran dokumen PK (§14.10).
- Perubahan Target Tahunan.
- Penugasan dan pergantian Penanggung Jawab (termasuk alasan pergantian).
- Pembuatan/perubahan/pengajuan/verifikasi/pengembalian/pengesahan/buka-kembali `rencana_aksi`.
- Pembuatan/perubahan/penghapusan/perubahan-status Kegiatan (termasuk penandaan geser periode, pengisian justifikasi, dan transisi ke `terlaksana` yang digerbangi kelengkapan bukti dukung tahap `kegiatan`, §15.6).
- Penambahan dan penghapusan Klaim Kegiatan.
- Penambahan/perubahan/nonaktivasi Komponen Indikator (nilai lama/baru).
- Penetapan/perubahan persyaratan `jenis_berkas` — termasuk perubahan mode bukti yang diizinkan (`izinkan_file`/`izinkan_tautan`/`izinkan_teks`) dan `semua_mode_wajib` — dengan `nilai_lama`/`nilai_baru` dan `alasan`.
- Pengiriman bukti dukung (`berkas`), mencatat `mode` dan sumbernya: untuk mode `file` — nama asli, ukuran, mime; untuk mode `tautan` — tautan tujuan; untuk mode `teks` — panjang teks (bukan salinan isinya). Penghapusan bukti dukung turut tercatat.
- Penandaan persyaratan sebagai `tidak_dapat_dipenuhi` (§18.7), termasuk pada gerbang lampiran PK saat aktivasi jadwal (§12.4, §14.10).
- Perubahan kunci setelan grup `berkas` (`berkas.unggahan_aktif`, `berkas.ukuran_maks_kb`, `berkas.format_diizinkan`, `berkas.tautan_selalu_diizinkan`) — nilai lama/baru per kunci.
- Perubahan `format_diizinkan`/`ukuran_maks_kb` lewat halaman "Batas unggahan berkas" (§26.4) — dicatat per persyaratan, kolom apa, nilai lama → nilai baru, oleh siapa.
- Penetapan Rekomendasi Pimpinan.
- Seluruh transisi status pada alur Pengukuran (ajukan, verifikasi, kembalikan beserta alasan, sahkan, buka kembali setelah pengesahan beserta alasan).
- Aktivasi, penutupan, dan buka kembali Jadwal Tahunan, termasuk pembuatan baris `jadwal_snapshot` dan `jadwal_snapshot_komponen` (dengan `actor_id` menempel pada pelaku aktivasi).
- Penetapan/penggantian Status Capaian.
- Percobaan tindakan yang ditolak sistem — termasuk gerbang Rencana Aksi belum disahkan, komponen belum lengkap, bukti dukung wajib belum terpenuhi (rencana aksi/pengukuran/kegiatan), penolakan akibat resolusi izin (fail closed atau deny yang cocok, §7.4), dan percobaan penghapusan yang ditolak — serta penghapusan yang berhasil dilakukan (mis. unit kosong yang dihapus Superadmin).
- Pembuatan dan penggunaan Unit (create/update/delete).
- Perubahan setelan aplikasi (`pengaturan:update` — nilai lama/baru per kunci, lihat §26.5), termasuk kunci grup `berkas` (§18.9).

### 25.2 Struktur Pencatatan

Setiap baris `audit_log` menyimpan: `actor_id`, `waktu`, `tindakan`, `objek_tipe`, `objek_id`, `nilai_lama` (JSON, nullable), `nilai_baru` (JSON, nullable), `alasan` (teks, wajib untuk tindakan tertentu seperti koreksi PK, pengembalian pengukuran/rencana aksi (baik jalur normal maupun buka kembali setelah pengesahan), pergantian penanggung jawab, revisi Renstra, dan penghapusan unit), dan **`dasar_izin`** (JSON, nullable) — khusus aksi dengan `permissions.sensitif = true` (§7.2), memuat daftar sumber izin yang mengizinkan aksi tersebut (peran mana/grant mana) atau deny yang memicu penolakan (§7.4). Transisi verifikasi/pengesahan Pengukuran yang dilakukan Perencanaan atas pengisiannya sendiri menyertakan penanda **`self_approval`** (§7.6). Perubahan definisi komponen dan persyaratan `jenis_berkas` (termasuk mode bukti dan `semua_mode_wajib`) dicatat dengan `nilai_lama`/`nilai_baru` dan `alasan` — inilah jejak yang menjaga data historis tetap dapat dipertanggungjawabkan meski formula dan persyaratan bukti dukung dapat diubah dari layar.

### 25.3 Sifat Append-Only

Tabel `audit_log` append-only — tidak ada endpoint untuk mengubah atau menghapus baris audit, baik melalui UI maupun API internal.

### 25.4 Visibilitas per Role

- `audit:read` dimiliki Superadmin, Perencanaan, dan Pimpinan.
- Pegawai tidak memiliki akses baca audit log secara umum pada Fase Awal (tidak termasuk dalam isi peran `pegawai`).

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
- Batas unggahan berkas (default aplikasi): ukuran maksimum unggahan berkas dan daftar format yang diizinkan, disimpan pada grup kunci `berkas` — dipakai sebagai nilai fallback saat `jenis_berkas.ukuran_maks_kb`/`format_diizinkan` kosong (§18.9). Termasuk panel read-only "Penggunaan penyimpanan bukti dukung" (§18.9).

### 26.4 Halaman "Batas Unggahan Berkas"

Halaman kerja tersendiri di dalam Setelan Aplikasi, terpisah dari kunci grup `berkas` pada §26.3: mendaftar **seluruh** `jenis_berkas` (aktif maupun nonaktif) dan mengizinkan pengubahan **hanya dua kolom** per baris persyaratan — **`format_diizinkan`** dan **`ukuran_maks_kb`** (§18.2). Kolom substantif persyaratan (`nama`, `tahap`, `wajib`, `izinkan_file`, `izinkan_tautan`, `izinkan_teks`, `semua_mode_wajib`, `indikator_id`) **tidak diterima** oleh halaman ini sama sekali — permintaan yang menyertakan kolom di luar dua kolom tersebut ditolak sistem, bukan diabaikan diam-diam.

**Validasi:**

- `ukuran_maks_kb` minimal `100` bila diisi.
- `format_diizinkan` wajib terisi bila mode file diizinkan (`izinkan_file = true`) pada persyaratan tersebut.
- Perubahan bersifat **grandfathered**: bukti yang sudah terunggah tidak menjadi tidak sah karena perubahan batas. Bila perubahan menyempitkan daftar format sementara sudah ada bukti berformat lama, sistem menampilkan **peringatan**, bukan menolak penyimpanan.

**Hak akses:** halaman ini digerbangi **`pengaturan:update`** (Admin dan Superadmin) — bukan `jenis_berkas:update` (Perencanaan). Pemegang `pengaturan:update` juga memegang `jenis_berkas:read` untuk melihat daftar persyaratan saat menilai kebutuhan penyimpanan (§7.3).

**Pembagian kewenangan (ditegaskan eksplisit):** substansi persyaratan bukti dukung — nama, tahap, wajib/opsional, mode yang diizinkan, `semua_mode_wajib`, cakupan per indikator — adalah wewenang **Perencanaan** (`jenis_berkas:create`/`update`/`delete`, §18.1). Kebijakan teknis unggahan — format dan ukuran maksimum per persyaratan lewat halaman ini, serta saklar/format/ukuran default tingkat aplikasi pada §26.3 — adalah wewenang pemegang **`pengaturan:update`** (Admin dan Superadmin). Admin dengan begitu dapat mengakomodasi kebutuhan teknis (mis. menaikkan batas ukuran karena kapasitas storage bertambah, atau melebarkan format yang diizinkan) tanpa memperoleh wewenang substantif atas persyaratan kinerja yang ditetapkan Perencanaan.

**Audit:** setiap perubahan lewat halaman ini tercatat di `audit_log` per persyaratan — persyaratan mana, kolom apa (`format_diizinkan` atau `ukuran_maks_kb`), nilai lama → nilai baru, oleh siapa (§25.1).

### 26.5 Hak Akses

Permission `pengaturan:update` dimiliki **Admin dan Superadmin** (§7.3) — tidak ada peran lain yang memilikinya lewat isi peran maupun dianjurkan mendapatkannya secara eksplisit. Pembacaan nilai setelan terjadi otomatis saat render halaman, tanpa memerlukan permission baca khusus.

### 26.6 Audit

Setiap perubahan setelan tercatat di `audit_log` (nilai lama/baru per kunci) — masuk ke daftar peristiwa teraudit (§25.1).

### 26.7 Batas Tegas

Yang boleh dikelola secara dinamis lewat modul ini **hanya teks, preferensi presentasional, dan dua kolom teknis unggahan pada §26.4**, termasuk kunci grup `berkas` (§18.9) yang tetap berstatus preferensi operasional. Modul ini secara eksplisit **bukan** tempat mengubah enum/status, nama permission, atau aturan bisnis apa pun — perubahan semacam itu tetap memerlukan perubahan kode dan migrasi basis data, bukan lewat UI setelan. Daftar mode bukti yang diizinkan per persyaratan dan seluruh kolom substantif lainnya tetap milik `jenis_berkas` (Perencanaan), bukan milik modul ini (§18.9, §26.4). Kustomisasi tampilan halaman login Keycloak (branding, logo login) berada di luar cakupan aplikasi SAKIP — diatur di level realm Keycloak, bukan lewat modul ini.

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
  - `renstra_pk`: unique(`renstra_id`, `tahun`).
  - `regulasi`: unique(`jenis`, `nomor`, `tahun`).
  - `jadwal_tahunan`: unique(`tahun`) di antara jadwal **aktif** untuk Renstra yang sama.
  - `jadwal_periode`: unique(`jadwal_id`, `periode_id`).
  - `jadwal_snapshot`: unique(`jadwal_id`, `indikator_id`) — menegakkan idempotensi pembentukan snapshot (§12.5); `jadwal_snapshot_komponen` mengikuti idempotensi induknya.
  - `permissions.kode`: unique.
  - `role_permissions`: unique(`role_id`, `permission_id`).
  - `user_roles`: unique(`user_id`) — satu peran per pengguna pada Fase Awal.
  - `user_permission_granted`: unique(`user_id`, `permission_id`, `unit_id`) — implementasi index unik memakai `COALESCE(unit_id, ...)` karena PostgreSQL memperlakukan NULL sebagai nilai berbeda.
  - `user_permission_denied`: unique(`user_id`, `permission_id`, `unit_id`) dengan pola `COALESCE` yang sama.
  - `users.keycloak_id`: unique — satu identitas Keycloak terhubung ke satu akun SAKIP.
  - `pengaturan.kunci`: unique.
- Anjuran PostgreSQL exclusion constraint (`EXCLUDE USING gist` + `btree_gist`) untuk rentang tahun Renstra yang beririsan tetap berlaku sebagai lapisan pertahanan kedua (§12.4); validasi utama ditegakkan di service layer.
- Tidak ada penghapusan permanen untuk data bermakna (pengukuran/rencana aksi dengan nilai/catatan, kegiatan yang sudah berjalan, riwayat penanggung jawab, riwayat status capaian, riwayat PK, baris snapshot yang telah dirujuk, berkas yang telah diunggah pada induk yang belum disahkan mengikuti soft delete) — lihat §4 prinsip 2 dan 3.
- Renstra dengan rentang tahun beririsan tidak boleh sama-sama berstatus `aktif` (§10.2); Renstra tidak dapat dinonaktifkan selama ada jadwal aktif yang merujuknya (§10.4).

---

## 28. Alert Kontekstual

Pada Fase Awal, alert kontekstual mencakup notifikasi/tampilan dalam aplikasi (bukan email/push eksternal, kecuali diputuskan lain kemudian) untuk:

- Tenggat penyusunan Rencana Aksi (`rencana_aksi_mulai/selesai` pada `jadwal_tahunan`, §12.2) yang mendekat/terlewati bagi PIC yang belum menyusun.
- Tenggat `pengisian_selesai` per periode (`jadwal_periode`, §12.3) yang mendekat/terlewati bagi PIC yang belum mengisi (status "Belum mengisi"/"Tidak mengisi", lihat §23).
- Rencana Aksi atau Pengukuran yang dikembalikan — notifikasi ke penanggung jawab terkait beserta alasan pengembalian, baik jalur normal maupun buka kembali setelah pengesahan.
- Rencana Aksi atau Pengukuran yang telah diajukan dan menunggu verifikasi — notifikasi/daftar tugas untuk Perencanaan.
- Kelengkapan bukti dukung wajib (rencana aksi/pengukuran/kegiatan) yang belum terpenuhi menjelang batas waktu pengajuan/perubahan status.
- Persyaratan bukti dukung yang ditandai `tidak_dapat_dipenuhi` (§18.7), agar Perencanaan segera menambahkan mode alternatif atau meninjau kebijakan unggahan.
- Kegiatan berstatus `tidak_terlaksana`/`ditunda`/`batal` yang belum diisi justifikasinya.
- PK tahun berjalan yang belum tercatat menjelang jendela aktivasi jadwal — pengingat bagi Perencanaan agar tidak terhambat validasi aktivasi (§10.3, §12.4).

---

## 29. Model Data (Ringkasan)

Model data lengkap (ERD dan detail kolom) didokumentasikan secara terpisah pada **SAKIP - Data Model.md**. Ringkasan kelompok entitas pada Fase Awal:

- **Identitas & Akses**: `users`, `unit`, `roles`, `permissions`, `role_permissions`, `user_roles`, `user_permission_granted`, `user_permission_denied`.
- **Dasar Aturan**: `regulasi`.
- **Strategi & Kinerja**: `renstra`, `renstra_pk`, `sasaran`, `indikator`, `target_tahunan`.
- **Periode & Jadwal**: `periode`, `jadwal_tahunan`, `jadwal_periode`, `jadwal_snapshot`, `jadwal_snapshot_komponen`.
- **Rencana & Kegiatan**: `rencana_aksi`, `rencana_aksi_target`, `kegiatan`, `klaim_kegiatan`.
- **Komponen & Perhitungan**: `indikator_komponen`, `pengukuran_komponen`.
- **Berkas**: `jenis_berkas`, `berkas`.
- **Eksekusi & Akuntabilitas**: `penanggung_jawab`, `pengukuran`, `status_capaian`, `rekomendasi_pimpinan`, `audit_log`.
- **Konfigurasi**: `pengaturan`.

---

## 30. Modul Produk

Urutan modul mengikuti prioritas pembangunan (§33):

1. **Autentikasi & Akses** — login Keycloak, pengelolaan peran, katalog permission, grant izin per unit, dan deny izin.
2. **Dasar Aturan** — CRUD `regulasi` (Kepmen/Permen/Perpres/keputusan lainnya) beserta lampiran dokumen sumbernya.
3. **Master Renstra** — Renstra (termasuk rujukan `regulasi_id` dan lampiran dokumen Renstra), Sasaran, Indikator (termasuk `tipe_perhitungan`, `regulasi_id`, dan definisi Komponen Indikator), Target Tahunan, PK (termasuk lampiran dokumen PK).
4. **Periode & Jadwal** — Periode master, Jadwal Tahunan (termasuk jendela Rencana Aksi dan gerbang lampiran PK), Jadwal Periode, aktivasi & snapshot (termasuk snapshot komponen), buka kembali.
5. **Penugasan** — Penanggung Jawab per indikator.
6. **Rencana Aksi** — penyusunan target per periode per komponen, ajukan, verifikasi, kembalikan, sahkan, buka kembali.
7. **Kegiatan & Klaim Kegiatan** — pencatatan kegiatan per unit per periode, klaim ke Rencana Aksi/komponen.
8. **Bukti Dukung (Berkas Persyaratan)** — pengelolaan `jenis_berkas` (tiga mode bukti, tahap rencana aksi/pengukuran/kegiatan), pengiriman dan pengelolaan `berkas` pada enam jenis induk (Rencana Aksi/Pengukuran/Kegiatan/Renstra/Perjanjian Kinerja/Dasar Aturan), kebijakan storage grup `berkas` pada Setelan Aplikasi.
9. **Pengukuran** — input komponen (atau nilai manual), ajukan (digerbangi §19.4), verifikasi, kembalikan, sahkan, buka kembali setelah pengesahan.
10. **Reviu & Pengesahan** — antarmuka kerja Perencanaan untuk memproses antrean Diajukan/Diverifikasi (Rencana Aksi maupun Pengukuran), penetapan Status Capaian, dan penetapan Rekomendasi Pimpinan.
11. **Dashboard** — visualisasi ApexCharts, filter, status tampilan (§23), panel Rencana Aksi & Kegiatan.
12. **Laporan & Ekspor** — tabel terfilter, rekapitulasi indikator × periode, ekspor Excel.
13. **Audit & Histori** — pencarian dan tampilan `audit_log`.
14. **Setelan Aplikasi** — identitas instansi/aplikasi, label unit, preferensi tampilan/laporan, kebijakan storage bukti dukung grup `berkas`, halaman "Batas unggahan berkas" (§26).

---

## 31. Alur Penggunaan Utama (Fase Awal)

Ringkasan naratif (diagram lengkap ada di SAKIP - Workflow.md):

1. Pengguna login via Keycloak (SSO).
2. Perencanaan mencatat Dasar Aturan (`regulasi`) yang relevan — mis. Kepmendiktisaintek 358/M/KEP/2025 — beserta lampiran dokumen sumbernya.
3. Perencanaan menyusun Renstra (dasar hukum, rentang tahun, rujukan `regulasi_id`) beserta Sasaran dan Indikator (termasuk arah penilaian, cara hitung/`tipe_perhitungan` tiap indikator, `regulasi_id` per indikator bila relevan, beserta definisi Komponen Indikator bila bukan `manual`), lalu mengisi Target Tahunan (termasuk baseline).
4. Perencanaan mencatat Perjanjian Kinerja (PK) untuk tahun berjalan beserta lampiran dokumennya.
5. Perencanaan mengaktifkan Renstra (lolos validasi §10.2).
6. Perencanaan membuat Jadwal Tahunan beserta Jadwal Periode (jendela pengisian/reviu per periode yang diharapkan pada tahun itu) dan jendela penyusunan Rencana Aksi, merujuk PK tahun tersebut.
7. Perencanaan mengaktifkan Jadwal Tahunan — lolos empat gerbang validasi (§12.4), termasuk gerbang lampiran dokumen PK (§14.10) — aktivasi memicu pembuatan `jadwal_snapshot` beserta `jadwal_snapshot_komponen` secara idempoten untuk seluruh indikator terkait.
8. Perencanaan/Superadmin menugaskan Penanggung Jawab per indikator (jika belum ada dari periode sebelumnya, atau terjadi pergantian).
9. Perencanaan menetapkan persyaratan Bukti Dukung (`jenis_berkas`) per tahap (Rencana Aksi/Pengukuran/Kegiatan) bila diperlukan, termasuk mode bukti yang diizinkan (file/tautan/teks); Admin/Superadmin dapat menyesuaikan `format_diizinkan`/`ukuran_maks_kb` lewat halaman "Batas unggahan berkas" (§26.4) tanpa mengubah substansi persyaratan.
10. Dalam jendela `rencana_aksi_mulai`–`rencana_aksi_selesai`, PIC menyusun Rencana Aksi indikatornya: mengisi target per periode per komponen (kumulatif), memenuhi persyaratan bukti dukung wajib tahap Rencana Aksi bila ada (file, tautan, atau teks sesuai mode yang diizinkan), lalu mengajukannya; Perencanaan memverifikasi dan mengesahkannya.
11. PIC menyusun Kegiatan per periode untuk unitnya, dan dapat mengklaim kegiatan ke Rencana Aksi/komponen yang didukungnya; saat kegiatan dinyatakan `terlaksana`, sistem memeriksa kelengkapan bukti dukung wajib bertahap `kegiatan` (§15.6).
12. Penanggung jawab (PIC) mengisi nilai komponen (atau nilai manual) Pengukuran (Draft), memenuhi persyaratan bukti dukung wajib tahap Pengukuran bila ada, lalu mengajukannya (Diajukan) dalam jendela `pengisian_mulai`–`pengisian_selesai` periode yang bersangkutan — tenggat ini mutlak bagi PIC, dan pengajuan hanya berhasil bila lolos gerbang §19.4 (Rencana Aksi disahkan, komponen lengkap, bukti dukung wajib terpenuhi).
13. Perencanaan memverifikasi (Diverifikasi) atau mengembalikan (Dikembalikan, dengan alasan) dalam jendela reviu periode tersebut; Perencanaan sendiri dapat mengisi/mengoreksi Rencana Aksi maupun Pengukuran kapan pun sampai `penutupan` jadwal tahunan.
14. Perencanaan mengesahkan (Disahkan) — tanpa tahap approval Pimpinan pada Fase Awal.
15. Perencanaan/Superadmin menetapkan Status Capaian secara manual atas pengukuran yang telah Disahkan, dan Perencanaan menetapkan Rekomendasi Pimpinan atas indikator × periode terkait.
16. Dashboard dan Laporan menampilkan capaian terkini secara otomatis begitu status berubah menjadi Disahkan dan Status Capaian ditetapkan; rekapitulasi indikator × periode dapat diekspor kapan pun.
17. Pimpinan memantau melalui dashboard dan laporan (akses baca + ekspor Excel, termasuk pembacaan Rekomendasi Pimpinan), tanpa melakukan aksi persetujuan pada Fase Awal.
18. Bila diperlukan, Perencanaan mengoreksi Rencana Aksi/Pengukuran yang sudah Disahkan lewat `rencana_aksi:buka_kembali`/`pengukuran:buka_kembali` (sebelum penutupan) atau `jadwal:buka_kembali` (setelah penutupan), atau menambahkan indikator IKU baru/backfill data historis melalui `jadwal:buka_kembali` — seluruhnya teraudit.

---

## 32. Kriteria Penerimaan (Fase Awal)

Kriteria berikut harus terpenuhi agar MVP dianggap layak rilis:

1. **Autentikasi**: Pengguna dapat login melalui Keycloak dan sesi Laravel terbentuk berbasis `keycloak_id`; percobaan akses tanpa sesi valid diarahkan ke login.
2. **Otorisasi granular**: Permintaan terhadap aksi apa pun ditolak (403) bila algoritma resolusi izin (§7.4) menghasilkan tolak — termasuk saat kode permission tidak dikenal (fail closed), saat ditemukan deny yang cocok (presedens deny menang atas allow), atau saat tidak ada allow yang cocok; permintaan `pengukuran:create`/`update`/`rencana_aksi:create`/`update`/`ajukan`/`kegiatan:create`/`update` oleh PIC ditolak jika tidak ada grant yang cocok dengan `unit_id` target, sementara permintaan yang sama oleh Perencanaan diizinkan tanpa syarat unit karena bersumber dari isi peran (selalu global).
3. **Validasi aktivasi Renstra**: Sistem menolak aktivasi Renstra tanpa `dasar_hukum` terisi, dan menolak aktivasi jika terjadi irisan rentang tahun dengan Renstra aktif lain.
4. **Larangan nonaktivasi Renstra**: Sistem menolak permintaan menonaktifkan Renstra selama masih ada `jadwal_tahunan` berstatus aktif yang merujuknya.
5. **Empat gerbang aktivasi Jadwal**: Sistem menolak aktivasi Jadwal Tahunan jika (a) `renstra_pk` tahun tersebut belum tercatat, (b) ada indikator aktif tanpa `target_tahunan` tahun itu, (c) tahun jadwal berada di luar rentang tahun Renstra, atau (d) `renstra_pk` tahun tersebut belum memiliki minimal satu lampiran dokumen — gerbang (d) ditandai `tidak_dapat_dipenuhi` dan tidak menghalangi aktivasi bila unggahan file dimatikan sementara belum ada lampiran mode lain (§12.4, §14.10).
6. **Snapshot otomatis, idempoten, dan imutabel**: Saat Jadwal diaktifkan (termasuk lewat buka kembali), baris `jadwal_snapshot` beserta `jadwal_snapshot_komponen` terbentuk otomatis untuk setiap indikator relevan yang belum punya baris snapshot (tidak pernah menimpa yang sudah ada); baris yang telah dirujuk pengukuran pertamanya tidak dapat diubah lagi.
7. **Rencana Aksi lengkap sebelum diajukan**: Sistem menolak pengajuan Rencana Aksi bila ada komponen aktif yang belum memiliki target pada salah satu periode yang diharapkan.
8. **Gerbang keras Pengukuran**: Sistem menolak pengajuan Pengukuran bila Rencana Aksi (indikator × tahun) belum `disahkan`, bila ada komponen aktif dengan nilai `null`, atau bila ada persyaratan bukti dukung wajib tahap pengukuran yang belum terpenuhi (mode file/tautan/teks sesuai yang diizinkan) — kecuali pada jadwal retroaktif.
9. **Gerbang keras Kegiatan**: Sistem menolak transisi status Kegiatan `rencana → terlaksana` bila ada persyaratan bukti dukung wajib bertahap `kegiatan` yang belum terpenuhi (§15.6); persyaratan yang ditandai `tidak_dapat_dipenuhi` (§18.7) tidak menghalangi transisi ini. Transisi ke `tidak_terlaksana`/`ditunda`/`batal` hanya mensyaratkan `justifikasi` terisi.
10. **Mesin perhitungan komponen**: Untuk indikator bertipe `rasio_persen`/`penjumlahan`, sistem menghitung `pengukuran.nilai` secara otomatis dan benar dari `pengukuran_komponen` sesuai definisi `indikator_komponen` (peran dan bobot), termasuk penanganan pembagian nol sebagai "tidak dapat dihitung".
11. **Deadline pengisian per periode dan per jendela Rencana Aksi**: PIC ditolak sistem saat mencoba membuat/mengubah/mengajukan Pengukuran setelah `pengisian_selesai` periode yang bersangkutan terlewati, dan saat mencoba menyusun/mengajukan Rencana Aksi setelah `rencana_aksi_selesai` terlewati; Perencanaan tetap dapat melakukannya sampai `penutupan` jadwal tahunan.
12. **Alur linear Rencana Aksi dan Pengukuran**: Transisi status pada kedua entitas hanya dapat mengikuti urutan yang diizinkan (Draft→Diajukan→Diverifikasi→Disahkan, dengan Dikembalikan sebagai jalur balik dari Diajukan/Diverifikasi/Disahkan); transisi di luar urutan ini ditolak sistem.
13. **Buka kembali setelah pengesahan**: Sebelum `penutupan`, `pengukuran:buka_kembali`/`rencana_aksi:buka_kembali` berhasil memindahkan status Disahkan ke Dikembalikan dengan alasan wajib; setelah `penutupan`, permintaan yang sama ditolak dan hanya `jadwal:buka_kembali` yang dapat membuka jalur koreksi.
14. **Validasi catatan wajib berbasis arah**: Pengajuan pengukuran ditolak jika catatan kosong pada kondisi nilai (termasuk nilai turunan) memburuk menurut `indikator.arah` dibanding pengukuran Disahkan terakhir secara kronologis, atau jika `indikator.wajib_catatan = true`; nilai stagnan dan pengukuran pertama tanpa pembanding tidak memicu penolakan ini.
15. **Klaim kegiatan tidak mengubah nilai komponen**: Menambah/menghapus baris `klaim_kegiatan` tidak mengubah nilai `pengukuran_komponen`/`rencana_aksi_target` mana pun secara otomatis.
16. **Optimistic locking**: Update pengukuran/rencana aksi dengan nomor `versi` usang ditolak dengan pesan konflik yang jelas.
17. **Tidak ada penghapusan permanen**: Percobaan menghapus pengukuran/rencana aksi yang memiliki nilai/catatan, baris snapshot yang telah dirujuk, atau bukti dukung pada induk yang sudah berada dalam keadaan sah (Disahkan/`terlaksana`), ditolak sistem dan/atau tercatat sebagai percobaan di audit log.
18. **Audit lengkap**: Setiap peristiwa pada §25.1 menghasilkan baris `audit_log` yang dapat dicari dan ditelusuri oleh pengguna dengan `audit:read`.
19. **Dashboard hanya data sah**: Angka capaian pada dashboard hanya dihitung dari pengukuran berstatus Disahkan; status "Belum mengisi"/"Tidak mengisi" dihitung per kombinasi indikator × periode dari `jadwal_periode`, mengecualikan indikator arsip.
20. **Ekspor Excel**: Laporan tabular terfilter dan rekapitulasi indikator × periode dapat diekspor ke berkas Excel yang valid dan dapat dibuka.
21. **Tanpa approval Pimpinan**: Tidak ada langkah wajib yang memerlukan aksi `pengukuran:setujui` agar Pengukuran mencapai status Disahkan pada Fase Awal; Rekomendasi Pimpinan diisi Perencanaan, bukan Pimpinan.
22. **Status capaian manual**: Seluruh baris `status_capaian` yang dibuat pada Fase Awal memiliki `sumber = 'manual'` dan `ditetapkan_oleh` terisi.
23. **UI akses terbatas**: Hanya tiga form pengelolaan akses (assign peran, kelola grant izin per unit, kelola deny izin) beserta halaman "Jelaskan izin pengguna" yang tersedia di UI; tidak ada UI matrix permission penuh.
24. **Setelan aplikasi terbatas dan teraudit**: Hanya Superadmin dan Admin yang dapat mengubah `pengaturan` (termasuk kunci grup `berkas`) dan mengubah `format_diizinkan`/`ukuran_maks_kb` lewat halaman "Batas unggahan berkas"; setiap perubahan menghasilkan baris `audit_log` dengan nilai lama/baru; tidak ada kunci `pengaturan` maupun halaman "Batas unggahan berkas" yang mengendalikan enum/status/permission/aturan bisnis maupun kolom substantif persyaratan bukti dukung.
25. **Seed data**: Skrip seeder Laravel dapat dijalankan pada lingkungan pengembangan/testing dan menghasilkan minimal 1 Renstra, Sasaran, beberapa Indikator (termasuk minimal satu indikator berkomponen), Target, 1 PK, 1 Jadwal Tahunan beserta Jadwal Periode-nya, Rencana Aksi yang disahkan, dan Kegiatan yang valid untuk pengujian alur end-to-end.
26. **Presedens deny**: Sistem menolak permintaan otorisasi bila ditemukan baris `user_permission_denied` yang cocok (unit maupun global), terlepas dari peran atau grant apa pun yang dimiliki pengguna.
27. **Fail closed**: Sistem menolak permintaan otorisasi untuk kode permission yang tidak terdaftar aktif di tabel `permissions`.
28. **Dasar izin tercatat**: Setiap aksi dengan `permissions.sensitif = true` menghasilkan baris `audit_log` yang memuat `dasar_izin` (sumber allow yang mengizinkan, atau deny yang menolak).
29. **Pemisahan tugas F1/F2**: Sistem menolak transisi verifikasi/pengesahan Pengukuran oleh aktor yang sama dengan `pengukuran.created_by` pada jalur PIC; transisi yang sama oleh Perencanaan atas pengisiannya sendiri berhasil namun tercatat dengan penanda `self_approval`.
30. **Halaman "Jelaskan izin pengguna"**: Untuk sembarang pengguna yang dipilih, halaman ini menampilkan daftar izin efektifnya per unit beserta asal (peran/grant) dan deny yang berlaku, konsisten dengan hasil algoritma resolusi izin (§7.4).
31. **Validasi mode bukti dukung**: Penyimpanan `jenis_berkas` ditolak bila ketiga `izinkan_file`/`izinkan_tautan`/`izinkan_teks` bernilai `false`; pengiriman bukti (`berkas`) dengan `mode` di luar yang diizinkan pada persyaratannya ditolak sistem; bukti bermode `tautan` tanpa skema `http`/`https` atau bukti bermode `teks`/`tautan` tanpa `isi_teks`/`tautan` terisi ditolak.
32. **Penanda `tidak_dapat_dipenuhi` tidak memblokir**: Bila `berkas.unggahan_aktif = false` dan suatu persyaratan hanya mengizinkan mode `file`, persyaratan itu ditandai `tidak_dapat_dipenuhi`, tercatat di `audit_log`, tampil pada rekapitulasi dan halaman kerja Perencanaan, dan tidak menghalangi gerbang kelengkapan mana pun (§18.6, §18.7), termasuk gerbang lampiran PK pada aktivasi jadwal (§12.4).
33. **Entitas `regulasi` dan rujukannya**: Penyimpanan `regulasi` ditolak bila kombinasi (`jenis`, `nomor`, `tahun`) sudah ada (`unique`); lampiran `regulasi` ditolak dihapus selama regulasi masih dirujuk oleh Renstra atau indikator aktif; perubahan `renstra.regulasi_id`/`indikator.regulasi_id` tercatat di `audit_log`.
34. **Lampiran enam induk**: Pengiriman/penghapusan `berkas` berhasil untuk keenam nilai `berkasable_type` (`rencana_aksi`/`pengukuran`/`kegiatan`/`renstra`/`renstra_pk`/`regulasi`); penghapusan ditolak sistem setelah induk masing-masing melewati batas imutabilitasnya (§18.8).
35. **Halaman "Batas unggahan berkas" terbatas**: Permintaan ubah `jenis_berkas` lewat halaman ini yang menyertakan kolom substantif di luar `format_diizinkan`/`ukuran_maks_kb` ditolak sistem; halaman hanya dapat diakses pemegang `pengaturan:update`; setiap perubahan tercatat di `audit_log` per persyaratan (§26.4).
36. **Lampiran pemetaan indikator sebagai fixture**: Kedelapan baris pada §17.11.1, saat diinput sebagai `indikator_komponen` dengan angka contoh 2026, menghasilkan nilai turunan yang sama persis dengan hitungan manual pada kolom "Contoh angka 2026" — dipakai sebagai test mesin perhitungan (§17.11.3), bukan nilai yang di-hardcode pada kode.

---

## 33. Prioritas Versi Awal (Urutan Pembangunan)

Urutan modul mengikuti §30, dengan alasan dependency teknis:

1. Autentikasi & Akses — fondasi wajib sebelum modul lain dapat diuji dengan pengguna berperan.
2. Dasar Aturan — bergantung pada Autentikasi & Akses (permission `regulasi:*`); dibangun lebih dulu karena Master Renstra merujuknya lewat `regulasi_id`.
3. Master Renstra (Renstra, Sasaran, Indikator termasuk komponen, Target, PK) — bergantung pada Dasar Aturan; data dasar yang menjadi rujukan seluruh modul berikutnya.
4. Periode & Jadwal (termasuk Jadwal Periode, jendela Rencana Aksi, snapshot beserta komponennya, buka kembali, gerbang lampiran PK) — bergantung pada Renstra + PK + lampiran `renstra_pk`.
5. Penugasan — bergantung pada Indikator.
6. Rencana Aksi — bergantung pada Jadwal aktif (snapshot komponen) + Penugasan.
7. Kegiatan & Klaim Kegiatan — bergantung pada Rencana Aksi (untuk klaim) dan Unit.
8. Berkas Persyaratan (Bukti Dukung) — bergantung pada definisi `jenis_berkas` dari Perencanaan; digunakan oleh Rencana Aksi, Pengukuran, Kegiatan, Renstra, Perjanjian Kinerja, dan Dasar Aturan.
9. Pengukuran — bergantung pada Rencana Aksi disahkan, Jadwal aktif (snapshot), dan Berkas Persyaratan.
10. Reviu & Pengesahan — bergantung pada Rencana Aksi dan Pengukuran.
11. Dashboard — bergantung pada Pengukuran, Rencana Aksi, Kegiatan, dan Status Capaian.
12. Laporan & Ekspor — bergantung pada Pengukuran, Rencana Aksi, Kegiatan, dan Dashboard.
13. Audit & Histori — dibangun paralel sejak modul pertama (audit log dicatat sejak Autentikasi & Akses), namun UI pencarian/tampilannya dituntaskan setelah modul inti berjalan.
14. Setelan Aplikasi (termasuk halaman "Batas unggahan berkas") — tidak memiliki dependency berat ke modul lain; ditempatkan terakhir karena bersifat administratif dan tidak menghambat alur inti pengukuran.

Detail pemecahan task granular per fitur ada di **SAKIP - Plan Pengembangan.md**.

---

## 34. Catatan Risiko

1. **Snapshot dan titik commit aktivasi jadwal.** `jadwal_snapshot` (beserta `jadwal_snapshot_komponen`) diterapkan penuh sejak Fase Awal, bersifat idempoten dan imutabel setelah dirujuk — begitu suatu Jadwal Tahunan diaktifkan, perubahan berikutnya pada master Indikator (nama, definisi, satuan, target, arah, cara hitung, definisi komponen) tidak lagi memengaruhi pengukuran tahun berjalan yang sudah merujuk snapshot tersebut. Ini memang disengaja, untuk menjaga integritas historis capaian yang telah atau akan disahkan. Konsekuensinya, aktivasi jadwal menjadi titik commit penting: kesalahan pada master data atau definisi komponen yang belum diperbaiki sebelum aktivasi ikut terbekukan ke snapshot dan sulit dikoreksi tanpa `jadwal:buka_kembali` atau koreksi manual pada baris snapshot yang belum dirujuk. Validasi kelengkapan dan kebenaran Renstra/Indikator/Komponen/Target sebelum aktivasi jadwal (lihat §10.3, §12.4) karena itu perlu ditegakkan ketat, idealnya lewat checklist yang ditegakkan aplikasi maupun SOP manual Perencanaan.
2. **Rencana Aksi sebagai gerbang baru berisiko menghambat pengisian bila keterlambatan penyusunannya tidak dikelola.** Karena pengajuan Pengukuran ditolak selama Rencana Aksi belum `disahkan` (§19.4), keterlambatan penyusunan atau pengesahan Rencana Aksi berdampak langsung memblokir seluruh pengukuran periode berjalan pada indikator tersebut. Disarankan alert dini (§28) menjelang `rencana_aksi_selesai` dan pemantauan proaktif Perencanaan atas indikator yang belum menyusun Rencana Aksi.
3. **Mesin perhitungan satu tingkat membatasi indikator yang dapat diotomasi.** Indikator dengan struktur formula bertingkat (mis. Predikat SAKIP/ZI) tetap harus diketik manual (§17.10), sehingga manfaat mesin perhitungan tidak merata ke seluruh indikator — perlu SOP terpisah untuk indikator-indikator ini agar tetap konsisten dan terverifikasi meski tidak dihitung sistem.
4. **Klaim kegiatan yang tidak mengubah nilai komponen berisiko disalahpahami pengguna sebagai bug** bila tidak dijelaskan jelas di UI — perlu penegasan tampilan bahwa klaim adalah dokumentasi keterkaitan, sementara nilai komponen tetap harus diisi manual terpisah (§16.3).
5. **Makna "Disahkan" pada Fase Awal.** Status ini dicapai murni oleh keputusan internal Perencanaan, tanpa jenjang persetujuan pimpinan unit — berlaku untuk Rencana Aksi maupun Pengukuran. Organisasi perlu menyesuaikan ekspektasi: "Disahkan" di sini setara "sah secara administratif teknis", bukan "disetujui pimpinan". Pelaporan formal (mis. LKj) sebaiknya memperhitungkan konteks ini sampai Fase Lanjutan mengaktifkan approval Pimpinan.
6. **Ketergantungan pada kedisiplinan Perencanaan sebagai pihak yang dikecualikan dari tenggat.** Karena permission pengisian Rencana Aksi dan Pengukuran milik Perencanaan bersifat global dan tidak tunduk pada jendela periode/tahun, kedisiplinan menjaga kelengkapan data sebelum `penutupan` sepenuhnya bergantung pada SOP internal, bukan penegakan sistem. Disarankan pengingat/alert (§28) menjelang tanggal `penutupan` sebagai kompensasi.
7. **`jadwal:buka_kembali` sebagai jalur serbaguna berisiko disalahgunakan bila SOP longgar.** Karena mekanisme yang sama dipakai untuk beberapa tujuan berbeda (koreksi, penambahan IKU baru, backfill), penting agar setiap penggunaannya disertai alasan yang spesifik dan dapat dibedakan lewat audit log, bukan sekadar "buka jadwal" generik.
8. Penetapan status capaian dan Rekomendasi Pimpinan sepenuhnya manual pada Fase Awal, sehingga rawan human error dan keterlambatan — dashboard bisa menampilkan banyak baris "Belum ditetapkan" meski data sudah Disahkan. SOP yang disarankan: tetapkan status capaian dan Rekomendasi Pimpinan segera setelah pengesahan, idealnya dalam satu alur kerja yang sama.
9. Permission individual di luar tiga form pengelolaan akses (§7.5) hanya bisa diberikan lewat seeder atau query manual, yang berisiko human error dan tidak terdokumentasi lewat UI. Mitigasi minimalnya: perubahan manual semacam ini tetap harus melalui jalur yang menulis ke `audit_log` — misalnya Artisan command yang mencatat audit, bukan `UPDATE` SQL langsung tanpa jejak.
10. Penyimpanan file di disk VPS memerlukan kapasitas dan strategi cadangan yang memadai seiring bertambahnya volume unggahan tiap periode. Panel "Penggunaan penyimpanan bukti dukung" (§18.9) membantu memantau tren ini, tetapi keputusan menaikkan kapasitas atau mendorong persyaratan baru memakai mode `tautan`/`teks` tetap SOP manual Perencanaan dan pemegang `pengaturan:update` — perlu dipantau sejak awal operasional agar tidak menjadi kendala kapasitas di kemudian hari.
11. Selama ekspor PDF belum tersedia (Fase Lanjutan), kebutuhan lampiran dokumen resmi (LKj, laporan ke Kemen PANRB) tetap memerlukan proses manual di luar sistem, misalnya menyalin data Excel ke template dokumen, sampai Fase Lanjutan direalisasikan.
12. **Penanda `self_approval` membutuhkan pemantauan aktif.** Karena jalur Perencanaan boleh mengesahkan pengisiannya sendiri (§7.6), volume penanda `self_approval` yang tinggi adalah sinyal beban kerja atau keterlambatan PIC yang perlu ditindaklanjuti secara organisasi, bukan sekadar catatan administratif yang dibiarkan menumpuk di audit log.
13. **Gerbang lampiran PK dapat "dilonggarkan" lewat mode teks.** Karena gerbang lampiran PK pada aktivasi jadwal (§12.4) menerima mode apa pun termasuk keterangan teks bebas, ada risiko Perencanaan mengisi lampiran seadanya (teks singkat) hanya untuk melewati gerbang, alih-alih mengunggah naskah PK yang sesungguhnya. Mitigasi: SOP internal Perencanaan menegaskan bahwa lampiran PK idealnya berupa salinan naskah resmi (mode file/tautan), bukan sekadar teks — gerbang sistem hanya menjamin *ada* lampiran, bukan menjamin *mutu* lampiran.
14. **Pemisahan kewenangan `format_diizinkan`/`ukuran_maks_kb` antara dua jalur berisiko tumpang tindih tanpa koordinasi.** Karena kedua kolom ini dapat diubah baik lewat form persyaratan (Perencanaan, §18.2) maupun halaman "Batas unggahan berkas" (Admin/Superadmin, §26.4), perubahan oleh satu pihak dapat menimpa nilai yang baru saja ditetapkan pihak lain tanpa keduanya menyadari. Mitigasi: SOP operasional menetapkan bahwa perubahan format/ukuran rutin (kapasitas, dukungan format baru) lewat halaman Admin, sedangkan perubahan yang menyertai perubahan substansi persyaratan lain lewat form Perencanaan — dan audit log (§26.4) dipantau berkala untuk mendeteksi tumpang tindih.
15. **Lampiran pemetaan indikator (§17.11) berisiko dibaca sebagai aturan final oleh implementator** meski berstatus contoh pengisian awal. Mitigasi: penegasan definisi IKU 8 (§17.11.2) wajib diselesaikan Tim Perencanaan sebelum data produksi tahun 2026 diinput, dan setiap turunan indikator baru (2027 dst.) harus melalui verifikasi ulang terhadap Kepmen yang berlaku saat itu, bukan menyalin lampiran ini secara membuta.

---
