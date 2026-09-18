# PLAN — Rencana Pengembangan SAKIP LLDIKTI Wilayah XVI

Setiap task memiliki tiga bagian wajib:
- **Scope** — apa yang dikerjakan.
- **Dependency** — task/modul lain yang harus selesai lebih dulu, atau "tidak ada".
- **Definition of Done (DoD)** — kriteria verifikasi mandiri yang konkret dan dapat dicek tanpa bertanya siapa yang mengerjakan.

---

## Baseline keputusan, penerimaan, dan parameter operasional

Dokumen ini mengikuti keputusan grill yang disetujui pengguna serta keputusan Q4 PM pada branch development. PM bertanggung jawab atas baseline scope, perubahan keputusan dan decision log; Tim Perencanaan memvalidasi definisi indikator/data awal/format laporan serta UAT; tim teknis bertanggung jawab atas implementasi dan bukti verifikasi. Nama penanggung jawab harus ditetapkan PM, tidak diwariskan dari proyek lain.

- Integrasi WhatsApp dan Email dituntaskan sebelum 9 November 2026 sesuai Q4; evaluasi dijadwalkan 16–30 November 2026. Siap UAT, pelaksanaan evaluasi, penerimaan UAT, dan izin production adalah milestone terpisah. Tanggal 9 November bukan otomatis tanggal go-live.
- Tahun operasional pertama, cakupan periode historis, daftar PIC, sumber kontak, data awal, dan contoh ekspor yang disetujui belum boleh ditebak; Perencanaan menyediakan, PM mencatat keputusan.
- IKU 8 belum boleh dipakai produksi sampai Perencanaan menegaskan pembilang, penyebut, populasi, satuan dan aturan penghitungan ganda. Fixture aritmetika tidak mengesahkan definisi.
- Baseline IKU 3 74,2 berasal dari SAKIP saja pada referensi Excel; kesetaraannya dengan indikator gabungan serta pemetaan target/realisasi TW II masih harus dikonfirmasi. Lima input perhitungan IKU 3 sudah menjadi keputusan implementasi, terpisah dari pengesahan angka operasional tersebut.
- Penugasan owner/dependency operasional mengikuti P.4. Tidak ada perubahan dokumen ini yang menyatakan aplikasi sudah memenuhi DoD atau lulus UAT.

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

### 1.3 Migrasi & model `users` dengan pemetaan `keycloak_id`
- **Scope:** Buat migrasi tabel `users` (id uuid, keycloak_id unique, nama, email) dan model Eloquent terkait; logic pada callback OIDC untuk `firstOrCreate` berdasarkan `keycloak_id`, sinkronisasi `nama`/`email` dari klaim token setiap login.
- **Dependency:** 1.2.
- **DoD:** Login pengguna baru menghasilkan baris baru di tabel `users` dengan `keycloak_id` terisi; login ulang pengguna yang sama tidak membuat baris duplikat (constraint unique `keycloak_id` ditegakkan, dibuktikan test Pest yang memanggil proses login dua kali dengan klaim identik dan menghitung jumlah baris tetap 1).

### 1.4 Migrasi `unit`
- **Scope:** Buat migrasi dan model `unit` (id, nama, status enum aktif/nonaktif, created_by, created_at). Nama tabel dan seluruh referensi kode memakai `unit` — bukan `tim_kerja` — sejak migrasi pertama.
- **Dependency:** 1.3 (butuh `users` untuk FK `created_by`).
- **DoD:** Migrasi berjalan tanpa error; model `Unit` dapat membuat baris baru via tinker/test dengan status default `aktif`; pencarian string `tim_kerja` di seluruh basis kode (migrasi, model, factory) mengembalikan nol hasil.

### 1.5 Migrasi & model `permissions` — katalog permission
- **Scope:** Buat migrasi dan model `permissions`: `id` (uuid, PK), `kode` (varchar, unique, not null — format `entitas:aksi`), `entitas` (varchar, not null), `aksi` (varchar, not null), `keterangan` (text, nullable), `butuh_scope` enum(`global`,`unit`) not null default `global`, `sensitif` (boolean, not null, default `false`), `aktif` (boolean, not null, default `true`), `created_at`, `updated_at`.
- **Dependency:** 1.3.
- **DoD:** Migrasi berjalan tanpa error; percobaan insert dua baris dengan `kode` sama ditolak database (unique constraint); model dapat dibuat via factory/test dengan `butuh_scope` default `global` dan `sensitif` default `false`.

### 1.6 Migrasi & model `roles` dan `role_permissions`
- **Scope:** Buat migrasi tabel `roles` (`id` uuid PK, `kode` varchar unique — `superadmin`/`admin`/`perencanaan`/`pimpinan`/`pegawai`, `nama` varchar, `keterangan` text nullable, `is_sistem` boolean default `true`, `urutan` int, `aktif` boolean default `true`) dan tabel `role_permissions` (`id` uuid PK, `role_id` FK → roles.id, `permission_id` FK → permissions.id, `created_at`), dengan constraint `unique(role_id, permission_id)`. Tabel `role_permissions` **tidak** memiliki kolom `unit_id` — permission hasil peran selalu bersifat global.
- **Dependency:** 1.5.
- **DoD:** Migrasi berjalan; kelima kode peran (`superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`) dapat disimpan sebagai baris `roles`; percobaan insert baris `role_permissions` duplikat (kombinasi role_id+permission_id sama) ditolak database dengan unique constraint violation, dibuktikan test Pest; skema tabel `role_permissions` diverifikasi tidak memiliki kolom `unit_id` (introspeksi skema di test Pest).

### 1.7 Migrasi & model `user_roles`
- **Scope:** Buat migrasi tabel `user_roles`: `id` (uuid, PK), `user_id` (FK → users.id), `role_id` (FK → roles.id), `diberikan_oleh` (FK → users.id), `created_at`; constraint `unique(user_id)` — pada Fase Awal satu pengguna memegang tepat satu peran (struktur pivot disiapkan agar multi-peran dapat dibuka di Fase Lanjutan hanya dengan melepas constraint ini).
- **Dependency:** 1.6, 1.3.
- **DoD:** Migrasi berjalan; percobaan insert baris `user_roles` kedua untuk `user_id` yang sama ditolak database (unique constraint), dibuktikan test Pest yang menangkap exception tersebut; baris dapat dibuat dengan `diberikan_oleh` terisi user lain.

### 1.8 Migrasi & model `user_permission_granted`
- **Scope:** Buat migrasi tabel `user_permission_granted`: `id` (uuid, PK), `user_id` (FK → users.id), `permission_id` (FK → permissions.id), `unit_id` (FK → unit.id, nullable — `NULL` = global), `alasan` (text, **not null**), `diberikan_oleh` (FK → users.id), `created_at`; constraint `unique(user_id, permission_id, unit_id)` dengan index unik memakai `COALESCE(unit_id, ...)` karena PostgreSQL memperlakukan NULL sebagai nilai berbeda. Validasi aplikasi: `permissions.butuh_scope = unit` → `unit_id` wajib diisi; `butuh_scope = global` → `unit_id` wajib NULL; grant untuk permission bertipe `unit` tanpa `unit_id` ditolak sistem.
- **Dependency:** 1.5, 1.4 (unit).
- **DoD:** Migrasi berjalan; percobaan insert baris duplikat pada kombinasi (user_id, permission_id, unit_id) — termasuk kasus `unit_id` NULL pada baris kedua dengan kombinasi lain sama — ditolak sesuai desain unique index; percobaan submit grant untuk permission `butuh_scope=unit` dengan `unit_id` NULL ditolak validasi aplikasi (test Pest); percobaan submit tanpa `alasan` ditolak validasi.

### 1.9 Migrasi & model `user_permission_denied`
- **Scope:** Buat migrasi tabel `user_permission_denied`: `id` (uuid, PK), `user_id` (FK → users.id), `permission_id` (FK → permissions.id), `unit_id` (FK → unit.id, nullable — `NULL` = pencabutan menyeluruh), `alasan` (text, **not null**), `ditetapkan_oleh` (FK → users.id), `created_at`; constraint `unique(user_id, permission_id, unit_id)` dengan pola `COALESCE` yang sama seperti 1.8. Deny dapat mencabut permission yang berasal dari peran maupun grant, dan berlaku terhadap permission bertipe `global` maupun `unit`.
- **Dependency:** 1.5, 1.4.
- **DoD:** Migrasi berjalan; percobaan insert baris duplikat pada kombinasi (user_id, permission_id, unit_id) ditolak sesuai desain unique index; percobaan submit tanpa `alasan` ditolak validasi; baris dapat dibuat dengan `unit_id` NULL (deny global) maupun terisi (deny ber-unit).

### 1.10 Katalog permission sebagai konstanta aplikasi + seeder tabel `permissions`
- **Scope:** Definisikan seluruh permission (termasuk `unit:create/read/update/delete`, `pengukuran:buka_kembali`, `pengaturan:update`, `rencana_aksi:read/create/update/ajukan/verifikasi/kembalikan/sahkan/buka_kembali`, `kegiatan:read/create/update/delete`, `komponen:create/read/update/delete`, `jenis_berkas:create/read/update/delete`, `regulasi:create/read/update/delete`, `berkas:read/upload/delete`, `rekomendasi:tetapkan`, `akses:update`, `pengguna:read`) sebagai konstanta terpusat (mis. PHP enum/class `Permission`) berikut atribut `butuh_scope` dan `sensitif` masing-masing kode; seeder yang menulis seluruh konstanta ke tabel `permissions` (1.5), idempoten (dapat dijalankan ulang tanpa duplikasi). Penambahan/penghapusan permission hanya lewat rilis kode + seeder ini, bukan lewat UI.
- **Dependency:** 1.5.
- **DoD:** Daftar konstanta memuat seluruh permission dari katalog PRD yang disepakati tanpa kekurangan/kelebihan, termasuk `komponen:create/read/update/delete`, `jenis_berkas:create/read/update/delete`, dan `regulasi:create/read/update/delete`; test Pest membandingkan daftar konstanta dengan daftar permission yang didefinisikan sebagai fixture test dan lulus sama persis; `php artisan db:seed` mengisi tabel `permissions` dengan `butuh_scope=unit` tepat untuk `pengukuran:create`, `pengukuran:update`, `rencana_aksi:create`, `rencana_aksi:update`, `rencana_aksi:ajukan`, `kegiatan:create`, `kegiatan:update`, `rencana_aksi:read`, `kegiatan:read` (sisanya `global`, termasuk seluruh `regulasi:*`), dan `sensitif=true` tepat untuk `pengukuran:sahkan`, `pengukuran:buka_kembali`, `pengukuran:verifikasi`, `rencana_aksi:verifikasi`, `rencana_aksi:sahkan`, `rencana_aksi:buka_kembali`, `jadwal:aktivasi`, `jadwal:tutup`, `jadwal:buka_kembali`, `status_capaian:update`, `rekomendasi:tetapkan`, `komponen:update`, `komponen:delete`, `jenis_berkas:update`, `jenis_berkas:delete`, `regulasi:update`, `regulasi:delete`, `akses:update`, `pengaturan:update`, `berkas:delete`, `kegiatan:delete`; menjalankan seeder dua kali tidak menghasilkan baris duplikat; tidak ada satu pun konstanta bernama `tim_kerja:*`, dan seluruh permission mengikuti bentuk `entitas:aksi` granular (create/read/update/delete atau kata kerja spesifik).

### 1.11 Service resolusi izin (allow/deny + scope + fail closed)
- **Scope:** Implementasi service/Gate Laravel tunggal yang menjadi satu-satunya titik evaluasi izin di backend, menjawab pertanyaan "boleh(kode_permission, unit_target?)" untuk aktor yang login, mengikuti algoritma: (1) fail closed bila `permissions` tidak punya baris aktif dengan kode diminta; (2) susun himpunan allow dari `user_roles → role_permissions` (selalu global) ditambah `user_permission_granted` yang cocok; (3) susun himpunan deny dari `user_permission_denied` yang cocok; (4) pencocokan scope: untuk pertanyaan dengan unit_target U, deny cocok bila `unit_id IS NULL` atau `unit_id = U`, grant cocok bila `unit_id = U`; untuk pertanyaan tanpa unit_target, deny ber-`unit_id` tidak menghalangi, deny `unit_id IS NULL` selalu menghalangi; (5) presedens DENY MENANG — ada deny cocok maka TOLAK, tidak ada deny tapi ada allow cocok maka IZINKAN, tidak ada allow maka TOLAK. Service ini TIDAK menjalankan validasi bisnis (jendela waktu, status alur, kepemilikan unit) — itu lapisan terpisah yang dipanggil setelah service ini mengizinkan.
- **Dependency:** 1.6, 1.7, 1.8, 1.9, 1.10.
- **DoD:** Test Pest — **fail closed**: permission dengan kode tidak terdaftar/tidak aktif di tabel `permissions` selalu menghasilkan tolak, terlepas peran/grant apa pun yang dimiliki user; **allow dari peran**: user dengan peran yang memiliki permission tsb di `role_permissions` diizinkan untuk permission bertipe global tanpa perlu baris grant; **allow dari grant + scope unit**: user tanpa peran relevan tapi punya `user_permission_granted` untuk unit A diizinkan untuk unit A, ditolak untuk unit B; **presedens deny menang**: user dengan permission dari peran DAN ada baris deny yang cocok (global maupun ber-unit yang sama) tetap ditolak; **deny mencabut grant**: user dengan grant di unit A yang di-deny untuk unit A yang sama ditolak, sementara grant miliknya di unit B (tanpa deny) tetap diizinkan; **deny global menghalangi pertanyaan tanpa unit_target**: deny dengan `unit_id IS NULL` menolak permintaan permission global; **deny ber-unit tidak menghalangi pertanyaan tanpa unit_target**: deny dengan `unit_id` tertentu tidak menghalangi permintaan permission global lain di luar konteks unit tsb; service tidak melakukan pengecekan jendela waktu/status alur apa pun (dibuktikan lewat test yang memanggil service langsung tanpa konteks bisnis dan tetap mendapat hasil izin/tolak murni berbasis RBAC).

### 1.12 Seeder isi peran (`role_permissions`) untuk kelima peran bawaan
- **Scope:** Seeder yang mengisi `role_permissions` untuk kelima peran bawaan. **Superadmin** — seluruh permission katalog tanpa kecuali, termasuk `pengaturan:update` dan seluruh permission rencana aksi/kegiatan/komponen/berkas/rekomendasi/regulasi. **Perencanaan** — seluruh permission substantif data kinerja: `renstra:*`, `sasaran:*`, `indikator:*`, `target:update`, `pk:create/update`, `periode:create/update`, `jadwal:*` (termasuk `jadwal:aktivasi`/`tutup`/`buka_kembali`), `penanggung_jawab:update`, `pengukuran:read/create/update/verifikasi/kembalikan/sahkan/buka_kembali` (global, tanpa baris grant tambahan — karena `role_permissions` tidak memiliki kolom `unit_id`), `rencana_aksi:read/create/update/ajukan/verifikasi/kembalikan/sahkan/buka_kembali` (global), `kegiatan:read/create/update/delete` (global), `komponen:create/read/update/delete`, `jenis_berkas:create/read/update/delete`, `regulasi:create/read/update/delete`, `rekomendasi:tetapkan`, `status_capaian:update`, `berkas:read/upload/delete`, `dashboard:read`, `laporan:read/ekspor`, `audit:read`. **Admin** — `pengguna:read`, `akses:update`, `unit:create/read/update/delete`, `pengaturan:update`, `komponen:read`, `jenis_berkas:read`, `regulasi:read`, `audit:read`, `dashboard:read`, `laporan:read` — **tanpa** satu pun permission substantif data kinerja lain (tanpa `renstra:*`, `indikator:*`, `jadwal:*`, `penanggung_jawab:update`, seluruh `rencana_aksi:*`, `kegiatan:*`, `komponen:create/update/delete`, `jenis_berkas:create/update/delete`, `regulasi:create/update/delete`, `pengukuran:*`, `rekomendasi:tetapkan`, `status_capaian:update`, `laporan:ekspor`). **Pimpinan** — `pengukuran:read`, `pengukuran:setujui` (disiapkan untuk Fase Lanjutan, tidak memiliki aksi/alur aktif pada MVP), `rencana_aksi:read`, `kegiatan:read`, `berkas:read`, `komponen:read`, `jenis_berkas:read`, `regulasi:read`, `dashboard:read`, `laporan:read/ekspor`, `audit:read`. **Pegawai** — `pengukuran:read`, `komponen:read`, `jenis_berkas:read`, `regulasi:read`, `dashboard:read`; permission substantif (`pengukuran:create/update`, `rencana_aksi:create/update/ajukan`, `kegiatan:create/update`) TIDAK termasuk peran ini — diberikan eksplisit lewat grant (1.14) per unit, termasuk rencana_aksi:read dan kegiatan:read. Permission berkas tetap global di katalog; capability PIC diturunkan dari izin baca/mutasi induk tanpa grant berkas khusus, dengan deny berkas/induk dan imutabilitas tetap berlaku. Daftar Admin adalah preset awal, bukan larangan permanen: grant tambahan yang sah tetap dievaluasi oleh resolver bersama deny dan seluruh guard bisnis.
- **Dependency:** 1.10, 1.11, 1.6.
- **DoD:** Test Pest per peran: seeder `role_permissions` untuk `superadmin` mencakup seluruh permission katalog termasuk seluruh permission rencana aksi/kegiatan/komponen/berkas/regulasi; `perencanaan` mencakup daftar permission substantif di atas secara lengkap (termasuk keempat aksi `regulasi:*`) DAN sama sekali tidak memiliki grant di `user_permission_granted` untuk mendapatkan sifat globalnya (dibuktikan lewat pengecekan langsung `role_permissions` tanpa join ke `user_permission_granted`); `admin` menghasilkan baris permission yang **cocok persis** dengan daftar administratif (termasuk `komponen:read`, `jenis_berkas:read`, `regulasi:read`) dan nol baris untuk seluruh permission substantif yang disebut (termasuk nol baris `regulasi:create/update/delete`); `pimpinan` dan `pegawai` masing-masing cocok persis dengan preset di atas (termasuk `regulasi:read`); `pengukuran:setujui` ada pada preset Pimpinan sebagai permission inert Fase Lanjutan, tanpa route/transisi approval Pimpinan aktif pada MVP; menjalankan seeder dua kali tidak menghasilkan baris `role_permissions` duplikat.

### 1.13 UI Form 1 — Assign Peran
- **Scope:** halaman Inertia + komponen React untuk pemegang `akses:update` (Superadmin/Admin) memilih pengguna, menetapkan SATU peran (Superadmin/Admin/Perencanaan/Pimpinan/Pegawai), dan mengisi alasan penetapan/pergantian; submit melakukan INSERT/UPDATE `user_roles` (unique per user_id — Fase Awal satu peran per pengguna) dengan `diberikan_oleh` terisi; mencatat `audit_log` dengan `nilai_lama`/`nilai_baru` peran bila ini pergantian.
- **Dependency:** 1.7, 1.12, Modul 10 (audit dasar — lihat 10.1).
- **DoD:** Melalui UI, pemegang `akses:update` (Superadmin atau Admin) dapat memilih user dan peran, submit berhasil menyimpan/mengubah baris `user_roles`; pergantian peran pada user yang sudah punya peran sebelumnya menghasilkan baris `audit_log` dengan `nilai_lama` (peran lama) dan `nilai_baru` (peran baru) terisi benar, dan `alasan` tersimpan; penetapan pertama (user belum punya peran) tidak mewajibkan `nilai_lama`; akses form ini oleh user tanpa `akses:update` (mis. Pimpinan, Pegawai) menghasilkan 403.

### 1.14 UI Form 2 — Kelola Grant Izin per Unit
- **Scope:** halaman Inertia + komponen React untuk pemegang `akses:update` (Superadmin/Admin) memilih pengguna, permission bertipe `butuh_scope=unit` (mis. `pengukuran:create`, `pengukuran:update`, `rencana_aksi:create/update/ajukan`, `kegiatan:create/update`), unit tujuan, dan alasan (wajib); daftar permission unit juga mencakup `rencana_aksi:read` dan `kegiatan:read`; berkas tidak diberi grant unit tersendiri; submit melakukan INSERT `user_permission_granted`; menampilkan daftar grant aktif pengguna dengan aksi cabut (soft: hapus baris, tercatat audit).
- **Dependency:** 1.8, 1.11, 1.4.
- **DoD:** Submit form menghasilkan baris baru `user_permission_granted` dengan `unit_id` terisi (bukan NULL) untuk permission yang dipilih; percobaan submit untuk permission bertipe `global` (mis. `pengaturan:update`) ditolak validasi (baris ini bukan kandidat scope unit); percobaan submit tanpa alasan ditolak; baris `audit_log` terbentuk untuk penambahan maupun pencabutan grant; percobaan submit oleh user tanpa `akses:update` (mis. Perencanaan, Pimpinan, Pegawai) ditolak 403; user dengan peran Admin berhasil submit form ini.

### 1.15 UI Form 3 — Kelola Deny Izin
- **Scope:** halaman Inertia + komponen React untuk pemegang `akses:update` (Superadmin/Admin) memilih pengguna, permission (global maupun unit), unit (nullable — kosong berarti pencabutan menyeluruh), dan alasan (wajib); submit melakukan INSERT `user_permission_denied`; deny dapat menyasar permission yang berasal dari peran maupun grant; menampilkan daftar deny aktif dengan aksi cabut (tercatat audit).
- **Dependency:** 1.9, 1.11.
- **DoD:** Submit deny dengan `unit_id` kosong (global) menghasilkan baris `user_permission_denied` dengan `unit_id` NULL; submit dengan `unit_id` terisi menghasilkan baris ber-unit; percobaan submit tanpa alasan ditolak; setelah deny tersimpan, pemanggilan service resolusi izin (1.11) untuk kombinasi user+permission+unit yang di-deny mengembalikan tolak meski user memiliki allow dari peran maupun grant; baris `audit_log` terbentuk untuk penambahan maupun pencabutan deny; percobaan submit oleh user tanpa `akses:update` ditolak 403.

### 1.16 Halaman "Jelaskan Izin Pengguna"
- **Scope:** halaman Inertia + komponen React, digerbangi permission `pengguna:read` (tanpa permission baru khusus) — memilih seorang pengguna, menampilkan daftar izin EFEKTIF pengguna tsb per unit, lengkap dengan ASAL tiap izin (peran mana / grant mana) dan DENY yang berlaku beserta alasannya. Halaman murni read-only — tidak ada aksi ubah izin di halaman ini (perubahan tetap lewat Form 1.13–1.15).
- **Dependency:** 1.11 (service resolusi izin, dipakai untuk menghitung izin efektif), 1.13, 1.14, 1.15.
- **DoD:** Untuk pengguna dengan peran Perencanaan (global) DITAMBAH satu grant unit DITAMBAH satu deny, halaman menampilkan: permission dari peran ditandai asal "peran: perencanaan"; permission dari grant ditandai asal "grant: unit X" beserta alasannya; permission yang di-deny ditandai eksplisit sebagai dicabut beserta alasannya dan TIDAK muncul sebagai izin aktif meski asalnya ada; user dengan `pengguna:read` (Admin/Superadmin) dapat mengakses halaman ini; user tanpa `pengguna:read` (mis. Pegawai, Pimpinan) mendapat 403; halaman tidak memuat elemen/aksi untuk mengubah `user_roles`/grant/deny secara langsung (hanya tautan ke Form 1.13–1.15).

### 1.17 Middleware proteksi rute berbasis service resolusi izin
- **Scope:** Terapkan pemanggilan service resolusi izin (1.11) sebagai middleware/Policy di seluruh rute dan endpoint Inertia yang memerlukan otorisasi, bukan hanya disembunyikan di tampilan; React hanya menerima hasil evaluasi (mis. props `can.*`) untuk menyembunyikan tombol — tidak pernah mengevaluasi izin sendiri.
- **Dependency:** 1.11.
- **DoD:** Test Pest feature: mengakses endpoint/komponen tanpa permission memadai mengembalikan 403 meski URL diakses langsung (bukan hanya tombol UI yang disembunyikan); mencakup skenario peran Admin tanpa grant substantif mencoba mengakses endpoint substantif data kinerja (mis. create Renstra, verifikasi pengukuran, aktivasi jadwal, susun rencana aksi, `komponen:update`, `jenis_berkas:update`) dan mendapat 403 pada setiap kasus; Admin yang memperoleh grant eksplisit yang sesuai diizinkan setelah seluruh guard bisnis terpenuhi, lalu ditolak kembali bila ada deny cocok (tidak ada larangan keras berdasarkan nama role); inspeksi kode (test statis atau review checklist) memastikan tidak ada logic evaluasi permission yang dihitung di sisi komponen React (React hanya membaca props `can.*` yang dikirim server).

### 1.18 UI Form — Pengelolaan Unit (Admin/Superadmin)
- **Scope:** halaman Inertia + komponen React untuk CRUD Unit (create/read/update/delete, transisi status aktif/nonaktif) bagi pemegang `unit:create/read/update/delete` (Admin dan Superadmin); menyatukan aturan integritas: unit dengan indikator terkait tidak dapat dihapus oleh siapa pun, terlepas peran.
- **Dependency:** 1.4, 1.11, 1.17.
- **DoD:** User dengan peran Admin **atau** Superadmin dapat membuat, membaca, mengubah, dan menghapus unit kosong lewat UI ini; percobaan menghapus unit yang masih memiliki indikator terkait ditolak dengan pesan spesifik untuk kedua peran; user dengan peran Perencanaan/Pimpinan/Pegawai (tanpa permission `unit:*` eksplisit) mendapat 403 saat mengakses halaman ini; setiap create/update/delete tercatat di `audit_log` dengan `objek_tipe = unit`.

### 1.19 Test — presedens deny, scope unit, fail closed
- **Scope:** Suite test Pest terpusat yang memverifikasi ulang secara eksplisit (di luar test unit service 1.11) tiga sifat inti resolusi izin pada level integrasi (lewat endpoint HTTP sungguhan, bukan pemanggilan service langsung): presedens deny menang atas allow; pencocokan scope unit (grant unit A tidak berlaku untuk unit B); fail closed untuk kode permission yang tidak dikenal.
- **Dependency:** 1.11, 1.17.
- **DoD:** Test feature: request HTTP ke endpoint yang memerlukan permission dengan allow dari peran DAN deny yang cocok menghasilkan 403 (deny menang, diuji end-to-end lewat HTTP, bukan hanya unit test service); request ke endpoint scoped-unit dengan grant di unit A berhasil untuk data unit A dan 403 untuk data unit B; endpoint yang secara sengaja diminta mengevaluasi kode permission yang tidak terdaftar (skenario simulasi) menghasilkan 403, bukan 500 atau lolos secara default.

### 1.20 Test — `dasar_izin` pada aksi sensitif
- **Scope:** Memastikan setiap aksi yang menyentuh permission bertanda `sensitif=true` (mis. `pengukuran:sahkan`, `rencana_aksi:buka_kembali`, `jadwal:aktivasi`, `komponen:update`, `jenis_berkas:delete`, `regulasi:update`, `regulasi:delete`) mencatat kolom tambahan `dasar_izin` pada baris `audit_log` yang dihasilkan, berisi sumber allow yang berlaku (peran/grant) atau, pada kasus penolakan, deny yang memicu.
- **Dependency:** 1.11, 10.1 (audit dasar), 1.10 (daftar sensitif).
- **DoD:** Test Pest: memanggil aksi sensitif yang diizinkan lewat peran menghasilkan `audit_log.dasar_izin` yang menyebut peran tsb; memanggil aksi sensitif yang diizinkan lewat grant menghasilkan `dasar_izin` yang menyebut grant tsb (termasuk unit-nya); memanggil aksi sensitif yang ditolak karena deny menghasilkan baris audit percobaan dengan `dasar_izin` menyebut deny yang memicu; aksi pada permission yang TIDAK bertanda sensitif tidak mewajibkan kolom `dasar_izin` terisi.

### 1.21 Test — pemisahan tugas F1/F2 berdasarkan pengajuan Rencana Aksi dan Pengukuran
- **Scope:** Guard berlaku untuk verifikasi dan pengesahan kedua alur. Setiap pengajuan membentuk versi dengan `diajukan_by`, `diajukan_at`, `jalur_pengajuan` (`pic`/`perencanaan`) dan `dasar_izin_pengajuan` beku. F1 melarang aktor memverifikasi/mengesahkan versi yang ia ajukan lewat jalur PIC, walaupun role/grant/PIC berubah kemudian. F2 mengizinkan self-approval pengajuan lewat jalur Perencanaan dengan permission efektif saat tindakan, serta penanda `self_approval` pada versi/audit dan tampilan. `created_by` bukan dasar guard.
- **Dependency:** 1.11, 5.1, 11.1, 10.1 (guard); test integrasi dituntaskan bersama transisi pengukuran dan RA.
- **DoD:** Test Pest untuk kedua alur: pembuat draft berbeda dari pengaju; pergantian PIC/role setelah pengajuan tidak mengubah provenance; F1 tetap menolak self-approval oleh pengaju PIC; F2 berhasil dan ditandai, tetapi deny atau hilangnya permission tetap menolak; aktor berbeda mengikuti izin dan guard normal. Versi lama tidak berubah saat pengajuan ulang.

### 1.22 Audit perubahan isi peran (`role_permissions`)
- **Scope:** Mekanisme (service/halaman internal, dapat berupa command Artisan terproteksi atau form khusus superadmin) untuk menambah/mencabut permission dari suatu peran (`role_permissions`), terpisah dari ketiga form pengelolaan akses (1.13–1.15) karena mengubah peran = mengubah hak seluruh pemegangnya sekaligus. Setiap perubahan WAJIB mencatat `audit_log` dengan `nilai_lama`/`nilai_baru` (daftar permission sebelum/sesudah perubahan pada peran tsb) dan `alasan`.
- **Dependency:** 1.6, 1.12, 10.1.
- **DoD:** Test Pest: menambah satu permission ke suatu peran menghasilkan baris `audit_log` dengan `nilai_lama` (daftar permission sebelum) dan `nilai_baru` (daftar permission sesudah, mencakup tambahan) yang benar; mencabut satu permission menghasilkan pola yang simetris; submit tanpa `alasan` ditolak; setelah perubahan, seluruh pemegang peran tsb (dibuktikan dengan ≥2 user berbeda memegang peran yang sama) langsung memperoleh/kehilangan permission tsb tanpa perlu re-assign individual (diverifikasi lewat pemanggilan service resolusi izin 1.11 untuk kedua user).

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
- **Dependency:** 2.3, 3.9 (aksi tutup jadwal, untuk pengujian guard).
- **DoD:** Test Pest: percobaan nonaktifkan Renstra yang masih memiliki jadwal_tahunan aktif ditolak dengan pesan spesifik; setelah seluruh jadwal terkait ditutup, nonaktifkan berhasil; urutan transisi hanya bisa terjadi sesuai alur (tidak bisa langsung draft→diarsipkan); setiap transisi tercatat di audit_log dengan nilai_lama/nilai_baru status.

### 2.5 Migrasi & CRUD `sasaran`
- **Scope:** Migrasi tabel `sasaran` (renstra_id, nama, keterangan, urutan) + CRUD (Inertia + React) dengan reorder urutan; permission `sasaran:create/update/delete`.
- **Dependency:** 2.1.
- **DoD:** Sasaran baru dapat dibuat di bawah Renstra tertentu dan tampil terurut sesuai kolom `urutan`; percobaan hapus Sasaran yang masih punya Indikator ditolak (lihat 2.7) atau memicu peringatan konfirmasi eksplisit.

### 2.6 Migrasi `indikator` (termasuk kolom `arah` dan `tipe_perhitungan`)
- **Scope:** Migrasi tabel `indikator`, termasuk `unit_id` NOT NULL (menggantikan `tim_kerja_id`), `wajib_catatan`, `created_by_role`, kolom `arah` enum(`naik_baik`, `turun_baik`) NOT NULL default `naik_baik`, dan kolom baru **`tipe_perhitungan`** enum(`rasio_persen`, `penjumlahan`, `manual`) NOT NULL default `manual`.
- **Dependency:** 2.5, 1.4 (unit).
- **DoD:** Migrasi berjalan; percobaan insert indikator tanpa `unit_id` ditolak database (NOT NULL constraint); indikator baru tanpa mengisi `arah`/`tipe_perhitungan` secara eksplisit otomatis tersimpan dengan `arah = naik_baik` dan `tipe_perhitungan = manual`; kolom bernama `unit_id`, bukan `tim_kerja_id`; kolom `tipe_perhitungan` memuat tepat ketiga nilai enum yang didefinisikan.

### 2.7 CRUD Indikator + validasi kepemilikan unit + pilihan arah + tipe perhitungan
- **Scope:** Form create/edit/list Indikator di bawah Sasaran, wajib memilih Unit pemilik, Arah Penilaian (naik_baik/turun_baik), dan Tipe Perhitungan (rasio_persen/penjumlahan/manual); permission `indikator:create/read/update/delete`.
- **Dependency:** 2.6, 1.4.
- **DoD:** Indikator baru tersimpan dengan `unit_id`, `arah`, dan `tipe_perhitungan` terisi; `created_by_role` otomatis terisi dari role aktif user saat pembuatan; setiap create/update/delete tercatat di audit_log; form memuat ketiga opsi tipe perhitungan dan kedua opsi arah secara eksplisit (tidak hanya default tersembunyi).

### 2.8 Fitur pindah unit pada Indikator
- **Scope:** Aksi khusus (terpisah dari edit umum) untuk mengubah `unit_id` suatu indikator, dengan konfirmasi eksplisit dan pencatatan audit detail (nilai_lama/nilai_baru unit).
- **Dependency:** 2.7.
- **DoD:** Test Pest: mengubah unit indikator menghasilkan baris audit_log dengan `tindakan` spesifik (mis. `indikator.pindah_unit`) berisi `nilai_lama.unit_id` dan `nilai_baru.unit_id` yang berbeda dan benar.

### 2.9 Aksi arsipkan Indikator + blokir create pada indikator arsip
- **Scope:** Transisi status indikator `aktif → arsip`; indikator arsip tidak muncul sebagai opsi pengisian pengukuran maupun rencana aksi baru namun tetap terbaca di riwayat. Guard eksplisit di layer `pengukuran:create` dan `rencana_aksi:create`: menolak permintaan create untuk indikator berstatus `arsip`, terlepas dari siapa pemohonnya (termasuk Perencanaan/Superadmin).
- **Dependency:** 2.7.
- **DoD:** Indikator berstatus arsip tidak muncul di dropdown pembuatan pengukuran baru (Modul 5) maupun rencana aksi baru (Modul 11); query riwayat pengukuran/rencana aksi lama untuk indikator tsb tetap mengembalikan data lengkap dengan penanda status arsip; test Pest: percobaan `pengukuran:create` dan `rencana_aksi:create` langsung (bypass UI, mis. lewat pemanggilan service) untuk indikator arsip ditolak dengan pesan spesifik, diuji baik oleh user PIC maupun user Perencanaan.

### 2.10 Migrasi & fitur Target Tahunan (termasuk kolom `baseline`)
- **Scope:** Migrasi `target_tahunan` (unique indikator_id+tahun), termasuk kolom baru **`baseline`** (numeric, nullable — baseline tahun sebelumnya yang dipakai saat menyusun target tahun itu) + form input/update target dan baseline per indikator per tahun; permission `target:update`.
- **Dependency:** 2.6.
- **DoD:** Input target untuk kombinasi indikator+tahun yang sudah ada memperbarui baris (bukan duplikat, dibuktikan unique constraint); target dapat diisi `0` sebagai nilai sah, dan dibedakan dari kondisi belum diisi (`null`) di tampilan; kolom `baseline` dapat diisi terpisah dari `target`, keduanya tampil berdampingan di form.

### 2.11 Migrasi & fitur Perjanjian Kinerja (renstra_pk)
- **Scope:** Migrasi `renstra_pk` (unique renstra_id+tahun) + form input nomor_pk, tanggal_pk per tahun per Renstra; permission `pk:create/update`.
- **Dependency:** 2.1.
- **DoD:** PK baru untuk kombinasi Renstra+tahun yang sama ditolak sistem (unique constraint) kecuali melalui alur koreksi (2.12); PK tersimpan dan dapat dirujuk saat aktivasi Jadwal (Modul 3).

### 2.12 Fitur koreksi Perjanjian Kinerja (wajib alasan)
- **Scope:** Alur khusus untuk mengubah `nomor_pk`/`tanggal_pk` pada PK yang sudah tercatat, mewajibkan pengisian alasan koreksi.
- **Dependency:** 2.11.
- **DoD:** Submit koreksi tanpa alasan ditolak validasi (pesan error jelas); koreksi berhasil menghasilkan baris audit_log dengan `alasan` terisi dan `nilai_lama`/`nilai_baru` mencerminkan field yang berubah.

### 2.13 Fitur revisi Renstra in place akibat perubahan Kepmen IKU
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
- **Scope:** Migrasi kolom baru `renstra.regulasi_id` (FK → regulasi.id, nullable). Perluasan form 2.2 untuk memilih `regulasi_id` (opsional) saat menyusun/mengedit Renstra, DI SAMPING kolom `dasar_hukum` yang tetap wajib diisi sebelum aktivasi (2.3, tidak berubah). Halaman Renstra menampilkan: lampiran dokumen Renstra (`berkasable_type = renstra`, Modul 13, mode file/tautan/teks, tanpa `jenis_berkas`), rujukan regulasi (bila `regulasi_id` terisi, lengkap jenis/nomor/tahun/tentang), dan ringkasan `dasar_hukum` berdampingan.
- **Dependency:** 2.16, 2.2, 13.2, 13.5.
- **DoD:** Migrasi berjalan; `renstra.regulasi_id` dapat diisi/dikosongkan lewat form tanpa mempengaruhi validasi aktivasi (2.3) yang tetap hanya memeriksa `dasar_hukum`; halaman Renstra menampilkan lampiran dokumen Renstra, rujukan regulasi terpilih, dan ringkasan `dasar_hukum` pada satu layar; melampirkan dokumen Renstra (mode apa pun) berhasil sebelum Renstra `aktif`; percobaan menghapus lampiran Renstra setelah Renstra berstatus `aktif` DITOLAK (imutabilitas per §10.6 Workflow); test Pest memverifikasi `renstra.regulasi_id` dapat NULL tanpa error skema.

### 2.19 Kolom `regulasi_id` pada `indikator` + tampilan rujukan pada halaman Indikator
- **Scope:** Migrasi kolom baru `indikator.regulasi_id` (FK → regulasi.id, nullable). Perluasan form 2.7 untuk memilih `regulasi_id` (opsional) saat menyusun/mengedit Indikator, sebagai dasar aturan per indikator yang dapat ditelusuri tanpa mengunggah dokumen yang sama berulang. Halaman Indikator menampilkan rujukan regulasi terpilih (bila ada) lengkap jenis/nomor/tahun/tentang.
- **Dependency:** 2.16, 2.7.
- **DoD:** Migrasi berjalan; `indikator.regulasi_id` dapat diisi/dikosongkan lewat form tanpa mempengaruhi validasi create/update Indikator yang lain; halaman Indikator menampilkan rujukan regulasi terpilih; test Pest memverifikasi `indikator.regulasi_id` dapat NULL tanpa error skema; mengubah `indikator.regulasi_id` tercatat di `audit_log` dengan `nilai_lama`/`nilai_baru`.

### 2.20 Lampiran dokumen Perjanjian Kinerja pada `renstra_pk` + tampilan pada halaman PK
- **Scope:** Integrasi lampiran dokumen pada halaman Perjanjian Kinerja (2.11) via komponen bukti dukung generik (`berkasable_type = renstra_pk`, Modul 13) — mode file/tautan/teks, tanpa `jenis_berkas` untuk pemenuhannya (tidak ada daftar persyaratan bernama), NAMUN minimal satu lampiran menjadi syarat gerbang keempat aktivasi Jadwal Tahunan (lihat 3.5). Halaman Perjanjian Kinerja menampilkan lampiran dokumen PK beserta `nomor_pk`/`tanggal_pk`.
- **Dependency:** 2.11, 13.2, 13.5.
- **DoD:** Melampirkan dokumen PK (mode apa pun) pada `renstra_pk` berhasil tersimpan dan tampil berdampingan dengan `nomor_pk`/`tanggal_pk` pada halaman Perjanjian Kinerja; percobaan menghapus lampiran PK setelah Jadwal Tahunan tahun tsb berstatus `aktif` DITOLAK (imutabilitas per §10.6 Workflow, diuji setelah 3.5 tersedia); renstra_pk tanpa lampiran tetap dapat tersimpan (lampiran bukan syarat penyimpanan PK itu sendiri, hanya syarat aktivasi jadwal — lihat 3.5).

---

## Modul 3 — Periode & Jadwal

### 3.1 Migrasi & seed master `periode`
- **Scope:** Migrasi tabel `periode` (nama, urutan, aktif, is_nilai_akhir) + form CRUD terbatas (Superadmin/Perencanaan); validasi tepat satu baris `is_nilai_akhir=true`.
- **Dependency:** 1.11.
- **DoD:** Percobaan menyimpan periode kedua dengan `is_nilai_akhir=true` ditolak selama masih ada periode lain yang juga `true` (harus non-aktifkan yang lama dulu, ditegaskan via validasi aplikasi); test Pest mengonfirmasi hal ini.

### 3.2 Migrasi `jadwal_tahunan` (tanpa kolom jendela periode, dengan jendela rencana aksi)
- **Scope:** Migrasi tabel `jadwal_tahunan` **tanpa** kolom `pengisian_mulai`, `pengisian_selesai`, `reviu_mulai`, `reviu_selesai` (dipindahkan ke `jadwal_periode`, lihat 3.3). Kolom yang tetap ada: `renstra_id`, `tahun`, `penutupan`, `pakai_persetujuan_pimpinan`, `persetujuan_mulai`, `persetujuan_selesai` (disiapkan, tidak dipakai logic Fase Awal), `status`, `renstra_pk_id`, `activated_at`, `closed_at`; ditambah kolom baru **`rencana_aksi_mulai`**, **`rencana_aksi_selesai`** (date, nullable); constraint unique tahun untuk jadwal aktif per Renstra.
- **Dependency:** 2.1, 2.11 (renstra_pk).
- **DoD:** Migrasi berjalan; skema tabel `jadwal_tahunan` tidak memiliki satu pun kolom `pengisian_*`/`reviu_*` (diverifikasi via introspeksi skema di test Pest); kolom `rencana_aksi_mulai`/`rencana_aksi_selesai` ada di skema, nullable, dan dapat diisi independen dari kolom `jadwal_periode`; kolom-kolom approval Pimpinan ada di skema dan bernilai default (`false`/`null`) tanpa mempengaruhi logic manapun saat ini.

### 3.3 Migrasi & CRUD `jadwal_periode`
- **Scope:** Migrasi tabel pivot baru `jadwal_periode` (jadwal_id, periode_id, pengisian_mulai, pengisian_selesai, reviu_mulai, reviu_selesai) + form (Inertia + React) bagi Perencanaan untuk memilih periode-periode yang diharapkan pada suatu Jadwal Tahunan dan mengisi jendela masing-masing; permission `jadwal:update`.
- **Dependency:** 3.2, 3.1.
- **DoD:** Baris `jadwal_periode` baru tersimpan dengan `jadwal_id` + `periode_id` unik per pasangan; validasi tanggal logis per baris (`pengisian_mulai` ≤ `pengisian_selesai` ≤ `reviu_mulai` ≤ `reviu_selesai`) ditegakkan dan diuji via Pest; satu Jadwal Tahunan dapat memiliki beberapa baris `jadwal_periode` (mis. 4 baris untuk Triwulan I–IV) dengan jendela yang berbeda-beda per baris.

### 3.4 CRUD Jadwal Tahunan (draft) + urutan jendela Rencana Aksi
- **Scope:** Form draft memilih Renstra, tahun, penutupan, `rencana_aksi_mulai/selesai`; permission `jadwal:create/update`. Pada penyusunan normal, `rencana_aksi_mulai ≤ rencana_aksi_selesai < pengisian_mulai` periode pertama merupakan hard gate server. Indikator baru, revisi resmi, dan backfill memakai jalur pengecualian beralasan yang eksplisit, bukan menghapus gate normal.
- **Dependency:** 3.2, 3.3.
- **DoD:** Jadwal valid tersimpan draft; rentang RA terbalik atau bertumpang tindih dengan pengisian pertama pada jalur normal ditolak. Pengecualian hanya diterima melalui jalur yang sesuai dan tercatat audit; payload edit umum tidak dapat menonaktifkan gate. Field pengisian/reviu tetap dimiliki jadwal_periode.

### 3.5 Aksi aktivasi Jadwal + empat gerbang validasi
- **Scope:** Implementasi aksi `jadwal:aktivasi` dengan EMPAT gerbang berurutan: (1) `renstra_pk` untuk `(renstra_id, tahun)` jadwal sudah tercatat; (2) seluruh indikator berstatus `aktif` milik Renstra tersebut memiliki `target_tahunan` untuk tahun jadwal; (3) tahun jadwal berada dalam rentang `[tahun_mulai, tahun_akhir]` Renstra terkait; (4) BARU — `renstra_pk` untuk `(renstra_id, tahun)` tsb memiliki MINIMAL SATU lampiran dokumen (`berkasable_type = renstra_pk`, mode file/tautan/teks bebas), KECUALI ditandai `tidak_dapat_dipenuhi` (lihat 13.7 — dipicu saat unggahan file dimatikan sementara belum ada lampiran mode lain). Jika seluruh gerbang lolos: isi `renstra_pk_id` dan `activated_at`.
- **Dependency:** 3.4, 2.11, 2.10, 2.6, 2.20, 13.7.
- **DoD:** Test Pest terpisah untuk masing-masing gerbang: (a) aktivasi tanpa PK tahun terkait ditolak dengan pesan spesifik gerbang 1; (b) aktivasi dengan minimal satu indikator aktif tanpa target tahun tsb ditolak dengan pesan spesifik gerbang 2; (c) aktivasi dengan tahun jadwal di luar rentang Renstra ditolak dengan pesan spesifik gerbang 3; (d) BARU — aktivasi dengan `renstra_pk` tahun tsb belum memiliki satu pun lampiran ditolak dengan pesan spesifik gerbang 4, dan berhasil begitu satu lampiran (mode apa pun) ditambahkan; (e) aktivasi pada `renstra_pk` yang gerbang 4-nya ditandai `tidak_dapat_dipenuhi` (unggahan file dimatikan, belum ada lampiran mode lain) berhasil melewati gerbang 4 secara administratif; (f) aktivasi yang lolos keempat gerbang berhasil mengubah status ke `aktif`, mengisi `renstra_pk_id`, dan `activated_at` terisi timestamp saat itu; setiap penolakan (termasuk gerbang 4) tercatat sebagai audit_log percobaan gagal (lihat 10.5).

### 3.6 Migrasi & model snapshot indikator berversi
- **Scope:** Skema `jadwal_snapshot` mengikuti Data Model: salinan konteks indikator/target/baseline/cara hitung, `nomor_versi`, `menggantikan_id`, `alasan_koreksi`, `rujukan_koreksi`, dan `periode_mulai_id`; unique(jadwal_id, indikator_id, nomor_versi). Konteks tiap versi beserta komponen disimpan beku.
- **Dependency:** 3.2, 2.6, 2.10.
- **DoD:** Migrasi dan constraints menolak duplikasi nomor versi; versi pengganti menunjuk versi lama dari pasangan jadwal/indikator yang sama. Periode mulai harus termasuk jadwal. Snapshot lama dan referensi pengukuran historis tetap tersedia.

### 3.7 Pembentukan snapshot awal idempoten dan indikator baru
- **Scope:** Pada aktivasi atau buka kembali, buat versi awal hanya untuk indikator yang belum punya snapshot. Jangan membuat versi baru hanya karena fungsi dipanggil ulang atau master berubah. Untuk indikator baru, Perencanaan menetapkan periode mulai berlaku; periode sebelumnya diberi N/A dan tidak menjadi kewajiban pengisian. Koreksi snapshot memakai jalur 3.8 secara eksplisit.
- **Dependency:** 3.5, 2.7, 2.10, 3.6, 5.15.
- **DoD:** Pemanggilan berulang mempertahankan jumlah dan isi versi awal. Menambah satu indikator menghasilkan satu snapshot awal berikut komponen tanpa menyentuh indikator lama. Periode sebelumnya tidak menambah hitungan Belum/Tidak Mengisi. actor_id audit sesuai pelaku aktivasi/buka kembali.

### 3.8 Koreksi snapshot terkendali tanpa menimpa histori
- **Scope:** Snapshot yang telah dirujuk RA/pengukuran tidak boleh ditimpa. Koreksi salah input target memakai permission target:update dan bukti resmi PK, bukan perubahan definisi/formula; buat versi baru dengan `menggantikan_id`, alasan, rujukan bukti resmi, cakupan dan periode berlaku; record tersahkan tetap menunjuk konteks lama sampai dibuka, diajukan, dan disahkan ulang melalui versi baru. Snapshot belum dirujuk boleh diperbaiki saat jadwal aktif dengan audit; setelah penutupan hanya dalam jendela koreksi 3.9. Koreksi salah data bukan jalur bebas mengubah komitmen target PK.
- **Dependency:** 3.6, 3.7, 5.1, 11.1, 10.1.
- **DoD:** Overwrite/delete snapshot dirujuk ditolak; koreksi tanpa alasan/rujukan ditolak. Versi baru tidak mengubah hasil laporan lama; pengesahan ulang dapat menunjuk versi baru dan tetap menyediakan versi lama. Koreksi di luar cakupan atau durasi buka-kembali ditolak.

### 3.9 Tutup dan buka kembali Jadwal dengan cakupan koreksi
- **Scope:** Tutup mengisi closed_at. Buka kembali memerlukan `jadwal:buka_kembali`, alasan, `koreksi_mulai/koreksi_sampai` dan `lingkup_koreksi` eksplisit sesuai Data Model. Sesudah penutupan asli, buka kembali hanya membuka jalur Perencanaan secara default untuk koreksi yang tercakup selama durasinya. PIC memerlukan tindakan terpisah oleh pemegang jadwal:update berupa pembukaan jendela resmi beralasan (3.12), dengan seluruh rentang dan lingkupnya berada di dalam sesi koreksi tahunan; grant unit, PIC efektif, status induk dan gerbang kelengkapan tetap diperiksa. Status aktif saja tidak membuka hak PIC, dan penutupan asli tetap terjejak. Tutup kembali mengakhiri akses koreksi. Penambahan indikator tetap melalui 3.7.
- **Dependency:** 3.5, 3.7, 10.1.
- **DoD:** Test sebelum/sesudah batas waktu, di dalam/luar cakupan, dan PIC vs Perencanaan. Membuka tahun sesudah tanggal penutupan asli mengizinkan koreksi sah tanpa mengganti deadline asli; tindakan di luar cakupan/durasi ditolak. PIC tetap ditolak setelah buka tahun tanpa aksi jendela terpisah, lalu hanya diizinkan jika jendela PIC dan sesi/lingkup koreksi tahunan sama-sama berlaku serta grant/PIC/status valid. Alasan kosong ditolak dan seluruh riwayat penutupan/pembukaan teraudit.

### 3.10 Tampilan status jadwal & indikator per jendela waktu periode dan rencana aksi
- **Scope:** Halaman/komponen yang menampilkan jadwal aktif beserta status jendela pengisian/reviu **per periode** (dari `jadwal_periode`, bukan dari `jadwal_tahunan`), berjalan berdasarkan tanggal hari ini vs kolom `jadwal_periode` yang relevan; ditambah tampilan status jendela **rencana aksi** tingkat tahun (dari `jadwal_tahunan.rencana_aksi_mulai/selesai`), mis. "Masa penyusunan rencana aksi dibuka/ditutup".
- **Dependency:** 3.5, 3.3, 3.4.
- **DoD:** Komponen menampilkan label benar (mis. "Masa pengisian Triwulan II", "Masa reviu Triwulan II", "Periode Triwulan I ditutup", "Masa penyusunan rencana aksi tahun berjalan telah ditutup") sesuai tanggal sistem saat ini dibandingkan kolom `jadwal_periode` dan `jadwal_tahunan.rencana_aksi_mulai/selesai` masing-masing, diuji dengan Pest menggunakan `Carbon::setTestNow()` pada beberapa tanggal dan periode berbeda dalam jadwal yang sama.

---

### 3.11 Aktivasi retroaktif dan backfill nilai final historis
- **Scope:** Perencanaan membuat jadwal retroaktif dalam rentang Renstra, menyiapkan PK dan lampirannya, periode, serta jendela historis. Aktivasi tetap melewati EMPAT gerbang 3.5 dan snapshot idempoten. PIC terkunci. Gerbang RA/komponen/bukti pengajuan boleh dikecualikan untuk backfill resmi. Jika hanya skor final tersedia, terima `sumber_nilai=historis` dengan `sumber_historis` dan `alasan_historis`, tanpa komponen rekaan atau mengubah tipe indikator master.
- **Dependency:** 2.11, 3.3, 3.4, 3.5, 3.7, 5.1, 5.2, 5.16, 11.4.
- **DoD:** Keempat gerbang diuji, termasuk lampiran PK/pengecualian resmi. PIC ditolak, Perencanaan dapat mengisi dalam cakupan/durasi koreksi jika sudah melewati penutupan. activated_at adalah waktu nyata. Backfill skor akhir tanpa komponen berhasil hanya dengan sumber/alasan dan penanda terlihat di laporan; tidak mengubah formula master, tidak mengarang komponen, tetap melewati verifikasi/pengesahan dan audit.

---

### 3.12 Revisi resmi jendela PIC
- **Scope:** Perencanaan dengan `jadwal:update` dapat menetapkan revisi jendela RA/pengisian yang beralasan, menyimpan tanggal lama/baru, aktor, dan cakupan. Pengembalian record saja tidak merevisi deadline. Setelah penutupan tahunan, wajib ada sesi/lingkup koreksi 3.9 terlebih dahulu; pembukaan PIC adalah aksi jadwal:update terpisah dengan alasan dan tenggat baru. Rentang jendela PIC dan objek yang dibuka wajib berada dalam durasi/lingkup sesi koreksi tahunan; membuka tahun tidak otomatis membuka PIC.
- **Dependency:** 3.3, 3.4, 3.9, 10.1.
- **DoD:** Revisi tanpa alasan ditolak; PIC hanya memperoleh akses dalam jendela baru yang relevan, deadline lama tetap tertelusur. PIC/record di luar cakupan tetap terkunci. Setelah penutupan, jendela PIC tanpa sesi koreksi, melampaui durasinya atau di luar lingkupnya ditolak. Sesi koreksi berakhir langsung menutup hak PIC walaupun status jadwal masih aktif; grant/PIC/status dan gerbang kelengkapan tetap diperlukan; audit mencatat perubahan.

---

## Modul 4 — Penugasan (Penanggung Jawab)

### 4.1 Migrasi & model `penanggung_jawab`
- **Scope:** Migrasi tabel sesuai data model.
- **Dependency:** 2.6 (indikator), 1.3 (users).
- **DoD:** Migrasi berjalan; baris dapat dibuat dengan `alasan` nullable untuk penugasan pertama.

### 4.2 Form penugasan awal Penanggung Jawab
- **Scope:** halaman Inertia + komponen React untuk menetapkan penanggung jawab pertama suatu indikator (tanggal_mulai_berlaku, user_id); permission `penanggung_jawab:update`.
- **Dependency:** 4.1.
- **DoD:** Penugasan pertama tersimpan tanpa mewajibkan `alasan`; baris tercatat di audit_log.

### 4.3 Fitur pergantian Penanggung Jawab (wajib alasan)
- **Scope:** Alur khusus untuk menambah baris baru penugasan pada indikator yang sudah punya penanggung jawab sebelumnya, mewajibkan `alasan`.
- **Dependency:** 4.2.
- **DoD:** Test Pest: submit pergantian tanpa alasan pada indikator yang sudah punya PJ sebelumnya ditolak; submit dengan alasan berhasil menambah baris baru (baris lama tidak terhapus/termodifikasi) dan tercatat audit_log.

### 4.4 Query/fungsi resolusi Penanggung Jawab Efektif
- **Scope:** Implementasi fungsi/scope Eloquent yang mengembalikan baris `penanggung_jawab` dengan `tanggal_mulai_berlaku` maksimum yang ≤ tanggal acuan tertentu, untuk suatu indikator. Fungsi ini dipakai baik oleh alur pengukuran maupun alur rencana aksi (Modul 11) untuk menentukan PIC efektif.
- **Dependency:** 4.1.
- **DoD:** Test Pest dengan 3 baris riwayat penugasan bertanggal berbeda mengonfirmasi fungsi mengembalikan baris yang benar untuk beberapa tanggal acuan berbeda, termasuk tanggal acuan di masa lalu (sebelum pergantian terakhir); fungsi yang sama dipakai ulang tanpa duplikasi logic oleh service rencana aksi (dibuktikan lewat referensi kode/pemanggilan fungsi yang sama pada test Modul 11).

---

## Modul 5 — Pengukuran

### 5.1 Migrasi pengukuran dan versi pengajuan/pengesahan
- **Scope:** Header `pengukuran` tetap unique(indikator_id,tahun,periode_id) dan memakai `versi` untuk optimistic locking. Ikuti Data Model untuk `sumber_nilai` (komponen/manual/historis), `status_perhitungan`, alasan/rujukan backfill, serta `pengukuran_versi` unique(pengukuran_id,nomor). Versi pengajuan menyimpan pengaju/jalur izin dan snapshot angka, formula, target, RA, klaim, narasi, bukti serta persyaratan; mengacu `rencana_aksi_versi_id` dan `jadwal_snapshot_id`; rencana_aksi_versi_id boleh null hanya untuk backfill tanpa RA. Pengesahan melengkapi actor/timestamp satu kali.
- **Dependency:** 3.6, 11.1, 1.11.
- **DoD:** Constraints header/nomor versi berlaku; snapshot versi tidak dapat ditimpa setelah diajukan. Versi tersahkan dapat dibaca ulang tanpa bergantung pada master/kegiatan/RA terkini. status_perhitungan membedakan belum_diisi, terhitung, dan tidak_dapat_dihitung; sumber_nilai membedakan komponen, manual, dan historis.

### 5.2 Buat Draft Pengukuran (grant unit + PIC efektif, Perencanaan global)
- **Scope:** Pengguna jalur PIC wajib sekaligus memiliki `pengukuran:create` pada unit target dan menjadi PIC indikator yang efektif sekarang; salah satu saja tidak cukup. Perencanaan dengan izin global tidak wajib menjadi PIC, tetap tunduk deny/status/jadwal. Snapshot dan periode yang dipilih harus berlaku pada indikator dan jadwal tersebut.
- **Dependency:** 5.1, 3.7, 4.4, 1.11.
- **DoD:** Test dua PIC pada unit yang sama: masing-masing hanya dapat membuat indikator penugasannya; grant tanpa PIC dan PIC tanpa grant ditolak. Perencanaan global dapat lintas unit selama guard terpenuhi. Indikator arsip atau periode sebelum mulai berlaku ditolak; draft baru versi=1.

### 5.3 Edit nilai/catatan Draft (manual)
- **Scope:** Form edit nilai dan catatan pada pengukuran berstatus draft **untuk indikator bertipe `manual`**; menaikkan `versi` setiap simpan; permission `pengukuran:update` (scoped untuk PIC, global untuk Perencanaan). Untuk indikator bertipe `rasio_persen`/`penjumlahan`, field `nilai` ditampilkan read-only (lihat 5.13).
- **Dependency:** 5.2.
- **DoD:** Setiap submit edit berhasil menaikkan kolom `versi` sebanyak 1; submit dengan `versi` yang dikirim klien tidak sesuai versi terbaru di database ditolak dengan pesan konflik (test Pest mensimulasikan dua edit berurutan dengan versi usang pada percobaan kedua); form untuk indikator `tipe_perhitungan=manual` menampilkan field nilai sebagai input bebas.

### 5.4 Guard jendela pengisian PIC dan jendela koreksi Perencanaan
- **Scope:** Create/update/ajukan PIC hanya di antara pengisian_mulai dan pengisian_selesai periode; setelah penutupan wajib pula ada sesi/lingkup koreksi tahunan 3.9 dan pembukaan jendela PIC terpisah 3.12 di dalamnya. Dikembalikan tidak memperpanjang akses PIC otomatis; perpanjangan resmi lewat revisi jadwal beralasan 3.12. Perencanaan dikecualikan dari tenggat PIC sampai penutupan tahunan, atau dalam lingkup/durasi koreksi resmi 3.9 setelah penutupan.
- **Dependency:** 5.2, 5.3, 3.3, 3.9, 3.12.
- **DoD:** Test sebelum mulai, tepat batas, dan sesudah selesai: PIC ditolak di luar jendela termasuk setelah dikembalikan. Revisi resmi membuka akses hanya sampai deadline baru dan menyimpan jejak lama. Perencanaan lewat penutupan ditolak tanpa koreksi resmi dan diizinkan hanya di dalam scope/durasi koreksi.

### 5.5 Ajukan Pengukuran (validasi catatan wajib berbasis arah)
- **Scope:** Aksi transisi `draft → diajukan`, dengan validasi: catatan wajib diisi jika (a) nilai **memburuk menurut arah pada snapshot indikator** dibanding pengukuran berstatus **Disahkan terakhir secara kronologis** untuk indikator yang sama (naik_baik: nilai turun memicu; turun_baik: nilai naik memicu; nilai stagnan **tidak** memicu; pengukuran pertama tanpa pembanding Disahkan tidak wajib), atau (b) `indikator.wajib_catatan = true`. Perbandingan nilai memakai `pengukuran.nilai` final — untuk indikator berkomponen, ini adalah nilai turunan hasil mesin perhitungan (lihat 5.13).
- **Dependency:** 5.3, 5.4, 5.13.
- **DoD:** Test Pest per arah: indikator `arah=naik_baik` dengan nilai turun dari pembanding Disahkan terakhir tanpa catatan ditolak; indikator `arah=turun_baik` dengan nilai **naik** dari pembanding Disahkan terakhir tanpa catatan ditolak; indikator manapun dengan nilai stagnan (sama dengan pembanding) tanpa catatan **diterima**; pengukuran pertama indikator (tanpa pembanding Disahkan sebelumnya) dengan nilai numerik sah tanpa catatan **diterima**, sepanjang syarat catatan/kelengkapan lain terpenuhi; null karena penyebut nol tetap memerlukan alasan 5.16; indikator `wajib_catatan=true` tanpa catatan ditolak meski nilai membaik; pengajuan valid mengubah `status_alur` menjadi `diajukan` dan tercatat audit_log; pengajuan oleh PIC setelah deadline periode (5.4) tetap ditolak terlepas kondisi catatan; validasi arah pada indikator berkomponen memakai nilai turunan hasil 5.13, bukan input komponen mentah.

### 5.6 Verifikasi dengan penanda reviu terlambat
- **Scope:** Transisi diajukan → diverifikasi memerlukan `pengukuran:verifikasi` dan F1/F2. reviu_selesai adalah tenggat operasional: Perencanaan tetap dapat memproses setelahnya sampai penutupan tahunan dengan penanda terlambat; sesudah penutupan wajib koreksi resmi 3.9.
- **Dependency:** 5.5, 1.21, 3.9.
- **DoD:** Permission tidak memadai/deny/F1 menolak. Verifikasi lewat reviu_selesai tetapi sebelum penutupan berhasil dan ditandai terlambat; setelah penutupan tanpa koreksi resmi ditolak. Provenance pengajuan dan hasil audit tetap tersedia.

### 5.7 Kembalikan dengan alasan (pra-pengesahan)
- **Scope:** Aksi transisi `diajukan atau diverifikasi → dikembalikan`, mewajibkan pengisian alasan; notifikasi/alert kontekstual ke penanggung jawab terkait.
- **Dependency:** 5.6.
- **DoD:** Submit pengembalian tanpa alasan ditolak validasi; pengembalian valid mengubah status menjadi `dikembalikan`, tercatat audit_log dengan `alasan` terisi, dan baris menjadi dapat diedit kembali (efektif kembali ke alur draft).

### 5.8 Revisi pasca-dikembalikan
- **Scope:** Memastikan pengukuran berstatus `dikembalikan` dapat diedit ulang oleh pemegang scope yang sesuai (PIC atau Perencanaan) dan diajukan kembali (transisi kembali memakai alur 5.5).
- **Dependency:** 5.7, 5.5.
- **DoD:** Test Pest: pengukuran berstatus `dikembalikan` dapat diubah nilainya oleh pemegang scope yang sesuai, lalu diajukan ulang, dan validasi 5.5, 5.4, dan gerbang 5.16 tetap berlaku pada pengajuan ulang ini.

### 5.9 Sahkan Pengukuran dan bekukan konteks laporan
- **Scope:** Transisi diverifikasi → disahkan memerlukan pengukuran:sahkan, F1/F2 dan aturan jendela 5.6; approval Pimpinan tidak dipakai. Sahkan versi pengajuan beserta konteks laporan beku, bukan membaca ulang data sumber yang sudah berubah. Hasil penyebut nol dapat disahkan dengan alasan dan status_perhitungan tidak_dapat_dihitung; tidak otomatis menetapkan capaian. Sesudah koreksi disahkan ulang, versi baru berstatus capaian Belum Ditetapkan sampai diputus manual; versi/status lama tetap historis.
- **Dependency:** 5.6, 5.1, 1.21, 6.6.
- **DoD:** Test pengesahan normal, terlambat, F1/F2, penyebut nol beralasan, dan pengesahan ulang. Perubahan RA/kegiatan/master setelah pengesahan tidak mengubah laporan versi tersebut. Versi baru tidak mewarisi capaian lama; tidak ada approval Pimpinan yang dipersyaratkan.

### 5.10 Buka-kembali Pengukuran Disahkan tanpa menghapus versi resmi
- **Scope:** pengukuran:buka_kembali mengembalikan header ke dikembalikan dengan alasan. Sebelum penutupan berlaku jalur biasa; setelah penutupan aksi buka record memerlukan scope/durasi koreksi resmi 3.9 dan pelaku Perencanaan yang berizin. Revisi oleh PIC sesudahnya tetap memerlukan pembukaan jendela terpisah 3.12 di dalam sesi/lingkup koreksi tersebut. Versi tersahkan lama tetap tersedia selama draft koreksi dikerjakan. PIC harus tetap memenuhi 5.4.
- **Dependency:** 5.9, 3.9.
- **DoD:** Alasan/permission wajib; setelah penutupan tanpa koreksi resmi ditolak, dengan koreksi resmi sesuai cakupan berhasil. Versi resmi lama tetap dapat diekspor dan dibandingkan; draft koreksi tidak mengganti laporan resmi sebelum disahkan ulang.

### 5.11 Larangan penghapusan permanen data bermakna
- **Scope:** Guard aplikasi (Policy/Observer) yang menolak operasi delete pada baris `pengukuran` yang memiliki `nilai` atau `catatan` terisi, di seluruh titik masuk (UI maupun akses langsung ke model).
- **Dependency:** 5.1.
- **DoD:** Test Pest: percobaan delete pengukuran dengan nilai terisi menghasilkan exception/response ditolak, dan tercatat sebagai audit_log bertindakan "percobaan_hapus_ditolak" atau setara.

### 5.12 Validasi scope unit dan PIC aktif pada mutasi Pengukuran
- **Scope:** Backend memeriksa izin efektif/deny, unit konteks snapshot dan PIC indikator efektif pada semua create/update/ajukan. Perencanaan global dikecualikan dari syarat PIC, bukan dari deny atau guard alur. Pembacaan bukan hak mutasi.
- **Dependency:** 5.2, 5.3, 1.11, 4.4.
- **DoD:** Grant satu unit tidak mengizinkan mutasi indikator lain dalam unit yang sama jika aktor bukan PIC. Pergantian PIC langsung mencabut hak mutasi PIC lama walau grantnya masih ada; PIC baru tetap memerlukan grant. Scope unit lain/deny tetap menolak Perencanaan/PIC sesuai resolver.

### 5.13 Migrasi `pengukuran_komponen` & mesin perhitungan nilai turunan
- **Scope:** Migrasi tabel baru `pengukuran_komponen`: `id`, `pengukuran_id` (FK), `komponen_id` (FK → indikator_komponen), `nilai` (numeric, nullable), `updated_by`, `updated_at`, unique(`pengukuran_id`, `komponen_id`). Implementasi service mesin perhitungan yang menghitung `pengukuran.nilai` dari `pengukuran_komponen` sesuai tipe_perhitungan dan definisi komponen snapshot yang dirujuk: `rasio_persen` = (Σ(pembilang_i × bobot_i) ÷ (penyebut × bobot)) × 100; `penjumlahan` = Σ(penjumlah_i × bobot_i). Pembagian dengan penyebut 0 menghasilkan nilai `null` ("tidak dapat dihitung"), bukan `0` atau galat. Nilai dibulatkan sesuai presisi pada snapshot. Realisasi kumulatif menggunakan basis yang sama dengan target periode dan definisi operasional indikator; rasio dihitung dari komponen kumulatif, bukan menjumlahkan persentase antartriwulan.
- **Dependency:** 5.1, 2.14, 2.15.
- **DoD:** Test Pest: indikator `rasio_persen` dengan komponen pembilang=80, penyebut=100, bobot=1 menghasilkan nilai turunan 80; kasus multi-pembilang berbobot `(a×k1 + b×k2)/t×100` menghasilkan angka sesuai perhitungan manual yang diuji dengan minimal 2 skenario angka berbeda; indikator `penjumlahan` dengan 3 komponen (Lektor, Lektor Kepala, Guru Besar) menjumlahkan ketiganya sesuai bobot; komponen penyebut bernilai 0 menghasilkan `pengukuran.nilai = null`, `status_perhitungan=tidak_dapat_dihitung`, alasan wajib sebelum pengajuan/pengesahan, dan flag "tidak dapat dihitung" pada respons, bukan exception atau `0`; hasil dibulatkan sesuai presisi snapshot yang diset berbeda-beda pada minimal 2 skenario.

### 5.14 UI pengisian nilai komponen & nilai turunan read-only
- **Scope:** halaman Inertia + komponen React form pengisian pengukuran untuk indikator bertipe `rasio_persen`/`penjumlahan`: menampilkan input per komponen aktif (sesuai definisi snapshot `jadwal_snapshot_komponen`), memanggil mesin perhitungan (5.13) secara reaktif untuk menampilkan `pengukuran.nilai` sebagai field READ-ONLY, mengisi `sumber_nilai = komponen` otomatis. Untuk indikator `manual`, form tetap seperti 5.3 dengan `sumber_nilai = manual`.
- **Dependency:** 5.13, 5.15 (snapshot komponen).
- **DoD:** frontend test (Vitest + React Testing Library): mengubah nilai salah satu komponen pada form memperbarui tampilan nilai turunan secara reaktif tanpa reload halaman penuh; test Pest memastikan nilai yang tersimpan tetap hasil hitungan sisi server; field `pengukuran.nilai` tidak dapat diketik langsung (disabled/readonly) untuk indikator berkomponen, dibuktikan dengan percobaan submit payload yang memaksa nilai `pengukuran.nilai` custom — nilai yang tersimpan tetap hasil hitungan sistem, bukan payload custom tsb.

### 5.15 Migrasi `jadwal_snapshot_komponen`
- **Scope:** Migrasi tabel anak baru `jadwal_snapshot_komponen`: `jadwal_snapshot_id` (FK), `komponen_id` (identitas komponen sumber), `kode`, `label`, `peran`, `bobot`, `urutan`. Diisi oleh trigger snapshot (3.7 diperluas) saat aktivasi/buka-kembali jadwal, menyalin seluruh komponen aktif indikator pada saat itu.
- **Dependency:** 3.6, 2.14 (skema); pemanggilan pembentukan diuji bersama task aktivasi snapshot.
- **DoD:** Setelah aktivasi jadwal untuk indikator berkomponen dengan N komponen aktif, `jadwal_snapshot_komponen WHERE jadwal_snapshot_id = ...` menghasilkan tepat N baris; mengubah definisi `indikator_komponen` (bobot/label) setelah snapshot terbentuk tidak mengubah baris snapshot komponen yang sudah ada (test Pest membandingkan nilai sebelum dan sesudah perubahan master); form pengisian komponen (5.14) membaca definisi dari `jadwal_snapshot_komponen`, bukan langsung dari `indikator_komponen` master.

### 5.16 Gerbang pengajuan dan pembekuan konteks versi
- **Scope:** Pengajuan normal wajib merujuk versi RA tersahkan yang sesuai indikator/tahun; seluruh input komponen wajib lengkap atau nilai langsung manual terisi; bukti wajib mengikuti snapshot persyaratan versi pengajuan serta pengecualian mode file 13.7. Angka komponen nol sah; jika penyebut nol, nilai final null diterima dengan alasan dan status_perhitungan tidak_dapat_dihitung, bukan dianggap belum mengisi. Bekukan target/narasi/klaim/bukti/persyaratan beserta provenance saat diajukan; perubahan sumber setelah itu tidak mengubah versi yang direviu. Backfill resmi 3.11 boleh melewati ketiga gerbang dengan sumber/alasan/penanda.
- **Dependency:** 5.5, 11.3, 5.13, 13.4, 5.1.
- **DoD:** Test gagal tiap gerbang, manual null vs nol, komponen null vs penyebut nol beralasan, dan pengecualian file-only/mode campuran. Ajukan ulang membentuk versi baru; perubahan master/bukti/RA setelah pengajuan tidak mengubah versi lama. Backfill final lolos tanpa komponen rekaan setelah sumber/alasan diverifikasi dan tetap diaudit.

### 5.17 Fixture perhitungan indikator 2026 dan definisi yang belum disahkan
- **Scope:** Uji formula dari lampiran PRD: rasio sederhana/multi-pembilang, penjumlahan berbobot, penjumlahan subjenjang tanpa baseline, dan kemampuan multi-pembilang berbobot. IKU 3 memakai lima input penjumlah (`perencanaan_kinerja`, `pengukuran_kinerja`, `pelaporan_kinerja`, `evaluasi_internal`, `zi`) masing-masing koefisien 0,5. Subtotal SAKIP = empat input pertama; IKU 3 = jumlah kelima input × 0,5, bukan rata-rata lima angka dan bukan mengalikan subskor kembali dengan 30%/30%/15%/25%. IKU 8 boleh diuji aritmetikanya dengan fixture, tetapi definisi populasi/penyebut harus disahkan Perencanaan sebelum produksi.
- **Dependency:** 5.13, 2.14, 2.15.
- **DoD:** Fixture IKU 3: 23;24;11,5;19;75 → subtotal SAKIP 77,5 dan IKU 76,25; 23,1;24,6;11,55;20,5;53,04 → subtotal 79,75 dan IKU 66,395 sebelum pembulatan sesuai presisi snapshot. Kelima varian formula, zero/null dan komponen nonaktif diuji; varian kemampuan yang tidak mewakili IKU aktif memakai fixture ilustratif yang diberi label. Baseline 74,2 dan pemetaan target/realisasi TW II belum dijadikan fixture kebenaran bisnis sebelum konfirmasi; hasil test angka IKU 8 bukan persetujuan definisi produksi.

---

## Modul 6 — Reviu & Pengesahan

### 6.1 Dashboard kerja Perencanaan — antrean Diajukan
- **Scope:** halaman Inertia + komponen React menampilkan daftar pengukuran berstatus `diajukan` yang menunggu tindakan Perencanaan, dengan filter Renstra/Tahun/Periode/Unit.
- **Dependency:** 5.5, 1.11.
- **DoD:** Halaman menampilkan hanya baris berstatus `diajukan`; filter berfungsi dan mengurangi/menambah hasil sesuai kriteria; akses halaman ini memerlukan `pengukuran:verifikasi` atau lebih tinggi.

### 6.2 Dashboard kerja Perencanaan — antrean Diverifikasi (siap sahkan)
- **Scope:** halaman Inertia + komponen React menampilkan daftar pengukuran berstatus `diverifikasi` yang siap disahkan.
- **Dependency:** 5.6, 6.1.
- **DoD:** Halaman menampilkan hanya baris berstatus `diverifikasi`; aksi "Sahkan" pada halaman ini memanggil logic 5.9 dan memperbarui tampilan tanpa reload penuh (reaktif di sisi klien).

### 6.3 Antrean pengukuran resmi dan koreksi yang diizinkan
- **Scope:** Tampilkan versi pengukuran disahkan beserta aksi buka kembali sesuai 5.10. Rekaman setelah penutupan tetap dapat dibaca, tetapi aksi koreksi hanya tersedia dalam lingkup/durasi 3.9. Draft koreksi dipisahkan dari versi resmi terakhir.
- **Dependency:** 5.10, 6.2.
- **DoD:** Baris historis tetap terbaca; tombol mengikuti capability server. Buka kembali valid menghasilkan dikembalikan pada antrean revisi PIC/Perencanaan, bukan langsung Diajukan pada 6.1. Alasan kosong atau koreksi di luar cakupan ditolak; versi resmi tetap tersedia.

### 6.4 Penetapan Status Capaian manual per versi tersahkan
- **Scope:** Pemegang status_capaian:update menetapkan tercapai/belum_tercapai pada `pengukuran_versi` yang disahkan, berdasarkan keputusan Perencanaan. Versi baru setelah koreksi dimulai sebagai Belum Ditetapkan. Tidak ada keputusan capaian otomatis dari angka, nilai null, atau status versi lama.
- **Dependency:** 5.9, 6.6, 1.11.
- **DoD:** Penetapan menyimpan pengukuran_versi_id, sumber=manual, ditetapkan_oleh. Versi belum disahkan ditolak. Sesudah koreksi disahkan ulang, capaian versi lama tetap historis dan versi baru belum memiliki capaian sampai ditetapkan eksplisit; hasil tidak_dapat_dihitung tidak otomatis menjadi belum_tercapai.

### 6.5 Revisi Status Capaian dengan riwayat per versi
- **Scope:** Revisi menambah baris status_capaian baru untuk versi tersahkan yang sama; tidak mengubah atau menghapus riwayat. Query status aktif memilih penetapan terbaru untuk pengukuran_versi_id yang dimaksud, bukan lintas seluruh versi header.
- **Dependency:** 6.4.
- **DoD:** Penetapan terbaru aktif hanya pada versi tersebut; baris lama masih dapat ditelusuri; versi lain tidak berubah; perubahan memerlukan audit.

### 6.6 Migrasi status_capaian yang merujuk versi pengesahan
- **Scope:** Ikuti Data Model untuk relasi pengukuran_versi_id, sumber manual/data_sumber dan ditetapkan_oleh. Hanya versi tersahkan yang dapat memiliki penetapan; data_sumber belum menjadi jalur operasional MVP.
- **Dependency:** 5.1.
- **DoD:** FK/version scope berlaku; sumber manual selalu menyertakan aktor. Riwayat penetapan tidak tertimpa saat pengukuran dikoreksi atau disahkan ulang.

---

## Modul 7 — Dashboard

### 7.1 Migrasi/index pendukung query dashboard
- **Scope:** Tambahkan index database yang diperlukan untuk query agregasi dashboard (mis. index pada `pengukuran(status_alur)`, `status_capaian(pengukuran_versi_id, created_at)`, `rencana_aksi(status_alur)`, `kegiatan(status, periode_id)`).
- **Dependency:** 5.1, 6.6, 11.1, 12.1.
- **DoD:** `EXPLAIN ANALYZE` pada query ringkasan dashboard menunjukkan penggunaan index yang relevan (bukan sequential scan penuh pada tabel besar) di lingkungan uji dengan data seed memadai.

### 7.2 Ringkasan status indikator × periode sesuai masa berlaku
- **Scope:** Hitung kategori Tercapai, Belum Tercapai, Belum Ditetapkan, Belum Mengisi, Tidak Mengisi dari versi resmi dan kewajiban periode yang berlaku. Keluarkan indikator arsip dan periode sebelum periode_mulai_id dari kewajiban. Tampilkan N/A serta status_perhitungan tidak_dapat_dihitung terpisah dari belum mengisi; null karena penyebut nol bukan ketidakadaan pengajuan.
- **Dependency:** 6.6, 5.1, 3.7, 3.3.
- **DoD:** Test kategori dan jumlah tepat per indikator/periode; indikator baru tidak memunculkan tunggakan periode sebelumnya. Nilai penyebut nol yang disahkan tidak terhitung sebagai Belum/Tidak Mengisi. Koreksi tersahkan dengan capaian belum diputus tampil Belum Ditetapkan.

### 7.3 Grafik target vs realisasi berbasis versi resmi
- **Scope:** Grafik target tahunan/sasaran periode vs realisasi menggunakan snapshot dan konteks RA yang dibekukan pada versi pengukuran tersahkan. Sediakan penanda bila sedang ada draft koreksi; data draft bukan pengganti seri resmi.
- **Dependency:** 7.2, 3.7, 11.2, 5.9.
- **DoD:** Grafik target/realisasi sesuai data uji; mengubah RA/kegiatan/master sesudah pengesahan tidak mengubah seri historis. Label membedakan nilai target, realisasi, persentase pencapaian dan status capaian manual.

### 7.4 Filter dashboard (Renstra, Tahun, Periode, Sasaran, Unit)
- **Scope:** Kontrol filter reaktif (state React + Inertia partial reload) yang memperbarui komponen ringkasan (7.2) dan grafik (7.3) tanpa reload halaman penuh.
- **Dependency:** 7.2, 7.3.
- **DoD:** Mengubah filter Unit pada UI mengubah angka pada komponen ringkasan sesuai data yang difilter, diuji dengan frontend test (Vitest + React Testing Library) pada komponen filter, dan test Pest untuk query backend-nya.

### 7.5 Akses dashboard oleh seluruh role (termasuk Pegawai dan Admin)
- **Scope:** Memastikan `dashboard:read` yang dimiliki seluruh preset role (Superadmin, Admin, Perencanaan, Pimpinan, Pegawai) benar-benar memberi akses baca dashboard tanpa perlu permission tambahan.
- **Dependency:** 7.4, 1.11.
- **DoD:** Test Pest: user dengan peran Pegawai (tanpa permission tambahan apapun) dapat mengakses halaman dashboard dan menerima response 200/tampil normal; user dengan peran Admin juga menerima 200/tampil normal pada halaman yang sama, meski tidak memiliki permission substantif data kinerja apa pun.

### 7.6 Panel progres Rencana Aksi per indikator (status alur)
- **Scope:** halaman Inertia + komponen React menampilkan panel dashboard baru: jumlah rencana aksi per status alur (draft/diajukan/diverifikasi/dikembalikan/disahkan) per Renstra/tahun/unit, sebagai indikator progres kesiapan sebelum periode pengisian pengukuran dimulai.
- **Dependency:** 11.1, 11.3, 7.4.
- **DoD:** Test Pest (backend) / Vitest (frontend): dengan data seed berisi rencana aksi pada seluruh 5 status, panel menampilkan hitungan yang tepat per status serta penanda self_approval; filter Unit pada 7.4 ikut memfilter panel ini.

### 7.7 Panel jumlah Kegiatan per status per periode
- **Scope:** halaman Inertia + komponen React menampilkan panel dashboard baru: jumlah kegiatan per status (rencana/terlaksana/tidak_terlaksana/ditunda/batal) per periode, termasuk hitungan eksplisit kegiatan `batal`/`tidak_terlaksana` sebagai sinyal risiko capaian.
- **Dependency:** 12.1, 7.4.
- **DoD:** Test Pest (backend) / Vitest (frontend): dengan data seed kegiatan pada seluruh status, panel menampilkan hitungan tepat per status per periode; kegiatan `kegiatan_asal_id` terisi (hasil geser periode) dihitung terpisah dari kegiatan asalnya (tidak dobel-hitung sebagai kegiatan yang sama).

### 7.8 Target periode vs realisasi komponen/manual
- **Scope:** Untuk indikator berkomponen tampilkan target dan realisasi per komponen dari konteks versi resmi. Untuk manual tampilkan target periode langsung dan nilai realisasi tanpa komponen dummy. Label basis waktu mengikuti definisi operasional dan target kumulatif; persentase antartriwulan tidak dijumlahkan.
- **Dependency:** 11.2, 5.13, 7.4, 5.9.
- **DoD:** Indikator manual dan berkomponen sama-sama menampilkan pembanding yang benar. Ubah target RA sesudah pengesahan tidak mengubah tabel versi lama. Nol tampil sebagai nilai sah dan null belum diisi/tidak dapat dihitung tampil berbeda.

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

### 8.3 Rekapitulasi indikator × periode dengan konteks laporan beku
- **Scope:** Baris laporan resmi bersumber dari versi pengukuran tersahkan dan konteks beku: PIC, indikator/formula, baseline, target PK, versi RA beserta target periode, nilai realisasi, komponen, klaim/status/narasi kegiatan, bukti/persyaratan serta pengecualian. Rekomendasi Pimpinan ditampilkan beserta riwayatnya. Draft koreksi dapat ditinjau terpisah dan tidak mengubah laporan versi resmi lama. Bedakan target, realisasi, persentase pencapaian, dan status capaian manual.
- **Dependency:** 11.2, 5.13, 5.14, 12.1, 12.6, 8.5, 8.1, 13.7, 5.9.
- **DoD:** Test gabungan indikator komponen/manual, dua klaim dengan narasi berbeda, kegiatan tidak_terlaksana, rekomendasi dan bukti dengan pengecualian. Mutasi sumber sesudah pengesahan tidak mengubah rekap versi lama; pengesahan koreksi menghasilkan konteks baru yang dapat dibandingkan. Periode N/A dan backfill final terlihat jelas.

### 8.4 Ekspor rekapitulasi Excel dan persetujuan contoh keluaran
- **Scope:** Ekspor 8.3 memuat baris komponen atau nilai langsung untuk manual, narasi dan rekomendasi serta penanda historis/pengecualian. Perencanaan menyetujui satu contoh keluaran sebagai acuan UAT sebelum format dianggap final. Workbook lama adalah referensi isi, bukan kewajiban menyalin ketidakkonsistenan judul kolom.
- **Dependency:** 8.3, 8.2.
- **DoD:** Reader test memverifikasi data/filter/versi dan struktur sesuai contoh keluaran yang disetujui Perencanaan. Target PK, target periode, realisasi, persentase pencapaian dan status capaian dibedakan. Fixture format dan persetujuannya tercatat; tanpa itu format belum dianggap diterima UAT.

---

### 8.5 Rekomendasi Pimpinan yang dicatat Perencanaan
- **Scope:** Migrasi/model dan form rekomendasi_pimpinan mengikuti Data Model/PRD untuk indikator × tahun × periode; permission `rekomendasi:tetapkan`. Perencanaan mencatat rekomendasi, revisi menambah riwayat; tidak ada approval atau pengisian langsung oleh Pimpinan dalam MVP.
- **Dependency:** 5.1, 1.11, 10.1.
- **DoD:** Pengguna berizin dapat mencatat/revisi rekomendasi dengan riwayat lama tetap terbaca dan audit; pengguna tanpa izin ditolak. Rekomendasi yang relevan tampil di rekap 8.3 tanpa mengubah snapshot data kinerja resmi.

---

## Modul 9 — Setelan Aplikasi

### 9.1 Migrasi & model `pengaturan`
- **Scope:** Migrasi tabel key-value `pengaturan`: `kunci` (unique), `nilai`, `tipe`, `grup`, `updated_by`, `updated_at`.
- **Dependency:** 1.3 (FK updated_by).
- **DoD:** Migrasi berjalan; constraint unique pada `kunci` ditegakkan (percobaan insert kunci duplikat ditolak, diuji Pest); model dapat dibuat/dibaca sesuai struktur.

### 9.2 Seeder nilai default `pengaturan`
- **Scope:** Seeder yang mengisi kunci awal saat instalasi: identitas instansi (`instansi.nama`, `instansi.alamat`, `instansi.telepon`, `instansi.surel`, `instansi.laman`, `instansi.logo`), identitas aplikasi (`aplikasi.nama`, `aplikasi.label_unit`), preferensi tampilan/laporan (`preferensi.zona_waktu`, `preferensi.format_tanggal`, `preferensi.format_angka`, `preferensi.header_ekspor`, `preferensi.footer_ekspor`) — masing-masing dengan `tipe` dan `grup` yang sesuai.
- **Dependency:** 9.1.
- **DoD:** Pada database testing disposable yang sudah diverifikasi, `php artisan migrate:fresh --seed` menghasilkan seluruh kunci di atas dengan `nilai` default terisi (bukan NULL) dan `grup` terisi sesuai pengelompokan (`instansi`, `aplikasi`, `preferensi`); jumlah baris `pengaturan` setelah seed sama dengan jumlah kunci yang didefinisikan di seeder (tidak kurang/lebih).

### 9.3 Accessor pengaturan dengan cache
- **Scope:** Service/helper (mis. `Pengaturan::get('kunci', $default)`) yang membaca nilai dari tabel `pengaturan`, dengan layer cache (mis. Laravel Cache) agar pembacaan berulang saat render tidak selalu menghantam database; cache diinvalidasi otomatis saat nilai diperbarui (lihat 9.4).
- **Dependency:** 9.2.
- **DoD:** Test Pest: pemanggilan `Pengaturan::get('aplikasi.label_unit')` berulang menghasilkan nilai yang benar; setelah nilai diubah lewat 9.4, pemanggilan berikutnya (tanpa restart proses) mengembalikan nilai baru, bukan nilai cache lama — dibuktikan dengan assertion pada nilai sebelum dan sesudah update dalam satu skenario test.

### 9.4 Form Setelan Aplikasi (Admin/Superadmin)
- **Scope:** halaman Inertia + komponen React terkelompok per grup (`instansi`, `aplikasi`, `preferensi`, `berkas`, `notifikasi`) untuk mengubah nilai `pengaturan`; permission `pengaturan:update`; validasi whitelist kunci (hanya kunci yang telah didefinisikan di 9.2/9.7/14.2 yang dapat diubah, kunci lain ditolak); setiap perubahan menginvalidasi cache accessor (9.3) untuk kunci terkait.
- **Dependency:** 9.3, 1.11.
- **DoD:** Submit perubahan pada kunci valid berhasil memperbarui `nilai`, `updated_by`, `updated_at`; submit dengan kunci di luar whitelist (mis. hasil manipulasi request) ditolak validasi; user dengan peran Superadmin **atau** Admin berhasil mengakses dan menyimpan form ini; user dengan peran Perencanaan/Pimpinan/Pegawai mendapat 403.

### 9.5 Audit perubahan Setelan Aplikasi
- **Scope:** Setiap perubahan nilai lewat 9.4 tercatat di `audit_log` per kunci yang berubah, dengan `nilai_lama`/`nilai_baru` berisi nilai kunci sebelum/sesudah perubahan untuk nilai nonrahasia; token/credential hanya dicatat sebagai perubahan metadata tersamarkan, tidak pernah nilainya.
- **Dependency:** 9.4, 10.1 (audit dasar).
- **DoD:** Test Pest: mengubah 2 kunci sekaligus dalam satu submit menghasilkan 2 baris `audit_log` terpisah (satu per kunci), masing-masing dengan `objek_tipe = pengaturan`, `objek_id`/pengenal kunci yang sesuai, dan `nilai_lama`/`nilai_baru` yang benar.

### 9.6 Guard cakupan: larangan mengubah enum/status/aturan bisnis lewat `pengaturan`
- **Scope:** Tinjauan kode dan test regresi yang memastikan tabel `pengaturan` dan form 9.4 tidak dipakai sebagai jalur untuk mengubah nilai enum (status Renstra/Jadwal/Pengukuran/Rencana Aksi/Kegiatan, dsb), nama permission, atau aturan bisnis apa pun — seluruh whitelist kunci di 9.2/9.7/14.2 terbatas pada teks dan preferensi presentasional/operasional.
- **Dependency:** 9.4.
- **DoD:** Test Pest/statis: seluruh kunci pada whitelist 9.2/9.7/14.2 diverifikasi bertipe teks/tanggal-format/angka-format/URL/angka-ukuran/boolean, tidak ada satu pun kunci yang membaca/menulis ke kolom enum tabel lain; tidak ada rute/method di form 9.4 yang menerima parameter di luar daftar kunci whitelist.

### 9.7 Migrasi & seeder kunci grup `berkas` (`berkas.unggahan_aktif`, `berkas.ukuran_maks_kb`, `berkas.format_diizinkan`, `berkas.tautan_selalu_diizinkan`)
- **Scope:** Tambahan seeder kunci baru pada grup `berkas`: `berkas.unggahan_aktif` (boolean, default `true` — saklar aktif/nonaktif unggahan mode file secara aplikasi-wide; bila `false`, seluruh endpoint unggah file di ketiga tahap — rencana_aksi/pengukuran/kegiatan — menolak permintaan terlepas `jenis_berkas` apa pun), `berkas.ukuran_maks_kb` (int) dan `berkas.format_diizinkan` (varchar, mis. `pdf,docx,xlsx,jpg,png`) — kedua kunci terakhir dipakai sebagai fallback saat `jenis_berkas.ukuran_maks_kb`/`format_diizinkan` kosong (lihat Modul 13), dan `berkas.tautan_selalu_diizinkan` (boolean, default `true` — penanda bahwa mode tautan dan teks selalu tersedia sebagai jalur alternatif tanpa memakai storage, dibaca oleh UI persyaratan pada 13.1 sebagai indikasi non-blokir). Keempat kunci ini diubah lewat form 9.4 yang sama, digerbangi `pengaturan:update` — dipegang **Admin maupun Superadmin**. Wewenang ini terpisah dari substansi persyaratan bukti dukung per indikator/tahap (`jenis_berkas:create/update/delete`, dipegang Perencanaan/Superadmin, lihat 13.1): grup `berkas` di sini mengatur kebijakan/saklar tingkat aplikasi, bukan menentukan bukti dukung apa yang wajib untuk indikator/tahap mana.
- **Dependency:** 9.1; seeder konfigurasi bersama 9.2, integrasi UI pada 9.4.
- **DoD:** Pada database testing disposable yang sudah diverifikasi, `php artisan migrate:fresh --seed` menghasilkan keempat kunci baru dengan nilai default terisi (`berkas.unggahan_aktif = true`, `berkas.tautan_selalu_diizinkan = true`); test Pest: validasi unggahan berkas (13.3) yang `jenis_berkas` terkait memiliki `ukuran_maks_kb`/`format_diizinkan` NULL berhasil mengambil nilai fallback dari kedua kunci ukuran/format, dibuktikan dengan mengubah nilai kunci lalu memverifikasi validasi ikut berubah; mengubah `berkas.unggahan_aktif` menjadi `false` membuat SELURUH percobaan unggah mode file (rencana_aksi/pengukuran/kegiatan) ditolak dengan pesan spesifik "unggahan dinonaktifkan", terlepas status `jenis_berkas.wajib`, sementara mode tautan/teks tetap dapat dipakai; user dengan peran Admin **maupun** Superadmin (keduanya memegang `pengaturan:update`) berhasil mengubah keempat kunci ini lewat form 9.4; user dengan peran Perencanaan (yang memegang `jenis_berkas:create/update/delete` tapi bukan `pengaturan:update`) mendapat 403 saat mencoba mengubah kunci grup `berkas` ini, membuktikan pemisahan wewenang kebijakan vs substansi persyaratan.

### 9.8 Panel read-only "Penggunaan Penyimpanan Bukti Dukung"
- **Scope:** halaman Inertia + komponen React pada halaman Setelan Aplikasi menampilkan panel READ-ONLY: jumlah baris `berkas` bermode `file` beserta total `ukuran_bytes`, dan jumlah baris `berkas` bermode `tautan`/`teks` (masing-masing dihitung terpisah, lintas keenam induk `berkasable_type`). Panel ini dapat diakses oleh siapa pun yang memegang `pengaturan:update` ATAU `jenis_berkas:read` (Admin, Superadmin, Perencanaan, Pimpinan, Pegawai — seluruh peran memegang `jenis_berkas:read`), tanpa permission baru khusus.
- **Dependency:** 9.4, 13.2 (tabel `berkas`).
- **DoD:** Test Pest: dengan data seed berisi N baris `berkas` mode file (total ukuran tertentu, lintas keenam induk), M baris mode tautan, dan K baris mode teks, panel menampilkan angka N + total ukuran, dan (M+K) sebagai jumlah bukti non-file — dicocokkan tepat dengan hitungan basis data; panel dapat diakses user Perencanaan (tanpa `pengaturan:update`) maupun user Admin/Superadmin; panel tidak menampilkan aksi ubah data apa pun (murni tampilan).

### 9.9 Halaman "Batas unggahan berkas" (format/ukuran per persyaratan)
- **Scope:** halaman Inertia + komponen React terpisah dari 9.4, mendaftar SELURUH `jenis_berkas` (aktif maupun nonaktif) sebagai baris, dengan HANYA dua kolom yang dapat diubah inline: `format_diizinkan` dan `ukuran_maks_kb`; permission `pengaturan:update` (Admin dan Superadmin). Kolom substantif (`nama`, `tahap`, `wajib`, `izinkan_file`, `izinkan_tautan`, `izinkan_teks`, `semua_mode_wajib`, `indikator_id`) ditampilkan sebagai referensi read-only — payload yang menyertakan kolom tsb di luar dua kolom yang diizinkan DITOLAK sistem sama sekali, terlepas nilai yang dikirim. Validasi: `ukuran_maks_kb` minimal `100` bila diisi; `format_diizinkan` wajib terisi bila `izinkan_file = true` pada persyaratan tersebut. Perubahan bersifat GRANDFATHERED — bukti yang sudah diunggah tidak menjadi tidak sah karena perubahan batas; bila perubahan menyempitkan format sementara sudah ada bukti berformat lama, sistem menampilkan PERINGATAN (bukan menolak penyimpanan).
- **Dependency:** 13.1 (`jenis_berkas`), 9.4, 1.11.
- **DoD:** Test Pest: submit `ukuran_maks_kb = 50` (di bawah 100) DITOLAK validasi; submit `format_diizinkan` kosong pada persyaratan dengan `izinkan_file = true` DITOLAK validasi; submit yang menyertakan kolom substantif (mis. `wajib`, `nama`) di luar dua kolom yang diizinkan DITOLAK sistem sepenuhnya (baik kolom substantif maupun kedua kolom yang sah tidak tersimpan pada payload tsb); submit valid pada `format_diizinkan`/`ukuran_maks_kb` berhasil memperbarui baris `jenis_berkas` terkait TANPA mengubah kolom substantif lain (dibuktikan dengan membandingkan snapshot kolom substantif sebelum/sesudah); bukti yang sudah diunggah dengan format lama (sebelum penyempitan `format_diizinkan`) tetap sah setelah perubahan (grandfathered, dibuktikan lolos gerbang kelengkapan 13.4 tanpa perlu diunggah ulang); penyempitan format sementara ada bukti berformat lama pada persyaratan tsb memicu PERINGATAN pada respons (bukan penolakan penyimpanan); setiap perubahan tercatat `audit_log` PER PERSYARATAN (jenis_berkas_id, kolom yang berubah, nilai lama → nilai baru, oleh siapa) — dibuktikan dengan mengubah 2 persyaratan sekaligus menghasilkan 2 baris audit_log terpisah; user dengan peran Admin **maupun** Superadmin berhasil mengakses dan menyimpan halaman ini; user dengan peran Perencanaan (memegang `jenis_berkas:create/update/delete` tapi bukan `pengaturan:update`) mendapat 403 saat mencoba mengakses halaman ini, membuktikan pemisahan wewenang: substansi persyaratan = Perencanaan (13.1), kebijakan teknis unggahan per persyaratan = pemegang `pengaturan:update` (Admin/Superadmin).

---

## Modul 10 — Audit & Histori

### 10.1 Infrastruktur dasar pencatatan Audit Log
- **Scope:** Migrasi tabel `audit_log` dan service/helper terpusat (`AuditLogger::catat(...)`) yang dipanggil oleh seluruh modul lain untuk mencatat peristiwa; tanpa endpoint update/delete.
- **Dependency:** 1.3 (users, untuk FK actor_id). Task ini secara praktik dikerjakan lebih awal secara paralel dengan Modul 1, karena hampir seluruh task lain di atas bergantung padanya untuk memenuhi DoD masing-masing yang menyebut audit_log.
- **DoD:** Memanggil `AuditLogger::catat(...)` dari test Pest menghasilkan baris baru di `audit_log` dengan seluruh kolom wajib terisi; tidak ada rute/method publik untuk mengubah/menghapus baris `audit_log` (dicek dengan mencoba akses rute semacam itu dan memastikan 404/405).

### 10.2 Validasi alasan wajib pada tindakan sensitif
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

### 10.6 Audit pengecualian mode file dan gerbang lampiran PK
- **Scope:** Catat pengecualian/pencabutan mode file per induk/persyaratan secara terpisah dari perubahan saklar global, termasuk mode campuran semua_mode_wajib. Mode tautan/teks yang masih diwajibkan tidak dihapus oleh pengecualian. Pengecualian gerbang PK dicatat menurut 13.7.
- **Dependency:** 10.1, 13.7, 9.7.
- **DoD:** File-only dan file+tautan semua_mode_wajib menghasilkan audit pengecualian file saat relevan; mode tautan yang belum dipenuhi tetap gagal. Pemulihan unggahan mempengaruhi evaluasi baru, tidak membatalkan versi yang telah disahkan. Setiap audit menyebut induk/persyaratan, alasan sistem, mode, aktor perubahan dan keadaan sebelum/sesudah.

---

## Modul 11 — Rencana Aksi

### 11.1 Migrasi Rencana Aksi dan versi pengajuan/pengesahan
- **Scope:** Header RA tetap unique(indikator_id,tahun), dengan unit/jadwal/PIC historis, jadwal_snapshot_id, uraian, status dan versi optimistic locking sesuai Data Model. Tambah rencana_aksi_versi unique(rencana_aksi_id,nomor) untuk snapshot target, uraian, klaim, bukti/persyaratan, dan provenance pengajuan diajukan_by/at, jalur_pengajuan, dasar_izin_pengajuan. Pengesahan actor/timestamp dicatat satu kali; versi lama tetap tersedia bagi pengukuran.
- **Dependency:** 2.6, 3.2, 3.6, 4.4.
- **DoD:** Header duplikat/nomor versi duplikat ditolak. Versi diajukan tidak dapat ditimpa oleh edit header/master. Pengukuran tetap dapat merujuk versi RA lama walaupun RA direvisi/disahkan ulang.

### 11.2 Target periode langsung untuk manual atau per komponen
- **Scope:** rencana_aksi_target mengikuti Data Model: komponen_id nullable hanya untuk indikator manual; satu baris target langsung per RA/periode pada manual, satu baris per komponen/periode pada tipe berkomponen. Gunakan dua partial unique index sesuai Data Model: (rencana_aksi_id,periode_id) WHERE komponen_id IS NULL untuk manual, serta (rencana_aksi_id,periode_id,komponen_id) WHERE komponen_id IS NOT NULL untuk target per komponen. Tampilkan skor turunan hanya untuk komponen; manual menerima nilai akhir langsung tanpa komponen dummy.
- **Dependency:** 11.1, 2.14, 3.3, 5.13.
- **DoD:** Test unique untuk manual/komponen; komponen kosong pada nonmanual dan komponen dummy pada manual ditolak. Setiap periode berlaku wajib memiliki target; nol sah dan null belum diisi. Preview komponen dihitung server, manual ditampilkan langsung, dan keduanya dipakai pada 11.6.

### 11.3 Alur Rencana Aksi dengan kelengkapan dan F1/F2
- **Scope:** Transisi draft/dikembalikan → diajukan membekukan versi pengajuan. Gerbang: target lengkap untuk seluruh periode berlaku (nilai langsung manual atau seluruh komponen wajib pada snapshot RA), serta bukti menurut persyaratan versi pengajuan. Verifikasi/kembalikan/sahkan memerlukan permission masing-masing dan F1/F2 berbasis pengaju aktual 1.21; pengembalian wajib alasan. PIC membutuhkan grant unit dan penugasan indikator efektif, Perencanaan global dikecualikan dari syarat PIC. Perencanaan dapat menyelesaikan reviu terlambat sampai penutupan tahunan (dengan penanda bila tenggat reviu terlewati); setelahnya wajib koreksi resmi 3.9. Pengembalian saja tidak membuka deadline.
- **Dependency:** 11.2, 1.11, 13.4, 4.4.
- **DoD:** Test kelengkapan target manual/komponen, N/A sebelum mulai berlaku, kelengkapan bukti, grant+PIC, deny dan F1/F2. Aktor created_by berbeda dari diajukan_by tetap memakai pengaju versi. Pengajuan ulang menghasilkan versi baru; versi sebelumnya dan pengukuran yang sudah merujuknya tidak berubah.

### 11.4 Jendela penyusunan RA dan koreksi resmi
- **Scope:** PIC hanya boleh create/update/ajukan dalam rencana_aksi_mulai/selesai, termasuk setelah pengembalian; setelah penutupan, jendela PIC resmi 3.12 harus sekaligus berada dalam sesi/lingkup koreksi tahunan 3.9. Perencanaan boleh memproses sampai penutupan tahunan; sesudahnya hanya dalam scope/durasi koreksi 3.9. Revisi jendela PIC memakai 3.12 dengan alasan/deadline baru.
- **Dependency:** 11.3, 3.4, 3.9, 3.12.
- **DoD:** Test di luar rentang PIC ditolak, pengembalian tidak mengubah hasil; revisi resmi jendela membuka cakupan yang sesuai. Perencanaan lewat penutupan ditolak tanpa koreksi resmi, diizinkan selama scope/durasinya cocok. Riwayat deadline lama tidak hilang.

### 11.5 Peringatan nilai turun antar-periode (bukan blokir)
- **Scope:** Validasi non-blocking pada form pengisian `rencana_aksi_target` (11.2): saat nilai suatu periode untuk suatu komponen lebih kecil dari nilai periode sebelumnya (periode dengan urutan lebih awal pada `periode.urutan`, komponen yang sama), tampilkan peringatan pada UI tanpa mencegah penyimpanan draft maupun pengajuan.
- **Dependency:** 11.2.
- **DoD:** frontend test (Vitest + React Testing Library): mengisi nilai Triwulan II lebih kecil dari Triwulan I pada komponen yang sama memicu flag/pesan peringatan pada respons komponen, namun submit tetap berhasil tersimpan (tidak ada exception/penolakan); mengisi nilai yang sama atau lebih besar tidak memicu peringatan tsb.

### 11.6 Peringatan + alasan wajib: total target periode terakhir vs target PK tahunan
- **Scope:** Validasi pada aksi ajukan (11.3): menghitung nilai turunan hasil mesin perhitungan (5.13) dari `rencana_aksi_target` periode terakhir yang berlaku, atau mengambil nilai target langsung untuk manual, lalu membandingkannya dengan target PK pada snapshot yang dirujuk. Bila tidak setara (dengan toleransi presisi pada snapshot), tampilkan peringatan dan WAJIBKAN pengisian field alasan sebelum pengajuan diterima — tidak memblokir pengajuan itu sendiri.
- **Dependency:** 11.3, 5.13, 2.10 (target_tahunan).
- **DoD:** Test Pest: pengajuan dengan total target periode terakhir ≠ target PK tahunan tanpa alasan ditolak (validasi wajib alasan); pengajuan yang sama dengan alasan terisi berhasil, dan `alasan_revisi` (atau kolom alasan pengajuan yang relevan) tersimpan; pengajuan dengan total target = target PK tahunan berhasil tanpa perlu alasan tambahan.

### 11.7 Buka kembali RA tanpa mengubah konteks pengukuran lama
- **Scope:** Pemegang rencana_aksi:buka_kembali membuka RA disahkan dengan alasan. Sebelum penutupan memakai alur biasa; sesudahnya wajib koreksi resmi 3.9. Revisi menghasilkan versi pengajuan/pengesahan baru. Pengukuran yang sudah disahkan tetap merujuk versi RA lama; penerapan target baru pada pengukuran lama memerlukan koreksi pengukuran tersendiri.
- **Dependency:** 11.3, 3.9.
- **DoD:** Alasan/permission wajib; koreksi di luar scope/durasi ditolak. Versi RA baru dapat disahkan tanpa mengubah target/narasi/klaim di laporan pengukuran lama. PIC tetap tunduk jendela; setelah disahkan ulang validasi target dan bukti tetap berlaku.

### 11.8 Penetapan PIC rencana aksi mengikuti Penanggung Jawab efektif
- **Scope:** Logic pengisian `rencana_aksi.penanggung_jawab_id` saat pembuatan mengambil hasil resolusi Penanggung Jawab Efektif (4.4) pada saat itu — jejak historis; hak pengisian selanjutnya (create/update rencana_aksi_target) tetap mengikuti Penanggung Jawab yang **berlaku saat ini**, bukan yang tercatat di `penanggung_jawab_id` bila sudah berganti.
- **Dependency:** 4.4, 11.1.
- **DoD:** Test Pest: rencana aksi dibuat saat PIC A menjabat mencatat `penanggung_jawab_id = A`; setelah PIC berganti ke B (4.3), percobaan update `rencana_aksi_target` oleh A ditolak dan oleh B dengan grant unit yang sesuai berhasil, sementara `rencana_aksi.penanggung_jawab_id` tetap menunjuk A sebagai jejak historis penyusunan awal.

---

## Modul 12 — Kegiatan & Klaim Kegiatan

### 12.1 Migrasi & model `kegiatan`
- **Scope:** Migrasi tabel baru `kegiatan`: `id`, `unit_id` (FK, not null), `tahun` (int, not null), `periode_id` (FK, not null), `nama`, `tujuan`, `sasaran_peserta` (int, nullable), `keterangan_peserta` (varchar, nullable), `lokasi` (varchar, nullable), `tanggal_rencana` (date, nullable), `tanggal_realisasi` (date, nullable), `anggaran` (numeric, nullable), `status` enum(`rencana`,`terlaksana`,`tidak_terlaksana`,`ditunda`,`batal`) default `rencana`, `realisasi_peserta` (int, nullable), `justifikasi` (text, nullable), `kegiatan_asal_id` (uuid, nullable, FK → kegiatan.id), `uraian_pelaksanaan` (text, nullable), `kendala` (text, nullable), `strategi_tindaklanjut` (text, nullable), `created_by`, `created_at`, `updated_at`.
- **Dependency:** 1.4 (unit), 3.1 (periode).
- **DoD:** Migrasi berjalan; FK `kegiatan_asal_id` merujuk ke tabel yang sama (self-referencing) dan boleh NULL; percobaan insert tanpa `unit_id`/`periode_id` ditolak database (NOT NULL); model dapat dibuat via factory dengan status default `rencana`.

### 12.2 CRUD Kegiatan (Inertia + React) + validasi kepemilikan unit
- **Scope:** Form create/edit/list Kegiatan per periode tanpa input/tampilan/validasi bisnis anggaran pada MVP; kolom anggaran nullable tetap disiapkan tanpa digunakan. Kegiatan dikerjakan kolaboratif oleh pengguna berizin pada unit, tidak mensyaratkan PIC indikator tertentu. Permission `kegiatan:create/read/update/delete`; pengguna scoped hanya dapat membuat/mengubah kegiatan pada unitnya, Perencanaan global. Akses baca dan unduh bukti mengikuti scope induk serta deny.
- **Dependency:** 12.1, 1.11.
- **DoD:** User dengan `kegiatan:create` scoped unit A berhasil membuat kegiatan untuk unit A, ditolak (403) untuk unit B; user dengan permission global (Perencanaan) berhasil untuk unit mana pun; pengguna unit lain ditolak dan pengguna berizin lain pada unit yang sama dapat berkolaborasi; field anggaran tidak ditampilkan/diterima sebagai input MVP; setiap create/update/delete tercatat audit_log.

### 12.3 Transisi status Kegiatan (`tidak_terlaksana`/`ditunda`/`batal`) + justifikasi wajib
- **Scope:** Implementasi transisi `rencana → tidak_terlaksana`/`ditunda`/`batal` (WAJIB mengisi `justifikasi`, TIDAK memerlukan bukti dukung pelaksanaan apa pun). Kegiatan tidak pernah dihapus akibat gagal terlaksana — hanya berubah status. Transisi `rencana → terlaksana` (dengan gerbang bukti dukung) ditangani terpisah di 12.11.
- **Dependency:** 12.2.
- **DoD:** Test Pest: transisi ke `tidak_terlaksana`/`ditunda`/`batal` tanpa `justifikasi` ditolak validasi; transisi dengan justifikasi terisi berhasil, kegiatan tetap ada di database (bukan soft/hard delete); ketiga transisi ini tidak pernah memeriksa kelengkapan bukti dukung tahap kegiatan (dibuktikan dengan skenario tanpa satu pun bukti dukung terunggah, transisi tetap berhasil selama justifikasi terisi).

### 12.4 Geser periode: kegiatan baru dengan `kegiatan_asal_id`
- **Scope:** Aksi khusus "Geser ke periode berikutnya" pada kegiatan berstatus `ditunda`/`tidak_terlaksana`: membuat BARIS KEGIATAN BARU pada periode tujuan (periode_id berbeda, tahun bisa sama/berbeda), menyalin data relevan (nama, tujuan, unit_id) dan mengisi `kegiatan_asal_id` menunjuk kegiatan asal. Kegiatan asal tidak diubah/dihapus.
- **Dependency:** 12.3.
- **DoD:** Test Pest: aksi geser menghasilkan baris kegiatan baru dengan `kegiatan_asal_id` = id kegiatan asal, `periode_id` = periode tujuan, status awal `rencana`; kegiatan asal tetap ada dengan periode dan status aslinya tidak berubah; query kegiatan pada kedua periode (asal dan tujuan) masing-masing mengembalikan baris kegiatan yang sesuai — membuktikan "kegiatan yang sama tampil di dua periode" sebagai baris terpisah yang tertaut.

### 12.5 Narasi per kegiatan (uraian_pelaksanaan, kendala, strategi_tindaklanjut)
- **Scope:** Form input narasi kegiatan sebagai bagian dari update kegiatan (12.2/12.11), disimpan per baris kegiatan (bukan blok tunggal di level indikator).
- **Dependency:** 12.2.
- **DoD:** frontend test (Vitest + React Testing Library): mengisi ketiga field narasi pada kegiatan tersimpan dan dapat dibaca ulang per kegiatan; dua kegiatan berbeda pada indikator yang sama (via klaim) memiliki narasi masing-masing yang independen, tidak saling menimpa.

### 12.6 Migrasi & CRUD `klaim_kegiatan`
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

### 12.10 Koreksi klaim mengikuti sumber dan versi induk
- **Scope:** Klaim sumber rencana_aksi mengikuti status/versi RA; klaim sumber pengukuran mengikuti status/versi pengukuran terkait, sehingga RA yang disahkan tidak mengunci seluruh klaim pengukuran. Mutasi draft/dikembalikan memerlukan izin/PIC yang sesuai; bila induk sedang diajukan/diverifikasi harus dikembalikan terlebih dahulu. Setelah disahkan, koreksi melalui buka kembali dan versi baru; relasi lama yang telah dibekukan tetap historis.
- **Dependency:** 12.6, 11.3, 5.1, 5.10, 11.7.
- **DoD:** Test kedua sumber: klaim pengukuran draft dapat dikoreksi walaupun RA sudah disahkan; klaim pada versi diajukan/diverifikasi tidak berubah diam-diam. Koreksi pascapengesahan tidak menghapus relasi/narasi laporan lama; aktor tidak berizin/PIC unit lain ditolak; audit mencatat alasan dan perubahan.

### 12.11 Bukti kegiatan: persyaratan global dan gabungan indikator klaim
- **Scope:** Transisi rencana → terlaksana memeriksa gabungan persyaratan kegiatan global dan seluruh indikator yang diklaim, dideduplikasi per persyaratan. Kegiatan tanpa klaim tetap memenuhi persyaratan global. Tambahan klaim setelah terlaksana memeriksa persyaratan tambahan sebelum klaim diterima; jangan membatalkan status/laporan lama secara retroaktif. Bekukan persyaratan dan bukti saat penyelesaian serta ketika versi pengukuran disahkan.
- **Dependency:** 12.2, 12.6, 13.1, 13.4.
- **DoD:** Test tanpa klaim, dua indikator dengan syarat berbeda, deduplikasi global, dan tambahan klaim setelah selesai. Kelengkapan tambahan belum terpenuhi menolak klaim baru, bukan menghapus status lama. Status tidak_terlaksana/ditunda/batal tetap hanya wajib justifikasi. Audit menangkap penolakan dan pengecualian mode file.

---

## Modul 13 — Bukti Dukung: Persyaratan, Tiga Mode & Enam Induk Lampiran

### 13.1 Migrasi `jenis_berkas` (tahap `kegiatan` + kolom mode + `semua_mode_wajib`) + CRUD granular (`jenis_berkas:create/read/update/delete`)
- **Scope:** Migrasi tabel baru `jenis_berkas`: `id`, `nama`, `tahap` enum(`rencana_aksi`,`pengukuran`,**`kegiatan`**), `indikator_id` (FK, nullable — null berarti berlaku untuk semua indikator), `wajib` (boolean, default false), `keterangan` (text, nullable), `format_diizinkan` (varchar, nullable), `ukuran_maks_kb` (int, nullable), `izinkan_file` (boolean, not null, default `true`), `izinkan_tautan` (boolean, not null, default `false`), `izinkan_teks` (boolean, not null, default `false`), `semua_mode_wajib` (boolean, not null, default `false`), `urutan` (int, not null, default 0), `aktif` (boolean, default true), `created_by`, `created_at`, `updated_at`. Validasi aplikasi saat penyimpanan: minimal satu dari `izinkan_file`/`izinkan_tautan`/`izinkan_teks` harus `true` — penyimpanan ditolak bila ketiganya `false`. CRUD (Inertia + React) bagi Perencanaan untuk mendefinisikan persyaratan bukti dukung per tahap (termasuk `kegiatan`), per indikator atau global, dengan permission granular: `jenis_berkas:create`, `jenis_berkas:read` (dipegang seluruh peran), `jenis_berkas:update`, `jenis_berkas:delete`. `jenis_berkas:update` dan `jenis_berkas:delete` bertanda `sensitif=true` — setiap aksinya mencatat `dasar_izin` pada `audit_log` (lihat 1.20).
- **Dependency:** 2.6 (indikator), 1.10, 1.11.
- **DoD:** Migrasi berjalan; kolom `tahap` menerima ketiga nilai enum termasuk `kegiatan`; percobaan menyimpan `jenis_berkas` dengan `izinkan_file=false`, `izinkan_tautan=false`, `izinkan_teks=false` sekaligus DITOLAK validasi; jenis_berkas dengan `indikator_id = null` dapat dibuat dan diverifikasi berlaku untuk semua indikator (query test); user dengan `jenis_berkas:create`/`update`/`delete` (Perencanaan/Superadmin) berhasil CRUD penuh termasuk mencentang kombinasi mode apa pun; user dengan HANYA `jenis_berkas:read` (mis. Admin, Pimpinan, Pegawai) dapat melihat daftar persyaratan namun mendapat 403 saat mencoba create/update/delete; `jenis_berkas:update` dan `jenis_berkas:delete` masing-masing menghasilkan baris `audit_log` dengan `dasar_izin` terisi; satu indikator dapat memiliki jenis_berkas bertahap `rencana_aksi`, `pengukuran`, DAN `kegiatan` sekaligus (dibuktikan test dengan 3 baris berbeda tahap untuk indikator yang sama).

### 13.2 Migrasi & model polimorfik `berkas` (kolom `mode`, `tautan`, `isi_teks`, enam induk)
- **Scope:** Migrasi tabel baru `berkas`: `id`, `jenis_berkas_id` (FK, nullable), `berkasable_type` — ENAM nilai: `rencana_aksi`/`pengukuran`/`kegiatan` (tiga induk bergerbang, mengacu `jenis_berkas`) DAN `renstra`/`renstra_pk`/`regulasi` (tiga induk dokumen dasar, TANPA `jenis_berkas` — `jenis_berkas_id` selalu NULL untuk ketiganya), `berkasable_id` (uuid), `mode` enum(`file`,`tautan`,`teks`) not null, `nama_asli` (nullable), `path` (nullable), `mime` (nullable), `ukuran_bytes` (nullable), `tautan` (varchar 2048, nullable — wajib diisi bila `mode=tautan`, divalidasi skema http/https), `isi_teks` (text, nullable — wajib diisi bila `mode=teks`), `uploaded_by`, `created_at`, `dihapus_pada` (timestamp, nullable), `dihapus_oleh` (FK, nullable), serta `menggantikan_id` dan `alasan_koreksi` untuk koreksi append-only sesuai Data Model.
- **Dependency:** 13.1, 2.1, 2.11, 2.16 (tabel induk dasar; integrasi lampiran menyusul pada task UI masing-masing induk).
- **DoD:** Migrasi berjalan; baris `berkas` dapat dibuat dengan `jenis_berkas_id = null` (lampiran bebas, WAJIB untuk ketiga induk dokumen dasar) maupun terisi (persyaratan bergerbang, hanya untuk tiga induk pertama), untuk ketiga mode; percobaan insert baris `mode=file` tanpa `path` DITOLAK aplikasi; percobaan insert baris `mode=tautan` tanpa `tautan` terisi DITOLAK aplikasi; percobaan insert baris `mode=teks` tanpa `isi_teks` terisi DITOLAK aplikasi; query berdasarkan `berkasable_type` + `berkasable_id` mengembalikan bukti dukung milik induk yang benar untuk KEENAM jenis induk (rencana_aksi, pengukuran, kegiatan, renstra, renstra_pk, regulasi), lintas ketiga mode; kolom `berkasable_type` menerima keenam nilai (test enum/CHECK constraint atau validasi aplikasi, sesuai pendekatan yang dipakai).

### 13.3 Validasi pemilihan mode oleh PIC sesuai `jenis_berkas`
- **Scope:** Guard aplikasi yang menegakkan bahwa `mode` yang dikirim PIC saat mengirim bukti dukung terhadap suatu `jenis_berkas_id` HARUS termasuk mode yang diizinkan pada baris `jenis_berkas` terkait (`izinkan_file`/`izinkan_tautan`/`izinkan_teks`); permintaan dengan mode di luar daftar yang diizinkan DITOLAK sistem. Lampiran bebas (tanpa `jenis_berkas_id`, `berkasable_type = kegiatan` atau lainnya) boleh memakai mode apa pun tanpa guard ini.
- **Dependency:** 13.2, 13.1.
- **DoD:** Test Pest: mengirim bukti dukung mode `tautan` terhadap `jenis_berkas` yang hanya `izinkan_file=true` DITOLAK dengan pesan spesifik; mengirim mode `file` terhadap `jenis_berkas` yang mengizinkan `izinkan_file` dan `izinkan_tautan` (dua-duanya `true`) BERHASIL; mengirim bukti dukung tanpa `jenis_berkas_id` (lampiran bebas) dengan mode apa pun BERHASIL tanpa guard ini diterapkan.

### 13.4 Gerbang bukti berdasarkan versi persyaratan dan pengecualian per mode
- **Scope:** Service server dipakai oleh RA, pengukuran, dan kegiatan. Bekukan persyaratan/mode/wajib pada versi pengajuan RA/pengukuran dan pada penyelesaian kegiatan. Perubahan master berlaku untuk versi/pengajuan baru; jika harus diterapkan pada versi yang sedang direviu, kembalikan dengan alasan lalu ajukan ulang. Versi tersahkan tidak menjadi tidak sah karena master berubah. Semua_mode_wajib memerlukan seluruh mode aktif yang wajib; pengecualian 13.7 menghilangkan kewajiban mode file saja, bukan mode tautan/teks.
- **Dependency:** 13.3, 13.1, 9.7.
- **DoD:** Test any-mode vs all-mode, global+indikator kegiatan, serta file+tautan all-mode saat unggahan off: tautan masih wajib. Ubah persyaratan setelah diajukan tidak mengubah versi berjalan sampai dikembalikan/diajukan ulang; hasil tersahkan tetap sah. Service yang sama dipakai semua tahap.

### 13.5 Bukti tiga mode dan akses melalui scope induk
- **Scope:** UI/pengiriman file, tautan http/https dan teks mengikuti enam induk/mode yang diizinkan serta format/ukuran dan saklar berkas.unggahan_aktif. File disimpan privat dan di-stream melalui route. Permission berkas:read/upload/delete tetap global dalam katalog. Pada jalur PIC, capability bukti diperoleh dari izin baca/mutasi induk yang sesuai tanpa grant berkas tersendiri; resolver tetap memeriksa katalog aktif, deny berkas, deny induk dan scope unit target. Izin baca pengukuran/dashboard umum tidak otomatis membuka bukti unit lain. Lampiran dokumen dasar mengikuti izin induk terkait dan aturan imutabilitas.
- **Dependency:** 13.2, 13.3, 9.7, 1.11.
- **DoD:** Test mode/ukuran/format/URL/teks kosong pada enam induk; file tidak tersedia lewat URL publik. PIC dengan grant induk unit A dapat mengakses bukti induk itu tanpa grant berkas tambahan, tetapi tidak bukti unit B. Deny berkas global/unit atau deny induk tetap menolak; permission katalog nonaktif fail-closed. Akses write memerlukan izin mutasi induk dan seluruh guard bisnis. Saklar off menolak file tetapi mode nonfile yang diizinkan tetap diterima.

### 13.6 Imutabilitas bukti dan koreksi kegiatan append-only
- **Scope:** Larangan overwrite/hapus bukti berlaku setelah RA/pengukuran disahkan, kegiatan terlaksana, Renstra aktif, jadwal PK aktif, atau regulasi masih dirujuk aktif. Sebelum batas berlaku soft-delete terotorisasi dan audit. Koreksi bukti kegiatan terlaksana memakai baris pengganti append-only dengan `menggantikan_id` dan `alasan_koreksi`; bukti lama tetap tersedia dan konteks laporan tersahkan tidak berubah. Koreksi RA/pengukuran memakai versi baru/buka kembali; setelah penutupan tahunan harus dalam scope/durasi 3.9. Lampiran dokumen dasar mengikuti koreksi resmi induknya, tanpa overwrite riwayat yang sudah dirujuk.
- **Dependency:** 13.5, 11.3, 5.9, 12.11, 2.3, 3.5, 2.17, 3.9.
- **DoD:** Test enam batas imutabilitas dan scope aktor. Hapus/overwrite setelah batas ditolak; koreksi kegiatan beralasan menambah bukti pengganti, tidak menghapus fisik/baris lama. Versi laporan lama masih menunjuk bukti lama, versi koreksi dapat menunjuk pengganti; koreksi di luar scope/durasi ditolak.

### 13.7 Pengecualian mode file saat unggahan dinonaktifkan
- **Scope:** Jika mode file wajib belum dapat dipenuhi karena unggahan off, tandai `tidak_dapat_dipenuhi` untuk kewajiban file pada induk/persyaratan, termasuk file-only dan kombinasi semua_mode_wajib. Mode tautan/teks yang masih diwajibkan tetap harus lengkap. Untuk any-mode dengan alternatif nonfile tersedia, alternatif tetap digunakan. Gerbang keempat PK mempertahankan pengecualian administratif yang eksplisit bila tidak ada lampiran dan unggahan off. Catat dan tampilkan mode/alasan pengecualian; pemulihan saklar mempengaruhi evaluasi berikutnya, bukan membatalkan versi tersahkan.
- **Dependency:** 9.7, 10.1, 13.1, 13.2.
- **DoD:** File-only tanpa bukti dapat lewat dengan penanda. File+tautan all-mode hanya lewat jika tautan ada; file+teks all-mode tetap wajib teks; any-mode nonfile yang tersedia masih harus dipenuhi. Gerbang PK dengan pengecualian resmi dapat lolos dan tercatat. Saklar aktif kembali memulihkan kewajiban file pada evaluasi baru, histori keputusan tetap utuh.

### 13.8 Lampiran bebas di level kegiatan (dan induk lain) + lampiran murni pada tiga induk dokumen dasar
- **Scope:** Memastikan bukti dukung dengan `jenis_berkas_id = null` (lampiran bebas, bukan persyaratan bergerbang) berfungsi pada TIGA induk bergerbang (`rencana_aksi`/`pengukuran`/`kegiatan`) sebagai pelengkap — tidak pernah menghalangi/menggerbangi transisi status induknya, dan tampil pada rekapitulasi (8.3); DAN memastikan bahwa pada TIGA induk dokumen dasar (`renstra`/`renstra_pk`/`regulasi`), lampiran SELALU tanpa `jenis_berkas_id` (tidak ada persyaratan bernama untuk ketiganya) — `renstra`/`regulasi` murni pelengkap tanpa gerbang apa pun, sementara `renstra_pk` memiliki gerbang keempat aktivasi jadwal (3.5/13.7) yang berbeda mekanismenya dari gerbang `jenis_berkas` (tidak memakai kolom `wajib`/`semua_mode_wajib`, cukup "ada minimal satu baris `berkas`").
- **Dependency:** 13.5, 12.2, 2.17, 2.18, 2.20; integrasi rekap diuji setelah 8.3.
- **DoD:** Test Pest: pengiriman bukti dukung (mode apa pun) untuk induk kegiatan tanpa `jenis_berkas_id` berhasil; transisi status kegiatan (12.3, 12.11) tidak pernah tergantung/terblokir oleh ada-tidaknya lampiran bebas; rekapitulasi 8.3 menampilkan daftar lampiran bebas yang melekat pada kegiatan yang diklaim pada baris indikator × periode terkait; percobaan mengirim `jenis_berkas_id` terisi (bukan null) untuk `berkasable_type = renstra`/`renstra_pk`/`regulasi` DITOLAK validasi aplikasi (ketiga induk ini tidak memiliki persyaratan bernama); lampiran `renstra`/`regulasi` tidak pernah menggerbangi transisi status apa pun (test eksplisit: Renstra tanpa lampiran tetap dapat diaktifkan selama `dasar_hukum` terisi); lampiran `renstra_pk` HANYA menggerbangi aktivasi Jadwal Tahunan (3.5), tidak menggerbangi transisi lain.

### 13.9 Audit mode & sumber bukti dukung
- **Scope:** Memastikan setiap pengiriman bukti dukung mencatat `mode` dan sumbernya pada `audit_log`: untuk `file` — nama asli, ukuran, mime; untuk `tautan` — nilai tautan tersimpan utuh pada baris `berkas`, dicatat sebagai perubahan biasa (bukan disensor); untuk `teks` — panjang teks (jumlah karakter), BUKAN salinan isi teksnya. Perubahan/penetapan persyaratan `jenis_berkas` (termasuk mode yang diizinkan, `wajib`, `semua_mode_wajib`) dicatat dengan `nilai_lama`/`nilai_baru` (lihat 13.1).
- **Dependency:** 13.5, 10.1.
- **DoD:** Test Pest: mengirim bukti dukung mode `file` menghasilkan baris `audit_log` dengan detail nama_asli/ukuran/mime; mengirim mode `tautan` menghasilkan baris audit dengan nilai tautan tersimpan; mengirim mode `teks` menghasilkan baris audit yang mencatat panjang teks TANPA menyalin isi_teks ke kolom audit (dibuktikan lewat assertion bahwa `audit_log` untuk peristiwa ini tidak memuat isi teks aslinya secara utuh, hanya metadata panjang).

---

## Modul 14 — Alert Kontekstual & Notifikasi (In-App & Eksternal)

### 14.1 Alert Kontekstual & Notifikasi In-App (Baseline Scope MVP)
- **Scope:** Implementasi komponen alert kontekstual dan notifikasi dalam aplikasi yang dievaluasi di server dan dikirimkan via shared props Inertia: (1) Banner pengingat kontekstual pada dashboard/halaman kerja terkait batas waktu pengisian aktif (`pengisian_selesai`), jendela penyusunan rencana aksi (`rencana_aksi_selesai`), daftar indikator yang masih belum terisi ("Belum Mengisi" / "Tidak Mengisi"), dan penanda persyaratan bukti dukung `tidak_dapat_dipenuhi` (13.7); (2) Indikator lonceng notifikasi pada navbar header dengan badge counter angka belum dibaca (*unread count*), dropdown riwayat notifikasi (notifikasi pengembalian rencana aksi/pengukuran beserta cuplikan catatan revisi bagi PIC, notifikasi pengajuan baru bagi Perencanaan, dan notifikasi log akses bagi Admin); (3) Penyajian daftar tugas & aksi tertunda (*task list / action items*) pada dashboard masing-masing peran.
- **Dependency:** Modul 1 (autentikasi & akses), Modul 5 (pengukuran), Modul 6 (reviu), Modul 7 (dashboard), Modul 11 (rencana aksi), Modul 13 (bukti dukung).
- **DoD:** Test Pest & Feature: halaman beranda PIC menampilkan banner countdown hari tersisa saat jadwal periode aktif; navbar header menampilkan badge counter notifikasi yang bertambah saat Perencanaan mengembalikan pengukuran milik PIC; membuka dropdown notifikasi menampilkan cuplikan alasan pengembalian dan tautan langsung ke halaman perbaikan; dashboard Perencanaan menampilkan antrean pengajuan baru; seluruh logika alert dihitung di server (tidak ada evaluasi status/tenggat yang dihitung manual di React).

### 14.2 Integrasi Saluran Notifikasi Eksternal (WhatsApp & Email Gateway) — Target Sebelum 9 November 2026
- **Scope:** Pembangunan integrasi saluran pengingat eksternal (WhatsApp Gateway via HTTP API dan Email via Laravel Mail/Notification) yang diselesaikan di bagian akhir sebelum batas waktu 9 November 2026: (1) Notification Channel / Service untuk pengiriman pesan WhatsApp dan Email; (2) Background job broadcast otomatis saat jadwal pengisian triwulan resmi dibuka (`jadwal_periode.pengisian_mulai`) ke seluruh PIC unit kerja terkait; (3) Scheduled command harian (Early Warning System / EWS) yang dijalankan scheduler Laravel setiap hari untuk mengirim pengingat berjenjang hanya pada H-7, H-3, dan H-1 tenggat `pengisian_selesai` ke WhatsApp dan Email PIC yang indikatornya masih berstatus Draft / Belum Mengisi; scheduler mengecek setiap hari, tetapi tidak dispatch pengingat PIC pada H-6/H-5/H-4/H-2; (4) Job pengiriman pesan rekapitulasi status pengisian ke WhatsApp/Email Tim Perencanaan (H-3 dan H-1); (5) Job notifikasi instan saat pengembalian berkas pengukuran/rencana aksi beserta catatan revisi wajib ke WhatsApp/Email PIC. Parameter integrasi memakai kontrak grup notifikasi pada PRD/Data Model (saklar, gateway, template dan daftar H-minus), dengan API token/credential dilindungi dan tidak tampil di log/audit/props biasa. Daftar H-minus awal adalah 7,3,1. Provider, kredensial, sumber kontak, worker/scheduler dan pemilik operasional wajib tersedia sebelum uji integrasi nyata, sesuai P.4; jangan menyalin konfigurasi SIMPEG.
- **Dependency:** 14.1, Modul 3 (jadwal_periode), Modul 9 (pengaturan grup notifikasi), 1.3 dan kontrak sumber kontak PIC yang disediakan pemilik operasional (jangan mengasumsikan nomor telepon tersedia pada claim Keycloak).
- **DoD:** Test Pest: simulasi tanggal pengisian_mulai memicu dispatch job broadcast ke antrean (`Queue::fake()`); aktivasi jauh sebelum tanggal tersebut tidak mengirim broadcast pembukaan lebih awal; scheduler EWS harian memfilter daftar PIC dengan pengukuran belum diajukan pada periode aktif dan mendispatch notifikasi WhatsApp/Email sesuai template; test tanggal membuktikan pengiriman PIC hanya H-7/H-3/H-1, pemeriksaan scheduler berulang tidak mengirim duplikat untuk penerima/peristiwa/periode/channel yang sama; aksi pengembalian pengukuran mendispatch notifikasi instan ke antrean; kegagalan respon dari WhatsApp Gateway atau mail server tidak menggagalkan transaksi status pengukuran di database (status gagal dicatat tanpa rahasia dan retry terkontrol; response accepted provider tidak diklaim sebagai delivered).

---

## Seed Data Pengembangan/Testing

### S.1 Seeder Laravel — data minimal validasi model
- **Scope:** Seeder (seeder demo khusus, terpisah dari seeder konfigurasi produksi) yang membuat ≥1 regulasi contoh (jenis kepmen, dengan lampiran dokumen mode file), 1 Renstra contoh (dengan dasar_hukum terisi, `regulasi_id` merujuk regulasi contoh, dan lampiran dokumen Renstra), beberapa Sasaran, beberapa Indikator (lintas ≥2 unit, dengan variasi `arah` naik_baik dan turun_baik, variasi `tipe_perhitungan` mencakup ketiganya — manual, rasio_persen dengan `indikator_komponen` lengkap, penjumlahan dengan `indikator_komponen` lengkap, dan minimal satu indikator dengan `regulasi_id` terisi), Target Tahunan (dengan baseline) untuk tahun berjalan, 1 renstra_pk (dengan MINIMAL SATU lampiran dokumen PK — syarat gerbang keempat aktivasi jadwal), 1 Jadwal Tahunan (hingga status aktif lewat KEEMPAT gerbang, dengan `jadwal_periode` dan jendela `rencana_aksi_mulai/selesai` tersusun, snapshot + snapshot komponen terbentuk), ≥1 Rencana Aksi berstatus `disahkan` dengan `rencana_aksi_target` terisi lengkap, ≥2 Kegiatan (salah satu berstatus `tidak_terlaksana` dengan justifikasi, salah satu berstatus `terlaksana` dengan bukti dukung tahap kegiatan lengkap, salah satu hasil geser periode dengan `kegiatan_asal_id` terisi), ≥1 Klaim Kegiatan, ≥1 `jenis_berkas` per tahap (rencana_aksi/pengukuran/kegiatan) dengan minimal satu `wajib=true` per tahap dan variasi mode (minimal satu bermode file-only, satu bermode kombinasi file+tautan dengan `semua_mode_wajib=true`), ≥1 baris `berkas` per mode (file/tautan/teks) TERSEBAR pada KEENAM induk `berkasable_type` (rencana_aksi, pengukuran, kegiatan, renstra, renstra_pk, regulasi), beberapa user dengan **kelima** peran (superadmin, admin, perencanaan, pimpinan, pegawai). **Ini murni untuk pengembangan/testing, bukan data produksi** — tahun awal, periode historis, daftar PIC, indikator dan data awal diserahkan Perencanaan serta disetujui PM sebelum digunakan; data riil dimasukkan manual lewat alur aplikasi/backfill yang berlaku, tanpa fitur impor baru.
- **Dependency:** Migrasi model yang digunakan tersedia (Modul 1/2/3/4/5/6/9/11/12/13); fixture disiapkan bertahap dan validasi alur lengkap dituntaskan setelah fitur terkait tersedia, termasuk 3.5, 5.9, 11.3, 12.11 dan 14.1.
- **DoD:** Pada database development/testing disposable yang telah diverifikasi (bukan database produksi), `php artisan migrate:fresh --seed` berjalan tanpa error dan menghasilkan: ≥1 regulasi dengan lampiran dokumen, ≥1 Renstra berstatus aktif dengan `regulasi_id` terisi dan lampiran dokumen Renstra, ≥1 indikator dengan `regulasi_id` terisi, ≥1 jadwal_tahunan berstatus aktif (lolos KEEMPAT gerbang termasuk lampiran PK) dengan ≥1 baris `jadwal_periode`, jendela rencana aksi terisi, dan `jadwal_snapshot`+`jadwal_snapshot_komponen` terbentuk sejumlah indikator/komponen seed, ≥1 Rencana Aksi berstatus `disahkan`, ≥2 Kegiatan dengan variasi status termasuk geser periode dan minimal satu `terlaksana` dengan bukti dukung terpenuhi, ≥1 Klaim Kegiatan, ≥1 `jenis_berkas` wajib per tahap (termasuk `kegiatan`) dengan variasi mode, ≥1 baris `berkas` per mode (file/tautan/teks) yang dapat diverifikasi lewat query TERSEBAR pada keenam induk `berkasable_type` (dibuktikan query `GROUP BY berkasable_type` mengembalikan ≥1 baris untuk masing-masing dari keenam nilai), ≥1 user per **kelima** peran (superadmin, admin, perencanaan, pimpinan, pegawai) yang dapat dipakai untuk login uji manual, dan seluruh kunci `pengaturan` default (9.2, 9.7) sudah terisi.

---

## Pra-syarat Lingkungan & Aturan Penempatan Logika

### P.1 Kebutuhan lingkungan VPS

Sebelum rilis pertama dapat berjalan, VPS penempatan SAKIP wajib menyediakan:

- **PHP** versi sesuai kebutuhan Laravel 13 (rilis PHP terbaru yang didukung Laravel 13 pada saat instalasi), beserta ekstensi standar yang dibutuhkan Laravel (mis. `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`).
- **PostgreSQL** sebagai basis data utama, dengan akses jaringan/kredensial khusus aplikasi SAKIP (bukan akun bersama dengan aplikasi lain).
- **Web server** (Nginx/Apache) yang mengarahkan permintaan ke `public/index.php` sesuai konvensi Laravel, dengan konfigurasi HTTPS untuk lalu lintas produksi.
- **Bun** terpasang sebagai runtime/toolchain build asset frontend — dipakai untuk `bun install` dan `bun run build` (Vite dijalankan lewat Bun). **Node.js/npm tidak dipasang sebagai jalur build** pada lingkungan ini.
- **Client Keycloak khusus SAKIP** (client_id terpisah dari aplikasi lain di lingkungan LLDIKTI Wilayah XVI), dikonfigurasi untuk OIDC Authorization Code Flow berbasis session.
- **Penyimpanan berkas** pada path `storage/app/berkas/...` di disk **privat** (bukan disk `public`) — akses file hanya lewat route ber-permission (streamed download), tidak pernah lewat URL langsung. Kapasitas disk untuk bukti dukung mode `file` dipantau memakai panel "Penggunaan Penyimpanan Bukti Dukung" (9.8); mode `tautan`/`teks` tidak memakai storage aplikasi sama sekali, sehingga kebijakan grup `berkas` (9.7) dapat dipakai sebagai katup pengaman bila kapasitas disk mendekati batas.

### P.2 Langkah rilis (deploy/migrasi aset)

Setiap rilis (deploy) WAJIB menjalankan urutan berikut pada build asset frontend, sebagai pengganti langkah npm konvensional:

1. `bun install` — memasang dependency frontend sesuai `bun.lock`/`bun.lockb` yang di-commit ke repositori (bukan `package-lock.json`).
2. `bun run build` — mengompilasi asset React/TypeScript lewat Vite, menghasilkan berkas produksi di `public/build`.
3. Setelah backup dan kesiapan rollback diverifikasi, jalankan migrasi produksi yang direview serta hanya seeder konfigurasi idempoten Modul 1/9 yang telah disetujui. Seeder data contoh S.1 dan `migrate:fresh` dilarang pada deployment produksi; seeder tidak boleh menimpa konfigurasi operasional existing.

Skrip CI/CD atau instruksi deploy manual **tidak boleh** memanggil `npm install`/`npm run build` sebagai jalur utama; bila ada tooling pihak ketiga yang secara internal mensyaratkan Node.js runtime (bukan sebagai package manager pilihan proyek), itu dicatat sebagai pengecualian eksplisit dengan alasannya, bukan default diam-diam.

### P.3 Aturan penempatan logika (mengikat seluruh modul)

Aturan berikut berlaku di seluruh Plan ini dan menjadi rujukan wajib bagi siapa pun yang mendelegasikan atau mengerjakan task:

- **Otorisasi** (resolusi izin peran/grant/deny, §19 Workflow, task 1.11/1.17) **HANYA** dievaluasi di server — Gate/Policy/service layer Laravel. Komponen React tidak pernah menghitung ulang hasil resolusi izin; ia hanya menerima flag `can.*` dari props Inertia untuk menyembunyikan elemen UI.
- **Validasi bisnis** (jendela waktu jadwal/periode/rencana aksi, status alur, kepemilikan unit, gerbang kelengkapan rencana aksi/komponen/bukti dukung tiga tahap) **HANYA** dijalankan di server — Form Request atau service layer, terpisah dari lapisan otorisasi (lihat §19 Workflow butir 7). Server selalu mengevaluasi ulang validasi ini pada SETIAP permintaan, termasuk permintaan yang membypass tombol UI.
- **Perhitungan nilai indikator** (mesin perhitungan komponen, 5.13/5.17) **HANYA** dihitung di server; nilai turunan yang dikirim ke klien murni untuk ditampilkan, tidak pernah diterima balik sebagai input yang dipercaya (dibuktikan test 5.14 yang menolak payload custom pada `pengukuran.nilai`).
- **Penulisan audit** (`AuditLogger::catat(...)`, Modul 10) **HANYA** dipanggil dari kode server pada titik transisi/mutasi data yang relevan — tidak pernah dari sisi klien, dan tidak ada endpoint yang menerima payload audit siap-pakai dari luar.
- **Konsekuensi untuk delegasi coding:** setiap brief task yang didelegasikan (mis. ke agen coding eksternal) WAJIB menyebutkan secara eksplisit di mana logika diletakkan — "server: Form Request X" atau "server: Policy Y" untuk otorisasi/validasi bisnis, dan "klien: hanya menampilkan hasil dari props Z" untuk UI — sehingga tidak ada ambiguitas yang berujung logika bisnis/izin bocor ke komponen React.

---

### P.4 Kesiapan operasional, data awal, dan penerimaan rilis
- **Scope:** PM menetapkan nama owner untuk domain/DNS/TLS, VPS/database/storage/backup-restore, Keycloak client dan pemetaan akun, WhatsApp/email provider, sumber kontak, worker/scheduler, pemantauan serta dukungan insiden. Tim Perencanaan menyiapkan tahun/periode awal, data master/PIC, definisi operasional indikator dan contoh laporan UAT. Tim teknis memverifikasi konfigurasi khusus SAKIP dan menjalankan uji dengan bukti. Keputusan yang belum tersedia tetap blocker pada jalur terkait; tidak diisi memakai asumsi SIMPEG.
- **Dependency:** P.1, P.2, 1.2, 8.4, 14.2 serta penyerahan parameter oleh PM/Perencanaan.
- **DoD:** Owner dan akses yang diperlukan tercatat; backup/restore dan deployment terverifikasi pada environment tujuan; konfigurasi seed terpisah dari demo seed; uji integrasi membedakan acceptance provider dan delivery. Siap UAT, evaluasi 16–30 November 2026, hasil penerimaan UAT, dan persetujuan production memiliki bukti/status terpisah; go-live tidak diklaim dari selesainya coding.

---

## Ringkasan Urutan Eksekusi Modul

Nomor modul adalah pengelompokan domain, bukan instruksi menyelesaikan seluruh Modul 1 lalu seluruh Modul 2 secara kaku. Task fondasi dan integrasi lintas modul mengikuti dependency masing-masing; test integrasi dijalankan setelah kedua sisi tersedia.

| Gelombang | Task/modul | Hasil yang diperlukan |
|---|---|---|
| 1 | Fondasi Modul 1, 10.1, 9.1–9.3/9.7 | Autentikasi, permission, audit dasar, dan konfigurasi seed tersedia; UI/test alur pada Modul 1 menyusul integrasi |
| 2 | Fondasi master Modul 2; 3.1–3.4/3.6; 4.1–4.4; 5.1/5.13/5.15; 11.1; 12.1; 13.1–13.5/13.7 | Skema dan layanan dasar untuk periode, snapshot, penugasan, RA/pengukuran, kegiatan, bukti dukung dan lampiran PK |
| 3 | Integrasi 2.17/2.18/2.20; 3.5/3.7/3.9/3.12 | Empat gerbang aktivasi, snapshot awal, dan pengaturan jadwal/koreksi siap |
| 4 | Modul 11, 12, 13 serta 1.21 | Penyusunan/pengesahan RA, target manual/komponen, kegiatan/klaim dan versi bukti; F1/F2 dibuktikan bersama transisi |
| 5 | Sisa Modul 5/6; 3.8/3.11 | Pengukuran, konteks versi resmi, koreksi snapshot, status capaian per versi, dan backfill |
| 6 | Modul 7/8, sisa Modul 9/10, 14.1 | Dashboard/laporan historis, rekomendasi, UI administrasi/audit, serta notifikasi in-app |
| 7 | 14.2 dan P.4 | Integrasi WhatsApp/email sebelum 9 November 2026; bukti UAT/evaluasi/penerimaan dan kesiapan operasional dipisahkan |
| Lintas gelombang | S.1 pada environment disposable | Fixture pengembangan tersedia sesuai migrasi/fitur yang sudah ada; tidak dipakai sebagai seed production |

---

## 15. Fase Lanjutan (Belum Termasuk MVP)

Daftar cakupan yang secara sengaja tidak dibangun pada Fase Awal, dicantumkan sebagai catatan cakupan masa depan tanpa breakdown granular (akan dituangkan dalam dokumen pengembangan lanjutan terpisah):

- **Approval Pimpinan** dalam alur pengesahan pengukuran — mengaktifkan pemakaian kolom `jadwal_tahunan.pakai_persetujuan_pimpinan`, `persetujuan_mulai`, `persetujuan_selesai`, serta permission `pengukuran:setujui` dan peran aktif Pimpinan dalam state machine status alur.
- **Ekspor PDF** untuk rekap laporan formal (mis. lampiran LKj).
- **Ekspor gambar grafik** dashboard (mis. PNG/SVG dari ApexCharts) untuk disisipkan ke dokumen/presentasi.
- **Integrasi status capaian otomatis** dari sistem sumber data eksternal — mengaktifkan pemakaian `status_capaian.sumber = data_sumber` dan jalur `ditetapkan_oleh = NULL`, termasuk desain integrasi/job/API yang relevan.
- **UI matrix permission penuh** — antarmuka yang menampilkan dan memungkinkan pencentangan bebas seluruh permission katalog per pengguna, menggantikan kebutuhan seeder/query manual untuk kasus edge di luar preset form Fase Awal.
- **Impor data massal** dari sumber eksternal (Excel/sistem lain) — belum diputuskan masuk fase mana pun; memerlukan keputusan produk tersendiri jika dibutuhkan di masa depan.
- **Revisi komitmen target PK di tengah tahun** — tetap di luar jalur normal MVP. Koreksi kesalahan yang didukung bukti resmi memakai versi snapshot baru 3.8 dan koreksi terbatas 3.9; bukan overwrite target historis. Revisi target operasional RA tetap memakai 11.7.
- **Mesin formula bertingkat generik** — tetap deferred. IKU 3 masuk MVP sebagai satu penjumlahan lima input berkoefisien 0,5 dengan subtotal SAKIP untuk tampilan, sehingga tidak memerlukan mesin bertingkat atau input gabungan manual.
- **Approval Pimpinan atas Rekomendasi Pimpinan** — pada Fase Awal, Rekomendasi Pimpinan diisi oleh Perencanaan (permission `rekomendasi:tetapkan`); pengalihan hak pengisian ke Pimpinan sendiri, berikut alur approval-nya, adalah pengembangan lanjutan yang menunggu integrasi dengan Approval Pimpinan di atas.

---

