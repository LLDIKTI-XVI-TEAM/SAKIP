# USER ISSUES — SAKIP LLDIKTI WILAYAH XVI

> **Klarifikasi final LLDIKTI Wilayah XVI — 24 September 2026**  
> Bagian ini adalah kontrak terbaru dan **menggantikan keputusan Q31 atau teks lama yang bertentangan**. Role bawaan SAKIP berjumlah **lima**: `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`. **PIC bukan role sistem**; PIC adalah konteks operasional yang dibentuk oleh penugasan dan grant unit. Detail keputusan dicatat sebagai **Q32** pada dokumen Keputusan Penyelarasan.


**Status:** Revisi Q32 — 24 September 2026. Seluruh issue mengikuti jawaban resmi LLDIKTI terbaru; Q31 hanya histori dan tidak lagi menjadi kontrak implementasi.

## 1. Tujuan

Dokumen ini menerjemahkan seluruh User Story menjadi **development issue yang executable**. Setiap issue mempertahankan kontrak bisnis User Story namun menambahkan pekerjaan teknis yang perlu diselesaikan pada persistence, backend/domain, authorization/audit, frontend, testing, dan operasi/integrasi bila relevan.

## 2. Hasil Review File User Issues Sebelumnya

- File sebelumnya memiliki **54 issue** dan masih mengikuti User Stories lama. Baseline baru memiliki **74 User Stories**, sehingga terdapat capability yang belum memiliki issue.
- Technical task lama terlalu sering memakai pola generik `Database / Backend middleware / Frontend React`, sehingga beberapa task salah kategori—contohnya login publik diperlakukan seperti route RBAC, cron dianggap middleware user, dan private disk dianggap migration database.
- Issue lama belum cukup menangkap kontrak kritis: F1/F2 berdasarkan `*_versi.diajukan_by`, immutable versioning, snapshot correction, backfill, revisi jendela PIC, evidence freeze, append-only correction, notification idempotency, serta readiness/UAT.
- Dokumen ini **menggantikan** pola tersebut dengan 1 issue untuk 1 User Story serta test traceability per Acceptance Criteria.
- Penyelarasan Q31 **tidak menambah issue baru di luar 74 issue 1:1**. Dampaknya diserap ke issue akses/PIC yang relevan (`ISS-01.03`, `ISS-01.04`, `ISS-01.06`, `ISS-01.07`, `ISS-04.01`, issue RA/Pengukuran/Notifikasi terkait PIC, `ISS-10.01`, dan `ISS-15.01`) serta ke aturan global. Requirement yang masih OPEN tidak diubah menjadi AC final.

## 3. Aturan Global untuk Seluruh Development Issue

- Gunakan Laravel 13 + Inertia 3 + React 19 + TypeScript + Bun sebagaimana baseline Plan/Design System.
- Permission dievaluasi server-side melalui resolver/Policy/Gate; unknown permission fail closed dan deny yang cocok selalu menang.
- Grant unit tidak menggantikan PIC efektif; validasi izin dan validasi bisnis adalah dua lapisan berbeda.
- F1/F2 memakai `rencana_aksi_versi.diajukan_by` / `pengukuran_versi.diajukan_by` + `jalur_pengajuan`, bukan `created_by`.
- Setiap submit RA/Pengukuran membuat versi immutable. Laporan resmi membaca versi disahkan.
- Aksi sensitif mengisi `audit_log.dasar_izin`; audit append-only.
- Jadwal dibuka kembali tidak otomatis membuka PIC; jendela PIC harus dibuka terpisah dan masih tunduk scope/grant/PIC/status.
- UI mengikuti canonical `design-system.md`; tidak menggunakan raw hex/warna default di luar token.
- Automated test developer tidak menggantikan walkthrough/UAT dan bukti readiness operasional.

- **Q32 role final:** hanya `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`; PIC bukan role.
- **PIC operasional:** konteks user/assignment/grant; bukan nilai `roles.kode`.
- **Grant Unit:** hanya 7 permission scoped dan digerbangi `delegasi:update`.
- **PJ:** assignment historis; tidak memberi permission dan tidak berakhir otomatis ketika role berubah.
- **Role permissions:** halaman read-only; preset berubah via code/seeder/release.
- **Issue tidak boleh diselesaikan dengan asumsi yang bertentangan dengan Q32.**

## 4. Format Issue

Setiap issue memuat: **Terkait User Story, Prioritas/Story Point, Traceability, Labels, Scope, Acceptance Criteria, Implementation Tasks, Automated Tests/UAT, dan Definition of Done**. Nomor `ISS-XX.YY` selalu mengikuti `US-XX.YY`.

### 4.1 Kontrak Penyelarasan Q32 untuk Development Issue

Semua issue wajib mengikuti keputusan final 24 September 2026:

- 5 role final: `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`;
- tidak ada role `pic`; PIC tetap ada sebagai konteks operasional;
- PIC operasional = user aktif + assignment bila relevan + Grant Unit + business guard;
- PJ bukan role dan tidak memberi permission;
- 7 permission unit-scoped final;
- Grant Unit menggunakan `delegasi:update`;
- Assign Role/Deny menggunakan `akses:update`;
- role-permission UI read-only; preset via code/seeder/release;
- onboarding SSO nonaktif + tanpa role;
- logout lokal default, logout SSO terpisah;
- Jadwal pertama 2026, TW I–II periode lampau, TW III–IV normal, tanpa `is_backfill`;
- IKU 3/IKU 8 mengikuti formula final Q32.

Bila body issue lama bertentangan dengan poin di atas, **Q32 mengalahkan teks lama** dan body issue harus diperbarui sebelum PR dinyatakan Ready for QA.

## Bagian 1 — Fondasi Autentikasi, Unit Organisasi, dan Model Akses RBAC

### ISS-01.01 · [Feature] Login Terpusat Menggunakan Single Sign-On (SSO) Keycloak

> **Q32 FINAL:** JIT SSO membuat user `nonaktif` **tanpa role**. Admin mengaktifkan dan assign salah satu 5 role. Logout lokal default; logout SSO aksi terpisah.

**Terkait User Story:** `US-01.01`  
**Prioritas:** 🔴 P0  
**Story Points:** 5  
**Labels:** `feature`, `auth-rbac`, `P0`, `frontend`, `ops`  
**Plan:** Modul 1: 1.2–1.3  
**PRD:** §6–7  
**Workflow:** §1, §19  
**Data Model/Entitas:** `users`

#### User Story

> **Sebagai** seluruh Pengguna SAKIP,  
> **Saya ingin** melakukan autentikasi menggunakan akun resmi institusi melalui Keycloak OIDC,  
> **Sehingga** pengguna tidak mengelola password lokal dan identitas aplikasi tetap terpusat.

#### Kontrak Teknis

- **Dependensi:** Realm Keycloak LLDIKTI XVI aktif, client SAKIP terdaftar, callback URL tersedia.
- **Otorisasi:** Rute login/callback publik; halaman aplikasi setelah callback memerlukan sesi autentikasi.
- **Dampak Data:** `users`, sesi autentikasi; audit kegagalan/kejadian penting sesuai kebijakan.
- **Rule:** Tidak ada password lokal SAKIP.
- **Rule:** Token/secret Keycloak tidak boleh masuk log, audit, atau props React.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given pengguna belum login, When menekan **Login SSO**, Then sistem mengarahkan ke Keycloak melalui OIDC Authorization Code Flow.
- [ ] **AC-2:** Given callback membawa token yang valid, When callback diproses, Then sistem mencocokkan `keycloak_id`, menyinkronkan nama/email yang diizinkan, membentuk sesi Laravel, dan mengarahkan pengguna ke dashboard.
- [ ] **AC-3:** Given pengguna belum ada pada `users`, When login pertama berhasil, Then akun lokal dibuat dengan identitas Keycloak dan status aktif sesuai kebijakan onboarding.
- [ ] **AC-4:** Given callback gagal atau token tidak valid, When diproses, Then sesi lokal tidak dibuat dan pengguna menerima pesan kesalahan yang aman tanpa membocorkan token/credential.
- [ ] **AC-5:** Given pengguna telah logout, When mencoba membuka rute terproteksi, Then sistem meminta autentikasi kembali.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `users`, sesi autentikasi; audit kegagalan/kejadian penting sesuai kebijakan.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan redirect OIDC Authorization Code Flow, callback, pemetaan `keycloak_id`, sinkronisasi atribut yang diizinkan, session Laravel, logout, dan error handling aman.
- [ ] Jangan menerapkan RBAC middleware pada rute login/callback publik; proteksi dimulai setelah sesi aplikasi terbentuk.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: Rute login/callback publik; halaman aplikasi setelah callback memerlukan sesi autentikasi.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Login Terpusat Menggunakan Single Sign-On (SSO) Keycloak**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

**E. Integration / Operations**
- [ ] Konfigurasi realm/client/callback melalui environment; jangan commit client secret.
- [ ] Tambahkan test mode/fake yang tidak membutuhkan realm produksi untuk CI.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given pengguna belum login, When menekan **Login SSO**, Then sistem mengarahkan ke Keycloak melalui OIDC Authorization Code Flow.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given callback membawa token yang valid, When callback diproses, Then sistem mencocokkan `keycloak_id`, menyinkronkan nama/email yang diizinkan, membentuk sesi Laravel, dan mengarahkan pengguna ke dashboard.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given pengguna belum ada pada `users`, When login pertama berhasil, Then akun lokal dibuat dengan identitas Keycloak dan status aktif sesuai kebijakan onboarding.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given callback gagal atau token tidak valid, When diproses, Then sesi lokal tidak dibuat dan pengguna menerima pesan kesalahan yang aman tanpa membocorkan token/credential.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given pengguna telah logout, When mencoba membuka rute terproteksi, Then sistem meminta autentikasi kembali.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-01.02 · [Feature] Pengelolaan Master Unit Organisasi

**Terkait User Story:** `US-01.02`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `auth-rbac`, `P1`, `frontend`  
**Plan:** Modul 1: 1.4, 1.18  
**PRD:** §7.7  
**Workflow:** §21  
**Data Model/Entitas:** `unit`

#### User Story

> **Sebagai** Admin atau Superadmin,  
> **Saya ingin** menambah, membaca, mengubah, menonaktifkan, dan menghapus unit organisasi yang benar-benar kosong,  
> **Sehingga** struktur pemilik indikator dan scope akses selalu mengikuti organisasi resmi.

#### Kontrak Teknis

- **Dependensi:** Pengguna telah login; katalog permission dan audit log tersedia.
- **Otorisasi:** `unit:create`, `unit:read`, `unit:update`, `unit:delete`.
- **Dampak Data:** `unit`, `audit_log`.
- **Rule:** Unit adalah master global, bukan hierarki organisasi bertingkat.
- **Rule:** Delete unit kosong hanya oleh Superadmin; Admin tetap dapat create/read/update sesuai katalog role.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given pengguna memiliki permission yang sesuai, When membuat unit dengan data valid, Then unit tersimpan dengan status default `aktif` dan perubahan tercatat di audit.
- [x] **AC-2:** Given unit masih memiliki keterkaitan dengan **indikator, rencana aksi, atau kegiatan**, When penghapusan dicoba, Then penghapusan ditolak terlepas dari role aktor.
- [x] **AC-3:** Given unit tidak memiliki keterkaitan historis yang dilindungi, When **Superadmin** menghapus unit, Then unit dapat dihapus dan alasan/peristiwa tercatat di audit.
- [x] **AC-4:** Given Admin memiliki `unit:update`, When menonaktifkan unit, Then status berubah tanpa menghapus histori.
- [x] **AC-5:** Given pengguna tanpa permission `unit:*`, When mengakses endpoint secara langsung, Then server mengembalikan 403.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `unit`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Implementasikan use-case **Pengelolaan Master Unit Organisasi** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [x] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `unit:create`, `unit:read`, `unit:update`, `unit:delete`.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Pengelolaan Master Unit Organisasi**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [x] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given pengguna memiliki permission yang sesuai, When membuat unit dengan data valid, Then unit tersimpan dengan status default `aktif` dan perubahan tercatat di audit.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given unit masih memiliki keterkaitan dengan **indikator, rencana aksi, atau kegiatan**, When penghapusan dicoba, Then penghapusan ditolak terlepas dari role aktor.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given unit tidak memiliki keterkaitan historis yang dilindungi, When **Superadmin** menghapus unit, Then unit dapat dihapus dan alasan/peristiwa tercatat di audit.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given Admin memiliki `unit:update`, When menonaktifkan unit, Then status berubah tanpa menghapus histori.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given pengguna tanpa permission `unit:*`, When mengakses endpoint secara langsung, Then server mengembalikan 403.

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [x] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-01.03 · [Feature] Penetapan Peran Utama Pengguna (Assign Peran)

**Terkait User Story:** `US-01.03`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `auth-rbac`, `audit-sensitive`, `frontend`

#### Kontrak Teknis

- Role final tepat 5: `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`.
- User JIT boleh belum memiliki role.
- Gate: `pengguna:read` + `akses:update`.
- Satu user maksimal satu role pada MVP.
- Role change tidak mengubah grant/deny/PJ/provenance historis.

#### Acceptance Criteria

- [ ] Assign role pertama berhasil dengan alasan.
- [ ] Change role mempertahankan constraint satu role.
- [ ] `pic` tidak muncul dan tidak dapat disimpan.
- [ ] Direct request tanpa permission 403.
- [ ] Grant/deny/PJ tidak berubah sebagai side effect.
- [ ] PJ aktif tetap aktif setelah role change dan dapat diberi warning monitoring.

#### Implementation Tasks

- [ ] Selaraskan RoleCatalog/seeder/dropdown/test ke 5 role.
- [ ] Dukung user tanpa role dari onboarding.
- [ ] Implementasikan transaction + stale-state/concurrency guard + audit.
- [ ] Pastikan React hanya memakai capability server.

#### Automated Tests / Verification

- [ ] Assign pertama, change role, invalid role, missing reason, unauthorized, concurrency, no-side-effect.

#### Definition of Done

- [ ] Semua AC/test lulus; UI sesuai Design System; corrective code Q31 lama sudah diselaraskan.

### ISS-01.04 · [Feature] Pemberian Grant Izin Tambahan per Unit

**Terkait User Story:** `US-01.04`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `auth-rbac`, `audit-sensitive`, `frontend`

#### Kontrak Teknis

Gate Form Grant = **`delegasi:update`**. Pemegang baseline: Perencanaan, Admin, Superadmin.

Hanya 7 permission unit-scoped:

```text
pengukuran:create
pengukuran:update
rencana_aksi:create
rencana_aksi:update
rencana_aksi:ajukan
kegiatan:create
kegiatan:update
```

#### Acceptance Criteria

- [x] Create/revoke grant valid dengan user aktif, unit aktif, alasan.
- [ ] Permission global, termasuk `rencana_aksi:read` dan `kegiatan:read`, ditolak oleh Form Grant Unit.
- [x] Missing unit/reason/duplicate ditolak.
- [ ] Direct request tanpa `delegasi:update` 403.
- [x] Grant/revoke tidak mengubah role atau assignment PJ.
- [x] Deny tetap menang.

#### Implementation Tasks

- [ ] Tambahkan/sinkronkan `delegasi:update`.
- [ ] Ubah `UNIT_SCOPED` menjadi tepat 7 kode.
- [ ] Ubah policy/controller/UI/tests dari `akses:update` ke `delegasi:update` untuk grant.
- [x] Audit create/revoke dengan actor, reason, permission, unit, dasar izin.

#### Definition of Done

- [ ] Resolver, UI, audit, test, dan seeder konsisten dengan Q32.

### ISS-01.05 · [Security] Pencabutan Izin Eksplisit (Deny)

**Terkait User Story:** `US-01.05`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `security`, `auth-rbac`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 1: 1.9, 1.15  
**PRD:** §7.1–7.5  
**Workflow:** §19, §21  
**Data Model/Entitas:** `user_permission_denials`

#### User Story

> **Sebagai** Admin atau Superadmin,  
> **Saya ingin** membuat deny global atau deny pada unit tertentu,  
> **Sehingga** akses berisiko dapat dihentikan presisi tanpa merusak konfigurasi role/grant lain.

#### Kontrak Teknis

- **Dependensi:** Pengguna target dan permission tersedia.
- **Otorisasi:** `akses:update`.
- **Dampak Data:** `user_permission_denials`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given user, permission, alasan, dan scope valid, When deny disimpan, Then baris `user_permission_denials` terbentuk.
- [x] **AC-2:** Given allow berasal dari role atau grant dan terdapat deny yang cocok, When resolver mengevaluasi izin, Then **deny menang** dan akses ditolak.
- [x] **AC-3:** Given deny berscope unit A, When user meminta permission unit-scoped pada unit B, Then deny unit A tidak otomatis memblokir unit B.
- [x] **AC-4:** Given deny global (`unit_id = NULL`) cocok, When permission diminta, Then permintaan ditolak untuk seluruh scope yang relevan.
- [x] **AC-5:** Given deny dicabut, When resolusi dilakukan ulang, Then izin efektif kembali mengikuti role/grant yang masih sah.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `user_permission_denials`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Implementasikan resolver deny global/unit dengan presedens **deny menang** terhadap allow role maupun grant.
- [x] Pastikan pencabutan deny langsung tercermin pada request berikutnya tanpa cache izin statis yang stale.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `akses:update`.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Pencabutan Izin Eksplisit (Deny)**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [x] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [x] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given user, permission, alasan, dan scope valid, When deny disimpan, Then baris `user_permission_denials` terbentuk.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given allow berasal dari role atau grant dan terdapat deny yang cocok, When resolver mengevaluasi izin, Then **deny menang** dan akses ditolak.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given deny berscope unit A, When user meminta permission unit-scoped pada unit B, Then deny unit A tidak otomatis memblokir unit B.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given deny global (`unit_id = NULL`) cocok, When permission diminta, Then permintaan ditolak untuk seluruh scope yang relevan.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given deny dicabut, When resolusi dilakukan ulang, Then izin efektif kembali mengikuti role/grant yang masih sah.
- [x] SECURITY: Uji request langsung untuk scope unit lain dan deny yang cocok menghasilkan 403 meski tombol UI disembunyikan.

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [x] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-01.06 · [Security] Transparansi Izin Pengguna — Jelaskan Izin

**Terkait User Story:** `US-01.06`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `security`, `auth-rbac`, `P1`, `frontend`  
**Plan:** Modul 1: 1.16  
**PRD:** §7.4–7.5  
**Workflow:** §19, §21  
**Data Model/Entitas:** resolver izin

#### User Story

> **Sebagai** Admin atau Superadmin,  
> **Saya ingin** melihat seluruh izin efektif pengguna per unit beserta sumber allow dan deny,  
> **Sehingga** keputusan akses dapat dijelaskan saat audit dan troubleshooting.

#### Kontrak Teknis

- **Dependensi:** Service resolver izin tersedia.
- **Otorisasi:** `pengguna:read`.
- **Dampak Data:** Read-only terhadap `roles`, `role_permissions`, grant, deny, permission.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given pengguna dipilih, When halaman dimuat, Then sistem menampilkan permission efektif, scope, asal peran/grant, dan deny yang berlaku.
- [ ] **AC-2:** Given suatu permission berasal dari role dan kemudian di-deny, When ditampilkan, Then permission ditandai dicabut dan tidak ditampilkan sebagai izin efektif.
- [ ] **AC-3:** Given halaman bersifat read-only, When pengguna berinteraksi, Then tidak ada mutasi role/grant/deny langsung dari halaman tersebut.
- [ ] **AC-4:** Given pengguna tanpa `pengguna:read`, When membuka endpoint, Then 403.
- [ ] **AC-5 (Q31):** Given target user ber-PIC operasional (bukan role), When halaman dimuat, Then sistem menampilkan izin efektif aktual berdasarkan `role_permissions`/grant/deny yang benar-benar ada; tidak ada permission sintetis hanya karena nama role PIC.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Verifikasi query, relasi, indeks, dan eager-loading yang diperlukan; **jangan** membuat mutasi domain baru untuk fitur read-only.

**B. Backend / Domain**
- [ ] Bangun service penjelasan izin dari role + grant + deny menggunakan resolver yang sama dengan otorisasi request; jangan buat algoritma kedua yang dapat berbeda hasil.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengguna:read`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.

**D. Frontend / UX**
- [ ] Buat/rapikan halaman Inertia React untuk **Transparansi Izin Pengguna — Jelaskan Izin** sebagai tampilan read-only/filterable sesuai hak server.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given pengguna dipilih, When halaman dimuat, Then sistem menampilkan permission efektif, scope, asal peran/grant, dan deny yang berlaku.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given suatu permission berasal dari role dan kemudian di-deny, When ditampilkan, Then permission ditandai dicabut dan tidak ditampilkan sebagai izin efektif.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given halaman bersifat read-only, When pengguna berinteraksi, Then tidak ada mutasi role/grant/deny langsung dari halaman tersebut.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given pengguna tanpa `pengguna:read`, When membuka endpoint, Then 403.

- [ ] TEST-Q31: Buat Pest Feature/Unit test untuk user PIC operasional (bukan role) dengan kombinasi role/grant/deny berbeda dan pastikan halaman menjelaskan permission efektif aktual tanpa hardcode role.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-01.07 · [Security] Peran & Izin Read-Only + Preset Permission via Seeder

**Terkait User Story:** `US-01.07`  
**Prioritas:** 🔴 P0 corrective alignment  
**Story Points:** 5  
**Labels:** `security`, `auth-rbac`, `audit-sensitive`, `frontend`

#### Kontrak Teknis

- UI `Peran & Izin` read-only, gate `pengguna:read`.
- Tidak ada mutation endpoint role-permission.
- Preset 5 role berasal dari code/seeder/release.
- Seeder delta wajib audit before/after + alasan/sumber rilis.
- Seeder tanpa delta idempoten.
- 7 permission unit-scoped tidak masuk role-global untuk mengganti grant.

#### Acceptance Criteria

- [ ] User berizin dapat melihat 5 role dan presetnya.
- [ ] User tanpa `pengguna:read` 403.
- [ ] Tidak ada tombol/form add/revoke/edit permission role.
- [ ] Mutation route/controller/service lama dihapus/nonaktif.
- [ ] Perubahan preset via release menghasilkan audit delta.
- [ ] Tidak ada role `pic` di katalog/fixture/test.

#### Implementation Tasks

- [ ] Hapus editor yang sebelumnya diimplementasikan PR #23.
- [ ] Implementasikan read-only page.
- [ ] Hapus mutation endpoints.
- [ ] Sinkronkan seeder + audit delta.
- [ ] Update test/fixture ke 5 role dan 7 scoped permissions.

#### Definition of Done

- [ ] Corrective PR ke `development` lulus review/QA dan tidak ada editor role-permission tersisa.

## Bagian 2 — Dokumen Dasar Hukum, Renstra, Sasaran, Indikator, Target, dan PK

### ISS-02.01 · [Feature] Pencatatan Dokumen Dasar Regulasi

**Terkait User Story:** `US-02.01`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `master-data`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 2: 2.16–2.19  
**PRD:** §9  
**Workflow:** §3  
**Data Model/Entitas:** `regulasi`, `berkas`

#### User Story

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** mencatat katalog dasar hukum lengkap dengan metadata dan lampiran,  
> **Sehingga** Renstra dan indikator memiliki rujukan hukum yang dapat ditelusuri.

#### Kontrak Teknis

- **Dependensi:** Dokumen/metadata regulasi tersedia.
- **Otorisasi:** `regulasi:create`, `regulasi:read`, `regulasi:update`, `regulasi:delete`.
- **Dampak Data:** `regulasi`, `berkas`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given metadata regulasi valid, When disimpan, Then regulasi terbentuk dan audit dicatat.
- [x] **AC-2:** Given lampiran berupa file/tautan/teks, When disimpan, Then `berkas` terbentuk sebagai lampiran bebas dokumen dasar (`jenis_berkas_id = NULL`).
- [x] **AC-3:** Given kombinasi jenis-nomor-tahun yang sama telah ada, When dibuat lagi, Then duplikasi ditolak.
- [x] **AC-4:** Given regulasi masih dirujuk Renstra/Indikator aktif, When delete dicoba, Then penghapusan ditolak.
- [x] **AC-5:** Given aksi update/delete adalah sensitif, When berhasil atau ditolak oleh deny, Then `audit_log.dasar_izin` merekam sumber keputusan izin.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `regulasi`, `berkas`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Implementasikan use-case **Pencatatan Dokumen Dasar Regulasi** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [x] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `regulasi:create`, `regulasi:read`, `regulasi:update`, `regulasi:delete`.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Pencatatan Dokumen Dasar Regulasi**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [x] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [x] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given metadata regulasi valid, When disimpan, Then regulasi terbentuk dan audit dicatat.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given lampiran berupa file/tautan/teks, When disimpan, Then `berkas` terbentuk sebagai lampiran bebas dokumen dasar (`jenis_berkas_id = NULL`).
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given kombinasi jenis-nomor-tahun yang sama telah ada, When dibuat lagi, Then duplikasi ditolak.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given regulasi masih dirujuk Renstra/Indikator aktif, When delete dicoba, Then penghapusan ditolak.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given aksi update/delete adalah sensitif, When berhasil atau ditolak oleh deny, Then `audit_log.dasar_izin` merekam sumber keputusan izin.

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [x] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-02.02 · [Feature] Penyusunan Master Renstra & Rujukan Regulasi

**Terkait User Story:** `US-02.02`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `master-data`, `P1`, `frontend`  
**Plan:** Modul 2: 2.1–2.2, 2.18  
**PRD:** §10  
**Workflow:** §2–3  
**Data Model/Entitas:** `renstra`, `berkas`

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** menyusun Renstra dengan rentang tahun, dasar hukum, dan lampiran resmi,  
> **Sehingga** arah strategis multi-tahun menjadi sumber utama struktur kinerja.

#### Kontrak Teknis

- **Dependensi:** Master regulasi tersedia bila digunakan.
- **Otorisasi:** `renstra:create`, `renstra:read`, `renstra:update`, `renstra:delete`.
- **Dampak Data:** `renstra`, `berkas`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given data Renstra valid, When dibuat, Then status awal `draft` dan data tersimpan.
- [ ] **AC-2:** Given naskah Renstra dilampirkan dalam mode yang sah, When disimpan, Then lampiran terhubung ke Renstra.
- [ ] **AC-3:** Given rentang tahun tidak valid, When submit, Then validasi menolak.
- [ ] **AC-4:** Given Renstra aktif memiliki lampiran yang telah mencapai batas imutabilitas, When delete lampiran dicoba, Then ditolak.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `renstra`, `berkas`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Penyusunan Master Renstra & Rujukan Regulasi** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `renstra:create`, `renstra:read`, `renstra:update`, `renstra:delete`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Penyusunan Master Renstra & Rujukan Regulasi**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given data Renstra valid, When dibuat, Then status awal `draft` dan data tersimpan.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given naskah Renstra dilampirkan dalam mode yang sah, When disimpan, Then lampiran terhubung ke Renstra.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given rentang tahun tidak valid, When submit, Then validasi menolak.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given Renstra aktif memiliki lampiran yang telah mencapai batas imutabilitas, When delete lampiran dicoba, Then ditolak.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-02.03 · [Feature] Aktivasi, Arsip, dan Revisi Renstra karena Perubahan Kebijakan

**Terkait User Story:** `US-02.03`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `master-data`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 2: 2.3–2.4, 2.13  
**PRD:** §10.2–10.5  
**Workflow:** §2, §14  
**Data Model/Entitas:** `renstra`, snapshot

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengaktifkan, mengarsipkan, atau merevisi Renstra secara terkendali ketika dasar kebijakan/IKU berubah,  
> **Sehingga** perubahan kebijakan dapat diterapkan tanpa menghapus histori tahun/periode yang sudah berjalan.

#### Kontrak Teknis

- **Dependensi:** Renstra draft beserta struktur minimum telah tersedia; keputusan revisi resmi tersedia bila mengubah Renstra aktif.
- **Otorisasi:** `renstra:update` dan permission terkait entitas turunannya.
- **Dampak Data:** `renstra`, `sasaran`, `indikator`, `jadwal_snapshot`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given tidak ada Renstra aktif dengan rentang tahun beririsan, When aktivasi dilakukan, Then Renstra menjadi aktif.
- [ ] **AC-2:** Given Renstra memiliki jadwal aktif, When dinonaktifkan secara langsung, Then sistem menolak agar konteks tahun berjalan tidak rusak.
- [ ] **AC-3:** Given Kepmen/aturan IKU baru mengubah struktur indikator, When revisi dilakukan, Then perubahan master dicatat dan histori snapshot lama tidak ditimpa.
- [ ] **AC-4:** Given indikator baru hasil revisi berlaku mulai periode tertentu, Then efektivitasnya ditentukan melalui mekanisme snapshot/periode mulai, bukan dianggap wajib sejak periode sebelumnya.
- [ ] **AC-5:** Given perubahan substansial dilakukan, Then audit menyimpan alasan, aktor, nilai lama, dan nilai baru.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `renstra`, `sasaran`, `indikator`, `jadwal_snapshot`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Aktivasi, Arsip, dan Revisi Renstra karena Perubahan Kebijakan** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `renstra:update` dan permission terkait entitas turunannya.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Aktivasi, Arsip, dan Revisi Renstra karena Perubahan Kebijakan**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given tidak ada Renstra aktif dengan rentang tahun beririsan, When aktivasi dilakukan, Then Renstra menjadi aktif.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given Renstra memiliki jadwal aktif, When dinonaktifkan secara langsung, Then sistem menolak agar konteks tahun berjalan tidak rusak.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given Kepmen/aturan IKU baru mengubah struktur indikator, When revisi dilakukan, Then perubahan master dicatat dan histori snapshot lama tidak ditimpa.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given indikator baru hasil revisi berlaku mulai periode tertentu, Then efektivitasnya ditentukan melalui mekanisme snapshot/periode mulai, bukan dianggap wajib sejak periode sebelumnya.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given perubahan substansial dilakukan, Then audit menyimpan alasan, aktor, nilai lama, dan nilai baru.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-02.04 · [Feature] Penyusunan Sasaran Strategis & Indikator Kinerja

> **Q32 FINAL:** IKU 8 = `n/t × 100%`, t total publikasi seluruh PTS; jangan hardcode 84. IKU 3 harus mendukung formula dua input.

**Terkait User Story:** `US-02.04`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `master-data`, `P1`, `frontend`  
**Plan:** Modul 2: 2.5–2.7  
**PRD:** §11  
**Workflow:** §2  
**Data Model/Entitas:** `sasaran`, `indikator`

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** menyusun Sasaran dan Indikator lengkap dengan unit pemilik, arah, satuan, formula, dan regulasi,  
> **Sehingga** setiap ukuran kinerja memiliki definisi dan kepemilikan yang tegas.

#### Kontrak Teknis

- **Dependensi:** Renstra tersedia; unit tersedia.
- **Otorisasi:** `sasaran:create`, `sasaran:update`, `sasaran:delete`, `indikator:create`, `indikator:read`, `indikator:update`, `indikator:delete`.
- **Dampak Data:** `sasaran`, `indikator`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given data sasaran valid, When disimpan, Then sasaran tersimpan di bawah Renstra yang benar.
- [ ] **AC-2:** Given data indikator valid, When disimpan, Then indikator memiliki unit pemilik, arah (`naik_baik`/`turun_baik`), tipe (`manual`/`rasio_persen`/`penjumlahan`), satuan, presisi, dan regulasi bila ada.
- [ ] **AC-3:** Given indikator baru dibuat, When status belum siap digunakan, Then indikator tidak otomatis masuk kewajiban periode yang sudah lampau.
- [ ] **AC-4:** Given kode/relasi tidak valid, When submit, Then server menolak terlepas dari validasi klien.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `sasaran`, `indikator`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Penyusunan Sasaran Strategis & Indikator Kinerja** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `sasaran:create`, `sasaran:update`, `sasaran:delete`, `indikator:create`, `indikator:read`, `indikator:update`, `indikator:delete`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Penyusunan Sasaran Strategis & Indikator Kinerja**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given data sasaran valid, When disimpan, Then sasaran tersimpan di bawah Renstra yang benar.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given data indikator valid, When disimpan, Then indikator memiliki unit pemilik, arah (`naik_baik`/`turun_baik`), tipe (`manual`/`rasio_persen`/`penjumlahan`), satuan, presisi, dan regulasi bila ada.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given indikator baru dibuat, When status belum siap digunakan, Then indikator tidak otomatis masuk kewajiban periode yang sudah lampau.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given kode/relasi tidak valid, When submit, Then server menolak terlepas dari validasi klien.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-02.05 · [Feature] Perpindahan Unit dan Pengarsipan Indikator

**Terkait User Story:** `US-02.05`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `master-data`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 2: 2.8–2.9  
**PRD:** §11, §25  
**Workflow:** §14  
**Data Model/Entitas:** `indikator`, snapshot

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** memindahkan kepemilikan indikator atau mengarsipkan indikator dengan jejak historis utuh,  
> **Sehingga** perubahan struktur organisasi tidak mengubah laporan historis secara diam-diam.

#### Kontrak Teknis

- **Dependensi:** Indikator telah ada; unit tujuan valid.
- **Otorisasi:** `indikator:update` / `indikator:delete` sesuai tindakan.
- **Dampak Data:** `indikator`, snapshot terkait, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given indikator dipindahkan ke unit lain, When perubahan disimpan, Then `indikator.unit_id` berubah untuk konteks master ke depan dan audit merekam unit lama/baru.
- [ ] **AC-2:** Given snapshot lama telah dipakai RA/Pengukuran, When unit master berubah, Then snapshot/laporan versi lama tetap memakai unit historis yang dibekukan.
- [ ] **AC-3:** Given indikator diarsipkan, When periode baru dihitung, Then indikator arsip tidak menjadi kewajiban baru.
- [ ] **AC-4:** Given ada histori pengukuran/RA, When hard delete indikator dicoba, Then sistem menolak penghapusan permanen yang merusak histori.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `indikator`, snapshot terkait, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Perpindahan Unit dan Pengarsipan Indikator** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `indikator:update` / `indikator:delete` sesuai tindakan.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Perpindahan Unit dan Pengarsipan Indikator**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given indikator dipindahkan ke unit lain, When perubahan disimpan, Then `indikator.unit_id` berubah untuk konteks master ke depan dan audit merekam unit lama/baru.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given snapshot lama telah dipakai RA/Pengukuran, When unit master berubah, Then snapshot/laporan versi lama tetap memakai unit historis yang dibekukan.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given indikator diarsipkan, When periode baru dihitung, Then indikator arsip tidak menjadi kewajiban baru.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given ada histori pengukuran/RA, When hard delete indikator dicoba, Then sistem menolak penghapusan permanen yang merusak histori.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-02.06 · [Feature] Konfigurasi Komponen Angka Indikator (Data-Driven)

> **Q32 FINAL:** AC lima komponen IKU 3 superseded. Gunakan tepat dua input `sakip` + `zi_wbk`, koefisien 0,5 masing-masing.

**Terkait User Story:** `US-02.06`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `master-data`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 2: 2.14–2.15  
**PRD:** §17  
**Workflow:** §6  
**Data Model/Entitas:** `indikator_komponen`

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mendefinisikan komponen pembilang, penyebut, penjumlah, bobot, urutan, dan kode,  
> **Sehingga** perhitungan dapat dikonfigurasi dari data tanpa menaruh formula per indikator di UI.

#### Kontrak Teknis

- **Dependensi:** Indikator bertipe nonmanual tersedia.
- **Otorisasi:** `komponen:create`, `komponen:read`, `komponen:update`, `komponen:delete`.
- **Dampak Data:** `indikator_komponen`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given indikator `rasio_persen`, When definisi disimpan, Then sistem mensyaratkan minimal satu pembilang dan tepat satu penyebut efektif.
- [x] **AC-2:** Given indikator `penjumlahan`, When definisi disimpan, Then minimal satu komponen penjumlah tersedia.
- [x] **AC-3:** Given IKU 3 dikonfigurasi, Then tersedia tepat dua input `sakip` dan `zi_wbk` dengan koefisien 0,5 dan formula `(sakip + zi_wbk) / 2`.
- [x] **AC-4:** Given kode komponen sama pada indikator yang sama, When submit, Then constraint unik menolak.
- [x] **AC-5:** Given definisi komponen diubah setelah snapshot historis dirujuk, Then snapshot lama tidak berubah.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `indikator_komponen`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Implementasikan use-case **Konfigurasi Komponen Angka Indikator (Data-Driven)** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [x] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `komponen:create`, `komponen:read`, `komponen:update`, `komponen:delete`.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Konfigurasi Komponen Angka Indikator (Data-Driven)**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [x] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [x] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given indikator `rasio_persen`, When definisi disimpan, Then sistem mensyaratkan minimal satu pembilang dan tepat satu penyebut efektif.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given indikator `penjumlahan`, When definisi disimpan, Then minimal satu komponen penjumlah tersedia.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given IKU 3 memakai keputusan Q5, Then tersedia lima komponen datar `perencanaan_kinerja`, `pengukuran_kinerja`, `pelaporan_kinerja`, `evaluasi_internal`, `zi`, masing-masing koefisien 0,5; subtotal SAKIP hanya nilai turunan tampilan.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given kode komponen sama pada indikator yang sama, When submit, Then constraint unik menolak.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given definisi komponen diubah setelah snapshot historis dirujuk, Then snapshot lama tidak berubah.

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [x] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-02.07 · [Feature] Penetapan Baseline & Target Tahunan

> **Q32 FINAL:** baseline IKU 3 2025 = 74,2; target 2026 = 76,25; jangan anggap selisih sebagai tren; 66,395/87,08 bukan realisasi.

**Terkait User Story:** `US-02.07`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `master-data`, `P1`, `frontend`  
**Plan:** Modul 2: 2.10  
**PRD:** §11–12, §17  
**Workflow:** §5–6  
**Data Model/Entitas:** `target_tahunan`, snapshot

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** menetapkan baseline dan target tahunan indikator,  
> **Sehingga** target PK memiliki pembanding resmi sebelum siklus tahunan diaktifkan.

#### Kontrak Teknis

- **Dependensi:** Indikator valid dan tahun berada dalam rentang Renstra.
- **Otorisasi:** `target:update`.
- **Dampak Data:** `target_tahunan`, `audit_log`.
- **Rule:** Katalog permission baseline hanya memakai `target:update`; tidak ada `target:create` terpisah pada baseline PRD.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given indikator dan tahun valid, When baseline/target disimpan, Then satu baris target tahunan tersedia untuk kombinasi tersebut.
- [ ] **AC-2:** Given kombinasi indikator-tahun sudah ada, When nilai diperbarui, Then data diperbarui melalui `target:update` dan tidak membuat duplikasi.
- [ ] **AC-3:** Given target sudah dibekukan ke snapshot yang dirujuk histori, When master target dikoreksi, Then laporan lama tidak ikut berubah.
- [ ] **AC-4:** Given koreksi salah input terhadap sumber PK resmi dibutuhkan, Then koreksi snapshot dilakukan melalui mekanisme versi dengan alasan dan rujukan bukti.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `target_tahunan`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Gunakan permission literal `target:update`; jangan membuat `target:create` baru tanpa perubahan katalog permission resmi.
- [ ] Pisahkan koreksi master target dari koreksi snapshot historis.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `target:update`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Penetapan Baseline & Target Tahunan**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given indikator dan tahun valid, When baseline/target disimpan, Then satu baris target tahunan tersedia untuk kombinasi tersebut.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given kombinasi indikator-tahun sudah ada, When nilai diperbarui, Then data diperbarui melalui `target:update` dan tidak membuat duplikasi.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given target sudah dibekukan ke snapshot yang dirujuk histori, When master target dikoreksi, Then laporan lama tidak ikut berubah.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given koreksi salah input terhadap sumber PK resmi dibutuhkan, Then koreksi snapshot dilakukan melalui mekanisme versi dengan alasan dan rujukan bukti.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-02.08 · [Feature] Pencatatan Perjanjian Kinerja (PK) & Lampiran Legal

**Terkait User Story:** `US-02.08`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `master-data`, `P1`, `frontend`  
**Plan:** Modul 2: 2.11, 2.20  
**PRD:** §10.6, §14.10  
**Workflow:** §3, §5  
**Data Model/Entitas:** `renstra_pk`, `berkas`

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mencatat PK tahun berjalan beserta metadata dan dokumen resmi,  
> **Sehingga** jadwal hanya dapat diaktifkan dengan dasar komitmen formal yang dapat dibuktikan.

#### Kontrak Teknis

- **Dependensi:** Renstra valid.
- **Otorisasi:** `pk:create`, `pk:update`, serta capability berkas melalui kewenangan induk.
- **Dampak Data:** `renstra_pk`, `berkas`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given nomor/tanggal/tahun PK valid, When disimpan, Then `renstra_pk` terbentuk untuk Renstra tersebut.
- [ ] **AC-2:** Given lampiran file/tautan/teks tersedia, When disimpan, Then `berkas` terhubung ke `renstra_pk`.
- [ ] **AC-3:** Given PK untuk Renstra-tahun yang sama sudah ada, When dibuat ulang, Then duplikasi ditolak.
- [ ] **AC-4:** Given jadwal tahun tersebut telah aktif, When lampiran PK dihapus, Then penghapusan ditolak sesuai batas imutabilitas.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `renstra_pk`, `berkas`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Pencatatan Perjanjian Kinerja (PK) & Lampiran Legal** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pk:create`, `pk:update`, serta capability berkas melalui kewenangan induk.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pencatatan Perjanjian Kinerja (PK) & Lampiran Legal**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given nomor/tanggal/tahun PK valid, When disimpan, Then `renstra_pk` terbentuk untuk Renstra tersebut.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given lampiran file/tautan/teks tersedia, When disimpan, Then `berkas` terhubung ke `renstra_pk`.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given PK untuk Renstra-tahun yang sama sudah ada, When dibuat ulang, Then duplikasi ditolak.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given jadwal tahun tersebut telah aktif, When lampiran PK dihapus, Then penghapusan ditolak sesuai batas imutabilitas.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-02.09 · [Feature] Koreksi Perjanjian Kinerja Secara Teraudit

**Terkait User Story:** `US-02.09`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `master-data`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 2: 2.12  
**PRD:** §12.5–12.7  
**Workflow:** §13–14  
**Data Model/Entitas:** PK + snapshot version

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengoreksi salah input metadata/target PK dengan alasan dan rujukan resmi,  
> **Sehingga** kesalahan administratif dapat diperbaiki tanpa menghapus konteks historis.

#### Kontrak Teknis

- **Dependensi:** PK telah ada; sumber koreksi resmi tersedia.
- **Otorisasi:** `pk:update`.
- **Dampak Data:** `renstra_pk`, snapshot terdampak bila ada, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given koreksi PK diajukan tanpa alasan, When submit, Then ditolak.
- [ ] **AC-2:** Given nilai PK dikoreksi berdasarkan dokumen resmi, When disimpan, Then audit merekam nilai lama/baru, alasan, dan rujukan.
- [ ] **AC-3:** Given snapshot belum dirujuk, When koreksi diperbolehkan, Then snapshot dapat dikoreksi sesuai aturan.
- [ ] **AC-4:** Given snapshot sudah dirujuk versi RA/Pengukuran, When koreksi diperlukan, Then sistem membuat versi snapshot pengganti dan mempertahankan snapshot lama.
- [ ] **AC-5:** Given hasil resmi lama sudah disahkan, Then hasil lama tidak berubah sampai versi koreksi diajukan dan disahkan ulang.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `renstra_pk`, snapshot terdampak bila ada, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Koreksi Perjanjian Kinerja Secara Teraudit** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pk:update`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Koreksi Perjanjian Kinerja Secara Teraudit**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given koreksi PK diajukan tanpa alasan, When submit, Then ditolak.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given nilai PK dikoreksi berdasarkan dokumen resmi, When disimpan, Then audit merekam nilai lama/baru, alasan, dan rujukan.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given snapshot belum dirujuk, When koreksi diperbolehkan, Then snapshot dapat dikoreksi sesuai aturan.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given snapshot sudah dirujuk versi RA/Pengukuran, When koreksi diperlukan, Then sistem membuat versi snapshot pengganti dan mempertahankan snapshot lama.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given hasil resmi lama sudah disahkan, Then hasil lama tidak berubah sampai versi koreksi diajukan dan disahkan ulang.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.


---

## Bagian 3 — Periode, Jadwal, Snapshot, Efektivitas, dan Backfill

### ISS-03.01 · [Feature] Penyusunan Master Periode, Jadwal Tahunan, dan Jendela Periode

> **Q32 FINAL:** Jadwal pertama 2026; TW I–II periode lampau oleh Perencanaan, TW III–IV normal; tidak ada flag backfill.

**Terkait User Story:** `US-03.01`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `schedule-snapshot`, `P1`, `frontend`  
**Plan:** Modul 3: 3.1–3.4  
**PRD:** §12.1–12.3  
**Workflow:** §4  
**Data Model/Entitas:** `periode`, `jadwal_*`

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** menyusun periode yang diharapkan, jendela Rencana Aksi, pengisian, reviu, dan penutupan,  
> **Sehingga** siklus kerja tahunan memiliki kalender resmi dan sumber kebenaran tunggal.

#### Kontrak Teknis

- **Dependensi:** Renstra/PK tersedia untuk tahun yang disiapkan.
- **Otorisasi:** `periode:create`, `periode:update`, `jadwal:create`, `jadwal:update`.
- **Dampak Data:** `periode`, `jadwal_tahunan`, `jadwal_periode`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given tahun Y disiapkan, When jadwal tahunan dibuat, Then status awal `draft` dengan `rencana_aksi_mulai`, `rencana_aksi_selesai`, dan `penutupan`.
- [ ] **AC-2:** Given daftar Triwulan/Semester dimasukkan, When disimpan, Then `jadwal_periode` unik per periode pada jadwal tersebut.
- [ ] **AC-3:** Given urutan normal, Then `rencana_aksi_mulai <= rencana_aksi_selesai < pengisian_mulai` periode pertama.
- [ ] **AC-4:** Given jendela reviu disusun, Then `reviu_selesai` diperlakukan sebagai target operasional; Perencanaan masih dapat reviu sampai penutupan dengan penanda terlambat.
- [ ] **AC-5:** Given tanggal tidak konsisten, When submit, Then server menolak.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `periode`, `jadwal_tahunan`, `jadwal_periode`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Penyusunan Master Periode, Jadwal Tahunan, dan Jendela Periode** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `periode:create`, `periode:update`, `jadwal:create`, `jadwal:update`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Penyusunan Master Periode, Jadwal Tahunan, dan Jendela Periode**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given tahun Y disiapkan, When jadwal tahunan dibuat, Then status awal `draft` dengan `rencana_aksi_mulai`, `rencana_aksi_selesai`, dan `penutupan`.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given daftar Triwulan/Semester dimasukkan, When disimpan, Then `jadwal_periode` unik per periode pada jadwal tersebut.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given urutan normal, Then `rencana_aksi_mulai <= rencana_aksi_selesai < pengisian_mulai` periode pertama.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given jendela reviu disusun, Then `reviu_selesai` diperlakukan sebagai target operasional; Perencanaan masih dapat reviu sampai penutupan dengan penanda terlambat.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given tanggal tidak konsisten, When submit, Then server menolak.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-03.02 · [Feature] Aktivasi Jadwal Tahunan melalui Empat Gerbang & Pembentukan Snapshot

**Terkait User Story:** `US-03.02`  
**Prioritas:** 🔴 P0  
**Story Points:** 8  
**Labels:** `feature`, `schedule-snapshot`, `P0`, `audit-sensitive`, `frontend`  
**Plan:** Modul 3: 3.5–3.7  
**PRD:** §12.4–12.5  
**Workflow:** §5  
**Data Model/Entitas:** `jadwal_snapshot*`

#### User Story

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** mengaktifkan jadwal setelah seluruh gerbang wajib terpenuhi,  
> **Sehingga** konteks indikator dan formula tahun berjalan dibekukan secara aman sebelum pekerjaan PIC dimulai.

#### Kontrak Teknis

- **Dependensi:** Jadwal draft; data master tahun berjalan siap.
- **Otorisasi:** `jadwal:aktivasi` (sensitif).
- **Dampak Data:** `jadwal_tahunan`, `jadwal_snapshot`, `jadwal_snapshot_komponen`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given aktivasi diminta, When server mengevaluasi, Then empat gerbang diperiksa: PK tersedia; target tahunan semua indikator aktif tersedia; tahun berada dalam rentang Renstra; lampiran PK tersedia atau pengecualian file yang sah tercatat.
- [ ] **AC-2:** Given salah satu gerbang wajib gagal, When aktivasi diproses, Then seluruh transaksi ditolak atomik dan pesan menjelaskan gerbang yang gagal.
- [ ] **AC-3:** Given semua gerbang lolos, When commit berhasil, Then jadwal menjadi `aktif` dan `activated_at` terisi.
- [ ] **AC-4:** Given indikator aktif belum memiliki snapshot pada jadwal, When aktivasi sukses, Then `jadwal_snapshot` dibuat idempoten beserta `jadwal_snapshot_komponen`.
- [ ] **AC-5:** Given snapshot pasangan indikator-jadwal sudah ada, When proses diulang, Then baris lama tidak ditimpa/duplikasi.
- [ ] **AC-6:** Given permission sensitif digunakan, Then audit menyimpan `dasar_izin` dan aktor sebenarnya.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `jadwal_tahunan`, `jadwal_snapshot`, `jadwal_snapshot_komponen`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Jaga snapshot idempoten/berversi: snapshot yang telah dirujuk tidak boleh di-update in-place.

**B. Backend / Domain**
- [ ] Implementasikan aktivasi dalam satu transaksi atomik: empat gerbang → transisi status → snapshot indikator → snapshot komponen → audit.
- [ ] Pembuatan snapshot harus idempoten dan tidak menimpa pasangan jadwal-indikator yang sudah ada.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `jadwal:aktivasi` (sensitif).
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Aktivasi Jadwal Tahunan melalui Empat Gerbang & Pembentukan Snapshot**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given aktivasi diminta, When server mengevaluasi, Then empat gerbang diperiksa: PK tersedia; target tahunan semua indikator aktif tersedia; tahun berada dalam rentang Renstra; lampiran PK tersedia atau pengecualian file yang sah tercatat.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given salah satu gerbang wajib gagal, When aktivasi diproses, Then seluruh transaksi ditolak atomik dan pesan menjelaskan gerbang yang gagal.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given semua gerbang lolos, When commit berhasil, Then jadwal menjadi `aktif` dan `activated_at` terisi.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given indikator aktif belum memiliki snapshot pada jadwal, When aktivasi sukses, Then `jadwal_snapshot` dibuat idempoten beserta `jadwal_snapshot_komponen`.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given snapshot pasangan indikator-jadwal sudah ada, When proses diulang, Then baris lama tidak ditimpa/duplikasi.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given permission sensitif digunakan, Then audit menyimpan `dasar_izin` dan aktor sebenarnya.
- [ ] CONCURRENCY: Uji transaksi/optimistic locking/versi stale sesuai kontrak Data Model.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-03.03 · [Feature] Koreksi Snapshot Terkendali dan Versioning Konteks

**Terkait User Story:** `US-03.03`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `schedule-snapshot`, `P1`, `audit-sensitive`, `backend`  
**Plan:** Modul 3: 3.8  
**PRD:** §12.5–12.7  
**Workflow:** §13–14  
**Data Model/Entitas:** `jadwal_snapshot*`

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengoreksi konteks snapshot yang salah tanpa mengubah versi yang telah dipakai histori,  
> **Sehingga** target, formula, dan identitas laporan historis tetap dapat dipertanggungjawabkan.

#### Kontrak Teknis

- **Dependensi:** Snapshot tersedia; koreksi memiliki alasan dan rujukan resmi.
- **Otorisasi:** `target:update` / permission substantif terkait serta guard jadwal.
- **Dampak Data:** `jadwal_snapshot`, `jadwal_snapshot_komponen`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given snapshot belum pernah dirujuk RA/Pengukuran/versi pengajuan, When koreksi sah dilakukan pada jadwal aktif, Then snapshot dapat dikoreksi dan audit menyimpan before/after.
- [ ] **AC-2:** Given snapshot sudah dirujuk, When koreksi diperlukan, Then baris versi baru dibuat dengan `nomor_versi + 1`, `menggantikan_id`, `alasan_koreksi`, dan `rujukan_koreksi`.
- [ ] **AC-3:** Given versi lama telah dirujuk laporan/pengajuan, Then versi lama dan komponen anaknya tetap immutable.
- [ ] **AC-4:** Given pengukuran hendak memakai snapshot koreksi, Then pengukuran harus diajukan dan disahkan ulang; tidak ada propagasi diam-diam.
- [ ] **AC-5:** Given versi snapshot tidak cocok indikator/jadwal, When koreksi dibuat, Then ditolak.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `jadwal_snapshot`, `jadwal_snapshot_komponen`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Jaga snapshot idempoten/berversi: snapshot yang telah dirujuk tidak boleh di-update in-place.

**B. Backend / Domain**
- [ ] Jika snapshot belum dirujuk, koreksi hanya pada kondisi yang diizinkan dan selalu diaudit; jika sudah dirujuk, buat versi pengganti dengan `menggantikan_id`, alasan, dan rujukan resmi.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `target:update` / permission substantif terkait serta guard jadwal.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given snapshot belum pernah dirujuk RA/Pengukuran/versi pengajuan, When koreksi sah dilakukan pada jadwal aktif, Then snapshot dapat dikoreksi dan audit menyimpan before/after.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given snapshot sudah dirujuk, When koreksi diperlukan, Then baris versi baru dibuat dengan `nomor_versi + 1`, `menggantikan_id`, `alasan_koreksi`, dan `rujukan_koreksi`.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given versi lama telah dirujuk laporan/pengajuan, Then versi lama dan komponen anaknya tetap immutable.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given pengukuran hendak memakai snapshot koreksi, Then pengukuran harus diajukan dan disahkan ulang; tidak ada propagasi diam-diam.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given versi snapshot tidak cocok indikator/jadwal, When koreksi dibuat, Then ditolak.
- [ ] CONCURRENCY: Uji transaksi/optimistic locking/versi stale sesuai kontrak Data Model.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-03.04 · [Feature] Penambahan Indikator Baru di Tengah Tahun & Periode Mulai Berlaku

**Terkait User Story:** `US-03.04`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `schedule-snapshot`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 3: 3.7–3.8  
**PRD:** §10.5, §12.5  
**Workflow:** §14  
**Data Model/Entitas:** snapshot `periode_mulai_id`

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** menambahkan indikator resmi yang baru berlaku mulai periode tertentu,  
> **Sehingga** periode sebelumnya tidak salah dianggap belum mengisi atau bernilai nol.

#### Kontrak Teknis

- **Dependensi:** Jadwal aktif; indikator baru telah ditetapkan resmi.
- **Otorisasi:** `jadwal:buka_kembali` untuk penambahan snapshot beralasan pada jadwal aktif; permission indikator terkait.
- **Dampak Data:** `jadwal_snapshot`, `jadwal_snapshot_komponen`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given indikator baru ditambahkan pada jadwal aktif, When snapshot dibuat, Then `periode_mulai_id` wajib ditetapkan dan merupakan anggota jadwal.
- [ ] **AC-2:** Given periode sebelum `periode_mulai_id`, When dashboard/laporan menghitung kewajiban, Then statusnya **Tidak berlaku**, bukan Belum Mengisi/Tidak Mengisi/nol.
- [ ] **AC-3:** Given snapshot indikator lama sudah ada, When indikator baru ditambahkan, Then snapshot lama tidak ditimpa.
- [ ] **AC-4:** Given deadline PIC lama tidak diubah, When penambahan indikator dilakukan, Then akses PIC tidak otomatis dibuka; jendela resmi harus diatur terpisah jika diperlukan.
- [ ] **AC-5:** Given tindakan dilakukan, Then alasan dan detail penambahan tercatat di audit.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `jadwal_snapshot`, `jadwal_snapshot_komponen`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Jaga snapshot idempoten/berversi: snapshot yang telah dirujuk tidak boleh di-update in-place.

**B. Backend / Domain**
- [ ] Tambahkan indikator baru pada jadwal aktif secara eksplisit dan tetapkan `periode_mulai_id`; periode sebelumnya harus menghasilkan status Tidak berlaku.
- [ ] Penambahan snapshot tidak boleh membuka deadline PIC secara implisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `jadwal:buka_kembali` untuk penambahan snapshot beralasan pada jadwal aktif; permission indikator terkait.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Penambahan Indikator Baru di Tengah Tahun & Periode Mulai Berlaku**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given indikator baru ditambahkan pada jadwal aktif, When snapshot dibuat, Then `periode_mulai_id` wajib ditetapkan dan merupakan anggota jadwal.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given periode sebelum `periode_mulai_id`, When dashboard/laporan menghitung kewajiban, Then statusnya **Tidak berlaku**, bukan Belum Mengisi/Tidak Mengisi/nol.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given snapshot indikator lama sudah ada, When indikator baru ditambahkan, Then snapshot lama tidak ditimpa.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given deadline PIC lama tidak diubah, When penambahan indikator dilakukan, Then akses PIC tidak otomatis dibuka; jendela resmi harus diatur terpisah jika diperlukan.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given tindakan dilakukan, Then alasan dan detail penambahan tercatat di audit.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-03.05 · [Feature] Pengisian Periode Lampau 2026 oleh Perencanaan

**Terkait User Story:** `US-03.05`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `schedule`, `P1`, `audit-sensitive`

#### Scope

Mendukung TW I–II 2026 sebagai periode lampau berdasarkan `pengisian_selesai < activated_at`, tanpa flag/backfill mode khusus. Perencanaan mengisi periode lampau; PK 2026 tetap wajib; gerbang RA/komponen/bukti normal dikecualikan pada jalur ini.

#### Acceptance Criteria

- [ ] TW I–II terdeteksi otomatis sebagai periode lampau.
- [ ] Tidak ada `is_backfill`/`retroaktif` sebagai state domain baru.
- [ ] Perencanaan dapat mengisi periode lampau; user scoped tetap tunduk jendela.
- [ ] Tahun 2025 tidak dibuat sebagai Jadwal/Pengukuran.
- [ ] Aktivasi dan pengisian tetap teraudit.

#### Automated Tests

- [ ] Uji tanggal untuk TW I–II vs TW III–IV.
- [ ] Uji bypass hanya jalur Perencanaan yang memang diizinkan.
- [ ] Uji PK 2026 tetap menjadi gerbang.
- [ ] Uji tidak ada flag backfill.

### ISS-03.06 · [Feature] Revisi Resmi Jendela PIC

**Terkait User Story:** `US-03.06`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `schedule-snapshot`, `P1`, `frontend`  
**Plan:** Modul 3: 3.12  
**PRD:** §12.3, §12.6  
**Workflow:** §13  
**Data Model/Entitas:** jadwal windows

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** memperpanjang atau membuka ulang jendela kerja PIC secara resmi,  
> **Sehingga** pengecualian tenggat tidak dilakukan diam-diam dan tetap terbatas waktu.

#### Kontrak Teknis

- **Dependensi:** Jadwal ada; perubahan deadline memiliki alasan dan batas baru.
- **Otorisasi:** `jadwal:update`.
- **Dampak Data:** `jadwal_tahunan`/`jadwal_periode`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given deadline PIC perlu diubah, When Perencanaan mengisi alasan dan batas baru, Then jendela resmi diperbarui dan audit menyimpan batas lama/baru.
- [ ] **AC-2:** Given PIC mencoba bekerja di luar jendela yang berlaku, Then ditolak meskipun jadwal berstatus aktif.
- [ ] **AC-3:** Given jadwal sebelumnya ditutup dan sedang dalam sesi koreksi, When jendela PIC dibuka, Then jendela baru wajib berada di dalam `koreksi_mulai..koreksi_sampai` dan hanya untuk lingkup yang diizinkan.
- [ ] **AC-4:** Given jendela dibuka untuk PIC, Then grant unit, PIC efektif, deny, status record, dan gerbang kelengkapan tetap wajib.
- [ ] **AC-5:** Given `jadwal:buka_kembali` dilakukan tanpa revisi jendela PIC, Then PIC tetap tidak memperoleh akses koreksi otomatis.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `jadwal_tahunan`/`jadwal_periode`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan perubahan deadline/jendela resmi dengan alasan dan audit before/after. Setelah penutupan, jendela PIC baru harus berada di dalam sesi koreksi dan lingkup koreksi.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `jadwal:update`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Revisi Resmi Jendela PIC**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given deadline PIC perlu diubah, When Perencanaan mengisi alasan dan batas baru, Then jendela resmi diperbarui dan audit menyimpan batas lama/baru.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given PIC mencoba bekerja di luar jendela yang berlaku, Then ditolak meskipun jadwal berstatus aktif.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given jadwal sebelumnya ditutup dan sedang dalam sesi koreksi, When jendela PIC dibuka, Then jendela baru wajib berada di dalam `koreksi_mulai..koreksi_sampai` dan hanya untuk lingkup yang diizinkan.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given jendela dibuka untuk PIC, Then grant unit, PIC efektif, deny, status record, dan gerbang kelengkapan tetap wajib.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given `jadwal:buka_kembali` dilakukan tanpa revisi jendela PIC, Then PIC tetap tidak memperoleh akses koreksi otomatis.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).


---

## Bagian 4 — Penugasan Penanggung Jawab (PIC)

### ISS-04.01 · [Feature] Penetapan & Pergantian Penanggung Jawab Indikator

**Terkait User Story:** `US-04.01`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `assignment`, `audit-sensitive`, `frontend`

#### Kontrak Teknis Final Q32

- Target PJ dapat berupa **user aktif mana pun**; tidak ada syarat role `pic`.
- Penetapan dilakukan Perencanaan/Superadmin sesuai `penanggung_jawab:update`.
- Assignment tidak memberikan permission.
- User tetap membutuhkan Grant Unit yang cocok untuk hak kerja scoped.
- Calon PJ tanpa grant: **warning, bukan blokir**.
- Sistem menyediakan daftar **PJ aktif tanpa hak isi**.
- Pergantian menambah histori baru; baris lama tidak ditimpa.
- Perubahan role user tidak mengakhiri assignment.

#### Acceptance Criteria

- [ ] Penugasan awal user aktif berhasil.
- [ ] User nonaktif ditolak.
- [ ] User tanpa grant tetap dapat ditetapkan dengan warning.
- [ ] Pergantian PJ wajib alasan dan menjaga histori.
- [ ] Query PJ efektif menggunakan `tanggal_mulai_berlaku` terbaru <= tanggal acuan.
- [ ] Role change tidak menonaktifkan PJ.
- [ ] Daftar PJ tanpa hak isi dapat ditampilkan secara akurat.

#### Automated Tests

- [ ] Assignment awal/pergantian/histori.
- [ ] User nonaktif.
- [ ] Warning tanpa grant.
- [ ] Monitoring PJ tanpa hak isi.
- [ ] Regression role change tidak menghapus assignment.

#### Definition of Done

- [ ] Tidak ada validasi role PIC; authorization dan audit server-side; UI sesuai Design System.

### ISS-05.01 · [Feature] Penyusunan Target Rencana Aksi per Periode

**Terkait User Story:** `US-05.01`  
**Prioritas:** 🔴 P0  
**Story Points:** 8  
**Labels:** `feature`, `rencana-aksi`, `P0`, `frontend`  
**Plan:** Modul 11: 11.1–11.2, 11.5–11.6  
**PRD:** §14  
**Workflow:** §7  
**Data Model/Entitas:** `rencana_aksi_target`

#### User Story

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** mengisi target kumulatif per periode secara manual atau per komponen sesuai snapshot,  
> **Sehingga** target tahunan dapat diturunkan menjadi target operasional tanpa kehilangan konsistensi formula.

#### Kontrak Teknis

- **Dependensi:** Jadwal aktif; daftar periode tersedia; snapshot indikator tersedia; PIC memenuhi grant unit + PIC efektif atau Perencanaan memiliki izin global.
- **Otorisasi:** `rencana_aksi:create`, `rencana_aksi:update`.
- **Dampak Data:** `rencana_aksi`, `rencana_aksi_target`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given indikator manual, When target periode diisi, Then satu target langsung per periode disimpan dengan `komponen_id = NULL`.
- [ ] **AC-2:** Given indikator nonmanual, When target diisi, Then target disimpan per komponen snapshot dan nilai turunan dihitung server.
- [ ] **AC-3:** Given target periode lebih rendah dari periode sebelumnya, When disimpan, Then sistem memberi warning kumulatif namun tidak memblokir.
- [ ] **AC-4:** Given periode sebelum efektivitas indikator, When target diminta, Then periode tersebut dikecualikan sebagai Tidak berlaku.
- [ ] **AC-5:** Given PIC berada di luar jendela RA, When mutasi dicoba, Then ditolak; Perencanaan dapat bekerja sampai penutupan/jendela koreksi yang sah.
- [ ] **AC-6:** Given target akhir berbeda dari target PK snapshot, Then submit tetap dapat dilakukan hanya setelah alasan deviasi diisi.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `rencana_aksi`, `rencana_aksi_target`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Penyusunan Target Rencana Aksi per Periode** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `rencana_aksi:create`, `rencana_aksi:update`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Penyusunan Target Rencana Aksi per Periode**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given indikator manual, When target periode diisi, Then satu target langsung per periode disimpan dengan `komponen_id = NULL`.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given indikator nonmanual, When target diisi, Then target disimpan per komponen snapshot dan nilai turunan dihitung server.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given target periode lebih rendah dari periode sebelumnya, When disimpan, Then sistem memberi warning kumulatif namun tidak memblokir.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given periode sebelum efektivitas indikator, When target diminta, Then periode tersebut dikecualikan sebagai Tidak berlaku.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given PIC berada di luar jendela RA, When mutasi dicoba, Then ditolak; Perencanaan dapat bekerja sampai penutupan/jendela koreksi yang sah.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given target akhir berbeda dari target PK snapshot, Then submit tetap dapat dilakukan hanya setelah alasan deviasi diisi.
- [ ] SECURITY: Uji request langsung untuk scope unit lain dan deny yang cocok menghasilkan 403 meski tombol UI disembunyikan.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-05.02 · [Feature] Pemenuhan Bukti Dukung Rencana Aksi

**Terkait User Story:** `US-05.02`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `rencana-aksi`, `P1`, `frontend`  
**Plan:** Modul 13: 13.3–13.5  
**PRD:** §18  
**Workflow:** §10  
**Data Model/Entitas:** `berkas`

#### User Story

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** memenuhi persyaratan bukti RA dengan mode file, tautan, atau teks yang diizinkan,  
> **Sehingga** rencana aksi memiliki dasar dokumen yang dapat diverifikasi.

#### Kontrak Teknis

- **Dependensi:** RA berstatus dapat diedit; persyaratan bukti tersedia.
- **Otorisasi:** Capability berkas mengikuti kewenangan induk RA; deny berkas tetap berlaku.
- **Dampak Data:** `berkas`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given persyaratan tahap `rencana_aksi`, When bukti mode valid disimpan, Then `berkas` terhubung ke RA dan jenis persyaratan yang tepat.
- [ ] **AC-2:** Given mode tidak diizinkan, When bukti dikirim, Then server menolak.
- [ ] **AC-3:** Given `semua_mode_wajib = true`, When sebagian mode belum terpenuhi, Then persyaratan tetap belum lengkap.
- [ ] **AC-4:** Given deny/capability induk tidak mengizinkan upload, When request dipanggil langsung, Then 403.
- [ ] **AC-5:** Given bukti sudah dibekukan dalam versi pengajuan, Then perubahan setelahnya tidak mengubah snapshot versi tersebut.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `berkas`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Pemenuhan Bukti Dukung Rencana Aksi** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: Capability berkas mengikuti kewenangan induk RA; deny berkas tetap berlaku.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pemenuhan Bukti Dukung Rencana Aksi**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given persyaratan tahap `rencana_aksi`, When bukti mode valid disimpan, Then `berkas` terhubung ke RA dan jenis persyaratan yang tepat.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given mode tidak diizinkan, When bukti dikirim, Then server menolak.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given `semua_mode_wajib = true`, When sebagian mode belum terpenuhi, Then persyaratan tetap belum lengkap.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given deny/capability induk tidak mengizinkan upload, When request dipanggil langsung, Then 403.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given bukti sudah dibekukan dalam versi pengajuan, Then perubahan setelahnya tidak mengubah snapshot versi tersebut.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-05.03 · [Feature] Pengajuan Rencana Aksi & Pembekuan Versi

**Terkait User Story:** `US-05.03`  
**Prioritas:** 🔴 P0  
**Story Points:** 8  
**Labels:** `feature`, `rencana-aksi`, `P0`, `frontend`  
**Plan:** Modul 11: 11.1, 11.3  
**PRD:** §14.6–14.9, §18.11  
**Workflow:** §7, §20  
**Data Model/Entitas:** `rencana_aksi_versi`

#### User Story

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** mengajukan RA sebagai versi substansi yang immutable,  
> **Sehingga** review dan laporan selalu menilai data persis seperti saat diajukan.

#### Kontrak Teknis

- **Dependensi:** RA draft/dikembalikan; target dan bukti memenuhi aturan; jendela aktor sah.
- **Otorisasi:** `rencana_aksi:ajukan` (unit-scoped bagi PIC; global bagi Perencanaan).
- **Dampak Data:** `rencana_aksi`, `rencana_aksi_versi`, `audit_log`, notifikasi in-app.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given target wajib/periode berlaku belum lengkap, When submit, Then pengajuan ditolak.
- [ ] **AC-2:** Given bukti wajib belum lengkap sesuai versi persyaratan saat submit, When submit, Then ditolak.
- [ ] **AC-3:** Given deviasi target akhir terhadap PK belum memiliki alasan, When submit, Then ditolak.
- [ ] **AC-4:** Given seluruh gerbang lolos, When submit commit, Then status header menjadi `diajukan` dan **baris baru `rencana_aksi_versi`** dibuat.
- [ ] **AC-5:** Given versi dibuat, Then server membekukan `diajukan_by`, `diajukan_at`, `jalur_pengajuan` (`pic`/`perencanaan`), `dasar_izin_pengajuan`, target, persyaratan, bukti, klaim/narasi yang relevan, dan snapshot konteks.
- [ ] **AC-6:** Given draft dibuat oleh A tetapi diajukan B, Then `diajukan_by = B`; `created_by` tidak dipakai sebagai identitas pengaju F1/F2.
- [ ] **AC-7:** Given submit berhasil, Then notifikasi in-app untuk antrean Perencanaan dibuat setelah transaksi berhasil.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `rencana_aksi`, `rencana_aksi_versi`, `audit_log`, notifikasi in-app.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Pastikan `rencana_aksi_versi` immutable setelah INSERT; submit ulang membuat versi baru, bukan overwrite snapshot lama.

**B. Backend / Domain**
- [ ] Submit RA harus berjalan atomik, memvalidasi target/bukti/deviasi PK/waktu/scope/PIC, lalu membuat `rencana_aksi_versi` baru.
- [ ] Bekukan `diajukan_by`, `diajukan_at`, `jalur_pengajuan`, `dasar_izin_pengajuan`, target, persyaratan, bukti, klaim/narasi, dan konteks snapshot.
- [ ] `created_by` hanya pembuat draft; jangan gunakan untuk F1/F2.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `rencana_aksi:ajukan` (unit-scoped bagi PIC; global bagi Perencanaan).
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pengajuan Rencana Aksi & Pembekuan Versi**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given target wajib/periode berlaku belum lengkap, When submit, Then pengajuan ditolak.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given bukti wajib belum lengkap sesuai versi persyaratan saat submit, When submit, Then ditolak.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given deviasi target akhir terhadap PK belum memiliki alasan, When submit, Then ditolak.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given seluruh gerbang lolos, When submit commit, Then status header menjadi `diajukan` dan **baris baru `rencana_aksi_versi`** dibuat.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given versi dibuat, Then server membekukan `diajukan_by`, `diajukan_at`, `jalur_pengajuan` (`pic`/`perencanaan`), `dasar_izin_pengajuan`, target, persyaratan, bukti, klaim/narasi yang relevan, dan snapshot konteks.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given draft dibuat oleh A tetapi diajukan B, Then `diajukan_by = B`; `created_by` tidak dipakai sebagai identitas pengaju F1/F2.
- [ ] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given submit berhasil, Then notifikasi in-app untuk antrean Perencanaan dibuat setelah transaksi berhasil.
- [ ] REGRESSION: Uji pembuat draft berbeda dari pengaju; perubahan role/PIC/grant setelah submit tidak mengubah provenance atau meloloskan F1.
- [ ] CONCURRENCY: Uji transaksi/optimistic locking/versi stale sesuai kontrak Data Model.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-05.04 · [Feature] Verifikasi & Pengembalian Rencana Aksi dengan F1

**Terkait User Story:** `US-05.04`  
**Prioritas:** 🔴 P0  
**Story Points:** 8  
**Labels:** `feature`, `rencana-aksi`, `P0`, `audit-sensitive`, `frontend`  
**Plan:** Modul 11: 11.3  
**PRD:** §7.6, §14.6  
**Workflow:** §7, §20  
**Data Model/Entitas:** RA + versi

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** memverifikasi versi RA atau mengembalikannya dengan alasan,  
> **Sehingga** substansi RA diperiksa tanpa membuka celah self-review jalur PIC.

#### Kontrak Teknis

- **Dependensi:** RA berstatus `diajukan` dan versi terbaru tersedia.
- **Otorisasi:** `rencana_aksi:verifikasi`, `rencana_aksi:kembalikan`.
- **Dampak Data:** `rencana_aksi`, versi terkait, `audit_log`, notifikasi.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given versi diajukan melalui `jalur_pengajuan = pic`, When aktor sama dengan `rencana_aksi_versi.diajukan_by` mencoba **memverifikasi**, Then F1 menolak walaupun aktor kemudian memperoleh role/grant lain.
- [ ] **AC-2:** Given aktor berbeda dan memiliki permission efektif, When verifikasi dilakukan, Then status menjadi `diverifikasi` tanpa mengubah payload versi.
- [ ] **AC-3:** Given perbaikan dibutuhkan, When dikembalikan dengan alasan, Then status menjadi `dikembalikan`, audit mencatat alasan, dan PIC menerima notifikasi.
- [ ] **AC-4:** Given pengembalian tanpa alasan, When submit, Then ditolak.
- [ ] **AC-5:** Given data substansi perlu diubah saat review, Then data harus dikembalikan lalu diajukan sebagai versi baru; snapshot versi lama tidak ditambal.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `rencana_aksi`, versi terkait, `audit_log`, notifikasi.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] F1 wajib diterapkan pada **verifikasi**: pengaju versi jalur PIC tidak boleh memverifikasi versi yang diajukan sendiri.
- [ ] Perubahan substansi saat review harus melalui kembalikan → edit → submit versi baru; jangan menambal snapshot versi.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `rencana_aksi:verifikasi`, `rencana_aksi:kembalikan`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Verifikasi & Pengembalian Rencana Aksi dengan F1**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given versi diajukan melalui `jalur_pengajuan = pic`, When aktor sama dengan `rencana_aksi_versi.diajukan_by` mencoba **memverifikasi**, Then F1 menolak walaupun aktor kemudian memperoleh role/grant lain.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given aktor berbeda dan memiliki permission efektif, When verifikasi dilakukan, Then status menjadi `diverifikasi` tanpa mengubah payload versi.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given perbaikan dibutuhkan, When dikembalikan dengan alasan, Then status menjadi `dikembalikan`, audit mencatat alasan, dan PIC menerima notifikasi.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given pengembalian tanpa alasan, When submit, Then ditolak.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given data substansi perlu diubah saat review, Then data harus dikembalikan lalu diajukan sebagai versi baru; snapshot versi lama tidak ditambal.
- [ ] REGRESSION: Uji pembuat draft berbeda dari pengaju; perubahan role/PIC/grant setelah submit tidak mengubah provenance atau meloloskan F1.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-05.05 · [Feature] Pengesahan Rencana Aksi & Pemisahan Tugas F1/F2

**Terkait User Story:** `US-05.05`  
**Prioritas:** 🔴 P0  
**Story Points:** 8  
**Labels:** `feature`, `rencana-aksi`, `P0`, `audit-sensitive`, `frontend`  
**Plan:** Modul 11: 11.3  
**PRD:** §7.6, §14.6  
**Workflow:** §7, §20  
**Data Model/Entitas:** RA + versi

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengesahkan versi RA yang telah diverifikasi,  
> **Sehingga** RA menjadi dokumen operasional resmi dengan provenance yang dapat diaudit.

#### Kontrak Teknis

- **Dependensi:** RA berstatus `diverifikasi`; versi yang direviu adalah versi terbaru dan tidak stale.
- **Otorisasi:** `rencana_aksi:sahkan` (sensitif).
- **Dampak Data:** `rencana_aksi`, `rencana_aksi_versi`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given versi diajukan melalui jalur PIC, When aktor sama dengan `diajukan_by` mencoba mengesahkan, Then **F1 menolak**.
- [ ] **AC-2:** Given versi diajukan oleh PIC dan aktor Perencanaan lain berizin, When sahkan, Then header menjadi `disahkan` dan metadata pengesahan pada versi terisi atomik.
- [ ] **AC-3:** Given versi diajukan melalui `jalur_pengajuan = perencanaan`, When pengaju yang sama memverifikasi/mengesahkan dan masih memiliki permission efektif, Then **F2 mengizinkan** serta mencatat `self_approval`.
- [ ] **AC-4:** Given terdapat deny atau permission reviewer hilang, When F2 dicoba, Then tetap ditolak; F2 bukan bypass resolver.
- [ ] **AC-5:** Given versi yang hendak disahkan bukan versi terbaru yang sedang direviu, When aksi dilakukan, Then ditolak untuk mencegah pengesahan payload stale.
- [ ] **AC-6:** Given RA disahkan, Then bukti yang dirujuk versi resmi tidak boleh dihapus.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `rencana_aksi`, `rencana_aksi_versi`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Pastikan `rencana_aksi_versi` immutable setelah INSERT; submit ulang membuat versi baru, bukan overwrite snapshot lama.

**B. Backend / Domain**
- [ ] F1/F2 harus membaca `rencana_aksi_versi.diajukan_by` dan `jalur_pengajuan`; perubahan role/PIC/grant setelah submit tidak mengubah provenance.
- [ ] Pengesahan wajib mengunci versi terbaru yang sedang direviu dan menolak versi stale.
- [ ] F2 self-approval jalur Perencanaan tetap wajib lolos resolver permission/deny dan dicatat sebagai `self_approval`.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `rencana_aksi:sahkan` (sensitif).
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pengesahan Rencana Aksi & Pemisahan Tugas F1/F2**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given versi diajukan melalui jalur PIC, When aktor sama dengan `diajukan_by` mencoba mengesahkan, Then **F1 menolak**.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given versi diajukan oleh PIC dan aktor Perencanaan lain berizin, When sahkan, Then header menjadi `disahkan` dan metadata pengesahan pada versi terisi atomik.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given versi diajukan melalui `jalur_pengajuan = perencanaan`, When pengaju yang sama memverifikasi/mengesahkan dan masih memiliki permission efektif, Then **F2 mengizinkan** serta mencatat `self_approval`.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given terdapat deny atau permission reviewer hilang, When F2 dicoba, Then tetap ditolak; F2 bukan bypass resolver.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given versi yang hendak disahkan bukan versi terbaru yang sedang direviu, When aksi dilakukan, Then ditolak untuk mencegah pengesahan payload stale.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given RA disahkan, Then bukti yang dirujuk versi resmi tidak boleh dihapus.
- [ ] REGRESSION: Uji pembuat draft berbeda dari pengaju; perubahan role/PIC/grant setelah submit tidak mengubah provenance atau meloloskan F1.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-05.06 · [Feature] Buka-Kembali Rencana Aksi yang Telah Disahkan

**Terkait User Story:** `US-05.06`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `rencana-aksi`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 11: 11.7  
**PRD:** §14.6  
**Workflow:** §7, §13  
**Data Model/Entitas:** RA + versi

#### User Story

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** membuka RA disahkan kembali ke jalur revisi dengan alasan wajib,  
> **Sehingga** koreksi resmi dapat dilakukan tanpa menghapus versi yang sudah pernah disahkan.

#### Kontrak Teknis

- **Dependensi:** RA disahkan; sebelum penutupan atau di dalam sesi koreksi yang sah.
- **Otorisasi:** `rencana_aksi:buka_kembali` (sensitif).
- **Dampak Data:** `rencana_aksi`, versi historis, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given alasan valid dan waktu koreksi sah, When buka kembali dilakukan, Then header berpindah ke `dikembalikan`.
- [ ] **AC-2:** Given versi lama telah disahkan, When RA dibuka kembali, Then versi lama tetap immutable dan tetap dapat dibaca sebagai histori.
- [ ] **AC-3:** Given PIC perlu memperbaiki, Then PIC hanya dapat bekerja bila jendela PIC resmi masih/baru dibuka dan grant + PIC efektif valid.
- [ ] **AC-4:** Given jadwal ditutup tanpa sesi koreksi, When buka kembali RA langsung dicoba, Then ditolak.
- [ ] **AC-5:** Given RA diajukan ulang setelah perbaikan, Then baris `rencana_aksi_versi` baru dibuat.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `rencana_aksi`, versi historis, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Pastikan `rencana_aksi_versi` immutable setelah INSERT; submit ulang membuat versi baru, bukan overwrite snapshot lama.

**B. Backend / Domain**
- [ ] Buka kembali hanya mengubah header ke jalur revisi; versi disahkan lama tetap immutable dan resmi sampai versi baru disahkan.
- [ ] PIC tetap tunduk jendela kerja resmi; buka RA tidak otomatis memperpanjang deadline.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `rencana_aksi:buka_kembali` (sensitif).
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Buka-Kembali Rencana Aksi yang Telah Disahkan**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given alasan valid dan waktu koreksi sah, When buka kembali dilakukan, Then header berpindah ke `dikembalikan`.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given versi lama telah disahkan, When RA dibuka kembali, Then versi lama tetap immutable dan tetap dapat dibaca sebagai histori.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given PIC perlu memperbaiki, Then PIC hanya dapat bekerja bila jendela PIC resmi masih/baru dibuka dan grant + PIC efektif valid.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given jadwal ditutup tanpa sesi koreksi, When buka kembali RA langsung dicoba, Then ditolak.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given RA diajukan ulang setelah perbaikan, Then baris `rencana_aksi_versi` baru dibuat.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.


---

## Bagian 6 — Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan

### ISS-06.01 · [Feature] Pencatatan Rencana Kegiatan Unit

**Terkait User Story:** `US-06.01`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `kegiatan`, `P1`, `frontend`  
**Plan:** Modul 12: 12.1–12.2  
**PRD:** §15.1–15.4  
**Workflow:** §8  
**Data Model/Entitas:** `kegiatan`

#### User Story

> **Sebagai** PIC/petugas unit berizin atau Tim Perencanaan,  
> **Saya ingin** mencatat kegiatan yang mendukung pencapaian kinerja,  
> **Sehingga** aktivitas operasional dapat ditelusuri per unit dan periode.

#### Kontrak Teknis

- **Dependensi:** Unit aktif dan periode valid.
- **Otorisasi:** `kegiatan:create`, `kegiatan:update` (unit-scoped bila melalui grant).
- **Dampak Data:** `kegiatan`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given nama, tujuan, sasaran peserta, lokasi, dan tanggal valid, When disimpan, Then kegiatan terbentuk dengan status `rencana`.
- [ ] **AC-2:** Given MVP, When form kegiatan ditampilkan, Then **field anggaran tidak ditampilkan, tidak diterima sebagai input bisnis, dan tidak divalidasi**; kolom database boleh tetap nullable untuk fase lanjutan.
- [ ] **AC-3:** Given pengguna unit memiliki grant `kegiatan:create/update`, When bekerja pada unit yang sama, Then aksi diizinkan tanpa syarat PIC indikator tertentu.
- [ ] **AC-4:** Given grant hanya unit A, When kegiatan unit B dimutasi, Then ditolak.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `kegiatan`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Jangan expose/accept/validate field anggaran pada UI/API MVP walaupun kolom database tersedia nullable untuk fase lanjut.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `kegiatan:create`, `kegiatan:update` (unit-scoped bila melalui grant).
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pencatatan Rencana Kegiatan Unit**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given nama, tujuan, sasaran peserta, lokasi, dan tanggal valid, When disimpan, Then kegiatan terbentuk dengan status `rencana`.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given MVP, When form kegiatan ditampilkan, Then **field anggaran tidak ditampilkan, tidak diterima sebagai input bisnis, dan tidak divalidasi**; kolom database boleh tetap nullable untuk fase lanjutan.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given pengguna unit memiliki grant `kegiatan:create/update`, When bekerja pada unit yang sama, Then aksi diizinkan tanpa syarat PIC indikator tertentu.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given grant hanya unit A, When kegiatan unit B dimutasi, Then ditolak.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-06.02 · [Feature] Klaim Keterkaitan Kegiatan terhadap Rencana Aksi/Komponen

**Terkait User Story:** `US-06.02`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `kegiatan`, `P1`, `frontend`  
**Plan:** Modul 12: 12.6–12.9  
**PRD:** §16  
**Workflow:** §9  
**Data Model/Entitas:** `klaim_kegiatan`

#### User Story

> **Sebagai** PIC/petugas unit berizin atau Tim Perencanaan,  
> **Saya ingin** menghubungkan kegiatan ke RA dan opsional ke komponen indikator,  
> **Sehingga** laporan dapat menjelaskan kontribusi kegiatan tanpa mengubah angka capaian secara otomatis.

#### Kontrak Teknis

- **Dependensi:** Kegiatan dan RA tersedia pada konteks tahun/unit yang konsisten.
- **Otorisasi:** `kegiatan:update`/capability sumber dan kewenangan RA sesuai alur.
- **Dampak Data:** `klaim_kegiatan`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given kegiatan, RA, komponen opsional, arah dampak, dan catatan valid, When disimpan, Then klaim terbentuk.
- [ ] **AC-2:** Given unit kegiatan berbeda dengan unit RA, When klaim dibuat, Then ditolak.
- [ ] **AC-3:** Given klaim identik sudah ada, When dibuat ulang, Then duplikasi ditolak.
- [ ] **AC-4:** Given klaim berhasil, Then nilai komponen/pengukuran **tidak** berubah otomatis.
- [ ] **AC-5:** Given kegiatan batal/tidak terlaksana, Then klaim historis tetap dapat dipertahankan untuk menjelaskan kendala.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `klaim_kegiatan`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Klaim Keterkaitan Kegiatan terhadap Rencana Aksi/Komponen** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `kegiatan:update`/capability sumber dan kewenangan RA sesuai alur.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Klaim Keterkaitan Kegiatan terhadap Rencana Aksi/Komponen**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan, RA, komponen opsional, arah dampak, dan catatan valid, When disimpan, Then klaim terbentuk.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given unit kegiatan berbeda dengan unit RA, When klaim dibuat, Then ditolak.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given klaim identik sudah ada, When dibuat ulang, Then duplikasi ditolak.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given klaim berhasil, Then nilai komponen/pengukuran **tidak** berubah otomatis.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan batal/tidak terlaksana, Then klaim historis tetap dapat dipertahankan untuk menjelaskan kendala.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-06.03 · [Feature] Pelaksanaan Kegiatan & Gerbang Bukti Gabungan

**Terkait User Story:** `US-06.03`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `kegiatan`, `P1`, `frontend`  
**Plan:** Modul 12: 12.11; Modul 13  
**PRD:** §15.6, §18  
**Workflow:** §8–10  
**Data Model/Entitas:** kegiatan + bukti

#### User Story

> **Sebagai** PIC/petugas unit berizin,  
> **Saya ingin** menyelesaikan kegiatan setelah seluruh bukti wajib yang relevan terpenuhi,  
> **Sehingga** status terlaksana hanya diberikan pada kegiatan yang dapat dipertanggungjawabkan.

#### Kontrak Teknis

- **Dependensi:** Kegiatan berstatus `rencana`; persyaratan global dan indikator klaim dapat dihitung.
- **Otorisasi:** `kegiatan:update` + capability bukti induk.
- **Dampak Data:** `kegiatan`, `klaim_kegiatan`, `jenis_berkas`, `berkas`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given kegiatan hendak menjadi `terlaksana`, When gerbang dievaluasi, Then persyaratan bukti = **union persyaratan global kegiatan + persyaratan indikator yang diklaim**.
- [ ] **AC-2:** Given salah satu bukti wajib pada union belum terpenuhi, When status terlaksana disubmit, Then ditolak.
- [ ] **AC-3:** Given tanggal realisasi, peserta riil, uraian pelaksanaan, kendala, strategi tindak lanjut, dan bukti lengkap, When commit, Then status menjadi `terlaksana`.
- [ ] **AC-4:** Given klaim indikator baru ditambahkan setelah kegiatan sudah terlaksana, When klaim menambah persyaratan bukti, Then sistem kembali memeriksa bukti tambahan dan menandai kebutuhan koreksi bila belum lengkap.
- [ ] **AC-5:** Given kegiatan telah terlaksana, Then bukti yang menjadi dasar hasil tidak dapat dihapus; koreksi memakai pola append-only.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `kegiatan`, `klaim_kegiatan`, `jenis_berkas`, `berkas`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Gerbang status `terlaksana` harus menghitung union persyaratan global kegiatan dan persyaratan semua indikator yang diklaim.
- [ ] Klaim baru setelah kegiatan terlaksana harus memicu evaluasi bukti tambahan.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `kegiatan:update` + capability bukti induk.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pelaksanaan Kegiatan & Gerbang Bukti Gabungan**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan hendak menjadi `terlaksana`, When gerbang dievaluasi, Then persyaratan bukti = **union persyaratan global kegiatan + persyaratan indikator yang diklaim**.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given salah satu bukti wajib pada union belum terpenuhi, When status terlaksana disubmit, Then ditolak.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given tanggal realisasi, peserta riil, uraian pelaksanaan, kendala, strategi tindak lanjut, dan bukti lengkap, When commit, Then status menjadi `terlaksana`.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given klaim indikator baru ditambahkan setelah kegiatan sudah terlaksana, When klaim menambah persyaratan bukti, Then sistem kembali memeriksa bukti tambahan dan menandai kebutuhan koreksi bila belum lengkap.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan telah terlaksana, Then bukti yang menjadi dasar hasil tidak dapat dihapus; koreksi memakai pola append-only.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-06.04 · [Feature] Kegiatan Tidak Terlaksana, Ditunda, atau Dibatalkan

**Terkait User Story:** `US-06.04`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `kegiatan`, `P1`, `frontend`  
**Plan:** Modul 12: 12.3  
**PRD:** §15.2–15.5  
**Workflow:** §8  
**Data Model/Entitas:** `kegiatan`

#### User Story

> **Sebagai** PIC/petugas unit berizin,  
> **Saya ingin** mencatat kegagalan/penundaan kegiatan beserta justifikasi,  
> **Sehingga** kendala operasional tetap terlihat tanpa menghapus kegiatan.

#### Kontrak Teknis

- **Dependensi:** Kegiatan masih dapat ditransisikan.
- **Otorisasi:** `kegiatan:update`.
- **Dampak Data:** `kegiatan`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given status diubah menjadi `tidak_terlaksana`, `ditunda`, atau `batal`, When submit, Then justifikasi wajib.
- [ ] **AC-2:** Given justifikasi kosong, When submit, Then ditolak.
- [ ] **AC-3:** Given status bukan `terlaksana`, Then bukti SPJ pelaksanaan tidak diwajibkan semata-mata untuk transisi tersebut.
- [ ] **AC-4:** Given kegiatan telah diklaim, When laporan dibentuk, Then narasi kegagalan/penundaan tetap dapat muncul sebagai konteks.
- [ ] **AC-5:** Given perubahan status dilakukan, Then audit mencatat nilai lama/baru dan alasan.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `kegiatan`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Kegiatan Tidak Terlaksana, Ditunda, atau Dibatalkan** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `kegiatan:update`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Kegiatan Tidak Terlaksana, Ditunda, atau Dibatalkan**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given status diubah menjadi `tidak_terlaksana`, `ditunda`, atau `batal`, When submit, Then justifikasi wajib.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given justifikasi kosong, When submit, Then ditolak.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given status bukan `terlaksana`, Then bukti SPJ pelaksanaan tidak diwajibkan semata-mata untuk transisi tersebut.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan telah diklaim, When laporan dibentuk, Then narasi kegagalan/penundaan tetap dapat muncul sebagai konteks.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given perubahan status dilakukan, Then audit mencatat nilai lama/baru dan alasan.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-06.05 · [Feature] Roll-Over Kegiatan ke Periode Berikutnya

**Terkait User Story:** `US-06.05`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `kegiatan`, `P1`, `frontend`  
**Plan:** Modul 12: 12.4  
**PRD:** §15.4  
**Workflow:** §8  
**Data Model/Entitas:** `kegiatan_asal_id`

#### User Story

> **Sebagai** PIC/petugas unit berizin,  
> **Saya ingin** menggeser kegiatan ditunda ke periode berikutnya tanpa menghapus kegiatan asal,  
> **Sehingga** jejak penundaan antarperiode dapat ditelusuri.

#### Kontrak Teknis

- **Dependensi:** Kegiatan asal berstatus `ditunda`; periode tujuan valid.
- **Otorisasi:** `kegiatan:create`.
- **Dampak Data:** `kegiatan`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given kegiatan ditunda, When roll-over dibuat ke periode tujuan, Then baris kegiatan baru dibuat dengan `kegiatan_asal_id` menunjuk kegiatan lama.
- [ ] **AC-2:** Given baris baru dibuat, Then kegiatan asal tetap `ditunda` dan tidak dihapus.
- [ ] **AC-3:** Given periode tujuan tidak valid/lebih awal, When roll-over dicoba, Then ditolak.
- [ ] **AC-4:** Given roll-over selesai, Then audit menyimpan hubungan asal-tujuan.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `kegiatan`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Roll-Over Kegiatan ke Periode Berikutnya** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `kegiatan:create`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Roll-Over Kegiatan ke Periode Berikutnya**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan ditunda, When roll-over dibuat ke periode tujuan, Then baris kegiatan baru dibuat dengan `kegiatan_asal_id` menunjuk kegiatan lama.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given baris baru dibuat, Then kegiatan asal tetap `ditunda` dan tidak dihapus.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given periode tujuan tidak valid/lebih awal, When roll-over dicoba, Then ditolak.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given roll-over selesai, Then audit menyimpan hubungan asal-tujuan.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-06.06 · [Feature] Koreksi Klaim Kegiatan Berdasarkan Sumber Versi

**Terkait User Story:** `US-06.06`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `kegiatan`, `P1`, `backend`  
**Plan:** Modul 12: 12.10  
**PRD:** §16.5  
**Workflow:** §9, §13  
**Data Model/Entitas:** klaim + versi

#### User Story

> **Sebagai** Tim Perencanaan/PIC sesuai sumber klaim,  
> **Saya ingin** mengoreksi klaim tanpa mengubah versi historis yang sudah diajukan/disahkan,  
> **Sehingga** laporan lama tetap merepresentasikan klaim yang dahulu diperiksa.

#### Kontrak Teknis

- **Dependensi:** Klaim telah digunakan dalam proses RA/Pengukuran.
- **Otorisasi:** Kewenangan mengikuti sumber yang sedang dikoreksi.
- **Dampak Data:** `klaim_kegiatan`, versi RA/Pengukuran, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given klaim menjadi bagian snapshot RA, When koreksi dilakukan, Then koreksi mengikuti siklus RA dan baru masuk versi RA berikutnya.
- [ ] **AC-2:** Given klaim menjadi bagian snapshot Pengukuran, When koreksi dilakukan, Then koreksi mengikuti siklus Pengukuran terkait dan baru masuk versi berikutnya.
- [ ] **AC-3:** Given versi historis sudah disahkan, Then klaim yang dibekukan di versi tersebut tidak berubah.
- [ ] **AC-4:** Given klaim dihapus/diganti pada data kerja, Then audit tetap mempertahankan jejak perubahan.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `klaim_kegiatan`, versi RA/Pengukuran, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Koreksi klaim harus mengikuti siklus versi sumber (RA atau Pengukuran) dan tidak mengubah snapshot versi historis.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: Kewenangan mengikuti sumber yang sedang dikoreksi.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given klaim menjadi bagian snapshot RA, When koreksi dilakukan, Then koreksi mengikuti siklus RA dan baru masuk versi RA berikutnya.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given klaim menjadi bagian snapshot Pengukuran, When koreksi dilakukan, Then koreksi mengikuti siklus Pengukuran terkait dan baru masuk versi berikutnya.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given versi historis sudah disahkan, Then klaim yang dibekukan di versi tersebut tidak berubah.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given klaim dihapus/diganti pada data kerja, Then audit tetap mempertahankan jejak perubahan.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.

### ISS-06.07 · [Feature] Koreksi Bukti Kegiatan secara Append-Only

**Terkait User Story:** `US-06.07`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `kegiatan`, `P1`, `backend`  
**Plan:** Modul 13: 13.6  
**PRD:** §18.8  
**Workflow:** §10  
**Data Model/Entitas:** `berkas` append-only

#### User Story

> **Sebagai** PIC atau Tim Perencanaan yang berwenang,  
> **Saya ingin** mengganti bukti kegiatan melalui bukti baru tanpa menghapus bukti lama,  
> **Sehingga** auditor dapat melihat bukti awal dan bukti koreksi beserta alasannya.

#### Kontrak Teknis

- **Dependensi:** Kegiatan telah memiliki bukti yang sudah digunakan/terkunci.
- **Otorisasi:** Capability bukti induk + aturan koreksi.
- **Dampak Data:** `berkas`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given bukti kegiatan perlu dikoreksi setelah mencapai batas imutabilitas, When koreksi sah dilakukan, Then bukti baru ditambahkan dengan alasan dan hubungan pengganti; bukti lama tetap utuh.
- [ ] **AC-2:** Given bukti lama telah dirujuk versi resmi, Then tidak tersedia rollback status untuk menghapusnya.
- [ ] **AC-3:** Given koreksi belum disahkan dalam versi baru, Then laporan resmi tetap membaca bukti versi lama.
- [ ] **AC-4:** Given koreksi menjadi dasar pengajuan baru, Then metadata bukti baru dibekukan pada versi pengajuan baru.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `berkas`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Koreksi bukti terkunci menggunakan append-only replacement; tidak ada endpoint yang menghapus bukti lama untuk memalsukan histori.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: Capability bukti induk + aturan koreksi.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given bukti kegiatan perlu dikoreksi setelah mencapai batas imutabilitas, When koreksi sah dilakukan, Then bukti baru ditambahkan dengan alasan dan hubungan pengganti; bukti lama tetap utuh.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given bukti lama telah dirujuk versi resmi, Then tidak tersedia rollback status untuk menghapusnya.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given koreksi belum disahkan dalam versi baru, Then laporan resmi tetap membaca bukti versi lama.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given koreksi menjadi dasar pengajuan baru, Then metadata bukti baru dibekukan pada versi pengajuan baru.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.


---

## Bagian 7 — Pengisian Pengukuran Kinerja

### ISS-07.01 · [Feature] Pengisian Nilai Realisasi Komponen/Manual

**Terkait User Story:** `US-07.01`  
**Prioritas:** 🔴 P0  
**Story Points:** 8  
**Labels:** `feature`, `pengukuran`, `P0`, `frontend`  
**Plan:** Modul 5: 5.2–5.4, 5.13–5.15  
**PRD:** §17, §19–20  
**Workflow:** §11  
**Data Model/Entitas:** pengukuran + komponen

#### User Story

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** mengisi realisasi sesuai tipe perhitungan snapshot,  
> **Sehingga** nilai capaian dihitung konsisten dengan definisi yang dibekukan.

#### Kontrak Teknis

- **Dependensi:** Periode berlaku dan jendela pengisian sah; snapshot indikator tersedia; PIC grant + PIC efektif valid atau Perencanaan global.
- **Otorisasi:** `pengukuran:create`, `pengukuran:update`.
- **Dampak Data:** `pengukuran`, `pengukuran_komponen`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given indikator nonmanual, When semua komponen diisi, Then server menghitung `nilai` dari snapshot dan UI menampilkan nilai turunan read-only.
- [ ] **AC-2:** Given indikator manual, When nilai diisi, Then nilai langsung tersimpan sebagai sumber `manual`.
- [ ] **AC-3:** Given nilai 0, When disimpan, Then 0 diperlakukan sebagai nilai sah dan berbeda dari NULL.
- [ ] **AC-4:** Given periode sebelum `periode_mulai_id`, When pengukuran dibuat, Then ditolak sebagai Tidak berlaku.
- [ ] **AC-5:** Given indikator master sudah arsip, When pengukuran baru dibuat, Then create ditolak.
- [ ] **AC-6:** Given rasio antarperiode, Then sistem tidak menjumlahkan persentase; realisasi memakai basis waktu/populasi yang sebanding dengan target kumulatif.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `pengukuran`, `pengukuran_komponen`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Hitung nilai nonmanual server-side dari komponen **snapshot**, bukan master live; manual memakai nilai langsung.
- [ ] Terapkan 0 ≠ NULL, larangan create pada indikator arsip, masa berlaku periode, dan basis kumulatif yang sebanding.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengukuran:create`, `pengukuran:update`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pengisian Nilai Realisasi Komponen/Manual**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given indikator nonmanual, When semua komponen diisi, Then server menghitung `nilai` dari snapshot dan UI menampilkan nilai turunan read-only.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given indikator manual, When nilai diisi, Then nilai langsung tersimpan sebagai sumber `manual`.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given nilai 0, When disimpan, Then 0 diperlakukan sebagai nilai sah dan berbeda dari NULL.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given periode sebelum `periode_mulai_id`, When pengukuran dibuat, Then ditolak sebagai Tidak berlaku.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given indikator master sudah arsip, When pengukuran baru dibuat, Then create ditolak.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given rasio antarperiode, Then sistem tidak menjumlahkan persentase; realisasi memakai basis waktu/populasi yang sebanding dengan target kumulatif.
- [ ] SECURITY: Uji request langsung untuk scope unit lain dan deny yang cocok menghasilkan 403 meski tombol UI disembunyikan.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-07.02 · [Feature] Pemenuhan Bukti Dukung Pengukuran

**Terkait User Story:** `US-07.02`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `pengukuran`, `P1`, `frontend`  
**Plan:** Modul 13: 13.3–13.5  
**PRD:** §18  
**Workflow:** §10–11  
**Data Model/Entitas:** `berkas`

#### User Story

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** melampirkan file, tautan, atau teks sesuai persyaratan pengukuran,  
> **Sehingga** angka capaian memiliki sumber bukti yang dapat diverifikasi.

#### Kontrak Teknis

- **Dependensi:** Pengukuran dapat diedit; persyaratan bukti aktif.
- **Otorisasi:** Capability berkas mengikuti induk Pengukuran; deny tetap berlaku.
- **Dampak Data:** `berkas`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given mode diizinkan, When bukti disimpan, Then `berkas` terkait Pengukuran dan jenis persyaratan.
- [ ] **AC-2:** Given `semua_mode_wajib = true`, Then seluruh mode yang disyaratkan wajib terpenuhi.
- [ ] **AC-3:** Given file upload dimatikan dan persyaratan mengandung kewajiban file, Then hanya kewajiban **mode file** yang dapat dikecualikan sesuai kebijakan; mode tautan/teks lain tetap wajib bila dipersyaratkan.
- [ ] **AC-4:** Given bukti telah masuk versi submit, Then perubahan live tidak mengubah versi lama.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `berkas`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Pemenuhan Bukti Dukung Pengukuran** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: Capability berkas mengikuti induk Pengukuran; deny tetap berlaku.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pemenuhan Bukti Dukung Pengukuran**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given mode diizinkan, When bukti disimpan, Then `berkas` terkait Pengukuran dan jenis persyaratan.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given `semua_mode_wajib = true`, Then seluruh mode yang disyaratkan wajib terpenuhi.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given file upload dimatikan dan persyaratan mengandung kewajiban file, Then hanya kewajiban **mode file** yang dapat dikecualikan sesuai kebijakan; mode tautan/teks lain tetap wajib bila dipersyaratkan.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given bukti telah masuk versi submit, Then perubahan live tidak mengubah versi lama.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-07.03 · [Feature] Pengajuan Pengukuran & Pembekuan Versi

**Terkait User Story:** `US-07.03`  
**Prioritas:** 🔴 P0  
**Story Points:** 8  
**Labels:** `feature`, `pengukuran`, `P0`, `frontend`  
**Plan:** Modul 5: 5.5, 5.16  
**PRD:** §18.11, §19.4  
**Workflow:** §11–12, §20  
**Data Model/Entitas:** `pengukuran_versi`

#### User Story

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** mengajukan pengukuran sebagai versi immutable untuk direviu,  
> **Sehingga** angka, target pembanding, bukti, dan provenance tidak berubah selama proses review.

#### Kontrak Teknis

- **Dependensi:** Pengukuran draft/dikembalikan; jendela aktor sah; RA resmi tersedia pada jalur normal.
- **Otorisasi:** `pengukuran:update` untuk submit sesuai scope.
- **Dampak Data:** `pengukuran`, `pengukuran_versi`, `audit_log`, notifikasi.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given jalur normal, When submit, Then RA indikator-tahun harus memiliki versi yang telah disahkan.
- [ ] **AC-2:** Given nonmanual dan input wajib belum lengkap, When submit, Then ditolak; pengecualian `tidak_dapat_dihitung` hanya berlaku untuk penyebut efektif nol dengan komponen lengkap dan alasan.
- [ ] **AC-3:** Given bukti wajib menurut persyaratan saat submit belum terpenuhi, When submit, Then ditolak.
- [ ] **AC-4:** Given nilai memburuk menurut arah dibanding versi disahkan terakhir atau indikator `wajib_catatan`, When catatan kosong, Then submit ditolak.
- [ ] **AC-5:** Given seluruh gerbang lolos, Then header menjadi `diajukan` dan **`pengukuran_versi` baru** dibuat dengan `diajukan_by`, `diajukan_at`, `jalur_pengajuan`, `dasar_izin_pengajuan`, nilai/komponen, status perhitungan, snapshot target PK/RA, bukti, klaim/narasi, dan persyaratan.
- [ ] **AC-6:** Given A membuat draft dan B melakukan submit, Then `diajukan_by = B`; F1/F2 tidak menggunakan `created_by`.
- [ ] **AC-7:** Given submit berhasil, Then notifikasi antrean Perencanaan dibuat setelah commit.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `pengukuran`, `pengukuran_versi`, `audit_log`, notifikasi.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Pastikan `pengukuran_versi`/referensi `pengukuran_versi_id` konsisten; versi resmi lama tetap tersimpan ketika koreksi dibuat.

**B. Backend / Domain**
- [ ] Submit Pengukuran harus atomik: validasi RA versi disahkan, input/status perhitungan, bukti versi, catatan wajib, scope/PIC/waktu, lalu insert `pengukuran_versi`.
- [ ] Bekukan provenance (`diajukan_by`, `jalur_pengajuan`, `dasar_izin_pengajuan`) serta target PK/RA, komponen/nilai, bukti, klaim/narasi, dan persyaratan.
- [ ] `created_by` tidak boleh dipakai sebagai identitas pengaju F1/F2.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengukuran:update` untuk submit sesuai scope.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pengajuan Pengukuran & Pembekuan Versi**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given jalur normal, When submit, Then RA indikator-tahun harus memiliki versi yang telah disahkan.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given nonmanual dan input wajib belum lengkap, When submit, Then ditolak; pengecualian `tidak_dapat_dihitung` hanya berlaku untuk penyebut efektif nol dengan komponen lengkap dan alasan.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given bukti wajib menurut persyaratan saat submit belum terpenuhi, When submit, Then ditolak.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given nilai memburuk menurut arah dibanding versi disahkan terakhir atau indikator `wajib_catatan`, When catatan kosong, Then submit ditolak.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given seluruh gerbang lolos, Then header menjadi `diajukan` dan **`pengukuran_versi` baru** dibuat dengan `diajukan_by`, `diajukan_at`, `jalur_pengajuan`, `dasar_izin_pengajuan`, nilai/komponen, status perhitungan, snapshot target PK/RA, bukti, klaim/narasi, dan persyaratan.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given A membuat draft dan B melakukan submit, Then `diajukan_by = B`; F1/F2 tidak menggunakan `created_by`.
- [ ] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given submit berhasil, Then notifikasi antrean Perencanaan dibuat setelah commit.
- [ ] REGRESSION: Uji pembuat draft berbeda dari pengaju; perubahan role/PIC/grant setelah submit tidak mengubah provenance atau meloloskan F1.
- [ ] CONCURRENCY: Uji transaksi/optimistic locking/versi stale sesuai kontrak Data Model.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-07.04 · [Feature] Pengisian oleh Perencanaan di Luar Deadline PIC

**Terkait User Story:** `US-07.04`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `pengukuran`, `P1`, `frontend`  
**Plan:** Modul 5: 5.4–5.8  
**PRD:** §19.3  
**Workflow:** §11–12  
**Data Model/Entitas:** pengukuran

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengisi atau mengoreksi pengukuran setelah deadline PIC,  
> **Sehingga** pelaporan tidak macet ketika unit terlambat, tanpa melemahkan gerbang kualitas.

#### Kontrak Teknis

- **Dependensi:** Jadwal belum ditutup atau berada pada sesi koreksi yang sah.
- **Otorisasi:** `pengukuran:create`, `pengukuran:update` global melalui role Perencanaan.
- **Dampak Data:** `pengukuran`, versi, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given tanggal melewati `pengisian_selesai`, When PIC mencoba mengubah/submit, Then ditolak kecuali jendela PIC resmi dibuka ulang.
- [ ] **AC-2:** Given Perencanaan bekerja setelah deadline PIC tetapi sebelum penutupan/jendela koreksi sah, When mengisi data, Then diizinkan melalui izin global.
- [ ] **AC-3:** Given Perencanaan submit, Then seluruh gerbang kelengkapan normal tetap berlaku kecuali jalur backfill eksplisit.
- [ ] **AC-4:** Given submit oleh Perencanaan, Then `jalur_pengajuan = perencanaan` dibekukan pada versi untuk F2.
- [ ] **AC-5:** Given reviu melewati `reviu_selesai`, Then proses masih dapat berlangsung sampai penutupan dengan penanda terlambat.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `pengukuran`, versi, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Pengecualian Perencanaan hanya pada deadline PIC; kelengkapan, status, izin, deny, versioning, dan audit tetap berlaku.
- [ ] Reviu setelah `reviu_selesai` sampai penutupan diberi penanda terlambat, bukan ditolak semata-mata oleh deadline review.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengukuran:create`, `pengukuran:update` global melalui role Perencanaan.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pengisian oleh Perencanaan di Luar Deadline PIC**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given tanggal melewati `pengisian_selesai`, When PIC mencoba mengubah/submit, Then ditolak kecuali jendela PIC resmi dibuka ulang.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given Perencanaan bekerja setelah deadline PIC tetapi sebelum penutupan/jendela koreksi sah, When mengisi data, Then diizinkan melalui izin global.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given Perencanaan submit, Then seluruh gerbang kelengkapan normal tetap berlaku kecuali jalur backfill eksplisit.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given submit oleh Perencanaan, Then `jalur_pengajuan = perencanaan` dibekukan pada versi untuk F2.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given reviu melewati `reviu_selesai`, Then proses masih dapat berlangsung sampai penutupan dengan penanda terlambat.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-07.05 · [Feature] Pengisian Nilai Periode Lampau oleh Perencanaan

**Terkait User Story:** `US-07.05`  
**Prioritas:** 🟡 P1  
**Story Points:** 5

#### Scope

Mencatat pengukuran TW I–II 2026 melalui jalur Perencanaan karena jendela normal telah lewat. Tidak membangun tipe pengukuran/backfill khusus.

#### Acceptance Criteria

- [ ] Pengisian periode lampau menggunakan data Jadwal/Snapshot yang sah.
- [ ] Tidak ada flag backfill pada Pengukuran.
- [ ] 66,395 dan 87,08 tidak digunakan sebagai realisasi IKU 3 TW II.
- [ ] Audit merekam aktor dan dasar izin.

### ISS-07.06 · [Feature] Penanganan Penyebut Nol — Tidak Dapat Dihitung

**Terkait User Story:** `US-07.06`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `pengukuran`, `P1`, `frontend`  
**Plan:** Modul 5: 5.13–5.16  
**PRD:** §17.4, §19.4  
**Workflow:** §11  
**Data Model/Entitas:** status perhitungan

#### User Story

> **Sebagai** PIC atau Tim Perencanaan,  
> **Saya ingin** mengajukan kondisi penyebut nol tanpa menyamakan dengan belum diisi atau nilai nol,  
> **Sehingga** laporan membedakan data tidak terhitung secara matematis dari ketidaklengkapan input.

#### Kontrak Teknis

- **Dependensi:** Indikator rasio; semua komponen terisi; penyebut efektif = 0.
- **Otorisasi:** `pengukuran:update` sesuai scope.
- **Dampak Data:** `pengukuran`, `pengukuran_komponen`, versi, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given seluruh komponen lengkap dan penyebut = 0, When dihitung, Then `nilai = NULL` dan `status_perhitungan = tidak_dapat_dihitung`.
- [ ] **AC-2:** Given status tersebut akan disubmit, Then `alasan_tidak_dapat_dihitung` wajib.
- [ ] **AC-3:** Given komponen belum lengkap, Then status tetap `belum_diisi` dan tidak boleh menggunakan pengecualian penyebut nol.
- [ ] **AC-4:** Given nilai faktual 0 pada indikator yang dapat dihitung, Then 0 tetap `terhitung` dan bukan NULL.
- [ ] **AC-5:** Given versi disahkan, Then laporan menampilkan penanda tidak dapat dihitung beserta alasan yang sesuai hak baca.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `pengukuran`, `pengukuran_komponen`, versi, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Bedakan `belum_diisi`, `terhitung`, dan `tidak_dapat_dihitung`; hanya komponen lengkap + penyebut efektif nol yang boleh masuk status tidak dapat dihitung.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengukuran:update` sesuai scope.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Penanganan Penyebut Nol — Tidak Dapat Dihitung**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given seluruh komponen lengkap dan penyebut = 0, When dihitung, Then `nilai = NULL` dan `status_perhitungan = tidak_dapat_dihitung`.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given status tersebut akan disubmit, Then `alasan_tidak_dapat_dihitung` wajib.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given komponen belum lengkap, Then status tetap `belum_diisi` dan tidak boleh menggunakan pengecualian penyebut nol.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given nilai faktual 0 pada indikator yang dapat dihitung, Then 0 tetap `terhitung` dan bukan NULL.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given versi disahkan, Then laporan menampilkan penanda tidak dapat dihitung beserta alasan yang sesuai hak baca.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).


---

## Bagian 8 — Reviu, Verifikasi, Pengesahan, dan Koreksi Pengukuran

### ISS-08.01 · [Feature] Verifikasi & Pengembalian Pengukuran dengan F1

**Terkait User Story:** `US-08.01`  
**Prioritas:** 🔴 P0  
**Story Points:** 8  
**Labels:** `feature`, `review-approval`, `P0`, `audit-sensitive`, `frontend`  
**Plan:** Modul 5: 5.6–5.8; Modul 6: 6.1  
**PRD:** §7.6, §19  
**Workflow:** §12, §20  
**Data Model/Entitas:** pengukuran + versi

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** memeriksa nilai dan bukti lalu memverifikasi atau mengembalikan,  
> **Sehingga** angka resmi melewati review independen pada jalur PIC.

#### Kontrak Teknis

- **Dependensi:** Pengukuran berstatus `diajukan`; versi terbaru tersedia.
- **Otorisasi:** `pengukuran:verifikasi`, `pengukuran:kembalikan`.
- **Dampak Data:** `pengukuran`, `pengukuran_versi`, `audit_log`, notifikasi.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given versi diajukan melalui jalur PIC, When aktor sama dengan `pengukuran_versi.diajukan_by` mencoba **memverifikasi**, Then F1 menolak.
- [ ] **AC-2:** Given reviewer berbeda dan berizin, When verifikasi dilakukan, Then status menjadi `diverifikasi` tanpa mengubah snapshot versi.
- [ ] **AC-3:** Given kesalahan ditemukan pada status `diajukan` atau `diverifikasi`, When dikembalikan dengan alasan, Then status menjadi `dikembalikan` dan notifikasi dikirim setelah commit.
- [ ] **AC-4:** Given pengembalian tanpa alasan, Then ditolak.
- [ ] **AC-5:** Given tanggal melewati `reviu_selesai` tetapi belum penutupan, When Perencanaan mereviu, Then aksi tetap dapat dilakukan dan diberi penanda reviu terlambat.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `pengukuran`, `pengukuran_versi`, `audit_log`, notifikasi.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] F1 wajib diterapkan pada **verifikasi** berdasarkan `pengukuran_versi.diajukan_by` untuk jalur PIC.
- [ ] Kembalikan dari Diajukan/Diverifikasi memerlukan alasan dan tidak mengubah payload versi.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengukuran:verifikasi`, `pengukuran:kembalikan`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Verifikasi & Pengembalian Pengukuran dengan F1**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given versi diajukan melalui jalur PIC, When aktor sama dengan `pengukuran_versi.diajukan_by` mencoba **memverifikasi**, Then F1 menolak.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given reviewer berbeda dan berizin, When verifikasi dilakukan, Then status menjadi `diverifikasi` tanpa mengubah snapshot versi.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given kesalahan ditemukan pada status `diajukan` atau `diverifikasi`, When dikembalikan dengan alasan, Then status menjadi `dikembalikan` dan notifikasi dikirim setelah commit.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given pengembalian tanpa alasan, Then ditolak.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given tanggal melewati `reviu_selesai` tetapi belum penutupan, When Perencanaan mereviu, Then aksi tetap dapat dilakukan dan diberi penanda reviu terlambat.
- [ ] REGRESSION: Uji pembuat draft berbeda dari pengaju; perubahan role/PIC/grant setelah submit tidak mengubah provenance atau meloloskan F1.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-08.02 · [Feature] Pengesahan Pengukuran & F1/F2

**Terkait User Story:** `US-08.02`  
**Prioritas:** 🔴 P0  
**Story Points:** 8  
**Labels:** `feature`, `review-approval`, `P0`, `audit-sensitive`, `frontend`  
**Plan:** Modul 5: 5.9; Modul 6: 6.2  
**PRD:** §7.6, §19  
**Workflow:** §12, §20  
**Data Model/Entitas:** pengukuran + versi

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengesahkan versi pengukuran,  
> **Sehingga** nilai realisasi menjadi angka resmi organisasi dengan provenance yang tidak dapat ditulis ulang.

#### Kontrak Teknis

- **Dependensi:** Pengukuran `diverifikasi`; versi yang sama masih menjadi versi aktif untuk review.
- **Otorisasi:** `pengukuran:sahkan` (sensitif).
- **Dampak Data:** `pengukuran`, `pengukuran_versi`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given jalur pengajuan PIC, When aktor sama dengan `diajukan_by` mencoba sahkan, Then F1 menolak.
- [ ] **AC-2:** Given aktor lain berizin, When sahkan, Then status menjadi `disahkan` dan `disahkan_by/disahkan_at` versi terisi atomik.
- [ ] **AC-3:** Given jalur pengajuan Perencanaan, When pengaju yang sama mereviu/mengesahkan dan izin efektif masih ada, Then F2 mengizinkan dan mencatat `self_approval`.
- [ ] **AC-4:** Given deny/permission tidak memenuhi, Then F2 tidak dapat melewati resolver.
- [ ] **AC-5:** Given versi stale, When sahkan, Then ditolak.
- [ ] **AC-6:** Given pengesahan berhasil, Then laporan resmi membaca snapshot `pengukuran_versi` tersebut, bukan live join master terbaru.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `pengukuran`, `pengukuran_versi`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Pastikan `pengukuran_versi`/referensi `pengukuran_versi_id` konsisten; versi resmi lama tetap tersimpan ketika koreksi dibuat.

**B. Backend / Domain**
- [ ] Pengesahan memakai `pengukuran_versi.diajukan_by` + `jalur_pengajuan`, menolak versi stale, dan mengisi metadata pengesahan atomik.
- [ ] F2 tidak melewati resolver/deny; `self_approval` harus tampak pada audit/laporan internal sesuai kontrak.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengukuran:sahkan` (sensitif).
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pengesahan Pengukuran & F1/F2**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given jalur pengajuan PIC, When aktor sama dengan `diajukan_by` mencoba sahkan, Then F1 menolak.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given aktor lain berizin, When sahkan, Then status menjadi `disahkan` dan `disahkan_by/disahkan_at` versi terisi atomik.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given jalur pengajuan Perencanaan, When pengaju yang sama mereviu/mengesahkan dan izin efektif masih ada, Then F2 mengizinkan dan mencatat `self_approval`.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given deny/permission tidak memenuhi, Then F2 tidak dapat melewati resolver.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given versi stale, When sahkan, Then ditolak.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given pengesahan berhasil, Then laporan resmi membaca snapshot `pengukuran_versi` tersebut, bukan live join master terbaru.
- [ ] REGRESSION: Uji pembuat draft berbeda dari pengaju; perubahan role/PIC/grant setelah submit tidak mengubah provenance atau meloloskan F1.
- [ ] CONCURRENCY: Uji transaksi/optimistic locking/versi stale sesuai kontrak Data Model.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-08.03 · [Feature] Buka-Kembali Pengukuran Disahkan & Histori Status Capaian

**Terkait User Story:** `US-08.03`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `review-approval`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 5: 5.10; Modul 6: 6.3–6.6  
**PRD:** §19.5, §21  
**Workflow:** §13, §16  
**Data Model/Entitas:** versi + status capaian

#### User Story

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** membuka pengukuran resmi untuk koreksi tanpa menghapus hasil lama,  
> **Sehingga** kesalahan pasca-pengesahan dapat diperbaiki sementara histori keputusan tetap utuh.

#### Kontrak Teknis

- **Dependensi:** Pengukuran disahkan; waktu koreksi sah.
- **Otorisasi:** `pengukuran:buka_kembali` (sensitif).
- **Dampak Data:** `pengukuran`, `pengukuran_versi`, `status_capaian`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given alasan wajib dan waktu koreksi sah, When buka kembali dilakukan, Then header menjadi `dikembalikan`.
- [ ] **AC-2:** Given versi lama sudah disahkan, Then versi lama dan status capaian yang terkait versi itu tetap tersimpan sebagai histori.
- [ ] **AC-3:** Given pengukuran diperbaiki lalu diajukan ulang, Then `pengukuran_versi` baru dibuat; versi baru belum memiliki status capaian sampai ditetapkan setelah pengesahan.
- [ ] **AC-4:** Given jadwal ditutup tanpa sesi koreksi, When buka kembali pengukuran langsung dicoba, Then ditolak.
- [ ] **AC-5:** Given versi koreksi belum disahkan, Then laporan resmi tetap memakai versi disahkan sebelumnya.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `pengukuran`, `pengukuran_versi`, `status_capaian`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Pastikan `pengukuran_versi`/referensi `pengukuran_versi_id` konsisten; versi resmi lama tetap tersimpan ketika koreksi dibuat.

**B. Backend / Domain**
- [ ] Jangan reset/hapus `status_capaian` versi lama. Versi koreksi baru mulai tanpa status capaian sampai ditetapkan setelah pengesahan.
- [ ] Selama koreksi belum disahkan, laporan resmi tetap memakai versi disahkan sebelumnya.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengukuran:buka_kembali` (sensitif).
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Buka-Kembali Pengukuran Disahkan & Histori Status Capaian**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given alasan wajib dan waktu koreksi sah, When buka kembali dilakukan, Then header menjadi `dikembalikan`.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given versi lama sudah disahkan, Then versi lama dan status capaian yang terkait versi itu tetap tersimpan sebagai histori.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given pengukuran diperbaiki lalu diajukan ulang, Then `pengukuran_versi` baru dibuat; versi baru belum memiliki status capaian sampai ditetapkan setelah pengesahan.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given jadwal ditutup tanpa sesi koreksi, When buka kembali pengukuran langsung dicoba, Then ditolak.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given versi koreksi belum disahkan, Then laporan resmi tetap memakai versi disahkan sebelumnya.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.


---

## Bagian 9 — Rekomendasi Pimpinan & Status Capaian

### ISS-09.01 · [Feature] Pencatatan Rekomendasi Pimpinan oleh Perencanaan

**Terkait User Story:** `US-09.01`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `evaluation`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 8: 8.5  
**PRD:** §22  
**Workflow:** §17  
**Data Model/Entitas:** `rekomendasi_pimpinan`

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mencatat arahan/rekomendasi pimpinan hasil evaluasi per indikator-periode,  
> **Sehingga** tindak lanjut pimpinan terdokumentasi walaupun approval aktif Pimpinan belum masuk MVP.

#### Kontrak Teknis

- **Dependensi:** Indikator, tahun, dan periode valid.
- **Otorisasi:** `rekomendasi:tetapkan` (sensitif).
- **Dampak Data:** `rekomendasi_pimpinan`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given rekomendasi valid, When disimpan, Then baris baru dibuat dengan `ditetapkan_oleh` dan waktu.
- [ ] **AC-2:** Given pengukuran periode belum disahkan, When rekomendasi ditulis setelah rapat, Then sistem tetap mengizinkan karena rekomendasi independen dari status pengukuran.
- [ ] **AC-3:** Given rekomendasi lama ada, When rekomendasi baru dibuat, Then baris baru menjadi yang aktif dan histori lama tidak dihapus.
- [ ] **AC-4:** Given role Pimpinan pada MVP, When membaca rekomendasi, Then bersifat read-only.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `rekomendasi_pimpinan`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Pencatatan Rekomendasi Pimpinan oleh Perencanaan** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `rekomendasi:tetapkan` (sensitif).
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pencatatan Rekomendasi Pimpinan oleh Perencanaan**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given rekomendasi valid, When disimpan, Then baris baru dibuat dengan `ditetapkan_oleh` dan waktu.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given pengukuran periode belum disahkan, When rekomendasi ditulis setelah rapat, Then sistem tetap mengizinkan karena rekomendasi independen dari status pengukuran.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given rekomendasi lama ada, When rekomendasi baru dibuat, Then baris baru menjadi yang aktif dan histori lama tidak dihapus.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given role Pimpinan pada MVP, When membaca rekomendasi, Then bersifat read-only.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-09.02 · [Feature] Penetapan Status Capaian per Versi Pengukuran Disahkan

**Terkait User Story:** `US-09.02`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `evaluation`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 6: 6.4–6.6  
**PRD:** §21  
**Workflow:** §16  
**Data Model/Entitas:** `status_capaian`

#### User Story

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** menetapkan status `tercapai`/`belum_tercapai` pada versi resmi tertentu,  
> **Sehingga** penilaian akhir selalu terikat pada angka yang benar-benar disahkan.

#### Kontrak Teknis

- **Dependensi:** Terdapat `pengukuran_versi` yang telah disahkan.
- **Otorisasi:** `status_capaian:update` (sensitif).
- **Dampak Data:** `status_capaian`, `pengukuran_versi`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given versi pengukuran telah disahkan, When status dipilih, Then `status_capaian` merujuk `pengukuran_versi_id` yang sama.
- [ ] **AC-2:** Given pengukuran belum disahkan, When status dicoba, Then ditolak.
- [ ] **AC-3:** Given versi koreksi baru disahkan, Then versi baru mulai **Belum ditetapkan**; status versi lama tidak diwariskan otomatis.
- [ ] **AC-4:** Given status direvisi, Then histori status lama tetap tersimpan dan baris terbaru menjadi yang berlaku.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `status_capaian`, `pengukuran_versi`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Pastikan `pengukuran_versi`/referensi `pengukuran_versi_id` konsisten; versi resmi lama tetap tersimpan ketika koreksi dibuat.

**B. Backend / Domain**
- [ ] `status_capaian` wajib merujuk versi pengukuran yang telah disahkan; status tidak diwariskan ke versi koreksi baru.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `status_capaian:update` (sensitif).
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Penetapan Status Capaian per Versi Pengukuran Disahkan**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given versi pengukuran telah disahkan, When status dipilih, Then `status_capaian` merujuk `pengukuran_versi_id` yang sama.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given pengukuran belum disahkan, When status dicoba, Then ditolak.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given versi koreksi baru disahkan, Then versi baru mulai **Belum ditetapkan**; status versi lama tidak diwariskan otomatis.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given status direvisi, Then histori status lama tetap tersimpan dan baris terbaru menjadi yang berlaku.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.


---

## Bagian 10 — Dashboard, Rekapitulasi, dan Ekspor

### ISS-10.01 · [Feature] Dashboard Kinerja Eksekutif

**Terkait User Story:** `US-10.01`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `reporting`, `P1`, `frontend`  
**Plan:** Modul 7: 7.1–7.8  
**PRD:** §23  
**Workflow:** §1, §14  
**Data Model/Entitas:** dashboard queries

#### User Story

> **Sebagai** Pimpinan, Perencanaan, Admin, atau pengguna lain yang memiliki izin,  
> **Saya ingin** memantau status indikator, target-vs-realisasi, progres RA, dan kegiatan,  
> **Sehingga** pengambilan keputusan didukung tampilan ringkas yang konsisten dengan status data.

#### Kontrak Teknis

- **Dependensi:** Pengguna memiliki akses dashboard; data resmi/draft tersedia sesuai hak.
- **Otorisasi:** `dashboard:read`.
- **Dampak Data:** Read-only query terhadap snapshot/versi resmi dan data kerja sesuai konteks.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given dashboard dibuka, Then filter Renstra, tahun, periode, sasaran, dan unit bekerja sesuai hak akses.
- [ ] **AC-2:** Given indikator-periode belum memiliki pengukuran, Then status dihitung dari daftar periode yang diharapkan, bukan dari keberadaan baris pengukuran semata.
- [ ] **AC-3:** Given periode sebelum efektivitas indikator, Then dashboard menampilkan Tidak berlaku dan tidak menghitungnya sebagai missing.
- [ ] **AC-4:** Given indikator arsip, Then tidak menjadi kewajiban baru.
- [ ] **AC-5:** Given hasil resmi ditampilkan, Then angka berasal dari versi disahkan/snapshot yang tepat, bukan master terbaru yang bisa berubah.
- [ ] **AC-6 (Q31):** Given user ber-PIC operasional (bukan role), When membuka dashboard, Then akses ditentukan oleh `dashboard:read` efektif. Issue tidak mengasumsikan permission tersebut berasal dari role PIC sampai preset PIC dikonfirmasi.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Verifikasi query, relasi, indeks, dan eager-loading yang diperlukan; **jangan** membuat mutasi domain baru untuk fitur read-only.

**B. Backend / Domain**
- [ ] Query dashboard harus membedakan Tidak berlaku, Belum Mengisi, Tidak Mengisi, Tidak Dapat Dihitung, dan nilai 0; histori resmi membaca versi disahkan.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `dashboard:read`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.

**D. Frontend / UX**
- [ ] Buat/rapikan halaman Inertia React untuk **Dashboard Kinerja Eksekutif** sebagai tampilan read-only/filterable sesuai hak server.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given dashboard dibuka, Then filter Renstra, tahun, periode, sasaran, dan unit bekerja sesuai hak akses.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given indikator-periode belum memiliki pengukuran, Then status dihitung dari daftar periode yang diharapkan, bukan dari keberadaan baris pengukuran semata.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given periode sebelum efektivitas indikator, Then dashboard menampilkan Tidak berlaku dan tidak menghitungnya sebagai missing.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given indikator arsip, Then tidak menjadi kewajiban baru.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given hasil resmi ditampilkan, Then angka berasal dari versi disahkan/snapshot yang tepat, bukan master terbaru yang bisa berubah.

- [ ] TEST-6 (Q31): Uji user PIC operasional (bukan role) dengan dan tanpa `dashboard:read` efektif; hasil mengikuti resolver, bukan nama role.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-10.02 · [Feature] Matriks Rekapitulasi Indikator × Periode

**Terkait User Story:** `US-10.02`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `reporting`, `P1`, `frontend`  
**Plan:** Modul 8: 8.1, 8.3  
**PRD:** §22.4, §24  
**Workflow:** §17  
**Data Model/Entitas:** report matrix

#### User Story

> **Sebagai** Tim Perencanaan atau Pimpinan,  
> **Saya ingin** melihat matriks lengkap per indikator dan periode,  
> **Sehingga** seluruh rantai akuntabilitas dapat ditinjau dalam satu tampilan.

#### Kontrak Teknis

- **Dependensi:** Pengguna memiliki akses laporan.
- **Otorisasi:** `laporan:read`.
- **Dampak Data:** Read-only atas versi resmi, snapshot, rekomendasi, status capaian, kegiatan/klaim/bukti yang dibekukan.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given tahun/periode dipilih, Then tabel memuat identitas Sasaran, Indikator, Satuan, Unit, PIC efektif/historis yang relevan.
- [ ] **AC-2:** Then kolom target memisahkan baseline, target PK, dan target periode.
- [ ] **AC-3:** Then kolom realisasi memisahkan komponen, nilai akhir, status perhitungan, dan persentase capaian bila relevan.
- [ ] **AC-4:** Then narasi kegiatan/kendala/tindak lanjut berasal dari versi resmi yang dinilai, bukan live data yang telah berubah.
- [ ] **AC-5:** Then rekomendasi pimpinan, status capaian, dan status bukti ditampilkan sesuai versi/konteks.
- [ ] **AC-6:** Given `tidak_dapat_dihitung` atau `tidak_dapat_dipenuhi`, Then penanda ditampilkan dan tidak disamakan dengan nilai nol.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Verifikasi query, relasi, indeks, dan eager-loading yang diperlukan; **jangan** membuat mutasi domain baru untuk fitur read-only.

**B. Backend / Domain**
- [ ] Matriks laporan mengambil target/narasi/bukti dari konteks versi resmi; jangan live-join data kerja yang bisa berubah.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `laporan:read`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.

**D. Frontend / UX**
- [ ] Buat/rapikan halaman Inertia React untuk **Matriks Rekapitulasi Indikator × Periode** sebagai tampilan read-only/filterable sesuai hak server.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given tahun/periode dipilih, Then tabel memuat identitas Sasaran, Indikator, Satuan, Unit, PIC efektif/historis yang relevan.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Then kolom target memisahkan baseline, target PK, dan target periode.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Then kolom realisasi memisahkan komponen, nilai akhir, status perhitungan, dan persentase capaian bila relevan.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Then narasi kegiatan/kendala/tindak lanjut berasal dari versi resmi yang dinilai, bukan live data yang telah berubah.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Then rekomendasi pimpinan, status capaian, dan status bukti ditampilkan sesuai versi/konteks.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given `tidak_dapat_dihitung` atau `tidak_dapat_dipenuhi`, Then penanda ditampilkan dan tidak disamakan dengan nilai nol.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-10.03 · [Feature] Ekspor Laporan Kinerja ke Excel

**Terkait User Story:** `US-10.03`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `reporting`, `P1`, `frontend`  
**Plan:** Modul 8: 8.2, 8.4  
**PRD:** §24  
**Workflow:** §17  
**Data Model/Entitas:** Excel export

#### User Story

> **Sebagai** Tim Perencanaan atau Pimpinan,  
> **Saya ingin** mengekspor rekap kinerja ke Excel,  
> **Sehingga** laporan dapat digunakan pada proses formal tanpa mengubah makna data.

#### Kontrak Teknis

- **Dependensi:** Pengguna memiliki akses ekspor; matriks laporan dapat dibentuk.
- **Otorisasi:** `laporan:ekspor`.
- **Dampak Data:** Stream `.xlsx`; audit event ekspor bila disepakati baseline.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given filter laporan dipilih, When ekspor dijalankan, Then `.xlsx` memisahkan baseline, target, realisasi, persentase, narasi, rekomendasi, dan status bukti.
- [ ] **AC-2:** Given Pimpinan memiliki `laporan:ekspor`, When ekspor dilakukan, Then berhasil sesuai scope baca.
- [ ] **AC-3:** Given Admin bawaan tidak memiliki `laporan:ekspor`, When endpoint dipanggil, Then 403 kecuali Admin menerima grant eksplisit yang sah.
- [ ] **AC-4:** Given data resmi historis diekspor, Then file membaca versi disahkan dan konteks beku.
- [ ] **AC-5:** Given nilai tidak dapat dihitung, Then ekspor menandainya secara eksplisit dan tidak mengubah menjadi 0.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: Stream `.xlsx`; audit event ekspor bila disepakati baseline.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Generator XLSX harus memakai sumber data yang sama dengan laporan resmi dan mempertahankan pemisahan baseline/target/realisasi/persentase.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `laporan:ekspor`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Ekspor Laporan Kinerja ke Excel**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given filter laporan dipilih, When ekspor dijalankan, Then `.xlsx` memisahkan baseline, target, realisasi, persentase, narasi, rekomendasi, dan status bukti.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given Pimpinan memiliki `laporan:ekspor`, When ekspor dilakukan, Then berhasil sesuai scope baca.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given Admin bawaan tidak memiliki `laporan:ekspor`, When endpoint dipanggil, Then 403 kecuali Admin menerima grant eksplisit yang sah.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given data resmi historis diekspor, Then file membaca versi disahkan dan konteks beku.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given nilai tidak dapat dihitung, Then ekspor menandainya secara eksplisit dan tidak mengubah menjadi 0.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-10.04 · [UAT] Penerimaan Contoh Keluaran Excel untuk UAT

**Terkait User Story:** `US-10.04`  
**Prioritas:** 🟠 P2  
**Story Points:** 3  
**Labels:** `uat`, `reporting`, `P2`, `frontend`  
**Plan:** Modul 8: 8.4; P.4  
**PRD:** §24, §32  
**Workflow:** §17  
**Data Model/Entitas:** UAT artifact

#### User Story

> **Sebagai** Tim Perencanaan / pemilik UAT,  
> **Saya ingin** mengesahkan satu contoh keluaran Excel sebagai acuan penerimaan,  
> **Sehingga** developer dan tester memiliki target format/informasi yang tidak ambigu.

#### Kontrak Teknis

- **Dependensi:** Fitur ekspor tersedia; Tim Perencanaan menyediakan/menyetujui contoh penerimaan.
- **Otorisasi:** Proses UAT/acceptance, bukan permission aplikasi baru.
- **Dampak Data:** Artefak UAT/keputusan baseline; tidak menambah tabel domain.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given build ekspor siap UAT, When contoh dibandingkan, Then baseline/target/realisasi/persentase dan informasi wajib dinilai kesetaraannya.
- [ ] **AC-2:** Given sumber lama memiliki susunan sel ambigu, Then UAT menilai kesetaraan informasi; aplikasi tidak wajib menyalin layout ambigu secara literal.
- [ ] **AC-3:** Given contoh belum disetujui, Then dokumen tidak mengklaim format final telah diterima.
- [ ] **AC-4:** Given perubahan format setelah persetujuan, Then perubahan harus masuk catatan keputusan/UAT baru.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Tidak menambah skema domain tanpa keputusan eksplisit; simpan artefak/checklist penerimaan di mekanisme dokumentasi proyek yang telah ditetapkan.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Penerimaan Contoh Keluaran Excel untuk UAT** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Tidak menciptakan permission baru hanya untuk checklist governance/UAT kecuali PRD secara eksplisit menambahkannya.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Penerimaan Contoh Keluaran Excel untuk UAT**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] UAT-1: Verifikasi manual/acceptance — Given build ekspor siap UAT, When contoh dibandingkan, Then baseline/target/realisasi/persentase dan informasi wajib dinilai kesetaraannya.
- [ ] UAT-2: Verifikasi manual/acceptance — Given sumber lama memiliki susunan sel ambigu, Then UAT menilai kesetaraan informasi; aplikasi tidak wajib menyalin layout ambigu secara literal.
- [ ] UAT-3: Verifikasi manual/acceptance — Given contoh belum disetujui, Then dokumen tidak mengklaim format final telah diterima.
- [ ] UAT-4: Verifikasi manual/acceptance — Given perubahan format setelah persetujuan, Then perubahan harus masuk catatan keputusan/UAT baru.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).


---

## Bagian 11 — Bukti Dukung Multi-Mode & Integritas Lampiran

### ISS-11.01 · [Feature] Konfigurasi Persyaratan Jenis Berkas

**Terkait User Story:** `US-11.01`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `evidence`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 13: 13.1  
**PRD:** §18.2–18.7  
**Workflow:** §10  
**Data Model/Entitas:** `jenis_berkas`

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** menetapkan persyaratan bukti per tahap, mode, wajib/tidak, dan batas file,  
> **Sehingga** standar bukti dapat dikonfigurasi tanpa deployment kode.

#### Kontrak Teknis

- **Dependensi:** Indikator/unit tersedia bila persyaratan bersifat spesifik.
- **Otorisasi:** `jenis_berkas:create`, `jenis_berkas:read`, `jenis_berkas:update`, `jenis_berkas:delete`.
- **Dampak Data:** `jenis_berkas`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given persyaratan baru, When tahap, mode, wajib, batas ukuran/format disimpan, Then data valid tersimpan.
- [x] **AC-2:** Given tidak ada mode yang diizinkan, When submit, Then ditolak.
- [x] **AC-3:** Given `semua_mode_wajib = true`, Then seluruh mode yang diizinkan harus dipenuhi.
- [x] **AC-4:** Given perubahan substansi persyaratan dilakukan, Then audit menyimpan before/after dan alasan bila diwajibkan.
- [x] **AC-5:** Given persyaratan sudah dibekukan pada versi submit lama, Then perubahan master hanya berlaku untuk pengajuan berikutnya.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `jenis_berkas`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Implementasikan use-case **Konfigurasi Persyaratan Jenis Berkas** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [x] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `jenis_berkas:create`, `jenis_berkas:read`, `jenis_berkas:update`, `jenis_berkas:delete`.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Konfigurasi Persyaratan Jenis Berkas**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [x] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [x] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given persyaratan baru, When tahap, mode, wajib, batas ukuran/format disimpan, Then data valid tersimpan.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given tidak ada mode yang diizinkan, When submit, Then ditolak.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given `semua_mode_wajib = true`, Then seluruh mode yang diizinkan harus dipenuhi.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given perubahan substansi persyaratan dilakukan, Then audit menyimpan before/after dan alasan bila diwajibkan.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given persyaratan sudah dibekukan pada versi submit lama, Then perubahan master hanya berlaku untuk pengajuan berikutnya.

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [x] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-11.02 · [Feature] Unggah File Privat & Streamed Download

**Terkait User Story:** `US-11.02`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `evidence`, `P1`, `frontend`, `ops`  
**Plan:** Modul 13: 13.2, 13.5  
**PRD:** §18.4–18.9  
**Workflow:** §10  
**Data Model/Entitas:** `berkas`, private disk

#### User Story

> **Sebagai** Pengguna berwenang,  
> **Saya ingin** mengunggah dan mengunduh file bukti melalui storage privat,  
> **Sehingga** dokumen tidak terekspos sebagai URL publik.

#### Kontrak Teknis

- **Dependensi:** `berkas.unggahan_aktif = true`; capability induk valid.
- **Otorisasi:** Capability berkas mengikuti induk; `berkas:read`/deny tetap dievaluasi sesuai kontrak akses.
- **Dampak Data:** `berkas`, private filesystem, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given file valid, When upload, Then file disimpan pada disk privat dan metadata nama asli, MIME, ukuran, path aman disimpan.
- [ ] **AC-2:** Given ukuran/format melanggar batas persyaratan/fallback, When upload, Then ditolak sebelum file final tersimpan.
- [ ] **AC-3:** Given path fisik ditebak tanpa autentikasi, When diakses, Then tidak tersedia sebagai public URL.
- [ ] **AC-4:** Given pengguna meminta download, Then server mengevaluasi hak baca induk dan deny sebelum mengalirkan response.
- [ ] **AC-5:** Given token/credential/storage path sensitif dicatat audit, Then hanya metadata yang diperlukan yang boleh tampil; secret tidak boleh bocor.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `berkas`, private filesystem, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Konfigurasikan filesystem private dan streamed download; disk privat bukan migration database.
- [ ] Download selalu mengevaluasi capability induk + deny; path fisik tidak boleh menjadi URL publik.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: Capability berkas mengikuti induk; `berkas:read`/deny tetap dievaluasi sesuai kontrak akses.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Unggah File Privat & Streamed Download**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

**E. Integration / Operations**
- [ ] Konfigurasi private filesystem, batas upload web/PHP, dan streamed response; verifikasi file tidak terpublikasi lewat web root.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given file valid, When upload, Then file disimpan pada disk privat dan metadata nama asli, MIME, ukuran, path aman disimpan.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given ukuran/format melanggar batas persyaratan/fallback, When upload, Then ditolak sebelum file final tersimpan.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given path fisik ditebak tanpa autentikasi, When diakses, Then tidak tersedia sebagai public URL.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given pengguna meminta download, Then server mengevaluasi hak baca induk dan deny sebelum mengalirkan response.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given token/credential/storage path sensitif dicatat audit, Then hanya metadata yang diperlukan yang boleh tampil; secret tidak boleh bocor.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-11.03 · [Feature] Bukti Mode Tautan atau Teks

**Terkait User Story:** `US-11.03`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `evidence`, `P1`, `frontend`  
**Plan:** Modul 13: 13.2–13.5  
**PRD:** §18.4–18.6  
**Workflow:** §10  
**Data Model/Entitas:** `berkas`

#### User Story

> **Sebagai** Pengguna berwenang,  
> **Saya ingin** memenuhi bukti melalui URL resmi atau keterangan teks,  
> **Sehingga** proses tetap berjalan saat file fisik tidak diperlukan.

#### Kontrak Teknis

- **Dependensi:** Persyaratan mengizinkan tautan/teks.
- **Otorisasi:** Capability bukti induk.
- **Dampak Data:** `berkas`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given mode tautan dipilih, When URL `http/https` valid disimpan, Then URL tersimpan tanpa memakai kuota disk file.
- [ ] **AC-2:** Given mode teks dipilih, When isi valid disimpan, Then teks tersimpan pada kolom yang tepat.
- [ ] **AC-3:** Given mode tidak diizinkan oleh jenis persyaratan, Then ditolak.
- [ ] **AC-4:** Given audit untuk teks, Then audit tidak harus menyalin isi sensitif penuh; cukup metadata yang diperlukan sesuai kebijakan.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `berkas`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Bukti Mode Tautan atau Teks** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: Capability bukti induk.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Bukti Mode Tautan atau Teks**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given mode tautan dipilih, When URL `http/https` valid disimpan, Then URL tersimpan tanpa memakai kuota disk file.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given mode teks dipilih, When isi valid disimpan, Then teks tersimpan pada kolom yang tepat.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given mode tidak diizinkan oleh jenis persyaratan, Then ditolak.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given audit untuk teks, Then audit tidak harus menyalin isi sensitif penuh; cukup metadata yang diperlukan sesuai kebijakan.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-11.04 · [Feature] Imutabilitas Berkas Berdasarkan Induk

**Terkait User Story:** `US-11.04`  
**Prioritas:** 🔴 P0  
**Story Points:** 8  
**Labels:** `feature`, `evidence`, `P0`, `audit-sensitive`, `frontend`  
**Plan:** Modul 13: 13.6  
**PRD:** §18.8  
**Workflow:** §10  
**Data Model/Entitas:** `berkas`

#### User Story

> **Sebagai** Sistem Integritas SAKIP,  
> **Saya ingin** mencegah penghapusan bukti setelah mencapai batas legal/versi resmi,  
> **Sehingga** bukti yang telah menjadi dasar keputusan tidak dapat dimanipulasi.

#### Kontrak Teknis

- **Dependensi:** Berkas terkait salah satu induk yang didukung.
- **Otorisasi:** `berkas:delete` (sensitif) + guard status induk.
- **Dampak Data:** `berkas`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given berkas RA/Pengukuran telah dirujuk versi resmi/disahkan, When delete dicoba, Then ditolak.
- [ ] **AC-2:** Given kegiatan telah terlaksana dan bukti menjadi dasar gerbang, When delete dicoba, Then ditolak; koreksi append-only.
- [ ] **AC-3:** Given Renstra aktif, When lampiran resmi Renstra dihapus, Then ditolak.
- [ ] **AC-4:** Given PK sudah menjadi dasar jadwal aktif, When lampiran PK dihapus, Then ditolak.
- [ ] **AC-5:** Given regulasi masih dirujuk Renstra/Indikator aktif, When lampiran regulasi dihapus, Then ditolak.
- [ ] **AC-6:** Given batas imutabilitas belum tercapai dan aktor berwenang, Then soft delete dapat dilakukan dengan `dihapus_pada/dihapus_oleh` serta audit.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `berkas`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan guard imutabilitas per enam induk; penghapusan sebelum batas memakai soft delete, sesudah batas ditolak.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `berkas:delete` (sensitif) + guard status induk.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Imutabilitas Berkas Berdasarkan Induk**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given berkas RA/Pengukuran telah dirujuk versi resmi/disahkan, When delete dicoba, Then ditolak.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan telah terlaksana dan bukti menjadi dasar gerbang, When delete dicoba, Then ditolak; koreksi append-only.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given Renstra aktif, When lampiran resmi Renstra dihapus, Then ditolak.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given PK sudah menjadi dasar jadwal aktif, When lampiran PK dihapus, Then ditolak.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given regulasi masih dirujuk Renstra/Indikator aktif, When lampiran regulasi dihapus, Then ditolak.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given batas imutabilitas belum tercapai dan aktor berwenang, Then soft delete dapat dilakukan dengan `dihapus_pada/dihapus_oleh` serta audit.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-11.05 · [Feature] Pembekuan Persyaratan Bukti Saat Pengajuan

**Terkait User Story:** `US-11.05`  
**Prioritas:** 🔴 P0  
**Story Points:** 5  
**Labels:** `feature`, `evidence`, `P0`, `backend`  
**Plan:** Modul 13: 13.4  
**PRD:** §18.11  
**Workflow:** §10  
**Data Model/Entitas:** versi + persyaratan

#### User Story

> **Sebagai** Sistem,  
> **Saya ingin** membekukan persyaratan dan bukti yang digunakan pada setiap versi pengajuan,  
> **Sehingga** perubahan persyaratan di tengah proses tidak mengubah standar review yang sudah berlaku diam-diam.

#### Kontrak Teknis

- **Dependensi:** RA/Pengukuran siap submit.
- **Otorisasi:** Mengikuti permission submit induk.
- **Dampak Data:** `rencana_aksi_versi` / `pengukuran_versi` snapshot, `jenis_berkas`, `berkas`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given submit terjadi, Then versi menyimpan jenis/mode/wajib persyaratan serta bukti/pengecualian yang dipakai.
- [ ] **AC-2:** Given master `jenis_berkas` berubah setelah submit, Then versi yang sedang direviu tidak ikut berubah.
- [ ] **AC-3:** Given ketentuan baru harus diterapkan pada proses berjalan, Then proses harus dikembalikan beralasan dan diajukan ulang sebagai versi baru.
- [ ] **AC-4:** Given hasil sudah disahkan, Then perubahan persyaratan baru tidak otomatis membatalkan hasil lama.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `rencana_aksi_versi` / `pengukuran_versi` snapshot, `jenis_berkas`, `berkas`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Snapshot versi submit harus menyimpan persyaratan dan bukti yang dinilai. Perubahan master tidak boleh mengubah proses yang sedang direviu.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: Mengikuti permission submit induk.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given submit terjadi, Then versi menyimpan jenis/mode/wajib persyaratan serta bukti/pengecualian yang dipakai.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given master `jenis_berkas` berubah setelah submit, Then versi yang sedang direviu tidak ikut berubah.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given ketentuan baru harus diterapkan pada proses berjalan, Then proses harus dikembalikan beralasan dan diajukan ulang sebagai versi baru.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given hasil sudah disahkan, Then perubahan persyaratan baru tidak otomatis membatalkan hasil lama.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.

### ISS-11.06 · [Feature] Pengecualian Mode File Saat Unggah Dinonaktifkan

**Terkait User Story:** `US-11.06`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `evidence`, `P1`, `backend`  
**Plan:** Modul 13: 13.7  
**PRD:** §18.5–18.7  
**Workflow:** §10  
**Data Model/Entitas:** pengecualian file

#### User Story

> **Sebagai** Sistem / Tim Perencanaan,  
> **Saya ingin** menerapkan pengecualian hanya pada mode file yang tidak dapat dipenuhi,  
> **Sehingga** pelaporan tidak macet tanpa mengabaikan mode bukti lain.

#### Kontrak Teknis

- **Dependensi:** Setelan global unggah file mati dan persyaratan tertentu memerlukan file.
- **Otorisasi:** Evaluasi sistem; pengaturan dikelola `pengaturan:update`.
- **Dampak Data:** `jenis_berkas`, `berkas`, penanda/audit pengecualian.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given `berkas.unggahan_aktif = false`, When persyaratan membutuhkan file, Then kewajiban mode file dapat ditandai `tidak_dapat_dipenuhi` sesuai kontrak dan audit.
- [ ] **AC-2:** Given persyaratan juga mewajibkan tautan/teks, Then mode non-file tersebut tetap wajib.
- [ ] **AC-3:** Given gerbang PK tidak punya file namun unggah mati, Then tautan/teks tetap dapat memenuhi; jika tidak ada mode alternatif, pengecualian khusus PK dicatat tanpa menyamarkannya sebagai bukti nyata.
- [ ] **AC-4:** Given upload kembali aktif, Then persyaratan pengajuan berikutnya kembali mengikuti mode master saat itu; versi lama tetap menyimpan keputusan pengecualian yang dahulu berlaku.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `jenis_berkas`, `berkas`, penanda/audit pengecualian.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Saat upload off, kecualikan hanya kewajiban **mode file** yang sah; tautan/teks yang diwajibkan tetap harus dipenuhi.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: Evaluasi sistem; pengaturan dikelola `pengaturan:update`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given `berkas.unggahan_aktif = false`, When persyaratan membutuhkan file, Then kewajiban mode file dapat ditandai `tidak_dapat_dipenuhi` sesuai kontrak dan audit.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given persyaratan juga mewajibkan tautan/teks, Then mode non-file tersebut tetap wajib.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given gerbang PK tidak punya file namun unggah mati, Then tautan/teks tetap dapat memenuhi; jika tidak ada mode alternatif, pengecualian khusus PK dicatat tanpa menyamarkannya sebagai bukti nyata.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given upload kembali aktif, Then persyaratan pengajuan berikutnya kembali mengikuti mode master saat itu; versi lama tetap menyimpan keputusan pengecualian yang dahulu berlaku.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.


---

## Bagian 12 — Alert Kontekstual & Notifikasi


> **Aturan Q31 penerima notifikasi PIC:** “PIC terkait” berarti penerima efektif berdasarkan assignment/work item yang relevan, bukan seluruh akun yang memiliki PIC operasional (bukan role). Pemilihan penerima harus menggunakan data domain yang benar; tautan notifikasi tetap melewati authorization saat dibuka.

### ISS-12.01 · [Feature] Banner Pengingat Kontekstual

**Terkait User Story:** `US-12.01`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `notification`, `P1`, `frontend`  
**Plan:** Modul 14: 14.1  
**PRD:** §28.1  
**Workflow:** §22.1  
**Data Model/Entitas:** alert server-side

#### User Story

> **Sebagai** PIC dan pengguna terkait,  
> **Saya ingin** melihat banner tenggat dan masalah kelengkapan yang relevan,  
> **Sehingga** pengguna mengetahui kewajiban tanpa menghitung status sendiri di klien.

#### Kontrak Teknis

- **Dependensi:** Pengguna autentikasi; data jadwal/status tersedia.
- **Otorisasi:** Mengikuti akses dashboard/halaman kerja.
- **Dampak Data:** Read-only hasil evaluasi server.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given jendela RA aktif/mendekati batas, When halaman dibuka, Then banner menampilkan `rencana_aksi_selesai` sesuai konteks.
- [ ] **AC-2:** Given periode pengisian aktif, Then countdown mengacu `pengisian_selesai` resmi.
- [ ] **AC-3:** Given indikator belum diisi, Then status muncul hanya untuk periode yang berlaku.
- [ ] **AC-4:** Given bukti `tidak_dapat_dipenuhi` atau PK belum lengkap, Then Perencanaan mendapat penanda yang relevan.
- [ ] **AC-5:** Given props dikirim ke React, Then seluruh evaluasi status/tenggat dilakukan server-side.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Verifikasi query, relasi, indeks, dan eager-loading yang diperlukan; **jangan** membuat mutasi domain baru untuk fitur read-only.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Banner Pengingat Kontekstual** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: Mengikuti akses dashboard/halaman kerja.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Banner Pengingat Kontekstual**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given jendela RA aktif/mendekati batas, When halaman dibuka, Then banner menampilkan `rencana_aksi_selesai` sesuai konteks.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given periode pengisian aktif, Then countdown mengacu `pengisian_selesai` resmi.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given indikator belum diisi, Then status muncul hanya untuk periode yang berlaku.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given bukti `tidak_dapat_dipenuhi` atau PK belum lengkap, Then Perencanaan mendapat penanda yang relevan.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given props dikirim ke React, Then seluruh evaluasi status/tenggat dilakukan server-side.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-12.02 · [Feature] Lonceng Notifikasi & Antrean Tugas In-App

**Terkait User Story:** `US-12.02`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `notification`, `P1`, `frontend`  
**Plan:** Modul 14: 14.1  
**PRD:** §28.1  
**Workflow:** §22.1  
**Data Model/Entitas:** in-app notifications

#### User Story

> **Sebagai** PIC, Perencanaan, Admin,  
> **Saya ingin** melihat unread counter dan daftar tugas yang memerlukan tindakan,  
> **Sehingga** event penting tidak hanya bergantung pada pengecekan halaman manual.

#### Kontrak Teknis

- **Dependensi:** Pengguna autentikasi.
- **Otorisasi:** Seluruh pengguna autentikasi sesuai event yang berhak dilihat.
- **Dampak Data:** Penyimpanan notifikasi/read-state sesuai implementasi.

- **Q31 recipient rule:** PIC penerima ditentukan dari konteks assignment/work item efektif; jangan broadcast ke semua user PIC operasional (bukan role).

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given RA/Pengukuran dikembalikan, Then PIC menerima item berisi ringkasan alasan dan tautan aman.
- [ ] **AC-2:** Given PIC mengajukan RA/Pengukuran, Then Perencanaan menerima item antrean review.
- [ ] **AC-3:** Given jadwal pengisian dibuka, Then PIC terkait dapat menerima notifikasi in-app.
- [ ] **AC-4:** Given Admin memiliki event akses/audit yang ditetapkan, Then hanya event yang sesuai haknya yang tampil.
- [ ] **AC-5:** Given notifikasi dibuka, Then read-state berubah dan unread counter berkurang.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: Penyimpanan notifikasi/read-state sesuai implementasi.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Lonceng Notifikasi & Antrean Tugas In-App** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: Seluruh pengguna autentikasi sesuai event yang berhak dilihat.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Lonceng Notifikasi & Antrean Tugas In-App**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given RA/Pengukuran dikembalikan, Then PIC menerima item berisi ringkasan alasan dan tautan aman.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given PIC mengajukan RA/Pengukuran, Then Perencanaan menerima item antrean review.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given jadwal pengisian dibuka, Then PIC terkait dapat menerima notifikasi in-app.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given Admin memiliki event akses/audit yang ditetapkan, Then hanya event yang sesuai haknya yang tampil.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given notifikasi dibuka, Then read-state berubah dan unread counter berkurang.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-12.03 · [Feature] Broadcast Pembukaan Jadwal Pengisian via WhatsApp & Email

**Terkait User Story:** `US-12.03`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `notification`, `P1`, `backend`, `ops`  
**Plan:** Modul 14: 14.2  
**PRD:** §28.2  
**Workflow:** §22.2  
**Data Model/Entitas:** WA/Email queue

#### User Story

> **Sebagai** PIC terkait,  
> **Saya ingin** menerima pemberitahuan pembukaan pengisian melalui WhatsApp dan Email,  
> **Sehingga** PIC mengetahui dimulainya jendela walau sedang tidak membuka aplikasi.

#### Kontrak Teknis

- **Dependensi:** Integrasi eksternal aktif; sumber kontak dan template telah ditetapkan pemilik layanan.
- **Otorisasi:** Event sistem setelah waktu `pengisian_mulai`/pembukaan resmi.
- **Dampak Data:** Queue/job dan log status pengiriman tanpa secret.

- **Q31 recipient rule:** PIC penerima ditentukan dari konteks assignment/work item efektif; jangan broadcast ke semua user PIC operasional (bukan role).

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given tanggal/waktu pembukaan resmi tercapai, When pemicu berjalan, Then job WA dan Email didispatch ke PIC yang relevan.
- [ ] **AC-2:** Given jadwal diaktifkan jauh sebelum `pengisian_mulai`, Then broadcast pembukaan tidak dikirim terlalu awal.
- [ ] **AC-3:** Given provider gagal, Then transaksi bisnis pembukaan jadwal tidak dirollback.
- [ ] **AC-4:** Given job diulang, Then idempotency mencegah pesan ganda untuk event-penerima-periode-channel yang sama.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: Queue/job dan log status pengiriman tanpa secret.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Dispatch broadcast berdasarkan waktu pembukaan resmi, bukan hanya saat jadwal diaktifkan; gunakan queue setelah event bisnis sah.
- [ ] Kegagalan provider tidak boleh me-rollback transaksi domain.

**C. Authorization & Audit**
- [ ] Job/scheduler bukan HTTP user route: otorisasi ditentukan pada event bisnis/pemilihan penerima, bukan dengan memasang middleware RBAC palsu pada cron.

**E. Integration / Operations**
- [ ] Gunakan Laravel queue/worker dan scheduler sesuai Plan; credential provider disimpan di environment/secret manager, bukan `pengaturan` biasa.
- [ ] Log error provider tanpa token/secret; dukung fake/mock provider untuk automated test.

#### Automated Tests / Verification

- [ ] TEST-1: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given tanggal/waktu pembukaan resmi tercapai, When pemicu berjalan, Then job WA dan Email didispatch ke PIC yang relevan.
- [ ] TEST-2: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given jadwal diaktifkan jauh sebelum `pengisian_mulai`, Then broadcast pembukaan tidak dikirim terlalu awal.
- [ ] TEST-3: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given provider gagal, Then transaksi bisnis pembukaan jadwal tidak dirollback.
- [ ] TEST-4: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given job diulang, Then idempotency mencegah pesan ganda untuk event-penerima-periode-channel yang sama.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.

### ISS-12.04 · [Feature] EWS H-7, H-3, H-1 Menjelang Tenggat Pengisian

**Terkait User Story:** `US-12.04`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `notification`, `P1`, `backend`, `ops`  
**Plan:** Modul 14: 14.2  
**PRD:** §28.2  
**Workflow:** §22.2  
**Data Model/Entitas:** EWS

#### User Story

> **Sebagai** PIC yang belum menyelesaikan pengajuan,  
> **Saya ingin** menerima pengingat hanya pada H-7, H-3, dan H-1,  
> **Sehingga** risiko keterlambatan berkurang tanpa spam harian.

#### Kontrak Teknis

- **Dependensi:** Periode aktif; konfigurasi notifikasi tersedia; jam kirim/zona waktu ditetapkan pemilik layanan sebelum aktivasi nyata.
- **Otorisasi:** Scheduler sistem.
- **Dampak Data:** Queue/log pengiriman.

- **Q31 recipient rule:** PIC penerima ditentukan dari konteks assignment/work item efektif; jangan broadcast ke semua user PIC operasional (bukan role).

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given scheduler mengevaluasi harian, When tanggal tepat H-7/H-3/H-1 dari `pengisian_selesai`, Then PIC yang belum selesai menjadi kandidat penerima.
- [ ] **AC-2:** Given tanggal H-6/H-5/H-4/H-2, Then scheduler tidak mendispatch pengingat PIC meskipun tetap melakukan pengecekan.
- [ ] **AC-3:** Given pengukuran sudah diajukan dan tidak sedang dikembalikan untuk revisi, Then PIC dikeluarkan dari daftar pengingat.
- [ ] **AC-4:** Given pengukuran dikembalikan dan belum diajukan ulang, Then tetap dianggap pekerjaan belum selesai.
- [ ] **AC-5:** Given jam operasional belum ditetapkan, Then dokumen tidak menghardcode pukul 08.00 sebagai requirement final.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: Queue/log pengiriman.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Scheduler boleh berjalan harian tetapi dispatch hanya H-7/H-3/H-1; jangan hardcode pukul 08.00 sebagai requirement sebelum jam/zona waktu ditetapkan.
- [ ] Pengukuran dikembalikan yang belum diajukan ulang tetap dianggap belum selesai.

**C. Authorization & Audit**
- [ ] Job/scheduler bukan HTTP user route: otorisasi ditentukan pada event bisnis/pemilihan penerima, bukan dengan memasang middleware RBAC palsu pada cron.

**E. Integration / Operations**
- [ ] Gunakan Laravel queue/worker dan scheduler sesuai Plan; credential provider disimpan di environment/secret manager, bukan `pengaturan` biasa.
- [ ] Log error provider tanpa token/secret; dukung fake/mock provider untuk automated test.

#### Automated Tests / Verification

- [ ] TEST-1: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given scheduler mengevaluasi harian, When tanggal tepat H-7/H-3/H-1 dari `pengisian_selesai`, Then PIC yang belum selesai menjadi kandidat penerima.
- [ ] TEST-2: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given tanggal H-6/H-5/H-4/H-2, Then scheduler tidak mendispatch pengingat PIC meskipun tetap melakukan pengecekan.
- [ ] TEST-3: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given pengukuran sudah diajukan dan tidak sedang dikembalikan untuk revisi, Then PIC dikeluarkan dari daftar pengingat.
- [ ] TEST-4: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given pengukuran dikembalikan dan belum diajukan ulang, Then tetap dianggap pekerjaan belum selesai.
- [ ] TEST-5: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given jam operasional belum ditetapkan, Then dokumen tidak menghardcode pukul 08.00 sebagai requirement final.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.

### ISS-12.05 · [Feature] Rekap Progres untuk Tim Perencanaan H-3 & H-1

**Terkait User Story:** `US-12.05`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `notification`, `P1`, `backend`, `ops`  
**Plan:** Modul 14: 14.2  
**PRD:** §28.2  
**Workflow:** §22.2  
**Data Model/Entitas:** recap notification

#### User Story

> **Sebagai** Tim Perencanaan yang menjadi penerima efektif,  
> **Saya ingin** menerima rekap unit/indikator belum selesai pada H-3 dan H-1,  
> **Sehingga** koordinasi menjelang deadline dapat dilakukan proaktif.

#### Kontrak Teknis

- **Dependensi:** Periode aktif; kanal eksternal aktif.
- **Otorisasi:** Scheduler sistem.
- **Dampak Data:** Queue/log pengiriman.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given H-3 atau H-1, When job berjalan, Then ringkasan sudah/belum submit dikirim melalui WA dan Email kepada penerima Perencanaan yang ditetapkan.
- [ ] **AC-2:** Given pengguna tidak lagi menjadi penerima efektif, Then tidak otomatis menerima rekap.
- [ ] **AC-3:** Given job diulang, Then pesan tidak diduplikasi untuk kombinasi event/penerima/periode/channel yang sama.
- [ ] **AC-4:** Given provider gagal, Then kegagalan dicatat dan retry dilakukan terkontrol.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: Queue/log pengiriman.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Penerima rekap harus mengikuti daftar/hak/penugasan efektif, bukan broadcast ke semua akun Perencanaan tanpa seleksi.

**C. Authorization & Audit**
- [ ] Job/scheduler bukan HTTP user route: otorisasi ditentukan pada event bisnis/pemilihan penerima, bukan dengan memasang middleware RBAC palsu pada cron.

**E. Integration / Operations**
- [ ] Gunakan Laravel queue/worker dan scheduler sesuai Plan; credential provider disimpan di environment/secret manager, bukan `pengaturan` biasa.
- [ ] Log error provider tanpa token/secret; dukung fake/mock provider untuk automated test.

#### Automated Tests / Verification

- [ ] TEST-1: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given H-3 atau H-1, When job berjalan, Then ringkasan sudah/belum submit dikirim melalui WA dan Email kepada penerima Perencanaan yang ditetapkan.
- [ ] TEST-2: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given pengguna tidak lagi menjadi penerima efektif, Then tidak otomatis menerima rekap.
- [ ] TEST-3: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given job diulang, Then pesan tidak diduplikasi untuk kombinasi event/penerima/periode/channel yang sama.
- [ ] TEST-4: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given provider gagal, Then kegagalan dicatat dan retry dilakukan terkontrol.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.

### ISS-12.06 · [Feature] Notifikasi Instan Pengembalian RA/Pengukuran via WhatsApp & Email

**Terkait User Story:** `US-12.06`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `notification`, `P1`, `backend`, `ops`  
**Plan:** Modul 14: 14.2  
**PRD:** §28.2  
**Workflow:** §22.2  
**Data Model/Entitas:** return notification

#### User Story

> **Sebagai** PIC/pengaju terkait,  
> **Saya ingin** menerima notifikasi eksternal ketika pengajuannya dikembalikan,  
> **Sehingga** perbaikan dapat segera dilakukan sebelum jendela berakhir.

#### Kontrak Teknis

- **Dependensi:** Aksi `rencana_aksi:kembalikan` atau `pengukuran:kembalikan` berhasil commit dan memiliki alasan.
- **Otorisasi:** Dipicu event bisnis setelah commit.
- **Dampak Data:** Queue WA/Email dan log status.

- **Q31 recipient rule:** PIC penerima ditentukan dari konteks assignment/work item efektif; jangan broadcast ke semua user PIC operasional (bukan role).

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given transaksi pengembalian sukses, Then job WA **dan Email** didispatch setelah commit.
- [ ] **AC-2:** Given transaksi database gagal/rollback, Then notifikasi eksternal tidak dikirim.
- [ ] **AC-3:** Given pesan dibuat, Then memuat ringkasan alasan dan tautan aman yang tetap memerlukan autentikasi.
- [ ] **AC-4:** Given provider gagal, Then status bisnis tetap `dikembalikan` dan pengiriman dapat diretry terkontrol.
- [ ] **AC-5:** Given hak baca berubah setelah pesan dikirim, Then tautan tidak boleh melewati pemeriksaan otorisasi saat dibuka.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: Queue WA/Email dan log status.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Pengembalian sukses harus memicu **WhatsApp dan Email** setelah commit; rollback transaksi tidak boleh mengirim pesan.
- [ ] Tautan pada pesan tetap melewati autentikasi/otorisasi ketika dibuka.

**C. Authorization & Audit**
- [ ] Job/scheduler bukan HTTP user route: otorisasi ditentukan pada event bisnis/pemilihan penerima, bukan dengan memasang middleware RBAC palsu pada cron.

**E. Integration / Operations**
- [ ] Gunakan Laravel queue/worker dan scheduler sesuai Plan; credential provider disimpan di environment/secret manager, bukan `pengaturan` biasa.
- [ ] Log error provider tanpa token/secret; dukung fake/mock provider untuk automated test.

#### Automated Tests / Verification

- [ ] TEST-1: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given transaksi pengembalian sukses, Then job WA **dan Email** didispatch setelah commit.
- [ ] TEST-2: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given transaksi database gagal/rollback, Then notifikasi eksternal tidak dikirim.
- [ ] TEST-3: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given pesan dibuat, Then memuat ringkasan alasan dan tautan aman yang tetap memerlukan autentikasi.
- [ ] TEST-4: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given provider gagal, Then status bisnis tetap `dikembalikan` dan pengiriman dapat diretry terkontrol.
- [ ] TEST-5: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given hak baca berubah setelah pesan dikirim, Then tautan tidak boleh melewati pemeriksaan otorisasi saat dibuka.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.

### ISS-12.07 · [Infrastructure] Idempotensi, Retry, dan Status Pengiriman Notifikasi Eksternal

**Terkait User Story:** `US-12.07`  
**Prioritas:** 🟠 P2  
**Story Points:** 5  
**Labels:** `infrastructure`, `notification`, `P2`, `backend`, `ops`  
**Plan:** Modul 14: 14.2  
**PRD:** §28.2  
**Workflow:** §22.2  
**Data Model/Entitas:** delivery/idempotency

#### User Story

> **Sebagai** Pengelola operasional sistem,  
> **Saya ingin** memastikan pengiriman dapat diretry tanpa menggandakan pesan dan tanpa mengklaim delivery yang tidak terbukti,  
> **Sehingga** integrasi notifikasi stabil dan dapat diaudit.

#### Kontrak Teknis

- **Dependensi:** Job notification channel tersedia.
- **Otorisasi:** Proses sistem.
- **Dampak Data:** Log/idempotency key pengiriman.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given event yang sama diproses lebih dari sekali, Then idempotency key event-penerima-periode-channel mencegah duplikasi.
- [ ] **AC-2:** Given provider timeout/error, Then retry mengikuti kebijakan terkontrol dan secret tidak masuk log.
- [ ] **AC-3:** Given provider hanya mengembalikan accepted/queued, Then sistem tidak menyebut status tersebut sebagai delivered ke penerima.
- [ ] **AC-4:** Given credential/provider belum ditetapkan, Then fitur integrasi nyata belum dianggap siap produksi walaupun unit test lulus.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: Log/idempotency key pengiriman.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan idempotency key minimal berdasarkan event + penerima + periode + channel, retry terkontrol, dan status provider yang tidak menyamakan accepted dengan delivered.

**C. Authorization & Audit**
- [ ] Job/scheduler bukan HTTP user route: otorisasi ditentukan pada event bisnis/pemilihan penerima, bukan dengan memasang middleware RBAC palsu pada cron.

**E. Integration / Operations**
- [ ] Gunakan Laravel queue/worker dan scheduler sesuai Plan; credential provider disimpan di environment/secret manager, bukan `pengaturan` biasa.
- [ ] Log error provider tanpa token/secret; dukung fake/mock provider untuk automated test.

#### Automated Tests / Verification

- [ ] TEST-1: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given event yang sama diproses lebih dari sekali, Then idempotency key event-penerima-periode-channel mencegah duplikasi.
- [ ] TEST-2: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given provider timeout/error, Then retry mengikuti kebijakan terkontrol dan secret tidak masuk log.
- [ ] TEST-3: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given provider hanya mengembalikan accepted/queued, Then sistem tidak menyebut status tersebut sebagai delivered ke penerima.
- [ ] TEST-4: Gunakan Queue/Notification/HTTP fake + time travel bila perlu — Given credential/provider belum ditetapkan, Then fitur integrasi nyata belum dianggap siap produksi walaupun unit test lulus.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.


---

## Bagian 13 — Setelan Aplikasi, Audit, dan Kebijakan Operasional

### ISS-13.01 · [Feature] Setelan Identitas & Preferensi Presentasional

**Terkait User Story:** `US-13.01`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `settings-audit`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 9: 9.1–9.6  
**PRD:** §26  
**Workflow:** §18  
**Data Model/Entitas:** `pengaturan`

#### User Story

> **Sebagai** Admin atau Superadmin,  
> **Saya ingin** mengubah identitas instansi/aplikasi, label unit, zona waktu, format tanggal/angka, dan header/footer laporan,  
> **Sehingga** preferensi tampilan dapat berubah tanpa deployment kode.

#### Kontrak Teknis

- **Dependensi:** Pengaturan tersedia.
- **Otorisasi:** `pengaturan:update` (Admin/Superadmin bawaan).
- **Dampak Data:** `pengaturan`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given kunci yang termasuk whitelist presentasional, When diperbarui, Then nilai tersimpan dan cache diperbarui.
- [x] **AC-2:** Given perubahan disimpan, Then audit merekam nilai lama/baru.
- [x] **AC-3:** Given pengguna tanpa permission, Then 403.
- [x] **AC-4:** Given pengguna mencoba mengubah enum/status/permission/aturan bisnis lewat tabel pengaturan, Then sistem menolak karena di luar cakupan.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `pengaturan`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Implementasikan use-case **Setelan Identitas & Preferensi Presentasional** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [x] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengaturan:update` (Admin/Superadmin bawaan).
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Setelan Identitas & Preferensi Presentasional**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [x] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [x] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given kunci yang termasuk whitelist presentasional, When diperbarui, Then nilai tersimpan dan cache diperbarui.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given perubahan disimpan, Then audit merekam nilai lama/baru.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given pengguna tanpa permission, Then 403.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given pengguna mencoba mengubah enum/status/permission/aturan bisnis lewat tabel pengaturan, Then sistem menolak karena di luar cakupan.

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [x] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-13.02 · [Feature] Kebijakan Storage & Saklar Unggah File

**Terkait User Story:** `US-13.02`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `settings-audit`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 9: 9.7–9.9  
**PRD:** §18.9, §26  
**Workflow:** §18  
**Data Model/Entitas:** storage policy

#### User Story

> **Sebagai** Admin atau Superadmin,  
> **Saya ingin** mengatur kebijakan teknis upload dan memantau penggunaan storage,  
> **Sehingga** kapasitas VPS dapat dikendalikan tanpa mengubah persyaratan substantif milik Perencanaan.

#### Kontrak Teknis

- **Dependensi:** Admin/Superadmin autentikasi.
- **Otorisasi:** `pengaturan:update`.
- **Dampak Data:** `pengaturan`, metrik storage read-only, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given `berkas.unggahan_aktif = false`, Then mode file dinonaktifkan global sedangkan tautan/teks tetap dapat dipakai.
- [x] **AC-2:** Given panel storage dibuka, Then menampilkan jumlah file, total bytes, serta jumlah bukti tautan/teks.
- [x] **AC-3:** Given batas default format/ukuran diubah, Then perubahan hanya berfungsi sebagai fallback/kebijakan teknis; persyaratan spesifik tetap milik `jenis_berkas`.
- [x] **AC-4:** Given perubahan dilakukan, Then audit mencatat before/after.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `pengaturan`, metrik storage read-only, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Implementasikan use-case **Kebijakan Storage & Saklar Unggah File** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [x] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengaturan:update`.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Kebijakan Storage & Saklar Unggah File**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [x] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [x] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given `berkas.unggahan_aktif = false`, Then mode file dinonaktifkan global sedangkan tautan/teks tetap dapat dipakai.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given panel storage dibuka, Then menampilkan jumlah file, total bytes, serta jumlah bukti tautan/teks.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given batas default format/ukuran diubah, Then perubahan hanya berfungsi sebagai fallback/kebijakan teknis; persyaratan spesifik tetap milik `jenis_berkas`.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given perubahan dilakukan, Then audit mencatat before/after.

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [x] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-13.03 · [Security] Penelusuran Audit Trail Append-Only

**Terkait User Story:** `US-13.03`  
**Prioritas:** 🔴 P0  
**Story Points:** 8  
**Labels:** `security`, `settings-audit`, `P0`, `frontend`  
**Plan:** Modul 10: 10.1–10.6  
**PRD:** §25  
**Workflow:** §19–20  
**Data Model/Entitas:** `audit_log`

#### User Story

> **Sebagai** Admin, Superadmin, Tim Perencanaan, atau role lain yang memiliki izin,  
> **Saya ingin** mencari dan membaca seluruh peristiwa penting serta percobaan yang ditolak,  
> **Sehingga** alasan suatu perubahan/penolakan dapat direkonstruksi.

#### Kontrak Teknis

- **Dependensi:** Audit log tersedia.
- **Otorisasi:** `audit:read`.
- **Dampak Data:** Read-only `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given filter waktu/aktor/tindakan/objek, When dicari, Then hasil audit yang cocok ditampilkan.
- [ ] **AC-2:** Given aksi permission sensitif, Then detail audit memuat `dasar_izin` sumber allow atau deny pemicu.
- [ ] **AC-3:** Given aksi ditolak karena gate/deny/F1, Then percobaan penting dapat dicatat dengan alasan penolakan.
- [ ] **AC-4:** Given audit telah ditulis, Then tidak tersedia endpoint update/delete.
- [ ] **AC-5:** Given payload audit memuat data sensitif, Then token, secret, password, atau credential tidak boleh disimpan.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Verifikasi query, relasi, indeks, dan eager-loading yang diperlukan; **jangan** membuat mutasi domain baru untuk fitur read-only.

**B. Backend / Domain**
- [ ] Audit log bersifat append-only; tidak ada update/delete endpoint. Mask secret/credential dan simpan `dasar_izin` untuk aksi sensitif.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `audit:read`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan halaman Inertia React untuk **Penelusuran Audit Trail Append-Only** sebagai tampilan read-only/filterable sesuai hak server.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given filter waktu/aktor/tindakan/objek, When dicari, Then hasil audit yang cocok ditampilkan.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given aksi permission sensitif, Then detail audit memuat `dasar_izin` sumber allow atau deny pemicu.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given aksi ditolak karena gate/deny/F1, Then percobaan penting dapat dicatat dengan alasan penolakan.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given audit telah ditulis, Then tidak tersedia endpoint update/delete.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given payload audit memuat data sensitif, Then token, secret, password, atau credential tidak boleh disimpan.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-13.04 · [Feature] Konfigurasi Operasional Notifikasi Eksternal

**Terkait User Story:** `US-13.04`  
**Prioritas:** 🟠 P2  
**Story Points:** 5  
**Labels:** `feature`, `settings-audit`, `P2`, `frontend`, `ops`  
**Plan:** Modul 14: 14.2; Modul 9  
**PRD:** §28.2  
**Workflow:** §22.2  
**Data Model/Entitas:** notification config

#### User Story

> **Sebagai** Admin/Superadmin bersama pengelola infrastruktur,  
> **Saya ingin** mengaktifkan kanal, template, daftar H-minus, dan parameter non-rahasia,  
> **Sehingga** integrasi WA/Email dapat dikelola tanpa menaruh credential di database biasa atau UI.

#### Kontrak Teknis

- **Dependensi:** Pemilik layanan/provider telah dipilih.
- **Otorisasi:** `pengaturan:update` untuk saklar/parameter non-secret; secret melalui environment/secret management.
- **Dampak Data:** `pengaturan` grup notifikasi + konfigurasi environment.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given daftar H-minus default, Then nilai awal adalah `7,3,1` dan dapat dibaca scheduler.
- [ ] **AC-2:** Given credential API, Then credential tidak ditampilkan di UI/log/audit dan disimpan lewat mekanisme secret yang sesuai.
- [ ] **AC-3:** Given provider belum siap, Then saklar kanal dapat dinonaktifkan tanpa mengganggu alur bisnis inti.
- [ ] **AC-4:** Given perubahan parameter non-secret, Then audit menyimpan perubahan tanpa menyalin secret.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `pengaturan` grup notifikasi + konfigurasi environment.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Konfigurasi Operasional Notifikasi Eksternal** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengaturan:update` untuk saklar/parameter non-secret; secret melalui environment/secret management.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Konfigurasi Operasional Notifikasi Eksternal**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

**E. Integration / Operations**
- [ ] Gunakan Laravel queue/worker dan scheduler sesuai Plan; credential provider disimpan di environment/secret manager, bukan `pengaturan` biasa.
- [ ] Log error provider tanpa token/secret; dukung fake/mock provider untuk automated test.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given daftar H-minus default, Then nilai awal adalah `7,3,1` dan dapat dibaca scheduler.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given credential API, Then credential tidak ditampilkan di UI/log/audit dan disimpan lewat mekanisme secret yang sesuai.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given provider belum siap, Then saklar kanal dapat dinonaktifkan tanpa mengganggu alur bisnis inti.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given perubahan parameter non-secret, Then audit menyimpan perubahan tanpa menyalin secret.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).


---

## Bagian 14 — Penutupan Tahunan & Koreksi Pasca-Penutupan

### ISS-14.01 · [Feature] Penutupan Resmi Jadwal Tahunan

**Terkait User Story:** `US-14.01`  
**Prioritas:** 🔴 P0  
**Story Points:** 5  
**Labels:** `feature`, `correction`, `P0`, `audit-sensitive`, `frontend`  
**Plan:** Modul 3: 3.9  
**PRD:** §12.6  
**Workflow:** §13  
**Data Model/Entitas:** `jadwal_tahunan`

#### User Story

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** menutup siklus tahunan secara resmi,  
> **Sehingga** data tahun tersebut terkunci dari mutasi normal dan hanya dapat dikoreksi melalui jalur pembukaan resmi.

#### Kontrak Teknis

- **Dependensi:** Jadwal aktif; tanggal/otoritas penutupan memenuhi kebijakan.
- **Otorisasi:** `jadwal:tutup` (sensitif).
- **Dampak Data:** `jadwal_tahunan`, `audit_log`.
- **Rule:** Istilah yang benar adalah terkunci selama status ditutup; bukan 'tidak dapat dimutasi selamanya', karena jalur koreksi resmi tetap ada.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given penutupan dikonfirmasi, When commit, Then status menjadi `ditutup` dan `closed_at` terisi.
- [ ] **AC-2:** Given jadwal ditutup, When mutasi normal RA, kegiatan, klaim, bukti, atau pengukuran dicoba, Then ditolak.
- [ ] **AC-3:** Given data perlu dikoreksi pasca-penutupan, Then perubahan tidak dilakukan langsung; harus melalui `jadwal:buka_kembali` dengan sesi koreksi.
- [ ] **AC-4:** Given penutupan adalah aksi sensitif, Then audit menyimpan aktor dan `dasar_izin`.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `jadwal_tahunan`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Saat status `ditutup`, blok mutasi normal. Koreksi hanya melalui sesi `jadwal:buka_kembali`; jangan menerapkan 'freeze selamanya' yang meniadakan jalur koreksi resmi.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `jadwal:tutup` (sensitif).
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Penutupan Resmi Jadwal Tahunan**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given penutupan dikonfirmasi, When commit, Then status menjadi `ditutup` dan `closed_at` terisi.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given jadwal ditutup, When mutasi normal RA, kegiatan, klaim, bukti, atau pengukuran dicoba, Then ditolak.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given data perlu dikoreksi pasca-penutupan, Then perubahan tidak dilakukan langsung; harus melalui `jadwal:buka_kembali` dengan sesi koreksi.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given penutupan adalah aksi sensitif, Then audit menyimpan aktor dan `dasar_izin`.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-14.02 · [Feature] Pembukaan Kembali Jadwal untuk Sesi Koreksi Perencanaan

**Terkait User Story:** `US-14.02`  
**Prioritas:** 🔴 P0  
**Story Points:** 8  
**Labels:** `feature`, `correction`, `P0`, `audit-sensitive`, `frontend`  
**Plan:** Modul 3: 3.9  
**PRD:** §12.6  
**Workflow:** §13  
**Data Model/Entitas:** correction session

#### User Story

> **Sebagai** Tim Perencanaan atau Superadmin,  
> **Saya ingin** membuka sesi koreksi pasca-penutupan dengan lingkup dan deadline eksplisit,  
> **Sehingga** koreksi historis dapat dilakukan terkendali tanpa menghapus tanggal penutupan asli.

#### Kontrak Teknis

- **Dependensi:** Jadwal berstatus `ditutup`; lingkup, alasan, dan durasi koreksi ditetapkan.
- **Otorisasi:** `jadwal:buka_kembali` (sensitif).
- **Dampak Data:** `jadwal_tahunan.koreksi_*`, `lingkup_koreksi`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given alasan, `koreksi_mulai`, `koreksi_sampai`, dan `lingkup_koreksi` valid, When dibuka, Then status kembali aktif untuk sesi koreksi dan tanggal `penutupan` asli tidak ditimpa.
- [ ] **AC-2:** Given objek tidak termasuk `lingkup_koreksi`, When Perencanaan mencoba memutasi, Then ditolak.
- [ ] **AC-3:** Given waktu di luar sesi koreksi, When mutasi koreksi dilakukan, Then ditolak.
- [ ] **AC-4:** Given pembukaan tahun dilakukan, Then **hak PIC tidak otomatis terbuka**.
- [ ] **AC-5:** Given sesi selesai, When jadwal ditutup kembali, Then status menjadi `ditutup` dan seluruh peristiwa koreksi tetap teraudit.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `jadwal_tahunan.koreksi_*`, `lingkup_koreksi`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Buka tahun membuat sesi koreksi Perencanaan dengan `koreksi_mulai`, `koreksi_sampai`, `lingkup_koreksi`, alasan, dan audit; tanggal penutupan asli tidak ditimpa.
- [ ] Jangan memberi akses PIC otomatis hanya karena status jadwal kembali aktif.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `jadwal:buka_kembali` (sensitif).
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pembukaan Kembali Jadwal untuk Sesi Koreksi Perencanaan**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given alasan, `koreksi_mulai`, `koreksi_sampai`, dan `lingkup_koreksi` valid, When dibuka, Then status kembali aktif untuk sesi koreksi dan tanggal `penutupan` asli tidak ditimpa.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given objek tidak termasuk `lingkup_koreksi`, When Perencanaan mencoba memutasi, Then ditolak.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given waktu di luar sesi koreksi, When mutasi koreksi dilakukan, Then ditolak.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given pembukaan tahun dilakukan, Then **hak PIC tidak otomatis terbuka**.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given sesi selesai, When jadwal ditutup kembali, Then status menjadi `ditutup` dan seluruh peristiwa koreksi tetap teraudit.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-14.03 · [Feature] Pembukaan Jendela PIC di Dalam Sesi Koreksi

**Terkait User Story:** `US-14.03`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `correction`, `P1`, `frontend`  
**Plan:** Modul 3: 3.12  
**PRD:** §12.3, §12.6  
**Workflow:** §13  
**Data Model/Entitas:** PIC windows

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** membuka jendela kerja PIC secara terpisah di dalam sesi koreksi,  
> **Sehingga** PIC hanya dapat berpartisipasi pada koreksi yang memang diizinkan.

#### Kontrak Teknis

- **Dependensi:** Sesi koreksi tahun aktif; objek/unit berada dalam lingkup koreksi.
- **Otorisasi:** `jadwal:update` untuk revisi jendela PIC resmi.
- **Dampak Data:** `jadwal_tahunan`/`jadwal_periode`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given sesi koreksi aktif, When Perencanaan membuka jendela PIC dengan alasan dan batas baru, Then deadline baru wajib berada di dalam sesi koreksi.
- [ ] **AC-2:** Given PIC bekerja dalam jendela baru, Then scope unit, PIC efektif, grant, deny, status record, dan gate lain tetap diperiksa.
- [ ] **AC-3:** Given tahun dibuka tetapi jendela PIC belum dibuka, When PIC mencoba mutasi, Then ditolak.
- [ ] **AC-4:** Given jendela koreksi berakhir, Then akses PIC kembali tertutup tanpa perlu mengubah histori penutupan asli.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `jadwal_tahunan`/`jadwal_periode`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Akses PIC pada koreksi memerlukan aksi `jadwal:update` terpisah untuk jendela resmi yang berada dalam sesi koreksi dan tetap memeriksa grant/PIC efektif/deny/status.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `jadwal:update` untuk revisi jendela PIC resmi.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pembukaan Jendela PIC di Dalam Sesi Koreksi**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given sesi koreksi aktif, When Perencanaan membuka jendela PIC dengan alasan dan batas baru, Then deadline baru wajib berada di dalam sesi koreksi.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given PIC bekerja dalam jendela baru, Then scope unit, PIC efektif, grant, deny, status record, dan gate lain tetap diperiksa.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given tahun dibuka tetapi jendela PIC belum dibuka, When PIC mencoba mutasi, Then ditolak.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given jendela koreksi berakhir, Then akses PIC kembali tertutup tanpa perlu mengubah histori penutupan asli.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).


---

## Bagian 15 — Kesiapan Operasional, UAT, dan Penggunaan Pertama

### ISS-15.01 · [UAT] Verifikasi Data Awal sebelum Penggunaan Pertama

**Terkait User Story:** `US-15.01`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `uat`, `release-uat`, `P1`, `backend`  
**Plan:** S.1, P.4  
**PRD:** Pembuka, §12.6, §32  
**Workflow:** §15  
**Data Model/Entitas:** initial data

#### User Story

> **Sebagai** Tim Perencanaan bersama PM,  
> **Saya ingin** memverifikasi daftar data awal sebelum jadwal pertama diaktifkan,  
> **Sehingga** sistem tidak go-live dengan master atau histori wajib yang belum jelas.

#### Kontrak Teknis

- **Dependensi:** Tahun/periode penggunaan pertama telah ditetapkan PM/Perencanaan.
- **Otorisasi:** Akses administratif/substantif sesuai data yang diverifikasi.
- **Dampak Data:** Seed/config awal, master Renstra/indikator/unit/PIC, data historis wajib.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given tahun penggunaan pertama ditetapkan, Then daftar periode, indikator, unit/PIC, PK/target, dan data historis wajib harus didokumentasikan.
- [ ] **AC-2:** Given data belum lengkap, When aktivasi pertama dicoba, Then gate aplikasi dan checklist operasional harus menunjukkan kekurangan.
- [ ] **AC-3:** Given data uji dan data konfigurasi produksi, Then keduanya dipisahkan dan seed produksi tidak membawa fixture testing.
- [ ] **AC-4:** Given backfill dibutuhkan, Then menggunakan jalur backfill resmi dan bukan query database manual tanpa jejak.
- [ ] **AC-5 (Q31):** Given data awal UAT disiapkan, Then tersedia minimal satu akun uji untuk masing-masing dari lima role resmi; skenario authorization PIC yang bergantung preset/eligibility tidak dianggap final sampai keputusan OPEN ditutup.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Tidak menambah skema domain tanpa keputusan eksplisit; simpan artefak/checklist penerimaan di mekanisme dokumentasi proyek yang telah ditetapkan.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Verifikasi Data Awal sebelum Penggunaan Pertama** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [ ] Tidak menciptakan permission baru hanya untuk checklist governance/UAT kecuali PRD secara eksplisit menambahkannya.

#### Automated Tests / Verification

- [ ] UAT-1: Verifikasi manual/acceptance — Given tahun penggunaan pertama ditetapkan, Then daftar periode, indikator, unit/PIC, PK/target, dan data historis wajib harus didokumentasikan.
- [ ] UAT-2: Verifikasi manual/acceptance — Given data belum lengkap, When aktivasi pertama dicoba, Then gate aplikasi dan checklist operasional harus menunjukkan kekurangan.
- [ ] UAT-3: Verifikasi manual/acceptance — Given data uji dan data konfigurasi produksi, Then keduanya dipisahkan dan seed produksi tidak membawa fixture testing.
- [ ] UAT-4: Verifikasi manual/acceptance — Given backfill dibutuhkan, Then menggunakan jalur backfill resmi dan bukan query database manual tanpa jejak.
- [ ] UAT-5 (Q31): Verifikasi akun UAT tersedia untuk `superadmin`, `admin`, `perencanaan`, `pic`, `pimpinan`, dan `pegawai`; catat blocker bila preset/eligibility PIC belum diputuskan.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.

### ISS-15.02 · [Infrastructure] Kesiapan Infrastruktur dan Layanan Eksternal

**Terkait User Story:** `US-15.02`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `infrastructure`, `release-uat`, `P1`, `backend`, `ops`  
**Plan:** P.1–P.4  
**PRD:** §6, §28, §34  
**Workflow:** §22  
**Data Model/Entitas:** operational dependencies

#### User Story

> **Sebagai** PM/pengelola infrastruktur,  
> **Saya ingin** memastikan seluruh layanan pendukung siap sebelum penerimaan produksi,  
> **Sehingga** fitur yang lulus kode tidak dianggap siap produksi tanpa dependency operasional.

#### Kontrak Teknis

- **Dependensi:** Owner infrastruktur/layanan telah ditetapkan.
- **Otorisasi:** Proses operasional, bukan permission aplikasi tunggal.
- **Dampak Data:** Konfigurasi Keycloak, domain/HTTPS, storage, backup/restore, queue/worker/scheduler, WA/Email.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given rilis akan masuk UAT/produksi, Then realm/client Keycloak, domain HTTPS, database/storage, queue worker, scheduler, dan backup/pemulihan harus memiliki owner.
- [ ] **AC-2:** Given notifikasi eksternal akan diaktifkan, Then provider, template, sumber kontak, jam/zona waktu, dan credential tersedia.
- [ ] **AC-3:** Given backup tersedia, Then prosedur pemulihan harus dapat diuji/dibuktikan sesuai kebijakan operasional.
- [ ] **AC-4:** Given dependency eksternal belum tersedia, Then status rilis mencatatnya sebagai blocker/risiko dan tidak diasumsikan selesai.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Tidak menambah skema domain tanpa keputusan eksplisit; simpan artefak/checklist penerimaan di mekanisme dokumentasi proyek yang telah ditetapkan.

**B. Backend / Domain**
- [ ] Pisahkan readiness aplikasi dari readiness operasional: Keycloak, HTTPS, DB/storage, backup/restore, queue worker, scheduler, WA/Email, monitoring, dan owner harus diverifikasi.
- [ ] Jangan menandai produksi siap hanya karena automated test aplikasi lulus.

**C. Authorization & Audit**
- [ ] Tidak menciptakan permission baru hanya untuk checklist governance/UAT kecuali PRD secara eksplisit menambahkannya.

**E. Integration / Operations**
- [ ] Dokumentasikan owner, environment, health check, backup, restore test, queue worker, scheduler, storage, domain/HTTPS, dan dependency eksternal.
- [ ] Sediakan checklist readiness UAT/production yang dapat ditandatangani/diterima pihak berwenang.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given rilis akan masuk UAT/produksi, Then realm/client Keycloak, domain HTTPS, database/storage, queue worker, scheduler, dan backup/pemulihan harus memiliki owner.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given notifikasi eksternal akan diaktifkan, Then provider, template, sumber kontak, jam/zona waktu, dan credential tersedia.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given backup tersedia, Then prosedur pemulihan harus dapat diuji/dibuktikan sesuai kebijakan operasional.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given dependency eksternal belum tersedia, Then status rilis mencatatnya sebagai blocker/risiko dan tidak diasumsikan selesai.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.

### ISS-15.03 · [UAT] Tahapan UAT, Penerimaan, dan Produksi yang Terpisah

**Terkait User Story:** `US-15.03`  
**Prioritas:** 🟡 P1  
**Story Points:** 3  
**Labels:** `uat`, `release-uat`, `P1`, `backend`  
**Plan:** P.4  
**PRD:** Pembuka, §32  
**Workflow:** —  
**Data Model/Entitas:** UAT/acceptance records

#### User Story

> **Sebagai** PM dan Tim Perencanaan,  
> **Saya ingin** memisahkan status siap diuji, dievaluasi, diterima, dan diproduksikan,  
> **Sehingga** target tanggal pengembangan tidak disalahartikan sebagai persetujuan go-live.

#### Kontrak Teknis

- **Dependensi:** Build tersedia untuk pengujian.
- **Otorisasi:** Governance proyek.
- **Dampak Data:** Catatan hasil uji, keputusan penerimaan, daftar perbaikan.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given fitur selesai secara teknis, Then statusnya dapat menjadi siap diuji tanpa otomatis dianggap diterima.
- [ ] **AC-2:** Given UAT menemukan masalah, Then hasil masuk evaluasi/perbaikan sampai acceptance criteria terpenuhi.
- [ ] **AC-3:** Given tanggal target integrasi notifikasi sebelum 9 November 2026, Then tanggal tersebut tidak otomatis menjadi tanggal go-live.
- [ ] **AC-4:** Given tanggal penerimaan/produksi belum ditetapkan, Then dokumen tidak mengarang tanggal atau penerima UAT.
- [ ] **AC-5:** Given acceptance disetujui, Then pihak penerima, tanggal, build/versi, dan catatan keputusan dicatat.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Tidak menambah skema domain tanpa keputusan eksplisit; simpan artefak/checklist penerimaan di mekanisme dokumentasi proyek yang telah ditetapkan.

**B. Backend / Domain**
- [ ] Pisahkan status siap diuji, evaluasi/perbaikan, diterima, dan produksi. Tanggal target integrasi bukan otomatis tanggal go-live.

**C. Authorization & Audit**
- [ ] Tidak menciptakan permission baru hanya untuk checklist governance/UAT kecuali PRD secara eksplisit menambahkannya.

#### Automated Tests / Verification

- [ ] UAT-1: Verifikasi manual/acceptance — Given fitur selesai secara teknis, Then statusnya dapat menjadi siap diuji tanpa otomatis dianggap diterima.
- [ ] UAT-2: Verifikasi manual/acceptance — Given UAT menemukan masalah, Then hasil masuk evaluasi/perbaikan sampai acceptance criteria terpenuhi.
- [ ] UAT-3: Verifikasi manual/acceptance — Given tanggal target integrasi notifikasi sebelum 9 November 2026, Then tanggal tersebut tidak otomatis menjadi tanggal go-live.
- [ ] UAT-4: Verifikasi manual/acceptance — Given tanggal penerimaan/produksi belum ditetapkan, Then dokumen tidak mengarang tanggal atau penerima UAT.
- [ ] UAT-5: Verifikasi manual/acceptance — Given acceptance disetujui, Then pihak penerima, tanggal, build/versi, dan catatan keputusan dicatat.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.


---


## 5. Q31 — Readiness dan Decision Gates untuk User Issues

### 5.1 Issue yang Dapat Berjalan Sekarang

- `ISS-01.03` — katalog/Assign Peran lima role.
- `ISS-01.04`/`ISS-01.05`/`ISS-01.06` — grant, deny, explain-permission tetap generic.
- Issue domain RA/Pengukuran/Kegiatan dapat dikembangkan dengan resolver permission + scope + assignment + business guard.
- Notification issue dapat memakai PIC efektif dari assignment/work item.
- Seeder development dapat menyediakan user role PIC dan grant eksplisit untuk testing jalur scoped.

### 5.2 Issue/Subtask yang Decision-Gated

- `ISS-01.07` — preset permission bawaan role PIC.
- `ISS-04.01` — eligibility role terhadap `penanggung_jawab`.
- Migrasi akun existing Pegawai → PIC.
- Behavior assignment aktif saat role user berubah.
- Assertion default permission PIC pada dashboard/audit/read.

### 5.3 Syarat Menutup Decision Gate

Decision gate dianggap selesai bila:

1. ada keputusan tertulis dari LLDIKTI/Tim Perencanaan;
2. Keputusan Penyelarasan diperbarui;
3. PRD, Data Model, Workflow, Plan, User Stories, dan User Issues konsisten;
4. seeder/fixture/test diperbarui;
5. tidak ada permission unit-scoped yang berubah menjadi global tanpa keputusan eksplisit;
6. akun UAT lima role final dan skenario PIC operasional telah didefinisikan.

---

## 6. Matriks Coverage User Story → User Issue

| User Story | User Issue | Plan | PRD | Workflow | Status Coverage |
|---|---|---|---|---|---|
| `US-01.01` | `ISS-01.01` | Modul 1: 1.2–1.3 | §6–7 | §1, §19 | ✅ 1:1 |
| `US-01.02` | `ISS-01.02` | Modul 1: 1.4, 1.18 | §7.7 | §21 | ✅ 1:1 |
| `US-01.03` | `ISS-01.03` | Modul 1: 1.6–1.7, 1.13 | §7.1–7.5 | §21 | ✅ 1:1 + Q31 |
| `US-01.04` | `ISS-01.04` | Modul 1: 1.8, 1.14 | §7.1–7.5 | §19, §21 | ✅ 1:1 |
| `US-01.05` | `ISS-01.05` | Modul 1: 1.9, 1.15 | §7.1–7.5 | §19, §21 | ✅ 1:1 |
| `US-01.06` | `ISS-01.06` | Modul 1: 1.16 | §7.4–7.5 | §19, §21 | ✅ 1:1 |
| `US-01.07` | `ISS-01.07` | Modul 1: 1.22–1.23 | §7.3–7.5, §25 | §19, §21–23 | ✅ 1:1 + decision gate PIC |
| `US-02.01` | `ISS-02.01` | Modul 2: 2.16–2.19 | §9 | §3 | ✅ 1:1 |
| `US-02.02` | `ISS-02.02` | Modul 2: 2.1–2.2, 2.18 | §10 | §2–3 | ✅ 1:1 |
| `US-02.03` | `ISS-02.03` | Modul 2: 2.3–2.4, 2.13 | §10.2–10.5 | §2, §14 | ✅ 1:1 |
| `US-02.04` | `ISS-02.04` | Modul 2: 2.5–2.7 | §11 | §2 | ✅ 1:1 |
| `US-02.05` | `ISS-02.05` | Modul 2: 2.8–2.9 | §11, §25 | §14 | ✅ 1:1 |
| `US-02.06` | `ISS-02.06` | Modul 2: 2.14–2.15 | §17 | §6 | ✅ 1:1 |
| `US-02.07` | `ISS-02.07` | Modul 2: 2.10 | §11–12, §17 | §5–6 | ✅ 1:1 |
| `US-02.08` | `ISS-02.08` | Modul 2: 2.11, 2.20 | §10.6, §14.10 | §3, §5 | ✅ 1:1 |
| `US-02.09` | `ISS-02.09` | Modul 2: 2.12 | §12.5–12.7 | §13–14 | ✅ 1:1 |
| `US-03.01` | `ISS-03.01` | Modul 3: 3.1–3.4 | §12.1–12.3 | §4 | ✅ 1:1 |
| `US-03.02` | `ISS-03.02` | Modul 3: 3.5–3.7 | §12.4–12.5 | §5 | ✅ 1:1 |
| `US-03.03` | `ISS-03.03` | Modul 3: 3.8 | §12.5–12.7 | §13–14 | ✅ 1:1 |
| `US-03.04` | `ISS-03.04` | Modul 3: 3.7–3.8 | §10.5, §12.5 | §14 | ✅ 1:1 |
| `US-03.05` | `ISS-03.05` | Modul 3: 3.11; Modul 5: 5.17 | §12.6, §17.6 | §15 | ✅ 1:1 |
| `US-03.06` | `ISS-03.06` | Modul 3: 3.12 | §12.3, §12.6 | §13 | ✅ 1:1 |
| `US-04.01` | `ISS-04.01` | Modul 4: 4.1–4.5 | §13 | §6, §23 | ✅ 1:1 + decision gate eligibility |
| `US-05.01` | `ISS-05.01` | Modul 11: 11.1–11.2, 11.5–11.6 | §14 | §7 | ✅ 1:1 |
| `US-05.02` | `ISS-05.02` | Modul 13: 13.3–13.5 | §18 | §10 | ✅ 1:1 |
| `US-05.03` | `ISS-05.03` | Modul 11: 11.1, 11.3 | §14.6–14.9, §18.11 | §7, §20 | ✅ 1:1 |
| `US-05.04` | `ISS-05.04` | Modul 11: 11.3 | §7.6, §14.6 | §7, §20 | ✅ 1:1 |
| `US-05.05` | `ISS-05.05` | Modul 11: 11.3 | §7.6, §14.6 | §7, §20 | ✅ 1:1 |
| `US-05.06` | `ISS-05.06` | Modul 11: 11.7 | §14.6 | §7, §13 | ✅ 1:1 |
| `US-06.01` | `ISS-06.01` | Modul 12: 12.1–12.2 | §15.1–15.4 | §8 | ✅ 1:1 |
| `US-06.02` | `ISS-06.02` | Modul 12: 12.6–12.9 | §16 | §9 | ✅ 1:1 |
| `US-06.03` | `ISS-06.03` | Modul 12: 12.11; Modul 13 | §15.6, §18 | §8–10 | ✅ 1:1 |
| `US-06.04` | `ISS-06.04` | Modul 12: 12.3 | §15.2–15.5 | §8 | ✅ 1:1 |
| `US-06.05` | `ISS-06.05` | Modul 12: 12.4 | §15.4 | §8 | ✅ 1:1 |
| `US-06.06` | `ISS-06.06` | Modul 12: 12.10 | §16.5 | §9, §13 | ✅ 1:1 |
| `US-06.07` | `ISS-06.07` | Modul 13: 13.6 | §18.8 | §10 | ✅ 1:1 |
| `US-07.01` | `ISS-07.01` | Modul 5: 5.2–5.4, 5.13–5.15 | §17, §19–20 | §11 | ✅ 1:1 |
| `US-07.02` | `ISS-07.02` | Modul 13: 13.3–13.5 | §18 | §10–11 | ✅ 1:1 |
| `US-07.03` | `ISS-07.03` | Modul 5: 5.5, 5.16 | §18.11, §19.4 | §11–12, §20 | ✅ 1:1 |
| `US-07.04` | `ISS-07.04` | Modul 5: 5.4–5.8 | §19.3 | §11–12 | ✅ 1:1 |
| `US-07.05` | `ISS-07.05` | Modul 3: 3.11; Modul 5: 5.17 | §12.6, §17.6, §19 | §15 | ✅ 1:1 |
| `US-07.06` | `ISS-07.06` | Modul 5: 5.13–5.16 | §17.4, §19.4 | §11 | ✅ 1:1 |
| `US-08.01` | `ISS-08.01` | Modul 5: 5.6–5.8; Modul 6: 6.1 | §7.6, §19 | §12, §20 | ✅ 1:1 |
| `US-08.02` | `ISS-08.02` | Modul 5: 5.9; Modul 6: 6.2 | §7.6, §19 | §12, §20 | ✅ 1:1 |
| `US-08.03` | `ISS-08.03` | Modul 5: 5.10; Modul 6: 6.3–6.6 | §19.5, §21 | §13, §16 | ✅ 1:1 |
| `US-09.01` | `ISS-09.01` | Modul 8: 8.5 | §22 | §17 | ✅ 1:1 |
| `US-09.02` | `ISS-09.02` | Modul 6: 6.4–6.6 | §21 | §16 | ✅ 1:1 |
| `US-10.01` | `ISS-10.01` | Modul 7: 7.1–7.8 | §23 | §1, §14 | ✅ 1:1 |
| `US-10.02` | `ISS-10.02` | Modul 8: 8.1, 8.3 | §22.4, §24 | §17 | ✅ 1:1 |
| `US-10.03` | `ISS-10.03` | Modul 8: 8.2, 8.4 | §24 | §17 | ✅ 1:1 |
| `US-10.04` | `ISS-10.04` | Modul 8: 8.4; P.4 | §24, §32 | §17 | ✅ 1:1 |
| `US-11.01` | `ISS-11.01` | Modul 13: 13.1 | §18.2–18.7 | §10 | ✅ 1:1 |
| `US-11.02` | `ISS-11.02` | Modul 13: 13.2, 13.5 | §18.4–18.9 | §10 | ✅ 1:1 |
| `US-11.03` | `ISS-11.03` | Modul 13: 13.2–13.5 | §18.4–18.6 | §10 | ✅ 1:1 |
| `US-11.04` | `ISS-11.04` | Modul 13: 13.6 | §18.8 | §10 | ✅ 1:1 |
| `US-11.05` | `ISS-11.05` | Modul 13: 13.4 | §18.11 | §10 | ✅ 1:1 |
| `US-11.06` | `ISS-11.06` | Modul 13: 13.7 | §18.5–18.7 | §10 | ✅ 1:1 |
| `US-12.01` | `ISS-12.01` | Modul 14: 14.1 | §28.1 | §22.1 | ✅ 1:1 |
| `US-12.02` | `ISS-12.02` | Modul 14: 14.1 | §28.1 | §22.1 | ✅ 1:1 |
| `US-12.03` | `ISS-12.03` | Modul 14: 14.2 | §28.2 | §22.2 | ✅ 1:1 |
| `US-12.04` | `ISS-12.04` | Modul 14: 14.2 | §28.2 | §22.2 | ✅ 1:1 |
| `US-12.05` | `ISS-12.05` | Modul 14: 14.2 | §28.2 | §22.2 | ✅ 1:1 |
| `US-12.06` | `ISS-12.06` | Modul 14: 14.2 | §28.2 | §22.2 | ✅ 1:1 |
| `US-12.07` | `ISS-12.07` | Modul 14: 14.2 | §28.2 | §22.2 | ✅ 1:1 |
| `US-13.01` | `ISS-13.01` | Modul 9: 9.1–9.6 | §26 | §18 | ✅ 1:1 |
| `US-13.02` | `ISS-13.02` | Modul 9: 9.7–9.9 | §18.9, §26 | §18 | ✅ 1:1 |
| `US-13.03` | `ISS-13.03` | Modul 10: 10.1–10.6 | §25 | §19–20 | ✅ 1:1 |
| `US-13.04` | `ISS-13.04` | Modul 14: 14.2; Modul 9 | §28.2 | §22.2 | ✅ 1:1 |
| `US-14.01` | `ISS-14.01` | Modul 3: 3.9 | §12.6 | §13 | ✅ 1:1 |
| `US-14.02` | `ISS-14.02` | Modul 3: 3.9 | §12.6 | §13 | ✅ 1:1 |
| `US-14.03` | `ISS-14.03` | Modul 3: 3.12 | §12.3, §12.6 | §13 | ✅ 1:1 |
| `US-15.01` | `ISS-15.01` | S.1, P.4 | Pembuka, §12.6, §32 | §15 | ✅ 1:1 |
| `US-15.02` | `ISS-15.02` | P.1–P.4 | §6, §28, §34 | §22 | ✅ 1:1 |
| `US-15.03` | `ISS-15.03` | P.4 | Pembuka, §32 | — | ✅ 1:1 |

## 7. Checklist Sebelum Issue Dipindahkan ke GitHub Issues

- [ ] Pastikan title/ID tidak berubah sehingga traceability tetap 1:1.
- [ ] Tambahkan assignee sesuai pembagian tim setelah owner nyata ditentukan; jangan mengarang nama PIC developer di dokumen spesifikasi.
- [ ] Tambahkan milestone/sprint berdasarkan dependency Plan, bukan hanya urutan nomor issue.
- [ ] Untuk issue P0, dependency harus selesai atau minimal memiliki contract/stub yang dapat dites sebelum implementasi downstream dimulai.
- [ ] Jangan menutup issue hanya karena UI terlihat selesai; test server, audit, constraint, dan failure path harus lulus.
- [ ] Perubahan requirement selama coding harus terlebih dahulu disinkronkan ke dokumen sumber sebelum Acceptance Criteria issue diubah.
- [ ] UAT/operational issue hanya dapat ditutup dengan bukti penerimaan/readiness yang sesuai, bukan hasil unit test semata.

- [ ] Bila issue menyentuh role PIC, pastikan tidak ada hardcode authorization `role === 'pic'`; gunakan resolver permission efektif.
- [ ] Bila issue bergantung pada preset PIC atau eligibility `penanggung_jawab`, tandai BLOCKED/OPEN sampai keputusan tertulis tersedia.
- [ ] Pastikan terminology tabel sesuai Data Model: `user_permission_granted` dan `user_permission_denials`.

---

**Catatan:** Dokumen ini tidak menetapkan endpoint URL, nama class/service, provider WhatsApp, jam pengiriman, tanggal go-live, pihak penerima UAT, **preset permission role PIC, eligibility `penanggung_jawab`, mapping Pegawai→PIC, atau dampak perubahan role terhadap assignment aktif** yang belum disahkan. Detail tersebut harus mengikuti keputusan teknis/bisnis/operasional resmi berikutnya dan tidak boleh diasumsikan oleh developer.
