# PLAN — Rencana Pengembangan SAKIP LLDIKTI Wilayah XVI

Dokumen ini adalah rencana pengembangan teknis granular untuk Fase Awal (MVP) SAKIP LLDIKTI Wilayah XVI. 

### Arsitektur Teknologi (The Modern Monolith: Laravel + Inertia.js + React)

Aplikasi dibangun dalam satu repositori (*monorepo*) terpadu menggunakan pendekatan **Inertia.js**, menghubungkan backend Laravel dengan frontend React tanpa memerlukan REST API terpisah:

```
React + TypeScript (Inertia Pages & Components via Vite)
       ↕ (Inertia Protocol / Automatic XHR Props / Web Session & CSRF)
Laravel (Controller, Service, Policy/Gate, Middleware HandleInertiaRequests)
       ↓
PostgreSQL 17 (Database Relasional)
```

| Komponen | Teknologi | Peran & Tanggung Jawab |
|---|---|---|
| **Backend** | Laravel (v12 / v13) | **Single Source of Truth**: Menangani routing controller (`Inertia::render`), validasi bisnis (`FormRequest`), otorisasi (`Policy`/`Gate`), state machine alur pengukuran, validasi deadline, snapshot beku, dan audit log |
| **Adapter / Bridge** | **Inertia.js** (`@inertiajs/react` & `inertiajs/inertia-laravel`) | Protokol komunikasi data antara Laravel dan React, passing data sebagai props otomatis, navigasi SPA tanpa full page reload via `<Link>`, form handling via `useForm` hook |
| **Frontend** | React + TypeScript + Vite | **Presentation & Interaction Layer**: Halaman Inertia (`resources/js/Pages`), komponen UI reaktif, form input, dan visual feedback |
| **Styling** | Tailwind CSS v4 | Sistem token desain institusi (institutional blue `#122E92` + gold `#D6AC48`) |
| **Database** | PostgreSQL 17 | Penyimpanan data relasional, integritas constraint, snapshot beku, audit log append-only |
| **Autentikasi / SSO** | Keycloak via Socialite (OIDC Authorization Code Flow) | Single Sign-On institusi, session terproteksi cookie/CSRF, user dan permission di-share via middleware `HandleInertiaRequests` |
| **Visualisasi / Chart** | ApexCharts (`react-apexcharts`) | Grafik interaktif target vs realisasi, ringkasan capaian IKU |
| **Containerization** | Podman & Podman-compose | Standardisasi container rootless OCI untuk deployment, isolated testing, dan orchestration service (termasuk Keycloak SSO) |
| **Backend Testing** | Pest (Inertia Testing) | Pengujian fungsional controller via `$response->assertInertia(...)`, Policy/Gate, validasi bisnis, database assertions |
| **Frontend Testing** | Vitest + React Testing Library | Pengujian render komponen React, interaksi pengguna, mock props testing |

Setiap task memiliki tiga bagian wajib:
- **Scope** — apa yang dikerjakan.
- **Dependency** — task/modul lain yang harus selesai lebih dulu, atau "tidak ada".
- **Definition of Done (DoD)** — kriteria verifikasi mandiri yang konkret dan dapat dicek tanpa bertanya siapa yang mengerjakan.

---

## Modul 1 — Autentikasi & Akses

### 1.1 Setup project Laravel + Inertia.js + React + TypeScript + Tailwind CSS + PostgreSQL
- **Scope:** Inisialisasi project Laravel baru bernama `sakip`, instalasi paket `inertiajs/inertia-laravel`, instalasi `@inertiajs/react`, konfigurasi plugin React + Inertia di Vite (`@vitejs/plugin-react`), instalasi dan konfigurasi Tailwind CSS v4, konfigurasi koneksi database PostgreSQL 17, konfigurasi middleware `HandleInertiaRequests` (sharing `auth.user`, `auth.permissions`, dan `flash`), setup root template `resources/views/app.blade.php`, struktur folder frontend (`resources/js/Pages`, `resources/js/Components`, `resources/js/Layouts`, `resources/js/types`) dan backend (`app/Http/Controllers`, `app/Services`, `routes/web.php`), serta setup framework testing Pest (backend) dan Vitest + React Testing Library (frontend).
- **Dependency:** tidak ada.
- **DoD:** 
  1. Aplikasi Laravel berjalan via `php artisan serve` tanpa error;
  2. Inertia root template berhasil merender halaman React via Vite di browser (`npm run dev`);
  3. TypeScript aktif dan lulus pengecekan tipe (`npm run type-check` atau `tsc --noEmit`) tanpa error;
  4. Tailwind CSS aktif (kelas utility terkompilasi dan styling tampil di komponen React);
  5. Koneksi PostgreSQL berhasil (`php artisan migrate` sukses);
  6. Rute Inertia uji coba sederhana (`/`) berhasil merender halaman dengan props test;
  7. Test Pest backend dasar berjalan hijau menggunakan assertion Inertia (`$response->assertInertia(...)`);
  8. Test Vitest frontend dasar (`npm run test` / `vitest run`) berjalan hijau merender 1 komponen test React.

### 1.2 Integrasi Keycloak via Socialite (OIDC Authorization Code Flow)
- **Scope:** Install `laravel/socialite` + `SocialiteProviders/Keycloak`, konfigurasi client_id baru khusus SAKIP di `.env` dan `config/services.php`, implementasi rute `redirect`/`callback` berbasis web session terproteksi CSRF, injeksi data user dan permission aktif ke shared props Inertia via middleware `HandleInertiaRequests`, serta penanganan redirect ke Keycloak saat status sesi unauthenticated.
- **Dependency:** 1.1.
- **DoD:** Mengakses rute login mengarahkan browser ke halaman Keycloak; setelah login berhasil di Keycloak, redirect kembali ke callback Laravel dan sesi Laravel (`Auth::check()`) bernilai true; shared props `auth.user` dapat diakses di seluruh halaman React via hook `usePage().props.auth`; test Pest memverifikasi rute terproteksi mengembalikan redirect ke login saat belum autentikasi; test Vitest memverifikasi hook auth di React.

### 1.3 Migrasi & model `users` dengan pemetaan `keycloak_id`
- **Scope:** Buat migrasi tabel `users` (id uuid, keycloak_id unique, nama, email) dan model Eloquent terkait; logic pada callback OIDC untuk `firstOrCreate` berdasarkan `keycloak_id`, sinkronisasi `nama`/`email` dari klaim token setiap login.
- **Dependency:** 1.2.
- **DoD:** Login pengguna baru menghasilkan baris baru di tabel `users` dengan `keycloak_id` terisi; login ulang pengguna yang sama tidak membuat baris duplikat (constraint unique `keycloak_id` ditegakkan, dibuktikan test Pest yang memanggil proses login dua kali dengan klaim identik dan menghitung jumlah baris tetap 1).

### 1.4 Migrasi `unit`
- **Scope:** Buat migrasi dan model `unit` (id, nama, status enum aktif/nonaktif, created_by, created_at). Nama tabel dan seluruh referensi kode memakai `unit` — bukan `tim_kerja` — sejak migrasi pertama.
- **Dependency:** 1.3 (butuh `users` untuk FK `created_by`).
- **DoD:** Migrasi berjalan tanpa error; model `Unit` dapat membuat baris baru via tinker/test dengan status default `aktif`; pencarian string `tim_kerja` di seluruh basis kode (migrasi, model, factory, controller, frontend) mengembalikan nol hasil.

### 1.5 Migrasi `user_role_presets` & `user_permissions`
- **Scope:** Buat migrasi kedua tabel, termasuk enum `role` (`superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`) dan constraint unique `(user_id, permission, unit_id)` pada `user_permissions`. Kolom FK memakai nama `unit_id` (bukan `tim_kerja_id`).
- **Dependency:** 1.3, 1.4.
- **DoD:** Migrasi berhasil; enum `role` memuat kelima nilai; percobaan insert baris `user_permissions` duplikat (kombinasi user_id+permission+unit_id sama) ditolak database dengan unique constraint violation, dibuktikan test Pest yang menangkap exception tersebut.

### 1.6 Katalog permission sebagai konstanta/enum aplikasi
- **Scope:** Definisikan seluruh permission (termasuk `unit:create/read/update/delete`, `pengukuran:buka_kembali`, `pengaturan:update`) sebagai konstanta terpusat di backend Laravel (mis. PHP enum/class `Permission`) dan TypeScript types (`PermissionEnum`) di frontend agar dipakai konsisten di seluruh Policy/Gate dan komponen UI, menghindari typo string bebas.
- **Dependency:** 1.5.
- **DoD:** Daftar konstanta backend dan tipe TypeScript memuat seluruh permission dari katalog final (termasuk `pengukuran:buka_kembali` dan `pengaturan:update`) tanpa kekurangan/kelebihan; test Pest membandingkan daftar konstanta backend dengan daftar permission fixture test dan lulus sama persis; tidak ada satu pun konstanta bernama `tim_kerja:*`.

### 1.7 Service evaluasi permission (gate akses backend)
- **Scope:** Implementasi service/Gate Laravel yang mengevaluasi permission user terhadap `user_permissions` (union hasil preset + eksplisit), termasuk pengecekan scope `unit_id` khusus untuk `pengukuran:create`/`update`, dengan aturan: baris `unit_id = NULL` berlaku global (lintas unit), baris `unit_id` terisi hanya berlaku untuk unit tersebut.
- **Dependency:** 1.5, 1.6.
- **DoD:** Test Pest: user tanpa permission relevan mendapat hasil `false`/403 saat memanggil aksi terproteksi; user dengan permission global (`unit_id` NULL) untuk `pengukuran:create`/`update` (mis. Perencanaan) diizinkan untuk indikator milik unit mana pun; user dengan `pengukuran:create` di-scope unit A ditolak saat mencoba membuat pengukuran untuk indikator milik unit B, dan diizinkan untuk indikator milik unit A.

### 1.8 Seeder role preset → user_permissions
- **Scope:** Implementasi logic (service/Job) yang, saat role preset di-assign ke user, menyalin seluruh permission preset terkait ke `user_permissions` milik user tersebut. Preset Perencanaan menyalin `pengukuran:create`/`pengukuran:update` dengan `unit_id = NULL` (global) beserta `pengukuran:buka_kembali`. Preset Admin menyalin `pengguna:read`, `akses:update`, `unit:create/read/update/delete`, `pengaturan:update`, `audit:read`, `dashboard:read`, `laporan:read` — **tanpa** permission substantif data kinerja apa pun. Preset Superadmin menyalin seluruh permission katalog tanpa kecuali, termasuk `pengaturan:update`.
- **Dependency:** 1.6, 1.7.
- **DoD:** Test Pest: assign role `perencanaan` ke user menghasilkan baris `user_permissions` yang mencakup `pengukuran:create`/`update` dengan `unit_id` NULL serta `pengukuran:buka_kembali`; assign role `admin` menghasilkan baris permission yang **cocok persis** dengan daftar 7 permission di atas, dan sama sekali tidak menghasilkan baris `pengukuran:*`, `renstra:*`, `indikator:*`, `jadwal:*`, `penanggung_jawab:update`, `status_capaian:update`, atau `laporan:ekspor`; assign role `superadmin` menghasilkan baris yang mencakup seluruh permission katalog termasuk `pengaturan:update`.

### 1.9 UI Form — Assign Role Preset (Inertia React Page + Controller)
- **Scope:** Halaman React Inertia (`Pages/Users/AssignRole.tsx`) menggunakan hook `useForm` dari `@inertiajs/react` untuk Superadmin/Admin memilih pengguna dan menetapkan satu role preset (Superadmin/Admin/Perencanaan/Pimpinan/Pegawai), memanggil controller action `UserController@assignRole`; memicu logic 1.8 saat disimpan; mencatat audit log; permission `akses:update`.
- **Dependency:** 1.8, Modul 10 (audit dasar — lihat 10.1).
- **DoD:** 
  1. Test Pest backend: request ke controller dengan permission `akses:update` (Superadmin/Admin) berhasil menyimpan `user_role_presets` dan menyalin permission, baris `audit_log` terbentuk dengan `tindakan` mengandung kata "role" dan `objek_tipe = user`, sedangkan request dari user tanpa `akses:update` (Pimpinan, Pegawai) menghasilkan status 403;
  2. Test Vitest frontend: form React merender dropdown user dan opsi role, validasi submit mengirim data yang sesuai via Inertia.

### 1.10 UI Form — Assign Scope Unit untuk Pengukuran (Inertia React Page + Controller)
- **Scope:** Komponen form React Inertia (`useForm`) untuk pemegang `akses:update` (Superadmin/Admin) memilih pengguna + unit, memanggil controller action `UserController@assignUnitScope`, memberikan `pengukuran:create` dan `pengukuran:update` yang di-scope ke `unit_id` tersebut (khusus untuk role Pegawai — Perencanaan sudah global sejak preset).
- **Dependency:** 1.5, 1.7, 1.4 (unit harus ada).
- **DoD:** 
  1. Test Pest backend: submit request ke controller menghasilkan 2 baris baru di `user_permissions` dengan `unit_id` terisi sama (bukan NULL), baris `audit_log` terbentuk, percobaan submit oleh user tanpa `akses:update` ditolak 403, dan user dengan role preset Admin berhasil submit;
  2. Test Vitest frontend: form React menampilkan pilihan unit aktif dan menangani respons sukses serta error flash messages.

### 1.11 Middleware & Policy proteksi rute berbasis permission
- **Scope:** Terapkan pengecekan permission (1.7) sebagai middleware Laravel dan Policy di seluruh rute web Inertia yang memerlukan otorisasi. Seluruh proteksi hak akses ditegakkan mutlak di backend, sementara frontend React hanya mengatur visibilitas tombol/menu berdasarkan shared props permissions yang diterima dari middleware `HandleInertiaRequests`.
- **Dependency:** 1.7.
- **DoD:** Test Pest feature: mengakses rute Inertia tanpa permission memadai mengembalikan status HTTP 403 Forbidden meski URL diakses langsung di browser; mencakup skenario role Admin mencoba mengakses rute substantif data kinerja (mis. create Renstra, verifikasi pengukuran, aktivasi jadwal) dan mendapat 403 pada setiap kasus.

### 1.12 UI Form — Pengelolaan Unit (Inertia React Page + Controller)
- **Scope:** Halaman React Inertia (`Pages/Unit/Index.tsx`) dan modal CRUD Unit (create/read/update/delete, transisi status aktif/nonaktif) terhubung ke `UnitController` bagi pemegang `unit:create/read/update/delete` (Admin dan Superadmin); menyatukan aturan integritas: unit dengan indikator terkait tidak dapat dihapus oleh siapa pun, terlepas dari role.
- **Dependency:** 1.4, 1.7.
- **DoD:** 
  1. Test Pest backend: user dengan role Admin atau Superadmin dapat membuat, membaca, mengubah, dan menghapus unit kosong via controller; percobaan menghapus unit yang masih memiliki indikator terkait ditolak dengan redirect back dan flash error pesan spesifik; user dengan role Perencanaan/Pimpinan/Pegawai mendapat response 403; setiap create/update/delete tercatat di `audit_log` dengan `objek_tipe = unit`;
  2. Test Vitest frontend: komponen React merender daftar unit, menangani pencarian, modal create/edit via `useForm`, dan menampilkan feedback error penolakan integritas secara akurat.

---

## Modul 2 — Master Renstra (Renstra, Sasaran, Indikator, Target, PK)

### 2.1 Migrasi & model `renstra`
- **Scope:** Buat migrasi tabel `renstra` (termasuk enum status draft/aktif/nonaktif/diarsipkan).
- **Dependency:** 1.3 (FK created_by).
- **DoD:** Migrasi berjalan; model dapat dibuat dengan status default `draft`.

### 2.2 CRUD Renstra (Inertia React Page + Controller)
- **Scope:** Halaman React (`Pages/Renstra/Index.tsx`, `Form.tsx`) dan controller untuk create/edit/list/delete Renstra (nama, keterangan, dasar_hukum, tahun_mulai, tahun_akhir); permission backend `renstra:create/read/update/delete`.
- **Dependency:** 2.1, 1.7.
- **DoD:** 
  1. Test Pest backend: user dengan `renstra:create` dapat membuat Renstra baru berstatus draft, user tanpa `renstra:read` mendapat 403 saat memanggil index, setiap create/update/delete menghasilkan baris `audit_log`, diverifikasi dengan `$response->assertInertia(fn (Assert $page) => $page->component('Renstra/Index')->has('renstra'))`;
  2. Test Vitest frontend: komponen React merender form dengan `useForm`, tabel daftar Renstra, dan menangani state loading serta pesan kesalahan validasi.

### 2.3 Validasi & aksi aktivasi Renstra (Backend Controller + React Action)
- **Scope:** Controller action `RenstraController@aktivasi` dan tombol aksi "Aktivasi" pada komponen React, menjalankan validasi backend: `dasar_hukum` terisi dan tidak ada Renstra aktif lain dengan rentang tahun beririsan.
- **Dependency:** 2.2.
- **DoD:** Test Pest: aktivasi Renstra dengan `dasar_hukum` kosong ditolak dengan flash error spesifik; aktivasi Renstra yang rentang tahunnya beririsan dengan Renstra aktif lain ditolak; aktivasi Renstra valid mengubah `status` menjadi `aktif` dan mencatat audit log; test Vitest mengonfirmasi tombol aksi memicu Inertia visit dan memperbarui badge status Renstra menjadi `aktif`.

### 2.4 Guard nonaktifkan Renstra & aksi arsipkan
- **Scope:** Transisi status `aktif → nonaktif` ditolak di layer backend selama masih ada `jadwal_tahunan` berstatus `aktif` yang merujuk Renstra tersebut — jadwal harus ditutup lebih dulu (Modul 3). Transisi `nonaktif → diarsipkan` tetap berlaku tanpa syarat tambahan. Masing-masing transisi memerlukan `renstra:update` dan menghasilkan audit log. Tombol aksi di React menampilkan dialog konfirmasi tindakan.
- **Dependency:** 2.3, 3.7 (aksi tutup jadwal, untuk pengujian guard).
- **DoD:** Test Pest: percobaan nonaktifkan Renstra yang masih memiliki jadwal_tahunan aktif ditolak dengan response error spesifik; setelah seluruh jadwal terkait ditutup, nonaktifkan berhasil; urutan transisi hanya bisa terjadi sesuai alur (tidak bisa langsung draft→diarsipkan); setiap transisi tercatat di audit_log dengan nilai_lama/nilai_baru status.

### 2.5 Migrasi & CRUD `sasaran` (Inertia React + Controller)
- **Scope:** Migrasi tabel `sasaran` (renstra_id, nama, keterangan, urutan) + controller CRUD Sasaran dengan fitur reorder urutan dan komponen React `Pages/Renstra/Sasaran.tsx`; permission `sasaran:create/update/delete`.
- **Dependency:** 2.1.
- **DoD:** Sasaran baru dapat dibuat di bawah Renstra tertentu dan tampil terurut sesuai kolom `urutan`; percobaan hapus Sasaran yang masih punya Indikator ditolak di controller; test Pest menguji operasi CRUD via Inertia assertion; test Vitest menguji komponen list/reorder Sasaran.

### 2.6 Migrasi `indikator` (termasuk kolom `arah`)
- **Scope:** Migrasi tabel `indikator`, termasuk `unit_id` NOT NULL (menggantikan `tim_kerja_id`), `wajib_catatan`, `created_by_role`, dan kolom baru `arah` enum(`naik_baik`, `turun_baik`) NOT NULL default `naik_baik`.
- **Dependency:** 2.5, 1.4 (unit).
- **DoD:** Migrasi berjalan; percobaan insert indikator tanpa `unit_id` ditolak database (NOT NULL constraint); indikator baru tanpa mengisi `arah` secara eksplisit otomatis tersimpan dengan `arah = naik_baik`; kolom bernama `unit_id`, bukan `tim_kerja_id`.

### 2.7 CRUD Indikator + validasi kepemilikan unit + pilihan arah (Inertia React + Controller)
- **Scope:** React component form (`Pages/Indikator/Form.tsx`) dan controller untuk create/edit/list Indikator di bawah Sasaran, wajib memilih Unit pemilik dan Arah Penilaian (naik_baik/turun_baik); permission `indikator:create/read/update/delete`.
- **Dependency:** 2.6, 1.4.
- **DoD:** Indikator baru tersimpan dengan `unit_id` dan `arah` terisi; `created_by_role` otomatis terisi dari role aktif user saat pembuatan; setiap create/update/delete tercatat di audit_log; form React memuat kedua opsi arah secara eksplisit (tidak hanya default tersembunyi); test Pest menguji `$response->assertInertia(...)`; test Vitest menguji form pilihan unit dan radio/select arah penilaian.

### 2.8 Fitur pindah unit pada Indikator (Backend Action + Modal React)
- **Scope:** Controller action khusus `IndikatorController@pindahUnit` dan modal konfirmasi di React untuk mengubah `unit_id` suatu indikator, dengan konfirmasi eksplisit dan pencatatan audit detail (nilai_lama/nilai_baru unit).
- **Dependency:** 2.7.
- **DoD:** Test Pest: mengubah unit indikator via controller menghasilkan baris audit_log dengan `tindakan` spesifik (mis. `indikator.pindah_unit`) berisi `nilai_lama.unit_id` dan `nilai_baru.unit_id` yang berbeda dan benar; test Vitest mengonfirmasi modal pemindahan unit mengirim request Inertia dengan ID unit baru.

### 2.9 Aksi arsipkan Indikator + blokir create pada indikator arsip
- **Scope:** Transisi status indikator `aktif → arsip`; indikator arsip tidak muncul sebagai opsi pengisian pengukuran baru di frontend React namun tetap terbaca di riwayat. Guard eksplisit di layer backend `pengukuran:create`: menolak permintaan create untuk indikator berstatus `arsip`, terlepas dari siapa pemohonnya (termasuk Perencanaan/Superadmin).
- **Dependency:** 2.7.
- **DoD:** Indikator berstatus arsip tidak muncul di dropdown pembuatan pengukuran baru pada komponen React; query riwayat pengukuran lama untuk indikator tsb tetap mengembalikan data lengkap dengan penanda status arsip; test Pest: percobaan `pengukuran:create` langsung (bypass UI) untuk indikator arsip ditolak dengan pesan spesifik, diuji baik oleh user PIC maupun user Perencanaan.

### 2.10 Migrasi & fitur Target Tahunan (Inertia React Form + Controller)
- **Scope:** Migrasi `target_tahunan` (unique indikator_id+tahun) + controller action dan React form input/update target per indikator per tahun; permission `target:update`.
- **Dependency:** 2.6.
- **DoD:** Input target untuk kombinasi indikator+tahun yang sudah ada memperbarui baris (bukan duplikat, dibuktikan unique constraint); target dapat diisi `0` sebagai nilai sah, dan dibedakan dari kondisi belum diisi (`null`) di tampilan React; test Pest menguji keabsahan nilai 0 dan constraint via Inertia assertion; test Vitest menguji validasi input target numerik.

### 2.11 Migrasi & fitur Perjanjian Kinerja (Inertia React Form + Controller)
- **Scope:** Migrasi `renstra_pk` (unique renstra_id+tahun) + controller action dan React form input nomor_pk, tanggal_pk per tahun per Renstra; permission `pk:create/update`.
- **Dependency:** 2.1.
- **DoD:** PK baru untuk kombinasi Renstra+tahun yang sama ditolak sistem (unique constraint) kecuali melalui alur koreksi (2.12); PK tersimpan dan dapat dirujuk saat aktivasi Jadwal (Modul 3); test Pest & test Vitest memverifikasi pencatatan PK.

### 2.12 Fitur koreksi Perjanjian Kinerja (wajib alasan via Modal React)
- **Scope:** Controller action khusus untuk mengubah `nomor_pk`/`tanggal_pk` pada PK yang sudah tercatat, mewajibkan pengisian alasan koreksi via modal konfirmasi di frontend React.
- **Dependency:** 2.11.
- **DoD:** Submit koreksi tanpa alasan ditolak validasi backend (flash error jelas); koreksi berhasil menghasilkan baris audit_log dengan `alasan` terisi dan `nilai_lama`/`nilai_baru` mencerminkan field yang berubah; test Pest & Vitest memverifikasi alur koreksi.

### 2.13 Fitur revisi Renstra in place akibat perubahan Kepmen IKU
- **Scope:** Alur edit langsung atas field Renstra yang sudah aktif — dipakai khusus saat Kepmen IKU direvisi; mewajibkan alasan yang memuat nomor dan tanggal Kepmen; memakai permission `renstra:update` yang sama seperti edit biasa, namun dengan validasi tambahan di backend: alasan wajib diisi setiap kali Renstra berstatus `aktif` diedit.
- **Dependency:** 2.3.
- **DoD:** Test Pest: edit atas Renstra berstatus aktif tanpa alasan ditolak; edit dengan alasan berhasil menyimpan perubahan pada baris Renstra yang sama (ID tidak berubah, tidak ada baris Renstra baru terbentuk) dan tercatat audit_log dengan `nilai_lama`/`nilai_baru` field yang berubah; test Vitest menguji form modal revisi Renstra aktif.

---

## Modul 3 — Periode & Jadwal

### 3.1 Migrasi & seed master `periode`
- **Scope:** Migrasi tabel `periode` (nama, urutan, aktif, is_nilai_akhir) + controller dan React page CRUD terbatas (Superadmin/Perencanaan); validasi backend tepat satu baris `is_nilai_akhir=true`.
- **Dependency:** 1.7.
- **DoD:** Percobaan menyimpan periode kedua dengan `is_nilai_akhir=true` ditolak selama masih ada periode lain yang juga `true` (harus non-aktifkan yang lama dulu, ditegaskan via validasi backend); test Pest mengonfirmasi hal ini.

### 3.2 Migrasi `jadwal_tahunan` (tanpa kolom jendela)
- **Scope:** Migrasi tabel `jadwal_tahunan` **tanpa** kolom `pengisian_mulai`, `pengisian_selesai`, `reviu_mulai`, `reviu_selesai` (dipindahkan ke `jadwal_periode`, lihat 3.3). Kolom yang tetap ada: `renstra_id`, `tahun`, `penutupan`, `pakai_persetujuan_pimpinan`, `persetujuan_mulai`, `persetujuan_selesai` (disiapkan, tidak dipakai logic Fase Awal), `status`, `renstra_pk_id`, `activated_at`, `closed_at`; constraint unique tahun untuk jadwal aktif per Renstra.
- **Dependency:** 2.1, 2.11 (renstra_pk).
- **DoD:** Migrasi berjalan; skema tabel `jadwal_tahunan` tidak memiliki satu pun kolom `pengisian_*`/`reviu_*` (diverifikasi via introspeksi skema di test Pest); kolom-kolom approval Pimpinan ada di skema dan bernilai default (`false`/`null`) tanpa mempengaruhi logic manapun saat ini.

### 3.3 Migrasi & CRUD `jadwal_periode` (Inertia React Form + Controller)
- **Scope:** Migrasi tabel pivot baru `jadwal_periode` (jadwal_id, periode_id, pengisian_mulai, pengisian_selesai, reviu_mulai, reviu_selesai) + controller dan React form component (`Pages/Jadwal/PeriodeForm.tsx`) bagi Perencanaan untuk memilih periode-periode yang diharapkan pada suatu Jadwal Tahunan dan mengisi jendela masing-masing; permission `jadwal:update`.
- **Dependency:** 3.2, 3.1.
- **DoD:** Baris `jadwal_periode` baru tersimpan dengan `jadwal_id` + `periode_id` unik per pasangan; validasi tanggal logis per baris (`pengisian_mulai` ≤ `pengisian_selesai` ≤ `reviu_mulai` ≤ `reviu_selesai`) ditegakkan di backend Laravel dan diuji via Pest; satu Jadwal Tahunan dapat memiliki beberapa baris `jadwal_periode` (mis. 4 baris untuk Triwulan I–IV) dengan jendela yang berbeda-beda per baris; test Vitest menguji komponen React dalam menyusun daftar periode dan jendela waktu.

### 3.4 CRUD Jadwal Tahunan (draft) via Inertia React + Controller
- **Scope:** React component page (`Pages/Jadwal/Index.tsx`, `Form.tsx`) create/edit Jadwal Tahunan berstatus draft terhubung ke `JadwalController`: pilih Renstra, tahun, tanggal penutupan; permission `jadwal:create/update`.
- **Dependency:** 3.2.
- **DoD:** Jadwal baru tersimpan berstatus `draft`; form React tidak menampilkan field jendela pengisian/reviu tingkat tahun (pengaturan jendela dilakukan lewat 3.3); test Pest menguji `$response->assertInertia(...)`; test Vitest menguji form draft Jadwal.

### 3.5 Aksi aktivasi Jadwal + tiga gerbang validasi (Backend Controller + Modal Checklist React)
- **Scope:** Implementasi aksi controller `JadwalController@aktivasi` dengan tiga gerbang berurutan di backend Laravel: (1) `renstra_pk` untuk `(renstra_id, tahun)` jadwal sudah tercatat; (2) seluruh indikator berstatus `aktif` milik Renstra tersebut memiliki `target_tahunan` untuk tahun jadwal; (3) tahun jadwal berada dalam rentang `[tahun_mulai, tahun_akhir]` Renstra terkait. Jika seluruh gerbang lolos: isi `renstra_pk_id` dan `activated_at`. Di frontend React, sediakan modal checklist konfirmasi aktivasi yang menampilkan status kesiapan ketiga gerbang.
- **Dependency:** 3.4, 2.11, 2.10, 2.6.
- **DoD:** Test Pest terpisah untuk masing-masing gerbang: (a) aktivasi tanpa PK tahun terkait ditolak dengan pesan spesifik gerbang 1; (b) aktivasi dengan minimal satu indikator aktif tanpa target tahun tsb ditolak dengan pesan spesifik gerbang 2; (c) aktivasi dengan tahun jadwal di luar rentang Renstra ditolak dengan pesan spesifik gerbang 3; (d) aktivasi yang lolos ketiga gerbang berhasil mengubah status ke `aktif`, mengisi `renstra_pk_id`, dan `activated_at` terisi timestamp saat itu; setiap penolakan tercatat sebagai audit_log percobaan gagal; test Vitest menguji modal checklist konfirmasi aktivasi di React.

### 3.6 Migrasi & model `jadwal_snapshot` (kolom `unit_id`, `arah`)
- **Scope:** Migrasi tabel `jadwal_snapshot` (kolom salinan, bukan FK aktif untuk field non-indikator_id): `jadwal_id`, `indikator_id`, `unit_id`, `nama`, `definisi`, `satuan`, `presisi`, `desimal_tampilan`, `arah`, `target`. Kolom `arah` disalin dari `indikator.arah` sebagai bagian konteks beku.
- **Dependency:** 3.2, 2.6.
- **DoD:** Migrasi berjalan; model dapat diisi dan dibaca sesuai struktur snapshot; kolom bernama `unit_id` (bukan `tim_kerja_id`) dan mencakup `arah`.

### 3.7 Trigger idempoten pembuatan `jadwal_snapshot` saat aktivasi/buka-kembali
- **Scope:** Service layer backend (dipanggil dari 3.5 dan dari 3.9 saat `jadwal:buka_kembali`) yang mengambil seluruh indikator aktif milik Renstra terkait dan, untuk tiap indikator, **hanya** membuat baris `jadwal_snapshot` baru jika pasangan `(jadwal_id, indikator_id)` belum memiliki baris — baris yang sudah ada dilewati, tidak pernah ditimpa. Baris baru menyalin nama/definisi/satuan/presisi/desimal_tampilan/unit_id/arah dari master serta `target_tahunan` untuk tahun jadwal. Audit log pembuatan snapshot memakai `actor_id` = pengguna yang menjalankan aksi aktivasi/buka_kembali.
- **Dependency:** 3.5, 2.7, 2.10.
- **DoD:** Setelah aktivasi jadwal dengan N indikator aktif terkait, query `jadwal_snapshot WHERE jadwal_id = ...` mengembalikan tepat N baris; memanggil logic ini ulang tanpa perubahan data (idempoten) tidak menambah baris baru maupun mengubah baris lama; setelah menambahkan 1 indikator baru dan memanggil ulang logic ini, hanya 1 baris baru terbentuk sementara N baris lama tidak berubah; mengubah `indikator.nama` master setelah snapshot terbentuk tidak mengubah nilai `nama` pada baris snapshot yang sudah ada; audit_log baris snapshot baru memiliki `actor_id` sama dengan pelaku aktivasi/buka_kembali.

### 3.8 Guard imutabilitas baris `jadwal_snapshot` yang sudah dirujuk
- **Scope:** Policy/Observer backend yang menolak perubahan pada baris `jadwal_snapshot` yang telah dirujuk oleh minimal satu baris `pengukuran`; baris yang belum dirujuk boleh dikoreksi hanya selama `jadwal_tahunan` terkait berstatus `aktif`, dan koreksi tersebut wajib tercatat audit_log.
- **Dependency:** 3.7, 5.1 (pengukuran, untuk mengecek rujukan).
- **DoD:** Test Pest: percobaan mengubah baris snapshot yang sudah dirujuk pengukuran ditolak dengan pesan spesifik; percobaan mengubah baris snapshot yang belum dirujuk, saat jadwal `aktif`, berhasil dan tercatat audit_log; percobaan yang sama saat jadwal `ditutup` ditolak.

### 3.9 Aksi tutup & buka kembali Jadwal (mekanisme standar)
- **Scope:** Controller action untuk transisi status `aktif → ditutup` (mengisi `closed_at`) dan `ditutup → aktif` (`jadwal:buka_kembali`, alasan wajib via modal React, memicu ulang trigger idempoten 3.7); permission `jadwal:tutup`, `jadwal:buka_kembali`.
- **Dependency:** 3.5, 3.7.
- **DoD:** Test Pest memverifikasi transisi status dan pengisian/reset `closed_at` sesuai arah transisi; percobaan `jadwal:buka_kembali` tanpa alasan ditolak; setiap transisi tercatat audit_log; setelah buka_kembali, indikator baru yang ditambahkan sejak jadwal ditutup mendapat baris snapshot baru tanpa mengubah baris snapshot lama; test Vitest menguji modal buka-kembali di frontend React.

### 3.10 Tampilan status jadwal & indikator per jendela waktu periode (Inertia React + Controller)
- **Scope:** Halaman React (`Pages/Jadwal/Detail.tsx`) dan controller yang menampilkan jadwal aktif beserta status jendela pengisian/reviu **per periode** (dari `jadwal_periode`, bukan dari `jadwal_tahunan`), berjalan berdasarkan tanggal hari ini vs kolom `jadwal_periode` yang relevan.
- **Dependency:** 3.5, 3.3.
- **DoD:** Komponen React menampilkan label benar (mis. "Masa pengisian Triwulan II", "Masa reviu Triwulan II", "Periode Triwulan I ditutup") sesuai data props yang dikirim controller; test Pest menguji perhitungan status jendela waktu di backend dengan `Carbon::setTestNow()`; test Vitest menguji render badge status periode di React.

### 3.11 Aktivasi jadwal retroaktif untuk backfill data historis
- **Scope:** Memastikan alur backfill data tahun lampau berjalan end-to-end tanpa mekanisme khusus baru: Perencanaan membuat `jadwal_tahunan` untuk tahun lampau yang berada di dalam rentang Renstra (dengan `renstra_pk` tahun tersebut tercatat sebagai gerbang aktivasi), menyusun `jadwal_periode` untuk tahun itu, lalu mengaktifkannya via controller/UI. Karena seluruh jendela pengisian periode berada di masa lalu, PIC ber-scope unit otomatis terkunci dan hanya Perencanaan (permission global, tanpa batas jendela) yang dapat mengisi pengukuran.
- **Dependency:** 2.11, 3.3, 3.5, 3.7, 5.2.
- **DoD:** Test Pest membuktikan: (a) jadwal tahun lampau dalam rentang Renstra dapat diaktifkan lewat tiga gerbang validasi yang sama dan menghasilkan baris `jadwal_snapshot` secara idempoten; (b) pengguna ber-scope unit (PIC) ditolak saat mencoba membuat/mengubah/mengajukan pengukuran pada periode yang jendelanya sudah lewat, sementara Perencanaan berhasil mengisi periode yang sama; (c) `activated_at` mencatat waktu aktivasi sebenarnya dan tercatat di `audit_log`.

---

## Modul 4 — Penugasan (Penanggung Jawab)

### 4.1 Migrasi & model `penanggung_jawab`
- **Scope:** Migrasi tabel sesuai data model.
- **Dependency:** 2.6 (indikator), 1.3 (users).
- **DoD:** Migrasi berjalan; baris dapat dibuat dengan `alasan` nullable untuk penugasan pertama.

### 4.2 Form penugasan awal Penanggung Jawab (Inertia React + Controller)
- **Scope:** Komponen modal React form (`useForm`) dan controller action `PenugasanController@store` untuk menetapkan penanggung jawab pertama suatu indikator (tanggal_mulai_berlaku, user_id); permission `penanggung_jawab:update`.
- **Dependency:** 4.1.
- **DoD:** Penugasan pertama tersimpan tanpa mewajibkan `alasan`; baris tercatat di audit_log; test Pest menguji otorisasi dan controller action; test Vitest menguji form modal penugasan di React.

### 4.3 Fitur pergantian Penanggung Jawab (wajib alasan via Modal React)
- **Scope:** Controller action khusus untuk menambah baris baru penugasan pada indikator yang sudah punya penanggung jawab sebelumnya, mewajibkan `alasan` via modal konfirmasi di frontend React.
- **Dependency:** 4.2.
- **DoD:** Test Pest: submit pergantian tanpa alasan pada indikator yang sudah punya PJ sebelumnya ditolak validasi; submit dengan alasan berhasil menambah baris baru (baris lama tidak terhapus/termodifikasi) dan tercatat audit_log; test Vitest menguji modal pergantian PJ di React.

### 4.4 Query/fungsi resolusi Penanggung Jawab Efektif
- **Scope:** Implementasi fungsi/scope Eloquent di backend Laravel yang mengembalikan baris `penanggung_jawab` dengan `tanggal_mulai_berlaku` maksimum yang ≤ tanggal acuan tertentu, untuk suatu indikator.
- **Dependency:** 4.1.
- **DoD:** Test Pest dengan 3 baris riwayat penugasan bertanggal berbeda mengonfirmasi fungsi mengembalikan baris yang benar untuk beberapa tanggal acuan berbeda, termasuk tanggal acuan di masa lalu.

---

## Modul 5 — Pengukuran

### 5.1 Migrasi & model `pengukuran`
- **Scope:** Migrasi tabel sesuai data model, termasuk kolom `versi` (default 1) dan unique constraint `(indikator_id, tahun, periode_id)`.
- **Dependency:** 3.6 (jadwal_snapshot), 1.7.
- **DoD:** Migrasi berjalan; percobaan insert baris duplikat kombinasi indikator+tahun+periode ditolak database.

### 5.2 Buat Draft Pengukuran (PIC scoped, Perencanaan global via Inertia React + Controller)
- **Scope:** Halaman React (`Pages/Pengukuran/Create.tsx`) dengan `useForm` dan controller action `PengukuranController@store` untuk membuat baris pengukuran baru berstatus `draft`, terhubung ke `jadwal_snapshot_id` yang relevan (indikator+tahun+periode pada jadwal aktif). Pemohon dengan permission ber-scope unit (PIC) hanya dapat membuat untuk indikator unit-nya; pemohon dengan permission global (Perencanaan) dapat membuat untuk indikator unit mana pun.
- **Dependency:** 5.1, 3.7, 4.4, 1.7.
- **DoD:** User dengan `pengukuran:create` di-scope unit yang sesuai berhasil membuat draft untuk indikator unit tersebut via controller, dan ditolak (403) untuk indikator unit lain; user dengan `pengukuran:create` global (unit_id NULL) berhasil membuat draft untuk indikator unit mana pun; baris baru memiliki `versi = 1` dan `status_alur = draft`; percobaan membuat pengukuran untuk indikator berstatus `arsip` ditolak untuk kedua jenis pemohon; test Vitest menguji form draft pengukuran di React.

### 5.3 Edit nilai/catatan Draft (Optimistic Locking via Versi)
- **Scope:** Form edit React (`Pages/Pengukuran/Edit.tsx`) dan controller action `PengukuranController@update` untuk mengedit nilai dan catatan pada pengukuran berstatus draft; menaikkan `versi` setiap simpan; permission `pengukuran:update` (scoped untuk PIC, global untuk Perencanaan).
- **Dependency:** 5.2.
- **DoD:** Setiap submit edit berhasil menaikkan kolom `versi` sebanyak 1; submit dengan `versi` yang dikirim klien React tidak sesuai versi terbaru di database ditolak dengan status HTTP 409 Conflict dan flash error jelas; test Pest mensimulasikan dua edit berurutan dengan versi usang pada percobaan kedua; test Vitest menguji form edit dan penanganan error konflik versi.

### 5.4 Guard deadline jendela pengisian periode bagi PIC (Backend Enforcement)
- **Scope:** Lapisan validasi bisnis di backend Laravel (terpisah dari lapisan permission) yang menolak `pengukuran:create`/`update`/aksi ajukan oleh PIC (permission ber-scope unit) begitu tanggal hari ini melewati `jadwal_periode.pengisian_selesai` periode terkait. Permintaan dari pemegang permission global (Perencanaan) **dikecualikan** dari guard ini — hanya dibatasi oleh `jadwal_tahunan.penutupan`. Frontend React menampilkan banner deadline dan menonaktifkan form jika waktu telah habis, namun backend tetap menjadi pengawas mutlak.
- **Dependency:** 5.2, 5.3, 3.3.
- **DoD:** Test Pest dengan `Carbon::setTestNow()`: PIC mencoba create/update/ajukan via controller setelah `pengisian_selesai` periode terkait ditolak dengan pesan spesifik deadline; Perencanaan (permission global) berhasil melakukan aksi yang sama pada tanggal yang sama; Perencanaan tetap ditolak jika mencoba melakukannya setelah `jadwal_tahunan.penutupan` tercapai.

### 5.5 Ajukan Pengukuran — validasi catatan wajib berbasis arah (Backend Controller + React Form)
- **Scope:** Controller action `PengukuranController@ajukan` dan aksi tombol pada komponen React untuk transisi `draft → diajukan`, dengan validasi backend: catatan wajib diisi jika (a) nilai **memburuk menurut `indikator.arah`** dibanding pengukuran berstatus **Disahkan terakhir secara kronologis** untuk indikator yang sama (naik_baik: nilai turun memicu; turun_baik: nilai naik memicu; nilai stagnan **tidak** memicu; pengukuran pertama tanpa pembanding Disahkan tidak wajib), atau (b) `indikator.wajib_catatan = true`. Komponen React memberikan panduan visual terkait kewajiban catatan.
- **Dependency:** 5.3, 5.4.
- **DoD:** Test Pest per arah: indikator `arah=naik_baik` dengan nilai turun dari pembanding Disahkan terakhir tanpa catatan ditolak validasi; indikator `arah=turun_baik` dengan nilai **naik** dari pembanding Disahkan terakhir tanpa catatan ditolak validasi; nilai stagnan tanpa catatan diterima; pengukuran pertama indikator tanpa catatan diterima; indikator `wajib_catatan=true` tanpa catatan ditolak meski nilai membaik; pengajuan valid mengubah `status_alur` menjadi `diajukan` dan tercatat audit_log; pengajuan oleh PIC setelah deadline periode ditolak; test Vitest menguji interaksi tombol ajukan dan penandaan field catatan wajib di React.

### 5.6 Verifikasi oleh Perencanaan (Backend Controller + React Action)
- **Scope:** Controller action `PengukuranController@verifikasi` dan tombol aksi pada antrean reviu di React untuk transisi `diajukan → diverifikasi` oleh pemegang `pengukuran:verifikasi`.
- **Dependency:** 5.5.
- **DoD:** User dengan permission `pengukuran:verifikasi` berhasil mengubah status via controller; user tanpa permission tsb mendapat 403; transisi tercatat audit_log; test Vitest mengonfirmasi tombol verifikasi memicu Inertia visit dan memperbarui status antrean di React.

### 5.7 Kembalikan dengan alasan (pra-pengesahan via Modal React)
- **Scope:** Controller action `PengukuranController@kembalikan` dan modal input alasan di React untuk transisi `diajukan atau diverifikasi → dikembalikan`, mewajibkan pengisian alasan; notifikasi/alert kontekstual ke penanggung jawab terkait.
- **Dependency:** 5.6.
- **DoD:** Submit pengembalian tanpa alasan ditolak validasi controller; pengembalian valid mengubah status menjadi `dikembalikan`, tercatat audit_log dengan `alasan` terisi, dan data menjadi dapat diedit kembali oleh PIC/Perencanaan; test Pest & Vitest memverifikasi alur pengembalian.

### 5.8 Revisi pasca-dikembalikan
- **Scope:** Memastikan pengukuran berstatus `dikembalikan` dapat diedit ulang via React form oleh pemegang scope yang sesuai (PIC atau Perencanaan) dan diajukan kembali ke controller (transisi kembali memakai alur 5.5).
- **Dependency:** 5.7, 5.5.
- **DoD:** Test Pest: pengukuran berstatus `dikembalikan` dapat diubah nilainya via controller oleh pemegang scope yang sesuai, lalu diajukan ulang, dan validasi 5.5 dan 5.4 tetap berlaku pada pengajuan ulang ini.

### 5.9 Sahkan oleh Perencanaan (tanpa approval Pimpinan)
- **Scope:** Controller action `PengukuranController@sahkan` dan tombol aksi di React untuk transisi `diverifikasi → disahkan` oleh pemegang `pengukuran:sahkan`, tanpa syarat tambahan approval Pimpinan pada Fase Awal.
- **Dependency:** 5.6.
- **DoD:** Test Pest: transisi berhasil tanpa memerlukan baris apapun terkait `pengukuran:setujui`; user hanya dengan `pengukuran:verifikasi` (tanpa `pengukuran:sahkan`) mendapat 403 saat mencoba mengesahkan; transisi tercatat audit_log; test Vitest mengonfirmasi state lokal antrean React terupdate setelah pengesahan berhasil.

### 5.10 Buka-kembali Pengukuran Disahkan (`pengukuran:buka_kembali`)
- **Scope:** Controller action `PengukuranController@bukaKembali` dan modal alasan di React untuk transisi `disahkan → dikembalikan` oleh pemegang `pengukuran:buka_kembali` (Perencanaan/Superadmin), mewajibkan alasan, **hanya tersedia** selama `jadwal_tahunan` terkait belum mencapai `penutupan`. Setelah baris kembali ke `dikembalikan`, mengikuti alur revisi biasa (5.8).
- **Dependency:** 5.9, 3.9 (status penutupan jadwal).
- **DoD:** Test Pest: buka-kembali pada pengukuran Disahkan yang jadwalnya belum penutupan berhasil mengubah status ke `dikembalikan` dan tercatat audit_log dengan alasan; percobaan pada jadwal yang sudah `penutupan` ditolak dengan pesan spesifik yang mengarahkan ke `jadwal:buka_kembali` sebagai jalur alternatif; percobaan tanpa alasan ditolak; user tanpa `pengukuran:buka_kembali` mendapat 403; test Vitest menguji modal buka-kembali pengukuran di React.

### 5.11 Larangan penghapusan permanen data bermakna
- **Scope:** Guard backend aplikasi (Policy/Observer) yang menolak operasi delete pada baris `pengukuran` yang memiliki `nilai` atau `catatan` terisi, di seluruh titik masuk (controller maupun pemanggilan model langsung).
- **Dependency:** 5.1.
- **DoD:** Test Pest: percobaan delete pengukuran dengan nilai terisi menghasilkan exception/response 400 ditolak, dan tercatat sebagai audit_log bertindakan "percobaan_hapus_ditolak".

### 5.12 Validasi scope unit pada create/update Pengukuran di Backend
- **Scope:** Menyatukan pengecekan dari 1.7 secara spesifik pada controller create/update pengukuran — memastikan `indikator.unit_id` dicocokkan terhadap scope permission user yang login (kecuali pemegang permission global).
- **Dependency:** 5.2, 5.3, 1.7.
- **DoD:** Test Pest end-to-end: user dengan scope unit A gagal (403) membuat/mengubah pengukuran indikator milik unit B via controller, berhasil untuk indikator milik unit A; user dengan permission global (Perencanaan) berhasil untuk indikator milik unit apa pun.

---

## Modul 6 — Reviu & Pengesahan

### 6.1 Dashboard kerja Perencanaan — antrean Diajukan (Inertia React Page + Controller)
- **Scope:** Halaman React (`Pages/Reviu/Diajukan.tsx`) dan controller `ReviuController@diajukan` menampilkan daftar pengukuran berstatus `diajukan` yang menunggu tindakan Perencanaan, dilengkapi kontrol filter Renstra/Tahun/Periode/Unit.
- **Dependency:** 5.5, 1.7.
- **DoD:** Halaman React menampilkan hanya baris berstatus `diajukan`; filter pada controller berfungsi dan mengurangi/menambah hasil sesuai kriteria; akses endpoint ini memerlukan `pengukuran:verifikasi` atau lebih tinggi (diuji via Pest `$response->assertInertia(...)`); test Vitest menguji render tabel antrean dan interaksi filter di frontend.

### 6.2 Dashboard kerja Perencanaan — antrean Diverifikasi / Siap Sahkan (Inertia React Page + Controller)
- **Scope:** Komponen React (`Pages/Reviu/Diverifikasi.tsx`) dan controller action menampilkan daftar pengukuran berstatus `diverifikasi` yang siap disahkan. Aksi "Sahkan" memanggil controller action 5.9 dan memperbarui tampilan Inertia tanpa reload penuh.
- **Dependency:** 5.6, 6.1.
- **DoD:** Halaman menampilkan hanya baris berstatus `diverifikasi`; aksi "Sahkan" memicu controller dan memperbarui antrean; test Pest menguji controller dan mutasi status; test Vitest menguji interaksi tombol dan rendering antrean React.

### 6.3 Dashboard kerja Perencanaan — antrean Disahkan / Buka-Kembali (Inertia React Page + Controller)
- **Scope:** Komponen React (`Pages/Reviu/Disahkan.tsx`) dan controller action menampilkan daftar pengukuran berstatus `disahkan` yang jadwalnya belum `penutupan`, dengan aksi "Buka Kembali" yang memicu modal input alasan dan memanggil action 5.10.
- **Dependency:** 5.10, 6.2.
- **DoD:** Halaman hanya menampilkan baris `disahkan` pada jadwal yang belum penutupan; submit alasan kosong pada modal ditolak validasi; submit valid memanggil controller, memindahkan data kembali ke antrean Diajukan/Dikembalikan, dan tercatat di audit log (diuji Pest & Vitest).

### 6.4 Form Status Capaian manual (Inertia React Form + Controller)
- **Scope:** Komponen form React modal/inline (`useForm`) dan controller action `PengukuranController@setStatusCapaian` bagi Perencanaan/Superadmin untuk menetapkan `status_capaian` (tercapai/belum_tercapai) pada pengukuran berstatus `disahkan` yang belum memiliki status capaian aktif.
- **Dependency:** 5.9, 1.7.
- **DoD:** Submit form menghasilkan baris baru `status_capaian` dengan `sumber = manual` dan `ditetapkan_oleh` terisi user yang login; pengukuran yang sudah punya status capaian aktif tidak muncul lagi di daftar "belum ditetapkan"; test Pest menguji controller action; test Vitest menguji interaksi pilihan status capaian di React.

### 6.5 Revisi Status Capaian (soft replace via React + Controller)
- **Scope:** Aksi mengubah status capaian yang sudah ada via controller — menambah baris baru sebagai status aktif terbaru, bukan menimpa baris lama.
- **Dependency:** 6.4.
- **DoD:** Test Pest: setelah revisi, query "status capaian aktif" (baris `created_at` terbaru per pengukuran_id) mengembalikan baris baru; baris lama tetap ada di tabel dan dapat ditelusuri sebagai riwayat; audit_log mencatat perubahan; test Vitest menguji komponen revisi status capaian.

### 6.6 Migrasi & model `status_capaian`
- **Scope:** Migrasi tabel sesuai data model, termasuk kolom `sumber` enum (manual, data_sumber) dan `ditetapkan_oleh` nullable.
- **Dependency:** 5.1.
- **DoD:** Migrasi berjalan; constraint aplikasi memastikan `ditetapkan_oleh` selalu terisi ketika `sumber = manual` (divalidasi di layer backend/FormRequest, diuji Pest).

---

## Modul 7 — Dashboard & Visualisasi Kinerja

### 7.1 Migrasi/index pendukung query dashboard
- **Scope:** Tambahkan index database yang diperlukan untuk query agregasi dashboard (mis. index pada `pengukuran(status_alur)`, `status_capaian(pengukuran_id, created_at)`).
- **Dependency:** 5.1, 6.6.
- **DoD:** `EXPLAIN ANALYZE` pada query ringkasan dashboard menunjukkan penggunaan index yang relevan (bukan sequential scan penuh pada tabel besar) di lingkungan uji dengan data seed memadai.

### 7.2 Komponen ringkasan status capaian berbasis indikator × periode (Inertia React + Controller)
- **Scope:** Komponen React (`Pages/Dashboard/Index.tsx`) dan controller `DashboardController@index` yang menghitung dan mem-passing props ringkasan jumlah indikator per kategori: Tercapai, Belum Tercapai, Belum Ditetapkan, Belum Mengisi, Tidak Mengisi — dihitung per kombinasi indikator × periode yang diharapkan (dari `jadwal_periode`), bukan per tahun secara agregat. Indikator berstatus `arsip` dikecualikan dari perhitungan kewajiban pengisian.
- **Dependency:** 6.6, 5.1, 3.7, 3.3.
- **DoD:** Test Pest memverifikasi props Inertia dengan data seed mencakup kelima kondisi (termasuk kasus indikator arsip yang tidak muncul sebagai "Belum mengisi"/"Tidak mengisi") menghasilkan angka yang tepat sesuai definisi masing-masing kategori via `$response->assertInertia(...)`; test Vitest memverifikasi 4 stat card dan kartu ringkasan dirender akurat di React.

### 7.3 Grafik ApexCharts target vs realisasi (React Component via Inertia Props)
- **Scope:** Integrasi grafik ApexCharts (menggunakan `react-apexcharts`) pada dashboard React yang menerima props data deret target (dari snapshot) vs realisasi (nilai pengukuran disahkan) per indikator/periode dari `DashboardController`.
- **Dependency:** 7.2, 3.7.
- **DoD:** Grafik berhasil dirender di browser melalui komponen React; test Pest memverifikasi props Inertia memuat struktur series dan kategori yang tepat; test Vitest mengonfirmasi komponen chart React merender container chart dan memproses data props target vs realisasi tanpa runtime error.

### 7.4 Filter dashboard reaktif (Inertia Partial Reloads / Visits)
- **Scope:** Kontrol filter reaktif (Renstra, Tahun, Periode, Sasaran, Unit) menggunakan `router.get()` bawaan `@inertiajs/react` dengan opsi `preserveState: true` dan `preserveScroll: true` untuk memperbarui komponen ringkasan (7.2) serta grafik (7.3) secara asinkron tanpa reload halaman penuh.
- **Dependency:** 7.2, 7.3.
- **DoD:** Test Pest memverifikasi filter query string pada controller dashboard (`/dashboard?unit_id=...&periode_id=...`) menghasilkan props yang sesuai; test Vitest memverifikasi interaksi perubahan dropdown filter di React memicu pemanggilan Inertia visit dan me-render ulang data ringkasan.

### 7.5 Akses dashboard oleh seluruh role (termasuk Pegawai dan Admin)
- **Scope:** Memastikan permission `dashboard:read` yang dimiliki seluruh preset role (Superadmin, Admin, Perencanaan, Pimpinan, Pegawai) benar-benar memberi akses baca rute dashboard dan halaman dashboard di React.
- **Dependency:** 7.4, 1.7.
- **DoD:** Test Pest: user dengan role preset Pegawai (tanpa permission tambahan apapun) dan user dengan role preset Admin menerima HTTP response 200 OK pada rute dashboard Inertia; test Vitest mengonfirmasi halaman dashboard dapat diakses dan ditampilkan normal untuk seluruh role tersebut.

---

## Modul 8 — Laporan & Ekspor

### 8.1 Halaman laporan tabular terfilter (Inertia React Page + Controller)
- **Scope:** Halaman React (`Pages/Laporan/Index.tsx`) dan controller `LaporanController@index` menampilkan tabel pengukuran dengan filter komprehensif: Renstra/Tahun/Periode/Sasaran/Unit/Status; permission backend `laporan:read`.
- **Dependency:** 5.1, 1.7.
- **DoD:** Tabel React menampilkan kolom minimal: nama indikator, unit, tahun, periode, nilai, status_alur, status_capaian; filter pada controller berfungsi memfilter props data; user dengan role Admin (memiliki `laporan:read`) dapat mengakses rute ini; diuji dengan Pest untuk `$response->assertInertia(...)` dan Vitest untuk komponen tabel React.

### 8.2 Ekspor tabel laporan ke Excel
- **Scope:** Implementasi ekspor hasil laporan terfilter (8.1) ke berkas `.xlsx` via `maatwebsite/excel` di Laravel backend, memuat data sesuai parameter filter aktif; permission `laporan:ekspor`. Tombol ekspor di frontend React mengarahkan unduhan berkas melalui link browser biasa.
- **Dependency:** 8.1.
- **DoD:** Berkas hasil ekspor dapat diunduh, dibuka, dan divalidasi struktur (mis. via PHPSpreadsheet reader di test Pest) berisi jumlah baris yang sama dengan hasil filter; user tanpa `laporan:ekspor` mendapat 403 saat memanggil rute ekspor meski memiliki `laporan:read` (termasuk pengujian eksplisit dengan user berrole preset Admin).

---

## Modul 9 — Setelan Aplikasi

### 9.1 Migrasi & model `pengaturan`
- **Scope:** Migrasi tabel key-value `pengaturan`: `kunci` (unique), `nilai`, `tipe`, `grup`, `updated_by`, `updated_at`.
- **Dependency:** 1.3 (FK updated_by).
- **DoD:** Migrasi berjalan; constraint unique pada `kunci` ditegakkan (percobaan insert kunci duplikat ditolak, diuji Pest); model dapat dibuat/dibaca sesuai struktur.

### 9.2 Seeder nilai default `pengaturan`
- **Scope:** Seeder yang mengisi kunci awal saat instalasi: identitas instansi (`instansi.nama`, `instansi.alamat`, `instansi.telepon`, `instansi.surel`, `instansi.laman`, `instansi.logo`), identitas aplikasi (`aplikasi.nama`, `aplikasi.label_unit`), preferensi tampilan/laporan (`preferensi.zona_waktu`, `preferensi.format_tanggal`, `preferensi.format_angka`, `preferensi.header_ekspor`, `preferensi.footer_ekspor`) — masing-masing dengan `tipe` dan `grup` yang sesuai.
- **Dependency:** 9.1.
- **DoD:** `php artisan migrate:fresh --seed` menghasilkan seluruh kunci di atas dengan `nilai` default terisi (bukan NULL) dan `grup` terisi sesuai pengelompokan; jumlah baris `pengaturan` setelah seed sama dengan jumlah kunci yang didefinisikan di seeder.

### 9.3 Accessor pengaturan dengan cache
- **Scope:** Service/helper (mis. `Pengaturan::get('kunci', $default)`) yang membaca nilai dari tabel `pengaturan`, dengan layer cache (Laravel Cache) agar pembacaan berulang saat response Inertia tidak membebani database; cache diinvalidasi otomatis saat nilai diperbarui.
- **Dependency:** 9.2.
- **DoD:** Test Pest: pemanggilan `Pengaturan::get('aplikasi.label_unit')` berulang menghasilkan nilai yang benar; setelah nilai diubah lewat 9.4, pemanggilan berikutnya mengembalikan nilai baru, bukan nilai cache lama.

### 9.4 Form Setelan Aplikasi (Inertia React Form + Controller)
- **Scope:** Halaman React (`Pages/Pengaturan/Index.tsx`) terkelompok per tab/grup (`instansi`, `aplikasi`, `preferensi`) menggunakan `useForm` dan controller action `PengaturanController@update` untuk mengubah nilai `pengaturan`; permission `pengaturan:update`; validasi backend whitelist kunci (hanya kunci yang telah didefinisikan di 9.2 yang dapat diubah, kunci lain ditolak); setiap perubahan menginvalidasi cache accessor (9.3).
- **Dependency:** 9.3, 1.7.
- **DoD:** Submit perubahan pada kunci valid berhasil memperbarui `nilai`, `updated_by`, `updated_at`; submit dengan kunci di luar whitelist ditolak validasi; user dengan role Superadmin atau Admin berhasil mengakses dan menyimpan form ini; user dengan role Perencanaan/Pimpinan/Pegawai mendapat 403; test Vitest menguji form tabs setelan di React.

### 9.5 Audit perubahan Setelan Aplikasi
- **Scope:** Setiap perubahan nilai lewat controller 9.4 tercatat di `audit_log` per kunci yang berubah, dengan `nilai_lama`/`nilai_baru` berisi nilai kunci tersebut sebelum dan sesudah perubahan.
- **Dependency:** 9.4, 10.1.
- **DoD:** Test Pest: mengubah 2 kunci sekaligus dalam satu submit controller menghasilkan 2 baris `audit_log` terpisah, masing-masing dengan `objek_tipe = pengaturan`, `objek_id`/pengenal kunci yang sesuai, dan `nilai_lama`/`nilai_baru` yang benar.

### 9.6 Guard cakupan: larangan mengubah enum/status/aturan bisnis lewat `pengaturan`
- **Scope:** Tinjauan kode backend dan test regresi yang memastikan tabel `pengaturan` dan form 9.4 tidak dipakai sebagai jalur untuk mengubah nilai enum (status Renstra/Jadwal/Pengukuran, dsb), nama permission, atau aturan bisnis apa pun — seluruh whitelist kunci di 9.2 terbatas pada teks dan preferensi presentasional.
- **Dependency:** 9.4.
- **DoD:** Test Pest/statis: seluruh kunci pada whitelist 9.2 diverifikasi bertipe teks/tanggal-format/angka-format/URL, tidak ada satu pun kunci yang membaca/menulis ke kolom enum tabel lain; tidak ada parameter di form 9.4 yang menerima parameter di luar daftar kunci whitelist.

---

## Modul 10 — Audit & Histori

### 10.1 Infrastruktur dasar pencatatan Audit Log
- **Scope:** Migrasi tabel `audit_log` dan service/helper terpusat (`AuditLogger::catat(...)`) di backend Laravel yang dipanggil oleh seluruh controller/service lain untuk mencatat peristiwa; tanpa rute update/delete (hanya append-only).
- **Dependency:** 1.3 (users, untuk FK actor_id).
- **DoD:** Memanggil `AuditLogger::catat(...)` dari test Pest menghasilkan baris baru di `audit_log` dengan seluruh kolom wajib terisi; tidak ada rute untuk mengubah/menghapus baris `audit_log` (status 404/405).

### 10.2 Validasi alasan wajib pada tindakan sensitif
- **Scope:** Lapisan validasi backend terpusat yang menegakkan kewajiban field `alasan` pada pemanggilan `AuditLogger::catat(...)` untuk daftar tindakan sensitif: koreksi PK, pengembalian pengukuran (baik `pengukuran:kembalikan` maupun `pengukuran:buka_kembali`), pergantian penanggung jawab, penghapusan unit, `jadwal:buka_kembali`, revisi Renstra in place, dan tindakan sensitif lain yang relevan.
- **Dependency:** 10.1.
- **DoD:** Test Pest: memanggil `AuditLogger::catat(...)` untuk tindakan dalam daftar sensitif tanpa `alasan` melempar exception/ditolak sebelum baris tersimpan; tindakan di luar daftar tetap bisa disimpan tanpa alasan.

### 10.3 Halaman pencarian & tampilan Audit Log (Inertia React Page + Controller)
- **Scope:** Halaman React (`Pages/Audit/Index.tsx`) dan controller `AuditController@index` untuk mencari/memfilter `audit_log` berdasarkan actor, tindakan, objek_tipe, rentang waktu; permission `audit:read`.
- **Dependency:** 10.1.
- **DoD:** Filter berdasarkan `objek_tipe = renstra` hanya menampilkan baris terkait Renstra; user tanpa `audit:read` (mis. Pegawai) mendapat 403 saat mengakses rute ini; user dengan role preset Admin berhasil mengakses; test Vitest menguji komponen tabel audit log di React.

### 10.4 Tampilan detail perubahan — nilai_lama vs nilai_baru (React Component)
- **Scope:** Komponen React dialog/drawer (`Components/Audit/DetailDrawer.tsx`) yang merender perbandingan JSON `nilai_lama` vs `nilai_baru` secara human-readable (tabel dua kolom per field yang berubah).
- **Dependency:** 10.3.
- **DoD:** Untuk baris audit dengan `nilai_lama`/`nilai_baru` terisi, komponen React menampilkan perbandingan field yang berbeda secara jelas (diverifikasi manual terhadap minimal 4 jenis tindakan berbeda: perubahan Renstra in place, perubahan status pengukuran, perpindahan unit indikator, perubahan setelan aplikasi).

### 10.5 Audit untuk percobaan tindakan yang ditolak
- **Scope:** Memastikan seluruh titik penolakan sistem yang ditegakkan eksplisit di backend (percobaan hapus data bermakna, percobaan aktivasi gagal salah satu dari tiga gerbang validasi, percobaan create pengukuran untuk indikator arsip, dsb) juga menghasilkan baris audit log bertindakan "ditolak"/"percobaan", bukan hanya melempar exception tanpa jejak.
- **Dependency:** 10.1, 2.3, 3.5, 5.11, 2.9.
- **DoD:** Test Pest lintas modul: masing-masing dari kelima skenario penolakan di atas (termasuk ketiga gerbang aktivasi jadwal secara terpisah) menghasilkan baris audit_log baru dengan `tindakan` yang mengindikasikan kegagalan/percobaan.

---

## Seed Data Pengembangan/Testing

### S.1 Seeder Laravel — data minimal validasi model
- **Scope:** Seeder (`DatabaseSeeder` + seeder khusus) yang membuat 1 Renstra contoh (dengan dasar_hukum terisi), beberapa Sasaran, beberapa Indikator (lintas ≥2 unit, dengan variasi `arah` naik_baik dan turun_baik), Target Tahunan untuk tahun berjalan, 1 renstra_pk, 1 Jadwal Tahunan (hingga status aktif dengan `jadwal_periode` tersusun dan snapshot terbentuk), beberapa user dengan **kelima** role preset (superadmin, admin, perencanaan, pimpinan, pegawai). **Ini murni untuk pengembangan/testing, bukan data produksi** — data riil dimasukkan manual oleh Perencanaan setelah aplikasi live.
- **Dependency:** Seluruh migrasi Modul 1–6 dan Modul 9 selesai (2.1–2.13, 3.1–3.10, 4.1, 5.1, 6.6, 9.1–9.2).
- **DoD:** `php artisan migrate:fresh --seed` berjalan tanpa error dan menghasilkan: ≥1 Renstra berstatus aktif, ≥1 jadwal_tahunan berstatus aktif dengan ≥1 baris `jadwal_periode` dan `jadwal_snapshot` terbentuk sejumlah indikator seed, ≥1 user per **kelima** role preset (superadmin, admin, perencanaan, pimpinan, pegawai) yang dapat dipakai untuk login uji manual via frontend React Inertia, dan seluruh kunci `pengaturan` default (9.2) sudah terisi.

---

## Ringkasan Urutan Eksekusi Modul

| Urutan | Modul | Komponen & Pendekatan Teknis | Alasan Urutan |
|---|---|---|---|
| 1 | Autentikasi & Akses | Laravel Controller + Inertia Layout + Keycloak SSO + HandleInertiaRequests | Fondasi wajib — seluruh modul butuh gate akses backend, sharing auth props, & audit dasar |
| 2 | Master Renstra | Inertia Controller + React Pages (Renstra, Sasaran, IKU, Target, PK) | Data dasar yang dirujuk seluruh modul berikutnya; indikator membawa `unit_id` dan `arah` sejak awal |
| 3 | Periode & Jadwal | Laravel Schedule Engine + React Period Manager (`jadwal_periode`) | Menghasilkan `jadwal_periode` dan snapshot idempoten yang dipakai Pengukuran |
| 4 | Penugasan | Laravel PJ Service + React Modal Assignment | Menetapkan PIC per Indikator sebelum masa pengisian dimulai |
| 5 | Pengukuran | Laravel Workflow Service + React Form (`useForm`) & Upload Bukti | Jantung aplikasi SAKIP; deadline jendela periode & buka-kembali dua lapis berlaku di sini |
| 6 | Reviu & Pengesahan | Laravel Review Controller + React Verification Queues | Alur verifikasi teknis dan pengesahan pengukuran serta status capaian manual |
| 7 | Dashboard & Visualisasi | Laravel Dashboard Controller + React ApexCharts Components | Visualisasi metrik capaian IKU per indikator × periode via props Inertia |
| 8 | Laporan & Ekspor | Laravel Excel Export Engine + React Report Table | Pelaporan tabular dan unduhan berkas spreadsheet |
| 9 | Setelan Aplikasi | Laravel Key-Value Settings + React Settings Form (`useForm`) | Pengaturan identitas instansi dan preferensi presentasional |
| 10 | Audit & Histori | Laravel Audit Logger (Append-Only) + React Audit Viewer Drawer | Infrastruktur dasar dibangun sejak Modul 1; UI pencarian dituntaskan setelah modul inti |
| — | Seed Data | DatabaseSeeder + Mock Kinerja | Validasi end-to-end integrasi Laravel dan React Inertia |

---

## 11. Fase Lanjutan (Belum Termasuk MVP)

Daftar cakupan yang secara sengaja tidak dibangun pada Fase Awal, dicantumkan sebagai catatan cakupan masa depan tanpa breakdown granular:

- **Approval Pimpinan** dalam alur pengesahan pengukuran — mengaktifkan pemakaian kolom `jadwal_tahunan.pakai_persetujuan_pimpinan`, `persetujuan_mulai`, `persetujuan_selesai`, serta permission `pengukuran:setujui` dan peran aktif Pimpinan dalam state machine status alur.
- **Ekspor PDF** untuk rekap laporan formal (mis. lampiran LKj).
- **Ekspor gambar grafik** dashboard (mis. PNG/SVG dari ApexCharts di React) untuk disisipkan ke dokumen/presentasi.
- **Integrasi status capaian otomatis** dari sistem sumber data eksternal — mengaktifkan pemakaian `status_capaian.sumber = data_sumber` dan jalur `ditetapkan_oleh = NULL`, termasuk desain integrasi/job/API yang relevan.
- **UI matrix permission penuh di React** — antarmuka yang menampilkan dan memungkinkan pencentangan bebas seluruh permission katalog per pengguna, menggantikan kebutuhan seeder/query manual untuk kasus edge di luar preset form Fase Awal.
- **Impor data massal** dari sumber eksternal (Excel/sistem lain) — belum diputuskan masuk fase mana pun; memerlukan keputusan produk tersendiri jika dibutuhkan di masa depan.
- **Revisi target di tengah tahun** — Fase Awal hanya mendukung revisi target antar-tahun (mengubah master `target_tahunan` sebelum jadwal tahun tersebut diaktifkan); mekanisme revisi target pada tahun yang snapshot-nya sudah terbentuk tetap memakai jalur `jadwal:buka_kembali` beserta koreksi manual snapshot yang ter-audit, bukan fitur bertingkat tersendiri.

---

*Dokumen ini adalah rencana pengembangan granular untuk Fase Awal (MVP) SAKIP LLDIKTI Wilayah XVI dengan arsitektur The Modern Monolith: Laravel + Inertia.js + React + TypeScript + PostgreSQL 17.*
