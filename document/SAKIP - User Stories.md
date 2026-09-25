# USER STORIES — SAKIP LLDIKTI WILAYAH XVI

> **Klarifikasi final LLDIKTI Wilayah XVI — 24 September 2026**  
> Bagian ini adalah kontrak terbaru dan **menggantikan keputusan Q31 atau teks lama yang bertentangan**. Role bawaan SAKIP berjumlah **lima**: `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`. **PIC bukan role sistem**; PIC adalah konteks operasional yang dibentuk oleh penugasan dan grant unit. Detail keputusan dicatat sebagai **Q32** pada dokumen Keputusan Penyelarasan.

**Status dokumen:** Revisi Q32 — 24 September 2026. Dokumen ini telah diselaraskan terhadap jawaban resmi LLDIKTI: 5 role, PIC bukan role, Grant Unit 7 permission, role-permission read-only, onboarding tanpa role, logout terpisah, Jadwal 2026, serta formula final IKU 3/8.

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
- **Onboarding SSO** membuat user nonaktif tanpa role; Admin mengaktifkan dan assign role.
- **Logout lokal default**, logout SSO terpisah.
- **Initial year 2026.** TW I–II periode lampau oleh Perencanaan; TW III–IV normal; tanpa `is_backfill`.
- **Formula final:** IKU 3 = `(sakip + zi_wbk)/2`; IKU 8 = `n/t × 100%` dengan `t` total publikasi seluruh PTS.
- **Audit append-only** dan F1/F2 tetap business guard terpisah dari permission.

## Bagian 1 — Fondasi Autentikasi, Unit Organisasi, dan Model Akses RBAC

### US-01.01 · Login Terpusat Menggunakan Single Sign-On (SSO) Keycloak

> **Q32:** login pertama membuat user `nonaktif` tanpa role. Admin mengaktifkan dan assign role. Logout lokal menjadi default; logout SSO adalah aksi terpisah.

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

**Acceptance Criteria**

- [ ] **AC-1:** Given indikator dan tahun valid, When baseline/target disimpan, Then satu baris target tahunan tersedia untuk kombinasi tersebut.
- [ ] **AC-2:** Given kombinasi indikator-tahun sudah ada, When nilai diperbarui, Then data diperbarui melalui `target:update` dan tidak membuat duplikasi.
- [ ] **AC-3:** Given target sudah dibekukan ke snapshot yang dirujuk histori, When master target dikoreksi, Then laporan lama tidak ikut berubah.
- [ ] **AC-4:** Given koreksi salah input terhadap sumber PK resmi dibutuhkan, Then koreksi snapshot dilakukan melalui mekanisme versi dengan alasan dan rujukan bukti.

**Business Rules / Catatan**

- Katalog permission baseline hanya memakai `target:update`; tidak ada `target:create` terpisah pada baseline PRD.

### US-02.08 · Pencatatan Perjanjian Kinerja (PK) & Lampiran Legal

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
- [ ] **AC-3:** Given snapshot belum dirujuk, When koreksi diperbolehkan, Then snapshot dapat dikoreksi sesuai aturan.
- [ ] **AC-4:** Given snapshot sudah dirujuk versi RA/Pengukuran, When koreksi diperlukan, Then sistem membuat versi snapshot pengganti dan mempertahankan snapshot lama.
- [ ] **AC-5:** Given hasil resmi lama sudah disahkan, Then hasil lama tidak berubah sampai versi koreksi diajukan dan disahkan ulang.

---

## Bagian 3 — Periode, Jadwal, Snapshot, Efektivitas, dan Backfill

### US-03.01 · Penyusunan Master Periode, Jadwal Tahunan, dan Jendela Periode

> **Q32:** initial schedule = 2026. TW I–II periode lampau oleh Perencanaan, TW III–IV normal. Tidak ada `is_backfill`.

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

### US-03.02 · Aktivasi Jadwal Tahunan melalui Empat Gerbang & Pembentukan Snapshot

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

**Acceptance Criteria**

- [ ] **AC-1:** Given aktivasi diminta, When server mengevaluasi, Then empat gerbang diperiksa: PK tersedia; target tahunan semua indikator aktif tersedia; tahun berada dalam rentang Renstra; lampiran PK tersedia atau pengecualian file yang sah tercatat.
- [ ] **AC-2:** Given salah satu gerbang wajib gagal, When aktivasi diproses, Then seluruh transaksi ditolak atomik dan pesan menjelaskan gerbang yang gagal.
- [ ] **AC-3:** Given semua gerbang lolos, When commit berhasil, Then jadwal menjadi `aktif` dan `activated_at` terisi.
- [ ] **AC-4:** Given indikator aktif belum memiliki snapshot pada jadwal, When aktivasi sukses, Then `jadwal_snapshot` dibuat idempoten beserta `jadwal_snapshot_komponen`.
- [ ] **AC-5:** Given snapshot pasangan indikator-jadwal sudah ada, When proses diulang, Then baris lama tidak ditimpa/duplikasi.
- [ ] **AC-6:** Given permission sensitif digunakan, Then audit menyimpan `dasar_izin` dan aktor sebenarnya.

### US-03.03 · Koreksi Snapshot Terkendali dan Versioning Konteks

| Field | Detail |
|---|---|
| **ID** | `US-03.03` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 8 |
| **Modul** | Periode, Jadwal, Snapshot, Efektivitas, dan Backfill |
| **Dependensi** | Snapshot tersedia; koreksi memiliki alasan dan rujukan resmi. |
| **Otorisasi** | `target:update` / permission substantif terkait serta guard jadwal. |
| **Dampak Data** | `jadwal_snapshot`, `jadwal_snapshot_komponen`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengoreksi konteks snapshot yang salah tanpa mengubah versi yang telah dipakai histori,  
> **Sehingga** target, formula, dan identitas laporan historis tetap dapat dipertanggungjawabkan.

**Acceptance Criteria**

- [ ] **AC-1:** Given snapshot belum pernah dirujuk RA/Pengukuran/versi pengajuan, When koreksi sah dilakukan pada jadwal aktif, Then snapshot dapat dikoreksi dan audit menyimpan before/after.
- [ ] **AC-2:** Given snapshot sudah dirujuk, When koreksi diperlukan, Then baris versi baru dibuat dengan `nomor_versi + 1`, `menggantikan_id`, `alasan_koreksi`, dan `rujukan_koreksi`.
- [ ] **AC-3:** Given versi lama telah dirujuk laporan/pengajuan, Then versi lama dan komponen anaknya tetap immutable.
- [ ] **AC-4:** Given pengukuran hendak memakai snapshot koreksi, Then pengukuran harus diajukan dan disahkan ulang; tidak ada propagasi diam-diam.
- [ ] **AC-5:** Given versi snapshot tidak cocok indikator/jadwal, When koreksi dibuat, Then ditolak.

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
> **Sehingga** TW I–II dapat dicatat secara benar tanpa memalsukan tanggal atau membuat flag historis baru.

**Acceptance Criteria**

- [ ] TW I–II teridentifikasi sebagai periode lampau dari jadwal, bukan flag `is_backfill`.
- [ ] Perencanaan dapat mengisi periode lampau; user scoped mengikuti jendela dan tidak memperoleh bypass otomatis.
- [ ] Gerbang RA/komponen/bukti normal dikecualikan untuk jalur periode lampau, sedangkan PK 2026 tetap wajib.
- [ ] 2025 tidak dibuat sebagai baris Jadwal/Pengukuran; hanya baseline.
- [ ] Audit tetap mencatat aktor dan perubahan.

### US-03.06 · Revisi Resmi Jendela PIC

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

---

## Bagian 4 — Penugasan Penanggung Jawab (PIC)

### US-04.01 · Penetapan, Pergantian, dan Resolusi PIC Efektif

> **Q32:** PJ dapat menunjuk user aktif mana pun. Assignment tidak memberi permission; calon PJ tanpa grant hanya diberi warning dan masuk daftar “PJ aktif tanpa hak isi”.

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

- [ ] **AC-1:** Given indikator, pengguna, tanggal mulai, dan alasan valid, When penugasan disimpan, Then baris baru `penanggung_jawab` dibuat tanpa update in-place histori lama.
- [ ] **AC-2:** Given beberapa penugasan historis ada, When PIC efektif dicari pada tanggal T, Then sistem memilih baris terbaru dengan `tanggal_mulai_berlaku <= T`.
- [ ] **AC-3:** Given PIC berganti, When request RA/Pengukuran baru dilakukan, Then guard memakai PIC efektif terkini dan grant unit yang sah.
- [ ] **AC-4:** Given versi RA/Pengukuran lama sudah diajukan, When PIC berganti, Then `diajukan_by`/provenance versi lama tidak berubah.
- [ ] **AC-5:** Given alasan pergantian kosong, When disimpan, Then ditolak dan aksi sensitif diaudit.
- [ ] **AC-6 (Q31-OPEN):** Given belum ada keputusan final eligibility role terhadap `penanggung_jawab`, When implementasi assignment dibangun, Then tidak boleh ada constraint hardcoded yang hanya menerima PIC operasional (bukan role); validasi tersebut baru menjadi final setelah keputusan bisnis diterbitkan.


**Business Rules / Catatan Q31**

- `penanggung_jawab` tetap menjadi sumber histori assignment indikator.
- Role `pic` dan assignment `penanggung_jawab` adalah dua data yang berbeda.
- Belum boleh diasumsikan bahwa `penanggung_jawab.user_id` wajib PIC operasional (bukan role).
- Bila keputusan eligibility nanti ditetapkan, story ini wajib direvisi bersamaan dengan Data Model, Workflow, Plan, User Issues, dan test.

---


> **Catatan istilah untuk Bagian 5–14:** kecuali sebuah story secara eksplisit membahas `user_roles`, istilah **PIC** berarti **PIC operasional/Penanggung Jawab efektif** pada konteks indikator/unit tersebut. Role `pic` yang baru dikonfirmasi Q31 tidak otomatis menjadi allow terhadap semua aksi; resolver permission, grant/deny, assignment, jendela, status, F1/F2, dan gate tetap berlaku.

## Bagian 5 — Penyusunan, Pengajuan, Verifikasi, dan Pengesahan Rencana Aksi

### US-05.01 · Penyusunan Target Rencana Aksi per Periode

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

**Acceptance Criteria**

- [ ] **AC-1:** Given indikator manual, When target periode diisi, Then satu target langsung per periode disimpan dengan `komponen_id = NULL`.
- [ ] **AC-2:** Given indikator nonmanual, When target diisi, Then target disimpan per komponen snapshot dan nilai turunan dihitung server.
- [ ] **AC-3:** Given target periode lebih rendah dari periode sebelumnya, When disimpan, Then sistem memberi warning kumulatif namun tidak memblokir.
- [ ] **AC-4:** Given periode sebelum efektivitas indikator, When target diminta, Then periode tersebut dikecualikan sebagai Tidak berlaku.
- [ ] **AC-5:** Given PIC berada di luar jendela RA, When mutasi dicoba, Then ditolak; Perencanaan dapat bekerja sampai penutupan/jendela koreksi yang sah.
- [ ] **AC-6:** Given target akhir berbeda dari target PK snapshot, Then submit tetap dapat dilakukan hanya setelah alasan deviasi diisi.

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

- [ ] **AC-1:** Given persyaratan tahap `rencana_aksi`, When bukti mode valid disimpan, Then `berkas` terhubung ke RA dan jenis persyaratan yang tepat.
- [ ] **AC-2:** Given mode tidak diizinkan, When bukti dikirim, Then server menolak.
- [ ] **AC-3:** Given `semua_mode_wajib = true`, When sebagian mode belum terpenuhi, Then persyaratan tetap belum lengkap.
- [ ] **AC-4:** Given deny/capability induk tidak mengizinkan upload, When request dipanggil langsung, Then 403.
- [ ] **AC-5:** Given bukti sudah dibekukan dalam versi pengajuan, Then perubahan setelahnya tidak mengubah snapshot versi tersebut.

### US-05.03 · Pengajuan Rencana Aksi & Pembekuan Versi

| Field | Detail |
|---|---|
| **ID** | `US-05.03` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Penyusunan, Pengajuan, Verifikasi, dan Pengesahan Rencana Aksi |
| **Dependensi** | RA draft/dikembalikan; target dan bukti memenuhi aturan; jendela aktor sah. |
| **Otorisasi** | `rencana_aksi:ajukan` (unit-scoped bagi PIC; global bagi Perencanaan). |
| **Dampak Data** | `rencana_aksi`, `rencana_aksi_versi`, `audit_log`, notifikasi in-app. |

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** mengajukan RA sebagai versi substansi yang immutable,  
> **Sehingga** review dan laporan selalu menilai data persis seperti saat diajukan.

**Acceptance Criteria**

- [ ] **AC-1:** Given target wajib/periode berlaku belum lengkap, When submit, Then pengajuan ditolak.
- [ ] **AC-2:** Given bukti wajib belum lengkap sesuai versi persyaratan saat submit, When submit, Then ditolak.
- [ ] **AC-3:** Given deviasi target akhir terhadap PK belum memiliki alasan, When submit, Then ditolak.
- [ ] **AC-4:** Given seluruh gerbang lolos, When submit commit, Then status header menjadi `diajukan` dan **baris baru `rencana_aksi_versi`** dibuat.
- [ ] **AC-5:** Given versi dibuat, Then server membekukan `diajukan_by`, `diajukan_at`, `jalur_pengajuan` (`pic`/`perencanaan`), `dasar_izin_pengajuan`, target, persyaratan, bukti, klaim/narasi yang relevan, dan snapshot konteks.
- [ ] **AC-6:** Given draft dibuat oleh A tetapi diajukan B, Then `diajukan_by = B`; `created_by` tidak dipakai sebagai identitas pengaju F1/F2.
- [ ] **AC-7:** Given submit berhasil, Then notifikasi in-app untuk antrean Perencanaan dibuat setelah transaksi berhasil.

### US-05.04 · Verifikasi & Pengembalian Rencana Aksi dengan F1

| Field | Detail |
|---|---|
| **ID** | `US-05.04` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Penyusunan, Pengajuan, Verifikasi, dan Pengesahan Rencana Aksi |
| **Dependensi** | RA berstatus `diajukan` dan versi terbaru tersedia. |
| **Otorisasi** | `rencana_aksi:verifikasi`, `rencana_aksi:kembalikan`. |
| **Dampak Data** | `rencana_aksi`, versi terkait, `audit_log`, notifikasi. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** memverifikasi versi RA atau mengembalikannya dengan alasan,  
> **Sehingga** substansi RA diperiksa tanpa membuka celah self-review jalur PIC.

**Acceptance Criteria**

- [ ] **AC-1:** Given versi diajukan melalui `jalur_pengajuan = pic`, When aktor sama dengan `rencana_aksi_versi.diajukan_by` mencoba **memverifikasi**, Then F1 menolak walaupun aktor kemudian memperoleh role/grant lain.
- [ ] **AC-2:** Given aktor berbeda dan memiliki permission efektif, When verifikasi dilakukan, Then status menjadi `diverifikasi` tanpa mengubah payload versi.
- [ ] **AC-3:** Given perbaikan dibutuhkan, When dikembalikan dengan alasan, Then status menjadi `dikembalikan`, audit mencatat alasan, dan PIC menerima notifikasi.
- [ ] **AC-4:** Given pengembalian tanpa alasan, When submit, Then ditolak.
- [ ] **AC-5:** Given data substansi perlu diubah saat review, Then data harus dikembalikan lalu diajukan sebagai versi baru; snapshot versi lama tidak ditambal.

### US-05.05 · Pengesahan Rencana Aksi & Pemisahan Tugas F1/F2

| Field | Detail |
|---|---|
| **ID** | `US-05.05` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Penyusunan, Pengajuan, Verifikasi, dan Pengesahan Rencana Aksi |
| **Dependensi** | RA berstatus `diverifikasi`; versi yang direviu adalah versi terbaru dan tidak stale. |
| **Otorisasi** | `rencana_aksi:sahkan` (sensitif). |
| **Dampak Data** | `rencana_aksi`, `rencana_aksi_versi`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengesahkan versi RA yang telah diverifikasi,  
> **Sehingga** RA menjadi dokumen operasional resmi dengan provenance yang dapat diaudit.

**Acceptance Criteria**

- [ ] **AC-1:** Given versi diajukan melalui jalur PIC, When aktor sama dengan `diajukan_by` mencoba mengesahkan, Then **F1 menolak**.
- [ ] **AC-2:** Given versi diajukan oleh PIC dan aktor Perencanaan lain berizin, When sahkan, Then header menjadi `disahkan` dan metadata pengesahan pada versi terisi atomik.
- [ ] **AC-3:** Given versi diajukan melalui `jalur_pengajuan = perencanaan`, When pengaju yang sama memverifikasi/mengesahkan dan masih memiliki permission efektif, Then **F2 mengizinkan** serta mencatat `self_approval`.
- [ ] **AC-4:** Given terdapat deny atau permission reviewer hilang, When F2 dicoba, Then tetap ditolak; F2 bukan bypass resolver.
- [ ] **AC-5:** Given versi yang hendak disahkan bukan versi terbaru yang sedang direviu, When aksi dilakukan, Then ditolak untuk mencegah pengesahan payload stale.
- [ ] **AC-6:** Given RA disahkan, Then bukti yang dirujuk versi resmi tidak boleh dihapus.

### US-05.06 · Buka-Kembali Rencana Aksi yang Telah Disahkan

| Field | Detail |
|---|---|
| **ID** | `US-05.06` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Penyusunan, Pengajuan, Verifikasi, dan Pengesahan Rencana Aksi |
| **Dependensi** | RA disahkan; sebelum penutupan atau di dalam sesi koreksi yang sah. |
| **Otorisasi** | `rencana_aksi:buka_kembali` (sensitif). |
| **Dampak Data** | `rencana_aksi`, versi historis, `audit_log`. |

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** membuka RA disahkan kembali ke jalur revisi dengan alasan wajib,  
> **Sehingga** koreksi resmi dapat dilakukan tanpa menghapus versi yang sudah pernah disahkan.

**Acceptance Criteria**

- [ ] **AC-1:** Given alasan valid dan waktu koreksi sah, When buka kembali dilakukan, Then header berpindah ke `dikembalikan`.
- [ ] **AC-2:** Given versi lama telah disahkan, When RA dibuka kembali, Then versi lama tetap immutable dan tetap dapat dibaca sebagai histori.
- [ ] **AC-3:** Given PIC perlu memperbaiki, Then PIC hanya dapat bekerja bila jendela PIC resmi masih/baru dibuka dan grant + PIC efektif valid.
- [ ] **AC-4:** Given jadwal ditutup tanpa sesi koreksi, When buka kembali RA langsung dicoba, Then ditolak.
- [ ] **AC-5:** Given RA diajukan ulang setelah perbaikan, Then baris `rencana_aksi_versi` baru dibuat.

---

## Bagian 6 — Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan

### US-06.01 · Pencatatan Rencana Kegiatan Unit

| Field | Detail |
|---|---|
| **ID** | `US-06.01` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan |
| **Dependensi** | Unit aktif dan periode valid. |
| **Otorisasi** | `kegiatan:create`, `kegiatan:update` (unit-scoped bila melalui grant). |
| **Dampak Data** | `kegiatan`, `audit_log`. |

> **Sebagai** PIC/petugas unit berizin atau Tim Perencanaan,  
> **Saya ingin** mencatat kegiatan yang mendukung pencapaian kinerja,  
> **Sehingga** aktivitas operasional dapat ditelusuri per unit dan periode.

**Acceptance Criteria**

- [ ] **AC-1:** Given nama, tujuan, sasaran peserta, lokasi, dan tanggal valid, When disimpan, Then kegiatan terbentuk dengan status `rencana`.
- [ ] **AC-2:** Given MVP, When form kegiatan ditampilkan, Then **field anggaran tidak ditampilkan, tidak diterima sebagai input bisnis, dan tidak divalidasi**; kolom database boleh tetap nullable untuk fase lanjutan.
- [ ] **AC-3:** Given pengguna unit memiliki grant `kegiatan:create/update`, When bekerja pada unit yang sama, Then aksi diizinkan tanpa syarat PIC indikator tertentu.
- [ ] **AC-4:** Given grant hanya unit A, When kegiatan unit B dimutasi, Then ditolak.

### US-06.02 · Klaim Keterkaitan Kegiatan terhadap Rencana Aksi/Komponen

| Field | Detail |
|---|---|
| **ID** | `US-06.02` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan |
| **Dependensi** | Kegiatan dan RA tersedia pada konteks tahun/unit yang konsisten. |
| **Otorisasi** | `kegiatan:update`/capability sumber dan kewenangan RA sesuai alur. |
| **Dampak Data** | `klaim_kegiatan`, `audit_log`. |

> **Sebagai** PIC/petugas unit berizin atau Tim Perencanaan,  
> **Saya ingin** menghubungkan kegiatan ke RA dan opsional ke komponen indikator,  
> **Sehingga** laporan dapat menjelaskan kontribusi kegiatan tanpa mengubah angka capaian secara otomatis.

**Acceptance Criteria**

- [ ] **AC-1:** Given kegiatan, RA, komponen opsional, arah dampak, dan catatan valid, When disimpan, Then klaim terbentuk.
- [ ] **AC-2:** Given unit kegiatan berbeda dengan unit RA, When klaim dibuat, Then ditolak.
- [ ] **AC-3:** Given klaim identik sudah ada, When dibuat ulang, Then duplikasi ditolak.
- [ ] **AC-4:** Given klaim berhasil, Then nilai komponen/pengukuran **tidak** berubah otomatis.
- [ ] **AC-5:** Given kegiatan batal/tidak terlaksana, Then klaim historis tetap dapat dipertahankan untuk menjelaskan kendala.

### US-06.03 · Pelaksanaan Kegiatan & Gerbang Bukti Gabungan

| Field | Detail |
|---|---|
| **ID** | `US-06.03` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 8 |
| **Modul** | Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan |
| **Dependensi** | Kegiatan berstatus `rencana`; persyaratan global dan indikator klaim dapat dihitung. |
| **Otorisasi** | `kegiatan:update` + capability bukti induk. |
| **Dampak Data** | `kegiatan`, `klaim_kegiatan`, `jenis_berkas`, `berkas`, `audit_log`. |

> **Sebagai** PIC/petugas unit berizin,  
> **Saya ingin** menyelesaikan kegiatan setelah seluruh bukti wajib yang relevan terpenuhi,  
> **Sehingga** status terlaksana hanya diberikan pada kegiatan yang dapat dipertanggungjawabkan.

**Acceptance Criteria**

- [ ] **AC-1:** Given kegiatan hendak menjadi `terlaksana`, When gerbang dievaluasi, Then persyaratan bukti = **union persyaratan global kegiatan + persyaratan indikator yang diklaim**.
- [ ] **AC-2:** Given salah satu bukti wajib pada union belum terpenuhi, When status terlaksana disubmit, Then ditolak.
- [ ] **AC-3:** Given tanggal realisasi, peserta riil, uraian pelaksanaan, kendala, strategi tindak lanjut, dan bukti lengkap, When commit, Then status menjadi `terlaksana`.
- [ ] **AC-4:** Given klaim indikator baru ditambahkan setelah kegiatan sudah terlaksana, When klaim menambah persyaratan bukti, Then sistem kembali memeriksa bukti tambahan dan menandai kebutuhan koreksi bila belum lengkap.
- [ ] **AC-5:** Given kegiatan telah terlaksana, Then bukti yang menjadi dasar hasil tidak dapat dihapus; koreksi memakai pola append-only.

### US-06.04 · Kegiatan Tidak Terlaksana, Ditunda, atau Dibatalkan

| Field | Detail |
|---|---|
| **ID** | `US-06.04` |
| **Prioritas** | 🟡 P1 |
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

### US-06.05 · Roll-Over Kegiatan ke Periode Berikutnya

| Field | Detail |
|---|---|
| **ID** | `US-06.05` |
| **Prioritas** | 🟡 P1 |
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

---

## Bagian 7 — Pengisian Pengukuran Kinerja

### US-07.01 · Pengisian Nilai Realisasi Komponen/Manual

| Field | Detail |
|---|---|
| **ID** | `US-07.01` |
| **Prioritas** | 🔴 P0 |
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

### US-07.02 · Pemenuhan Bukti Dukung Pengukuran

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
- [ ] **AC-3:** Given file upload dimatikan dan persyaratan mengandung kewajiban file, Then hanya kewajiban **mode file** yang dapat dikecualikan sesuai kebijakan; mode tautan/teks lain tetap wajib bila dipersyaratkan.
- [ ] **AC-4:** Given bukti telah masuk versi submit, Then perubahan live tidak mengubah versi lama.

### US-07.03 · Pengajuan Pengukuran & Pembekuan Versi

| Field | Detail |
|---|---|
| **ID** | `US-07.03` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Pengisian Pengukuran Kinerja |
| **Dependensi** | Pengukuran draft/dikembalikan; jendela aktor sah; RA resmi tersedia pada jalur normal. |
| **Otorisasi** | `pengukuran:update` untuk submit sesuai scope. |
| **Dampak Data** | `pengukuran`, `pengukuran_versi`, `audit_log`, notifikasi. |

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** mengajukan pengukuran sebagai versi immutable untuk direviu,  
> **Sehingga** angka, target pembanding, bukti, dan provenance tidak berubah selama proses review.

**Acceptance Criteria**

- [ ] **AC-1:** Given jalur normal, When submit, Then RA indikator-tahun harus memiliki versi yang telah disahkan.
- [ ] **AC-2:** Given nonmanual dan input wajib belum lengkap, When submit, Then ditolak; pengecualian `tidak_dapat_dihitung` hanya berlaku untuk penyebut efektif nol dengan komponen lengkap dan alasan.
- [ ] **AC-3:** Given bukti wajib menurut persyaratan saat submit belum terpenuhi, When submit, Then ditolak.
- [ ] **AC-4:** Given nilai memburuk menurut arah dibanding versi disahkan terakhir atau indikator `wajib_catatan`, When catatan kosong, Then submit ditolak.
- [ ] **AC-5:** Given seluruh gerbang lolos, Then header menjadi `diajukan` dan **`pengukuran_versi` baru** dibuat dengan `diajukan_by`, `diajukan_at`, `jalur_pengajuan`, `dasar_izin_pengajuan`, nilai/komponen, status perhitungan, snapshot target PK/RA, bukti, klaim/narasi, dan persyaratan.
- [ ] **AC-6:** Given A membuat draft dan B melakukan submit, Then `diajukan_by = B`; F1/F2 tidak menggunakan `created_by`.
- [ ] **AC-7:** Given submit berhasil, Then notifikasi antrean Perencanaan dibuat setelah commit.

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

| Field | Detail |
|---|---|
| **ID** | `US-07.05` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Pengisian Pengukuran Kinerja |
| **Dependensi** | Periode lampau 2026 terdeteksi dari Jadwal. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mencatat nilai TW I–II 2026 yang periode pengisiannya telah lewat,  
> **Sehingga** data awal 2026 tersedia tanpa membangun mode backfill generik.

**Acceptance Criteria**

- [ ] Pengisian hanya memakai jalur Perencanaan untuk periode lampau.
- [ ] Tidak ada `is_backfill` atau tipe pengukuran khusus.
- [ ] Nilai tetap terhubung ke Jadwal/Snapshot yang sah dan teraudit.
- [ ] Data TW II IKU 3 tidak menggunakan 66,395 atau 87,08 sebagai realisasi.

### US-07.06 · Penanganan Penyebut Nol — Tidak Dapat Dihitung

| Field | Detail |
|---|---|
| **ID** | `US-07.06` |
| **Prioritas** | 🟡 P1 |
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

---

## Bagian 8 — Reviu, Verifikasi, Pengesahan, dan Koreksi Pengukuran

### US-08.01 · Verifikasi & Pengembalian Pengukuran dengan F1

| Field | Detail |
|---|---|
| **ID** | `US-08.01` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Reviu, Verifikasi, Pengesahan, dan Koreksi Pengukuran |
| **Dependensi** | Pengukuran berstatus `diajukan`; versi terbaru tersedia. |
| **Otorisasi** | `pengukuran:verifikasi`, `pengukuran:kembalikan`. |
| **Dampak Data** | `pengukuran`, `pengukuran_versi`, `audit_log`, notifikasi. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** memeriksa nilai dan bukti lalu memverifikasi atau mengembalikan,  
> **Sehingga** angka resmi melewati review independen pada jalur PIC.

**Acceptance Criteria**

- [ ] **AC-1:** Given versi diajukan melalui jalur PIC, When aktor sama dengan `pengukuran_versi.diajukan_by` mencoba **memverifikasi**, Then F1 menolak.
- [ ] **AC-2:** Given reviewer berbeda dan berizin, When verifikasi dilakukan, Then status menjadi `diverifikasi` tanpa mengubah snapshot versi.
- [ ] **AC-3:** Given kesalahan ditemukan pada status `diajukan` atau `diverifikasi`, When dikembalikan dengan alasan, Then status menjadi `dikembalikan` dan notifikasi dikirim setelah commit.
- [ ] **AC-4:** Given pengembalian tanpa alasan, Then ditolak.
- [ ] **AC-5:** Given tanggal melewati `reviu_selesai` tetapi belum penutupan, When Perencanaan mereviu, Then aksi tetap dapat dilakukan dan diberi penanda reviu terlambat.

### US-08.02 · Pengesahan Pengukuran & F1/F2

| Field | Detail |
|---|---|
| **ID** | `US-08.02` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Reviu, Verifikasi, Pengesahan, dan Koreksi Pengukuran |
| **Dependensi** | Pengukuran `diverifikasi`; versi yang sama masih menjadi versi aktif untuk review. |
| **Otorisasi** | `pengukuran:sahkan` (sensitif). |
| **Dampak Data** | `pengukuran`, `pengukuran_versi`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengesahkan versi pengukuran,  
> **Sehingga** nilai realisasi menjadi angka resmi organisasi dengan provenance yang tidak dapat ditulis ulang.

**Acceptance Criteria**

- [ ] **AC-1:** Given jalur pengajuan PIC, When aktor sama dengan `diajukan_by` mencoba sahkan, Then F1 menolak.
- [ ] **AC-2:** Given aktor lain berizin, When sahkan, Then status menjadi `disahkan` dan `disahkan_by/disahkan_at` versi terisi atomik.
- [ ] **AC-3:** Given jalur pengajuan Perencanaan, When pengaju yang sama mereviu/mengesahkan dan izin efektif masih ada, Then F2 mengizinkan dan mencatat `self_approval`.
- [ ] **AC-4:** Given deny/permission tidak memenuhi, Then F2 tidak dapat melewati resolver.
- [ ] **AC-5:** Given versi stale, When sahkan, Then ditolak.
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

### US-09.01 · Pencatatan Rekomendasi Pimpinan oleh Perencanaan

| Field | Detail |
|---|---|
| **ID** | `US-09.01` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Rekomendasi Pimpinan & Status Capaian |
| **Dependensi** | Indikator, tahun, dan periode valid. |
| **Otorisasi** | `rekomendasi:tetapkan` (sensitif). |
| **Dampak Data** | `rekomendasi_pimpinan`, `audit_log`. |

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mencatat arahan/rekomendasi pimpinan hasil evaluasi per indikator-periode,  
> **Sehingga** tindak lanjut pimpinan terdokumentasi walaupun approval aktif Pimpinan belum masuk MVP.

**Acceptance Criteria**

- [ ] **AC-1:** Given rekomendasi valid, When disimpan, Then baris baru dibuat dengan `ditetapkan_oleh` dan waktu.
- [ ] **AC-2:** Given pengukuran periode belum disahkan, When rekomendasi ditulis setelah rapat, Then sistem tetap mengizinkan karena rekomendasi independen dari status pengukuran.
- [ ] **AC-3:** Given rekomendasi lama ada, When rekomendasi baru dibuat, Then baris baru menjadi yang aktif dan histori lama tidak dihapus.
- [ ] **AC-4:** Given role Pimpinan pada MVP, When membaca rekomendasi, Then bersifat read-only.

### US-09.02 · Penetapan Status Capaian per Versi Pengukuran Disahkan

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

---

## Bagian 10 — Dashboard, Rekapitulasi, dan Ekspor

### US-10.01 · Dashboard Kinerja Eksekutif

| Field | Detail |
|---|---|
| **ID** | `US-10.01` |
| **Prioritas** | 🟡 P1 |
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

### US-10.02 · Matriks Rekapitulasi Indikator × Periode

| Field | Detail |
|---|---|
| **ID** | `US-10.02` |
| **Prioritas** | 🟡 P1 |
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
- [ ] **AC-4:** Then narasi kegiatan/kendala/tindak lanjut berasal dari versi resmi yang dinilai, bukan live data yang telah berubah.
- [ ] **AC-5:** Then rekomendasi pimpinan, status capaian, dan status bukti ditampilkan sesuai versi/konteks.
- [ ] **AC-6:** Given `tidak_dapat_dihitung` atau `tidak_dapat_dipenuhi`, Then penanda ditampilkan dan tidak disamakan dengan nilai nol.

### US-10.03 · Ekspor Laporan Kinerja ke Excel

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

---

## Bagian 11 — Bukti Dukung Multi-Mode & Integritas Lampiran

### US-11.01 · Konfigurasi Persyaratan Jenis Berkas

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

| Field | Detail |
|---|---|
| **ID** | `US-11.02` |
| **Prioritas** | 🟡 P1 |
| **Story Points** | 5 |
| **Modul** | Bukti Dukung Multi-Mode & Integritas Lampiran |
| **Dependensi** | `berkas.unggahan_aktif = true`; capability induk valid. |
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

| Field | Detail |
|---|---|
| **ID** | `US-11.04` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 8 |
| **Modul** | Bukti Dukung Multi-Mode & Integritas Lampiran |
| **Dependensi** | Berkas terkait salah satu induk yang didukung. |
| **Otorisasi** | `berkas:delete` (sensitif) + guard status induk. |
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

### US-11.06 · Pengecualian Mode File Saat Unggah Dinonaktifkan

| Field | Detail |
|---|---|
| **ID** | `US-11.06` |
| **Prioritas** | 🟡 P1 |
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

- [ ] **AC-1:** Given jendela RA aktif/mendekati batas, When halaman dibuka, Then banner menampilkan `rencana_aksi_selesai` sesuai konteks.
- [ ] **AC-2:** Given periode pengisian aktif, Then countdown mengacu `pengisian_selesai` resmi.
- [ ] **AC-3:** Given indikator belum diisi, Then status muncul hanya untuk periode yang berlaku.
- [ ] **AC-4:** Given bukti `tidak_dapat_dipenuhi` atau PK belum lengkap, Then Perencanaan mendapat penanda yang relevan.
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
| **Prioritas** | 🔴 P0 |
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

---

## Bagian 14 — Penutupan Tahunan & Koreksi Pasca-Penutupan

### US-14.01 · Penutupan Resmi Jadwal Tahunan

| Field | Detail |
|---|---|
| **ID** | `US-14.01` |
| **Prioritas** | 🔴 P0 |
| **Story Points** | 5 |
| **Modul** | Penutupan Tahunan & Koreksi Pasca-Penutupan |
| **Dependensi** | Jadwal aktif; tanggal/otoritas penutupan memenuhi kebijakan. |
| **Otorisasi** | `jadwal:tutup` (sensitif). |
| **Dampak Data** | `jadwal_tahunan`, `audit_log`. |

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** menutup siklus tahunan secara resmi,  
> **Sehingga** data tahun tersebut terkunci dari mutasi normal dan hanya dapat dikoreksi melalui jalur pembukaan resmi.

**Acceptance Criteria**

- [ ] **AC-1:** Given penutupan dikonfirmasi, When commit, Then status menjadi `ditutup` dan `closed_at` terisi.
- [ ] **AC-2:** Given jadwal ditutup, When mutasi normal RA, kegiatan, klaim, bukti, atau pengukuran dicoba, Then ditolak.
- [ ] **AC-3:** Given data perlu dikoreksi pasca-penutupan, Then perubahan tidak dilakukan langsung; harus melalui `jadwal:buka_kembali` dengan sesi koreksi.
- [ ] **AC-4:** Given penutupan adalah aksi sensitif, Then audit menyimpan aktor dan `dasar_izin`.

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

---

## Bagian 15 — Kesiapan Operasional, UAT, dan Penggunaan Pertama

### US-15.01 · Verifikasi Data Awal sebelum Penggunaan Pertama

| Field | Detail |
|---|---|
| **ID** | `US-15.01` |
| **Prioritas** | 🟡 P1 |
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
6. JIT SSO membuat user nonaktif tanpa role;
7. logout lokal default + logout SSO terpisah;
8. Jadwal pertama 2026; TW I–II periode lampau oleh Perencanaan, TW III–IV normal;
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
- US-15.01: initial setup 2026 mencakup RA existing berstatus disahkan sebelum TW III.

Acceptance Criteria yang bertentangan dengan Q32 dinyatakan superseded dan harus diperbarui pada issue implementasi sebelum coding/UAT.


## Matriks Ketertelusuran

| User Story | Plan Pengembangan | PRD | Workflow | Entitas/Objek Utama |
|---|---|---|---|---|
| **US-01.01** | Modul 1: 1.2–1.3 | §6–7 | §1, §19 | `users` |
| **US-01.02** | Modul 1: 1.4, 1.18 | §7.7 | §21 | `unit` |
| **US-01.03** | Modul 1: 1.6–1.7, 1.13 | §7.1–7.5 | §21 | `roles`, `user_roles` |
| **US-01.04** | Modul 1: 1.8, 1.14 | §7.1–7.5 | §19, §21 | `user_permission_granted` |
| **US-01.05** | Modul 1: 1.9, 1.15 | §7.1–7.5 | §19, §21 | `user_permission_denials` |
| **US-01.06** | Modul 1: 1.16 | §7.4–7.5 | §19, §21 | resolver izin |
| **US-01.07** | Modul 1: 1.22–1.23 | §7.3–7.5, §25 | §19, §21–23 | `role_permissions` |
| **US-02.01** | Modul 2: 2.16–2.19 | §9 | §3 | `regulasi`, `berkas` |
| **US-02.02** | Modul 2: 2.1–2.2, 2.18 | §10 | §2–3 | `renstra`, `berkas` |
| **US-02.03** | Modul 2: 2.3–2.4, 2.13 | §10.2–10.5 | §2, §14 | `renstra`, snapshot |
| **US-02.04** | Modul 2: 2.5–2.7 | §11 | §2 | `sasaran`, `indikator` |
| **US-02.05** | Modul 2: 2.8–2.9 | §11, §25 | §14 | `indikator`, snapshot |
| **US-02.06** | Modul 2: 2.14–2.15 | §17 | §6 | `indikator_komponen` |
| **US-02.07** | Modul 2: 2.10 | §11–12, §17 | §5–6 | `target_tahunan`, snapshot |
| **US-02.08** | Modul 2: 2.11, 2.20 | §10.6, §14.10 | §3, §5 | `renstra_pk`, `berkas` |
| **US-02.09** | Modul 2: 2.12 | §12.5–12.7 | §13–14 | PK + snapshot version |
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
| **US-06.01** | Modul 12: 12.1–12.2 | §15.1–15.4 | §8 | `kegiatan` |
| **US-06.02** | Modul 12: 12.6–12.9 | §16 | §9 | `klaim_kegiatan` |
| **US-06.03** | Modul 12: 12.11; Modul 13 | §15.6, §18 | §8–10 | kegiatan + bukti |
| **US-06.04** | Modul 12: 12.3 | §15.2–15.5 | §8 | `kegiatan` |
| **US-06.05** | Modul 12: 12.4 | §15.4 | §8 | `kegiatan_asal_id` |
| **US-06.06** | Modul 12: 12.10 | §16.5 | §9, §13 | klaim + versi |
| **US-06.07** | Modul 13: 13.6 | §18.8 | §10 | `berkas` append-only |
| **US-07.01** | Modul 5: 5.2–5.4, 5.13–5.15 | §17, §19–20 | §11 | pengukuran + komponen |
| **US-07.02** | Modul 13: 13.3–13.5 | §18 | §10–11 | `berkas` |
| **US-07.03** | Modul 5: 5.5, 5.16 | §18.11, §19.4 | §11–12, §20 | `pengukuran_versi` |
| **US-07.04** | Modul 5: 5.4–5.8 | §19.3 | §11–12 | pengukuran |
| **US-07.05** | Modul 3: 3.11; Modul 5: 5.17 | §12.6, §17.6, §19 | §15 | historical measurement |
| **US-07.06** | Modul 5: 5.13–5.16 | §17.4, §19.4 | §11 | status perhitungan |
| **US-08.01** | Modul 5: 5.6–5.8; Modul 6: 6.1 | §7.6, §19 | §12, §20 | pengukuran + versi |
| **US-08.02** | Modul 5: 5.9; Modul 6: 6.2 | §7.6, §19 | §12, §20 | pengukuran + versi |
| **US-08.03** | Modul 5: 5.10; Modul 6: 6.3–6.6 | §19.5, §21 | §13, §16 | versi + status capaian |
| **US-09.01** | Modul 8: 8.5 | §22 | §17 | `rekomendasi_pimpinan` |
| **US-09.02** | Modul 6: 6.4–6.6 | §21 | §16 | `status_capaian` |
| **US-10.01** | Modul 7: 7.1–7.8 | §23 | §1, §14 | dashboard queries |
| **US-10.02** | Modul 8: 8.1, 8.3 | §22.4, §24 | §17 | report matrix |
| **US-10.03** | Modul 8: 8.2, 8.4 | §24 | §17 | Excel export |
| **US-10.04** | Modul 8: 8.4; P.4 | §24, §32 | §17 | UAT artifact |
| **US-11.01** | Modul 13: 13.1 | §18.2–18.7 | §10 | `jenis_berkas` |
| **US-11.02** | Modul 13: 13.2, 13.5 | §18.4–18.9 | §10 | `berkas`, private disk |
| **US-11.03** | Modul 13: 13.2–13.5 | §18.4–18.6 | §10 | `berkas` |
| **US-11.04** | Modul 13: 13.6 | §18.8 | §10 | `berkas` |
| **US-11.05** | Modul 13: 13.4 | §18.11 | §10 | versi + persyaratan |
| **US-11.06** | Modul 13: 13.7 | §18.5–18.7 | §10 | pengecualian file |
| **US-12.01** | Modul 14: 14.1 | §28.1 | §22.1 | alert server-side |
| **US-12.02** | Modul 14: 14.1 | §28.1 | §22.1 | in-app notifications |
| **US-12.03** | Modul 14: 14.2 | §28.2 | §22.2 | WA/Email queue |
| **US-12.04** | Modul 14: 14.2 | §28.2 | §22.2 | EWS |
| **US-12.05** | Modul 14: 14.2 | §28.2 | §22.2 | recap notification |
| **US-12.06** | Modul 14: 14.2 | §28.2 | §22.2 | return notification |
| **US-12.07** | Modul 14: 14.2 | §28.2 | §22.2 | delivery/idempotency |
| **US-13.01** | Modul 9: 9.1–9.6 | §26 | §18 | `pengaturan` |
| **US-13.02** | Modul 9: 9.7–9.9 | §18.9, §26 | §18 | storage policy |
| **US-13.03** | Modul 10: 10.1–10.6 | §25 | §19–20 | `audit_log` |
| **US-13.04** | Modul 14: 14.2; Modul 9 | §28.2 | §22.2 | notification config |
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

- Approval aktif oleh Pimpinan (`pengukuran:setujui`) belum menjadi langkah workflow MVP.
- Anggaran kegiatan belum masuk UI/API MVP.
- Formula bertingkat generik di luar mesin satu tingkat yang telah disepakati ditunda.
- Multi-role per user ditunda; MVP mempertahankan maksimum satu role utama per pengguna dari lima role final, sementara user JIT dapat sementara belum memiliki role.
- Integrasi sumber data otomatis untuk `status_capaian.sumber = data_sumber` ditunda.
- Fitur yang dependency operasionalnya belum ditetapkan (provider, credential, owner, waktu kirim, tanggal go-live) tidak boleh diasumsikan siap hanya karena kode tersedia.

---

**Catatan baseline Q32:** keputusan role/PIC, eligibility PJ, onboarding, logout, penggunaan 2026, dan formula IKU sudah final. Dependency terbuka yang tersisa terutama tanggal penerimaan/go-live, provider/owner operasional, notifikasi, infrastruktur, data penetapan awal 2026, dan contoh keluaran Excel UAT.
