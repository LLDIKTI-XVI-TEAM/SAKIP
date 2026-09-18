# ISSUE TRACKER — SAKIP LLDIKTI WILAYAH XVI

Dokumen ini memuat penjabaran *User Stories* menjadi **Development Issues** yang lebih teknis (Epic/Task), disusun agar siap untuk dieksekusi secara *vertical slice* oleh tim *developer*.

---

## Bagian 1 — Fondasi Autentikasi, Unit Organisasi, dan Model Akses RBAC

### ISS-01.01 · [Feature] Login Terpusat Menggunakan Single Sign-On (SSO) Keycloak

**Terkait**: `US-01.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** seluruh Pengguna SAKIP (Pimpinan, Perencanaan, PIC, Admin),
> **Saya ingin** melakukan autentikasi menggunakan akun resmi institusi melalui Keycloak OIDC,
> **Sehingga** saya tidak perlu mengelola password lokal terpisah dan akses saya terotentikasi secara aman dan terpusat.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Pengguna belum login mengakses halaman sistem SAKIP, *When* Pengguna menekan tombol "Login SSO", *Then* Sistem mengarahkan Pengguna ke laman otentikasi Keycloak via *OIDC Authorization Code Flow*.
- [ ] AC-2: *Given* Pengguna memasukkan kredensial valid di Keycloak, *When* Keycloak mengalihkan kembali ke rute callback SAKIP, *Then* Sistem mencocokkan `keycloak_id` pada tabel `users`, menyinkronkan nama serta email, membentuk sesi Laravel, dan mengarahkan pengguna ke Dashboard.
- [ ] AC-3: *Given* Pengguna baru pertama kali login, *When* Token Keycloak valid diterima, *Then* Sistem secara otomatis menjalankan `firstOrCreate` pada tabel `users` dengan `status = aktif`.
- [ ] AC-4: *Given* Login gagal di sisi Keycloak, *When* Callback mengembalikan galat, *Then* Sistem menampilkan pesan kesalahan dan tidak membuat sesi lokal.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Mutasi tabel `users` (`keycloak_id`, `nama`, `email`).
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk `Rute publik (tanpa middleware auth).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-01.02 · [Feature] Pengelolaan Master Unit Organisasi

**Terkait**: `US-01.02`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Admin atau Superadmin,
> **Saya ingin** menambah, mengubah, menonaktifkan, dan menghapus unit organisasi,
> **Sehingga** struktur kepemilikan indikator dan ruang lingkup hak akses pegawai selalu sesuai dengan nomenklatur organisasi LLDIKTI XVI terkini.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Admin mengisi form pembuatan unit dengan nama valid, *When* Tombol simpan ditekan, *Then* Baris baru terbentuk di tabel `unit` dengan status default `aktif` dan tercatat di `audit_log`.
- [ ] AC-2: *Given* Suatu unit telah memiliki keterkaitan dengan minimal 1 indikator kinerja (`indikator.unit_id`), *When* Admin mencoba menghapus unit tersebut, *Then* Sistem menolak penghapusan dengan pesan validasi tegas bahwa unit yang memiliki indikator aktif tidak dapat dihapus.
- [ ] AC-3: *Given* Suatu unit tidak memiliki riwayat indikator sama sekali, *When* Superadmin menghapus unit, *Then* Baris unit dihapus dan peristiwa tercatat di `audit_log`.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `unit`, entri `audit_log` (`objek_tipe = unit`)., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``unit:create`, `unit:read`, `unit:update`, `unit:delete`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-01.03 · [Feature] Penetapan Peran Pengguna (Form 1 — Assign Peran)

**Terkait**: `US-01.03`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Admin atau Superadmin,
> **Saya ingin** menetapkan satu peran utama kepada pengguna (Superadmin, Admin, Perencanaan, Pimpinan, Pegawai) disertai alasan penetapan,
> **Sehingga** pengguna memperoleh paket permission bawaan sesuai tanggung jawab formalnya.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Admin membuka form penetapan peran, *When* Memilih pengguna X, memilih peran Y, dan mengisi alasan wajib, *Then* Sistem melakukan insert/update pada `user_roles` dengan `diberikan_oleh = auth()->id()`.
- [ ] AC-2: *Given* Pengguna X telah memiliki peran lama, *When* Peran diganti ke peran baru, *Then* Sistem memperbarui baris `user_roles` (karena batas unik 1 user = 1 peran di MVP) dan mencatat perubahan di `audit_log` dengan `nilai_lama` (peran asal) dan `nilai_baru` (peran baru).
- [ ] AC-3: *Given* Admin mengosongkan kolom alasan, *When* Menekan tombol simpan, *Then* Sistem menolak penyimpanan dan mewajibkan alasan diisi.
- [ ] AC-4: *Given* Pengguna dengan peran Perencanaan, Pimpinan, atau Pegawai mencoba mengakses form ini, *When* Membuka URL endpoint, *Then* Sistem mengembalikan respons 403 Forbidden.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `user_roles`, entri `audit_log` (`tindakan = user_roles.tambah/ubah`)., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``akses:update`, `pengguna:read`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-01.04 · [Feature] Pemberian Grant Izin Tambahan per Unit (Form 2 — Grant Izin Unit)

**Terkait**: `US-01.04`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Admin atau Superadmin,
> **Saya ingin** memberikan izin operasional khusus dengan batasan unit kerja tertentu kepada Pegawai (PIC),
> **Sehingga** PIC tersebut berwenang menyusun rencana aksi, mencatat kegiatan, dan mengisi pengukuran hanya untuk indikator milik unit kerjanya.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Admin memilih pengguna X, memilih permission bertipe `butuh_scope = unit` (mis. `pengukuran:create`), memilih unit U, dan mengisi alasan, *When* Form disimpan, *Then* Baris baru terbentuk di `user_permission_granted` dengan `unit_id = U`.
- [ ] AC-2: *Given* Admin mencoba memberikan grant atas permission bertipe `butuh_scope = global` (mis. `pengaturan:update` atau `jadwal:aktivasi`), *When* Validasi dijalankan, *Then* Sistem menolak karena permission global tidak boleh dibatasi unit.
- [ ] AC-3: *Given* Admin mencoba menyimpan grant tanpa memilih unit untuk permission berjenis unit, *When* Validasi dijalankan, *Then* Sistem menolak dengan pesan bahwa unit wajib dipilih.
- [ ] AC-4: *Given* Pengguna telah memiliki grant identik (user, permission, unit sama), *When* Disimpan ulang, *Then* Sistem menolak duplikasi (constraint unik).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `user_permission_granted`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``akses:update`.`; Buat endpoint POST dengan Form Request validation
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-01.05 · [Feature] Pencabutan Izin Eksplisit (Form 3 — Deny Izin)

**Terkait**: `US-01.05`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Admin atau Superadmin,
> **Saya ingin** menetapkan larangan izin (*deny*) terhadap pengguna tertentu baik secara global maupun terbatas unit,
> **Sehingga** akses berisiko dapat segera dimatikan secara presisi tanpa harus menghapus peran utama pengguna.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Admin memilih pengguna X, permission P, opsi unit (bisa NULL untuk global atau terisi unit U), dan mengisi alasan wajib, *When* Form disimpan, *Then* Baris baru terbentuk di `user_permission_denied`.
- [ ] AC-2: *Given* Pengguna X memiliki izin P dari peran bawaan (`role_permissions`), *When* Baris deny disimpan untuk P, *Then* Pada saat pengguna X melakukan aksi P, service evaluasi izin menolak akses (aturan: **DENY MENANG** atas allow).
- [ ] AC-3: *Given* Admin mencabut baris deny yang ada, *When* Konfirmasi dilakukan, *Then* Baris deny dihapus dan hak akses pengguna kembali normal sesuai peran/grant yang sah.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `user_permission_denied`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``akses:update`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-01.06 · [Feature] Transparansi Izin Pengguna (Halaman "Jelaskan Izin Pengguna")

**Terkait**: `US-01.06`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Admin atau Superadmin,
> **Saya ingin** melihat ringkasan visual seluruh izin efektif seorang pengguna beserta asal-usul perolehannya,
> **Sehingga** saya dapat mengaudit dan menjelaskan secara transparan mengapa seorang pengguna diizinkan atau dilarang mengeksekusi suatu fungsi di SAKIP.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Admin membuka halaman transparansi izin dan memilih Pengguna X, *When* Data dimuat, *Then* Sistem menampilkan daftar izin efektif per unit, menampilkan label asal izin ("Peran: Perencanaan", "Grant: Unit Fasilitasi Mutu", dst.), dan menampilkan baris deny yang sedang aktif beserta alasannya.
- [ ] AC-2: *Given* Halaman ini bersifat murni pemantauan (*read-only*), *When* Pengguna berinteraksi, *Then* Tidak ada tombol mutasi langsung di halaman ini; modifikasi diarahkan ke Form 1, Form 2, atau Form 3.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Verifikasi skema / relasi eksisting
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``pengguna:read`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
## Bagian 2 — Pengelolaan Dokumen Dasar Hukum & Master Renstra

### ISS-02.01 · [Feature] Pencatatan Dokumen Dasar Regulasi

**Terkait**: `US-02.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan atau Superadmin,
> **Saya ingin** mencatat katalog dasar hukum (Kepmen, Permen, Perpres, atau Keputusan Lainnya) lengkap dengan metadata dan bukti fisik lampiran,
> **Sehingga** seluruh sasaran dan indikator memiliki payung hukum formal yang dapat ditelusuri kapan saja.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan memasukkan jenis regulasi, nomor dokumen, tahun penetapan, judul pokok ("tentang"), tanggal, dan tautan sumber resmi, *When* Disimpan, *Then* Data tersimpan di tabel `regulasi` dan tercatat di `audit_log`.
- [ ] AC-2: *Given* Perencanaan melampirkan salinan naskah fisik produk hukum (bisa via unggah file PDF, tautan JDIH kementerian, atau ringkasan teks pasal), *When* Berkas dikirim, *Then* Baris polimorfik `berkas` terbentuk dengan `berkasable_type = regulasi` dan `jenis_berkas_id = NULL` (lampiran bebas dokumen dasar).
- [ ] AC-3: *Given* Regulasi dengan jenis, nomor, dan tahun yang identik sudah ada, *When* Dicoba input ulang, *Then* Sistem menolak penyimpanan (constraint unik database).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `regulasi`, tabel `berkas`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``regulasi:create`, `regulasi:read`, `regulasi:update`, `regulasi:delete`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-02.02 · [Feature] Penyusunan Renstra & Keterkaitan Regulasi

**Terkait**: `US-02.02`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** menyusun master Rencana Strategis (Renstra) baru dengan rentang tahun, ringkasan dasar hukum, tautan rujukan regulasi, dan lampiran dokumen,
> **Sehingga** acuan arah kebijakan instansi 5 tahunan terdokumentasi rapi di sistem.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan menginput nama Renstra, rentang tahun (mis. 2025–2029), teks ringkasan dasar hukum, dan memilih `regulasi_id`, *When* Form disubmit, *Then* Baris `renstra` terbentuk dengan status default `draft`.
- [ ] AC-2: *Given* Perencanaan melampirkan naskah resmi Renstra (mode file/tautan/teks), *When* Diunggah, *Then* Terbentuk baris `berkas` terkait `renstra` tersebut.
- [ ] AC-3: *Given* Sudah ada Renstra lain yang berstatus `aktif` dengan rentang tahun beririsan (mis. 2024–2028), *When* Perencanaan mencoba mengaktifkan Renstra baru tersebut, *Then* Sistem menolak aktivasi (validasi rentang tahun non-overlapping & exclusion constraint PostgreSQL).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `renstra`, `berkas`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``renstra:create`, `renstra:update`, `renstra:read`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-02.03 · [Feature] Penyusunan Sasaran Strategis & Indikator Kinerja Utama (IKU)

**Terkait**: `US-02.03`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** menyusun Sasaran Program dan Indikator Kinerja Utama (IKU) di bawah Renstra aktif, menetapkan unit pemilik, arah penilaian, tipe perhitungan, dan rujukan regulasi per indikator,
> **Sehingga** setiap tolak ukur keberhasilan instansi memiliki penanggung jawab unit dan logika ukur yang tegas.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan membuat sasaran strategis, *When* Berhasil disimpan, *Then* Baris baru terbentuk di tabel `sasaran`.
- [ ] AC-2: *Given* Perencanaan menambahkan indikator di bawah sasaran, memilih unit pemilik (`unit_id`), memilih arah (`naik_baik` / `turun_baik`), memilih tipe perhitungan (`manual` / `rasio_persen` / `penjumlahan`), serta mengisi presisi dan desimal tampilan, *When* Disimpan, *Then* Baris `indikator` terbentuk valid.
- [ ] AC-3: *Given* Indikator memiliki rujukan hukum teknis spesifik, *When* Perencanaan memilih `regulasi_id` pada indikator, *Then* Keterkaitan tersimpan dan dapat dilacak langsung dari ringkasan indikator.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `sasaran`, tabel `indikator`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``sasaran:create/update`, `indikator:create/update`, `indikator:read`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-02.04 · [Feature] Konfigurasi Komponen Angka Indikator (Data-Driven)

**Terkait**: `US-02.04`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** mendefinisikan komponen-komponen pembentuk indikator rasio persen atau penjumlahan (pembilang, penyebut, penjumlah, bobot, urutan),
> **Sehingga** mesin perhitungan sistem dapat melakukan kalkulasi capaian secara otomatis, transparan, dan dapat disesuaikan tanpa merilis kode baru.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Indikator bertipe `rasio_persen`, *When* Perencanaan mendefinisikan komponen, *Then* Sistem mewajibkan minimal 1 komponen berstatus `peran = pembilang` dan tepat 1 komponen berstatus `peran = penyebut`.
- [ ] AC-2: *Given* Indikator bertipe `penjumlahan`, *When* Perencanaan mendefinisikan komponen, *Then* Sistem mewajibkan minimal 1 komponen berstatus `peran = penjumlah`.
- [ ] AC-3: *Given* IKU 3 (Indeks Kinerja LLDIKTI) dikonfigurasi berdasarkan Keputusan Q5, *When* Komponen didaftarkan, *Then* Terdapat 5 komponen penjumlah (`perencanaan_kinerja`, `pengukuran_kinerja`, `pelaporan_kinerja`, `evaluasi_internal`, `zi`) masing-masing berbobot `0.5`, dan subtotal SAKIP ditampilkan sebagai nilai turunan.
- [ ] AC-4: *Given* Komponen didaftarkan dengan kode duplikat pada indikator yang sama, *When* Disimpan, *Then* Sistem menolak (constraint unik `indikator_id, kode`).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `indikator_komponen`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``komponen:create`, `komponen:update`, `komponen:delete`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-02.05 · [Feature] Penetapan Baseline & Target Tahunan Indikator

**Terkait**: `US-02.05`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** menginput nilai capaian tahun lalu (*baseline*) dan target kinerja tahunan untuk setiap indikator aktif,
> **Sehingga** penetapan target kinerja tahunan memiliki dasar pembanding yang valid sebelum perjanjian kinerja ditandatangani.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan membuka form target indikator untuk tahun Y, *When* Memasukkan angka baseline dan angka target tahunan, *Then* Data tersimpan pada tabel `target_tahunan`.
- [ ] AC-2: *Given* Sudah ada target tahunan untuk kombinasi indikator dan tahun yang sama, *When* Disimpan ulang, *Then* Sistem memperbarui nilai atau menolak duplikasi (constraint unik `indikator_id, tahun`).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `target_tahunan`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``target:create`, `target:update`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-02.06 · [Feature] Pencatatan Perjanjian Kinerja (PK) & Lampiran Berkas Legal

**Terkait**: `US-02.06`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** mencatat Perjanjian Kinerja (PK) tahun berjalan beserta nomor PK, tanggal penandatanganan, dan salinan dokumen fisiknya,
> **Sehingga** terdapat dasar hukum dan komitmen resmi pimpinan sebelum jadwal pelaksanaan kinerja diaktifkan.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan menginput nomor PK dan tanggal PK untuk tahun Y di bawah Renstra aktif, *When* Disimpan, *Then* Baris terbentuk di tabel `renstra_pk`.
- [ ] AC-2: *Given* Perencanaan melampirkan naskah fisik dokumen PK (mode file, tautan, atau teks), *When* Berkas dikirim, *Then* Baris polimorfik `berkas` terbentuk menginduk ke `renstra_pk`.
- [ ] AC-3: *Given* Renstra PK tahun Y sudah terdaftar di Renstra yang sama, *When* Diinput ulang, *Then* Sistem menolak duplikasi (constraint unik `renstra_id, tahun`).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `renstra_pk`, tabel `berkas`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``pk:create`, `pk:update`, `berkas:upload`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
## Bagian 3 — Periode, Penjadwalan, dan Pembekuan Konteks Snapshot

### ISS-03.01 · [Feature] Penyusunan Jadwal Tahunan & Periode Triwulan

**Terkait**: `US-03.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** menyusun Jadwal Tahunan tahun Y, menetapkan jendela rencana aksi tahunan, serta menyusun periode triwulanan beserta jendela pengisian dan reviu masing-masing,
> **Sehingga** siklus pelaporan berkala instansi memiliki batas waktu pelaksanaan yang terstruktur bagi seluruh unit kerja.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan membuat draft jadwal tahunan untuk tahun Y, mengisi tanggal `rencana_aksi_mulai` dan `rencana_aksi_selesai`, serta tanggal batas akhir `penutupan`, *When* Disimpan, *Then* Baris `jadwal_tahunan` berstatus `draft` terbentuk.
- [ ] AC-2: *Given* Perencanaan menambahkan daftar periode (Triwulan I, II, III, IV) pada `jadwal_periode`, *When* Masing-masing periode diisi tanggal `pengisian_mulai`, `pengisian_selesai`, `reviu_mulai`, dan `reviu_selesai`, *Then* Periode tersusun urut dan valid.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `jadwal_tahunan`, `jadwal_periode`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``jadwal:create`, `jadwal:update`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-03.02 · [Feature] Aktivasi Jadwal Tahunan Melalui Empat Gerbang Validasi Keras

**Terkait**: `US-03.02`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan atau Superadmin,
> **Saya ingin** mengaktifkan Jadwal Tahunan tahun berjalan,
> **Sehingga** sistem secara otomatis mengunci konteks indikator historis ke dalam snapshot dan membuka tahapan kerja bagi PIC.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan menekan tombol "Aktifkan Jadwal", *When* Sistem mengevaluasi 4 gerbang aktivasi: 1. Gerbang 1: `renstra_pk` tahun berjalan harus sudah tercatat. 2. Gerbang 2: Seluruh indikator aktif harus sudah memiliki `target_tahunan` pada tahun berjalan. 3. Gerbang 3: Tahun jadwal harus berada dalam rentang tahun Renstra induk. 4. Gerbang 4: `renstra_pk` tahun berjalan harus memiliki minimal satu lampiran dokumen fisik (file/tautan/teks) atau berstatus `tidak_dapat_dipenuhi` saat saklar berkas mati.
- [ ] AC-2: *Then* Jika salah satu gerbang gagal, aktivasi **DITOLAK** dan sistem menampilkan pesan gerbang mana yang belum terpenuhi.
- [ ] AC-3: *Given* Seluruh 4 gerbang lolos validasi, *When* Transaksi dieksekusi, *Then*:
- [ ] AC-4: Status `jadwal_tahunan` berubah menjadi `aktif`.
- [ ] AC-5: Sistem secara otomatis membuat salinan idempoten ke `jadwal_snapshot` untuk setiap indikator aktif (menyalin nama, arah, tipe perhitungan, presisi, target, dan baseline).
- [ ] AC-6: Sistem membuat salinan ke `jadwal_snapshot_komponen` untuk setiap komponen aktif.
- [ ] AC-7: Status jadwal tercatat di `audit_log`.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``jadwal:aktivasi` (permission bertipe sensitif).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
## Bagian 4 — Penugasan PIC Indikator Kinerja

### ISS-04.01 · [Feature] Penugasan Penanggung Jawab (PIC) Indikator Kinerja

**Terkait**: `US-04.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan atau Superadmin,
> **Saya ingin** menetapkan pegawai tertentu sebagai Penanggung Jawab (PIC) atas suatu indikator kinerja terhitung mulai tanggal tertentu,
> **Sehingga** akuntabilitas penginputan dan pelaporan indikator tersebut melekat secara formal kepada individu penanggung jawab.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan memilih indikator, memilih pengguna pegawai, menentukan `tanggal_mulai_berlaku`, dan mengisi alasan penetapan, *When* Disimpan, *Then* Baris baru terbentuk di tabel `penanggung_jawab` (pola riwayat/append-only, tidak ada mutasi in-place).
- [ ] AC-2: *Given* Suatu indikator telah memiliki beberapa baris penugasan di masa lalu, *When* Sistem mencari PIC aktif hari ini, *Then* Sistem mengambil baris terbaru dengan `tanggal_mulai_berlaku <= hari ini`.
- [ ] AC-3: *Given* PIC indikator berganti di tengah tahun, *When* Baris penugasan baru diinput, *Then* Hak pengisian rencana aksi dan pengukuran otomatis beralih ke PIC yang baru berlaku, sementara riwayat penugasan lama tetap abadi.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `penanggung_jawab`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``penanggung_jawab:update` (permission sensitif).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
## Bagian 5 — Penyusunan, Verifikasi, dan Pengesahan Rencana Aksi

### ISS-05.01 · [Feature] Penyusunan Target Rencana Aksi per Periode per Komponen

**Terkait**: `US-05.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja),
> **Saya ingin** menginput target kinerja kumulatif triwulanan pada tingkat komponen di bawah rencana aksi indikator saya,
> **Sehingga** target tahunan dapat dipecah menjadi target operasional bertahap yang terukur sepanjang tahun.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* PIC membuka layar Rencana Aksi untuk indikator miliknya, *When* Mengisi target periode per komponen (kumulatif), *Then* Sistem menyimpan baris `rencana_aksi_target` berstatus `draft`.
- [ ] AC-2: *Given* Nilai target suatu triwulan lebih kecil dari triwulan sebelumnya pada komponen yang sama, *When* PIC menginput angka, *Then* Sistem menampilkan indikator peringatan (*warning*) bahwa target bersifat kumulatif, tanpa memblokir input.
- [ ] AC-3: *Given* Batas akhir `rencana_aksi_selesai` telah terlewat, *When* PIC mencoba menambah atau mengubah target, *Then* Sistem menolak akses karena deadline PIC bersifat mutlak.
- [ ] AC-4: *Given* Tim Perencanaan mengakses rencana aksi di luar jendela waktu, *When* Menginput/mengubah data, *Then* Sistem mengizinkan (Perencanaan memiliki permission global dan dikecualikan dari batas jendela hingga penutupan tahunan).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `rencana_aksi`, `rencana_aksi_target`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``rencana_aksi:create`, `rencana_aksi:update` (scope unit bagi PIC, global bagi Perencanaan).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-05.02 · [Feature] Pemenuhan Bukti Dukung Tahap Rencana Aksi

**Terkait**: `US-05.02`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja),
> **Saya ingin** melampirkan dokumen bukti dukung pendukung rencana aksi sesuai katalog persyaratan yang ditetapkan,
> **Sehingga** rencana aksi yang saya susun didukung oleh dokumen perencanaan kerja yang valid.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Persyaratan jenis berkas mewajibkan dokumen bertahap `rencana_aksi`, *When* PIC mengirim bukti berupa unggah file, tautan dokumen cloud, atau keterangan teks, *Then* Baris polimorfik `berkas` terbentuk dengan `berkasable_type = rencana_aksi`.
- [ ] AC-2: *Given* Persyaratan hanya mengizinkan mode tautan, *When* PIC mencoba mengunggah file, *Then* Sistem menolak pengiriman mode yang tidak diizinkan.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `berkas`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``berkas:upload`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-05.03 · [Feature] Pengajuan Rencana Aksi & Gerbang Kelengkapan

**Terkait**: `US-05.03`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja),
> **Saya ingin** mengajukan rencana aksi yang telah selesai disusun kepada Tim Perencanaan,
> **Sehingga** rencana aksi tersebut dapat ditinjau dan disahkan secara resmi.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Ada komponen aktif yang target triwulannya masih bernilai `null`, *When* PIC menekan tombol ajukan, *Then* Sistem menolak pengajuan dengan pesan kesalahan bahwa seluruh komponen wajib memiliki target per periode.
- [ ] AC-2: *Given* Ada persyaratan bukti dukung bertahap `rencana_aksi` yang bertanda `wajib = true` dan belum terpenuhi, *When* PIC mengajukan, *Then* Sistem menolak pengajuan.
- [ ] AC-3: *Given* Total target komponen pada periode akhir tidak setara dengan target PK tahunan, *When* PIC mengajukan, *Then* Sistem menampilkan peringatan dan **mewajibkan kolom alasan diisi** sebelum pengajuan dapat diproses.
- [ ] AC-4: *Given* Seluruh validasi terpenuhi, *When* Pengajuan berhasil, *Then* Status rencana aksi berubah menjadi `diajukan`, versi bertambah, dan notifikasi in-app terkirim ke antrean tugas Tim Perencanaan.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``rencana_aksi:ajukan` (scope unit).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-05.04 · [Feature] Verifikasi & Pengembalian Rencana Aksi

**Terkait**: `US-05.04`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** memeriksa rencana aksi yang diajukan oleh PIC, serta menyetujui secara teknis atau mengembalikannya untuk direvisi,
> **Sehingga** kualitas substansi rencana aksi terjamin sebelum disahkan.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan menilai substansi rencana aksi telah baik, *When* Menekan tombol verifikasi, *Then* Status alur berubah menjadi `diverifikasi`.
- [ ] AC-2: *Given* Perencanaan menemukan target atau berkas yang tidak sesuai, *When* Menekan tombol kembalikan dan mengisi catatan alasan pengembalian wajib, *Then* Status berubah menjadi `dikembalikan`, dan notifikasi in-app instan dikirimkan ke PIC terkait berisi ringkasan catatan revisi.
- [ ] AC-3: *Given* Perencanaan mencoba mengembalikan tanpa mengisi alasan, *When* Disubmit, *Then* Sistem menolak pengembalian.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``rencana_aksi:verifikasi`, `rencana_aksi:kembalikan`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-05.05 · [Feature] Pengesahan Rencana Aksi & Penegakan Pemisahan Tugas (F1/F2)

**Terkait**: `US-05.05`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** mengesahkan rencana aksi yang telah berstatus diverifikasi,
> **Sehingga** rencana aksi tersebut menjadi dokumen operasional yang sah dan membuka izin pengajuan pengukuran kinerja.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Aktor yang melakukan pengesahan adalah PIC yang sama dengan pembuat pengajuan (`rencana_aksi.created_by`) melalui jalur unit kerja, *When* Transaksi pengesahan dicoba, *Then* Sistem menolak keras sesuai **Aturan F1 Pemisahan Tugas** (*Segregation of Duties*).
- [ ] AC-2: *Given* Aktor pengesahan adalah anggota Tim Perencanaan (jalur global) dan bukan pengaju unit, *When* Pengesahan disubmit, *Then* Status berubah menjadi `disahkan`, kolom `disahkan_at` dan `disahkan_by` terisi.
- [ ] AC-3: *Given* Rencana aksi disusun oleh Tim Perencanaan sendiri atas nama unit yang terlambat, *When* Anggota Perencanaan tersebut mengesahkan, *Then* Sistem mengizinkan sesuai **Aturan F2**, dan memberi penanda `self_approval = true` pada entri `audit_log`.
- [ ] AC-4: *Given* Rencana aksi telah berstatus `disahkan`, *When* PIC mencoba menghapus berkas bukti dukung yang menempel padanya, *Then* Sistem menolak karena batas imutabilitas telah tercapai.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``rencana_aksi:sahkan` (permission sensitif).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-05.06 · [Feature] Buka-Kembali Rencana Aksi yang Telah Disahkan

**Terkait**: `US-05.06`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan atau Superadmin,
> **Saya ingin** membuka kembali rencana aksi yang telah disahkan menjadi berstatus dikembalikan,
> **Sehingga** perbaikan mendasar dapat dilakukan apabila terdapat perubahan target resmi sebelum jadwal tahunan ditutup.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan memasukkan alasan pembukaan kembali yang sah, *When* Aksi dieksekusi, *Then* Status rencana aksi kembali menjadi `dikembalikan`, dan PIC dapat mengedit kembali targetnya.
- [ ] AC-2: *Given* Jadwal tahunan telah berstatus ditutup (*closed*), *When* Buka kembali dicoba, *Then* Sistem menolak aksi.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``rencana_aksi:buka_kembali` (permission sensitif).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
## Bagian 6 — Pengelolaan Kegiatan, Bukti SPJ, dan Klaim Dampak

### ISS-06.01 · [Feature] Pencatatan Rencana Kegiatan Unit Kerja

**Terkait**: `US-06.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja) atau Tim Perencanaan,
> **Saya ingin** mencatat rencana kegiatan nyata yang akan dilaksanakan oleh unit kerja pada suatu triwulan,
> **Sehingga** aktivitas operasional pendukung pencapaian target kinerja terdokumentasi terstruktur sejak awal periode.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Pengguna mengisi nama kegiatan, tujuan, target sasaran peserta, keterangan peserta, lokasi, dan tanggal rencana pelaksanaan, *When* Disimpan, *Then* Baris kegiatan terbentuk di tabel `kegiatan` dengan status default `rencana`.
- [ ] AC-2: *Given* Pengguna mengosongkan kolom anggaran, *When* Disimpan, *Then* Sistem mengizinkan (pada MVP kolom `anggaran` bersifat opsional/nullable dan tidak divalidasi).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `kegiatan`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``kegiatan:create`, `kegiatan:update` (scope unit bagi PIC).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-06.02 · [Feature] Klaim Keterkaitan Kegiatan Terhadap Rencana Aksi & Komponen

**Terkait**: `US-06.02`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC) atau Tim Perencanaan,
> **Saya ingin** mengklaim bahwa suatu kegiatan mendukung rencana aksi indikator tertentu dan berdampak pada komponen tertentu,
> **Sehingga** rekapitulasi capaian indikator secara otomatis menampilkan narasi kegiatan dan justifikasi pergerakan angka komponen.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* PIC memilih kegiatan K, memilih rencana aksi RA, memilih komponen C (opsional), menentukan arah dampak (`menambah` / `mengurangi`), dan mengisi catatan, *When* Klaim disimpan, *Then* Baris baru terbentuk di tabel `klaim_kegiatan`.
- [ ] AC-2: *Given* Unit pemilik kegiatan K berbeda dengan unit pemilik rencana aksi RA, *When* Klaim diajukan, *Then* Sistem menolak klaim lintas unit.
- [ ] AC-3: *Given* PIC mencoba mengklaim kegiatan yang sama pada rencana aksi dan komponen yang sama berulang kali, *When* Disimpan, *Then* Sistem menolak duplikasi (constraint unik dengan penanganan sentinel COALESCE).
- [ ] AC-4: *Given* Klaim berhasil disimpan, *When* Sistem memproses nilai komponen, *Then* **Nilai komponen TIDAK berubah otomatis** (klaim murni dokumentasi dukungan untuk mencegah *double counting*).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `klaim_kegiatan`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``kegiatan:create/update`, `rencana_aksi:update`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-06.03 · [Feature] Pelaksanaan Kegiatan & Gerbang Kelengkapan Bukti SPJ

**Terkait**: `US-06.03`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja),
> **Saya ingin** memperbarui status kegiatan menjadi "terlaksana", mengisi realisasi peserta dan narasi pelaksanaan, serta melampirkan bukti pertanggungjawaban fisik (SPJ),
> **Sehingga** kegiatan diakui secara sah telah berkontribusi pada pencapaian kinerja organisasi.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* PIC mengisi tanggal realisasi, jumlah peserta riil, narasi pelaksanaan, kendala, dan strategi tindak lanjut, *When* Memilih status `terlaksana`, *Then* Sistem mengevaluasi gerbang bukti dukung tahap `kegiatan`.
- [ ] AC-2: *Given* Terdapat persyaratan bukti dukung wajib bertahap kegiatan yang belum dipenuhi (mis. daftar hadir atau laporan kegiatan), *When* PIC submit status `terlaksana`, *Then* Sistem **MENOLAK** perubahan status.
- [ ] AC-3: *Given* Seluruh bukti dukung wajib bertahap kegiatan telah lengkap terunggah/terisi, *When* Status disubmit, *Then* Status kegiatan berubah menjadi `terlaksana`, dan seluruh berkas yang menempel padanya menjadi imutabel (tidak dapat dihapus lagi).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``kegiatan:update`, `berkas:upload`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-06.04 · [Feature] Penanganan Kegiatan Tidak Terlaksana, Ditunda, atau Dibatalkan

**Terkait**: `US-06.04`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja),
> **Saya ingin** mencatat bahwa suatu kegiatan tidak terlaksana, ditunda, atau dibatalkan beserta alasan justifikasinya tanpa menghapus data kegiatan,
> **Sehingga** kegagalan atau penundaan kegiatan tetap terekam sebagai bahan audit dan analisis kendala pada evaluasi triwulanan.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Suatu kegiatan batal dilaksanakan, *When* PIC mengubah status menjadi `tidak_terlaksana`, `ditunda`, atau `batal`, *Then* Sistem **MEWAJIBKAN** pengisian kolom `justifikasi`.
- [ ] AC-2: *Given* PIC mencoba mengubah status tersebut tanpa mengisi justifikasi, *When* Disubmit, *Then* Sistem menolak perubahan status.
- [ ] AC-3: *Given* Kegiatan diubah statusnya menjadi tidak terlaksana/batal/ditunda, *When* Sistem mengevaluasi bukti dukung, *Then* Sistem **TIDAK MENUNTUT** bukti dukung SPJ pelaksanaan.
- [ ] AC-4: *Given* Kegiatan yang tidak terlaksana telah diklaim pada rencana aksi, *When* Rekapitulasi laporan dibuat, *Then* Kegiatan tetap tampil pada rekapitulasi sebagai penjelasan mengapa komponen tidak bergerak.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``kegiatan:update`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-06.05 · [Feature] Pergeseran Kegiatan ke Periode Triwulan Berikutnya (*Roll-Over*)

**Terkait**: `US-06.05`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja),
> **Saya ingin** menggeser kegiatan yang ditunda ke periode triwulan berikutnya dengan tetap mempertahankan keterkaitan ke kegiatan asal,
> **Sehingga** kesinambungan rencana kerja terpelihara dan jejak penundaan antar-triwulan dapat ditelusuri.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* PIC memilih aksi "Geser ke Triwulan Berikutnya", *When* Memilih periode triwulan tujuan, *Then* Sistem membuat baris baru di tabel `kegiatan` pada periode tujuan dengan kolom `kegiatan_asal_id` menunjuk ke ID kegiatan asal.
- [ ] AC-2: *Given* Kegiatan baru terbentuk, *When* Diperiksa di daftar kegiatan, *Then* Kegiatan asal di periode lama tetap berstatus `ditunda` (tidak dihapus), dan kegiatan baru di periode tujuan berstatus `rencana`.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Baris baru tabel `kegiatan` (`kegiatan_asal_id` terisi), `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``kegiatan:create`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
## Bagian 7 — Pengisian Pengukuran Realisasi Kinerja Triwulanan

### ISS-07.01 · [Feature] Pengisian Nilai Realisasi Komponen Pengukuran Triwulan

**Terkait**: `US-07.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja),
> **Saya ingin** menginput nilai realisasi mentah per komponen pada triwulan berjalan,
> **Sehingga** sistem dapat menghitung nilai capaian indikator secara presisi berdasarkan formula perhitungan yang telah dibekukan di snapshot.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* PIC membuka form pengukuran untuk indikator miliknya pada triwulan aktif, *When* Mengisi angka realisasi pada setiap komponen aktif, *Then* Sistem menyimpan baris `pengukuran` berstatus `draft` dan baris `pengukuran_komponen`.
- [ ] AC-2: *Given* Indikator bertipe `rasio_persen` atau `penjumlahan`, *When* Komponen diisi, *Then* Sistem menghitung nilai indikator secara otomatis (*server-side calculation*) dan mengunci kolom nilai indikator (*read-only* dari sisi klien).
- [ ] AC-3: *Given* Pada indikator `rasio_persen`, nilai komponen penyebut bernilai `0`, *When* Dihitung, *Then* Sistem tidak memicu error matematika (division by zero), melainkan menyimpan nilai `null` dan menampilkan status **"tidak dapat dihitung"**.
- [ ] AC-4: *Given* Indikator bertipe `manual`, *When* PIC mengisi, *Then* Angka realisasi diketik langsung ke kolom `pengukuran.nilai`.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `pengukuran`, `pengukuran_komponen`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``pengukuran:create`, `pengukuran:update` (scope unit bagi PIC).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-07.02 · [Feature] Pemenuhan Bukti Dukung Wajib Tahap Pengukuran

**Terkait**: `US-07.02`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja),
> **Saya ingin** melampirkan berkas bukti fisik, tautan sumber data eksternal, atau narasi teks pendukung atas angka realisasi yang saya laporkan,
> **Sehingga** angka capaian kinerja triwulanan memiliki akuntabilitas dan bukti dukung yang dapat diverifikasi oleh auditor.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Terdapat persyaratan jenis berkas bertahap `pengukuran` yang aktif untuk indikator tersebut, *When* PIC mengirim berkas sesuai mode yang diizinkan (file/tautan/teks), *Then* Terbentuk baris polimorfik `berkas` dengan `berkasable_type = pengukuran`.
- [ ] AC-2: *Given* Persyaratan menetapkan `semua_mode_wajib = true` untuk mode file dan tautan, *When* PIC baru mengunggah file tanpa mengisi tautan, *Then* Persyaratan tersebut berstatus belum terpenuhi.
- [ ] AC-3: *Given* Saklar unggahan file dimatikan pada setelan aplikasi (`berkas.unggahan_aktif = false`) sementara persyaratan hanya mengizinkan file, *When* Sistem mengevaluasi pemenuhan, *Then* Persyaratan secara otomatis ditandai **`tidak_dapat_dipenuhi`** dan tidak memblokir alur pengajuan.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `berkas`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``berkas:upload`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-07.03 · [Feature] Pengajuan Pengukuran Triwulanan & Tiga Gerbang Kelengkapan

**Terkait**: `US-07.03`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja),
> **Saya ingin** mengajukan data pengukuran triwulan kepada Tim Perencanaan sebelum batas akhir pengisian berakhir,
> **Sehingga** data capaian unit saya dapat diverifikasi dan disahkan dalam laporan kinerja instansi.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* PIC menekan tombol "Ajukan Pengukuran", *When* Sistem mengevaluasi **Tiga Gerbang Kelengkapan Pengukuran**: 1. Gerbang 1: Rencana aksi untuk kombinasi (indikator × tahun) harus sudah berstatus **`disahkan`**. 2. Gerbang 2: Seluruh komponen aktif pada indikator tersebut harus telah memiliki nilai (tidak boleh `null`). 3. Gerbang 3: Seluruh persyaratan bukti dukung wajib bertahap pengukuran harus telah terpenuhi (kecuali ditandai `tidak_dapat_dipenuhi`).
- [ ] AC-2: *Then* Jika salah satu gerbang gagal, pengajuan **DITOLAK** dan sistem menampilkan rincian kekurangan yang harus dilengkapi.
- [ ] AC-3: *Given* Nilai capaian periode ini memburuk dibandingkan pengukuran disahkan terakhir atau indikator bertanda `wajib_catatan = true`, *When* PIC mengajukan, *Then* Sistem **MEWAJIBKAN** pengisian kolom `catatan`.
- [ ] AC-4: *Given* Seluruh validasi gerbang lolos, *When* Pengajuan sukses, *Then* Status pengukuran berubah menjadi `diajukan`, versi bertambah, dan notifikasi in-app masuk ke antrean tugas Tim Perencanaan.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``pengukuran:update` (scope unit).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-07.04 · [Feature] Pengisian Pengukuran oleh Tim Perencanaan (Pengecualian Batas Waktu)

**Terkait**: `US-07.04`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** membuat atau mengubah data pengukuran atas nama unit kerja yang telah melewati batas waktu pengisian,
> **Sehingga** pelaporan kinerja instansi tidak terhambat oleh keterlambatan penginputan PIC unit kerja.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Tanggal hari ini telah melewati `pengisian_selesai` pada `jadwal_periode`, *When* PIC mencoba menginput/mengajukan, *Then* Sistem menolak akses PIC karena batas waktu bersifat mutlak.
- [ ] AC-2: *Given* Tanggal hari ini telah melewati `pengisian_selesai`, *When* Tim Perencanaan menginput dan mengajukan pengukuran atas nama unit tersebut, *Then* Sistem mengizinkan aksi karena permission Perencanaan bersifat global dan bebas batas jendela periode.
- [ ] AC-3: *Given* Tim Perencanaan mengisi pengukuran, *When* Menekan ajukan, *Then* Sistem **TETAP MENEGAKKAN TIGA GERBANG KELENGKAPAN** (pengecualian Perencanaan hanya pada waktu, bukan pada kelengkapan data).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `pengukuran`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``pengukuran:create`, `pengukuran:update` (permission global peran Perencanaan).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
## Bagian 8 — Reviu, Verifikasi, dan Pengesahan Pengukuran

### ISS-08.01 · [Feature] Verifikasi & Pengembalian Berkas Pengukuran

**Terkait**: `US-08.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** memeriksa data realisasi komponen dan keabsahan bukti dukung yang diajukan PIC, lalu memverifikasi atau mengembalikannya jika perlu perbaikan,
> **Sehingga** data kinerja yang dilaporkan akurat dan dapat dipertanggungjawabkan secara hukum.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan menilai angka dan bukti dukung telah sesuai, *When* Menekan tombol "Verifikasi", *Then* Status pengukuran berubah menjadi `diverifikasi`.
- [ ] AC-2: *Given* Perencanaan menemukan angka perhitungan keliru atau berkas bukti tidak sah, *When* Menekan tombol "Kembalikan" dan mengisi alasan catatan perbaikan wajib, *Then* Status berubah menjadi `dikembalikan`, dan notifikasi in-app instan dikirim ke PIC terkait.
- [ ] AC-3: *Given* Pengukuran yang telah berstatus `diverifikasi` belakangan ditemukan memiliki kekurangan sebelum disahkan, *When* Perencanaan menekan tombol kembalikan beralasan, *Then* Status dapat kembali menjadi `dikembalikan`.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``pengukuran:verifikasi`, `pengukuran:kembalikan`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-08.02 · [Feature] Pengesahan Pengukuran & Penegakan Pemisahan Tugas (F1/F2)

**Terkait**: `US-08.02`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** mengesahkan pengukuran yang telah berstatus diverifikasi,
> **Sehingga** nilai realisasi triwulan tersebut menjadi angka capaian resmi organisasi yang siap dipublikasikan pada dashboard dan laporan.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Aktor yang hendak mengesahkan adalah pengguna yang sama dengan pembuat pengajuan (`pengukuran.created_by`) melalui jalur izin unit PIC, *When* Transaksi pengesahan dicoba, *Then* Sistem menolak transaksi sesuai **Aturan F1 Pemisahan Tugas** (*Segregation of Duties*).
- [ ] AC-2: *Given* Aktor pengesahan adalah staf Perencanaan dan pengukuran diajukan oleh PIC unit kerja, *When* Pengesahan disetujui, *Then* Status berubah menjadi `disahkan`, dan berkas bukti dukung yang menempel padanya terkunci permanen (imutabel).
- [ ] AC-3: *Given* Pengukuran diisi sendiri oleh Tim Perencanaan atas nama unit yang terlambat, *When* Anggota Perencanaan tersebut melakukan pengesahan, *Then* Sistem mengizinkan sesuai **Aturan F2**, mencatat penanda `self_approval = true` pada `audit_log`, dan menampilkan indikator self-approval pada laporan internal.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``pengukuran:sahkan` (permission bertipe sensitif).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-08.03 · [Feature] Buka-Kembali Pengukuran yang Disahkan

**Terkait**: `US-08.03`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan atau Superadmin,
> **Saya ingin** membuka kembali pengukuran yang telah berstatus disahkan menjadi berstatus dikembalikan,
> **Sehingga** kesalahan fatal yang teridentifikasi pasca-rapat evaluasi dapat diperbaiki secara sah dan terkontrol.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan memasukkan alasan pembukaan kembali yang komprehensif, *When* Tombol buka kembali dieksekusi, *Then* Status pengukuran berubah kembali menjadi `dikembalikan`, dan status capaian terkait dinonaktifkan/direset.
- [ ] AC-2: *Given* Jadwal tahunan telah berstatus ditutup, *When* Buka kembali pengukuran dicoba, *Then* Sistem menolak aksi secara mutlak (koreksi pasca-penutupan hanya lewat pembukaan kembali jadwal tahunan).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``pengukuran:buka_kembali` (permission sensitif).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
## Bagian 9 — Rekomendasi Pimpinan & Penilaian Status Capaian

### ISS-09.01 · [Feature] Penetapan Catatan Rekomendasi Pimpinan oleh Tim Perencanaan

**Terkait**: `US-09.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** menginput catatan arahan dan Rekomendasi Pimpinan untuk indikator tertentu pada triwulan berjalan,
> **Sehingga** arahan tindak lanjut pimpinan hasil rapat evaluasi triwulanan terdokumentasi dan dapat ditindaklanjuti oleh unit kerja.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan menginput teks arahan rekomendasi untuk indikator X pada triwulan T, *When* Disimpan, *Then* Baris baru terbentuk di tabel `rekomendasi_pimpinan` dengan `ditetapkan_oleh = auth()->id()`.
- [ ] AC-2: *Given* Pengukuran periode T untuk indikator X belum berstatus `disahkan`, *When* Rekomendasi diinput, *Then* Sistem mengizinkan penyimpanan (rekomendasi independen dari status pengukuran, dapat diisi segera setelah rapat evaluasi triwulan).
- [ ] AC-3: *Given* Terdapat rekomendasi lama pada indikator dan triwulan yang sama, *When* Rekomendasi baru diinput, *Then* Baris baru disimpan sebagai riwayat aktif terkini (soft replace, tidak menimpa data lama).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `rekomendasi_pimpinan`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``rekomendasi:tetapkan` (permission sensitif).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-09.02 · [Feature] Penetapan Status Capaian Akhir Indikator

**Terkait**: `US-09.02`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan atau Superadmin,
> **Saya ingin** menetapkan status penilaian akhir indikator ("Tercapai" atau "Belum Tercapai") secara manual atas pengukuran yang telah disahkan,
> **Sehingga** status keberhasilan kinerja instansi memiliki ketetapan formal dari tim evaluator.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Pengukuran telah berstatus `disahkan`, *When* Perencanaan memilih status `tercapai` atau `belum_tercapai`, *Then* Baris baru terbentuk di tabel `status_capaian` dengan `sumber = manual` dan `ditetapkan_oleh = auth()->id()`.
- [ ] AC-2: *Given* Pengukuran masih berstatus `draft`, `diajukan`, atau `diverifikasi`, *When* Penetapan status dicoba, *Then* Sistem menolak aksi.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `status_capaian`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``status_capaian:update` (permission sensitif).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
## Bagian 10 — Pemantauan Dashboard Eksekutif & Laporan Kinerja

### ISS-10.01 · [Feature] Pemantauan Kinerja Eksekutif pada Dashboard

**Terkait**: `US-10.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Pimpinan (Kepala LLDIKTI / Tim Eksekutif),
> **Saya ingin** memantau visualisasi capaian IKU, progres rencana aksi, status kegiatan unit, dan rekapitulasi triwulanan secara interaktif pada Dashboard,
> **Sehingga** saya dapat mengambil keputusan manajerial berbasis data kinerja yang mutakhir dan akurat.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Pimpinan membuka dashboard, *When* Halaman dimuat, *Then* Sistem menampilkan kartu metrik capaian total IKU, grafik tren capaian per triwulan (ApexCharts), panel progres Rencana Aksi, dan panel ringkasan status Kegiatan (terlaksana vs tidak terlaksana).
- [ ] AC-2: *Given* Status capaian ditampilkan, *When* Sistem membaca data, *Then* Sistem menghitung status per kombinasi indikator × periode triwulan yang diharapkan (bukan agregasi tahunan semu).
- [ ] AC-3: *Given* Indikator berstatus `arsip`, *When* Dashboard menghitung kewajiban pengisian, *Then* Indikator arsip tidak dihitung sebagai beban pengisian.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Verifikasi skema / relasi eksisting
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``dashboard:read`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-10.02 · [Feature] Penyajian Matriks Rekapitulasi Laporan Indikator × Periode

**Terkait**: `US-10.02`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan atau Pimpinan,
> **Saya ingin** melihat laporan matriks lengkap per indikator × periode yang memuat baseline, target kumulatif, nilai realisasi komponen, narasi progres kegiatan, rekomendasi pimpinan, dan status bukti dukung,
> **Sehingga** seluruh riwayat akuntabilitas tersaji dalam satu dokumen rekapitulasi komprehensif yang setara dengan format kerja dinas.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Pengguna membuka halaman Rekapitulasi Laporan, *When* Memilih tahun dan triwulan, *Then* Sistem merender tabel dengan struktur:
- [ ] AC-2: Kolom identitas: Sasaran, Indikator, Satuan, Unit Pemilik, PIC.
- [ ] AC-3: Kolom target: Baseline, Target Tahunan PK, Target Triwulan Kumulatif.
- [ ] AC-4: Kolom realisasi: Rincian Angka Riil per Komponen, Nilai Akhir Indikator, Persentase Capaian.
- [ ] AC-5: Kolom narasi: Gabungan otomatis uraian pelaksanaan, kendala, dan strategi tindak lanjut dari seluruh kegiatan yang diklaim pada indikator tersebut.
- [ ] AC-6: Kolom evaluasi: Catatan Rekomendasi Pimpinan dan Status Capaian.
- [ ] AC-7: Kolom bukti: Status kelengkapan berkas fisik beserta penanda `tidak_dapat_dipenuhi` jika ada.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Verifikasi skema / relasi eksisting
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``laporan:read`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-10.03 · [Feature] Ekspor Laporan Kinerja ke Format Excel

**Terkait**: `US-10.03`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan atau Pimpinan,
> **Saya ingin** mengekspor matriks rekapitulasi kinerja ke file format Microsoft Excel (`.xlsx`),
> **Sehingga** laporan dapat dilampirkan dalam pelaporan formal kementerian dan Laporan Kinerja Instansi Pemerintah (LKjIP).

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Pengguna menekan tombol "Ekspor Excel", *When* Sistem memproses data, *Then* Terbentuk file Excel resmi yang memisahkan baseline, target kumulatif, realisasi komponen, narasi kegiatan, dan rekomendasi secara rapi tanpa merusak tata letak sel.
- [ ] AC-2: *Given* Pengguna peran Admin mencoba menekan URL ekspor laporan, *When* Request dikirim ke backend, *Then* Sistem mengembalikan 403 Forbidden (Admin tidak memiliki hak `laporan:ekspor`).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``laporan:ekspor`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
## Bagian 11 — Pengelolaan Bukti Dukung Multi-Mode & Kebijakan Storage

### ISS-11.01 · [Feature] Konfigurasi Persyaratan Jenis Berkas Bergerbang

**Terkait**: `US-11.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** menetapkan katalog persyaratan bukti dukung (`jenis_berkas`) per tahapan (rencana aksi, kegiatan, pengukuran), menentukan mode yang diizinkan (file, tautan, teks), sifat wajib, dan aturan `semua_mode_wajib`,
> **Sehingga** standar kepatuhan bukti dukung instansi terdefinisi jelas bagi seluruh unit kerja.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan membuat jenis berkas baru, memilih tahap, mencentang mode yang diizinkan, menentukan status `wajib`, dan menyetel batasan format/ukuran, *When* Disimpan, *Then* Data tersimpan pada tabel `jenis_berkas`.
- [ ] AC-2: *Given* Perencanaan mencoba menyimpan jenis berkas tanpa mencentang satu pun mode yang diizinkan (ketiga opsi false), *When* Validasi dijalankan, *Then* Sistem menolak penyimpanan.
- [ ] AC-3: *Given* `semua_mode_wajib` disetel `true`, *When* Pengguna mengisi bukti dukung nantinya, *Then* Sistem mewajibkan seluruh mode yang diizinkan pada persyaratan tersebut terpenuhi.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `jenis_berkas`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``jenis_berkas:create`, `jenis_berkas:update`, `jenis_berkas:delete`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-11.02 · [Feature] Pengunggahan File Bukti Fisik Secara Aman (Streamed Download)

**Terkait**: `US-11.02`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Pengguna (PIC / Perencanaan),
> **Saya ingin** mengunggah file bukti fisik (PDF, dokumen, gambar) yang tersimpan secara privat di server,
> **Sehingga** dokumen rahasia instansi aman dari akses publik terbuka dan hanya dapat diunduh oleh pengguna berhak.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Pengguna mengunggah berkas PDF 5MB, *When* Diproses server, *Then* File fisik disimpan pada path privat `storage/app/berkas/...` (bukan public storage), dan metadata dicatat di tabel `berkas` (`mode = file`, nama asli, mime, ukuran bytes).
- [ ] AC-2: *Given* File melebihi batas `ukuran_maks_kb` atau format tidak sesuai `format_diizinkan`, *When* Diunggah, *Then* Sistem menolak file sebelum disimpan ke disk.
- [ ] AC-3: *Given* Seseorang mencoba mengakses URL path fisik berkas secara langsung di browser tanpa login, *When* Request diterima web server, *Then* Akses ditolak (berkas hanya dapat diunduh melalui streamed response route yang dilindungi middleware permission `berkas:read`).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Disk privat, tabel `berkas`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``berkas:upload`, `berkas:read`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-11.03 · [Feature] Penyerahan Bukti Berupa Tautan Eksternal atau Keterangan Teks

**Terkait**: `US-11.03`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Pengguna (PIC / Perencanaan),
> **Saya ingin** menyertakan bukti dukung berupa tautan resmi (Google Drive/Cloud Kemendikbud) atau menuliskan teks penjelasan langsung,
> **Sehingga** pemenuhan bukti dukung dapat terlaksana secara fleksibel tanpa membebani kuota penyimpanan VPS institusi.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Pengguna memilih mode `tautan` dan memasukkan URL valid berskema `http://` atau `https://`, *When* Disimpan, *Then* URL tersimpan pada kolom `berkas.tautan` tanpa memakai kuota disk fisik.
- [ ] AC-2: *Given* Pengguna memilih mode `teks` dan mengetikkan keterangan narasi atau nomor surat keputusan, *When* Disimpan, *Then* Keterangan tersimpan pada `berkas.isi_teks`, dan audit log hanya mencatat panjang karakter teks (bukan menyalin seluruh teks).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `berkas`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``berkas:upload`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-11.04 · [Feature] Penegakan Imutabilitas Berkas per Induk

**Terkait**: `US-11.04`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Sistem Integritas SAKIP,
> **Saya ingin** mengunci berkas bukti dukung secara permanen begitu status induknya mencapai batas legal akhir,
> **Sehingga** tidak ada pihak yang dapat mengubah, memanipulasi, atau menghapus bukti pertanggungjawaban yang telah disahkan.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Berkas menginduk ke `rencana_aksi` atau `pengukuran`, *When* Induk telah berstatus `disahkan`, *Then* Percobaan penghapusan berkas **DITOLAK KERAS**.
- [ ] AC-2: *Given* Berkas menginduk ke `kegiatan`, *When* Kegiatan telah berstatus `terlaksana`, *Then* Percobaan penghapusan berkas **DITOLAK KERAS**.
- [ ] AC-3: *Given* Berkas menginduk ke `renstra`, *When* Renstra telah berstatus `aktif`, *Then* Berkas naskah Renstra tidak dapat dihapus.
- [ ] AC-4: *Given* Berkas menginduk ke `renstra_pk`, *When* Jadwal tahunan tahun tersebut telah `aktif`, *Then* Berkas naskah PK tidak dapat dihapus.
- [ ] AC-5: *Given* Berkas menginduk ke `regulasi`, *When* Regulasi tersebut masih dirujuk oleh Renstra atau Indikator aktif, *Then* Berkas produk hukum tidak dapat dihapus.
- [ ] AC-6: *Given* Batas imutabilitas belum tercapai, *When* Pemilik berkas atau Perencanaan menghapus berkas, *Then* Penghapusan dilakukan secara *soft delete* (`dihapus_pada`, `dihapus_oleh`) dan tercatat lengkap di `audit_log`.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``berkas:delete` (permission sensitif).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
## Bagian 12 — Alert Kontekstual & Notifikasi (In-App & Eksternal)

### ISS-12.01 · [Feature] Banner Pengingat Kontekstual di Dashboard (Baseline Scope MVP)

**Terkait**: `US-12.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja),
> **Saya ingin** melihat banner peringatan tenggat waktu dan status kewajiban pengisian langsung di halaman kerja saya,
> **Sehingga** saya selalu terinformasi mengenai sisa waktu pengisian tanpa khawatir terlewat batas waktu.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Jendela penyusunan Rencana Aksi sedang aktif, *When* PIC membuka aplikasi, *Then* Tampil banner kontekstual yang menginformasikan batas akhir `rencana_aksi_selesai`.
- [ ] AC-2: *Given* Periode pengisian triwulan sedang aktif, *When* PIC membuka dashboard, *Then* Tampil banner hitung mundur (*countdown*) hari tersisa menuju penutupan pengisian (`pengisian_selesai`).
- [ ] AC-3: *Given* Unit kerja PIC memiliki indikator yang belum diisi nilainya pada triwulan aktif, *When* Dashboard dimuat, *Then* Tampil kartu daftar indikator dengan badge peringatan "Belum Mengisi".
- [ ] AC-4: *Given* Seluruh evaluasi alert ini berjalan, *When* Data dikirim ke klien, *Then* Seluruh logika dihitung di sisi server dan dikirimkan via shared props Inertia (komponen React tidak menghitung sendiri).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Verifikasi skema / relasi eksisting
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk `Diwarisi dari akses dashboard/halaman kerja.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-12.02 · [Feature] Indikator Lonceng Notifikasi & Antrean Tugas (Baseline Scope MVP)

**Terkait**: `US-12.02`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Pengguna (PIC / Perencanaan),
> **Saya ingin** melihat lonceng notifikasi pada navbar header dengan counter badge angka yang belum dibaca (*unread count*),
> **Sehingga** saya mengetahui secara langsung peristiwa penting yang memerlukan tindakan perbaikan atau verifikasi dari saya.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan mengembalikan pengukuran atau rencana aksi milik PIC, *When* PIC membuka aplikasi, *Then* Badge lonceng notifikasi bertambah angka, dan saat diklik menampilkan cuplikan alasan pengembalian serta tautan langsung ke halaman perbaikan.
- [ ] AC-2: *Given* PIC mengajukan rencana aksi atau pengukuran baru, *When* Tim Perencanaan membuka aplikasi, *Then* Lonceng notifikasi Perencanaan menampilkan item tugas baru yang siap diverifikasi.
- [ ] AC-3: *Given* Pengguna mengklik salah satu notifikasi, *When* Halaman target terbuka, *Then* Status notifikasi ditandai telah dibaca (*read*), dan counter badge berkurang.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Verifikasi skema / relasi eksisting
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk `Seluruh pengguna terautentikasi.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-12.03 · [Feature] Siaran Broadcast Pembukaan Jadwal Pengisian (Target Sebelum 9 Nov)

**Terkait**: `US-12.03`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja),
> **Saya ingin** menerima notifikasi siaran pembukaan jadwal melalui WhatsApp dan Email saat triwulan pengisian resmi dibuka,
> **Sehingga** saya dapat segera merencanakan penginputan data kinerja meskipun sedang berada di luar kantor.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Jadwal periode triwulan resmi dibuka oleh Perencanaan (`pengisian_mulai`), *When* Pemicu aktivasi berjalan, *Then* Sistem mendispatch queued background job untuk mengirimkan pesan pengumuman pembukaan pengisian ke nomor WhatsApp dan email seluruh PIC unit kerja terkait.
- [ ] AC-2: *Given* Gateway eksternal mengalami kendala koneksi, *When* Pengiriman gagal, *Then* Sistem mencatat error di log dan tidak menggagalkan transaksi pembukaan jadwal di basis data.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Verifikasi skema / relasi eksisting
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk `Dieksekusi otomatis oleh sistem/scheduler.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-12.04 · [Feature] Early Warning System (EWS) Harian Menjelang Tenggat Pengisian (Target Sebelum 9 Nov)

**Terkait**: `US-12.04`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja),
> **Saya ingin** menerima pesan pengingat berkala ke WhatsApp dan Email pada H-7, H-3, dan H-1 menjelang penutupan pengisian,
> **Sehingga** saya tidak terlambat menyelesaikan pengisian pengukuran kinerja unit saya.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Scheduler cron SAKIP berjalan harian pada pukul 08.00 pagi, *When* Hari ini tepat berada di rentang H-7, H-3, atau H-1 batas `pengisian_selesai`, *Then* Sistem mendata seluruh PIC yang indikatornya masih berstatus "Draft" atau "Belum Mengisi", lalu mengirimkan pesan pengingat WhatsApp dan Email otomatis.
- [ ] AC-2: *Given* PIC telah berhasil mengajukan pengukuran sebelum H-3, *When* Scheduler H-3 berjalan, *Then* Sistem mengecualikan PIC tersebut dari daftar penerima pengingat.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Verifikasi skema / relasi eksisting
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk `Eksekusi cron job sistem.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-12.05 · [Feature] Notifikasi Rekapitulasi untuk Tim Perencanaan (Target Sebelum 9 Nov)

**Terkait**: `US-12.05`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan,
> **Saya ingin** menerima rekapitulasi progres pengisian ke WhatsApp dan Email pada H-3 dan H-1 batas pengisian,
> **Sehingga** Tim Perencanaan dapat proaktif melakukan koordinasi atau peneguran terhadap unit kerja yang belum mengisi menjelang batas akhir.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Waktu mencapai H-3 dan H-1 penutupan triwulan, *When* Scheduled job berjalan, *Then* Sistem mengirimkan ringkasan persentase kepatuhan (daftar unit sudah submit vs belum submit) ke nomor WhatsApp dan email anggota Tim Perencanaan.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Verifikasi skema / relasi eksisting
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk `Eksekusi cron job sistem.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-12.06 · [Feature] Notifikasi Instan Pengembalian Berkas ke WhatsApp PIC (Target Sebelum 9 Nov)

**Terkait**: `US-12.06`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Penanggung Jawab (PIC Unit Kerja),
> **Saya ingin** menerima pemberitahuan instan via WhatsApp saat pengajuan saya dikembalikan oleh Perencanaan,
> **Sehingga** saya dapat segera membaca catatan alasan penolakan dan segera memperbaikinya sebelum tenggat berakhir.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan menekan tombol "Kembalikan" pada verifikasi, *When* Transaksi commit berhasil di database, *Then* Sistem secara asinkron mendispatch notifikasi WhatsApp ke nomor telepon PIC yang bersangkutan, memuat ringkasan catatan perbaikan dan link langsung ke aplikasi.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Verifikasi skema / relasi eksisting
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk `Terpicu dari aksi `pengukuran:kembalikan` / `rencana_aksi:kembalikan`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
## Bagian 13 — Setelan Aplikasi, Kebijakan Operasional, dan Audit Trail

### ISS-13.01 · [Feature] Pengelolaan Setelan Identitas & Preferensi Tampilan Aplikasi

**Terkait**: `US-13.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Admin atau Superadmin,
> **Saya ingin** mengubah teks identitas instansi, label sebutan unit, zona waktu, format tanggal/angka, dan header/footer laporan pada tabel pengaturan,
> **Sehingga** preferensi presentasional aplikasi dapat diperbarui secara dinamis tanpa memerlukan deployment ulang kode program.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Admin membuka halaman Setelan Aplikasi, *When* Memperbarui nama instansi atau format tanggal, *Then* Nilai pada tabel `pengaturan` terupdate dan langsung berlaku pada tampilan pengguna lain melalui mekanisme caching.
- [ ] AC-2: *Given* Setiap perubahan setelan disimpan, *When* Transaksi selesai, *Then* Perubahan tercatat di `audit_log` dengan `nilai_lama` dan `nilai_baru`.
- [ ] AC-3: *Given* Pengguna selain Admin/Superadmin mencoba mengakses rute ini, *When* Endpoint dibuka, *Then* Sistem menolak akses (403 Forbidden).

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `pengaturan`, `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``pengaturan:update`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-13.02 · [Feature] Pengaturan Kebijakan Teknis Penyimpanan Berkas & Saklar Unggah

**Terkait**: `US-13.02`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Admin atau Superadmin,
> **Saya ingin** mengatur saklar aktivasi unggahan file (`berkas.unggahan_aktif`), batas ukuran default, format yang diizinkan, serta memantau panel kapasitas penyimpanan disk fisik,
> **Sehingga** konsumsi ruang penyimpanan pada VPS dapat dikendalikan secara operasional tanpa menghentikan proses pelaporan kinerja.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Kapasitas disk VPS mendekati batas maksimal, *When* Admin mengubah setelan `berkas.unggahan_aktif = false`, *Then* Sistem secara global menonaktifkan pengiriman bukti dukung bermode file, sementara mode tautan dan teks tetap berfungsi normal.
- [ ] AC-2: *Given* Halaman setelan dibuka, *When* Panel "Penggunaan Penyimpanan Bukti Dukung" dimuat, *Then* Sistem menampilkan metrik riil: jumlah berkas mode file, total ukuran bytes di disk, dan jumlah bukti mode tautan/teks.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Migration/Seeder untuk Tabel `pengaturan` (grup `berkas`), `audit_log`., Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``pengaturan:update`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-13.03 · [Feature] Penelusuran Rekam Jejak Audit Sistem (*Audit Trail*)

**Terkait**: `US-13.03`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Admin, Superadmin, atau Tim Perencanaan,
> **Saya ingin** menelusuri rekam jejak audit (*audit log*) seluruh peristiwa mutasi data, tindakan administratif, dan percobaan aksi yang ditolak sistem,
> **Sehingga** seluruh aktivitas akuntabilitas sistem dapat diaudit secara objektif, forensik, dan transparan.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Pengguna membuka layar Audit Log, *When* Melakukan pencarian berdasarkan rentang waktu, aktor, jenis tindakan, atau objek entitas, *Then* Sistem menyajikan riwayat log secara *append-only* (tidak ada tombol ubah/hapus log).
- [ ] AC-2: *Given* Suatu peristiwa melibatkan permission bertanda sensitif (mis. aktivasi jadwal, buka kembali, pengesahan), *When* Detail log dibuka, *Then* Log wajib menyajikan data JSON `dasar_izin` yang menjelaskan mengapa aktor tersebut diizinkan (peran mana atau grant mana yang berlaku).
- [ ] AC-3: *Given* Suatu aksi ditolak oleh sistem karena pelanggaran gerbang atau presedens deny, *When* Log diperiksa, *Then* Peristiwa penolakan tercatat jelas beserta alasannya.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Verifikasi skema / relasi eksisting
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``audit:read`.`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
## Bagian 14 — Penutupan Jadwal & Pembukaan Kembali (Koreksi Pasca-Penutupan)

### ISS-14.01 · [Feature] Penutupan Resmi Siklus Kinerja Tahunan (*Jadwal Penutupan*)

**Terkait**: `US-14.01`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan atau Superadmin,
> **Saya ingin** menutup secara resmi Jadwal Tahunan setelah seluruh tahapan evaluasi dan pelaporan tahunan tuntas,
> **Sehingga** seluruh data kinerja tahun tersebut terkunci permanen (*freeze mutlak*) dan tidak dapat dimutasi lagi oleh siapapun.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan mengeksekusi aksi penutupan jadwal, *When* Dikonfirmasi, *Then* Status `jadwal_tahunan` berubah menjadi `ditutup`, dan kolom `closed_at` terisi timestamp terkini.
- [ ] AC-2: *Given* Jadwal telah berstatus `ditutup`, *When* PIC maupun Tim Perencanaan mencoba membuat atau mengubah Rencana Aksi, Kegiatan, Klaim, Berkas, atau Pengukuran pada tahun tersebut, *Then* Seluruh permintaan **DITOLAK KERAS** oleh sistem.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``jadwal:tutup` (permission sensitif).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---
### ISS-14.02 · [Feature] Pembukaan Kembali Jadwal Tahunan Pasca-Penutupan (`jadwal:buka_kembali`)

**Terkait**: `US-14.02`
**Assignee**: `[ ] Backend` | `[ ] Frontend`

**Deskripsi User Story**:
> **Sebagai** Tim Perencanaan atau Superadmin,
> **Saya ingin** membuka kembali Jadwal Tahunan yang telah berstatus ditutup untuk keperluan perbaikan resmi pasca-audit,
> **Sehingga** koreksi data historis dapat dilakukan secara legal, terkendali, berbatas waktu, dan tercatat penuh dalam riwayat audit.

**Acceptance Criteria (QA)**:
- [ ] AC-1: *Given* Perencanaan memasukkan alasan pembukaan kembali, memilih unit yang diizinkan melakukan koreksi, serta menetapkan tanggal batas akhir sesi koreksi baru, *When* Aksi dieksekusi, *Then* Status `jadwal_tahunan` kembali menjadi `aktif`, tanggal penutupan asli tetap dipertahankan, dan peristiwa tercatat di `audit_log`.
- [ ] AC-2: *Given* Sesi koreksi aktif, *When* PIC unit terkait melakukan perbaikan, *Then* Perbaikan hanya diizinkan dalam rentang waktu sesi koreksi yang telah ditetapkan.
- [ ] AC-3: *Given* Sesi koreksi telah berakhir, *When* Perencanaan menutup kembali jadwal, *Then* Status kembali menjadi `ditutup`.

**Technical Tasks (Sub-Tasks)**:
- [ ] **Database**: Pastikan trait/observer Audit Trail terpasang pada entitas
- [ ] **Backend**: Terapkan middleware/policy RBAC untuk ``jadwal:buka_kembali` (permission sensitif).`
- [ ] **Frontend**: Buat/update komponen React & page Inertia, Integrasikan form & state management, Pastikan penanganan error (Flash messages) berjalan baik

---