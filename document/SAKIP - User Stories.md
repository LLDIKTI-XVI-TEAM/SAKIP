# USER STORIES — SAKIP LLDIKTI WILAYAH XVI

> **Klarifikasi final LLDIKTI Wilayah XVI — 24 September 2026**  
> Bagian ini adalah kontrak terbaru dan **menggantikan keputusan Q31 atau teks lama yang bertentangan**. Role bawaan SAKIP berjumlah **lima**: `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`. **PIC bukan role sistem**; PIC adalah konteks operasional yang dibentuk oleh penugasan dan grant unit. Detail keputusan dicatat sebagai **Q32** pada dokumen Keputusan Penyelarasan.

**Status dokumen:** Revisi Q32 — 24 September 2026. Dokumen ini telah diselaraskan terhadap jawaban resmi LLDIKTI: 5 role, PIC bukan role, Grant Unit 7 permission, role-permission read-only, onboarding tanpa role, logout terpisah, Jadwal 2026, serta formula final IKU 3/8.

**Pembaruan 13 Oktober 2026 (Q38–Q51):** onboarding SSO langsung aktif tanpa peran (Q38, US-01.08), keputusan rapat asistensi Q39–Q46, dan keputusan analisis dokumen resmi 2026 Q47–Q51 diselaraskan. Story baru: US-02.10, US-02.11, US-02.12, US-05.07, US-06.08, US-10.05, US-13.05, dan US-13.06; US-11.06 dibatalkan Q44 dan dipertahankan sebagai histori, sedangkan fitur US-13.02 dihapus melalui US-13.06. Dokumen kini memuat **83 User Story**: 75 story sebelum Q39 (termasuk US-01.08) ditambah delapan story baru tersebut, dengan US-11.06 tetap dihitung sebagai story yang dibatalkan. Prioritas story yang tercakup urutan pengerjaan ditulis per tahap (Q46 §46.3). Story yang sudah dikerjakan diberi catatan perubahan perilaku tanpa mengubah AC yang sudah selesai; story yang belum dikerjakan diperbarui langsung dengan rujukan Q. Q38–Q51 adalah keputusan requirement, bukan bukti implementasi; konfirmasi tertulis Tim Perencanaan atasnya masih menunggu (pengantar Q39–Q46 dan Q47–Q51 pada Keputusan Penyelarasan).

## 1. Tujuan Dokumen
Dokumen ini mendefinisikan kebutuhan fungsional dan penerimaan (*acceptance*) SAKIP LLDIKTI Wilayah XVI dalam bentuk User Story yang dapat dipakai sebagai acuan UAT, refinement, pembuatan development issue, dan implementasi vertical slice.
### Hierarki sumber
- **PRD** menetapkan perilaku dan ruang lingkup produk.
- **Data Model** menetapkan struktur, constraint, versioning, provenance, dan integritas historis.
- **Workflow** menetapkan alur end-to-end dan transisi status.
- **Plan Pengembangan** menetapkan task, dependency, dan Definition of Done teknis.
- **Keputusan Penyelarasan** menetapkan keputusan terbaru yang mengoreksi/menegaskan baseline.
- **Design System** mengikat implementasi frontend React/Inertia.
Jika terdapat konflik, developer tidak boleh memilih interpretasi sendiri; gunakan keputusan penyelarasan terbaru lalu sinkronkan dokumen terdampak.
## 2. Aturan Global yang Mengikat Seluruh Story

- **Otorisasi server-side.** React hanya menerima `can.*`.
- **Fail closed.** Permission unknown/inactive dan user tanpa role ditolak.
- **Deny menang** atas allow role/grant.
- **Role final lima.** Tidak ada role `pic`.
- **PIC operasional** berarti user aktif yang berada dalam konteks pekerjaan, assignment bila relevan, Grant Unit yang sesuai, jendela/status valid, dan business guard lolos.
- **PJ tidak memberi permission.** Assignment dan grant adalah mekanisme terpisah.
- **Grant Unit** hanya 7 permission scoped dan memakai `delegasi:update`.
- **Role & Izin** read-only; preset role berubah melalui code/seeder/release.
- **Onboarding SSO** membuat user aktif tanpa role (Q38); Admin menetapkan role.
- **Logout lokal default**, logout SSO terpisah.
- **Initial year 2026 (Q46).** TW I–III periode lampau oleh Perencanaan; TW IV triwulan pertama workflow normal PIC operasional; tanpa `is_backfill`. Gerbang baru Q42–Q45 dikecualikan untuk periode lampau, sedangkan kunci per triwulan (Q41) bukan bagian pengecualian itu (Q46 §46.1 butir 1). Data awal 2026 diisi Perencanaan dari dokumen resmi 2026 (Q46 §46.1 butir 6). *(Semula Q32: TW I–II lampau, TW III–IV normal.)*
- **Dua lapis target (Q39, Q50).** Target Renstra per indikator per tahun hanya pembanding; target PK (`target_tahunan`) diinput Perencanaan di halaman PK dengan nilai penuh (Q50 §50.2), dibaca PJ/PIC sebagai baca-saja, dan menjadi acuan pengukuran melalui snapshot. Target PK di bawah target Renstra menurut `arah` memunculkan peringatan dan `alasan_deviasi_renstra` wajib; keduanya disimpan dengan `target:update` (Q39 §39.2–§39.3).
- **Rencana Aksi sepanjang tahun (Q41).** Tanpa jendela penyusunan RA; periode terkunci bagi PIC setelah `pengisian_selesai` periode tersebut; Perencanaan dikecualikan sampai `penutupan`.
- **Indikator dinilai tahunan dan periode tanpa target (Q47).** Indikator dinilai tahunan boleh tanpa target pada periode sebelum periode nilai akhir, sedangkan indikator lain tetap wajib bertarget di setiap periode kecuali periode lampau (Q46 §46.1 butir 6, Q47 §47.2 butir 4); target kosong berarti tidak ditargetkan dan berbeda dari `0`. Pada periode tanpa target, capaian persen dan status capaian tidak dihitung dan tampilan memuat selisih terhadap target PK. Realisasi yang belum tersedia dicatat `status_perhitungan = belum_dapat_diukur`.
- **Kegiatan dalam Rencana Aksi (Q42, Q49).** Kegiatan, anggaran, dan RAB menjadi bagian RA; pengajuan RA mensyaratkan minimal satu kegiatan yang diklaim pada tahap RA. Anggaran per kegiatan wajib (≥ 0) dan RAB wajib bila anggaran lebih dari 0. Nama kegiatan hingga 500 karakter, tujuan opsional, dan kegiatan dapat disalin ke periode lain yang belum terkunci (Q49).
- **Kewajiban alasan (Q43).** Alasan hanya diwajibkan pada aksi Q43 §43.2; aksi lain beralasan opsional dan audit tetap lengkap.
- **Unggahan selalu aktif (Q44).** Tanpa saklar unggahan dan halaman Kebijakan Storage; batas teknis unggahan berasal dari konfigurasi server.
- **Narasi pelaporan dan Rekomendasi Pimpinan (Q45, Q51).** Narasi progres kegiatan, kendala/masalah, dan tindak lanjut/strategi wajib per pengukuran. Rekomendasi Pimpinan disusun Perencanaan sebagai draf lalu disetujui atau ditulis langsung oleh Pimpinan; hanya rekomendasi disetujui yang berlaku dan memicu tindak lanjut wajib pada periode berikutnya.
- **Dokumen resmi (Q48).** SAKIP menghasilkan Rencana Aksi tahunan dan Laporan Kinerja Triwulan dalam Excel dari data yang disahkan; PDF bertanda tangan diunggah kembali dengan `laporan:unggah`; dokumen PK tidak dihasilkan SAKIP.
- **Kode indikator (Q50).** Kode sasaran dan indikator mengikuti PK: S1–S4 dan IKU 1.1–IKU 4.1; nomor IKU Renstra (IKU 1–8) menjadi keterangan pada definisi operasional.
- **Formula final:** IKU 3 = `(sakip + zi_wbk)/2`; IKU 8 = `n/t × 100%` dengan `t` total publikasi seluruh PTS. *(Nomor Renstra; kode PK masing-masing IKU 1.3 dan IKU 3.3, Q50 §50.4.)*
- **Audit append-only** dan F1/F2 tetap business guard terpisah dari permission.

## Bagian 1 — Fondasi Autentikasi, Unit Organisasi, dan Model Akses RBAC

### US-01.01 · Login Terpusat Menggunakan Single Sign-On (SSO) Keycloak

> **Q32:** login pertama membuat user `nonaktif` tanpa role. Admin mengaktifkan dan assign role. Logout lokal menjadi default; logout SSO adalah aksi terpisah.
>
> **Q38 (13 Oktober 2026):** login pertama kini membuat user `aktif` tanpa role; langkah aktivasi dihapus. Lihat US-01.08.

| Field | Detail |
|---|---|
| **ID** | `US-01.01` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 5 |
| **Modul** | Fondasi Autentikasi, Unit Organisasi, dan Model Akses RBAC |
| **Dependensi** | Realm Keycloak LLDIKTI XVI aktif, client SAKIP terdaftar, callback URL tersedia. |
| **Otorisasi** | Rute login/callback publik; halaman aplikasi setelah callback memerlukan sesi autentikasi. |
| **Dampak Data** | `users`, sesi autentikasi; audit kegagalan/kejadian penting sesuai kebijakan. |

> **Sebagai** seluruh Pengguna SAKIP,  
> **Saya ingin** melakukan autentikasi menggunakan akun resmi institusi melalui Keycloak OIDC,  
> **Sehingga** pengguna tidak mengelola password lokal dan identitas aplikasi tetap terpusat.

**Acceptance Criteria**

- [ ] **AC-1:** Given pengguna belum login, When menekan **Login SSO**, Then sistem mengarahkan ke Keycloak melalui OIDC Authorization Code Flow.
- [ ] **AC-2:** Given callback membawa token yang valid, When callback diproses, Then sistem mencocokkan `keycloak_id`, menyinkronkan nama/email yang diizinkan, membentuk sesi Laravel, dan mengarahkan pengguna ke dashboard.
- [ ] **AC-3:** Given pengguna belum ada pada `users`, When login pertama berhasil, Then akun lokal dibuat dengan identitas Keycloak dan status aktif sesuai kebijakan onboarding.
- [ ] **AC-4:** Given callback gagal atau token tidak valid, When diproses, Then sesi lokal tidak dibuat dan pengguna menerima pesan kesalahan yang aman tanpa membocorkan token/credential.
- [ ] **AC-5:** Given pengguna telah logout, When mencoba membuka rute terproteksi, Then sistem meminta autentikasi kembali.

**Business Rules / Catatan**

- Tidak ada password lokal SAKIP.
- Token/secret Keycloak tidak boleh masuk log, audit, atau props React.

### US-01.02 · Pengelolaan Master Unit Organisasi

| Field | Detail |
|---|---|
| **ID** | `US-01.02` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Fondasi Autentikasi, Unit Organisasi, dan Model Akses RBAC |
| **Dependensi** | Pengguna telah login; katalog permission dan audit log tersedia. |
| **Otorisasi** | `unit:create`, `unit:read`, `unit:update`, `unit:delete`. |
| **Dampak Data** | `unit`, `audit_log`. |

> **Sebagai** Admin atau Superadmin,  
> **Saya ingin** menambah, membaca, mengubah, menonaktifkan, dan menghapus unit organisasi yang benar-benar kosong,  
> **Sehingga** struktur pemilik indikator dan scope akses selalu mengikuti organisasi resmi.

**Acceptance Criteria**

- [x] **AC-1:** Given pengguna memiliki permission yang sesuai, When membuat unit dengan data valid, Then unit tersimpan dengan status default `aktif` dan perubahan tercatat di audit.
- [x] **AC-2:** Given unit masih memiliki keterkaitan dengan **indikator, rencana aksi, atau kegiatan**, When penghapusan dicoba, Then penghapusan ditolak terlepas dari role aktor.
- [x] **AC-3:** Given unit tidak memiliki keterkaitan historis yang dilindungi, When **Superadmin** menghapus unit, Then unit dapat dihapus dan alasan/peristiwa tercatat di audit.
- [x] **AC-4:** Given Admin memiliki `unit:update`, When menonaktifkan unit, Then status berubah tanpa menghapus histori.
- [x] **AC-5:** Given pengguna tanpa permission `unit:*`, When mengakses endpoint secara langsung, Then server mengembalikan 403.

**Business Rules / Catatan**

- Unit adalah master global, bukan hierarki organisasi bertingkat.
- Delete unit kosong hanya oleh Superadmin; Admin tetap dapat create/read/update sesuai katalog role.

### US-01.03 · Penetapan Peran Utama Pengguna (Assign Peran)

| Field | Detail |
|---|---|
| **ID** | `US-01.03` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Dependensi** | User tersedia; 5 role final tersedia. |
| **Otorisasi** | `pengguna:read` + `akses:update`. |

> **Sebagai** Admin/Superadmin yang berwenang, **saya ingin** menetapkan satu role utama kepada user, **sehingga** klasifikasi akses formal tercatat dan teraudit.

**Acceptance Criteria**

- [ ] User dapat belum memiliki role setelah JIT onboarding.
- [ ] Pilihan hanya `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`.
- [ ] Assign/change role mewajibkan alasan dan audit before/after.
- [ ] Role `pic` tidak tersedia dan tidak dapat disimpan.
- [ ] Perubahan role tidak mengubah grant, deny, assignment PJ, atau provenance submission historis.
- [ ] Bila user merupakan PJ aktif, assignment tetap aktif; UI dapat memberi warning/penanda untuk ditindaklanjuti Perencanaan.

### US-01.04 · Pemberian Grant Izin Tambahan per Unit

> **Q43 (13 Oktober 2026):** alasan pemberian grant menjadi opsional; bila dikosongkan, server mengisi alasan baku sehingga kolom `alasan` tetap terisi (Q43 §43.3 butir 1). Pencabutan grant tetap wajib alasan (Q43 §43.2 butir 4). Butir "alasan wajib" di bawah dipertahankan sebagai histori implementasi; perubahan dikerjakan pada US-13.05.

| Field | Detail |
|---|---|
| **ID** | `US-01.04` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Otorisasi** | `delegasi:update`. |
| **Dampak Data** | `user_permission_granted`, `audit_log`. |

> **Sebagai** Perencanaan/Admin/Superadmin yang berwenang, **saya ingin** memberi atau mencabut Grant Unit, **sehingga** hak kerja unit dapat diberikan tanpa mengubah role utama.

**Acceptance Criteria**

- [ ] Hanya 7 permission unit-scoped dapat diberikan: `pengukuran:create/update`, `rencana_aksi:create/update/ajukan`, `kegiatan:create/update`.
- [ ] `rencana_aksi:read` dan `kegiatan:read` tidak tersedia pada Form Grant Unit.
- [x] User target dan unit harus aktif; alasan wajib.
- [x] Grant/revoke tidak mengubah role atau assignment PJ.
- [x] Deny yang cocok tetap menang.
- [ ] Perencanaan, Admin, Superadmin dapat menggunakan flow sesuai `delegasi:update` efektif.

### US-01.05 · Pencabutan Izin Eksplisit (Deny)

| Field | Detail |
|---|---|
| **ID** | `US-01.05` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Fondasi Autentikasi, Unit Organisasi, dan Model Akses RBAC |
| **Dependensi** | Pengguna target dan permission tersedia. |
| **Otorisasi** | `akses:update`. |
| **Dampak Data** | `user_permission_denials`, `audit_log`. |

> **Sebagai** Admin atau Superadmin,  
> **Saya ingin** membuat deny global atau deny pada unit tertentu,  
> **Sehingga** akses berisiko dapat dihentikan presisi tanpa merusak konfigurasi role/grant lain.

**Acceptance Criteria**

- [x] **AC-1:** Given user, permission, alasan, dan scope valid, When deny disimpan, Then baris `user_permission_denials` terbentuk.
- [x] **AC-2:** Given allow berasal dari role atau grant dan terdapat deny yang cocok, When resolver mengevaluasi izin, Then **deny menang** dan akses ditolak.
- [x] **AC-3:** Given deny berscope unit A, When user meminta permission unit-scoped pada unit B, Then deny unit A tidak otomatis memblokir unit B.
- [x] **AC-4:** Given deny global (`unit_id = NULL`) cocok, When permission diminta, Then permintaan ditolak untuk seluruh scope yang relevan.
- [x] **AC-5:** Given deny dicabut, When resolusi dilakukan ulang, Then izin efektif kembali mengikuti role/grant yang masih sah.

### US-01.06 · Transparansi Izin Pengguna — Jelaskan Izin

| Field | Detail |
|---|---|
| **ID** | `US-01.06` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Fondasi Autentikasi, Unit Organisasi, dan Model Akses RBAC |
| **Dependensi** | Service resolver izin tersedia. |
| **Otorisasi** | `pengguna:read`. |
| **Dampak Data** | Read-only terhadap `roles`, `role_permissions`, grant, deny, permission. |

> **Sebagai** Admin atau Superadmin,  
> **Saya ingin** melihat seluruh izin efektif pengguna per unit beserta sumber allow dan deny,  
> **Sehingga** keputusan akses dapat dijelaskan saat audit dan troubleshooting.

**Acceptance Criteria**

- [ ] **AC-1:** Given pengguna dipilih, When halaman dimuat, Then sistem menampilkan permission efektif, scope, asal peran/grant, dan deny yang berlaku.
- [ ] **AC-2:** Given suatu permission berasal dari role dan kemudian di-deny, When ditampilkan, Then permission ditandai dicabut dan tidak ditampilkan sebagai izin efektif.
- [ ] **AC-3:** Given halaman bersifat read-only, When pengguna berinteraksi, Then tidak ada mutasi role/grant/deny langsung dari halaman tersebut.
- [ ] **AC-4:** Given pengguna tanpa `pengguna:read`, When membuka endpoint, Then 403.
- [ ] **AC-5:** Given target user memiliki PIC operasional (bukan role), When halaman dimuat, Then sistem menampilkan permission efektif aktual yang benar-benar berasal dari `role_permissions`/grant/deny; tidak ada permission sintetis hanya karena nama role PIC.

### US-01.07 · Peran & Izin Read-Only + Preset Permission via Seeder

> **Q45, Q48, Q51 (13 Oktober 2026):** preset Pimpinan memperoleh `rekomendasi:tetapkan` melalui seeder (Q32.4), sedangkan Perencanaan tetap memegangnya sebagai cadangan; simpan draf dan persetujuan rekomendasi dibedakan oleh status rekomendasi, bukan oleh permission (Q45 §45.2 butir 1, Q51 §51.2 butir 1). Katalog memperoleh permission baru `laporan:unggah` dengan preset Perencanaan dan Superadmin (Q48 §48.2 butir 2). Perubahan preset dikerjakan pada US-09.01 (`rekomendasi:tetapkan`) dan US-10.05 (`laporan:unggah`) serta mengikuti audit delta preset di bawah.

| Field | Detail |
|---|---|
| **ID** | `US-01.07` |
| **Prioritas** | 🔴 P0 corrective alignment |
| **Story Points** | 5 |
| **Otorisasi UI** | `pengguna:read`. |
| **Dampak Data** | `roles`, `role_permissions`, `audit_log`. |

> **Sebagai** pengguna yang berhak membaca pengguna/akses, **saya ingin** melihat role dan permission bawaannya secara read-only, **sehingga** struktur akses transparan tanpa membuka editor preset dari aplikasi.

**Acceptance Criteria**

- [ ] Halaman menampilkan tepat 5 role final dan preset permissionnya.
- [ ] Tidak ada add/revoke/edit role-permission dari UI atau mutation endpoint aplikasi.
- [ ] Preset role bersumber dari kode/seeder/release.
- [ ] Perubahan preset menghasilkan audit before/after + alasan/sumber release.
- [ ] Seeder rerun tanpa delta tidak menghasilkan audit palsu.
- [ ] 7 permission unit-scoped tidak diglobalisasi melalui preset role.

### US-01.08 · Onboarding SSO Langsung Aktif dan Status Akun

| Field | Detail |
|---|---|
| **ID** | `US-01.08` |
| **Prioritas** | 🟡 P1 · Tahap 1 (Q46 §46.3; sedang berjalan) |
| **Otorisasi UI** | `pengguna:read` + `akses:update` untuk menonaktifkan dan mengaktifkan kembali akun. |
| **Dampak Data** | `users.status`, `audit_log`. |

> **Sebagai** pengelola akses SAKIP, **saya ingin** akun SSO baru langsung aktif tanpa peran dan dapat menonaktifkan atau mengaktifkan kembali akun pegawai tertentu, **sehingga** persetujuan akses cukup satu langkah dan akses pegawai yang keluar dapat diputus tanpa bantuan operator.

**Acceptance Criteria**

- [x] Login SSO pertama membuat akun aktif tanpa peran dan menampilkan halaman menunggu penetapan peran.
- [x] Akun aktif tanpa peran tidak memiliki akses bisnis apa pun.
- [x] Menu Aktivasi Pengguna tidak tersedia lagi.
- [x] Admin dapat menonaktifkan dan mengaktifkan kembali akun lain dengan alasan; sesi akun yang dinonaktifkan terblokir.
- [x] Akun sendiri dan superadmin aktif terakhir tidak dapat dinonaktifkan.
- [x] Penetapan peran tidak dapat menurunkan peran superadmin aktif terakhir, termasuk peran akun sendiri (Q53).
- [x] Peran dan grant tetap ada setelah akun diaktifkan kembali, dan peringatan tampil bila pengguna masih Penanggung Jawab aktif.

## Bagian 2 — Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK

### US-02.01 · Pencatatan Dokumen Dasar Regulasi

| Field | Detail |
|---|---|
| **ID** | `US-02.01` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK |
| **Dependensi** | Dokumen/metadata regulasi tersedia. |
| **Otorisasi** | `regulasi:create`, `regulasi:read`, `regulasi:update`, `regulasi:delete`. |
| **Dampak Data** | `regulasi`, `berkas`, `audit_log`. |

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** mencatat katalog dasar hukum lengkap dengan metadata dan lampiran,  
> **Sehingga** Renstra dan indikator memiliki rujukan hukum yang dapat ditelusuri.

**Acceptance Criteria**

- [x] **AC-1:** Given metadata regulasi valid, When disimpan, Then regulasi terbentuk dan audit dicatat.
- [x] **AC-2:** Given lampiran berupa file/tautan/teks, When disimpan, Then `berkas` terbentuk sebagai lampiran bebas dokumen dasar (`jenis_berkas_id = NULL`).
- [x] **AC-3:** Given kombinasi jenis-nomor-tahun yang sama telah ada, When dibuat lagi, Then duplikasi ditolak.
- [x] **AC-4:** Given regulasi masih dirujuk Renstra/Indikator aktif, When delete dicoba, Then penghapusan ditolak.
- [x] **AC-5:** Given aksi update/delete adalah sensitif, When berhasil atau ditolak oleh deny, Then `audit_log.dasar_izin` merekam sumber keputusan izin.

### US-02.02 · Penyusunan Master Renstra & Rujukan Regulasi

| Field | Detail |
|---|---|
| **ID** | `US-02.02` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK |
| **Dependensi** | Master regulasi tersedia bila digunakan. |
| **Otorisasi** | `renstra:create`, `renstra:read`, `renstra:update`, `renstra:delete`. |
| **Dampak Data** | `renstra`, `berkas`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** menyusun Renstra dengan rentang tahun, dasar hukum, dan lampiran resmi,  
> **Sehingga** arah strategis multi-tahun menjadi sumber utama struktur kinerja.

**Acceptance Criteria**

- [ ] **AC-1:** Given data Renstra valid, When dibuat, Then status awal `draft` dan data tersimpan.
- [ ] **AC-2:** Given naskah Renstra dilampirkan dalam mode yang sah, When disimpan, Then lampiran terhubung ke Renstra.
- [ ] **AC-3:** Given rentang tahun tidak valid, When submit, Then validasi menolak.
- [ ] **AC-4:** Given Renstra aktif memiliki lampiran yang telah mencapai batas imutabilitas, When delete lampiran dicoba, Then ditolak.

### US-02.03 · Aktivasi, Arsip, dan Revisi Renstra karena Perubahan Kebijakan

> **Q40 (13 Oktober 2026):** atribut Renstra tetap direvisi in place dengan audit. Lampiran revisi kini boleh ditambahkan setelah Renstra aktif (penghapusan tetap dilarang), dan perubahan target Renstra saat Renstra aktif wajib beralasan serta tampil sebagai riwayat revisi; keduanya dikerjakan pada US-02.10. Versi penuh Renstra (sasaran dan indikator ikut berversi) tidak dibangun saat ini; penggantian Renstra karena kebijakan kementerian memakai Renstra baru (Q40 §40.2 butir 4; backlog tahap 3, Q46 §46.3).
>
> **Q50 (13 Oktober 2026):** sasaran S4 dan IKU 4.1 yang hanya ada di PK dicatat di bawah Renstra 2025–2029 tanpa target Renstra (Q50 §50.5), dan tanggal PK tidak divalidasi terhadap tanggal penetapan Renstra (Q50 §50.6). Keduanya ditangani pada US-02.11 dan data awal US-15.01.

| Field | Detail |
|---|---|
| **ID** | `US-02.03` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 8 |
| **Modul** | Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK |
| **Dependensi** | Renstra draft beserta struktur minimum telah tersedia; keputusan revisi resmi tersedia bila mengubah Renstra aktif. |
| **Otorisasi** | `renstra:update` dan permission terkait entitas turunannya. |
| **Dampak Data** | `renstra`, `sasaran`, `indikator`, `jadwal_snapshot`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengaktifkan, mengarsipkan, atau merevisi Renstra secara terkendali ketika dasar kebijakan/IKU berubah,  
> **Sehingga** perubahan kebijakan dapat diterapkan tanpa menghapus histori tahun/periode yang sudah berjalan.

**Acceptance Criteria**

- [ ] **AC-1:** Given tidak ada Renstra aktif dengan rentang tahun beririsan, When aktivasi dilakukan, Then Renstra menjadi aktif.
- [ ] **AC-2:** Given Renstra memiliki jadwal aktif, When dinonaktifkan secara langsung, Then sistem menolak agar konteks tahun berjalan tidak rusak.
- [ ] **AC-3:** Given Kepmen/aturan IKU baru mengubah struktur indikator, When revisi dilakukan, Then perubahan master dicatat dan histori snapshot lama tidak ditimpa.
- [ ] **AC-4:** Given indikator baru hasil revisi berlaku mulai periode tertentu, Then efektivitasnya ditentukan melalui mekanisme snapshot/periode mulai, bukan dianggap wajib sejak periode sebelumnya.
- [ ] **AC-5:** Given perubahan substansial dilakukan, Then audit menyimpan alasan, aktor, nilai lama, dan nilai baru.

### US-02.04 · Penyusunan Sasaran Strategis & Indikator Kinerja

> **Q39 (13 Oktober 2026):** halaman Sasaran & Indikator memuat target Renstra per tahun (US-02.10); target tahun berjalan (target PK) tidak lagi diisi di halaman ini dan dipindah ke halaman Perjanjian Kinerja (US-02.11).
>
> **Q45 (13 Oktober 2026):** saklar `wajib_catatan` disembunyikan dari form indikator tanpa menghapus kolomnya karena kewajiban catatan digantikan narasi pengukuran wajib. Perubahan form dikerjakan pada US-07.03.
>
> **Q47 (13 Oktober 2026):** form indikator memperoleh penanda **Dinilai tahunan** (`indikator.dinilai_tahunan`) yang diubah Perencanaan dan tercatat di audit; penanda tidak mengubah tipe perhitungan, formula, maupun komponen indikator (Q47 §47.1). Dikerjakan pada US-05.07.
>
> **Q50 (13 Oktober 2026):** kode sasaran dan indikator mengikuti PK, Rencana Aksi, dan laporan triwulan (S1–S4, IKU 1.1–IKU 4.1); nomor IKU Renstra (IKU 1–8) dicatat sebagai keterangan pada definisi operasional tanpa kolom baru (Q50 §50.4).

| Field | Detail |
|---|---|
| **ID** | `US-02.04` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 8 |
| **Modul** | Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK |
| **Dependensi** | Renstra tersedia; unit tersedia. |
| **Otorisasi** | `sasaran:create`, `sasaran:update`, `sasaran:delete`, `indikator:create`, `indikator:read`, `indikator:update`, `indikator:delete`. |
| **Dampak Data** | `sasaran`, `indikator`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** menyusun Sasaran dan Indikator lengkap dengan unit pemilik, arah, satuan, formula, dan regulasi,  
> **Sehingga** setiap ukuran kinerja memiliki definisi dan kepemilikan yang tegas.

**Acceptance Criteria**

- [ ] **AC-1:** Given data sasaran valid, When disimpan, Then sasaran tersimpan di bawah Renstra yang benar.
- [ ] **AC-2:** Given data indikator valid, When disimpan, Then indikator memiliki unit pemilik, arah (`naik_baik`/`turun_baik`), tipe (`manual`/`rasio_persen`/`penjumlahan`), satuan, presisi, dan regulasi bila ada.
- [ ] **AC-3:** Given indikator baru dibuat, When status belum siap digunakan, Then indikator tidak otomatis masuk kewajiban periode yang sudah lampau.
- [ ] **AC-4:** Given kode/relasi tidak valid, When submit, Then server menolak terlepas dari validasi klien.

### US-02.05 · Perpindahan Unit dan Pengarsipan Indikator

> **Q43 (13 Oktober 2026):** alasan pindah unit indikator menjadi opsional, sedangkan audit tetap merekam unit lama/baru (Q43 §43.1, §43.3 butir 3). Perubahan request dikerjakan pada US-13.05.

| Field | Detail |
|---|---|
| **ID** | `US-02.05` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK |
| **Dependensi** | Indikator telah ada; unit tujuan valid. |
| **Otorisasi** | `indikator:update` / `indikator:delete` sesuai tindakan. |
| **Dampak Data** | `indikator`, snapshot terkait, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** memindahkan kepemilikan indikator atau mengarsipkan indikator dengan jejak historis utuh,  
> **Sehingga** perubahan struktur organisasi tidak mengubah laporan historis secara diam-diam.

**Acceptance Criteria**

- [ ] **AC-1:** Given indikator dipindahkan ke unit lain, When perubahan disimpan, Then `indikator.unit_id` berubah untuk konteks master ke depan dan audit merekam unit lama/baru.
- [ ] **AC-2:** Given snapshot lama telah dipakai RA/Pengukuran, When unit master berubah, Then snapshot/laporan versi lama tetap memakai unit historis yang dibekukan.
- [ ] **AC-3:** Given indikator diarsipkan, When periode baru dihitung, Then indikator arsip tidak menjadi kewajiban baru.
- [ ] **AC-4:** Given ada histori pengukuran/RA, When hard delete indikator dicoba, Then sistem menolak penghapusan permanen yang merusak histori.

### US-02.06 · Konfigurasi Komponen Angka Indikator (Data-Driven)

> **Q32:** IKU 3 hanya dua komponen `sakip` + `zi_wbk` dengan formula rata-rata; IKU 8 memakai `n/t × 100%` dan t total publikasi seluruh PTS.

| Field | Detail |
|---|---|
| **ID** | `US-02.06` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 8 |
| **Modul** | Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK |
| **Dependensi** | Indikator bertipe nonmanual tersedia. |
| **Otorisasi** | `komponen:create`, `komponen:read`, `komponen:update`, `komponen:delete`. |
| **Dampak Data** | `indikator_komponen`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mendefinisikan komponen pembilang, penyebut, penjumlah, bobot, urutan, dan kode,  
> **Sehingga** perhitungan dapat dikonfigurasi dari data tanpa menaruh formula per indikator di UI.

**Acceptance Criteria**

- [x] **AC-1:** Given indikator `rasio_persen`, When definisi disimpan, Then sistem mensyaratkan minimal satu pembilang dan tepat satu penyebut efektif.
- [x] **AC-2:** Given indikator `penjumlahan`, When definisi disimpan, Then minimal satu komponen penjumlah tersedia.
- [x] **AC-3:** Given IKU 3 dikonfigurasi, Then tersedia tepat dua komponen input efektif `sakip` dan `zi_wbk`, masing-masing koefisien 0,5, dan nilai indikator dihitung `(sakip + zi_wbk) / 2`.
- [x] **AC-4:** Given kode komponen sama pada indikator yang sama, When submit, Then constraint unik menolak.
- [x] **AC-5:** Given definisi komponen diubah setelah snapshot historis dirujuk, Then snapshot lama tidak berubah.

### US-02.07 · Penetapan Baseline & Target Tahunan

> **Q32:** baseline IKU 3 2025 = 74,2; target 2026 = 76,25; jangan tampilkan selisih sebagai tren. 66,395/87,08 bukan realisasi.
>
> **Q39 (13 Oktober 2026):** `target_tahunan` tetap menyimpan **target PK** dengan domain Q33, tetapi editornya dipindah dari halaman indikator ke halaman Perjanjian Kinerja dan diinput Perencanaan; PJ/PIC membaca saja (US-02.11). Target Renstra adalah data terpisah (US-02.10). Alasan perubahan target PK tetap opsional (Q43 §43.3 butir 2). Perubahan target resmi setelah persetujuan Eselon I memakai revisi PK berversi (US-02.12, Q40); AC-4 tetap bergantung pada US-03.03.
>
> **Q50 (13 Oktober 2026):** target PK disimpan dengan nilai penuh yang dipakai pengukuran (mis. 94,05), bukan nilai tercetak pada dokumen PK yang dibulatkan; tampilan mengikuti `desimal_tampilan`. Baseline tetap disimpan pada `target_tahunan`, tetapi menjadi data pendukung dan bukan isi dokumen PK (Q50 §50.2–§50.3; US-02.11).

| Field | Detail |
|---|---|
| **ID** | `US-02.07` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK |
| **Dependensi** | Indikator valid dan tahun berada dalam rentang Renstra. |
| **Otorisasi** | `target:update`. |
| **Dampak Data** | `target_tahunan`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** menetapkan baseline dan target tahunan indikator,  
> **Sehingga** target PK memiliki pembanding resmi sebelum siklus tahunan diaktifkan.

> **Status implementasi parsial — PR #64:** AC-1–3 memiliki bukti QA pada `5c056e5`; batas bukti dan tindak lanjut tercatat pada [ISS-02.07](SAKIP%20-%20User%20Issues.md#iss-0207--feature-penetapan-baseline--target-tahunan). AC-4 tetap terbuka. Centang bukan persetujuan UAT atau deployment.

**Acceptance Criteria**

- [x] **AC-1:** Given indikator dan tahun valid, When baseline/target disimpan, Then satu baris target tahunan tersedia untuk kombinasi tersebut.
- [x] **AC-2:** Given kombinasi indikator-tahun sudah ada, When nilai diperbarui, Then data diperbarui melalui `target:update` dan tidak membuat duplikasi.
- [x] **AC-3:** Given target sudah dibekukan ke snapshot yang dirujuk histori, When master target dikoreksi, Then laporan lama tidak ikut berubah.
- [ ] **AC-4:** Given koreksi salah input terhadap sumber PK resmi dibutuhkan, Then koreksi snapshot dilakukan melalui mekanisme versi dengan alasan dan rujukan bukti.

**Business Rules / Catatan**

- Katalog permission baseline hanya memakai `target:update`; tidak ada `target:create` terpisah pada baseline PRD.

### US-02.08 · Pencatatan Perjanjian Kinerja (PK) & Lampiran Legal

> **Q39–Q40, Q43 (13 Oktober 2026):** halaman PK kini juga memuat isian baseline dan target tahun berjalan per indikator, dikelompokkan per sasaran (US-02.11). Revisi PK membentuk versi PK baru dengan nomor dan tanggal surat Eselon I sebagai isian tersendiri; PK awal dan akhir dapat diunduh (US-02.12, tahap 3). Alasan perubahan PK wajib hanya bila PK sudah dipakai jadwal aktif (koreksi PK, Q43 §43.2 butir 3; US-02.09) dan opsional sebelumnya (Q43 §43.3 butir 3; US-13.05).
>
> **Q48, Q50 (13 Oktober 2026):** `nomor_pk` opsional karena PK 2026 tidak bernomor, sedangkan PK tetap unik per Renstra-tahun (Q50 §50.1); AC-1 tidak lagi mewajibkan nomor dan perubahannya dikerjakan pada US-02.11. Tanggal PK tidak divalidasi terhadap tanggal penetapan Renstra (Q50 §50.6). PK perubahan yang hanya mengubah anggaran diunggah sebagai lampiran tambahan PK beserta keterangan pada `berkas.keterangan`; target dan snapshot tidak berubah (Q50 §50.7, Q48 §48.2 butir 6). PDF PK bertanda tangan tetap diunggah sebagai lampiran PK dan, setelah revisi berversi tersedia, per versi PK (Q48 §48.2 butir 1; US-02.12); dokumen PK sendiri tidak dihasilkan SAKIP (Q48 §48.1 butir 4).

| Field | Detail |
|---|---|
| **ID** | `US-02.08` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK |
| **Dependensi** | Renstra valid. |
| **Otorisasi** | `pk:create`, `pk:update`, serta capability berkas melalui kewenangan induk. |
| **Dampak Data** | `renstra_pk`, `berkas`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mencatat PK tahun berjalan beserta metadata dan dokumen resmi,  
> **Sehingga** jadwal hanya dapat diaktifkan dengan dasar komitmen formal yang dapat dibuktikan.

**Acceptance Criteria**

- [ ] **AC-1:** Given nomor/tanggal/tahun PK valid, When disimpan, Then `renstra_pk` terbentuk untuk Renstra tersebut.
- [ ] **AC-2:** Given lampiran file/tautan/teks tersedia, When disimpan, Then `berkas` terhubung ke `renstra_pk`.
- [ ] **AC-3:** Given PK untuk Renstra-tahun yang sama sudah ada, When dibuat ulang, Then duplikasi ditolak.
- [ ] **AC-4:** Given jadwal tahun tersebut telah aktif, When lampiran PK dihapus, Then penghapusan ditolak sesuai batas imutabilitas.

### US-02.09 · Koreksi Perjanjian Kinerja Secara Teraudit

> **Q40 (13 Oktober 2026):** jalur ini tetap untuk koreksi salah ketik atau salah input terhadap dokumen PK resmi, termasuk nomor dan tanggal PK. Perubahan target resmi setelah persetujuan Eselon I memakai revisi PK berversi (US-02.12), bukan koreksi ini (Q40 §40.1 butir 8). Koreksi PK tetap wajib alasan (Q43 §43.2 butir 3). `nomor_pk` opsional (Q50 §50.1), sehingga validasi koreksi tidak mewajibkan nomor PK.

| Field | Detail |
|---|---|
| **ID** | `US-02.09` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK |
| **Dependensi** | PK telah ada; sumber koreksi resmi tersedia. |
| **Otorisasi** | `pk:update`. |
| **Dampak Data** | `renstra_pk`, snapshot terdampak bila ada, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengoreksi salah input metadata/target PK dengan alasan dan rujukan resmi,  
> **Sehingga** kesalahan administratif dapat diperbaiki tanpa menghapus konteks historis.

**Acceptance Criteria**

- [ ] **AC-1:** Given koreksi PK diajukan tanpa alasan, When submit, Then ditolak.
- [ ] **AC-2:** Given nilai PK dikoreksi berdasarkan dokumen resmi, When disimpan, Then audit merekam nilai lama/baru, alasan, dan rujukan.
- [ ] **AC-3:** Given snapshot terdampak sudah terbit, When koreksi PK memerlukan perubahan konteks snapshot, Then snapshot tidak diubah in-place; perubahan dibuat sebagai versi snapshot pengganti (ISS-03.03, Q35 §35.1).
- [ ] **AC-4:** Given snapshot sudah dirujuk versi RA/Pengukuran, When koreksi diperlukan, Then sistem membuat versi snapshot pengganti dan mempertahankan snapshot lama.
- [ ] **AC-5:** Given hasil resmi lama sudah disahkan, Then hasil lama tidak berubah sampai versi koreksi diajukan dan disahkan ulang.

**Business Rules / Catatan**

- Koreksi tidak membentuk versi PK; pembentukan versi PK awal/akhir hanya melalui revisi berversi US-02.12 (Q40 §40.1 butir 2 dan 8).

### US-02.10 · Target Renstra per Indikator, Lampiran Revisi, dan Riwayat Revisi Target Renstra

| Field | Detail |
|---|---|
| **ID** | `US-02.10` |
| **Prioritas** | Tahap 1 (Q46 §46.3: sebelum integrasi 9 November 2026) |
| **Story Points** | Belum diestimasi |
| **Modul** | Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK |
| **Dependensi** | Renstra, sasaran, dan indikator tersedia (US-02.02–US-02.04). |
| **Otorisasi** | Penyimpanan target Renstra: `target:update` yang sudah ada, tanpa permission baru (Q39 §39.3 butir 3); lampiran revisi: `renstra:update` dan capability berkas melalui kewenangan induk. |
| **Dampak Data** | `target_renstra` (entitas baru per indikator per tahun Renstra; rancangan Data Model §2.35), `berkas` (induk `renstra`), `audit_log`. |
| **Keputusan terkait** | Q39 §39.1 dan §39.3 butir 3, Q40 §40.2, Q43 §43.2 butir 3, Q50 §50.5, Q46 §46.3. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mencatat target Renstra setiap indikator untuk setiap tahun dalam rentang Renstra, menambah lampiran revisi setelah Renstra aktif, dan melihat riwayat revisi target Renstra,  
> **Sehingga** target PK memiliki pembanding resmi dan revisi Renstra tetap tertelusur tanpa menghapus dokumen lama.

**Acceptance Criteria**

- [ ] **AC-1:** Given indikator aktif pada Renstra dengan rentang tahun tertentu, When Perencanaan menyimpan target Renstra, Then tersimpan paling banyak satu nilai per indikator per tahun dalam rentang Renstra; tahun di luar rentang ditolak server. *(Q39 §39.1)*
- [ ] **AC-2:** Given nilai target Renstra dikirim, When disimpan, Then berlaku domain nilai Q33: boleh kosong, bila diisi finite dan nonnegatif, `0` sah dan berbeda dari kosong, tanpa batas universal `100`. *(Q39 §39.1 butir 4)*
- [ ] **AC-3:** Given halaman Sasaran & Indikator dibuka, Then target Renstra per tahun tampil bersama indikator sebagai pembanding dan tidak dipakai menghitung capaian. *(Q39 §39.1 butir 2, §39.4)*
- [ ] **AC-4:** Given Renstra berstatus `aktif`, When target Renstra diubah tanpa alasan, Then perubahan ditolak; When diubah dengan alasan (dasar revisi), Then perubahan tersimpan dan audit mencatat aktor, waktu, nilai lama, nilai baru, dan alasan. Pada Renstra `draft` alasan tidak diwajibkan. *(Q40 §40.2 butir 3, Q43 §43.2 butir 3)*
- [ ] **AC-5:** Given target Renstra pernah diubah saat Renstra `aktif`, When riwayat dibuka, Then sistem menampilkan riwayat revisi target Renstra (waktu, aktor, nilai lama/baru, alasan) yang dibaca dari audit. *(Q40 §40.2 butir 3)*
- [ ] **AC-6:** Given Renstra berstatus `aktif`, When Perencanaan menambahkan lampiran revisi, Then lampiran baru tersimpan pada Renstra; When lampiran yang sudah ada dihapus, Then ditolak. *(Q40 §40.2 butir 2)*
- [ ] **AC-7:** Given target Renstra suatu indikator atau tahun kosong, When jadwal tahun tersebut diaktifkan, Then kekosongan target Renstra tidak menjadi gerbang aktivasi. *(Q39 §39.1 butir 5)*

**Business Rules / Catatan**

- Target Renstra terpisah dari target PK (`target_tahunan`, US-02.11); atribut Renstra lain tetap direvisi in place dengan audit (Q40 §40.2 butir 1).
- Bila target terlampaui sebelum akhir periode Renstra, target tahun berikutnya dinaikkan melalui perubahan beralasan pada AC-4 (Q40 §40.2 butir 3).
- Target Renstra disimpan dengan permission `target:update` yang sudah ada; tidak ada permission baru (Q39 §39.3 butir 3).
- Indikator yang hanya ada di PK (mis. IKU 4.1 pada sasaran S4) dicatat di bawah Renstra 2025–2029 tanpa target Renstra (Q50 §50.5); kekosongan ini sah menurut AC-2.
- Lampiran revisi hanya dapat ditambahkan pada Renstra berstatus `draft` atau `aktif`; Renstra `nonaktif` atau diarsipkan tidak menerima lampiran baru (Q40 §40.2 butir 5).
- **Di luar cakupan:** versi penuh Renstra (Q40 §40.2 butir 4; tahap 3, Q46 §46.3), pembanding target PK terhadap target Renstra (US-02.11), penghapusan lampiran, dan alur usulan target oleh PJ (Q39 §39.3 butir 2).

### US-02.11 · Input Target PK di Halaman Perjanjian Kinerja dengan Pembanding Target Renstra

| Field | Detail |
|---|---|
| **ID** | `US-02.11` |
| **Prioritas** | Tahap 1 (Q46 §46.3: sebelum integrasi 9 November 2026) |
| **Story Points** | Belum diestimasi |
| **Modul** | Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK |
| **Dependensi** | PK tahun berjalan tercatat (US-02.08); target Renstra tersedia sebagai pembanding (US-02.10). |
| **Otorisasi** | `target:update` untuk menyimpan target PK (dipegang Tim Perencanaan, Q39 §39.3); PJ/PIC baca-saja. Metadata PK tetap `pk:create`/`pk:update`. |
| **Dampak Data** | `target_tahunan` (termasuk `alasan_deviasi_renstra`), `renstra_pk.nomor_pk` (opsional, Q50 §50.1), `audit_log`. |
| **Keputusan terkait** | Q39 §39.1–§39.4, Q33, Q43 §43.2 butir 5 dan §43.3 butir 2, Q50 §50.1–§50.3 dan §50.5–§50.7, Q46 §46.3. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengisi baseline dan target tahun berjalan setiap indikator langsung di halaman Perjanjian Kinerja dengan target Renstra sebagai pembanding,  
> **Sehingga** target PK tercatat bersama dokumen PK dan setiap deviasi terhadap Renstra memiliki penjelasan.

**Acceptance Criteria**

- [ ] **AC-1:** Given PK suatu Renstra-tahun tercatat, When halaman PK dibuka, Then tampil isian baseline dan target tahun berjalan untuk setiap indikator aktif Renstra, dikelompokkan per sasaran, bersama nomor, tanggal, dan lampiran PK. *(Q39 §39.1 butir 1)*
- [ ] **AC-2:** Given Perencanaan menyimpan baseline/target di halaman PK, Then nilai tersimpan pada `target_tahunan` (satu baris per indikator-tahun) dengan domain nilai Q33; editor target di halaman indikator tidak lagi tersedia. *(Q39 §39.1 butir 2–3)*
- [ ] **AC-3:** Given target Renstra tahun yang sama tersedia, When target PK diisi, Then target Renstra ditampilkan di sampingnya bersama anjuran "minimal sama atau naik satu poin" sebagai panduan, bukan aturan sistem. *(Q39 §39.2 butir 1 dan 3)*
- [ ] **AC-4:** Given indikator `naik_baik` dengan target PK di bawah target Renstra, atau `turun_baik` dengan target PK di atas target Renstra, When disimpan, Then sistem menampilkan peringatan dan mewajibkan `alasan_deviasi_renstra`; penyimpanan tidak ditolak bila alasan terisi, dan alasan tersimpan bersama target PK serta tercatat di audit. *(Q39 §39.2 butir 2 dan 5, Q43 §43.2 butir 5)*
- [ ] **AC-5:** Given indikator belum memiliki target Renstra, When target PK diisi, Then penyimpanan diizinkan dengan keterangan "target Renstra belum ada" tanpa kewajiban alasan deviasi. *(Q39 §39.2 butir 4)*
- [ ] **AC-6:** Given PJ atau PIC membuka target PK, Then target tampil baca-saja; request mutasi langsung tanpa `target:update` ditolak 403. *(Q39 §39.3)*
- [ ] **AC-7:** Given aktivasi jadwal diminta, Then gerbang tetap: PK tahun tersebut tercatat, setiap indikator aktif memiliki target PK, tahun jadwal berada dalam rentang Renstra, dan PK memiliki lampiran. *(Q39 §39.1 butir 5)*
- [ ] **AC-8:** Given target PK sudah dibekukan ke snapshot jadwal, When target PK diubah di halaman PK, Then snapshot, rekonsiliasi Rencana Aksi (ADR-0008), dan pengukuran tidak berubah; alasan perubahan target PK tetap opsional. *(Q39 §39.1 butir 3, Q43 §43.3 butir 2)*
- [ ] **AC-9:** Given PK dicatat tanpa nomor, When disimpan, Then diterima karena `nomor_pk` opsional; PK tetap unik per Renstra-tahun. *(Q50 §50.1)*
- [ ] **AC-10:** Given target PK berpresisi penuh (mis. 94,05), When disimpan, Then nilai tersimpan apa adanya dan status capaian serta selisih memakai nilai tersimpan; tampilan mengikuti `desimal_tampilan`. *(Q50 §50.2)*
- [ ] **AC-11:** Given halaman PK, Then baseline tampil sebagai data pendukung, bukan bagian isi dokumen PK. *(Q50 §50.3)*
- [ ] **AC-12:** Given indikator yang hanya ada di PK (mis. IKU 4.1, sasaran S4), When target PK diisi, Then berlaku keterangan "target Renstra belum ada" (AC-5). *(Q50 §50.5)*
- [ ] **AC-13:** Given tanggal PK lebih awal dari tanggal penetapan Renstra, When disimpan, Then tidak ditolak karena urutan tanggal. *(Q50 §50.6)*

**Business Rules / Catatan**

- Target PK diinput Tim Perencanaan setelah angka disepakati dengan tim kerja di luar aplikasi (Q39 §39.3 butir 1).
- Capaian dinilai terhadap target PK melalui snapshot, bukan terhadap target Renstra; target periode RA dipecah dari target PK (Q39 §39.4).
- Perubahan target resmi setelah persetujuan Eselon I memakai revisi PK berversi (US-02.12); koreksi salah ketik memakai US-02.09 (Q40 §40.1 butir 8).
- PK perubahan yang hanya mengubah anggaran diunggah sebagai lampiran tambahan pada PK (fitur lampiran PK yang ada; Q50 §50.7).
- Keterangan lampiran tambahan tersebut (mis. "PK perubahan anggaran") disimpan pada kolom baru `berkas.keterangan` (Q48 §48.2 butir 6).
- PK perubahan yang mengubah target memakai revisi berversi (US-02.12); bila revisi seperti itu terbit sebelum US-02.12 selesai, penanganannya ditetapkan PM sebagai keputusan baru (Q50 §50.7 butir 2).
- **Di luar cakupan:** alur usulan target oleh PJ (Q39 §39.3 butir 2), revisi PK berversi (US-02.12), dan simulasi target (tahap 3, Q46 §46.3).

### US-02.12 · Revisi PK Berversi (PK Awal dan PK Akhir)

| Field | Detail |
|---|---|
| **ID** | `US-02.12` |
| **Prioritas** | Tahap 3 (Q46 §46.3: sesudah tahap 2; harus siap sebelum revisi PK pertama yang mengubah target) |
| **Story Points** | Belum diestimasi |
| **Modul** | Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK |
| **Dependensi** | Alur koreksi snapshot berversi US-03.03 sebagai prasyarat (Q40 §40.1 butir 9); PK dan target PK tercatat (US-02.08, US-02.11). |
| **Otorisasi** | Revisi disetujui Perencanaan (Q40 §40.1 butir 1); permission ditetapkan saat refinement (katalog existing memuat `pk:update`); capability berkas melalui kewenangan induk; unduhan mengikuti hak baca PK. |
| **Dampak Data** | `renstra_pk_versi` dan `renstra_pk_versi_target` (rancangan Data Model §2.36–§2.37; `nomor_surat`, `tanggal_surat`), `berkas` (lampiran per versi PK), `jadwal_snapshot` (versi baru bila target berubah), `audit_log`. |
| **Keputusan terkait** | Q40 §40.1, Q35 §35.1, Q43 §43.2 butir 3, Q48 §48.2 butir 1–2, Q50 §50.7, Q46 §46.3. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mencatat revisi PK yang disetujui Eselon I sebagai versi PK baru,  
> **Sehingga** PK awal dan PK akhir tersimpan serta dapat diunduh, sementara target baru berlaku mulai periode yang belum ditutup tanpa mengubah laporan periode sebelumnya.

**Acceptance Criteria**

- [ ] **AC-1:** Given PK sudah tercatat, When Perencanaan menyetujui revisi, Then terbentuk versi PK baru yang menyimpan nomor versi, nomor dan tanggal surat persetujuan Eselon I sebagai isian tersendiri, periode mulai berlaku, lampiran dokumen PK bertanda tangan, dan target per indikator; versi sebelumnya tidak berubah. *(Q40 §40.1 butir 1–2)*
- [ ] **AC-2:** Given revisi PK disimpan tanpa alasan, Then ditolak. *(Q43 §43.2 butir 3)*
- [ ] **AC-3:** Given revisi mengubah target, When disetujui, Then revisi berlaku mulai periode pertama yang belum ditutup: sistem menerbitkan versi `jadwal_snapshot` baru (Q35 §35.1) dengan `periode_mulai_id` periode tersebut dan `rujukan_koreksi` surat Eselon I, sedangkan periode yang sudah dilaporkan tetap memakai target versi lama. *(Q40 §40.1 butir 4)*
- [ ] **AC-4:** Given revisi hanya mengganti dokumen tanpa mengubah target, When disetujui, Then versi PK baru terbentuk tanpa menerbitkan versi snapshot. *(Q40 §40.1 butir 5)*
- [ ] **AC-5:** Given PK memiliki lebih dari satu versi, When halaman PK dibuka, Then PK awal dan PK akhir dapat diunduh sesuai hak baca. *(Q40 §40.1 butir 3)*
- [ ] **AC-6:** Given capaian akhir tahun dinilai, Then acuannya target PK akhir, yaitu versi yang berlaku pada periode nilai akhir. *(Q40 §40.1 butir 6)*
- [ ] **AC-7:** Given Rencana Aksi sudah disusun, When revisi PK mengubah target, Then RA tidak diubah otomatis; selisih target periode terakhir dengan target PK baru tampil sebagai deviasi yang wajib dijelaskan (ADR-0008), dan target periode yang belum terkunci disesuaikan lewat buka-kembali (US-05.06). *(Q40 §40.1 butir 7)*

**Business Rules / Catatan**

- Koreksi salah ketik nomor atau tanggal PK tetap memakai koreksi teraudit US-02.09; perubahan target resmi memakai revisi berversi (Q40 §40.1 butir 8).
- Aturan lama yang mencantumkan nomor dan tanggal surat Eselon I pada kolom `alasan` digantikan isian tersendiri (Q40 §40.3).
- Q40 tidak merinci kapan suatu periode dianggap "sudah ditutup"; definisinya ditetapkan saat refinement bersama US-03.03 sebelum implementasi.
- PDF PK bertanda tangan diunggah per versi PK dan mengikuti permission PK (Q48 §48.2 butir 1–2).
- Sebelum story ini tersedia, PK perubahan yang hanya mengubah anggaran diunggah sebagai lampiran tambahan PK (US-02.11); PK perubahan yang mengubah target dan terbit sebelum story ini selesai ditangani dengan keputusan baru PM (Q50 §50.7).
- **Di luar cakupan:** revisi dan riwayat target Renstra (US-02.10), versi penuh Renstra (Q40 §40.2 butir 4), dan koreksi salah ketik (US-02.09).

---

## Bagian 3 — Periode, Jadwal, Snapshot, Efektivitas, dan Backfill

### US-03.01 · Penyusunan Master Periode, Jadwal Tahunan, dan Jendela Periode

> **Q32:** initial schedule = 2026. TW I–II periode lampau oleh Perencanaan, TW III–IV normal. Tidak ada `is_backfill`.
>
> **Q41 (13 Oktober 2026):** jendela penyusunan RA dihapus; isian `rencana_aksi_mulai`/`rencana_aksi_selesai` dihapus dari form jadwal dan kolomnya dibiarkan nullable sampai migrasi lanjutan. AC-1 dan AC-3 yang menyebut jendela RA digantikan; perubahan dikerjakan pada US-05.07.
>
> **Q46 (13 Oktober 2026):** TW III 2026 ikut menjadi periode lampau yang diisi Perencanaan; TW IV 2026 menjadi triwulan pertama workflow normal PIC operasional. Catatan Q32 di atas dipertahankan sebagai riwayat.

| Field | Detail |
|---|---|
| **ID** | `US-03.01` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 8 |
| **Modul** | Periode, Jadwal, Snapshot, Efektivitas, dan Backfill |
| **Dependensi** | Renstra/PK tersedia untuk tahun yang disiapkan. |
| **Otorisasi** | `periode:create`, `periode:update`, `jadwal:create`, `jadwal:update`. |
| **Dampak Data** | `periode`, `jadwal_tahunan`, `jadwal_periode`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** menyusun periode yang diharapkan, jendela Rencana Aksi, pengisian, reviu, dan penutupan,  
> **Sehingga** siklus kerja tahunan memiliki kalender resmi dan sumber kebenaran tunggal.

**Acceptance Criteria**

- [ ] **AC-1:** Given tahun Y disiapkan, When jadwal tahunan dibuat, Then status awal `draft` dengan `rencana_aksi_mulai`, `rencana_aksi_selesai`, dan `penutupan`.
- [ ] **AC-2:** Given daftar Triwulan/Semester dimasukkan, When disimpan, Then `jadwal_periode` unik per periode pada jadwal tersebut.
- [ ] **AC-3:** Given urutan normal, Then `rencana_aksi_mulai <= rencana_aksi_selesai < pengisian_mulai` periode pertama.
- [ ] **AC-4:** Given jendela reviu disusun, Then `reviu_selesai` diperlakukan sebagai target operasional; Perencanaan masih dapat reviu sampai penutupan dengan penanda terlambat.
- [ ] **AC-5:** Given tanggal tidak konsisten, When submit, Then server menolak.
- [x] **AC-6:** Given lebih dari 12 periode dipilih, When jadwal disimpan, Then server menolak; satu jadwal tahunan memuat paling banyak 12 periode. *(Q35 §35.3; bukti `RencanaAksiMatrixLimitTest` pada PR #62.)*

### US-03.02 · Aktivasi Jadwal Tahunan melalui Empat Gerbang & Pembentukan Snapshot

> **Q39, Q43, Q44, Q47 (13 Oktober 2026):** gerbang tetap empat (Q39 §39.1 butir 5); "target tahunan" pada AC-1 adalah target PK yang kini diinput di halaman PK (US-02.11). Saklar unggahan dihapus sehingga pengecualian lampiran PK `tidak_dapat_dipenuhi` akibat unggahan dimatikan tidak lagi terjadi dan cabang anti-macet unggahan pada gerbang lampiran PK dihapus; penanda yang sudah tercatat tetap menjadi histori (Q44 §44.1 butir 2; US-13.06). Alasan aktivasi menjadi opsional (Q43 §43.3 butir 3; US-13.05). Snapshot yang dibentuk saat aktivasi ikut membekukan penanda dinilai tahunan sebagai `jadwal_snapshot.dinilai_tahunan` (Q47 §47.1 butir 2; US-05.07).

| Field | Detail |
|---|---|
| **ID** | `US-03.02` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Periode, Jadwal, Snapshot, Efektivitas, dan Backfill |
| **Dependensi** | Jadwal draft; data master tahun berjalan siap. |
| **Otorisasi** | `jadwal:aktivasi` (sensitif). |
| **Dampak Data** | `jadwal_tahunan`, `jadwal_snapshot`, `jadwal_snapshot_komponen`, `audit_log`. |

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** mengaktifkan jadwal setelah seluruh gerbang wajib terpenuhi,  
> **Sehingga** konteks indikator dan formula tahun berjalan dibekukan secara aman sebelum pekerjaan PIC dimulai.

> **Status implementasi — PR #67:** AC-1–6 memiliki bukti QA pada `57c0e46`; batas bukti dan item terbuka tercatat pada [ISS-03.02](SAKIP%20-%20User%20Issues.md#iss-0302--feature-aktivasi-jadwal-tahunan-melalui-empat-gerbang--pembentukan-snapshot). Centang bukan persetujuan UAT atau deployment.

**Acceptance Criteria**

- [x] **AC-1:** Given aktivasi diminta, When server mengevaluasi, Then empat gerbang diperiksa: PK tersedia; target tahunan semua indikator aktif tersedia; tahun berada dalam rentang Renstra; lampiran PK tersedia atau pengecualian file yang sah tercatat.
- [x] **AC-2:** Given salah satu gerbang wajib gagal, When aktivasi diproses, Then seluruh transaksi ditolak atomik dan pesan menjelaskan gerbang yang gagal.
- [x] **AC-3:** Given semua gerbang lolos, When commit berhasil, Then jadwal menjadi `aktif` dan `activated_at` terisi.
- [x] **AC-4:** Given indikator aktif belum memiliki snapshot pada jadwal, When aktivasi sukses, Then `jadwal_snapshot` dibuat idempoten beserta `jadwal_snapshot_komponen`.
- [x] **AC-5:** Given snapshot pasangan indikator-jadwal sudah ada, When proses diulang, Then baris lama tidak ditimpa/duplikasi.
- [x] **AC-6:** Given permission sensitif digunakan, Then audit menyimpan `dasar_izin` dan aktor sebenarnya.

### US-03.03 · Koreksi Snapshot Terkendali dan Versioning Konteks

> **Q40/Q46 (13 Oktober 2026):** alur koreksi snapshot berversi ini menjadi prasyarat revisi PK berversi (US-02.12): persetujuan revisi PK yang mengubah target menerbitkan versi `jadwal_snapshot` baru dengan `periode_mulai_id` periode pertama yang belum ditutup dan `rujukan_koreksi` surat Eselon I (AC-6). Keduanya dijadwalkan pada tahap 3 (Q46 §46.3).
>
> **Q47 (13 Oktober 2026):** koreksi snapshot juga menjadi jalur perubahan penanda dinilai tahunan setelah jadwal aktif (Q47 §47.1 butir 2).

| Field | Detail |
|---|---|
| **ID** | `US-03.03` |
| **Prioritas** | 🟡 P1 · Tahap 3 (Q46 §46.3; prasyarat US-02.12) |
| **Story Points** | 8 |
| **Modul** | Periode, Jadwal, Snapshot, Efektivitas, dan Backfill |
| **Dependensi** | Snapshot tersedia; koreksi memiliki alasan dan rujukan resmi. |
| **Otorisasi** | `target:update` / permission substantif terkait serta guard jadwal. |
| **Dampak Data** | `jadwal_snapshot`, `jadwal_snapshot_komponen`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengoreksi konteks snapshot yang salah tanpa mengubah versi yang telah dipakai histori,  
> **Sehingga** target, formula, dan identitas laporan historis tetap dapat dipertanggungjawabkan.

**Acceptance Criteria**

- [ ] **AC-1:** Given snapshot sudah terbit, baik belum maupun sudah dirujuk RA/Pengukuran/versi pengajuan, When koreksi diminta, Then baris snapshot dan komponennya tidak diubah in-place; koreksi dibuat sebagai versi baru (AC-2) dan audit menyimpan konteks lama/baru. *(Q35 §35.1)*
- [ ] **AC-2:** Given snapshot sudah terbit, When koreksi diperlukan, Then baris versi baru dibuat dengan `nomor_versi + 1`, `menggantikan_id`, `alasan_koreksi`, dan `rujukan_koreksi`.
- [ ] **AC-3:** Given versi lama telah dirujuk laporan/pengajuan, Then versi lama dan komponen anaknya tetap immutable.
- [ ] **AC-4:** Given pengukuran hendak memakai snapshot koreksi, Then pengukuran harus diajukan dan disahkan ulang; tidak ada propagasi diam-diam.
- [ ] **AC-5:** Given versi snapshot tidak cocok indikator/jadwal, When koreksi dibuat, Then ditolak.
- [ ] **AC-6:** Given revisi PK berversi yang mengubah target disetujui (US-02.12), When versi snapshot baru diterbitkan, Then versi tersebut memuat `periode_mulai_id` periode pertama yang belum ditutup dan `rujukan_koreksi` surat persetujuan Eselon I, sedangkan periode sebelumnya tetap memakai versi lama. *(Q40 §40.1 butir 4 dan 9)*

### US-03.04 · Penambahan Indikator Baru di Tengah Tahun & Periode Mulai Berlaku

| Field | Detail |
|---|---|
| **ID** | `US-03.04` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 8 |
| **Modul** | Periode, Jadwal, Snapshot, Efektivitas, dan Backfill |
| **Dependensi** | Jadwal aktif; indikator baru telah ditetapkan resmi. |
| **Otorisasi** | `jadwal:buka_kembali` untuk penambahan snapshot beralasan pada jadwal aktif; permission indikator terkait. |
| **Dampak Data** | `jadwal_snapshot`, `jadwal_snapshot_komponen`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** menambahkan indikator resmi yang baru berlaku mulai periode tertentu,  
> **Sehingga** periode sebelumnya tidak salah dianggap belum mengisi atau bernilai nol.

**Acceptance Criteria**

- [ ] **AC-1:** Given indikator baru ditambahkan pada jadwal aktif, When snapshot dibuat, Then `periode_mulai_id` wajib ditetapkan dan merupakan anggota jadwal.
- [ ] **AC-2:** Given periode sebelum `periode_mulai_id`, When dashboard/laporan menghitung kewajiban, Then statusnya **Tidak berlaku**, bukan Belum Mengisi/Tidak Mengisi/nol.
- [ ] **AC-3:** Given snapshot indikator lama sudah ada, When indikator baru ditambahkan, Then snapshot lama tidak ditimpa.
- [ ] **AC-4:** Given deadline PIC lama tidak diubah, When penambahan indikator dilakukan, Then akses PIC tidak otomatis dibuka; jendela resmi harus diatur terpisah jika diperlukan.
- [ ] **AC-5:** Given tindakan dilakukan, Then alasan dan detail penambahan tercatat di audit.

### US-03.05 · Pengisian Periode Lampau 2026 oleh Perencanaan

> **Q46, Q47 (13 Oktober 2026):** TW III 2026 ikut menjadi periode lampau yang diisi Perencanaan; TW IV 2026 menjadi triwulan pertama workflow normal PIC operasional dengan pelaporan awal Januari 2027 (Q46 §46.1 butir 2). Gerbang baru Q42–Q45 dikecualikan untuk periode lampau, target periode lampau boleh kosong, dan data awal diisi dari dokumen resmi 2026. AC di bawah diperbarui langsung karena story ini belum dikerjakan; rumusan semula (TW I–II) menjadi riwayat.

| Field | Detail |
|---|---|
| **ID** | `US-03.05` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Periode, Jadwal, Snapshot, Efektivitas |
| **Dependensi** | Jadwal Tahunan 2026 dan Jadwal Periode tersedia. |
| **Otorisasi** | Permission global Perencanaan untuk pengisian/koreksi. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengisi periode 2026 yang jendela normalnya sudah lewat tanpa mekanisme backfill khusus,  
> **Sehingga** TW I–III 2026 dapat dicatat secara benar tanpa memalsukan tanggal atau membuat flag historis baru.

**Acceptance Criteria**

- [ ] TW I–III 2026 teridentifikasi sebagai periode lampau dari jadwal (jendela pengisiannya sudah lewat saat aktivasi), bukan flag `is_backfill`. *(Q46 §46.1 butir 2; semula TW I–II)*
- [ ] Perencanaan dapat mengisi periode lampau; user scoped mengikuti jendela dan tidak memperoleh bypass otomatis.
- [ ] Gerbang RA/komponen/bukti normal dikecualikan untuk jalur periode lampau, sedangkan PK 2026 tetap wajib.
- [ ] Gerbang baru Q42–Q45 — RA minimal satu kegiatan, RAB, persyaratan tahap perencanaan kegiatan, narasi wajib, dan tindak lanjut rekomendasi — dikecualikan untuk periode lampau, dan story yang menambah gerbang tersebut menyertakan pengecualian ini beserta test-nya. Kunci per triwulan bukan bagian pengecualian ini: periode lampau tetap terkunci bagi PIC sejak aktivasi dan diisi Perencanaan, yang dikecualikan dari kunci tersebut. *(Q46 §46.1 butir 1 dan 4, Q41 §41.2 butir 3–4)*
- [ ] RA 2026 yang dicatat Perencanaan sebagai data awal dikecualikan dari gerbang kegiatan minimal dan RAB; kegiatan TW IV ditambahkan lewat klaim tahap pengukuran atau buka-kembali RA oleh Perencanaan; kegiatan data awal dan kegiatan pada periode lampau boleh tanpa anggaran (tercatat belum diisi). *(Q46 §46.1 butir 3)*
- [ ] Target RA periode lampau boleh kosong dan dicatat sebagai tidak ditargetkan, berbeda dari `0`; target TW I–III yang tertulis 0 pada Rencana Aksi 2026 dicatat kosong. *(Q46 §46.1 butir 6, Q47 §47.2 butir 4)*
- [ ] Data awal 2026 diisi Perencanaan dari dokumen resmi 2026: target PK, target periode dan kegiatan RA, serta realisasi, narasi, dan rekomendasi TW I–II; data yang belum dikonfirmasi mengikuti asumsi kerja dan tidak dipakai di produksi sebelum dikonfirmasi Perencanaan. *(Q46 §46.1 butir 6)*
- [ ] 2025 tidak dibuat sebagai baris Jadwal/Pengukuran; hanya baseline.
- [ ] Audit tetap mencatat aktor dan perubahan.

### US-03.06 · Revisi Resmi Jendela PIC

> **Q41 (13 Oktober 2026):** jendela penyusunan RA tidak ada lagi; revisi jendela PIC pada story ini berlaku untuk jendela pengisian pengukuran, dan Q18 tetap berlaku untuk jendela tersebut (Q41 §41.1 butir 3). Kunci RA per periode bagi PIC mengikuti `pengisian_selesai` yang berlaku, sehingga perpanjangan resmi jendela pengisian periode P ikut menggeser kunci RA periode P (Q41 §41.2 butir 5; US-05.07). AC-6 ditambahkan karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-03.06` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Periode, Jadwal, Snapshot, Efektivitas, dan Backfill |
| **Dependensi** | Jadwal ada; perubahan deadline memiliki alasan dan batas baru. |
| **Otorisasi** | `jadwal:update`. |
| **Dampak Data** | `jadwal_tahunan`/`jadwal_periode`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** memperpanjang atau membuka ulang jendela kerja PIC secara resmi,  
> **Sehingga** pengecualian tenggat tidak dilakukan diam-diam dan tetap terbatas waktu.

**Acceptance Criteria**

- [ ] **AC-1:** Given deadline PIC perlu diubah, When Perencanaan mengisi alasan dan batas baru, Then jendela resmi diperbarui dan audit menyimpan batas lama/baru.
- [ ] **AC-2:** Given PIC mencoba bekerja di luar jendela yang berlaku, Then ditolak meskipun jadwal berstatus aktif.
- [ ] **AC-3:** Given jadwal sebelumnya ditutup dan sedang dalam sesi koreksi, When jendela PIC dibuka, Then jendela baru wajib berada di dalam `koreksi_mulai..koreksi_sampai` dan hanya untuk lingkup yang diizinkan.
- [ ] **AC-4:** Given jendela dibuka untuk PIC, Then grant unit, PIC efektif, deny, status record, dan gerbang kelengkapan tetap wajib.
- [ ] **AC-5:** Given `jadwal:buka_kembali` dilakukan tanpa revisi jendela PIC, Then PIC tetap tidak memperoleh akses koreksi otomatis.
- [ ] **AC-6:** Given `pengisian_selesai` periode P diperpanjang secara resmi, Then kunci Rencana Aksi periode P bagi PIC ikut bergeser ke batas baru. *(Q41 §41.2 butir 5)*

---

## Bagian 4 — Penugasan Penanggung Jawab (PIC)

### US-04.01 · Penetapan, Pergantian, dan Resolusi PIC Efektif

> **Q32:** PJ dapat menunjuk user aktif mana pun. Assignment tidak memberi permission; calon PJ dengan izin kerja belum lengkap diberi warning dan masuk daftar “PJ aktif tanpa hak isi”.
>
> **Q43 (13 Oktober 2026):** kolom alasan pada penetapan dan pergantian Penanggung Jawab dihapus dari form dan request tanpa alasan diterima; kolom basis datanya sudah nullable (Q43 §43.3 butir 4). Kewajiban alasan pada AC-1 dan AC-5 digantikan dan perubahannya dikerjakan pada US-13.05; audit aksi sensitif tetap dicatat (Q43 §43.1).

| Field | Detail |
|---|---|
| **ID** | `US-04.01` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 8 |
| **Modul** | Penugasan Penanggung Jawab (PIC) |
| **Dependensi** | Indikator dan pengguna aktif. |
| **Otorisasi** | `penanggung_jawab:update` (sensitif). |
| **Dampak Data** | `penanggung_jawab`, `audit_log`. |

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** menetapkan dan mengganti PIC indikator secara append-only dengan tanggal mulai berlaku,  
> **Sehingga** hak kerja mengikuti penanggung jawab efektif tanpa menghapus sejarah penugasan.

**Acceptance Criteria**

- [x] **AC-1:** Given indikator, pengguna, tanggal mulai, dan alasan valid, When penugasan disimpan, Then baris baru `penanggung_jawab` dibuat tanpa update in-place histori lama.
- [x] **AC-2:** Given beberapa penugasan historis ada, When PIC efektif dicari pada tanggal T, Then sistem memilih baris terbaru dengan `tanggal_mulai_berlaku <= T`.
- [x] **AC-3:** Given PIC berganti, When request RA/Pengukuran baru dilakukan, Then guard memakai PIC efektif terkini dan grant unit yang sah.
- [x] **AC-4:** Given versi RA/Pengukuran lama sudah diajukan, When PIC berganti, Then `diajukan_by`/provenance versi lama tidak berubah.
- [x] **AC-5:** Given alasan pergantian kosong, When disimpan, Then ditolak dan aksi sensitif diaudit.
- [x] **AC-6 (Q32):** Given target user aktif dengan role apa pun atau belum memiliki role, When ditetapkan sebagai PJ, Then assignment diperbolehkan tanpa memberi permission; kesiapan tujuh izin kerja dinilai terpisah melalui PermissionResolver.


> Checkbox AC mencatat status implementasi/verifikasi teknis. Persetujuan kebijakan PJ-01–PJ-05 berasal dari Q34 dan ditelusuri pada matriks provenance ISS-04.01. AC-3 terverifikasi untuk Rencana Aksi dan Pengukuran, termasuk pergantian pada tanggal yang sama; AC-4 terverifikasi untuk versi pengajuan Rencana Aksi melalui `test_pengaju_berbeda_dari_pembuat_dan_provenance_tidak_berubah_saat_pj_berganti` (ISS-05.03).

**Kontrak Final Q32**

- `penanggung_jawab` tetap menjadi sumber histori assignment indikator.
- Peran pengguna dan assignment `penanggung_jawab` merupakan dua data yang berbeda; tidak ada syarat role PIC.
- Monitoring menampilkan izin tersedia dan kurang untuk semua tujuh permission scoped pada unit indikator menurut ACL saat ini. Explicit deny menang dan user nonaktif fail-closed. Diagnosis tidak menjadi gate pengganti resolver saat aksi dilakukan.
- Bukti reuse Rencana Aksi pada AC-4 berasal dari versi pengajuan Rencana Aksi (ISS-05.03, `test_pengaju_berbeda_dari_pembuat_dan_provenance_tidak_berubah_saat_pj_berganti`); guard AC-3 dan reuse Plan 4.4 sudah memakai resolver PJ yang sama.

**Kontrak Dasar Histori dan Resolusi — Plan §4.3–4.4 / Issue #54:** pergantian wajib alasan *(kewajiban alasan digantikan Q43 §43.3 butir 4; US-13.05)* dan menambah histori; resolver memilih tanggal mulai terbesar yang <= tanggal acuan, lalu `urutan` penugasan terbesar pada tanggal yang sama.

**Aturan PJ — Diratifikasi Q34; butir 1 (pergantian di tanggal yang sama) diimplementasikan pada [#70](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/70):** PJ-01 (tanggal mutasi lampau/mendatang), PJ-03 (no-op ditolak dan mantan PJ boleh kembali), PJ-04 (assignment mendatang dipertahankan), dan PJ-05 (guard indikator arsip/Renstra diarsipkan/unit nonaktif) sesuai Q34 §34.1 butir 2–6. PJ-02 (unique indikator/tanggal tanpa tie-break) digantikan butir 1 dan diimplementasikan pada #70 dengan kolom `urutan`. Detail evidence dan dampak berada pada [matriks provenance ISS-04.01](SAKIP%20-%20User%20Issues.md#status-review-dan-traceability-iss-0401--8-oktober-2026).

**Status review 8 Oktober 2026:** dua MINOR UI (kontras canonical Badge success dan reuse formatter timestamp histori) tetap **FIXED**, sebagaimana [re-review PR #68 pada `a51cedc`](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/68#issuecomment-6056081808). MAJOR provenance PJ-01–PJ-05 (tanggal mutasi, unique tanggal, no-op/reassignment, histori mendatang, dan lifecycle) **RESOLVED oleh Q34 (9 Oktober 2026)**; baseline, evidence, inferensi engineering, dan dampak kode dipetakan pada `SAKIP - User Issues.md`, bagian **Status Review dan Traceability ISS-04.01**. PJ-02 digantikan Q34 butir 1 dan dilacak [#70](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/70). AC-3 terverifikasi melalui #70 (10 Oktober 2026) dan AC-4 untuk Rencana Aksi Modul 11 melalui `test_pengaju_berbeda_dari_pembuat_dan_provenance_tidak_berubah_saat_pj_berganti` pada ISS-05.03 (11 Oktober 2026); catatan ini tidak mengubah requirement/checkbox atau mengklaim persetujuan domain, CI perubahan lokal, merge, UAT, maupun deployment.

**Tindak lanjut review M-02/M-03:** audit penolakan aktor nonaktif dan continuation monitoring dengan batas kandidat telah diperbaiki serta diverifikasi lokal. Cakupan PJ efektif yang akunnya nonaktif (D-01) tetap **PENDING REQUIREMENT CLARIFICATION** dengan filter existing dipertahankan sementara; status dan pilihan keputusan berada pada bagian traceability ISS-04.01 di User Issues. Tidak ada perubahan AC atau keputusan bisnis dari tindak lanjut teknis ini.

---


> **Catatan istilah untuk Bagian 5–14:** kecuali sebuah story secara eksplisit membahas `user_roles`, istilah **PIC** berarti **PIC operasional/Penanggung Jawab efektif** pada konteks indikator/unit tersebut. Role `pic` yang baru dikonfirmasi Q31 tidak otomatis menjadi allow terhadap semua aksi; resolver permission, grant/deny, assignment, jendela, status, F1/F2, dan gate tetap berlaku.

## Bagian 5 — Penyusunan, Pengajuan, Verifikasi, dan Pengesahan Rencana Aksi

### US-05.01 · Penyusunan Target Rencana Aksi per Periode

> **Q41, Q42, Q47 (13 Oktober 2026):** Rencana Aksi tidak lagi memakai jendela penyusunan; PIC bekerja sejak jadwal aktif sampai `penutupan`, dan periode terkunci bagi PIC setelah `pengisian_selesai` periode tersebut (Q41 §41.1–§41.2). AC-5 yang menolak PIC di luar jendela RA digantikan dan perubahannya dikerjakan pada US-05.07. Rencana Aksi juga memuat daftar kegiatan per periode beserta anggarannya (Q42 §42.1; US-06.02, US-06.08). Untuk indikator dinilai tahunan, target periode sebelum periode nilai akhir boleh kosong dan berbeda dari `0` (Q47 §47.2; US-05.07).

| Field | Detail |
|---|---|
| **ID** | `US-05.01` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Penyusunan, Pengajuan, Verifikasi, dan Pengesahan Rencana Aksi |
| **Dependensi** | Jadwal aktif; daftar periode tersedia; snapshot indikator tersedia; PIC memenuhi grant unit + PIC efektif atau Perencanaan memiliki izin global. |
| **Otorisasi** | `rencana_aksi:create`, `rencana_aksi:update`. |
| **Dampak Data** | `rencana_aksi`, `rencana_aksi_target`, `audit_log`. |

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** mengisi target kumulatif per periode secara manual atau per komponen sesuai snapshot,  
> **Sehingga** target tahunan dapat diturunkan menjadi target operasional tanpa kehilangan konsistensi formula.

> **Status implementasi — PR #62:** AC-1–5 memiliki bukti QA pada `7ec7007`; AC-6 terverifikasi pada pengajuan ISS-05.03 melalui `test_deviasi_tanpa_alasan_ditolak_dan_dengan_alasan_diterima` (ADR-0008). Batas bukti tercatat pada [ISS-05.01](SAKIP%20-%20User%20Issues.md#iss-0501--feature-penyusunan-target-rencana-aksi-per-periode).

**Acceptance Criteria**

- [x] **AC-1:** Given indikator manual, When target periode diisi, Then satu target langsung per periode disimpan dengan `komponen_id = NULL`.
- [x] **AC-2:** Given indikator nonmanual, When target diisi, Then target disimpan per komponen snapshot dan nilai turunan dihitung server.
- [x] **AC-3:** Given target periode lebih rendah dari periode sebelumnya, When disimpan, Then sistem memberi warning kumulatif namun tidak memblokir.
- [x] **AC-4:** Given periode sebelum efektivitas indikator, When target diminta, Then periode tersebut dikecualikan sebagai Tidak berlaku.
- [x] **AC-5:** Given PIC berada di luar jendela RA, When mutasi dicoba, Then ditolak; Perencanaan dapat bekerja sampai penutupan/jendela koreksi yang sah.
- [x] **AC-6:** Given target akhir berbeda dari target PK snapshot, Then submit tetap dapat dilakukan hanya setelah alasan deviasi diisi.

### US-05.02 · Pemenuhan Bukti Dukung Rencana Aksi

| Field | Detail |
|---|---|
| **ID** | `US-05.02` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Penyusunan, Pengajuan, Verifikasi, dan Pengesahan Rencana Aksi |
| **Dependensi** | RA berstatus dapat diedit; persyaratan bukti tersedia. |
| **Otorisasi** | Capability berkas mengikuti kewenangan induk RA; deny berkas tetap berlaku. |
| **Dampak Data** | `berkas`, `audit_log`. |

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** memenuhi persyaratan bukti RA dengan mode file, tautan, atau teks yang diizinkan,  
> **Sehingga** rencana aksi memiliki dasar dokumen yang dapat diverifikasi.

**Acceptance Criteria**

- [x] **AC-1:** Given persyaratan tahap `rencana_aksi`, When bukti mode valid disimpan, Then `berkas` terhubung ke RA dan jenis persyaratan yang tepat.
- [x] **AC-2:** Given mode tidak diizinkan, When bukti dikirim, Then server menolak.
- [x] **AC-3:** Given `semua_mode_wajib = true`, When sebagian mode belum terpenuhi, Then persyaratan tetap belum lengkap.
- [x] **AC-4:** Given deny/capability induk tidak mengizinkan upload, When request dipanggil langsung, Then 403.
- [x] **AC-5:** Given bukti sudah dibekukan dalam versi pengajuan, Then perubahan setelahnya tidak mengubah snapshot versi tersebut. *(PR #76 membuktikan penghapusan bukti non-destruktif — soft delete dan file fisik dipertahankan; pembekuan bukti ke dalam versi terverifikasi melalui `test_versi_beku_tidak_berubah_saat_draf_dan_bukti_berubah` pada ISS-05.03.)*

> **Status implementasi — PR #76 (ISS-05.02):** centang menunjukkan bukti implementasi dan test (`tests/Feature/RencanaAksi/RencanaAksiBuktiHttpTest`, `BuktiRencanaAksiEvaluasiTest`, `tests/Frontend/RencanaAksiBukti.test.tsx`) beserta CI exact-HEAD pada PR; bukan persetujuan UAT maupun deployment.

### US-05.03 · Pengajuan Rencana Aksi & Pembekuan Versi

> **Q41, Q42, Q47 (13 Oktober 2026):** pengajuan RA kini juga mensyaratkan minimal satu kegiatan yang diklaim pada tahap RA (Q42 §42.1 butir 3; US-06.02) serta kelengkapan persyaratan tahap `kegiatan_perencanaan`, termasuk RAB untuk kegiatan beranggaran lebih dari 0 (Q42 §42.3–§42.4; US-06.08). Pengajuan oleh PIC tidak boleh mengubah periode yang terkunci baginya (Q41 §41.2; US-05.07). AC-1 dibaca bersama Q47 §47.2: indikator dinilai tahunan boleh tanpa target pada periode sebelum periode nilai akhir (US-05.07). Aturan Q41 §41.3 tetap: pengukuran mensyaratkan RA disahkan, Perencanaan boleh mengesahkan RA yang diinputnya sendiri dengan penanda self-approval, target kumulatif yang turun tetap peringatan, dan deviasi target periode terakhir terhadap target PK wajib dijelaskan (ADR-0008). Catatan AC-5 tentang klaim yang belum dibekukan ditindaklanjuti setelah kegiatan dapat ditambahkan dari halaman RA (US-06.02).

| Field | Detail |
|---|---|
| **ID** | `US-05.03` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Penyusunan, Pengajuan, Verifikasi, dan Pengesahan Rencana Aksi |
| **Dependensi** | RA draft/dikembalikan; target dan bukti memenuhi aturan; jendela aktor sah. |
| **Otorisasi** | `rencana_aksi:ajukan` (unit-scoped bagi PIC; global bagi Perencanaan). |
| **Dampak Data** | `rencana_aksi`, `rencana_aksi_versi`, `audit_log`. |

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** mengajukan RA sebagai versi substansi yang immutable,  
> **Sehingga** review dan laporan selalu menilai data persis seperti saat diajukan.

**Acceptance Criteria**

- [x] **AC-1:** Given target wajib/periode berlaku belum lengkap, When submit, Then pengajuan ditolak.
- [x] **AC-2:** Given bukti wajib belum lengkap sesuai versi persyaratan saat submit, When submit, Then ditolak.
- [x] **AC-3:** Given deviasi target akhir terhadap PK belum memiliki alasan, When submit, Then ditolak.
- [x] **AC-4:** Given seluruh gerbang lolos, When submit commit, Then status header menjadi `diajukan` dan **baris baru `rencana_aksi_versi`** dibuat.
- [x] **AC-5:** Given versi dibuat, Then server membekukan `diajukan_by`, `diajukan_at`, `jalur_pengajuan` (`pic`/`perencanaan`), `dasar_izin_pengajuan`, target, persyaratan, bukti, klaim/narasi yang relevan, dan snapshot konteks. *Catatan: klaim/narasi kegiatan belum dibekukan karena modul Kegiatan belum memiliki writer klaim (non-goal #57).*
- [x] **AC-6:** Given draft dibuat oleh A tetapi diajukan B, Then `diajukan_by = B`; `created_by` tidak dipakai sebagai identitas pengaju F1/F2.
- [ ] **AC-7:** Given submit berhasil, Then RA tampil pada antrean reviu berbasis status (ISS-05.04/ISS-05.05); item notifikasi in-app mengikuti ISS-12.02 AC-2.

### US-05.04 · Verifikasi & Pengembalian Rencana Aksi dengan F1

| Field | Detail |
|---|---|
| **ID** | `US-05.04` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Penyusunan, Pengajuan, Verifikasi, dan Pengesahan Rencana Aksi |
| **Dependensi** | ISS-05.03 / #57 sudah merged ke `development`; RA berstatus `diajukan` dan versi terbaru tersedia. Pengiriman in-app menunggu ISS-12.02 (Q37). |
| **Otorisasi** | `rencana_aksi:verifikasi`, `rencana_aksi:kembalikan`. |
| **Dampak Data** | `rencana_aksi`, versi terkait, `audit_log`, event pengembalian setelah commit. |
| **Keputusan terkait** | Q37 §37.1–§37.3. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** memverifikasi versi RA atau mengembalikannya dengan alasan,  
> **Sehingga** substansi RA diperiksa tanpa membuka celah self-review jalur PIC.

**Bukti verifikasi:** hasil pengujian dan batas QA/UAT dicatat pada [PR #93](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/93); TEST-1–TEST-7 tercatat pada ISS-05.04. Checklist di bawah tetap untuk QA/UAT; hasil otomatis tidak mengesahkan UAT, merge atau deployment.

**Acceptance Criteria**

- [ ] **AC-1:** Given versi diajukan melalui `jalur_pengajuan = pic`, When aktor sama dengan `rencana_aksi_versi.diajukan_by` mencoba **memverifikasi**, Then F1 menolak walaupun aktor kemudian memperoleh role/grant lain.
- [ ] **AC-2:** Given aktor berbeda dan memiliki permission efektif, When verifikasi dilakukan, Then status menjadi `diverifikasi` tanpa mengubah payload versi.
- [ ] **AC-3:** Given perbaikan dibutuhkan, When dikembalikan dengan alasan valid, Then status menjadi `dikembalikan`, audit mencatat alasan dan pengaju historis, PJ efektif saat pengembalian ditentukan melalui `PenugasanIndikator::effectiveOn()`, dan event pengembalian dipancarkan setelah commit. **Catatan Q37:** pengiriman in-app menunggu ISS-12.02; cakupan issue ini adalah penentuan penerima dan event setelah commit, dengan otorisasi tautan tetap diperiksa saat dibuka.
- [ ] **AC-4:** Given pengembalian tanpa alasan, When submit, Then ditolak.
- [ ] **AC-5:** Given data substansi perlu diubah saat review, Then data harus dikembalikan lalu diajukan sebagai versi baru; snapshot versi lama tidak ditambal.
- [ ] **AC-6:** Given indikator `arsip` atau unit konteks RA `nonaktif`, When verifikasi/pengembalian dicoba termasuk melalui request langsung, Then `businessErrors` menolak, penolakan diaudit, dan status/payload versi tidak berubah; detail/histori tetap dapat dibaca sesuai otorisasi dan UI menampilkan alasan dari server.
- [ ] **AC-7:** Given versi/status valid, aktor berwenang, dan seluruh business guard lolos, When verifikasi dikonfirmasi tanpa alasan pengguna, Then verifikasi berhasil dan audit memuat alasan baku server serta `dasar_izin`.

### US-05.05 · Pengesahan Rencana Aksi & Pemisahan Tugas F1/F2

> **Q41 (13 Oktober 2026):** Perencanaan tetap boleh mengesahkan RA yang diinputnya sendiri dengan penanda self-approval (F2), sedangkan jalur PIC tetap dijaga F1 (Q41 §41.3 butir 2); AC-3 tidak berubah.

| Field | Detail |
|---|---|
| **ID** | `US-05.05` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Penyusunan, Pengajuan, Verifikasi, dan Pengesahan Rencana Aksi |
| **Dependensi** | RA berstatus `diverifikasi`; versi yang direviu adalah versi terbaru dan tidak stale. |
| **Otorisasi** | `rencana_aksi:sahkan` (sensitif). |
| **Dampak Data** | `rencana_aksi`, `rencana_aksi_versi`, `audit_log`. |
| **Keputusan terkait** | Q37 §37.1. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengesahkan versi RA yang telah diverifikasi,  
> **Sehingga** RA menjadi dokumen operasional resmi dengan provenance yang dapat diaudit.

**Acceptance Criteria**

- [x] **AC-1:** Given versi diajukan melalui jalur PIC, When aktor sama dengan `diajukan_by` mencoba mengesahkan, Then **F1 menolak**.
- [x] **AC-2:** Given versi diajukan oleh PIC dan aktor Perencanaan lain berizin, When sahkan, Then header menjadi `disahkan` dan metadata pengesahan pada versi terisi atomik.
- [x] **AC-3:** Given versi diajukan melalui `jalur_pengajuan = perencanaan`, When pengaju yang sama memverifikasi/mengesahkan dan masih memiliki permission efektif, Then **F2 mengizinkan** serta mencatat `self_approval`.
- [x] **AC-4:** Given terdapat deny atau permission reviewer hilang, When F2 dicoba, Then tetap ditolak; F2 bukan bypass resolver.
- [x] **AC-5:** Given versi yang hendak disahkan bukan versi terbaru yang sedang direviu, When aksi dilakukan, Then ditolak untuk mencegah pengesahan payload stale.
- [x] **AC-6:** Given RA disahkan, Then bukti yang dirujuk versi resmi tidak boleh dihapus.
- [x] **AC-7:** Given indikator `arsip` atau unit konteks RA `nonaktif`, When pengesahan dicoba termasuk pada jalur F2 dan request langsung, Then `businessErrors` yang sama dengan verifikasi/pengembalian menolak, penolakan diaudit, dan header/metadata versi tidak berubah; detail/histori tetap dapat dibaca sesuai otorisasi dan UI menampilkan alasan dari server.

> **Status implementasi — PR #66 (ISS-05.05):** centang menunjukkan bukti implementasi dan test (`tests/Feature/RencanaAksi/SahkanRencanaAksiTest`, `tests/Integration/Concurrency/MutationConcurrencyTest`, `tests/Frontend/RencanaAksiReview.test.tsx`) beserta CI exact-HEAD pada PR; bukan persetujuan UAT maupun deployment.

### US-05.06 · Buka-Kembali Rencana Aksi yang Telah Disahkan

> **Q40, Q41 (13 Oktober 2026):** buka-kembali dipakai untuk mengubah target periode yang belum terkunci, bukan untuk memindahkan kegiatan, dan versi setelah dibuka kembali tetap tunduk kunci per periode bagi PIC (Q41 §41.2, §41.4). AC-3 diperbarui serta AC-6 dan AC-7 ditambahkan langsung karena story ini sedang dikerjakan dan belum di-merge.

| Field | Detail |
|---|---|
| **ID** | `US-05.06` |
| **Prioritas** | 🟡 P1 · Tahap 1 (Q46 §46.3; sedang berjalan) |
| **Story Points** | 5 |
| **Modul** | Penyusunan, Pengajuan, Verifikasi, dan Pengesahan Rencana Aksi |
| **Dependensi** | RA disahkan; sebelum penutupan atau di dalam sesi koreksi yang sah. |
| **Otorisasi** | `rencana_aksi:buka_kembali` (sensitif). |
| **Dampak Data** | `rencana_aksi`, versi historis, `audit_log`. |

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** membuka RA disahkan kembali ke jalur revisi dengan alasan wajib,  
> **Sehingga** koreksi resmi dapat dilakukan tanpa menghapus versi yang sudah pernah disahkan.

**Acceptance Criteria**

- [x] **AC-1:** Given alasan valid dan waktu koreksi sah, When buka kembali dilakukan, Then header berpindah ke `dikembalikan`.
- [x] **AC-2:** Given versi lama telah disahkan, When RA dibuka kembali, Then versi lama tetap immutable dan tetap dapat dibaca sebagai histori.
- [x] **AC-3:** Given PIC perlu memperbaiki sebelum `penutupan`, Then PIC dapat bekerja tanpa jendela penyusunan RA selama grant + PIC efektif valid, tetapi hanya pada periode yang belum terkunci baginya. *(Diperbarui Q41 §41.1–§41.2; semula PIC hanya dapat bekerja bila jendela PIC resmi masih/baru dibuka. Terbukti CI exact-HEAD run 38533032704 9/9.)*
- [x] **AC-4:** Given jadwal ditutup tanpa sesi koreksi, When buka kembali RA langsung dicoba, Then ditolak.
- [x] **AC-5:** Given RA diajukan ulang setelah perbaikan, Then baris `rencana_aksi_versi` baru dibuat.
- [ ] **AC-6:** Given RA dibuka kembali, When PIC menyimpan draf atau mengajukan versi baru yang mengubah target atau daftar kegiatan periode yang sudah terkunci baginya, Then ditolak sebagai validasi bisnis (422) dan diaudit; Perencanaan dikecualikan sampai `penutupan`. *(Q41 §41.2 butir 2–3; guard level target terbukti, klausa daftar kegiatan menunggu writer klaim ISS-06.02/06.08 — review 14 Oktober 2026)*
- [ ] **AC-7:** Given sesi koreksi setelah penutupan, When PIC mengubah RA, Then perubahan hanya diizinkan pada periode yang jendela pengisiannya dibuka kembali secara resmi (US-14.03); koreksi RA di luar periode tersebut dilakukan Perencanaan. *(Q41 §41.2 butir 6, Q19. Menunggu CI exact-HEAD M1.)*

> **Status implementasi — ISS-05.06 (#85):** centang menunjukkan bukti implementasi dan test (`tests/Feature/RencanaAksi/BukaKembaliRencanaAksiTest`, regresi RA, `tests/Frontend/RencanaAksiBukaKembali.test.tsx`, `tests/Frontend/RencanaAksiKoreksi.test.tsx`, `tsc`, Pint, PHPStan bersih) pada branch `feature/iss-05.06-buka-kembali-ra`; bukan persetujuan UAT, merge, maupun deployment. Selaras Q41/Q43. Review 14 Oktober 2026: AC-6 dikembalikan `[ ]` karena frasa "atau daftar kegiatan" baru ditegakkan pada level target periode (writer klaim tahap RA milik ISS-06.02/06.08). AC-3 dan AC-7 dikembalikan `[ ]` menunggu CI exact-HEAD untuk kunci berbasis perubahan (B1) dan lingkup koreksi berbasis perubahan (M1).

**Business Rules / Catatan**

- Buka-kembali dipakai bila target periode yang belum terkunci perlu diubah (Q41 §41.4 butir 4), termasuk setelah revisi PK mengubah target (Q40 §40.1 butir 7). RA yang sudah disahkan tidak dibuka kembali hanya untuk memindahkan kegiatan; kegiatan yang mundur ditangani US-06.04 dan US-06.05 (Q41 §41.4 butir 3).
- Untuk data awal 2026, kegiatan TW IV dapat ditambahkan lewat buka-kembali RA oleh Perencanaan (Q46 §46.1 butir 3).
- Dalam sesi koreksi setelah penutupan, PIC hanya dapat mengubah RA pada periode yang jendela pengisiannya dibuka kembali secara resmi (US-14.03, Q19); di luar itu koreksi RA dilakukan Perencanaan (Q41 §41.2 butir 6).

### US-05.07 · Penyusunan Rencana Aksi Sepanjang Tahun, Kunci per Periode, dan Periode tanpa Target

| Field | Detail |
|---|---|
| **ID** | `US-05.07` |
| **Prioritas** | Tahap 1 (Q46 §46.3: sebelum integrasi 9 November 2026) |
| **Story Points** | Belum diestimasi |
| **Modul** | Penyusunan, Pengajuan, Verifikasi, dan Pengesahan Rencana Aksi |
| **Dependensi** | US-05.01 (target RA), US-05.03 (pengajuan RA), US-03.01/03.02 (jadwal dan snapshot), US-02.04 (form indikator untuk penanda). |
| **Otorisasi** | `rencana_aksi:create`/`update`/`ajukan` (PIC dengan grant unit sah dan PIC efektif; Perencanaan global); penanda dinilai tahunan: `indikator:update`. |
| **Dampak Data** | `jadwal_tahunan.rencana_aksi_mulai/selesai` (tidak dibaca, dihapus dari form, kolom nullable), `indikator.dinilai_tahunan`, `jadwal_snapshot.dinilai_tahunan`, `rencana_aksi_target`, `audit_log`. |
| **Keputusan terkait** | Q41, Q47 §47.1–§47.2, Q46 §46.1. |

> **Sebagai** PIC dan Tim Perencanaan,  
> **Saya ingin** menyusun dan mengubah Rencana Aksi kapan pun selama jadwal tahun berjalan aktif, dengan periode yang sudah lewat terkunci, serta mengosongkan target periode untuk indikator yang dinilai tahunan,  
> **Sehingga** Rencana Aksi mengikuti praktik penyusunan LLDIKTI tanpa mengubah periode yang sudah dilaporkan.

**Acceptance Criteria**

- [ ] **AC-1:** Given jadwal tahun berstatus `aktif` dan `penutupan` belum lewat, When PIC efektif dengan grant sah membuat, mengubah, atau mengajukan RA, Then aksi diizinkan tanpa memeriksa jendela penyusunan; `rencana_aksi_mulai`/`rencana_aksi_selesai` tidak lagi dibaca. *(Q41 §41.1 butir 1)*
- [ ] **AC-2:** Given form jadwal tahunan, Then isian jendela penyusunan RA tidak ditampilkan dan tidak divalidasi; kolomnya dibiarkan nullable. *(Q41 §41.1 butir 2)*
- [ ] **AC-3:** Given `pengisian_selesai` periode P sudah lewat (tanggal bisnis Asia/Makassar), When PIC menyimpan draf, mengajukan, atau menyimpan versi baru RA yang mengubah target atau daftar kegiatan periode P, Then ditolak sebagai validasi bisnis (422) dan diaudit; perubahan pada periode yang belum terkunci tetap diterima. *(Q41 §41.2 butir 1–2)*
- [ ] **AC-4:** Given aktor Perencanaan, When mengubah periode yang terkunci bagi PIC sebelum `penutupan`, Then diizinkan. *(Q41 §41.2 butir 3)*
- [ ] **AC-5:** Given periode lampau saat jadwal diaktifkan, Then periode itu terkunci bagi PIC sejak aktivasi. *(Q41 §41.2 butir 4, Q46 §46.1)*
- [ ] **AC-6:** Given Perencanaan menandai indikator sebagai dinilai tahunan, When disimpan, Then penanda tersimpan dan diaudit; snapshot jadwal yang diaktifkan sesudahnya membekukan penanda tersebut, dan perubahan setelah jadwal aktif mengikuti koreksi snapshot. *(Q47 §47.1)*
- [ ] **AC-7:** Given indikator dinilai tahunan menurut snapshot, When RA diajukan dengan target periode sebelum periode nilai akhir kosong, Then pengajuan diterima; target periode nilai akhir tetap wajib dan direkonsiliasi dengan target PK (ADR-0008). *(Q47 §47.2 butir 1)*
- [ ] **AC-8:** Given indikator yang tidak dinilai tahunan, When salah satu target periode kosong saat pengajuan, Then pengajuan ditolak dengan pesan per periode. *(Q47 §47.2 butir 2)*
- [ ] **AC-9:** Given target periode dikosongkan, Then disimpan sebagai tidak ditargetkan dan dibedakan dari target `0`. *(Q47 §47.2 butir 3)*
- [ ] **AC-10:** Given RA belum disahkan, When pengukuran periode mana pun diajukan, Then tetap ditolak; target kumulatif yang turun tetap berupa peringatan. *(Q41 §41.3 butir 1 dan 3)*

**Business Rules / Catatan**

- Perencanaan boleh mengesahkan RA yang diinputnya sendiri dengan penanda self-approval (Q41 §41.3 butir 2).
- Kegiatan yang mundur ditangani US-06.04/06.05 tanpa buka-kembali RA (Q41 §41.4); buka-kembali RA (US-05.06) hanya untuk target periode yang belum terkunci.
- Gerbang baru dikecualikan untuk periode lampau (Q46 §46.1).
- Data awal 2026: target TW I–III dicatat kosong (Q47 §47.2 butir 4).
- Pada versi RA yang dibekukan, periode tanpa target dicatat dengan status `tidak_ditargetkan`, bukan `belum_diisi`, sehingga laporan dapat membedakannya dari target yang belum diisi (Q47 §47.2 butir 5).
- Kunci mengikuti `pengisian_selesai` yang berlaku, termasuk hasil revisi resmi jendela pengisian (Q18, US-03.06): perpanjangan jendela pengisian periode P ikut menggeser kunci RA periode P bagi PIC (Q41 §41.2 butir 5).
- **Di luar cakupan:** kegiatan dalam RA (US-06.02, US-06.08); tampilan periode tanpa target pada pengukuran dan dashboard (US-07.03, US-10.01).

---

## Bagian 6 — Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan

### US-06.01 · Pencatatan Rencana Kegiatan Unit

> **Q42, Q49 (13 Oktober 2026):** `nama` kegiatan hingga 500 karakter dan `tujuan` opsional diterapkan oleh PR #97 (migrasi `2026_10_14_000001`, validasi request, dan form; Q49 §49.1). PR #97 tidak diperluas untuk anggaran: input, tampilan, dan penjumlahan anggaran dikerjakan pada US-06.08 (Q42 §42.6). Kegiatan juga ditambahkan dari halaman Rencana Aksi per periode melalui US-06.02 (Q42 §42.1 butir 2). Detail kegiatan tetap dibatasi unit (Q12, Q49 §49.3).

**Status implementasi (2026-10-14):** Diterapkan oleh PR #97 (migrasi `2026_10_14_000001`, validasi request, dan form). Checklist yang dicentang mencerminkan automated test lokal dan CI PR #97, bukan penerimaan UAT. Penyesuaian Q49 §49.1 mencakup `nama` hingga 500 karakter dan `tujuan` opsional. Penegakan pembatasan detail per unit (PRD §15.1, Q49 §49.3, Workflow §8 butir 9) dan akses baca penerima grant tulis ditunda ke satu issue lintas modul Rencana Aksi + Kegiatan sesuai [keputusan PM PR #97](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/97#issuecomment-6161158080). Requirement tetap berlaku; PR ini mempertahankan baca global dikurangi deny unit. Issue tindak lanjut: [#102](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/102), gerbang sebelum UAT dengan data nyata serta produksi, bukan gerbang merge PR ini. Sementara itu grant kegiatan:create/update hanya diberikan kepada pengguna yang sudah memiliki kegiatan:read; Admin tidak diberi grant kegiatan. Smoke browser desktop/mobile untuk build modal terbaru dan QA/retest SSO oleh Dion belum dilengkapi; item responsivitas dan checklist UI tetap belum dicentang sampai buktinya tersedia. UAT dan deployment belum dilakukan.

| Field | Detail |
|---|---|
| **ID** | `US-06.01` |
| **Prioritas** | 🟡 P1 · Tahap 1 (Q46 §46.3; PR #97 + Q49) |
| **Story Points** | 5 |
| **Modul** | Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan |
| **Dependensi** | Unit aktif dan periode valid. |
| **Otorisasi** | `kegiatan:create`, `kegiatan:update` (unit-scoped bila melalui grant). |
| **Dampak Data** | `kegiatan`, `audit_log`. |

> **Sebagai** PIC/petugas unit berizin atau Tim Perencanaan,  
> **Saya ingin** mencatat kegiatan yang mendukung pencapaian kinerja,  
> **Sehingga** aktivitas operasional dapat ditelusuri per unit dan periode.

**Acceptance Criteria**

- [x] **AC-1:** Given nama (hingga 500 karakter), sasaran peserta, lokasi, dan tanggal valid, dengan tujuan opsional, When disimpan, Then kegiatan terbentuk dengan status `rencana`. *(Diperbarui Q49 §49.1; semula tujuan termasuk isian wajib.)*
- [x] **AC-2:** Given MVP, When form kegiatan ditampilkan, Then **field anggaran tidak ditampilkan, tidak diterima sebagai input bisnis, dan tidak divalidasi**; kolom database boleh tetap nullable untuk fase lanjutan. *(Berlaku untuk PR #97; digantikan Q42 setelah US-06.08: anggaran per kegiatan wajib diinput, ditampilkan, dan dijumlahkan.)*
- [x] **AC-3:** Given pengguna unit memiliki grant `kegiatan:create/update`, When bekerja pada unit yang sama, Then aksi diizinkan tanpa syarat PIC indikator tertentu.
- [x] **AC-4:** Given grant hanya unit A, When kegiatan unit B dimutasi, Then ditolak.

### US-06.02 · Klaim Keterkaitan Kegiatan terhadap Rencana Aksi/Komponen

> **Q41, Q42, Q46, Q49 (13 Oktober 2026):** kegiatan kini ditambahkan dari halaman Rencana Aksi per periode, yang membuat kegiatan sekaligus klaimnya ke RA; pengajuan RA mensyaratkan minimal satu kegiatan yang diklaim pada tahap RA; kegiatan dapat disalin ke periode lain; dan periode yang terkunci bagi PIC tidak dapat ditambah kegiatan. AC-6 sampai AC-10 ditambahkan langsung karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-06.02` |
| **Prioritas** | 🟡 P1 · Tahap 1 (Q46 §46.3) |
| **Story Points** | 5 |
| **Modul** | Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan |
| **Dependensi** | Kegiatan dan RA tersedia pada konteks tahun/unit yang konsisten. |
| **Otorisasi** | `kegiatan:update`/capability sumber dan kewenangan RA sesuai alur. |
| **Dampak Data** | `klaim_kegiatan`, `kegiatan` (penambahan dan salinan dari halaman RA, Q42/Q49), `audit_log`. |

> **Sebagai** PIC/petugas unit berizin atau Tim Perencanaan,  
> **Saya ingin** menghubungkan kegiatan ke RA dan opsional ke komponen indikator,  
> **Sehingga** laporan dapat menjelaskan kontribusi kegiatan tanpa mengubah angka capaian secara otomatis.

**Acceptance Criteria**

- [ ] **AC-1:** Given kegiatan, RA, komponen opsional, arah dampak, dan catatan valid, When disimpan, Then klaim terbentuk.
- [ ] **AC-2:** Given unit kegiatan berbeda dengan unit RA, When klaim dibuat, Then ditolak.
- [ ] **AC-3:** Given klaim identik sudah ada, When dibuat ulang, Then duplikasi ditolak.
- [ ] **AC-4:** Given klaim berhasil, Then nilai komponen/pengukuran **tidak** berubah otomatis.
- [ ] **AC-5:** Given kegiatan batal/tidak terlaksana, Then klaim historis tetap dapat dipertahankan untuk menjelaskan kendala.
- [ ] **AC-6:** Given halaman Rencana Aksi per periode, When PIC atau Perencanaan menambah kegiatan, Then kegiatan (per unit × periode) dan klaimnya ke RA dengan `sumber_klaim = rencana_aksi` dibuat dalam satu aksi. *(Q42 §42.1 butir 2)*
- [ ] **AC-7:** Given RA diajukan tanpa satu pun kegiatan yang diklaim pada tahap RA, When submit, Then pengajuan ditolak; tidak setiap periode wajib berisi kegiatan. *(Q42 §42.1 butir 3)*
- [ ] **AC-8:** Given kegiatan ditambahkan dari halaman RA, When PIC menyalinnya ke satu atau lebih periode lain pada RA yang sama, Then setiap salinan menjadi kegiatan tersendiri per unit × periode dengan klaim `sumber_klaim = rencana_aksi` sendiri; nama, tujuan, dan anggaran ikut disalin dan dapat diubah per kegiatan, sedangkan berkas termasuk RAB tidak ikut disalin. *(Q49 §49.2 butir 1–3)*
- [ ] **AC-9:** Given periode sudah terkunci bagi PIC, When PIC menambah atau menyalin kegiatan ke periode tersebut, Then ditolak. *(Q41 §41.2, Q49 §49.2 butir 4)*
- [ ] **AC-10:** Given RA data awal 2026 atau periode lampau, Then gerbang minimal satu kegiatan dikecualikan. *(Q46 §46.1 butir 1 dan 3)*

**Business Rules / Catatan**

- Satu kegiatan tetap dapat diklaim lebih dari satu RA/IKU dalam unit yang sama, dan klaim lintas unit tetap ditolak (Q42 §42.1 butir 4). Penanda "kegiatan bersama" dan penjumlahan anggaran dari kegiatan unik dikerjakan pada US-06.08 (Q42 §42.2 butir 4).
- Persyaratan tahap perencanaan kegiatan diperiksa untuk setiap salinan (Q49 §49.2 butir 3; US-06.08).
- Kegiatan lanjutan dari kegiatan yang ditunda diklaim pada tahap pengukuran (`sumber_klaim = pengukuran`) melalui US-06.05 (Q41 §41.4 butir 2).

### US-06.03 · Pelaksanaan Kegiatan & Gerbang Bukti Gabungan

> **Q42, Q45 (13 Oktober 2026):** tahap persyaratan `kegiatan` menjadi tahap pelaksanaan kegiatan yang diperiksa saat kegiatan ditandai `terlaksana`, sedangkan tahap perencanaan kegiatan diperiksa saat RA diajukan (Q42 §42.4; US-06.08). Status kegiatan dapat diperbarui sepanjang periode berjalan, misalnya setiap bulan, tanpa entitas bulanan (Q45 §45.1 butir 4). Narasi per kegiatan tetap tersedia dan opsional (Q45 §45.1 butir 2). AC-3 diperbarui dan AC-6 ditambahkan karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-06.03` |
| **Prioritas** | 🟡 P1 · Tahap 2 (Q46 §46.3) |
| **Story Points** | 8 |
| **Modul** | Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan |
| **Dependensi** | Kegiatan berstatus `rencana`; persyaratan global dan indikator klaim dapat dihitung. |
| **Otorisasi** | `kegiatan:update` + capability bukti induk. |
| **Dampak Data** | `kegiatan`, `klaim_kegiatan`, `jenis_berkas`, `berkas`, `audit_log`. |

> **Sebagai** PIC/petugas unit berizin,  
> **Saya ingin** menyelesaikan kegiatan setelah seluruh bukti wajib yang relevan terpenuhi,  
> **Sehingga** status terlaksana hanya diberikan pada kegiatan yang dapat dipertanggungjawabkan.

**Acceptance Criteria**

- [ ] **AC-1:** Given kegiatan hendak menjadi `terlaksana`, When gerbang dievaluasi, Then persyaratan bukti = **union persyaratan global kegiatan + persyaratan indikator yang diklaim** pada tahap `kegiatan` (pelaksanaan). *(Q22, Q42 §42.4 butir 2)*
- [ ] **AC-2:** Given salah satu bukti wajib pada union belum terpenuhi, When status terlaksana disubmit, Then ditolak.
- [ ] **AC-3:** Given tanggal realisasi, peserta riil, dan bukti lengkap, When commit, Then status menjadi `terlaksana`; narasi per kegiatan (`uraian_pelaksanaan`, `kendala`, `strategi_tindaklanjut`) opsional. *(Diperbarui Q45 §45.1 butir 2; semula uraian pelaksanaan, kendala, dan strategi tindak lanjut ikut disyaratkan.)*
- [ ] **AC-4:** Given klaim indikator baru ditambahkan setelah kegiatan sudah terlaksana, When klaim menambah persyaratan bukti, Then sistem kembali memeriksa bukti tambahan dan menandai kebutuhan koreksi bila belum lengkap.
- [ ] **AC-5:** Given kegiatan telah terlaksana, Then bukti yang menjadi dasar hasil tidak dapat dihapus; koreksi memakai pola append-only.
- [ ] **AC-6:** Given periode berjalan, When status kegiatan diperbarui lebih dari sekali (mis. setiap bulan), Then perubahan tersimpan pada kegiatan yang sama tanpa entitas bulanan dan tercatat di audit. *(Q45 §45.1 butir 4)*

### US-06.04 · Kegiatan Tidak Terlaksana, Ditunda, atau Dibatalkan

> **Q41, Q43 (13 Oktober 2026):** kegiatan yang tidak dilaksanakan sesuai rencana ditandai `ditunda` dengan justifikasi dan periode tujuan, lalu dilanjutkan sebagai kegiatan lanjutan (US-06.05); RA yang sudah disahkan tidak dibuka kembali hanya untuk memindahkan kegiatan, dan justifikasi menjadi catatan resmi yang dilihat Perencanaan saat verifikasi pengukuran (Q41 §41.4 butir 1 dan 3). Justifikasi tetap wajib (Q43 §43.2 butir 5). AC-6 ditambahkan karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-06.04` |
| **Prioritas** | 🟡 P1 · Tahap 2 (Q46 §46.3) |
| **Story Points** | 5 |
| **Modul** | Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan |
| **Dependensi** | Kegiatan masih dapat ditransisikan. |
| **Otorisasi** | `kegiatan:update`. |
| **Dampak Data** | `kegiatan`, `audit_log`. |

> **Sebagai** PIC/petugas unit berizin,  
> **Saya ingin** mencatat kegagalan/penundaan kegiatan beserta justifikasi,  
> **Sehingga** kendala operasional tetap terlihat tanpa menghapus kegiatan.

**Acceptance Criteria**

- [ ] **AC-1:** Given status diubah menjadi `tidak_terlaksana`, `ditunda`, atau `batal`, When submit, Then justifikasi wajib.
- [ ] **AC-2:** Given justifikasi kosong, When submit, Then ditolak.
- [ ] **AC-3:** Given status bukan `terlaksana`, Then bukti SPJ pelaksanaan tidak diwajibkan semata-mata untuk transisi tersebut.
- [ ] **AC-4:** Given kegiatan telah diklaim, When laporan dibentuk, Then narasi kegagalan/penundaan tetap dapat muncul sebagai konteks.
- [ ] **AC-5:** Given perubahan status dilakukan, Then audit mencatat nilai lama/baru dan alasan.
- [ ] **AC-6:** Given status diubah menjadi `ditunda`, When submit, Then periode tujuan wajib diisi, dan justifikasi menjadi catatan resmi yang dilihat Perencanaan saat verifikasi pengukuran. *(Q41 §41.4 butir 1 dan 3)*

**Business Rules / Catatan**

- Kegiatan `ditunda` yang sudah memiliki kegiatan lanjutan tidak dihitung dalam total anggaran; anggarannya terhitung pada kegiatan lanjutan (Q42 §42.2 butir 6; US-06.05, US-06.08).
- Periode tujuan hanya periode lain pada tahun yang sama; kegiatan yang mundur ke tahun berikutnya dicatat sebagai kegiatan baru pada Rencana Aksi tahun itu (Q41 §41.4 butir 5).

### US-06.05 · Roll-Over Kegiatan ke Periode Berikutnya

> **Q41 (13 Oktober 2026):** sistem membuat kegiatan lanjutan pada periode tujuan yang tertaut ke kegiatan asal (`kegiatan_asal_id`), dan kegiatan lanjutan diklaim ke Rencana Aksi pada tahap pengukuran (`sumber_klaim = pengukuran`); RA yang sudah disahkan tidak dibuka kembali hanya untuk memindahkan kegiatan (Q41 §41.4 butir 2–3). AC-5 ditambahkan karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-06.05` |
| **Prioritas** | 🟡 P1 · Tahap 2 (Q46 §46.3) |
| **Story Points** | 5 |
| **Modul** | Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan |
| **Dependensi** | Kegiatan asal berstatus `ditunda`; periode tujuan valid. |
| **Otorisasi** | `kegiatan:create`. |
| **Dampak Data** | `kegiatan`, `audit_log`. |

> **Sebagai** PIC/petugas unit berizin,  
> **Saya ingin** menggeser kegiatan ditunda ke periode berikutnya tanpa menghapus kegiatan asal,  
> **Sehingga** jejak penundaan antarperiode dapat ditelusuri.

**Acceptance Criteria**

- [ ] **AC-1:** Given kegiatan ditunda, When roll-over dibuat ke periode tujuan, Then baris kegiatan baru dibuat dengan `kegiatan_asal_id` menunjuk kegiatan lama.
- [ ] **AC-2:** Given baris baru dibuat, Then kegiatan asal tetap `ditunda` dan tidak dihapus.
- [ ] **AC-3:** Given periode tujuan tidak valid/lebih awal, When roll-over dicoba, Then ditolak.
- [ ] **AC-4:** Given roll-over selesai, Then audit menyimpan hubungan asal-tujuan.
- [ ] **AC-5:** Given kegiatan lanjutan dibuat, Then kegiatan tersebut diklaim ke Rencana Aksi pada tahap pengukuran (`sumber_klaim = pengukuran`), dan RA yang sudah disahkan tidak dibuka kembali untuk memindahkan kegiatan. *(Q41 §41.4 butir 2–3)*

**Business Rules / Catatan**

- Kegiatan lanjutan menyalin anggaran kegiatan asal dan anggarannya dapat diubah; kegiatan asal `ditunda` yang sudah memiliki kegiatan lanjutan tidak dihitung dalam total, dan total yang sudah dibekukan pada versi RA tidak berubah (Q42 §42.2 butir 6; US-06.08).
- Kegiatan lanjutan hanya ke periode lain pada tahun yang sama; kegiatan yang mundur ke tahun berikutnya dicatat sebagai kegiatan baru pada Rencana Aksi tahun itu. Berkas kegiatan asal, termasuk RAB, tidak disalin; tautan ke asal lewat `kegiatan_asal_id`, dan persyaratan tahap perencanaan kegiatan tidak diperiksa ulang untuk kegiatan lanjutan yang diklaim pada tahap pengukuran (Q41 §41.4 butir 5).

### US-06.06 · Koreksi Klaim Kegiatan Berdasarkan Sumber Versi

| Field | Detail |
|---|---|
| **ID** | `US-06.06` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan |
| **Dependensi** | Klaim telah digunakan dalam proses RA/Pengukuran. |
| **Otorisasi** | Kewenangan mengikuti sumber yang sedang dikoreksi. |
| **Dampak Data** | `klaim_kegiatan`, versi RA/Pengukuran, `audit_log`. |

> **Sebagai** Tim Perencanaan/PIC sesuai sumber klaim,  
> **Saya ingin** mengoreksi klaim tanpa mengubah versi historis yang sudah diajukan/disahkan,  
> **Sehingga** laporan lama tetap merepresentasikan klaim yang dahulu diperiksa.

**Acceptance Criteria**

- [ ] **AC-1:** Given klaim menjadi bagian snapshot RA, When koreksi dilakukan, Then koreksi mengikuti siklus RA dan baru masuk versi RA berikutnya.
- [ ] **AC-2:** Given klaim menjadi bagian snapshot Pengukuran, When koreksi dilakukan, Then koreksi mengikuti siklus Pengukuran terkait dan baru masuk versi berikutnya.
- [ ] **AC-3:** Given versi historis sudah disahkan, Then klaim yang dibekukan di versi tersebut tidak berubah.
- [ ] **AC-4:** Given klaim dihapus/diganti pada data kerja, Then audit tetap mempertahankan jejak perubahan.

### US-06.07 · Koreksi Bukti Kegiatan secara Append-Only

| Field | Detail |
|---|---|
| **ID** | `US-06.07` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan |
| **Dependensi** | Kegiatan telah memiliki bukti yang sudah digunakan/terkunci. |
| **Otorisasi** | Capability bukti induk + aturan koreksi. |
| **Dampak Data** | `berkas`, `audit_log`. |

> **Sebagai** PIC atau Tim Perencanaan yang berwenang,  
> **Saya ingin** mengganti bukti kegiatan melalui bukti baru tanpa menghapus bukti lama,  
> **Sehingga** auditor dapat melihat bukti awal dan bukti koreksi beserta alasannya.

**Acceptance Criteria**

- [ ] **AC-1:** Given bukti kegiatan perlu dikoreksi setelah mencapai batas imutabilitas, When koreksi sah dilakukan, Then bukti baru ditambahkan dengan alasan dan hubungan pengganti; bukti lama tetap utuh.
- [ ] **AC-2:** Given bukti lama telah dirujuk versi resmi, Then tidak tersedia rollback status untuk menghapusnya.
- [ ] **AC-3:** Given koreksi belum disahkan dalam versi baru, Then laporan resmi tetap membaca bukti versi lama.
- [ ] **AC-4:** Given koreksi menjadi dasar pengajuan baru, Then metadata bukti baru dibekukan pada versi pengajuan baru.

### US-06.08 · Anggaran Kegiatan, RAB, Persyaratan Tahap Perencanaan Kegiatan, dan Penjumlahan Anggaran

| Field | Detail |
|---|---|
| **ID** | `US-06.08` |
| **Prioritas** | Tahap 1 (Q46 §46.3: sebelum integrasi 9 November 2026) |
| **Story Points** | Belum diestimasi |
| **Modul** | Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan; Bukti Dukung Multi-Mode & Integritas Lampiran |
| **Dependensi** | US-06.01 (kegiatan; PR #97), US-06.02 (kegiatan dari halaman RA), US-11.01 (persyaratan berkas), US-05.03 (pengajuan RA). |
| **Otorisasi** | `kegiatan:create`/`update` (scope unit); `jenis_berkas:update` (Perencanaan) untuk tahap dan contoh berkas; baca anggaran mengikuti akses detail kegiatan. |
| **Dampak Data** | `kegiatan.anggaran`, `jenis_berkas.tahap` (+ `kegiatan_perencanaan`), `jenis_berkas.hanya_kegiatan_beranggaran` (Q42 §42.3 butir 3), `berkas` (induk `jenis_berkas` untuk contoh berkas; induk `kegiatan` untuk RAB), `klaim_kegiatan`, `audit_log`. |
| **Keputusan terkait** | Q42 §42.2–§42.5, Q49 §49.1 butir 3, Q46 §46.1. |

> **Sebagai** PIC dan Tim Perencanaan,  
> **Saya ingin** mencatat anggaran setiap kegiatan, mengunggah RAB untuk kegiatan beranggaran, dan menetapkan persyaratan dokumen tahap perencanaan kegiatan beserta contoh berkas,  
> **Sehingga** anggaran per periode, per Rencana Aksi, dan per IKU dapat dijumlahkan tanpa hitung ganda.

**Acceptance Criteria**

- [ ] **AC-1:** Given kegiatan dibuat atau diubah, When `anggaran` kosong, Then ditolak; When diisi, Then harus rupiah nonnegatif; `0` sah dan kegiatan beranggaran nol tetap dapat diklaim. *(Q42 §42.2 butir 1–2)*
- [ ] **AC-2:** Given kegiatan lama dengan `anggaran` kosong, Then tampil "belum diisi" dan tidak dihitung sebagai 0; total menandai bila ada kegiatan yang anggarannya belum diisi. *(Q42 §42.2 butir 2)*
- [ ] **AC-3:** Given Perencanaan menyimpan persyaratan berkas, Then tersedia tahap `kegiatan_perencanaan`; persyaratan dapat dilampiri contoh berkas yang dapat diunduh PIC. *(Q42 §42.3 butir 2, §42.4 butir 3)*
- [ ] **AC-4:** Given RA diajukan, When ada kegiatan berklaim tahap RA yang belum memenuhi persyaratan wajib tahap `kegiatan_perencanaan`, termasuk RAB (Excel atau PDF) untuk kegiatan beranggaran lebih dari 0, Then pengajuan ditolak dengan daftar kegiatan dan persyaratan yang kurang. *(Q42 §42.3 butir 1, §42.4 butir 1)*
- [ ] **AC-5:** Given kegiatan beranggaran 0, Then RAB tidak diwajibkan. *(Q42 §42.3 butir 1)*
- [ ] **AC-6:** Given kegiatan ditandai `terlaksana`, Then persyaratan tahap `kegiatan` (pelaksanaan) tetap diperiksa sesuai PRD §15.6 dan Q22. *(Q42 §42.4 butir 2)*
- [ ] **AC-7:** Given halaman RA, Then total anggaran tampil per periode dan per RA, dan total per IKU tersedia untuk dashboard; kegiatan yang diklaim lebih dari satu IKU tampil penuh di setiap IKU dengan penanda "kegiatan bersama", sedangkan total per unit dan per tahun dihitung dari kegiatan unik. *(Q42 §42.2 butir 4)*
- [ ] **AC-8:** Given pengguna tanpa akses detail kegiatan unit tersebut, Then anggaran per kegiatan dan RAB tidak tampil; Perencanaan dan Pimpinan melihat seluruhnya. *(Q42 §42.5)*
- [ ] **AC-9:** Given RA data awal 2026 atau periode lampau, Then gerbang RAB dan persyaratan tahap perencanaan kegiatan dikecualikan. *(Q46 §46.1 butir 1 dan 3)*

**Business Rules / Catatan**

- Realisasi belanja, pagu induk, dan rekonsiliasi anggaran tidak dikelola (Q42 §42.2 butir 3).
- Kegiatan lanjutan (Q41 §41.4) menyalin anggaran kegiatan asal dan anggarannya dapat diubah. Kegiatan asal `ditunda` yang sudah memiliki kegiatan lanjutan tidak dihitung dalam total, sehingga anggarannya hanya terhitung pada kegiatan lanjutan; total yang sudah dibekukan pada versi RA tidak berubah (Q42 §42.2 butir 6).
- Aplikasi tidak membuat template RAB (Q42 §42.3 butir 2).
- Persyaratan tahap `kegiatan_perencanaan` dapat ditandai hanya untuk kegiatan beranggaran (`jenis_berkas.hanya_kegiatan_beranggaran`, default `false`). Persyaratan RAB memakai penanda ini sehingga hanya diperiksa untuk kegiatan beranggaran lebih dari 0, sedangkan persyaratan lain pada tahap yang sama berlaku untuk setiap kegiatan (Q42 §42.3 butir 3).
- Contoh berkas bukan bukti: Perencanaan dapat menambah, mengganti, atau menghapusnya kapan saja; penghapusannya wajib alasan (Q43 §43.2 butir 2), dan contoh berkas tidak dibekukan ke versi pengajuan (Q42 §42.3 butir 4).
- Persyaratan tahap perencanaan kegiatan yang diperiksa saat RA indikator X diajukan adalah persyaratan global ditambah persyaratan khusus indikator X; untuk kegiatan bersama, persyaratan khusus indikator lain diperiksa saat RA indikator tersebut diajukan (Q42 §42.4 butir 4).
- Berkas yang dipakai memenuhi persyaratan tahap perencanaan kegiatan (mis. RAB) tidak dapat dihapus sejak RA yang mengklaim kegiatan tersebut diajukan; koreksinya lewat pengembalian RA atau berkas pengganti (Q25), mengikuti pola bukti RA (Q36) (Q42 §42.4 butir 5).
- Kegiatan data awal 2026 dan kegiatan pada periode lampau boleh tanpa anggaran dan tercatat belum diisi (Q42 §42.2 butir 2), karena Rencana Aksi resmi hanya memuat anggaran per Rincian Output (Q46 §46.1 butir 3).
- Batas ukuran/format mengikuti persyaratan dan batas teknis Q44.
- Bila PR #97 di-merge sebelum Q49 diterapkan, `nama` hingga 500 karakter dan `tujuan` opsional dikerjakan di story ini (Q49 §49.1 butir 3).
- **Di luar cakupan:** dashboard anggaran per IKU (US-10.01), salin kegiatan (US-06.02).

---

## Bagian 7 — Pengisian Pengukuran Kinerja

### US-07.01 · Pengisian Nilai Realisasi Komponen/Manual

> **Q45, Q47, Q51 (13 Oktober 2026):** form pengukuran memuat narasi pengukuran per indikator per periode (`progres_kegiatan`, `kendala_masalah`, `strategi_tindak_lanjut`), isian `tindak_lanjut_rekomendasi` bila ada Rekomendasi Pimpinan disetujui pada periode sebelumnya, dan `catatan` sebagai keterangan opsional (Q45 §45.1, §45.2 butir 3; Q51 §51.1 butir 2). Realisasi yang belum tersedia dicatat sebagai `status_perhitungan = belum_dapat_diukur` (Q47 §47.4). Gerbang pengajuan dan tampilan periode tanpa target berada pada US-07.03. AC-7 dan AC-8 ditambahkan karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-07.01` |
| **Prioritas** | 🔴 P0 · Tahap 2 (Q46 §46.3) |
| **Story Points** | 8 |
| **Modul** | Pengisian Pengukuran Kinerja |
| **Dependensi** | Periode berlaku dan jendela pengisian sah; snapshot indikator tersedia; PIC grant + PIC efektif valid atau Perencanaan global. |
| **Otorisasi** | `pengukuran:create`, `pengukuran:update`. |
| **Dampak Data** | `pengukuran`, `pengukuran_komponen`, `audit_log`. |

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** mengisi realisasi sesuai tipe perhitungan snapshot,  
> **Sehingga** nilai capaian dihitung konsisten dengan definisi yang dibekukan.

**Acceptance Criteria**

- [ ] **AC-1:** Given indikator nonmanual, When semua komponen diisi, Then server menghitung `nilai` dari snapshot dan UI menampilkan nilai turunan read-only.
- [ ] **AC-2:** Given indikator manual, When nilai diisi, Then nilai langsung tersimpan sebagai sumber `manual`.
- [ ] **AC-3:** Given nilai 0, When disimpan, Then 0 diperlakukan sebagai nilai sah dan berbeda dari NULL.
- [ ] **AC-4:** Given periode sebelum `periode_mulai_id`, When pengukuran dibuat, Then ditolak sebagai Tidak berlaku.
- [ ] **AC-5:** Given indikator master sudah arsip, When pengukuran baru dibuat, Then create ditolak.
- [ ] **AC-6:** Given rasio antarperiode, Then sistem tidak menjumlahkan persentase; realisasi memakai basis waktu/populasi yang sebanding dengan target kumulatif.
- [ ] **AC-7:** Given nilai realisasi belum tersedia pada periode tersebut, When pengukuran dinyatakan belum dapat diukur, Then tersimpan `status_perhitungan = belum_dapat_diukur` dengan `nilai` kosong secara eksplisit, bukan disimpulkan dari nilai kosong, dan dibedakan dari realisasi `0` maupun `tidak_dapat_dihitung`. *(Q47 §47.4 butir 1 dan 3)*
- [ ] **AC-8:** Given form pengukuran dibuka, Then tersedia isian narasi `progres_kegiatan`, `kendala_masalah`, dan `strategi_tindak_lanjut`, isian `tindak_lanjut_rekomendasi` bila terdapat Rekomendasi Pimpinan disetujui pada periode sebelumnya, serta `catatan` opsional. *(Q45 §45.1, §45.2 butir 3; Q51 §51.1 butir 2)*

### US-07.02 · Pemenuhan Bukti Dukung Pengukuran

> **Q44 (13 Oktober 2026):** unggahan file selalu aktif sehingga pengecualian mode file pada AC-3 tidak lagi terjadi; penanda `tidak_dapat_dipenuhi` yang sudah tercatat tetap menjadi histori (Q44 §44.1 butir 2; US-11.06 dibatalkan, US-13.06).

| Field | Detail |
|---|---|
| **ID** | `US-07.02` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Pengisian Pengukuran Kinerja |
| **Dependensi** | Pengukuran dapat diedit; persyaratan bukti aktif. |
| **Otorisasi** | Capability berkas mengikuti induk Pengukuran; deny tetap berlaku. |
| **Dampak Data** | `berkas`, `audit_log`. |

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** melampirkan file, tautan, atau teks sesuai persyaratan pengukuran,  
> **Sehingga** angka capaian memiliki sumber bukti yang dapat diverifikasi.

**Acceptance Criteria**

- [ ] **AC-1:** Given mode diizinkan, When bukti disimpan, Then `berkas` terkait Pengukuran dan jenis persyaratan.
- [ ] **AC-2:** Given `semua_mode_wajib = true`, Then seluruh mode yang disyaratkan wajib terpenuhi.
- [ ] **AC-3:** *(Dihapus Q44 §44.1 butir 2; tidak dikerjakan.)* Semula: Given file upload dimatikan dan persyaratan mengandung kewajiban file, Then hanya kewajiban **mode file** yang dapat dikecualikan sesuai kebijakan; mode tautan/teks lain tetap wajib bila dipersyaratkan.
- [ ] **AC-4:** Given bukti telah masuk versi submit, Then perubahan live tidak mengubah versi lama.

### US-07.03 · Pengajuan Pengukuran & Pembekuan Versi

> **Q45, Q46, Q47, Q51 (13 Oktober 2026):** kewajiban `catatan` saat nilai memburuk atau indikator `wajib_catatan` digantikan narasi pengukuran wajib; `catatan` menjadi keterangan opsional dan saklar `wajib_catatan` disembunyikan dari form indikator tanpa menghapus kolomnya (Q45 §45.1 butir 1 dan 3). Tindak lanjut wajib hanya atas Rekomendasi Pimpinan yang disetujui (Q45 §45.2 butir 3, Q51 §51.1 butir 2). Realisasi `belum_dapat_diukur` mengecualikan gerbang kelengkapan komponen, dan periode tanpa target ditampilkan dengan selisih terhadap target PK (Q47 §47.3–§47.4). Gerbang baru dikecualikan untuk periode lampau (Q46 §46.1 butir 1). AC-2 dan AC-4 diperbarui serta AC-8 sampai AC-12 ditambahkan karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-07.03` |
| **Prioritas** | 🔴 P0 · Tahap 2 (Q46 §46.3) |
| **Story Points** | 8 |
| **Modul** | Pengisian Pengukuran Kinerja |
| **Dependensi** | Pengukuran draft/dikembalikan; jendela aktor sah; RA resmi tersedia pada jalur normal. |
| **Otorisasi** | `pengukuran:update` untuk submit sesuai scope. |
| **Dampak Data** | `pengukuran`, `pengukuran_versi`, `audit_log`; item notifikasi in-app ke Perencanaan menunggu ISS-12.02 (Q37 §37.3 butir 4). |

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** mengajukan pengukuran sebagai versi immutable untuk direviu,  
> **Sehingga** angka, target pembanding, bukti, dan provenance tidak berubah selama proses review.

**Acceptance Criteria**

- [ ] **AC-1:** Given jalur normal, When submit, Then RA indikator-tahun harus memiliki versi yang telah disahkan.
- [ ] **AC-2:** Given nonmanual dan input wajib belum lengkap, When submit, Then ditolak; pengecualian `tidak_dapat_dihitung` hanya berlaku untuk penyebut efektif nol dengan komponen lengkap dan alasan, dan pengukuran berstatus `belum_dapat_diukur` dikecualikan dari gerbang kelengkapan komponen. *(Q9; diperbarui Q47 §47.4 butir 1)*
- [ ] **AC-3:** Given bukti wajib menurut persyaratan saat submit belum terpenuhi, When submit, Then ditolak.
- [ ] **AC-4:** Given pengukuran diajukan, When salah satu narasi `progres_kegiatan`, `kendala_masalah`, atau `strategi_tindak_lanjut` kosong, Then submit ditolak; `catatan` tetap opsional. *(Diperbarui Q45 §45.1 butir 1 dan 3; semula `catatan` wajib bila nilai memburuk menurut arah atau indikator `wajib_catatan`.)*
- [ ] **AC-5:** Given seluruh gerbang lolos, Then header menjadi `diajukan` dan **`pengukuran_versi` baru** dibuat dengan `diajukan_by`, `diajukan_at`, `jalur_pengajuan`, `dasar_izin_pengajuan`, nilai/komponen, status perhitungan, snapshot target PK/RA, bukti, klaim/narasi, dan persyaratan.
- [ ] **AC-6:** Given A membuat draft dan B melakukan submit, Then `diajukan_by = B`; F1/F2 tidak menggunakan `created_by`.
- [ ] **AC-7:** Given submit berhasil, Then pengukuran tampil pada antrean verifikasi berbasis status (ISS-08.01); item notifikasi in-app mengikuti ISS-12.02 AC-2. *(Diperbarui Q37 §37.3 butir 4, keputusan PM 14 Oktober 2026; semula notifikasi antrean Perencanaan dibuat setelah commit.)*
- [ ] **AC-8:** Given terdapat Rekomendasi Pimpinan berstatus `disetujui` pada periode N untuk indikator tersebut, When pengukuran periode N+1 diajukan tanpa `tindak_lanjut_rekomendasi`, Then submit ditolak; rekomendasi berstatus `draf` tidak memicu kewajiban ini. *(Q45 §45.2 butir 3, Q51 §51.1 butir 2)*
- [ ] **AC-9:** Given pengukuran berstatus `belum_dapat_diukur`, When diajukan, Then narasi pengukuran tetap wajib dan menjelaskan mengapa nilai belum tersedia; pada periode nilai akhir status ini ditolak karena realisasi wajib terisi, sedangkan kondisi tidak dapat dihitung menurut Q9 tetap diperbolehkan. *(Q47 §47.4 butir 2 dan 4)*
- [ ] **AC-10:** Given pengukuran periode lampau diisi Perencanaan, Then gerbang narasi wajib dan tindak lanjut rekomendasi dikecualikan. *(Q46 §46.1 butir 1 dan 4)*
- [ ] **AC-11:** Given form indikator, Then saklar `wajib_catatan` tidak ditampilkan dan kolomnya tetap tersimpan. *(Q45 §45.1 butir 3)*
- [ ] **AC-12:** Given periode tanpa target, Then tampilan pengukuran menunjukkan realisasi, target PK, dan selisih terhadap target PK tanpa capaian persen maupun status capaian; pada periode bertarget, selisih terhadap target PK tampil sebagai informasi tambahan. *(Q47 §47.3 butir 1–2)*

**Business Rules / Catatan**

- Kewajiban tindak lanjut rekomendasi (AC-8) berlaku lintas tahun: rekomendasi disetujui pada periode terakhir suatu tahun ditindaklanjuti pada periode pertama jadwal tahun berikutnya bila indikatornya masih diukur (Q51 §51.1 butir 4).

### US-07.04 · Pengisian oleh Perencanaan di Luar Deadline PIC

| Field | Detail |
|---|---|
| **ID** | `US-07.04` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Pengisian Pengukuran Kinerja |
| **Dependensi** | Jadwal belum ditutup atau berada pada sesi koreksi yang sah. |
| **Otorisasi** | `pengukuran:create`, `pengukuran:update` global melalui role Perencanaan. |
| **Dampak Data** | `pengukuran`, versi, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengisi atau mengoreksi pengukuran setelah deadline PIC,  
> **Sehingga** pelaporan tidak macet ketika unit terlambat, tanpa melemahkan gerbang kualitas.

**Acceptance Criteria**

- [ ] **AC-1:** Given tanggal melewati `pengisian_selesai`, When PIC mencoba mengubah/submit, Then ditolak kecuali jendela PIC resmi dibuka ulang.
- [ ] **AC-2:** Given Perencanaan bekerja setelah deadline PIC tetapi sebelum penutupan/jendela koreksi sah, When mengisi data, Then diizinkan melalui izin global.
- [ ] **AC-3:** Given Perencanaan submit, Then seluruh gerbang kelengkapan normal tetap berlaku kecuali jalur backfill eksplisit.
- [ ] **AC-4:** Given submit oleh Perencanaan, Then `jalur_pengajuan = perencanaan` dibekukan pada versi untuk F2.
- [ ] **AC-5:** Given reviu melewati `reviu_selesai`, Then proses masih dapat berlangsung sampai penutupan dengan penanda terlambat.

### US-07.05 · Pengisian Nilai Periode Lampau oleh Perencanaan

> **Q46 (13 Oktober 2026):** TW III 2026 ikut menjadi periode lampau yang diisi Perencanaan, gerbang narasi wajib dan tindak lanjut rekomendasi dikecualikan untuk periode lampau, dan realisasi serta narasi TW I–II diisi dari Laporan Kinerja Triwulan I–II 2026 (Q46 §46.1 butir 1, 2, dan 6). AC diperbarui langsung karena story ini belum dikerjakan; rumusan semula (TW I–II) menjadi riwayat.

| Field | Detail |
|---|---|
| **ID** | `US-07.05` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Pengisian Pengukuran Kinerja |
| **Dependensi** | Periode lampau 2026 terdeteksi dari Jadwal. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mencatat nilai TW I–III 2026 yang periode pengisiannya telah lewat,  
> **Sehingga** data awal 2026 tersedia tanpa membangun mode backfill generik.

**Acceptance Criteria**

- [ ] Pengisian hanya memakai jalur Perencanaan untuk periode lampau (TW I–III 2026). *(Q46 §46.1 butir 2; semula TW I–II)*
- [ ] Tidak ada `is_backfill` atau tipe pengukuran khusus.
- [ ] Nilai tetap terhubung ke Jadwal/Snapshot yang sah dan teraudit.
- [ ] Data TW II IKU 3 (kode PK IKU 1.3, Q50 §50.4) tidak menggunakan 66,395 atau 87,08 sebagai realisasi.
- [ ] Realisasi dan narasi pengukuran TW I–II diisi dari Laporan Kinerja Triwulan I–II 2026 (rekomendasinya mengikuti US-09.01 dan US-15.01); gerbang narasi wajib dan tindak lanjut rekomendasi dikecualikan untuk periode lampau. *(Q46 §46.1 butir 1 dan 6)*

### US-07.06 · Penanganan Penyebut Nol — Tidak Dapat Dihitung

| Field | Detail |
|---|---|
| **ID** | `US-07.06` |
| **Prioritas** | 🟡 P1 · Tahap 2 (Q46 §46.3) |
| **Story Points** | 5 |
| **Modul** | Pengisian Pengukuran Kinerja |
| **Dependensi** | Indikator rasio; semua komponen terisi; penyebut efektif = 0. |
| **Otorisasi** | `pengukuran:update` sesuai scope. |
| **Dampak Data** | `pengukuran`, `pengukuran_komponen`, versi, `audit_log`. |

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** mengajukan kondisi penyebut nol tanpa menyamakan dengan belum diisi atau nilai nol,  
> **Sehingga** laporan membedakan data tidak terhitung secara matematis dari ketidaklengkapan input.

**Acceptance Criteria**

- [ ] **AC-1:** Given seluruh komponen lengkap dan penyebut = 0, When dihitung, Then `nilai = NULL` dan `status_perhitungan = tidak_dapat_dihitung`.
- [ ] **AC-2:** Given status tersebut akan disubmit, Then `alasan_tidak_dapat_dihitung` wajib.
- [ ] **AC-3:** Given komponen belum lengkap, Then status tetap `belum_diisi` dan tidak boleh menggunakan pengecualian penyebut nol.
- [ ] **AC-4:** Given nilai faktual 0 pada indikator yang dapat dihitung, Then 0 tetap `terhitung` dan bukan NULL.
- [ ] **AC-5:** Given versi disahkan, Then laporan menampilkan penanda tidak dapat dihitung beserta alasan yang sesuai hak baca.
- [ ] **AC-6:** Given nilai realisasi belum tersedia dan bukan karena penyebut nol, Then status yang dipakai adalah `belum_dapat_diukur`, bukan `tidak_dapat_dihitung`; keduanya ditampilkan berbeda dan tidak dihitung sebagai capaian 0. *(Q47 §47.4 butir 3)*

---

## Bagian 8 — Reviu, Verifikasi, Pengesahan, dan Koreksi Pengukuran

### US-08.01 · Verifikasi & Pengembalian Pengukuran dengan F1

> **Q47 dan ketentuan pelengkap Q47–Q51 (13 Oktober 2026):** saat verifikasi, sistem memperingatkan bila realisasi atau narasi sama persis dengan periode sebelumnya tanpa memblokir verifikasi, dan Perencanaan menilai alasan pengukuran berstatus `belum_dapat_diukur` (Q47 §47.4 butir 2). AC-6 dan AC-7 ditambahkan karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-08.01` |
| **Prioritas** | 🔴 P0 · Tahap 2 (Q46 §46.3) |
| **Story Points** | 8 |
| **Modul** | Reviu, Verifikasi, Pengesahan, dan Koreksi Pengukuran |
| **Dependensi** | Pengukuran berstatus `diajukan`; versi terbaru tersedia. |
| **Otorisasi** | `pengukuran:verifikasi`, `pengukuran:kembalikan`. |
| **Dampak Data** | `pengukuran`, `pengukuran_versi`, `audit_log`; item notifikasi in-app pengembalian menunggu ISS-12.02 (Q37 §37.3 butir 4). |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** memeriksa nilai dan bukti lalu memverifikasi atau mengembalikan,  
> **Sehingga** angka resmi melewati review independen pada jalur PIC.

**Acceptance Criteria**

- [ ] **AC-1:** Given versi diajukan melalui jalur PIC, When aktor sama dengan `pengukuran_versi.diajukan_by` mencoba **memverifikasi**, Then F1 menolak.
- [ ] **AC-2:** Given reviewer berbeda dan berizin, When verifikasi dilakukan, Then status menjadi `diverifikasi` tanpa mengubah snapshot versi.
- [ ] **AC-3:** Given kesalahan ditemukan pada status `diajukan` atau `diverifikasi`, When dikembalikan dengan alasan, Then status menjadi `dikembalikan`; item notifikasi pengembalian in-app mengikuti ISS-12.02 AC-1. *(Diperbarui Q37 §37.3 butir 4, keputusan PM 14 Oktober 2026; semula notifikasi dikirim setelah commit.)*
- [ ] **AC-4:** Given pengembalian tanpa alasan, Then ditolak.
- [ ] **AC-5:** Given tanggal melewati `reviu_selesai` tetapi belum penutupan, When Perencanaan mereviu, Then aksi tetap dapat dilakukan dan diberi penanda reviu terlambat.
- [ ] **AC-6:** Given realisasi atau narasi pengukuran sama persis dengan periode sebelumnya, When Perencanaan memverifikasi, Then sistem menampilkan peringatan yang tidak memblokir verifikasi. *(Ketentuan pelengkap butir 2, pengantar Q47–Q51)*
- [ ] **AC-7:** Given pengukuran berstatus `belum_dapat_diukur`, When Perencanaan menilai narasi alasannya tidak dapat diterima, Then pengukuran dapat dikembalikan dengan alasan. *(Q47 §47.4 butir 2)*

### US-08.02 · Pengesahan Pengukuran & F1/F2

| Field | Detail |
|---|---|
| **ID** | `US-08.02` |
| **Prioritas** | 🔴 P0 · Tahap 2 (Q46 §46.3) |
| **Story Points** | 8 |
| **Modul** | Reviu, Verifikasi, Pengesahan, dan Koreksi Pengukuran |
| **Dependensi** | Pengukuran `diverifikasi`; versi yang sama masih menjadi versi aktif untuk review. |
| **Otorisasi** | `pengukuran:sahkan` (sensitif). |
| **Dampak Data** | `pengukuran`, `pengukuran_versi`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengesahkan versi pengukuran,  
> **Sehingga** nilai realisasi menjadi angka resmi organisasi dengan provenance yang tidak dapat ditulis ulang.

**Acceptance Criteria**

- [x] **AC-1:** Given jalur pengajuan PIC, When aktor sama dengan `diajukan_by` mencoba sahkan, Then F1 menolak.
- [x] **AC-2:** Given aktor lain berizin, When sahkan, Then status menjadi `disahkan` dan `disahkan_by/disahkan_at` versi terisi atomik.
- [x] **AC-3:** Given jalur pengajuan Perencanaan, When pengaju yang sama mereviu/mengesahkan dan izin efektif masih ada, Then F2 mengizinkan dan mencatat `self_approval`.
- [x] **AC-4:** Given deny/permission tidak memenuhi, Then F2 tidak dapat melewati resolver.
- [x] **AC-5:** Given versi stale, When sahkan, Then ditolak.
- [ ] **AC-6:** Given pengesahan berhasil, Then laporan resmi membaca snapshot `pengukuran_versi` tersebut, bukan live join master terbaru.

### US-08.03 · Buka-Kembali Pengukuran Disahkan & Histori Status Capaian

| Field | Detail |
|---|---|
| **ID** | `US-08.03` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 8 |
| **Modul** | Reviu, Verifikasi, Pengesahan, dan Koreksi Pengukuran |
| **Dependensi** | Pengukuran disahkan; waktu koreksi sah. |
| **Otorisasi** | `pengukuran:buka_kembali` (sensitif). |
| **Dampak Data** | `pengukuran`, `pengukuran_versi`, `status_capaian`, `audit_log`. |

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** membuka pengukuran resmi untuk koreksi tanpa menghapus hasil lama,  
> **Sehingga** kesalahan pasca-pengesahan dapat diperbaiki sementara histori keputusan tetap utuh.

**Acceptance Criteria**

- [ ] **AC-1:** Given alasan wajib dan waktu koreksi sah, When buka kembali dilakukan, Then header menjadi `dikembalikan`.
- [ ] **AC-2:** Given versi lama sudah disahkan, Then versi lama dan status capaian yang terkait versi itu tetap tersimpan sebagai histori.
- [ ] **AC-3:** Given pengukuran diperbaiki lalu diajukan ulang, Then `pengukuran_versi` baru dibuat; versi baru belum memiliki status capaian sampai ditetapkan setelah pengesahan.
- [ ] **AC-4:** Given jadwal ditutup tanpa sesi koreksi, When buka kembali pengukuran langsung dicoba, Then ditolak.
- [ ] **AC-5:** Given versi koreksi belum disahkan, Then laporan resmi tetap memakai versi disahkan sebelumnya.

---

## Bagian 9 — Rekomendasi Pimpinan & Status Capaian

### US-09.01 · Rekomendasi Pimpinan: Draf Perencanaan dan Persetujuan Pimpinan

> **Q45, Q51 (13 Oktober 2026):** Rekomendasi Pimpinan kini diisi Pimpinan: Perencanaan menyusun draf, Pimpinan menyetujui draf (dapat mengubah isinya lebih dulu) atau menulis rekomendasi langsung, dan hanya rekomendasi berstatus disetujui yang berlaku (Q45 §45.2 butir 1, Q51 §51.1). Persetujuan oleh Perencanaan menjadi jalur cadangan yang diberi penanda (Q51 §51.2 butir 2). Story ini belum dikerjakan sehingga isinya diperbarui langsung dan dipindah ke tahap 2 (Q51 §51.3). Riwayat: judul semula "Pencatatan Rekomendasi Pimpinan oleh Perencanaan" (digantikan Q45/Q51); AC-1 sampai AC-3 semula diperbarui menjadi AC-1, AC-5, dan AC-6; AC-4 semula (Pimpinan hanya membaca rekomendasi pada MVP) dibatalkan Q45 §45.2 butir 1.

> **Q55 (16 Oktober 2026):** paling banyak satu draf terbuka per indikator × tahun × periode; penyimpanan dan persetujuan membawa token versi draf dan rekomendasi berlaku yang dilihat, sehingga tampilan basi ditolak sebagai konflik versi tanpa mengubah data. Penyetuju cadangan boleh menulis rekomendasi yang langsung disetujui dengan penanda cadangan, dan versi pengajuan pengukuran membekukan rekomendasi yang tampil; pengajuan dari tampilan basi ditolak (Q55 §55.1).

| Field | Detail |
|---|---|
| **ID** | `US-09.01` |
| **Prioritas** | 🟡 P1 · Tahap 2 (Q46 §46.3; dipindah Q51 §51.3) |
| **Story Points** | 5 |
| **Modul** | Rekomendasi Pimpinan & Status Capaian |
| **Dependensi** | Indikator, tahun, dan periode valid. |
| **Otorisasi** | `rekomendasi:tetapkan` (sensitif; preset Pimpinan dan Perencanaan melalui seeder, Q45 §45.2 butir 1, Q51 §51.2 butir 1). |
| **Dampak Data** | `rekomendasi_pimpinan` (`status` `draf`/`disetujui`, `disetujui_oleh`, `disetujui_oleh_role`, `disetujui_pada`, `rujukan_sumber`, `versi` (Q55)), preset `role_permissions`, `audit_log`. |
| **Keputusan terkait** | Q45 §45.2, Q51, Q46 §46.3. |

> **Sebagai** Pimpinan dan Tim Perencanaan,  
> **Saya ingin** Perencanaan menyusun draf rekomendasi per indikator-periode lalu Pimpinan menyetujuinya atau menulis rekomendasi langsung,  
> **Sehingga** arahan pimpinan hasil evaluasi tercatat sebagai keputusan Pimpinan dan hanya rekomendasi yang disetujui ditindaklanjuti.

**Acceptance Criteria**

- [x] **AC-1:** Given Perencanaan menyimpan rekomendasi untuk indikator × periode, Then baris baru dibuat berstatus `draf` dengan `ditetapkan_oleh` dan waktu, serta tercatat di audit. *(Diperbarui Q51 §51.1 butir 1; semula baris langsung berlaku.)*
- [x] **AC-2:** Given Pimpinan memiliki `rekomendasi:tetapkan`, When Pimpinan menyetujui draf (dengan atau tanpa mengubah isinya lebih dulu) atau menulis rekomendasi langsung, Then rekomendasi berstatus `disetujui` dengan `disetujui_oleh`, `disetujui_oleh_role`, dan `disetujui_pada` terisi serta tercatat di audit. *(Q51 §51.1 butir 1)*
- [x] **AC-3:** Given rekomendasi berstatus `draf`, When Perencanaan menyetujuinya sebagai jalur cadangan bila Pimpinan berhalangan, Then persetujuan diterima dengan penanda cadangan (`disetujui_oleh_role = perencanaan`) dan tercatat di audit. *(Q51 §51.2 butir 2)*
- [ ] **AC-4:** Given rekomendasi masih berstatus `draf`, Then rekomendasi tidak tampil bagi PIC, tidak tampil pada pengukuran periode berikutnya maupun Laporan Kinerja Triwulan, dan tidak memicu tindak lanjut wajib; hanya rekomendasi `disetujui` yang berlaku. *(Q51 §51.1 butir 2)* *(Draf tidak tampil pada pengukuran periode berikutnya, dan halaman Rekomendasi Pimpinan hanya untuk pemegang izin; bagian Laporan Kinerja Triwulan menunggu ISS-10.05 dan tindak lanjut wajib menunggu gerbang ISS-07.03, keduanya memakai pembaca rekomendasi yang berlaku.)*
- [x] **AC-5:** Given pengukuran periode belum disahkan, When rekomendasi ditulis setelah rapat, Then sistem tetap mengizinkan karena rekomendasi opsional per indikator per periode dan independen dari status pengukuran. *(Q51 §51.1 butir 3)*
- [x] **AC-6:** Given rekomendasi sudah disetujui, When diubah, Then perubahan membentuk baris baru yang melalui persetujuan yang sama, sedangkan baris lama tidak dihapus dan tetap menjadi histori. *(Q51 §51.2 butir 3)*
- [ ] **AC-7:** Given rekomendasi disetujui pada periode N, Then pengukuran periode N+1 untuk indikator tersebut wajib memuat tindak lanjut atas rekomendasi saat diajukan (gerbang pada US-07.03). *(Q45 §45.2 butir 3)* *(Gerbang pengajuan dipasang pada ISS-07.03 / #82; issue ini menyediakan pembaca rekomendasi yang berlaku, termasuk lintas tahun.)*
- [x] **AC-8:** Given seeder preset dijalankan, Then `rekomendasi:tetapkan` dimiliki preset Pimpinan dan Perencanaan; simpan draf dan setujui dibedakan oleh status rekomendasi, bukan oleh permission. *(Q45 §45.2 butir 1, Q51 §51.2 butir 1)*

**Business Rules / Catatan**

- Rekomendasi melekat pada indikator × periode dan independen dari status pengukuran (Q45 §45.2 butir 2).
- Rekomendasi yang disetujui dimuat pada Laporan Kinerja Triwulan (Q48 §48.1 butir 1; US-10.05).
- Kewajiban tindak lanjut berlaku lintas tahun: rekomendasi pada periode terakhir suatu tahun ditindaklanjuti pada periode pertama jadwal tahun berikutnya bila indikatornya masih diukur (Q51 §51.1 butir 4).
- Tindak lanjut rekomendasi dikecualikan untuk periode lampau (Q46 §46.1 butir 1; US-07.03).
- Data awal 2026: rekomendasi TW I–II dari laporan triwulan yang sudah ditandatangani Pimpinan dicatat Perencanaan langsung berstatus `disetujui`, dengan penanda persetujuan cadangan dan rujukan laporan sumbernya pada kolom rancangan `rekomendasi_pimpinan.rujukan_sumber` (Q51 §51.1 butir 5; US-15.01).
- Penanda persetujuan cadangan berlaku untuk persetujuan oleh peran selain Pimpinan, termasuk Superadmin, bukan hanya Perencanaan (Q51 §51.2 butir 2).
- Paling banyak satu draf terbuka per indikator × tahun × periode; token versi draf dan rekomendasi berlaku yang dilihat menolak tampilan basi tanpa mengubah data, dan versi pengajuan pengukuran membekukan rekomendasi yang tampil; pengajuan dari tampilan basi ditolak (Q55).

### US-09.02 · Penetapan Status Capaian per Versi Pengukuran Disahkan

> **Q47, Q50 (13 Oktober 2026):** pada periode tanpa target, capaian persen dan status capaian tidak dihitung, dan realisasi `belum_dapat_diukur` tidak dihitung sebagai capaian 0 (Q47 §47.3 butir 1, §47.4 butir 3). Status capaian memakai target PK bernilai penuh yang tersimpan (Q50 §50.2). Penetapan status capaian manual tidak tersedia untuk pengukuran pada periode tanpa target maupun pengukuran berstatus `belum_dapat_diukur` (Q47 §47.3 butir 4). AC-5 ditambahkan karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-09.02` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Rekomendasi Pimpinan & Status Capaian |
| **Dependensi** | Terdapat `pengukuran_versi` yang telah disahkan. |
| **Otorisasi** | `status_capaian:update` (sensitif). |
| **Dampak Data** | `status_capaian`, `pengukuran_versi`, `audit_log`. |

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** menetapkan status `tercapai`/`belum_tercapai` pada versi resmi tertentu,  
> **Sehingga** penilaian akhir selalu terikat pada angka yang benar-benar disahkan.

**Acceptance Criteria**

- [ ] **AC-1:** Given versi pengukuran telah disahkan, When status dipilih, Then `status_capaian` merujuk `pengukuran_versi_id` yang sama.
- [ ] **AC-2:** Given pengukuran belum disahkan, When status dicoba, Then ditolak.
- [ ] **AC-3:** Given versi koreksi baru disahkan, Then versi baru mulai **Belum ditetapkan**; status versi lama tidak diwariskan otomatis.
- [ ] **AC-4:** Given status direvisi, Then histori status lama tetap tersimpan dan baris terbaru menjadi yang berlaku.
- [ ] **AC-5:** Given pengukuran pada periode tanpa target atau berstatus `belum_dapat_diukur`, When status capaian manual hendak ditetapkan, Then penetapan tidak tersedia, termasuk melalui request langsung. *(Q47 §47.3 butir 4)*

---

## Bagian 10 — Dashboard, Rekapitulasi, dan Ekspor

### US-10.01 · Dashboard Kinerja Eksekutif

> **Q42, Q45, Q47 (13 Oktober 2026):** dashboard menampilkan status kegiatan per periode secara real-time berlabel "belum diverifikasi" dengan peringatan bagi kegiatan yang melewati tanggal rencana (Q45 §45.3), total anggaran per IKU dengan drill-down sampai kegiatan (Q42 §42.2 butir 5), serta "Tanpa target periode", "Belum dapat diukur", dan selisih terhadap target PK (Q47 §47.3–§47.4). Angka capaian resmi tetap hanya dari data yang disahkan (Q45 §45.3 butir 2). AC-7 sampai AC-10 ditambahkan karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-10.01` |
| **Prioritas** | 🟡 P1 · Tahap 2 (Q46 §46.3) |
| **Story Points** | 8 |
| **Modul** | Dashboard, Rekapitulasi, dan Ekspor |
| **Dependensi** | Pengguna memiliki akses dashboard; data resmi/draft tersedia sesuai hak. |
| **Otorisasi** | `dashboard:read`. |
| **Dampak Data** | Read-only query terhadap snapshot/versi resmi dan data kerja sesuai konteks. |

> **Sebagai** Pimpinan, Perencanaan, Admin, atau pengguna lain yang memiliki izin,  
> **Saya ingin** memantau status indikator, target-vs-realisasi, progres RA, dan kegiatan,  
> **Sehingga** pengambilan keputusan didukung tampilan ringkas yang konsisten dengan status data.

**Acceptance Criteria**

- [ ] **AC-1:** Given dashboard dibuka, Then filter Renstra, tahun, periode, sasaran, dan unit bekerja sesuai hak akses.
- [ ] **AC-2:** Given indikator-periode belum memiliki pengukuran, Then status dihitung dari daftar periode yang diharapkan, bukan dari keberadaan baris pengukuran semata.
- [ ] **AC-3:** Given periode sebelum efektivitas indikator, Then dashboard menampilkan Tidak berlaku dan tidak menghitungnya sebagai missing.
- [ ] **AC-4:** Given indikator arsip, Then tidak menjadi kewajiban baru.
- [ ] **AC-5:** Given hasil resmi ditampilkan, Then angka berasal dari versi disahkan/snapshot yang tepat, bukan master terbaru yang bisa berubah.
- [ ] **AC-6 (Q31):** Given user ber-PIC operasional (bukan role), When mengakses dashboard, Then keputusan akses tetap berdasarkan `dashboard:read` efektif. Dokumen tidak mengasumsikan role PIC memiliki permission tersebut sampai preset PIC dikonfirmasi.
- [ ] **AC-7:** Given kegiatan pada periode berjalan, Then dashboard menampilkan status kegiatan secara real-time dengan label "belum diverifikasi", dan kegiatan yang melewati tanggal rencana tetapi belum terlaksana diberi peringatan. *(Q45 §45.3 butir 1)*
- [ ] **AC-8:** Given dashboard dibuka, Then total anggaran per IKU tampil dengan drill-down sampai kegiatan sesuai akses dashboard; kegiatan yang diklaim lebih dari satu IKU ditandai "kegiatan bersama". *(Q42 §42.2 butir 4–5, §42.5 butir 2)*
- [ ] **AC-9:** Given periode tanpa target, Then dashboard menampilkannya sebagai "Tanpa target periode", bukan "Belum tercapai", beserta realisasi, target PK, dan selisih terhadap target PK. *(Q47 §47.3)*
- [ ] **AC-10:** Given pengukuran berstatus `belum_dapat_diukur`, Then dashboard menampilkan "Belum dapat diukur" dan tidak menghitungnya sebagai capaian 0; bila periodenya juga tanpa target, yang tampil "Belum dapat diukur". *(Q47 §47.3 butir 3, §47.4 butir 3)*

### US-10.02 · Matriks Rekapitulasi Indikator × Periode

> **Q42, Q44, Q45, Q47, Q48, Q51 (13 Oktober 2026):** rekap menampilkan narasi pengukuran bersama daftar kegiatan yang diklaim dan anggarannya (Q45 §45.1 butir 2, Q42 §42.2), Rekomendasi Pimpinan yang berlaku yaitu berstatus disetujui (Q51 §51.1 butir 2), tampilan periode tanpa target dan realisasi belum dapat diukur sesuai Q47 §47.3–§47.4, serta penanda `tidak_dapat_dipenuhi` hanya sebagai histori (Q44 §44.1 butir 2). Dokumen resmi Rencana Aksi tahunan dan Laporan Kinerja Triwulan dibuat melalui US-10.05 (Q48). AC-4 sampai AC-6 diperbarui dan AC-7 ditambahkan karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-10.02` |
| **Prioritas** | 🟡 P1 · Tahap 2 (Q46 §46.3) |
| **Story Points** | 8 |
| **Modul** | Dashboard, Rekapitulasi, dan Ekspor |
| **Dependensi** | Pengguna memiliki akses laporan. |
| **Otorisasi** | `laporan:read`. |
| **Dampak Data** | Read-only atas versi resmi, snapshot, rekomendasi, status capaian, kegiatan/klaim/bukti yang dibekukan. |

> **Sebagai** Tim Perencanaan atau Pimpinan,  
> **Saya ingin** melihat matriks lengkap per indikator dan periode,  
> **Sehingga** seluruh rantai akuntabilitas dapat ditinjau dalam satu tampilan.

**Acceptance Criteria**

- [ ] **AC-1:** Given tahun/periode dipilih, Then tabel memuat identitas Sasaran, Indikator, Satuan, Unit, PIC efektif/historis yang relevan.
- [ ] **AC-2:** Then kolom target memisahkan baseline, target PK, dan target periode.
- [ ] **AC-3:** Then kolom realisasi memisahkan komponen, nilai akhir, status perhitungan, dan persentase capaian bila relevan.
- [ ] **AC-4:** Then narasi pengukuran (`progres_kegiatan`, `kendala_masalah`, `strategi_tindak_lanjut`, `tindak_lanjut_rekomendasi`) beserta daftar kegiatan yang diklaim dan anggarannya berasal dari versi resmi yang dinilai, bukan live data yang telah berubah. *(Diperbarui Q45 §45.1 butir 2, Q42 §42.2; semula narasi kegiatan/kendala/tindak lanjut.)*
- [ ] **AC-5:** Then Rekomendasi Pimpinan yang berlaku (berstatus `disetujui`), status capaian, dan status bukti ditampilkan sesuai versi/konteks. *(Diperbarui Q51 §51.1 butir 2)*
- [ ] **AC-6:** Given `tidak_dapat_dihitung`, `belum_dapat_diukur`, atau penanda histori `tidak_dapat_dipenuhi`, Then penanda ditampilkan dan tidak disamakan dengan nilai nol. *(Diperbarui Q44 §44.1 butir 2, Q47 §47.4 butir 3)*
- [ ] **AC-7:** Given periode tanpa target, Then rekap menampilkan "Tanpa target periode" beserta realisasi, target PK, dan selisih terhadap target PK tanpa capaian persen; bila realisasinya juga belum dapat diukur, yang tampil "Belum dapat diukur". *(Q47 §47.3)*

### US-10.03 · Ekspor Laporan Kinerja ke Excel

> **Q47, Q48 (13 Oktober 2026):** keluaran dokumen resmi Rencana Aksi tahunan dan Laporan Kinerja Triwulan dibuat melalui US-10.05 dengan permission `laporan:ekspor` yang sama (Q48 §48.1 butir 6); ekspor rekap pada story ini tetap berlaku. Penanda "Belum dapat diukur" dan "Tanpa target periode" pada ekspor mengikuti rekap US-10.02 dan tidak diubah menjadi 0 (Q47 §47.3–§47.4).

| Field | Detail |
|---|---|
| **ID** | `US-10.03` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 8 |
| **Modul** | Dashboard, Rekapitulasi, dan Ekspor |
| **Dependensi** | Pengguna memiliki akses ekspor; matriks laporan dapat dibentuk. |
| **Otorisasi** | `laporan:ekspor`. |
| **Dampak Data** | Stream `.xlsx`; audit event ekspor bila disepakati baseline. |

> **Sebagai** Tim Perencanaan atau Pimpinan,  
> **Saya ingin** mengekspor rekap kinerja ke Excel,  
> **Sehingga** laporan dapat digunakan pada proses formal tanpa mengubah makna data.

**Acceptance Criteria**

- [ ] **AC-1:** Given filter laporan dipilih, When ekspor dijalankan, Then `.xlsx` memisahkan baseline, target, realisasi, persentase, narasi, rekomendasi, dan status bukti.
- [ ] **AC-2:** Given Pimpinan memiliki `laporan:ekspor`, When ekspor dilakukan, Then berhasil sesuai scope baca.
- [ ] **AC-3:** Given Admin bawaan tidak memiliki `laporan:ekspor`, When endpoint dipanggil, Then 403 kecuali Admin menerima grant eksplisit yang sah.
- [ ] **AC-4:** Given data resmi historis diekspor, Then file membaca versi disahkan dan konteks beku.
- [ ] **AC-5:** Given nilai tidak dapat dihitung, Then ekspor menandainya secara eksplisit dan tidak mengubah menjadi 0.

### US-10.04 · Penerimaan Contoh Keluaran Excel untuk UAT

> **Q48 (13 Oktober 2026):** contoh keluaran yang disahkan Perencanaan juga menjadi acuan format Rencana Aksi tahunan dan Laporan Kinerja Triwulan (Q48 §48.1 butir 5; US-10.05).

| Field | Detail |
|---|---|
| **ID** | `US-10.04` |
| **Prioritas** | 🟠 P2 |
| **Story Points** | 3 |
| **Modul** | Dashboard, Rekapitulasi, dan Ekspor |
| **Dependensi** | Fitur ekspor tersedia; Tim Perencanaan menyediakan/menyetujui contoh penerimaan. |
| **Otorisasi** | Proses UAT/acceptance, bukan permission aplikasi baru. |
| **Dampak Data** | Artefak UAT/keputusan baseline; tidak menambah tabel domain. |

> **Sebagai** Tim Perencanaan / pemilik UAT,  
> **Saya ingin** mengesahkan satu contoh keluaran Excel sebagai acuan penerimaan,  
> **Sehingga** developer dan tester memiliki target format/informasi yang tidak ambigu.

**Acceptance Criteria**

- [ ] **AC-1:** Given build ekspor siap UAT, When contoh dibandingkan, Then baseline/target/realisasi/persentase dan informasi wajib dinilai kesetaraannya.
- [ ] **AC-2:** Given sumber lama memiliki susunan sel ambigu, Then UAT menilai kesetaraan informasi; aplikasi tidak wajib menyalin layout ambigu secara literal.
- [ ] **AC-3:** Given contoh belum disetujui, Then dokumen tidak mengklaim format final telah diterima.
- [ ] **AC-4:** Given perubahan format setelah persetujuan, Then perubahan harus masuk catatan keputusan/UAT baru.

### US-10.05 · Keluaran Rencana Aksi Tahunan dan Laporan Kinerja Triwulan serta Unggah PDF Bertanda Tangan

| Field | Detail |
|---|---|
| **ID** | `US-10.05` |
| **Prioritas** | Tahap 2 (Q46 §46.3: sebelum pelaporan TW IV 2026, awal Januari 2027) |
| **Story Points** | Belum diestimasi |
| **Modul** | Dashboard, Rekapitulasi, dan Ekspor |
| **Dependensi** | US-05.05 (RA disahkan), US-06.02/06.08 (kegiatan dan anggaran), US-07.03 (narasi), US-09.01 (rekomendasi disetujui), US-10.02/10.03 (rekap dan ekspor Excel), US-10.04 (contoh keluaran UAT). |
| **Otorisasi** | `laporan:ekspor` (membuat dan mengunduh); `laporan:unggah` (baru; mengunggah arsip; preset Perencanaan dan Superadmin; tidak bertanda sensitif karena unggahan append-only dan tidak mengubah data, sedangkan unggahan tetap diaudit, Q48 §48.2 butir 2). |
| **Dampak Data** | `berkas` dengan induk `jadwal_tahunan` dan `jadwal_periode`; `berkas.keterangan` (kolom baru, Q48 §48.2 butir 6); katalog permission (`laporan:unggah`); `audit_log`. |
| **Keputusan terkait** | Q48, Q47 §47.3, Q51, Q29, Q46 §46.3. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** membuat dokumen Rencana Aksi tahunan dan Laporan Kinerja Triwulan lembaga langsung dari SAKIP lalu mengunggah kembali PDF yang sudah ditandatangani,  
> **Sehingga** dokumen resmi disusun dari data yang disahkan dan arsip bertanda tangan tersimpan di satu tempat.

**Acceptance Criteria**

- [ ] **AC-1:** Given jadwal tahunan, When pemegang `laporan:ekspor` membuat Rencana Aksi tahunan, Then berkas Excel memuat sasaran, indikator, satuan, target PK, target per periode seluruh indikator, dan daftar kegiatan per indikator per periode dari RA versi disahkan. *(Q48 §48.1 butir 1)*
- [ ] **AC-2:** Given periode jadwal, When Laporan Kinerja Triwulan dibuat, Then berkas Excel memuat per indikator target PK, target periode, realisasi, capaian atau selisih terhadap target PK, analisis dari narasi pengukuran, dan Rekomendasi Pimpinan yang disetujui; status kegiatan per indikator dimuat sebagai lampiran tambahan. *(Q48 §48.1 butir 1, Q47 §47.3, Q51 §51.1)*
- [ ] **AC-3:** Given indikator yang RA atau pengukurannya belum disahkan, Then indikator tetap tercantum dengan keterangan "belum disahkan" tanpa angka resmi. *(Q48 §48.1 butir 2)*
- [ ] **AC-4:** Given periode tanpa target atau realisasi belum dapat diukur, Then keluaran memakai keterangan yang sama dengan dashboard ("Tanpa target periode", "Belum dapat diukur"). *(Q47 §47.3–§47.4)*
- [ ] **AC-5:** Given pengguna tanpa `laporan:ekspor`, When meminta keluaran atau mengunduh arsip, Then ditolak 403. *(Q48 §48.1 butir 6, §48.2 butir 2)*
- [ ] **AC-6:** Given Perencanaan dengan `laporan:unggah`, When mengunggah PDF bertanda tangan untuk Rencana Aksi tahunan (induk jadwal tahunan) atau Laporan Kinerja Triwulan (induk periode jadwal), Then berkas tersimpan beserta keterangan; satu induk boleh memiliki lebih dari satu berkas. *(Q48 §48.2 butir 1–2)*
- [ ] **AC-7:** Given arsip sudah diunggah, When dihapus, Then ditolak; koreksi dilakukan dengan mengunggah berkas pengganti beserta keterangan. *(Q48 §48.2 butir 3)*
- [ ] **AC-8:** Given arsip diunggah, Then data, status, dan snapshot tidak berubah, dan unggahan tercatat di audit. *(Q48 §48.2 butir 4)*
- [ ] **AC-9:** Given jadwal tahunan berstatus `ditutup`, When Perencanaan mengunggah arsip, Then unggahan diterima tanpa membuka kembali jadwal. *(Q48 §48.2 butir 5)*

**Business Rules / Catatan**

- Rincian Output dan realisasi anggaran per Rincian Output disusun di luar SAKIP (Q48 §48.1 butir 3).
- Dokumen PK tidak dihasilkan SAKIP; lampiran PK tetap lewat halaman PK (Q48 §48.1 butir 4, §48.2 butir 1).
- Format mengikuti contoh keluaran yang disahkan Perencanaan (Q29); PDF tetap fase lanjutan (PRD §24.2).
- Bila pembuatan berkas memerlukan pustaka baru, catat sebagai ADR.
- Keterangan berkas (mis. "Rencana Aksi perubahan") disimpan pada kolom baru `berkas.keterangan` (nullable); `alasan_koreksi` tetap khusus untuk berkas pengganti (Q48 §48.2 butir 6).
- Arsip Rencana Aksi tahunan dan Laporan Kinerja Triwulan hanya menerima berkas PDF, dengan batas ukuran mengikuti batas teknis Q44 §44.2 (Q48 §48.2 butir 7).

---

## Bagian 11 — Bukti Dukung Multi-Mode & Integritas Lampiran

### US-11.01 · Konfigurasi Persyaratan Jenis Berkas

> **Q42, Q43, Q44 (13 Oktober 2026):** tahap persyaratan memperoleh nilai baru `kegiatan_perencanaan`, dan persyaratan dapat dilampiri contoh berkas melalui induk `berkas` baru `jenis_berkas` (Q42 §42.3 butir 2, §42.4 butir 3; US-06.08). `ukuran_maks_kb` dibatasi batas atas 100 MB (102400 KB), format atau ukuran yang dikosongkan memakai default konfigurasi, dan menu Persyaratan Berkas dipindah ke grup Perencanaan Kinerja (Q44 §44.2–§44.3; US-13.06). Alasan perubahan persyaratan menjadi opsional (Q43 §43.3 butir 3; US-13.05).

| Field | Detail |
|---|---|
| **ID** | `US-11.01` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 8 |
| **Modul** | Bukti Dukung Multi-Mode & Integritas Lampiran |
| **Dependensi** | Indikator/unit tersedia bila persyaratan bersifat spesifik. |
| **Otorisasi** | `jenis_berkas:create`, `jenis_berkas:read`, `jenis_berkas:update`, `jenis_berkas:delete`. |
| **Dampak Data** | `jenis_berkas`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** menetapkan persyaratan bukti per tahap, mode, wajib/tidak, dan batas file,  
> **Sehingga** standar bukti dapat dikonfigurasi tanpa deployment kode.

**Acceptance Criteria**

- [x] **AC-1:** Given persyaratan baru, When tahap, mode, wajib, batas ukuran/format disimpan, Then data valid tersimpan.
- [x] **AC-2:** Given tidak ada mode yang diizinkan, When submit, Then ditolak.
- [x] **AC-3:** Given `semua_mode_wajib = true`, Then seluruh mode yang diizinkan harus dipenuhi.
- [x] **AC-4:** Given perubahan substansi persyaratan dilakukan, Then audit menyimpan before/after dan alasan bila diwajibkan.
- [x] **AC-5:** Given persyaratan sudah dibekukan pada versi submit lama, Then perubahan master hanya berlaku untuk pengajuan berikutnya.

### US-11.02 · Unggah File Privat & Streamed Download

> **Q42, Q44, Q48 (13 Oktober 2026):** unggahan file selalu aktif sehingga prasyarat saklar unggahan tidak lagi berlaku; batas fallback berasal dari konfigurasi aplikasi (default 10 MB dan `pdf,docx,xlsx,jpg,jpeg,png`, batas atas 100 MB) (Q44 §44.1 butir 2, §44.2; US-13.06). Induk `berkas` bertambah `jenis_berkas` untuk contoh berkas (Q42 §42.4 butir 3), `jadwal_tahunan` dan `jadwal_periode` untuk arsip dokumen resmi (Q48 §48.2), serta versi PK (Q40, Q48 §48.2 butir 1; US-02.12). Penyimpanan tetap privat dan unduhan melalui route berizin (Q44 §44.1 butir 4).

| Field | Detail |
|---|---|
| **ID** | `US-11.02` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Bukti Dukung Multi-Mode & Integritas Lampiran |
| **Dependensi** | Capability induk valid. *(Prasyarat `berkas.unggahan_aktif = true` dihapus Q44.)* |
| **Otorisasi** | Capability berkas mengikuti induk; `berkas:read`/deny tetap dievaluasi sesuai kontrak akses. |
| **Dampak Data** | `berkas`, private filesystem, `audit_log`. |

> **Sebagai** Pengguna berwenang,  
> **Saya ingin** mengunggah dan mengunduh file bukti melalui storage privat,  
> **Sehingga** dokumen tidak terekspos sebagai URL publik.

**Acceptance Criteria**

- [ ] **AC-1:** Given file valid, When upload, Then file disimpan pada disk privat dan metadata nama asli, MIME, ukuran, path aman disimpan.
- [ ] **AC-2:** Given ukuran/format melanggar batas persyaratan/fallback, When upload, Then ditolak sebelum file final tersimpan.
- [ ] **AC-3:** Given path fisik ditebak tanpa autentikasi, When diakses, Then tidak tersedia sebagai public URL.
- [ ] **AC-4:** Given pengguna meminta download, Then server mengevaluasi hak baca induk dan deny sebelum mengalirkan response.
- [ ] **AC-5:** Given token/credential/storage path sensitif dicatat audit, Then hanya metadata yang diperlukan yang boleh tampil; secret tidak boleh bocor.

### US-11.03 · Bukti Mode Tautan atau Teks

| Field | Detail |
|---|---|
| **ID** | `US-11.03` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Bukti Dukung Multi-Mode & Integritas Lampiran |
| **Dependensi** | Persyaratan mengizinkan tautan/teks. |
| **Otorisasi** | Capability bukti induk. |
| **Dampak Data** | `berkas`, `audit_log`. |

> **Sebagai** Pengguna berwenang,  
> **Saya ingin** memenuhi bukti melalui URL resmi atau keterangan teks,  
> **Sehingga** proses tetap berjalan saat file fisik tidak diperlukan.

**Acceptance Criteria**

- [ ] **AC-1:** Given mode tautan dipilih, When URL `http/https` valid disimpan, Then URL tersimpan tanpa memakai kuota disk file.
- [ ] **AC-2:** Given mode teks dipilih, When isi valid disimpan, Then teks tersimpan pada kolom yang tepat.
- [ ] **AC-3:** Given mode tidak diizinkan oleh jenis persyaratan, Then ditolak.
- [ ] **AC-4:** Given audit untuk teks, Then audit tidak harus menyalin isi sensitif penuh; cukup metadata yang diperlukan sesuai kebijakan.

### US-11.04 · Imutabilitas Berkas Berdasarkan Induk

> **Q42, Q48 (13 Oktober 2026):** arsip dokumen resmi pada induk `jadwal_tahunan` dan `jadwal_periode` tidak dapat dihapus; salah unggah dikoreksi dengan berkas pengganti beserta keterangan (Q48 §48.2 butir 3; US-10.05). Contoh berkas pada induk `jenis_berkas` bukan bukti: Perencanaan dapat menambah, mengganti, atau menghapusnya kapan saja dengan alasan wajib saat menghapus, dan contoh berkas tidak dibekukan ke versi pengajuan (Q42 §42.3 butir 4; US-06.08).

| Field | Detail |
|---|---|
| **ID** | `US-11.04` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Bukti Dukung Multi-Mode & Integritas Lampiran |
| **Dependensi** | Berkas terkait salah satu induk yang didukung. |
| **Otorisasi** | `berkas:delete` (sensitif) untuk induk dokumen dasar (`renstra`/`renstra_pk`/`regulasi`); untuk `rencana_aksi`/`pengukuran`/`kegiatan` hak hapus turun dari izin mutasi induk ber-scope unit, dengan `berkas:delete` sebagai gerbang deny (Q32.3); + guard status induk (Q36). |
| **Dampak Data** | `berkas`, `audit_log`. |

> **Sebagai** Sistem Integritas SAKIP,  
> **Saya ingin** mencegah penghapusan bukti setelah mencapai batas legal/versi resmi,  
> **Sehingga** bukti yang telah menjadi dasar keputusan tidak dapat dimanipulasi.

**Acceptance Criteria**

- [ ] **AC-1:** Given berkas RA/Pengukuran telah dirujuk versi resmi/disahkan, When delete dicoba, Then ditolak.
- [ ] **AC-2:** Given kegiatan telah terlaksana dan bukti menjadi dasar gerbang, When delete dicoba, Then ditolak; koreksi append-only.
- [ ] **AC-3:** Given Renstra aktif, When lampiran resmi Renstra dihapus, Then ditolak.
- [ ] **AC-4:** Given PK sudah menjadi dasar jadwal aktif, When lampiran PK dihapus, Then ditolak.
- [ ] **AC-5:** Given regulasi masih dirujuk Renstra/Indikator aktif, When lampiran regulasi dihapus, Then ditolak.
- [ ] **AC-6:** Given batas imutabilitas belum tercapai dan aktor berwenang, Then soft delete dapat dilakukan dengan `dihapus_pada/dihapus_oleh` serta audit.

### US-11.05 · Pembekuan Persyaratan Bukti Saat Pengajuan

| Field | Detail |
|---|---|
| **ID** | `US-11.05` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 5 |
| **Modul** | Bukti Dukung Multi-Mode & Integritas Lampiran |
| **Dependensi** | RA/Pengukuran siap submit. |
| **Otorisasi** | Mengikuti permission submit induk. |
| **Dampak Data** | `rencana_aksi_versi` / `pengukuran_versi` snapshot, `jenis_berkas`, `berkas`. |

> **Sebagai** Sistem,  
> **Saya ingin** membekukan persyaratan dan bukti yang digunakan pada setiap versi pengajuan,  
> **Sehingga** perubahan persyaratan di tengah proses tidak mengubah standar review yang sudah berlaku diam-diam.

**Acceptance Criteria**

- [ ] **AC-1:** Given submit terjadi, Then versi menyimpan jenis/mode/wajib persyaratan serta bukti/pengecualian yang dipakai.
- [ ] **AC-2:** Given master `jenis_berkas` berubah setelah submit, Then versi yang sedang direviu tidak ikut berubah.
- [ ] **AC-3:** Given ketentuan baru harus diterapkan pada proses berjalan, Then proses harus dikembalikan beralasan dan diajukan ulang sebagai versi baru.
- [ ] **AC-4:** Given hasil sudah disahkan, Then perubahan persyaratan baru tidak otomatis membatalkan hasil lama.

### US-11.06 · Pengecualian Mode File Saat Unggah Dinonaktifkan *(dibatalkan Q44)*

> **Dibatalkan Q44 (13 Oktober 2026):** saklar unggahan dihapus sehingga unggahan file selalu aktif dan pengecualian mode file karena unggahan dimatikan tidak lagi terjadi (Q44 §44.1 butir 2 dan 5). Story ini tidak dikerjakan; isi di bawah dipertahankan sebagai histori. Penanda `tidak_dapat_dipenuhi` yang sudah tercatat tetap tampil sebagai histori (US-13.06).

| Field | Detail |
|---|---|
| **ID** | `US-11.06` |
| **Prioritas** | Dibatalkan Q44 (semula 🟡 P1) |
| **Story Points** | 5 |
| **Modul** | Bukti Dukung Multi-Mode & Integritas Lampiran |
| **Dependensi** | Setelan global unggah file mati dan persyaratan tertentu memerlukan file. |
| **Otorisasi** | Evaluasi sistem; pengaturan dikelola `pengaturan:update`. |
| **Dampak Data** | `jenis_berkas`, `berkas`, penanda/audit pengecualian. |

> **Sebagai** Sistem / Tim Perencanaan,  
> **Saya ingin** menerapkan pengecualian hanya pada mode file yang tidak dapat dipenuhi,  
> **Sehingga** pelaporan tidak macet tanpa mengabaikan mode bukti lain.

**Acceptance Criteria**

- [ ] **AC-1:** Given `berkas.unggahan_aktif = false`, When persyaratan membutuhkan file, Then kewajiban mode file dapat ditandai `tidak_dapat_dipenuhi` sesuai kontrak dan audit.
- [ ] **AC-2:** Given persyaratan juga mewajibkan tautan/teks, Then mode non-file tersebut tetap wajib.
- [ ] **AC-3:** Given gerbang PK tidak punya file namun unggah mati, Then tautan/teks tetap dapat memenuhi; jika tidak ada mode alternatif, pengecualian khusus PK dicatat tanpa menyamarkannya sebagai bukti nyata.
- [ ] **AC-4:** Given upload kembali aktif, Then persyaratan pengajuan berikutnya kembali mengikuti mode master saat itu; versi lama tetap menyimpan keputusan pengecualian yang dahulu berlaku.

---

## Bagian 12 — Alert Kontekstual & Notifikasi


> **Aturan Q31 untuk penerima PIC:** frasa “PIC terkait” pada notifikasi berarti penerima efektif menurut konteks indikator/assignment/work item yang sah, **bukan seluruh user yang kebetulan memiliki PIC operasional (bukan role)**. Query penerima harus menggunakan sumber data domain yang relevan dan permission/hak baca saat tautan dibuka.


### US-12.01 · Banner Pengingat Kontekstual

> **Q41, Q44 (13 Oktober 2026):** banner tenggat penyusunan RA pada AC-1 dihapus karena Rencana Aksi tidak lagi memakai jendela penyusunan (Q41 §41.1). Penanda `tidak_dapat_dipenuhi` baru tidak lagi terjadi karena unggahan selalu aktif; penanda lama tetap histori (Q44 §44.1 butir 2). AC-1 dan AC-4 diperbarui karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-12.01` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Alert Kontekstual & Notifikasi |
| **Dependensi** | Pengguna autentikasi; data jadwal/status tersedia. |
| **Otorisasi** | Mengikuti akses dashboard/halaman kerja. |
| **Dampak Data** | Read-only hasil evaluasi server. |

> **Sebagai** PIC dan pengguna terkait,  
> **Saya ingin** melihat banner tenggat dan masalah kelengkapan yang relevan,  
> **Sehingga** pengguna mengetahui kewajiban tanpa menghitung status sendiri di klien.

**Acceptance Criteria**

- [ ] **AC-1:** *(Dihapus Q41 §41.1; tidak dikerjakan.)* Semula: Given jendela RA aktif/mendekati batas, When halaman dibuka, Then banner menampilkan `rencana_aksi_selesai` sesuai konteks.
- [ ] **AC-2:** Given periode pengisian aktif, Then countdown mengacu `pengisian_selesai` resmi.
- [ ] **AC-3:** Given indikator belum diisi, Then status muncul hanya untuk periode yang berlaku.
- [ ] **AC-4:** Given PK belum lengkap, Then Perencanaan mendapat penanda yang relevan. *(Diperbarui Q44 §44.1 butir 2; semula juga untuk bukti `tidak_dapat_dipenuhi`, yang tidak lagi terbentuk.)*
- [ ] **AC-5:** Given props dikirim ke React, Then seluruh evaluasi status/tenggat dilakukan server-side.

### US-12.02 · Lonceng Notifikasi & Antrean Tugas In-App

| Field | Detail |
|---|---|
| **ID** | `US-12.02` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 8 |
| **Modul** | Alert Kontekstual & Notifikasi |
| **Dependensi** | Pengguna autentikasi. |
| **Otorisasi** | Seluruh pengguna autentikasi sesuai event yang berhak dilihat. |
| **Dampak Data** | Penyimpanan notifikasi/read-state sesuai implementasi. |

> **Sebagai** PIC, Perencanaan, Admin,  
> **Saya ingin** melihat unread counter dan daftar tugas yang memerlukan tindakan,  
> **Sehingga** event penting tidak hanya bergantung pada pengecekan halaman manual.

**Acceptance Criteria**

- [ ] **AC-1:** Given RA/Pengukuran dikembalikan, Then PIC menerima item berisi ringkasan alasan dan tautan aman.
- [ ] **AC-2:** Given PIC mengajukan RA/Pengukuran, Then Perencanaan menerima item antrean review.
- [ ] **AC-3:** Given jadwal pengisian dibuka, Then PIC terkait dapat menerima notifikasi in-app.
- [ ] **AC-4:** Given Admin memiliki event akses/audit yang ditetapkan, Then hanya event yang sesuai haknya yang tampil.
- [ ] **AC-5:** Given notifikasi dibuka, Then read-state berubah dan unread counter berkurang.

### US-12.03 · Broadcast Pembukaan Jadwal Pengisian via WhatsApp & Email

| Field | Detail |
|---|---|
| **ID** | `US-12.03` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Alert Kontekstual & Notifikasi |
| **Dependensi** | Integrasi eksternal aktif; sumber kontak dan template telah ditetapkan pemilik layanan. |
| **Otorisasi** | Event sistem setelah waktu `pengisian_mulai`/pembukaan resmi. |
| **Dampak Data** | Queue/job dan log status pengiriman tanpa secret. |

> **Sebagai** PIC terkait,  
> **Saya ingin** menerima pemberitahuan pembukaan pengisian melalui WhatsApp dan Email,  
> **Sehingga** PIC mengetahui dimulainya jendela walau sedang tidak membuka aplikasi.

**Acceptance Criteria**

- [ ] **AC-1:** Given tanggal/waktu pembukaan resmi tercapai, When pemicu berjalan, Then job WA dan Email didispatch ke PIC yang relevan.
- [ ] **AC-2:** Given jadwal diaktifkan jauh sebelum `pengisian_mulai`, Then broadcast pembukaan tidak dikirim terlalu awal.
- [ ] **AC-3:** Given provider gagal, Then transaksi bisnis pembukaan jadwal tidak dirollback.
- [ ] **AC-4:** Given job diulang, Then idempotency mencegah pesan ganda untuk event-penerima-periode-channel yang sama.

### US-12.04 · EWS H-7, H-3, H-1 Menjelang Tenggat Pengisian

| Field | Detail |
|---|---|
| **ID** | `US-12.04` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Alert Kontekstual & Notifikasi |
| **Dependensi** | Periode aktif; konfigurasi notifikasi tersedia; jam kirim/zona waktu ditetapkan pemilik layanan sebelum aktivasi nyata. |
| **Otorisasi** | Scheduler sistem. |
| **Dampak Data** | Queue/log pengiriman. |

> **Sebagai** PIC yang belum menyelesaikan pengajuan,  
> **Saya ingin** menerima pengingat hanya pada H-7, H-3, dan H-1,  
> **Sehingga** risiko keterlambatan berkurang tanpa spam harian.

**Acceptance Criteria**

- [ ] **AC-1:** Given scheduler mengevaluasi harian, When tanggal tepat H-7/H-3/H-1 dari `pengisian_selesai`, Then PIC yang belum selesai menjadi kandidat penerima.
- [ ] **AC-2:** Given tanggal H-6/H-5/H-4/H-2, Then scheduler tidak mendispatch pengingat PIC meskipun tetap melakukan pengecekan.
- [ ] **AC-3:** Given pengukuran sudah diajukan dan tidak sedang dikembalikan untuk revisi, Then PIC dikeluarkan dari daftar pengingat.
- [ ] **AC-4:** Given pengukuran dikembalikan dan belum diajukan ulang, Then tetap dianggap pekerjaan belum selesai.
- [ ] **AC-5:** Given jam operasional belum ditetapkan, Then dokumen tidak menghardcode pukul 08.00 sebagai requirement final.

### US-12.05 · Rekap Progres untuk Tim Perencanaan H-3 & H-1

| Field | Detail |
|---|---|
| **ID** | `US-12.05` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Alert Kontekstual & Notifikasi |
| **Dependensi** | Periode aktif; kanal eksternal aktif. |
| **Otorisasi** | Scheduler sistem. |
| **Dampak Data** | Queue/log pengiriman. |

> **Sebagai** Tim Perencanaan yang menjadi penerima efektif,  
> **Saya ingin** menerima rekap unit/indikator belum selesai pada H-3 dan H-1,  
> **Sehingga** koordinasi menjelang deadline dapat dilakukan proaktif.

**Acceptance Criteria**

- [ ] **AC-1:** Given H-3 atau H-1, When job berjalan, Then ringkasan sudah/belum submit dikirim melalui WA dan Email kepada penerima Perencanaan yang ditetapkan.
- [ ] **AC-2:** Given pengguna tidak lagi menjadi penerima efektif, Then tidak otomatis menerima rekap.
- [ ] **AC-3:** Given job diulang, Then pesan tidak diduplikasi untuk kombinasi event/penerima/periode/channel yang sama.
- [ ] **AC-4:** Given provider gagal, Then kegagalan dicatat dan retry dilakukan terkontrol.

### US-12.06 · Notifikasi Instan Pengembalian RA/Pengukuran via WhatsApp & Email

| Field | Detail |
|---|---|
| **ID** | `US-12.06` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Alert Kontekstual & Notifikasi |
| **Dependensi** | Aksi `rencana_aksi:kembalikan` atau `pengukuran:kembalikan` berhasil commit dan memiliki alasan. |
| **Otorisasi** | Dipicu event bisnis setelah commit. |
| **Dampak Data** | Queue WA/Email dan log status. |

> **Sebagai** PIC/pengaju terkait,  
> **Saya ingin** menerima notifikasi eksternal ketika pengajuannya dikembalikan,  
> **Sehingga** perbaikan dapat segera dilakukan sebelum jendela berakhir.

**Acceptance Criteria**

- [ ] **AC-1:** Given transaksi pengembalian sukses, Then job WA **dan Email** didispatch setelah commit.
- [ ] **AC-2:** Given transaksi database gagal/rollback, Then notifikasi eksternal tidak dikirim.
- [ ] **AC-3:** Given pesan dibuat, Then memuat ringkasan alasan dan tautan aman yang tetap memerlukan autentikasi.
- [ ] **AC-4:** Given provider gagal, Then status bisnis tetap `dikembalikan` dan pengiriman dapat diretry terkontrol.
- [ ] **AC-5:** Given hak baca berubah setelah pesan dikirim, Then tautan tidak boleh melewati pemeriksaan otorisasi saat dibuka.

### US-12.07 · Idempotensi, Retry, dan Status Pengiriman Notifikasi Eksternal

| Field | Detail |
|---|---|
| **ID** | `US-12.07` |
| **Prioritas** | 🟠 P2 |
| **Story Points** | 5 |
| **Modul** | Alert Kontekstual & Notifikasi |
| **Dependensi** | Job notification channel tersedia. |
| **Otorisasi** | Proses sistem. |
| **Dampak Data** | Log/idempotency key pengiriman. |

> **Sebagai** Pengelola operasional sistem,  
> **Saya ingin** memastikan pengiriman dapat diretry tanpa menggandakan pesan dan tanpa mengklaim delivery yang tidak terbukti,  
> **Sehingga** integrasi notifikasi stabil dan dapat diaudit.

**Acceptance Criteria**

- [ ] **AC-1:** Given event yang sama diproses lebih dari sekali, Then idempotency key event-penerima-periode-channel mencegah duplikasi.
- [ ] **AC-2:** Given provider timeout/error, Then retry mengikuti kebijakan terkontrol dan secret tidak masuk log.
- [ ] **AC-3:** Given provider hanya mengembalikan accepted/queued, Then sistem tidak menyebut status tersebut sebagai delivered ke penerima.
- [ ] **AC-4:** Given credential/provider belum ditetapkan, Then fitur integrasi nyata belum dianggap siap produksi walaupun unit test lulus.

---

## Bagian 13 — Setelan Aplikasi, Audit, dan Kebijakan Operasional

### US-13.01 · Setelan Identitas & Preferensi Presentasional

> **Q43 (13 Oktober 2026):** alasan perubahan setelan aplikasi bersifat opsional, sedangkan audit nilai lama/baru tetap dicatat (Q43 §43.1, §43.3 butir 3; US-13.05).

| Field | Detail |
|---|---|
| **ID** | `US-13.01` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Setelan Aplikasi, Audit, dan Kebijakan Operasional |
| **Dependensi** | Pengaturan tersedia. |
| **Otorisasi** | `pengaturan:update` (Admin/Superadmin bawaan). |
| **Dampak Data** | `pengaturan`, `audit_log`. |

> **Sebagai** Admin atau Superadmin,  
> **Saya ingin** mengubah identitas instansi/aplikasi, label unit, zona waktu, format tanggal/angka, dan header/footer laporan,  
> **Sehingga** preferensi tampilan dapat berubah tanpa deployment kode.

**Acceptance Criteria**

- [x] **AC-1:** Given kunci yang termasuk whitelist presentasional, When diperbarui, Then nilai tersimpan dan cache diperbarui.
- [x] **AC-2:** Given perubahan disimpan, Then audit merekam nilai lama/baru.
- [x] **AC-3:** Given pengguna tanpa permission, Then 403.
- [x] **AC-4:** Given pengguna mencoba mengubah enum/status/permission/aturan bisnis lewat tabel pengaturan, Then sistem menolak karena di luar cakupan.

### US-13.02 · Kebijakan Storage & Saklar Unggah File

> **Q44 (13 Oktober 2026):** menu Kebijakan Storage, saklar unggahan, panel penggunaan penyimpanan, dan pengelolaan batas default dari UI dihapus; unggahan selalu aktif dan batas teknis berasal dari konfigurasi aplikasi (Q44 §44.1–§44.2). Penghapusan dikerjakan pada US-13.06; AC di bawah dipertahankan sebagai histori implementasi.

| Field | Detail |
|---|---|
| **ID** | `US-13.02` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Setelan Aplikasi, Audit, dan Kebijakan Operasional |
| **Dependensi** | Admin/Superadmin autentikasi. |
| **Otorisasi** | `pengaturan:update`. |
| **Dampak Data** | `pengaturan`, metrik storage read-only, `audit_log`. |

> **Sebagai** Admin atau Superadmin,  
> **Saya ingin** mengatur kebijakan teknis upload dan memantau penggunaan storage,  
> **Sehingga** kapasitas VPS dapat dikendalikan tanpa mengubah persyaratan substantif milik Perencanaan.

**Acceptance Criteria**

- [x] **AC-1:** Given `berkas.unggahan_aktif = false`, Then mode file dinonaktifkan global sedangkan tautan/teks tetap dapat dipakai.
- [x] **AC-2:** Given panel storage dibuka, Then menampilkan jumlah file, total bytes, serta jumlah bukti tautan/teks.
- [x] **AC-3:** Given batas default format/ukuran diubah, Then perubahan hanya berfungsi sebagai fallback/kebijakan teknis; persyaratan spesifik tetap milik `jenis_berkas`.
- [x] **AC-4:** Given perubahan dilakukan, Then audit mencatat before/after.

### US-13.03 · Penelusuran Audit Trail Append-Only

| Field | Detail |
|---|---|
| **ID** | `US-13.03` |
| **Prioritas** | 🔴 P0 · Tahap 3 (Q46 §46.3) |
| **Story Points** | 8 |
| **Modul** | Setelan Aplikasi, Audit, dan Kebijakan Operasional |
| **Dependensi** | Audit log tersedia. |
| **Otorisasi** | `audit:read`. |
| **Dampak Data** | Read-only `audit_log`. |

> **Sebagai** Admin, Superadmin, Tim Perencanaan, atau role lain yang memiliki izin,  
> **Saya ingin** mencari dan membaca seluruh peristiwa penting serta percobaan yang ditolak,  
> **Sehingga** alasan suatu perubahan/penolakan dapat direkonstruksi.

**Acceptance Criteria**

- [ ] **AC-1:** Given filter waktu/aktor/tindakan/objek, When dicari, Then hasil audit yang cocok ditampilkan.
- [ ] **AC-2:** Given aksi permission sensitif, Then detail audit memuat `dasar_izin` sumber allow atau deny pemicu.
- [ ] **AC-3:** Given aksi ditolak karena gate/deny/F1, Then percobaan penting dapat dicatat dengan alasan penolakan.
- [ ] **AC-4:** Given audit telah ditulis, Then tidak tersedia endpoint update/delete.
- [ ] **AC-5:** Given payload audit memuat data sensitif, Then token, secret, password, atau credential tidak boleh disimpan.

### US-13.04 · Konfigurasi Operasional Notifikasi Eksternal

| Field | Detail |
|---|---|
| **ID** | `US-13.04` |
| **Prioritas** | 🟠 P2 |
| **Story Points** | 5 |
| **Modul** | Setelan Aplikasi, Audit, dan Kebijakan Operasional |
| **Dependensi** | Pemilik layanan/provider telah dipilih. |
| **Otorisasi** | `pengaturan:update` untuk saklar/parameter non-secret; secret melalui environment/secret management. |
| **Dampak Data** | `pengaturan` grup notifikasi + konfigurasi environment. |

> **Sebagai** Admin/Superadmin bersama pengelola infrastruktur,  
> **Saya ingin** mengaktifkan kanal, template, daftar H-minus, dan parameter non-rahasia,  
> **Sehingga** integrasi WA/Email dapat dikelola tanpa menaruh credential di database biasa atau UI.

**Acceptance Criteria**

- [ ] **AC-1:** Given daftar H-minus default, Then nilai awal adalah `7,3,1` dan dapat dibaca scheduler.
- [ ] **AC-2:** Given credential API, Then credential tidak ditampilkan di UI/log/audit dan disimpan lewat mekanisme secret yang sesuai.
- [ ] **AC-3:** Given provider belum siap, Then saklar kanal dapat dinonaktifkan tanpa mengganggu alur bisnis inti.
- [ ] **AC-4:** Given perubahan parameter non-secret, Then audit menyimpan perubahan tanpa menyalin secret.

### US-13.05 · Penyederhanaan Kewajiban Alasan

| Field | Detail |
|---|---|
| **ID** | `US-13.05` |
| **Prioritas** | Tahap 1 (Q46 §46.3: sebelum integrasi 9 November 2026) |
| **Story Points** | Belum diestimasi |
| **Modul** | Setelan Aplikasi, Audit, dan Kebijakan Operasional (lintas modul) |
| **Dependensi** | Fitur terdampak sudah ada: Grant Unit (US-01.04), PJ (US-04.01), persyaratan berkas (US-11.01), pindah unit indikator (US-02.05), aktivasi jadwal (US-03.02), setelan (US-13.01), PK (US-02.08). |
| **Otorisasi** | Tidak berubah. |
| **Dampak Data** | `user_permission_granted.alasan` (tetap NOT NULL; alasan baku server), `penanggung_jawab.alasan` (nullable; field form dihapus), `audit_log`. |
| **Keputusan terkait** | Q43, Q37 §37.2, Q14. |

> **Sebagai** pengguna yang mengelola data dan akses,  
> **Saya ingin** hanya diminta alasan pada aksi yang alasannya memberi informasi tambahan,  
> **Sehingga** pekerjaan rutin tidak terhambat sementara jejak audit tetap lengkap.

**Acceptance Criteria**

- [ ] **AC-1:** Given Grant Unit diberikan tanpa alasan, When disimpan, Then tersimpan dengan alasan baku dari server; bila pengguna mengisi alasan, alasan itu yang disimpan. *(Q43 §43.3 butir 1)*
- [ ] **AC-2:** Given form penetapan atau pergantian Penanggung Jawab, Then field alasan tidak ditampilkan dan request tanpa alasan diterima. *(Q43 §43.3 butir 4)*
- [ ] **AC-3:** Given perubahan persyaratan berkas, pindah unit indikator, aktivasi jadwal, perubahan setelan aplikasi, atau perubahan PK sebelum PK dipakai jadwal aktif dikirim tanpa alasan, Then diterima; UI menampilkan "Alasan (opsional)". *(Q43 §43.3 butir 3)*
- [ ] **AC-4:** Given aksi yang tercantum pada Q43 §43.2 dikirim tanpa alasan, Then tetap ditolak. *(Q43 §43.2)*
- [ ] **AC-5:** Given aksi apa pun, Then audit tetap mencatat aktor, waktu, tindakan, nilai lama dan baru, serta `dasar_izin` untuk permission sensitif. *(Q43 §43.1)*

**Business Rules / Catatan**

- Kewajiban alasan dari keputusan sebelumnya yang tidak disebut Q43 §43.3 tetap berlaku.
- Perubahan target PK sudah opsional (Q43 §43.3 butir 2).
- Implementation tasks pada ISS-13.05 memuat tabel per request: `StoreUnitGrantRequest` + `CreateUnitGrantAction` (alasan baku), form/request Penanggung Jawab, `StoreJenisBerkasRequest`/`UpdateJenisBerkasRequest`, `PindahUnitIndikatorRequest`, `ActivateJadwalRequest`, `UpdatePengaturanRequest`, `UpdatePerjanjianKinerjaRequest` (opsional sebelum PK dipakai jadwal aktif; koreksi sesudahnya tetap wajib alasan, US-02.09). Tanpa nomor baris kode.

### US-13.06 · Penghapusan Kebijakan Storage, Batas Teknis Unggahan di Konfigurasi, dan Penataan Menu Referensi

| Field | Detail |
|---|---|
| **ID** | `US-13.06` |
| **Prioritas** | Tahap 1 (Q46 §46.3: sebelum integrasi 9 November 2026) |
| **Story Points** | Belum diestimasi |
| **Modul** | Setelan Aplikasi, Audit, dan Kebijakan Operasional; Bukti Dukung Multi-Mode & Integritas Lampiran |
| **Dependensi** | US-13.02 (Kebijakan Storage, sudah ada), US-11.01 (persyaratan berkas); US-11.06 dibatalkan. |
| **Otorisasi** | Tidak berubah; menu tetap mengikuti `can.*`. |
| **Dampak Data** | Kunci grup `berkas` pada `pengaturan` (tidak lagi dibaca atau dikelola UI; histori), `jenis_berkas.ukuran_maks_kb` ≤ 102400, konfigurasi aplikasi (default 10 MB dan format default; batas atas 100 MB). |
| **Keputusan terkait** | Q44 (menggantikan Q23). |

> **Sebagai** Tim Perencanaan dan Admin,  
> **Saya ingin** batas unggahan ditetapkan per persyaratan berkas dengan batas teknis dari server, tanpa halaman kebijakan storage terpisah, dan menu referensi yang tertata,  
> **Sehingga** pengaturan berkas tidak berulang di dua tempat dan unggahan tidak pernah dimatikan secara tidak sengaja.

**Acceptance Criteria**

- [ ] **AC-1:** Given pengguna mana pun, Then menu Kebijakan Storage dan halaman Batas Unggahan Berkas tidak tersedia, dan route lamanya tidak lagi melayani halaman tersebut. *(Q44 §44.1 butir 1)*
- [ ] **AC-2:** Given unggahan file, Then selalu aktif tanpa saklar; penanda `tidak_dapat_dipenuhi` akibat unggahan dimatikan tidak lagi dibuat, sedangkan penanda lama tetap tampil sebagai histori. *(Q44 §44.1 butir 2)*
- [ ] **AC-3:** Given lampiran tanpa persyaratan (Renstra, PK, Dasar Aturan, lampiran bebas kegiatan) atau persyaratan yang mengosongkan format atau ukuran, Then server menerapkan default konfigurasi 10 MB dan `pdf,docx,xlsx,jpg,jpeg,png`. *(Q44 §44.2 butir 1)*
- [ ] **AC-4:** Given persyaratan disimpan dengan `ukuran_maks_kb` di atas 102400, Then ditolak; Given unggahan melebihi batas atas, Then ditolak. *(Q44 §44.2 butir 1)*
- [ ] **AC-5:** Given halaman Persyaratan Berkas, Then mode "Ubah batas teknis" dan peringatan unggahan dimatikan dihapus; format dan ukuran diubah Perencanaan lewat form persyaratan. *(Q44 §44.1 butir 1–2)*
- [ ] **AC-6:** Given panel penggunaan penyimpanan, Then panel dihapus. *(Q44 §44.1 butir 3)*
- [ ] **AC-7:** Given sidebar, Then Dasar Aturan berada di grup Master Data & Referensi dan Persyaratan Berkas di grup Perencanaan Kinerja; permission tidak berubah. *(Q44 §44.3)*
- [ ] **AC-8:** Given berkas tersimpan, Then penyimpanan tetap privat dan unduhan tetap melalui route berizin. *(Q44 §44.1 butir 4)*

**Business Rules / Catatan**

- Perubahan batas teknis lewat konfigurasi deployment dan dicatat pada catatan rilis (Q44 §44.2 butir 3).
- Cabang anti-macet unggahan pada gerbang lampiran PK, pengajuan RA, pengajuan pengukuran, dan penambahan bukti dihapus; pembacaan nilai default yang tersebar di kode dialihkan ke konfigurasi, bukan dihapus.
- Tampilan daftar berkas terunggah masuk backlog tahap 3.
- **Di luar cakupan:** perubahan permission.

---

## Bagian 14 — Penutupan Tahunan & Koreksi Pasca-Penutupan

### US-14.01 · Penutupan Resmi Jadwal Tahunan

> **Q48 (13 Oktober 2026):** penutupan tidak memblokir unggah arsip dokumen resmi (US-10.05).

> **Q52 (14 Oktober 2026):** penutupan wajib alasan dan boleh dilakukan sebelum tanggal `penutupan`; tahun terkunci bila jadwal berstatus `ditutup` atau tanggal `penutupan` terlewati; kegiatan dikunci per tahun (Q52 §52.1–§52.3).

| Field | Detail |
|---|---|
| **ID** | `US-14.01` |
| **Prioritas** | 🔴 P0 · Tahap 2 (Q46 §46.3) |
| **Story Points** | 5 |
| **Modul** | Penutupan Tahunan & Koreksi Pasca-Penutupan |
| **Dependensi** | Jadwal aktif; otoritas `jadwal:tutup`. Tidak ada prasyarat tanggal: penutupan boleh sebelum tanggal `penutupan` (Q52 §52.2). |
| **Otorisasi** | `jadwal:tutup` (sensitif). |
| **Dampak Data** | `jadwal_tahunan`, `audit_log`. |

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** menutup siklus tahunan secara resmi,  
> **Sehingga** data tahun tersebut terkunci dari mutasi normal dan hanya dapat dikoreksi melalui jalur pembukaan resmi.

**Acceptance Criteria**

- [x] **AC-1:** Given penutupan dikonfirmasi, When commit, Then status menjadi `ditutup` dan `closed_at` terisi.
- [x] **AC-2:** Given jadwal ditutup, When mutasi normal RA, kegiatan, klaim, bukti, atau pengukuran dicoba, Then ditolak. *(Klaim belum memiliki jalur tulis, Q52 §52.3.)*
- [x] **AC-3:** Given data perlu dikoreksi pasca-penutupan, Then perubahan tidak dilakukan langsung; harus melalui `jadwal:buka_kembali` dengan sesi koreksi.
- [x] **AC-4:** Given penutupan adalah aksi sensitif, Then audit menyimpan aktor dan `dasar_izin`.
- [x] **AC-5:** Given jadwal `draft` atau sudah `ditutup`, When penutupan dicoba, Then ditolak sebagai validasi bisnis (422); tanpa izin atau terkena deny ditolak 403; keduanya teraudit.
- [ ] **AC-6:** Given jadwal `ditutup`, Then daftar Rencana Aksi dan Pengukuran tahun tersebut tetap terbaca (read-only) oleh pemilik izin baca. *(Rencana Aksi terpenuhi; daftar Pengukuran dilanjutkan di #110.)*

**Business Rules / Catatan**

- Istilah yang benar adalah terkunci selama status ditutup; bukan 'tidak dapat dimutasi selamanya', karena jalur koreksi resmi tetap ada.

### US-14.02 · Pembukaan Kembali Jadwal untuk Sesi Koreksi Perencanaan

| Field | Detail |
|---|---|
| **ID** | `US-14.02` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Penutupan Tahunan & Koreksi Pasca-Penutupan |
| **Dependensi** | Jadwal berstatus `ditutup`; lingkup, alasan, dan durasi koreksi ditetapkan. |
| **Otorisasi** | `jadwal:buka_kembali` (sensitif). |
| **Dampak Data** | `jadwal_tahunan.koreksi_*`, `lingkup_koreksi`, `audit_log`. |

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** membuka sesi koreksi pasca-penutupan dengan lingkup dan deadline eksplisit,  
> **Sehingga** koreksi historis dapat dilakukan terkendali tanpa menghapus tanggal penutupan asli.

**Acceptance Criteria**

- [ ] **AC-1:** Given alasan, `koreksi_mulai`, `koreksi_sampai`, dan `lingkup_koreksi` valid, When dibuka, Then status kembali aktif untuk sesi koreksi dan tanggal `penutupan` asli tidak ditimpa.
- [ ] **AC-2:** Given objek tidak termasuk `lingkup_koreksi`, When Perencanaan mencoba memutasi, Then ditolak.
- [ ] **AC-3:** Given waktu di luar sesi koreksi, When mutasi koreksi dilakukan, Then ditolak.
- [ ] **AC-4:** Given pembukaan tahun dilakukan, Then **hak PIC tidak otomatis terbuka**.
- [ ] **AC-5:** Given sesi selesai, When jadwal ditutup kembali, Then status menjadi `ditutup` dan seluruh peristiwa koreksi tetap teraudit.

### US-14.03 · Pembukaan Jendela PIC di Dalam Sesi Koreksi

> **Q41 (13 Oktober 2026):** jendela pengisian yang dibuka kembali di dalam sesi koreksi juga menentukan periode Rencana Aksi yang boleh diubah PIC; di luar periode tersebut koreksi RA dilakukan Perencanaan (Q41 §41.2 butir 6). AC-5 ditambahkan karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-14.03` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Penutupan Tahunan & Koreksi Pasca-Penutupan |
| **Dependensi** | Sesi koreksi tahun aktif; objek/unit berada dalam lingkup koreksi. |
| **Otorisasi** | `jadwal:update` untuk revisi jendela PIC resmi. |
| **Dampak Data** | `jadwal_tahunan`/`jadwal_periode`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** membuka jendela kerja PIC secara terpisah di dalam sesi koreksi,  
> **Sehingga** PIC hanya dapat berpartisipasi pada koreksi yang memang diizinkan.

**Acceptance Criteria**

- [ ] **AC-1:** Given sesi koreksi aktif, When Perencanaan membuka jendela PIC dengan alasan dan batas baru, Then deadline baru wajib berada di dalam sesi koreksi.
- [ ] **AC-2:** Given PIC bekerja dalam jendela baru, Then scope unit, PIC efektif, grant, deny, status record, dan gate lain tetap diperiksa.
- [ ] **AC-3:** Given tahun dibuka tetapi jendela PIC belum dibuka, When PIC mencoba mutasi, Then ditolak.
- [ ] **AC-4:** Given jendela koreksi berakhir, Then akses PIC kembali tertutup tanpa perlu mengubah histori penutupan asli.
- [ ] **AC-5:** Given jendela pengisian periode P dibuka kembali di dalam sesi koreksi, Then PIC juga dapat mengubah Rencana Aksi periode P, sedangkan RA periode lain hanya dapat dikoreksi Perencanaan. *(Q41 §41.2 butir 6)*

---

## Bagian 15 — Kesiapan Operasional, UAT, dan Penggunaan Pertama

### US-15.01 · Verifikasi Data Awal sebelum Penggunaan Pertama

> **Q46, Q47, Q50, Q51 (13 Oktober 2026):** data awal 2026 diisi Perencanaan dari lima dokumen resmi 2026 (Renstra 2025–2029, Perjanjian Kinerja 2026, Rencana Aksi 2026, serta Laporan Kinerja Triwulan I dan II 2026) dan disiapkan di akhir tahap 1 (Q46 §46.1 butir 6, §46.3). Kegiatan data awal boleh tanpa anggaran dan tercatat belum diisi (Q46 §46.1 butir 3). Rekomendasi TW I–II dicatat Perencanaan langsung berstatus `disetujui` dengan penanda persetujuan cadangan dan rujukan laporan sumbernya pada `rekomendasi_pimpinan.rujukan_sumber` (Q51 §51.1 butir 5). AC-6 sampai AC-8 ditambahkan karena story ini belum dikerjakan.

| Field | Detail |
|---|---|
| **ID** | `US-15.01` |
| **Prioritas** | 🟡 P1 · Tahap 1, di akhir tahap (Q46 §46.3) |
| **Story Points** | 5 |
| **Modul** | Kesiapan Operasional, UAT, dan Penggunaan Pertama |
| **Dependensi** | Tahun/periode penggunaan pertama telah ditetapkan PM/Perencanaan. |
| **Otorisasi** | Akses administratif/substantif sesuai data yang diverifikasi. |
| **Dampak Data** | Seed/config awal, master Renstra/indikator/unit/PIC, data historis wajib. |

> **Sebagai** Tim Perencanaan bersama PM,  
> **Saya ingin** memverifikasi daftar data awal sebelum jadwal pertama diaktifkan,  
> **Sehingga** sistem tidak go-live dengan master atau histori wajib yang belum jelas.

**Acceptance Criteria**

- [ ] **AC-1:** Given tahun penggunaan pertama ditetapkan, Then daftar periode, indikator, unit/PIC, PK/target, dan data historis wajib harus didokumentasikan.
- [ ] **AC-2:** Given data belum lengkap, When aktivasi pertama dicoba, Then gate aplikasi dan checklist operasional harus menunjukkan kekurangan.
- [ ] **AC-3:** Given data uji dan data konfigurasi produksi, Then keduanya dipisahkan dan seed produksi tidak membawa fixture testing.
- [ ] **AC-4:** Given backfill dibutuhkan, Then menggunakan jalur backfill resmi dan bukan query database manual tanpa jejak.
- [ ] **AC-5 (Q31):** Given data awal UAT disiapkan, Then minimal tersedia akun uji untuk lima role resmi; akun PIC tidak dianggap siap untuk seluruh skenario authorization sampai preset permission dan eligibility assignment dikonfirmasi.
- [ ] **AC-6:** Given data awal 2026 disiapkan, Then data memuat kode sasaran dan indikator mengikuti PK (S1–S4, IKU 1.1–IKU 4.1), sasaran S4 dan IKU 4.1 di bawah Renstra 2025–2029 tanpa target Renstra, target PK bernilai penuh, IKU 1.3 ditandai dinilai tahunan, target TW I–III kosong, kegiatan Rencana Aksi per triwulan (boleh tanpa anggaran, tercatat belum diisi), realisasi/narasi/rekomendasi TW I–II, dan TW III sebagai periode lampau. *(Q46 §46.1 butir 2, 3, dan 6, Q47 §47.1 butir 4 dan §47.2 butir 4, Q50 §50.2 dan §50.4–§50.5)*
- [ ] **AC-7:** Given data yang masih memakai asumsi kerja data awal 2026 pada Keputusan Penyelarasan, Then data tersebut hanya dipakai untuk data uji dan rancangan data awal, dan tidak dipakai di produksi sebelum dikonfirmasi Perencanaan. *(Q46 §46.1 butir 6)*
- [ ] **AC-8:** Given rekomendasi TW I–II 2026 dari laporan triwulan yang sudah ditandatangani Pimpinan, When dicatat Perencanaan sebagai data awal, Then rekomendasi langsung berstatus `disetujui` dengan penanda persetujuan cadangan dan rujukan laporan sumbernya. *(Q51 §51.1 butir 5)*

### US-15.02 · Kesiapan Infrastruktur dan Layanan Eksternal

| Field | Detail |
|---|---|
| **ID** | `US-15.02` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Kesiapan Operasional, UAT, dan Penggunaan Pertama |
| **Dependensi** | Owner infrastruktur/layanan telah ditetapkan. |
| **Otorisasi** | Proses operasional, bukan permission aplikasi tunggal. |
| **Dampak Data** | Konfigurasi Keycloak, domain/HTTPS, storage, backup/restore, queue/worker/scheduler, WA/Email. |

> **Sebagai** PM/pengelola infrastruktur,  
> **Saya ingin** memastikan seluruh layanan pendukung siap sebelum penerimaan produksi,  
> **Sehingga** fitur yang lulus kode tidak dianggap siap produksi tanpa dependency operasional.

**Acceptance Criteria**

- [ ] **AC-1:** Given rilis akan masuk UAT/produksi, Then realm/client Keycloak, domain HTTPS, database/storage, queue worker, scheduler, dan backup/pemulihan harus memiliki owner.
- [ ] **AC-2:** Given notifikasi eksternal akan diaktifkan, Then provider, template, sumber kontak, jam/zona waktu, dan credential tersedia.
- [ ] **AC-3:** Given backup tersedia, Then prosedur pemulihan harus dapat diuji/dibuktikan sesuai kebijakan operasional.
- [ ] **AC-4:** Given dependency eksternal belum tersedia, Then status rilis mencatatnya sebagai blocker/risiko dan tidak diasumsikan selesai.

### US-15.03 · Tahapan UAT, Penerimaan, dan Produksi yang Terpisah

| Field | Detail |
|---|---|
| **ID** | `US-15.03` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 3 |
| **Modul** | Kesiapan Operasional, UAT, dan Penggunaan Pertama |
| **Dependensi** | Build tersedia untuk pengujian. |
| **Otorisasi** | Governance proyek. |
| **Dampak Data** | Catatan hasil uji, keputusan penerimaan, daftar perbaikan. |

> **Sebagai** PM dan Tim Perencanaan,  
> **Saya ingin** memisahkan status siap diuji, dievaluasi, diterima, dan diproduksikan,  
> **Sehingga** target tanggal pengembangan tidak disalahartikan sebagai persetujuan go-live.

**Acceptance Criteria**

- [ ] **AC-1:** Given fitur selesai secara teknis, Then statusnya dapat menjadi siap diuji tanpa otomatis dianggap diterima.
- [ ] **AC-2:** Given UAT menemukan masalah, Then hasil masuk evaluasi/perbaikan sampai acceptance criteria terpenuhi.
- [ ] **AC-3:** Given tanggal target integrasi notifikasi sebelum 9 November 2026, Then tanggal tersebut tidak otomatis menjadi tanggal go-live.
- [ ] **AC-4:** Given tanggal penerimaan/produksi belum ditetapkan, Then dokumen tidak mengarang tanggal atau penerima UAT.
- [ ] **AC-5:** Given acceptance disetujui, Then pihak penerima, tanggal, build/versi, dan catatan keputusan dicatat.

---


## Q32 — Penyelarasan Final User Stories (24 September 2026)

Semua User Story memakai kontrak berikut:

1. role final: Superadmin, Admin, Perencanaan, Pimpinan, Pegawai;
2. PIC bukan role, melainkan aktor operasional dengan grant unit yang relevan;
3. PJ adalah assignment historis, tidak memberi permission;
4. Grant Unit hanya 7 permission scoped dan memakai `delegasi:update`;
5. Role & Permission UI read-only;
6. JIT SSO membuat user nonaktif tanpa role *(digantikan Q38: aktif tanpa role)*;
7. logout lokal default + logout SSO terpisah;
8. Jadwal pertama 2026; TW I–II periode lampau oleh Perencanaan, TW III–IV normal *(diperbarui Q46: TW I–III periode lampau, TW IV normal)*;
9. tidak ada mekanisme/flag backfill khusus;
10. IKU 3 = dua input SAKIP/ZI-WBK; IKU 8 = `n/t × 100%` dengan t total publikasi seluruh PTS.

### Dampak ke User Story

- US-01.01: onboarding tanpa role + dua jenis logout.
- US-01.03: Assign Peran hanya 5 role; perubahan role tidak mengubah grant/PJ.
- US-01.04: Grant Unit memakai `delegasi:update` dan 7 permission.
- US-01.07: menjadi **Peran & Izin read-only**; preset diubah via code/seeder/release.
- US-02.04/02.06/02.07: formula/data IKU mengikuti keputusan final.
- US-03.01/03.05: konsep backfill lama diganti periode lampau terhitung pada Jadwal 2026.
- US-04.01: PJ dapat menunjuk user aktif mana pun; warning jika tanpa hak isi.
- US-07.04/07.05: Perencanaan mengisi periode lampau; tidak ada flag backfill.
- US-15.01: initial setup 2026 mencakup RA existing berstatus disahkan sebelum TW III *(diperbarui Q46: sebelum pengajuan TW IV)*.

Acceptance Criteria yang bertentangan dengan Q32 dinyatakan superseded dan harus diperbarui pada issue implementasi sebelum coding/UAT.


## Matriks Ketertelusuran

Matriks memuat seluruh 83 User Story per 13 Oktober 2026, termasuk US-11.06 yang dibatalkan Q44 dan dipertahankan untuk traceability.

| User Story | Plan Pengembangan | PRD | Workflow | Entitas/Objek Utama |
|---|---|---|---|---|
| **US-01.01** | Modul 1: 1.2–1.3 | §6–7 | §1, §19 | `users` |
| **US-01.02** | Modul 1: 1.4, 1.18 | §7.7 | §21 | `unit` |
| **US-01.03** | Modul 1: 1.6–1.7, 1.13 | §7.1–7.5 | §21 | `roles`, `user_roles` |
| **US-01.04** | Modul 1: 1.8, 1.14 | §7.1–7.5 | §19, §21 | `user_permission_granted` |
| **US-01.05** | Modul 1: 1.9, 1.15 | §7.1–7.5 | §19, §21 | `user_permission_denials` |
| **US-01.06** | Modul 1: 1.16 | §7.4–7.5 | §19, §21 | resolver izin |
| **US-01.07** | Modul 1: 1.22–1.23 | §7.3–7.5, §25 | §19, §21–23 | `role_permissions` |
| **US-01.08** | Modul 1: 1.3 | §6–7, §34 | §21.1 | `users.status` |
| **US-02.01** | Modul 2: 2.16–2.19 | §9 | §3 | `regulasi`, `berkas` |
| **US-02.02** | Modul 2: 2.1–2.2, 2.18 | §10 | §2–3 | `renstra`, `berkas` |
| **US-02.03** | Modul 2: 2.3–2.4, 2.13 | §10.2–10.5 | §2, §14 | `renstra`, snapshot |
| **US-02.04** | Modul 2: 2.5–2.7 | §11 | §2 | `sasaran`, `indikator` |
| **US-02.05** | Modul 2: 2.8–2.9 | §11, §25 | §14 | `indikator`, snapshot |
| **US-02.06** | Modul 2: 2.14–2.15 | §17 | §6 | `indikator_komponen` |
| **US-02.07** | Modul 2: 2.10 | §11–12, §17 | §5–6 | `target_tahunan`, snapshot |
| **US-02.08** | Modul 2: 2.11, 2.20 | §10.6, §14.10 | §3, §5 | `renstra_pk`, `berkas` |
| **US-02.09** | Modul 2: 2.12 | §12.5–12.7 | §13–14 | PK + snapshot version |
| **US-02.10** | Modul 2: 2.21 | §8, §10.5–10.6, §11 | §2, §14 | `target_renstra`, `berkas` |
| **US-02.11** | Modul 2: 2.22 | §8, §11, §12.4, §14.5, §14.10 | §3.3, §5 | `target_tahunan`, `renstra_pk` |
| **US-02.12** | Modul 2: 2.23 | §12.5, §12.7, §14.5 | §3.3, §13–14 | `renstra_pk_versi`, `renstra_pk_versi_target` |
| **US-03.01** | Modul 3: 3.1–3.4 | §12.1–12.3 | §4 | `periode`, `jadwal_*` |
| **US-03.02** | Modul 3: 3.5–3.7 | §12.4–12.5 | §5 | `jadwal_snapshot*` |
| **US-03.03** | Modul 3: 3.8 | §12.5–12.7 | §13–14 | `jadwal_snapshot*` |
| **US-03.04** | Modul 3: 3.7–3.8 | §10.5, §12.5 | §14 | snapshot `periode_mulai_id` |
| **US-03.05** | Modul 3: 3.11; Modul 5: 5.17 | §12.6, §17.6 | §15 | historical backfill |
| **US-03.06** | Modul 3: 3.12 | §12.3, §12.6 | §13 | jadwal windows |
| **US-04.01** | Modul 4: 4.1–4.5 | §13 | §6, §23 | `penanggung_jawab` |
| **US-05.01** | Modul 11: 11.1–11.2, 11.5–11.6 | §14 | §7 | `rencana_aksi_target` |
| **US-05.02** | Modul 13: 13.3–13.5 | §18 | §10 | `berkas` |
| **US-05.03** | Modul 11: 11.1, 11.3 | §14.6–14.9, §18.11 | §7, §20 | `rencana_aksi_versi` |
| **US-05.04** | Modul 11: 11.3 | §7.6, §14.6 | §7, §20 | RA + versi |
| **US-05.05** | Modul 11: 11.3 | §7.6, §14.6 | §7, §20 | RA + versi |
| **US-05.06** | Modul 11: 11.7 | §14.6 | §7, §13 | RA + versi |
| **US-05.07** | Modul 11: 11.9 | §12.2–12.3, §14.1, §14.3, §14.7 | §4, §7 | `rencana_aksi_target`, `indikator.dinilai_tahunan` |
| **US-06.01** | Modul 12: 12.1–12.2 | §15.1–15.4 | §8 | `kegiatan` |
| **US-06.02** | Modul 12: 12.6–12.9 | §16 | §9 | `klaim_kegiatan` |
| **US-06.03** | Modul 12: 12.11; Modul 13 | §15.6, §18 | §8–10 | kegiatan + bukti |
| **US-06.04** | Modul 12: 12.3 | §15.2–15.5 | §8 | `kegiatan` |
| **US-06.05** | Modul 12: 12.4 | §15.4 | §8 | `kegiatan_asal_id` |
| **US-06.06** | Modul 12: 12.10 | §16.5 | §9, §13 | klaim + versi |
| **US-06.07** | Modul 13: 13.6 | §18.8 | §10 | `berkas` append-only |
| **US-06.08** | Modul 12: 12.12 | §15.1, §15.6, §18.2–18.3 | §8–10 | `kegiatan.anggaran`, `jenis_berkas`, `berkas` |
| **US-07.01** | Modul 5: 5.2–5.4, 5.13–5.15 | §17, §19–20 | §11 | pengukuran + komponen |
| **US-07.02** | Modul 13: 13.3–13.5 | §18 | §10–11 | `berkas` |
| **US-07.03** | Modul 5: 5.5, 5.16 | §18.11, §19.4 | §11–12, §20 | `pengukuran_versi` |
| **US-07.04** | Modul 5: 5.4–5.8 | §19.3 | §11–12 | pengukuran |
| **US-07.05** | Modul 3: 3.11; Modul 5: 5.17 | §12.6, §17.6, §19 | §15 | historical measurement |
| **US-07.06** | Modul 5: 5.13–5.16 | §17.4, §19.4 | §11 | status perhitungan |
| **US-08.01** | Modul 5: 5.6–5.8; Modul 6: 6.1 | §7.6, §19 | §12, §20 | pengukuran + versi |
| **US-08.02** | Modul 5: 5.9; Modul 6: 6.2 | §7.6, §19 | §12, §20 | pengukuran + versi |
| **US-08.03** | Modul 5: 5.10; Modul 6: 6.3–6.6 | §19.5, §21 | §13, §16 | versi + status capaian |
| **US-09.01** | Modul 6: 6.7 | §22 | §17 | `rekomendasi_pimpinan` |
| **US-09.02** | Modul 6: 6.4–6.6 | §21 | §16 | `status_capaian` |
| **US-10.01** | Modul 7: 7.1–7.8 | §23 | §1, §14 | dashboard queries |
| **US-10.02** | Modul 8: 8.1, 8.3 | §22.4, §24 | §17 | report matrix |
| **US-10.03** | Modul 8: 8.2, 8.4 | §24 | §17 | Excel export |
| **US-10.04** | Modul 8: 8.4; P.4 | §24, §32 | §17 | UAT artifact |
| **US-10.05** | Modul 8: 8.5 | §24 | alur keluaran dokumen resmi (Q48) | `berkas` (induk `jadwal_tahunan`/`jadwal_periode`) |
| **US-11.01** | Modul 13: 13.1 | §18.2–18.7 | §10 | `jenis_berkas` |
| **US-11.02** | Modul 13: 13.2, 13.5 | §18.4–18.9 | §10 | `berkas`, private disk |
| **US-11.03** | Modul 13: 13.2–13.5 | §18.4–18.6 | §10 | `berkas` |
| **US-11.04** | Modul 13: 13.6 | §18.8 | §10 | `berkas` |
| **US-11.05** | Modul 13: 13.4 | §18.11 | §10 | versi + persyaratan |
| **US-11.06** *(dibatalkan Q44)* | Modul 13: 13.7 | §18.5–18.7 | §10 | pengecualian file (histori) |
| **US-12.01** | Modul 14: 14.1 | §28.1 | §22.1 | alert server-side |
| **US-12.02** | Modul 14: 14.1 | §28.1 | §22.1 | in-app notifications |
| **US-12.03** | Modul 14: 14.2 | §28.2 | §22.2 | WA/Email queue |
| **US-12.04** | Modul 14: 14.2 | §28.2 | §22.2 | EWS |
| **US-12.05** | Modul 14: 14.2 | §28.2 | §22.2 | recap notification |
| **US-12.06** | Modul 14: 14.2 | §28.2 | §22.2 | return notification |
| **US-12.07** | Modul 14: 14.2 | §28.2 | §22.2 | delivery/idempotency |
| **US-13.01** | Modul 9: 9.1–9.6 | §26 | §18 | `pengaturan` |
| **US-13.02** | Modul 9: 9.7–9.9 *(dihapus Q44 melalui 9.10)* | §18.9, §26 | §18 | storage policy (dihapus Q44, US-13.06) |
| **US-13.03** | Modul 10: 10.1–10.6 | §25 | §19–20 | `audit_log` |
| **US-13.04** | Modul 14: 14.2; Modul 9 | §28.2 | §22.2 | notification config |
| **US-13.05** | Modul 10: 10.7 | §4, §7.5, §13, §25 | §21–22 | `audit_log`, `user_permission_granted`, `penanggung_jawab` |
| **US-13.06** | Modul 9: 9.10 | §18, §26 | §10, §18 | konfigurasi unggahan, `jenis_berkas` |
| **US-14.01** | Modul 3: 3.9 | §12.6 | §13 | `jadwal_tahunan` |
| **US-14.02** | Modul 3: 3.9 | §12.6 | §13 | correction session |
| **US-14.03** | Modul 3: 3.12 | §12.3, §12.6 | §13 | PIC windows |
| **US-15.01** | S.1, P.4 | Pembuka, §12.6, §32 | §15 | initial data |
| **US-15.02** | P.1–P.4 | §6, §28, §34 | §22 | operational dependencies |
| **US-15.03** | P.4 | Pembuka, §32 | — | UAT/acceptance records |

## Definition of Done Global

- [ ] Acceptance Criteria story yang dikerjakan memiliki automated test yang sesuai risiko: Pest/Feature untuk aturan server, dan test frontend bila logika presentasi kritis.
- [ ] Semua endpoint mutasi menegakkan resolver permission di server dan tidak hanya menyembunyikan tombol.
- [ ] Semua invariant Data Model (constraint unik, FK, versioning, provenance, scope, status) dipenuhi.
- [ ] Aksi sensitif merekam `audit_log.dasar_izin`; alasan wajib benar-benar divalidasi server.
- [ ] RA/Pengukuran yang disubmit membuat versi immutable dan mencegah pengesahan versi stale.
- [ ] Query dashboard/laporan resmi membaca snapshot/versi yang benar dan tidak mengandalkan live master untuk histori.
- [ ] Komponen React/Inertia mematuhi `design-system.md`: token, reusable components, typography, layout, form pattern, disabled/loading/error state, dan responsivitas.
- [ ] File bukti berada di private storage dan download selalu melewati otorisasi induk.
- [ ] Queue/scheduler bersifat idempotent; kegagalan provider eksternal tidak merollback transaksi domain.
- [ ] Secret/token tidak pernah masuk git, props React, flash message, audit log, atau log aplikasi biasa.
- [ ] Migration/seeder bersifat repeatable sesuai strategi proyek; fixture testing dipisahkan dari data/config produksi.
- [ ] Dokumentasi User Issue yang diturunkan dari story harus menyebut endpoint/service, data mutation, audit, test, dan dependency nyata—bukan template Backend/Frontend generik semata.
- [ ] Story dianggap selesai hanya jika implementasi, test, dan dokumentasi relevan telah sinkron dengan baseline terbaru.

- [ ] Story yang menyentuh role/access telah diuji terhadap **lima role resmi** tanpa hardcode authorization di React.
- [ ] Story PIC tidak menganggap PIC operasional (bukan role) otomatis memiliki permission scoped/global yang belum dikonfirmasi.
- [ ] Story yang memakai `penanggung_jawab` tidak menambahkan constraint eligibility berdasarkan role sebelum keputusan Q31 tersedia.
- [ ] Nama tabel akses konsisten dengan Data Model resmi: `user_permission_granted` dan `user_permission_denials`.

## Batas Fase Lanjutan

- Approval aktif oleh Pimpinan (`pengukuran:setujui`) belum menjadi langkah workflow MVP. Persetujuan Rekomendasi Pimpinan oleh Pimpinan (Q51, US-09.01) adalah alur terpisah.
- Anggaran kegiatan belum masuk UI/API MVP. *(Digantikan Q42: anggaran per kegiatan diinput, ditampilkan, dan dijumlahkan melalui US-06.08; realisasi belanja, pagu, dan rekonsiliasi anggaran tetap tidak dikelola, Q42 §42.2 butir 3.)*
- Formula bertingkat generik di luar mesin satu tingkat yang telah disepakati ditunda.
- Multi-role per user ditunda; MVP mempertahankan maksimum satu role utama per pengguna dari lima role final, sementara user JIT dapat sementara belum memiliki role.
- Integrasi sumber data otomatis untuk `status_capaian.sumber = data_sumber` ditunda.
- Fitur yang dependency operasionalnya belum ditetapkan (provider, credential, owner, waktu kirim, tanggal go-live) tidak boleh diasumsikan siap hanya karena kode tersedia.

---

**Catatan baseline Q32:** keputusan role/PIC, eligibility PJ, onboarding, logout, penggunaan 2026, dan formula IKU sudah final. Dependency terbuka yang tersisa terutama tanggal penerimaan/go-live, provider/owner operasional, notifikasi, infrastruktur, data penetapan awal 2026, dan contoh keluaran Excel UAT.
