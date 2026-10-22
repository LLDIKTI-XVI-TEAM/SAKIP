# USER ISSUES — SAKIP LLDIKTI WILAYAH XVI

> **Klarifikasi final LLDIKTI Wilayah XVI — 24 September 2026**  
> Bagian ini adalah kontrak terbaru dan **menggantikan keputusan Q31 atau teks lama yang bertentangan**. Role bawaan SAKIP berjumlah **lima**: `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`. **PIC bukan role sistem**; PIC adalah konteks operasional yang dibentuk oleh penugasan dan grant unit. Detail keputusan dicatat sebagai **Q32** pada dokumen Keputusan Penyelarasan.


**Status:** Revisi Q32 — 24 September 2026. Seluruh issue mengikuti jawaban resmi LLDIKTI terbaru; Q31 hanya histori dan tidak lagi menjadi kontrak implementasi.

**Pembaruan 13 Oktober 2026 (Q38–Q51):** onboarding SSO langsung aktif tanpa peran (Q38, ISS-01.08), keputusan rapat asistensi Q39–Q46, dan analisis dokumen resmi 2026 Q47–Q51 diselaraskan. Issue baru: ISS-02.10, ISS-02.11, ISS-02.12, ISS-05.07, ISS-06.08, ISS-10.05, ISS-13.05, dan ISS-13.06; ISS-11.06 dibatalkan Q44 dan ISS-13.02 digantikan ISS-13.06. Issue yang sudah diimplementasikan diberi catatan Q tanpa menulis ulang AC; issue yang belum dikerjakan diperbarui langsung dengan rujukan Q. Q38–Q51 adalah keputusan requirement, bukan bukti implementasi.

**Pembaruan 14–17 Oktober 2026 (Q52–Q58):** penutupan resmi jadwal wajib alasan (Q52, ISS-14.01), penolakan penurunan superadmin aktif terakhir (Q53, ISS-01.08), unit berizin untuk detail Rencana Aksi dan Kegiatan (Q54, ISS-06.01), draf/token/pembekuan Rekomendasi Pimpinan (Q55, ISS-09.01), Admin memegang `audit:read` (Q56, ISS-13.03), jendela reviu dalam revisi resmi jendela PIC (Q57, ISS-03.06), dan penyamaran kunci rahasia pada penulisan audit (Q58, Issue #122). Keputusan ini dibawa PR issue masing-masing; tidak ada issue baru. Tenggat WA/email pada Q4 digantikan Q46 §46.3: ISS-12.01–ISS-12.07 dan ISS-13.04 mengikuti rencana sprint setelah Tahap 2.

## 1. Tujuan

Dokumen ini menerjemahkan seluruh User Story menjadi **development issue yang executable**. Setiap issue mempertahankan kontrak bisnis User Story namun menambahkan pekerjaan teknis yang perlu diselesaikan pada persistence, backend/domain, authorization/audit, frontend, testing, dan operasi/integrasi bila relevan.

## 2. Hasil Review File User Issues Sebelumnya

- File sebelumnya memiliki **54 issue** dan masih mengikuti User Stories lama. Baseline baru memiliki **74 User Stories**, sehingga terdapat capability yang belum memiliki issue.
- Technical task lama terlalu sering memakai pola generik `Database / Backend middleware / Frontend React`, sehingga beberapa task salah kategori—contohnya login publik diperlakukan seperti route RBAC, cron dianggap middleware user, dan private disk dianggap migration database.
- Issue lama belum cukup menangkap kontrak kritis: F1/F2 berdasarkan `*_versi.diajukan_by`, immutable versioning, snapshot correction, backfill, revisi jendela PIC, evidence freeze, append-only correction, notification idempotency, serta readiness/UAT.
- Dokumen ini **menggantikan** pola tersebut dengan 1 issue untuk 1 User Story serta test traceability per Acceptance Criteria.
- Penyelarasan Q31 **tidak menambah issue baru di luar 74 issue 1:1**. Dampaknya diserap ke issue akses/PIC yang relevan (`ISS-01.03`, `ISS-01.04`, `ISS-01.06`, `ISS-01.07`, `ISS-04.01`, issue RA/Pengukuran/Notifikasi terkait PIC, `ISS-10.01`, dan `ISS-15.01`) serta ke aturan global. Requirement yang masih OPEN tidak diubah menjadi AC final.
- **Hitungan terbaru (13 Oktober 2026):** dokumen memuat **83 issue 1:1** dengan 83 User Stories: 74 issue baseline, `ISS-01.08` (Q38), dan delapan issue baru `ISS-02.10`, `ISS-02.11`, `ISS-02.12` (Q39, Q40, Q50), `ISS-05.07` (Q41, Q47), `ISS-06.08` (Q42), `ISS-10.05` (Q48), `ISS-13.05` (Q43), serta `ISS-13.06` (Q44). `ISS-11.06` dibatalkan Q44 dan tetap dihitung sebagai histori.

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

Centang `[x]` pada Acceptance Criteria dan Automated Tests adalah **bukti implementasi dan test yang ditambahkan oleh PR yang bersangkutan**, bukan status rencana, sehingga jumlah centang tidak dapat dipakai menghitung sisa pekerjaan. Per 17 Oktober 2026, sebelas issue berikut sudah merged tetapi belum memperoleh centang: ISS-01.01, ISS-01.03, ISS-01.04, ISS-01.06, ISS-01.07, ISS-02.02, ISS-02.03, ISS-02.04, ISS-02.08, ISS-03.01 (sebagian), dan ISS-04.01; statusnya mengikuti issue dan PR GitHub masing-masing sampai dicentang lewat PR dokumen yang menyebut nama test bukti. ISS yang kodenya sudah ada di `development` tanpa issue GitHub (ISS-02.05, ISS-07.02, ISS-11.02–ISS-11.05) ditangani issue traceability tersendiri.

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
- onboarding SSO aktif + tanpa role (Q38 menggantikan status nonaktif Q32);
- logout lokal default, logout SSO terpisah;
- Jadwal pertama 2026, TW I–III periode lampau, TW IV normal (Q46 §46.1 butir 2; semula Q32: TW I–II lampau, TW III–IV normal), tanpa `is_backfill`;
- IKU 3/IKU 8 mengikuti formula final Q32 (pada PK berkode IKU 1.3/IKU 3.3, Q50 §50.4).

Kontrak tambahan 13 Oktober 2026 (Q39–Q51):

- dua lapis target: target Renstra (`target_renstra`, pembanding) dan target PK (`target_tahunan`, acuan pengukuran) yang diinput Perencanaan di halaman PK; keduanya disimpan dengan `target:update`; target PK di bawah target Renstra menurut `arah` memunculkan peringatan dan `alasan_deviasi_renstra` wajib (Q39);
- revisi PK berversi pada tahap 3 dengan prasyarat ISS-03.03; lampiran revisi Renstra boleh ditambah setelah Renstra aktif (Q40);
- Rencana Aksi tanpa jendela penyusunan; periode terkunci bagi PIC setelah `pengisian_selesai` periode tersebut, Perencanaan dikecualikan sampai `penutupan` (Q41; menggantikan Q16);
- kegiatan bagian Rencana Aksi: minimal satu kegiatan saat pengajuan, anggaran per kegiatan wajib (≥ 0), RAB bila anggaran > 0, dan tahap persyaratan `kegiatan_perencanaan` (Q42; menggantikan Q28);
- alasan wajib hanya untuk aksi Q43 §43.2; aksi Q43 §43.3 beralasan opsional dan audit tetap lengkap (Q43);
- Kebijakan Storage dan halaman Batas Unggahan dihapus; unggahan selalu aktif dengan batas teknis dari konfigurasi server (Q44; menggantikan Q23);
- narasi pengukuran wajib; Rekomendasi Pimpinan disusun Perencanaan sebagai draf dan berlaku setelah disetujui Pimpinan (Q45, Q51);
- gerbang baru Q42–Q45 dikecualikan untuk periode lampau, sedangkan kunci per triwulan (Q41) bukan pengecualian karena periode lampau terkunci bagi PIC sejak aktivasi; issue yang menambah gerbang Q42–Q45 menyertakan test pengecualian, dan issue kunci per triwulan menyertakan test bahwa periode lampau terkunci bagi PIC; data awal 2026 diisi dari dokumen resmi (Q46 §46.1);
- indikator dinilai tahunan, periode tanpa target, dan realisasi belum dapat diukur (`status_perhitungan = belum_dapat_diukur`) (Q47);
- keluaran Rencana Aksi tahunan dan Laporan Kinerja Triwulan serta unggah PDF bertanda tangan dengan `laporan:unggah` (Q48);
- `kegiatan.nama` hingga 500 karakter, `tujuan` opsional, dan salin kegiatan ke periode lain (Q49);
- `nomor_pk` opsional, target PK bernilai penuh, serta kode S1–S4/IKU 1.1–IKU 4.1 mengikuti PK (Q50).

Urutan pengerjaan (Q46 §46.3, diperbarui Q51):

| Tahap | Batas | Issue |
|---|---|---|
| 1 | Sebelum integrasi 9 November 2026 | ISS-01.08, ISS-02.10, ISS-02.11, ISS-05.06, ISS-05.07, ISS-06.01, ISS-06.02, ISS-06.08, ISS-13.05, ISS-13.06, pengecualian periode lampau (Q46 §46.1; termasuk ISS-03.05); di akhir tahap ISS-15.01 |
| 2 | Sebelum pelaporan TW IV 2026 (awal Januari 2027) | ISS-06.03, ISS-06.04, ISS-06.05, ISS-07.01, ISS-07.03, ISS-07.06, ISS-08.01, ISS-08.02, ISS-09.01, ISS-10.01, ISS-10.02, ISS-10.05, ISS-14.01 |
| 3 | Sesudah tahap 2; revisi PK siap sebelum revisi PK pertama yang mengubah target | ISS-03.03 dan ISS-02.12, simulasi target, tampilan daftar berkas terunggah, versi penuh Renstra, ISS-13.03 |

Issue yang tidak disebut pada tabel mengikuti rencana sprint yang berlaku.

Status per 17 Oktober 2026 (`development` `f9fd9a7c`): selesai — ISS-01.08 (#101, #108, #114), ISS-05.06 (#100), ISS-06.01 (#97, termasuk Q49 §49.1), ISS-07.01 (#106), ISS-07.03 (#105), ISS-07.06 (#118), ISS-08.02 (#107), ISS-09.01 (#115), ISS-13.03 (#119), ISS-14.01 (#109, #117); dalam review — ISS-06.02 (PR #111); issue Tahap 1 lainnya belum dimulai.

Bila body issue lama bertentangan dengan poin di atas, **Q32 dan keputusan lanjutannya (Q38–Q58) mengalahkan teks lama** dan body issue harus diperbarui sebelum PR dinyatakan Ready for QA.

## Bagian 1 — Fondasi Autentikasi, Unit Organisasi, dan Model Akses RBAC

### ISS-01.01 · [Feature] Login Terpusat Menggunakan Single Sign-On (SSO) Keycloak

> **Q32 FINAL:** JIT SSO membuat user `nonaktif` **tanpa role**. Admin mengaktifkan dan assign salah satu 5 role. Logout lokal default; logout SSO aksi terpisah.
>
> **Q38 (13 Oktober 2026):** JIT SSO kini membuat user `aktif` **tanpa role**; langkah aktivasi dihapus dan admin cukup menetapkan role. Lihat ISS-01.08.

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

> **Q43 (13 Oktober 2026):** alasan pemberian grant menjadi opsional; bila dikosongkan, server mengisi alasan baku sehingga kolom `alasan` tetap `NOT NULL` (Q43 §43.3 butir 1, pola Q37 §37.2). Pencabutan grant tetap wajib alasan (Q43 §43.2 butir 4). Butir "alasan" pada AC dan task di bawah dipertahankan sebagai histori implementasi; perubahan dikerjakan pada ISS-13.05.

> **Implementasi ISS-13.05 (22 Oktober 2026):** `alasan` grant opsional dengan alasan baku `CreateUnitGrantAction::ALASAN_BAKU`; revoke tetap wajib alasan. Lihat ISS-13.05 / PR #156.

> **Q62 (18 Oktober 2026):** daftar Izin Unit satu baris per pengguna × unit dengan halaman detail dan aksi Kelola izin (tambah dan cabut sekaligus, alasan wajib bila ada pencabutan, ditolak bila data berubah sejak dibuka). Form pemberian dapat memberi beberapa izin sekaligus. Audit tetap per izin.

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

> **Q45, Q48, Q51 (13 Oktober 2026):** perubahan preset tetap melalui seeder/rilis dengan audit delta: preset Pimpinan memperoleh `rekomendasi:tetapkan` sementara Perencanaan tetap memegangnya sebagai cadangan (Q45 §45.2 butir 1, Q51 §51.2 butir 1; dikerjakan pada ISS-09.01), dan permission baru `laporan:unggah` masuk katalog dengan preset Perencanaan dan Superadmin (Q48 §48.2 butir 2; dikerjakan pada ISS-10.05). Halaman Peran & Izin tetap read-only.

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

### ISS-01.08 · [Feature] Onboarding SSO Langsung Aktif dan Nonaktifkan/Aktifkan Kembali Akun (Q38)

**Terkait User Story:** `US-01.08`  
**Prioritas:** 🟡 P1 · Tahap 1, berjalan (Q46 §46.3)  
**Labels:** `feature`, `auth-rbac`, `security`, `audit-sensitive`, `frontend`  
**GitHub:** [#99](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/99)

**Status implementasi Bagian A (2026-10-14):** diimplementasikan oleh PR #101 (onboarding SSO langsung aktif tanpa peran, bootstrap kandidat aktif, penghapusan Aktivasi Pengguna). Checklist yang dicentang mencerminkan automated test dan CI PR #101, bukan penerimaan UAT. AC-5 menunggu smoke browser SSO; AC-7 terverifikasi inspeksi (tanpa migrasi dan tanpa kode yang mengubah `audit_log`). Bagian B (AC-8–AC-13) dikerjakan pada PR terpisah. UAT dan deployment belum dilakukan.

**Status implementasi Bagian B (2026-10-15):** diimplementasikan oleh PR #108 (penonaktifan dan pengaktifan kembali akun dari Penetapan Peran). Checklist yang dicentang mencerminkan automated test dan CI PR #108, bukan penerimaan UAT; AC-10 dan AC-12 dibuktikan integration test PostgreSQL dua koneksi. Smoke browser SSO, UAT, dan deployment belum dilakukan.

**Tindak lanjut Q53 (2026-10-15):** guard superadmin aktif terakhir pada penetapan peran (AC-14) dikerjakan di [#112](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/112). Centang AC-14 mencerminkan test feature dan integration test PostgreSQL dua koneksi pada PR tersebut, bukan penerimaan UAT.

#### Kontrak Teknis

- Onboarding JIT membuat `users.status = aktif` tanpa `user_roles`; login ulang tidak mengubah status.
- User tanpa role tetap nol permission (`no_role`), termasuk bila memiliki grant.
- Menu, endpoint `/akses/aktivasi`, dan capability `aktivasi` dihapus; izin `pengguna:read` dan `akses:update` dipertahankan.
- Bootstrap Super Admin menerima kandidat aktif tanpa role yang tercatat onboarding SSO.
- Penonaktifan dan pengaktifan kembali: aktor `akses:update`, alasan wajib, audit; akun sendiri dan superadmin aktif terakhir tidak dapat dinonaktifkan; peran dan grant dipertahankan; peringatan bila pengguna masih Penanggung Jawab aktif.
- Penetapan peran tidak dapat menurunkan peran superadmin aktif terakhir, termasuk peran akun sendiri; superadmin `nonaktif` tidak dihitung (Q53).

#### Acceptance Criteria

- [x] **AC-1:** login SSO pertama membuat user `aktif` tanpa role, audit `pengguna.terdaftar` mencatat `status: aktif`, dan pengguna diarahkan ke "Menunggu penetapan peran".
- [x] **AC-2:** user aktif tanpa role mendapat 403 di `/dashboard` dan `/akses/peran`, dan seluruh `can.*` bernilai `false`.
- [x] **AC-3:** user yang dinonaktifkan tetap `nonaktif` setelah login ulang dan melihat "Akun tidak aktif".
- [x] **AC-4:** bootstrap berhasil sekali untuk akun aktif tanpa role yang tercatat onboarding; kandidat berperan atau tanpa jejak onboarding ditolak tanpa perubahan data.
- [ ] **AC-5:** setelah admin menetapkan role, pengguna dapat masuk dashboard melalui "Periksa status".
- [x] **AC-6:** GET dan POST `/akses/aktivasi` mengembalikan 404 dan menu "Aktivasi Pengguna" tidak tampil.
- [ ] **AC-7:** riwayat audit `pengguna.aktivasi` lama tetap utuh.
- [x] **AC-8:** penonaktifan akun lain dengan alasan membuat status `nonaktif`, memblokir sesi target pada request berikutnya, dan tercatat di audit.
- [x] **AC-9:** pengaktifan kembali membuat status `aktif` dengan peran dan grant sebelumnya tetap berlaku.
- [x] **AC-10:** akun sendiri dan superadmin aktif terakhir tidak dapat dinonaktifkan.
- [x] **AC-11:** alasan kosong → 422; tanpa izin atau deny yang cocok → 403, dan penolakan di dalam action teraudit.
- [x] **AC-12:** dua mutasi status bersamaan pada akun yang sama → satu berhasil, yang lain ditolak stale.
- [x] **AC-13:** bila target masih Penanggung Jawab aktif, dialog menampilkan peringatan sebelum konfirmasi.
- [x] **AC-14 (Q53, #112):** penetapan peran tidak dapat menurunkan peran superadmin aktif terakhir, termasuk peran akun sendiri; penurunan peran dan penonaktifan yang berjalan bersamaan tidak menghabiskan superadmin aktif.

#### Implementation Tasks

- [x] Onboarding `aktif`, bootstrap kandidat aktif, alasan pending `inactive`.
- [x] Hapus fitur Aktivasi Pengguna (halaman, route, controller, action, request, capability, menu).
- [x] Action, request, dan route penonaktifan/pengaktifan kembali beserta aksi di Penetapan Peran.
- [ ] Test AC-1..AC-13, termasuk konkurensi bootstrap dan mutasi status.

#### Definition of Done

- [ ] PR Bagian A dan Bagian B ke `development` lulus review/QA. Persetujuan LLDIKTI atas Q38 pada rapat asistensi 13 Oktober 2026 (§38.5) memenuhi syarat merge; konfirmasi tertulis diterima sebelum deploy produksi (keputusan PM di PR #101).
- [ ] Bagian A dan Bagian B dideploy bersamaan setelah konfirmasi tertulis Q38 diterima; query akun `nonaktif` yang belum pernah diaktifkan dijalankan sebelum dan sesudah deploy dan hasilnya dicatat di laporan rilis (§38.3, keputusan PM di PR #101).

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

> **Q40 (13 Oktober 2026):** atribut Renstra tetap direvisi in place dengan audit (Q40 §40.2 butir 1). Lampiran revisi kini boleh ditambahkan setelah Renstra aktif, sedangkan penghapusan lampiran tetap ditolak; perubahan target Renstra saat Renstra aktif tercatat di audit dengan nilai lama dan baru dan tampil sebagai riwayat revisi (Q40 §40.2 butir 2–3; Q60). Keduanya sudah dikerjakan pada ISS-02.10. Versi penuh Renstra (sasaran dan indikator ikut berversi) tidak dibangun saat ini; penggantian Renstra karena kebijakan kementerian memakai Renstra baru (Q40 §40.2 butir 4; backlog tahap 3, Q46 §46.3).

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
>
> **Q39 (13 Oktober 2026):** halaman Sasaran & Indikator memuat target Renstra per tahun (ISS-02.10); target tahun berjalan (target PK) tidak lagi diisi di halaman ini dan dipindah ke halaman Perjanjian Kinerja (ISS-02.11).
>
> **Q45 (13 Oktober 2026):** saklar `wajib_catatan` disembunyikan dari form indikator tanpa menghapus kolomnya karena kewajiban catatan digantikan narasi pengukuran wajib (Q45 §45.1 butir 3); perubahan form dikerjakan pada ISS-07.03.
>
> **Q47 (13 Oktober 2026):** form indikator memperoleh penanda **Dinilai tahunan** (`indikator.dinilai_tahunan`) yang diubah Perencanaan dan tercatat di audit (Q47 §47.1); dikerjakan pada ISS-05.07.
>
> **Q50 (13 Oktober 2026):** kode sasaran dan indikator mengikuti PK, Rencana Aksi, dan laporan triwulan (**S1–S4**, **IKU 1.1–IKU 4.1**); nomor IKU Renstra (IKU 1–8) dicatat sebagai keterangan pada definisi operasional tanpa kolom baru (Q50 §50.4). "IKU 3" dan "IKU 8" pada catatan Q32 di atas memakai nomor Renstra, yaitu IKU 1.3 dan IKU 3.3 pada PK. Sasaran S4 dan IKU 4.1 dicatat di bawah Renstra 2025–2029 tanpa target Renstra (Q50 §50.5); penerapannya pada data awal mengikuti ISS-15.01.

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

> **Q43 (13 Oktober 2026):** alasan pemindahan unit indikator menjadi opsional, sedangkan audit unit lama/baru tetap wajib (Q43 §43.1, §43.3 butir 3). `PindahUnitIndikatorRequest` saat ini masih mewajibkan alasan; perubahannya dikerjakan pada ISS-13.05. Penghapusan indikator tetap wajib alasan (Q43 §43.2 butir 2).

> **Status implementasi - issue #132 (2026-10-20):** centang menunjukkan bukti implementasi dan test pada PR #151, bukan penerimaan UAT/deployment. Test: `SasaranIndikatorTest::test_pindah_unit_via_endpoint_sukses_mencatat_audit_tunggal_tepat`, `test_8_destroy_indikator_selalu_mengarsipkan_tanpa_hapus_fisik`, `test_destroy_indikator_dengan_histori_pengukuran_dan_rencana_aksi_tetap_diarsipkan`; `IndexPengukuranTest::test_unit_master_berubah_tidak_mengganti_unit_baris_dan_deny_mengikuti_unit_baris`; `JadwalActivationReadinessTest::test_target_gate_requires_nonnull_value_for_every_eligible_indicator`. AC-4 dibaca sebagai arsip/never-delete, bukan penolakan HTTP 4xx. Perubahan alasan pindah unit menjadi opsional tetap di ISS-13.05. Keputusan PM atas pembacaan AC-4: [review PM #151](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/151#issuecomment-6292560535).

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

- [x] **AC-1:** Given indikator dipindahkan ke unit lain, When perubahan disimpan, Then `indikator.unit_id` berubah untuk konteks master ke depan dan audit merekam unit lama/baru.
- [x] **AC-2:** Given snapshot lama telah dipakai RA/Pengukuran, When unit master berubah, Then snapshot/laporan versi lama tetap memakai unit historis yang dibekukan.
- [x] **AC-3:** Given indikator diarsipkan, When periode baru dihitung, Then indikator arsip tidak menjadi kewajiban baru.
- [x] **AC-4:** Given ada histori pengukuran/RA, When hard delete indikator dicoba, Then sistem menolak penghapusan permanen yang merusak histori.

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

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given indikator dipindahkan ke unit lain, When perubahan disimpan, Then `indikator.unit_id` berubah untuk konteks master ke depan dan audit merekam unit lama/baru.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given snapshot lama telah dipakai RA/Pengukuran, When unit master berubah, Then snapshot/laporan versi lama tetap memakai unit historis yang dibekukan.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given indikator diarsipkan, When periode baru dihitung, Then indikator arsip tidak menjadi kewajiban baru.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given ada histori pengukuran/RA, When hard delete indikator dicoba, Then sistem menolak penghapusan permanen yang merusak histori.

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
> **Q33 DOMAIN:** baseline dan target tahunan PK boleh `null` secara independen; jika diisi harus finite dan nonnegatif (`>= 0`); `0` adalah nilai sah; tidak ada batas universal maksimum `100`. Aturan ini tidak otomatis berlaku pada realisasi, nilai komponen, atau target periode Rencana Aksi.
>
> **Q39, Q50 (13 Oktober 2026):** `target_tahunan` (tabel `target_kinerjas`) tetap menyimpan **target PK** dengan domain Q33, tetapi editornya dipindah dari halaman indikator (`/perencanaan/indikator/{id}/target-tahunan/{tahun}`) ke halaman Perjanjian Kinerja dan diinput Perencanaan; PJ/PIC baca-saja (ISS-02.11). Target Renstra adalah data terpisah (ISS-02.10). Target PK disimpan dengan nilai penuh yang dipakai pengukuran (mis. 94,05), dan baseline ditampilkan sebagai data pendukung, bukan isi dokumen PK (Q50 §50.2–§50.3). Alasan perubahan target PK tetap opsional (Q43 §43.3 butir 2). Perubahan target resmi setelah persetujuan Eselon I memakai revisi PK berversi (ISS-02.12, Q40); AC-4 tetap bergantung pada ISS-03.03. "IKU 3" pada catatan Q32 di atas adalah IKU 1.3 pada PK (Q50 §50.4).

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
- **Rule domain nilai (Q33):** `baseline` dan target tahunan nullable secara independen; jika terisi harus berupa desimal finite dan nonnegatif (`>= 0`). `0` berbeda dari `null`, dan tidak ada batas universal maksimum `100`.

> **Status implementasi parsial — PR #64:** centang menunjukkan bukti implementasi dan QA pada `5c056e5`, dengan [CI 9/9 lulus](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/actions/runs/37452026989); bukan persetujuan UAT atau deployment. Bukti memakai test PHPUnit Feature/Unit existing pada `tests/Feature/TargetTahunan/` dan test frontend pada `tests/Frontend/TargetTahunan/`. AC-3/TEST-3 dibuktikan melalui regresi snapshot existing yang tidak berubah saat master dikoreksi; bukan pengujian laporan akhir atau pembentukan snapshot. Konkurensi pembentukan snapshot dengan koreksi target dituntaskan pada [#53](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/53); koreksi/versi snapshot AC-4/TEST-4 tetap pada ISS-03.03. Checklist gabungan yang belum terbukti penuh tetap terbuka.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given indikator dan tahun valid, When baseline/target disimpan, Then satu baris target tahunan tersedia untuk kombinasi tersebut.
- [x] **AC-2:** Given kombinasi indikator-tahun sudah ada, When nilai diperbarui, Then data diperbarui melalui `target:update` dan tidak membuat duplikasi.
- [x] **AC-3:** Given target sudah dibekukan ke snapshot yang dirujuk histori, When master target dikoreksi, Then laporan lama tidak ikut berubah.
- [ ] **AC-4:** Given koreksi salah input terhadap sumber PK resmi dibutuhkan, Then koreksi snapshot dilakukan melalui mekanisme versi dengan alasan dan rujukan bukti.
- [x] **AC-5 (Q33):** Given baseline atau target tahunan bernilai `0`, When disimpan, Then sistem menerima `0` sebagai nilai sah dan tetap membedakannya dari `null`.
- [x] **AC-6 (Q33):** Given baseline atau target tahunan bernilai negatif atau non-finite, When disimpan melalui jalur aplikasi maupun writer yang tunduk pada constraint database, Then nilai ditolak; nilai di atas `100` tidak ditolak hanya karena melewati `100` selama sesuai presisi indikator dan kapasitas storage.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `target_tahunan`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [x] Tegakkan domain Q33 pada persistence: `baseline` dan target tahunan tetap nullable, tetapi nilai terisi harus finite dan nonnegatif; `0` tetap sah; jangan menambahkan cap universal `100`.

**B. Backend / Domain**
- [x] Gunakan permission literal `target:update`; jangan membuat `target:create` baru tanpa perubahan katalog permission resmi.
- [x] Pisahkan koreksi master target dari koreksi snapshot historis.
- [x] Validasi/normalisasi input target tahunan dan baseline di server sesuai Q33; jangan mengandalkan validasi React sebagai enforcement domain.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `target:update`.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Penetapan Baseline & Target Tahunan**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given indikator dan tahun valid, When baseline/target disimpan, Then satu baris target tahunan tersedia untuk kombinasi tersebut.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given kombinasi indikator-tahun sudah ada, When nilai diperbarui, Then data diperbarui melalui `target:update` dan tidak membuat duplikasi.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given target sudah dibekukan ke snapshot yang dirujuk histori, When master target dikoreksi, Then laporan lama tidak ikut berubah.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given koreksi salah input terhadap sumber PK resmi dibutuhkan, Then koreksi snapshot dilakukan melalui mekanisme versi dengan alasan dan rujukan bukti.
- [x] **TEST-5 (Q33):** Buktikan `0` dapat disimpan untuk baseline/target tahunan dan tetap berbeda dari `null`.
- [x] **TEST-6 (Q33):** Buktikan nilai negatif dan non-finite ditolak, sementara nilai valid di atas `100` tidak ditolak hanya karena melewati `100`.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-02.08 · [Feature] Pencatatan Perjanjian Kinerja (PK) & Lampiran Legal

> **Q39, Q40, Q43, Q48, Q50 (13 Oktober 2026):** halaman PK kini juga memuat isian baseline dan target tahun berjalan per indikator, dikelompokkan per sasaran (ISS-02.11, Q39 §39.1 butir 1). `nomor_pk` menjadi opsional karena PK 2026 tidak bernomor; PK tetap unik per Renstra-tahun (Q50 §50.1). `StorePerjanjianKinerjaRequest`/`UpdatePerjanjianKinerjaRequest` dan kolom `nomor_pk` yang masih wajib disesuaikan pada ISS-02.11; AC-1 di bawah dibaca dengan nomor PK opsional. PK perubahan yang hanya mengubah anggaran diunggah sebagai lampiran tambahan PK beserta keterangan pada `berkas.keterangan`, tanpa mengubah target maupun snapshot (Q50 §50.7, Q48 §48.2 butir 6). Revisi PK membentuk versi PK baru dengan nomor dan tanggal surat Eselon I sebagai isian tersendiri; PK awal dan akhir dapat diunduh, dan PDF PK bertanda tangan disimpan per versi (ISS-02.12, tahap 3; Q40 §40.1, Q48 §48.2 butir 1). Alasan perubahan PK wajib hanya bila PK sudah dipakai jadwal aktif dan opsional sebelumnya (Q43 §43.3 butir 3; ISS-13.05).

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

> **Q40, Q43, Q50 (13 Oktober 2026):** jalur ini tetap untuk koreksi salah ketik atau salah input terhadap dokumen PK resmi; perubahan target resmi setelah persetujuan Eselon I memakai revisi PK berversi ISS-02.12, bukan koreksi ini (Q40 §40.1 butir 8). Koreksi PK yang sudah dipakai jadwal aktif tetap wajib alasan (Q43 §43.2 butir 3); perubahan PK sebelum dipakai jadwal aktif beralasan opsional (Q43 §43.3 butir 3; ISS-13.05). `nomor_pk` opsional (Q50 §50.1). Issue ini belum dikerjakan sehingga kontrak dan test di bawah diperbarui langsung; AC tetap sama dengan US-02.09.

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
- **Rule (Q40 §40.1 butir 8):** koreksi hanya untuk salah ketik atau salah input terhadap dokumen PK resmi, termasuk nomor dan tanggal PK; koreksi tidak membentuk versi PK. Pembentukan versi PK awal/akhir dan perubahan target resmi hanya melalui revisi berversi ISS-02.12.
- **Rule (Q50 §50.1):** `nomor_pk` opsional; koreksi tidak mewajibkan nomor PK terisi, dan PK tetap unik per Renstra-tahun.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given koreksi PK diajukan tanpa alasan, When submit, Then ditolak.
- [ ] **AC-2:** Given nilai PK dikoreksi berdasarkan dokumen resmi, When disimpan, Then audit merekam nilai lama/baru, alasan, dan rujukan.
- [ ] **AC-3:** Given snapshot terdampak sudah terbit, When koreksi PK memerlukan perubahan konteks snapshot, Then snapshot tidak diubah in-place; perubahan dibuat sebagai versi snapshot pengganti (ISS-03.03, Q35 §35.1).
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
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given snapshot terdampak sudah terbit, When koreksi PK memerlukan perubahan konteks snapshot, Then snapshot tidak diubah in-place; perubahan dibuat sebagai versi snapshot pengganti (ISS-03.03, Q35 §35.1).
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given snapshot sudah dirujuk versi RA/Pengukuran, When koreksi diperlukan, Then sistem membuat versi snapshot pengganti dan mempertahankan snapshot lama.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given hasil resmi lama sudah disahkan, Then hasil lama tidak berubah sampai versi koreksi diajukan dan disahkan ulang.
- [ ] NOMOR PK: Uji koreksi metadata PK tanpa `nomor_pk` beserta alasan diterima karena `nomor_pk` opsional, dan PK tetap unik per Renstra-tahun (Q50 §50.1).

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-02.10 · [Feature] Target Renstra per Indikator, Lampiran Revisi, dan Riwayat Revisi Target Renstra

**Terkait User Story:** `US-02.10`  
**Prioritas:** Tahap 1 (Q46 §46.3: sebelum integrasi 9 November 2026)  
**Story Points:** Belum diestimasi  
**Labels:** `feature`, `master-data`, `audit-sensitive`, `frontend`  
**Plan:** Modul 2: 2.21  
**PRD:** §8, §10.5–10.6, §11  
**Workflow:** §2, §14  
**Data Model/Entitas:** `target_renstra` (Data Model §2.35), `berkas` (induk `renstra`)

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mencatat target Renstra setiap indikator untuk setiap tahun dalam rentang Renstra, menambah lampiran revisi setelah Renstra aktif, dan melihat riwayat revisi target Renstra,  
> **Sehingga** target PK memiliki pembanding resmi dan revisi Renstra tetap tertelusur tanpa menghapus dokumen lama.

#### Kontrak Teknis

- **Dependensi:** Renstra, sasaran, dan indikator tersedia (ISS-02.02–ISS-02.04).
- **Otorisasi:** penyimpanan target Renstra: `target:update` (Q39 §39.3 butir 3; tidak ada permission baru); lampiran revisi: `renstra:update` dan capability berkas melalui kewenangan induk.
- **Dampak Data:** entitas baru `target_renstra` per indikator per tahun Renstra, `berkas` (induk `renstra`), `audit_log`.
- **Keputusan terkait:** Q39 §39.1 dan §39.3 butir 3, Q40 §40.2, Q43 §43.3 butir 5, Q60, Q46 §46.3.
- **Rule:** target Renstra terpisah dari target PK (`target_tahunan`, ISS-02.11) dan hanya menjadi pembanding; atribut Renstra lain tetap direvisi in place dengan audit (Q40 §40.2 butir 1).
- **Rule:** bila target terlampaui sebelum akhir periode Renstra, target tahun berikutnya dinaikkan melalui perubahan pada AC-4 (Q40 §40.2 butir 3).
- **Rule:** lampiran revisi hanya dapat ditambahkan pada Renstra berstatus `draft` atau `aktif`; Renstra `nonaktif` atau diarsipkan tidak menerima lampiran baru (Q40 §40.2 butir 5).
- **Rule:** indikator yang hanya ada di PK (mis. IKU 4.1 pada sasaran S4) dicatat di bawah Renstra 2025–2029 tanpa target Renstra (Q50 §50.5); kekosongan ini sah menurut AC-2.
- **Di luar cakupan:** versi penuh Renstra (Q40 §40.2 butir 4; tahap 3, Q46 §46.3), pembanding target PK terhadap target Renstra (ISS-02.11), penghapusan lampiran, dan alur usulan target oleh PJ (Q39 §39.3 butir 2).

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given indikator aktif pada Renstra dengan rentang tahun tertentu, When Perencanaan menyimpan target Renstra, Then tersimpan paling banyak satu nilai per indikator per tahun dalam rentang Renstra; tahun di luar rentang ditolak server. *(Q39 §39.1)*
- [x] **AC-2:** Given nilai target Renstra dikirim, When disimpan, Then berlaku domain nilai Q33: boleh kosong, bila diisi finite dan nonnegatif, `0` sah dan berbeda dari kosong, tanpa batas universal `100`. *(Q39 §39.1 butir 4)*
- [x] **AC-3:** Given halaman Sasaran & Indikator dibuka, Then target Renstra per tahun tampil bersama indikator sebagai pembanding dan tidak dipakai menghitung capaian. *(Q39 §39.1 butir 2, §39.4)*
- [x] **AC-4:** Given target Renstra pada Renstra `draft` atau `aktif`, When nilainya diisi, diubah, atau dikosongkan, Then perubahan tersimpan tanpa field alasan dan audit mencatat aktor, waktu, tindakan, nilai lama, dan nilai baru; nilai yang tidak berubah tidak diaudit. *(Q40 §40.2 butir 3, Q43 §43.1, Q60)*
- [x] **AC-5:** Given target Renstra pernah diubah saat Renstra `aktif`, When riwayat dibuka, Then sistem menampilkan riwayat revisi target Renstra (waktu, aktor, tahun, nilai lama/baru) yang dibaca dari audit. *(Q40 §40.2 butir 3)*
- [x] **AC-6:** Given Renstra berstatus `aktif`, When Perencanaan menambahkan lampiran revisi, Then lampiran baru tersimpan pada Renstra; When lampiran yang sudah ada dihapus, Then ditolak. *(Q40 §40.2 butir 2)*
- [x] **AC-7:** Given target Renstra suatu indikator atau tahun kosong, When jadwal tahun tersebut diaktifkan, Then kekosongan target Renstra tidak menjadi gerbang aktivasi. *(Q39 §39.1 butir 5)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Buat migrasi dan model `target_renstra` sesuai Data Model §2.35: satu baris per (`indikator_id`, `tahun`) dengan constraint unik, nilai nullable, dan CHECK finite serta nonnegatif (domain Q33); tahun dalam rentang Renstra divalidasi server.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Implementasikan action simpan target Renstra: domain nilai Q33, tanpa field alasan, serta audit nilai lama/baru dalam transaksi yang sama.
- [x] Sediakan pembacaan riwayat revisi target Renstra dari `audit_log` (waktu, aktor, tahun, nilai lama/baru).
- [x] Ubah `UpdateRenstraAction`, yang saat ini menolak penambahan lampiran pada Renstra bukan draf, agar lampiran revisi dapat ditambahkan pada Renstra `draft` atau `aktif` dan tetap ditolak pada Renstra `nonaktif`/diarsipkan (Q40 §40.2 butir 5); penghapusan lampiran yang sudah ada tetap ditolak (`DeleteRenstraAttachmentAction`).
- [x] Pastikan `JadwalActivationReadiness` dan `ActivateJadwal` tidak menjadikan target Renstra sebagai gerbang aktivasi.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side: `target:update` untuk target Renstra; `renstra:update` dan capability berkas induk untuk lampiran revisi.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; audit nilai lama/baru.

**D. Frontend / UX**
- [x] Sediakan tampilan dan isian target Renstra per tahun rentang Renstra pada halaman Sasaran & Indikator (`resources/js/Pages/Perencanaan/SasaranIndikator/`) sebagai pembanding, beserta riwayat revisinya.
- [x] Simpan langsung tanpa Modal Alasan Audit (Q60); validasi server tetap menjadi sumber kebenaran.
- [x] Aktifkan unggah lampiran revisi pada halaman Renstra (`resources/js/Pages/Renstra/`) untuk Renstra `aktif`, tanpa aksi hapus bagi lampiran yang sudah ada.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit; pastikan state loading/disabled/error, responsif mobile, dan kontras sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given indikator aktif pada Renstra dengan rentang tahun tertentu, When Perencanaan menyimpan target Renstra, Then tersimpan paling banyak satu nilai per indikator per tahun dalam rentang Renstra; tahun di luar rentang ditolak server. *(Q39 §39.1)*
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given nilai target Renstra dikirim, When disimpan, Then berlaku domain nilai Q33: boleh kosong, bila diisi finite dan nonnegatif, `0` sah dan berbeda dari kosong, tanpa batas universal `100`. *(Q39 §39.1 butir 4)*
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given halaman Sasaran & Indikator dibuka, Then target Renstra per tahun tampil bersama indikator sebagai pembanding dan tidak dipakai menghitung capaian. *(Q39 §39.1 butir 2, §39.4)*
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given target Renstra pada Renstra `draft` atau `aktif`, When nilainya diisi, diubah, atau dikosongkan, Then perubahan tersimpan tanpa field alasan dan audit mencatat aktor, waktu, tindakan, nilai lama, dan nilai baru; nilai yang tidak berubah tidak diaudit. *(Q40 §40.2 butir 3, Q43 §43.1, Q60)*
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given target Renstra pernah diubah saat Renstra `aktif`, When riwayat dibuka, Then sistem menampilkan riwayat revisi target Renstra (waktu, aktor, tahun, nilai lama/baru) yang dibaca dari audit. *(Q40 §40.2 butir 3)*
- [x] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given Renstra berstatus `aktif`, When Perencanaan menambahkan lampiran revisi, Then lampiran baru tersimpan pada Renstra; When lampiran yang sudah ada dihapus, Then ditolak. *(Q40 §40.2 butir 2)*
- [x] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given target Renstra suatu indikator atau tahun kosong, When jadwal tahun tersebut diaktifkan, Then kekosongan target Renstra tidak menjadi gerbang aktivasi. *(Q39 §39.1 butir 5)*
- [x] STATUS RENSTRA: Uji penambahan lampiran revisi pada Renstra `nonaktif` atau diarsipkan ditolak, sedangkan pada Renstra `draft` dan `aktif` diterima (Q40 §40.2 butir 5).
- [x] SECURITY: Uji request langsung tanpa `target:update` (target Renstra) atau tanpa `renstra:update` (lampiran revisi), serta deny yang cocok, menghasilkan 403 meski tombol UI disembunyikan.

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [x] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-02.11 · [Feature] Input Target PK di Halaman Perjanjian Kinerja dengan Pembanding Target Renstra

**Terkait User Story:** `US-02.11`  
**Prioritas:** Tahap 1 (Q46 §46.3: sebelum integrasi 9 November 2026)  
**Story Points:** Belum diestimasi  
**Labels:** `feature`, `master-data`, `audit-sensitive`, `frontend`  
**Plan:** Modul 2: 2.22  
**PRD:** §8, §11, §12.4, §14.5  
**Workflow:** §3.3, §5  
**Data Model/Entitas:** `target_tahunan` (tabel `target_kinerjas`, termasuk `alasan_deviasi_renstra`), `renstra_pk`, `target_renstra` (baca)

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengisi baseline dan target tahun berjalan setiap indikator langsung di halaman Perjanjian Kinerja dengan target Renstra sebagai pembanding,  
> **Sehingga** target PK tercatat bersama dokumen PK dan setiap deviasi terhadap Renstra memiliki penjelasan.

#### Kontrak Teknis

- **Dependensi:** PK tahun berjalan tercatat (ISS-02.08); target Renstra tersedia sebagai pembanding (ISS-02.10).
- **Otorisasi:** `target:update` untuk menyimpan target PK (dipegang Tim Perencanaan, Q39 §39.3 butir 3); PJ/PIC baca-saja; metadata dan lampiran PK tetap memakai `pk:create`/`pk:update` serta capability berkas melalui kewenangan induk.
- **Dampak Data:** `target_tahunan` (termasuk `alasan_deviasi_renstra`), `renstra_pk` (`nomor_pk` opsional), `audit_log`.
- **Keputusan terkait:** Q39 §39.1–§39.4, Q33, Q43 §43.2 butir 5 dan §43.3 butir 2, Q50 §50.1–§50.3 dan §50.5–§50.7, Q46 §46.3.
- **Rule:** target PK diinput Tim Perencanaan setelah angka disepakati dengan tim kerja di luar aplikasi (Q39 §39.3 butir 1); capaian dinilai terhadap target PK melalui snapshot, bukan terhadap target Renstra, dan target periode RA dipecah dari target PK (Q39 §39.4).
- **Rule:** perubahan target resmi setelah persetujuan Eselon I memakai revisi PK berversi (ISS-02.12); koreksi salah ketik memakai ISS-02.09 (Q40 §40.1 butir 8).
- **Rule:** PK perubahan yang hanya mengubah anggaran diunggah sebagai lampiran tambahan pada PK melalui fitur lampiran PK yang ada, beserta keterangan (mis. "PK perubahan anggaran") yang disimpan pada kolom baru `berkas.keterangan`; target dan snapshot tidak berubah (Q50 §50.7, Q48 §48.2 butir 6). `alasan_koreksi` tetap khusus untuk berkas pengganti.
- **Di luar cakupan:** alur usulan target oleh PJ (Q39 §39.3 butir 2), revisi PK berversi (ISS-02.12), dan simulasi target (tahap 3, Q46 §46.3).

#### Acceptance Criteria (QA/UAT)

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

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Tambahkan kolom `alasan_deviasi_renstra` (nullable) pada `target_kinerjas`; domain nilai Q33 tetap berlaku dan target disimpan dengan nilai penuh sesuai presisi kolom (Q50 §50.2).
- [ ] Ubah `renstra_pk.nomor_pk` menjadi nullable lewat migrasi; `unique(renstra_id, tahun)` tetap berlaku (Q50 §50.1).
- [ ] Simpan keterangan lampiran tambahan PK pada `berkas.keterangan` (kolom nullable baru, Q48 §48.2 butir 6); migrasi kolom dibuat oleh issue yang lebih dulu dikerjakan antara ISS-02.11 dan ISS-10.05.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Pindahkan editor baseline/target dari halaman indikator (`TargetTahunanController`, `ShowTargetTahunan`, `SaveTargetTahunan`, `SaveTargetTahunanRequest`; route `/perencanaan/indikator/{indikator}/target-tahunan/{tahun}`) ke halaman PK (`PerjanjianKinerjaController`, `ShowPerjanjianKinerja`); route lama tidak lagi melayani mutasi target.
- [ ] Evaluasi deviasi terhadap target Renstra menurut `indikator.arah` (`naik_baik`: target PK di bawah target Renstra; `turun_baik`: di atasnya): tampilkan peringatan dan wajibkan `alasan_deviasi_renstra` tanpa menolak penyimpanan bila alasan terisi; indikator tanpa target Renstra memperoleh keterangan "target Renstra belum ada" tanpa kewajiban alasan.
- [ ] Jadikan `nomor_pk` opsional pada `StorePerjanjianKinerjaRequest` dan `UpdatePerjanjianKinerjaRequest`; jangan menambah validasi urutan tanggal PK terhadap tanggal penetapan Renstra (Q50 §50.6).
- [ ] Simpan dan bandingkan target memakai nilai tersimpan, bukan nilai tampilan; snapshot, rekonsiliasi Rencana Aksi (ADR-0008, `DeviasiTargetPk`), dan pengukuran tetap membaca target PK melalui snapshot sehingga perubahan setelah snapshot tidak mengubahnya.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side: `target:update` untuk mutasi target PK; PJ/PIC memperoleh tampilan baca-saja dan 403 pada request mutasi langsung.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat audit perubahan baseline/target PK beserta nilai lama/baru dan `alasan_deviasi_renstra`; alasan perubahan target PK opsional (Q43 §43.3 butir 2).

**D. Frontend / UX**
- [ ] Tampilkan pada halaman PK (`resources/js/Pages/PerjanjianKinerja/Show.tsx`) isian baseline (ditandai data pendukung) dan target per indikator aktif, dikelompokkan per sasaran, dengan target Renstra di sampingnya, anjuran "minimal sama atau naik satu poin", peringatan deviasi, dan isian alasan deviasi.
- [ ] Hapus editor target dari halaman Sasaran & Indikator (`resources/js/Pages/Perencanaan/SasaranIndikator/Index.tsx` yang memuat `resources/js/Pages/TargetTahunan/Editor.tsx` dan `TargetModal.tsx`) atau pakai ulang komponennya di halaman PK.
- [ ] Tandai nomor PK sebagai opsional pada `PerjanjianKinerjaCreateModal.tsx` dan `PerjanjianKinerjaEditModal.tsx`.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit; pastikan state loading/disabled/error, responsif mobile, dan kontras sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given PK suatu Renstra-tahun tercatat, When halaman PK dibuka, Then tampil isian baseline dan target tahun berjalan untuk setiap indikator aktif Renstra, dikelompokkan per sasaran, bersama nomor, tanggal, dan lampiran PK. *(Q39 §39.1 butir 1)*
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given Perencanaan menyimpan baseline/target di halaman PK, Then nilai tersimpan pada `target_tahunan` (satu baris per indikator-tahun) dengan domain nilai Q33; editor target di halaman indikator tidak lagi tersedia. *(Q39 §39.1 butir 2–3)*
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given target Renstra tahun yang sama tersedia, When target PK diisi, Then target Renstra ditampilkan di sampingnya bersama anjuran "minimal sama atau naik satu poin" sebagai panduan, bukan aturan sistem. *(Q39 §39.2 butir 1 dan 3)*
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given indikator `naik_baik` dengan target PK di bawah target Renstra, atau `turun_baik` dengan target PK di atas target Renstra, When disimpan, Then sistem menampilkan peringatan dan mewajibkan `alasan_deviasi_renstra`; penyimpanan tidak ditolak bila alasan terisi, dan alasan tersimpan bersama target PK serta tercatat di audit. *(Q39 §39.2 butir 2 dan 5, Q43 §43.2 butir 5)*
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given indikator belum memiliki target Renstra, When target PK diisi, Then penyimpanan diizinkan dengan keterangan "target Renstra belum ada" tanpa kewajiban alasan deviasi. *(Q39 §39.2 butir 4)*
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given PJ atau PIC membuka target PK, Then target tampil baca-saja; request mutasi langsung tanpa `target:update` ditolak 403. *(Q39 §39.3)*
- [ ] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given aktivasi jadwal diminta, Then gerbang tetap: PK tahun tersebut tercatat, setiap indikator aktif memiliki target PK, tahun jadwal berada dalam rentang Renstra, dan PK memiliki lampiran. *(Q39 §39.1 butir 5)*
- [ ] TEST-8: Buat Pest Feature/Unit test yang membuktikan — Given target PK sudah dibekukan ke snapshot jadwal, When target PK diubah di halaman PK, Then snapshot, rekonsiliasi Rencana Aksi (ADR-0008), dan pengukuran tidak berubah; alasan perubahan target PK tetap opsional. *(Q39 §39.1 butir 3, Q43 §43.3 butir 2)*
- [ ] TEST-9: Buat Pest Feature/Unit test yang membuktikan — Given PK dicatat tanpa nomor, When disimpan, Then diterima karena `nomor_pk` opsional; PK tetap unik per Renstra-tahun. *(Q50 §50.1)*
- [ ] TEST-10: Buat Pest Feature/Unit test yang membuktikan — Given target PK berpresisi penuh (mis. 94,05), When disimpan, Then nilai tersimpan apa adanya dan status capaian serta selisih memakai nilai tersimpan; tampilan mengikuti `desimal_tampilan`. *(Q50 §50.2)*
- [ ] TEST-11: Buat Pest Feature/Unit test yang membuktikan — Given halaman PK, Then baseline tampil sebagai data pendukung, bukan bagian isi dokumen PK. *(Q50 §50.3)*
- [ ] TEST-12: Buat Pest Feature/Unit test yang membuktikan — Given indikator yang hanya ada di PK (mis. IKU 4.1, sasaran S4), When target PK diisi, Then berlaku keterangan "target Renstra belum ada" (AC-5). *(Q50 §50.5)*
- [ ] TEST-13: Buat Pest Feature/Unit test yang membuktikan — Given tanggal PK lebih awal dari tanggal penetapan Renstra, When disimpan, Then tidak ditolak karena urutan tanggal. *(Q50 §50.6)*
- [ ] SECURITY: Uji request langsung tanpa `target:update` dan deny yang cocok menghasilkan 403 meski tombol UI disembunyikan.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-02.12 · [Feature] Revisi PK Berversi (PK Awal dan PK Akhir)

**Terkait User Story:** `US-02.12`  
**Prioritas:** Tahap 3 (Q46 §46.3: sesudah tahap 2; harus siap sebelum revisi PK pertama yang mengubah target)  
**Story Points:** Belum diestimasi  
**Labels:** `feature`, `master-data`, `schedule-snapshot`, `audit-sensitive`, `frontend`  
**Plan:** Modul 2: 2.23  
**PRD:** §12.7, §14.5, §14.10  
**Workflow:** §3.3, §13–14  
**Data Model/Entitas:** `renstra_pk_versi` (Data Model §2.36, rancangan), `renstra_pk_versi_target` (Data Model §2.37, rancangan), `berkas`, `jadwal_snapshot`

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mencatat revisi PK yang disetujui Eselon I sebagai versi PK baru,  
> **Sehingga** PK awal dan PK akhir tersimpan serta dapat diunduh, sementara target baru berlaku mulai periode yang belum ditutup tanpa mengubah laporan periode sebelumnya.

#### Kontrak Teknis

- **Dependensi:** alur koreksi snapshot berversi ISS-03.03 sebagai prasyarat (Q40 §40.1 butir 9); PK dan target PK tercatat (ISS-02.08, ISS-02.11).
- **Otorisasi:** revisi disetujui Perencanaan (Q40 §40.1 butir 1); permission ditetapkan saat refinement (katalog existing memuat `pk:update`); capability berkas melalui kewenangan induk; unduhan mengikuti hak baca PK.
- **Dampak Data:** `renstra_pk_versi` (nomor versi, `nomor_surat`, `tanggal_surat`, periode mulai berlaku), `renstra_pk_versi_target`, `berkas` (lampiran PK per versi), `jadwal_snapshot` (versi baru bila target berubah), `audit_log`.
- **Keputusan terkait:** Q40 §40.1, Q35 §35.1, Q43 §43.2 butir 3, Q48 §48.2 butir 1, Q50 §50.7, Q46 §46.3.
- **Rule:** koreksi salah ketik nomor atau tanggal PK tetap memakai koreksi teraudit ISS-02.09; perubahan target resmi memakai revisi berversi (Q40 §40.1 butir 8). Nomor dan tanggal surat Eselon I disimpan sebagai isian tersendiri, menggantikan aturan lama yang mencantumkannya pada kolom `alasan` (Q40 §40.3).
- **Rule:** Q40 tidak merinci kapan suatu periode dianggap "sudah ditutup"; definisinya ditetapkan saat refinement bersama ISS-03.03 sebelum implementasi.
- **Rule:** sebelum issue ini selesai, PK perubahan yang hanya mengubah anggaran memakai lampiran tambahan PK (Q50 §50.7 butir 1); PK perubahan yang mengubah target sebelum issue ini selesai ditangani melalui keputusan PM baru (Q50 §50.7 butir 2).
- **Di luar cakupan:** revisi dan riwayat target Renstra (ISS-02.10), versi penuh Renstra (Q40 §40.2 butir 4), dan koreksi salah ketik (ISS-02.09).

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given PK sudah tercatat, When Perencanaan menyetujui revisi, Then terbentuk versi PK baru yang menyimpan nomor versi, nomor dan tanggal surat persetujuan Eselon I sebagai isian tersendiri, periode mulai berlaku, lampiran dokumen PK bertanda tangan, dan target per indikator; versi sebelumnya tidak berubah. *(Q40 §40.1 butir 1–2)*
- [ ] **AC-2:** Given revisi PK disimpan tanpa alasan, Then ditolak. *(Q43 §43.2 butir 3)*
- [ ] **AC-3:** Given revisi mengubah target, When disetujui, Then revisi berlaku mulai periode pertama yang belum ditutup: sistem menerbitkan versi `jadwal_snapshot` baru (Q35 §35.1) dengan `periode_mulai_id` periode tersebut dan `rujukan_koreksi` surat Eselon I, sedangkan periode yang sudah dilaporkan tetap memakai target versi lama. *(Q40 §40.1 butir 4)*
- [ ] **AC-4:** Given revisi hanya mengganti dokumen tanpa mengubah target, When disetujui, Then versi PK baru terbentuk tanpa menerbitkan versi snapshot. *(Q40 §40.1 butir 5)*
- [ ] **AC-5:** Given PK memiliki lebih dari satu versi, When halaman PK dibuka, Then PK awal dan PK akhir dapat diunduh sesuai hak baca. *(Q40 §40.1 butir 3)*
- [ ] **AC-6:** Given capaian akhir tahun dinilai, Then acuannya target PK akhir, yaitu versi yang berlaku pada periode nilai akhir. *(Q40 §40.1 butir 6)*
- [ ] **AC-7:** Given Rencana Aksi sudah disusun, When revisi PK mengubah target, Then RA tidak diubah otomatis; selisih target periode terakhir dengan target PK baru tampil sebagai deviasi yang wajib dijelaskan (ADR-0008), dan target periode yang belum terkunci disesuaikan lewat buka-kembali (US-05.06). *(Q40 §40.1 butir 7)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Buat migrasi dan model `renstra_pk_versi` serta `renstra_pk_versi_target` sesuai rancangan Data Model §2.36–§2.37; baris versi immutable setelah dibuat. Representasi PK yang sudah tercatat sebagai versi awal ditetapkan saat refinement.
- [ ] Daftarkan versi PK sebagai induk `berkas` untuk lampiran dokumen PK bertanda tangan per versi (Q48 §48.2 butir 1).
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Persetujuan revisi membentuk versi PK baru beserta target per indikator dan lampirannya dalam satu transaksi; versi sebelumnya tidak berubah.
- [ ] Bila target berubah, terbitkan versi `jadwal_snapshot` baru melalui mekanisme ISS-03.03 dengan `periode_mulai_id` periode pertama yang belum ditutup dan `rujukan_koreksi` surat Eselon I; revisi yang hanya mengganti dokumen tidak menerbitkan versi snapshot.
- [ ] Tetapkan target PK akhir sebagai versi yang berlaku pada periode nilai akhir (`periode.is_nilai_akhir`) untuk penilaian capaian akhir tahun.
- [ ] Jangan mengubah Rencana Aksi otomatis; tampilkan selisih target periode terakhir terhadap target PK baru sebagai deviasi yang wajib dijelaskan (ADR-0008, `DeviasiTargetPk`); target periode yang belum terkunci disesuaikan lewat buka-kembali ISS-05.06.

**C. Authorization & Audit**
- [ ] Terapkan permission revisi yang ditetapkan saat refinement melalui Policy/Gate/resolver server-side; alasan revisi wajib dan divalidasi server (Q43 §43.2 butir 3).
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat audit persetujuan revisi dengan aktor, nilai lama/baru, nomor dan tanggal surat, alasan, dan `dasar_izin`.

**D. Frontend / UX**
- [ ] Tampilkan daftar versi PK pada halaman PK (`resources/js/Pages/PerjanjianKinerja/Show.tsx`) beserta unduhan PK awal dan PK akhir sesuai hak baca.
- [ ] Sediakan form revisi dengan isian nomor dan tanggal surat Eselon I, periode mulai berlaku, lampiran, dan target per indikator, memakai pola **Modal Alasan Audit**.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit; pastikan state loading/disabled/error, responsif mobile, dan kontras sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given PK sudah tercatat, When Perencanaan menyetujui revisi, Then terbentuk versi PK baru yang menyimpan nomor versi, nomor dan tanggal surat persetujuan Eselon I sebagai isian tersendiri, periode mulai berlaku, lampiran dokumen PK bertanda tangan, dan target per indikator; versi sebelumnya tidak berubah. *(Q40 §40.1 butir 1–2)*
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given revisi PK disimpan tanpa alasan, Then ditolak. *(Q43 §43.2 butir 3)*
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given revisi mengubah target, When disetujui, Then revisi berlaku mulai periode pertama yang belum ditutup: sistem menerbitkan versi `jadwal_snapshot` baru (Q35 §35.1) dengan `periode_mulai_id` periode tersebut dan `rujukan_koreksi` surat Eselon I, sedangkan periode yang sudah dilaporkan tetap memakai target versi lama. *(Q40 §40.1 butir 4)*
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given revisi hanya mengganti dokumen tanpa mengubah target, When disetujui, Then versi PK baru terbentuk tanpa menerbitkan versi snapshot. *(Q40 §40.1 butir 5)*
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given PK memiliki lebih dari satu versi, When halaman PK dibuka, Then PK awal dan PK akhir dapat diunduh sesuai hak baca. *(Q40 §40.1 butir 3)*
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given capaian akhir tahun dinilai, Then acuannya target PK akhir, yaitu versi yang berlaku pada periode nilai akhir. *(Q40 §40.1 butir 6)*
- [ ] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given Rencana Aksi sudah disusun, When revisi PK mengubah target, Then RA tidak diubah otomatis; selisih target periode terakhir dengan target PK baru tampil sebagai deviasi yang wajib dijelaskan (ADR-0008), dan target periode yang belum terkunci disesuaikan lewat buka-kembali (US-05.06). *(Q40 §40.1 butir 7)*
- [ ] CONCURRENCY: Uji persetujuan revisi bersamaan dan penerbitan versi snapshot tidak menghasilkan versi ganda atau versi stale.

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

> **Q32 FINAL:** Jadwal pertama 2026; TW I–II periode lampau oleh Perencanaan, TW III–IV normal; tidak ada flag backfill. *(Diperbarui Q46 §46.1 butir 2: TW III 2026 juga periode lampau; TW IV menjadi triwulan pertama workflow normal PIC operasional.)*
>
> **Q41 (13 Oktober 2026):** jendela penyusunan Rencana Aksi dihapus; isian `rencana_aksi_mulai`/`rencana_aksi_selesai` dihapus dari form jadwal dan tidak lagi divalidasi, sedangkan kolomnya dibiarkan nullable sampai migrasi lanjutan (Q41 §41.1 butir 2). AC-1 dan AC-3 beserta TEST-1 dan TEST-3 yang menyebut jendela RA digantikan Q41 dan dipertahankan sebagai histori; perubahan dikerjakan pada ISS-05.07.

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
- [x] **AC-6:** Given lebih dari 12 periode dipilih, When jadwal disimpan, Then server menolak; satu jadwal tahunan memuat paling banyak 12 periode. *(Q35 §35.3; bukti `RencanaAksiMatrixLimitTest` pada PR #62.)*

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
- [x] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given lebih dari 12 periode dipilih, When jadwal disimpan, Then server menolak. *(`RencanaAksiMatrixLimitTest`: 13 periode ditolak, 12 diterima.)*

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-03.02 · [Feature] Aktivasi Jadwal Tahunan melalui Empat Gerbang & Pembentukan Snapshot

> **Q39, Q43, Q44, Q47 (13 Oktober 2026):** gerbang tetap empat; "target tahunan" pada AC-1 adalah target PK yang kini diinput di halaman PK (ISS-02.11, Q39 §39.1 butir 5). Saklar unggahan dihapus sehingga cabang pengecualian lampiran PK `tidak_dapat_dipenuhi` akibat unggahan dimatikan tidak lagi terjadi; penanda yang sudah tercatat tetap menjadi histori (Q44 §44.1 butir 2; ISS-13.06). Alasan aktivasi menjadi opsional (Q43 §43.3 butir 3; ISS-13.05). Snapshot ikut membekukan penanda `dinilai_tahunan` indikator (Q47 §47.1 butir 2; ISS-05.07).

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

> **Status implementasi — PR #67:** centang menunjukkan bukti implementasi dan QA pada `57c0e46`, dengan [CI 9/9 lulus](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/actions/runs/37603846679); bukan persetujuan UAT atau deployment. Bukti memakai test PHPUnit pada `tests/Feature/Jadwal/JadwalActivation*`, test integrasi PostgreSQL dua koneksi dan commit fisik pada `tests/Integration/Jadwal/`, test frontend `tests/Frontend/JadwalActivationPanel.test.tsx`, serta E2E browser lokal. Indikator yang belum mulai berlaku pada tahun jadwal tidak termasuk gerbang/snapshot sesuai Q20. Audit kontras dan checklist `design-system.md` dilakukan pada perbaikan token `text-success-dark` (status lolos/siap sebelumnya 3,3:1; seluruh teks panel kini ≥4,5:1 pada state siap, gagal, blocker, dirty, sukses, dan pemulihan). Pembentukan snapshot saat `jadwal:buka_kembali` (Plan 3.9) dan koreksi/versi snapshot (ISS-03.03) bukan cakupan PR ini.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given aktivasi diminta, When server mengevaluasi, Then empat gerbang diperiksa: PK tersedia; target tahunan semua indikator aktif tersedia; tahun berada dalam rentang Renstra; lampiran PK tersedia atau pengecualian file yang sah tercatat.
- [x] **AC-2:** Given salah satu gerbang wajib gagal, When aktivasi diproses, Then seluruh transaksi ditolak atomik dan pesan menjelaskan gerbang yang gagal.
- [x] **AC-3:** Given semua gerbang lolos, When commit berhasil, Then jadwal menjadi `aktif` dan `activated_at` terisi.
- [x] **AC-4:** Given indikator aktif belum memiliki snapshot pada jadwal, When aktivasi sukses, Then `jadwal_snapshot` dibuat idempoten beserta `jadwal_snapshot_komponen`.
- [x] **AC-5:** Given snapshot pasangan indikator-jadwal sudah ada, When proses diulang, Then baris lama tidak ditimpa/duplikasi.
- [x] **AC-6:** Given permission sensitif digunakan, Then audit menyimpan `dasar_izin` dan aktor sebenarnya.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `jadwal_tahunan`, `jadwal_snapshot`, `jadwal_snapshot_komponen`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [x] Jaga snapshot idempoten/berversi: snapshot yang telah dirujuk tidak boleh di-update in-place.

**B. Backend / Domain**
- [x] Implementasikan aktivasi dalam satu transaksi atomik: empat gerbang → transisi status → snapshot indikator → snapshot komponen → audit.
- [x] Pembuatan snapshot harus idempoten dan tidak menimpa pasangan jadwal-indikator yang sudah ada.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `jadwal:aktivasi` (sensitif).
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Aktivasi Jadwal Tahunan melalui Empat Gerbang & Pembentukan Snapshot**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [x] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [x] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given aktivasi diminta, When server mengevaluasi, Then empat gerbang diperiksa: PK tersedia; target tahunan semua indikator aktif tersedia; tahun berada dalam rentang Renstra; lampiran PK tersedia atau pengecualian file yang sah tercatat.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given salah satu gerbang wajib gagal, When aktivasi diproses, Then seluruh transaksi ditolak atomik dan pesan menjelaskan gerbang yang gagal.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given semua gerbang lolos, When commit berhasil, Then jadwal menjadi `aktif` dan `activated_at` terisi.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given indikator aktif belum memiliki snapshot pada jadwal, When aktivasi sukses, Then `jadwal_snapshot` dibuat idempoten beserta `jadwal_snapshot_komponen`.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given snapshot pasangan indikator-jadwal sudah ada, When proses diulang, Then baris lama tidak ditimpa/duplikasi.
- [x] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given permission sensitif digunakan, Then audit menyimpan `dasar_izin` dan aktor sebenarnya.
- [x] CONCURRENCY: Uji transaksi/optimistic locking/versi stale sesuai kontrak Data Model.

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [x] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-03.03 · [Feature] Koreksi Snapshot Terkendali dan Versioning Konteks

> **Q40, Q46 (13 Oktober 2026):** alur koreksi snapshot berversi ini menjadi prasyarat revisi PK berversi (ISS-02.12, Q40 §40.1 butir 9): persetujuan revisi PK yang mengubah target menerbitkan versi `jadwal_snapshot` baru dengan `periode_mulai_id` periode pertama yang belum ditutup dan `rujukan_koreksi` surat Eselon I (AC-6). Keduanya dijadwalkan pada tahap 3 (Q46 §46.3). Koreksi snapshot tetap wajib alasan (Q43 §43.2 butir 3). Penanda `dinilai_tahunan` yang dibekukan pada snapshot (Q47 §47.1 butir 2) diubah melalui koreksi ini setelah jadwal aktif.

**Terkait User Story:** `US-03.03`  
**Prioritas:** 🟡 P1 · Tahap 3 (Q46 §46.3)  
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
- **Batasan dari ISS-05.01 (PR #62):** aktivasi pertama di `ActivateJadwal` dan trigger database `finalisasi_snapshot_saat_jadwal_aktif` (setiap transisi status ke `aktif`, termasuk jalur buka-kembali yang akan datang) membekukan **seluruh** snapshot jadwal yang masih `komposisi_final = false`. Bila koreksi berversi memakai draf snapshot yang belum terbit, kedua jalur itu wajib mengecualikan draf koreksi agar tidak ikut dibekukan sebelum waktunya.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given snapshot sudah terbit, baik belum maupun sudah dirujuk RA/Pengukuran/versi pengajuan, When koreksi diminta, Then baris snapshot dan komponennya tidak diubah in-place; koreksi dibuat sebagai versi baru (AC-2) dan audit menyimpan konteks lama/baru. *(Q35 §35.1)*
- [ ] **AC-2:** Given snapshot sudah terbit, When koreksi diperlukan, Then baris versi baru dibuat dengan `nomor_versi + 1`, `menggantikan_id`, `alasan_koreksi`, dan `rujukan_koreksi`.
- [ ] **AC-3:** Given versi lama telah dirujuk laporan/pengajuan, Then versi lama dan komponen anaknya tetap immutable.
- [ ] **AC-4:** Given pengukuran hendak memakai snapshot koreksi, Then pengukuran harus diajukan dan disahkan ulang; tidak ada propagasi diam-diam.
- [ ] **AC-5:** Given versi snapshot tidak cocok indikator/jadwal, When koreksi dibuat, Then ditolak.
- [ ] **AC-6:** Given revisi PK berversi yang mengubah target disetujui (US-02.12), When versi snapshot baru diterbitkan, Then versi tersebut memuat `periode_mulai_id` periode pertama yang belum ditutup dan `rujukan_koreksi` surat persetujuan Eselon I, sedangkan periode sebelumnya tetap memakai versi lama. *(Q40 §40.1 butir 4 dan 9)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `jadwal_snapshot`, `jadwal_snapshot_komponen`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Jaga snapshot idempoten/berversi: snapshot yang telah terbit tidak boleh di-update in-place (Q35 §35.1).

**B. Backend / Domain**
- [ ] Koreksi selalu membuat versi pengganti dengan `menggantikan_id`, alasan, dan rujukan resmi, baik sebelum maupun sesudah snapshot dirujuk, dan selalu diaudit (Q35 §35.1).

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `target:update` / permission substantif terkait serta guard jadwal.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given snapshot sudah terbit, baik belum maupun sudah dirujuk RA/Pengukuran/versi pengajuan, When koreksi diminta, Then baris snapshot dan komponennya tidak diubah in-place; koreksi dibuat sebagai versi baru (AC-2) dan audit menyimpan konteks lama/baru. *(Q35 §35.1)*
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given snapshot sudah terbit, When koreksi diperlukan, Then baris versi baru dibuat dengan `nomor_versi + 1`, `menggantikan_id`, `alasan_koreksi`, dan `rujukan_koreksi`.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given versi lama telah dirujuk laporan/pengajuan, Then versi lama dan komponen anaknya tetap immutable.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given pengukuran hendak memakai snapshot koreksi, Then pengukuran harus diajukan dan disahkan ulang; tidak ada propagasi diam-diam.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given versi snapshot tidak cocok indikator/jadwal, When koreksi dibuat, Then ditolak.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given revisi PK berversi yang mengubah target disetujui (US-02.12), When versi snapshot baru diterbitkan, Then versi tersebut memuat `periode_mulai_id` periode pertama yang belum ditutup dan `rujukan_koreksi` surat persetujuan Eselon I, sedangkan periode sebelumnya tetap memakai versi lama. *(Q40 §40.1 butir 4 dan 9)*
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
- [ ] Jaga snapshot idempoten/berversi: snapshot yang telah terbit tidak boleh di-update in-place (Q35 §35.1).

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

> **Q46, Q47 (13 Oktober 2026):** TW III 2026 ikut menjadi periode lampau yang diisi Perencanaan; TW IV 2026 menjadi triwulan pertama workflow normal PIC operasional dengan pelaporan awal Januari 2027 (Q46 §46.1 butir 2). Gerbang baru Q42–Q45 dikecualikan untuk periode lampau; kunci per triwulan (Q41 §41.2) bukan bagian pengecualian karena periode lampau terkunci bagi PIC sejak aktivasi, sedangkan Perencanaan yang mengisinya tidak terikat kunci (Q46 §46.1 butir 1, dikoreksi 13 Oktober 2026). Target periode lampau boleh kosong (Q47 §47.2 butir 4). Issue ini belum dikerjakan sehingga scope, AC, dan test di bawah diperbarui langsung.
>
> **Q63 (19 Oktober 2026):** bukti wajib tahap `rencana_aksi` dikecualikan hanya pada jalur Perencanaan (RA data awal 2026 dan RA seluruh-periode-lampau); PIC tidak pernah dikecualikan, termasuk gerbang minimal satu kegiatan; alasan deviasi PK tetap wajib. Pengecualian kegiatan lampau untuk RAB/tahap `kegiatan_perencanaan` (Q42) tidak berubah.

**Terkait User Story:** `US-03.05`  
**Prioritas:** 🟡 P1 · Tahap 1, pengecualian periode lampau (Q46 §46.3)  
**Story Points:** 5  
**Labels:** `feature`, `schedule`, `P1`, `audit-sensitive`

#### Scope

Mendukung TW I–III 2026 sebagai periode lampau berdasarkan `pengisian_selesai < activated_at`, tanpa flag/backfill mode khusus (Q46 §46.1 butir 2; semula Q32: TW I–II). Perencanaan mengisi periode lampau; PK 2026 tetap wajib; gerbang RA/komponen/bukti normal serta gerbang baru Q42–Q45 (RA minimal satu kegiatan, RAB, persyaratan tahap perencanaan kegiatan, narasi wajib, dan tindak lanjut rekomendasi) dikecualikan pada jalur ini (Q46 §46.1 butir 1). Kunci per triwulan tetap berlaku: periode lampau terkunci bagi PIC sejak aktivasi (Q41 §41.2 butir 4). Data awal 2026 diisi Perencanaan dari dokumen resmi 2026 (Q46 §46.1 butir 6; rincian pada ISS-15.01).

#### Acceptance Criteria

- [ ] TW I–III 2026 teridentifikasi sebagai periode lampau dari jadwal (jendela pengisiannya sudah lewat saat aktivasi), bukan flag `is_backfill`. *(Q46 §46.1 butir 2; semula TW I–II)*
- [ ] Perencanaan dapat mengisi periode lampau; user scoped mengikuti jendela dan tidak memperoleh bypass otomatis.
- [ ] Gerbang RA/komponen/bukti normal dikecualikan untuk jalur periode lampau, sedangkan PK 2026 tetap wajib.
- [ ] Gerbang baru Q42–Q45 — RA minimal satu kegiatan, RAB, persyaratan tahap perencanaan kegiatan, narasi wajib, dan tindak lanjut rekomendasi — dikecualikan untuk periode lampau, dan story yang menambah gerbang tersebut menyertakan pengecualian ini beserta test-nya. Kunci per triwulan bukan bagian pengecualian ini: periode lampau tetap terkunci bagi PIC sejak aktivasi dan diisi Perencanaan, yang dikecualikan dari kunci tersebut. *(Q46 §46.1 butir 1 dan 4, Q41 §41.2 butir 3–4)*
- [ ] RA 2026 yang dicatat Perencanaan sebagai data awal dikecualikan dari gerbang kegiatan minimal dan RAB; kegiatan TW IV ditambahkan lewat klaim tahap pengukuran atau buka-kembali RA oleh Perencanaan; kegiatan data awal dan kegiatan pada periode lampau boleh tanpa anggaran (tercatat belum diisi). *(Q46 §46.1 butir 3)*
- [ ] Target RA periode lampau boleh kosong dan dicatat sebagai tidak ditargetkan, berbeda dari `0`; target TW I–III yang tertulis 0 pada Rencana Aksi 2026 dicatat kosong. *(Q46 §46.1 butir 6, Q47 §47.2 butir 4)*
- [ ] Data awal 2026 diisi Perencanaan dari dokumen resmi 2026: target PK, target periode dan kegiatan RA, serta realisasi, narasi, dan rekomendasi TW I–II; data yang belum dikonfirmasi mengikuti asumsi kerja dan tidak dipakai di produksi sebelum dikonfirmasi Perencanaan. *(Q46 §46.1 butir 6)*
- [ ] 2025 tidak dibuat sebagai baris Jadwal/Pengukuran; hanya baseline.
- [ ] Audit tetap mencatat aktor dan perubahan.

#### Automated Tests

- [ ] Uji tanggal untuk TW I–III vs TW IV dan tidak ada flag `is_backfill` (Q46 §46.1 butir 2).
- [ ] Uji bypass hanya jalur Perencanaan yang memang diizinkan; user scoped tetap mengikuti jendela.
- [ ] Uji PK 2026 tetap menjadi gerbang sementara gerbang RA/komponen/bukti normal dikecualikan pada jalur periode lampau.
- [ ] Uji gerbang baru Q42–Q45 dikecualikan untuk periode lampau, sedangkan periode lampau tetap terkunci bagi PIC dan dapat diisi Perencanaan.
- [ ] Uji RA data awal 2026 tanpa kegiatan minimal, RAB, maupun anggaran kegiatan tetap diterima.
- [ ] Uji target periode lampau yang kosong tersimpan sebagai tidak ditargetkan dan dibedakan dari `0`.
- [ ] UAT: verifikasi data awal 2026 berasal dari dokumen resmi dan data berasumsi kerja tidak dipakai di produksi sebelum dikonfirmasi (rincian ISS-15.01).
- [ ] Uji 2025 tidak dibuat sebagai Jadwal/Pengukuran, serta aktivasi dan pengisian tetap teraudit.

### ISS-03.06 · [Feature] Revisi Resmi Jendela PIC

> **Q41 (13 Oktober 2026):** jendela penyusunan Rencana Aksi tidak ada lagi; revisi jendela PIC pada issue ini berlaku untuk jendela pengisian pengukuran, dan Q18 tetap berlaku untuk jendela tersebut (Q41 §41.1 butir 3). "Jendela" pada AC-2 dibaca sebagai jendela pengisian; Rencana Aksi mengikuti kunci per periode bagi PIC (ISS-05.07). Kunci RA per periode mengikuti `pengisian_selesai` yang berlaku, sehingga perpanjangan resmi jendela pengisian periode P ikut menggeser kunci RA periode P (Q41 §41.2 butir 5). Alasan revisi jendela tetap wajib karena tidak termasuk daftar alasan opsional Q43 §43.3. AC-6 ditambahkan karena issue ini belum dikerjakan.

> **Q57 (17 Oktober 2026):** revisi resmi dapat ikut menyesuaikan jendela reviu periode yang sama karena urutan `pengisian_selesai ≤ reviu_mulai ≤ reviu_selesai`; audit menyimpan seluruh jendela sebelum dan sesudah revisi, penanda `reviu_terlambat` yang sudah tercatat tetap beku, dan reviu sesudahnya mengikuti jendela reviu yang berlaku.

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

- [x] **AC-1:** Given deadline PIC perlu diubah, When Perencanaan mengisi alasan dan batas baru, Then jendela resmi diperbarui dan audit menyimpan batas lama/baru.
- [x] **AC-2:** Given PIC mencoba bekerja di luar jendela yang berlaku, Then ditolak meskipun jadwal berstatus aktif.
- [ ] **AC-3:** Given jadwal sebelumnya ditutup dan sedang dalam sesi koreksi, When jendela PIC dibuka, Then jendela baru wajib berada di dalam `koreksi_mulai..koreksi_sampai` dan hanya untuk lingkup yang diizinkan. *(Dikerjakan ISS-14.03; revisi pada issue ini hanya untuk jadwal `aktif` yang belum melewati `penutupan`.)*
- [x] **AC-4:** Given jendela dibuka untuk PIC, Then grant unit, PIC efektif, deny, status record, dan gerbang kelengkapan tetap wajib.
- [x] **AC-5:** Given `jadwal:buka_kembali` dilakukan tanpa revisi jendela PIC, Then PIC tetap tidak memperoleh akses koreksi otomatis.
- [x] **AC-6:** Given `pengisian_selesai` periode P diperpanjang secara resmi, Then kunci Rencana Aksi periode P bagi PIC ikut bergeser ke batas baru. *(Q41 §41.2 butir 5)*

> **Status implementasi — ISS-03.06 (#90):** centang menunjukkan bukti implementasi dan test:
> - `tests/Feature/Jadwal/JadwalRevisiJendelaTest` (AC-1; penolakan aturan kalender, daftar periode, alasan kosong, revisi basi, jadwal bukan aktif atau lewat penutupan, tanpa izin, dan deny beraudit; aturan jendela Rencana Aksi tidak lagi diperiksa sejak Q41);
> - `tests/Unit/Jadwal/JadwalDraftRulesTest` (aturan kalender bersama untuk revisi);
> - `tests/Feature/Pengukuran/PengukuranRevisiJendelaTest` (AC-2, AC-4, AC-5 untuk pengukuran, dan penanda `reviu_terlambat` pada audit reviu lama tidak berubah);
> - `tests/Feature/RencanaAksi/RencanaAksiRevisiJendelaTest` (AC-6) dan `RencanaAksiKoreksiKunciTanggalTest` (AC-5 untuk Rencana Aksi), dengan regresi `BukaKembaliRencanaAksiTest` dan `RencanaAksiAjukanTest`;
> - `tests/Integration/Jadwal/PeriodeJadwalConcurrencyTest` (dua revisi bersamaan: satu berhasil, satu ditolak basi dan teraudit);
> - `tests/Frontend/JadwalEditor.test.tsx` dan `tests/Frontend/RencanaAksiKoreksi.test.tsx`.
>
> Dalam sesi koreksi, kunci tanggal tetap berlaku bagi PIC pada Rencana Aksi sehingga akses PIC menunggu pembukaan jendela pengisian resmi (Q19, Q41 §41.2 butir 6). Responsivitas mobile belum diuji di browser dan menunggu smoke SSO.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `jadwal_tahunan`/`jadwal_periode`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan perubahan deadline/jendela resmi dengan alasan dan audit before/after. Setelah penutupan, jendela PIC baru harus berada di dalam sesi koreksi dan lingkup koreksi. *(Revisi pada jadwal aktif selesai; pembukaan setelah penutupan dikerjakan ISS-14.03.)*
- [x] Pastikan kunci RA per periode (aturan bersama ISS-05.07 pada `JendelaTulisRencanaAksi`) membaca `pengisian_selesai` yang berlaku setelah revisi, tanpa salinan tanggal terpisah (Q41 §41.2 butir 5).

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `jadwal:update`.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Revisi Resmi Jendela PIC**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given deadline PIC perlu diubah, When Perencanaan mengisi alasan dan batas baru, Then jendela resmi diperbarui dan audit menyimpan batas lama/baru.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given PIC mencoba bekerja di luar jendela yang berlaku, Then ditolak meskipun jadwal berstatus aktif.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given jadwal sebelumnya ditutup dan sedang dalam sesi koreksi, When jendela PIC dibuka, Then jendela baru wajib berada di dalam `koreksi_mulai..koreksi_sampai` dan hanya untuk lingkup yang diizinkan. *(ISS-14.03)*
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given jendela dibuka untuk PIC, Then grant unit, PIC efektif, deny, status record, dan gerbang kelengkapan tetap wajib.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given `jadwal:buka_kembali` dilakukan tanpa revisi jendela PIC, Then PIC tetap tidak memperoleh akses koreksi otomatis.
- [x] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given `pengisian_selesai` periode P diperpanjang secara resmi, Then kunci Rencana Aksi periode P bagi PIC ikut bergeser ke batas baru. *(Q41 §41.2 butir 5)*

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus. *(AC-3/TEST-3 menunggu ISS-14.03.)*
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline. *(Rujukan "Plan Modul 3: 3.12" belum memiliki bagian di Plan Pengembangan.)*
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas). *(Responsivitas menunggu smoke SSO.)*


---

## Bagian 4 — Penugasan Penanggung Jawab (PIC)

### ISS-04.01 · [Feature] Penetapan & Pergantian Penanggung Jawab Indikator

> **Q43 (13 Oktober 2026):** kolom alasan pada penetapan dan pergantian Penanggung Jawab dihapus dari form; kolom basis datanya sudah nullable dan request tanpa alasan diterima (Q43 §43.3 butir 4). Butir "Pergantian wajib alasan" pada kontrak dan AC di bawah dipertahankan sebagai histori implementasi; perubahan dikerjakan pada ISS-13.05. Audit tetap mencatat aktor, waktu, tindakan, dan nilai lama/baru (Q43 §43.1).

> **Implementasi ISS-13.05 (22 Oktober 2026):** field alasan dihapus dari form dan modal konfirmasi PJ (PR #156); payload tidak lagi mengirim `alasan`, sedangkan kolom histori tetap ditampilkan.

**Terkait User Story:** `US-04.01`  
**Prioritas:** 🟡 P1  
**Story Points:** 8  
**Labels:** `feature`, `assignment`, `audit-sensitive`, `frontend`

#### Kontrak Final Q32

- Target PJ dapat berupa **user aktif mana pun**; tidak ada syarat role `pic`.
- Penetapan dilakukan Perencanaan/Superadmin sesuai `penanggung_jawab:update`.
- Assignment tidak memberikan permission.
- Hak kerja scoped dinilai oleh PermissionResolver pada unit indikator; assignment sendiri bukan sumber allow.
- Calon PJ dengan izin kerja belum lengkap: **warning, bukan blokir**.
- Sistem menyediakan daftar **PJ aktif tanpa hak isi** dengan tujuh permission scoped: `pengukuran:create/update`, `rencana_aksi:create/update/ajukan`, `kegiatan:create/update`; tampilkan izin tersedia/kurang menggunakan ACL saat ini. Explicit deny menang dan user nonaktif fail-closed; diagnosis tidak menggantikan resolver pada aksi.
- Pergantian menambah histori baru; baris lama tidak ditimpa.
- Perubahan role user tidak mengakhiri assignment.

#### Kontrak Dasar Histori dan Resolusi — Plan §4.3–4.4 / Issue #54

- Pergantian wajib alasan dan menambah histori tanpa menimpa baris lama.
- PJ efektif pada tanggal acuan adalah assignment dengan tanggal mulai terbesar yang <= tanggal acuan.

#### Aturan PJ — Diratifikasi Q34

Diratifikasi Q34; butir 1 (pergantian di tanggal yang sama) diimplementasikan pada [#70](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/70). PJ-01 dan PJ-03–PJ-05 pada matriks **Status Review dan Traceability ISS-04.01** di bawah (tanggal mutasi lampau/mendatang, penolakan no-op dan pengembalian mantan PJ, pemeliharaan assignment mendatang, serta guard lifecycle) sesuai Q34 §34.1 butir 2–6. PJ-02 (unique indikator/tanggal) digantikan butir 1; unique dilepas dan urutan penugasan disimpan pada kolom `urutan` (#70).

**Decision gate:** ditutup oleh keputusan PM pada Q34, 9 Oktober 2026. Dampak rekonsiliasi dokumen/kode/schema ditelusuri pada matriks di bawah.

#### Acceptance Criteria

> Centang di bagian AC, Automated Tests, dan DoD mencatat implementasi/verifikasi teknis yang tersedia, bukan penyelesaian review, merge, UAT, atau deployment. Persetujuan PJ-01–PJ-05 berasal dari Q34; cakupan monitoring akun nonaktif (D-01) masih memerlukan klarifikasi.

- [x] Penugasan awal user aktif berhasil.
- [x] User nonaktif ditolak.
- [x] User tanpa grant tetap dapat ditetapkan dengan warning.
- [x] Pergantian PJ wajib alasan dan menjaga histori.
- [x] Query PJ efektif menggunakan `tanggal_mulai_berlaku` terbaru <= tanggal acuan.
- [x] Role change tidak menonaktifkan PJ.
- [x] Daftar PJ tanpa hak isi dapat ditampilkan secara akurat.

#### Automated Tests

- [x] Assignment awal/pergantian/histori.
- [x] User nonaktif.
- [x] Warning tanpa grant.
- [x] Monitoring PJ tanpa hak isi.
- [x] Regression role change tidak menghapus assignment.

#### Definition of Done

- [x] Tidak ada validasi role PIC; authorization dan audit server-side; UI sesuai Design System.

#### Status Review dan Traceability ISS-04.01 — 8 Oktober 2026

**Tindak lanjut teknis M-02/M-03 — terverifikasi lokal, belum dipublikasikan:** penolakan aktor nonaktif pada mutasi PJ mencatat tepat satu audit melalui policy/resolver canonical sebelum respons existing dikirim; assignment dan histori tidak berubah. Monitoring membatasi evaluasi pada 100 kandidat per request dan 20 hasil, meneruskan cursor dari kandidat terakhir yang diperiksa walaupun halaman kosong, serta memulai ulang scan saat filter berubah. Regresi berada pada `tests/Feature/PenanggungJawabTest.php` dan `tests/Frontend/PenanggungJawabMonitoring.test.tsx`; rincian eksekusi/benchmark berada pada laporan QA di luar repository. Status ini tidak menutup dependency keputusan domain atau menyatakan CI patch lokal, merge, UAT, maupun deployment.

**D-01: OPEN — PENDING REQUIREMENT CLARIFICATION.** Frasa “PJ aktif tanpa hak isi” pada Q32, Plan §4.5, dan Issue #54 belum membuktikan keputusan eksplisit mengenai PJ efektif yang kemudian dinonaktifkan. Filter akun aktif existing dipertahankan sementara. Stakeholder perlu memilih **A:** hanya akun aktif dengan assignment efektif dan izin kurang, atau **B:** seluruh assignment efektif yang bermasalah termasuk akun nonaktif. Opsi B memerlukan penjelasan status akun/reason `inactive_user` dan regresi monitoring; kedua opsi tetap menjaga histori, fail-closed, serta tidak memberi grant/aktivasi otomatis. Evidence keputusan perlu ditautkan pada Issue #54 atau dokumen pemilik requirement sebelum perilaku diubah.

**MAJOR: RESOLVED oleh Q34 (9 Oktober 2026).** PJ-01 dan PJ-03–PJ-05 diratifikasi; PJ-02 digantikan butir 1 (pergantian di tanggal yang sama) yang diimplementasikan pada [#70](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/70). Sebelum Q34, pemeriksaan [re-review PR #68 pada `a51cedc`](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/68#issuecomment-6056081808), seluruh komentar/review thread PR, [Issue #54](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/54) beserta komentarnya (belum ada), Q32, ADR, dan histori dokumen belum menemukan persetujuan stakeholder untuk PJ-01–PJ-05. Pernyataan di body PR, implementasi, test, serta penambahan requirement pada branch bukan bukti persetujuan domain. Checklist implementasi existing tidak berarti finding MAJOR, review tim, merge, UAT, atau deployment sudah selesai.

Matriks berikut memetakan setiap aturan PJ terhadap baseline, evidence resmi, status keputusan, dan dampak kode. Status PJ-01–PJ-05 diperbarui menurut Q34 (9 Oktober 2026).

| Rule | Existing baseline | Evidence resmi | Status | Dampak kode |
|---|---|---|---|---|
| Q32: user aktif dengan/tanpa role boleh menjadi PJ; assignment tidak memberi permission; warning hak isi; role change menjaga assignment | Keputusan Penyelarasan §32.2–32.3; Plan §4.5 | Klarifikasi final LLDIKTI 24 September 2026 (Q32); Issue #54, kontrak Q32 | **CONFIRMED** | Pertahankan pemisahan assignment dan role/grant, tujuh izin scoped, canonical PermissionResolver, deny-wins, dan akun nonaktif fail-closed. |
| Resolusi tanggal terbesar ≤ tanggal acuan; pergantian beralasan menambah histori | Plan §4.3–4.4; Data Model baseline §2.19 | Issue #54 AC-4–AC-5 dan Plan Modul 4 | **CONFIRMED** | Pertahankan `PenugasanIndikator::effectiveOn()` dan histori append-only; Rencana Aksi (`EnsureDraftRencanaAksi`, `JendelaTulisRencanaAksi`) dan Pengukuran memakai resolver yang sama dan teruji, termasuk pergantian pada tanggal yang sama. |
| PJ-01: tanggal mutasi lampau dan mendatang diperbolehkan | Plan §4.4 mengizinkan query tanggal acuan lampau, tetapi tidak menetapkan tanggal mutasi yang boleh dipilih | Q34 §34.1 butir 2; aturan awal ditambahkan pada PRD §13, Data Model §2.19, Workflow §22.1 di PR #68 | **CONFIRMED** | `AssignmentRequest` menerima tanggal valid tanpa batas hari ini; `AppendAssignment` memakai tanggal yang dipilih. Jika dibatasi, ubah validasi dan test tanggal; data historis existing tidak dikoreksi otomatis. |
| PJ-02: unique(`indikator_id`, `tanggal_mulai_berlaku`) tanpa tie-break | Data Model baseline §2.19 dan Plan §4.4 meminta PJ efektif, tanpa menetapkan constraint satu assignment per tanggal | Q34 §34.1 butir 1 menggantinya: pergantian PJ pada tanggal yang sama diperbolehkan dengan tepat satu PJ efektif menurut tanggal efektif lalu urutan penugasan; constraint awal ditambahkan pada Data Model §2.19/§5 dan migration PR #68 | **DIGANTI Q34** — diimplementasikan pada [#70](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/70) | `AppendAssignment` tidak lagi menolak tanggal sama; migration `2026_10_09_000002_allow_same_date_penanggung_jawab.php` melepas unique dan menambah kolom identity `urutan`; `effectiveOn()` memilih tanggal lalu `urutan`. Duplikat legacy sebelum `2026_10_06_000001` tetap ditolak migration tersebut dan memerlukan keputusan koreksi data; jangan memilih/menghapus duplikat otomatis. |
| PJ-03: no-op ditolak; mantan PJ boleh kembali sesudah digantikan | Plan §4.3 dan Issue #54 AC-4 menetapkan alasan/histori pergantian; belum mengatur kedua kebijakan ini | Q34 §34.1 butir 3–4; aturan awal ditambahkan pada PRD §13 dan Workflow §22.1 di PR #68 | **CONFIRMED** | `AppendAssignment` membandingkan target dengan PJ efektif pada tanggal mutasi. Jika keputusan berbeda, selaraskan validasi no-op/reassignment dan audit/test; tidak perlu mengubah schema hanya untuk membolehkan no-op. |
| PJ-04: assignment mendatang existing dipertahankan ketika assignment lebih awal ditambahkan | Plan §4.3 dan Issue #54 AC-4 melarang overwrite histori; belum menetapkan kebijakan benturan jadwal mendatang secara spesifik | Q34 §34.1 butir 5; aturan eksplisit awal ditambahkan pada Workflow §22.1 di PR #68 | **CONFIRMED** | `AppendAssignment` hanya menambah baris; jadwal mendatang kembali efektif pada tanggalnya. Jika benturan harus ditolak, tambah guard/test; jika diperlukan pembatalan jadwal, tentukan kontrak data/migration dan audit terlebih dahulu tanpa overwrite histori. |
| PJ-05: mutasi diblokir pada indikator arsip, Renstra diarsipkan, atau unit nonaktif; histori tetap terbaca | Lifecycle entitas dan fail-closed tersedia, tetapi larangan mutasi PJ untuk ketiga kondisi belum dinyatakan pada baseline Modul 4/Issue #54 | Q34 §34.1 butir 6; guard dan tambahan PRD §13/Workflow §22.1 berasal dari PR #68 | **CONFIRMED** | `IndikatorKinerja::assignmentBlockReason()` dipakai `AppendAssignment` dan capability read path. Jika ada pengecualian resmi, selaraskan guard/capability/audit/test; tidak memberi permission baru atau menghapus histori. |

Pembandingan menggunakan [baseline development `6e09189`](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/tree/6e09189516a97035e6a08b05f97cfa9985527b55); tambahan aturan dapat ditelusuri pada histori/diff PR #68. Folder `docs/adr/` tidak tersedia; ADR accepted pada `SAKIP - Architecture Decision Records.md` menetapkan arsitektur/authorization, bukan persetujuan PJ-01–PJ-05. Q34 menutup MAJOR. PJ-01 dan PJ-03–PJ-05 sudah sesuai implementasi; PJ-02 berbeda dan direkonsiliasi pada [#70](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/70) (resolver, constraint, migration, dan test).

**MINOR tetap FIXED:** kontras canonical `Badge` success dan reuse `useFormatTanggal` untuk timestamp histori, tanpa mengubah tanggal efektif/input/payload API. Detail QA dan keterbatasannya dirujuk pada [PR #68](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/68) dan [re-review terkait](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/68#issuecomment-6056081808). US-04.01 AC-3 terverifikasi melalui #70 (10 Oktober 2026) dan AC-4 untuk Rencana Aksi Modul 11 melalui `test_pengaju_berbeda_dari_pembuat_dan_provenance_tidak_berubah_saat_pj_berganti` pada ISS-05.03 (11 Oktober 2026); catatan ini tidak mengubah checkbox atau keputusan domain.

### ISS-05.01 · [Feature] Penyusunan Target Rencana Aksi per Periode

> **Q41, Q42, Q47 (13 Oktober 2026):** Rencana Aksi tidak lagi memakai jendela penyusunan: PIC efektif dengan grant sah dapat menyusun RA sejak jadwal aktif sampai `penutupan`, dan periode P terkunci bagi PIC setelah `pengisian_selesai` P, dengan Perencanaan dikecualikan (Q41 §41.1–§41.2). AC-5 dan TEST-5 digantikan aturan tersebut dan dipertahankan sebagai histori; perubahan dikerjakan pada ISS-05.07. RA kini juga memuat daftar kegiatan per periode yang ditambahkan dari halaman RA (Q42 §42.1; ISS-06.02, ISS-06.08). Untuk indikator dinilai tahunan, target periode sebelum periode nilai akhir boleh kosong dan dibedakan dari `0` (Q47 §47.2; ISS-05.07).

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

> **Status implementasi — PR #62:** centang menunjukkan bukti implementasi dan QA pada `7ec7007`, dengan [CI 9/9 lulus](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/actions/runs/37902466096); bukan persetujuan UAT atau deployment. Bukti memakai test Pest pada `tests/Feature/RencanaAksi/` (antara lain `RencanaAksiTargetTest`, `RencanaAksiIndexTest`, `RencanaAksiWriteGuardTest`, `RencanaAksiAuthorizationTest`, dan `RencanaAksiKoreksiLingkupTest`), test frontend `tests/Frontend/RencanaAksi*.test.tsx`, serta E2E browser lokal pada desktop dan mobile. AC-6/TEST-6 terverifikasi pada pengajuan ISS-05.03 melalui `test_deviasi_tanpa_alasan_ditolak_dan_dengan_alasan_diterima` (ADR-0008); simpan draf tetap hanya memberi peringatan. Kontras teks belum diaudit formal terhadap checklist `design-system.md`.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given indikator manual, When target periode diisi, Then satu target langsung per periode disimpan dengan `komponen_id = NULL`.
- [x] **AC-2:** Given indikator nonmanual, When target diisi, Then target disimpan per komponen snapshot dan nilai turunan dihitung server.
- [x] **AC-3:** Given target periode lebih rendah dari periode sebelumnya, When disimpan, Then sistem memberi warning kumulatif namun tidak memblokir.
- [x] **AC-4:** Given periode sebelum efektivitas indikator, When target diminta, Then periode tersebut dikecualikan sebagai Tidak berlaku.
- [x] **AC-5:** Given PIC berada di luar jendela RA, When mutasi dicoba, Then ditolak; Perencanaan dapat bekerja sampai penutupan/jendela koreksi yang sah.
- [x] **AC-6:** Given target akhir berbeda dari target PK snapshot, Then submit tetap dapat dilakukan hanya setelah alasan deviasi diisi. *(Q34/ADR-0008: kewajiban alasan ditegakkan pada pengajuan ISS-05.03 dan terverifikasi melalui `test_deviasi_tanpa_alasan_ditolak_dan_dengan_alasan_diterima`; ISS-05.01 hanya menampilkan peringatan deviasi dan menyimpan `alasan_deviasi_pk` pada draf.)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `rencana_aksi`, `rencana_aksi_target`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Implementasikan use-case **Penyusunan Target Rencana Aksi per Periode** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [x] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `rencana_aksi:create`, `rencana_aksi:update`.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Penyusunan Target Rencana Aksi per Periode**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given indikator manual, When target periode diisi, Then satu target langsung per periode disimpan dengan `komponen_id = NULL`.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given indikator nonmanual, When target diisi, Then target disimpan per komponen snapshot dan nilai turunan dihitung server.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given target periode lebih rendah dari periode sebelumnya, When disimpan, Then sistem memberi warning kumulatif namun tidak memblokir.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given periode sebelum efektivitas indikator, When target diminta, Then periode tersebut dikecualikan sebagai Tidak berlaku.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given PIC berada di luar jendela RA, When mutasi dicoba, Then ditolak; Perencanaan dapat bekerja sampai penutupan/jendela koreksi yang sah.
- [x] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given target akhir berbeda dari target PK snapshot, Then submit tetap dapat dilakukan hanya setelah alasan deviasi diisi.
- [x] SECURITY: Uji request langsung untuk scope unit lain dan deny yang cocok menghasilkan 403 meski tombol UI disembunyikan.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
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

> **Status implementasi — PR #76:** centang menunjukkan bukti implementasi dan test pada PostgreSQL disposable (`tests/Feature/RencanaAksi/RencanaAksiBuktiHttpTest`, `BuktiRencanaAksiEvaluasiTest`, `tests/Frontend/RencanaAksiBukti.test.tsx`) beserta CI exact-HEAD pada PR; bukan persetujuan UAT/deployment. Hak mutasi bukti diturunkan dari `rencana_aksi:update` pada unit header (Q32.3: `berkas:*` hanya gerbang deny/fail-closed) dan dijawab 403; unit nonaktif, indikator arsip, status di luar `draft`/`dikembalikan`, serta jendela PIC adalah validasi bisnis 422 (Data Model §3.2 langkah 6). Evaluator kelengkapan memakai `EvaluateEvidence` bersama (Plan 13.4). Browser smoke desktop/mobile belum dilakukan.
>
> **Keputusan D1 — diputuskan Q36 (10 Oktober 2026):** mutasi bukti `rencana_aksi`/`pengukuran` hanya selama induk `draft`/`dikembalikan`; sejak `diajukan` koreksi lewat pengembalian beralasan. PRD §18.8, Workflow §10.3/§10.6a, dan Data Model §2.30/§5 telah diselaraskan.
>
> **Handoff ISS-05.03:** `DownloadBuktiRencanaAksi` saat ini hanya melayani bukti yang masih berlaku (`current`). Setelah pembekuan versi ada, unduhan bukti yang dirujuk versi beku tetapi sudah dihapus perlu membaca metadata dari snapshot versi, mengikuti pola `DownloadBuktiPengukuran`.

- [x] **AC-1:** Given persyaratan tahap `rencana_aksi`, When bukti mode valid disimpan, Then `berkas` terhubung ke RA dan jenis persyaratan yang tepat.
- [x] **AC-2:** Given mode tidak diizinkan, When bukti dikirim, Then server menolak.
- [x] **AC-3:** Given `semua_mode_wajib = true`, When sebagian mode belum terpenuhi, Then persyaratan tetap belum lengkap.
- [x] **AC-4:** Given deny/capability induk tidak mengizinkan upload, When request dipanggil langsung, Then 403.
- [x] **AC-5:** Given bukti sudah dibekukan dalam versi pengajuan, Then perubahan setelahnya tidak mengubah snapshot versi tersebut. *(Penghapusan bukti non-destruktif terbukti — soft delete dan file fisik dipertahankan; pembekuan bukti ke dalam versi terverifikasi melalui `test_versi_beku_tidak_berubah_saat_draf_dan_bukti_berubah` pada ISS-05.03.)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `berkas`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Implementasikan use-case **Pemenuhan Bukti Dukung Rencana Aksi** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [x] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: Capability berkas mengikuti kewenangan induk RA; deny berkas tetap berlaku.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Pemenuhan Bukti Dukung Rencana Aksi**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`. *(State loading/disabled/error teruji Vitest; responsivitas dan kontras menunggu browser smoke.)*

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given persyaratan tahap `rencana_aksi`, When bukti mode valid disimpan, Then `berkas` terhubung ke RA dan jenis persyaratan yang tepat.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given mode tidak diizinkan, When bukti dikirim, Then server menolak.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given `semua_mode_wajib = true`, When sebagian mode belum terpenuhi, Then persyaratan tetap belum lengkap.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given deny/capability induk tidak mengizinkan upload, When request dipanggil langsung, Then 403.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given bukti sudah dibekukan dalam versi pengajuan, Then perubahan setelahnya tidak mengubah snapshot versi tersebut. *(`test_hapus_soft_delete_beralasan_teraudit_dan_mempertahankan_file` menjadi regresi soft delete dan retensi file; pembekuan versi terverifikasi melalui `test_versi_beku_tidak_berubah_saat_draf_dan_bukti_berubah` pada ISS-05.03.)*

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus. *(AC-5/TEST-5 masih parsial.)*
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline. *(D1 diputuskan Q36; PRD §18.8, Workflow §10.3/§10.6a, Data Model §2.30/§5, Plan 13.6, dan tabel klasifikasi permission Plan diselaraskan; hak mutasi bukti di Data Model §2.30 serta otorisasi ISS-11.04/US-11.04 diselaraskan dengan Q32.3. Tersisa: Plan 13.5 masih menyebut route bukti memakai permission `berkas:upload`/`berkas:read` tanpa membedakan induk bergerbang unit (Q32.3).)*
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas). *(Menunggu browser smoke desktop/mobile.)*

### ISS-05.03 · [Feature] Pengajuan Rencana Aksi & Pembekuan Versi

> **Q41, Q42, Q46, Q47 (13 Oktober 2026):** pengajuan RA kini juga mensyaratkan minimal satu kegiatan yang diklaim pada tahap RA (Q42 §42.1 butir 3; ISS-06.02) serta persyaratan wajib tahap `kegiatan_perencanaan` untuk setiap kegiatan tersebut, termasuk RAB bagi kegiatan beranggaran lebih dari 0 (Q42 §42.3–§42.4; ISS-06.08); versi RA membekukan daftar kegiatan dan klaim tahap RA yang tertunda pada catatan AC-5. Pengajuan oleh PIC tidak boleh mengubah target atau daftar kegiatan periode yang terkunci, dan target kosong sebelum periode nilai akhir untuk indikator dinilai tahunan diterima (Q41 §41.2, Q47 §47.2; ISS-05.07). Q41 §41.3 menegaskan aturan yang tetap: pengukuran mensyaratkan RA disahkan, target kumulatif yang turun tetap peringatan, dan deviasi target periode terakhir terhadap target PK tetap wajib dijelaskan (ADR-0008). Gerbang baru dikecualikan untuk periode lampau dan RA data awal 2026 (Q46 §46.1 butir 1 dan 3).

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
- **Dampak Data:** `rencana_aksi`, `rencana_aksi_versi`, `audit_log`.

> **Status implementasi — PR #92 (ISS-05.03):** centang menunjukkan bukti implementasi dan test pada PostgreSQL disposable (`tests/Feature/RencanaAksi/RencanaAksiAjukanTest`, `RencanaAksiKesiapanPengajuanTest`, `RencanaAksiVersiBekuTest`, `tests/Unit/RencanaAksi/DeviasiTargetPkTest`, serta race dua koneksi `tests/Integration/Concurrency/MutationConcurrencyTest`) dan test frontend `tests/Frontend/RencanaAksiAjukan.test.tsx`; SHA dan CI exact-HEAD dicatat pada PR, bukan persetujuan UAT/deployment. AC-7/TEST-7 diubah oleh keputusan PM L3 (10 Oktober 2026): ISS-05.03 tidak membuat notifikasi, inbox, atau antrean; antrean berbasis status milik ISS-05.04/ISS-05.05 dan item notifikasi in-app milik ISS-12.02 AC-2. Klaim kegiatan belum dibekukan pada versi karena belum ada writer klaim rencana aksi. Provenance saat PJ berganti terbukti melalui `test_pengaju_berbeda_dari_pembuat_dan_provenance_tidak_berubah_saat_pj_berganti`; bagian REGRESSION tentang F1 diverifikasi bersama ISS-05.04/ISS-05.05. Kontrak `rencana_aksi_versi` dan isi `snapshot` ada di Data Model §2.34.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given target wajib/periode berlaku belum lengkap, When submit, Then pengajuan ditolak.
- [x] **AC-2:** Given bukti wajib belum lengkap sesuai versi persyaratan saat submit, When submit, Then ditolak.
- [x] **AC-3:** Given deviasi target akhir terhadap PK belum memiliki alasan, When submit, Then ditolak.
- [x] **AC-4:** Given seluruh gerbang lolos, When submit commit, Then status header menjadi `diajukan` dan **baris baru `rencana_aksi_versi`** dibuat.
- [x] **AC-5:** Given versi dibuat, Then server membekukan `diajukan_by`, `diajukan_at`, `jalur_pengajuan` (`pic`/`perencanaan`), `dasar_izin_pengajuan`, target, persyaratan, bukti, klaim/narasi yang relevan, dan snapshot konteks. *Catatan: klaim/narasi kegiatan belum dibekukan karena modul Kegiatan belum memiliki writer klaim (non-goal #57).*
- [x] **AC-6:** Given draft dibuat oleh A tetapi diajukan B, Then `diajukan_by = B`; `created_by` tidak dipakai sebagai identitas pengaju F1/F2.
- [ ] **AC-7:** Given submit berhasil, Then RA tampil pada antrean reviu berbasis status (ISS-05.04/ISS-05.05); item notifikasi in-app mengikuti ISS-12.02 AC-2.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `rencana_aksi`, `rencana_aksi_versi`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [x] Pastikan `rencana_aksi_versi` immutable setelah INSERT; submit ulang membuat versi baru, bukan overwrite snapshot lama.

**B. Backend / Domain**
- [x] Submit RA harus berjalan atomik, memvalidasi target/bukti/deviasi PK/waktu/scope/PIC, lalu membuat `rencana_aksi_versi` baru.
- [x] Bekukan `diajukan_by`, `diajukan_at`, `jalur_pengajuan`, `dasar_izin_pengajuan`, target, persyaratan, bukti, klaim/narasi, dan konteks snapshot. *Catatan: klaim/narasi kegiatan belum dibekukan karena modul Kegiatan belum memiliki writer klaim (non-goal #57).*
- [x] `created_by` hanya pembuat draft; jangan gunakan untuk F1/F2.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `rencana_aksi:ajukan` (unit-scoped bagi PIC; global bagi Perencanaan).
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Pengajuan Rencana Aksi & Pembekuan Versi**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [x] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given target wajib/periode berlaku belum lengkap, When submit, Then pengajuan ditolak.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given bukti wajib belum lengkap sesuai versi persyaratan saat submit, When submit, Then ditolak.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given deviasi target akhir terhadap PK belum memiliki alasan, When submit, Then ditolak.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given seluruh gerbang lolos, When submit commit, Then status header menjadi `diajukan` dan **baris baru `rencana_aksi_versi`** dibuat.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given versi dibuat, Then server membekukan `diajukan_by`, `diajukan_at`, `jalur_pengajuan` (`pic`/`perencanaan`), `dasar_izin_pengajuan`, target, persyaratan, bukti, klaim/narasi yang relevan, dan snapshot konteks. *Catatan: klaim/narasi kegiatan belum dibekukan karena modul Kegiatan belum memiliki writer klaim (non-goal #57).*
- [x] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given draft dibuat oleh A tetapi diajukan B, Then `diajukan_by = B`; `created_by` tidak dipakai sebagai identitas pengaju F1/F2.
- [ ] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given submit berhasil, Then RA tampil pada antrean reviu berbasis status (ISS-05.04/ISS-05.05); item notifikasi in-app mengikuti ISS-12.02 AC-2.
- [ ] REGRESSION: Uji pembuat draft berbeda dari pengaju; perubahan role/PIC/grant setelah submit tidak mengubah provenance atau meloloskan F1.
- [x] CONCURRENCY: Uji transaksi/optimistic locking/versi stale sesuai kontrak Data Model.

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

**Keputusan terkait:** Q37 §37.1–§37.3

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** memverifikasi versi RA atau mengembalikannya dengan alasan,  
> **Sehingga** substansi RA diperiksa tanpa membuka celah self-review jalur PIC.

#### Kontrak Teknis

- **Dependensi:** ISS-05.03 / #57 sudah merged ke `development`; RA berstatus `diajukan` dan versi terbaru tersedia. Pengiriman notifikasi in-app menunggu ISS-12.02 (Q37).
- **Otorisasi:** `rencana_aksi:verifikasi`, `rencana_aksi:kembalikan`.
- **Dampak Data:** `rencana_aksi`, versi terkait, `audit_log`, event pengembalian setelah commit.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given versi diajukan melalui `jalur_pengajuan = pic`, When aktor sama dengan `rencana_aksi_versi.diajukan_by` mencoba **memverifikasi**, Then F1 menolak walaupun aktor kemudian memperoleh role/grant lain.
- [ ] **AC-2:** Given aktor berbeda dan memiliki permission efektif, When verifikasi dilakukan, Then status menjadi `diverifikasi` tanpa mengubah payload versi.
- [ ] **AC-3:** Given perbaikan dibutuhkan, When dikembalikan dengan alasan valid, Then status menjadi `dikembalikan`, audit mencatat alasan dan pengaju historis, PJ efektif saat pengembalian ditentukan melalui `PenugasanIndikator::effectiveOn()`, dan event pengembalian dipancarkan setelah commit. **Catatan Q37:** pengiriman in-app menunggu ISS-12.02; cakupan issue ini adalah penentuan penerima dan event setelah commit, dengan otorisasi tautan tetap diperiksa saat dibuka.
- [ ] **AC-4:** Given pengembalian tanpa alasan, When submit, Then ditolak.
- [ ] **AC-5:** Given data substansi perlu diubah saat review, Then data harus dikembalikan lalu diajukan sebagai versi baru; snapshot versi lama tidak ditambal.
- [ ] **AC-6:** Given indikator `arsip` atau unit konteks RA `nonaktif`, When verifikasi/pengembalian dicoba termasuk melalui request langsung, Then `businessErrors` menolak, penolakan diaudit, dan status/payload versi tidak berubah; detail/histori tetap dapat dibaca sesuai otorisasi dan UI menampilkan alasan dari server.
- [ ] **AC-7:** Given versi/status valid, aktor berwenang, dan seluruh business guard lolos, When verifikasi dikonfirmasi tanpa alasan pengguna, Then verifikasi berhasil dan audit memuat alasan baku server serta `dasar_izin`.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `rencana_aksi`, versi terkait, `audit_log`; penyimpanan/pengiriman notifikasi in-app menunggu ISS-12.02.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] F1 wajib diterapkan pada **verifikasi**: pengaju versi jalur PIC tidak boleh memverifikasi versi yang diajukan sendiri.
- [ ] Perubahan substansi saat review harus melalui kembalikan → edit → submit versi baru; jangan menambal snapshot versi.
- [ ] Gunakan `businessErrors` bersama untuk verifikasi, pengembalian, dan pengesahan (ISS-05.05): tolak indikator `arsip`/unit nonaktif; guard jadwal menangani Renstra yang sudah diarsipkan (Data Model §2.9). Periksa ulang state di mutation boundary.
- [ ] Tentukan penerima pengembalian dari `PenugasanIndikator::effectiveOn()` pada saat pengembalian dan pancarkan event hanya setelah commit; pengaju historis tetap pada versi/audit dan tautan tetap diotorisasi saat dibuka. Pengiriman in-app menjadi cakupan ISS-12.02.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `rencana_aksi:verifikasi`, `rencana_aksi:kembalikan`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.
- [ ] Verifikasi memakai alasan baku server dan `dasar_izin`, tanpa mewajibkan alasan pengguna. Pengembalian wajib alasan pengguna yang divalidasi/disanitasi di backend; audit penolakan guard tetap tersimpan walaupun mutasi dibatalkan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Verifikasi & Pengembalian Rencana Aksi dengan F1**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Verifikasi memakai dialog konfirmasi tanpa input alasan; pengembalian memakai **Modal Alasan Audit** dengan validasi backend. Detail/histori tetap dapat dibaca sesuai otorisasi dan alasan guard yang menolak ditampilkan dari server.

**Bukti verifikasi:** hasil pengujian dan batas QA/UAT dicatat pada [PR #93](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/93). Checklist Acceptance Criteria tetap untuk QA/UAT; hasil otomatis tidak mengesahkan UAT, merge atau deployment.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given versi diajukan melalui `jalur_pengajuan = pic`, When aktor sama dengan `rencana_aksi_versi.diajukan_by` mencoba **memverifikasi**, Then F1 menolak walaupun aktor kemudian memperoleh role/grant lain.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given aktor berbeda dan memiliki permission efektif, When verifikasi dilakukan, Then status menjadi `diverifikasi` tanpa mengubah payload versi.
- [x] TEST-3: Uji pengembalian beralasan, audit, dan penerima PJ efektif saat pengembalian (termasuk PJ berubah setelah pengajuan); event hanya dipancarkan setelah commit dan tidak pada rollback. Pengaju historis tetap utuh. Pengiriman in-app diuji pada ISS-12.02, bukan diklaim lulus dari event saja.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given pengembalian tanpa alasan, When submit, Then ditolak.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given data substansi perlu diubah saat review, Then data harus dikembalikan lalu diajukan sebagai versi baru; snapshot versi lama tidak ditambal.
- [x] TEST-6: Uji penolakan verifikasi/pengembalian pada indikator arsip/unit nonaktif, audit penolakan, dan tidak berubahnya status/payload; baca detail/histori serta alasan UI tetap sesuai otorisasi.
- [x] TEST-7: Uji verifikasi tanpa alasan pengguna memakai alasan baku server dan `dasar_izin`; pengembalian tanpa alasan/beralasan tidak valid tetap ditolak.
- [x] REGRESSION: Uji pembuat draft berbeda dari pengaju; perubahan role/PIC/grant setelah submit tidak mengubah provenance atau meloloskan F1.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test dalam cakupan issue ini lulus; AC-3 membuktikan penerima/event setelah commit, sedangkan pengiriman in-app menunggu ISS-12.02 sesuai Q37.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-05.05 · [Feature] Pengesahan Rencana Aksi & Pemisahan Tugas F1/F2

> **Q41 (13 Oktober 2026):** Perencanaan tetap boleh mengesahkan RA yang diinputnya sendiri dengan penanda self-approval (F2), sedangkan jalur PIC tetap dijaga F1 (Q41 §41.3 butir 2). Tidak ada perubahan AC.

**Terkait User Story:** `US-05.05`  
**Prioritas:** 🔴 P0  
**Story Points:** 8  
**Labels:** `feature`, `rencana-aksi`, `P0`, `audit-sensitive`, `frontend`  
**Plan:** Modul 11: 11.3  
**PRD:** §7.6, §14.6  
**Workflow:** §7, §20  
**Data Model/Entitas:** RA + versi

**Keputusan terkait:** Q37 §37.1

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** mengesahkan versi RA yang telah diverifikasi,  
> **Sehingga** RA menjadi dokumen operasional resmi dengan provenance yang dapat diaudit.

#### Kontrak Teknis

- **Dependensi:** RA berstatus `diverifikasi`; versi yang direviu adalah versi terbaru dan tidak stale.
- **Otorisasi:** `rencana_aksi:sahkan` (sensitif).
- **Dampak Data:** `rencana_aksi`, `rencana_aksi_versi`, `audit_log`.

> **Status implementasi — PR #66 (ISS-05.05):** centang menunjukkan bukti implementasi dan test pada PostgreSQL disposable (`tests/Feature/RencanaAksi/SahkanRencanaAksiTest`, `RencanaAksiVersiBekuTest`, serta race dua koneksi `tests/Integration/Concurrency/MutationConcurrencyTest`) dan test frontend `tests/Frontend/RencanaAksiReview.test.tsx`; SHA dan CI exact-HEAD dicatat pada PR, bukan persetujuan UAT/deployment. Sesuai D1 (Keputusan Penyelarasan §37.5), pengesahan memakai antrean, halaman review, dan `GerbangReviewRencanaAksi` milik ISS-05.04. Versi pengajuan pada test dibentuk fixture bersama sesuai kontrak Data Model §2.34, lalu diverifikasi dan disahkan melalui Action/HTTP nyata. Pengesahan tidak mewajibkan alasan (alasan wajib hanya pada pengembalian), sehingga item Modal Alasan Audit tidak dicentang. Checklist `design-system.md` dan browser smoke desktop/mobile belum dilakukan.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given versi diajukan melalui jalur PIC, When aktor sama dengan `diajukan_by` mencoba mengesahkan, Then **F1 menolak**.
- [x] **AC-2:** Given versi diajukan oleh PIC dan aktor Perencanaan lain berizin, When sahkan, Then header menjadi `disahkan` dan metadata pengesahan pada versi terisi atomik.
- [x] **AC-3:** Given versi diajukan melalui `jalur_pengajuan = perencanaan`, When pengaju yang sama memverifikasi/mengesahkan dan masih memiliki permission efektif, Then **F2 mengizinkan** serta mencatat `self_approval`.
- [x] **AC-4:** Given terdapat deny atau permission reviewer hilang, When F2 dicoba, Then tetap ditolak; F2 bukan bypass resolver.
- [x] **AC-5:** Given versi yang hendak disahkan bukan versi terbaru yang sedang direviu, When aksi dilakukan, Then ditolak untuk mencegah pengesahan payload stale.
- [x] **AC-6:** Given RA disahkan, Then bukti yang dirujuk versi resmi tidak boleh dihapus.
- [x] **AC-7:** Given indikator `arsip` atau unit konteks RA `nonaktif`, When pengesahan dicoba termasuk pada jalur F2 dan request langsung, Then `businessErrors` yang sama dengan verifikasi/pengembalian menolak, penolakan diaudit, dan header/metadata versi tidak berubah; detail/histori tetap dapat dibaca sesuai otorisasi dan UI menampilkan alasan dari server.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `rencana_aksi`, `rencana_aksi_versi`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [x] Pastikan `rencana_aksi_versi` immutable setelah INSERT; submit ulang membuat versi baru, bukan overwrite snapshot lama.

**B. Backend / Domain**
- [x] F1/F2 harus membaca `rencana_aksi_versi.diajukan_by` dan `jalur_pengajuan`; perubahan role/PIC/grant setelah submit tidak mengubah provenance.
- [x] Pengesahan wajib mengunci versi terbaru yang sedang direviu dan menolak versi stale.
- [x] F2 self-approval jalur Perencanaan tetap wajib lolos resolver permission/deny dan dicatat sebagai `self_approval`.
- [x] Gunakan `businessErrors` yang sama dengan verifikasi/pengembalian ISS-05.04: tolak indikator `arsip` atau unit konteks RA nonaktif, termasuk jalur F2; periksa ulang pada mutation boundary. Renstra diarsipkan tertutup oleh guard jadwal (Data Model §2.9).

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `rencana_aksi:sahkan` (sensitif).
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.
- [x] Audit penolakan guard indikator arsip/unit nonaktif tanpa mengubah header/metadata versi; penolakan tidak hilang akibat rollback mutasi.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Pengesahan Rencana Aksi & Pemisahan Tugas F1/F2**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [x] Detail/histori tetap dapat dibaca sesuai otorisasi; tampilkan alasan aksi tidak tersedia dari server untuk indikator arsip/unit nonaktif.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given versi diajukan melalui jalur PIC, When aktor sama dengan `diajukan_by` mencoba mengesahkan, Then **F1 menolak**.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given versi diajukan oleh PIC dan aktor Perencanaan lain berizin, When sahkan, Then header menjadi `disahkan` dan metadata pengesahan pada versi terisi atomik.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given versi diajukan melalui `jalur_pengajuan = perencanaan`, When pengaju yang sama memverifikasi/mengesahkan dan masih memiliki permission efektif, Then **F2 mengizinkan** serta mencatat `self_approval`.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given terdapat deny atau permission reviewer hilang, When F2 dicoba, Then tetap ditolak; F2 bukan bypass resolver.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given versi yang hendak disahkan bukan versi terbaru yang sedang direviu, When aksi dilakukan, Then ditolak untuk mencegah pengesahan payload stale.
- [x] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given RA disahkan, Then bukti yang dirujuk versi resmi tidak boleh dihapus.
- [x] TEST-7: Uji guard bersama pada indikator arsip/unit nonaktif termasuk F2/direct HTTP, audit penolakan, header/metadata versi tetap utuh, serta akses detail/histori dan alasan UI sesuai otorisasi.
- [x] REGRESSION: Uji pembuat draft berbeda dari pengaju; perubahan role/PIC/grant setelah submit tidak mengubah provenance atau meloloskan F1.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-05.06 · [Feature] Buka-Kembali Rencana Aksi yang Telah Disahkan

> **Q40, Q41, Q46 (13 Oktober 2026):** buka-kembali RA oleh Perencanaan dipakai bila target periode yang belum terkunci perlu diubah, termasuk penyesuaian setelah revisi PK (Q40 §40.1 butir 7, Q41 §41.4 butir 4), bukan untuk memindahkan kegiatan yang mundur; kegiatan tersebut ditandai ditunda dan dilanjutkan sebagai kegiatan lanjutan (ISS-06.04, ISS-06.05; Q41 §41.4 butir 1–3). Untuk RA data awal 2026, kegiatan TW IV dapat ditambahkan lewat buka-kembali oleh Perencanaan atau klaim tahap pengukuran (Q46 §46.1 butir 3). Setelah dibuka kembali, perubahan oleh PIC tetap tidak boleh mengubah target atau daftar kegiatan periode yang terkunci (Q41 §41.2 butir 2). Dalam sesi koreksi setelah penutupan, PIC hanya dapat mengubah RA pada periode yang jendela pengisiannya dibuka kembali secara resmi (ISS-14.03, Q19); di luar itu koreksi RA dilakukan Perencanaan (Q41 §41.2 butir 6). Alasan buka-kembali tetap wajib (Q43 §43.2 butir 1). Issue ini belum di-merge sehingga AC-3 diperbarui, AC-6 dan AC-7 ditambahkan, serta task dan test disesuaikan.

**Terkait User Story:** `US-05.06`  
**Prioritas:** 🟡 P1 · Tahap 1, berjalan (Q46 §46.3)  
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

- [x] **AC-1:** Given alasan valid dan waktu koreksi sah, When buka kembali dilakukan, Then header berpindah ke `dikembalikan`.
- [x] **AC-2:** Given versi lama telah disahkan, When RA dibuka kembali, Then versi lama tetap immutable dan tetap dapat dibaca sebagai histori.
- [x] **AC-3:** Given PIC perlu memperbaiki sebelum `penutupan`, Then PIC dapat bekerja tanpa jendela penyusunan RA selama grant + PIC efektif valid, tetapi hanya pada periode yang belum terkunci baginya. *(Diperbarui Q41 §41.1–§41.2; semula PIC hanya dapat bekerja bila jendela PIC resmi masih/baru dibuka. Terbukti CI exact-HEAD run 38533032704 9/9.)*
- [x] **AC-4:** Given jadwal ditutup tanpa sesi koreksi, When buka kembali RA langsung dicoba, Then ditolak.
- [x] **AC-5:** Given RA diajukan ulang setelah perbaikan, Then baris `rencana_aksi_versi` baru dibuat.
- [ ] **AC-6:** Given RA dibuka kembali, When PIC menyimpan draf atau mengajukan versi baru yang mengubah target atau daftar kegiatan periode yang sudah terkunci baginya, Then ditolak sebagai validasi bisnis (422) dan diaudit; Perencanaan dikecualikan sampai `penutupan`. *(Q41 §41.2 butir 2–3; guard level target terbukti, klausa daftar kegiatan menunggu writer klaim ISS-06.02/06.08)*
- [ ] **AC-7:** Given sesi koreksi setelah penutupan, When PIC mengubah RA, Then perubahan hanya diizinkan pada periode yang jendela pengisiannya dibuka kembali secara resmi (US-14.03); koreksi RA di luar periode tersebut dilakukan Perencanaan. *(Q41 §41.2 butir 6, Q19. Menunggu CI exact-HEAD untuk penilaian lingkup berbasis perubahan M1.)*

> **Status implementasi — ISS-05.06 (#85):** centang menunjukkan bukti implementasi dan test (`tests/Feature/RencanaAksi/BukaKembaliRencanaAksiTest`, regresi `RencanaAksiAjukanTest`/`SahkanRencanaAksiTest`/`ReviewRencanaAksiTest`, `tests/Frontend/RencanaAksiBukaKembali.test.tsx`, `tests/Frontend/RencanaAksiKoreksi.test.tsx`, `tsc`, Pint, PHPStan bersih) pada branch `feature/iss-05.06-buka-kembali-ra`; bukan persetujuan UAT, merge, maupun deployment. Selaras Q41: jendela penyusunan tidak lagi dibaca, guard kunci per periode berbasis perubahan di `JendelaTulisRencanaAksi`. Review 14 Oktober 2026: AC-6/TEST-6/DoD-1 dikembalikan `[ ]` karena frasa "atau daftar kegiatan" baru ditegakkan pada level target periode — writer klaim tahap RA belum ada (milik ISS-06.02/06.08). AC-3/TEST-3 dan AC-7/TEST-7 dikembalikan `[ ]` menunggu CI exact-HEAD untuk kunci berbasis perubahan (B1) dan lingkup koreksi berbasis perubahan (M1). Checklist `design-system.md` dan browser smoke desktop/mobile belum dilakukan.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `rencana_aksi`, versi historis, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [x] Pastikan `rencana_aksi_versi` immutable setelah INSERT; submit ulang membuat versi baru, bukan overwrite snapshot lama.

**B. Backend / Domain**
- [x] Buka kembali hanya mengubah header ke jalur revisi; pengajuan pengukuran ditahan sampai versi baru disahkan, sedangkan versi yang disahkan sebelumnya tetap tersimpan dan dapat dibaca sebagai histori.
- [ ] PIC tetap tunduk kunci per periode berbasis perubahan (Q41 §41.2; aturan bersama ISS-05.07 pada `JendelaTulisRencanaAksi`); dalam sesi koreksi pascapenutupan PIC hanya dapat mengubah RA pada periode yang jendela pengisiannya dibuka kembali secara resmi (Q41 §41.2 butir 6, ISS-14.03). Buka RA tidak otomatis membuka periode yang terkunci maupun memperpanjang deadline. *(Menunggu CI exact-HEAD B1/M1.)*

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `rencana_aksi:buka_kembali` (sensitif).
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Buka-Kembali Rencana Aksi yang Telah Disahkan**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [x] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given alasan valid dan waktu koreksi sah, When buka kembali dilakukan, Then header berpindah ke `dikembalikan`.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given versi lama telah disahkan, When RA dibuka kembali, Then versi lama tetap immutable dan tetap dapat dibaca sebagai histori.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given PIC perlu memperbaiki sebelum `penutupan`, Then PIC dapat bekerja tanpa jendela penyusunan RA selama grant + PIC efektif valid, tetapi hanya pada periode yang belum terkunci baginya. *(Diperbarui Q41 §41.1–§41.2; semula PIC hanya dapat bekerja bila jendela PIC resmi masih/baru dibuka. Terbukti CI exact-HEAD run 38533032704 9/9.)*
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given jadwal ditutup tanpa sesi koreksi, When buka kembali RA langsung dicoba, Then ditolak.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given RA diajukan ulang setelah perbaikan, Then baris `rencana_aksi_versi` baru dibuat.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given RA dibuka kembali, When PIC menyimpan draf atau mengajukan versi baru yang mengubah target atau daftar kegiatan periode yang sudah terkunci baginya, Then ditolak sebagai validasi bisnis (422) dan diaudit; Perencanaan dikecualikan sampai `penutupan`. *(Q41 §41.2 butir 2–3; guard level target terbukti, klausa daftar kegiatan menunggu writer klaim ISS-06.02/06.08)*
- [ ] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given sesi koreksi setelah penutupan, When PIC mengubah RA, Then perubahan hanya diizinkan pada periode yang jendela pengisiannya dibuka kembali secara resmi (US-14.03); koreksi RA di luar periode tersebut dilakukan Perencanaan. *(Q41 §41.2 butir 6, Q19. Menunggu CI exact-HEAD M1.)*

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus. *(AC-3/TEST-3 menunggu CI B1, AC-7/TEST-7 menunggu CI M1, AC-6/TEST-6 menunggu guard daftar kegiatan; lihat catatan status)*
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-05.07 · [Feature] Penyusunan Rencana Aksi Sepanjang Tahun, Kunci per Periode, dan Periode tanpa Target

**Status implementasi (2026-10-20):** Sesuai [Keputusan PM M1 pada PR #105](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/105#issuecomment-6189887938), PR #105 memiliki kontrak minimum Q47: penanda dinilai tahunan beserta audit dan snapshot, target kosong yang sah dan status beku `tidak_ditargetkan`, serta gerbang pengajuan/review RA dan pembacanya pada pengukuran. Checklist yang dicentang didukung automated test pada `021c911` dan [CI 9/9](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/actions/runs/38716756264), bukan penerimaan UAT. `RencanaAksiTanpaTargetTest` membuktikan simpan/baca/bekukan kosong vs `0`, pengajuan sampai pengesahan, penolakan target wajib/komponen sebagian, audit penanda, dan imutabilitas snapshot; `DinilaiTahunanSnapshotTest` membuktikan pembekuan saat aktivasi; `RencanaAksiAjukanTest` melindungi rekonsiliasi/deviasi target PK. Pembaruan 20 Oktober 2026: #130 menerapkan Q41 §41.1–§41.2 dan melengkapi Q47 §47.1–§47.2 sesuai keputusan PM 19 Oktober 2026. Jendela penyusunan RA dihapus dari form, validasi, props, dan kesiapan aktivasi jadwal; `rencana_aksi_mulai`/`rencana_aksi_selesai` yang masih dikirim ditolak sebagai field asing. Kunci per periode `JendelaTulisRencanaAksi` dibuktikan untuk PIC, Perencanaan, periode lampau, revisi jendela, dan sesi koreksi; periode lampau 2026 yang jendelanya diperpanjang melewati tanggal aktivasi menjadi periode normal. Target kosong pada periode yang terkunci bagi PIC ditolak dengan pesan yang menyebut Tim Perencanaan. Matriks target menandai periode terkunci ("Terkunci" atau "Terkunci bagi PIC" beserta batas pengisiannya) dan target "Tidak ditargetkan"; kartu RA menampilkan badge "Dinilai tahunan". Checklist yang dicentang untuk #130 didukung automated test lokal (Pest pada PostgreSQL `sakip_test` dan Vitest) serta browser smoke dengan data sintetis pada `aa71c25`, bukan penerimaan UAT.

**Sisa cakupan:** DoD dokumentasi/traceability belum dicentang karena beberapa frasa lama (antara lain "periode lampau" tanpa "2026") masih ada di PRD, Plan, Data Model, User Stories, serta Rule dan Task B issue ini; sesuai [keputusan PM](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/130#issuecomment-6322219488), maknanya adalah periode lampau jadwal 2026, dan frasa itu diselaraskan pada PR dokumen terpisah, sehingga DoD ini tetap terbuka sampai PR tersebut merge. Task constraint/locking (A) belum dipetakan ke bukti. Task C bagian `indikator:update` untuk penanda dan `dasar_izin` pada audit penolakan periode terkunci, beserta DoD audit `dasar_izin`, belum dibuktikan oleh test yang dipetakan. Teks checklist SECURITY diselaraskan dengan keputusan PM yang sama: tanpa grant unit sah atau dengan deny yang cocok ditolak 403, sedangkan aktor yang bukan PIC efektif ditolak 422 sebagai validasi bisnis karena penugasan PIC bukan izin. Checklist itu dan DoD "Semua Acceptance Criteria dan test pada issue ini lulus" dicentang berdasarkan test yang ada dan [CI 9/9](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/actions/runs/39574046400) pada `4686301`. Di luar cakupan: penolakan perubahan atribut kegiatan periode terkunci sejak Simpan (issue ISS-06.x yang dibuat PM; saat ini ditolak saat Ajukan), migrasi lanjutan yang menghapus kolom jendela RA beserta CHECK `jadwal_tahunan_urutan_ra`, serta badge "Dinilai tahunan" di Sasaran & Indikator dan halaman Review RA. Task kolom penanda mencakup pembekuan dan larangan menulis ulang snapshot; implementasi fitur koreksi snapshot tetap milik ISS-03.03. Gerbang RA lintas modul dan urutan kunci akan direview ulang PM setelah integrasi #106.

**Terkait User Story:** `US-05.07`  
**Prioritas:** Tahap 1 (Q46 §46.3: sebelum integrasi 9 November 2026)  
**Story Points:** Belum diestimasi  
**Labels:** `feature`, `rencana-aksi`, `schedule-snapshot`, `audit-sensitive`, `frontend`  
**Plan:** Modul 11: 11.9  
**PRD:** §12.2–12.3, §14.1, §14.3–14.4, §14.7, §14.9  
**Workflow:** §4, §7  
**Data Model/Entitas:** `jadwal_tahunan`, `indikator.dinilai_tahunan`, `jadwal_snapshot.dinilai_tahunan`, `rencana_aksi_target`

#### User Story

> **Sebagai** PIC dan Tim Perencanaan,  
> **Saya ingin** menyusun dan mengubah Rencana Aksi kapan pun selama jadwal tahun berjalan aktif, dengan periode yang sudah lewat terkunci, serta mengosongkan target periode untuk indikator yang dinilai tahunan,  
> **Sehingga** Rencana Aksi mengikuti praktik penyusunan LLDIKTI tanpa mengubah periode yang sudah dilaporkan.

#### Kontrak Teknis

- **Dependensi:** ISS-05.01 (target RA), ISS-05.03 (pengajuan RA), ISS-03.01/03.02 (jadwal dan snapshot), ISS-02.04 (form indikator untuk penanda).
- **Otorisasi:** `rencana_aksi:create`/`update`/`ajukan` (PIC dengan grant unit sah dan PIC efektif; Perencanaan global); penanda dinilai tahunan: `indikator:update`.
- **Dampak Data:** `jadwal_tahunan.rencana_aksi_mulai/selesai` (tidak dibaca, dihapus dari form, kolom nullable), `indikator.dinilai_tahunan`, `jadwal_snapshot.dinilai_tahunan`, `rencana_aksi_target`, `audit_log`.
- **Keputusan terkait:** Q41, Q47 §47.1–§47.2, Q46 §46.1.
- **Rule:** Perencanaan boleh mengesahkan RA yang diinputnya sendiri dengan penanda self-approval (Q41 §41.3 butir 2). Kegiatan yang mundur ditangani ISS-06.04/06.05 tanpa buka-kembali RA (Q41 §41.4); buka-kembali RA (ISS-05.06) hanya untuk target periode yang belum terkunci.
- **Rule:** gerbang baru Q42–Q45 dikecualikan untuk periode lampau, tetapi kunci per periode bukan bagian pengecualian: periode lampau terkunci bagi PIC sejak aktivasi, sedangkan Perencanaan tidak terikat kunci (Q46 §46.1 butir 1, Q41 §41.2 butir 3–4). Data awal 2026: target TW I–III dicatat kosong (Q47 §47.2 butir 4).
- **Rule:** kunci mengikuti nilai `pengisian_selesai` yang berlaku, termasuk hasil revisi resmi jendela pengisian (Q18, ISS-03.06), sehingga perpanjangan jendela pengisian periode P ikut menggeser kunci RA periode P bagi PIC (Q41 §41.2 butir 5). Dalam sesi koreksi setelah penutupan, PIC hanya dapat mengubah RA pada periode yang jendela pengisiannya dibuka kembali secara resmi (ISS-14.03); di luar itu koreksi RA dilakukan Perencanaan (Q41 §41.2 butir 6).
- **Rule:** pada versi RA yang dibekukan, periode tanpa target dicatat dengan status `tidak_ditargetkan`, bukan `belum_diisi`, sehingga laporan dapat membedakannya dari target yang belum diisi (Q47 §47.2 butir 5).
- **Di luar cakupan:** kegiatan dalam RA (ISS-06.02, ISS-06.08); tampilan periode tanpa target pada pengukuran dan dashboard (ISS-07.03, ISS-10.01).

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given jadwal tahun berstatus `aktif` dan `penutupan` belum lewat, When PIC efektif dengan grant sah membuat, mengubah, atau mengajukan RA, Then aksi diizinkan tanpa memeriksa jendela penyusunan; `rencana_aksi_mulai`/`rencana_aksi_selesai` tidak lagi dibaca. *(Q41 §41.1 butir 1)*
- [x] **AC-2:** Given form jadwal tahunan, Then isian jendela penyusunan RA tidak ditampilkan dan tidak divalidasi; kolomnya dibiarkan nullable. *(Q41 §41.1 butir 2)*
- [x] **AC-3:** Given `pengisian_selesai` periode P sudah lewat (tanggal bisnis Asia/Makassar), When PIC menyimpan draf, mengajukan, atau menyimpan versi baru RA yang mengubah target atau daftar kegiatan periode P, Then ditolak sebagai validasi bisnis (422) dan diaudit; perubahan pada periode yang belum terkunci tetap diterima. *(Q41 §41.2 butir 1–2)*
- [x] **AC-4:** Given aktor Perencanaan, When mengubah periode yang terkunci bagi PIC sebelum `penutupan`, Then diizinkan. *(Q41 §41.2 butir 3)*
- [x] **AC-5:** Given periode lampau saat jadwal diaktifkan, Then periode itu terkunci bagi PIC sejak aktivasi. *(Q41 §41.2 butir 4, Q46 §46.1)*
- [x] **AC-6:** Given Perencanaan menandai indikator sebagai dinilai tahunan, When disimpan, Then penanda tersimpan dan diaudit; snapshot jadwal yang diaktifkan sesudahnya membekukan penanda tersebut, dan perubahan setelah jadwal aktif berlaku pada jadwal berikutnya; koreksi tahun berjalan lewat koreksi snapshot ISS-03.03 (Q35). *(Q47 §47.1)*
- [x] **AC-7:** Given indikator dinilai tahunan menurut snapshot, When RA diajukan dengan target periode sebelum periode nilai akhir kosong, Then pengajuan diterima; target periode nilai akhir tetap wajib dan direkonsiliasi dengan target PK (ADR-0008). *(Q47 §47.2 butir 1)*
- [x] **AC-8:** Given indikator yang tidak dinilai tahunan, When salah satu target periode kosong saat pengajuan, Then pengajuan ditolak dengan pesan per periode, kecuali periode lampau 2026. *(Q47 §47.2 butir 2)*
- [x] **AC-9:** Given target periode dikosongkan, Then disimpan sebagai tidak ditargetkan dan dibedakan dari target `0`. *(Q47 §47.2 butir 3)*
- [x] **AC-10:** Given RA belum disahkan, When pengukuran diajukan, Then ditolak, kecuali periode lampau 2026 (Q32.7; Q32.8 diperbarui Q46); target kumulatif yang turun tetap peringatan. *(Q41 §41.3 butir 1 dan 3)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Tambahkan `indikator.dinilai_tahunan` (boolean, default `false`) dan salinannya `jadwal_snapshot.dinilai_tahunan` yang dibekukan saat snapshot dibentuk (`ActivateJadwal`); perubahan setelah jadwal aktif mengikuti koreksi snapshot ISS-03.03 (Q35, Q47 §47.1 butir 2).
- [x] Biarkan `jadwal_tahunan.rencana_aksi_mulai`/`rencana_aksi_selesai` nullable tanpa dibaca sampai migrasi lanjutan (Q41 §41.1 butir 2).
- [x] Pastikan `rencana_aksi_target` menyimpan nilai kosong sebagai "tidak ditargetkan" yang berbeda dari `0`, dan bekukan periode tersebut pada `rencana_aksi_versi` dengan status `tidak_ditargetkan`, bukan `belum_diisi` (Q47 §47.2 butir 5; Data Model §2.34).
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] `JendelaTulisRencanaAksi` menerapkan kunci per periode Q41 tanpa membaca jendela penyusunan: PIC efektif dengan grant sah boleh menulis sejak jadwal `aktif` sampai `penutupan`; periode P terkunci bagi PIC setelah `jadwal_periode.pengisian_selesai` P yang berlaku, termasuk hasil revisi resmi jendela (tanggal bisnis Asia/Makassar; Q41 §41.2 butir 5); Perencanaan dikecualikan sampai `penutupan`; periode lampau saat aktivasi terkunci sejak aktivasi; dalam sesi koreksi pascapenutupan PIC hanya dapat mengubah periode yang jendela pengisiannya dibuka kembali (Q41 §41.2 butir 6).
- [x] Terapkan guard periode terkunci pada simpan draf (`SimpanTargetPeriode`, `EnsureDraftRencanaAksi`), pengajuan (`AjukanRencanaAksi`), serta versi setelah dikembalikan atau dibuka kembali: tolak perubahan target atau daftar kegiatan periode terkunci sebagai validasi bisnis (422) dan audit penolakannya.
- [x] Hapus isian dan validasi jendela RA dari `StoreJadwalRequest`, `UpdateJadwalRequest`, `PeriodeJadwalMutationRequest`, `JadwalDraftRules`, dan `SaveJadwalDraft`; `IndexRencanaAksi` dan `JadwalActivationReadiness` tidak lagi membaca kolom jendela RA.
- [x] Perbarui gerbang pengajuan: untuk indikator dinilai tahunan menurut snapshot, terima target kosong pada periode sebelum periode nilai akhir dan tetap wajibkan target periode nilai akhir beserta rekonsiliasinya dengan target PK (ADR-0008, `DeviasiTargetPk`); indikator lain ditolak dengan pesan per periode bila ada target kosong, kecuali periode lampau (Q47 §47.2 butir 4, Q46 §46.1).
- [x] Pertahankan aturan Q41 §41.3: pengukuran tetap mensyaratkan RA disahkan dan target kumulatif yang turun tetap berupa peringatan.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side: `rencana_aksi:create`/`update`/`ajukan` (unit-scoped bagi PIC; global bagi Perencanaan) dan `indikator:update` untuk penanda dinilai tahunan.
- [x] React hanya menerima props `can.*` serta status kunci per periode dari server; request langsung tetap harus ditolak bila permission/scope/deny atau kunci periode tidak memenuhi.
- [ ] Catat audit perubahan penanda dinilai tahunan dan penolakan perubahan periode terkunci, termasuk `dasar_izin` untuk permission sensitif.

**D. Frontend / UX**
- [x] Hapus isian jendela RA dari form jadwal (`resources/js/Pages/Jadwal/Editor.tsx`).
- [x] Tampilkan periode terkunci bagi PIC sebagai baca-saja pada matriks target (`resources/js/Pages/RencanaAksi/MatriksTarget.tsx`) dan bedakan target kosong ("tidak ditargetkan") dari `0`.
- [x] Tambahkan saklar **Dinilai tahunan** pada form indikator (`resources/js/Pages/Perencanaan/SasaranIndikator/IndikatorModal.tsx`, divalidasi `StoreIndikatorRequest`/`UpdateIndikatorRequest`).
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit; pastikan state loading/disabled/error, responsif mobile, dan kontras sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given jadwal tahun berstatus `aktif` dan `penutupan` belum lewat, When PIC efektif dengan grant sah membuat, mengubah, atau mengajukan RA, Then aksi diizinkan tanpa memeriksa jendela penyusunan; `rencana_aksi_mulai`/`rencana_aksi_selesai` tidak lagi dibaca. *(Q41 §41.1 butir 1)*
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given form jadwal tahunan, Then isian jendela penyusunan RA tidak ditampilkan dan tidak divalidasi; kolomnya dibiarkan nullable. *(Q41 §41.1 butir 2)*
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given `pengisian_selesai` periode P sudah lewat (tanggal bisnis Asia/Makassar), When PIC menyimpan draf, mengajukan, atau menyimpan versi baru RA yang mengubah target atau daftar kegiatan periode P, Then ditolak sebagai validasi bisnis (422) dan diaudit; perubahan pada periode yang belum terkunci tetap diterima. *(Q41 §41.2 butir 1–2)*
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given aktor Perencanaan, When mengubah periode yang terkunci bagi PIC sebelum `penutupan`, Then diizinkan. *(Q41 §41.2 butir 3)*
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given periode lampau saat jadwal diaktifkan, Then periode itu terkunci bagi PIC sejak aktivasi. *(Q41 §41.2 butir 4, Q46 §46.1)*
- [x] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given Perencanaan menandai indikator sebagai dinilai tahunan, When disimpan, Then penanda tersimpan dan diaudit; snapshot jadwal yang diaktifkan sesudahnya membekukan penanda tersebut, dan perubahan setelah jadwal aktif berlaku pada jadwal berikutnya; koreksi tahun berjalan lewat koreksi snapshot ISS-03.03 (Q35). *(Q47 §47.1)*
- [x] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given indikator dinilai tahunan menurut snapshot, When RA diajukan dengan target periode sebelum periode nilai akhir kosong, Then pengajuan diterima; target periode nilai akhir tetap wajib dan direkonsiliasi dengan target PK (ADR-0008). *(Q47 §47.2 butir 1)*
- [x] TEST-8: Buat Pest Feature/Unit test yang membuktikan — Given indikator yang tidak dinilai tahunan, When salah satu target periode kosong saat pengajuan, Then pengajuan ditolak dengan pesan per periode, kecuali periode lampau 2026. *(Q47 §47.2 butir 2)*
- [x] TEST-9: Buat Pest Feature/Unit test yang membuktikan — Given target periode dikosongkan, Then disimpan sebagai tidak ditargetkan dan dibedakan dari target `0`. *(Q47 §47.2 butir 3)*
- [x] TEST-10: Buat Pest Feature/Unit test yang membuktikan — Given RA belum disahkan, When pengukuran diajukan, Then ditolak, kecuali periode lampau 2026 (Q32.7; Q32.8 diperbarui Q46); target kumulatif yang turun tetap peringatan. *(Q41 §41.3 butir 1 dan 3)*
- [x] VERSI RA: Uji versi RA yang dibekukan mencatat periode tanpa target dengan status `tidak_ditargetkan`, bukan `belum_diisi` (Q47 §47.2 butir 5).
- [x] REVISI JENDELA: Uji perpanjangan resmi `pengisian_selesai` periode P menggeser kunci RA periode P bagi PIC, dan dalam sesi koreksi pascapenutupan PIC hanya dapat mengubah RA pada periode yang jendela pengisiannya dibuka kembali (Q41 §41.2 butir 5–6).
- [x] PERIODE LAMPAU: Uji target kosong pada periode lampau (data awal 2026 TW I–III) tidak ditolak dan kunci periode lampau tidak menghalangi jalur Perencanaan (Q47 §47.2 butir 4, Q46 §46.1).
- [x] SECURITY: Uji request langsung PIC tanpa grant unit sah atau dengan deny yang cocok menghasilkan 403, aktor yang bukan PIC efektif menghasilkan 422 sebagai validasi bisnis, dan pelanggaran kunci periode menghasilkan 422 yang diaudit.

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [x] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.


---

## Bagian 6 — Kegiatan, Klaim Dampak, dan Bukti Pelaksanaan

### ISS-06.01 · [Feature] Pencatatan Rencana Kegiatan Unit

> **Q42, Q49 (13 Oktober 2026):** `nama` kegiatan hingga 500 karakter dan `tujuan` opsional diterapkan oleh PR #97 (migrasi `2026_10_14_000001`, validasi request, dan form; Q49 §49.1). PR #97 tidak diperluas untuk anggaran: input, tampilan, validasi, penjumlahan anggaran, dan RAB dikerjakan pada ISS-06.08 (Q42 §42.2–§42.3). Penambahan kegiatan dari halaman Rencana Aksi beserta klaimnya dikerjakan pada ISS-06.02 (Q42 §42.1 butir 2). Detail kegiatan tetap dibatasi unit (Q12, Q49 §49.3). AC-1 mengikuti Q49 §49.1.

**Status implementasi (2026-10-14):** Diterapkan oleh PR #97 (migrasi `2026_10_14_000001`, validasi request, dan form). Checklist yang dicentang mencerminkan automated test lokal dan CI PR #97, bukan penerimaan UAT. Penyesuaian Q49 §49.1 mencakup `nama` hingga 500 karakter dan `tujuan` opsional. Pembatasan detail per unit berizin (PRD §15.1, Q49 §49.3, Workflow §8 butir 9) dan syarat izin baca bagi penerima grant tulis diterapkan oleh [#102](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/102) sesuai Q54. Smoke browser desktop/mobile untuk build modal terbaru dan QA/retest SSO oleh Dion belum dilengkapi; item responsivitas dan checklist UI tetap belum dicentang sampai buktinya tersedia. UAT dan deployment belum dilakukan.

**Terkait User Story:** `US-06.01`  
**Prioritas:** 🟡 P1 · Tahap 1, PR #97 + Q49 (Q46 §46.3)  
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

- [x] **AC-1:** Given nama (hingga 500 karakter), sasaran peserta, lokasi, dan tanggal valid, dengan tujuan opsional, When disimpan, Then kegiatan terbentuk dengan status `rencana`. *(Diperbarui Q49 §49.1; semula tujuan termasuk isian wajib.)*
- [x] **AC-2:** Given MVP, When form kegiatan ditampilkan, Then **field anggaran tidak ditampilkan, tidak diterima sebagai input bisnis, dan tidak divalidasi**; kolom database boleh tetap nullable untuk fase lanjutan. *(Berlaku untuk PR #97; digantikan Q42 setelah US-06.08: anggaran per kegiatan wajib diinput, ditampilkan, dan dijumlahkan.)*
- [x] **AC-3:** Given pengguna unit memiliki grant `kegiatan:create/update`, When bekerja pada unit yang sama, Then aksi diizinkan tanpa syarat PIC indikator tertentu.
- [x] **AC-4:** Given grant hanya unit A, When kegiatan unit B dimutasi, Then ditolak.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `kegiatan`, `audit_log`.
- [x] Sesuaikan kolom `kegiatan.nama` (saat ini `string` 255) agar menampung hingga 500 karakter dan `tujuan` (saat ini wajib) menjadi nullable lewat migrasi (Q49 §49.1).
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Validasi request kegiatan: `nama` maksimal 500 karakter dan `tujuan` opsional (Q49 §49.1).
- [x] Jangan expose/accept/validate field anggaran pada UI/API MVP walaupun kolom database tersedia nullable untuk fase lanjut. *(Digantikan Q42: PR #97 tidak diperluas; input dan validasi anggaran dikerjakan pada ISS-06.08.)*

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `kegiatan:create`, `kegiatan:update` (unit-scoped bila melalui grant).
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Pencatatan Rencana Kegiatan Unit**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given nama (hingga 500 karakter), sasaran peserta, lokasi, dan tanggal valid, dengan tujuan opsional, When disimpan, Then kegiatan terbentuk dengan status `rencana`; nama lebih dari 500 karakter ditolak. *(Diperbarui Q49 §49.1; semula tujuan termasuk isian wajib.)*
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given MVP, When form kegiatan ditampilkan, Then **field anggaran tidak ditampilkan, tidak diterima sebagai input bisnis, dan tidak divalidasi**; kolom database boleh tetap nullable untuk fase lanjutan. *(Berlaku untuk PR #97; digantikan Q42 setelah US-06.08: anggaran per kegiatan wajib diinput, ditampilkan, dan dijumlahkan.)*
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given pengguna unit memiliki grant `kegiatan:create/update`, When bekerja pada unit yang sama, Then aksi diizinkan tanpa syarat PIC indikator tertentu.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given grant hanya unit A, When kegiatan unit B dimutasi, Then ditolak.

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-06.02 · [Feature] Klaim Keterkaitan Kegiatan terhadap Rencana Aksi/Komponen

> **Q41, Q42, Q46, Q49 (13 Oktober 2026):** kegiatan ditambahkan dari halaman Rencana Aksi per periode; aksi ini membuat kegiatan (per unit × periode) sekaligus klaimnya ke RA dengan `sumber_klaim = rencana_aksi` (Q42 §42.1 butir 2). Pengajuan RA mensyaratkan minimal satu kegiatan yang diklaim pada tahap RA, tetapi tidak setiap periode wajib berisi kegiatan (Q42 §42.1 butir 3). Kegiatan dapat disalin ke periode lain pada RA yang sama saat ditambahkan (Q49 §49.2), dan periode yang terkunci bagi PIC tidak dapat ditambah kegiatan (Q41 §41.2). Satu kegiatan tetap dapat diklaim ke lebih dari satu RA/IKU dalam unit yang sama, sedangkan klaim lintas unit tetap ditolak (Q42 §42.1 butir 4). Gerbang minimal satu kegiatan dikecualikan untuk periode lampau dan RA data awal 2026 (Q46 §46.1). Issue ini belum dikerjakan sehingga kontrak, AC, task, dan test diperbarui langsung.

**Status implementasi (2026-10-15):** Diterapkan pada branch `feature/iss-06-02-klaim-kegiatan` (PR #111). Checklist yang dicentang mencerminkan automated test lokal, bukan penerimaan UAT. Asumsi kerja berikut **disetujui PM** pada 16 Oktober 2026 ([review PM PR #111](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/111#issuecomment-6198491258)): (1) tambah/hapus klaim hanya saat RA `draft`/`dikembalikan`, penyelarasan PRD §16.5 dengan Q24/Q26; (2) hapus klaim oleh pembuat klaim atau Perencanaan sesuai PRD §16.5; (3) RA data awal 2026 dikenali dari pengajuan lewat jalur Perencanaan tanpa kolom penanda. Input anggaran belum tersedia (ISS-06.08), sehingga salinan kegiatan membawa anggaran kosong; AC-8 belum dicentang sampai salin dan ubah anggaran per kegiatan tersedia. Smoke browser desktop/mobile belum dilengkapi; item responsivitas, checklist UI, dan traceability belum dicentang. Pembaruan 18 Oktober 2026: ISS-06.08 menyediakan input anggaran, sehingga salinan kegiatan kini menyalin anggaran yang dapat diubah per kegiatan; AC-8 dicentang berdasarkan automated test.

Catatan perbaikan review (17 Oktober 2026): pengajuan membandingkan nama dan tujuan rencana kegiatan yang dibekukan terhadap versi sah untuk menjaga periode terkunci; perubahan status pelaksanaan saja tidak dianggap perubahan rencana. Tambah/hapus klaim tahap RA dan tambah/salin kegiatan dari RA menaikkan versi header sekali per transaksi berhasil, sehingga pengajuan memakai versi lama ditolak 409. Panel RA menampilkan komponen snapshot, arah dampak, dan catatan klaim bagi pembaca berizin. Perbaikan ini tidak mencentang penerimaan UI/UAT atau AC-8 yang masih tertunda.

Catatan tindak lanjut review (18 Oktober 2026): tautan Kelola klaim ditahan saat target belum disimpan, mengikuti penahan tambah kegiatan; anjuran menyimpan draf yang selalu tampil pada kartu kesiapan dihapus. Pengecualian seluruh periode efektif lampau memakai definisi bersama khusus jadwal 2026 (Q46 §46.1); RA 2027 tanpa kegiatan tetap ditolak. Regresi frontend dan kontrol backend 2026/2027 ditambahkan. AC-8, QA browser, UAT, dan deployment tetap tidak dicentang.

**Terkait User Story:** `US-06.02`  
**Prioritas:** 🟡 P1 · Tahap 1 (Q46 §46.3)  
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

- **Dependensi:** Kegiatan dan RA tersedia pada konteks tahun/unit yang konsisten; ISS-06.01 (PR #97), kunci per periode ISS-05.07, dan gerbang pengajuan RA ISS-05.03.
- **Otorisasi:** `kegiatan:update`/capability sumber dan kewenangan RA sesuai alur; penambahan kegiatan dari halaman RA memakai `kegiatan:create` dan kewenangan mutasi RA pada unit yang sama.
- **Dampak Data:** `kegiatan`, `klaim_kegiatan`, `rencana_aksi_versi` (pembekuan klaim tahap RA), `audit_log`.
- **Keputusan terkait:** Q42 §42.1, Q49 §49.2, Q41 §41.2, Q46 §46.1.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given kegiatan, RA, komponen opsional, arah dampak, dan catatan valid, When disimpan, Then klaim terbentuk.
- [x] **AC-2:** Given unit kegiatan berbeda dengan unit RA, When klaim dibuat, Then ditolak.
- [x] **AC-3:** Given klaim identik sudah ada, When dibuat ulang, Then duplikasi ditolak.
- [x] **AC-4:** Given klaim berhasil, Then nilai komponen/pengukuran **tidak** berubah otomatis.
- [x] **AC-5:** Given kegiatan batal/tidak terlaksana, Then klaim historis tetap dapat dipertahankan untuk menjelaskan kendala.
- [x] **AC-6:** Given halaman Rencana Aksi per periode, When PIC atau Perencanaan menambah kegiatan, Then kegiatan (per unit × periode) dan klaimnya ke RA dengan `sumber_klaim = rencana_aksi` dibuat dalam satu aksi. *(Q42 §42.1 butir 2)*
- [x] **AC-7:** Given RA diajukan tanpa satu pun kegiatan yang diklaim pada tahap RA, When submit, Then pengajuan ditolak; tidak setiap periode wajib berisi kegiatan. *(Q42 §42.1 butir 3)*
- [x] **AC-8:** Given kegiatan ditambahkan dari halaman RA, When PIC menyalinnya ke satu atau lebih periode lain pada RA yang sama, Then setiap salinan menjadi kegiatan tersendiri per unit × periode dengan klaim `sumber_klaim = rencana_aksi` sendiri; nama, tujuan, dan anggaran ikut disalin dan dapat diubah per kegiatan, sedangkan berkas termasuk RAB tidak ikut disalin. *(Q49 §49.2 butir 1–3)*
- [x] **AC-9:** Given periode sudah terkunci bagi PIC, When PIC menambah atau menyalin kegiatan ke periode tersebut, Then ditolak. *(Q41 §41.2, Q49 §49.2 butir 4)*
- [x] **AC-10:** Given RA data awal 2026 atau periode lampau, Then gerbang minimal satu kegiatan dikecualikan. *(Q46 §46.1 butir 1 dan 3)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `klaim_kegiatan`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Implementasikan use-case **Klaim Keterkaitan Kegiatan terhadap Rencana Aksi/Komponen** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [x] Sediakan aksi tambah kegiatan dari halaman RA per periode yang membuat `kegiatan` dan `klaim_kegiatan` (`sumber_klaim = rencana_aksi`) secara atomik; opsi salin ke periode lain membuat pasangan kegiatan + klaim per periode tujuan tanpa menyalin `berkas` (Q42 §42.1 butir 2, Q49 §49.2).
- [x] Tambahkan gerbang minimal satu kegiatan tahap RA pada `AjukanRencanaAksi`, dengan pengecualian periode lampau dan RA data awal 2026 (Q42 §42.1 butir 3, Q46 §46.1).
- [x] Pakai aturan kunci per periode ISS-05.07 (`JendelaTulisRencanaAksi`) untuk menolak penambahan atau salinan kegiatan ke periode yang terkunci bagi PIC.
- [x] Lengkapi pembekuan klaim tahap RA pada `rencana_aksi_versi` yang tertunda di ISS-05.03 AC-5 (Data Model §2.34).
- [x] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `kegiatan:update`/capability sumber dan kewenangan RA sesuai alur.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Klaim Keterkaitan Kegiatan terhadap Rencana Aksi/Komponen**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Sediakan panel kegiatan per periode pada halaman Rencana Aksi (`resources/js/Pages/RencanaAksi/Show.tsx`) dengan aksi tambah dan salin ke periode lain; periode yang terkunci tampil baca-saja bagi PIC.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan, RA, komponen opsional, arah dampak, dan catatan valid, When disimpan, Then klaim terbentuk.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given unit kegiatan berbeda dengan unit RA, When klaim dibuat, Then ditolak.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given klaim identik sudah ada, When dibuat ulang, Then duplikasi ditolak.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given klaim berhasil, Then nilai komponen/pengukuran **tidak** berubah otomatis.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan batal/tidak terlaksana, Then klaim historis tetap dapat dipertahankan untuk menjelaskan kendala.
- [x] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given halaman Rencana Aksi per periode, When PIC atau Perencanaan menambah kegiatan, Then kegiatan (per unit × periode) dan klaimnya ke RA dengan `sumber_klaim = rencana_aksi` dibuat dalam satu aksi (satu transaksi). *(Q42 §42.1 butir 2)*
- [x] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given RA diajukan tanpa satu pun kegiatan yang diklaim pada tahap RA, When submit, Then pengajuan ditolak; tidak setiap periode wajib berisi kegiatan. *(Q42 §42.1 butir 3)*
- [ ] TEST-8: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan ditambahkan dari halaman RA, When PIC menyalinnya ke satu atau lebih periode lain pada RA yang sama, Then setiap salinan menjadi kegiatan tersendiri per unit × periode dengan klaim `sumber_klaim = rencana_aksi` sendiri; nama, tujuan, dan anggaran ikut disalin dan dapat diubah per kegiatan, sedangkan berkas termasuk RAB tidak ikut disalin. *(Q49 §49.2 butir 1–3)*
- [x] TEST-9: Buat Pest Feature/Unit test yang membuktikan — Given periode sudah terkunci bagi PIC, When PIC menambah atau menyalin kegiatan ke periode tersebut, Then ditolak sebagai validasi bisnis (422) dan diaudit, sedangkan Perencanaan dikecualikan sampai `penutupan`. *(Q41 §41.2, Q49 §49.2 butir 4)*
- [x] TEST-10: Buat Pest Feature/Unit test yang membuktikan — Given RA data awal 2026 atau periode lampau, Then gerbang minimal satu kegiatan dikecualikan. *(Q46 §46.1 butir 1 dan 3)*

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-06.03 · [Feature] Pelaksanaan Kegiatan & Gerbang Bukti Gabungan

> **Q42, Q45 (13 Oktober 2026):** persyaratan tahap `kegiatan` adalah tahap **pelaksanaan** kegiatan dan tetap diperiksa saat kegiatan ditandai `terlaksana` (PRD §15.6, Q22 tetap); persyaratan tahap perencanaan kegiatan (`kegiatan_perencanaan`, mis. RAB) diperiksa saat Rencana Aksi diajukan (ISS-06.08; Q42 §42.4). Status kegiatan dapat diperbarui sepanjang periode berjalan, misalnya setiap bulan, tanpa entitas bulanan (Q45 §45.1 butir 4); dashboard menampilkannya secara real-time sebagai "belum diverifikasi" (ISS-10.01). Narasi per kegiatan tetap tersedia dan opsional, sedangkan narasi wajib berada pada pengukuran (Q45 §45.1 butir 1–2; ISS-07.03). AC-3 diperbarui dan AC-6 ditambahkan karena issue ini belum dikerjakan.

**Terkait User Story:** `US-06.03`  
**Prioritas:** 🟡 P1 · Tahap 2 (Q46 §46.3)  
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

- [ ] **AC-1:** Given kegiatan hendak menjadi `terlaksana`, When gerbang dievaluasi, Then persyaratan bukti = **union persyaratan global kegiatan + persyaratan indikator yang diklaim** pada tahap `kegiatan` (pelaksanaan). *(Q22, Q42 §42.4 butir 2)*
- [ ] **AC-2:** Given salah satu bukti wajib pada union belum terpenuhi, When status terlaksana disubmit, Then ditolak.
- [ ] **AC-3:** Given tanggal realisasi, peserta riil, dan bukti lengkap, When commit, Then status menjadi `terlaksana`; narasi per kegiatan (`uraian_pelaksanaan`, `kendala`, `strategi_tindaklanjut`) opsional. *(Diperbarui Q45 §45.1 butir 2; semula uraian pelaksanaan, kendala, dan strategi tindak lanjut ikut disyaratkan.)*
- [ ] **AC-4:** Given klaim indikator baru ditambahkan setelah kegiatan sudah terlaksana, When klaim menambah persyaratan bukti, Then sistem kembali memeriksa bukti tambahan dan menandai kebutuhan koreksi bila belum lengkap.
- [ ] **AC-5:** Given kegiatan telah terlaksana, Then bukti yang menjadi dasar hasil tidak dapat dihapus; koreksi memakai pola append-only.
- [ ] **AC-6:** Given periode berjalan, When status kegiatan diperbarui lebih dari sekali (mis. setiap bulan), Then perubahan tersimpan pada kegiatan yang sama tanpa entitas bulanan dan tercatat di audit. *(Q45 §45.1 butir 4)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `kegiatan`, `klaim_kegiatan`, `jenis_berkas`, `berkas`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Gerbang status `terlaksana` harus menghitung union persyaratan global kegiatan dan persyaratan semua indikator yang diklaim pada tahap `kegiatan` (pelaksanaan); tahap `kegiatan_perencanaan` diperiksa saat pengajuan RA (ISS-06.08, Q42 §42.4).
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

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan hendak menjadi `terlaksana`, When gerbang dievaluasi, Then persyaratan bukti = **union persyaratan global kegiatan + persyaratan indikator yang diklaim** pada tahap `kegiatan` (pelaksanaan). *(Q22, Q42 §42.4 butir 2)*
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given salah satu bukti wajib pada union belum terpenuhi, When status terlaksana disubmit, Then ditolak.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given tanggal realisasi, peserta riil, dan bukti lengkap, When commit, Then status menjadi `terlaksana`; narasi per kegiatan (`uraian_pelaksanaan`, `kendala`, `strategi_tindaklanjut`) opsional. *(Diperbarui Q45 §45.1 butir 2; semula uraian pelaksanaan, kendala, dan strategi tindak lanjut ikut disyaratkan.)*
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given klaim indikator baru ditambahkan setelah kegiatan sudah terlaksana, When klaim menambah persyaratan bukti, Then sistem kembali memeriksa bukti tambahan dan menandai kebutuhan koreksi bila belum lengkap.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan telah terlaksana, Then bukti yang menjadi dasar hasil tidak dapat dihapus; koreksi memakai pola append-only.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given periode berjalan, When status kegiatan diperbarui lebih dari sekali (mis. setiap bulan), Then perubahan tersimpan pada kegiatan yang sama tanpa entitas bulanan dan tercatat di audit. *(Q45 §45.1 butir 4)*

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-06.04 · [Feature] Kegiatan Tidak Terlaksana, Ditunda, atau Dibatalkan

> **Q41, Q42, Q43 (13 Oktober 2026):** kegiatan yang tidak dilaksanakan sesuai rencana ditandai **ditunda** saat pelaporan dengan justifikasi dan periode tujuan; sistem membuat kegiatan lanjutan pada periode tujuan yang tertaut ke kegiatan asal (`kegiatan_asal_id`) dan diklaim ke RA pada tahap pengukuran (`sumber_klaim = pengukuran`; ISS-06.05). RA yang sudah disahkan tidak dibuka kembali hanya untuk memindahkan kegiatan; justifikasi menjadi catatan resmi yang dilihat Perencanaan saat verifikasi pengukuran (Q41 §41.4 butir 1–3; ISS-08.01). Kegiatan asal `ditunda` yang sudah memiliki kegiatan lanjutan tidak dihitung dalam total anggaran (Q42 §42.2 butir 6; ISS-06.08). Justifikasi tetap wajib (Q43 §43.2 butir 5). AC-6 ditambahkan karena issue ini belum dikerjakan.

**Terkait User Story:** `US-06.04`  
**Prioritas:** 🟡 P1 · Tahap 2 (Q46 §46.3)  
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
- **Rule (Q41 §41.4 butir 5):** periode tujuan kegiatan `ditunda` harus berada pada tahun (jadwal) yang sama; kegiatan lanjutan tidak menyalin berkas asal termasuk RAB (ISS-06.05).

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given status diubah menjadi `tidak_terlaksana`, `ditunda`, atau `batal`, When submit, Then justifikasi wajib.
- [ ] **AC-2:** Given justifikasi kosong, When submit, Then ditolak.
- [ ] **AC-3:** Given status bukan `terlaksana`, Then bukti SPJ pelaksanaan tidak diwajibkan semata-mata untuk transisi tersebut.
- [ ] **AC-4:** Given kegiatan telah diklaim, When laporan dibentuk, Then narasi kegagalan/penundaan tetap dapat muncul sebagai konteks.
- [ ] **AC-5:** Given perubahan status dilakukan, Then audit mencatat nilai lama/baru dan alasan.
- [ ] **AC-6:** Given status diubah menjadi `ditunda`, When submit, Then periode tujuan wajib diisi, dan justifikasi menjadi catatan resmi yang dilihat Perencanaan saat verifikasi pengukuran. *(Q41 §41.4 butir 1 dan 3)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `kegiatan`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan use-case **Kegiatan Tidak Terlaksana, Ditunda, atau Dibatalkan** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [ ] Saat status `ditunda` disimpan beserta periode tujuan, picu pembuatan kegiatan lanjutan ISS-06.05 tanpa membuka kembali RA yang sudah disahkan, dan sediakan justifikasi sebagai catatan resmi untuk verifikasi pengukuran ISS-08.01 (Q41 §41.4 butir 2–3).
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
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given status diubah menjadi `ditunda`, When submit, Then periode tujuan wajib diisi, dan justifikasi menjadi catatan resmi yang dilihat Perencanaan saat verifikasi pengukuran. *(Q41 §41.4 butir 1 dan 3)*

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-06.05 · [Feature] Roll-Over Kegiatan ke Periode Berikutnya

> **Q41, Q42 (13 Oktober 2026):** kegiatan lanjutan dibuat sistem pada periode tujuan saat kegiatan ditandai ditunda (ISS-06.04), tertaut ke kegiatan asal melalui `kegiatan_asal_id`, dan diklaim ke Rencana Aksi pada tahap pengukuran (`sumber_klaim = pengukuran`); RA yang sudah disahkan tidak dibuka kembali hanya untuk memindahkan kegiatan (Q41 §41.4 butir 2–3). Kegiatan lanjutan menyalin anggaran kegiatan asal yang dapat diubah; kegiatan asal yang sudah memiliki lanjutan tidak dihitung dalam total, dan total yang dibekukan pada versi RA tidak berubah (Q42 §42.2 butir 6). AC-5 ditambahkan karena issue ini belum dikerjakan.

**Terkait User Story:** `US-06.05`  
**Prioritas:** 🟡 P1 · Tahap 2 (Q46 §46.3)  
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
- **Dampak Data:** `kegiatan`, `klaim_kegiatan` (`sumber_klaim = pengukuran`), `audit_log`.
- **Rule (Q42 §42.2 butir 6):** kegiatan lanjutan menyalin anggaran kegiatan asal dan anggarannya dapat diubah; kegiatan asal `ditunda` yang sudah memiliki kegiatan lanjutan tidak dihitung dalam total, dan total yang sudah dibekukan pada versi RA tidak berubah (penjumlahan pada ISS-06.08).
- **Rule (Q41 §41.4 butir 5):** kegiatan lanjutan hanya dapat dibuat ke periode lain pada tahun (jadwal) yang sama; berkas kegiatan asal, termasuk RAB, tidak disalin; persyaratan tahap perencanaan kegiatan tidak diperiksa ulang untuk kegiatan lanjutan yang diklaim pada tahap pengukuran.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given kegiatan ditunda, When roll-over dibuat ke periode tujuan, Then baris kegiatan baru dibuat dengan `kegiatan_asal_id` menunjuk kegiatan lama.
- [ ] **AC-2:** Given baris baru dibuat, Then kegiatan asal tetap `ditunda` dan tidak dihapus.
- [ ] **AC-3:** Given periode tujuan tidak valid/lebih awal, When roll-over dicoba, Then ditolak.
- [ ] **AC-4:** Given roll-over selesai, Then audit menyimpan hubungan asal-tujuan.
- [ ] **AC-5:** Given kegiatan lanjutan dibuat, Then kegiatan tersebut diklaim ke Rencana Aksi pada tahap pengukuran (`sumber_klaim = pengukuran`), dan RA yang sudah disahkan tidak dibuka kembali untuk memindahkan kegiatan. *(Q41 §41.4 butir 2–3)*

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
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan lanjutan dibuat, Then kegiatan tersebut diklaim ke Rencana Aksi pada tahap pengukuran (`sumber_klaim = pengukuran`), dan RA yang sudah disahkan tidak dibuka kembali untuk memindahkan kegiatan. *(Q41 §41.4 butir 2–3)*
- [ ] TAHUN SAMA DAN BERKAS: Uji kegiatan lanjutan ke periode pada tahun lain ditolak, berkas asal termasuk RAB tidak ikut disalin, dan persyaratan tahap perencanaan kegiatan tidak diperiksa ulang untuk lanjutan yang diklaim tahap pengukuran (Q41 §41.4 butir 5).
- [ ] ANGGARAN: Uji kegiatan lanjutan menyalin anggaran kegiatan asal yang dapat diubah, kegiatan asal `ditunda` yang sudah memiliki lanjutan tidak dihitung dalam total, dan total pada versi RA yang dibekukan tidak berubah (Q42 §42.2 butir 6).

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

### ISS-06.08 · [Feature] Anggaran Kegiatan, RAB, Persyaratan Tahap Perencanaan Kegiatan, dan Penjumlahan Anggaran

**Terkait User Story:** `US-06.08`  
**Prioritas:** Tahap 1 (Q46 §46.3: sebelum integrasi 9 November 2026)  
**Story Points:** Belum diestimasi  
**Labels:** `feature`, `kegiatan`, `evidence`, `audit-sensitive`, `frontend`  
**Plan:** Modul 12: 12.12  
**PRD:** §15.1, §15.6, §16, §18.2–18.4  
**Workflow:** §7–10  
**Data Model/Entitas:** `kegiatan.anggaran`, `jenis_berkas` (`tahap`, `hanya_kegiatan_beranggaran`), `berkas`, `klaim_kegiatan`

**Status implementasi (2026-10-20):** dikerjakan dalam dua PR. PR pertama ([#142](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/142)) menerapkan anggaran wajib, tahap `kegiatan_perencanaan` dengan penanda dan contoh berkas, panel berkas perencanaan pada halaman ubah kegiatan, gerbang pengajuan RA beserta pembekuannya pada key snapshot `persyaratan_kegiatan`, dan imutabilitas berkas. Checklist yang dicentang mencerminkan automated test lokal, bukan penerimaan UAT. Asumsi kerja yang menunggu persetujuan PM: (1) kegiatan lama dengan anggaran kosong menolak pengajuan RA dengan pesan "anggaran belum diisi", kecuali data awal 2026 dan periode lampau; (2) anggaran berupa rupiah bulat 0 sampai 15 digit; (3) contoh berkas hanya untuk tahap `kegiatan_perencanaan`, diunduh dengan `jenis_berkas:read`, dan ikut dihapus lunak bersama persyaratannya; (4) RAB diunggah pada halaman ubah kegiatan per mode persyaratan; berkas pengganti wajib beralasan dan, seperti penghapusan, ditolak sejak RA pengklaim diajukan (pola Q36), sedangkan unggahan baru tetap diterima; (5) format RAB Excel/PDF diatur Perencanaan lewat `format_diizinkan` persyaratan RAB, tanpa aturan format khusus RAB di kode; (6) kunci hapus dan ganti berlaku untuk seluruh berkas tahap `kegiatan_perencanaan` milik kegiatan sejak salah satu RA pengklaimnya diajukan, sejalan dengan snapshot versi; koreksi lewat pengembalian RA. PR kedua ([#148](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/148)) menerapkan penjumlahan anggaran (`TotalAnggaranKegiatan`) dari seluruh klaim tahap RA dan pengukuran: total per periode dan per RA tampil di halaman RA beserta anggaran per kegiatan, penanda "kegiatan bersama", label klaim pengukuran, dan penanda kegiatan belum diisi; total per IKU dan per unit/tahun (kegiatan unik) tersedia di service untuk dashboard ISS-10.01; kegiatan asal `ditunda` yang memiliki kegiatan lanjutan tidak dihitung; total dibekukan pada key snapshot `anggaran_kegiatan` dan ditampilkan di halaman review versi. Anggaran per kegiatan dan total hanya dikirim pada payload yang tertutup tanpa izin baca kegiatan unit (AC-8). Keputusan pemilik PR yang disetujui PM 20 Oktober 2026 dan dicatat pada Q42 §42.2 butir 7–9: (7) total menghitung klaim tahap RA dan tahap pengukuran, sehingga kegiatan klaim pengukuran ikut tampil di halaman RA dengan label tersendiri; (8) total per IKU dan per unit/tahun belum ditampilkan sampai dashboard ISS-10.01; (9) total beku `anggaran_kegiatan` hanya menghitung klaim tahap RA, sama dengan daftar klaim yang dikunci dan dibekukan saat pengajuan, karena klaim pengukuran tidak dikunci dan tidak menaikkan versi RA; total di halaman RA tetap menghitung semua klaim. AC-6 tidak diubah issue ini dan mengikuti ISS-06.03.

#### User Story

> **Sebagai** PIC dan Tim Perencanaan,  
> **Saya ingin** mencatat anggaran setiap kegiatan, mengunggah RAB untuk kegiatan beranggaran, dan menetapkan persyaratan dokumen tahap perencanaan kegiatan beserta contoh berkas,  
> **Sehingga** anggaran per periode, per Rencana Aksi, dan per IKU dapat dijumlahkan tanpa hitung ganda.

#### Kontrak Teknis

- **Dependensi:** ISS-06.01 (kegiatan; PR #97), ISS-06.02 (kegiatan dari halaman RA), ISS-11.01 (persyaratan berkas), ISS-05.03 (pengajuan RA).
- **Otorisasi:** `kegiatan:create`/`update` (scope unit); `jenis_berkas:update` (Perencanaan) untuk tahap dan contoh berkas; baca anggaran mengikuti akses detail kegiatan.
- **Dampak Data:** `kegiatan.anggaran`, `jenis_berkas.tahap` (+ `kegiatan_perencanaan`), `jenis_berkas.hanya_kegiatan_beranggaran` (boolean, default `false`), `berkas` (induk `jenis_berkas` untuk contoh berkas; induk `kegiatan` untuk RAB), `klaim_kegiatan`, `audit_log`.
- **Keputusan terkait:** Q42 §42.2–§42.5, Q49 §49.1 butir 3, Q46 §46.1.
- **Rule:** realisasi belanja, pagu induk, dan rekonsiliasi anggaran tidak dikelola (Q42 §42.2 butir 3). Aplikasi tidak membuat template RAB (Q42 §42.3 butir 2). Batas ukuran/format mengikuti persyaratan dan batas teknis Q44 (ISS-13.06).
- **Rule:** kegiatan lanjutan menyalin anggaran kegiatan asal yang dapat diubah; kegiatan asal `ditunda` yang sudah memiliki kegiatan lanjutan tidak dihitung dalam total, dan total yang dibekukan pada versi RA tidak berubah (Q42 §42.2 butir 6; ISS-06.05).
- **Rule:** contoh berkas bukan bukti: Perencanaan dapat menambah, mengganti, atau menghapusnya kapan saja, penghapusannya wajib alasan (Q43 §43.2 butir 2), dan contoh berkas tidak dibekukan ke versi pengajuan (Q42 §42.3 butir 4).
- **Rule:** persyaratan tahap `kegiatan_perencanaan` dapat ditandai `jenis_berkas.hanya_kegiatan_beranggaran` (boolean, default `false`); persyaratan RAB memakai penanda ini sehingga hanya diperiksa untuk kegiatan beranggaran lebih dari 0, sedangkan persyaratan lain pada tahap yang sama berlaku untuk setiap kegiatan (Q42 §42.3 butir 3).
- **Rule:** persyaratan tahap `kegiatan_perencanaan` yang diperiksa saat RA indikator X diajukan adalah persyaratan global ditambah persyaratan khusus indikator X; untuk kegiatan bersama, persyaratan khusus indikator lain diperiksa saat RA indikator tersebut diajukan (Q42 §42.4 butir 4).
- **Rule:** berkas yang memenuhi persyaratan tahap perencanaan kegiatan (mis. RAB) tidak dapat dihapus sejak RA yang mengklaim kegiatan itu diajukan, mengikuti pola Q36 (Q42 §42.4 butir 5).
- **Rule:** kegiatan data awal 2026 dan kegiatan pada periode lampau boleh tanpa anggaran (tercatat belum diisi) karena Rencana Aksi resmi hanya memuat anggaran per Rincian Output (Q46 §46.1 butir 3).
- **Rule:** bila PR #97 di-merge sebelum Q49 diterapkan, `nama` hingga 500 karakter dan `tujuan` opsional dikerjakan di issue ini (Q49 §49.1 butir 3).
- **Di luar cakupan:** dashboard anggaran per IKU (ISS-10.01), salin kegiatan (ISS-06.02).

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given kegiatan dibuat atau diubah, When `anggaran` kosong, Then ditolak; When diisi, Then harus rupiah nonnegatif; `0` sah dan kegiatan beranggaran nol tetap dapat diklaim. *(Q42 §42.2 butir 1–2)*
- [x] **AC-2:** Given kegiatan lama dengan `anggaran` kosong, Then tampil "belum diisi" dan tidak dihitung sebagai 0; total menandai bila ada kegiatan yang anggarannya belum diisi. *(Q42 §42.2 butir 2)*
- [x] **AC-3:** Given Perencanaan menyimpan persyaratan berkas, Then tersedia tahap `kegiatan_perencanaan`; persyaratan dapat dilampiri contoh berkas yang dapat diunduh PIC. *(Q42 §42.3 butir 2, §42.4 butir 3)*
- [x] **AC-4:** Given RA diajukan, When ada kegiatan berklaim tahap RA yang belum memenuhi persyaratan wajib tahap `kegiatan_perencanaan`, termasuk RAB (Excel atau PDF) untuk kegiatan beranggaran lebih dari 0, Then pengajuan ditolak dengan daftar kegiatan dan persyaratan yang kurang. *(Q42 §42.3 butir 1, §42.4 butir 1)*
- [x] **AC-5:** Given kegiatan beranggaran 0, Then RAB tidak diwajibkan. *(Q42 §42.3 butir 1)*
- [ ] **AC-6:** Given kegiatan ditandai `terlaksana`, Then persyaratan tahap `kegiatan` (pelaksanaan) tetap diperiksa sesuai PRD §15.6 dan Q22. *(Q42 §42.4 butir 2)*
- [x] **AC-7:** Given halaman RA, Then total anggaran tampil per periode dan per RA, dan total per IKU tersedia untuk dashboard; kegiatan yang diklaim lebih dari satu IKU tampil penuh di setiap IKU dengan penanda "kegiatan bersama", sedangkan total per unit dan per tahun dihitung dari kegiatan unik. *(Q42 §42.2 butir 4)*
- [x] **AC-8:** Given pengguna tanpa akses detail kegiatan unit tersebut, Then anggaran per kegiatan dan RAB tidak tampil; Perencanaan dan Pimpinan melihat seluruhnya. *(Q42 §42.5)*
- [x] **AC-9:** Given RA data awal 2026 atau periode lampau, Then gerbang RAB dan persyaratan tahap perencanaan kegiatan dikecualikan. *(Q46 §46.1 butir 1 dan 3)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Tegakkan `kegiatan.anggaran` (kolom desimal nullable yang sudah tersedia) sebagai rupiah nonnegatif dengan CHECK `>= 0`; nilai kosong hanya untuk data sebelum Q42.
- [x] Tambahkan nilai `kegiatan_perencanaan` pada `jenis_berkas.tahap` (saat ini enum `rencana_aksi`, `pengukuran`, `kegiatan`) dan kolom `jenis_berkas.hanya_kegiatan_beranggaran` (boolean, default `false`) lewat migrasi (Q42 §42.3 butir 3, §42.4 butir 3).
- [x] Daftarkan induk `jenis_berkas` pada `berkas` (relasi `berkasable`) untuk contoh berkas; RAB disimpan sebagai `berkas` berinduk `kegiatan` yang memenuhi persyaratan tahap `kegiatan_perencanaan`.
- [x] Bila PR #97 sudah di-merge sebelum Q49 diterapkan, sesuaikan `kegiatan.nama` hingga 500 karakter dan `tujuan` nullable lewat migrasi (Q49 §49.1 butir 3). *(Tidak berlaku: diterapkan PR #97.)*
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Validasi request kegiatan: `anggaran` wajib saat create/update, rupiah nonnegatif, dan `0` sah; data lama, kegiatan data awal 2026, dan kegiatan periode lampau ber-`anggaran` kosong diperlakukan "belum diisi", bukan 0 (Q46 §46.1 butir 3).
- [x] Perluas gerbang pengajuan RA (`AjukanRencanaAksi` dengan evaluator bukti bersama `EvaluateEvidence`) untuk memeriksa persyaratan wajib tahap `kegiatan_perencanaan` (global + khusus indikator RA yang diajukan, Q42 §42.4 butir 4) setiap kegiatan yang diklaim pada tahap RA, termasuk RAB (Excel atau PDF) bagi kegiatan beranggaran lebih dari 0 melalui penanda `hanya_kegiatan_beranggaran`; pesan menyebut kegiatan dan persyaratan yang kurang.
- [x] Kecualikan gerbang tersebut untuk RA data awal 2026 dan periode lampau (Q46 §46.1 butir 1 dan 3).
- [ ] Pertahankan gerbang tahap `kegiatan` saat transisi `terlaksana` (ISS-06.03; PRD §15.6, Q22).
- [x] Hitung total anggaran per periode, per RA, dan per IKU; tandai "kegiatan bersama" bagi kegiatan yang diklaim lebih dari satu IKU; hitung total per unit dan per tahun dari kegiatan unik; keluarkan kegiatan asal `ditunda` yang sudah memiliki lanjutan; tandai total yang memuat anggaran "belum diisi"; total pada versi RA yang dibekukan tidak dihitung ulang.
- [x] Perlakukan contoh berkas sebagai lampiran persyaratan, bukan bukti: tidak dinilai gerbang kelengkapan dan tidak dibekukan pada versi pengajuan.
- [x] Tolak penghapusan berkas yang memenuhi persyaratan tahap perencanaan kegiatan (mis. RAB) sejak RA yang mengklaim kegiatan tersebut diajukan, mengikuti pola Q36; koreksi lewat pengembalian RA (Q42 §42.4 butir 5).

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side: `kegiatan:create`/`update` unit-scoped; `jenis_berkas:update` untuk tahap, penanda, dan contoh berkas.
- [x] Kirim anggaran per kegiatan dan RAB hanya kepada pengguna dengan akses detail kegiatan unit tersebut (Q12); Perencanaan dan Pimpinan melihat seluruhnya.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat audit perubahan anggaran, unggah RAB, serta perubahan persyaratan dan contoh berkas, termasuk alasan penghapusan contoh berkas.

**D. Frontend / UX**
- [x] Tambahkan isian anggaran (rupiah) pada form kegiatan dan label "belum diisi" untuk data lama.
- [x] Tampilkan total anggaran per periode dan per RA pada halaman Rencana Aksi (`resources/js/Pages/RencanaAksi/Show.tsx`) dengan penanda "kegiatan bersama".
- [x] Tambahkan opsi tahap perencanaan kegiatan, penanda "hanya untuk kegiatan beranggaran", dan unggah contoh berkas pada form persyaratan (`resources/js/Pages/JenisBerkas/Partials/JenisBerkasModal.tsx`); PIC dapat mengunduh contoh berkas.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit; pastikan state loading/disabled/error, responsif mobile, dan kontras sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan dibuat atau diubah, When `anggaran` kosong, Then ditolak; When diisi, Then harus rupiah nonnegatif; `0` sah dan kegiatan beranggaran nol tetap dapat diklaim. *(Q42 §42.2 butir 1–2)*
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan lama dengan `anggaran` kosong, Then tampil "belum diisi" dan tidak dihitung sebagai 0; total menandai bila ada kegiatan yang anggarannya belum diisi. *(Q42 §42.2 butir 2)*
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given Perencanaan menyimpan persyaratan berkas, Then tersedia tahap `kegiatan_perencanaan`; persyaratan dapat dilampiri contoh berkas yang dapat diunduh PIC. *(Q42 §42.3 butir 2, §42.4 butir 3)*
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given RA diajukan, When ada kegiatan berklaim tahap RA yang belum memenuhi persyaratan wajib tahap `kegiatan_perencanaan`, termasuk RAB (Excel atau PDF) untuk kegiatan beranggaran lebih dari 0, Then pengajuan ditolak dengan daftar kegiatan dan persyaratan yang kurang. *(Q42 §42.3 butir 1, §42.4 butir 1)*
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan beranggaran 0, Then RAB tidak diwajibkan. *(Q42 §42.3 butir 1)*
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan ditandai `terlaksana`, Then persyaratan tahap `kegiatan` (pelaksanaan) tetap diperiksa sesuai PRD §15.6 dan Q22. *(Q42 §42.4 butir 2)*
- [x] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given halaman RA, Then total anggaran tampil per periode dan per RA, dan total per IKU tersedia untuk dashboard; kegiatan yang diklaim lebih dari satu IKU tampil penuh di setiap IKU dengan penanda "kegiatan bersama", sedangkan total per unit dan per tahun dihitung dari kegiatan unik. *(Q42 §42.2 butir 4)*
- [x] TEST-8: Buat Pest Feature/Unit test yang membuktikan — Given pengguna tanpa akses detail kegiatan unit tersebut, Then anggaran per kegiatan dan RAB tidak tampil; Perencanaan dan Pimpinan melihat seluruhnya. *(Q42 §42.5)*
- [x] TEST-9: Buat Pest Feature/Unit test yang membuktikan — Given RA data awal 2026 atau periode lampau, Then gerbang RAB dan persyaratan tahap perencanaan kegiatan dikecualikan. *(Q46 §46.1 butir 1 dan 3)*
- [x] PENANDA BERANGGARAN: Uji persyaratan bertanda `hanya_kegiatan_beranggaran` hanya diperiksa untuk kegiatan beranggaran lebih dari 0, sedangkan persyaratan tanpa penanda berlaku untuk setiap kegiatan (Q42 §42.3 butir 3).
- [x] PERSYARATAN PER INDIKATOR: Uji pengajuan RA indikator X memeriksa persyaratan global + khusus indikator X, sedangkan persyaratan khusus indikator lain bagi kegiatan bersama baru diperiksa saat RA indikator tersebut diajukan (Q42 §42.4 butir 4).
- [x] CONTOH BERKAS: Uji contoh berkas dapat ditambah, diganti, atau dihapus Perencanaan kapan saja, penghapusan wajib alasan, dan contoh berkas tidak dibekukan ke versi pengajuan (Q42 §42.3 butir 4, Q43 §43.2 butir 2).
- [x] PENJUMLAHAN LANJUTAN: Uji kegiatan asal `ditunda` yang sudah memiliki kegiatan lanjutan tidak dihitung dalam total sehingga anggarannya hanya terhitung pada kegiatan lanjutan, dan total pada versi RA yang dibekukan tidak berubah (Q42 §42.2 butir 6).
- [x] DATA AWAL: Uji kegiatan data awal 2026 dan kegiatan periode lampau tanpa anggaran diterima dan tampil "belum diisi" (Q46 §46.1 butir 3).
- [x] IMUTABILITAS RAB: Uji penghapusan RAB atau berkas tahap perencanaan kegiatan ditolak sejak RA yang mengklaim kegiatan tersebut diajukan, dan masih diterima sebelum pengajuan (Q42 §42.4 butir 5, Q36).
- [x] SECURITY: Uji request langsung untuk kegiatan unit lain, deny yang cocok, dan pembacaan anggaran/RAB tanpa akses detail kegiatan menghasilkan 403 atau data tersembunyi meski tombol UI disembunyikan.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.


---

## Bagian 7 — Pengisian Pengukuran Kinerja

### ISS-07.01 · [Feature] Pengisian Nilai Realisasi Komponen/Manual

> **Q45, Q46, Q47, Q51 (13 Oktober 2026):** form pengukuran memuat narasi pengukuran per indikator per periode (`progres_kegiatan`, `kendala_masalah`, `strategi_tindak_lanjut`), isian `tindak_lanjut_rekomendasi` bila ada Rekomendasi Pimpinan disetujui pada periode sebelumnya, dan `catatan` sebagai keterangan opsional (Q45 §45.1, §45.2 butir 3; Q51 §51.1 butir 2). Realisasi yang belum tersedia dicatat sebagai `status_perhitungan = belum_dapat_diukur` dengan `nilai` kosong (Q47 §47.4). Gerbang pengajuan narasi dan tampilan periode tanpa target berada pada ISS-07.03; gerbang baru Q42–Q45 dikecualikan untuk periode lampau (Q46 §46.1). AC-7 dan AC-8 ditambahkan karena issue ini belum dikerjakan.

**Terkait User Story:** `US-07.01`  
**Prioritas:** 🔴 P0 · Tahap 2 (Q46 §46.3)  
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
- [ ] **AC-7:** Given nilai realisasi belum tersedia pada periode tersebut, When pengukuran dinyatakan belum dapat diukur, Then tersimpan `status_perhitungan = belum_dapat_diukur` dengan `nilai` kosong secara eksplisit, bukan disimpulkan dari nilai kosong, dan dibedakan dari realisasi `0` maupun `tidak_dapat_dihitung`. *(Q47 §47.4 butir 1 dan 3)*
- [ ] **AC-8:** Given form pengukuran dibuka, Then tersedia isian narasi `progres_kegiatan`, `kendala_masalah`, dan `strategi_tindak_lanjut`, isian `tindak_lanjut_rekomendasi` bila terdapat Rekomendasi Pimpinan disetujui pada periode sebelumnya, serta `catatan` opsional. *(Q45 §45.1, §45.2 butir 3; Q51 §51.1 butir 2)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `pengukuran`, `pengukuran_komponen`, `audit_log`.
- [ ] Perluas enum `status_perhitungan` pada `pengukuran_kinerjas` (saat ini `belum_diisi`, `terhitung`, `tidak_dapat_dihitung`) dengan `belum_dapat_diukur`, dan perluas CHECK `nilai_status_cocok` agar `nilai` kosong untuk status tersebut (Q47 §47.4).
- [ ] Tambahkan isian narasi pengukuran `progres_kegiatan`, `kendala_masalah`, `strategi_tindak_lanjut`, dan `tindak_lanjut_rekomendasi` pada pengukuran sesuai Data Model §2.20; `catatan` tetap opsional (Q45 §45.1).
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Hitung nilai nonmanual server-side dari komponen **snapshot**, bukan master live; manual memakai nilai langsung.
- [ ] Terapkan 0 ≠ NULL, larangan create pada indikator arsip, masa berlaku periode, dan basis kumulatif yang sebanding.
- [ ] Simpan pilihan "Belum dapat diukur" sebagai status eksplisit `belum_dapat_diukur`; jangan menyimpulkannya dari `nilai` kosong (Q47 §47.4 butir 1).

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengukuran:create`, `pengukuran:update`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pengisian Nilai Realisasi Komponen/Manual**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Sediakan pilihan "Belum dapat diukur" pada form realisasi yang mengosongkan isian nilai/komponen dan menampilkan label tersebut, bukan angka 0.
- [ ] Tampilkan isian narasi pengukuran, isian `tindak_lanjut_rekomendasi` hanya bila ada Rekomendasi Pimpinan disetujui pada periode sebelumnya (draf tidak tampil bagi PIC), dan `catatan` opsional.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given indikator nonmanual, When semua komponen diisi, Then server menghitung `nilai` dari snapshot dan UI menampilkan nilai turunan read-only.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given indikator manual, When nilai diisi, Then nilai langsung tersimpan sebagai sumber `manual`.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given nilai 0, When disimpan, Then 0 diperlakukan sebagai nilai sah dan berbeda dari NULL.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given periode sebelum `periode_mulai_id`, When pengukuran dibuat, Then ditolak sebagai Tidak berlaku.
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given indikator master sudah arsip, When pengukuran baru dibuat, Then create ditolak.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given rasio antarperiode, Then sistem tidak menjumlahkan persentase; realisasi memakai basis waktu/populasi yang sebanding dengan target kumulatif.
- [ ] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given nilai realisasi belum tersedia pada periode tersebut, When pengukuran dinyatakan belum dapat diukur, Then tersimpan `status_perhitungan = belum_dapat_diukur` dengan `nilai` kosong secara eksplisit, bukan disimpulkan dari nilai kosong, dan dibedakan dari realisasi `0` maupun `tidak_dapat_dihitung`. *(Q47 §47.4 butir 1 dan 3)*
- [ ] TEST-8: Buat Pest Feature/Unit test yang membuktikan — Given form pengukuran dibuka, Then tersedia isian narasi `progres_kegiatan`, `kendala_masalah`, dan `strategi_tindak_lanjut`, isian `tindak_lanjut_rekomendasi` bila terdapat Rekomendasi Pimpinan disetujui pada periode sebelumnya, serta `catatan` opsional. *(Q45 §45.1, §45.2 butir 3; Q51 §51.1 butir 2)*
- [ ] SECURITY: Uji request langsung untuk scope unit lain dan deny yang cocok menghasilkan 403 meski tombol UI disembunyikan.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-07.02 · [Feature] Pemenuhan Bukti Dukung Pengukuran

> **Q44 (13 Oktober 2026):** saklar unggahan dihapus sehingga pengecualian mode file karena unggahan dimatikan tidak lagi terjadi (ISS-11.06 dibatalkan); batas ukuran/format mengikuti persyaratan dengan default dan batas atas dari konfigurasi server (ISS-13.06). AC-3 dan TEST-3 dihapus (tidak dikerjakan) dan dipertahankan sebagai riwayat; penanda `tidak_dapat_dipenuhi` yang sudah tercatat tetap menjadi histori.

> **Status implementasi - issue #132 (2026-10-20):** centang menunjukkan bukti implementasi dan test pada PR #151, bukan penerimaan UAT/deployment. Test: `CanonicalPengukuranTest::test_required_modes_remain_mandatory_and_requirements_are_frozen`, `test_download_uses_the_file_frozen_at_submission`; `JenisBerkasTest::test_semua_mode_wajib_semantics`; `BuktiDukungPengukuranTest::test_mode_tautan_dan_teks_pengukuran_tersimpan_tanpa_file` (AC-1); `BuktiRencanaAksiEvaluasiTest::test_semua_mode_wajib_belum_lengkap_bila_sebagian_mode_kosong`; `EvidenceCorrectionTest::test_superseded_mode_does_not_satisfy_required_evidence`. AC-3/TEST-3 dihapus Q44 dan tetap historis.

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

- [x] **AC-1:** Given mode diizinkan, When bukti disimpan, Then `berkas` terkait Pengukuran dan jenis persyaratan.
- [x] **AC-2:** Given `semua_mode_wajib = true`, Then seluruh mode yang disyaratkan wajib terpenuhi.
- [ ] **AC-3:** *(Dihapus Q44 §44.1 butir 2; tidak dikerjakan.)* Semula: Given file upload dimatikan dan persyaratan mengandung kewajiban file, Then hanya kewajiban **mode file** yang dapat dikecualikan sesuai kebijakan; mode tautan/teks lain tetap wajib bila dipersyaratkan.
- [x] **AC-4:** Given bukti telah masuk versi submit, Then perubahan live tidak mengubah versi lama.

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

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given mode diizinkan, When bukti disimpan, Then `berkas` terkait Pengukuran dan jenis persyaratan.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given `semua_mode_wajib = true`, Then seluruh mode yang disyaratkan wajib terpenuhi.
- [ ] TEST-3: *(Dihapus Q44 §44.1 butir 2; tidak dikerjakan.)* Semula: Buat Pest Feature/Unit test yang membuktikan — Given file upload dimatikan dan persyaratan mengandung kewajiban file, Then hanya kewajiban **mode file** yang dapat dikecualikan sesuai kebijakan; mode tautan/teks lain tetap wajib bila dipersyaratkan.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given bukti telah masuk versi submit, Then perubahan live tidak mengubah versi lama.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-07.03 · [Feature] Pengajuan Pengukuran & Pembekuan Versi

> **Status implementasi dan verifikasi otomatis — 15 Oktober 2026:** AC-4/TEST-4 dan AC-11/TEST-11 dibuktikan pada commit `60b7dc7`: `tests/Feature/PengukuranNarasiTest.php` menguji penolakan narasi kosong, rollback/audit, serta catatan opsional meski flag lama aktif atau nilai memburuk; `tests/Frontend/IndikatorAtomicForm.test.tsx` dan `tests/Feature/IndikatorKomponen/PhaseDAtomicDefinitionTest.php` membuktikan saklar tersembunyi dan nilai kolom dipertahankan. [CI exact-HEAD](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/actions/runs/38664114343) lulus. Centang menandai implementasi/test otomatis, bukan persetujuan QA/UAT, merge, atau seluruh DoD.
>
> **Integrasi dan gerbang rekomendasi — 16 Oktober 2026:** #106 telah diintegrasikan melalui `19ab8fe`; kolom, simpan, presenter, dan form draf narasi memakai kontrak #106, termasuk `PengukuranKinerja::NARASI`, serta migration duplikat telah dihapus sesuai [keputusan PM](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/106#issuecomment-6187340893). Gerbang narasi pengajuan, pembekuan, dan tampilan verifikator tetap milik #105. AC-8/TEST-8 dan uji lintas tahun dibuktikan pada `04c8a16` melalui `tests/Feature/Rekomendasi/RekomendasiBerlakuTest.php`; `850ae98` menambahkan empat skenario konkurensi persetujuan/pengajuan sesuai Q55 pada `tests/Integration/Concurrency/MutationConcurrencyTest.php`. [CI commit `850ae98`](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/actions/runs/38921923125) lulus. Centang menandai implementasi/test otomatis, bukan penerimaan QA/UAT atau penyelesaian seluruh DoD.
>
> **Pengecualian periode lampau dan gerbang final — 17 Oktober 2026:** AC-10/TEST-10 dan task pengecualian periode lampau dibuktikan pada `346bb66` dan `0c9a1ea`. `tests/Feature/PengukuranPeriodeLampauTest.php` menguji pengecualian gerbang RA disahkan, kelengkapan nilai/komponen, bukti wajib, dan narasi pada periode lampau Jadwal 2026 yang diisi Perencanaan, sedangkan konteks/izin dan alasan penyebut nol (Q9) tetap wajib. Kontrol positifnya: gerbang tetap berlaku pada periode 2026 yang belum lampau saat aktivasi maupun pada aktivasi terlambat 2027, PIC tetap ditolak, dan pengajuan ditolak setelah penutupan. `tests/Feature/Rekomendasi/RekomendasiBerlakuTest.php` menguji pengecualian tindak lanjut rekomendasi pada periode lampau. Gerbang final Belum dapat diukur sudah diterapkan dan diuji di `tests/Feature/PengukuranWorkflowTest.php` (narasi wajib per field dan penolakan pada periode nilai akhir); AC-9/TEST-9 belum dicentang karena klausa kondisi tidak dapat dihitung (Q9) pada periode nilai akhir belum memiliki Pest test khusus. [CI exact-HEAD `0c9a1ea`](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/actions/runs/39021536370) lulus. Centang menandai implementasi/test otomatis, bukan penerimaan QA/UAT atau penyelesaian seluruh DoD.

> **Q45, Q46, Q47, Q51 (13 Oktober 2026):** kewajiban `catatan` saat nilai memburuk atau indikator `wajib_catatan` digantikan narasi pengukuran wajib; `catatan` menjadi keterangan opsional dan saklar `wajib_catatan` disembunyikan dari form indikator tanpa menghapus kolomnya (Q45 §45.1 butir 1 dan 3). Tindak lanjut wajib hanya atas Rekomendasi Pimpinan yang disetujui, termasuk lintas tahun (Q45 §45.2 butir 3, Q51 §51.1 butir 2 dan 4). Realisasi `belum_dapat_diukur` mengecualikan gerbang kelengkapan komponen, dan periode tanpa target ditampilkan dengan selisih terhadap target PK (Q47 §47.3–§47.4). Gerbang baru Q42–Q45 dikecualikan untuk periode lampau (Q46 §46.1 butir 1). AC-2 dan AC-4 diperbarui serta AC-8 sampai AC-12 ditambahkan karena issue ini belum dikerjakan.

**Terkait User Story:** `US-07.03`  
**Prioritas:** 🔴 P0 · Tahap 2 (Q46 §46.3)  
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

- **Dependensi:** Pengukuran draft/dikembalikan; jendela aktor sah; RA resmi tersedia pada jalur normal; Rekomendasi Pimpinan disetujui (ISS-09.01) untuk gerbang tindak lanjut.
- **Otorisasi:** `pengukuran:update` untuk submit sesuai scope.
- **Dampak Data:** `pengukuran`, `pengukuran_versi`, `audit_log`; item notifikasi in-app ke Perencanaan menunggu ISS-12.02 (Q37 §37.3 butir 4).
- **Rule (Q51 §51.1 butir 4):** kewajiban tindak lanjut rekomendasi (AC-8) berlaku lintas tahun: rekomendasi disetujui pada periode terakhir suatu tahun ditindaklanjuti pada periode pertama jadwal tahun berikutnya bila indikatornya masih diukur.
- **Rule (Q47 §47.3 butir 3–4):** bila periode tanpa target dan realisasi belum dapat diukur berlaku bersamaan, tampilan memakai "Belum dapat diukur"; penetapan status capaian manual tidak tersedia untuk kedua kondisi tersebut (ISS-09.02).

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given jalur normal, When submit, Then RA indikator-tahun harus memiliki versi yang telah disahkan.
- [ ] **AC-2:** Given nonmanual dan input wajib belum lengkap, When submit, Then ditolak; pengecualian `tidak_dapat_dihitung` hanya berlaku untuk penyebut efektif nol dengan komponen lengkap dan alasan, dan pengukuran berstatus `belum_dapat_diukur` dikecualikan dari gerbang kelengkapan komponen. *(Q9; diperbarui Q47 §47.4 butir 1)*
- [ ] **AC-3:** Given bukti wajib menurut persyaratan saat submit belum terpenuhi, When submit, Then ditolak.
- [x] **AC-4:** Given pengukuran diajukan, When salah satu narasi `progres_kegiatan`, `kendala_masalah`, atau `strategi_tindak_lanjut` kosong, Then submit ditolak; `catatan` tetap opsional. *(Diperbarui Q45 §45.1 butir 1 dan 3; semula `catatan` wajib bila nilai memburuk menurut arah atau indikator `wajib_catatan`.)*
- [ ] **AC-5:** Given seluruh gerbang lolos, Then header menjadi `diajukan` dan **`pengukuran_versi` baru** dibuat dengan `diajukan_by`, `diajukan_at`, `jalur_pengajuan`, `dasar_izin_pengajuan`, nilai/komponen, status perhitungan, snapshot target PK/RA, bukti, klaim/narasi, dan persyaratan.
- [ ] **AC-6:** Given A membuat draft dan B melakukan submit, Then `diajukan_by = B`; F1/F2 tidak menggunakan `created_by`.
- [ ] **AC-7:** Given submit berhasil, Then pengukuran tampil pada antrean verifikasi berbasis status (ISS-08.01); item notifikasi in-app mengikuti ISS-12.02 AC-2. *(Diperbarui Q37 §37.3 butir 4, keputusan PM 14 Oktober 2026; semula notifikasi antrean Perencanaan dibuat setelah commit.)*
- [x] **AC-8:** Given terdapat Rekomendasi Pimpinan berstatus `disetujui` pada periode N untuk indikator tersebut, When pengukuran periode N+1 diajukan tanpa `tindak_lanjut_rekomendasi`, Then submit ditolak; rekomendasi berstatus `draf` tidak memicu kewajiban ini. *(Q45 §45.2 butir 3, Q51 §51.1 butir 2)*
- [ ] **AC-9:** Given pengukuran berstatus `belum_dapat_diukur`, When diajukan, Then narasi pengukuran tetap wajib dan menjelaskan mengapa nilai belum tersedia; pada periode nilai akhir status ini ditolak karena realisasi wajib terisi, sedangkan kondisi tidak dapat dihitung menurut Q9 tetap diperbolehkan. *(Q47 §47.4 butir 2 dan 4)*
- [x] **AC-10:** Given pengukuran periode lampau diisi Perencanaan, Then gerbang narasi wajib dan tindak lanjut rekomendasi dikecualikan. *(Q46 §46.1 butir 1 dan 4)*
- [x] **AC-11:** Given form indikator, Then saklar `wajib_catatan` tidak ditampilkan dan kolomnya tetap tersimpan. *(Q45 §45.1 butir 3)*
- [ ] **AC-12:** Given periode tanpa target, Then tampilan pengukuran menunjukkan realisasi, target PK, dan selisih terhadap target PK tanpa capaian persen maupun status capaian; pada periode bertarget, selisih terhadap target PK tampil sebagai informasi tambahan. *(Q47 §47.3 butir 1–2)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `pengukuran`, `pengukuran_versi`, `audit_log`; penyimpanan/pengiriman notifikasi in-app menunggu ISS-12.02.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Pastikan `pengukuran_versi`/referensi `pengukuran_versi_id` konsisten; versi resmi lama tetap tersimpan ketika koreksi dibuat.

**B. Backend / Domain**
- [ ] Submit Pengukuran harus atomik: validasi RA versi disahkan, input/status perhitungan, bukti versi, narasi pengukuran wajib (`catatan` opsional, Q45 §45.1), scope/PIC/waktu, lalu insert `pengukuran_versi`.
- [ ] Bekukan provenance (`diajukan_by`, `jalur_pengajuan`, `dasar_izin_pengajuan`) serta target PK/RA, komponen/nilai, bukti, klaim/narasi, dan persyaratan.
- [x] Wajibkan `tindak_lanjut_rekomendasi` bila ada Rekomendasi Pimpinan `disetujui` pada periode sebelumnya, termasuk periode terakhir tahun sebelumnya terhadap periode pertama jadwal tahun berikutnya bila indikator masih diukur (Q51 §51.1 butir 4); draf tidak memicu kewajiban.
- [ ] Terima `belum_dapat_diukur` dengan pengecualian gerbang kelengkapan komponen, kecuali pada periode nilai akhir (`periode.is_nilai_akhir`) yang mewajibkan realisasi terisi selain kondisi Q9 (Q47 §47.4 butir 1 dan 4).
- [x] Kecualikan gerbang narasi wajib dan tindak lanjut rekomendasi untuk periode lampau (Q46 §46.1 butir 1 dan 4).
- [ ] Hitung selisih terhadap target PK dari snapshot (realisasi − target PK, dibaca menurut `arah`); pada periode tanpa target, capaian persen dan status capaian tidak dihitung, dan bila realisasi juga belum dapat diukur yang tampil "Belum dapat diukur" (Q47 §47.3 butir 1–3).
- [ ] `created_by` tidak boleh dipakai sebagai identitas pengaju F1/F2.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengukuran:update` untuk submit sesuai scope.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Pengajuan Pengukuran & Pembekuan Versi**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Sembunyikan saklar `wajib_catatan` pada form indikator (`resources/js/Pages/Perencanaan/SasaranIndikator/IndikatorModal.tsx`) tanpa menghapus kolomnya (Q45 §45.1 butir 3).
- [ ] Tampilkan realisasi, target PK, dan selisih terhadap target PK; pada periode tanpa target, tanpa capaian persen maupun status capaian (Q47 §47.3).
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given jalur normal, When submit, Then RA indikator-tahun harus memiliki versi yang telah disahkan.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given nonmanual dan input wajib belum lengkap, When submit, Then ditolak; pengecualian `tidak_dapat_dihitung` hanya berlaku untuk penyebut efektif nol dengan komponen lengkap dan alasan, dan pengukuran berstatus `belum_dapat_diukur` dikecualikan dari gerbang kelengkapan komponen. *(Q9; diperbarui Q47 §47.4 butir 1)*
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given bukti wajib menurut persyaratan saat submit belum terpenuhi, When submit, Then ditolak.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given pengukuran diajukan, When salah satu narasi `progres_kegiatan`, `kendala_masalah`, atau `strategi_tindak_lanjut` kosong, Then submit ditolak; `catatan` tetap opsional. *(Diperbarui Q45 §45.1 butir 1 dan 3; semula `catatan` wajib bila nilai memburuk menurut arah atau indikator `wajib_catatan`.)*
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given seluruh gerbang lolos, Then header menjadi `diajukan` dan **`pengukuran_versi` baru** dibuat dengan `diajukan_by`, `diajukan_at`, `jalur_pengajuan`, `dasar_izin_pengajuan`, nilai/komponen, status perhitungan, snapshot target PK/RA, bukti, klaim/narasi, dan persyaratan.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given A membuat draft dan B melakukan submit, Then `diajukan_by = B`; F1/F2 tidak menggunakan `created_by`.
- [ ] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given submit berhasil, Then pengukuran tampil pada antrean verifikasi berbasis status (ISS-08.01); pengiriman notifikasi in-app diuji pada ISS-12.02.
- [x] TEST-8: Buat Pest Feature/Unit test yang membuktikan — Given terdapat Rekomendasi Pimpinan berstatus `disetujui` pada periode N untuk indikator tersebut, When pengukuran periode N+1 diajukan tanpa `tindak_lanjut_rekomendasi`, Then submit ditolak; rekomendasi berstatus `draf` tidak memicu kewajiban ini. *(Q45 §45.2 butir 3, Q51 §51.1 butir 2)*
- [ ] TEST-9: Buat Pest Feature/Unit test yang membuktikan — Given pengukuran berstatus `belum_dapat_diukur`, When diajukan, Then narasi pengukuran tetap wajib dan menjelaskan mengapa nilai belum tersedia; pada periode nilai akhir status ini ditolak karena realisasi wajib terisi, sedangkan kondisi tidak dapat dihitung menurut Q9 tetap diperbolehkan. *(Q47 §47.4 butir 2 dan 4)*
- [x] TEST-10: Buat Pest Feature/Unit test yang membuktikan — Given pengukuran periode lampau diisi Perencanaan, Then gerbang narasi wajib dan tindak lanjut rekomendasi dikecualikan. *(Q46 §46.1 butir 1 dan 4)*
- [x] TEST-11: Buat Vitest frontend test dan Pest Feature test yang membuktikan — Given form indikator, Then saklar `wajib_catatan` tidak ditampilkan dan kolomnya tetap tersimpan. *(Q45 §45.1 butir 3)*
- [ ] TEST-12: Buat Pest Feature/Unit test yang membuktikan — Given periode tanpa target, Then tampilan pengukuran menunjukkan realisasi, target PK, dan selisih terhadap target PK tanpa capaian persen maupun status capaian; pada periode bertarget, selisih terhadap target PK tampil sebagai informasi tambahan. *(Q47 §47.3 butir 1–2)*
- [x] LINTAS TAHUN: Uji rekomendasi disetujui pada periode terakhir suatu tahun mewajibkan `tindak_lanjut_rekomendasi` pada periode pertama jadwal tahun berikutnya bila indikatornya masih diukur (Q51 §51.1 butir 4).
- [ ] TAMPILAN BERSAMAAN: Uji periode tanpa target yang realisasinya `belum_dapat_diukur` ditampilkan sebagai "Belum dapat diukur" (Q47 §47.3 butir 3).
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

> **Q46, Q50 (13 Oktober 2026):** TW III 2026 ikut menjadi periode lampau yang diisi Perencanaan, gerbang narasi wajib dan tindak lanjut rekomendasi dikecualikan untuk periode lampau, dan realisasi serta narasi TW I–II diisi dari Laporan Kinerja Triwulan I–II 2026 (Q46 §46.1 butir 1, 2, dan 6). "IKU 3" berkode IKU 1.3 pada PK (Q50 §50.4). Scope dan AC diperbarui langsung karena issue ini belum dikerjakan; rumusan semula (TW I–II) menjadi riwayat.

**Terkait User Story:** `US-07.05`  
**Prioritas:** 🟡 P1  
**Story Points:** 5

#### Scope

Mencatat pengukuran TW I–III 2026 melalui jalur Perencanaan karena jendela normal telah lewat (Q46 §46.1 butir 2; semula TW I–II). Tidak membangun tipe pengukuran/backfill khusus.

#### Acceptance Criteria

- [ ] Pengisian hanya memakai jalur Perencanaan untuk periode lampau (TW I–III 2026). *(Q46 §46.1 butir 2; semula TW I–II)*
- [ ] Tidak ada `is_backfill` atau tipe pengukuran khusus.
- [ ] Nilai tetap terhubung ke Jadwal/Snapshot yang sah dan teraudit.
- [ ] Data TW II IKU 3 (kode PK IKU 1.3, Q50 §50.4) tidak menggunakan 66,395 atau 87,08 sebagai realisasi.
- [ ] Realisasi dan narasi pengukuran TW I–II diisi dari Laporan Kinerja Triwulan I–II 2026 (rekomendasinya mengikuti US-09.01 dan US-15.01); gerbang narasi wajib dan tindak lanjut rekomendasi dikecualikan untuk periode lampau. *(Q46 §46.1 butir 1 dan 6)*

#### Automated Tests

- [ ] Uji hanya jalur Perencanaan yang dapat mengisi TW I–III 2026, sedangkan user scoped tetap mengikuti jendela.
- [ ] Uji tidak ada `is_backfill` atau tipe pengukuran khusus, dan nilai terhubung ke Jadwal/Snapshot yang sah serta teraudit.
- [ ] Uji 66,395 dan 87,08 tidak dicatat sebagai realisasi IKU 1.3 TW II.
- [ ] Uji gerbang narasi wajib dan tindak lanjut rekomendasi dikecualikan untuk periode lampau; UAT memverifikasi realisasi dan narasi TW I–II sesuai Laporan Kinerja Triwulan I–II 2026.

### ISS-07.06 · [Feature] Penanganan Penyebut Nol — Tidak Dapat Dihitung

> **Q47 (13 Oktober 2026):** realisasi yang belum tersedia dan bukan karena penyebut nol dicatat sebagai `status_perhitungan = belum_dapat_diukur` (ISS-07.01), yang berbeda dari `tidak_dapat_dihitung` dan dari realisasi `0`; keduanya tidak dihitung sebagai capaian 0 (Q47 §47.4 butir 3). AC-6 ditambahkan karena issue ini belum dikerjakan.

> **Keputusan PM (16 Oktober 2026):** AC-5 dan TEST-5 disamakan dengan body issue #81: buktinya ada di halaman Verifikasi, yang membaca penanda dan alasan dari snapshot versi. Tampilan laporan beserta alasan tetap terbuka sebagai milik ISS-10.02 (AC-6) dan US-07.06 AC-5, sehingga tidak ikut dicentang di sini.

**Terkait User Story:** `US-07.06`  
**Prioritas:** 🟡 P1 · Tahap 2 (Q46 §46.3)  
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

- [x] **AC-1:** Given seluruh komponen lengkap dan penyebut = 0, When dihitung, Then `nilai = NULL` dan `status_perhitungan = tidak_dapat_dihitung`.
- [x] **AC-2:** Given status tersebut akan disubmit, Then `alasan_tidak_dapat_dihitung` wajib.
- [x] **AC-3:** Given komponen belum lengkap, Then status tetap `belum_diisi` dan tidak boleh menggunakan pengecualian penyebut nol.
- [x] **AC-4:** Given nilai faktual 0 pada indikator yang dapat dihitung, Then 0 tetap `terhitung` dan bukan NULL.
- [x] **AC-5:** Given versi diajukan/disahkan dengan `tidak_dapat_dihitung`, Then halaman verifikasi menampilkan penanda dan alasan dari snapshot versi sesuai hak baca; tampilan laporan milik ISS-10.02.
- [ ] **AC-6:** Given nilai realisasi belum tersedia dan bukan karena penyebut nol, Then status yang dipakai adalah `belum_dapat_diukur`, bukan `tidak_dapat_dihitung`; keduanya ditampilkan berbeda dan tidak dihitung sebagai capaian 0. *(Q47 §47.4 butir 3)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `pengukuran`, `pengukuran_komponen`, versi, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Bedakan `belum_diisi`, `terhitung`, dan `tidak_dapat_dihitung`; hanya komponen lengkap + penyebut efektif nol yang boleh masuk status tidak dapat dihitung. Status `belum_dapat_diukur` (ISS-07.01) tetap terpisah dan tidak disamakan dengan penyebut nol (Q47 §47.4 butir 3).

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengukuran:update` sesuai scope.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Penanganan Penyebut Nol — Tidak Dapat Dihitung**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given seluruh komponen lengkap dan penyebut = 0, When dihitung, Then `nilai = NULL` dan `status_perhitungan = tidak_dapat_dihitung`.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given status tersebut akan disubmit, Then `alasan_tidak_dapat_dihitung` wajib.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given komponen belum lengkap, Then status tetap `belum_diisi` dan tidak boleh menggunakan pengecualian penyebut nol.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given nilai faktual 0 pada indikator yang dapat dihitung, Then 0 tetap `terhitung` dan bukan NULL.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given versi diajukan/disahkan dengan `tidak_dapat_dihitung`, Then halaman verifikasi menampilkan penanda dan alasan dari snapshot versi sesuai hak baca; tampilan laporan milik ISS-10.02.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given nilai realisasi belum tersedia dan bukan karena penyebut nol, Then status yang dipakai adalah `belum_dapat_diukur`, bukan `tidak_dapat_dihitung`; keduanya ditampilkan berbeda dan tidak dihitung sebagai capaian 0. *(Q47 §47.4 butir 3)*

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).


---

## Bagian 8 — Reviu, Verifikasi, Pengesahan, dan Koreksi Pengukuran

### ISS-08.01 · [Feature] Verifikasi & Pengembalian Pengukuran dengan F1

> **Status implementasi (GitHub #83):** Inti verifikasi/pengembalian, F1, alasan wajib, reviu terlambat, peringatan nonblocking, pembacaan narasi/justifikasi beku, filter antrean, serta revisi/pengajuan ulang telah terimplementasi dan diuji; lihat [PR #144](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/144) untuk baseline, cakupan test, CI, dan bukti browser. Kontrak pembanding peringatan mengikuti Q61, berdasarkan keputusan PM 19 Oktober 2026. Status ini bukan persetujuan QA/UAT, merge, atau deployment.

> **Batas completion:** AC-3 tetap terbuka untuk ketergantungan item notifikasi ISS-12.02; transisi pengembalian, alasan, audit, dan revisinya sudah teruji. Reader justifikasi versi beku teruji, sedangkan workflow perubahan status kegiatan tetap mengikuti ISS-06.04. Test utama: VerifikasiPengukuranTest, AntreanVerifikasiTest, VerticalSlice1Test, SahkanPengukuranTest, dan PengukuranWorkflow.

> **Pemetaan nomor:** GitHub #83 AC-7 bersesuaian dengan AC-6 dokumen ini; GitHub AC-8 dengan AC-7. GitHub AC-6 tentang izin/stale dilindungi regresi authorization dan optimistic locking. Checkbox AC menandai bukti lokal; persetujuan QA/UAT belum dinyatakan.

> **Q41, Q47 dan ketentuan pelengkap Q47–Q51 (13 Oktober 2026):** saat verifikasi, sistem memperingatkan bila realisasi atau narasi sama persis dengan periode sebelumnya tanpa memblokir verifikasi, Perencanaan menilai alasan pengukuran berstatus `belum_dapat_diukur` (Q47 §47.4 butir 2), dan justifikasi kegiatan yang ditunda tampil sebagai catatan resmi (Q41 §41.4 butir 3). Pengembalian tetap wajib alasan (Q43 §43.2 butir 1). AC-6 dan AC-7 ditambahkan karena issue ini belum dikerjakan.

**Terkait User Story:** `US-08.01`  
**Prioritas:** 🔴 P0 · Tahap 2 (Q46 §46.3)  
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
- **Dampak Data:** `pengukuran`, `pengukuran_versi`, `audit_log`; item notifikasi in-app pengembalian menunggu ISS-12.02 (Q37 §37.3 butir 4).

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given versi diajukan melalui jalur PIC, When aktor sama dengan `pengukuran_versi.diajukan_by` mencoba **memverifikasi**, Then F1 menolak.
- [x] **AC-2:** Given reviewer berbeda dan berizin, When verifikasi dilakukan, Then status menjadi `diverifikasi` tanpa mengubah snapshot versi.
- [ ] **AC-3:** Given kesalahan ditemukan pada status `diajukan` atau `diverifikasi`, When dikembalikan dengan alasan, Then status menjadi `dikembalikan`; item notifikasi pengembalian in-app mengikuti ISS-12.02 AC-1. *(Diperbarui Q37 §37.3 butir 4, keputusan PM 14 Oktober 2026; semula notifikasi dikirim setelah commit.)*
- [x] **AC-4:** Given pengembalian tanpa alasan, Then ditolak.
- [x] **AC-5:** Given tanggal melewati `reviu_selesai` tetapi belum penutupan, When Perencanaan mereviu, Then aksi tetap dapat dilakukan dan diberi penanda reviu terlambat.
- [x] **AC-6:** Given realisasi atau narasi pengukuran sama persis dengan periode sebelumnya, When Perencanaan memverifikasi, Then sistem menampilkan peringatan yang tidak memblokir verifikasi. *(Ketentuan pelengkap butir 2, pengantar Q47–Q51)*
- [x] **AC-7:** Given pengukuran berstatus `belum_dapat_diukur`, When Perencanaan menilai narasi alasannya tidak dapat diterima, Then pengukuran dapat dikembalikan dengan alasan. *(Q47 §47.4 butir 2)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `pengukuran`, `pengukuran_versi`, `audit_log`; penyimpanan/pengiriman notifikasi in-app menunggu ISS-12.02.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] F1 wajib diterapkan pada **verifikasi** berdasarkan `pengukuran_versi.diajukan_by` untuk jalur PIC.
- [x] Kembalikan dari Diajukan/Diverifikasi memerlukan alasan dan tidak mengubah payload versi.
- [x] Bandingkan realisasi dan narasi pengukuran dengan versi periode sebelumnya pada indikator yang sama; bila sama persis, tampilkan peringatan tanpa memblokir verifikasi.
- [x] Tampilkan narasi alasan pengukuran `belum_dapat_diukur` untuk dinilai, serta justifikasi kegiatan `ditunda`/`tidak_terlaksana` yang diklaim sebagai catatan resmi verifikasi (Q47 §47.4 butir 2, Q41 §41.4 butir 3; ISS-06.04).

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengukuran:verifikasi`, `pengukuran:kembalikan`.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Verifikasi & Pengembalian Pengukuran dengan F1**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [x] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [x] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given versi diajukan melalui jalur PIC, When aktor sama dengan `pengukuran_versi.diajukan_by` mencoba **memverifikasi**, Then F1 menolak.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given reviewer berbeda dan berizin, When verifikasi dilakukan, Then status menjadi `diverifikasi` tanpa mengubah snapshot versi.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given kesalahan ditemukan pada status `diajukan` atau `diverifikasi`, When dikembalikan dengan alasan, Then status menjadi `dikembalikan`; pengiriman notifikasi in-app diuji pada ISS-12.02.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given pengembalian tanpa alasan, Then ditolak.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given tanggal melewati `reviu_selesai` tetapi belum penutupan, When Perencanaan mereviu, Then aksi tetap dapat dilakukan dan diberi penanda reviu terlambat.
- [x] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given realisasi atau narasi pengukuran sama persis dengan periode sebelumnya, When Perencanaan memverifikasi, Then sistem menampilkan peringatan yang tidak memblokir verifikasi. *(Ketentuan pelengkap butir 2, pengantar Q47–Q51)*
- [x] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given pengukuran berstatus `belum_dapat_diukur`, When Perencanaan menilai narasi alasannya tidak dapat diterima, Then pengukuran dapat dikembalikan dengan alasan. *(Q47 §47.4 butir 2)*
- [x] JUSTIFIKASI KEGIATAN: Uji justifikasi kegiatan `ditunda` yang diklaim tampil sebagai catatan resmi saat verifikasi pengukuran (Q41 §41.4 butir 3).
- [x] REGRESSION: Uji pembuat draft berbeda dari pengaju; perubahan role/PIC/grant setelah submit tidak mengubah provenance atau meloloskan F1.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [x] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-08.02 · [Feature] Pengesahan Pengukuran & F1/F2

**Terkait User Story:** `US-08.02`  
**Prioritas:** 🔴 P0 · Tahap 2 (Q46 §46.3)  
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

- [x] **AC-1:** Given jalur pengajuan PIC, When aktor sama dengan `diajukan_by` mencoba sahkan, Then F1 menolak.
- [x] **AC-2:** Given aktor lain berizin, When sahkan, Then status menjadi `disahkan` dan `disahkan_by/disahkan_at` versi terisi atomik.
- [x] **AC-3:** Given jalur pengajuan Perencanaan, When pengaju yang sama mereviu/mengesahkan dan izin efektif masih ada, Then F2 mengizinkan dan mencatat `self_approval`.
- [x] **AC-4:** Given deny/permission tidak memenuhi, Then F2 tidak dapat melewati resolver.
- [x] **AC-5:** Given versi stale, When sahkan, Then ditolak.
- [ ] **AC-6:** Given pengesahan berhasil, Then laporan resmi membaca snapshot `pengukuran_versi` tersebut, bukan live join master terbaru.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `pengukuran`, `pengukuran_versi`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Pastikan `pengukuran_versi`/referensi `pengukuran_versi_id` konsisten; versi resmi lama tetap tersimpan ketika koreksi dibuat. (Dikembalikan `[ ]` 15 Oktober 2026 menunggu ISS-08.03 — temuan review PR #107.)

**B. Backend / Domain**
- [x] Pengesahan memakai `pengukuran_versi.diajukan_by` + `jalur_pengajuan`, menolak versi stale, dan mengisi metadata pengesahan atomik.
- [x] F2 tidak melewati resolver/deny; `self_approval` harus tampak pada audit/laporan internal sesuai kontrak.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `pengukuran:sahkan` (sensitif).
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Pengesahan Pengukuran & F1/F2**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`. (Dikembalikan `[ ]` 15 Oktober 2026 menunggu evidence QA browser — temuan review PR #107.)
- [x] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given jalur pengajuan PIC, When aktor sama dengan `diajukan_by` mencoba sahkan, Then F1 menolak.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given aktor lain berizin, When sahkan, Then status menjadi `disahkan` dan `disahkan_by/disahkan_at` versi terisi atomik.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given jalur pengajuan Perencanaan, When pengaju yang sama mereviu/mengesahkan dan izin efektif masih ada, Then F2 mengizinkan dan mencatat `self_approval`.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given deny/permission tidak memenuhi, Then F2 tidak dapat melewati resolver.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given versi stale, When sahkan, Then ditolak.
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given pengesahan berhasil, Then laporan resmi membaca snapshot `pengukuran_versi` tersebut, bukan live join master terbaru.
- [x] REGRESSION: Uji pembuat draft berbeda dari pengaju; perubahan role/PIC/grant setelah submit tidak mengubah provenance atau meloloskan F1.
- [x] CONCURRENCY: Uji transaksi/optimistic locking/versi stale sesuai kontrak Data Model.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline. (Acuan F1 diselaraskan ke diajukan_by versi beku; diizinkan re-review PM 15 Oktober 2026. AC-6/TEST-6/koreksi/UI tetap terbuka.)
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas). (Dikembalikan `[ ]` 15 Oktober 2026 menunggu evidence QA browser — temuan review PR #107.)
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

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

### ISS-09.01 · [Feature] Rekomendasi Pimpinan: Draf Perencanaan dan Persetujuan Pimpinan

> **Q45, Q51 (13 Oktober 2026):** Rekomendasi Pimpinan kini diisi Pimpinan: Perencanaan menyusun draf, Pimpinan menyetujui draf (dapat mengubah isinya lebih dulu) atau menulis rekomendasi langsung, dan hanya rekomendasi berstatus disetujui yang berlaku (Q45 §45.2 butir 1, Q51 §51.1). Persetujuan oleh Perencanaan menjadi jalur cadangan yang diberi penanda (Q51 §51.2 butir 2). Issue ini belum dikerjakan sehingga isinya diperbarui langsung dan dipindah ke tahap 2 (Q51 §51.3); task Plan memakai 6.7 (rujukan lama "Modul 8: 8.5" kini milik ISS-10.05). Riwayat: judul semula "Pencatatan Rekomendasi Pimpinan oleh Perencanaan" (digantikan Q45/Q51); AC-1 sampai AC-3 semula diperbarui menjadi AC-1, AC-5, dan AC-6; AC-4 semula (Pimpinan hanya membaca rekomendasi pada MVP) dibatalkan Q45 §45.2 butir 1.

> **Q55 (16 Oktober 2026):** paling banyak satu draf terbuka per indikator × tahun × periode; penyimpanan dan persetujuan membawa token versi draf dan rekomendasi berlaku yang dilihat, sehingga tampilan basi ditolak sebagai konflik versi tanpa mengubah data. Penyetuju cadangan boleh menulis rekomendasi yang langsung disetujui dengan penanda cadangan, dan versi pengajuan pengukuran membekukan rekomendasi yang tampil; pengajuan dari tampilan basi ditolak (Q55 §55.1).

**Terkait User Story:** `US-09.01`  
**Prioritas:** 🟡 P1 · Tahap 2 (Q46 §46.3; dipindah Q51 §51.3)  
**Story Points:** 5  
**Labels:** `feature`, `evaluation`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 6: 6.7  
**PRD:** §4 prinsip 5, §22  
**Workflow:** §17  
**Data Model/Entitas:** `rekomendasi_pimpinan`

#### User Story

> **Sebagai** Pimpinan dan Tim Perencanaan,  
> **Saya ingin** Perencanaan menyusun draf rekomendasi per indikator-periode lalu Pimpinan menyetujuinya atau menulis rekomendasi langsung,  
> **Sehingga** arahan pimpinan hasil evaluasi tercatat sebagai keputusan Pimpinan dan hanya rekomendasi yang disetujui ditindaklanjuti.

#### Kontrak Teknis

- **Dependensi:** Indikator, tahun, dan periode valid.
- **Otorisasi:** `rekomendasi:tetapkan` (sensitif; preset Pimpinan dan Perencanaan melalui seeder, Q45 §45.2 butir 1, Q51 §51.2 butir 1).
- **Dampak Data:** `rekomendasi_pimpinan` (`status` `draf`/`disetujui`, `disetujui_oleh`, `disetujui_oleh_role`, `disetujui_pada`, `rujukan_sumber`, `versi` (Q55)), preset `role_permissions`, `audit_log`.
- **Keputusan terkait:** Q45 §45.2, Q51, Q46 §46.3.
- **Rule:** rekomendasi melekat pada indikator × periode dan independen dari status pengukuran (Q45 §45.2 butir 2); rekomendasi yang disetujui dimuat pada Laporan Kinerja Triwulan (Q48 §48.1 butir 1; ISS-10.05).
- **Rule (Q51 §51.1 butir 4):** kewajiban tindak lanjut berlaku lintas tahun: rekomendasi pada periode terakhir suatu tahun ditindaklanjuti pada periode pertama jadwal tahun berikutnya bila indikatornya masih diukur; tindak lanjut dikecualikan untuk periode lampau (Q46 §46.1 butir 1; ISS-07.03).
- **Rule (Q51 §51.1 butir 5):** data awal 2026: rekomendasi TW I–II dari laporan triwulan yang sudah ditandatangani Pimpinan dicatat Perencanaan langsung berstatus `disetujui`, dengan penanda persetujuan cadangan dan rujukan laporan sumbernya pada `rujukan_sumber` (ISS-15.01).
- **Rule (Q51 §51.2 butir 2):** penanda persetujuan cadangan berlaku untuk setiap persetujuan oleh peran selain Pimpinan, termasuk Superadmin, dan tercatat di audit.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given Perencanaan menyimpan rekomendasi untuk indikator × periode, Then baris baru dibuat berstatus `draf` dengan `ditetapkan_oleh` dan waktu, serta tercatat di audit. *(Diperbarui Q51 §51.1 butir 1; semula baris langsung berlaku.)*
- [x] **AC-2:** Given Pimpinan memiliki `rekomendasi:tetapkan`, When Pimpinan menyetujui draf (dengan atau tanpa mengubah isinya lebih dulu) atau menulis rekomendasi langsung, Then rekomendasi berstatus `disetujui` dengan `disetujui_oleh`, `disetujui_oleh_role`, dan `disetujui_pada` terisi serta tercatat di audit. *(Q51 §51.1 butir 1)*
- [x] **AC-3:** Given rekomendasi berstatus `draf`, When Perencanaan menyetujuinya sebagai jalur cadangan bila Pimpinan berhalangan, Then persetujuan diterima dengan penanda cadangan (`disetujui_oleh_role = perencanaan`) dan tercatat di audit. *(Q51 §51.2 butir 2)*
- [ ] **AC-4:** Given rekomendasi masih berstatus `draf`, Then rekomendasi tidak tampil bagi PIC, tidak tampil pada pengukuran periode berikutnya maupun Laporan Kinerja Triwulan, dan tidak memicu tindak lanjut wajib; hanya rekomendasi `disetujui` yang berlaku. *(Q51 §51.1 butir 2)* *(Draf tidak tampil pada pengukuran periode berikutnya, dan halaman Rekomendasi Pimpinan hanya untuk pemegang izin; bagian Laporan Kinerja Triwulan menunggu ISS-10.05 dan tindak lanjut wajib menunggu gerbang ISS-07.03, keduanya memakai pembaca rekomendasi yang berlaku.)*
- [x] **AC-5:** Given pengukuran periode belum disahkan, When rekomendasi ditulis setelah rapat, Then sistem tetap mengizinkan karena rekomendasi opsional per indikator per periode dan independen dari status pengukuran. *(Q51 §51.1 butir 3)*
- [x] **AC-6:** Given rekomendasi sudah disetujui, When diubah, Then perubahan membentuk baris baru yang melalui persetujuan yang sama, sedangkan baris lama tidak dihapus dan tetap menjadi histori. *(Q51 §51.2 butir 3)*
- [ ] **AC-7:** Given rekomendasi disetujui pada periode N, Then pengukuran periode N+1 untuk indikator tersebut wajib memuat tindak lanjut atas rekomendasi saat diajukan (gerbang pada US-07.03). *(Q45 §45.2 butir 3)* *(Gerbang pengajuan dipasang pada ISS-07.03 / #82; issue ini menyediakan pembaca rekomendasi yang berlaku, termasuk lintas tahun.)*
- [x] **AC-8:** Given seeder preset dijalankan, Then `rekomendasi:tetapkan` dimiliki preset Pimpinan dan Perencanaan; simpan draf dan setujui dibedakan oleh status rekomendasi, bukan oleh permission. *(Q45 §45.2 butir 1, Q51 §51.2 butir 1)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `rekomendasi_pimpinan`, `audit_log`.
- [x] Sediakan `rekomendasi_pimpinan` sesuai Data Model §2.31 dengan `status` enum(`draf`,`disetujui`), `disetujui_oleh`, `disetujui_oleh_role`, dan `disetujui_pada`; baris bersifat riwayat, dan rekomendasi yang berlaku adalah baris terbaru berstatus `disetujui`.
- [x] Tambahkan kolom `rekomendasi_pimpinan.rujukan_sumber` untuk rujukan laporan sumber rekomendasi data awal 2026 (Q51 §51.1 butir 5; Data Model §2.31).
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Implementasikan use-case **Rekomendasi Pimpinan: Draf Perencanaan dan Persetujuan Pimpinan** pada service/domain layer sesuai Acceptance Criteria; keputusan bisnis tidak boleh ditempatkan hanya di React.
- [x] Bedakan simpan draf dan setujui melalui status, bukan permission; persetujuan oleh peran selain Pimpinan (Perencanaan, Superadmin, atau peran lain yang memegang permission) diberi penanda persetujuan cadangan melalui `disetujui_oleh_role` (Q51 §51.2 butir 2); perubahan rekomendasi yang sudah disetujui membentuk baris baru melalui persetujuan yang sama (Q51 §51.2).
- [x] Sediakan pembacaan rekomendasi yang berlaku (baris terbaru berstatus `disetujui`) bagi gerbang tindak lanjut ISS-07.03, termasuk lintas tahun (Q51 §51.1 butir 4), dan bagi Laporan Kinerja Triwulan ISS-10.05; draf tidak dikirim ke PIC.
- [x] Gunakan transaction boundary pada mutasi multi-entitas dan kembalikan validation error/403/conflict secara eksplisit.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `rekomendasi:tetapkan` (sensitif).
- [x] Tambahkan `rekomendasi:tetapkan` pada preset Pimpinan melalui `RolePermissionPresets`/`SyncRolePermissionPresets` dengan audit delta preset (ISS-01.07); Perencanaan tetap memegangnya.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Rekomendasi Pimpinan: Draf Perencanaan dan Persetujuan Pimpinan**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Sediakan aksi Pimpinan untuk menyetujui draf (dengan mengubah isi bila perlu) dan menulis rekomendasi langsung; aksi Perencanaan untuk menyimpan draf dan menyetujui sebagai cadangan dengan penanda yang terlihat; PIC hanya melihat rekomendasi disetujui.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`. *(Responsivitas dan kontras menunggu smoke browser SSO.)*
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran. *(Tidak ada aksi rekomendasi yang mewajibkan alasan menurut Q43 §43.2; persetujuan memakai modal peninjauan isi.)*

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given Perencanaan menyimpan rekomendasi untuk indikator × periode, Then baris baru dibuat berstatus `draf` dengan `ditetapkan_oleh` dan waktu, serta tercatat di audit. *(Diperbarui Q51 §51.1 butir 1; semula baris langsung berlaku.)*
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given Pimpinan memiliki `rekomendasi:tetapkan`, When Pimpinan menyetujui draf (dengan atau tanpa mengubah isinya lebih dulu) atau menulis rekomendasi langsung, Then rekomendasi berstatus `disetujui` dengan `disetujui_oleh`, `disetujui_oleh_role`, dan `disetujui_pada` terisi serta tercatat di audit. *(Q51 §51.1 butir 1)*
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given rekomendasi berstatus `draf`, When Perencanaan menyetujuinya sebagai jalur cadangan bila Pimpinan berhalangan, Then persetujuan diterima dengan penanda cadangan (`disetujui_oleh_role = perencanaan`) dan tercatat di audit. *(Q51 §51.2 butir 2)*
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given rekomendasi masih berstatus `draf`, Then rekomendasi tidak tampil bagi PIC, tidak tampil pada pengukuran periode berikutnya maupun Laporan Kinerja Triwulan, dan tidak memicu tindak lanjut wajib; hanya rekomendasi `disetujui` yang berlaku. *(Q51 §51.1 butir 2)* *(Dibuktikan untuk pengukuran periode berikutnya dan halaman Rekomendasi Pimpinan; Laporan Kinerja Triwulan dan gerbang tindak lanjut menunggu ISS-10.05 dan ISS-07.03.)*
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given pengukuran periode belum disahkan, When rekomendasi ditulis setelah rapat, Then sistem tetap mengizinkan karena rekomendasi opsional per indikator per periode dan independen dari status pengukuran. *(Q51 §51.1 butir 3)*
- [x] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given rekomendasi sudah disetujui, When diubah, Then perubahan membentuk baris baru yang melalui persetujuan yang sama, sedangkan baris lama tidak dihapus dan tetap menjadi histori. *(Q51 §51.2 butir 3)*
- [ ] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given rekomendasi disetujui pada periode N, Then pengukuran periode N+1 untuk indikator tersebut wajib memuat tindak lanjut atas rekomendasi saat diajukan (gerbang pada US-07.03). *(Q45 §45.2 butir 3)* *(Menunggu gerbang ISS-07.03 / #82.)*
- [x] TEST-8: Buat Pest Feature/Unit test yang membuktikan — Given seeder preset dijalankan, Then `rekomendasi:tetapkan` dimiliki preset Pimpinan dan Perencanaan; simpan draf dan setujui dibedakan oleh status rekomendasi, bukan oleh permission. *(Q45 §45.2 butir 1, Q51 §51.2 butir 1)*
- [x] LINTAS TAHUN: Uji rekomendasi disetujui pada periode terakhir suatu tahun berlaku sebagai dasar tindak lanjut pada periode pertama jadwal tahun berikutnya bila indikatornya masih diukur (Q51 §51.1 butir 4).
- [x] DATA AWAL: Uji rekomendasi TW I–II data awal 2026 tersimpan berstatus `disetujui` dengan penanda persetujuan cadangan dan rujukan laporan sumbernya pada `rujukan_sumber` (Q51 §51.1 butir 5).
- [x] CADANGAN NON-PIMPINAN: Uji persetujuan oleh Superadmin atau peran lain selain Pimpinan juga diberi penanda persetujuan cadangan dan tercatat di audit (Q51 §51.2 butir 2).

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus. *(AC-4 bagian Laporan Kinerja Triwulan menunggu ISS-10.05; AC-7 menunggu gerbang ISS-07.03.)*
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas). *(Responsivitas menunggu smoke browser SSO.)*
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-09.02 · [Feature] Penetapan Status Capaian per Versi Pengukuran Disahkan

> **Q47, Q50 (13 Oktober 2026):** pada periode tanpa target, capaian persen dan status capaian tidak dihitung, dan realisasi `belum_dapat_diukur` tidak dihitung sebagai capaian 0 (Q47 §47.3 butir 1, §47.4 butir 3). Penetapan status capaian manual tidak tersedia untuk pengukuran pada periode tanpa target maupun pengukuran berstatus belum dapat diukur (Q47 §47.3 butir 4). Status capaian memakai target PK bernilai penuh yang tersimpan (Q50 §50.2).

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
- **Rule (Q47 §47.3 butir 4):** penetapan status capaian manual tidak tersedia untuk pengukuran pada periode tanpa target maupun pengukuran berstatus `belum_dapat_diukur`.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given versi pengukuran telah disahkan, When status dipilih, Then `status_capaian` merujuk `pengukuran_versi_id` yang sama.
- [ ] **AC-2:** Given pengukuran belum disahkan, When status dicoba, Then ditolak.
- [ ] **AC-3:** Given versi koreksi baru disahkan, Then versi baru mulai **Belum ditetapkan**; status versi lama tidak diwariskan otomatis.
- [ ] **AC-4:** Given status direvisi, Then histori status lama tetap tersimpan dan baris terbaru menjadi yang berlaku.
- [ ] **AC-5:** Given pengukuran pada periode tanpa target atau berstatus `belum_dapat_diukur`, When status capaian manual hendak ditetapkan, Then penetapan tidak tersedia, termasuk melalui request langsung. *(Q47 §47.3 butir 4)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `status_capaian`, `pengukuran_versi`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.
- [ ] Pastikan `pengukuran_versi`/referensi `pengukuran_versi_id` konsisten; versi resmi lama tetap tersimpan ketika koreksi dibuat.

**B. Backend / Domain**
- [ ] `status_capaian` wajib merujuk versi pengukuran yang telah disahkan; status tidak diwariskan ke versi koreksi baru.
- [ ] Tolak penetapan status capaian untuk pengukuran pada periode tanpa target atau berstatus `belum_dapat_diukur`, dan jangan tampilkan aksinya di UI (Q47 §47.3 butir 4).

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
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given pengukuran pada periode tanpa target atau berstatus `belum_dapat_diukur`, When status capaian manual hendak ditetapkan, Then penetapan tidak tersedia, termasuk melalui request langsung. *(Q47 §47.3 butir 4)*

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

> **Status implementasi (GitHub #135):** filter Renstra/tahun/periode/sasaran/unit yang divalidasi server, status indikator × periode dari daftar kewajiban, angka resmi dari versi disahkan, selisih terhadap target PK, progres Rencana Aksi (Plan 7.6), dan panel kegiatan real-time telah terimplementasi dan diuji (PR #147). Status tampilan mengikuti Q64. Sejak Q66, status tampilan tampil per baris pada tabel tanpa kartu ringkasan per status, dan panel Rencana Aksi serta kegiatan tampil berdampingan.

> **Batas completion:** AC-8 dan AKSES ANGGARAN tetap terbuka sampai penjumlahan anggaran ISS-06.08 bagian kedua tersedia; dikerjakan pada PR lanjutan. Tercapai/Belum tercapai belum tampil karena status capaian belum dapat ditetapkan (Q64 §64.1 butir 4). Plan 7.8 belum dikerjakan. Test utama: DashboardFilterTest, DashboardAksesTest, DashboardStatusIndikatorTest, DashboardRencanaAksiTest, DashboardKegiatanTest, DaftarKewajibanPengukuranTest, StatusTampilanPengukuranTest, serta Vitest DashboardIndex, DashboardFilter, DashboardPanel, dan SelisihTargetPk. Checkbox AC menandai bukti lokal; persetujuan QA/UAT belum dinyatakan.

> **Q42, Q45, Q47 (13 Oktober 2026):** dashboard menampilkan status kegiatan per periode secara real-time berlabel "belum diverifikasi" dengan peringatan bagi kegiatan yang melewati tanggal rencana (Q45 §45.3), total anggaran per IKU dengan drill-down sampai kegiatan (Q42 §42.2 butir 5), serta "Tanpa target periode", "Belum dapat diukur", dan selisih terhadap target PK; bila keduanya berlaku bersamaan, yang tampil "Belum dapat diukur" (Q47 §47.3–§47.4). Angka capaian resmi tetap hanya dari data yang disahkan (Q45 §45.3 butir 2). AC-7 sampai AC-10 ditambahkan karena issue ini belum dikerjakan.

**Terkait User Story:** `US-10.01`  
**Prioritas:** 🟡 P1 · Tahap 2 (Q46 §46.3)  
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

- [x] **AC-1:** Given dashboard dibuka, Then filter Renstra, tahun, periode, sasaran, dan unit bekerja sesuai hak akses.
- [x] **AC-2:** Given indikator-periode belum memiliki pengukuran, Then status dihitung dari daftar periode yang diharapkan, bukan dari keberadaan baris pengukuran semata.
- [x] **AC-3:** Given periode sebelum efektivitas indikator, Then dashboard menampilkan Tidak berlaku dan tidak menghitungnya sebagai missing.
- [x] **AC-4:** Given indikator arsip, Then tidak menjadi kewajiban baru.
- [x] **AC-5:** Given hasil resmi ditampilkan, Then angka berasal dari versi disahkan/snapshot yang tepat, bukan master terbaru yang bisa berubah.
- [x] **AC-6 (Q31):** Given user ber-PIC operasional (bukan role), When mengakses dashboard, Then keputusan akses tetap berdasarkan `dashboard:read` efektif. Dokumen tidak mengasumsikan role PIC memiliki permission tersebut sampai preset PIC dikonfirmasi.
- [x] **AC-7:** Given kegiatan pada periode berjalan, Then dashboard menampilkan status kegiatan secara real-time dengan label "belum diverifikasi", dan kegiatan yang melewati tanggal rencana tetapi belum terlaksana diberi peringatan. *(Q45 §45.3 butir 1)*
- [ ] **AC-8:** Given dashboard dibuka, Then total anggaran per IKU tampil dengan drill-down sampai kegiatan sesuai akses dashboard; kegiatan yang diklaim lebih dari satu IKU ditandai "kegiatan bersama". *(Q42 §42.2 butir 4–5, §42.5 butir 2)*
- [x] **AC-9:** Given periode tanpa target, Then dashboard menampilkannya sebagai "Tanpa target periode", bukan "Belum tercapai", beserta realisasi, target PK, dan selisih terhadap target PK. *(Q47 §47.3)*
- [x] **AC-10:** Given pengukuran berstatus `belum_dapat_diukur`, Then dashboard menampilkan "Belum dapat diukur" dan tidak menghitungnya sebagai capaian 0; bila periodenya juga tanpa target, yang tampil "Belum dapat diukur". *(Q47 §47.3 butir 3, §47.4 butir 3)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Verifikasi query, relasi, indeks, dan eager-loading yang diperlukan; **jangan** membuat mutasi domain baru untuk fitur read-only.

**B. Backend / Domain**
- [ ] Query dashboard harus membedakan Tidak berlaku, Belum Mengisi, Tidak Mengisi, Tidak Dapat Dihitung, Belum dapat diukur, Tanpa target periode, dan nilai 0; bila tanpa target dan belum dapat diukur berlaku bersamaan, yang tampil "Belum dapat diukur" (Q47 §47.3 butir 3); histori resmi membaca versi disahkan.
- [ ] Sajikan status kegiatan per periode dari data kerja secara real-time dengan label "belum diverifikasi" dan peringatan bagi kegiatan yang lewat tanggal rencana tetapi belum terlaksana; angka capaian resmi tetap dari data disahkan (Q45 §45.3).
- [ ] Sajikan total anggaran per IKU dari penjumlahan ISS-06.08 dengan drill-down sampai kegiatan dan penanda "kegiatan bersama"; total per IKU mengikuti akses dashboard, sedangkan anggaran per kegiatan pada drill-down mengikuti akses detail kegiatan unit (Q42 §42.5).
- [ ] Hitung selisih terhadap target PK (realisasi − target PK, dibaca menurut `arah`) untuk periode tanpa target maupun bertarget (Q47 §47.3 butir 1–2).

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `dashboard:read`.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.

**D. Frontend / UX**
- [ ] Buat/rapikan halaman Inertia React untuk **Dashboard Kinerja Eksekutif** sebagai tampilan read-only/filterable sesuai hak server.
- [ ] Tampilkan label "belum diverifikasi", peringatan kegiatan lewat tanggal rencana, total anggaran per IKU dengan drill-down, serta label "Tanpa target periode"/"Belum dapat diukur" dan selisih terhadap target PK sesuai token status `design-system.md`.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given dashboard dibuka, Then filter Renstra, tahun, periode, sasaran, dan unit bekerja sesuai hak akses.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given indikator-periode belum memiliki pengukuran, Then status dihitung dari daftar periode yang diharapkan, bukan dari keberadaan baris pengukuran semata.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given periode sebelum efektivitas indikator, Then dashboard menampilkan Tidak berlaku dan tidak menghitungnya sebagai missing.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given indikator arsip, Then tidak menjadi kewajiban baru.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given hasil resmi ditampilkan, Then angka berasal dari versi disahkan/snapshot yang tepat, bukan master terbaru yang bisa berubah.

- [x] TEST-6 (Q31): Uji user PIC operasional (bukan role) dengan dan tanpa `dashboard:read` efektif; hasil mengikuti resolver, bukan nama role.
- [x] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan pada periode berjalan, Then dashboard menampilkan status kegiatan secara real-time dengan label "belum diverifikasi", dan kegiatan yang melewati tanggal rencana tetapi belum terlaksana diberi peringatan. *(Q45 §45.3 butir 1)*
- [ ] TEST-8: Buat Pest Feature/Unit test yang membuktikan — Given dashboard dibuka, Then total anggaran per IKU tampil dengan drill-down sampai kegiatan sesuai akses dashboard; kegiatan yang diklaim lebih dari satu IKU ditandai "kegiatan bersama". *(Q42 §42.2 butir 4–5, §42.5 butir 2)*
- [x] TEST-9: Buat Pest Feature/Unit test yang membuktikan — Given periode tanpa target, Then dashboard menampilkannya sebagai "Tanpa target periode", bukan "Belum tercapai", beserta realisasi, target PK, dan selisih terhadap target PK. *(Q47 §47.3)*
- [x] TEST-10: Buat Pest Feature/Unit test yang membuktikan — Given pengukuran berstatus `belum_dapat_diukur`, Then dashboard menampilkan "Belum dapat diukur" dan tidak menghitungnya sebagai capaian 0; bila periodenya juga tanpa target, yang tampil "Belum dapat diukur". *(Q47 §47.3 butir 3, §47.4 butir 3)*
- [ ] AKSES ANGGARAN: Uji anggaran per kegiatan pada drill-down tidak tampil bagi pengguna tanpa akses detail kegiatan unit tersebut (Q42 §42.5 butir 1).

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-10.02 · [Feature] Matriks Rekapitulasi Indikator × Periode

> **Q42, Q44, Q45, Q47, Q48, Q51 (13 Oktober 2026):** rekap menampilkan narasi pengukuran bersama daftar kegiatan yang diklaim dan anggarannya (Q45 §45.1 butir 2, Q42 §42.2), Rekomendasi Pimpinan yang berlaku yaitu berstatus disetujui (Q51 §51.1 butir 2), tampilan periode tanpa target dan realisasi belum dapat diukur sesuai Q47 §47.3–§47.4, serta penanda `tidak_dapat_dipenuhi` hanya sebagai histori (Q44 §44.1 butir 2). Dokumen resmi Rencana Aksi tahunan dan Laporan Kinerja Triwulan dibuat melalui ISS-10.05 (Q48). AC-4 sampai AC-6 diperbarui dan AC-7 ditambahkan karena issue ini belum dikerjakan.

> **Keputusan PM (17 Oktober 2026):** rekap juga menampilkan alasan `tidak_dapat_dihitung` dari versi resmi sesuai hak baca, sehingga tampilan laporan pada US-07.06 AC-5 dipenuhi melalui issue ini (tindak lanjut review PR #118, m2). AC-6 dan TEST-6 diperbarui.

**Terkait User Story:** `US-10.02`  
**Prioritas:** 🟡 P1 · Tahap 2 (Q46 §46.3)  
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
- [ ] **AC-4:** Then narasi pengukuran (`progres_kegiatan`, `kendala_masalah`, `strategi_tindak_lanjut`, `tindak_lanjut_rekomendasi`) beserta daftar kegiatan yang diklaim dan anggarannya berasal dari versi resmi yang dinilai, bukan live data yang telah berubah. *(Diperbarui Q45 §45.1 butir 2, Q42 §42.2; semula narasi kegiatan/kendala/tindak lanjut.)*
- [ ] **AC-5:** Then Rekomendasi Pimpinan yang berlaku (berstatus `disetujui`), status capaian, dan status bukti ditampilkan sesuai versi/konteks. *(Diperbarui Q51 §51.1 butir 2)*
- [ ] **AC-6:** Given `tidak_dapat_dihitung`, `belum_dapat_diukur`, atau penanda histori `tidak_dapat_dipenuhi`, Then penanda ditampilkan dan tidak disamakan dengan nilai nol; penanda `tidak_dapat_dihitung` ditampilkan beserta alasan dari versi resmi sesuai hak baca. *(Diperbarui Q44 §44.1 butir 2, Q47 §47.4 butir 3, keputusan PM 17 Oktober 2026)*
- [ ] **AC-7:** Given periode tanpa target, Then rekap menampilkan "Tanpa target periode" beserta realisasi, target PK, dan selisih terhadap target PK tanpa capaian persen; bila realisasinya juga belum dapat diukur, yang tampil "Belum dapat diukur". *(Q47 §47.3)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Verifikasi query, relasi, indeks, dan eager-loading yang diperlukan; **jangan** membuat mutasi domain baru untuk fitur read-only.

**B. Backend / Domain**
- [ ] Matriks laporan mengambil target/narasi/bukti dari konteks versi resmi; jangan live-join data kerja yang bisa berubah.
- [ ] Sajikan narasi pengukuran bersama daftar kegiatan yang diklaim dan anggarannya dari versi resmi (Q45 §45.1 butir 2; penjumlahan ISS-06.08), dengan anggaran per kegiatan mengikuti akses detail kegiatan (Q42 §42.5).
- [ ] Sajikan hanya Rekomendasi Pimpinan berstatus `disetujui` (Q51 §51.1 butir 2); penanda `tidak_dapat_dipenuhi` hanya untuk data histori karena tidak lagi terbentuk (Q44 §44.1 butir 2).
- [ ] Hitung selisih terhadap target PK untuk periode tanpa target tanpa capaian persen; bila realisasi juga belum dapat diukur, tampilkan "Belum dapat diukur" (Q47 §47.3).

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
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Then narasi pengukuran (`progres_kegiatan`, `kendala_masalah`, `strategi_tindak_lanjut`, `tindak_lanjut_rekomendasi`) beserta daftar kegiatan yang diklaim dan anggarannya berasal dari versi resmi yang dinilai, bukan live data yang telah berubah. *(Diperbarui Q45 §45.1 butir 2, Q42 §42.2; semula narasi kegiatan/kendala/tindak lanjut.)*
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Then Rekomendasi Pimpinan yang berlaku (berstatus `disetujui`), status capaian, dan status bukti ditampilkan sesuai versi/konteks. *(Diperbarui Q51 §51.1 butir 2)*
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given `tidak_dapat_dihitung`, `belum_dapat_diukur`, atau penanda histori `tidak_dapat_dipenuhi`, Then penanda ditampilkan dan tidak disamakan dengan nilai nol; penanda `tidak_dapat_dihitung` ditampilkan beserta alasan dari versi resmi sesuai hak baca. *(Diperbarui Q44 §44.1 butir 2, Q47 §47.4 butir 3, keputusan PM 17 Oktober 2026)*
- [ ] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given periode tanpa target, Then rekap menampilkan "Tanpa target periode" beserta realisasi, target PK, dan selisih terhadap target PK tanpa capaian persen; bila realisasinya juga belum dapat diukur, yang tampil "Belum dapat diukur". *(Q47 §47.3)*

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-10.03 · [Feature] Ekspor Laporan Kinerja ke Excel

> **Q47, Q48 (13 Oktober 2026):** keluaran dokumen resmi Rencana Aksi tahunan dan Laporan Kinerja Triwulan dibuat melalui ISS-10.05 dengan permission `laporan:ekspor` yang sama dan sumber data yang sama dengan ekspor ini (Q48 §48.1 butir 6); ekspor rekap pada issue ini tetap berlaku. Penanda "Belum dapat diukur" dan "Tanpa target periode" pada ekspor mengikuti rekap ISS-10.02 dan tidak diubah menjadi 0 (Q47 §47.3–§47.4).

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

> **Q48 (13 Oktober 2026):** contoh keluaran yang disahkan Perencanaan juga menjadi acuan format Rencana Aksi tahunan dan Laporan Kinerja Triwulan (Q48 §48.1 butir 5; ISS-10.05).

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

### ISS-10.05 · [Feature] Keluaran Rencana Aksi Tahunan dan Laporan Kinerja Triwulan serta Unggah PDF Bertanda Tangan

**Terkait User Story:** `US-10.05`  
**Prioritas:** Tahap 2 (Q46 §46.3: sebelum pelaporan TW IV 2026, awal Januari 2027)  
**Story Points:** Belum diestimasi  
**Labels:** `feature`, `reporting`, `audit-sensitive`, `frontend`  
**Plan:** Modul 8: 8.5  
**PRD:** §7.2, §24  
**Workflow:** alur keluaran dokumen resmi Q48 (buat Excel, tanda tangan elektronik di luar aplikasi, unggah PDF)  
**Data Model/Entitas:** `berkas` (induk `jadwal_tahunan` dan `jadwal_periode`; kolom baru `keterangan`), katalog permission (`laporan:unggah`)

#### User Story

> **Sebagai** Tim Perencanaan,  
> **Saya ingin** membuat dokumen Rencana Aksi tahunan dan Laporan Kinerja Triwulan lembaga langsung dari SAKIP lalu mengunggah kembali PDF yang sudah ditandatangani,  
> **Sehingga** dokumen resmi disusun dari data yang disahkan dan arsip bertanda tangan tersimpan di satu tempat.

#### Kontrak Teknis

- **Dependensi:** ISS-05.05 (RA disahkan), ISS-06.02/06.08 (kegiatan dan anggaran), ISS-07.03 (narasi), ISS-09.01 (rekomendasi disetujui), ISS-10.02/10.03 (rekap dan ekspor Excel), ISS-10.04 (contoh keluaran UAT).
- **Otorisasi:** `laporan:ekspor` (membuat dan mengunduh); `laporan:unggah` (baru; mengunggah arsip; preset Perencanaan dan Superadmin). `laporan:unggah` tidak bertanda sensitif karena unggahan bersifat append-only dan tidak mengubah data, tetapi tetap tercatat di audit (Q48 §48.2 butir 2).
- **Dampak Data:** `berkas` dengan induk `jadwal_tahunan` dan `jadwal_periode`; `berkas.keterangan` (kolom baru, Q48 §48.2 butir 6); katalog permission (`laporan:unggah`); `audit_log`.
- **Keputusan terkait:** Q48, Q47 §47.3, Q51, Q29, Q46 §46.3.
- **Rule:** Rincian Output dan realisasi anggaran per Rincian Output disusun di luar SAKIP (Q48 §48.1 butir 3). Dokumen PK tidak dihasilkan SAKIP; lampiran PK tetap lewat halaman PK (Q48 §48.1 butir 4, §48.2 butir 1).
- **Rule:** format mengikuti contoh keluaran yang disahkan Perencanaan (Q29); PDF tetap fase lanjutan (PRD §24.2). Bila pembuatan berkas memerlukan pustaka baru, catat sebagai ADR.
- **Rule:** keterangan berkas (mis. "Rencana Aksi perubahan") disimpan pada kolom baru `berkas.keterangan` (nullable); `alasan_koreksi` tetap khusus untuk berkas pengganti (Q48 §48.2 butir 6).
- **Rule:** bila periode tanpa target dan realisasi belum dapat diukur berlaku bersamaan, keluaran memakai keterangan "Belum dapat diukur" (Q47 §47.3 butir 3).
- **Rule:** arsip yang diunggah hanya berformat PDF dengan ukuran mengikuti batas teknis Q44 (Q48 §48.2 butir 7).

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given jadwal tahunan, When pemegang `laporan:ekspor` membuat Rencana Aksi tahunan, Then berkas Excel memuat sasaran, indikator, satuan, target PK, target per periode seluruh indikator, dan daftar kegiatan per indikator per periode dari RA versi disahkan. *(Q48 §48.1 butir 1)*
- [ ] **AC-2:** Given periode jadwal, When Laporan Kinerja Triwulan dibuat, Then berkas Excel memuat per indikator target PK, target periode, realisasi, capaian atau selisih terhadap target PK, analisis dari narasi pengukuran, dan Rekomendasi Pimpinan yang disetujui; status kegiatan per indikator dimuat sebagai lampiran tambahan. *(Q48 §48.1 butir 1, Q47 §47.3, Q51 §51.1)*
- [ ] **AC-3:** Given indikator yang RA atau pengukurannya belum disahkan, Then indikator tetap tercantum dengan keterangan "belum disahkan" tanpa angka resmi. *(Q48 §48.1 butir 2)*
- [ ] **AC-4:** Given periode tanpa target atau realisasi belum dapat diukur, Then keluaran memakai keterangan yang sama dengan dashboard ("Tanpa target periode", "Belum dapat diukur"). *(Q47 §47.3–§47.4)*
- [ ] **AC-5:** Given pengguna tanpa `laporan:ekspor`, When meminta keluaran atau mengunduh arsip, Then ditolak 403. *(Q48 §48.1 butir 6, §48.2 butir 2)*
- [ ] **AC-6:** Given Perencanaan dengan `laporan:unggah`, When mengunggah PDF bertanda tangan untuk Rencana Aksi tahunan (induk jadwal tahunan) atau Laporan Kinerja Triwulan (induk periode jadwal), Then berkas tersimpan beserta keterangan; satu induk boleh memiliki lebih dari satu berkas. *(Q48 §48.2 butir 1–2)*
- [ ] **AC-7:** Given arsip sudah diunggah, When dihapus, Then ditolak; koreksi dilakukan dengan mengunggah berkas pengganti beserta keterangan. *(Q48 §48.2 butir 3)*
- [ ] **AC-8:** Given arsip diunggah, Then data, status, dan snapshot tidak berubah, dan unggahan tercatat di audit. *(Q48 §48.2 butir 4)*
- [ ] **AC-9:** Given jadwal tahunan berstatus `ditutup`, When Perencanaan mengunggah arsip, Then unggahan diterima tanpa membuka kembali jadwal. *(Q48 §48.2 butir 5)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Daftarkan induk `jadwal_tahunan` dan `jadwal_periode` pada `berkas` (relasi `berkasable`) untuk arsip PDF; arsip bersifat append-only tanpa jalur hapus.
- [ ] Tambahkan kolom `berkas.keterangan` (nullable) untuk keterangan berkas non-pengganti; berkas pengganti tetap memakai `alasan_koreksi` (Q48 §48.2 butir 6). Kolom ini juga dipakai lampiran tambahan PK (ISS-02.11) dan dibuat oleh issue yang lebih dulu dikerjakan.
- [ ] Tambahkan `laporan:unggah` pada katalog permission (`PermissionCatalog`, `PermissionCatalogSeeder`) sebagai permission tidak sensitif, serta pada preset Perencanaan dan Superadmin (`RolePermissionPresets`, `SyncRolePermissionPresets`) dengan audit delta preset (ISS-01.07).
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Buat generator Rencana Aksi tahunan per jadwal tahunan dari RA versi disahkan: sasaran, indikator, satuan, target PK, target per periode seluruh indikator, dan kegiatan per indikator per periode.
- [ ] Buat generator Laporan Kinerja Triwulan per periode dari pengukuran versi disahkan, narasi pengukuran, dan Rekomendasi Pimpinan disetujui, dengan lampiran status kegiatan per indikator.
- [ ] Cantumkan indikator yang RA atau pengukurannya belum disahkan dengan keterangan "belum disahkan" tanpa angka resmi; pakai keterangan "Tanpa target periode" dan "Belum dapat diukur" yang sama dengan dashboard.
- [ ] Gunakan sumber data dan query yang sama dengan rekap/ekspor ISS-10.02/10.03; `composer.json` saat ini belum memuat pustaka Excel sehingga pemilihan pustaka dicatat sebagai ADR.
- [ ] Unggah arsip: terima hanya PDF dengan ukuran mengikuti batas teknis Q44 (ISS-13.06; Q48 §48.2 butir 7), simpan keterangan, tolak penghapusan, koreksi lewat berkas pengganti, terima saat jadwal `ditutup`, dan pastikan data, status, serta snapshot tidak berubah.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side: `laporan:ekspor` untuk membuat dan mengunduh keluaran serta arsip; `laporan:unggah` untuk unggah; request langsung tanpa permission ditolak 403.
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat audit setiap unggahan arsip (aktor, induk, berkas, keterangan) walaupun `laporan:unggah` tidak bertanda sensitif; audit pembuatan keluaran mengikuti ketentuan audit ekspor ISS-10.03.

**D. Frontend / UX**
- [ ] Sediakan aksi "Buat Rencana Aksi tahunan" pada jadwal tahunan dan "Buat Laporan Kinerja Triwulan" pada periode jadwal, beserta daftar arsip PDF, unggah dengan keterangan, unggah pengganti, dan unduh.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit; pastikan state loading/disabled/error, responsif mobile, dan kontras sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given jadwal tahunan, When pemegang `laporan:ekspor` membuat Rencana Aksi tahunan, Then berkas Excel memuat sasaran, indikator, satuan, target PK, target per periode seluruh indikator, dan daftar kegiatan per indikator per periode dari RA versi disahkan. *(Q48 §48.1 butir 1)*
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given periode jadwal, When Laporan Kinerja Triwulan dibuat, Then berkas Excel memuat per indikator target PK, target periode, realisasi, capaian atau selisih terhadap target PK, analisis dari narasi pengukuran, dan Rekomendasi Pimpinan yang disetujui; status kegiatan per indikator dimuat sebagai lampiran tambahan. *(Q48 §48.1 butir 1, Q47 §47.3, Q51 §51.1)*
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given indikator yang RA atau pengukurannya belum disahkan, Then indikator tetap tercantum dengan keterangan "belum disahkan" tanpa angka resmi. *(Q48 §48.1 butir 2)*
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given periode tanpa target atau realisasi belum dapat diukur, Then keluaran memakai keterangan yang sama dengan dashboard ("Tanpa target periode", "Belum dapat diukur"). *(Q47 §47.3–§47.4)*
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given pengguna tanpa `laporan:ekspor`, When meminta keluaran atau mengunduh arsip, Then ditolak 403. *(Q48 §48.1 butir 6, §48.2 butir 2)*
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given Perencanaan dengan `laporan:unggah`, When mengunggah PDF bertanda tangan untuk Rencana Aksi tahunan (induk jadwal tahunan) atau Laporan Kinerja Triwulan (induk periode jadwal), Then berkas tersimpan beserta keterangan; satu induk boleh memiliki lebih dari satu berkas. *(Q48 §48.2 butir 1–2)*
- [ ] TEST-7: Buat Pest Feature/Unit test yang membuktikan — Given arsip sudah diunggah, When dihapus, Then ditolak; koreksi dilakukan dengan mengunggah berkas pengganti beserta keterangan. *(Q48 §48.2 butir 3)*
- [ ] TEST-8: Buat Pest Feature/Unit test yang membuktikan — Given arsip diunggah, Then data, status, dan snapshot tidak berubah, dan unggahan tercatat di audit. *(Q48 §48.2 butir 4)*
- [ ] TEST-9: Buat Pest Feature/Unit test yang membuktikan — Given jadwal tahunan berstatus `ditutup`, When Perencanaan mengunggah arsip, Then unggahan diterima tanpa membuka kembali jadwal. *(Q48 §48.2 butir 5)*
- [ ] FORMAT ARSIP: Uji arsip selain PDF atau melebihi batas teknis unggahan ditolak (Q48 §48.2 butir 7, Q44 §44.2).
- [ ] PERMISSION: Uji `laporan:unggah` terdaftar tidak sensitif pada katalog dan preset Perencanaan serta Superadmin, sedangkan setiap unggahan tetap tercatat di audit (Q48 §48.2 butir 2).
- [ ] TAMPILAN BERSAMAAN: Uji periode tanpa target yang realisasinya `belum_dapat_diukur` tercantum sebagai "Belum dapat diukur" pada keluaran (Q47 §47.3 butir 3).

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Pembuatan keluaran dan unggahan arsip tercatat di audit sesuai kontrak.


---

## Bagian 11 — Bukti Dukung Multi-Mode & Integritas Lampiran

### ISS-11.01 · [Feature] Konfigurasi Persyaratan Jenis Berkas

> **Q42, Q43, Q44 (13 Oktober 2026):** `jenis_berkas.tahap` memperoleh nilai baru `kegiatan_perencanaan`, penanda `hanya_kegiatan_beranggaran`, dan contoh berkas melalui induk `berkas` baru `jenis_berkas` (Q42 §42.3–§42.4; ISS-06.08). `ukuran_maks_kb` dibatasi batas atas 100 MB (102400 KB), format atau ukuran yang dikosongkan memakai default konfigurasi, mode "Ubah batas teknis" dihapus, dan menu Persyaratan Berkas dipindah ke grup Perencanaan Kinerja (Q44 §44.1–§44.3; ISS-13.06). Alasan perubahan persyaratan menjadi opsional (Q43 §43.3 butir 3; ISS-13.05).

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

> **Q44 (13 Oktober 2026):** unggahan file selalu aktif tanpa saklar; batas ukuran/format mengikuti persyaratan, dan bila tidak ada persyaratan atau persyaratan mengosongkan format/ukuran, server memakai default konfigurasi 10 MB dan `pdf,docx,xlsx,jpg,jpeg,png` dengan batas atas 100 MB per berkas (Q44 §44.2 butir 1). Penyimpanan tetap privat dan unduhan melalui route berizin (Q44 §44.1 butir 4). Pengalihan pembacaan default ke konfigurasi telah dikerjakan pada ISS-13.06/PR #150.

> **Status implementasi - issue #132 (2026-10-20, diperbarui setelah merge ISS-13.06):** AC-1..5 dan TEST-1..5 terbukti pada PR #151 setelah merge ISS-13.06, bukan penerimaan UAT/deployment. Test: `BuktiDukungPengukuranTest::test_metadata_berkas_privat_nama_asli_mime_ukuran_dan_path_aman`, `test_path_privat_tidak_dapat_diakses_sebagai_url_publik`; `RegulasiFeatureTest` (Pest) `download lampiran regulasi ditolak tanpa regulasi:read`; `RencanaAksiBuktiHttpTest::test_tautan_non_http_dan_file_di_luar_batas_persyaratan_ditolak`; `VerticalSlice1Test::test_private_evidence_requires_authorized_parent`; `BerkasPerencanaanKegiatanTest::test_mode_tautan_dan_teks_kegiatan_tersimpan_dan_audit_metadata` (AC-5); `RegulasiFeatureTest` (Pest) `lampiran regulasi mode tautan menyimpan nilai dan audit hanya metadata` (AC-5). **Dependency "unggahan selalu aktif" telah diselesaikan oleh PR #150 (ISS-13.06, `d02bab7`)**: kode tidak lagi membaca `berkas.unggahan_aktif` dan halaman Kebijakan Storage telah dihapus. ISS-11.02 dapat ditutup sebagai traceability setelah PR ini merge. AC-4 untuk Regulasi memakai `regulasi:read` sesuai Workflow §3.1; DoD Plan 13.5 diselaraskan sebagai pengecualian.

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

- **Dependensi:** capability induk valid; unggahan selalu aktif (Q44 §44.1 butir 2; semula `berkas.unggahan_aktif = true`).
- **Otorisasi:** Capability berkas mengikuti induk; `berkas:read`/deny tetap dievaluasi sesuai kontrak akses.
- **Dampak Data:** `berkas`, private filesystem, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given file valid, When upload, Then file disimpan pada disk privat dan metadata nama asli, MIME, ukuran, path aman disimpan.
- [x] **AC-2:** Given ukuran/format melanggar batas persyaratan/fallback, When upload, Then ditolak sebelum file final tersimpan.
- [x] **AC-3:** Given path fisik ditebak tanpa autentikasi, When diakses, Then tidak tersedia sebagai public URL.
- [x] **AC-4:** Given pengguna meminta download, Then server mengevaluasi hak baca induk dan deny sebelum mengalirkan response.
- [x] **AC-5:** Given token/credential/storage path sensitif dicatat audit, Then hanya metadata yang diperlukan yang boleh tampil; secret tidak boleh bocor.

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

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given file valid, When upload, Then file disimpan pada disk privat dan metadata nama asli, MIME, ukuran, path aman disimpan.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given ukuran/format melanggar batas persyaratan/fallback, When upload, Then ditolak sebelum file final tersimpan.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given path fisik ditebak tanpa autentikasi, When diakses, Then tidak tersedia sebagai public URL.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given pengguna meminta download, Then server mengevaluasi hak baca induk dan deny sebelum mengalirkan response.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given token/credential/storage path sensitif dicatat audit, Then hanya metadata yang diperlukan yang boleh tampil; secret tidak boleh bocor.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-11.03 · [Feature] Bukti Mode Tautan atau Teks

> **Status implementasi - issue #132 (2026-10-20):** AC-1..4 dan TEST-1..4 terbukti pada PR #151, bukan penerimaan UAT/deployment. Test: `BuktiDukungPengukuranTest::test_mode_tautan_dan_teks_pengukuran_tersimpan_tanpa_file`, `test_mode_di_luar_izinkan_persyaratan_ditolak_pengukuran`; `BerkasPerencanaanKegiatanTest::test_mode_tautan_dan_teks_kegiatan_tersimpan_dan_audit_metadata`; `RegulasiFeatureTest` (Pest) `lampiran regulasi mode tautan menyimpan nilai dan audit hanya metadata`.

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

- [x] **AC-1:** Given mode tautan dipilih, When URL `http/https` valid disimpan, Then URL tersimpan tanpa memakai kuota disk file.
- [x] **AC-2:** Given mode teks dipilih, When isi valid disimpan, Then teks tersimpan pada kolom yang tepat.
- [x] **AC-3:** Given mode tidak diizinkan oleh jenis persyaratan, Then ditolak.
- [x] **AC-4:** Given audit untuk teks, Then audit tidak harus menyalin isi sensitif penuh; cukup metadata yang diperlukan sesuai kebijakan.

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

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given mode tautan dipilih, When URL `http/https` valid disimpan, Then URL tersimpan tanpa memakai kuota disk file.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given mode teks dipilih, When isi valid disimpan, Then teks tersimpan pada kolom yang tepat.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given mode tidak diizinkan oleh jenis persyaratan, Then ditolak.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given audit untuk teks, Then audit tidak harus menyalin isi sensitif penuh; cukup metadata yang diperlukan sesuai kebijakan.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).

### ISS-11.04 · [Feature] Imutabilitas Berkas Berdasarkan Induk

> **Q40, Q42, Q48 (13 Oktober 2026):** induk `berkas` bertambah: `jenis_berkas` untuk contoh berkas persyaratan (bukan bukti; dapat diganti/dihapus beralasan, ISS-06.08), `jadwal_tahunan` dan `jadwal_periode` untuk arsip PDF Rencana Aksi tahunan dan Laporan Kinerja Triwulan yang tidak dapat dihapus dan dikoreksi lewat berkas pengganti (ISS-10.05, Q48 §48.2 butir 3), serta lampiran per versi PK setelah revisi berversi tersedia (ISS-02.12). RAB dan berkas tahap perencanaan kegiatan tidak dapat dihapus sejak RA yang mengklaim kegiatannya diajukan (Q42 §42.4 butir 5).

> **Status implementasi - issue #132 (2026-10-20):** AC-1..6 dan TEST-1..6 terbukti dengan catatan Q36 §36.2: bukti Pengukuran tidak memiliki endpoint hapus; koreksi lewat pengembalian beralasan dan versi baru. Soft delete `dihapus_pada`/`dihapus_oleh` terbukti untuk RA, Kegiatan, Renstra, PK, dan Regulasi. Test baru: `BuktiDukungPengukuranTest::test_delete_bukti_pengukuran_setelah_diajukan_tidak_tersedia_dan_tidak_mengubah_data`; `BerkasPerencanaanKegiatanTest::test_berkas_kegiatan_terlaksana_tidak_dapat_dihapus_atau_diganti`. Test existing: `RencanaAksiBuktiHttpTest::test_status_di_luar_draf_ditolak_sebagai_validasi_bisnis_bukan_izin` (AC-1 RA) dan `test_hapus_soft_delete_beralasan_teraudit_dan_mempertahankan_file` (AC-6 RA); `RenstraFeatureTest` "Imutabilitas lampiran: percobaan menghapus lampiran pada Renstra aktif ditolak" (AC-3) dan "Penghapusan lampiran pada Renstra draft diizinkan dan tercatat di audit log" (AC-6); `DeleteBerkasPerjanjianKinerjaTest::test_delete_berkas_rejected_when_jadwal_tahunan_active` (AC-4) dan `test_delete_berkas_succeeds_when_jadwal_not_active` (AC-6 PK); `RegulasiFeatureTest` "hapus lampiran ditolak saat regulasi dirujuk data aktif" (AC-5) dan "hapus lampiran menggunakan permission berkas dan mencatat audit" (AC-6 Regulasi). Induk `jadwal_tahunan`/`jadwal_periode` (Q48) dan `renstra_pk_versi` (Q40) di luar AC ini (ISS-10.05/ISS-02.12).

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
- **Otorisasi:** `berkas:delete` (sensitif) untuk induk dokumen dasar (`renstra`/`renstra_pk`/`regulasi`); untuk `rencana_aksi`/`pengukuran`/`kegiatan` hak hapus turun dari izin mutasi induk ber-scope unit, dengan `berkas:delete` sebagai gerbang deny (Q32.3); + guard status induk (Q36).
- **Dampak Data:** `berkas`, `audit_log`.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given berkas RA/Pengukuran telah dirujuk versi resmi/disahkan, When delete dicoba, Then ditolak.
- [x] **AC-2:** Given kegiatan telah terlaksana dan bukti menjadi dasar gerbang, When delete dicoba, Then ditolak; koreksi append-only.
- [x] **AC-3:** Given Renstra aktif, When lampiran resmi Renstra dihapus, Then ditolak.
- [x] **AC-4:** Given PK sudah menjadi dasar jadwal aktif, When lampiran PK dihapus, Then ditolak.
- [x] **AC-5:** Given regulasi masih dirujuk Renstra/Indikator aktif, When lampiran regulasi dihapus, Then ditolak.
- [x] **AC-6:** Given batas imutabilitas belum tercapai dan aktor berwenang, Then soft delete dapat dilakukan dengan `dihapus_pada/dihapus_oleh` serta audit.

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Implementasikan/validasi persistence untuk dampak data: `berkas`, `audit_log`.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Implementasikan guard imutabilitas per enam induk; penghapusan sebelum batas memakai soft delete, sesudah batas ditolak.

**C. Authorization & Audit**
- [ ] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `berkas:delete` (sensitif) untuk induk dokumen dasar (`renstra`/`renstra_pk`/`regulasi`); untuk `rencana_aksi`/`pengukuran`/`kegiatan` hak hapus turun dari izin mutasi induk ber-scope unit, dengan `berkas:delete` sebagai gerbang deny (Q32.3); + guard status induk (Q36).
- [ ] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [ ] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [ ] Buat/rapikan page dan reusable component React untuk **Imutabilitas Berkas Berdasarkan Induk**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [ ] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given berkas RA/Pengukuran telah dirujuk versi resmi/disahkan, When delete dicoba, Then ditolak.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given kegiatan telah terlaksana dan bukti menjadi dasar gerbang, When delete dicoba, Then ditolak; koreksi append-only.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given Renstra aktif, When lampiran resmi Renstra dihapus, Then ditolak.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given PK sudah menjadi dasar jadwal aktif, When lampiran PK dihapus, Then ditolak.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given regulasi masih dirujuk Renstra/Indikator aktif, When lampiran regulasi dihapus, Then ditolak.
- [x] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given batas imutabilitas belum tercapai dan aktor berwenang, Then soft delete dapat dilakukan dengan `dihapus_pada/dihapus_oleh` serta audit.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [ ] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-11.05 · [Feature] Pembekuan Persyaratan Bukti Saat Pengajuan

> **Q42, Q44 (13 Oktober 2026):** pengecualian mode file karena unggahan dimatikan tidak lagi terjadi; versi lama yang membekukan pengecualian tersebut tetap menjadi histori (Q44 §44.1 butir 2). Persyaratan tahap `kegiatan_perencanaan` yang diperiksa saat RA diajukan mengikuti aturan pembekuan yang sama (Q24; ISS-06.08), sedangkan contoh berkas tidak dibekukan (Q42 §42.3 butir 4).

> **Status implementasi - issue #132 (2026-10-20):** AC-1..4 dan TEST-1..4 terbukti pada PR #151, bukan penerimaan UAT/deployment. Test baru: `PembekuanPersyaratanBuktiTest::test_perubahan_master_jenis_berkas_setelah_submit_tidak_mengubah_versi`, `test_ketentuan_baru_setelah_submit_dikembalikan_beralasan_dan_diajukan_ulang_sebagai_versi_baru`, `test_pengesahan_lama_tetap_sah_setelah_persyaratan_berubah`; test existing: `RencanaAksiAjukanTest::test_bukti_wajib_tetap_diperiksa_meski_setting_unggahan_lama_nonaktif`, `test_ajukan_ulang_dari_dikembalikan_membuat_nomor_2`.

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

- [x] **AC-1:** Given submit terjadi, Then versi menyimpan jenis/mode/wajib persyaratan serta bukti/pengecualian yang dipakai.
- [x] **AC-2:** Given master `jenis_berkas` berubah setelah submit, Then versi yang sedang direviu tidak ikut berubah.
- [x] **AC-3:** Given ketentuan baru harus diterapkan pada proses berjalan, Then proses harus dikembalikan beralasan dan diajukan ulang sebagai versi baru.
- [x] **AC-4:** Given hasil sudah disahkan, Then perubahan persyaratan baru tidak otomatis membatalkan hasil lama.

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

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given submit terjadi, Then versi menyimpan jenis/mode/wajib persyaratan serta bukti/pengecualian yang dipakai.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given master `jenis_berkas` berubah setelah submit, Then versi yang sedang direviu tidak ikut berubah.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given ketentuan baru harus diterapkan pada proses berjalan, Then proses harus dikembalikan beralasan dan diajukan ulang sebagai versi baru.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given hasil sudah disahkan, Then perubahan persyaratan baru tidak otomatis membatalkan hasil lama.

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.

### ISS-11.06 · [Feature] Pengecualian Mode File Saat Unggah Dinonaktifkan *(dibatalkan Q44)*

> **Dibatalkan Q44 (13 Oktober 2026):** saklar global unggahan dihapus sehingga unggahan file selalu aktif dan pengecualian mode file `tidak_dapat_dipenuhi` akibat unggahan dimatikan tidak lagi terjadi; penanda yang sudah tercatat tetap menjadi histori (Q44 §44.1 butir 2 dan 5, menggantikan Q23). Issue ini tidak dikerjakan; isi di bawah dipertahankan sebagai histori. Penghapusan cabang anti-macet unggahan dan batas teknis server dikerjakan pada ISS-13.06.

**Terkait User Story:** `US-11.06`  
**Prioritas:** — (dibatalkan Q44)  
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

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given `berkas.unggahan_aktif = false`, When persyaratan membutuhkan file, Then kewajiban mode file dapat ditandai `tidak_dapat_dipenuhi` sesuai kontrak dan audit. *(Histori; dibatalkan Q44.)*
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

> **Q41, Q44 (13 Oktober 2026):** banner tenggat penyusunan RA pada AC-1 dihapus karena Rencana Aksi tidak lagi memakai jendela penyusunan (Q41 §41.1). Penanda `tidak_dapat_dipenuhi` baru tidak lagi terjadi karena unggahan selalu aktif; penanda lama tetap histori (Q44 §44.1 butir 2). AC-1 dan AC-4 diperbarui karena issue ini belum dikerjakan.

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

- [ ] **AC-1:** *(Dihapus Q41 §41.1; tidak dikerjakan.)* Semula: Given jendela RA aktif/mendekati batas, When halaman dibuka, Then banner menampilkan `rencana_aksi_selesai` sesuai konteks.
- [ ] **AC-2:** Given periode pengisian aktif, Then countdown mengacu `pengisian_selesai` resmi.
- [ ] **AC-3:** Given indikator belum diisi, Then status muncul hanya untuk periode yang berlaku.
- [ ] **AC-4:** Given PK belum lengkap, Then Perencanaan mendapat penanda yang relevan. *(Diperbarui Q44 §44.1 butir 2; semula juga untuk bukti `tidak_dapat_dipenuhi`, yang tidak lagi terbentuk.)*
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

- [ ] TEST-1: *(Dihapus Q41 §41.1; tidak dikerjakan.)* Semula: Buat Pest Feature/Unit test yang membuktikan — Given jendela RA aktif/mendekati batas, When halaman dibuka, Then banner menampilkan `rencana_aksi_selesai` sesuai konteks.
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given periode pengisian aktif, Then countdown mengacu `pengisian_selesai` resmi.
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given indikator belum diisi, Then status muncul hanya untuk periode yang berlaku.
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given PK belum lengkap, Then Perencanaan mendapat penanda yang relevan. *(Diperbarui Q44 §44.1 butir 2; semula juga untuk bukti `tidak_dapat_dipenuhi`, yang tidak lagi terbentuk.)*
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

> **Q43 (13 Oktober 2026):** alasan perubahan setelan aplikasi menjadi opsional, sedangkan audit nilai lama/baru tetap dicatat (Q43 §43.1, §43.3 butir 3). `UpdatePengaturanRequest` saat ini masih mewajibkan alasan; perubahannya dikerjakan pada ISS-13.05.

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

> **Q44 (13 Oktober 2026):** fitur ini dihapus dan digantikan ISS-13.06: menu Kebijakan Storage, halaman Batas Unggahan Berkas, saklar unggahan, dan panel penggunaan penyimpanan dihapus; batas teknis dipindah ke konfigurasi server (default 10 MB + `pdf,docx,xlsx,jpg,jpeg,png`, batas atas 100 MB) dan kunci grup `berkas` pada `pengaturan` menjadi histori (Q44 §44.1–§44.2, menggantikan Q23). AC dan test di bawah dipertahankan sebagai histori implementasi.

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
**Prioritas:** 🔴 P0 · Tahap 3 (Q46 §46.3)  
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

- [x] **AC-1:** Given filter waktu/aktor/tindakan/objek, When dicari, Then hasil audit yang cocok ditampilkan.
- [x] **AC-2:** Given aksi permission sensitif, Then detail audit memuat `dasar_izin` sumber allow atau deny pemicu.
- [x] **AC-3:** Given aksi ditolak karena gate/deny/F1, Then percobaan penting dapat dicatat dengan alasan penolakan. *(Pencatatan dilakukan modul masing-masing; halaman ini menampilkan percobaan bertindakan berakhiran `ditolak` beserta alasannya.)*
- [x] **AC-4:** Given audit telah ditulis, Then tidak tersedia endpoint update/delete.
- [x] **AC-5:** Given payload audit memuat data sensitif, Then token, secret, password, atau credential tidak boleh disimpan.

> **Status implementasi — ISS-13.03 (#87):** centang menunjukkan bukti implementasi dan test:
> - `tests/Feature/Audit/AuditTrailTest` (AC-1 termasuk batas tanggal Asia/Makassar; AC-2 dan AC-3 dari penetapan peran yang diizinkan dan yang ditolak deny; AC-4 405 dan model append-only; AC-5; 403 tanpa `audit:read` atau terkena deny; paginasi server dan index pencarian);
> - `tests/Feature/Authorization/InertiaCapabilitiesTest` (`can.audit`);
> - `tests/Frontend/AuditTrail.test.tsx` dan `tests/Frontend/GroupedSidebarNavigation.test.tsx`.
>
> AC-5 sisi penyimpanan dijaga dua lapis: allowlist payload di setiap modul penulis (test memeriksa payload modul yang dijalankan dalam test), dan sejak Q58 (Issue #122) `WriteAuditLog` menyamarkan nilai di bawah kunci rahasia sebelum disimpan (`AuditTrailTest::test_penulisan_audit_menyamarkan_kunci_rahasia_pada_nilai_tersimpan`, `test_penulisan_audit_menyimpan_utuh_kunci_yang_hanya_mirip`). Halaman detail tetap menyamarkan kunci yang menyerupai rahasia di server sebelum menjadi props, termasuk baris yang tersimpan sebelum Q58. Membaca audit tidak diaudit karena PRD §25.1 tidak mencantumkannya. Responsivitas mobile belum diuji di browser dan menunggu smoke SSO.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Verifikasi query, relasi, indeks, dan eager-loading yang diperlukan; **jangan** membuat mutasi domain baru untuk fitur read-only.

**B. Backend / Domain**
- [x] Audit log bersifat append-only; tidak ada update/delete endpoint. Mask secret/credential dan simpan `dasar_izin` untuk aksi sensitif.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `audit:read`.
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat event audit yang ditentukan kontrak dengan aktor, objek, waktu, dan nilai lama/baru bila relevan. *(Tidak ada event baru: fitur ini hanya membaca.)*

**D. Frontend / UX**
- [x] Buat/rapikan halaman Inertia React untuk **Penelusuran Audit Trail Append-Only** sebagai tampilan read-only/filterable sesuai hak server.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given filter waktu/aktor/tindakan/objek, When dicari, Then hasil audit yang cocok ditampilkan.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given aksi permission sensitif, Then detail audit memuat `dasar_izin` sumber allow atau deny pemicu.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given aksi ditolak karena gate/deny/F1, Then percobaan penting dapat dicatat dengan alasan penolakan.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given audit telah ditulis, Then tidak tersedia endpoint update/delete.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given payload audit memuat data sensitif, Then token, secret, password, atau credential tidak boleh disimpan.

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas). *(Responsivitas menunggu smoke SSO.)*

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

### ISS-13.05 · [Feature] Penyederhanaan Kewajiban Alasan

**Terkait User Story:** `US-13.05`  
**Prioritas:** Tahap 1 (Q46 §46.3: sebelum integrasi 9 November 2026)  
**Story Points:** Belum diestimasi  
**Labels:** `feature`, `settings-audit`, `audit-sensitive`, `frontend`  
**Plan:** Modul 10: 10.7  
**PRD:** §4 prinsip 6, §7.5, §13, §25.1–25.2  
**Workflow:** §21–22  
**Data Model/Entitas:** `user_permission_granted.alasan`, `penanggung_jawab.alasan`, `audit_log`

#### User Story

> **Sebagai** pengguna yang mengelola data dan akses,  
> **Saya ingin** hanya diminta alasan pada aksi yang alasannya memberi informasi tambahan,  
> **Sehingga** pekerjaan rutin tidak terhambat sementara jejak audit tetap lengkap.

#### Kontrak Teknis

- **Dependensi:** fitur terdampak sudah ada: Grant Unit (ISS-01.04), PJ (ISS-04.01), persyaratan berkas (ISS-11.01), pindah unit indikator (ISS-02.05), aktivasi jadwal (ISS-03.02), setelan (ISS-13.01), PK (ISS-02.08).
- **Otorisasi:** tidak berubah.
- **Dampak Data:** `user_permission_granted.alasan` (tetap NOT NULL; alasan baku server), `penanggung_jawab.alasan` (nullable; field form dihapus), `audit_log`.
- **Keputusan terkait:** Q43, Q37 §37.2, Q14.
- **Rule:** kewajiban alasan dari keputusan sebelumnya yang tidak disebut Q43 §43.3 tetap berlaku. Perubahan target PK sudah opsional (Q43 §43.3 butir 2).

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given Grant Unit diberikan tanpa alasan, When disimpan, Then tersimpan dengan alasan baku dari server; bila pengguna mengisi alasan, alasan itu yang disimpan. *(Q43 §43.3 butir 1)*
- [x] **AC-2:** Given form penetapan atau pergantian Penanggung Jawab, Then field alasan tidak ditampilkan dan request tanpa alasan diterima. *(Q43 §43.3 butir 4)*
- [x] **AC-3:** Given perubahan persyaratan berkas, pindah unit indikator, aktivasi jadwal, perubahan setelan aplikasi, atau perubahan PK sebelum PK dipakai jadwal aktif dikirim tanpa alasan, Then diterima; UI menampilkan "Alasan (opsional)". *(Q43 §43.3 butir 3)*
- [x] **AC-4:** Given aksi yang tercantum pada Q43 §43.2 dikirim tanpa alasan, Then tetap ditolak. *(Q43 §43.2)*
- [x] **AC-5:** Given aksi apa pun, Then audit tetap mencatat aktor, waktu, tindakan, nilai lama dan baru, serta `dasar_izin` untuk permission sensitif. *(Q43 §43.1)*

> **Status verifikasi AC-4 (22 Oktober 2026):** dipetakan ke test per endpoint Q43 §43.2 yang sudah ada — `ChangeUserStatusTest`, `ExplicitDenyTest`, `JadwalPenutupanTest`, `JadwalRevisiJendelaTest`, `BukaKembaliRencanaAksiTest`, `RencanaAksiBuktiHttpTest`, `SasaranIndikatorTest`, `RenstraFeatureTest`, `JenisBerkasContohTest` — dan CI 9/9 pada exact HEAD `1fdb7e2` (Backend 2183 passed; Frontend 79 file/844 test). Tidak ada test tabel-driven tunggal; pemetaan ini yang menjadi bukti.

#### Implementation Tasks

Perubahan per request (tanpa nomor baris kode):

| Aksi | Request / Action | Perubahan | Keputusan |
|---|---|---|---|
| Pemberian grant unit | `StoreUnitGrantRequest` + `CreateUnitGrantAction` | `alasan` opsional; bila kosong server mengisi alasan baku (pola helper `AlasanAudit`) sehingga kolom tetap `NOT NULL`; pencabutan (`RevokeUnitGrantRequest`) tetap wajib alasan | Q43 §43.3 butir 1, §43.2 butir 4 |
| Penetapan/pergantian PJ | `AssignmentRequest` + `AssignPenanggungJawab`/`ChangePenanggungJawab` + form PJ | field alasan dihapus dari form; request tanpa alasan diterima | Q43 §43.3 butir 4 |
| Perubahan persyaratan berkas | `StoreJenisBerkasRequest`/`UpdateJenisBerkasRequest` | `alasan` opsional; penghapusan persyaratan tetap wajib alasan | Q43 §43.3 butir 3, §43.2 butir 2 |
| Pindah unit indikator | `PindahUnitIndikatorRequest` | `alasan` opsional | Q43 §43.3 butir 3 |
| Aktivasi jadwal | `ActivateJadwalRequest` | `alasan` opsional | Q43 §43.3 butir 3 |
| Perubahan setelan aplikasi | `UpdatePengaturanRequest` | `alasan` opsional | Q43 §43.3 butir 3 |
| Perubahan PK | `UpdatePerjanjianKinerjaRequest` | `alasan` opsional sebelum PK dipakai jadwal aktif; koreksi sesudahnya tetap wajib alasan (ISS-02.09) | Q43 §43.3 butir 3, §43.2 butir 3 |

**A. Persistence / Data Model**
- [x] Tidak ada perubahan skema: `user_permission_granted.alasan` tetap `NOT NULL` dan `penanggung_jawab.alasan` sudah nullable; audit tetap menyimpan nilai lama/baru.

**B. Backend / Domain**
- [x] Terapkan perubahan pada tabel di atas; alasan opsional yang diisi tetap disanitasi helper canonical (`AuditReason`/`AlasanAudit`).
- [x] Pastikan aksi Q43 §43.2 (pengembalian dan buka-kembali; penghapusan; koreksi/revisi data sah; perubahan akses; justifikasi kegiatan, deviasi PK, dan target PK di bawah target Renstra) tetap menolak request tanpa alasan.

**C. Authorization & Audit**
- [x] Otorisasi tidak berubah; audit tetap mencatat aktor, waktu, tindakan, nilai lama/baru, serta `dasar_izin` untuk permission sensitif (Q37 §37.2).

**D. Frontend / UX**
- [x] Ubah label isian menjadi "Alasan (opsional)" pada form terkait dan hapus field alasan dari form Penanggung Jawab; pola **Modal Alasan Audit** wajib hanya untuk aksi Q43 §43.2.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit; pastikan state loading/disabled/error, responsif mobile, dan kontras sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given Grant Unit diberikan tanpa alasan, When disimpan, Then tersimpan dengan alasan baku dari server; bila pengguna mengisi alasan, alasan itu yang disimpan. *(Q43 §43.3 butir 1)*
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given form penetapan atau pergantian Penanggung Jawab, Then field alasan tidak ditampilkan dan request tanpa alasan diterima. *(Q43 §43.3 butir 4)*
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given perubahan persyaratan berkas, pindah unit indikator, aktivasi jadwal, perubahan setelan aplikasi, atau perubahan PK sebelum PK dipakai jadwal aktif dikirim tanpa alasan, Then diterima; UI menampilkan "Alasan (opsional)". *(Q43 §43.3 butir 3)*
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given aksi yang tercantum pada Q43 §43.2 dikirim tanpa alasan, Then tetap ditolak. *(Q43 §43.2)*
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given aksi apa pun, Then audit tetap mencatat aktor, waktu, tindakan, nilai lama dan baru, serta `dasar_izin` untuk permission sensitif. *(Q43 §43.1)*

> **Status verifikasi TEST-4 (22 Oktober 2026):** bukti mengikuti pemetaan AC-4 di atas (test per endpoint §43.2 pada CI 9/9 exact HEAD `1fdb7e2`). Tidak ada test tabel-driven tunggal untuk seluruh request §43.2.

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

> **Batas verifikasi PR:** AC-4/TEST-4 dipetakan ke test per endpoint dan CI 9/9 pada `1fdb7e2`. QA SSO/browser smoke dan UAT belum dilakukan; DoD design-system tetap terbuka sampai QA tersebut.

### ISS-13.06 · [Feature] Penghapusan Kebijakan Storage, Batas Teknis Unggahan di Konfigurasi, dan Penataan Menu Referensi

**Terkait User Story:** `US-13.06`  
**Prioritas:** Tahap 1 (Q46 §46.3: sebelum integrasi 9 November 2026)  
**Story Points:** Belum diestimasi  
**Labels:** `feature`, `settings-audit`, `evidence`, `frontend`  
**Plan:** Modul 9: 9.10  
**PRD:** §18.5–18.9, §26.3–26.4  
**Workflow:** §10, §18  
**Data Model/Entitas:** `pengaturan` (kunci grup `berkas`), `jenis_berkas.ukuran_maks_kb`, konfigurasi aplikasi

#### User Story

> **Sebagai** Tim Perencanaan dan Admin,  
> **Saya ingin** batas unggahan ditetapkan per persyaratan berkas dengan batas teknis dari server, tanpa halaman kebijakan storage terpisah, dan menu referensi yang tertata,  
> **Sehingga** pengaturan berkas tidak berulang di dua tempat dan unggahan tidak pernah dimatikan secara tidak sengaja.

#### Kontrak Teknis

- **Dependensi:** ISS-13.02 (Kebijakan Storage, sudah ada), ISS-11.01 (persyaratan berkas); ISS-11.06 dibatalkan.
- **Otorisasi:** tidak berubah; menu tetap mengikuti `can.*`.
- **Dampak Data:** kunci grup `berkas` pada `pengaturan` (tidak lagi dibaca atau dikelola UI; histori), `jenis_berkas.ukuran_maks_kb` ≤ 102400, konfigurasi aplikasi (default 10 MB dan format default; batas atas 100 MB).
- **Keputusan terkait:** Q44 (menggantikan Q23).
- **Rule:** perubahan batas teknis lewat konfigurasi deployment dan dicatat pada catatan rilis (Q44 §44.2 butir 3).
- **Rule:** cabang anti-macet unggahan pada gerbang lampiran PK, pengajuan RA, pengajuan pengukuran, dan penambahan bukti dihapus; pembacaan nilai default yang tersebar di kode dialihkan ke konfigurasi, bukan dihapus. Tampilan daftar berkas terunggah masuk backlog tahap 3.
- **Di luar cakupan:** perubahan permission.

#### Acceptance Criteria (QA/UAT)

- [ ] **AC-1:** Given pengguna mana pun, Then menu Kebijakan Storage dan halaman Batas Unggahan Berkas tidak tersedia, dan route lamanya tidak lagi melayani halaman tersebut. *(Q44 §44.1 butir 1)*
- [ ] **AC-2:** Given unggahan file, Then selalu aktif tanpa saklar; penanda `tidak_dapat_dipenuhi` akibat unggahan dimatikan tidak lagi dibuat, sedangkan penanda lama tetap tampil sebagai histori. *(Q44 §44.1 butir 2)*
- [ ] **AC-3:** Given lampiran tanpa persyaratan (Renstra, PK, Dasar Aturan, lampiran bebas kegiatan) atau persyaratan yang mengosongkan format atau ukuran, Then server menerapkan default konfigurasi 10 MB dan `pdf,docx,xlsx,jpg,jpeg,png`. *(Q44 §44.2 butir 1)*
- [ ] **AC-4:** Given persyaratan disimpan dengan `ukuran_maks_kb` di atas 102400, Then ditolak; Given unggahan melebihi batas atas, Then ditolak. *(Q44 §44.2 butir 1)*
- [ ] **AC-5:** Given halaman Persyaratan Berkas, Then mode "Ubah batas teknis" dan peringatan unggahan dimatikan dihapus; format dan ukuran diubah Perencanaan lewat form persyaratan. *(Q44 §44.1 butir 1–2)*
- [ ] **AC-6:** Given panel penggunaan penyimpanan, Then panel dihapus. *(Q44 §44.1 butir 3)*
- [ ] **AC-7:** Given sidebar, Then Dasar Aturan berada di grup Master Data & Referensi dan Persyaratan Berkas di grup Perencanaan Kinerja; permission tidak berubah. *(Q44 §44.3)*
- [ ] **AC-8:** Given berkas tersimpan, Then penyimpanan tetap privat dan unduhan tetap melalui route berizin. *(Q44 §44.1 butir 4)*

#### Implementation Tasks

**A. Persistence / Data Model**
- [ ] Jangan menghapus kunci grup `berkas` pada `pengaturan`; kunci tersebut tidak lagi dibaca atau dikelola UI dan menjadi histori (`StoragePolicySeeder` tidak lagi dipakai untuk nilai efektif).
- [ ] Tegakkan `jenis_berkas.ukuran_maks_kb` ≤ 102400 lewat validasi server dan constraint sesuai Data Model.
- [ ] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [ ] Pindahkan default teknis (10240 KB; `pdf,docx,xlsx,jpg,jpeg,png`; batas atas 102400 KB) dari `StoragePolicyDefaults` dan pembacaan kunci `berkas.*` ke konfigurasi aplikasi; pembaca saat ini antara lain `RenstraAttachments`, `RegulasiAttachments`, `RenstraMutationRequest`, `RegulasiMutationRequest`, `PerjanjianKinerjaAttachmentService`, `CreateJenisBerkasAction`, `UpdateJenisBerkasAction`, dan `GetJenisBerkasPage`.
- [ ] Hapus cabang anti-macet unggahan (pembacaan saklar dan pembentukan `tidak_dapat_dipenuhi` baru) pada `ActivateJadwal`, `JadwalActivationReadiness`, dan `PerjanjianKinerjaSupport` (gerbang lampiran PK), `AjukanRencanaAksi`, `TambahBuktiRencanaAksi`, serta `SubmitPengukuran`, `PengukuranMutationSteps`, `EvaluateEvidence`, dan `PresentPengukuran`; penanda lama tetap dibaca sebagai histori.
- [ ] Hapus route, controller, action, dan request halaman kebijakan (`StoragePolicyController`, `GetStoragePolicyPage`, `UpdateStoragePolicyAction`, `UpdateStoragePolicyRequest`) serta panel penggunaan penyimpanan (`StorageMetricsService`); route `/pengaturan/storage` tidak lagi melayani halaman.
- [ ] Hapus mode "Ubah batas teknis" (`UpdateBatasTeknisJenisBerkasAction`, `UpdateBatasTeknisJenisBerkasRequest`) dan peringatan unggahan dimatikan (`JenisBerkasWarnings`).
- [ ] Validasi unggahan di server: ukuran tidak melebihi persyaratan maupun batas atas 100 MB; format sesuai persyaratan atau default konfigurasi.

**C. Authorization & Audit**
- [ ] Permission tidak berubah; menu tetap tampil berdasarkan `can.*` dari server; unduhan tetap melalui route berizin dan disk privat.
- [ ] Perubahan persyaratan tetap diaudit; perubahan batas teknis dilakukan lewat konfigurasi deployment dan catatan rilis, bukan audit setelan aplikasi.

**D. Frontend / UX**
- [ ] Hapus halaman `resources/js/Pages/Pengaturan/StorageIndex.tsx` beserta menunya, serta mode "Ubah batas teknis" dan peringatan unggahan dimatikan dari `resources/js/Pages/JenisBerkas/`.
- [ ] Pindahkan Dasar Aturan ke grup Master Data & Referensi dan Persyaratan Berkas ke grup Perencanaan Kinerja pada `resources/js/lib/navigation/sidebarNavigation.ts`.
- [ ] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit; pastikan state loading/disabled/error, responsif mobile, dan kontras sesuai checklist `design-system.md`.

#### Automated Tests / Verification

- [ ] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given pengguna mana pun, Then menu Kebijakan Storage dan halaman Batas Unggahan Berkas tidak tersedia, dan route lamanya tidak lagi melayani halaman tersebut. *(Q44 §44.1 butir 1)*
- [ ] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given unggahan file, Then selalu aktif tanpa saklar; penanda `tidak_dapat_dipenuhi` akibat unggahan dimatikan tidak lagi dibuat, sedangkan penanda lama tetap tampil sebagai histori. *(Q44 §44.1 butir 2)*
- [ ] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given lampiran tanpa persyaratan (Renstra, PK, Dasar Aturan, lampiran bebas kegiatan) atau persyaratan yang mengosongkan format atau ukuran, Then server menerapkan default konfigurasi 10 MB dan `pdf,docx,xlsx,jpg,jpeg,png`. *(Q44 §44.2 butir 1)*
- [ ] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given persyaratan disimpan dengan `ukuran_maks_kb` di atas 102400, Then ditolak; Given unggahan melebihi batas atas, Then ditolak. *(Q44 §44.2 butir 1)*
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given halaman Persyaratan Berkas, Then mode "Ubah batas teknis" dan peringatan unggahan dimatikan dihapus; format dan ukuran diubah Perencanaan lewat form persyaratan. *(Q44 §44.1 butir 1–2)*
- [ ] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given panel penggunaan penyimpanan, Then panel dihapus. *(Q44 §44.1 butir 3)*
- [ ] TEST-7: Buat Vitest frontend test dan Pest Feature test yang membuktikan — Given sidebar, Then Dasar Aturan berada di grup Master Data & Referensi dan Persyaratan Berkas di grup Perencanaan Kinerja; permission tidak berubah. *(Q44 §44.3)*
- [ ] TEST-8: Buat Pest Feature/Unit test yang membuktikan — Given berkas tersimpan, Then penyimpanan tetap privat dan unduhan tetap melalui route berizin. *(Q44 §44.1 butir 4)*

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).


---

## Bagian 14 — Penutupan Tahunan & Koreksi Pasca-Penutupan

### ISS-14.01 · [Feature] Penutupan Resmi Jadwal Tahunan

> **Q48 (13 Oktober 2026):** penutupan tidak memblokir unggah arsip dokumen resmi (ISS-10.05).

> **Q52 (14 Oktober 2026):** penutupan wajib alasan dan boleh dilakukan sebelum tanggal `penutupan`; tahun terkunci bila jadwal berstatus `ditutup` atau tanggal `penutupan` terlewati; kegiatan dikunci per tahun (Q52 §52.1–§52.3).

**Terkait User Story:** `US-14.01`  
**Prioritas:** 🔴 P0 · Tahap 2 (Q46 §46.3)  
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

- **Dependensi:** Jadwal aktif; otoritas `jadwal:tutup`. Tidak ada prasyarat tanggal: penutupan boleh sebelum tanggal `penutupan` (Q52 §52.2).
- **Otorisasi:** `jadwal:tutup` (sensitif).
- **Dampak Data:** `jadwal_tahunan`, `audit_log`.
- **Rule:** Istilah yang benar adalah terkunci selama status ditutup; bukan 'tidak dapat dimutasi selamanya', karena jalur koreksi resmi tetap ada.

#### Acceptance Criteria (QA/UAT)

- [x] **AC-1:** Given penutupan dikonfirmasi, When commit, Then status menjadi `ditutup` dan `closed_at` terisi.
- [x] **AC-2:** Given jadwal ditutup, When mutasi normal RA, kegiatan, klaim, bukti, atau pengukuran dicoba, Then ditolak. *(Klaim belum memiliki jalur tulis; penulis klaim kelak memakai aturan yang sama, Q52 §52.3.)*
- [x] **AC-3:** Given data perlu dikoreksi pasca-penutupan, Then perubahan tidak dilakukan langsung; harus melalui `jadwal:buka_kembali` dengan sesi koreksi.
- [x] **AC-4:** Given penutupan adalah aksi sensitif, Then audit menyimpan aktor dan `dasar_izin`.
- [x] **AC-5:** Given jadwal `draft` atau sudah `ditutup`, When penutupan dicoba, Then ditolak sebagai validasi bisnis (422); tanpa izin atau terkena deny ditolak 403; keduanya teraudit.
- [x] **AC-6:** Given jadwal `ditutup`, Then daftar Rencana Aksi dan Pengukuran tahun tersebut tetap terbaca (read-only) oleh pemilik izin baca. *(Rencana Aksi pada #109. Daftar Pengukuran pada #110: periode terakhir yang sudah dimulai pada tahun ditutup tampil sebagai baris baca saja setelah jadwal aktif, dan jalur Perencanaan dapat memilih periode lain tahun itu untuk dibaca.)*

> **Status implementasi — ISS-14.01 (#86):** centang menunjukkan bukti implementasi dan test:
> - `tests/Feature/Jadwal/JadwalPenutupanTest` (AC-1, AC-4, AC-5: penolakan draft/ditutup/izin/revisi basi beraudit);
> - `tests/Unit/Jadwal/JadwalTahunDitutupTest`;
> - `tests/Feature/RencanaAksi/RencanaAksiJadwalDitutupTest` (termasuk daftar RA baca saja untuk AC-6), regresi `RencanaAksiBuktiHttpTest` dan `ReviewRencanaAksiTest` (RA dan bukti);
> - `tests/Feature/Pengukuran/PengukuranJadwalDitutupTest` (lima perintah pengukuran dan detail baca saja);
> - `tests/Feature/IndexPengukuranTest` (daftar Pengukuran tahun ditutup untuk AC-6, #110: baris baca saja setelah jadwal aktif, deny unit, filter Perencanaan, dan jumlah query tetap) serta `EnsureDraftPengukuranTest` (pembentukan draf tahun ditutup tetap ditolak);
> - `tests/Feature/Kegiatan/KegiatanHttpTest` (kegiatan);
> - `tests/Frontend/JadwalEditor.test.tsx`.
>
> Jalur `jadwal:buka_kembali` sendiri milik ISS-14.02. Responsivitas mobile belum diuji di browser dan menunggu smoke SSO.

#### Implementation Tasks

**A. Persistence / Data Model**
- [x] Implementasikan/validasi persistence untuk dampak data: `jadwal_tahunan`, `audit_log`.
- [x] Pastikan FK, unique/partial index, enum/check constraint, optimistic locking, dan aturan imutabilitas yang relevan mengikuti Data Model; jangan mengganti constraint dengan validasi UI saja.

**B. Backend / Domain**
- [x] Saat status `ditutup`, blok mutasi normal. Koreksi hanya melalui sesi `jadwal:buka_kembali`; jangan menerapkan 'freeze selamanya' yang meniadakan jalur koreksi resmi.

**C. Authorization & Audit**
- [x] Terapkan Policy/Gate/resolver server-side sesuai kontrak otorisasi: `jadwal:tutup` (sensitif).
- [x] React hanya menerima props `can.*`; request langsung tetap harus ditolak bila permission/scope/deny tidak memenuhi.
- [x] Catat `audit_log.dasar_izin` untuk aksi sensitif; jika alasan diwajibkan, validasi di server dan simpan alasan bersama before/after yang relevan.

**D. Frontend / UX**
- [x] Buat/rapikan page dan reusable component React untuk **Penutupan Resmi Jadwal Tahunan**; gunakan `useForm` untuk mutasi form dan tampilkan validation/flash error yang spesifik.
- [x] Gunakan token Design System, `font-sans` (Poppins), komponen reusable/shadcn yang telah ditokenisasi, `<Link>` Inertia untuk navigasi internal, dan TypeScript props/interface eksplisit.
- [ ] Pastikan state loading/disabled/error, responsive mobile tanpa horizontal overflow, dan kontras teks minimum sesuai checklist `design-system.md`.
- [x] Untuk aksi yang memerlukan alasan, gunakan pola **Modal Alasan Audit** sebelum request dikirim; validasi server tetap menjadi sumber kebenaran.

#### Automated Tests / Verification

- [x] TEST-1: Buat Pest Feature/Unit test yang membuktikan — Given penutupan dikonfirmasi, When commit, Then status menjadi `ditutup` dan `closed_at` terisi.
- [x] TEST-2: Buat Pest Feature/Unit test yang membuktikan — Given jadwal ditutup, When mutasi normal RA, kegiatan, klaim, bukti, atau pengukuran dicoba, Then ditolak.
- [x] TEST-3: Buat Pest Feature/Unit test yang membuktikan — Given data perlu dikoreksi pasca-penutupan, Then perubahan tidak dilakukan langsung; harus melalui `jadwal:buka_kembali` dengan sesi koreksi.
- [x] TEST-4: Buat Pest Feature/Unit test yang membuktikan — Given penutupan adalah aksi sensitif, Then audit menyimpan aktor dan `dasar_izin`.
- [x] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given jadwal `draft` atau sudah `ditutup`, When penutupan dicoba, Then ditolak 422; tanpa izin atau deny ditolak 403; keduanya teraudit.
- [x] TEST-6: Buat Pest Feature/Unit test yang membuktikan — Given jadwal `ditutup`, Then daftar Rencana Aksi dan Pengukuran tahun tersebut tetap terbaca (read-only). *(Rencana Aksi pada `RencanaAksiJadwalDitutupTest`; daftar Pengukuran pada `IndexPengukuranTest`, #110.)*

#### Definition of Done

- [x] Semua Acceptance Criteria dan test pada issue ini lulus.
- [x] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [x] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [x] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas). *(Responsivitas menunggu smoke SSO.)*
- [x] Aksi sensitif menghasilkan audit yang memuat `dasar_izin` dan alasan/old-new value bila diwajibkan.

### ISS-14.02 · [Feature] Pembukaan Kembali Jadwal untuk Sesi Koreksi Perencanaan

> **Q41 (13 Oktober 2026):** dalam sesi koreksi setelah penutupan, koreksi Rencana Aksi dilakukan Perencanaan; PIC hanya dapat mengubah RA pada periode yang jendela pengisiannya dibuka kembali secara resmi melalui ISS-14.03 (Q41 §41.2 butir 6, Q19). Pembukaan tahun tetap tidak membuka hak PIC secara otomatis (AC-4).

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
- **Q35 §35.2:** `lingkup_koreksi` tanpa `periode_ids` berarti tidak ada periode tercakup (gagal tertutup) untuk Rencana Aksi dan Pengukuran; lingkup yang mencakup periode wajib menyebutkan `periode_ids` secara eksplisit.

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

> **Q41 (13 Oktober 2026):** jendela pengisian yang dibuka kembali di dalam sesi koreksi juga menentukan periode Rencana Aksi yang boleh diubah PIC; di luar periode tersebut koreksi RA dilakukan Perencanaan (Q41 §41.2 butir 6). AC-5 ditambahkan karena issue ini belum dikerjakan.

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
- [ ] **AC-5:** Given jendela pengisian periode P dibuka kembali di dalam sesi koreksi, Then PIC juga dapat mengubah Rencana Aksi periode P, sedangkan RA periode lain hanya dapat dikoreksi Perencanaan. *(Q41 §41.2 butir 6)*

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
- [ ] TEST-5: Buat Pest Feature/Unit test yang membuktikan — Given jendela pengisian periode P dibuka kembali di dalam sesi koreksi, Then PIC juga dapat mengubah Rencana Aksi periode P, sedangkan RA periode lain hanya dapat dikoreksi Perencanaan. *(Q41 §41.2 butir 6)*

#### Definition of Done

- [ ] Semua Acceptance Criteria dan test pada issue ini lulus.
- [ ] Tidak ada keputusan permission atau aturan bisnis substantif yang hanya hidup di sisi React.
- [ ] Tidak ada raw secret/token pada git, props, log, audit, atau error message.
- [ ] Dokumentasi/traceability tidak bertentangan dengan PRD, Workflow, Data Model, Plan, Keputusan Penyelarasan, dan User Stories baseline.
- [ ] UI lulus checklist `design-system.md` (token, Poppins, Inertia Link/useForm, TypeScript, responsivitas, aksesibilitas).


---

## Bagian 15 — Kesiapan Operasional, UAT, dan Penggunaan Pertama

### ISS-15.01 · [UAT] Verifikasi Data Awal sebelum Penggunaan Pertama

> **Q46, Q47, Q50, Q51 (13 Oktober 2026):** data awal 2026 diisi Perencanaan dari lima dokumen resmi 2026 (Renstra 2025–2029, Perjanjian Kinerja 2026, Rencana Aksi 2026, serta Laporan Kinerja Triwulan I dan II 2026) dan disiapkan di akhir tahap 1 (Q46 §46.1 butir 6, §46.3). Kegiatan data awal boleh tanpa anggaran (Q46 §46.1 butir 3), dan rekomendasi TW I–II dicatat langsung `disetujui` dengan penanda persetujuan cadangan serta rujukan laporan sumber pada `rekomendasi_pimpinan.rujukan_sumber` (Q51 §51.1 butir 5). Data berasumsi kerja tidak dipakai di produksi sebelum dikonfirmasi. AC-5 diselaraskan dengan US-15.01, AC-6 sampai AC-8 ditambahkan, dan UAT-5 diperbaiki agar tidak lagi menyebut role `pic` (Q32).

**Terkait User Story:** `US-15.01`  
**Prioritas:** 🟡 P1 · akhir Tahap 1 (Q46 §46.3)  
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
- [ ] **AC-5 (Q31):** Given data awal UAT disiapkan, Then minimal tersedia akun uji untuk lima role resmi; akun PIC tidak dianggap siap untuk seluruh skenario authorization sampai preset permission dan eligibility assignment dikonfirmasi.
- [ ] **AC-6:** Given data awal 2026 disiapkan, Then data memuat kode sasaran dan indikator mengikuti PK (S1–S4, IKU 1.1–IKU 4.1), sasaran S4 dan IKU 4.1 di bawah Renstra 2025–2029 tanpa target Renstra, target PK bernilai penuh, IKU 1.3 ditandai dinilai tahunan, target TW I–III kosong, kegiatan Rencana Aksi per triwulan (boleh tanpa anggaran, tercatat belum diisi), realisasi/narasi/rekomendasi TW I–II, dan TW III sebagai periode lampau. *(Q46 §46.1 butir 2, 3, dan 6, Q47 §47.1 butir 4 dan §47.2 butir 4, Q50 §50.2 dan §50.4–§50.5)*
- [ ] **AC-7:** Given data yang masih memakai asumsi kerja data awal 2026 pada Keputusan Penyelarasan, Then data tersebut hanya dipakai untuk data uji dan rancangan data awal, dan tidak dipakai di produksi sebelum dikonfirmasi Perencanaan. *(Q46 §46.1 butir 6)*
- [ ] **AC-8:** Given rekomendasi TW I–II 2026 dari laporan triwulan yang sudah ditandatangani Pimpinan, When dicatat Perencanaan sebagai data awal, Then rekomendasi langsung berstatus `disetujui` dengan penanda persetujuan cadangan dan rujukan laporan sumbernya. *(Q51 §51.1 butir 5)*

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
