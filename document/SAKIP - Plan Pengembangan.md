# PLAN — Rencana Pengembangan SAKIP LLDIKTI Wilayah XVI

> **Klarifikasi final LLDIKTI Wilayah XVI — 24 September 2026**  
> Bagian ini adalah kontrak terbaru dan **menggantikan keputusan Q31 atau teks lama yang bertentangan**. Role bawaan SAKIP berjumlah **lima**: `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`. **PIC bukan role sistem**; PIC adalah konteks operasional yang dibentuk oleh penugasan dan grant unit. Detail keputusan dicatat sebagai **Q32** pada dokumen Keputusan Penyelarasan.


Setiap task memiliki tiga bagian wajib:
- **Scope** — apa yang dikerjakan.
- **Dependency** — task/modul lain yang harus selesai lebih dulu, atau "tidak ada".
- **Definition of Done (DoD)** — kriteria verifikasi mandiri yang konkret dan dapat dicek tanpa bertanya siapa yang mengerjakan.


> **Baseline final akses — Q32, 24 September 2026:** role bawaan final hanya `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`. PIC bukan role. Grant Unit hanya untuk 7 permission scoped dan dikelola dengan `delegasi:update`. `role_permissions` tidak diedit dari UI; preset disinkronkan code/seeder/release. JIT SSO membuat user nonaktif tanpa role (**diperbarui Q38:** akun SSO baru langsung `aktif` tanpa role). Logout lokal default, logout SSO terpisah. Jadwal pertama adalah 2026; TW I–II periode lampau oleh Perencanaan, TW III–IV normal (**diperbarui Q46:** TW III juga periode lampau; TW IV menjadi triwulan pertama workflow normal).

---

## Modul 1 — Autentikasi & Akses

### 1.1 Setup project Laravel 13 + Inertia 3 + React 19 (TypeScript) dengan Bun
- **Scope:** Inisialisasi project Laravel 13 baru bernama `sakip` memakai Laravel React starter kit (Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui), konfigurasi koneksi PostgreSQL, setup struktur folder dasar (app/Http, resources/js/pages, resources/js/components, resources/js/layouts). Toolchain frontend memakai **Bun** sebagai package manager dan runner — bukan npm/Node; Vite dijalankan lewat Bun (`bun run dev`/`bun run build`).
- **Dependency:** tidak ada.
- **DoD:** `php artisan serve` menampilkan halaman default tanpa error; `bun install` selesai tanpa error dan `bun run build` selesai tanpa error (asset React terkompilasi via Vite dijalankan lewat Bun); `bun run test` (Vitest + React Testing Library) berjalan hijau minimal 1 test placeholder komponen; migrasi kosong (`php artisan migrate`) berhasil terhubung ke PostgreSQL; test Pest dasar (`php artisan test`) berjalan hijau minimal 1 test placeholder; tidak ada `package-lock.json` atau referensi `npm`/`node_modules` yang dihasilkan npm di repositori (lockfile yang sah adalah `bun.lock`/`bun.lockb`).

### 1.2 Integrasi Keycloak via Socialite (OIDC Authorization Code Flow)
- **Scope:** Install `laravel/socialite` + `SocialiteProviders/Keycloak`, konfigurasi client_id baru khusus SAKIP di `.env` dan `config/services.php`, implementasi rute `redirect`/`callback` berbasis session (bukan JWT bearer).
- **Dependency:** 1.1.
- **DoD:** Mengakses rute login mengarahkan browser ke halaman Keycloak; setelah login berhasil di Keycloak, redirect kembali ke `callback` dan sesi Laravel (`Auth::check()`) bernilai true; test Pest memverifikasi rute terproteksi mengembalikan redirect ke login saat belum autentikasi.

### 1.3 Migrasi & model `users` + JIT onboarding tanpa role
- **Scope:** tabel `users` memakai `id`, `keycloak_id` unique, nama, email, profil opsional, serta `status` enum(`aktif`,`nonaktif`) default `nonaktif`. Callback Keycloak melakukan JIT provisioning berdasarkan `keycloak_id` tanpa membuat `user_roles` otomatis.
- **Dependency:** 1.2.
- **DoD:** login pertama membuat satu user `aktif` tanpa role (Q38; semula `nonaktif`); login ulang tidak menduplikasi dan tidak mengubah status; user diarahkan ke halaman menunggu penetapan peran; test membuktikan user tanpa role memiliki 0 permission.

### 1.4 Migrasi `unit`
- **Scope:** Buat migrasi dan model `unit` (id, nama, status enum aktif/nonaktif, created_by, created_at). Nama tabel dan seluruh referensi kode memakai `unit` — bukan `tim_kerja` — sejak migrasi pertama.
- **Dependency:** 1.3 (butuh `users` untuk FK `created_by`).
- **DoD:** Migrasi berjalan tanpa error; model `Unit` dapat membuat baris baru via tinker/test dengan status default `aktif`; pencarian string `tim_kerja` di seluruh basis kode (migrasi, model, factory) mengembalikan nol hasil.

### 1.5 Migrasi & model `permissions` — katalog permission
- **Scope:** Buat migrasi dan model `permissions`: `id` (uuid, PK), `kode` (varchar, unique, not null — format `entitas:aksi`), `entitas` (varchar, not null), `aksi` (varchar, not null), `keterangan` (text, nullable), `butuh_scope` enum(`global`,`unit`) not null default `global`, `sensitif` (boolean, not null, default `false`), `aktif` (boolean, not null, default `true`), `created_at`, `updated_at`.
- **Dependency:** 1.3.
- **DoD:** Migrasi berjalan tanpa error; percobaan insert dua baris dengan `kode` sama ditolak database (unique constraint); model dapat dibuat via factory/test dengan `butuh_scope` default `global` dan `sensitif` default `false`.

### 1.6 Migrasi & model `roles` dan `role_permissions` — 5 role final
- **Scope:** `roles` berisi `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`; tidak ada `pic`. `role_permissions` tetap pasangan role-permission global tanpa `unit_id`.
- **Dependency:** 1.5.
- **DoD:** tepat 5 role tersedia; `pic` ditolak/tidak diseed; unique role-permission berlaku; skema `role_permissions` tidak memiliki `unit_id`.

### 1.7 Migrasi & model `user_roles`
- **Scope:** Buat migrasi tabel `user_roles`: `id` (uuid, PK), `user_id` (FK → users.id), `role_id` (FK → roles.id), `diberikan_oleh` (FK → users.id), `created_at`; constraint `unique(user_id)` — pada Fase Awal satu pengguna memegang tepat satu peran (struktur pivot disiapkan agar multi-peran dapat dibuka di Fase Lanjutan hanya dengan melepas constraint ini).
- **Dependency:** 1.6, 1.3.
- **DoD:** Migrasi berjalan; percobaan insert baris `user_roles` kedua untuk `user_id` yang sama ditolak database (unique constraint), dibuktikan test Pest yang menangkap exception tersebut; baris dapat dibuat dengan `diberikan_oleh` terisi user lain.

### 1.8 Migrasi & model `user_permission_granted`

> **Q43 (13 Oktober 2026):** alasan pemberian grant menjadi opsional. Kolom `alasan` tetap `NOT NULL`; bila pengguna mengosongkannya, server mengisi alasan baku (pola Q37 §37.2). Penyesuaian validasi dikerjakan pada 10.7 (ISS-13.05); pencabutan grant tetap wajib beralasan.

- **Scope:** Buat migrasi tabel `user_permission_granted`: `id` (uuid, PK), `user_id` (FK → users.id), `permission_id` (FK → permissions.id), `unit_id` (FK → unit.id, nullable — `NULL` = global), `alasan` (text, **not null**), `diberikan_oleh` (FK → users.id), `created_at`; constraint `unique(user_id, permission_id, unit_id)` dengan index unik memakai `COALESCE(unit_id, ...)` karena PostgreSQL memperlakukan NULL sebagai nilai berbeda. Validasi aplikasi: `permissions.butuh_scope = unit` → `unit_id` wajib diisi; `butuh_scope = global` → `unit_id` wajib NULL; grant untuk permission bertipe `unit` tanpa `unit_id` ditolak sistem.
- **Dependency:** 1.5, 1.4 (unit).
- **DoD:** Migrasi berjalan; percobaan insert baris duplikat pada kombinasi (user_id, permission_id, unit_id) — termasuk kasus `unit_id` NULL pada baris kedua dengan kombinasi lain sama — ditolak sesuai desain unique index; percobaan submit grant untuk permission `butuh_scope=unit` dengan `unit_id` NULL ditolak validasi aplikasi (test Pest); percobaan submit tanpa `alasan` ditolak validasi.

### 1.9 Migrasi & model `user_permission_denials`
- **Scope:** Buat migrasi tabel `user_permission_denials`: `id` (uuid, PK), `user_id` (FK → users.id), `permission_id` (FK → permissions.id), `unit_id` (FK → unit.id, nullable — `NULL` = pencabutan menyeluruh), `alasan` (text, **not null**), `ditetapkan_oleh` (FK → users.id), `created_at`; constraint `unique(user_id, permission_id, unit_id)` dengan pola `COALESCE` yang sama seperti 1.8. Deny dapat mencabut permission yang berasal dari peran maupun grant, dan berlaku terhadap permission bertipe `global` maupun `unit`.
- **Dependency:** 1.5, 1.4.
- **DoD:** Migrasi berjalan; percobaan insert baris duplikat pada kombinasi (user_id, permission_id, unit_id) ditolak sesuai desain unique index; percobaan submit tanpa `alasan` ditolak validasi; baris dapat dibuat dengan `unit_id` NULL (deny global) maupun terisi (deny ber-unit).

### 1.10 Katalog permission sebagai konstanta + seeder

> **Q48 (13 Oktober 2026):** katalog memperoleh permission baru `laporan:unggah` untuk mengunggah PDF bertanda tangan Rencana Aksi tahunan dan Laporan Kinerja Triwulan (preset Perencanaan dan Superadmin, 1.12). Permission ini tidak termasuk daftar unit-scoped (Q32 §32.3), sehingga jumlah permission unit-scoped tetap 7. `laporan:unggah` tidak bertanda sensitif karena unggahan bersifat append-only dan tidak mengubah data; unggahan tetap tercatat di audit (Q48 §48.2 butir 2). Penambahan dikerjakan pada 8.5 (ISS-10.05).

- **Scope:** sinkronkan katalog permission source-controlled. Hanya 7 permission `butuh_scope=unit`: `pengukuran:create/update`, `rencana_aksi:create/update/ajukan`, `kegiatan:create/update`. Tambahkan permission global `delegasi:update`. Permission read RA/Kegiatan/Pengukuran bersifat global sesuai preset.
- **Dependency:** 1.5.
- **DoD:** test menghitung tepat 7 permission unit-scoped; `delegasi:update` tersedia global; unknown/inactive permission fail closed; seeder idempoten.

### 1.11 Service resolusi izin (allow/deny + scope + fail closed)
- **Scope:** Implementasi service/Gate Laravel tunggal yang menjadi satu-satunya titik evaluasi izin di backend, menjawab pertanyaan "boleh(kode_permission, unit_target?)" untuk aktor yang login, mengikuti algoritma: (1) fail closed bila `permissions` tidak punya baris aktif dengan kode diminta; (2) susun himpunan allow dari `user_roles → role_permissions` (selalu global) ditambah `user_permission_granted` yang cocok; (3) susun himpunan deny dari `user_permission_denials` yang cocok; (4) pencocokan scope: untuk pertanyaan dengan unit_target U, deny cocok bila `unit_id IS NULL` atau `unit_id = U`, grant cocok bila `unit_id = U`; untuk pertanyaan tanpa unit_target, deny ber-`unit_id` tidak menghalangi, deny `unit_id IS NULL` selalu menghalangi; (5) presedens DENY MENANG — ada deny cocok maka TOLAK, tidak ada deny tapi ada allow cocok maka IZINKAN, tidak ada allow maka TOLAK. Service ini TIDAK menjalankan validasi bisnis (jendela waktu, status alur, kepemilikan unit) — itu lapisan terpisah yang dipanggil setelah service ini mengizinkan.
- **Dependency:** 1.6, 1.7, 1.8, 1.9, 1.10.
- **DoD:** Test Pest — **fail closed**: permission dengan kode tidak terdaftar/tidak aktif di tabel `permissions` selalu menghasilkan tolak, terlepas peran/grant apa pun yang dimiliki user; **allow dari peran**: user dengan peran yang memiliki permission tsb di `role_permissions` diizinkan untuk permission bertipe global tanpa perlu baris grant; **allow dari grant + scope unit**: user tanpa peran relevan tapi punya `user_permission_granted` untuk unit A diizinkan untuk unit A, ditolak untuk unit B; **presedens deny menang**: user dengan permission dari peran DAN ada baris deny yang cocok (global maupun ber-unit yang sama) tetap ditolak; **deny mencabut grant**: user dengan grant di unit A yang di-deny untuk unit A yang sama ditolak, sementara grant miliknya di unit B (tanpa deny) tetap diizinkan; **deny global menghalangi pertanyaan tanpa unit_target**: deny dengan `unit_id IS NULL` menolak permintaan permission global; **deny ber-unit tidak menghalangi pertanyaan tanpa unit_target**: deny dengan `unit_id` tertentu tidak menghalangi permintaan permission global lain di luar konteks unit tsb; service tidak melakukan pengecekan jendela waktu/status alur apa pun (dibuktikan lewat test yang memanggil service langsung tanpa konteks bisnis dan tetap mendapat hasil izin/tolak murni berbasis RBAC).

### 1.12 Seeder preset `role_permissions` — source-controlled & audited

> **Q45/Q51 (13 Oktober 2026):** preset Pimpinan memperoleh `rekomendasi:tetapkan` melalui seeder ini, dengan audit before/after yang sama; Perencanaan tetap memegangnya sebagai cadangan (Q45 §45.2 butir 1). Simpan draf dan persetujuan rekomendasi dibedakan oleh status rekomendasi, bukan oleh permission (Q51 §51.2 butir 1). Perubahan preset dikerjakan bersama 6.7 (ISS-09.01) pada tahap 2 (Q51 §51.3).
>
> **Q48 (13 Oktober 2026):** preset Perencanaan dan Superadmin memperoleh permission baru `laporan:unggah` yang tidak bertanda sensitif (1.10, Q48 §48.2 butir 2); dikerjakan pada 8.5 (ISS-10.05).

- **Scope:** definisikan preset lima role dalam kode/seeder. Tidak ada editor browser. Bila preset berubah saat release, seeder menyimpan audit before/after + alasan/sumber rilis.
- **Dependency:** 1.6, 1.10, audit foundation.
- **DoD:** lima role mendapat preset final; Pegawai memiliki hak baca baseline; Perencanaan/Admin/Superadmin memperoleh `delegasi:update` sesuai keputusan; rerun tanpa delta tidak membuat audit palsu; permission unit-scoped tidak diglobalisasi secara tidak sah.

### 1.13 UI Form 1 — Assign Peran
- **Scope:** assign/change satu role dari 5 role final. Gate `pengguna:read` + `akses:update`, alasan wajib. Tidak mengubah grant/deny/PJ.
- **Dependency:** 1.7, resolver, audit.
- **DoD:** dropdown hanya 5 role; PIC operasional (bukan role) tidak muncul; perubahan teraudit; user JIT tanpa role dapat diberi role pertama; perubahan role tidak menghapus grant/PJ.

### 1.14 UI Form 2 — Kelola Grant Izin per Unit

> **Q43 (13 Oktober 2026):** field alasan pada pemberian grant menjadi opsional; bila dikosongkan, server mengisi alasan baku (lihat catatan 1.8). Pencabutan grant tetap wajib beralasan. Penyesuaian dikerjakan pada 10.7 (ISS-13.05).

- **Scope:** create/revoke grant untuk 7 permission unit-scoped; gate `delegasi:update`; aktor baseline Perencanaan/Admin/Superadmin. User target dan unit harus aktif; alasan wajib.
- **Dependency:** 1.8, 1.10, resolver, audit.
- **DoD:** hanya 7 permission tampil; `rencana_aksi:read`/`kegiatan:read` ditolak sebagai grant-unit; direct request tanpa `delegasi:update` 403; grant tidak mengubah role/PJ; create/revoke teraudit.

### 1.15 UI Form 3 — Kelola Deny Izin
- **Scope:** halaman Inertia + komponen React untuk pemegang `akses:update` (Superadmin/Admin) memilih pengguna, permission (global maupun unit), unit (nullable — kosong berarti pencabutan menyeluruh), dan alasan (wajib); submit melakukan INSERT `user_permission_denials`; deny dapat menyasar permission yang berasal dari peran maupun grant; menampilkan daftar deny aktif dengan aksi cabut (tercatat audit).
- **Dependency:** 1.9, 1.11.
- **DoD:** Submit deny dengan `unit_id` kosong (global) menghasilkan baris `user_permission_denials` dengan `unit_id` NULL; submit dengan `unit_id` terisi menghasilkan baris ber-unit; percobaan submit tanpa alasan ditolak; setelah deny tersimpan, pemanggilan service resolusi izin (1.11) untuk kombinasi user+permission+unit yang di-deny mengembalikan tolak meski user memiliki allow dari peran maupun grant; baris `audit_log` terbentuk untuk penambahan maupun pencabutan deny; percobaan submit oleh user tanpa `akses:update` ditolak 403.

### 1.16 Halaman "Jelaskan Izin Pengguna"
- **Scope:** halaman Inertia + komponen React, digerbangi permission `pengguna:read` (tanpa permission baru khusus) — memilih seorang pengguna, menampilkan daftar izin EFEKTIF pengguna tsb per unit, lengkap dengan ASAL tiap izin (peran mana / grant mana) dan DENY yang berlaku beserta alasannya. Halaman murni read-only — tidak ada aksi ubah izin di halaman ini (perubahan tetap lewat Form 1.13–1.15).
- **Dependency:** 1.11 (service resolusi izin, dipakai untuk menghitung izin efektif), 1.13, 1.14, 1.15.
- **DoD:** Untuk pengguna dengan peran Perencanaan (global) DITAMBAH satu grant unit DITAMBAH satu deny, halaman menampilkan: permission dari peran ditandai asal "peran: perencanaan"; permission dari grant ditandai asal "grant: unit X" beserta alasannya; permission yang di-deny ditandai eksplisit sebagai dicabut beserta alasannya dan TIDAK muncul sebagai izin aktif meski asalnya ada; user dengan `pengguna:read` (Admin/Superadmin) dapat mengakses halaman ini; user tanpa `pengguna:read` (mis. Pegawai, Pimpinan) mendapat 403; halaman tidak memuat elemen/aksi untuk mengubah `user_roles`/grant/deny secara langsung (hanya tautan ke Form 1.13–1.15); untuk user role PIC, halaman menampilkan izin efektif aktual berdasarkan `role_permissions`/grant/deny yang benar-benar ada dan tidak membuat permission sintetis hanya karena nama role = `pic`.

### 1.17 Middleware proteksi rute berbasis service resolusi izin
- **Scope:** Terapkan pemanggilan service resolusi izin (1.11) sebagai middleware/Policy di seluruh rute dan endpoint Inertia yang memerlukan otorisasi, bukan hanya disembunyikan di tampilan; React hanya menerima hasil evaluasi (mis. props `can.*`) untuk menyembunyikan tombol — tidak pernah mengevaluasi izin sendiri.
- **Dependency:** 1.11.
- **DoD:** Test Pest feature: mengakses endpoint/komponen tanpa permission memadai mengembalikan 403 meski URL diakses langsung (bukan hanya tombol UI yang disembunyikan); mencakup skenario peran Admin mencoba mengakses endpoint substantif data kinerja (mis. create Renstra, verifikasi pengukuran, aktivasi jadwal, susun rencana aksi, `komponen:update`, `jenis_berkas:update`) dan mendapat 403 pada setiap kasus; inspeksi kode (test statis atau review checklist) memastikan tidak ada logic evaluasi permission yang dihitung di sisi komponen React (React hanya membaca props `can.*` yang dikirim server).

### 1.18 UI Form — Pengelolaan Unit (Admin/Superadmin)
- **Scope:** halaman Inertia + komponen React untuk pengelolaan master Unit. **Admin** memiliki `unit:create`, `unit:read`, `unit:update`; **Superadmin** memiliki `unit:create`, `unit:read`, `unit:update`, dan `unit:delete`. `unit:delete` bersifat **sensitif** dan hanya dapat dipakai untuk unit kosong yang salah dibuat. Unit yang sudah memiliki riwayat indikator, Rencana Aksi, kegiatan, atau referensi domain lain **tidak boleh hard-delete** dan harus dinonaktifkan.
- **Dependency:** 1.4, 1.10, 1.11, 1.17, 10.1.
- **DoD:** 
  1. Admin dapat create/read/update dan mengaktifkan/menonaktifkan unit.
  2. Admin yang memanggil endpoint `unit:delete` secara langsung menerima 403, termasuk ketika unit benar-benar kosong.
  3. Superadmin dapat menghapus **unit kosong** dengan alasan wajib; aksi menulis `audit_log` termasuk `dasar_izin`.
  4. Superadmin juga ditolak menghapus unit yang sudah memiliki referensi/riwayat; UI mengarahkan ke nonaktif.
  5. Perencanaan/Pimpinan/Pegawai tanpa permission eksplisit tidak dapat mengakses halaman pengelolaan unit.
  6. create/update/delete yang relevan tercatat pada audit; khusus delete diperlakukan sebagai aksi sensitif.

### 1.19 Test — presedens deny, scope unit, fail closed
- **Scope:** Suite test Pest terpusat yang memverifikasi ulang secara eksplisit (di luar test unit service 1.11) tiga sifat inti resolusi izin pada level integrasi (lewat endpoint HTTP sungguhan, bukan pemanggilan service langsung): presedens deny menang atas allow; pencocokan scope unit (grant unit A tidak berlaku untuk unit B); fail closed untuk kode permission yang tidak dikenal.
- **Dependency:** 1.11, 1.17.
- **DoD:** Test feature: request HTTP ke endpoint yang memerlukan permission dengan allow dari peran DAN deny yang cocok menghasilkan 403 (deny menang, diuji end-to-end lewat HTTP, bukan hanya unit test service); request ke endpoint scoped-unit dengan grant di unit A berhasil untuk data unit A dan 403 untuk data unit B; endpoint yang secara sengaja diminta mengevaluasi kode permission yang tidak terdaftar (skenario simulasi) menghasilkan 403, bukan 500 atau lolos secara default.

### 1.20 Test — `dasar_izin` pada seluruh aksi sensitif
- **Scope:** Memastikan setiap permission yang pada **baris detail Dokumen Konfirmasi Permission & Hak Akses v1.0** bertanda `Sensitif = Ya` selalu mencatat alasan resmi dan `audit_log.dasar_izin`. Fixture test menggunakan 22 kode sensitif pada task 1.10, termasuk `unit:delete`.
- **Dependency:** 1.11, 10.1 (audit dasar), 1.10 (fixture permission/sensitif).
- **DoD:** 
  1. Test parameterized menjalankan seluruh **22 kode sensitif** dan membuktikan aksi yang berhasil menghasilkan `audit_log.dasar_izin` yang menyebut sumber allow yang berlaku.
  2. Bila permission sensitif diperoleh lewat grant yang valid, `dasar_izin` menyimpan sumber grant dan unit.
  3. Percobaan yang ditolak karena deny mencatat sumber deny untuk kejadian yang memang diwajibkan diaudit.
  4. Aksi sensitif dengan alasan wajib tetapi alasan kosong ditolak server-side.
  5. `unit:delete` diuji eksplisit sebagai sensitif dan hanya Superadmin.
  6. Permission yang tidak bertanda sensitif tidak diwajibkan mengisi `dasar_izin` oleh kontrak ini.
  7. Test/documentation note mempertahankan discrepancy sumber: narasi menyebut 21, tabel detail menandai 22; developer tidak boleh mengurangi fixture menjadi 21 tanpa keputusan PM.

### 1.21 Test — aturan pemisahan tugas F1 (keras) & F2 (`self_approval`)
- **Scope:** Implementasi dan test guard pemisahan tugas untuk **Rencana Aksi dan Pengukuran**. F1 mengikuti pengaju versi (`rencana_aksi_versi.diajukan_by` / `pengukuran_versi.diajukan_by`) dan `jalur_pengajuan`, **bukan `created_by` dan bukan role user saat ini**. Pada jalur PIC, aktor yang sama dengan `diajukan_by` dilarang memverifikasi atau mengesahkan versi yang ia ajukan sendiri walaupun kemudian mendapat permission tambahan/role berubah. F2 untuk jalur Perencanaan tetap mengikuti kontrak penyelarasan: self-review/self-approval hanya dapat berlangsung bila permission efektif mengizinkan dan harus teraudit sebagai `self_approval`.
- **Dependency:** 1.11, 5.6, 5.9, 11.3, 10.1.
- **DoD:** 
  1. **F1 Pengukuran:** A membuat draft, B melakukan submit melalui jalur PIC, maka `diajukan_by = B`; B ditolak memverifikasi/mengesahkan, sedangkan A tidak ditolak hanya karena `created_by = A`.
  2. **F1 Rencana Aksi:** pola yang sama berlaku pada `rencana_aksi_versi.diajukan_by`.
  3. Perubahan role/grant B setelah submit tidak mengubah provenance versi dan tidak menghapus F1.
  4. Reviewer berbeda yang memiliki permission efektif dapat melanjutkan review.
  5. Jalur Perencanaan yang diizinkan melakukan self-approval mencatat `self_approval = true` dan `dasar_izin`.
  6. Deny tetap menang; F2 tidak boleh melewati resolver permission.
  7. Test memastikan tidak ada guard F1 yang memakai `*.created_by` sebagai identitas pengaju final.

### 1.22 Halaman “Peran & Izin” read-only + audit sinkronisasi preset
- **Scope:** ubah konsep lama editor role-permission menjadi halaman read-only yang menampilkan lima role dan preset permission. Mutation `role_permissions` hanya melalui code/seeder/release.
- **Dependency:** 1.6, 1.12, audit.
- **DoD:** halaman digerbangi `pengguna:read`; tidak ada mutation endpoint/tombol add/revoke; seeder delta membuat audit before/after; rerun idempoten.

### 1.23 Corrective alignment: hapus role PIC dari foundation
- **Scope:** hapus `pic` dari RoleCatalog, seeder, fixture, demo, UI Assign Peran, policy/test, dan migration/data development yang sudah terlanjur dibuat. Pastikan referensi PIC yang tersisa berarti aktor operasional, bukan role.
- **Dependency:** Q32 final.
- **DoD:** pencarian source untuk role code `pic` tidak menghasilkan penggunaan sebagai role resmi; test katalog role = 5; tidak ada migrasi Pegawai→PIC.

### 1.24 Corrective alignment logout lokal vs SSO
- **Scope:** tombol `Keluar` hanya invalidate session Laravel; sediakan aksi terpisah `Keluar dari semua aplikasi (SSO)` yang memanggil Keycloak end-session. Keduanya POST + CSRF.
- **Dependency:** 1.2.
- **DoD:** test membuktikan logout lokal tidak memanggil end-session; logout SSO memanggilnya; protected route kembali meminta autentikasi.

## Baseline data 2026 untuk Modul 2–5

- IKU 3 (nomor Renstra; kode PK **IKU 1.3**, Q50 §50.4): dua input `sakip` dan `zi_wbk`, formula `(sakip + zi_wbk)/2`; baseline 2025 = 74,2, target 2026 = 76,25; jangan tampilkan selisih sebagai tren. Satu-satunya indikator yang ditandai **dinilai tahunan** pada data awal (Q47 §47.1 butir 4).
- IKU 8 (nomor Renstra; kode PK **IKU 3.3**, Q50 §50.4): `n/t × 100%`, `t = total publikasi seluruh PTS wilayah kerja`; jangan hardcode 84. Definisi, arah, dan target 2027–2029 pada Renstra 2025–2029 masih diklarifikasi Perencanaan, sehingga komponennya tidak disetel sampai jelas; target PK 2026 tetap 59,5.
- 66,395 dan 87,08 bukan realisasi dan tidak boleh di-seed sebagai pengukuran.
- Jadwal pertama = 2026; TW I–III periode lampau oleh Perencanaan, TW IV triwulan pertama workflow normal PIC operasional dengan pelaporan awal Januari 2027 (Q46; semula TW I–II lampau, TW III–IV normal); tidak ada `is_backfill`.
- RA 2026 existing diinput Perencanaan berstatus `disahkan` sebelum pengajuan TW IV (Q46; semula sebelum TW III). RA data awal ini dikecualikan dari gerbang kegiatan minimal dan RAB (Q42, Q46 §46.1); kegiatan TW IV ditambahkan lewat klaim tahap pengukuran atau buka-kembali RA oleh Perencanaan.
- Kode sasaran dan indikator mengikuti PK, Rencana Aksi, dan laporan triwulan: **S1–S4** dan **IKU 1.1–IKU 4.1**; nomor IKU Renstra (IKU 1–8) dicatat sebagai keterangan pada definisi operasional, tanpa kolom baru (Q50 §50.4). Sasaran S4 dan IKU 4.1 dicatat di bawah Renstra 2025–2029 tanpa target Renstra (Q50 §50.5).
- Target PK 2026 disimpan dengan nilai penuh yang dipakai pengukuran (mis. 94,05; 80,95; 92,86), bukan nilai tercetak yang dibulatkan; tampilan mengikuti `desimal_tampilan` (Q50 §50.2).
- Data awal 2026 diisi Perencanaan dari dokumen resmi 2026: target PK, target periode dan kegiatan Rencana Aksi, serta realisasi, narasi, dan rekomendasi TW I–II (Q46 §46.1 butir 6). Target TW I–III dicatat kosong (tidak ditargetkan) untuk semua indikator (Q47 §47.2 butir 4).
- Kegiatan data awal 2026 dan kegiatan pada periode lampau boleh tanpa anggaran (tercatat belum diisi), karena Rencana Aksi resmi hanya memuat anggaran per Rincian Output (Q46 §46.1 butir 3).
- Rekomendasi TW I–II dari laporan triwulan yang sudah ditandatangani Pimpinan dicatat Perencanaan langsung berstatus `disetujui`, dengan penanda persetujuan cadangan dan rujukan laporan sumbernya pada `rekomendasi_pimpinan.rujukan_sumber` (Q51 §51.1 butir 5, 6.7).
- Asumsi kerja data awal 2026 pada Keputusan Penyelarasan (formula, komponen, serta pemetaan unit dan PJ) hanya dipakai untuk data uji dan rancangan data awal; asumsi tersebut tidak dipakai di produksi sebelum dikonfirmasi Perencanaan.

## Modul 2 — Master Renstra (Renstra, Sasaran, Indikator, Target, PK)

### 2.1 Migrasi & model `renstra`
- **Scope:** Buat migrasi tabel `renstra` (termasuk enum status draft/aktif/nonaktif/diarsipkan).
- **Dependency:** 1.3 (FK created_by).
- **DoD:** Migrasi berjalan; model dapat dibuat dengan status default `draft`.

### 2.2 CRUD Renstra (Inertia + React)
- **Scope:** Form create/edit/list/delete Renstra (nama, keterangan, dasar_hukum, tahun_mulai, tahun_akhir); permission `renstra:create/read/update/delete`.
- **Dependency:** 2.1, 1.11.
- **DoD:** User dengan `renstra:create` dapat membuat Renstra baru berstatus draft; user tanpa `renstra:read` mendapat 403 saat mengakses halaman daftar; setiap create/update/delete menghasilkan baris `audit_log`.

### 2.3 Validasi & aksi aktivasi Renstra
- **Scope:** Implementasi tombol/aksi "Aktivasi" pada Renstra berstatus draft, menjalankan validasi: `dasar_hukum` terisi dan tidak ada Renstra aktif lain dengan rentang tahun beririsan.
- **Dependency:** 2.2.
- **DoD:** Test Pest: aktivasi Renstra dengan `dasar_hukum` kosong ditolak dengan pesan error spesifik; aktivasi Renstra yang rentang tahunnya beririsan dengan Renstra aktif lain ditolak; aktivasi Renstra valid mengubah `status` menjadi `aktif` dan mencatat audit log.

### 2.4 Guard nonaktifkan Renstra & aksi arsipkan
- **Scope:** Transisi status `aktif → nonaktif` ditolak selama masih ada `jadwal_tahunan` berstatus `aktif` yang merujuk Renstra tersebut — jadwal harus ditutup lebih dulu (Modul 3). Transisi `nonaktif → diarsipkan` tetap berlaku tanpa syarat tambahan. Masing-masing transisi memerlukan `renstra:update` dan menghasilkan audit log.
- **Dependency:** 2.3, 3.7 (aksi tutup jadwal, untuk pengujian guard).
- **DoD:** Test Pest: percobaan nonaktifkan Renstra yang masih memiliki jadwal_tahunan aktif ditolak dengan pesan spesifik; setelah seluruh jadwal terkait ditutup, nonaktifkan berhasil; urutan transisi hanya bisa terjadi sesuai alur (tidak bisa langsung draft→diarsipkan); setiap transisi tercatat di audit_log dengan nilai_lama/nilai_baru status.

### 2.5 Migrasi & CRUD `sasaran`
- **Scope:** Migrasi tabel `sasaran` (renstra_id, nama, keterangan, urutan) + CRUD (Inertia + React) dengan reorder urutan; permission `sasaran:create/update/delete`.
- **Dependency:** 2.1.
- **DoD:** Sasaran baru dapat dibuat di bawah Renstra tertentu dan tampil terurut sesuai kolom `urutan`; percobaan hapus Sasaran yang masih punya Indikator ditolak (lihat 2.7) atau memicu peringatan konfirmasi eksplisit.

### 2.6 Migrasi `indikator` (termasuk kolom `arah` dan `tipe_perhitungan`)

> **Q47 (13 Oktober 2026):** `indikator` memperoleh kolom `dinilai_tahunan` (boolean, default `false`, label UI "Dinilai tahunan") yang dibekukan pada snapshot dan tidak mengubah `tipe_perhitungan`, formula, maupun komponen. Penambahan dikerjakan pada 11.9 (ISS-05.07). Kolom `wajib_catatan` dipertahankan walaupun saklarnya disembunyikan dari form (Q45, 2.7).

- **Scope:** Migrasi tabel `indikator`, termasuk `unit_id` NOT NULL (menggantikan `tim_kerja_id`), `wajib_catatan`, `created_by_role`, kolom `arah` enum(`naik_baik`, `turun_baik`) NOT NULL default `naik_baik`, dan kolom baru **`tipe_perhitungan`** enum(`rasio_persen`, `penjumlahan`, `manual`) NOT NULL default `manual`.
- **Dependency:** 2.5, 1.4 (unit).
- **DoD:** Migrasi berjalan; percobaan insert indikator tanpa `unit_id` ditolak database (NOT NULL constraint); indikator baru tanpa mengisi `arah`/`tipe_perhitungan` secara eksplisit otomatis tersimpan dengan `arah = naik_baik` dan `tipe_perhitungan = manual`; kolom bernama `unit_id`, bukan `tim_kerja_id`; kolom `tipe_perhitungan` memuat tepat ketiga nilai enum yang didefinisikan.

### 2.7 CRUD Indikator + validasi kepemilikan unit + pilihan arah + tipe perhitungan

> **Q45 (13 Oktober 2026):** saklar `wajib_catatan` disembunyikan dari form indikator tanpa menghapus kolomnya (2.6), karena kewajiban catatan digantikan narasi pengukuran wajib. Penyesuaian dikerjakan pada 5.5 (ISS-07.03).
>
> **Q47 (13 Oktober 2026):** form indikator memuat penanda **Dinilai tahunan** (`indikator.dinilai_tahunan`) yang diubah Perencanaan dengan `indikator:update` dan tercatat di audit (Q47 §47.1); dikerjakan pada 11.9 (ISS-05.07).
>
> **Q50 (13 Oktober 2026):** kode sasaran dan indikator mengikuti PK, yaitu S1–S4 dan IKU 1.1–IKU 4.1; nomor IKU pada Renstra (IKU 1–8) dicatat sebagai keterangan pada definisi operasional, tanpa kolom baru (Q50 §50.4).

- **Scope:** Form create/edit/list Indikator di bawah Sasaran, wajib memilih Unit pemilik, Arah Penilaian (naik_baik/turun_baik), dan Tipe Perhitungan (rasio_persen/penjumlahan/manual); permission `indikator:create/read/update/delete`.
- **Dependency:** 2.6, 1.4.
- **DoD:** Indikator baru tersimpan dengan `unit_id`, `arah`, dan `tipe_perhitungan` terisi; `created_by_role` otomatis terisi dari role aktif user saat pembuatan; setiap create/update/delete tercatat di audit_log; form memuat ketiga opsi tipe perhitungan dan kedua opsi arah secara eksplisit (tidak hanya default tersembunyi).

### 2.8 Fitur pindah unit pada Indikator

> **Q43 (13 Oktober 2026):** alasan pada pindah unit indikator menjadi opsional (Q43 §43.3 butir 3); audit nilai lama dan baru unit tetap dicatat. Penyesuaian `PindahUnitIndikatorRequest` dikerjakan pada 10.7 (ISS-13.05).

- **Scope:** Aksi khusus (terpisah dari edit umum) untuk mengubah `unit_id` suatu indikator, dengan konfirmasi eksplisit dan pencatatan audit detail (nilai_lama/nilai_baru unit).
- **Dependency:** 2.7.
- **DoD:** Test Pest: mengubah unit indikator menghasilkan baris audit_log dengan `tindakan` spesifik (mis. `indikator.pindah_unit`) berisi `nilai_lama.unit_id` dan `nilai_baru.unit_id` yang berbeda dan benar.

### 2.9 Aksi arsipkan Indikator + blokir create pada indikator arsip
- **Scope:** Transisi status indikator `aktif → arsip`; indikator arsip tidak muncul sebagai opsi pengisian pengukuran maupun rencana aksi baru namun tetap terbaca di riwayat. Guard eksplisit di layer `pengukuran:create` dan `rencana_aksi:create`: menolak permintaan create untuk indikator berstatus `arsip`, terlepas dari siapa pemohonnya (termasuk Perencanaan/Superadmin).
- **Dependency:** 2.7.
- **DoD:** Indikator berstatus arsip tidak muncul di dropdown pembuatan pengukuran baru (Modul 5) maupun rencana aksi baru (Modul 11); query riwayat pengukuran/rencana aksi lama untuk indikator tsb tetap mengembalikan data lengkap dengan penanda status arsip; test Pest: percobaan `pengukuran:create` dan `rencana_aksi:create` langsung (bypass UI, mis. lewat pemanggilan service) untuk indikator arsip ditolak dengan pesan spesifik, diuji baik oleh user PIC maupun user Perencanaan.

### 2.10 Migrasi & fitur Target Tahunan (termasuk kolom `baseline`)

> **Q39 (13 Oktober 2026):** `target_tahunan` tetap menyimpan **target PK** (baseline dan target tahun berjalan) dengan domain Q33, tetapi editornya dipindah ke halaman Perjanjian Kinerja (2.22, ISS-02.11). Halaman Sasaran & Indikator memuat **target Renstra** (2.21, ISS-02.10). Snapshot, rekonsiliasi Rencana Aksi, dan pengukuran tetap membaca target PK melalui snapshot.

- **Scope:** Migrasi `target_tahunan` (unique indikator_id+tahun), termasuk kolom baru **`baseline`** (numeric, nullable — baseline tahun sebelumnya yang dipakai saat menyusun target tahun itu) + form input/update target dan baseline per indikator per tahun; permission `target:update`.
- **Dependency:** 2.6.
- **DoD:** Input target untuk kombinasi indikator+tahun yang sudah ada memperbarui baris (bukan duplikat, dibuktikan unique constraint); target dapat diisi `0` sebagai nilai sah, dan dibedakan dari kondisi belum diisi (`null`) di tampilan; kolom `baseline` dapat diisi terpisah dari `target`, keduanya tampil berdampingan di form.

### 2.11 Migrasi & fitur Perjanjian Kinerja (renstra_pk)

> **Q39/Q40 (13 Oktober 2026):** halaman PK tidak sekadar mencatat dokumen; isian baseline dan target PK per indikator beserta pembanding target Renstra sudah dikerjakan pada 2.22 (ISS-02.11). Perubahan target resmi setelah PK disetujui memakai revisi PK berversi (2.23, ISS-02.12); alur koreksi 2.12 tetap untuk salah ketik nomor/tanggal PK.
>
> **Q43/Q50 (13 Oktober 2026):** `nomor_pk` menjadi opsional karena PK 2026 tidak bernomor; PK tetap unik per Renstra-tahun (Q50 §50.1). Tanggal PK tidak divalidasi terhadap tanggal penetapan Renstra (Q50 §50.6). Penyesuaian `StorePerjanjianKinerjaRequest`/`UpdatePerjanjianKinerjaRequest` sudah dikerjakan pada 2.22 (ISS-02.11). Alasan perubahan PK sebelum PK dipakai jadwal aktif menjadi opsional (Q43 §43.3 butir 3, 10.7); koreksi sesudahnya tetap wajib alasan lewat 2.12.

- **Scope:** Migrasi `renstra_pk` (unique renstra_id+tahun) + form input nomor_pk, tanggal_pk per tahun per Renstra; permission `pk:create/update`.
- **Dependency:** 2.1.
- **DoD:** PK baru untuk kombinasi Renstra+tahun yang sama ditolak sistem (unique constraint) kecuali melalui alur koreksi (2.12); PK tersimpan dan dapat dirujuk saat aktivasi Jadwal (Modul 3).

### 2.12 Fitur koreksi Perjanjian Kinerja (wajib alasan)
- **Scope:** Alur khusus untuk mengubah `nomor_pk`/`tanggal_pk` pada PK yang sudah tercatat, mewajibkan pengisian alasan koreksi (tetap wajib, Q43 §43.2 butir 3). Jalur ini hanya untuk koreksi salah ketik nomor atau tanggal PK; perubahan target resmi memakai revisi PK berversi (2.23, Q40 §40.1 butir 8). `nomor_pk` bersifat opsional (Q50 §50.1), sehingga koreksi tidak mewajibkan nomor terisi.
- **Dependency:** 2.11.
- **DoD:** Submit koreksi tanpa alasan ditolak validasi (pesan error jelas); koreksi berhasil menghasilkan baris audit_log dengan `alasan` terisi dan `nilai_lama`/`nilai_baru` mencerminkan field yang berubah.

### 2.13 Fitur revisi Renstra in place akibat perubahan Kepmen IKU

> **Q40 (13 Oktober 2026):** atribut Renstra tetap direvisi in place dengan alasan dan audit seperti di bawah. Lampiran revisi pada Renstra `aktif` serta perubahan target Renstra yang tercatat di audit dengan nilai lama dan baru beserta riwayat revisinya sudah dikerjakan pada 2.21 (ISS-02.10). Versi penuh Renstra (sasaran dan indikator ikut berversi) tidak dibangun; penggantian Renstra karena kebijakan kementerian memakai Renstra baru.

- **Scope:** Alur edit langsung (bukan pembuatan baris Renstra baru) atas field Renstra yang sudah aktif — dipakai khusus saat Kepmen IKU direvisi; mewajibkan alasan yang memuat nomor dan tanggal Kepmen; memakai permission `renstra:update` yang sama seperti edit biasa, namun dengan validasi tambahan: alasan wajib diisi setiap kali Renstra berstatus `aktif` diedit.
- **Dependency:** 2.3.
- **DoD:** Test Pest: edit atas Renstra berstatus aktif tanpa alasan ditolak; edit dengan alasan berhasil menyimpan perubahan pada baris Renstra yang sama (ID tidak berubah, tidak ada baris Renstra baru terbentuk) dan tercatat audit_log dengan `nilai_lama`/`nilai_baru` field yang berubah.

### 2.14 Migrasi `indikator_komponen` + CRUD granular (`komponen:create/read/update/delete`)
- **Scope:** Migrasi tabel baru `indikator_komponen`: `id`, `indikator_id` (FK), `kode` (varchar), `label` (text), `peran` enum(`pembilang`,`penyebut`,`penjumlah`), `bobot` (numeric, not null, default 1), `urutan` (int), `satuan` (varchar, nullable), `aktif` (boolean, default true), `created_by`, `created_at`, `updated_at`, unique(`indikator_id`, `kode`); CRUD (Inertia + React) komponen di bawah indikator dengan permission granular: `komponen:create` (tambah baris), `komponen:read` (lihat daftar komponen di bawah indikator), `komponen:update` (ubah bobot/label/urutan/status aktif), `komponen:delete` (nonaktifkan/hapus baris). `komponen:update` dan `komponen:delete` bertanda `sensitif=true` — setiap aksinya mencatat `dasar_izin` pada `audit_log` (lihat 1.20).
- **Dependency:** 2.7, 1.10, 1.11.
- **DoD:** Migrasi berjalan; percobaan insert dua komponen dengan `kode` sama pada indikator yang sama ditolak database (unique constraint); user dengan `komponen:create`/`update`/`delete` (Perencanaan/Superadmin, lewat peran) dapat menambah/mengubah/menonaktifkan komponen lewat UI; user dengan HANYA `komponen:read` (mis. Admin, Pimpinan, Pegawai) dapat melihat daftar komponen namun mendapat 403 saat mencoba create/update/delete; `komponen:update` dan `komponen:delete` masing-masing menghasilkan baris `audit_log` dengan `dasar_izin` terisi (dibuktikan test terpisah untuk update dan untuk delete).

### 2.15 Validasi definisi komponen sesuai `tipe_perhitungan`
- **Scope:** Validasi saat menyimpan indikator/komponen: `rasio_persen` wajib punya ≥1 komponen `pembilang` aktif dan tepat 1 komponen `penyebut` aktif; `penjumlahan` wajib punya ≥1 komponen `penjumlah` aktif. Penyimpanan indikator dengan tipe itu ditolak bila tidak terpenuhi.
- **Dependency:** 2.14, 2.7.
- **DoD:** Test Pest: menyimpan indikator `tipe_perhitungan=rasio_persen` tanpa komponen `penyebut` aktif ditolak; menyimpan dengan 2 komponen `penyebut` aktif sekaligus ditolak (harus tepat 1); menyimpan `tipe_perhitungan=penjumlahan` tanpa komponen `penjumlah` aktif ditolak; kombinasi valid masing-masing tipe berhasil tersimpan.

### 2.16 Migrasi & CRUD entitas `regulasi` (`regulasi:create/read/update/delete`)
- **Scope:** Migrasi tabel baru `regulasi`: `id` (uuid, PK), `jenis` enum(`kepmen`,`permen`,`perpres`,`keputusan_lainnya`) not null, `nomor` (varchar, not null), `tahun` (int, not null), `tentang` (text, not null), `tanggal` (date, nullable), `tautan_sumber` (varchar 2048, nullable), `catatan` (text, nullable), `aktif` (boolean, not null, default `true`), `created_by` (FK → users.id), `created_at`, `updated_at`; unique(`jenis`, `nomor`, `tahun`). Halaman "Dasar Aturan" (Inertia + React) untuk CRUD `regulasi` dengan permission granular: `regulasi:create`, `regulasi:read` (dipegang seluruh peran), `regulasi:update`, `regulasi:delete`. `regulasi:update` dan `regulasi:delete` bertanda `sensitif=true` — setiap aksinya mencatat `dasar_izin` pada `audit_log` (lihat 1.20).
- **Dependency:** 1.10, 1.11, 1.3.
- **DoD:** Migrasi berjalan; percobaan insert dua baris dengan kombinasi (`jenis`, `nomor`, `tahun`) sama ditolak database (unique constraint); user dengan `regulasi:create`/`update`/`delete` (Perencanaan/Superadmin, lewat peran) dapat menambah/mengubah/menonaktifkan regulasi lewat UI; user dengan HANYA `regulasi:read` (Admin, Pimpinan, Pegawai) dapat melihat daftar regulasi namun mendapat 403 saat mencoba create/update/delete; `regulasi:update` dan `regulasi:delete` masing-masing menghasilkan baris `audit_log` dengan `dasar_izin` terisi (dibuktikan test terpisah untuk update dan untuk delete); baris baru tersimpan dengan `aktif = true` sebagai default.

### 2.17 Guard hapus `regulasi` yang masih dirujuk + lampiran dokumen regulasi
- **Scope:** Guard yang menolak `regulasi:delete` selama regulasi tsb masih dirujuk `renstra.regulasi_id` atau `indikator.regulasi_id` yang berstatus aktif. Integrasi lampiran dokumen pada halaman "Dasar Aturan" via komponen bukti dukung generik (`berkasable_type = regulasi`, Modul 13) — mode file/tautan/teks, tanpa `jenis_berkas` (lampiran bebas murni, tidak bergerbang).
- **Dependency:** 2.16, 13.2, 13.5.
- **DoD:** Test Pest: percobaan `regulasi:delete` pada regulasi yang masih dirujuk `renstra.regulasi_id` aktif DITOLAK dengan pesan spesifik; percobaan yang sama pada regulasi yang masih dirujuk `indikator.regulasi_id` indikator berstatus aktif DITOLAK; regulasi tanpa rujukan aktif berhasil dihapus; melampirkan dokumen (mode apa pun) pada `berkasable_type = regulasi` berhasil tersimpan dan tampil di halaman "Dasar Aturan"; percobaan menghapus lampiran regulasi yang masih dirujuk Renstra/indikator aktif DITOLAK (imutabilitas per §10.6 Workflow), sementara lampiran pada regulasi tanpa rujukan aktif berhasil dihapus.

### 2.18 Kolom `regulasi_id` pada `renstra` + tampilan rujukan & lampiran pada halaman Renstra

> **Q40 (13 Oktober 2026):** lampiran revisi boleh ditambahkan setelah Renstra `aktif`; lampiran yang sudah ada tetap tidak dapat dihapus. Penyesuaian sudah dikerjakan pada 2.21 (ISS-02.10).

- **Scope:** Migrasi kolom baru `renstra.regulasi_id` (FK → regulasi.id, nullable). Perluasan form 2.2 untuk memilih `regulasi_id` (opsional) saat menyusun/mengedit Renstra, DI SAMPING kolom `dasar_hukum` yang tetap wajib diisi sebelum aktivasi (2.3, tidak berubah). Halaman Renstra menampilkan: lampiran dokumen Renstra (`berkasable_type = renstra`, Modul 13, mode file/tautan/teks, tanpa `jenis_berkas`), rujukan regulasi (bila `regulasi_id` terisi, lengkap jenis/nomor/tahun/tentang), dan ringkasan `dasar_hukum` berdampingan.
- **Dependency:** 2.16, 2.2, 13.2, 13.5.
- **DoD:** Migrasi berjalan; `renstra.regulasi_id` dapat diisi/dikosongkan lewat form tanpa mempengaruhi validasi aktivasi (2.3) yang tetap hanya memeriksa `dasar_hukum`; halaman Renstra menampilkan lampiran dokumen Renstra, rujukan regulasi terpilih, dan ringkasan `dasar_hukum` pada satu layar; melampirkan dokumen Renstra (mode apa pun) berhasil sebelum Renstra `aktif`; percobaan menghapus lampiran Renstra setelah Renstra berstatus `aktif` DITOLAK (imutabilitas per §10.6 Workflow); test Pest memverifikasi `renstra.regulasi_id` dapat NULL tanpa error skema.

### 2.19 Kolom `regulasi_id` pada `indikator` + tampilan rujukan pada halaman Indikator
- **Scope:** Migrasi kolom baru `indikator.regulasi_id` (FK → regulasi.id, nullable). Perluasan form 2.7 untuk memilih `regulasi_id` (opsional) saat menyusun/mengedit Indikator, sebagai dasar aturan per indikator yang dapat ditelusuri tanpa mengunggah dokumen yang sama berulang. Halaman Indikator menampilkan rujukan regulasi terpilih (bila ada) lengkap jenis/nomor/tahun/tentang.
- **Dependency:** 2.16, 2.7.
- **DoD:** Migrasi berjalan; `indikator.regulasi_id` dapat diisi/dikosongkan lewat form tanpa mempengaruhi validasi create/update Indikator yang lain; halaman Indikator menampilkan rujukan regulasi terpilih; test Pest memverifikasi `indikator.regulasi_id` dapat NULL tanpa error skema; mengubah `indikator.regulasi_id` tercatat di `audit_log` dengan `nilai_lama`/`nilai_baru`.

### 2.20 Lampiran dokumen Perjanjian Kinerja pada `renstra_pk` + tampilan pada halaman PK

> **Q44/Q48/Q50 (13 Oktober 2026):** lampiran PK memakai batas teknis default dari konfigurasi tanpa cabang anti-macet unggahan (9.10). PDF PK bertanda tangan tetap diunggah lewat lampiran PK dengan permission PK, dan per versi PK setelah revisi berversi tersedia (Q48 §48.2 butir 1–2, 2.23). Sebelum itu, PK perubahan yang hanya mengubah anggaran diunggah sebagai lampiran tambahan PK beserta keterangan pada `berkas.keterangan` (Q48 §48.2 butir 6) tanpa mengubah target maupun snapshot (Q50 §50.7).

- **Scope:** Integrasi lampiran dokumen pada halaman Perjanjian Kinerja (2.11) via komponen bukti dukung generik (`berkasable_type = renstra_pk`, Modul 13) — mode file/tautan/teks, tanpa `jenis_berkas` untuk pemenuhannya (tidak ada daftar persyaratan bernama), NAMUN minimal satu lampiran menjadi syarat gerbang keempat aktivasi Jadwal Tahunan (lihat 3.5a). Halaman Perjanjian Kinerja menampilkan lampiran dokumen PK beserta `nomor_pk`/`tanggal_pk`.
- **Dependency:** 2.11, 13.2, 13.5.
- **DoD:** Melampirkan dokumen PK (mode apa pun) pada `renstra_pk` berhasil tersimpan dan tampil berdampingan dengan `nomor_pk`/`tanggal_pk` pada halaman Perjanjian Kinerja; percobaan menghapus lampiran PK setelah Jadwal Tahunan tahun tsb berstatus `aktif` DITOLAK (imutabilitas per §10.6 Workflow, diuji setelah 3.5a tersedia); renstra_pk tanpa lampiran tetap dapat tersimpan (lampiran bukan syarat penyimpanan PK itu sendiri, hanya syarat aktivasi jadwal — lihat 3.5a).

### 2.21 Target Renstra per indikator + lampiran revisi & riwayat revisi target Renstra (ISS-02.10)
- **Scope:** Q39 §39.1 dan Q40 §40.2. Data baru **target Renstra** per indikator (entitas `target_renstra`, Data Model §2.35) untuk setiap tahun dalam rentang `[tahun_mulai, tahun_akhir]` Renstra, diisi sekali per Renstra pada halaman Sasaran & Indikator sesuai dokumen Renstra, dengan domain nilai Q33 (nullable, finite, nonnegatif, `0` sah dan dibedakan dari `null`). Target Renstra disimpan dengan permission `target:update` yang sudah ada, tanpa permission baru (Q39 §39.3 butir 3). Target Renstra menjadi pembanding target PK (2.22) dan tidak dipakai menghitung capaian. Pada Renstra `aktif`: lampiran revisi boleh ditambahkan, sedangkan lampiran yang sudah ada tetap tidak dapat dihapus (13.6); lampiran revisi hanya dapat ditambahkan pada Renstra berstatus `draft` atau `aktif`, dan Renstra `nonaktif` atau diarsipkan tidak menerima lampiran baru (Q40 §40.2 butir 5); perubahan target Renstra tercatat di audit dengan nilai lama dan baru (Q60) dan ditampilkan sebagai riwayat revisi target Renstra — termasuk saat target tahun-tahun berikutnya dinaikkan karena target terlampaui sebelum akhir periode. Versi penuh Renstra tidak dibangun (Q40 §40.2 butir 4).
- **Dependency:** 2.3, 2.7, 2.18, 13.5, 13.6, 10.1. Tahap 1 Q46 §46.3.
- **DoD:** Test Pest: target Renstra tersimpan untuk setiap tahun dalam rentang Renstra dan ditolak untuk tahun di luar rentang; pengguna tanpa `target:update` ditolak 403; nilai negatif, `NaN`/infinity, atau representasi non-desimal ditolak, sedangkan `0` tersimpan sah dan tampil berbeda dari `null`; halaman Sasaran & Indikator menampilkan target Renstra per tahun; menambah lampiran revisi pada Renstra `aktif` berhasil, sedangkan menghapus lampiran Renstra `aktif` tetap ditolak dan menambah lampiran pada Renstra `nonaktif` atau diarsipkan ditolak; mengubah target Renstra pada Renstra `aktif` berhasil tanpa alasan dan menghasilkan baris `audit_log` berisi nilai lama/baru yang tampil pada riwayat revisi target Renstra; perubahan target Renstra tidak mengubah `target_tahunan`, `jadwal_snapshot`, maupun capaian.

### 2.22 Isian baseline & target PK di halaman Perjanjian Kinerja + pembanding target Renstra (ISS-02.11)
- **Scope:** Q39 §39.1–§39.4, Q50, dan Q65. Halaman Perjanjian Kinerja (2.11) memuat isian baseline dan target tahun berjalan (**target PK**) untuk setiap indikator aktif Renstra, dikelompokkan per sasaran, di samping nomor, tanggal, dan lampiran dokumen PK bertanda tangan (2.11, 2.20). Nilai tetap disimpan di `target_tahunan` dengan domain Q33; editor target pada halaman indikator (2.10) dipindah ke halaman ini. Target Renstra tahun yang sama (2.21) ditampilkan di samping isian. Untuk indikator `naik_baik`, target PK di bawah target Renstra memunculkan peringatan dan alasan wajib; kondisi dibalik untuk `turun_baik` (target PK di atas target Renstra). Nilai tersebut tidak ditolak karena PK revisi yang sah dapat lebih rendah; yang diwajibkan adalah alasannya, diisi pada isian di editor target PK (bukan Modal Alasan Audit) dan ditegakkan server saat simpan. Nilai yang sama tidak menyimpang, perbandingan memakai nilai tersimpan tanpa pembulatan, dan deviasi tidak dinilai bila target Renstra belum ada atau target PK kosong (Q65 §65.2). Anjuran "minimal sama atau naik satu poin" (untuk indikator `turun_baik`: "minimal sama atau turun satu poin", Q65 §65.2 butir 13) tampil sebagai panduan, bukan aturan sistem. Indikator tanpa target Renstra tetap dapat diberi target PK dengan keterangan "target Renstra belum ada". Alasan deviasi terhadap target Renstra disimpan bersama target PK (`target_tahunan.alasan_deviasi_renstra`) dan tercatat di audit; alasan perubahan target PK di luar deviasi tersebut tetap opsional (Q43 §43.3 butir 2). Target PK diinput Perencanaan dengan permission `target:update` (Q39 §39.3 butir 3) setelah angka disepakati dengan tim kerja di luar aplikasi; PJ dan PIC melihatnya baca-saja; alur usulan target oleh PJ tidak dibangun. Snapshot (3.7), rekonsiliasi Rencana Aksi (11.6, ADR-0008), pengukuran, dan gerbang aktivasi jadwal (3.5) tetap membaca target PK sehingga tidak berubah. Target PK tetap dapat diubah setelah snapshot terbentuk dengan alasan opsional, tanpa mengubah snapshot (Q65 §65.1). **Q50:** `nomor_pk` opsional dan PK tetap unik per Renstra-tahun, dengan penyesuaian `StorePerjanjianKinerjaRequest`/`UpdatePerjanjianKinerjaRequest` (§50.1); target PK disimpan dengan nilai penuh yang dipakai pengukuran (mis. 94,05), bukan nilai tercetak yang dibulatkan, sedangkan tampilan mengikuti `desimal_tampilan` dan status capaian serta selisih terhadap target PK memakai nilai tersimpan (§50.2); baseline tampil sebagai data pendukung, bukan isi dokumen PK (§50.3); indikator yang hanya ada di PK, mis. IKU 4.1 pada sasaran S4, mendapat keterangan "target Renstra belum ada" (§50.5); tanggal PK yang lebih awal dari tanggal penetapan Renstra tidak ditolak (§50.6). PK perubahan yang hanya mengubah anggaran diunggah sebagai lampiran tambahan PK lewat fitur lampiran yang ada (2.20, §50.7), dengan keterangan opsional pada `berkas.keterangan` (Q65 §65.3).
- **Dependency:** 2.10, 2.11, 2.20, 2.21, 10.1. Tahap 1 Q46 §46.3.
- **DoD:** Test Pest/Vitest: halaman PK menampilkan isian baseline dan target PK untuk setiap indikator aktif Renstra, dikelompokkan per sasaran, beserta nomor/tanggal/lampiran PK dan target Renstra tahun yang sama; editor target tidak lagi tersedia di halaman indikator; nilai tersimpan di `target_tahunan` dengan domain Q33; penyimpanan target PK di bawah target Renstra pada indikator `naik_baik` tanpa alasan ditolak validasi alasan, sedangkan dengan alasan berhasil dan tercatat `audit_log`; skenario terbalik pada indikator `turun_baik` berperilaku sama; target PK yang sama atau lebih baik dari target Renstra tersimpan tanpa alasan; indikator tanpa target Renstra tersimpan dengan keterangan "target Renstra belum ada"; anjuran tampil tanpa validasi; PJ/PIC tanpa izin ubah target hanya melihat nilai dan request ubah langsung ditolak 403; aktivasi jadwal (gerbang 2, 3.5) dan pembentukan snapshot (3.7) tetap membaca `target_tahunan`; PK tanpa nomor tersimpan, sedangkan PK kedua pada Renstra-tahun yang sama tetap ditolak; target PK 94,05 tersimpan apa adanya dan tampil sesuai `desimal_tampilan`; baseline tampil terpisah sebagai data pendukung; IKU 4.1 tanpa target Renstra tersimpan dengan keterangan "target Renstra belum ada"; tanggal PK sebelum tanggal penetapan Renstra tidak ditolak; perubahan target PK setelah snapshot terbentuk diterima tanpa alasan dan tidak mengubah snapshot (Q65 §65.1); lampiran tambahan PK menyimpan keterangannya (Q65 §65.3).

### 2.23 Revisi PK berversi (ISS-02.12)
- **Scope:** Q40 §40.1. PK dapat direvisi, umumnya pada akhir tahun setelah persetujuan Eselon I; di aplikasi revisi disetujui Perencanaan dan wajib beralasan (Q43 §43.2 butir 3). Setiap revisi membentuk **versi PK baru** (`renstra_pk_versi`, Data Model §2.36, rancangan) yang menyimpan nomor versi, nomor dan tanggal surat persetujuan Eselon I sebagai isian tersendiri (`nomor_surat`, `tanggal_surat`; bukan pada kolom `alasan`), periode mulai berlaku, lampiran PDF PK bertanda tangan (Q48 §48.2 butir 1), dan target per indikator (`renstra_pk_versi_target`, §2.37); versi sebelumnya tidak diubah. PK awal dan PK akhir sama-sama disimpan dan dapat diunduh sebagai lampiran laporan kinerja. Revisi berlaku mulai periode pertama yang belum ditutup; periode yang sudah dilaporkan tetap memakai target versi lama. Persetujuan revisi yang mengubah target menerbitkan versi `jadwal_snapshot` baru (Q35 §35.1) dengan `periode_mulai_id` periode tersebut dan `rujukan_koreksi` surat Eselon I; revisi yang hanya mengganti dokumen tidak menerbitkan versi snapshot. Capaian akhir tahun dinilai terhadap target PK akhir, yaitu versi yang berlaku pada periode nilai akhir. Rencana Aksi tidak diubah otomatis: selisih target periode terakhir dengan target PK baru tampil sebagai deviasi yang wajib dijelaskan (11.6, ADR-0008), dan target periode yang belum terkunci disesuaikan lewat buka-kembali (11.7, 11.9). Koreksi salah ketik nomor/tanggal PK tetap memakai 2.12. Sebelum task ini selesai, PK perubahan yang hanya mengubah anggaran memakai lampiran tambahan PK (2.20), sedangkan PK perubahan yang mengubah target ditangani sesuai keputusan baru PM (Q50 §50.7 butir 2).
- **Dependency:** 3.8 (alur koreksi snapshot berversi ISS-03.03, prasyarat), 2.11, 2.12, 2.20, 2.22, 3.7, 11.6, 11.7. Dijadwalkan pada tahap 3 Q46 §46.3 dan harus siap sebelum revisi PK pertama yang mengubah target.
- **DoD:** Test Pest: persetujuan revisi menghasilkan versi PK baru dengan nomor versi berikutnya, isian nomor/tanggal surat Eselon I, periode mulai berlaku, lampiran, dan target per indikator, sementara versi sebelumnya tidak berubah; PK awal dan PK akhir dapat diunduh; revisi yang mengubah target menerbitkan versi snapshot baru mulai periode pertama yang belum ditutup dengan `rujukan_koreksi` surat Eselon I, sedangkan periode yang sudah dilaporkan tetap merujuk target versi lama; revisi yang hanya mengganti dokumen tidak menerbitkan versi snapshot; capaian periode nilai akhir dinilai terhadap target PK akhir; Rencana Aksi tidak berubah otomatis dan pengajuan berikutnya menampilkan deviasi terhadap target PK baru yang wajib dijelaskan; revisi tanpa alasan ditolak; setiap revisi tercatat di `audit_log`.

---

## Modul 3 — Periode & Jadwal

### 3.1 Migrasi & seed master `periode`
- **Scope:** Migrasi tabel `periode` (nama, urutan, aktif, is_nilai_akhir) + form CRUD terbatas (Superadmin/Perencanaan); validasi tepat satu baris `is_nilai_akhir=true`.
- **Seed awal:** `PeriodeSeeder` dipanggil oleh `DatabaseSeeder`; hanya pada tabel kosong, isi Triwulan I–IV aktif dengan urutan 1–4 dan Triwulan IV sebagai nilai akhir, sesuai [addendum seed ISS-03.01](SAKIP%20-%20Keputusan%20Penyelarasan.md). Konfigurasi existing dipertahankan dengan pesan CLI, termasuk konfigurasi kustom yang berbeda dari default; tidak ada sinkronisasi ulang atau koreksi otomatis.
- **Dependency:** 1.11.
- **DoD:** Percobaan menyimpan periode kedua dengan `is_nilai_akhir=true` ditolak selama masih ada periode lain yang juga `true` (harus non-aktifkan yang lama dulu, ditegaskan via validasi aplikasi); test Pest mengonfirmasi hal ini.
- **DoD seed:** Test membuktikan integrasi setup melalui `DatabaseSeeder`, empat baris awal dengan tepat satu final aktif, pengulangan tanpa perubahan ID/konfigurasi, preservasi konfigurasi kustom, rollback seluruh bootstrap bila gagal, serta serialisasi dengan seeder lain dan mutasi master melalui lock konfigurasi existing.

### 3.2 Migrasi `jadwal_tahunan` (tanpa kolom jendela periode, dengan jendela rencana aksi)

> **Q41 (13 Oktober 2026):** `rencana_aksi_mulai`/`rencana_aksi_selesai` tidak lagi menjadi batas PIC dan dihapus dari form jadwal; kolomnya dibiarkan nullable sampai dihapus pada migrasi lanjutan. Penyesuaian dikerjakan pada 11.9 (ISS-05.07).

- **Scope:** Migrasi tabel `jadwal_tahunan` **tanpa** kolom `pengisian_mulai`, `pengisian_selesai`, `reviu_mulai`, `reviu_selesai` (dipindahkan ke `jadwal_periode`, lihat 3.3). Kolom yang tetap ada: `renstra_id`, `tahun`, `penutupan`, `pakai_persetujuan_pimpinan`, `persetujuan_mulai`, `persetujuan_selesai` (disiapkan, tidak dipakai logic Fase Awal), `status`, `renstra_pk_id`, `activated_at`, `closed_at`; ditambah kolom baru **`rencana_aksi_mulai`**, **`rencana_aksi_selesai`** (date, nullable); constraint unique (`renstra_id`, `tahun`) lintas seluruh status sesuai [addendum ISS-03.01](SAKIP%20-%20Keputusan%20Penyelarasan.md). Pasangan yang sudah memiliki jadwal memakai objek existing sesuai status dan permission, tanpa membuat jadwal kedua.
- **Dependency:** 2.1, 2.11 (renstra_pk).
- **DoD:** Migrasi berjalan; skema tabel `jadwal_tahunan` tidak memiliki satu pun kolom `pengisian_*`/`reviu_*` (diverifikasi via introspeksi skema di test Pest); kolom `rencana_aksi_mulai`/`rencana_aksi_selesai` ada di skema, nullable, dan dapat diisi independen dari kolom `jadwal_periode`; kolom-kolom approval Pimpinan ada di skema dan bernilai default (`false`/`null`) tanpa mempengaruhi logic manapun saat ini. Test memverifikasi penolakan jadwal kedua pada pasangan Renstra–tahun yang sama lintas status. Preflight menghentikan rollout bila data existing memiliki beberapa jadwal per pasangan, tanpa koreksi atau penghapusan otomatis.

### 3.3 Migrasi & CRUD `jadwal_periode`
- **Scope:** Migrasi tabel pivot baru `jadwal_periode` (jadwal_id, periode_id, pengisian_mulai, pengisian_selesai, reviu_mulai, reviu_selesai) + form (Inertia + React) bagi Perencanaan untuk memilih periode-periode yang diharapkan pada suatu Jadwal Tahunan dan mengisi jendela masing-masing; permission `jadwal:update`.
- **Dependency:** 3.2, 3.1.
- **DoD:** Baris `jadwal_periode` baru tersimpan dengan `jadwal_id` + `periode_id` unik per pasangan; validasi tanggal logis per baris (`pengisian_mulai` ≤ `pengisian_selesai` ≤ `reviu_mulai` ≤ `reviu_selesai`) ditegakkan dan diuji via Pest; satu Jadwal Tahunan dapat memiliki beberapa baris `jadwal_periode` (mis. 4 baris untuk Triwulan I–IV) dengan jendela yang berbeda-beda per baris.

### 3.4 CRUD Jadwal Tahunan (draft) + form jendela rencana aksi

> **Q41 (13 Oktober 2026):** Rencana Aksi tidak terikat jendela penyusunan; jadwal hanya mengikat pelaporan realisasi melalui jendela pengisian per periode (3.3). Input `rencana_aksi_mulai`/`rencana_aksi_selesai` beserta validasi urutannya dihapus dari form ini melalui 11.9 (ISS-05.07); menggantikan Q16.

- **Scope:** Form create/edit Jadwal Tahunan berstatus draft: pilih Renstra, tahun, tanggal penutupan, serta input `rencana_aksi_mulai`/`rencana_aksi_selesai`; permission `jadwal:create/update`. Validasi urutan tanggal logis: `rencana_aksi_mulai` ≤ `rencana_aksi_selesai`, dan disarankan (bukan wajib diblokir) berada sebelum jendela pengisian periode pertama.
- **Dependency:** 3.2.
- **DoD:** Jadwal baru tersimpan berstatus `draft` dengan `rencana_aksi_mulai`/`rencana_aksi_selesai` terisi; form tidak menampilkan/menerima field jendela pengisian/reviu tingkat tahun (field tersebut sudah tidak ada di skema, lihat 3.2) — pengaturan jendela periode dilakukan lewat 3.3; submit dengan `rencana_aksi_mulai` > `rencana_aksi_selesai` ditolak validasi.

### 3.5 Aksi aktivasi Jadwal + empat gerbang validasi

> **Q39/Q43/Q44 (13 Oktober 2026):** keempat gerbang tetap (Q39 §39.1 butir 5); gerbang 2 membaca target PK (`target_tahunan`) yang kini diisi di halaman PK (2.22). Alasan aktivasi jadwal menjadi opsional (Q43 §43.3 butir 3; penyesuaian `ActivateJadwalRequest` pada 10.7). Saklar unggahan dihapus (9.10, ISS-13.06) sehingga pengecualian gerbang 4 karena `tidak_dapat_dipenuhi` tidak lagi terjadi; penanda yang sudah tercatat tetap menjadi histori, dan skenario (e) pada DoD menjadi riwayat.

- **Scope:** Implementasi aksi `jadwal:aktivasi` dengan EMPAT gerbang berurutan: (1) `renstra_pk` untuk `(renstra_id, tahun)` jadwal sudah tercatat; (2) seluruh indikator berstatus `aktif` milik Renstra tersebut memiliki `target_tahunan` untuk tahun jadwal; (3) tahun jadwal berada dalam rentang `[tahun_mulai, tahun_akhir]` Renstra terkait; (4) BARU — `renstra_pk` untuk `(renstra_id, tahun)` tsb memiliki MINIMAL SATU lampiran dokumen (`berkasable_type = renstra_pk`, mode file/tautan/teks bebas), KECUALI ditandai `tidak_dapat_dipenuhi` (lihat 13.7 — dipicu saat unggahan file dimatikan sementara belum ada lampiran mode lain). Jika seluruh gerbang lolos: isi `renstra_pk_id` dan `activated_at`.
- **Dependency:** 3.4, 2.11, 2.10, 2.6, 2.20, 13.7.
- **DoD:** Test Pest terpisah untuk masing-masing gerbang: (a) aktivasi tanpa PK tahun terkait ditolak dengan pesan spesifik gerbang 1; (b) aktivasi dengan minimal satu indikator aktif tanpa target tahun tsb ditolak dengan pesan spesifik gerbang 2; (c) aktivasi dengan tahun jadwal di luar rentang Renstra ditolak dengan pesan spesifik gerbang 3; (d) BARU — aktivasi dengan `renstra_pk` tahun tsb belum memiliki satu pun lampiran ditolak dengan pesan spesifik gerbang 4, dan berhasil begitu satu lampiran (mode apa pun) ditambahkan; (e) aktivasi pada `renstra_pk` yang gerbang 4-nya ditandai `tidak_dapat_dipenuhi` (unggahan file dimatikan, belum ada lampiran mode lain) berhasil melewati gerbang 4 secara administratif; (f) aktivasi yang lolos keempat gerbang berhasil mengubah status ke `aktif`, mengisi `renstra_pk_id`, dan `activated_at` terisi timestamp saat itu; setiap penolakan (termasuk gerbang 4) tercatat sebagai audit_log percobaan gagal (lihat 10.5).

### 3.6 Migrasi & model `jadwal_snapshot` (kolom `unit_id`, `arah`, `tipe_perhitungan`, `baseline`)

> **Q47 (13 Oktober 2026):** snapshot memperoleh kolom salinan `dinilai_tahunan` yang disalin trigger 3.7 bersama atribut indikator lain; perubahan penanda setelah jadwal aktif mengikuti koreksi snapshot (3.8, Q35). Dikerjakan pada 11.9 (ISS-05.07).

- **Scope:** Migrasi tabel `jadwal_snapshot` (kolom salinan, bukan FK aktif untuk field non-indikator_id): `jadwal_id`, `indikator_id`, `unit_id`, `nama`, `definisi`, `satuan`, `presisi`, `desimal_tampilan`, `arah`, `target`, ditambah kolom baru **`tipe_perhitungan`**, **`baseline`**. Kolom `arah`, `tipe_perhitungan`, `baseline` disalin dari master sebagai bagian konteks beku.
- **Dependency:** 3.2, 2.6, 2.10.
- **DoD:** Migrasi berjalan; model dapat diisi dan dibaca sesuai struktur snapshot; kolom bernama `unit_id` (bukan `tim_kerja_id`) dan mencakup `arah`, `tipe_perhitungan`, `baseline`.

### 3.7 Trigger idempoten pembuatan `jadwal_snapshot` saat aktivasi/buka-kembali
- **Scope:** Logic aplikasi (dipanggil dari 3.5 dan dari 3.9 saat `jadwal:buka_kembali`) yang mengambil seluruh indikator aktif milik Renstra terkait dan, untuk tiap indikator, **hanya** membuat baris `jadwal_snapshot` baru jika pasangan `(jadwal_id, indikator_id)` belum memiliki baris — baris yang sudah ada dilewati, tidak pernah ditimpa. Baris baru menyalin nama/definisi/satuan/presisi/desimal_tampilan/unit_id/arah/tipe_perhitungan/baseline dari master serta `target_tahunan` untuk tahun jadwal. Audit log pembuatan snapshot memakai `actor_id` = pengguna yang menjalankan aksi aktivasi/buka_kembali.
- **Dependency:** 3.5, 2.7, 2.10, 3.6.
- **DoD:** Setelah aktivasi jadwal dengan N indikator aktif terkait, query `jadwal_snapshot WHERE jadwal_id = ...` mengembalikan tepat N baris; memanggil logic ini ulang tanpa perubahan data (idempoten) tidak menambah baris baru maupun mengubah baris lama; setelah menambahkan 1 indikator baru dan memanggil ulang logic ini (mensimulasikan `jadwal:buka_kembali`), hanya 1 baris baru terbentuk sementara N baris lama tidak berubah; mengubah `indikator.nama` master setelah snapshot terbentuk tidak mengubah nilai `nama` pada baris snapshot yang sudah ada; audit_log baris snapshot baru memiliki `actor_id` sama dengan pelaku aktivasi/buka_kembali.

### 3.8 Guard imutabilitas baris `jadwal_snapshot` sejak terbit

> **Q40 (13 Oktober 2026):** alur koreksi snapshot berversi (ISS-03.03) menjadi prasyarat revisi PK berversi (2.23); keduanya dijadwalkan pada tahap 3 Q46 §46.3.

- **Scope:** guard database yang menolak perubahan in-place pada baris `jadwal_snapshot` dan `jadwal_snapshot_komponen` sejak snapshot terbit, baik sudah maupun belum dirujuk (Q35 §35.1); koreksi konteks selalu dibuat sebagai versi baru yang tercatat audit_log.
- **Dependency:** 3.7, 5.1 (pengukuran, untuk mengecek rujukan).
- **DoD:** Test Pest: percobaan mengubah atau menghapus baris snapshot yang sudah terbit ditolak dengan pesan spesifik, baik sudah maupun belum dirujuk; penyisipan komponen ke snapshot yang sudah final ditolak; penyisipan versi snapshot baru tetap diterima.

### 3.9 Aksi tutup & buka kembali Jadwal (mekanisme standar)

> **Q48 (13 Oktober 2026):** penutupan jadwal hanya menghentikan mutasi data kinerja. Unggahan arsip PDF bertanda tangan Rencana Aksi tahunan dan Laporan Kinerja Triwulan tetap dapat dilakukan pada jadwal `ditutup` karena tidak mengubah data, status, maupun snapshot (Q48 §48.2 butir 5, 8.5).

> **Q52 (14 Oktober 2026):** penutupan jadwal (`jadwal:tutup`) juga wajib alasan pengguna; alasan divalidasi dan disanitasi server, lalu dicatat di `audit_log` bersama `dasar_izin` (Q52 §52.1 butir 1; ISS-14.01, PR #109). Daftar Rencana Aksi dan Pengukuran tahun yang ditutup tetap terbaca baca-saja (ISS-14.01 AC-6; PR #117). Buka kembali dan sesi koreksi tetap mengikuti ISS-14.02.

- **Scope:** Transisi status `aktif → ditutup` (mengisi `closed_at`) dan `ditutup → aktif` (`jadwal:buka_kembali`, alasan wajib, memicu ulang trigger idempoten 3.7); permission `jadwal:tutup`, `jadwal:buka_kembali`. Dokumentasikan dalam kode/komentar bahwa buka_kembali adalah jalur standar untuk koreksi pasca-penutupan maupun penambahan indikator baru di tengah tahun — bukan jalur darurat semata.
- **Dependency:** 3.5, 3.7.
- **DoD:** Test Pest memverifikasi transisi status dan pengisian/reset `closed_at` sesuai arah transisi; percobaan `jadwal:buka_kembali` tanpa alasan ditolak; setiap transisi tercatat audit_log; setelah buka_kembali, indikator baru yang ditambahkan sejak jadwal ditutup mendapat baris snapshot baru (menguji integrasi dengan 3.7) tanpa mengubah baris snapshot lama.

### 3.10 Tampilan status jadwal & indikator per jendela waktu periode (jendela rencana aksi dihapus Q41)
- **Scope:** Halaman/komponen yang menampilkan jadwal aktif beserta status jendela pengisian/reviu **per periode** (dari `jadwal_periode`, bukan dari `jadwal_tahunan`), berjalan berdasarkan tanggal hari ini vs kolom `jadwal_periode` yang relevan. Tampilan status jendela **rencana aksi** tingkat tahun (semula dari `jadwal_tahunan.rencana_aksi_mulai/selesai`, mis. "Masa penyusunan rencana aksi dibuka/ditutup") dihapus karena Rencana Aksi tidak lagi memakai jendela penyusunan (Q41, 11.9).
- **Dependency:** 3.5, 3.3, 3.4.
- **DoD:** Komponen menampilkan label benar (mis. "Masa pengisian Triwulan II", "Masa reviu Triwulan II", "Periode Triwulan I ditutup") sesuai tanggal sistem saat ini dibandingkan kolom `jadwal_periode` masing-masing, diuji dengan Pest menggunakan `Carbon::setTestNow()` pada beberapa tanggal dan periode berbeda dalam jadwal yang sama; label jendela penyusunan rencana aksi tidak lagi ditampilkan (Q41).

---

### 3.11 Pengisian Periode Lampau 2026 — tanpa mekanisme backfill khusus
- **Scope:** dukung TW I–III 2026 (Q46; semula TW I–II) sebagai periode lampau yang dihitung dari `pengisian_selesai < activated_at`; dengan pola pelaporan pada minggu pertama bulan setelah triwulan, TW IV 2026 menjadi triwulan pertama workflow normal PIC operasional dengan pelaporan awal Januari 2027. Tidak menambah `is_backfill`, `retroaktif`, atau flag khusus. Pada aktivasi Jadwal 2026, PK tahun berjalan tetap menjadi gerbang; pada pengisian periode lampau Perencanaan dapat mengisi tanpa gerbang RA/komponen/bukti normal yang sudah tidak realistis untuk periode lewat. Pengecualian yang sama berlaku untuk gerbang baru Q42–Q45 (Q46 §46.1 butir 1): RA minimal satu kegiatan (12.6), RAB dan persyaratan tahap `kegiatan_perencanaan` (12.12), narasi wajib (5.5), dan tindak lanjut rekomendasi (5.5, 6.7). Kunci per triwulan (11.9) bukan bagian pengecualian ini: periode lampau saat aktivasi sudah terkunci bagi PIC sejak aktivasi, sedangkan Perencanaan yang mengisinya tidak terikat kunci (Q41 §41.2 butir 3–4; dikoreksi Q46 §46.1 butir 1, semula kunci per triwulan ikut disebut sebagai gerbang yang dikecualikan). Target periode lampau boleh kosong; data awal 2026 mencatat target TW I–III kosong untuk semua indikator (Q47 §47.2 butir 4, 11.9). Data awal 2026 diisi Perencanaan dari dokumen resmi 2026: target PK, target periode dan kegiatan Rencana Aksi, serta realisasi, narasi, dan rekomendasi TW I–II (Q46 §46.1 butir 6). RA 2026 yang dicatat Perencanaan sebagai data awal dikecualikan dari gerbang kegiatan minimal dan RAB; kegiatan TW IV ditambahkan lewat klaim tahap pengukuran atau buka-kembali RA oleh Perencanaan. Setiap task yang menambah gerbang Q42–Q45 menyertakan pengecualian periode lampau beserta test-nya, sedangkan task kunci per triwulan (11.9) menyertakan test bahwa periode lampau terkunci bagi PIC (Q46 §46.1 butir 1 dan 4).
- **Dependency:** 2.11, 3.3–3.7, pengukuran.
- **DoD:** test membuktikan TW I–III teridentifikasi sebagai lampau dari tanggal dan TW IV normal (Q46; semula TW I–II lampau, TW III–IV normal); Pegawai dengan grant tetap terkunci pada jendela lewat; Perencanaan dapat mengisi; tidak ada kolom/flag backfill; 2025 tidak dibuat sebagai jadwal/pengukuran; gerbang Q42–Q45 tidak menghalangi pengisian periode lampau oleh Perencanaan, dibuktikan oleh test pengecualian pada masing-masing task penambah gerbang, sedangkan PIC tetap ditolak mengubah Rencana Aksi pada periode lampau (11.9); Rencana Aksi data awal 2026 dengan target TW I–III kosong dapat dicatat dan disahkan Perencanaan (11.9).

### 3.12 Revisi resmi jendela PIC dan pembukaan jendela dalam sesi koreksi (ISS-03.06, ISS-14.03)
- **Scope:** Q18, Q41 §41.1 butir 3, Q57. Aksi beralasan `jadwal:update` pada jadwal `aktif` yang belum melewati `penutupan` untuk mengubah `pengisian_mulai`, `pengisian_selesai`, `reviu_mulai`, dan `reviu_selesai` setiap periode anggota jadwal dalam satu aksi; urutan `pengisian_selesai ≤ reviu_mulai ≤ reviu_selesai` ditegakkan constraint basis data dan aturan kalender; audit `jadwal.revisi_jendela` menyimpan alasan, `dasar_izin`, dan seluruh jendela sebelum/sesudah revisi; penanda `reviu_terlambat` yang sudah tercatat tetap beku, reviu sesudahnya dinilai terhadap jendela reviu yang berlaku. Revisi tidak memberi akses tambahan kepada PIC: grant unit, PIC efektif, deny, status record, dan gerbang kelengkapan tetap diperiksa (11.9 membaca `pengisian_selesai` yang berlaku untuk kunci RA per periode). Pembukaan jendela PIC di dalam sesi koreksi pascapenutupan (ISS-14.03) memakai aksi yang sama dengan batas wajib di dalam `koreksi_mulai..koreksi_sampai` dan hanya untuk lingkup koreksi; `jadwal:buka_kembali` tanpa revisi jendela tidak membuka akses PIC.
- **Dependency:** 3.3, 3.5, 3.9, 3.10, 10.1; 11.9 sebagai konsumen kunci per periode.
- **DoD:** Bagian jadwal `aktif` selesai pada ISS-03.06 (PR #120): `JadwalRevisiJendelaTest` dan `PengukuranRevisiJendelaTest` membuktikan revisi beralasan, audit jendela lama/baru, penolakan PIC di luar jendela yang berlaku, dan pembekuan `reviu_terlambat`. Bagian sesi koreksi menunggu ISS-14.02 dan ISS-14.03.

## Modul 4 — Penugasan (Penanggung Jawab)

### 4.1 Migrasi & model `penanggung_jawab`
- **Scope:** Migrasi tabel sesuai data model.
- **Dependency:** 2.6 (indikator), 1.3 (users).
- **DoD:** Migrasi berjalan; baris dapat dibuat dengan `alasan` nullable untuk penugasan pertama.

### 4.2 Form penugasan awal Penanggung Jawab
- **Scope:** halaman Inertia + komponen React untuk menetapkan penanggung jawab pertama suatu indikator (tanggal_mulai_berlaku, user_id); permission `penanggung_jawab:update`.
- **Dependency:** 4.1.
- **DoD:** Penugasan pertama tersimpan tanpa mewajibkan `alasan`; baris tercatat di audit_log.

### 4.3 Fitur pergantian Penanggung Jawab (wajib alasan — digantikan Q43)

> **Q43 (13 Oktober 2026):** kolom alasan pada penetapan dan pergantian Penanggung Jawab dihapus dari form; kolom basis datanya sudah nullable. Histori pergantian tetap append-only dan teraudit. Penyesuaian dikerjakan pada 10.7 (ISS-13.05); kewajiban alasan pada scope dan DoD di bawah menjadi riwayat.

- **Scope:** Alur khusus untuk menambah baris baru penugasan pada indikator yang sudah punya penanggung jawab sebelumnya, mewajibkan `alasan`.
- **Dependency:** 4.2.
- **DoD:** Test Pest: submit pergantian tanpa alasan pada indikator yang sudah punya PJ sebelumnya ditolak; submit dengan alasan berhasil menambah baris baru (baris lama tidak terhapus/termodifikasi) dan tercatat audit_log.

### 4.4 Query/fungsi resolusi Penanggung Jawab Efektif
- **Scope:** Implementasi fungsi/scope Eloquent yang mengembalikan baris `penanggung_jawab` dengan `tanggal_mulai_berlaku` maksimum yang ≤ tanggal acuan tertentu, untuk suatu indikator. Fungsi ini dipakai baik oleh alur pengukuran maupun alur rencana aksi (Modul 11) untuk menentukan PIC efektif.
- **Dependency:** 4.1.
- **DoD:** Test Pest dengan 3 baris riwayat penugasan bertanggal berbeda mengonfirmasi fungsi mengembalikan baris yang benar untuk beberapa tanggal acuan berbeda, termasuk tanggal acuan di masa lalu (sebelum pergantian terakhir); fungsi yang sama dipakai ulang tanpa duplikasi logic oleh service rencana aksi (dibuktikan lewat referensi kode/pemanggilan fungsi yang sama pada test Modul 11).

### 4.5 Validasi Penanggung Jawab final Q32 + monitoring hak isi
- **Scope:** target PJ boleh user aktif dengan role apa pun atau tanpa perubahan role khusus. Assignment tidak memberi permission. Form memberi warning non-blocking jika user belum memiliki grant kerja yang cocok dengan unit indikator dan menyediakan daftar “PJ aktif tanpa hak isi”. Perubahan role tidak menonaktifkan assignment.
- **Dependency:** 4.1–4.4, resolver/grant.
- **DoD:** test: user aktif dapat ditetapkan tanpa role PIC; user nonaktif ditolak; calon PJ tanpa grant tetap dapat ditetapkan tetapi menghasilkan warning/monitoring; setelah perubahan role assignment tetap efektif; grant/revoke terpisah dari assignment.

## Modul 5 — Pengukuran

### 5.1 Migrasi & model `pengukuran`

> **Q45/Q47 (13 Oktober 2026):** `pengukuran` (tabel aplikasi `pengukuran_kinerjas`) memperoleh isian narasi `progres_kegiatan`, `kendala_masalah`, `strategi_tindak_lanjut`, dan `tindak_lanjut_rekomendasi` (Q45 §45.1–§45.2, 5.5). Realisasi belum dapat diukur dicatat sebagai nilai enum keempat `status_perhitungan = belum_dapat_diukur` dengan `nilai` kosong, di samping `belum_diisi`, `terhitung`, dan `tidak_dapat_dihitung` — bukan kolom boolean terpisah; migrasinya memperluas enum dan CHECK `nilai_status_cocok` (Q47 §47.4 butir 1, 5.5).

- **Scope:** Migrasi tabel sesuai data model, termasuk kolom `versi` (default 1), unique constraint `(indikator_id, tahun, periode_id)`, dan kolom baru **`sumber_nilai`** enum(`komponen`,`manual`) untuk mencatat asal nilai `pengukuran.nilai`.
- **Dependency:** 3.6 (jadwal_snapshot), 1.11.
- **DoD:** Migrasi berjalan; percobaan insert baris duplikat kombinasi indikator+tahun+periode ditolak database; kolom `sumber_nilai` tersedia dengan kedua nilai enum.

### 5.2 Buat Draft Pengukuran (jalur PIC scoped, Perencanaan global)
- **Scope:** halaman Inertia + komponen React untuk membuat baris pengukuran baru berstatus `draft`, terhubung ke `jadwal_snapshot_id` yang relevan (indikator+tahun+periode pada jadwal aktif). Pemohon dengan permission ber-scope unit (jalur PIC operasional) hanya dapat membuat untuk indikator unit-nya; pemohon dengan permission global (Perencanaan) dapat membuat untuk indikator unit mana pun.
- **Dependency:** 5.1, 3.7, 4.4, 1.11.
- **DoD:** User dengan `pengukuran:create` di-scope unit yang sesuai berhasil membuat draft untuk indikator unit tersebut, dan ditolak (403) untuk indikator unit lain; user dengan `pengukuran:create` global (unit_id NULL) berhasil membuat draft untuk indikator unit mana pun; baris baru memiliki `versi = 1` dan `status_alur = draft`; percobaan membuat pengukuran untuk indikator berstatus `arsip` ditolak untuk kedua jenis pemohon (lihat 2.9).

### 5.3 Edit nilai/catatan Draft (manual)
- **Scope:** Form edit nilai dan catatan pada pengukuran berstatus draft **untuk indikator bertipe `manual`**; menaikkan `versi` setiap simpan; permission `pengukuran:update` (scoped untuk jalur PIC operasional, global untuk Perencanaan). Untuk indikator bertipe `rasio_persen`/`penjumlahan`, field `nilai` ditampilkan read-only (lihat 5.13). Form draf, baik manual (task ini) maupun berkomponen (5.14), memuat isian narasi pengukuran dan `catatan` sebagai keterangan tambahan opsional (Q45; kewajiban narasi divalidasi pada 5.5), serta pilihan eksplisit realisasi **Belum dapat diukur** yang menyimpan `status_perhitungan = belum_dapat_diukur` dengan `nilai` kosong (Q47 §47.4, 5.5).
- **Dependency:** 5.2.
- **DoD:** Setiap submit edit berhasil menaikkan kolom `versi` sebanyak 1; submit dengan `versi` yang dikirim klien tidak sesuai versi terbaru di database ditolak dengan pesan konflik (test Pest mensimulasikan dua edit berurutan dengan versi usang pada percobaan kedua); form untuk indikator `tipe_perhitungan=manual` menampilkan field nilai sebagai input bebas.

### 5.4 Guard deadline jendela pengisian periode bagi PIC
- **Scope:** Lapisan validasi bisnis (terpisah dari lapisan permission) yang menolak `pengukuran:create`/`update`/aksi ajukan oleh aktor jalur PIC (permission ber-scope unit) begitu tanggal hari ini melewati `jadwal_periode.pengisian_selesai` periode terkait. Permintaan dari pemegang permission global (Perencanaan) **dikecualikan** dari guard ini — hanya dibatasi oleh `jadwal_tahunan.penutupan`.
- **Dependency:** 5.2, 5.3, 3.3.
- **DoD:** Test Pest dengan `Carbon::setTestNow()`: aktor jalur PIC mencoba create/update/ajukan setelah `pengisian_selesai` periode terkait ditolak dengan pesan spesifik deadline; Perencanaan (permission global) berhasil melakukan aksi yang sama pada tanggal yang sama; Perencanaan tetap ditolak jika mencoba melakukannya setelah `jadwal_tahunan.penutupan` tercapai.

### 5.5 Ajukan Pengukuran (narasi wajib — Q45; semula catatan wajib berbasis arah)

> **Q45 (13 Oktober 2026):** kewajiban `catatan` berbasis arah dan berbasis `indikator.wajib_catatan` digantikan narasi pengukuran wajib. Scope dan DoD di bawah sudah diselaraskan; aturan lama dicatat sebagai riwayat pada kalimat "Semula". Q47 dan Q51 menambah aturan realisasi belum dapat diukur dan tindak lanjut hanya atas rekomendasi yang disetujui.

- **Scope:** Aksi transisi `draft → diajukan`, dengan validasi narasi pengukuran wajib per indikator per periode: **progres kegiatan** (`progres_kegiatan`), **kendala/masalah** (`kendala_masalah`), dan **tindak lanjut/strategi penyelesaian** (`strategi_tindak_lanjut`) (Q45 §45.1 butir 1). Narasi diisi pada draf (5.3/5.14) dan diperiksa saat pengajuan. Kolom `catatan` tetap tersedia sebagai keterangan tambahan opsional; saklar `wajib_catatan` disembunyikan dari form indikator (2.7) tanpa menghapus kolomnya. Narasi per kegiatan (12.5) tetap opsional. Bila terdapat Rekomendasi Pimpinan berstatus **disetujui** pada periode N, pengajuan pengukuran periode N+1 wajib memuat isian tindak lanjut atas rekomendasi (`tindak_lanjut_rekomendasi`; Q45 §45.2 butir 3, Q51 §51.1 butir 2); draf rekomendasi tidak memicu kewajiban ini. Kewajiban berlaku lintas tahun: rekomendasi disetujui pada periode terakhir suatu tahun ditindaklanjuti pada periode pertama jadwal tahun berikutnya bila indikatornya masih diukur (Q51 §51.1 butir 4). Gerbang tindak lanjut dipasang pada aksi ajukan ini tetapi dibangun bersama 6.7 (ISS-09.01) pada tahap 2 (Q46 §46.3, Q51 §51.3). **Realisasi belum dapat diukur (Q47 §47.4):** pengukuran dapat diajukan dengan `status_perhitungan = belum_dapat_diukur` dan `nilai` kosong bila nilainya belum tersedia pada periode tersebut; status ini dipilih eksplisit pada draf, bukan disimpulkan dari nilai kosong; narasi tetap wajib dan menjelaskan mengapa nilai belum tersedia; gerbang kelengkapan komponen dikecualikan (5.16); status ini dibedakan dari realisasi `0` dan dari `tidak_dapat_dihitung` (Q9, 5.13); pada periode nilai akhir (`periode.is_nilai_akhir`) realisasi wajib terisi kecuali tidak dapat dihitung menurut Q9. Narasi wajib dan tindak lanjut rekomendasi dikecualikan untuk periode lampau (3.11). Semula: catatan wajib bila (a) nilai **memburuk menurut `indikator.arah`** dibanding pengukuran berstatus **Disahkan terakhir secara kronologis** atau (b) `indikator.wajib_catatan = true` — digantikan Q45.
- **Dependency:** 5.1, 5.3, 5.4, 5.13, 5.14, 2.7, 3.11; 6.7 (ISS-09.01) untuk gerbang tindak lanjut rekomendasi.
- **DoD:** Test Pest: pengajuan tanpa salah satu dari ketiga narasi ditolak dengan pesan spesifik per isian; pengajuan dengan ketiga narasi terisi dan `catatan` kosong **diterima**, termasuk saat nilai memburuk menurut `indikator.arah` atau `indikator.wajib_catatan = true`; saklar `wajib_catatan` disembunyikan dari form indikator (Q45) sementara kolomnya tetap ada; pengajuan valid mengubah `status_alur` menjadi `diajukan` dan tercatat audit_log; pengajuan oleh aktor jalur PIC setelah deadline periode (5.4) tetap ditolak terlepas kelengkapan narasi; pengisian periode lampau oleh Perencanaan tidak terhalang narasi wajib; pengajuan `belum_dapat_diukur` dengan narasi lengkap pada periode sebelum periode nilai akhir diterima dan tersimpan dengan `nilai` kosong, terbedakan dari realisasi `0` maupun `tidak_dapat_dihitung`; pengajuan `belum_dapat_diukur` pada periode nilai akhir ditolak; penyimpanan `nilai` terisi bersama `belum_dapat_diukur` ditolak CHECK `nilai_status_cocok`; setelah 6.7 tersedia, pengajuan periode N+1 tanpa isian tindak lanjut atas rekomendasi disetujui periode N ditolak, sedangkan pengajuan yang periode N-nya tanpa rekomendasi atau hanya memiliki draf rekomendasi diterima tanpa isian tersebut; rekomendasi disetujui pada periode terakhir tahun T mewajibkan isian tindak lanjut pada periode pertama jadwal tahun T+1 bila indikatornya masih diukur.

### 5.6 Verifikasi oleh Perencanaan

> **Q47 dan ketentuan pelengkap Q47–Q51 (13 Oktober 2026):** saat Perencanaan memverifikasi, sistem menampilkan peringatan bila realisasi atau narasi sama persis dengan periode sebelumnya; peringatan tidak memblokir verifikasi (pengantar Q47–Q51, ketentuan pelengkap butir 2). Perencanaan juga menilai penjelasan realisasi belum dapat diukur dan dapat mengembalikan pengukuran bila penjelasannya tidak dapat diterima (Q47 §47.4 butir 2, 5.7). Dikerjakan pada ISS-08.01 (tahap 2).

- **Scope:** Aksi transisi `diajukan → diverifikasi` oleh pemegang `pengukuran:verifikasi`.
- **Dependency:** 5.5.
- **DoD:** User dengan permission `pengukuran:verifikasi` berhasil mengubah status; user tanpa permission tsb mendapat 403; transisi tercatat audit_log.

### 5.7 Kembalikan dengan alasan (pra-pengesahan)
- **Scope:** Aksi transisi `diajukan atau diverifikasi → dikembalikan`, mewajibkan pengisian alasan; notifikasi/alert kontekstual ke penanggung jawab terkait.
- **Dependency:** 5.6.
- **DoD:** Submit pengembalian tanpa alasan ditolak validasi; pengembalian valid mengubah status menjadi `dikembalikan`, tercatat audit_log dengan `alasan` terisi, dan baris menjadi dapat diedit kembali (efektif kembali ke alur draft).

### 5.8 Revisi pasca-dikembalikan
- **Scope:** Memastikan pengukuran berstatus `dikembalikan` dapat diedit ulang oleh pemegang scope yang sesuai (jalur PIC operasional atau Perencanaan) dan diajukan kembali (transisi kembali memakai alur 5.5).
- **Dependency:** 5.7, 5.5.
- **DoD:** Test Pest: pengukuran berstatus `dikembalikan` dapat diubah nilainya oleh pemegang scope yang sesuai, lalu diajukan ulang, dan validasi 5.5, 5.4, dan gerbang 5.16 tetap berlaku pada pengajuan ulang ini.

### 5.9 Sahkan oleh Perencanaan (tanpa approval Pimpinan)
- **Scope:** Aksi transisi `diverifikasi → disahkan` oleh pemegang `pengukuran:sahkan`, tanpa syarat tambahan approval Pimpinan pada Fase Awal.
- **Dependency:** 5.6.
- **DoD:** Test Pest: transisi berhasil tanpa memerlukan baris apapun terkait `pengukuran:setujui`; user hanya dengan `pengukuran:verifikasi` (tanpa `pengukuran:sahkan`) mendapat 403 saat mencoba mengesahkan; transisi tercatat audit_log.

### 5.10 Buka-kembali Pengukuran Disahkan (`pengukuran:buka_kembali`)
- **Scope:** Aksi transisi `disahkan → dikembalikan` oleh pemegang `pengukuran:buka_kembali` (Perencanaan/Superadmin), mewajibkan alasan, **hanya tersedia** selama `jadwal_tahunan` terkait belum mencapai `penutupan`. Setelah baris kembali ke `dikembalikan`, mengikuti alur revisi biasa (5.8).
- **Dependency:** 5.9, 3.9 (status penutupan jadwal).
- **DoD:** Test Pest: buka-kembali pada pengukuran Disahkan yang jadwalnya belum penutupan berhasil mengubah status ke `dikembalikan` dan tercatat audit_log dengan alasan; percobaan pada jadwal yang sudah `penutupan` ditolak dengan pesan spesifik yang mengarahkan ke `jadwal:buka_kembali` sebagai jalur alternatif; percobaan tanpa alasan ditolak; user tanpa `pengukuran:buka_kembali` mendapat 403.

### 5.11 Larangan penghapusan permanen data bermakna
- **Scope:** Guard aplikasi (Policy/Observer) yang menolak operasi delete pada baris `pengukuran` yang memiliki `nilai` atau `catatan` terisi, di seluruh titik masuk (UI maupun akses langsung ke model).
- **Dependency:** 5.1.
- **DoD:** Test Pest: percobaan delete pengukuran dengan nilai terisi menghasilkan exception/response ditolak, dan tercatat sebagai audit_log bertindakan "percobaan_hapus_ditolak" atau setara.

### 5.12 Validasi scope unit pada create/update Pengukuran
- **Scope:** Menyatukan pengecekan dari 1.11 secara spesifik pada endpoint create/update pengukuran — memastikan `indikator.unit_id` dicocokkan terhadap scope permission user yang login (kecuali pemegang permission global).
- **Dependency:** 5.2, 5.3, 1.11.
- **DoD:** Test Pest end-to-end: user dengan scope unit A gagal (403) membuat/mengubah pengukuran indikator milik unit B, berhasil untuk indikator milik unit A; user dengan permission global (Perencanaan) berhasil untuk indikator milik unit apa pun.

### 5.13 Migrasi `pengukuran_komponen` & mesin perhitungan nilai turunan

> **Q47 (13 Oktober 2026):** hasil penyebut nol tetap `tidak_dapat_dihitung` (Q9) dan dibedakan dari `belum_dapat_diukur`, yang dipilih eksplisit saat nilai belum tersedia (5.5); keduanya bukan nilai `0` dan tidak dihitung sebagai capaian 0 (Q47 §47.4 butir 3). Pembedaan dikerjakan pada ISS-07.06.

- **Scope:** Migrasi tabel baru `pengukuran_komponen`: `id`, `pengukuran_id` (FK), `komponen_id` (FK → indikator_komponen), `nilai` (numeric, nullable), `updated_by`, `updated_at`, unique(`pengukuran_id`, `komponen_id`). Implementasi service mesin perhitungan yang menghitung `pengukuran.nilai` dari `pengukuran_komponen` sesuai `indikator.tipe_perhitungan`: `rasio_persen` = (Σ(pembilang_i × bobot_i) ÷ (penyebut × bobot)) × 100; `penjumlahan` = Σ(penjumlah_i × bobot_i). Pembagian dengan penyebut 0 menghasilkan nilai `null` ("tidak dapat dihitung"), bukan `0` atau galat. Nilai dibulatkan sesuai `indikator.presisi`.
- **Dependency:** 5.1, 2.14, 2.15.
- **DoD:** Test Pest: indikator `rasio_persen` dengan komponen pembilang=80, penyebut=100, bobot=1 menghasilkan nilai turunan 80; kasus multi-pembilang berbobot `(a×k1 + b×k2)/t×100` menghasilkan angka sesuai perhitungan manual yang diuji dengan minimal 2 skenario angka berbeda; indikator `penjumlahan` dengan 3 komponen (Lektor, Lektor Kepala, Guru Besar) menjumlahkan ketiganya sesuai bobot; komponen penyebut bernilai 0 menghasilkan `pengukuran.nilai = null` dan flag "tidak dapat dihitung" pada respons, bukan exception atau `0`; hasil dibulatkan sesuai `indikator.presisi` yang diset berbeda-beda pada minimal 2 skenario.

### 5.14 UI pengisian nilai komponen & nilai turunan read-only
- **Scope:** halaman Inertia + komponen React form pengisian pengukuran untuk indikator bertipe `rasio_persen`/`penjumlahan`: menampilkan input per komponen aktif (sesuai definisi snapshot `jadwal_snapshot_komponen`), memanggil mesin perhitungan (5.13) secara reaktif untuk menampilkan `pengukuran.nilai` sebagai field READ-ONLY, mengisi `sumber_nilai = komponen` otomatis. Untuk indikator `manual`, form tetap seperti 5.3 dengan `sumber_nilai = manual`.
- **Dependency:** 5.13, 3.7 (snapshot komponen).
- **DoD:** frontend test (Vitest + React Testing Library): mengubah nilai salah satu komponen pada form memperbarui tampilan nilai turunan secara reaktif tanpa reload halaman penuh; test Pest memastikan nilai yang tersimpan tetap hasil hitungan sisi server; field `pengukuran.nilai` tidak dapat diketik langsung (disabled/readonly) untuk indikator berkomponen, dibuktikan dengan percobaan submit payload yang memaksa nilai `pengukuran.nilai` custom — nilai yang tersimpan tetap hasil hitungan sistem, bukan payload custom tsb.

### 5.15 Migrasi `jadwal_snapshot_komponen`
- **Scope:** Migrasi tabel anak baru `jadwal_snapshot_komponen`: `jadwal_snapshot_id` (FK), `kode`, `label`, `peran`, `bobot`, `urutan`. Diisi oleh trigger snapshot (3.7 diperluas) saat aktivasi/buka-kembali jadwal, menyalin seluruh komponen aktif indikator pada saat itu.
- **Dependency:** 3.6, 3.7, 2.14.
- **DoD:** Setelah aktivasi jadwal untuk indikator berkomponen dengan N komponen aktif, `jadwal_snapshot_komponen WHERE jadwal_snapshot_id = ...` menghasilkan tepat N baris; mengubah definisi `indikator_komponen` (bobot/label) setelah snapshot terbentuk tidak mengubah baris snapshot komponen yang sudah ada (test Pest membandingkan nilai sebelum dan sesudah perubahan master); form pengisian komponen (5.14) membaca definisi dari `jadwal_snapshot_komponen`, bukan langsung dari `indikator_komponen` master.

### 5.16 Gerbang kelengkapan pengajuan: rencana aksi disahkan, komponen lengkap, bukti dukung lengkap
- **Scope:** Perluasan aksi ajukan (5.5) dengan tiga gerbang tambahan yang dievaluasi sebelum transisi `draft → diajukan` diterima: (a) `rencana_aksi` untuk (indikator × tahun) harus berstatus `disahkan` (tetap berlaku, Q41 §41.3); (b) tidak ada `pengukuran_komponen` aktif bernilai `null` untuk pengukuran ini, kecuali pengukuran berstatus `belum_dapat_diukur` (Q47 §47.4 butir 1); (c) seluruh `jenis_berkas` aktif bertanda `wajib` tahap `pengukuran` untuk indikator ini sudah terpenuhi menurut mode yang dipilih dan aturan `semua_mode_wajib` (lihat 13.4); pengecualian `tidak_dapat_dipenuhi` (13.7) tidak lagi terbentuk karena saklar unggahan dihapus (Q44), sedangkan penanda lama tetap histori. Ketiga gerbang, beserta gerbang Q45 pada 5.5, DIKECUALIKAN untuk periode lampau (3.11), yaitu periode yang jendela pengisiannya sudah berakhir saat jadwal diaktifkan, tanpa kolom/flag retroaktif (Q32.7, Q46 §46.1; semula ditulis sebagai jadwal retroaktif bertanda flag).
- **Dependency:** 5.5, 11.3 (rencana_aksi disahkan), 5.13 (komponen), 13.4 (gerbang bukti dukung), 3.11 (periode lampau).
- **DoD:** Test Pest: pengajuan pengukuran untuk indikator yang rencana aksinya belum `disahkan` ditolak dengan pesan spesifik gerbang (a); pengajuan dengan komponen aktif bernilai null ditolak dengan pesan spesifik gerbang (b), sedangkan pengajuan `belum_dapat_diukur` dengan komponen kosong dan narasi lengkap tidak terhalang gerbang (b); pengajuan tanpa bukti dukung wajib tahap pengukuran ditolak dengan pesan spesifik gerbang (c) (pengecualian `tidak_dapat_dipenuhi` dihapus bersama saklar unggahan, Q44); pengajuan yang lolos ketiganya berhasil; pengisian periode lampau oleh Perencanaan berhasil diajukan meski salah satu dari ketiga syarat di atas belum terpenuhi (dibuktikan test khusus periode lampau tanpa flag, Q46); setiap penolakan tercatat audit_log percobaan gagal.

### 5.17 Uji mesin perhitungan dengan kasus nyata: 8 indikator kinerja 2026

> **Q47/Q50 (13 Oktober 2026):** kode indikator mengikuti PK: delapan indikator Renstra berkode IKU 1.1–IKU 3.3, dan PK 2026 memuat IKU 4.1 (sasaran S4) sebagai indikator kesembilan (Q50 §50.4–§50.5). Formula dan komponen indikator 2026 yang belum dikonfirmasi Perencanaan hanya dipakai sebagai data uji; komponen IKU 3.3 tidak disetel sampai definisinya jelas. Penanda dinilai tahunan pada IKU 1.3 tidak mengubah formula maupun komponennya (Q47 §47.1 butir 3).

- **Scope:** Suite test Pest yang memvalidasi mesin perhitungan (5.13) terhadap fixture data nyata: 8 indikator kinerja tahun 2026 yang dipetakan pada bab lampiran PRD ("Lampiran: Pemetaan Indikator 2026 ke Definisi Komponen"). Fixture mencakup kelima varian formula yang benar-benar dipakai pada pemetaan tsb: (1) rasio persen 2 komponen sederhana (n/t × 100); (2) rasio persen 3 komponen penjumlahan pembilang ((a + b) / t × 100); (3) penjumlahan berbobot untuk indikator gabungan (mis. Nilai SAKIP dan Nilai ZI, masing-masing berbobot 0,5 dijumlahkan menjadi satu indikator komposit — bukan `tipe_perhitungan = manual`, melainkan `penjumlahan` dengan dua komponen `penjumlah` berbobot 0,5); (4) penjumlahan murni pada sub-jenjang tertentu (menjumlahkan hanya komponen yang memang ditargetkan pada tahun tsb; komponen baseline/pembanding tidak ikut dijumlahkan — divalidasi lewat kombinasi `aktif=true/false` pada `indikator_komponen`); (5) varian berbobot multi-pembilang Σ(nᵢ × kᵢ) / t di mana tiap pembilang memiliki bobot `kᵢ` berbeda. Setiap kasus memakai angka nyata dari lampiran (bukan angka rekaan) sebagai input `pengukuran_komponen`, dan hasil `pengukuran.nilai` dibandingkan terhadap hasil hitungan manual dari lampiran yang sama. Fixture ini secara eksplisit mencakup indikator gabungan berbobot 0,5 (Nilai SAKIP + Nilai ZI, varian 3) dan penjumlahan sub-jenjang tanpa komponen baseline (varian 4) sebagai dua skenario wajib, bukan opsional.
- **Dependency:** 5.13, 2.14, 2.15.
- **DoD:** Test Pest mencakup kelima varian di atas sebagai skenario terpisah, masing-masing memakai fixture data indikator/komponen/bobot dan angka `pengukuran_komponen` persis seperti pada "Lampiran: Pemetaan Indikator 2026 ke Definisi Komponen" (PRD); nilai `pengukuran.nilai` hasil mesin perhitungan sama persis (dalam toleransi `indikator.presisi` masing-masing indikator) dengan nilai hasil perhitungan manual yang tercantum di lampiran tsb untuk kedelapan indikator; kasus varian (4) diuji eksplisit bahwa komponen yang dinonaktifkan (`aktif=false`) tidak ikut terjumlah; kasus varian (3) diuji eksplisit bahwa bobot 0,5 pada masing-masing komponen diterapkan sebelum dijumlahkan, bukan dirata-rata otomatis oleh sistem.

---

## Modul 6 — Reviu & Pengesahan

> **Q45/Q51 (13 Oktober 2026) — Rekomendasi Pimpinan:** alur draf oleh Perencanaan, persetujuan oleh Pimpinan (Perencanaan sebagai cadangan bertanda), dan tindak lanjut wajib atas rekomendasi yang disetujui dikerjakan pada task baru 6.7 (ISS-09.01, tahap 2 Q46 §46.3; Q51 §51.3). Rujukan lama "Plan 8.5" untuk ISS-09.01 tidak pernah menunjuk task yang ada dan digantikan 6.7.

### 6.1 Dashboard kerja Perencanaan — antrean Diajukan
- **Scope:** halaman Inertia + komponen React menampilkan daftar pengukuran berstatus `diajukan` yang menunggu tindakan Perencanaan, dengan filter Renstra/Tahun/Periode/Unit.
- **Dependency:** 5.5, 1.11.
- **DoD:** Halaman menampilkan hanya baris berstatus `diajukan`; filter berfungsi dan mengurangi/menambah hasil sesuai kriteria; akses halaman ini memerlukan `pengukuran:verifikasi` atau lebih tinggi.

### 6.2 Dashboard kerja Perencanaan — antrean Diverifikasi (siap sahkan)
- **Scope:** halaman Inertia + komponen React menampilkan daftar pengukuran berstatus `diverifikasi` yang siap disahkan.
- **Dependency:** 5.6, 6.1.
- **DoD:** Halaman menampilkan hanya baris berstatus `diverifikasi`; aksi "Sahkan" pada halaman ini memanggil logic 5.9 dan memperbarui tampilan tanpa reload penuh (reaktif di sisi klien).

### 6.3 Dashboard kerja Perencanaan — antrean Disahkan (buka-kembali)
- **Scope:** halaman Inertia + komponen React menampilkan daftar pengukuran berstatus `disahkan` yang jadwalnya belum `penutupan`, dengan aksi "Buka Kembali" yang memicu logic 5.10 (memerlukan alasan via modal/form).
- **Dependency:** 5.10, 6.2.
- **DoD:** Halaman hanya menampilkan baris `disahkan` pada jadwal yang belum penutupan (baris pada jadwal yang sudah penutupan tidak muncul di antrean ini); submit alasan kosong pada aksi buka-kembali ditolak validasi; submit valid memindahkan baris ke antrean 6.1 (kembali menjadi `dikembalikan` lalu dapat direvisi).

### 6.4 Form Status Capaian manual

> **Q47/Q50 (13 Oktober 2026):** penetapan status capaian manual tidak tersedia untuk pengukuran pada periode tanpa target maupun pengukuran berstatus belum dapat diukur (Q47 §47.3 butir 4); pengukuran tersebut tidak masuk daftar "belum ditetapkan", dan permintaan penetapan langsung ditolak server. Tampilannya memuat realisasi, target PK, dan selisih terhadap target PK (Q47 §47.3 butir 1, §47.4 butir 3). Status capaian dan selisih memakai nilai target PK yang tersimpan (Q50 §50.2). Penyesuaian dikerjakan pada ISS-09.02.

- **Scope:** halaman Inertia + komponen React bagi Perencanaan/Superadmin untuk menetapkan `status_capaian` (tercapai/belum_tercapai) pada pengukuran berstatus `disahkan` yang belum memiliki status capaian aktif.
- **Dependency:** 5.9, 1.11.
- **DoD:** Submit form menghasilkan baris baru `status_capaian` dengan `sumber = manual` dan `ditetapkan_oleh` terisi user yang login; pengukuran yang sudah punya status capaian aktif tidak muncul lagi di daftar "belum ditetapkan" (kecuali fitur revisi 6.5 dipakai).

### 6.5 Revisi Status Capaian (soft replace)
- **Scope:** Aksi mengubah status capaian yang sudah ada — menambah baris baru sebagai status aktif terbaru, bukan mengubah baris lama.
- **Dependency:** 6.4.
- **DoD:** Test Pest: setelah revisi, query "status capaian aktif" (baris `created_at` terbaru per pengukuran_id) mengembalikan baris baru; baris lama tetap ada di tabel dan dapat ditelusuri sebagai riwayat; audit_log mencatat perubahan.

### 6.6 Migrasi & model `status_capaian`
- **Scope:** Migrasi tabel sesuai data model, termasuk kolom `sumber` enum (manual, data_sumber) dan `ditetapkan_oleh` nullable.
- **Dependency:** 5.1.
- **DoD:** Migrasi berjalan; constraint aplikasi memastikan `ditetapkan_oleh` selalu terisi ketika `sumber = manual` (divalidasi di layer aplikasi/form, diuji Pest).

### 6.7 Rekomendasi Pimpinan: draf, persetujuan, dan tindak lanjut (ISS-09.01)
- **Scope:** Q45 §45.2 dan Q51 §51.1–§51.2. Migrasi dan model `rekomendasi_pimpinan` (Data Model §2.31) per indikator × tahun × periode berisi `isi`, `ditetapkan_oleh` (penyusun baris), `status` enum(`draf`,`disetujui`) default `draf`, `disetujui_oleh`, `disetujui_oleh_role` (salinan kode peran penyetuju untuk penanda cadangan), `disetujui_pada`, dan `rujukan_sumber` (rancangan, nullable; rujukan laporan sumber data awal, Q51 §51.1 butir 5); ketiga kolom persetujuan terisi jika dan hanya jika `status = disetujui`. Perencanaan menyusun **draf**; Pimpinan **menyetujui** draf, dapat mengubah isinya lebih dulu, atau menulis rekomendasi langsung yang tersimpan berstatus `disetujui`. Persetujuan oleh Perencanaan adalah jalur cadangan bila Pimpinan berhalangan; persetujuan oleh peran mana pun selain Pimpinan, termasuk Perencanaan dan Superadmin, diberi penanda cadangan lewat `disetujui_oleh_role` dan tercatat di `audit_log` (Q51 §51.2 butir 2). Isi draf dapat diubah sebelum disetujui dengan perubahan teraudit; baris `disetujui` tidak diubah, sehingga perubahan membentuk baris baru melalui persetujuan yang sama dan baris lama tetap tersimpan sebagai riwayat. Rekomendasi yang berlaku per indikator × periode adalah baris `disetujui` dengan `disetujui_pada` terbaru; hanya rekomendasi itu yang tampil pada pengukuran periode berikutnya dan Laporan Kinerja Triwulan (8.5) serta memicu kewajiban `tindak_lanjut_rekomendasi` pada pengajuan pengukuran periode berikutnya (gerbang di 5.5), termasuk lintas tahun: rekomendasi pada periode terakhir suatu tahun ditindaklanjuti pada periode pertama jadwal tahun berikutnya bila indikatornya masih diukur (Q51 §51.1 butir 4). Draf tidak tampil bagi PIC. Rekomendasi tetap opsional per indikator per periode dan independen dari status pengukuran. Permission tetap `rekomendasi:tetapkan` (preset Pimpinan dan Perencanaan, 1.12; bertanda sensitif sehingga mencatat `dasar_izin`, 1.20); simpan draf dan setujui dibedakan oleh `status`, bukan oleh permission. Data awal 2026: rekomendasi TW I–II dari laporan triwulan yang sudah ditandatangani Pimpinan dicatat Perencanaan langsung berstatus `disetujui`, dengan penanda persetujuan cadangan dan rujukan laporan sumbernya pada `rujukan_sumber` (Q51 §51.1 butir 5). Gerbang tindak lanjut dikecualikan untuk periode lampau (3.11).
- **Dependency:** 1.12 (preset Pimpinan), 1.20, 2.6, 3.3, 5.1 (kolom `tindak_lanjut_rekomendasi`), 10.1; gerbang tindak lanjut dipasang pada 5.5. Tahap 2 Q46 §46.3 (Q51 §51.3).
- **DoD:**
  1. Test Pest: migrasi `rekomendasi_pimpinan` berjalan, dan `disetujui_oleh`, `disetujui_oleh_role`, serta `disetujui_pada` terisi jika dan hanya jika `status = disetujui`.
  2. Perencanaan menyimpan draf; Pimpinan menyetujui draf, dengan atau tanpa mengubah isinya, sehingga status menjadi `disetujui` dengan `disetujui_oleh_role = pimpinan`; rekomendasi yang ditulis langsung oleh Pimpinan tersimpan `disetujui`.
  3. Persetujuan oleh Perencanaan maupun Superadmin tersimpan dengan `disetujui_oleh_role` peran tersebut sebagai penanda cadangan dan tercatat di `audit_log` beserta `dasar_izin`.
  4. Pemegang `rekomendasi:tetapkan` (Pimpinan dan Perencanaan) dapat menyimpan draf maupun menyetujui; pengguna tanpa permission tersebut ditolak 403.
  5. Baris `disetujui` tidak dapat diubah; perubahan membentuk baris baru yang melalui persetujuan yang sama, baris lama tetap tersimpan, dan rekomendasi yang berlaku adalah baris `disetujui` dengan `disetujui_pada` terbaru.
  6. Draf tidak tampil bagi PIC, pada pengukuran periode berikutnya, maupun pada Laporan Kinerja Triwulan; hanya rekomendasi yang berlaku yang tampil dan memicu kewajiban tindak lanjut (5.5), termasuk dari periode terakhir tahun T ke periode pertama jadwal tahun T+1 bila indikatornya masih diukur.
  7. Rekomendasi dapat dicatat untuk indikator × periode yang pengukurannya belum disahkan, dan tidak wajib ada pada setiap periode.
  8. Rekomendasi data awal TW I–II 2026 dicatat Perencanaan langsung `disetujui` dengan penanda cadangan dan `rujukan_sumber` terisi rujukan laporan sumbernya.

---

## Modul 7 — Dashboard

> **Q42/Q45/Q47 (13 Oktober 2026):** dashboard menampilkan total anggaran per IKU dengan drill-down sampai kegiatan, mengikuti akses dashboard; kegiatan bersama tampil penuh di setiap IKU, sedangkan total per unit dan per tahun dihitung dari kegiatan unik (Q42 §42.2 butir 4–5, §42.5 butir 2; data dari 12.12). Ringkasan (7.2) dan grafik (7.3, 7.8) menampilkan periode tanpa target sebagai **"Tanpa target periode"**, bukan "Belum tercapai", dan realisasi belum dapat diukur sebagai **"Belum dapat diukur"** yang tidak dihitung sebagai capaian 0; bila keduanya berlaku, yang tampil "Belum dapat diukur" (Q47 §47.3 butir 3); target periode kosong tidak ditampilkan sebagai `0`, dan selisih terhadap target PK tampil pada periode tanpa target serta sebagai informasi tambahan pada periode bertarget (Q47 §47.3–§47.4). Status kegiatan real-time berlabel "belum diverifikasi" dikerjakan pada 7.7 (Q45 §45.3). Seluruhnya dikerjakan pada ISS-10.01 (tahap 2).

> **Q64 dan status 19 Oktober 2026 (ISS-10.01, PR #147):** 7.2–7.7 dikerjakan; 7.2 memakai status tampilan Q64 (Belum mengisi, Tidak mengisi, Dalam proses, Belum ditetapkan, Tanpa target periode, Belum dapat diukur, Tidak dapat dihitung) karena status capaian belum dapat ditetapkan, dan 7.6 menghitung indikator tanpa Rencana Aksi sebagai "Belum disusun". 7.1: `EXPLAIN ANALYZE` pada data uji sintetis (±60 indikator × 4 periode, 2.000 kegiatan) menunjukkan setiap query dashboard di bawah 1 ms; sequential scan terjadi pada tabel kecil dan pada `kegiatan` (2.000 baris, ±0,3 ms), sehingga belum ada indeks baru. 7.8 belum dikerjakan. Total anggaran per IKU (catatan Modul 7) menunggu penjumlahan ISS-06.08 bagian kedua.

> **Q66 (21 Oktober 2026):** kartu ringkasan 7.2 per status tampilan dihapus sementara; status tampilan tampil per baris pada tabel status per indikator, ringkasan di atas dashboard memakai status alur kewajiban (kartu Draft menyebut jumlah "Tidak mengisi" bila ada), dan ringkasan Tercapai/Belum Tercapai menunggu 6.4. Panel 7.6 dan 7.7 tampil berdampingan, dengan tabel kegiatan per IKU pada kartu tersendiri.

### 7.1 Migrasi/index pendukung query dashboard
- **Scope:** Tambahkan index database yang diperlukan untuk query agregasi dashboard (mis. index pada `pengukuran(status_alur)`, `status_capaian(pengukuran_id, created_at)`, `rencana_aksi(status_alur)`, `kegiatan(status, periode_id)`).
- **Dependency:** 5.1, 6.6, 11.1, 12.1.
- **DoD:** `EXPLAIN ANALYZE` pada query ringkasan dashboard menunjukkan penggunaan index yang relevan (bukan sequential scan penuh pada tabel besar) di lingkungan uji dengan data seed memadai.

### 7.2 Komponen ringkasan status capaian berbasis indikator × periode
- **Scope:** halaman Inertia + komponen React yang menghitung dan menampilkan jumlah indikator per kategori: Tercapai, Belum Tercapai, Belum Ditetapkan, Belum Mengisi, Tidak Mengisi — dihitung per kombinasi indikator × periode yang diharapkan (dari `jadwal_periode`), bukan per tahun secara agregat. Indikator berstatus `arsip` dikecualikan dari perhitungan kewajiban pengisian.
- **Dependency:** 6.6, 5.1, 3.7, 3.3.
- **DoD:** Test Pest dengan data seed mencakup kelima kondisi (termasuk kasus indikator arsip yang seharusnya tidak muncul sebagai "Belum mengisi"/"Tidak mengisi") menghasilkan angka yang tepat sesuai definisi masing-masing kategori (dicek satu per satu via assertion count per kombinasi indikator+periode).

### 7.3 Grafik ApexCharts target vs realisasi
- **Scope:** Integrasi ApexCharts (via `react-apexcharts`) menampilkan grafik batang/garis perbandingan target (dari snapshot, dan target periode dari rencana aksi) vs realisasi (nilai pengukuran disahkan) per indikator/periode.
- **Dependency:** 7.2, 3.7, 11.2.
- **DoD:** Grafik dapat dirender di browser (diverifikasi manual/screenshot) dengan data uji, menampilkan minimal 2 seri (target, realisasi) yang berbeda secara visual dan numerik sesuai data sumber.

### 7.4 Filter dashboard (Renstra, Tahun, Periode, Sasaran, Unit)
- **Scope:** Kontrol filter reaktif (state React + Inertia partial reload) yang memperbarui komponen ringkasan (7.2) dan grafik (7.3) tanpa reload halaman penuh.
- **Dependency:** 7.2, 7.3.
- **DoD:** Mengubah filter Unit pada UI mengubah angka pada komponen ringkasan sesuai data yang difilter, diuji dengan frontend test (Vitest + React Testing Library) pada komponen filter, dan test Pest untuk query backend-nya.

### 7.5 Akses dashboard berdasarkan preset role yang telah dikonfirmasi
- **Scope:** Memastikan `dashboard:read` untuk preset lima role lama (Superadmin, Admin, Perencanaan, Pimpinan, Pegawai) tetap berfungsi sesuai baseline resmi. Role keenam `pic` **tidak otomatis dianggap memiliki `dashboard:read`** sampai preset permission PIC ditetapkan melalui 1.23.
- **Dependency:** 7.4, 1.11, 1.12; untuk assertion baseline role final mengikuti Q32/1.23.
- **DoD:** Test Pest: user Pegawai tanpa permission tambahan dapat mengakses dashboard dan menerima 200; user Admin juga menerima 200 sesuai preset existing; user role PIC diuji berdasarkan izin efektif aktual—sebelum 1.23 tidak ada assertion bahwa `dashboard:read` harus berasal dari role, sesudah 1.23 test mengikuti preset keputusan final. Tidak ada pengecekan nama role langsung pada controller/React; akses tetap lewat permission resolver.

### 7.6 Panel progres Rencana Aksi per indikator (status alur)
- **Scope:** halaman Inertia + komponen React menampilkan panel dashboard baru: jumlah rencana aksi per status alur (draft/diajukan/diverifikasi/dikembalikan/disahkan) per Renstra/tahun/unit, sebagai indikator progres kesiapan sebelum periode pengisian pengukuran dimulai.
- **Dependency:** 11.1, 11.3, 7.4.
- **DoD:** Test Pest (backend) / Vitest (frontend): dengan data seed berisi rencana aksi pada seluruh 5 status, panel menampilkan hitungan yang tepat per status; filter Unit pada 7.4 ikut memfilter panel ini.

### 7.7 Panel jumlah Kegiatan per status per periode
- **Scope:** halaman Inertia + komponen React menampilkan panel dashboard baru: jumlah kegiatan per status (rencana/terlaksana/tidak_terlaksana/ditunda/batal) per periode, termasuk hitungan eksplisit kegiatan `batal`/`tidak_terlaksana` sebagai sinyal risiko capaian. Status kegiatan ditampilkan secara real-time dengan label "belum diverifikasi"; kegiatan yang melewati `tanggal_rencana` tetapi belum `terlaksana` diberi peringatan. Angka capaian resmi tetap hanya dari data yang disahkan (Q45 §45.3).
- **Dependency:** 12.1, 12.3, 7.4.
- **DoD:** Test Pest (backend) / Vitest (frontend): dengan data seed kegiatan pada seluruh status, panel menampilkan hitungan tepat per status per periode; kegiatan `kegiatan_asal_id` terisi (hasil geser periode) dihitung terpisah dari kegiatan asalnya (tidak dobel-hitung sebagai kegiatan yang sama); perubahan status kegiatan langsung tercermin tanpa menunggu pengesahan pengukuran dan berlabel "belum diverifikasi"; kegiatan berstatus `rencana` dengan `tanggal_rencana` sebelum hari ini ditandai peringatan; ringkasan capaian (7.2) tetap hanya membaca data disahkan.

### 7.8 Panel perbandingan target periode (rencana aksi) vs realisasi komponen
- **Scope:** halaman Inertia + komponen React menampilkan panel dashboard baru: untuk indikator terpilih, tabel/grafik perbandingan `rencana_aksi_target.nilai` (target komponen per periode) vs `pengukuran_komponen.nilai` (realisasi komponen per periode), per komponen.
- **Dependency:** 11.2, 5.13, 7.4.
- **DoD:** frontend test (Vitest + React Testing Library): memilih indikator berkomponen menampilkan baris per komponen dengan kolom target dan realisasi yang sesuai data seed; indikator bertipe manual menampilkan pesan/kondisi bahwa panel ini tidak berlaku (tidak error).

---

## Modul 8 — Laporan & Ekspor

### 8.1 Halaman laporan tabular terfilter
- **Scope:** halaman Inertia + komponen React menampilkan tabel pengukuran dengan filter Renstra/Tahun/Periode/Sasaran/Unit/Status; permission `laporan:read`.
- **Dependency:** 5.1, 1.11.
- **DoD:** Tabel menampilkan kolom minimal: nama indikator, unit, tahun, periode, nilai, status_alur, status_capaian; filter berfungsi mengurangi hasil sesuai kriteria yang dipilih, diuji frontend test (Vitest + React Testing Library); user dengan peran Admin (memiliki `laporan:read` tapi bukan `laporan:ekspor`) dapat mengakses halaman ini.

### 8.2 Ekspor tabel laporan ke Excel
- **Scope:** Implementasi ekspor hasil laporan terfilter (8.1) ke berkas `.xlsx` (mis. via `maatwebsite/excel` atau setara), memuat data sesuai filter aktif saat tombol ekspor ditekan; permission `laporan:ekspor`.
- **Dependency:** 8.1.
- **DoD:** Berkas hasil ekspor dapat dibuka dan divalidasi struktur (mis. via PHPSpreadsheet reader di test Pest) berisi jumlah baris yang sama dengan hasil filter pada layar; user tanpa `laporan:ekspor` mendapat 403 saat memicu aksi ekspor meski memiliki `laporan:read` — mencakup pengujian eksplisit dengan user berperan Admin.

### 8.3 Rekapitulasi indikator × periode (tampilan)

> **Q42/Q44/Q45/Q47/Q51 (13 Oktober 2026):** rekapitulasi menampilkan narasi pengukuran (5.5) bersama daftar kegiatan yang diklaim beserta anggarannya (12.12), dengan anggaran per kegiatan mengikuti akses detail kegiatan (Q42 §42.5); narasi per kegiatan tetap opsional (12.5). Rekomendasi yang ditampilkan hanya yang berstatus disetujui (Q51 §51.1, 6.7). Periode tanpa target dan realisasi belum dapat diukur memakai keterangan "Tanpa target periode" dan "Belum dapat diukur" beserta selisih terhadap target PK (Q47). Penanda `tidak_dapat_dipenuhi` hanya tampil sebagai histori (Q44). Dokumen resmi Rencana Aksi tahunan dan Laporan Kinerja Triwulan dikerjakan pada 8.5 (Q48). Penyesuaian dikerjakan pada ISS-10.02 (tahap 2).

- **Scope:** halaman Inertia + komponen React menampilkan rekapitulasi setara format kerja institusional saat ini, per baris indikator × periode: PIC, nama indikator, formula/cara hitung, baseline, target PK tahunan, target periode (dari rencana aksi), capaian periode, nilai tiap komponen (target dari `rencana_aksi_target` & realisasi dari `pengukuran_komponen`), daftar kegiatan yang diklaim beserta statusnya, narasi `uraian_pelaksanaan`/`kendala`/`strategi_tindaklanjut` teragregasi dari kegiatan yang diklaim, penanda bukti dukung `tidak_dapat_dipenuhi` bila ada (13.7), dan rekomendasi Pimpinan aktif untuk kombinasi tsb.
- **Dependency:** 11.2, 5.13, 5.14, 12.1, 14.2, 16.1, 8.1, 13.7.
- **DoD:** frontend test dengan data seed mencakup 1 indikator berkomponen memiliki ≥2 kegiatan diklaim (salah satu berstatus `tidak_terlaksana`), 1 rekomendasi Pimpinan aktif, dan 1 bukti dukung ditandai `tidak_dapat_dipenuhi`: rekapitulasi menampilkan seluruh kolom di atas terisi benar sesuai data seed, termasuk narasi kegiatan yang tergabung otomatis (bukan field kosong), status kegiatan `tidak_terlaksana` yang tetap tampil, dan penanda `tidak_dapat_dipenuhi` yang terlihat jelas.

### 8.4 Ekspor rekapitulasi indikator × periode ke Excel

> **Q48 (13 Oktober 2026):** contoh keluaran yang disahkan Perencanaan (Q29) juga menjadi acuan format Rencana Aksi tahunan dan Laporan Kinerja Triwulan pada 8.5.

- **Scope:** Perluasan ekspor 8.2 (atau ekspor terpisah) untuk memuat struktur rekapitulasi 8.3 — satu baris per komponen per indikator × periode, kolom narasi kegiatan dan rekomendasi Pimpinan disertakan; permission `laporan:ekspor`.
- **Dependency:** 8.3, 8.2.
- **DoD:** Berkas hasil ekspor memuat seluruh kolom rekapitulasi 8.3 dan divalidasi strukturnya (via PHPSpreadsheet reader di test Pest) sesuai data seed yang sama dipakai pada 8.3.

### 8.5 Keluaran Rencana Aksi tahunan dan Laporan Kinerja Triwulan + unggah PDF bertanda tangan (ISS-10.05)
- **Scope:** Q48. Pemegang `laporan:ekspor` (preset Perencanaan, Pimpinan, dan Superadmin) membuat dan mengunduh dua dokumen gabungan untuk satu lembaga dalam format Excel: (1) **Rencana Aksi tahunan** per jadwal tahunan — sasaran, indikator, satuan, target PK, target per periode seluruh indikator, dan daftar kegiatan per indikator per periode dari Rencana Aksi versi disahkan; (2) **Laporan Kinerja Triwulan** per periode jadwal — per indikator target PK, target periode, realisasi, capaian atau selisih terhadap target PK (Q47 §47.3), analisis dari narasi pengukuran (Q45 §45.1), dan Rekomendasi Pimpinan yang disetujui (Q51 §51.1), dengan status kegiatan per indikator sebagai lampiran tambahan. Isi diambil dari data yang disahkan; indikator yang Rencana Aksi atau pengukurannya belum disahkan tetap tercantum dengan keterangan "belum disahkan" tanpa angka resmi. Periode tanpa target dan realisasi belum dapat diukur memakai keterangan yang sama dengan dashboard ("Tanpa target periode", "Belum dapat diukur"; yang kedua didahulukan bila keduanya berlaku, Q47 §47.3 butir 3). Rincian Output dan realisasi anggaran per Rincian Output tetap disusun di luar SAKIP; dokumen PK tidak dihasilkan SAKIP, dan PDF PK tetap diunggah lewat lampiran PK (2.20, 2.23). Format mengikuti contoh keluaran yang disahkan Perencanaan (Q29, 8.4); keluaran PDF tetap fase lanjutan. PDF yang sudah ditandatangani elektronik di luar aplikasi diunggah kembali oleh pemegang permission baru `laporan:unggah` (preset Perencanaan dan Superadmin, 1.10/1.12) sebagai arsip resmi: Rencana Aksi tahunan pada induk `berkas` `jadwal_tahunan` (boleh lebih dari satu berkas, mis. Rencana Aksi awal dan perubahan) dan Laporan Kinerja Triwulan pada induk `jadwal_periode`, masing-masing beserta keterangan pada kolom baru `berkas.keterangan` (nullable; Q48 §48.2 butir 6); arsip hanya menerima berkas PDF dengan batas ukuran mengikuti batas teknis Q44 §44.2 (9.10; Q48 §48.2 butir 7); unduhan arsip mengikuti `laporan:ekspor`. Arsip bersifat append-only: tidak dapat dihapus, dan salah unggah dikoreksi dengan berkas pengganti yang mencatat `alasan_koreksi`, kolom yang tetap khusus untuk berkas pengganti (Q25, Q48 §48.2 butir 3 dan 6). Unggahan tidak mengubah data, status, maupun snapshot, tercatat di `audit_log`, dan tetap dapat dilakukan setelah jadwal tahunan `ditutup` (Q48 §48.2 butir 5). Karena itu `laporan:unggah` tidak bertanda sensitif (Q48 §48.2 butir 2). Bila pembuatan berkas memerlukan pustaka baru, keputusannya dicatat sebagai ADR.
- **Dependency:** 1.10, 1.12 (`laporan:unggah`), 8.2–8.4 (ekspor Excel dan rekapitulasi), 11.3 dan 11.9 (Rencana Aksi disahkan, periode tanpa target), 12.6 dan 12.12 (kegiatan dalam Rencana Aksi), 5.5 dan 5.9 (narasi dan pengukuran disahkan), 6.7 (Rekomendasi Pimpinan disetujui, ISS-09.01), 13.2, 13.5, 13.6 (induk `berkas` baru, unggah privat, append-only), 3.9 (status `ditutup`), 9.10 (batas teknis), 10.1. Tahap 2 Q46 §46.3.
- **DoD:**
  1. Test Pest: berkas Excel Rencana Aksi tahunan yang dibuat pemegang `laporan:ekspor` dibaca dengan PHPSpreadsheet dan memuat sasaran, indikator, satuan, target PK, target per periode seluruh indikator, serta kegiatan per indikator per periode dari versi Rencana Aksi yang disahkan; perubahan draf sesudah pengesahan tidak ikut.
  2. Berkas Excel Laporan Kinerja Triwulan memuat per indikator target PK, target periode, realisasi, capaian atau selisih terhadap target PK, analisis dari narasi pengukuran, dan Rekomendasi Pimpinan berstatus `disetujui` (draf tidak ikut), serta lampiran status kegiatan per indikator.
  3. Indikator yang Rencana Aksi atau pengukurannya belum disahkan tercantum dengan keterangan "belum disahkan" tanpa angka resmi.
  4. Periode tanpa target dan realisasi belum dapat diukur tampil dengan keterangan "Tanpa target periode" dan "Belum dapat diukur", sama dengan dashboard, dan tidak tampil sebagai capaian 0.
  5. Pengguna tanpa `laporan:ekspor` yang meminta keluaran atau mengunduh arsip ditolak 403.
  6. Perencanaan dengan `laporan:unggah` mengunggah PDF bertanda tangan pada induk `jadwal_tahunan` dan `jadwal_periode` dengan keterangan tersimpan di `berkas.keterangan`, dan satu induk dapat memiliki lebih dari satu berkas; berkas selain PDF atau melebihi batas teknis ditolak; pengguna tanpa `laporan:unggah` ditolak 403; katalog memuat `laporan:unggah` dengan `sensitif = false` dan preset Perencanaan/Superadmin memilikinya melalui seeder idempoten (1.12).
  7. Penghapusan arsip ditolak; berkas pengganti tersimpan sebagai baris baru dengan `alasan_koreksi`, dan berkas lama tetap utuh.
  8. Data, status, dan snapshot tidak berubah setelah unggahan (dibandingkan sebelum dan sesudah), unggahan tercatat di `audit_log`, dan unggahan arsip pada jadwal berstatus `ditutup` diterima (Q48 §48.2 butir 5).

---

## Modul 9 — Setelan Aplikasi

### 9.1 Migrasi & model `pengaturan`
- **Scope:** Migrasi tabel key-value `pengaturan`: `kunci` (unique), `nilai`, `tipe`, `grup`, `updated_by`, `updated_at`.
- **Dependency:** 1.3 (FK updated_by).
- **DoD:** Migrasi berjalan; constraint unique pada `kunci` ditegakkan (percobaan insert kunci duplikat ditolak, diuji Pest); model dapat dibuat/dibaca sesuai struktur.

### 9.2 Seeder nilai default `pengaturan`
- **Scope:** Seeder yang mengisi kunci awal saat instalasi: identitas instansi (`instansi.nama`, `instansi.alamat`, `instansi.telepon`, `instansi.surel`, `instansi.laman`, `instansi.logo`), identitas aplikasi (`aplikasi.nama`, `aplikasi.label_unit`), preferensi tampilan/laporan (`preferensi.zona_waktu`, `preferensi.format_tanggal`, `preferensi.format_angka`, `preferensi.header_ekspor`, `preferensi.footer_ekspor`) — masing-masing dengan `tipe` dan `grup` yang sesuai.
- **Dependency:** 9.1.
- **DoD:** `php artisan migrate:fresh --seed` menghasilkan seluruh kunci di atas dengan `nilai` default terisi (bukan NULL) dan `grup` terisi sesuai pengelompokan (`instansi`, `aplikasi`, `preferensi`); jumlah baris `pengaturan` setelah seed sama dengan jumlah kunci yang didefinisikan di seeder (tidak kurang/lebih).

### 9.3 Accessor pengaturan dengan cache
- **Scope:** Service/helper (mis. `Pengaturan::get('kunci', $default)`) yang membaca nilai dari tabel `pengaturan`, dengan layer cache (mis. Laravel Cache) agar pembacaan berulang saat render tidak selalu menghantam database; cache diinvalidasi otomatis saat nilai diperbarui (lihat 9.4).
- **Dependency:** 9.2.
- **DoD:** Test Pest: pemanggilan `Pengaturan::get('aplikasi.label_unit')` berulang menghasilkan nilai yang benar; setelah nilai diubah lewat 9.4, pemanggilan berikutnya (tanpa restart proses) mengembalikan nilai baru, bukan nilai cache lama — dibuktikan dengan assertion pada nilai sebelum dan sesudah update dalam satu skenario test.

### 9.4 Form Setelan Aplikasi (Admin/Superadmin)

> **Q43/Q44 (13 Oktober 2026):** grup `berkas` tidak lagi tampil maupun diterima form ini, dan whitelist 9.4/9.6 tidak lagi memuat kunci grup `berkas` karena batas teknis unggahan dibaca dari konfigurasi aplikasi (9.10). Alasan perubahan setelan menjadi opsional (Q43 §43.3 butir 3; penyesuaian `UpdatePengaturanRequest` pada 10.7).

- **Scope:** halaman Inertia + komponen React terkelompok per grup (`instansi`, `aplikasi`, `preferensi`, `berkas`) untuk mengubah nilai `pengaturan`; permission `pengaturan:update`; validasi whitelist kunci (hanya kunci yang telah didefinisikan di 9.2/9.7 yang dapat diubah, kunci lain ditolak); setiap perubahan menginvalidasi cache accessor (9.3) untuk kunci terkait.
- **Dependency:** 9.3, 1.11.
- **DoD:** Submit perubahan pada kunci valid berhasil memperbarui `nilai`, `updated_by`, `updated_at`; submit dengan kunci di luar whitelist (mis. hasil manipulasi request) ditolak validasi; user dengan peran Superadmin **atau** Admin berhasil mengakses dan menyimpan form ini; user dengan peran Perencanaan/Pimpinan/Pegawai mendapat 403.

### 9.5 Audit perubahan Setelan Aplikasi
- **Scope:** Setiap perubahan nilai lewat 9.4 tercatat di `audit_log` per kunci yang berubah, dengan `nilai_lama`/`nilai_baru` berisi nilai kunci tersebut sebelum dan sesudah perubahan.
- **Dependency:** 9.4, 10.1 (audit dasar).
- **DoD:** Test Pest: mengubah 2 kunci sekaligus dalam satu submit menghasilkan 2 baris `audit_log` terpisah (satu per kunci), masing-masing dengan `objek_tipe = pengaturan`, `objek_id`/pengenal kunci yang sesuai, dan `nilai_lama`/`nilai_baru` yang benar.

### 9.6 Guard cakupan: larangan mengubah enum/status/aturan bisnis lewat `pengaturan`
- **Scope:** Tinjauan kode dan test regresi yang memastikan tabel `pengaturan` dan form 9.4 tidak dipakai sebagai jalur untuk mengubah nilai enum (status Renstra/Jadwal/Pengukuran/Rencana Aksi/Kegiatan, dsb), nama permission, atau aturan bisnis apa pun — seluruh whitelist kunci di 9.2/9.7 terbatas pada teks dan preferensi presentasional/operasional.
- **Dependency:** 9.4.
- **DoD:** Test Pest/statis: seluruh kunci pada whitelist 9.2 dan 9.7 diverifikasi bertipe teks/tanggal-format/angka-format/URL/angka-ukuran/boolean, tidak ada satu pun kunci yang membaca/menulis ke kolom enum tabel lain; tidak ada rute/method di form 9.4 yang menerima parameter di luar daftar kunci whitelist.

### 9.7 Migrasi & seeder kunci grup `berkas` (`berkas.unggahan_aktif`, `berkas.ukuran_maks_kb`, `berkas.format_diizinkan`, `berkas.tautan_selalu_diizinkan`) — digantikan Q44

> **Q44 (13 Oktober 2026):** saklar unggahan dan kebijakan grup `berkas` dihapus; batas teknis dibaca dari konfigurasi aplikasi (default 10 MB dan `pdf,docx,xlsx,jpg,jpeg,png`, batas atas 100 MB). Kunci yang sudah ada tidak lagi dibaca maupun dikelola UI dan tetap sebagai histori. Digantikan 9.10 (ISS-13.06); scope dan DoD di bawah dipertahankan sebagai riwayat.

- **Scope:** Tambahan seeder kunci baru pada grup `berkas`: `berkas.unggahan_aktif` (boolean, default `true` — saklar aktif/nonaktif unggahan mode file secara aplikasi-wide; bila `false`, seluruh endpoint unggah file di ketiga tahap — rencana_aksi/pengukuran/kegiatan — menolak permintaan terlepas `jenis_berkas` apa pun), `berkas.ukuran_maks_kb` (int) dan `berkas.format_diizinkan` (varchar, mis. `pdf,docx,xlsx,jpg,png`) — kedua kunci terakhir dipakai sebagai fallback saat `jenis_berkas.ukuran_maks_kb`/`format_diizinkan` kosong (lihat Modul 13), dan `berkas.tautan_selalu_diizinkan` (boolean, default `true` — penanda bahwa mode tautan dan teks selalu tersedia sebagai jalur alternatif tanpa memakai storage, dibaca oleh UI persyaratan pada 13.1 sebagai indikasi non-blokir). Keempat kunci ini diubah lewat form 9.4 yang sama, digerbangi `pengaturan:update` — dipegang **Admin maupun Superadmin**. Wewenang ini terpisah dari substansi persyaratan bukti dukung per indikator/tahap (`jenis_berkas:create/update/delete`, dipegang Perencanaan/Superadmin, lihat 13.1): grup `berkas` di sini mengatur kebijakan/saklar tingkat aplikasi, bukan menentukan bukti dukung apa yang wajib untuk indikator/tahap mana.
- **Dependency:** 9.2, 9.1, 9.4.
- **DoD:** `php artisan migrate:fresh --seed` menghasilkan keempat kunci baru dengan nilai default terisi (`berkas.unggahan_aktif = true`, `berkas.tautan_selalu_diizinkan = true`); test Pest: validasi unggahan berkas (13.3) yang `jenis_berkas` terkait memiliki `ukuran_maks_kb`/`format_diizinkan` NULL berhasil mengambil nilai fallback dari kedua kunci ukuran/format, dibuktikan dengan mengubah nilai kunci lalu memverifikasi validasi ikut berubah; mengubah `berkas.unggahan_aktif` menjadi `false` membuat SELURUH percobaan unggah mode file (rencana_aksi/pengukuran/kegiatan) ditolak dengan pesan spesifik "unggahan dinonaktifkan", terlepas status `jenis_berkas.wajib`, sementara mode tautan/teks tetap dapat dipakai; user dengan peran Admin **maupun** Superadmin (keduanya memegang `pengaturan:update`) berhasil mengubah keempat kunci ini lewat form 9.4; user dengan peran Perencanaan (yang memegang `jenis_berkas:create/update/delete` tapi bukan `pengaturan:update`) mendapat 403 saat mencoba mengubah kunci grup `berkas` ini, membuktikan pemisahan wewenang kebijakan vs substansi persyaratan.

### 9.8 Panel read-only "Penggunaan Penyimpanan Bukti Dukung" — digantikan Q44

> **Q44 (13 Oktober 2026):** panel penggunaan penyimpanan dihapus (Q44 §44.1 butir 3) melalui 9.10 (ISS-13.06); tampilan daftar berkas terunggah masuk backlog tahap 3 (Q46 §46.3). Scope dan DoD di bawah dipertahankan sebagai riwayat.

- **Scope:** halaman Inertia + komponen React pada halaman Setelan Aplikasi menampilkan panel READ-ONLY: jumlah baris `berkas` bermode `file` beserta total `ukuran_bytes`, dan jumlah baris `berkas` bermode `tautan`/`teks` (masing-masing dihitung terpisah, lintas keenam induk `berkasable_type`). Panel ini dapat diakses oleh siapa pun yang memegang `pengaturan:update` ATAU `jenis_berkas:read` (Admin, Superadmin, Perencanaan, Pimpinan, Pegawai — seluruh peran memegang `jenis_berkas:read`), tanpa permission baru khusus.
- **Dependency:** 9.4, 13.2 (tabel `berkas`).
- **DoD:** Test Pest: dengan data seed berisi N baris `berkas` mode file (total ukuran tertentu, lintas keenam induk), M baris mode tautan, dan K baris mode teks, panel menampilkan angka N + total ukuran, dan (M+K) sebagai jumlah bukti non-file — dicocokkan tepat dengan hitungan basis data; panel dapat diakses user Perencanaan (tanpa `pengaturan:update`) maupun user Admin/Superadmin; panel tidak menampilkan aksi ubah data apa pun (murni tampilan).

### 9.9 Halaman "Batas unggahan berkas" (format/ukuran per persyaratan) — digantikan Q44

> **Q44 (13 Oktober 2026):** halaman ini dihapus; format dan ukuran maksimum per persyaratan ditetapkan Perencanaan di Persyaratan Berkas (13.1), dengan batas atas 100 MB dari konfigurasi aplikasi. Digantikan 9.10 (ISS-13.06); scope dan DoD di bawah dipertahankan sebagai riwayat.

- **Scope:** halaman Inertia + komponen React terpisah dari 9.4, mendaftar SELURUH `jenis_berkas` (aktif maupun nonaktif) sebagai baris, dengan HANYA dua kolom yang dapat diubah inline: `format_diizinkan` dan `ukuran_maks_kb`; permission `pengaturan:update` (Admin dan Superadmin). Kolom substantif (`nama`, `tahap`, `wajib`, `izinkan_file`, `izinkan_tautan`, `izinkan_teks`, `semua_mode_wajib`, `indikator_id`) ditampilkan sebagai referensi read-only — payload yang menyertakan kolom tsb di luar dua kolom yang diizinkan DITOLAK sistem sama sekali, terlepas nilai yang dikirim. Validasi: `ukuran_maks_kb` minimal `100` bila diisi; `format_diizinkan` wajib terisi bila `izinkan_file = true` pada persyaratan tersebut. Perubahan bersifat GRANDFATHERED — bukti yang sudah diunggah tidak menjadi tidak sah karena perubahan batas; bila perubahan menyempitkan format sementara sudah ada bukti berformat lama, sistem menampilkan PERINGATAN (bukan menolak penyimpanan).
- **Dependency:** 13.1 (`jenis_berkas`), 9.4, 1.11.
- **DoD:** Test Pest: submit `ukuran_maks_kb = 50` (di bawah 100) DITOLAK validasi; submit `format_diizinkan` kosong pada persyaratan dengan `izinkan_file = true` DITOLAK validasi; submit yang menyertakan kolom substantif (mis. `wajib`, `nama`) di luar dua kolom yang diizinkan DITOLAK sistem sepenuhnya (baik kolom substantif maupun kedua kolom yang sah tidak tersimpan pada payload tsb); submit valid pada `format_diizinkan`/`ukuran_maks_kb` berhasil memperbarui baris `jenis_berkas` terkait TANPA mengubah kolom substantif lain (dibuktikan dengan membandingkan snapshot kolom substantif sebelum/sesudah); bukti yang sudah diunggah dengan format lama (sebelum penyempitan `format_diizinkan`) tetap sah setelah perubahan (grandfathered, dibuktikan lolos gerbang kelengkapan 13.4 tanpa perlu diunggah ulang); penyempitan format sementara ada bukti berformat lama pada persyaratan tsb memicu PERINGATAN pada respons (bukan penolakan penyimpanan); setiap perubahan tercatat `audit_log` PER PERSYARATAN (jenis_berkas_id, kolom yang berubah, nilai lama → nilai baru, oleh siapa) — dibuktikan dengan mengubah 2 persyaratan sekaligus menghasilkan 2 baris audit_log terpisah; user dengan peran Admin **maupun** Superadmin berhasil mengakses dan menyimpan halaman ini; user dengan peran Perencanaan (memegang `jenis_berkas:create/update/delete` tapi bukan `pengaturan:update`) mendapat 403 saat mencoba mengakses halaman ini, membuktikan pemisahan wewenang: substansi persyaratan = Perencanaan (13.1), kebijakan teknis unggahan per persyaratan = pemegang `pengaturan:update` (Admin/Superadmin).

### 9.10 Penghapusan Kebijakan Storage, batas teknis unggahan di konfigurasi, dan penataan menu referensi (ISS-13.06, Q44)

> **Status implementasi lokal (20 Oktober 2026):** jalur unggahan existing termasuk perencanaan kegiatan/RAB dan contoh Jenis Berkas menggunakan konfigurasi satu pintu; penghapusan kebijakan/saklar, gerbang tanpa pengecualian baru, serta penataan menu diterapkan pada working diff issue #128. DoD 3 tetap parsial karena lampiran bebas kegiatan (13.8, PRD §18.10) belum memiliki endpoint dan berada di luar slice yang disepakati. Histori lama dibuktikan di Jejak Audit; penyajian baru rekapitulasi/review historis RA berada di ISS-10.02 (#134). Belum menyatakan UAT/deployment atau seluruh issue selesai. Lihat [catatan rilis dan batas cakupan](catatan-rilis-ISS-13.06.md).

- **Scope:** Q44, menggantikan Q23 serta 9.7–9.9 dan 10.6. Menu **Kebijakan Storage** (`/pengaturan/storage`) dan halaman **Batas Unggahan Berkas** dihapus; route lamanya tidak lagi melayani halaman tersebut. Saklar global unggahan dihapus sehingga unggahan file selalu aktif; penanda `tidak_dapat_dipenuhi` akibat unggahan dimatikan tidak lagi dibuat, sedangkan penanda lama tetap tampil sebagai histori. Batas teknis dibaca dari konfigurasi aplikasi, bukan dari UI maupun `pengaturan`: default 10 MB (10240 KB) dan format `pdf,docx,xlsx,jpg,jpeg,png` untuk lampiran tanpa persyaratan (Renstra, PK, Dasar Aturan, lampiran bebas kegiatan) dan untuk persyaratan yang mengosongkan format atau ukuran; batas atas 100 MB (102400 KB) per berkas yang tidak dapat dilampaui `jenis_berkas.ukuran_maks_kb`, divalidasi saat persyaratan disimpan dan saat unggah. Pembacaan nilai default yang tersebar di kode dialihkan ke konfigurasi, bukan dihapus; kunci grup `berkas` pada `pengaturan` tidak lagi dibaca maupun dikelola UI dan tetap sebagai histori. Cabang anti-macet unggahan pada gerbang lampiran PK (3.5), pengajuan Rencana Aksi (11.3), pengajuan pengukuran (5.16), dan penambahan bukti (13.4, 13.5, 13.7) dihapus. Halaman Persyaratan Berkas tidak lagi memuat mode "Ubah batas teknis" maupun peringatan unggahan dimatikan; format dan ukuran diubah Perencanaan lewat form persyaratan (13.1). Panel penggunaan penyimpanan (9.8) dihapus. Sidebar: **Dasar Aturan** dipindah ke grup **Master Data & Referensi** dan **Persyaratan Berkas** ke grup **Perencanaan Kinerja**; permission tidak berubah dan menu tetap mengikuti `can.*` dari server. Penyimpanan tetap privat dan unduhan tetap melalui route berizin. Perubahan batas teknis dilakukan lewat konfigurasi deployment dan dicatat pada catatan rilis. Tampilan daftar berkas terunggah masuk backlog tahap 3 (Q46 §46.3).
- **Dependency:** 9.7, 9.8, 9.9 (fitur yang dihapus), 13.1, 13.4, 13.5, 13.7, 3.5, 5.16, 11.3, 1.17. Tahap 1 Q46 §46.3.
- **DoD:**
  1. Test Pest/Vitest: menu Kebijakan Storage dan halaman Batas Unggahan Berkas tidak tersedia bagi peran mana pun, dan request ke route lamanya tidak lagi mengembalikan halaman tersebut.
  2. Unggahan file diterima tanpa pemeriksaan saklar; tidak ada penanda `tidak_dapat_dipenuhi` baru pada gerbang lampiran PK, pengajuan Rencana Aksi, pengajuan pengukuran, maupun penambahan bukti; penanda lama tetap tampil sebagai histori.
  3. Lampiran tanpa persyaratan dan persyaratan yang mengosongkan format atau ukuran memakai default konfigurasi: berkas di atas 10 MB atau berformat di luar `pdf,docx,xlsx,jpg,jpeg,png` ditolak, berkas sesuai default diterima, dan mengubah nilai konfigurasi pada test ikut mengubah validasi.
  4. Menyimpan persyaratan dengan `ukuran_maks_kb` di atas 102400 ditolak, dan unggahan di atas 100 MB ditolak.
  5. Halaman Persyaratan Berkas tidak lagi memuat mode "Ubah batas teknis" maupun peringatan unggahan dimatikan (Vitest).
  6. Panel penggunaan penyimpanan tidak lagi dirender.
  7. Sidebar menampilkan Dasar Aturan di grup Master Data & Referensi dan Persyaratan Berkas di grup Perencanaan Kinerja, dengan visibilitas menu tetap mengikuti `can.*` (Vitest dan Pest).
  8. Berkas tetap tersimpan di disk privat dan unduhan tanpa izin ditolak 403.

---

## Modul 10 — Audit & Histori

### 10.1 Infrastruktur dasar pencatatan Audit Log
- **Scope:** Migrasi tabel `audit_log` dan service/helper terpusat (`AuditLogger::catat(...)`) yang dipanggil oleh seluruh modul lain untuk mencatat peristiwa; tanpa endpoint update/delete.
- **Dependency:** 1.3 (users, untuk FK actor_id). Task ini secara praktik dikerjakan lebih awal secara paralel dengan Modul 1, karena hampir seluruh task lain di atas bergantung padanya untuk memenuhi DoD masing-masing yang menyebut audit_log.
- **DoD:** Memanggil `AuditLogger::catat(...)` dari test Pest menghasilkan baris baru di `audit_log` dengan seluruh kolom wajib terisi; tidak ada rute/method publik untuk mengubah/menghapus baris `audit_log` (dicek dengan mencoba akses rute semacam itu dan memastikan 404/405).

### 10.2 Validasi alasan wajib pada tindakan sensitif

> **Q43 (13 Oktober 2026):** daftar tindakan wajib alasan mengikuti Q43 §43.2, termasuk revisi PK, koreksi snapshot, penonaktifan dan pengaktifan kembali akun, justifikasi kegiatan, deviasi target PK, dan target PK yang menyimpang dari target Renstra menurut arah indikator (Q65 §65.2); pergantian penanggung jawab keluar dari daftar (Q43 §43.3 butir 4). Penyesuaian dikerjakan pada 10.7 (ISS-13.05).

- **Scope:** Lapisan validasi terpusat yang menegakkan kewajiban field `alasan` pada pemanggilan `AuditLogger::catat(...)` untuk daftar tindakan sensitif: koreksi PK, pengembalian pengukuran (baik `pengukuran:kembalikan` maupun `pengukuran:buka_kembali`), pengembalian rencana aksi (`rencana_aksi:kembalikan` maupun `rencana_aksi:buka_kembali`), pergantian penanggung jawab, penghapusan unit, `jadwal:buka_kembali`, revisi Renstra in place, dan tindakan sensitif lain yang relevan.
- **Dependency:** 10.1.
- **DoD:** Test Pest: memanggil `AuditLogger::catat(...)` untuk tindakan dalam daftar sensitif tanpa `alasan` melempar exception/ditolak sebelum baris tersimpan — mencakup pengujian eksplisit untuk `pengukuran:buka_kembali`, `rencana_aksi:buka_kembali`, dan `jadwal:buka_kembali`; tindakan di luar daftar tetap bisa disimpan tanpa alasan.

### 10.3 Halaman pencarian & tampilan Audit Log
- **Scope:** halaman Inertia + komponen React untuk mencari/memfilter `audit_log` berdasarkan actor, tindakan, objek_tipe, rentang waktu; permission `audit:read`. Daftar `objek_tipe` yang dapat difilter mencakup: renstra, indikator, jadwal, pengukuran, unit, pengaturan, **rencana_aksi, kegiatan, klaim_kegiatan, indikator_komponen, jenis_berkas, berkas, rekomendasi_pimpinan, regulasi**.
- **Dependency:** 10.1.
- **DoD:** Filter berdasarkan `objek_tipe = renstra` hanya menampilkan baris terkait Renstra; filter berdasarkan `objek_tipe = rencana_aksi` hanya menampilkan baris terkait rencana aksi; filter berdasarkan `objek_tipe = regulasi` hanya menampilkan baris terkait dokumen dasar; user tanpa `audit:read` (mis. Pegawai) mendapat 403 saat mengakses halaman ini; user dengan peran Admin (memiliki `audit:read`) berhasil mengakses halaman ini.

### 10.4 Tampilan detail perubahan (nilai_lama vs nilai_baru)
- **Scope:** Komponen tampilan yang merender perbandingan JSON `nilai_lama` vs `nilai_baru` secara human-readable (mis. tabel dua kolom per field yang berubah).
- **Dependency:** 10.3.
- **DoD:** Untuk baris audit dengan `nilai_lama`/`nilai_baru` terisi, tampilan menunjukkan field yang berbeda secara jelas (diverifikasi manual terhadap minimal 4 jenis tindakan berbeda: perubahan Renstra in place, perubahan status pengukuran, perpindahan unit indikator, perubahan setelan aplikasi, perubahan definisi komponen indikator).

### 10.5 Audit untuk percobaan tindakan yang ditolak
- **Scope:** Memastikan seluruh titik penolakan sistem yang ditegakkan eksplisit (percobaan hapus data bermakna, percobaan aktivasi gagal salah satu dari EMPAT gerbang validasi termasuk gerbang keempat lampiran PK, percobaan create pengukuran/rencana aksi untuk indikator arsip, percobaan pengajuan pengukuran yang gagal salah satu dari tiga gerbang kelengkapan §5.16, percobaan transisi kegiatan ke terlaksana yang gagal gerbang bukti dukung §12.11, percobaan `regulasi:delete` pada regulasi yang masih dirujuk (§2.17), dsb) juga menghasilkan baris audit log bertindakan "ditolak"/"percobaan", bukan hanya tindakan yang berhasil.
- **Dependency:** 10.1, 2.3 (validasi aktivasi Renstra), 3.5 (empat gerbang validasi aktivasi Jadwal), 5.11 (larangan hapus pengukuran), 2.9 (blokir create indikator arsip), 5.16 (gerbang kelengkapan pengajuan pengukuran), 11.3 (gerbang pengajuan rencana aksi), 12.11 (gerbang kegiatan terlaksana), 2.17 (guard hapus regulasi dirujuk).
- **DoD:** Test Pest lintas modul: masing-masing dari kesembilan skenario penolakan di atas (termasuk KEEMPAT gerbang aktivasi jadwal, ketiga gerbang kelengkapan pengukuran, gerbang bukti dukung kegiatan, dan guard hapus regulasi dirujuk secara terpisah) menghasilkan baris audit_log baru dengan `tindakan` yang mengindikasikan kegagalan/percobaan (bukan hanya exception yang dilempar tanpa jejak).

### 10.6 Audit penandaan `tidak_dapat_dipenuhi` pada bukti dukung (termasuk gerbang keempat aktivasi jadwal) — digantikan Q44

> **Q44 (13 Oktober 2026):** penandaan otomatis `tidak_dapat_dipenuhi` akibat unggahan dimatikan tidak lagi terjadi karena saklar unggahan dihapus (9.10; 13.7 dibatalkan). Baris audit yang sudah tercatat tetap menjadi histori; scope dan DoD di bawah dipertahankan sebagai riwayat.

- **Scope:** Memastikan penandaan otomatis (13.7) sebagai `tidak_dapat_dipenuhi` — baik pada persyaratan `jenis_berkas` maupun pada gerbang keempat aktivasi Jadwal Tahunan atas lampiran `renstra_pk` — dipicu saat `berkas.unggahan_aktif` diubah menjadi `false` sementara ada persyaratan/gerbang wajib mode file-only, mencatat baris `audit_log` tersendiri, terpisah dari baris audit perubahan kunci setelan itu sendiri (9.5).
- **Dependency:** 10.1, 13.7, 9.7.
- **DoD:** Test Pest: mengubah `berkas.unggahan_aktif` menjadi `false` saat terdapat ≥2 persyaratan `jenis_berkas` wajib mode file-only menghasilkan 1 baris `audit_log` untuk perubahan kunci setelan (9.5) DITAMBAH baris `audit_log` terpisah per persyaratan yang baru ditandai `tidak_dapat_dipenuhi` (total 2 baris tambahan pada kasus 2 persyaratan); mengaktifkan kembali `berkas.unggahan_aktif` menjadi `true` mencatat pencabutan penanda tsb sebagai baris audit tersendiri pula; BARU — mengubah `berkas.unggahan_aktif` menjadi `false` saat suatu `renstra_pk` belum memiliki lampiran mode `tautan`/`teks` menghasilkan baris `audit_log` tersendiri untuk penandaan gerbang keempat `tidak_dapat_dipenuhi` pada `renstra_pk` tsb, terpisah dari baris audit perubahan kunci setelan.

### 10.7 Penyederhanaan kewajiban alasan (ISS-13.05)
- **Scope:** Q43. Alasan pengguna hanya diwajibkan pada aksi Q43 §43.2: (1) pengembalian dan buka-kembali Rencana Aksi, pengukuran, dan jadwal; (2) penghapusan data atau berkas; (3) koreksi atau revisi data yang sudah sah — koreksi PK (2.12), revisi PK (2.23), dan koreksi snapshot (3.8); (4) perubahan akses — penetapan dan pergantian peran, penonaktifan dan pengaktifan kembali akun (Q38), deny dan pencabutannya, serta pencabutan grant; (5) justifikasi kegiatan `tidak_terlaksana`/`ditunda`/`batal` (12.3), alasan deviasi terhadap target PK (11.6, ADR-0008), dan alasan target PK yang menyimpang dari target Renstra menurut arah indikator (2.22, Q65 §65.2; diisi pada isian di editor target PK, bukan Modal Alasan Audit). Alasan menjadi opsional (Q43 §43.3) pada: pemberian grant unit — kolom `user_permission_granted.alasan` tetap `NOT NULL`, server mengisi alasan baku bila dikosongkan (pola Q37 §37.2), dan alasan yang diisi pengguna disimpan apa adanya; perubahan target PK, termasuk setelah snapshot terbentuk (Q65 §65.1); serta perubahan persyaratan berkas, pindah unit indikator, aktivasi jadwal, perubahan setelan aplikasi, dan perubahan PK sebelum PK dipakai jadwal aktif, dengan label UI "Alasan (opsional)". Field alasan pada penetapan dan pergantian Penanggung Jawab dihapus dari form; kolom `penanggung_jawab.alasan` sudah nullable. Kewajiban alasan dari keputusan sebelumnya yang tidak disebut Q43 §43.3 tetap berlaku, dan daftar tindakan wajib alasan pada 10.2 diselaraskan dengan Q43 §43.2. Audit tetap mencatat aktor, waktu, tindakan, nilai lama dan baru, serta `dasar_izin` untuk permission sensitif (Q43 §43.1, Q37 §37.2). Request terdampak: `StoreUnitGrantRequest` dan `CreateUnitGrantAction` (alasan baku), form penugasan Penanggung Jawab (`AssignmentRequest`), `StoreJenisBerkasRequest`/`UpdateJenisBerkasRequest`, `PindahUnitIndikatorRequest`, `ActivateJadwalRequest`, `UpdatePengaturanRequest`, dan `UpdatePerjanjianKinerjaRequest` (opsional sebelum PK dipakai jadwal aktif; koreksi sesudahnya tetap wajib alasan melalui 2.12).
- **Dependency:** 10.1, 10.2, 1.8, 1.14, 2.8, 2.11, 2.12, 3.5, 4.2, 4.3, 9.4, 13.1. Tahap 1 Q46 §46.3.
- **DoD:**
  1. Test Pest: grant unit tanpa alasan tersimpan dengan alasan baku dari server (tidak kosong), grant dengan alasan menyimpan alasan pengguna, dan pencabutan grant tanpa alasan tetap ditolak.
  2. Form penetapan dan pergantian Penanggung Jawab tidak menampilkan field alasan (Vitest), dan request tanpa alasan diterima untuk keduanya.
  3. Perubahan persyaratan berkas, pindah unit indikator, aktivasi jadwal, perubahan setelan aplikasi, dan perubahan PK sebelum PK dipakai jadwal aktif tanpa alasan diterima; UI menampilkan label "Alasan (opsional)".
  4. Test parameterized: setiap aksi Q43 §43.2 tanpa alasan tetap ditolak, termasuk koreksi PK setelah PK dipakai jadwal aktif, penetapan peran, penonaktifan akun, deny, pengembalian, buka-kembali, justifikasi kegiatan, deviasi target PK, dan target PK yang menyimpang dari target Renstra menurut arah indikator (Q65 §65.2).
  5. Aksi beralasan opsional yang dikirim tanpa alasan tetap menghasilkan baris `audit_log` berisi aktor, waktu, tindakan, nilai lama/baru, dan `dasar_izin` untuk permission sensitif.

---

## Modul 11 — Rencana Aksi

### 11.1 Migrasi & model `rencana_aksi`
- **Scope:** Migrasi tabel baru `rencana_aksi` (header, per indikator × tahun): `id`, `indikator_id` (FK), `tahun` (int), `unit_id` (FK → unit), `jadwal_tahunan_id` (FK → jadwal_tahunan), `penanggung_jawab_id` (FK → users), `uraian` (text, nullable), `status_alur` enum(`draft`,`diajukan`,`diverifikasi`,`dikembalikan`,`disahkan`) default `draft`, `versi` (int, default 1), `alasan_revisi` (text, nullable), `created_by`, `created_at`, `updated_at`, `disahkan_at`, `disahkan_by`; unique(`indikator_id`, `tahun`).
- **Dependency:** 2.6 (indikator), 3.2 (jadwal_tahunan), 4.4 (penanggung_jawab efektif).
- **DoD:** Migrasi berjalan; percobaan insert baris kedua dengan kombinasi `indikator_id`+`tahun` sama ditolak database (unique constraint); model dapat dibuat via factory/test dengan `status_alur` default `draft` dan `versi = 1`.

### 11.2 Migrasi & CRUD `rencana_aksi_target`

> **Q41/Q47 (13 Oktober 2026):** target periode boleh kosong untuk indikator dinilai tahunan pada periode sebelum periode nilai akhir; kosong berarti tidak ditargetkan dan dibedakan dari `0`. PIC tidak dapat mengubah target periode yang sudah terkunci. Keduanya dikerjakan pada 11.9 (ISS-05.07).

- **Scope:** Migrasi tabel baru `rencana_aksi_target`: `id`, `rencana_aksi_id` (FK), `periode_id` (FK), `komponen_id` (FK → indikator_komponen), `nilai` (numeric, nullable), `keterangan` (text, nullable), `updated_by`, `updated_at`; unique(`rencana_aksi_id`, `periode_id`, `komponen_id`). form (Inertia + React) bagi PIC untuk mengisi target per periode per komponen, menampilkan perkiraan skor turunan (memanggil mesin perhitungan 5.13 dengan input dari `rencana_aksi_target` alih-alih `pengukuran_komponen`).
- **Dependency:** 11.1, 2.14 (indikator_komponen), 3.3 (jadwal_periode), 5.13 (mesin perhitungan, dipakai ulang).
- **DoD:** Migrasi berjalan; percobaan insert baris duplikat kombinasi (rencana_aksi_id, periode_id, komponen_id) ditolak database; form menampilkan perkiraan skor yang berubah reaktif saat nilai komponen diubah, dibuktikan dengan frontend test (Vitest + React Testing Library); nilai `0` tersimpan sah dan dibedakan dari `null` (belum diisi) di tampilan.

### 11.3 Alur status Rencana Aksi (draft → diajukan → diverifikasi → disahkan) + gerbang kelengkapan

> **Q41/Q42/Q44/Q47 (13 Oktober 2026):** gerbang pengajuan diperluas: Rencana Aksi wajib memuat **minimal satu kegiatan** yang diklaim pada tahap Rencana Aksi tanpa mewajibkan setiap periode berisi kegiatan (Q42 §42.1 butir 3, 12.6), dan setiap kegiatan tersebut memenuhi persyaratan tahap `kegiatan_perencanaan`, termasuk RAB untuk kegiatan beranggaran lebih dari 0 (12.12). Target periode sebelum periode nilai akhir boleh kosong untuk indikator dinilai tahunan, sedangkan indikator lain tetap wajib bertarget di setiap periode, dan perubahan PIC pada periode terkunci ditolak (11.9). Pengecualian `tidak_dapat_dipenuhi` pada gerbang bukti dukung tidak lagi terbentuk karena saklar unggahan dihapus (9.10). Perencanaan tetap boleh mengesahkan Rencana Aksi yang diinputnya sendiri dengan penanda self-approval (Q41 §41.3 butir 2). Gerbang kegiatan minimal, RAB, dan persyaratan tahap perencanaan kegiatan dikecualikan untuk periode lampau dan Rencana Aksi data awal 2026 (3.11), sedangkan kunci per periode tetap berlaku bagi PIC (Q46 §46.1 butir 1).

- **Scope:** Implementasi transisi status `rencana_aksi.status_alur` mengikuti pola pengukuran: `draft → diajukan` (permission `rencana_aksi:ajukan`, gerbang: setiap komponen aktif harus punya target pada seluruh periode yang diharapkan dari `jadwal_periode`, DAN seluruh `jenis_berkas` aktif bertanda wajib tahap `rencana_aksi` terpenuhi sesuai mode dan `semua_mode_wajib` (13.4) kecuali ditandai `tidak_dapat_dipenuhi` (13.7) — ditolak bila salah satu belum lengkap, kelengkapan bukan catatan), `diajukan → diverifikasi` (`rencana_aksi:verifikasi`), `diajukan`/`diverifikasi → dikembalikan` (`rencana_aksi:kembalikan`, alasan wajib), `diverifikasi → disahkan` (`rencana_aksi:sahkan`), `dikembalikan → diajukan` (revisi langsung, `rencana_aksi:ajukan`, versi baru).
- **Dependency:** 11.2, 1.11, 13.4.
- **DoD:** Test Pest: pengajuan rencana aksi dengan salah satu periode yang diharapkan belum punya target pada salah satu komponen aktif DITOLAK dengan pesan spesifik; pengajuan dengan komponen lengkap tapi bukti dukung wajib tahap rencana_aksi belum terpenuhi DITOLAK dengan pesan spesifik gerbang bukti dukung, kecuali persyaratan tsb ditandai `tidak_dapat_dipenuhi`; pengajuan dengan seluruh kombinasi periode×komponen terisi DAN bukti dukung wajib lengkap berhasil mengubah status ke `diajukan`; verifikasi/kembalikan/sahkan mengikuti hak permission masing-masing dan menolak (403) pemohon tanpa permission terkait; setiap transisi tercatat audit_log; PIC scoped unit hanya dapat mengajukan rencana aksi indikator unitnya, Perencanaan (global) dapat untuk unit mana pun.

### 11.4 Guard deadline jendela rencana_aksi_mulai/selesai bagi jalur PIC — digantikan Q41

> **Q41 (13 Oktober 2026):** Rencana Aksi tidak lagi memakai jendela penyusunan; guard ini digantikan kunci per periode pada 11.9 (ISS-05.07), sekaligus menggantikan Q16. Scope dan DoD di bawah dipertahankan sebagai riwayat.

- **Scope:** Lapisan validasi bisnis (terpisah dari permission) yang menolak `rencana_aksi:create`/`update`/`ajukan` oleh aktor jalur PIC (permission ber-scope unit) begitu tanggal hari ini berada di luar `jadwal_tahunan.rencana_aksi_mulai`–`rencana_aksi_selesai`. Permintaan dari pemegang permission global (Perencanaan) dikecualikan dari guard jendela ini — hanya dibatasi `jadwal_tahunan.penutupan`.
- **Dependency:** 11.3, 3.4 (jendela rencana_aksi_mulai/selesai).
- **DoD:** Test Pest dengan `Carbon::setTestNow()`: aktor jalur PIC mencoba create/update/ajukan rencana aksi sebelum `rencana_aksi_mulai` atau setelah `rencana_aksi_selesai` ditolak dengan pesan spesifik; Perencanaan berhasil melakukan aksi yang sama pada tanggal yang sama; Perencanaan tetap ditolak jika mencoba setelah `jadwal_tahunan.penutupan` tercapai.

### 11.5 Peringatan nilai turun antar-periode (bukan blokir)
- **Scope:** Validasi non-blocking pada form pengisian `rencana_aksi_target` (11.2): saat nilai suatu periode untuk suatu komponen lebih kecil dari nilai periode sebelumnya (periode dengan urutan lebih awal pada `periode.urutan`, komponen yang sama), tampilkan peringatan pada UI tanpa mencegah penyimpanan draft maupun pengajuan.
- **Dependency:** 11.2.
- **DoD:** frontend test (Vitest + React Testing Library): mengisi nilai Triwulan II lebih kecil dari Triwulan I pada komponen yang sama memicu flag/pesan peringatan pada respons komponen, namun submit tetap berhasil tersimpan (tidak ada exception/penolakan); mengisi nilai yang sama atau lebih besar tidak memicu peringatan tsb.

### 11.6 Peringatan + alasan wajib: total target periode terakhir vs target PK tahunan
- **Scope:** Validasi pada aksi ajukan (11.3): menghitung nilai turunan hasil mesin perhitungan (5.13) dari `rencana_aksi_target` periode dengan urutan terakhir yang diharapkan pada tahun itu, membandingkannya dengan `target_tahunan.target` indikator yang sama. Bila tidak setara (dengan toleransi presisi `indikator.presisi`), tampilkan peringatan dan WAJIBKAN pengisian field alasan sebelum pengajuan diterima — tidak memblokir pengajuan itu sendiri.
- **Dependency:** 11.3, 5.13, 2.10 (target_tahunan).
- **DoD:** Test Pest: pengajuan dengan total target periode terakhir ≠ target PK tahunan tanpa alasan ditolak (validasi wajib alasan); pengajuan yang sama dengan alasan terisi berhasil, dan `alasan_revisi` (atau kolom alasan pengajuan yang relevan) tersimpan; pengajuan dengan total target = target PK tahunan berhasil tanpa perlu alasan tambahan.

### 11.7 Buka-kembali Rencana Aksi Disahkan (`rencana_aksi:buka_kembali`)

> **Q41/Q46 (13 Oktober 2026):** buka-kembali dipakai bila target periode yang belum terkunci perlu diubah, termasuk penyesuaian setelah revisi PK (2.23), dan tidak dipakai hanya untuk memindahkan kegiatan; kegiatan yang mundur ditangani 12.3–12.4 (Q41 §41.4). Versi setelah dibuka kembali tetap tidak boleh mengubah periode yang terkunci bagi PIC (11.9). Dalam sesi koreksi setelah penutupan, PIC hanya dapat mengubah Rencana Aksi pada periode yang jendela pengisiannya dibuka kembali secara resmi; di luar itu koreksi dilakukan Perencanaan (Q41 §41.2 butir 6, Q19). Pada Rencana Aksi data awal 2026, Perencanaan dapat memakai buka-kembali untuk menambah kegiatan TW IV (Q46 §46.1 butir 3).

- **Scope:** Aksi transisi `disahkan → dikembalikan` oleh pemegang `rencana_aksi:buka_kembali` (Perencanaan/Superadmin), mewajibkan alasan, hanya tersedia selama `jadwal_tahunan` terkait belum `penutupan`. Setelah kembali ke `dikembalikan`, mengikuti alur revisi biasa: disunting pada status `dikembalikan`, lalu diajukan ulang.
- **Dependency:** 11.3.
- **DoD:** Test Pest: buka-kembali pada rencana aksi Disahkan yang jadwalnya belum penutupan berhasil mengubah status ke `dikembalikan` dan tercatat audit_log dengan alasan; percobaan pada jadwal yang sudah `penutupan` ditolak; percobaan tanpa alasan ditolak; user tanpa `rencana_aksi:buka_kembali` mendapat 403; setelah dikembalikan dan direvisi ulang, gerbang 11.3 dan 11.6 tetap berlaku pada pengajuan ulang.

### 11.8 Penetapan PIC rencana aksi mengikuti Penanggung Jawab efektif
- **Scope:** Logic pengisian `rencana_aksi.penanggung_jawab_id` saat pembuatan mengambil hasil resolusi Penanggung Jawab Efektif (4.4) pada saat itu — jejak historis; hak pengisian selanjutnya (create/update rencana_aksi_target) tetap mengikuti Penanggung Jawab yang **berlaku saat ini**, bukan yang tercatat di `penanggung_jawab_id` bila sudah berganti.
- **Dependency:** 4.4, 11.1.
- **DoD:** Test Pest: rencana aksi dibuat saat PIC A menjabat mencatat `penanggung_jawab_id = A`; setelah PIC berganti ke B (4.3), percobaan update `rencana_aksi_target` oleh A ditolak dan oleh B berhasil, sementara `rencana_aksi.penanggung_jawab_id` tetap menunjuk A sebagai jejak historis penyusunan awal.

### 11.9 Penyusunan Rencana Aksi sepanjang tahun, kunci per periode, dan periode tanpa target (ISS-05.07)
- **Scope:** Q41, Q47 §47.1–§47.2, dan Q46 §46.1; menggantikan 11.4 dan Q16. Guard jendela penyusunan dihapus: PIC efektif dengan grant unit yang sah dapat membuat, mengubah, dan mengajukan Rencana Aksi sejak jadwal tahun tersebut `aktif` sampai `penutupan`; `jadwal_tahunan.rencana_aksi_mulai`/`rencana_aksi_selesai` tidak lagi dibaca, isiannya dihapus dari form jadwal (3.4) dan tampilan jadwal (3.10), dan kolomnya dibiarkan nullable sampai migrasi lanjutan. **Kunci per periode:** periode P terkunci bagi PIC setelah `jadwal_periode.pengisian_selesai` P terlewati (tanggal bisnis Asia/Makassar); setiap perubahan Rencana Aksi oleh PIC — simpan draf, pengajuan, maupun versi setelah dikembalikan atau dibuka kembali — yang mengubah target atau daftar kegiatan periode terkunci ditolak sebagai validasi bisnis (422) dan diaudit, sedangkan perubahan pada periode yang belum terkunci tetap diterima. Perencanaan dikecualikan sampai `penutupan`, dan periode yang sudah lampau saat aktivasi jadwal terkunci bagi PIC sejak aktivasi. Kunci mengikuti nilai `pengisian_selesai` yang berlaku, termasuk hasil revisi resmi jendela pengisian (Q18, ISS-03.06), sehingga perpanjangan jendela pengisian periode P ikut menggeser kunci periode P bagi PIC (Q41 §41.2 butir 5). Dalam sesi koreksi setelah penutupan, PIC hanya dapat mengubah Rencana Aksi pada periode yang jendela pengisiannya dibuka kembali secara resmi (Q19, ISS-14.03); di luar itu koreksi dilakukan Perencanaan (Q41 §41.2 butir 6). **Dinilai tahunan:** Perencanaan menandai indikator lewat `indikator.dinilai_tahunan` (boolean, default `false`, label "Dinilai tahunan") di Sasaran & Indikator dengan `indikator:update`; perubahan tercatat di audit, dibekukan pada `jadwal_snapshot.dinilai_tahunan` saat snapshot terbentuk (3.6, 3.7), dan perubahan setelah jadwal aktif mengikuti koreksi snapshot (3.8, Q35). Penanda tidak mengubah tipe perhitungan, formula, maupun komponen. **Target periode:** gerbang pengajuan 11.3 disesuaikan — untuk indikator dinilai tahunan menurut snapshot, target periode sebelum periode nilai akhir (`periode.is_nilai_akhir`) boleh kosong, sedangkan target periode nilai akhir tetap wajib dan direkonsiliasi dengan target PK (11.6, ADR-0008); indikator lain tetap wajib bertarget di setiap periode dan ditolak dengan pesan per periode. Target kosong disimpan sebagai tidak ditargetkan (`rencana_aksi_target` bernilai kosong) dan dibedakan dari `0`; pada versi Rencana Aksi yang dibekukan, periode tanpa target dicatat dengan status `tidak_ditargetkan`, bukan `belum_diisi`, sehingga laporan dapat membedakannya dari target yang belum diisi (Q47 §47.2 butir 5). Aturan yang tetap: pengajuan pengukuran mensyaratkan Rencana Aksi disahkan (5.16), target kumulatif yang turun tetap peringatan (11.5), dan Perencanaan boleh mengesahkan Rencana Aksi yang diinputnya sendiri dengan penanda self-approval (Q41 §41.3 butir 2). Kegiatan yang mundur ditangani 12.3–12.4 tanpa buka-kembali Rencana Aksi, sedangkan buka-kembali (11.7) hanya untuk target periode yang belum terkunci. Gerbang target periode dikecualikan untuk periode lampau: target periode lampau boleh kosong, dan data awal 2026 mencatat target TW I–III kosong (3.11, Q46 §46.1 butir 6, Q47 §47.2 butir 4). Kunci per periode tidak termasuk pengecualian periode lampau (Q46 §46.1 butir 1).
- **Dependency:** 11.3, 11.5, 11.6, 11.7, 3.2, 3.4, 3.6, 3.7, 3.8, 3.9, 3.10, 3.11, 2.7, 4.4, 10.1; ISS-03.06 dan ISS-14.03 untuk revisi dan pembukaan kembali jendela pengisian PIC. Tahap 1 Q46 §46.3.
- **DoD:**
  1. Test Pest dengan `Carbon::setTestNow()`: PIC efektif bergrant membuat, mengubah, dan mengajukan Rencana Aksi selama jadwal `aktif` tanpa pemeriksaan jendela, termasuk saat `rencana_aksi_mulai`/`rencana_aksi_selesai` kosong atau tanggalnya sudah lewat karena kolom tersebut tidak lagi dibaca; aksi yang sama ditolak setelah `penutupan`.
  2. Form jadwal tahunan tidak menampilkan dan tidak memvalidasi isian jendela penyusunan Rencana Aksi (Vitest dan Pest), dan kolomnya tetap nullable.
  3. PIC yang menyimpan draf, mengajukan, atau menyimpan versi baru yang mengubah target atau daftar kegiatan periode P setelah `pengisian_selesai` P (diuji pada batas tanggal Asia/Makassar) ditolak 422 dan menghasilkan baris `audit_log`; perubahan pada periode yang belum terkunci diterima.
  4. Perencanaan dapat mengubah periode yang terkunci bagi PIC sebelum `penutupan`.
  5. Periode yang sudah lampau saat jadwal diaktifkan terkunci bagi PIC sejak aktivasi.
  6. Penanda dinilai tahunan tersimpan dan diaudit; snapshot yang terbentuk sesudahnya menyalin penanda tersebut; perubahan penanda setelah jadwal aktif tidak mengubah baris snapshot yang sudah terbit.
  7. Untuk indikator dinilai tahunan menurut snapshot, pengajuan dengan target periode sebelum periode nilai akhir kosong diterima, sedangkan pengajuan dengan target periode nilai akhir kosong ditolak; selisih target periode nilai akhir terhadap target PK tetap wajib dijelaskan (11.6).
  8. Untuk indikator yang tidak dinilai tahunan, pengajuan dengan salah satu target periode kosong ditolak dengan pesan per periode.
  9. Target kosong tersimpan sebagai tidak ditargetkan dan tampil berbeda dari target `0`; versi Rencana Aksi yang dibekukan mencatat periode tersebut berstatus `tidak_ditargetkan`, bukan `belum_diisi`.
  10. Pengajuan pengukuran pada indikator yang Rencana Aksinya belum disahkan tetap ditolak, dan target kumulatif yang turun tetap hanya peringatan.
  11. Rencana Aksi data awal 2026 dengan target TW I–III kosong dapat dicatat dan disahkan Perencanaan untuk semua indikator (pengecualian periode lampau, 3.11).
  12. Setelah `pengisian_selesai` periode P diperpanjang melalui revisi resmi jendela pengisian, kunci periode P bagi PIC mengikuti tanggal baru.
  13. Dalam sesi koreksi setelah penutupan, PIC hanya dapat mengubah Rencana Aksi pada periode yang jendela pengisiannya dibuka kembali secara resmi; perubahan PIC pada periode lain ditolak, sedangkan Perencanaan tetap dapat mengoreksi.

---

## Modul 12 — Kegiatan & Klaim Kegiatan

### 12.1 Migrasi & model `kegiatan`

> **Q42/Q46/Q49 (13 Oktober 2026):** `anggaran` wajib diisi untuk data baru, sedangkan `null` hanya ada pada data sebelum Q42 serta pada kegiatan data awal 2026 dan periode lampau (Q46 §46.1 butir 3, 12.12). `nama` hingga 500 karakter dan `tujuan` opsional diterapkan pada PR #97 (ISS-06.01) sebelum merge, atau pada 12.12 bila PR #97 sudah di-merge (Q49 §49.1).

- **Scope:** Migrasi tabel baru `kegiatan`: `id`, `unit_id` (FK, not null), `tahun` (int, not null), `periode_id` (FK, not null), `nama`, `tujuan`, `sasaran_peserta` (int, nullable), `keterangan_peserta` (varchar, nullable), `lokasi` (varchar, nullable), `tanggal_rencana` (date, nullable), `tanggal_realisasi` (date, nullable), `anggaran` (numeric, nullable), `status` enum(`rencana`,`terlaksana`,`tidak_terlaksana`,`ditunda`,`batal`) default `rencana`, `realisasi_peserta` (int, nullable), `justifikasi` (text, nullable), `kegiatan_asal_id` (uuid, nullable, FK → kegiatan.id), `uraian_pelaksanaan` (text, nullable), `kendala` (text, nullable), `strategi_tindaklanjut` (text, nullable), `created_by`, `created_at`, `updated_at`.
- **Dependency:** 1.4 (unit), 3.1 (periode).
- **DoD:** Migrasi berjalan; FK `kegiatan_asal_id` merujuk ke tabel yang sama (self-referencing) dan boleh NULL; percobaan insert tanpa `unit_id`/`periode_id` ditolak database (NOT NULL); model dapat dibuat via factory dengan status default `rencana`.

### 12.2 CRUD Kegiatan (Inertia + React) + validasi kepemilikan unit

> **Q41/Q42/Q49 (13 Oktober 2026):** kegiatan ditambahkan dari halaman Rencana Aksi per periode; aksi ini membuat kegiatan per unit × periode sekaligus klaimnya ke Rencana Aksi, dan kegiatan dapat disalin ke periode lain yang belum terkunci (Q42 §42.1 butir 2, Q49 §49.2, 12.6). Anggaran wajib diisi, kecuali pada kegiatan data awal 2026 dan periode lampau (Q46 §46.1 butir 3, 12.12); `nama` hingga 500 karakter dan `tujuan` opsional (Q49 §49.1). PIC tidak dapat menambah kegiatan pada periode yang terkunci (11.9). Detail kegiatan, termasuk anggaran dan RAB, tetap dibatasi unit (Q12, Q42 §42.5, Q49 §49.3).

- **Scope:** Form list/create/edit Kegiatan per periode memakai `kegiatan:read`, `kegiatan:create`, dan `kegiatan:update` sebagai permission **scope unit**. User operasional dengan grant hanya dapat membaca/membuat/mengubah kegiatan pada unit grant-nya; Perencanaan/Superadmin dapat melakukannya secara global sesuai matrix. **`kegiatan:delete` berbeda:** permission ini bertipe **global, sensitif, dan hanya untuk Perencanaan/Superadmin**; bukan kandidat grant-unit dan tidak diberikan kepada Pegawai/PIC hanya karena memiliki `kegiatan:create/update`.
- **Dependency:** 12.1, 1.10, 1.11, 1.20.
- **DoD:** 
  1. User dengan `kegiatan:read/create/update` scoped unit A berhasil untuk unit A dan ditolak untuk unit B.
  2. Perencanaan/Superadmin dengan izin global dapat membaca/membuat/mengubah lintas unit.
  3. Pegawai/PIC/user grant unit yang mencoba `kegiatan:delete` menerima 403 kecuali konfigurasi permission final secara eksplisit memberi allow global yang sah.
  4. Perencanaan/Superadmin dapat delete hanya bila seluruh guard domain mengizinkan; alasan wajib dan `audit_log.dasar_izin` terisi karena `kegiatan:delete` sensitif.
  5. `kegiatan:delete` tidak tampil pada Form Grant per Unit.
  6. create/update/delete yang berhasil tercatat audit sesuai sifat permission.

### 12.3 Transisi status Kegiatan (`tidak_terlaksana`/`ditunda`/`batal`) + justifikasi wajib

> **Q41/Q43/Q45 (13 Oktober 2026):** status kegiatan dapat diperbarui sepanjang periode berjalan, mis. setiap bulan, tanpa entitas bulanan (Q45 §45.1 butir 4). Kegiatan yang ditandai `ditunda` beserta justifikasi dan periode tujuan menghasilkan kegiatan lanjutan (12.4), dan justifikasinya menjadi catatan resmi yang dilihat Perencanaan saat verifikasi pengukuran (Q41 §41.4). Justifikasi tetap wajib (Q43 §43.2 butir 5). Dikerjakan pada ISS-06.04 (tahap 2).

- **Scope:** Implementasi transisi `rencana → tidak_terlaksana`/`ditunda`/`batal` (WAJIB mengisi `justifikasi`, TIDAK memerlukan bukti dukung pelaksanaan apa pun). Kegiatan tidak pernah dihapus akibat gagal terlaksana — hanya berubah status. Transisi `rencana → terlaksana` (dengan gerbang bukti dukung) ditangani terpisah di 12.11.
- **Dependency:** 12.2.
- **DoD:** Test Pest: transisi ke `tidak_terlaksana`/`ditunda`/`batal` tanpa `justifikasi` ditolak validasi; transisi dengan justifikasi terisi berhasil, kegiatan tetap ada di database (bukan soft/hard delete); ketiga transisi ini tidak pernah memeriksa kelengkapan bukti dukung tahap kegiatan (dibuktikan dengan skenario tanpa satu pun bukti dukung terunggah, transisi tetap berhasil selama justifikasi terisi).

### 12.4 Geser periode: kegiatan baru dengan `kegiatan_asal_id`

> **Q41 (13 Oktober 2026):** saat pelaporan, kegiatan yang ditandai `ditunda` dengan periode tujuan menghasilkan kegiatan lanjutan pada periode tujuan yang tertaut lewat `kegiatan_asal_id` dan diklaim ke Rencana Aksi pada tahap pengukuran (`sumber_klaim = pengukuran`, 12.6). Rencana Aksi yang sudah disahkan tidak dibuka kembali hanya untuk memindahkan kegiatan (Q41 §41.4). Kegiatan lanjutan menyalin anggaran kegiatan asal dan anggarannya dapat diubah; kegiatan asal yang `ditunda` dan sudah memiliki kegiatan lanjutan tidak dihitung dalam total anggaran (Q42 §42.2 butir 6, 12.12). Kegiatan lanjutan hanya dapat ditujukan ke periode lain pada tahun yang sama; kegiatan yang mundur ke tahun berikutnya dicatat sebagai kegiatan baru pada Rencana Aksi tahun itu. Berkas kegiatan asal, termasuk RAB, tidak disalin, dan persyaratan tahap perencanaan kegiatan tidak diperiksa ulang untuk kegiatan lanjutan yang diklaim pada tahap pengukuran (Q41 §41.4 butir 5). Dikerjakan pada ISS-06.05 (tahap 2).

- **Scope:** Aksi khusus "Geser ke periode berikutnya" pada kegiatan berstatus `ditunda`/`tidak_terlaksana`: membuat BARIS KEGIATAN BARU pada periode tujuan (periode_id berbeda, tahun yang sama menurut Q41 §41.4 butir 5; semula tahun bisa sama/berbeda), menyalin data relevan (nama, tujuan, unit_id) dan mengisi `kegiatan_asal_id` menunjuk kegiatan asal. Kegiatan asal tidak diubah/dihapus.
- **Dependency:** 12.3.
- **DoD:** Test Pest: aksi geser menghasilkan baris kegiatan baru dengan `kegiatan_asal_id` = id kegiatan asal, `periode_id` = periode tujuan, status awal `rencana`; kegiatan asal tetap ada dengan periode dan status aslinya tidak berubah; query kegiatan pada kedua periode (asal dan tujuan) masing-masing mengembalikan baris kegiatan yang sesuai — membuktikan "kegiatan yang sama tampil di dua periode" sebagai baris terpisah yang tertaut; percobaan menjadikan periode pada tahun lain sebagai tujuan ditolak, dan berkas kegiatan asal (termasuk RAB) tidak ikut tersalin (Q41 §41.4 butir 5).

### 12.5 Narasi per kegiatan (uraian_pelaksanaan, kendala, strategi_tindaklanjut)

> **Q45 (13 Oktober 2026):** narasi per kegiatan tetap tersedia dan opsional; narasi wajib berada pada tingkat pengukuran (5.5), dan rekapitulasi menampilkan narasi pengukuran bersama daftar kegiatan yang diklaim (Q45 §45.1 butir 2, 8.3).

- **Scope:** Form input narasi kegiatan sebagai bagian dari update kegiatan (12.2/12.11), disimpan per baris kegiatan (bukan blok tunggal di level indikator).
- **Dependency:** 12.2.
- **DoD:** frontend test (Vitest + React Testing Library): mengisi ketiga field narasi pada kegiatan tersimpan dan dapat dibaca ulang per kegiatan; dua kegiatan berbeda pada indikator yang sama (via klaim) memiliki narasi masing-masing yang independen, tidak saling menimpa.

### 12.6 Migrasi & CRUD `klaim_kegiatan`

> **Q41/Q42/Q49 (13 Oktober 2026):** penambahan kegiatan dari halaman Rencana Aksi per periode membuat kegiatan sekaligus klaim `sumber_klaim = rencana_aksi` (Q42 §42.1 butir 2), dan Rencana Aksi diajukan dengan minimal satu kegiatan berklaim tahap Rencana Aksi (Q42 §42.1 butir 3; gerbang pada 11.3). Saat menambah, PIC dapat menyalin kegiatan ke satu atau lebih periode lain pada Rencana Aksi yang sama: setiap salinan menjadi kegiatan tersendiri per unit × periode dengan klaim `rencana_aksi` sendiri; nama, tujuan, dan anggaran ikut disalin dan dapat diubah, sedangkan berkas termasuk RAB tidak ikut; salinan hanya untuk periode yang belum terkunci (Q49 §49.2). Kegiatan lanjutan hasil penundaan diklaim pada tahap pengukuran (12.4). Satu kegiatan tetap dapat diklaim lebih dari satu Rencana Aksi/IKU dalam unit yang sama ("kegiatan bersama", 12.12) dan klaim lintas unit tetap ditolak (12.7). Dikerjakan pada ISS-06.02 (tahap 1).

- **Scope:** Migrasi tabel baru `klaim_kegiatan`: `id`, `rencana_aksi_id` (FK), `kegiatan_id` (FK), `komponen_id` (FK → indikator_komponen, nullable), `arah_dampak` enum(`menambah`,`mengurangi`) default `menambah`, `catatan` (varchar, nullable), `sumber_klaim` enum(`rencana_aksi`,`pengukuran`), `pengukuran_id` (FK → pengukuran, nullable), `created_by`, `created_at`. Unique index pada (`rencana_aksi_id`, `kegiatan_id`, `komponen_id`) dengan `COALESCE(komponen_id, ...)` mengingat PostgreSQL memperlakukan NULL sebagai nilai berbeda. form (Inertia + React) untuk membuat klaim baik dari layar rencana aksi (`sumber_klaim=rencana_aksi`) maupun layar pengukuran (`sumber_klaim=pengukuran`, `pengukuran_id` terisi).
- **Dependency:** 11.1, 12.1, 2.14.
- **DoD:** Migrasi berjalan; percobaan insert klaim duplikat pada kombinasi (rencana_aksi_id, kegiatan_id, komponen_id) sama — termasuk kasus `komponen_id` NULL pada baris kedua dengan kombinasi lain sama — ditolak sesuai desain unique index; klaim dari layar rencana aksi tersimpan dengan `sumber_klaim=rencana_aksi`, `pengukuran_id=null`; klaim dari layar pengukuran tersimpan dengan `sumber_klaim=pengukuran`, `pengukuran_id` terisi.

### 12.7 Validasi klaim lintas unit
- **Scope:** Guard yang menolak pembuatan klaim bila `kegiatan.unit_id` berbeda dari unit rencana aksi/indikator yang diklaim.
- **Dependency:** 12.6.
- **DoD:** Test Pest: percobaan klaim kegiatan milik unit B ke rencana aksi indikator milik unit A ditolak dengan pesan spesifik; klaim kegiatan unit A ke rencana aksi indikator unit A berhasil.

### 12.8 Klaim TIDAK mengubah nilai komponen otomatis + tampilan pengingat
- **Scope:** Memastikan pembuatan/penghapusan `klaim_kegiatan` sama sekali tidak memicu perhitungan ulang atau penulisan otomatis ke `pengukuran_komponen`/`rencana_aksi_target` — keduanya tetap murni diisi manual. Implementasi tampilan "kegiatan terkait" pada layar pengisian pengukuran (5.14) dan rencana aksi (11.2): daftar kegiatan yang sudah diklaim untuk periode yang sama, sebagai pembanding/pengingat, tanpa tombol "jumlahkan otomatis".
- **Dependency:** 12.6, 5.14, 11.2.
- **DoD:** Test Pest: membuat klaim baru untuk suatu komponen tidak mengubah nilai `pengukuran_komponen`/`rencana_aksi_target` komponen tsb sama sekali (assert nilai sebelum dan sesudah pembuatan klaim identik); frontend test (Vitest + React Testing Library): layar pengisian pengukuran menampilkan daftar kegiatan diklaim untuk periode yang sama sebagai informasi tampilan, terpisah dari input nilai komponen.

### 12.9 Kegiatan batal/tidak_terlaksana tetap dapat diklaim
- **Scope:** Memastikan validasi klaim (12.6, 12.7) tidak menolak kegiatan berdasarkan status `batal`/`tidak_terlaksana` — kegiatan pada status apa pun (kecuali dihapus, yang memang tidak terjadi di sistem ini) tetap dapat diklaim.
- **Dependency:** 12.6, 12.3.
- **DoD:** Test Pest: klaim terhadap kegiatan berstatus `batal` dan `tidak_terlaksana` masing-masing berhasil tersimpan tanpa penolakan; status kegiatan tsb ikut tampil pada tampilan rekapitulasi (diverifikasi lewat komponen 8.3) sebagai bagian narasi kendala.

### 12.10 Penghapusan klaim (sebelum rencana aksi disahkan)

> **Q41 (13 Oktober 2026):** PIC tidak dapat menghapus klaim tahap Rencana Aksi pada periode yang terkunci karena daftar kegiatan periode tersebut tidak boleh berubah (Q41 §41.2 butir 2, 11.9).

- **Scope:** Aksi hapus `klaim_kegiatan` oleh PIC pembuat klaim atau Perencanaan, hanya diizinkan selama `rencana_aksi` induknya belum berstatus `disahkan`.
- **Dependency:** 12.6, 11.3.
- **DoD:** Test Pest: penghapusan klaim pada rencana aksi berstatus `draft`/`diajukan`/`diverifikasi`/`dikembalikan` berhasil dan tercatat audit_log; penghapusan pada rencana aksi berstatus `disahkan` ditolak; pemohon selain pembuat klaim/Perencanaan (mis. PIC unit lain) mendapat 403.

### 12.11 Gerbang bukti dukung pada transisi Kegiatan `rencana → terlaksana`

> **Q42/Q44 (13 Oktober 2026):** tahap `kegiatan` adalah tahap pelaksanaan dan tetap diperiksa saat `terlaksana` (Q42 §42.4 butir 2; Q22 tetap), sedangkan persyaratan tahap `kegiatan_perencanaan` diperiksa saat Rencana Aksi diajukan (12.12). Pengecualian `tidak_dapat_dipenuhi` tidak lagi terbentuk karena saklar unggahan dihapus (9.10).

- **Scope:** Implementasi transisi `rencana → terlaksana` (mengisi tanggal_realisasi, realisasi_peserta, narasi) dengan **gerbang kelengkapan bukti dukung**: sebelum transisi diterima, sistem memeriksa apakah seluruh `jenis_berkas` aktif bertanda `wajib` bertahap `kegiatan` yang berlaku untuk kegiatan ini (indikator terkait via klaim, atau global bila `indikator_id = null`) sudah terpenuhi menurut mode yang diizinkan (file/tautan/teks) dan aturan `semua_mode_wajib` (lihat 13.4), kecuali persyaratan tsb ditandai `tidak_dapat_dipenuhi` (13.7). Transisi ke `tidak_terlaksana`/`ditunda`/`batal` (12.3) **tidak pernah** melewati gerbang ini.
- **Dependency:** 12.2, 13.1, 13.4.
- **DoD:** Test Pest: transisi ke `terlaksana` untuk kegiatan yang persyaratan bukti dukung wajib tahap `kegiatan`-nya belum terpenuhi DITOLAK dengan pesan spesifik gerbang bukti dukung; melengkapi bukti dukung (mode apa pun yang diizinkan) lalu mencoba transisi ulang BERHASIL; kegiatan tanpa persyaratan `jenis_berkas` wajib tahap `kegiatan` yang berlaku (mis. `indikator_id` tidak match klaim manapun dan tidak ada yang global) berhasil bertransisi tanpa syarat tambahan; persyaratan yang ditandai `tidak_dapat_dipenuhi` tidak menghalangi transisi; setiap penolakan tercatat audit_log percobaan gagal (lihat 10.5).

### 12.12 Anggaran kegiatan, RAB, persyaratan tahap perencanaan kegiatan, dan penjumlahan anggaran (ISS-06.08)

> **Status 18 Oktober 2026:** dikerjakan dalam dua PR. PR pertama menerapkan anggaran wajib (DoD 1, 9), tahap `kegiatan_perencanaan` dengan penanda dan contoh berkas (DoD 3, 5), panel berkas perencanaan pada halaman ubah kegiatan, gerbang pengajuan Rencana Aksi beserta pembekuan evaluasinya (DoD 4, 10), dan imutabilitas berkas (DoD 11). Kegiatan lama dengan anggaran kosong menolak pengajuan dengan pesan "anggaran belum diisi" (asumsi kerja, menunggu persetujuan PM). PR kedua menerapkan penjumlahan dan penanda total (DoD 2, 7; total per IKU dan per unit/tahun di service, tampilan dashboard mengikuti Modul 7/ISS-10.01; salin anggaran pada kegiatan lanjutan mengikuti 12.4) serta pembatasan anggaran per kegiatan (DoD 8). DoD 6 tidak diubah ISS-06.08 dan mengikuti 12.11.
- **Scope:** Q42 §42.2–§42.5 (menggantikan Q28), Q49 §49.1 butir 3, dan Q46 §46.1. **Anggaran:** `kegiatan.anggaran` (kolom sudah ada, nullable) wajib diisi saat kegiatan dibuat atau diubah, berupa satu angka rupiah nonnegatif; `0` sah dan kegiatan beranggaran nol tetap dapat diklaim. `null` hanya ada pada data sebelum Q42 serta pada kegiatan data awal 2026 dan kegiatan periode lampau, yang boleh tanpa anggaran karena Rencana Aksi resmi hanya memuat anggaran per Rincian Output (Q46 §46.1 butir 3); `null` tampil sebagai "belum diisi", tidak dihitung sebagai 0, dan total yang memuatnya diberi penanda. **Persyaratan dua tahap:** `jenis_berkas.tahap` memperoleh nilai `kegiatan_perencanaan` (13.1) untuk dokumen tahap perencanaan kegiatan (mis. RAB), sedangkan tahap `kegiatan` tetap menjadi tahap pelaksanaan yang diperiksa saat kegiatan ditandai `terlaksana` (12.11, Q22). Persyaratan dapat dilampiri **contoh berkas** (`berkas` berinduk `jenis_berkas`, 13.2) yang dapat diunduh PIC; aplikasi tidak membuat template RAB. Contoh berkas bukan bukti: Perencanaan dapat menambah, mengganti, atau menghapusnya kapan saja, penghapusannya wajib alasan (Q43 §43.2 butir 2), dan contoh berkas tidak dibekukan ke versi pengajuan (Q42 §42.3 butir 4). **Gerbang pengajuan Rencana Aksi (11.3):** setiap kegiatan yang diklaim pada tahap Rencana Aksi harus memenuhi persyaratan wajib tahap `kegiatan_perencanaan` yang dievaluasi per kegiatan (13.4), termasuk RAB berformat Excel atau PDF untuk kegiatan beranggaran lebih dari 0; penolakan menyebut daftar kegiatan dan persyaratan yang kurang. Persyaratan yang diperiksa saat Rencana Aksi indikator X diajukan adalah persyaratan global ditambah persyaratan khusus indikator X; untuk kegiatan bersama, persyaratan khusus indikator lain diperiksa saat Rencana Aksi indikator tersebut diajukan (Q42 §42.4 butir 4). Persyaratan tahap tersebut dapat ditandai `jenis_berkas.hanya_kegiatan_beranggaran` (boolean, default `false`); persyaratan RAB memakai penanda ini sehingga hanya diperiksa untuk kegiatan beranggaran lebih dari 0, sedangkan persyaratan lain pada tahap yang sama berlaku untuk setiap kegiatan (Q42 §42.3 butir 3). RAB disimpan sebagai `berkas` berinduk `kegiatan`. Berkas yang dipakai memenuhi persyaratan tahap perencanaan kegiatan tidak dapat dihapus sejak Rencana Aksi yang mengklaim kegiatan tersebut diajukan; koreksinya lewat pengembalian Rencana Aksi atau berkas pengganti (Q25), mengikuti pola bukti Rencana Aksi (Q36; Q42 §42.4 butir 5). **Penjumlahan:** halaman Rencana Aksi menampilkan total anggaran per periode dan per Rencana Aksi, dan total per IKU disediakan untuk dashboard (catatan Modul 7); kegiatan yang diklaim lebih dari satu IKU tampil penuh di setiap IKU dengan penanda "kegiatan bersama", sedangkan total per unit dan per tahun dihitung dari kegiatan unik. Kegiatan lanjutan (12.4) menyalin anggaran kegiatan asal dan anggarannya dapat diubah; kegiatan asal yang `ditunda` dan sudah memiliki kegiatan lanjutan tidak dihitung dalam total sehingga anggarannya hanya terhitung pada kegiatan lanjutan; total yang sudah dibekukan pada versi Rencana Aksi tidak berubah (Q42 §42.2 butir 6). **Akses:** anggaran per kegiatan dan berkas RAB mengikuti akses detail kegiatan yang dibatasi unit (Q12); Perencanaan dan Pimpinan melihat seluruhnya; total per IKU di dashboard mengikuti akses dashboard. Realisasi belanja, pagu induk, dan rekonsiliasi anggaran tidak dikelola. Batas ukuran dan format RAB mengikuti persyaratan dan batas teknis (9.10). Bila PR #97 (ISS-06.01) sudah di-merge sebelum Q49 diterapkan, `nama` kegiatan hingga 500 karakter dan `tujuan` opsional dikerjakan di sini. Rencana Aksi data awal 2026 dan periode lampau dikecualikan dari gerbang RAB dan persyaratan tahap perencanaan kegiatan (3.11).
- **Dependency:** 12.1, 12.2, 12.6, 12.11, 11.3, 13.1, 13.2, 13.4, 13.5, 9.10, 3.11, 10.1; aturan total untuk kegiatan lanjutan diuji setelah 12.4 tersedia. Tahap 1 Q46 §46.3.
- **DoD:**
  1. Test Pest: membuat atau mengubah kegiatan dengan `anggaran` negatif ditolak, dan tanpa `anggaran` ditolak kecuali pada kegiatan data awal 2026 dan periode lampau; `0` diterima dan kegiatan beranggaran 0 dapat diklaim.
  2. Kegiatan lama dengan `anggaran` kosong tampil "belum diisi", tidak dihitung sebagai 0, dan total yang memuatnya diberi penanda.
  3. Perencanaan menyimpan persyaratan bertahap `kegiatan_perencanaan` beserta contoh berkas, dan PIC dapat mengunduh contoh berkas melalui route berizin; contoh berkas dapat diganti atau dihapus Perencanaan kapan saja, penghapusan tanpa alasan ditolak, dan contoh berkas tidak ikut dibekukan pada versi pengajuan.
  4. Pengajuan Rencana Aksi yang memuat kegiatan berklaim tahap Rencana Aksi tanpa persyaratan wajib `kegiatan_perencanaan`, termasuk kegiatan beranggaran lebih dari 0 tanpa RAB Excel/PDF, ditolak dengan daftar kegiatan dan persyaratan yang kurang; setelah dilengkapi, pengajuan diterima.
  5. Persyaratan bertanda `hanya_kegiatan_beranggaran` (RAB) tidak diperiksa untuk kegiatan beranggaran 0, sehingga kegiatan tersebut tanpa RAB tidak menghalangi pengajuan, sedangkan persyaratan lain tahap `kegiatan_perencanaan` tetap diperiksa untuk kegiatan beranggaran 0.
  6. Transisi kegiatan ke `terlaksana` tetap memeriksa persyaratan tahap `kegiatan` (12.11).
  7. Total anggaran per periode, per Rencana Aksi, dan per IKU sesuai data seed; kegiatan yang diklaim dua IKU tampil penuh di kedua IKU dengan penanda "kegiatan bersama", dan total per unit/tahun menghitungnya sekali; total pada versi Rencana Aksi yang sudah dibekukan tidak berubah; setelah 12.4 tersedia, kegiatan lanjutan menyalin anggaran kegiatan asal dan kegiatan asal `ditunda` yang sudah memiliki lanjutan tidak ikut dihitung.
  8. Pengguna tanpa akses detail kegiatan unit tersebut tidak menerima anggaran per kegiatan maupun RAB pada props/respons, sedangkan Perencanaan dan Pimpinan menerima seluruhnya.
  9. Rencana Aksi data awal 2026 dan periode lampau lolos tanpa RAB dan tanpa persyaratan tahap perencanaan kegiatan, dan kegiatannya dapat disimpan tanpa anggaran dengan tampilan "belum diisi".
  10. Pengajuan Rencana Aksi indikator X hanya memeriksa persyaratan `kegiatan_perencanaan` global dan khusus indikator X; persyaratan khusus indikator Y atas kegiatan bersama tidak menghalangi pengajuan indikator X dan baru diperiksa saat Rencana Aksi indikator Y diajukan.
  11. Penghapusan RAB atau berkas persyaratan tahap perencanaan kegiatan ditolak (422) sejak Rencana Aksi yang mengklaim kegiatan itu diajukan, berhasil sebelum diajukan atau setelah Rencana Aksi dikembalikan, dan berkas pengganti tersimpan sebagai baris baru dengan `alasan_koreksi` tanpa mengubah berkas lama.

---

## Modul 13 — Bukti Dukung: Persyaratan, Tiga Mode & Enam Induk Lampiran

### 13.1 Migrasi `jenis_berkas` (tahap `kegiatan` + kolom mode + `semua_mode_wajib`) + CRUD granular (`jenis_berkas:create/read/update/delete`)

> **Q42/Q43/Q44 (13 Oktober 2026):** `tahap` memperoleh nilai `kegiatan_perencanaan`, persyaratan dapat dilampiri contoh berkas, dan `jenis_berkas` memperoleh kolom `hanya_kegiatan_beranggaran` (boolean, default `false`) untuk persyaratan RAB yang hanya diperiksa pada kegiatan beranggaran lebih dari 0 (Q42 §42.3 butir 3–4, 12.12). `ukuran_maks_kb` paling besar 102400 (100 MB), format atau ukuran yang dikosongkan memakai default konfigurasi, dan menu Persyaratan Berkas dipindah ke grup Perencanaan Kinerja (9.10). Alasan perubahan persyaratan menjadi opsional (Q43 §43.3 butir 3, 10.7).

- **Scope:** Migrasi tabel baru `jenis_berkas`: `id`, `nama`, `tahap` enum(`rencana_aksi`,`pengukuran`,**`kegiatan`**), `indikator_id` (FK, nullable — null berarti berlaku untuk semua indikator), `wajib` (boolean, default false), `keterangan` (text, nullable), `format_diizinkan` (varchar, nullable), `ukuran_maks_kb` (int, nullable), `izinkan_file` (boolean, not null, default `true`), `izinkan_tautan` (boolean, not null, default `false`), `izinkan_teks` (boolean, not null, default `false`), `semua_mode_wajib` (boolean, not null, default `false`), `urutan` (int, not null, default 0), `aktif` (boolean, default true), `created_by`, `created_at`, `updated_at`. Validasi aplikasi saat penyimpanan: minimal satu dari `izinkan_file`/`izinkan_tautan`/`izinkan_teks` harus `true` — penyimpanan ditolak bila ketiganya `false`. CRUD (Inertia + React) bagi Perencanaan untuk mendefinisikan persyaratan bukti dukung per tahap (termasuk `kegiatan`), per indikator atau global, dengan permission granular: `jenis_berkas:create`, `jenis_berkas:read` (dipegang seluruh peran), `jenis_berkas:update`, `jenis_berkas:delete`. `jenis_berkas:update` dan `jenis_berkas:delete` bertanda `sensitif=true` — setiap aksinya mencatat `dasar_izin` pada `audit_log` (lihat 1.20).
- **Dependency:** 2.6 (indikator), 1.10, 1.11.
- **DoD:** Migrasi berjalan; kolom `tahap` menerima ketiga nilai enum termasuk `kegiatan`; percobaan menyimpan `jenis_berkas` dengan `izinkan_file=false`, `izinkan_tautan=false`, `izinkan_teks=false` sekaligus DITOLAK validasi; jenis_berkas dengan `indikator_id = null` dapat dibuat dan diverifikasi berlaku untuk semua indikator (query test); user dengan `jenis_berkas:create`/`update`/`delete` (Perencanaan/Superadmin) berhasil CRUD penuh termasuk mencentang kombinasi mode apa pun; user dengan HANYA `jenis_berkas:read` (mis. Admin, Pimpinan, Pegawai) dapat melihat daftar persyaratan namun mendapat 403 saat mencoba create/update/delete; `jenis_berkas:update` dan `jenis_berkas:delete` masing-masing menghasilkan baris `audit_log` dengan `dasar_izin` terisi; satu indikator dapat memiliki jenis_berkas bertahap `rencana_aksi`, `pengukuran`, DAN `kegiatan` sekaligus (dibuktikan test dengan 3 baris berbeda tahap untuk indikator yang sama).

### 13.2 Migrasi & model polimorfik `berkas` (kolom `mode`, `tautan`, `isi_teks`, enam induk)

> **Q40/Q42/Q48 (13 Oktober 2026):** `berkasable_type` memperoleh induk baru `jenis_berkas` untuk contoh berkas (12.12) serta `jadwal_tahunan` dan `jadwal_periode` untuk arsip PDF bertanda tangan Rencana Aksi tahunan dan Laporan Kinerja Triwulan (8.5); lampiran per versi PK (`renstra_pk_versi`, rancangan) mengikuti 2.23 pada tahap 3. `berkas` juga memperoleh kolom `keterangan` (nullable) untuk keterangan berkas, mis. arsip dokumen resmi dan lampiran tambahan PK, sedangkan `alasan_koreksi` tetap khusus untuk berkas pengganti (Q48 §48.2 butir 6).

- **Scope:** Migrasi tabel baru `berkas`: `id`, `jenis_berkas_id` (FK, nullable), `berkasable_type` — ENAM nilai: `rencana_aksi`/`pengukuran`/`kegiatan` (tiga induk bergerbang, mengacu `jenis_berkas`) DAN `renstra`/`renstra_pk`/`regulasi` (tiga induk dokumen dasar, TANPA `jenis_berkas` — `jenis_berkas_id` selalu NULL untuk ketiganya), `berkasable_id` (uuid), `mode` enum(`file`,`tautan`,`teks`) not null, `nama_asli` (nullable), `path` (nullable), `mime` (nullable), `ukuran_bytes` (nullable), `tautan` (varchar 2048, nullable — wajib diisi bila `mode=tautan`, divalidasi skema http/https), `isi_teks` (text, nullable — wajib diisi bila `mode=teks`), `uploaded_by`, `created_at`, `dihapus_pada` (timestamp, nullable), `dihapus_oleh` (FK, nullable).
- **Dependency:** 13.1, 2.16, 2.18, 2.20.
- **DoD:** Migrasi berjalan; baris `berkas` dapat dibuat dengan `jenis_berkas_id = null` (lampiran bebas, WAJIB untuk ketiga induk dokumen dasar) maupun terisi (persyaratan bergerbang, hanya untuk tiga induk pertama), untuk ketiga mode; percobaan insert baris `mode=file` tanpa `path` DITOLAK aplikasi; percobaan insert baris `mode=tautan` tanpa `tautan` terisi DITOLAK aplikasi; percobaan insert baris `mode=teks` tanpa `isi_teks` terisi DITOLAK aplikasi; query berdasarkan `berkasable_type` + `berkasable_id` mengembalikan bukti dukung milik induk yang benar untuk KEENAM jenis induk (rencana_aksi, pengukuran, kegiatan, renstra, renstra_pk, regulasi), lintas ketiga mode; kolom `berkasable_type` menerima keenam nilai (test enum/CHECK constraint atau validasi aplikasi, sesuai pendekatan yang dipakai).

### 13.3 Validasi pemilihan mode oleh pengirim bukti/PIC operasional sesuai `jenis_berkas`
- **Scope:** Guard aplikasi yang menegakkan bahwa `mode` yang dikirim aktor berizin saat mengirim bukti dukung terhadap suatu `jenis_berkas_id` HARUS termasuk mode yang diizinkan pada baris `jenis_berkas` terkait (`izinkan_file`/`izinkan_tautan`/`izinkan_teks`); permintaan dengan mode di luar daftar yang diizinkan DITOLAK sistem. Lampiran bebas (tanpa `jenis_berkas_id`, `berkasable_type = kegiatan` atau lainnya) boleh memakai mode apa pun tanpa guard ini.
- **Dependency:** 13.2, 13.1.
- **DoD:** Test Pest: mengirim bukti dukung mode `tautan` terhadap `jenis_berkas` yang hanya `izinkan_file=true` DITOLAK dengan pesan spesifik; mengirim mode `file` terhadap `jenis_berkas` yang mengizinkan `izinkan_file` dan `izinkan_tautan` (dua-duanya `true`) BERHASIL; mengirim bukti dukung tanpa `jenis_berkas_id` (lampiran bebas) dengan mode apa pun BERHASIL tanpa guard ini diterapkan.

### 13.4 Gerbang kelengkapan bukti dukung tiga tahap (rencana_aksi / pengukuran / kegiatan)

> **Q42/Q44 (13 Oktober 2026):** service yang sama juga mengevaluasi tahap `kegiatan_perencanaan` per kegiatan berklaim tahap Rencana Aksi saat Rencana Aksi diajukan (12.12). Penanda `tidak_dapat_dipenuhi` tidak lagi dibuat karena saklar unggahan dihapus (9.10); penanda lama tetap histori.

- **Scope:** Service tunggal yang dipakai ulang oleh tiga titik gerbang berbeda — pengajuan rencana_aksi (11.3), pengajuan pengukuran (5.16), dan transisi kegiatan ke `terlaksana` (12.11) — untuk memeriksa: bagi setiap `jenis_berkas` aktif bertanda `wajib` pada tahap yang sesuai (dan `indikator_id` cocok atau `null`), apakah kombinasi baris `berkas` yang sudah dikirim untuk induk ini memenuhi aturan `semua_mode_wajib` (bila `true`: seluruh mode yang diizinkan pada `jenis_berkas` tsb harus punya minimal satu baris `berkas` terkait; bila `false`: minimal satu mode yang diizinkan sudah terisi). Persyaratan yang ditandai `tidak_dapat_dipenuhi` (13.7) dianggap terpenuhi oleh service ini.
- **Dependency:** 13.3, 13.1.
- **DoD:** Test Pest langsung terhadap service (bukan lewat endpoint): persyaratan `semua_mode_wajib=false` dengan `izinkan_file` dan `izinkan_tautan` sama-sama `true`, dan hanya baris `berkas` mode tautan yang terkirim, dinyatakan TERPENUHI oleh service; persyaratan `semua_mode_wajib=true` dengan kombinasi sama tapi hanya mode tautan yang terkirim dinyatakan BELUM TERPENUHI (mode file masih kosong); persyaratan yang ditandai `tidak_dapat_dipenuhi` dinyatakan TERPENUHI oleh service terlepas ada/tidaknya baris `berkas`; service yang sama dipanggil dari ketiga titik gerbang (5.16, 11.3, 12.11) dibuktikan lewat referensi kode/pemanggilan fungsi yang sama pada test masing-masing modul (tidak ada duplikasi logic tiga kali).

### 13.5 Pengiriman bukti dukung tiga mode: unggah file (streamed download), isi tautan, tulis teks — enam induk

> **Q44 (13 Oktober 2026):** pemeriksaan saklar `pengaturan.berkas.unggahan_aktif` dan fallback ke kunci grup `berkas` dihapus; format dan ukuran memakai persyaratan atau default konfigurasi aplikasi dengan batas atas 100 MB (9.10). Penyimpanan privat dan unduhan lewat route berizin tetap berlaku.

- **Scope:** halaman/komponen Inertia + React pengiriman bukti dukung untuk suatu induk (keenam nilai `berkasable_type`) yang menampilkan mode-mode yang diizinkan — untuk tiga induk bergerbang (rencana_aksi/pengukuran/kegiatan) sesuai `jenis_berkas` terkait (§13.1); untuk tiga induk dokumen dasar (renstra/renstra_pk/regulasi) mode dipilih bebas TANPA daftar persyaratan mengikat — dan memproses submit sesuai mode dipilih: `file` — menyimpan file fisik di `storage/app/berkas/...`, memvalidasi format & ukuran sesuai `jenis_berkas.format_diizinkan`/`ukuran_maks_kb` bila induknya bergerbang (fallback ke kunci `pengaturan` grup `berkas`, 9.7 bila kosong — digantikan default konfigurasi, Q44) atau default grup `berkas` langsung bila induknya dokumen dasar, DAN memeriksa saklar `pengaturan.berkas.unggahan_aktif` sebelum menerima unggahan apa pun (pemeriksaan saklar dihapus Q44, 9.10); `tautan` — memvalidasi skema http/https; `teks` — menyimpan keterangan langsung. Route unduh file ber-permission (streamed, bukan URL publik), permission `berkas:upload`/`berkas:read`.
- **Dependency:** 13.2, 13.3, 9.7.
- **DoD:** Test Pest/feature: unggah file dengan format di luar `format_diizinkan` ditolak; unggah file melebihi `ukuran_maks_kb` (atau default pengaturan bila kosong) ditolak; unggah valid tersimpan di disk dan baris `berkas` (mode file) tercatat, diuji untuk keenam induk; pengiriman tautan dengan skema selain http/https ditolak; pengiriman tautan valid tersimpan sebagai baris `berkas` mode tautan tanpa menyentuh storage, diuji untuk keenam induk; pengiriman teks kosong (untuk mode teks) ditolak validasi, pengiriman teks terisi tersimpan sebagai baris `berkas` mode teks; percobaan mengakses path file secara langsung (tanpa lewat route permission) tidak menghasilkan akses publik (disk bukan `public`); unduh lewat route ber-permission oleh user tanpa `berkas:read` mendapat 403 (kecuali lampiran regulasi yang mengikuti `regulasi:read`, Workflow §3.1); saat `pengaturan.berkas.unggahan_aktif = false`, percobaan mengirim mode `file` ditolak dengan pesan spesifik sebelum validasi format/ukuran dijalankan, SEMENTARA pengiriman mode `tautan`/`teks` pada persyaratan/induk yang sama (bila diizinkan) tetap berhasil, diuji baik pada induk bergerbang maupun induk dokumen dasar (skenario saklar ini dihapus Q44; 9.10 menguji default konfigurasi dan batas atas 100 MB).

### 13.6 Imutabilitas bukti dukung per induk (enam induk, aturan berbeda-beda)

> **Q40/Q48/Q50 (13 Oktober 2026):** lampiran revisi boleh ditambahkan pada Renstra `aktif` tanpa membuka penghapusan lampiran lama (2.21); PK perubahan karena anggaran ditambahkan sebagai lampiran tambahan PK (Q50 §50.7, 2.20); berkas persyaratan tahap perencanaan kegiatan pada induk `kegiatan` tidak dapat dihapus sejak Rencana Aksi yang mengklaim kegiatan itu diajukan (Q42 §42.4 butir 5, 12.12); arsip dokumen resmi pada induk `jadwal_tahunan`/`jadwal_periode` tidak pernah dihapus dan dikoreksi dengan berkas pengganti, termasuk setelah jadwal ditutup (Q48 §48.2 butir 3–5, 8.5).

- **Scope:** Guard yang menolak hapus/ganti isi bukti dukung (mode apa pun) sesuai batas imutabilitas PER INDUK: `rencana_aksi`/`pengukuran` — sejak induk berstatus `diajukan` (penambahan, penggantian, dan penghapusan hanya saat `draft`/`dikembalikan`; koreksi lewat pengembalian beralasan; imutabel setelah `disahkan`, Q36); `kegiatan` — setelah induk berstatus `terlaksana`; `renstra` — setelah Renstra berstatus `aktif`; `renstra_pk` — setelah Jadwal Tahunan tahun tsb berstatus `aktif`; `regulasi` — selama regulasi masih dirujuk `renstra.regulasi_id` atau `indikator.regulasi_id` aktif (bukan batas waktu, melainkan status rujukan — lihat 2.17). Sebelum batas masing-masing, PIC/Perencanaan (untuk tiga induk pertama) atau Perencanaan/Superadmin (untuk tiga induk dokumen dasar) dapat menghapus — keduanya sebagai soft delete (`dihapus_pada`, `dihapus_oleh` terisi), tercatat audit_log. Setelah `jadwal_tahunan.penutupan`, koreksi lampiran rencana_aksi/pengukuran/kegiatan hanya lewat `jadwal:buka_kembali`; untuk renstra/renstra_pk/regulasi tidak ada jalur buka_kembali serupa — revisi memerlukan revisi in place pada induknya (2.13/2.12/2.16).
- **Dependency:** 13.5, 11.3, 5.9 (status disahkan pengukuran), 12.11 (status terlaksana kegiatan), 2.3 (status aktif Renstra), 3.5 (status aktif Jadwal, untuk renstra_pk), 2.17 (rujukan aktif regulasi).
- **DoD:** Test Pest per induk (enam skenario terpisah): penambahan dan penghapusan lampiran `rencana_aksi`/`pengukuran` pada induk berstatus `diajukan`/`diverifikasi`/`disahkan` DITOLAK sebagai validasi bisnis (422, Q36; khusus pengukuran, tidak ada endpoint hapus, koreksi lewat pengembalian beralasan dan versi baru — Q36 §36.2); penghapusan lampiran `kegiatan` pada induk berstatus `terlaksana` DITOLAK; penghapusan lampiran `renstra` pada Renstra berstatus `aktif` DITOLAK; penghapusan lampiran `renstra_pk` pada Jadwal Tahunan tahun tsb berstatus `aktif` DITOLAK; penghapusan lampiran `regulasi` yang masih dirujuk Renstra/indikator aktif DITOLAK; untuk kelima kasus, penghapusan sebelum batas berhasil (soft delete) bagi pemegang wewenang yang sesuai dan ditolak bagi pihak lain (mis. PIC unit lain untuk tiga induk pertama); setiap penghapusan berhasil tercatat audit_log; kegiatan berstatus `tidak_terlaksana`/`ditunda`/`batal` (tanpa bukti dukung pelaksanaan) tidak pernah masuk kondisi imutabel ini karena tidak pernah mencapai status `terlaksana`.

### 13.7 Penanda `tidak_dapat_dipenuhi` (aturan anti-macet, termasuk gerbang keempat aktivasi jadwal) — dibatalkan Q44

> **Q44 (13 Oktober 2026):** saklar unggahan dihapus sehingga penanda `tidak_dapat_dipenuhi` akibat unggahan dimatikan tidak lagi dibuat; US/ISS-11.06 dibatalkan dan Q23 digantikan. Cabang anti-macet pada gerbang dihapus melalui 9.10 (ISS-13.06), sedangkan penanda yang sudah tercatat tetap tampil sebagai histori. Scope dan DoD di bawah dipertahankan sebagai riwayat.

- **Scope:** Logic yang secara otomatis menandai persyaratan/gerbang sebagai `tidak_dapat_dipenuhi` ketika unggahan file dimatikan dan tidak ada mode alternatif tersedia, pada DUA konteks: (a) persyaratan `jenis_berkas` untuk induk tertentu — ketika `wajib = true`, hanya `izinkan_file = true` (izinkan_tautan dan izinkan_teks sama-sama `false`), DAN `pengaturan.berkas.unggahan_aktif = false` pada saat pemeriksaan gerbang (13.4) dijalankan; (b) BARU — gerbang keempat aktivasi Jadwal Tahunan (3.5) pada `renstra_pk` tahun tsb — ketika belum ada satu pun lampiran `berkasable_type = renstra_pk` bermode `tautan`/`teks` DAN `pengaturan.berkas.unggahan_aktif = false`. Penanda (a) dibaca oleh service gerbang (13.4) sebagai kondisi "dianggap terpenuhi"; penanda (b) dibaca oleh aksi `jadwal:aktivasi` (3.5) sebagai kondisi gerbang 4 "dianggap terpenuhi". Keduanya dicatat di `audit_log` (lihat 10.6) dan ditampilkan pada rekapitulasi (8.3) serta halaman kerja Perencanaan.
- **Dependency:** 13.4, 9.7, 10.6, 2.20 (lampiran renstra_pk).
- **DoD:** Test Pest (a): dengan `berkas.unggahan_aktif = false` dan sebuah `jenis_berkas` wajib mode file-only tanpa satu pun baris `berkas` terkirim, evaluasi gerbang (13.4) untuk induk terkait mengembalikan TERPENUHI (bukan DITOLAK) karena persyaratan ditandai `tidak_dapat_dipenuhi`; Test Pest (b) BARU: dengan `berkas.unggahan_aktif = false` dan `renstra_pk` tahun tsb belum memiliki satu pun lampiran mode `tautan`/`teks`, aksi `jadwal:aktivasi` (3.5) tetap LOLOS gerbang 4 karena ditandai `tidak_dapat_dipenuhi`; halaman kerja Perencanaan menampilkan daftar induk/gerbang dengan penanda ini, mencakup baik persyaratan `jenis_berkas` maupun gerbang aktivasi jadwal; mengaktifkan kembali `berkas.unggahan_aktif = true` membuat evaluasi gerbang berikutnya (baik 13.4 maupun 3.5 gerbang 4) kembali memeriksa kelengkapan riil (tidak lagi otomatis terpenuhi) untuk induk yang belum benar-benar mengunggah/melampirkan bukti.

### 13.8 Lampiran bebas di level kegiatan (dan induk lain) + lampiran murni pada tiga induk dokumen dasar
- **Scope:** Memastikan bukti dukung dengan `jenis_berkas_id = null` (lampiran bebas, bukan persyaratan bergerbang) berfungsi pada TIGA induk bergerbang (`rencana_aksi`/`pengukuran`/`kegiatan`) sebagai pelengkap — tidak pernah menghalangi/menggerbangi transisi status induknya, dan tampil pada rekapitulasi (8.3); DAN memastikan bahwa pada TIGA induk dokumen dasar (`renstra`/`renstra_pk`/`regulasi`), lampiran SELALU tanpa `jenis_berkas_id` (tidak ada persyaratan bernama untuk ketiganya) — `renstra`/`regulasi` murni pelengkap tanpa gerbang apa pun, sementara `renstra_pk` memiliki gerbang keempat aktivasi jadwal (3.5/13.7) yang berbeda mekanismenya dari gerbang `jenis_berkas` (tidak memakai kolom `wajib`/`semua_mode_wajib`, cukup "ada minimal satu baris `berkas`").
- **Dependency:** 13.5, 12.2, 8.3, 2.17, 2.18, 2.20.
- **DoD:** Test Pest: pengiriman bukti dukung (mode apa pun) untuk induk kegiatan tanpa `jenis_berkas_id` berhasil; transisi status kegiatan (12.3, 12.11) tidak pernah tergantung/terblokir oleh ada-tidaknya lampiran bebas; rekapitulasi 8.3 menampilkan daftar lampiran bebas yang melekat pada kegiatan yang diklaim pada baris indikator × periode terkait; percobaan mengirim `jenis_berkas_id` terisi (bukan null) untuk `berkasable_type = renstra`/`renstra_pk`/`regulasi` DITOLAK validasi aplikasi (ketiga induk ini tidak memiliki persyaratan bernama); lampiran `renstra`/`regulasi` tidak pernah menggerbangi transisi status apa pun (test eksplisit: Renstra tanpa lampiran tetap dapat diaktifkan selama `dasar_hukum` terisi); lampiran `renstra_pk` HANYA menggerbangi aktivasi Jadwal Tahunan (3.5), tidak menggerbangi transisi lain.

### 13.9 Audit mode & sumber bukti dukung
- **Scope:** Memastikan setiap pengiriman bukti dukung mencatat `mode` dan sumbernya pada `audit_log`: untuk `file` — nama asli, ukuran, mime; untuk `tautan` — nilai tautan tersimpan utuh pada baris `berkas`, dicatat sebagai perubahan biasa (bukan disensor); untuk `teks` — panjang teks (jumlah karakter), BUKAN salinan isi teksnya. Perubahan/penetapan persyaratan `jenis_berkas` (termasuk mode yang diizinkan, `wajib`, `semua_mode_wajib`) dicatat dengan `nilai_lama`/`nilai_baru` (lihat 13.1).
- **Dependency:** 13.5, 10.1.
- **DoD:** Test Pest: mengirim bukti dukung mode `file` menghasilkan baris `audit_log` dengan detail nama_asli/ukuran/mime; mengirim mode `tautan` menghasilkan baris audit dengan nilai tautan tersimpan; mengirim mode `teks` menghasilkan baris audit yang mencatat panjang teks TANPA menyalin isi_teks ke kolom audit (dibuktikan lewat assertion bahwa `audit_log` untuk peristiwa ini tidak memuat isi teks aslinya secara utuh, hanya metadata panjang).

---

## Seed Data Pengembangan/Testing

### S.1 Seeder Laravel — data minimal validasi model

> **Q41/Q42/Q44/Q47/Q50 (13 Oktober 2026):** seeder pengembangan memakai kode sasaran dan indikator S1–S4 dan IKU 1.1–IKU 4.1 (Q50 §50.4), termasuk S4/IKU 4.1 tanpa target Renstra (Q50 §50.5) dan target PK bernilai penuh (Q50 §50.2); minimal satu indikator dinilai tahunan dengan target periode sebelum periode nilai akhir kosong (Q47); kegiatan beranggaran beserta RAB dan persyaratan tahap `kegiatan_perencanaan` (Q42, 12.12); tanpa jendela `rencana_aksi_mulai/selesai` (Q41) dan tanpa kunci grup `berkas` (Q44, 9.10). Asumsi kerja data awal 2026 pada Keputusan Penyelarasan hanya boleh dipakai untuk data uji ini dan tidak dipakai di produksi sebelum dikonfirmasi Perencanaan; data awal produksi 2026 disiapkan melalui ISS-15.01.

- **Scope:** Seeder (`DatabaseSeeder` + seeder khusus) yang membuat ≥1 regulasi contoh (jenis kepmen, dengan lampiran dokumen mode file), 1 Renstra contoh (dengan dasar_hukum terisi, `regulasi_id` merujuk regulasi contoh, dan lampiran dokumen Renstra), beberapa Sasaran, beberapa Indikator (lintas ≥2 unit, dengan variasi `arah` naik_baik dan turun_baik, variasi `tipe_perhitungan` mencakup ketiganya — manual, rasio_persen dengan `indikator_komponen` lengkap, penjumlahan dengan `indikator_komponen` lengkap, dan minimal satu indikator dengan `regulasi_id` terisi), Target Tahunan (dengan baseline) untuk tahun berjalan, 1 renstra_pk (dengan MINIMAL SATU lampiran dokumen PK — syarat gerbang keempat aktivasi jadwal), 1 Jadwal Tahunan (hingga status aktif lewat KEEMPAT gerbang, dengan `jadwal_periode` dan jendela `rencana_aksi_mulai/selesai` tersusun (tidak lagi dipakai, Q41), snapshot + snapshot komponen terbentuk), ≥1 Rencana Aksi berstatus `disahkan` dengan `rencana_aksi_target` terisi lengkap, ≥2 Kegiatan (salah satu berstatus `tidak_terlaksana` dengan justifikasi, salah satu berstatus `terlaksana` dengan bukti dukung tahap kegiatan lengkap, salah satu hasil geser periode dengan `kegiatan_asal_id` terisi), ≥1 Klaim Kegiatan, ≥1 `jenis_berkas` per tahap (rencana_aksi/pengukuran/kegiatan) dengan minimal satu `wajib=true` per tahap dan variasi mode (minimal satu bermode file-only, satu bermode kombinasi file+tautan dengan `semua_mode_wajib=true`), ≥1 baris `berkas` per mode (file/tautan/teks) TERSEBAR pada KEENAM induk `berkasable_type` (rencana_aksi, pengukuran, kegiatan, renstra, renstra_pk, regulasi), beberapa user dengan **keenam** peran (superadmin, admin, perencanaan, pic, pimpinan, pegawai). User role PIC dibuat untuk pengujian identitas/Assign Peran; permission kerjanya menggunakan konfigurasi aktual yang tersedia (grant eksplisit selama preset PIC masih OPEN, lalu preset final setelah 1.23). **Ini murni untuk pengembangan/testing, bukan data produksi** — data riil dimasukkan manual oleh Perencanaan setelah aplikasi live.
- **Dependency:** Seluruh migrasi Modul 1–6, Modul 9, Modul 11, Modul 12, Modul 13 selesai (2.1–2.20, 3.1–3.10, 4.1, 5.1–5.17, 6.6, 9.1–9.9, 11.1–11.2, 12.1, 12.6, 13.1–13.9).
- **DoD:** `php artisan migrate:fresh --seed` berjalan tanpa error dan menghasilkan: ≥1 regulasi dengan lampiran dokumen, ≥1 Renstra berstatus aktif dengan `regulasi_id` terisi dan lampiran dokumen Renstra, ≥1 indikator dengan `regulasi_id` terisi, ≥1 jadwal_tahunan berstatus aktif (lolos KEEMPAT gerbang termasuk lampiran PK) dengan ≥1 baris `jadwal_periode`, jendela rencana aksi terisi, dan `jadwal_snapshot`+`jadwal_snapshot_komponen` terbentuk sejumlah indikator/komponen seed, ≥1 Rencana Aksi berstatus `disahkan`, ≥2 Kegiatan dengan variasi status termasuk geser periode dan minimal satu `terlaksana` dengan bukti dukung terpenuhi, ≥1 Klaim Kegiatan, ≥1 `jenis_berkas` wajib per tahap (termasuk `kegiatan`) dengan variasi mode, ≥1 baris `berkas` per mode (file/tautan/teks) yang dapat diverifikasi lewat query TERSEBAR pada keenam induk `berkasable_type` (dibuktikan query `GROUP BY berkasable_type` mengembalikan ≥1 baris untuk masing-masing dari keenam nilai), ≥1 user per **keenam** peran (superadmin, admin, perencanaan, pic, pimpinan, pegawai) yang dapat dipakai untuk login uji manual, dan seluruh kunci `pengaturan` default (9.2, 9.7) sudah terisi.


---

## Q31 — Checklist Penyelarasan Role PIC Sebelum UAT/Produksi

Checklist ini memisahkan hal yang dapat dikerjakan sekarang dari keputusan yang masih OPEN.

### Q31.1 Dapat dikerjakan sekarang

- `roles` mendukung enam kode termasuk `pic`.
- `user_roles` tetap satu role per user.
- Assign Peran menampilkan PIC.
- Resolver tetap generic dan data-driven.
- Grant/deny tetap bekerja untuk user role PIC.
- Halaman "Jelaskan Izin Pengguna" membaca izin efektif aktual.
- Seeder development membuat minimal satu user PIC.
- Workflow PIC tetap tunduk scope/unit, jendela, status, dan business guard existing.
- Tidak ada authorization berbasis hardcode nama role di React.

### Q31.2 Decision-gated sebelum UAT final/produksi

- **revisi/sign-off Dokumen Konfirmasi Permission & Hak Akses untuk memasukkan role PIC** — task 1.23;
- preset permission bawaan role PIC — task 1.23;
- eligibility role menjadi `penanggung_jawab` — task 4.5;
- mapping user existing Pegawai → PIC;
- perlakuan assignment aktif ketika role user berubah;
- apakah 5 permission akses umum pada matrix lama juga otomatis menjadi preset PIC;
- penyelesaian discrepancy jumlah sensitive action: narasi 21 vs tabel detail 22 (`unit:delete`);
- apakah menu/dashboard PIC memerlukan permission default tertentu.

### Q31.3 Definition of Ready untuk menutup Q31

Q31 dianggap siap untuk UAT final bila:

1. ada revisi/sign-off tertulis matrix permission yang memasukkan role PIC;
2. ada keputusan eligibility `penanggung_jawab`;
3. PRD, Data Model, Workflow, Plan, User Stories, dan User Issues menggunakan keputusan yang sama;
4. seeder + automated test telah diperbarui;
5. tidak ada teks produksi yang masih menyamakan role PIC dengan Pegawai;
6. tidak ada permission scoped-unit yang berubah menjadi global tanpa keputusan eksplisit;
7. mapping akun UAT yang ber-role PIC telah ditentukan.


## Permission Baseline Gate — Wajib Sebelum Authorization Dianggap Final

Dokumen Konfirmasi Permission & Hak Akses v1.0 berstatus **Draf untuk Ditinjau & Dikonfirmasi**. Plan ini sudah menyelaraskan implementasi teknis terhadap seluruh baris detailnya, tetapi status produksi authorization tetap mengikuti sign-off PM.

### Yang sudah dapat diimplementasikan dari dokumen permission

- 70 kode permission persis (angka 70 adalah baseline dokumen permission v1.0; katalog kode saat ini berisi 71 kode, dan Q48 menambah `laporan:unggah` sehingga katalog menjadi 72, dengan preset Perencanaan dan Superadmin);
- 9 permission scope unit;
- flag sensitif per baris;
- `unit:delete` hanya Superadmin;
- pemisahan Admin teknis dari wewenang substantif;
- grant unit untuk operasional;
- hak Pimpinan pada pelaporan dan staging `pengukuran:setujui`, ditambah `rekomendasi:tetapkan` pada preset Pimpinan (Q45/Q51, 1.12);
- entitlement lima role lama yang tertulis eksplisit.

### Yang belum boleh dianggap final

- preset PIC operasional (bukan role) sebagai role keenam;
- eligibility `penanggung_jawab` terhadap role PIC;
- mapping akun existing;
- penyelesaian hitungan sensitif 21 vs 22 secara administratif/sign-off.

### Rule Implementasi

Apabila ringkasan/narasi dan baris tabel permission berbeda, **jangan menyembunyikan konflik**. Test/fixture harus merekam nilai per-baris yang benar-benar ditulis pada tabel sambil membuka blocker sign-off untuk perbedaan naratif. Perubahan setelah sign-off wajib disinkronkan ke seeder, test, PRD, Data Model, Workflow, User Stories, User Issues, dan Design System.


## Pra-syarat Lingkungan & Aturan Penempatan Logika

### P.1 Kebutuhan lingkungan VPS

Sebelum rilis pertama dapat berjalan, VPS penempatan SAKIP wajib menyediakan:

- **PHP** versi sesuai kebutuhan Laravel 13 (rilis PHP terbaru yang didukung Laravel 13 pada saat instalasi), beserta ekstensi standar yang dibutuhkan Laravel (mis. `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`).
- **PostgreSQL** sebagai basis data utama, dengan akses jaringan/kredensial khusus aplikasi SAKIP (bukan akun bersama dengan aplikasi lain).
- **Web server** (Nginx/Apache) yang mengarahkan permintaan ke `public/index.php` sesuai konvensi Laravel, dengan konfigurasi HTTPS untuk lalu lintas produksi.
- **Bun** terpasang sebagai runtime/toolchain build asset frontend — dipakai untuk `bun install` dan `bun run build` (Vite dijalankan lewat Bun). **Node.js/npm tidak dipasang sebagai jalur build** pada lingkungan ini.
- **Client Keycloak khusus SAKIP** (client_id terpisah dari aplikasi lain di lingkungan LLDIKTI Wilayah XVI), dikonfigurasi untuk OIDC Authorization Code Flow berbasis session.
- **Penyimpanan berkas** pada path `storage/app/berkas/...` di disk **privat** (bukan disk `public`) — akses file hanya lewat route ber-permission (streamed download), tidak pernah lewat URL langsung. Kapasitas disk untuk bukti dukung mode `file` dipantau memakai panel "Penggunaan Penyimpanan Bukti Dukung" (9.8); mode `tautan`/`teks` tidak memakai storage aplikasi sama sekali, sehingga kebijakan grup `berkas` (9.7) dapat dipakai sebagai katup pengaman bila kapasitas disk mendekati batas. **Q44 (13 Oktober 2026):** panel penggunaan penyimpanan dan kebijakan grup `berkas` dihapus (9.10); batas teknis unggahan dibaca dari konfigurasi aplikasi dan perubahannya dilakukan lewat konfigurasi deployment yang dicatat pada catatan rilis (Q44 §44.2 butir 3); pemantauan kapasitas disk mengikuti kesiapan infrastruktur produksi (Q30).

### P.2 Langkah rilis (deploy/migrasi aset)

Setiap rilis (deploy) WAJIB menjalankan urutan berikut pada build asset frontend, sebagai pengganti langkah npm konvensional:

1. `bun install` — memasang dependency frontend sesuai `bun.lock`/`bun.lockb` yang di-commit ke repositori (bukan `package-lock.json`).
2. `bun run build` — mengompilasi asset React/TypeScript lewat Vite, menghasilkan berkas produksi di `public/build`.
3. `php artisan migrate --force` (lingkungan produksi) dan seeder yang relevan (idempoten, lihat Modul 1/9/S.1) dijalankan setelah asset build sukses.

Skrip CI/CD atau instruksi deploy manual **tidak boleh** memanggil `npm install`/`npm run build` sebagai jalur utama; bila ada tooling pihak ketiga yang secara internal mensyaratkan Node.js runtime (bukan sebagai package manager pilihan proyek), itu dicatat sebagai pengecualian eksplisit dengan alasannya, bukan default diam-diam.

### P.3 Aturan penempatan logika (mengikat seluruh modul)

Aturan berikut berlaku di seluruh Plan ini dan menjadi rujukan wajib bagi siapa pun yang mendelegasikan atau mengerjakan task:

- **Otorisasi** (resolusi izin peran/grant/deny, §19 Workflow, task 1.11/1.17) **HANYA** dievaluasi di server — Gate/Policy/service layer Laravel. Komponen React tidak pernah menghitung ulang hasil resolusi izin; ia hanya menerima flag `can.*` dari props Inertia untuk menyembunyikan elemen UI.
- **Validasi bisnis** (jendela waktu jadwal/periode, kunci periode Rencana Aksi yang menggantikan jendela rencana aksi sejak Q41, status alur, kepemilikan unit, gerbang kelengkapan rencana aksi/komponen/bukti dukung tiga tahap) **HANYA** dijalankan di server — Form Request atau service layer, terpisah dari lapisan otorisasi (lihat §19 Workflow butir 7). Server selalu mengevaluasi ulang validasi ini pada SETIAP permintaan, termasuk permintaan yang membypass tombol UI.
- **Perhitungan nilai indikator** (mesin perhitungan komponen, 5.13/5.17) **HANYA** dihitung di server; nilai turunan yang dikirim ke klien murni untuk ditampilkan, tidak pernah diterima balik sebagai input yang dipercaya (dibuktikan test 5.14 yang menolak payload custom pada `pengukuran.nilai`).
- **Penulisan audit** (`AuditLogger::catat(...)`, Modul 10) **HANYA** dipanggil dari kode server pada titik transisi/mutasi data yang relevan — tidak pernah dari sisi klien, dan tidak ada endpoint yang menerima payload audit siap-pakai dari luar.
- **Konsekuensi untuk delegasi coding:** setiap brief task yang didelegasikan (mis. ke agen coding eksternal) WAJIB menyebutkan secara eksplisit di mana logika diletakkan — "server: Form Request X" atau "server: Policy Y" untuk otorisasi/validasi bisnis, dan "klien: hanya menampilkan hasil dari props Z" untuk UI — sehingga tidak ada ambiguitas yang berujung logika bisnis/izin bocor ke komponen React.


### P.5 Gate Q32 sebelum UAT final

- **Scope:** verifikasi bahwa seluruh corrective alignment Q32 sudah selesai: 5 role; Grant Unit 7 scoped + `delegasi:update`; role-permission read-only; onboarding tanpa role; logout terpisah; PJ tanpa role PIC; periode lampau 2026; formula IKU final.
- **Dependency:** task 1.3, 1.10–1.14, 1.22–1.24, 3.11, 4.5, fixture indikator.
- **DoD:** tidak ada test/seed/UI yang masih menganggap `pic` role; seluruh issue corrective lulus; daftar akun UAT 5 role + user pending tersedia; dokumen konsisten.

---

## Ringkasan Urutan Eksekusi Modul

| Urutan | Modul | Alasan urutan |
|---|---|---|
| 1 | Autentikasi & Akses | Fondasi wajib — seluruh modul lain butuh gate akses & audit dasar; including lima role resmi; corrective Q32 selesai lewat task 1.23/1.24, serta katalog permission final (rencana aksi, kegiatan, komponen, bukti dukung) sejak awal; setup awal memakai Bun sebagai toolchain frontend |
| 2 | Master Renstra | Data dasar yang dirujuk seluruh modul berikutnya; indikator kini membawa `unit_id`, `arah`, `tipe_perhitungan`, dan definisi `indikator_komponen` sejak migrasi awal; menyertakan entitas `regulasi` (dokumen dasar) dan kolom `regulasi_id` pada renstra/indikator sejak migrasi awal; **Q39/Q40/Q50:** target Renstra, isian target PK di halaman PK, dan revisi PK berversi (2.21–2.23) |
| 3 | Periode & Jadwal | Butuh Renstra+PK (dan lampiran dokumen PK — gerbang keempat, Modul 2/13); menghasilkan `jadwal_periode`, jendela rencana aksi tingkat tahun (dihapus Q41; diganti kunci per periode, 11.9), dan snapshot idempoten (termasuk snapshot komponen) yang dipakai Rencana Aksi & Pengukuran |
| 4 | Penugasan | Butuh Indikator (dari Modul 2); resolusi PIC efektif dipakai ulang oleh Rencana Aksi (Modul 11). Task 4.5 menerapkan rule final Q32: user aktif mana pun dapat menjadi PJ; warning bila tanpa grant |
| 5 | Pengukuran | Butuh Jadwal aktif (snapshot) + Penugasan; deadline jendela periode, mesin perhitungan komponen (diuji dengan fixture 8 indikator nyata 2026), dan gerbang kelengkapan tiga lapis (rencana aksi/komponen/bukti dukung) berlaku di sini; **Q45/Q47/Q51:** narasi wajib, tindak lanjut atas rekomendasi disetujui, dan realisasi belum dapat diukur (5.5, 5.16) |
| 6 | Reviu & Pengesahan | Butuh Pengukuran berjalan; mencakup antrean buka-kembali; **Q45/Q51:** Rekomendasi Pimpinan dengan alur draf dan persetujuan (6.7, ISS-09.01) |
| 7 | Dashboard | Butuh Pengukuran + Status Capaian + Rencana Aksi + Kegiatan; status tampilan kini per indikator × periode, ditambah panel progres rencana aksi dan kegiatan; **Q42/Q45/Q47:** total anggaran per IKU, status kegiatan real-time, "Tanpa target periode", dan "Belum dapat diukur" (catatan Modul 7) |
| 8 | Laporan & Ekspor | Butuh Pengukuran + pola query Dashboard + Rencana Aksi + Kegiatan + Klaim + Rekomendasi Pimpinan + penanda bukti dukung `tidak_dapat_dipenuhi` untuk rekapitulasi indikator × periode; **Q48:** keluaran Rencana Aksi tahunan dan Laporan Kinerja Triwulan serta arsip PDF bertanda tangan (8.5); penanda `tidak_dapat_dipenuhi` hanya histori (Q44) |
| 9 | Setelan Aplikasi | Modul independen secara data (hanya butuh `users` untuk FK); ditempatkan setelah modul inti karena bukan jalur kritis alur kinerja, ditambah kunci grup `berkas`, panel penggunaan penyimpanan, dan halaman "Batas unggahan berkas" (per persyaratan, terpisah dari grup `berkas`) yang dirujuk Modul 13; ketiganya dihapus Q44 melalui 9.10, dengan batas teknis unggahan di konfigurasi aplikasi |
| 10 | Audit & Histori | Infrastruktur dasar (10.1) dibangun paralel sejak Modul 1; UI pencarian (10.3–10.5) dan audit penanda `tidak_dapat_dipenuhi` (10.6) dituntaskan setelah modul inti berjalan, termasuk Modul 9, 11, 12, 13; 10.6 digantikan Q44, dan 10.7 menyederhanakan kewajiban alasan (Q43) |
| 11 | Rencana Aksi | Butuh Indikator+Komponen (Modul 2), Jadwal+jendela rencana aksi (Modul 3), Penugasan (Modul 4); menjadi gerbang wajib sebelum Pengukuran (Modul 5) dapat diajukan; gerbang pengajuannya kini juga menyertakan bukti dukung tahap `rencana_aksi` (Modul 13); **Q41/Q42/Q47:** jendela rencana aksi diganti kunci per periode dan periode tanpa target (11.9), serta pengajuan mensyaratkan minimal satu kegiatan beserta persyaratan tahap `kegiatan_perencanaan`/RAB (12.6, 12.12) |
| 12 | Kegiatan & Klaim Kegiatan | Butuh Unit, Periode; Kegiatan berdiri independen dari Rencana Aksi, diikat lewat Klaim yang butuh Rencana Aksi (Modul 11) sudah ada; transisi ke `terlaksana` kini digerbangi bukti dukung tahap `kegiatan` (Modul 13); **Q42/Q49:** kegiatan ditambahkan dari halaman Rencana Aksi per periode beserta klaimnya, dapat disalin ke periode lain, dan wajib beranggaran dengan RAB bila anggaran lebih dari 0 (12.6, 12.12) |
| 13 | Bukti Dukung: Persyaratan, Tiga Mode & Enam Induk Lampiran | Butuh Indikator (untuk `jenis_berkas.indikator_id`) dan entitas dokumen dasar (Modul 2: `regulasi`, `renstra`, `renstra_pk`); dirujuk sebagai gerbang oleh Rencana Aksi (Modul 11), Pengukuran (Modul 5), Kegiatan (Modul 12), DAN gerbang keempat aktivasi Jadwal (Modul 3, atas `renstra_pk`) — mencakup tiga mode (file/tautan/teks) pada ENAM induk `berkasable_type`, `semua_mode_wajib`, dan penanda `tidak_dapat_dipenuhi` yang bergantung pada kunci setelan grup `berkas` (Modul 9); **Q42/Q44/Q48:** tahap `kegiatan_perencanaan`, contoh berkas, induk `jadwal_tahunan`/`jadwal_periode`, dan batas teknis dari konfigurasi, sedangkan penanda `tidak_dapat_dipenuhi` dihapus (9.10) |
| — | Seed Data | Setelah seluruh migrasi Modul 1–6, 9, 11, 12, 13 tersedia, sebelum pengujian end-to-end menyeluruh |

### Tahap pengerjaan Q46 §46.3 (diperbarui Q51)

Urutan modul di atas tetap menjadi urutan dependensi teknis. Prioritas pengerjaan sejak 13 Oktober 2026 mengikuti tiga tahap berikut (Q46 §46.3; ISS-09.01 dipindah ke tahap 2 oleh Q51 §51.3).

| Tahap | Batas | Issue dan task Plan |
|---|---|---|
| 1 | Sebelum integrasi 9 November 2026 (Q2) | ISS-01.08 (1.3); ISS-02.10 (2.21); ISS-02.11 (2.22); ISS-05.06 (11.7); ISS-05.07 (11.9); ISS-06.01 (12.1–12.2); ISS-06.02 (12.6–12.9); ISS-06.08 (12.12); ISS-13.05 (10.7); ISS-13.06 (9.10); pengecualian periode lampau (3.11); di akhir tahap, penyiapan data awal 2026 (ISS-15.01) |
| 2 | Sebelum pelaporan TW IV 2026 (awal Januari 2027) | ISS-06.03 (12.11); ISS-06.04 (12.3); ISS-06.05 (12.4); ISS-07.01 (5.2–5.4, 5.13–5.15); ISS-07.03 (5.5, 5.16); ISS-07.06 (5.13–5.16); ISS-08.01 (5.6–5.8, 6.1); ISS-08.02 (5.9, 6.2); ISS-09.01 (6.7); ISS-10.01 (7.1–7.8); ISS-10.02 (8.1, 8.3); ISS-10.05 (8.5); ISS-14.01 (3.9) |
| 3 | Sesudah tahap 2; revisi PK siap sebelum revisi PK pertama yang mengubah target | ISS-03.03 (3.8) dan ISS-02.12 (2.23); simulasi target; tampilan daftar berkas terunggah; versi penuh Renstra; ISS-13.03 (10.1–10.5; 10.6 digantikan Q44) |

Issue yang tidak disebut pada tabel mengikuti rencana sprint yang berlaku.

Status per 17 Oktober 2026 (`development` `f9fd9a7c`): selesai — ISS-01.08 (#101, #108, #114), ISS-05.06 (#100), ISS-06.01 (#97, termasuk Q49 §49.1), ISS-07.01 (#106), ISS-07.03 (#105), ISS-07.06 (#118), ISS-08.02 (#107), ISS-09.01 (#115), ISS-13.03 (#119), ISS-14.01 (#109, #117); dalam review — ISS-06.02 (PR #111); task Tahap 1 lainnya belum dimulai.

---


## Catatan Implementasi Q31

Untuk seluruh task yang menyebut **PIC**, gunakan pembedaan berikut sampai keputusan 1.23/4.5 selesai:

```text
role PIC
= nilai role resmi pada user_roles

PIC operasional / Penanggung Jawab
= aktor yang lolos permission efektif + scope unit
  + assignment penanggung_jawab + business guard
```

Keduanya tidak boleh disamakan secara implisit oleh frontend maupun backend sebelum aturan eligibility dikonfirmasi.

**Tidak boleh:**

- hardcode `if role === 'pic'` sebagai authorization;
- memberi semua permission scoped ke role PIC secara global;
- menghapus grant unit;
- menghapus histori Penanggung Jawab;
- auto-migrasi Pegawai → PIC berdasarkan perkiraan;
- menganggap user PIC otomatis boleh bekerja pada semua indikator.

**Boleh dikerjakan sebelum keputusan final:**

- membuat role PIC;
- assign user ke PIC;
- memberi grant eksplisit yang valid untuk development/testing;
- menguji resolver/deny/scope;
- menguji workflow dengan effective permission dan assignment;
- menampilkan hasil `can.*` dari server.

---

## 14. Fase Lanjutan (Belum Termasuk MVP)

Daftar cakupan yang secara sengaja tidak dibangun pada Fase Awal, dicantumkan sebagai catatan cakupan masa depan tanpa breakdown granular (akan dituangkan dalam dokumen pengembangan lanjutan terpisah):

- **Approval Pimpinan** dalam alur pengesahan pengukuran — mengaktifkan pemakaian kolom `jadwal_tahunan.pakai_persetujuan_pimpinan`, `persetujuan_mulai`, `persetujuan_selesai`, serta permission `pengukuran:setujui` dan peran aktif Pimpinan dalam state machine status alur.
- **Ekspor PDF** untuk rekap laporan formal (mis. lampiran LKj). **Q48 (13 Oktober 2026):** keluaran PDF tetap fase lanjutan; PDF bertanda tangan yang dibuat di luar aplikasi diunggah sebagai arsip melalui 8.5.
- **Ekspor gambar grafik** dashboard (mis. PNG/SVG dari ApexCharts) untuk disisipkan ke dokumen/presentasi.
- **Integrasi status capaian otomatis** dari sistem sumber data eksternal — mengaktifkan pemakaian `status_capaian.sumber = data_sumber` dan jalur `ditetapkan_oleh = NULL`, termasuk desain integrasi/job/API yang relevan.
- **UI matrix permission penuh** — antarmuka yang menampilkan dan memungkinkan pencentangan bebas seluruh permission katalog per pengguna, menggantikan kebutuhan seeder/query manual untuk kasus edge di luar preset form Fase Awal.
- **Impor data massal** dari sumber eksternal (Excel/sistem lain) — belum diputuskan masuk fase mana pun; memerlukan keputusan produk tersendiri jika dibutuhkan di masa depan.
- **Revisi target di tengah tahun** — Fase Awal hanya mendukung revisi target antar-tahun (mengubah master `target_tahunan` sebelum jadwal tahun tersebut diaktifkan); mekanisme revisi target pada tahun yang snapshot-nya sudah terbentuk tetap memakai jalur `jadwal:buka_kembali` beserta koreksi manual snapshot yang ter-audit, bukan fitur bertingkat tersendiri. **Digantikan Q40 (13 Oktober 2026):** perubahan target PK resmi di tengah tahun memakai revisi PK berversi yang menerbitkan versi snapshot baru (2.23, tahap 3 Q46 §46.3).
- **Formula perhitungan bertingkat** — mesin perhitungan komponen (Modul 5) mendukung satu tingkat (rasio atau penjumlahan); formula bertingkat (sub-skor → nilai komposit → digabung lagi), seperti kebutuhan penghitungan Predikat SAKIP/Zona Integritas dari sub-skor 30/30/15/25, tetap memakai `tipe_perhitungan = manual` pada Fase Awal dan menjadi kandidat pengembangan lanjutan.
- **Approval Pimpinan atas Rekomendasi Pimpinan** — pada Fase Awal, Rekomendasi Pimpinan diisi oleh Perencanaan (permission `rekomendasi:tetapkan`); pengalihan hak pengisian ke Pimpinan sendiri, berikut alur approval-nya, adalah pengembangan lanjutan yang menunggu integrasi dengan Approval Pimpinan di atas. **Digantikan Q45/Q51 (13 Oktober 2026):** Pimpinan menyetujui draf rekomendasi atau menulis langsung, Perencanaan menyusun draf dan menjadi jalur cadangan bertanda, dan alur ini dikerjakan pada 6.7 (ISS-09.01) tahap 2; approval Pimpinan atas pengesahan pengukuran tetap fase lanjutan.

---

---

# Lampiran A — Matrix 70 Permission (Baseline Dokumen Permission v1.0)

> **Tujuan:** Lampiran ini disalin dari bagian tabel klasifikasi Dokumen Konfirmasi Permission & Hak Akses Sistem SAKIP v1.0 agar developer memiliki fixture yang sama dengan sumber review PM. Isi mapping lima role pada lampiran **belum memasukkan role PIC Q31**; karena itu preset PIC tetap mengikuti task 1.23 dan tidak boleh diinferensikan dari kolom Pegawai.
>
> **Catatan discrepancy sensitif:** teks naratif sumber menyebut 21 tindakan sensitif, sedangkan tabel di bawah menandai 22 baris `Ya`. Plan menggunakan flag per-baris untuk test teknis sambil menunggu sign-off PM terhadap hitungan final.
>
> **Catatan jumlah permission dan Q44/Q45/Q48/Q51 (13 Oktober 2026):** angka 70 adalah baseline dokumen permission v1.0; katalog kode saat ini berisi 71 kode, dan Q48 menambah `laporan:unggah` sehingga katalog menjadi 72 (preset Perencanaan dan Superadmin; baris 71 di bawah). Preset Pimpinan memperoleh `rekomendasi:tetapkan` (Q45 §45.2, Q51 §51.2; baris 67). Halaman Batas Unggahan Berkas dihapus sehingga batas unggahan tidak lagi diubah lewat setelan (Q44; baris 12).

## 3. TABEL KLASIFIKASI: SEMUA ROLE VS ROLE TERTENTU

Tabel berikut memetakan setiap permission beserta klasifikasinya untuk dicek dan disetujui oleh Project Manager.

### A. Izin yang Terbuka untuk SEMUA Role (5 Permission)
Izin-izin ini diberikan secara bawaan (*default*) kepada seluruh pengguna yang telah terautentikasi:

| No | Kode Permission | Entitas | Aksi | Scope | Sensitif | Keterangan untuk PM | Status Konfirmasi PM |
|:---:|:---|:---|:---|:---:|:---:|:---|:---:|
| 1 | `dashboard:read` | `dashboard` | `read` | Global | Tidak | Membuka dasbor ringkasan capaian kinerja instansi | [ ] Disetujui |
| 2 | `regulasi:read` | `regulasi` | `read` | Global | Tidak | Membaca katalog dasar hukum / regulasi acuan IKU | [ ] Disetujui |
| 3 | `komponen:read` | `komponen` | `read` | Global | Tidak | Membaca komponen formula pembentuk indikator | [ ] Disetujui |
| 4 | `jenis_berkas:read`| `jenis_berkas`| `read` | Global | Tidak | Membaca persyaratan dokumen bukti dukung | [ ] Disetujui |
| 5 | `pengukuran:read` | `pengukuran` | `read` | Global | Tidak | Membaca rekapitulasi capaian indikator institusi | [ ] Disetujui |

---

### B. Izin yang HANYA Khusus Role Tertentu (65 Permission; ditambah `laporan:unggah` Q48)

#### 1. Tata Kelola Akun, Akses, Unit & Setelan Teknis (Khusus ADMIN & SUPERADMIN)
*Role Perencanaan, Pimpinan, dan Pegawai DILARANG mengakses area ini.*

| No | Kode Permission | Entitas | Aksi | Role yang Berhak | Sensitif | Alasan Pembatasan untuk PM | Status Konfirmasi PM |
|:---:|:---|:---|:---|:---|:---:|:---|:---:|
| 6 | `pengguna:read` | `pengguna` | `read` | Admin, Superadmin | Tidak | Melihat daftar seluruh akun dan analisis izin efektif | [ ] Disetujui |
| 7 | `akses:update` | `akses` | `update` | Admin, Superadmin | **Ya** | Memberikan peran, grant izin unit, atau mencabut hak akses (Deny) | [ ] Disetujui |
| 8 | `unit:create` | `unit` | `create` | Admin, Superadmin | Tidak | Menambah master struktur unit organisasi | [ ] Disetujui |
| 9 | `unit:read` | `unit` | `read` | Admin, Superadmin | Tidak | Membaca manajemen master data unit | [ ] Disetujui |
| 10 | `unit:update` | `unit` | `update` | Admin, Superadmin | Tidak | Mengubah nama, kode, atau mengaktifkan/menonaktifkan unit | [ ] Disetujui |
| 11 | `unit:delete` | `unit` | `delete` | **Hanya Superadmin** | **Ya** | Hapus unit kosong yang salah buat (unit berdata dilarang hapus) | [ ] Disetujui |
| 12 | `pengaturan:update`| `pengaturan`| `update` | Admin, Superadmin | **Ya** | Mengubah teks label aplikasi, batasan ukuran unggahan file (batasan unggahan dihapus dari setelan oleh Q44; batas teknis di konfigurasi, 9.10) | [ ] Disetujui |
| 13 | `audit:read` | `audit` | `read` | Admin, Superadmin, Perencanaan, Pimpinan | Tidak | Melihat rekam jejak audit forensik perubahan data dan `dasar_izin` | [ ] Disetujui |

---

#### 2. Perencanaan Strategis & Penetapan Target (Khusus PERENCANAAN & SUPERADMIN)
*Role Admin teknis dilarang mengubah substansi target kinerja demi menjaga objektivitas.*

| No | Kode Permission | Entitas | Aksi | Role yang Berhak | Sensitif | Alasan Pembatasan untuk PM | Status Konfirmasi PM |
|:---:|:---|:---|:---|:---|:---:|:---|:---:|
| 14 | `renstra:create` | `renstra` | `create` | Perencanaan, Superadmin | Tidak | Membuat draf periode Renstra 5 tahunan baru | [ ] Disetujui |
| 15 | `renstra:read` | `renstra` | `read` | Perencanaan, Superadmin, Pimpinan | Tidak | Melihat pohon cascading sasaran dan Renstra | [ ] Disetujui |
| 16 | `renstra:update` | `renstra` | `update` | Perencanaan, Superadmin | Tidak | Mengubah rincian, status aktif/nonaktif Renstra | [ ] Disetujui |
| 17 | `renstra:delete` | `renstra` | `delete` | Perencanaan, Superadmin | Tidak | Menghapus draf Renstra yang belum memiliki riwayat | [ ] Disetujui |
| 18 | `sasaran:create` | `sasaran` | `create` | Perencanaan, Superadmin | Tidak | Menambah sasaran strategis di bawah Renstra | [ ] Disetujui |
| 19 | `sasaran:update` | `sasaran` | `update` | Perencanaan, Superadmin | Tidak | Mengubah nama dan urutan sasaran strategis | [ ] Disetujui |
| 20 | `sasaran:delete` | `sasaran` | `delete` | Perencanaan, Superadmin | Tidak | Menghapus sasaran strategis yang belum punya indikator | [ ] Disetujui |
| 21 | `indikator:create` | `indikator` | `create` | Perencanaan, Superadmin | Tidak | Mendaftarkan indikator kinerja (IKU/IKT) baru | [ ] Disetujui |
| 22 | `indikator:read` | `indikator` | `read` | Perencanaan, Superadmin, Pimpinan | Tidak | Membaca master definisi dan formula indikator | [ ] Disetujui |
| 23 | `indikator:update` | `indikator` | `update` | Perencanaan, Superadmin | Tidak | Mengubah definisi, satuan, arah, dan unit pemilik indikator | [ ] Disetujui |
| 24 | `indikator:delete` | `indikator` | `delete` | Perencanaan, Superadmin | Tidak | Menghapus draf indikator sebelum jadwal aktif | [ ] Disetujui |
| 25 | `target:update` | `target` | `update` | Perencanaan, Superadmin | Tidak | Mengoreksi salah input master target tahunan via snapshot pengganti; **Q39:** juga menyimpan target Renstra (2.21) dan target PK (2.22) | [ ] Disetujui |
| 26 | `pk:create` | `pk` | `create` | Perencanaan, Superadmin | Tidak | Membuat dokumen Perjanjian Kinerja tahunan | [ ] Disetujui |
| 27 | `pk:update` | `pk` | `update` | Perencanaan, Superadmin | Tidak | Memperbarui rincian Perjanjian Kinerja tahunan | [ ] Disetujui |
| 28 | `penanggung_jawab:update`| `penanggung_jawab`| `update`| Perencanaan, Superadmin | Tidak | Menetapkan / mengalihkan pegawai sebagai PIC indikator | [ ] Disetujui |

---

#### 3. Master Penunjang: Regulasi, Komponen & Persyaratan Bukti (Khusus PERENCANAAN & SUPERADMIN)
*Admin hanya dapat membaca (`read`), tidak boleh mengutak-atik dasar hukum atau formula.*

| No | Kode Permission | Entitas | Aksi | Role yang Berhak | Sensitif | Alasan Pembatasan untuk PM | Status Konfirmasi PM |
|:---:|:---|:---|:---|:---|:---:|:---|:---:|
| 29 | `regulasi:create` | `regulasi` | `create` | Perencanaan, Superadmin | Tidak | Mendaftarkan payung hukum baru | [ ] Disetujui |
| 30 | `regulasi:update` | `regulasi` | `update` | Perencanaan, Superadmin | **Ya** | Mengubah nomor/tahun/substansi dasar hukum | [ ] Disetujui |
| 31 | `regulasi:delete` | `regulasi` | `delete` | Perencanaan, Superadmin | **Ya** | Menghapus produk hukum dari rujukan | [ ] Disetujui |
| 32 | `komponen:create` | `komponen` | `create` | Perencanaan, Superadmin | Tidak | Menambah komponen variabel rumus indikator | [ ] Disetujui |
| 33 | `komponen:update` | `komponen` | `update` | Perencanaan, Superadmin | **Ya** | Mengubah formula rumus atau bobot komponen | [ ] Disetujui |
| 34 | `komponen:delete` | `komponen` | `delete` | Perencanaan, Superadmin | **Ya** | Menghapus komponen dari rumus indikator | [ ] Disetujui |
| 35 | `jenis_berkas:create`| `jenis_berkas`| `create`| Perencanaan, Superadmin | Tidak | Menetapkan syarat dokumen bukti dukung indikator | [ ] Disetujui |
| 36 | `jenis_berkas:update`| `jenis_berkas`| `update`| Perencanaan, Superadmin | **Ya** | Mengubah aturan kewajiban dokumen bukti dukung | [ ] Disetujui |
| 37 | `jenis_berkas:delete`| `jenis_berkas`| `delete`| Perencanaan, Superadmin | **Ya** | Menghapus persyaratan bukti dukung | [ ] Disetujui |

---

#### 4. Manajemen Jadwal & Siklus Pelaporan (Khusus PERENCANAAN & SUPERADMIN)

| No | Kode Permission | Entitas | Aksi | Role yang Berhak | Sensitif | Alasan Pembatasan untuk PM | Status Konfirmasi PM |
|:---:|:---|:---|:---|:---|:---:|:---|:---:|
| 38 | `periode:create` | `periode` | `create` | Perencanaan, Superadmin | Tidak | Menambah master periode (Triwulan I–IV) | [ ] Disetujui |
| 39 | `periode:update` | `periode` | `update` | Perencanaan, Superadmin | Tidak | Mengubah nama atau urutan periode | [ ] Disetujui |
| 40 | `jadwal:create` | `jadwal` | `create` | Perencanaan, Superadmin | Tidak | Membuat kalender jadwal siklus tahunan SAKIP | [ ] Disetujui |
| 41 | `jadwal:update` | `jadwal` | `update` | Perencanaan, Superadmin | Tidak | Mengatur rentang tanggal buka/tutup pengisian | [ ] Disetujui |
| 42 | `jadwal:aktivasi` | `jadwal` | `aktivasi` | Perencanaan, Superadmin | **Ya** | Mengunci master data dan mencetak `jadwal_snapshot` | [ ] Disetujui |
| 43 | `jadwal:tutup` | `jadwal` | `tutup` | Perencanaan, Superadmin | **Ya** | Menutup siklus tahunan pengisian data kinerja | [ ] Disetujui |
| 44 | `jadwal:buka_kembali`| `jadwal` | `buka_kembali`| Perencanaan, Superadmin| **Ya** | Membuka jadwal tertutup untuk sanggah resmi | [ ] Disetujui |

---

#### 5. Operasional Pengisian Capaian, Rencana Aksi & Kegiatan (BERSYARAT: PEGATURAN PIC / UNIT)
*Perencanaan memegang izin ini secara GLOBAL (bebas unit & batas waktu). Pegawai HANYA berhak jika memiliki GRANT UNIT + PIC AKTIF + JENDELA TERBUKA.*

| No | Kode Permission | Entitas | Aksi | Scope | Role Default | Pegawai (Unit) | Sensitif | Keterangan untuk PM | Status Konfirmasi PM |
|:---:|:---|:---|:---|:---:|:---|:---:|:---:|:---|:---:|
| 45 | `rencana_aksi:read` | `rencana_aksi` | `read` | **Unit** | Perencanaan, Pimpinan, Superadmin | **Grant Unit** | Tidak | Melihat rincian draf target rencana aksi unit | [ ] Disetujui |
| 46 | `rencana_aksi:create` | `rencana_aksi` | `create` | **Unit** | Perencanaan (Global), Superadmin | **Grant Unit + PIC** | Tidak | Mengisi draf komitmen target rencana aksi | [ ] Disetujui |
| 47 | `rencana_aksi:update` | `rencana_aksi` | `update` | **Unit** | Perencanaan (Global), Superadmin | **Grant Unit + PIC** | Tidak | Mengubah draf komitmen target rencana aksi | [ ] Disetujui |
| 48 | `rencana_aksi:ajukan` | `rencana_aksi` | `ajukan` | **Unit** | Perencanaan (Global), Superadmin | **Grant Unit + PIC** | Tidak | Mengirim draf target untuk diverifikasi Perencanaan | [ ] Disetujui |
| 49 | `kegiatan:read` | `kegiatan` | `read` | **Unit** | Perencanaan, Pimpinan, Superadmin | **Grant Unit** | Tidak | Melihat daftar inisiatif kegiatan unit | [ ] Disetujui |
| 50 | `kegiatan:create` | `kegiatan` | `create` | **Unit** | Perencanaan (Global), Superadmin | **Grant Unit** | Tidak | Menambah inisiatif pendukung capaian (kolaboratif unit) | [ ] Disetujui |
| 51 | `kegiatan:update` | `kegiatan` | `update` | **Unit** | Perencanaan (Global), Superadmin | **Grant Unit** | Tidak | Mengubah rincian progres atau tautan klaim kegiatan | [ ] Disetujui |
| 52 | `kegiatan:delete` | `kegiatan` | `delete` | Global | Perencanaan, Superadmin | – | **Ya** | Menghapus inisiatif kegiatan (wajib alasan resmi) | [ ] Disetujui |
| 53 | `pengukuran:create` | `pengukuran` | `create` | **Unit** | Perencanaan (Global), Superadmin | **Grant Unit + PIC** | Tidak | Mengisi realisasi capaian triwulan pada unit | [ ] Disetujui |
| 54 | `pengukuran:update` | `pengukuran` | `update` | **Unit** | Perencanaan (Global), Superadmin | **Grant Unit + PIC** | Tidak | Memperbarui capaian dan narasi analisis deviasi | [ ] Disetujui |

---

#### 6. Manajemen Berkas Bukti Dukung (Turunan Mutasi Induk)

| No | Kode Permission | Entitas | Aksi | Scope | Hak Akses | Sensitif | Keterangan untuk PM | Status Konfirmasi PM |
|:---:|:---|:---|:---|:---:|:---|:---:|:---|:---:|
| 55 | `berkas:read` | `berkas` | `read` | Global | Seluruh pemegang izin baca data induk | Tidak | Mengunduh berkas bukti (mengikuti hak baca induk) | [ ] Disetujui |
| 56 | `berkas:upload` | `berkas` | `upload` | Global | Diturunkan dari hak mutasi data induk | Tidak | Mengunggah bukti PDF / link dokumen (terkunci sesuai batas imutabilitas induk, 13.6; `rencana_aksi`/`pengukuran` sejak `diajukan`, Q36) | [ ] Disetujui |
| 57 | `berkas:delete` | `berkas` | `delete` | Global | Perencanaan & Pemegang hak mutasi induk | **Ya** | Menghapus lampiran bukti dukung (terkunci sesuai batas imutabilitas induk, 13.6; `rencana_aksi`/`pengukuran` sejak `diajukan`, Q36) | [ ] Disetujui |

---

#### 7. Verifikasi, Pengesahan & Pembukaan Kembali (Khusus PERENCANAAN & SUPERADMIN)
*Role Pegawai dan Admin DILARANG mengesahkan datanya sendiri demi menjamin akuntabilitas instansi.*

| No | Kode Permission | Entitas | Aksi | Role yang Berhak | Sensitif | Aturan Khusus Segregasi Tugas (F1/F2) | Status Konfirmasi PM |
|:---:|:---|:---|:---|:---|:---:|:---|:---:|
| 58 | `rencana_aksi:verifikasi` | `rencana_aksi` | `verifikasi` | Perencanaan, Superadmin | **Ya** | Verifikator dilarang sama dengan `diajukan_by` (F1) | [ ] Disetujui |
| 59 | `rencana_aksi:kembalikan` | `rencana_aksi` | `kembalikan` | Perencanaan, Superadmin | Tidak | Mengembalikan draf ke PIC (alur normal reviu) | [ ] Disetujui |
| 60 | `rencana_aksi:sahkan` | `rencana_aksi` | `sahkan` | Perencanaan, Superadmin | **Ya** | Mengesahkan target beku kumulatif tahunan (F1 berlaku) | [ ] Disetujui |
| 61 | `rencana_aksi:buka_kembali`| `rencana_aksi` | `buka_kembali`| Perencanaan, Superadmin| **Ya** | Membatalkan status sah ke dikembalikan (jalur darurat) | [ ] Disetujui |
| 62 | `pengukuran:verifikasi` | `pengukuran` | `verifikasi` | Perencanaan, Superadmin | **Ya** | Verifikator capaian dilarang sama dengan pengaju (F1) | [ ] Disetujui |
| 63 | `pengukuran:kembalikan` | `pengukuran` | `kembalikan` | Perencanaan, Superadmin | Tidak | Mengembalikan capaian ke draf (alur reviu biasa) | [ ] Disetujui |
| 64 | `pengukuran:sahkan` | `pengukuran` | `sahkan` | Perencanaan, Superadmin | **Ya** | Mengesahkan capaian resmi instansi (F1 berlaku) | [ ] Disetujui |
| 65 | `pengukuran:buka_kembali` | `pengukuran` | `buka_kembali` | Perencanaan, Superadmin | **Ya** | Membuka capaian yang sudah disahkan (wajib alasan audit) | [ ] Disetujui |
| 66 | `status_capaian:update` | `status_capaian` | `update` | Perencanaan, Superadmin | **Ya** | Melakukan override status Tercapai / Belum Tercapai | [ ] Disetujui |
| 67 | `rekomendasi:tetapkan` | `rekomendasi` | `tetapkan` | Pimpinan, Perencanaan, Superadmin (Pimpinan ditambah Q45/Q51) | **Ya** | Menetapkan rekomendasi tindak lanjut evaluasi pimpinan; **Q51:** Perencanaan menyusun draf, Pimpinan menyetujui atau menulis langsung, dan persetujuan oleh Perencanaan menjadi cadangan bertanda (6.7) | [ ] Disetujui |

---

#### 8. Pelaporan Eksekutif & Persetujuan Pimpinan (Khusus PIMPINAN & PERENCANAAN)

| No | Kode Permission | Entitas | Aksi | Role yang Berhak | Sensitif | Keterangan untuk PM | Status Konfirmasi PM |
|:---:|:---|:---|:---|:---|:---:|:---|:---:|
| 68 | `laporan:read` | `laporan` | `read` | Perencanaan, Pimpinan, Admin, Superadmin | Tidak | Membuka matriks tabel rekapitulasi laporan kinerja | [ ] Disetujui |
| 69 | `laporan:ekspor` | `laporan` | `ekspor` | Perencanaan, Pimpinan, Superadmin | Tidak | Mengunduh file laporan resmi (Excel / PDF SAKIP); **Q48:** juga membuat dan mengunduh Rencana Aksi tahunan dan Laporan Kinerja Triwulan serta mengunduh arsip PDF bertanda tangan (8.5) | [ ] Disetujui |
| 70 | `pengukuran:setujui` | `pengukuran` | `setujui` | **Pimpinan**, Superadmin | Tidak | **Fase Lanjutan:** Approval pimpinan setelah disahkan Perencanaan | [ ] Disetujui |
| 71 | `laporan:unggah` | `laporan` | `unggah` | Perencanaan, Superadmin | Tidak (Q48 §48.2 butir 2) | **Baru Q48:** mengunggah PDF bertanda tangan Rencana Aksi tahunan dan Laporan Kinerja Triwulan sebagai arsip append-only (8.5) | [ ] Disetujui |

---

# Lampiran B — Fixture Teknis Ringkas

## B.1 Sembilan Permission `butuh_scope=unit`

```text
rencana_aksi:read
rencana_aksi:create
rencana_aksi:update
rencana_aksi:ajukan
kegiatan:read
kegiatan:create
kegiatan:update
pengukuran:create
pengukuran:update
```

## B.2 Dua Puluh Dua Permission Bertanda Sensitif pada Tabel Detail

```text
akses:update
unit:delete
pengaturan:update
regulasi:update
regulasi:delete
komponen:update
komponen:delete
jenis_berkas:update
jenis_berkas:delete
jadwal:aktivasi
jadwal:tutup
jadwal:buka_kembali
kegiatan:delete
berkas:delete
rencana_aksi:verifikasi
rencana_aksi:sahkan
rencana_aksi:buka_kembali
pengukuran:verifikasi
pengukuran:sahkan
pengukuran:buka_kembali
status_capaian:update
rekomendasi:tetapkan
```

## B.3 Lima Role Sistem Final Q32

```text
superadmin
admin
perencanaan
pimpinan
pegawai
```

PIC bukan role. Tidak ada preset PIC dan tidak ada migrasi Pegawai→PIC. Hak kerja PIC operasional berasal dari Grant Unit yang sesuai.


