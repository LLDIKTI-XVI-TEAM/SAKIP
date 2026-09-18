# DATA MODEL — SAKIP LLDIKTI Wilayah XVI

**Status dokumen:** Versi konsolidasi draf — menunggu pembahasan bersama Tim Perencanaan. 33 entitas, skema lengkap dirancang sejak Fase Awal (MVP), mencakup alur penuh dasar aturan (regulasi) → Renstra → Perjanjian Kinerja → jadwal & periode → rencana aksi → kegiatan → pengukuran berbasis komponen → rekomendasi Pimpinan → status capaian, serta model hak akses **RBAC dengan pengecualian eksplisit** (peran, grant, deny) yang dievaluasi saat request.

**Basis data target:** PostgreSQL — dipilih secara sadar karena beberapa kapabilitas yang dipakai langsung oleh skema ini: tipe kolom `jsonb` untuk `audit_log` (menampung struktur nilai lama/baru yang berbeda-beda per entitas tanpa memerlukan tabel audit terpisah per entitas), **exclusion constraint** (`EXCLUDE USING gist` dengan ekstensi `btree_gist`) sebagai lapisan pertahanan kedua untuk menegakkan rentang tahun Renstra yang tidak boleh beririsan, **partial unique index** untuk menjamin tepat satu `jadwal_tahunan` berstatus aktif per kombinasi Renstra-tahun, dan penanganan **NULL pada index unik** lewat `COALESCE` untuk constraint yang melibatkan kolom nullable — dipakai pada `klaim_kegiatan.komponen_id` maupun pada `user_permission_granted.unit_id`/`user_permission_denials.unit_id`, karena PostgreSQL memperlakukan `NULL` sebagai nilai berbeda antarbaris pada unique index standar.

**Catatan cakupan:** Seluruh tabel dan kolom pada dokumen ini dibuat pada migrasi Laravel sejak Fase Awal, termasuk keenam tabel model hak akses (`permissions`, `roles`, `role_permissions`, `user_roles`, `user_permission_granted`, `user_permission_denials`). Yang ditunda ke Fase Lanjutan hanya jalur pemakaian/UI atas kolom-kolom tertentu; kolom itu sendiri tetap ada di skema agar tidak perlu migrasi besar/berisiko di kemudian hari, dan diberi anotasi eksplisit `-- (kolom tersedia, jalur pengisian/pemakaian menyusul fase lanjutan)`.

---

## 1. Diagram Relasi Entitas (ERD)

```mermaid
erDiagram
    USERS ||--o{ USER_ROLES : "memiliki"
    USERS ||--o{ USER_PERMISSION_GRANTED : "menerima grant"
    USERS ||--o{ USER_PERMISSION_DENIALS : "menerima deny"
    USERS ||--o{ PENANGGUNG_JAWAB : "ditugaskan sebagai"
    USERS ||--o{ PENGUKURAN : "membuat/mengubah"
    USERS ||--o{ STATUS_CAPAIAN : "menetapkan"
    USERS ||--o{ AUDIT_LOG : "melakukan tindakan"
    USERS ||--o{ RENSTRA : "membuat"
    USERS ||--o{ REGULASI : "membuat"
    USERS ||--o{ UNIT : "membuat"
    USERS ||--o{ PENGATURAN : "mengubah"
    USERS ||--o{ RENCANA_AKSI : "membuat/mengesahkan"
    USERS ||--o{ KEGIATAN : "membuat"
    USERS ||--o{ KLAIM_KEGIATAN : "membuat"
    USERS ||--o{ INDIKATOR_KOMPONEN : "membuat/mengubah"
    USERS ||--o{ JENIS_BERKAS : "membuat/mengubah"
    USERS ||--o{ BERKAS : "mengunggah"
    USERS ||--o{ REKOMENDASI_PIMPINAN : "menetapkan"

    ROLES ||--o{ USER_ROLES : "dipegang oleh"
    ROLES ||--o{ ROLE_PERMISSIONS : "berisi"
    PERMISSIONS ||--o{ ROLE_PERMISSIONS : "menjadi isi peran"
    PERMISSIONS ||--o{ USER_PERMISSION_GRANTED : "diberikan"
    PERMISSIONS ||--o{ USER_PERMISSION_DENIALS : "dicabut"

    UNIT ||--o{ INDIKATOR : "memiliki"
    UNIT ||--o{ USER_PERMISSION_GRANTED : "menjadi scope (nullable)"
    UNIT ||--o{ USER_PERMISSION_DENIALS : "menjadi scope (nullable)"
    UNIT ||--o{ JADWAL_SNAPSHOT : "salinan konteks"
    UNIT ||--o{ KEGIATAN : "memiliki"

    RENSTRA ||--o{ SASARAN : "memiliki"
    RENSTRA ||--o{ RENSTRA_PK : "memiliki PK per tahun"
    RENSTRA ||--o{ JADWAL_TAHUNAN : "memiliki jadwal per tahun"

    SASARAN ||--o{ INDIKATOR : "memiliki"

    INDIKATOR ||--o{ TARGET_TAHUNAN : "memiliki target per tahun"
    INDIKATOR ||--o{ PENANGGUNG_JAWAB : "memiliki riwayat PJ"
    INDIKATOR ||--o{ JADWAL_SNAPSHOT : "dibekukan ke"
    INDIKATOR ||--o{ PENGUKURAN : "diukur pada"
    INDIKATOR ||--o{ RENCANA_AKSI : "memiliki rencana aksi per tahun"
    INDIKATOR ||--o{ INDIKATOR_KOMPONEN : "memiliki komponen"
    INDIKATOR ||--o{ JENIS_BERKAS : "membatasi jenis berkas (nullable)"
    INDIKATOR ||--o{ REKOMENDASI_PIMPINAN : "menerima rekomendasi"

    RENSTRA_PK ||--o{ JADWAL_TAHUNAN : "direferensikan saat aktivasi"
    RENSTRA_PK ||--o{ BERKAS : "berkasable (polimorfik)"
    REGULASI ||--o{ RENSTRA : "menjadi dasar hukum (nullable)"
    REGULASI ||--o{ INDIKATOR : "menjadi dasar hukum (nullable)"
    REGULASI ||--o{ BERKAS : "berkasable (polimorfik)"
    RENSTRA ||--o{ BERKAS : "berkasable (polimorfik)"

    PERIODE ||--o{ PENGUKURAN : "menjadi satuan waktu"
    PERIODE ||--o{ JADWAL_PERIODE : "direferensikan"
    PERIODE ||--o{ RENCANA_AKSI_TARGET : "menjadi satuan waktu target"
    PERIODE ||--o{ KEGIATAN : "menjadi periode rencana"
    PERIODE ||--o{ REKOMENDASI_PIMPINAN : "menjadi periode evaluasi"

    JADWAL_TAHUNAN ||--o{ JADWAL_PERIODE : "menentukan periode diharapkan"
    JADWAL_TAHUNAN ||--o{ JADWAL_SNAPSHOT : "menghasilkan (saat aktivasi)"
    JADWAL_TAHUNAN ||--o{ RENCANA_AKSI : "menaungi"

    JADWAL_SNAPSHOT ||--o{ PENGUKURAN : "menjadi konteks beku"
    JADWAL_SNAPSHOT ||--o{ JADWAL_SNAPSHOT_KOMPONEN : "membekukan komponen"

    RENCANA_AKSI ||--o{ RENCANA_AKSI_TARGET : "memiliki target per periode per komponen"
    RENCANA_AKSI ||--o{ KLAIM_KEGIATAN : "menerima klaim"
    RENCANA_AKSI ||--o{ BERKAS : "berkasable (polimorfik)"

    INDIKATOR_KOMPONEN ||--o{ RENCANA_AKSI_TARGET : "menjadi target"
    INDIKATOR_KOMPONEN ||--o{ PENGUKURAN_KOMPONEN : "diukur pada"
    INDIKATOR_KOMPONEN ||--o{ KLAIM_KEGIATAN : "menjadi sasaran dampak (nullable)"

    KEGIATAN ||--o{ KLAIM_KEGIATAN : "diklaim"
    KEGIATAN ||--o{ BERKAS : "berkasable (polimorfik, bukti SPJ & lampiran bebas)"
    KEGIATAN ||--o{ KEGIATAN : "digeser dari (kegiatan_asal_id)"

    PENGUKURAN ||--o{ STATUS_CAPAIAN : "memiliki status akhir"
    PENGUKURAN ||--o{ PENGUKURAN_KOMPONEN : "memiliki nilai komponen"
    PENGUKURAN ||--o{ KLAIM_KEGIATAN : "sumber klaim (nullable)"
    PENGUKURAN ||--o{ BERKAS : "berkasable (polimorfik)"

    JENIS_BERKAS ||--o{ BERKAS : "menentukan jenis, tahap & mode yang diizinkan (nullable)"

    USERS {
        uuid id PK
        string keycloak_id UK
        string nama
        string email
    }

    UNIT {
        uuid id PK
        string nama
        enum status
        uuid created_by FK
        timestamp created_at
    }

    PERMISSIONS {
        uuid id PK
        varchar kode UK
        varchar entitas
        varchar aksi
        text keterangan "nullable"
        enum butuh_scope
        boolean sensitif
        boolean aktif
        timestamp created_at
        timestamp updated_at
    }

    ROLES {
        uuid id PK
        varchar kode UK
        string nama
        text keterangan "nullable"
        boolean is_sistem
        int urutan
        boolean aktif
    }

    ROLE_PERMISSIONS {
        uuid id PK
        uuid role_id FK
        uuid permission_id FK
        timestamp created_at
    }

    USER_ROLES {
        uuid id PK
        uuid user_id FK
        uuid role_id FK
        uuid diberikan_oleh FK
        timestamp created_at
    }

    USER_PERMISSION_GRANTED {
        uuid id PK
        uuid user_id FK
        uuid permission_id FK
        uuid unit_id FK "nullable"
        text alasan
        uuid diberikan_oleh FK
        timestamp created_at
    }

    USER_PERMISSION_DENIALS {
        uuid id PK
        uuid user_id FK
        uuid permission_id FK
        uuid unit_id FK "nullable"
        text alasan
        uuid ditetapkan_oleh FK
        timestamp created_at
    }

    REGULASI {
        uuid id PK
        enum jenis
        varchar nomor
        int tahun
        text tentang
        date tanggal "nullable"
        varchar tautan_sumber "nullable"
        text catatan "nullable"
        boolean aktif
        uuid created_by FK
        timestamp created_at
        timestamp updated_at
    }

    RENSTRA {
        uuid id PK
        string nama
        text keterangan
        text dasar_hukum
        uuid regulasi_id FK "nullable"
        int tahun_mulai
        int tahun_akhir
        enum status
        uuid created_by FK
        timestamp created_at
        timestamp updated_at
    }

    RENSTRA_PK {
        uuid id PK
        uuid renstra_id FK
        int tahun
        string nomor_pk
        date tanggal_pk
        uuid created_by FK
        timestamp created_at
        timestamp updated_at
    }

    SASARAN {
        uuid id PK
        uuid renstra_id FK
        string nama
        text keterangan
        int urutan
    }

    INDIKATOR {
        uuid id PK
        uuid sasaran_id FK
        uuid unit_id FK
        uuid regulasi_id FK "nullable"
        string nama
        text definisi
        string satuan
        smallint presisi
        smallint desimal_tampilan
        enum arah
        enum tipe_perhitungan
        int tahun_mulai_berlaku
        enum status
        boolean wajib_catatan
        uuid created_by FK
        string created_by_role
        timestamp created_at
        timestamp updated_at
    }

    TARGET_TAHUNAN {
        uuid id PK
        uuid indikator_id FK
        int tahun
        numeric nilai "nullable"
        numeric baseline "nullable"
        uuid updated_by FK
        timestamp updated_at
    }

    PERIODE {
        uuid id PK
        string nama
        int urutan
        boolean aktif
        boolean is_nilai_akhir
    }

    JADWAL_TAHUNAN {
        uuid id PK
        uuid renstra_id FK
        int tahun
        boolean pakai_persetujuan_pimpinan
        date persetujuan_mulai "nullable"
        date persetujuan_selesai "nullable"
        date rencana_aksi_mulai "nullable"
        date rencana_aksi_selesai "nullable"
        date penutupan
        enum status
        uuid renstra_pk_id FK
        timestamp activated_at "nullable"
        timestamp closed_at "nullable"
    }

    JADWAL_PERIODE {
        uuid id PK
        uuid jadwal_id FK
        uuid periode_id FK
        date pengisian_mulai
        date pengisian_selesai
        date reviu_mulai
        date reviu_selesai
    }

    JADWAL_SNAPSHOT {
        uuid id PK
        uuid jadwal_id FK
        uuid indikator_id FK
        uuid unit_id
        string nama
        text definisi
        string satuan
        smallint presisi
        smallint desimal_tampilan
        enum arah
        enum tipe_perhitungan
        numeric target "nullable"
        numeric baseline "nullable"
    }

    JADWAL_SNAPSHOT_KOMPONEN {
        uuid id PK
        uuid jadwal_snapshot_id FK
        varchar kode
        text label
        enum peran
        numeric bobot
        int urutan
    }

    PENANGGUNG_JAWAB {
        uuid id PK
        uuid indikator_id FK
        uuid user_id FK
        date tanggal_mulai_berlaku
        uuid ditetapkan_oleh FK
        text alasan "nullable"
        timestamp created_at
    }

    RENCANA_AKSI {
        uuid id PK
        uuid indikator_id FK
        int tahun
        uuid unit_id FK
        uuid jadwal_tahunan_id FK
        uuid penanggung_jawab_id FK
        text uraian "nullable"
        enum status_alur
        int versi
        text alasan_revisi "nullable"
        uuid created_by FK
        timestamp created_at
        timestamp updated_at
        timestamp disahkan_at "nullable"
        uuid disahkan_by FK "nullable"
    }

    RENCANA_AKSI_TARGET {
        uuid id PK
        uuid rencana_aksi_id FK
        uuid periode_id FK
        uuid komponen_id FK
        numeric nilai "nullable"
        text keterangan "nullable"
        uuid updated_by FK
        timestamp updated_at
    }

    KEGIATAN {
        uuid id PK
        uuid unit_id FK
        int tahun
        uuid periode_id FK
        string nama
        text tujuan
        int sasaran_peserta "nullable"
        varchar keterangan_peserta "nullable"
        varchar lokasi "nullable"
        date tanggal_rencana "nullable"
        date tanggal_realisasi "nullable"
        numeric anggaran "nullable"
        enum status
        int realisasi_peserta "nullable"
        text justifikasi "nullable"
        uuid kegiatan_asal_id FK "nullable"
        text uraian_pelaksanaan "nullable"
        text kendala "nullable"
        text strategi_tindaklanjut "nullable"
        uuid created_by FK
        timestamp created_at
        timestamp updated_at
    }

    KLAIM_KEGIATAN {
        uuid id PK
        uuid rencana_aksi_id FK
        uuid kegiatan_id FK
        uuid komponen_id FK "nullable"
        enum arah_dampak
        varchar catatan "nullable"
        enum sumber_klaim
        uuid pengukuran_id FK "nullable"
        uuid created_by FK
        timestamp created_at
    }

    INDIKATOR_KOMPONEN {
        uuid id PK
        uuid indikator_id FK
        varchar kode
        text label
        enum peran
        numeric bobot
        int urutan
        varchar satuan "nullable"
        boolean aktif
        uuid created_by FK
        timestamp created_at
        timestamp updated_at
    }

    PENGUKURAN {
        uuid id PK
        uuid indikator_id FK
        int tahun
        uuid periode_id FK
        uuid jadwal_snapshot_id FK
        numeric nilai "nullable, turunan utk non-manual"
        enum sumber_nilai
        text catatan "nullable"
        enum status_alur
        int versi
        uuid created_by FK
        timestamp created_at
        timestamp updated_at
    }

    PENGUKURAN_KOMPONEN {
        uuid id PK
        uuid pengukuran_id FK
        uuid komponen_id FK
        numeric nilai "nullable"
        uuid updated_by FK
        timestamp updated_at
    }

    JENIS_BERKAS {
        uuid id PK
        string nama
        enum tahap
        uuid indikator_id FK "nullable"
        boolean wajib
        text keterangan "nullable"
        boolean izinkan_file
        boolean izinkan_tautan
        boolean izinkan_teks
        boolean semua_mode_wajib
        int urutan
        varchar format_diizinkan "nullable"
        int ukuran_maks_kb "nullable"
        boolean aktif
        uuid created_by FK
        timestamp created_at
        timestamp updated_at
    }

    BERKAS {
        uuid id PK
        uuid jenis_berkas_id FK "nullable"
        varchar berkasable_type "enum 6 nilai"
        uuid berkasable_id
        enum mode
        varchar nama_asli "nullable, wajib utk mode file"
        varchar path "nullable, wajib utk mode file"
        varchar mime "nullable, wajib utk mode file"
        bigint ukuran_bytes "nullable, wajib utk mode file"
        varchar tautan "nullable, wajib utk mode tautan"
        text isi_teks "nullable, wajib utk mode teks"
        uuid uploaded_by FK
        timestamp created_at
        timestamp dihapus_pada "nullable"
        uuid dihapus_oleh FK "nullable"
    }

    REKOMENDASI_PIMPINAN {
        uuid id PK
        uuid indikator_id FK
        int tahun
        uuid periode_id FK
        text isi
        uuid ditetapkan_oleh FK
        timestamp created_at
    }

    STATUS_CAPAIAN {
        uuid id PK
        uuid pengukuran_id FK
        enum status
        enum sumber
        uuid ditetapkan_oleh FK "nullable"
        timestamp created_at
    }

    AUDIT_LOG {
        uuid id PK
        uuid actor_id FK
        timestamp waktu
        string tindakan
        string objek_tipe
        uuid objek_id
        jsonb nilai_lama "nullable"
        jsonb nilai_baru "nullable"
        text alasan "nullable"
        jsonb dasar_izin "nullable"
    }

    PENGATURAN {
        uuid id PK
        string kunci UK
        text nilai
        string tipe
        string grup
        uuid updated_by FK
        timestamp updated_at
    }
```

---

## 2. Detail Entitas

### 2.1 `users`

Representasi lokal pengguna yang terautentikasi via Keycloak. Tabel ini tidak menyimpan password; otentikasi didelegasikan sepenuhnya ke Keycloak (lihat PRD §6).

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `keycloak_id` | string | **unique**, not null | Subject ID dari token OIDC Keycloak; kunci pemetaan identitas |
| `nama` | string | not null | Nama tampil, disinkronkan dari klaim profil Keycloak saat login |
| `email` | string | not null | Disinkronkan dari klaim email Keycloak |

---

### 2.2 `unit`

Master global unit organisasi, tanpa tabel keanggotaan eksplisit — keterkaitan pengguna dilakukan melalui scope pada `user_permission_granted.unit_id`/`user_permission_denials.unit_id` (lihat §2.7–§2.8).

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `nama` | string | not null | |
| `status` | enum(`aktif`,`nonaktif`) | not null, default `aktif` | |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |

**Catatan definisi (wajib dipahami sebelum membaca entitas lain):** `unit` merepresentasikan kelompok organisasi pemilik indikator, sekaligus scope permission (lihat `user_permission_granted.unit_id`, `user_permission_denials.unit_id`, `indikator.unit_id`, `jadwal_snapshot.unit_id`, `kegiatan.unit_id`, `rencana_aksi.unit_id`) — **bukan** satuan ukur; peran itu dipegang oleh kolom `indikator.satuan` yang sepenuhnya independen. Istilah "unit" dipilih dengan sengaja, bukan "unit kerja", agar tidak bertabrakan dengan kosakata evaluasi ZI/SAKIP yang sudah memakai istilah "unit kerja" untuk konsep lain. Nama tabel dan kolom pada skema tetap `unit`/`unit_id` secara permanen; label yang tampil di antarmuka dapat disetel lewat kunci `aplikasi.label_unit` pada modul setelan (`pengaturan`, lihat §2.22) tanpa memerlukan migrasi ulang.

**Aturan integritas (level aplikasi):**
- Unit yang memiliki ≥1 `indikator` terkait tidak dapat dihapus.
- Unit kosong (tanpa indikator) dapat dihapus hanya oleh Superadmin.
- Tabel ini tidak punya kolom `updated_at`/riwayat lain — perubahan penting (create/update/delete) tercatat lewat `audit_log` (tindakan `unit.hapus`, dst.).

---

### 2.3 `permissions`

Katalog permission — sumber kebenaran tunggal atas seluruh kode permission yang dikenal aplikasi. Katalog **didefinisikan sebagai konstanta pada kode aplikasi** (kode permission bertipe aman, tidak bisa salah ketik), kemudian **di-seed** ke tabel ini. Tabel hanya dibaca oleh aplikasi saat runtime; penambahan/penghapusan permission dilakukan lewat rilis kode + seeder, **bukan** lewat UI.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `kode` | varchar | **unique**, not null | Format `entitas:aksi`, mis. `pengukuran:sahkan`, `rencana_aksi:ajukan`, `komponen:update` — string yang sama dipakai di kode aplikasi; satu baris = satu kode permission |
| `entitas` | varchar | not null | Bagian sebelum titik dua |
| `aksi` | varchar | not null | Bagian setelah titik dua |
| `keterangan` | text | nullable | |
| `butuh_scope` | enum(`global`,`unit`) | not null, default `global` | `unit` = permission yang wajib melekat pada satu unit saat diberikan lewat grant (lihat §2.7) |
| `sensitif` | boolean | not null, default `false` | Aksi yang wajib mencatat alasan dan **dasar izin** di `audit_log` (lihat §2.32, §3.5) |
| `aktif` | boolean | not null, default `true` | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Isi katalog:** katalog permission memakai dua pola penamaan. Entitas master berbentuk seragam entitas master memakai bentuk seragam `entitas:create`, `entitas:read`, `entitas:update`, `entitas:delete`; aksi alur kerja/administratif memakai kata kerja spesifik (`ajukan`, `verifikasi`, `kembalikan`, `sahkan`, `buka_kembali`, `aktivasi`, `tutup`, `tetapkan`, `ekspor`, dll). Katalog didefinisikan sebagai konstanta pada kode aplikasi lalu di-seed ke tabel ini — **satu baris `permissions` = satu kode permission** (bukan gabungan beberapa aksi dalam satu baris); penambahan/penghapusan kode permission dilakukan lewat rilis kode + seeder, bukan lewat UI.

Definisi komponen indikator, persyaratan berkas, dan katalog dasar aturan memakai pola permission CRUD penuh yang sama: `komponen:create`, `komponen:read`, `komponen:update`, `komponen:delete`; `jenis_berkas:create`, `jenis_berkas:read`, `jenis_berkas:update`, `jenis_berkas:delete` (lihat §2.27 dan §2.29); serta `regulasi:create`, `regulasi:read`, `regulasi:update`, `regulasi:delete` (lihat §2.33).

`butuh_scope = unit` berlaku untuk tepat permission berikut: `pengukuran:create`, `pengukuran:update`, `rencana_aksi:create`, `rencana_aksi:update`, `rencana_aksi:ajukan`, `kegiatan:create`, `kegiatan:update`. Permission lain seluruhnya `global`.

`sensitif = true` berlaku untuk: `pengukuran:sahkan`, `pengukuran:buka_kembali`, `pengukuran:verifikasi`, `rencana_aksi:verifikasi`, `rencana_aksi:sahkan`, `rencana_aksi:buka_kembali`, `jadwal:aktivasi`, `jadwal:tutup`, `jadwal:buka_kembali`, `status_capaian:update`, `rekomendasi:tetapkan`, `komponen:update`, `komponen:delete`, `jenis_berkas:update`, `jenis_berkas:delete`, **`regulasi:update`**, **`regulasi:delete`**, `akses:update`, `pengaturan:update`, `berkas:delete`, `kegiatan:delete`. Perhatikan bahwa untuk `komponen`/`jenis_berkas`/`regulasi`, hanya aksi `update` dan `delete` yang bertanda sensitif — `create` dan `read` tidak, karena penambahan definisi baru dan pembacaan katalog tidak mengubah/menghapus data yang sudah dirujuk pengukuran/berkas/Renstra/indikator berjalan.

---

### 2.4 `roles`

Peran — pengelompokan permission yang dapat dipegang pengguna. Lima peran bawaan tetap: `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `kode` | varchar | **unique**, not null | `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai` |
| `nama` | string | not null | Label tampilan, mis. "Administrator" untuk `admin` |
| `keterangan` | text | nullable | Termasuk catatan pemisahan tugas (lihat §4) |
| `is_sistem` | boolean | not null, default `true` | Peran bawaan tidak dapat dihapus |
| `urutan` | int | not null | Urutan tampil |
| `aktif` | boolean | not null, default `true` | |

Isi masing-masing dari kelima peran bawaan mengikuti katalog operasional yang berlaku saat ini — isi peran adalah baris data pada `role_permissions` (§2.5), dievaluasi hidup saat request, bukan daftar hardcode di kode aplikasi.

- **Superadmin** — seluruh permission, termasuk `pengaturan:update`.
- **Perencanaan** — `pengukuran:create`/`pengukuran:update` **global (tanpa scope unit)**, `pengukuran:buka_kembali`; `rencana_aksi:create`/`update`/`ajukan` **global**, `rencana_aksi:verifikasi`/`kembalikan`/`sahkan`/`buka_kembali`; `komponen:create`/`read`/`update`/`delete`, `jenis_berkas:create`/`read`/`update`/`delete`, **`regulasi:create`/`read`/`update`/`delete`**, `rekomendasi:tetapkan`, `berkas:delete`.
- **Pimpinan** — read-only (`pengukuran:read`, `rencana_aksi:read`, `kegiatan:read`, `berkas:read`, `dashboard:read`, `laporan:read`, `laporan:ekspor`, `audit:read`, `komponen:read`, `jenis_berkas:read`, **`regulasi:read`**, pembacaan rekomendasi Pimpinan); `pengukuran:setujui` tetap disiapkan untuk Fase Lanjutan.
- **Pegawai** — `pengukuran:read`, `dashboard:read`, `rencana_aksi:read/create/update/ajukan`, `kegiatan:read/create/update`, `berkas:read/upload/delete` (terbatas pada induk yang belum disahkan), `komponen:read` (label komponen tampil di form pengisian), `jenis_berkas:read` (daftar persyaratan tampil di form rencana aksi/pengukuran/kegiatan), **`regulasi:read`** (dasar aturan tampil pada halaman Renstra/indikator agar konteksnya terbaca). Karena `role_permissions` selalu bersifat global (§2.5), izin `create`/`update`/`ajukan` yang secara bisnis harus terbatas per unit **tidak** dimasukkan ke isi peran Pegawai — izin itu diberikan eksplisit per pengguna per unit lewat `user_permission_granted` (§2.7).
- **Admin** — `pengaturan:update` (permission yang sama juga dipegang `superadmin`, lihat §2.22, catatan pemisahan tugas), ditambah `komponen:read`, `jenis_berkas:read`, dan **`regulasi:read`**. Seluruh wewenang substantif lain (pengelolaan `renstra`/`sasaran`/`indikator`/`target_tahunan`/`renstra_pk`/`periode`/`jadwal_tahunan`, penyusunan dan pengesahan `rencana_aksi`/`kegiatan`, verifikasi dan pengesahan `pengukuran`, `komponen:create`/`update`/`delete`, `jenis_berkas:create`/`update`/`delete`, **`regulasi:create`/`update`/`delete`**, penetapan `status_capaian`/`rekomendasi_pimpinan`) **sengaja tidak diberikan** kepada peran `admin` — pemisahan tugas antara administrasi teknis aplikasi (setelan) dan wewenang substantif atas kinerja.

---

### 2.5 `role_permissions`

Isi peran — daftar permission yang dimiliki setiap peran, disimpan sebagai baris data dan dievaluasi **saat request**, bukan disalin ke baris per pengguna.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `role_id` | uuid | FK → roles.id | |
| `permission_id` | uuid | FK → permissions.id | |
| `created_at` | timestamp | not null | |

**Constraint:** `unique(role_id, permission_id)`.

**Aturan penting — selalu global:** permission yang berasal dari peran **selalu bersifat global** — tabel ini **tidak** memiliki kolom `unit_id`. Cakupan unit hanya dapat diberikan lewat grant (§2.7). Inilah yang membuat peran `perencanaan` global untuk `pengukuran:create`/`update` tanpa perlu baris izin terpisah per unit.

**Perubahan isi peran bersifat sensitif dan WAJIB ter-audit** dengan `nilai_lama`/`nilai_baru` (daftar permission sebelum/sesudah perubahan) beserta `alasan`. Perubahan berlaku langsung bagi **seluruh pemegang peran tersebut** — konsekuensi yang disadari, dan justru alasan mengapa auditnya wajib (lihat §2.32).

---

### 2.6 `user_roles`

Peran yang dipegang setiap pengguna.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `user_id` | uuid | FK → users.id | |
| `role_id` | uuid | FK → roles.id | |
| `diberikan_oleh` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |

**Constraint:** **`unique(user_id)`** — pada Fase Awal, satu pengguna memegang **tepat satu** peran. Struktur tabel sudah berbentuk pivot (bukan kolom `role` langsung pada `users`), sehingga multi-peran dapat dibuka di Fase Lanjutan hanya dengan melepas constraint ini — dicatat sebagai batas fase yang disadari, bukan celah desain (lihat §6).

Setiap penambahan/penggantian/penghapusan baris pada tabel ini tercatat di `audit_log` dengan `alasan` wajib diisi (lihat §2.32).

---

### 2.7 `user_permission_granted`

Pemberian izin tambahan di luar peran — mekanisme satu-satunya untuk memberi cakupan unit pada suatu permission.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `user_id` | uuid | FK → users.id | |
| `permission_id` | uuid | FK → permissions.id | |
| `unit_id` | uuid | FK → unit.id, **nullable** | `NULL` = global |
| `alasan` | text | **not null** | Grant adalah pengecualian administratif — alasannya wajib |
| `diberikan_oleh` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |

**Constraint:** `unique(user_id, permission_id, unit_id)` — implementasi index unik memakai `COALESCE(unit_id, sentinel)` karena PostgreSQL memperlakukan `NULL` sebagai nilai berbeda antarbaris.

**Validasi scope (ditegakkan di level aplikasi):**
- `permissions.butuh_scope = unit` → `unit_id` **wajib diisi** pada baris grant (mis. `pengukuran:create` untuk PIC di unitnya).
- `permissions.butuh_scope = global` → `unit_id` **wajib NULL**.
- Grant untuk permission bertipe `unit` tanpa `unit_id` **ditolak sistem**.

**Pola pemakaian:** "PIC unit A" secara operasional adalah kombinasi peran `pegawai` (izin baca dasar, global lewat §2.5) ditambah baris grant `pengukuran:create`/`update`, `rencana_aksi:create`/`update`/`ajukan`, `kegiatan:create`/`update` dengan `unit_id = A` (lihat §3.3).

---

### 2.8 `user_permission_denials`

Pencabutan izin — satu-satunya mekanisme untuk menyatakan "sengaja dicabut", berbeda dari "tidak pernah diberi".

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `user_id` | uuid | FK → users.id | |
| `permission_id` | uuid | FK → permissions.id | |
| `unit_id` | uuid | FK → unit.id, **nullable** | `NULL` = pencabutan menyeluruh (lintas unit) |
| `alasan` | text | **not null** | Wajib — inilah yang membedakan "sengaja dicabut" dari "belum pernah diberi" |
| `ditetapkan_oleh` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |

**Constraint:** `unique(user_id, permission_id, unit_id)` dengan pola `COALESCE` yang sama seperti §2.7.

Deny dapat mencabut permission yang berasal dari **peran maupun grant**, dan berlaku terhadap permission bertipe `global` maupun `unit`. Konsekuensi saat ditolak: aksi dibatalkan dan percobaan aksi yang ditolak tercatat di `audit_log` (lihat §2.32).

---

### 2.9 `renstra`

Payung strategis multi-tahun.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `nama` | string | not null | |
| `keterangan` | text | nullable | |
| `dasar_hukum` | text | **wajib diisi sebelum status dapat menjadi `aktif`** | Divalidasi di level aplikasi saat aksi aktivasi, bukan NOT NULL murni di DB (agar draft awal boleh kosong sementara). **Tetap dipertahankan** sebagai ringkasan teks yang dibaca cepat, berdampingan dengan rujukan terstruktur `regulasi_id` di bawah — keduanya tidak saling menggantikan |
| `regulasi_id` | uuid | FK → regulasi.id, **nullable** | Rujukan terstruktur ke dasar aturan penyusunan Renstra (lihat §2.33) |
| `tahun_mulai` | int | not null | |
| `tahun_akhir` | int | not null | |
| `status` | enum(`draft`,`aktif`,`nonaktif`,`diarsipkan`) | not null, default `draft` | |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Constraint level aplikasi:**
- Tidak boleh terdapat dua baris berstatus `aktif` dengan rentang `[tahun_mulai, tahun_akhir]` yang beririsan. Aturan ini ditegakkan lewat validasi service-layer sebelum commit transaksi — constraint DB murni sulit mengekspresikan pengecekan rentang beririsan secara portabel di PostgreSQL tanpa exclusion constraint tambahan. Menambahkan **PostgreSQL exclusion constraint** (`EXCLUDE USING gist` dengan ekstensi `btree_gist`) sebagai **lapisan pertahanan kedua** direkomendasikan untuk menutup celah race condition yang tidak tertangkap validasi aplikasi.
- Sebuah baris `renstra` **tidak dapat ditransisikan keluar dari status `aktif`** (ke `nonaktif` maupun `diarsipkan`) selama masih terdapat `jadwal_tahunan` berstatus `aktif` yang mengacu ke Renstra tersebut. Jadwal harus ditutup (`status = ditutup`) terlebih dahulu. Divalidasi di service layer sebelum commit transaksi.

---

### 2.10 `renstra_pk`

Perjanjian Kinerja tahunan per Renstra, masuk penuh sejak Fase Awal.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `renstra_id` | uuid | FK → renstra.id | |
| `tahun` | int | not null | |
| `nomor_pk` | string | not null | Nomor dokumen resmi PK |
| `tanggal_pk` | date | not null | |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Constraint:** `unique(renstra_id, tahun)` — satu PK per tahun per Renstra.
**Aturan operasional:** koreksi atas `nomor_pk`/`tanggal_pk` wajib menyertakan alasan dan tercatat di `audit_log` (nilai lama vs baru). Revisi Renstra akibat terbitnya Kepmen IKU baru diperlakukan sebagai **edit in place** pada baris `renstra` yang sama (bukan Renstra baru), dengan `audit_log.alasan` memuat nomor & tanggal Kepmen; nomor/tanggal surat persetujuan Eselon 1 kementerian pusat dicantumkan pada kolom `alasan` yang sama agar jejak audit tersambung ke otorisasi eksternalnya.

**Lampiran dokumen PK:** `renstra_pk` menjadi salah satu induk polimorfik `berkas`
(`berkasable_type = renstra_pk`, lihat §2.30) — dokumen Perjanjian Kinerja resmi dapat
dilampirkan dalam mode file/tautan/teks. Aktivasi `jadwal_tahunan` mensyaratkan **minimal
satu** lampiran pada `renstra_pk` tahun tersebut sebagai salah satu gerbang aktivasi (lihat
§2.15, gerbang keempat). Lampiran ini tidak dapat dihapus setelah `jadwal_tahunan` tahun
tersebut berstatus `aktif` (lihat §2.30).

---

### 2.11 `sasaran`

Tujuan strategis di bawah Renstra.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `renstra_id` | uuid | FK → renstra.id | |
| `nama` | string | not null | |
| `keterangan` | text | nullable | |
| `urutan` | int | not null | Menentukan urutan tampil di UI/laporan |

---

### 2.12 `indikator`

Unit ukur kinerja konkret.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `sasaran_id` | uuid | FK → sasaran.id | |
| `unit_id` | uuid | FK → unit.id, **NOT NULL** | Setiap indikator wajib dimiliki tepat satu unit |
| `regulasi_id` | uuid | FK → regulasi.id, **nullable** | Rujukan dasar aturan per indikator kinerja (mis. produk hukum yang menetapkan IKU tersebut), agar dasar hukum dapat ditelusuri per indikator tanpa mengunggah dokumen yang sama berulang (lihat §2.33) |
| `nama` | string | not null | |
| `definisi` | text | nullable | |
| `satuan` | varchar | not null | mis. "%", "orang", "dokumen" |
| `presisi` | smallint | not null, default 2 | Jumlah digit desimal disimpan |
| `desimal_tampilan` | smallint | not null, default 2 | Jumlah digit desimal ditampilkan (dapat berbeda dari presisi penyimpanan) |
| `arah` | enum(`naik_baik`,`turun_baik`) | not null, default `naik_baik` | Menentukan arah nilai yang dianggap membaik; dipakai untuk menentukan wajib-tidaknya catatan saat pengajuan pengukuran (lihat §2.20) dan disalin ke `jadwal_snapshot.arah` sebagai bagian konteks beku (lihat §2.17) |
| `tipe_perhitungan` | enum(`rasio_persen`,`penjumlahan`,`manual`) | not null, default `manual` | Menentukan apakah `pengukuran.nilai` diketik manual atau diturunkan dari `pengukuran_komponen` (lihat §2.20, §2.27, §2.28). Disalin ke `jadwal_snapshot.tipe_perhitungan` sebagai konteks beku |
| `tahun_mulai_berlaku` | int | not null | |
| `status` | enum(`aktif`,`arsip`) | not null, default `aktif` | |
| `wajib_catatan` | boolean | not null, default `false` | Jika true, catatan wajib diisi setiap pengajuan pengukuran indikator ini |
| `created_by` | uuid | FK → users.id | |
| `created_by_role` | string | not null | Salinan kode peran (`roles.kode`) pembuat pada saat pembuatan (untuk konteks audit historis meski peran pengguna berubah kemudian) |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Aturan validasi `tipe_perhitungan` (level aplikasi, ditegakkan saat menyimpan definisi komponen — lihat §2.27):**
- `rasio_persen` wajib memiliki **minimal 1** `indikator_komponen` berperan `pembilang` yang aktif **dan tepat 1** `indikator_komponen` berperan `penyebut` yang aktif. Penyimpanan indikator dengan tipe ini ditolak bila syarat tidak terpenuhi.
- `penjumlahan` wajib memiliki **minimal 1** `indikator_komponen` berperan `penjumlah` yang aktif. Penyimpanan indikator dengan tipe ini ditolak bila syarat tidak terpenuhi.
- `manual` tidak mensyaratkan komponen apa pun — nilai `pengukuran.nilai` diketik langsung oleh PIC, mengikuti perilaku lama. Indikator seperti Predikat SAKIP dan Nilai Zona Integritas menggunakan tipe ini, karena mesin perhitungan berbasis komponen hanya mendukung satu tingkat perhitungan (lihat §2.28).

**Aturan siklus hidup dan integritas:**
- Perpindahan `unit_id` (indikator dipindah ke unit lain) wajib dicatat di `audit_log` dengan `nilai_lama`/`nilai_baru` berisi unit sebelum/sesudah.
- Indikator yang berhenti relevan (mis. IKU dihapus dari Kepmen acuan) diarsipkan (`status = arsip`), **tidak pernah dihapus** — data pengukuran lama tetap utuh dan tetap tampil pada laporan historis, dengan penanda status arsip.
- Indikator berstatus `arsip` **tidak dapat** menjadi target `pengukuran:create` baru — permintaan pembuatan pengukuran untuk indikator arsip **ditolak sistem** di level service layer, terlepas dari permission/scope yang dimiliki pengguna yang mengajukan.
- Indikator baru dibuat setelah jadwal tahun berjalan sudah aktif tidak otomatis ikut terukur pada tahun tersebut; jalur standar untuk mengikutsertakannya di tahun berjalan adalah `jadwal:buka_kembali` (lihat §2.15) — ini sekaligus jalur standar untuk mengisi periode yang tersisa pada tahun berjalan bagi IKU baru. Tanpa melalui jalur itu, indikator baru mulai terukur sejak jadwal tahun berikutnya diaktifkan — konsekuensi desain yang disadari, bukan celah.

---

### 2.13 `target_tahunan`

Target per indikator per tahun.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `indikator_id` | uuid | FK → indikator.id | |
| `tahun` | int | not null | |
| `nilai` | numeric | **nullable** | `0` = nilai sah; `null` = belum diisi |
| `baseline` | numeric | **nullable** | Nilai baseline (capaian tahun sebelumnya) yang dipakai sebagai acuan saat menyusun target tahun ini; ditampilkan pada dokumen PK dan rekapitulasi indikator × periode, dan disalin ke `jadwal_snapshot.baseline` saat aktivasi jadwal |
| `updated_by` | uuid | FK → users.id | |
| `updated_at` | timestamp | not null | |

**Constraint:** `unique(indikator_id, tahun)`.

**Aturan revisi:** revisi target antar-tahun (mis. menaikkan target tahun-tahun mendatang setelah tahun berjalan terlampaui) cukup dilakukan dengan mengubah baris master ini; baris `jadwal_snapshot` untuk tahun tersebut baru terbentuk saat jadwal tahun itu diaktifkan dan otomatis membawa nilai revisi terkini (termasuk `baseline`). Tahun yang jadwalnya sudah beku (snapshot sudah terbentuk dan/atau sudah dirujuk pengukuran) tidak tersentuh oleh revisi ini. Aturan ini berlaku untuk **target tahunan PK**; target komponen per periode pada rencana aksi memiliki jalur revisinya sendiri (lihat §2.23–§2.24).

---

### 2.14 `periode`

Master satuan waktu pelaporan (global, tidak terikat Renstra/tahun tertentu).

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `nama` | string | not null | mis. "Triwulan I", "Semester II", "Tahunan" |
| `urutan` | int | not null | |
| `aktif` | boolean | not null, default `true` | |
| `is_nilai_akhir` | boolean | not null, default `false` | **Tepat satu** baris bernilai `true` di seluruh konfigurasi aktif — ditegakkan di level aplikasi (validasi sebelum simpan). Nilai periode ini **diisi manual**; tidak ada perhitungan agregasi otomatis dari periode-periode lain |

**Catatan relasi:** baris pada tabel ini bersifat global lintas tahun/Renstra. Daftar periode yang *diharapkan* pada satu jadwal tahun tertentu — beserta jendela pengisian dan reviunya — ditentukan oleh baris `jadwal_periode` (lihat §2.16), bukan langsung oleh tabel ini. Periode yang sama juga menjadi satuan waktu bagi `rencana_aksi_target` (§2.24) dan `kegiatan` (§2.25).

---

### 2.15 `jadwal_tahunan`

Jendela penutupan tingkat tahun, jendela penyusunan rencana aksi, dan (opsional) persetujuan pimpinan, per Renstra per tahun. Daftar periode yang diharapkan pada tahun tersebut beserta jendela pengisian/reviu masing-masing periode disimpan terpisah pada `jadwal_periode` (§2.16) — pemisahan ini memungkinkan satu jadwal tahunan memiliki susunan periode yang berbeda dari tahun lain (mis. Triwulan I–IV pada satu tahun, kombinasi lain pada tahun berikutnya) tanpa mengubah struktur tabel ini.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `renstra_id` | uuid | FK → renstra.id | |
| `tahun` | int | not null | |
| `pakai_persetujuan_pimpinan` | boolean | not null, default `false` | **-- (kolom tersedia, jalur pengisian/pemakaian menyusul fase lanjutan)**. Fase Awal: selalu `false`, tidak memengaruhi alur pengesahan |
| `persetujuan_mulai` | date | nullable | **-- (kolom tersedia, jalur pengisian/pemakaian menyusul fase lanjutan)** |
| `persetujuan_selesai` | date | nullable | **-- (kolom tersedia, jalur pengisian/pemakaian menyusul fase lanjutan)** |
| `rencana_aksi_mulai` | date | nullable | Awal jendela penyusunan `rencana_aksi` tingkat tahun. Disusun **setelah** `jadwal_periode` tahun ini tersusun dan **sebelum** jendela pengisian periode pertama dibuka (lihat §2.16, §2.23) |
| `rencana_aksi_selesai` | date | nullable | Batas mutlak bagi PIC (izin ber-scope unit lewat grant, §2.7) untuk menyusun/mengubah/mengajukan `rencana_aksi`. Pola batas waktu sama dengan pengisian pengukuran: **Perencanaan dikecualikan** (izin global lewat peran, §2.5, dibatasi `penutupan`, bukan oleh kolom ini) |
| `penutupan` | date | not null | Tanggal batas akhir seluruh aktivitas jadwal tahun ini — termasuk batas akhir bagi Perencanaan mengisi/mengubah pengukuran maupun rencana aksi (lihat §2.16, §2.23) |
| `status` | enum(`draft`,`aktif`,`ditutup`) | not null, default `draft` | |
| `renstra_pk_id` | uuid | FK → renstra_pk.id | **Wajib diisi saat aktivasi** |
| `activated_at` | timestamp | nullable | Diisi otomatis saat transisi ke `aktif`. Pada backfill data historis (jadwal retroaktif untuk tahun lampau), kolom ini tetap mencatat **waktu aktivasi sebenarnya** — jujur, bukan dipalsukan menjadi tanggal retroaktif |
| `closed_at` | timestamp | nullable | Diisi otomatis saat transisi ke `ditutup` |

**Syarat aktivasi (divalidasi di service layer, dalam satu transaksi, sebelum transisi status → `aktif`) — EMPAT gerbang:**
1. `renstra_pk` untuk kombinasi (`renstra_id`, `tahun`) sudah ada (lihat §2.10) — jika belum, aktivasi ditolak.
2. Seluruh indikator berstatus `aktif` yang berada di bawah Renstra ini sudah memiliki `target_tahunan` untuk tahun Y (lihat §2.13) — indikator berstatus `arsip` dikecualikan dari syarat ini.
3. `jadwal_tahunan.tahun` berada di dalam rentang `[renstra.tahun_mulai, renstra.tahun_akhir]`.
4. **(Gerbang lampiran PK)** Baris `renstra_pk` yang dirujuk pada syarat 1 memiliki **minimal satu** lampiran `berkas` (`berkasable_type = renstra_pk`, lihat §2.30) — mode bebas (file/tautan/teks), sehingga persyaratan ini tidak dapat memacetkan alur. Bila unggahan file dinonaktifkan lewat setelan grup `berkas` (`berkas.unggahan_aktif = false`, §2.22) sementara belum ada lampiran mode `tautan`/`teks`, gerbang ini ditandai **`tidak_dapat_dipenuhi`** dan tidak memblokir aktivasi — penandaan tercatat di `audit_log` dan tampil pada halaman kerja Perencanaan agar terlihat dan dapat diperbaiki (lihat §2.30).

Jika salah satu dari keempat syarat gagal — kecuali gerbang 4 yang sudah ditandai `tidak_dapat_dipenuhi` — aktivasi ditolak dan pesan kesalahan menyebutkan syarat mana yang tidak terpenuhi.

**Pembuatan snapshot saat aktivasi/buka_kembali:** begitu transisi ke `aktif` berhasil (baik aktivasi awal maupun `jadwal:buka_kembali` dari `ditutup → aktif`), sistem membentuk baris `jadwal_snapshot` untuk tiap indikator aktif yang **belum** memiliki snapshot pada jadwal ini (lihat §2.17), lengkap dengan baris anak `jadwal_snapshot_komponen` yang membekukan definisi komponen indikator tersebut (lihat §2.18). Proses ini **idempoten**: baris yang sudah ada tidak pernah ditimpa, sehingga backfill indikator baru di tengah tahun (§2.12) hanya menambah baris baru tanpa mengganggu snapshot lama. Baris `audit_log` yang mencatat peristiwa ini (`jadwal.aktivasi` atau `jadwal.buka_kembali`) memakai `actor_id` = pengguna Perencanaan/Superadmin yang menjalankan aksinya — bukan nilai sistem, meski pembuatan barisnya berjalan otomatis di dalam transaksi yang sama.

**Koreksi setelah penutupan:** satu-satunya jalur koreksi pengukuran, rencana aksi, maupun kegiatan setelah `penutupan` tercapai adalah `jadwal:buka_kembali` (`ditutup → aktif`), lalu melakukan koreksi, lalu `jadwal:tutup` kembali — seluruh rangkaian ini tercatat di `audit_log` (lihat §2.20, §2.23, §2.25).

**Constraint:** tepat satu baris berstatus **aktif** per kombinasi (`renstra_id`, `tahun`), ditegakkan lewat **partial unique index**:
```sql
CREATE UNIQUE INDEX jadwal_tahunan_aktif_unik
    ON jadwal_tahunan (renstra_id, tahun)
    WHERE status = 'aktif';
```
Ini bukan unique global lintas seluruh Renstra — dua Renstra berbeda secara teori bisa memiliki jadwal aktif pada tahun yang sama jika rentang tahunnya tidak beririsan (dan constraint `renstra` di §2.9 sudah mencegah dua Renstra aktif dengan rentang beririsan). Partial unique index di sini adalah pertahanan tambahan pada level jadwal itu sendiri.

---

### 2.16 `jadwal_periode`

Daftar periode yang diharapkan pada suatu `jadwal_tahunan`, beserta jendela waktu pengisian dan reviu masing-masing periode. Tabel ini adalah **sumber kebenaran tunggal** untuk menentukan periode apa saja yang wajib diisi pada tahun tersebut (mis. Triwulan I–IV, atau Semester I–II) — status tampilan "Belum mengisi"/"Tidak mengisi" pada dashboard dihitung dari kombinasi indikator × periode yang diharapkan menurut tabel ini, bukan dari seluruh baris `periode` global yang ada.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `jadwal_id` | uuid | FK → jadwal_tahunan.id | |
| `periode_id` | uuid | FK → periode.id | |
| `pengisian_mulai` | date | not null | |
| `pengisian_selesai` | date | not null | Batas mutlak bagi PIC (izin `pengukuran:create`/`update` yang di-scope per unit lewat grant, lihat §2.7) untuk membuat/mengubah/mengajukan pengukuran pada periode ini. Setelah tanggal ini terlampaui, PIC tidak dapat lagi membuat/mengubah/mengajukan pengukuran periode berjalan |
| `reviu_mulai` | date | not null | |
| `reviu_selesai` | date | not null | |

**Constraint:** `unique(jadwal_id, periode_id)` — satu periode hanya boleh muncul sekali per jadwal tahunan.

**Aturan integritas:**
- Batas `pengisian_selesai` bersifat mutlak hanya bagi PIC dengan izin ber-scope unit (grant, §2.7). Peran **Perencanaan** (izin global lewat `role_permissions`, §2.5) dikecualikan dari batas ini dan tetap dapat mengisi/mengubah pengukuran sampai `jadwal_tahunan.penutupan` tercapai.
- Indikator berstatus `arsip` **tidak dihitung** sebagai kewajiban pengisian pada kombinasi indikator × periode manapun di tabel ini.
- Periode dengan `periode.is_nilai_akhir = true` (mis. "Tahunan") tetap dapat memiliki baris di tabel ini seperti periode lain; nilainya diisi manual, tidak dihitung otomatis dari agregasi periode-periode lain (lihat §2.14).
- Rencana aksi disusun **setelah** daftar periode pada jadwal tahunan tersusun (target periode pada rencana aksi butuh daftar periode ini) — lihat §2.23.

---

### 2.17 `jadwal_snapshot`

Salinan beku konteks indikator (termasuk konteks cara hitung), dibuat otomatis oleh logika aplikasi (bukan trigger DB) saat `jadwal_tahunan` bertransisi ke `aktif` — baik pada aktivasi awal maupun pada `jadwal:buka_kembali`. Masuk penuh sejak Fase Awal sebagai fondasi integritas historis.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `jadwal_id` | uuid | FK → jadwal_tahunan.id | |
| `indikator_id` | uuid | FK → indikator.id | Referensi ke master, untuk ketertelusuran — **bukan** untuk menarik data terkini |
| `unit_id` | uuid | (salinan, bukan FK aktif secara semantik) | Salinan nilai `indikator.unit_id` saat aktivasi |
| `nama` | string | salinan | Salinan `indikator.nama` saat aktivasi |
| `definisi` | text | salinan | Salinan `indikator.definisi` saat aktivasi |
| `satuan` | string | salinan | Salinan `indikator.satuan` saat aktivasi |
| `presisi` | smallint | salinan | Salinan `indikator.presisi` saat aktivasi |
| `desimal_tampilan` | smallint | salinan | Salinan `indikator.desimal_tampilan` saat aktivasi |
| `arah` | enum(`naik_baik`,`turun_baik`) | salinan | Salinan `indikator.arah` saat aktivasi — konteks beku untuk menilai kewajiban catatan pada pengukuran yang merujuk snapshot ini (lihat §2.20) |
| `tipe_perhitungan` | enum(`rasio_persen`,`penjumlahan`,`manual`) | salinan | Salinan `indikator.tipe_perhitungan` saat aktivasi — menentukan apakah pengukuran yang merujuk snapshot ini dihitung dari komponen atau diketik manual, tanpa terpengaruh perubahan definisi komponen di tengah tahun |
| `target` | numeric | nullable, salinan | Salinan `target_tahunan.nilai` untuk `(indikator_id, tahun jadwal)` saat aktivasi |
| `baseline` | numeric | nullable, salinan | Salinan `target_tahunan.baseline` untuk `(indikator_id, tahun jadwal)` saat aktivasi |

**Constraint:** `unique(jadwal_id, indikator_id)` — menjamin idempotensi pembuatan snapshot: setiap pasangan (`jadwal_id`, `indikator_id`) hanya boleh punya tepat satu baris.

**Perilaku kunci:**
- **Idempoten:** pembuatan baris dilakukan hanya untuk pasangan (`jadwal_id`, `indikator_id`) yang belum memiliki snapshot pada jadwal tersebut — baik saat aktivasi awal maupun saat `jadwal:buka_kembali`. Baris yang sudah ada **tidak pernah ditimpa**.
- **Abadi setelah dirujuk:** begitu suatu baris snapshot dirujuk oleh pengukuran pertama (lihat `pengukuran.jadwal_snapshot_id`, §2.20), baris tersebut menjadi final dan tidak boleh diubah lagi — termasuk baris anak `jadwal_snapshot_komponen`-nya (§2.18).
- **Koreksi terbatas sebelum dirujuk:** selama baris snapshot belum dirujuk pengukuran manapun, baris tersebut boleh dikoreksi — namun hanya ketika `jadwal_tahunan` berstatus `aktif` (termasuk aktif kembali via `buka_kembali`), dan koreksi tersebut wajib tercatat di `audit_log` (`nilai_lama`/`nilai_baru`).
- **Tidak ada restatement:** perubahan pada `indikator`/`target_tahunan`/`indikator_komponen` master setelah snapshot terbentuk tidak pernah mempropagasi ke baris snapshot yang sudah ada — tidak ada mekanisme restatement data historis pada Fase Awal maupun Fase Lanjutan. Baris baru untuk indikator yang sama hanya terbentuk lagi saat jadwal tahun berikutnya diaktifkan, dengan kondisi master terkini pada saat itu. Konsekuensinya, perubahan definisi komponen (§2.27) di tengah tahun **tidak** mengubah makna data historis yang sudah dibekukan.

---

### 2.18 `jadwal_snapshot_komponen`

Salinan beku definisi komponen indikator pada suatu `jadwal_snapshot`, dibuat pada saat yang sama dengan baris induknya. Tabel anak ini melengkapi §2.17 agar cara hitung berbasis komponen ikut membeku, bukan hanya nilai target/baseline.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `jadwal_snapshot_id` | uuid | FK → jadwal_snapshot.id | |
| `kode` | varchar | salinan | Salinan `indikator_komponen.kode` saat aktivasi |
| `label` | text | salinan | Salinan `indikator_komponen.label` saat aktivasi |
| `peran` | enum(`pembilang`,`penyebut`,`penjumlah`) | salinan | Salinan `indikator_komponen.peran` saat aktivasi |
| `bobot` | numeric | salinan | Salinan `indikator_komponen.bobot` saat aktivasi |
| `urutan` | int | salinan | Salinan `indikator_komponen.urutan` saat aktivasi |

**Perilaku kunci:** mengikuti sepenuhnya sifat idempoten dan imutabel `jadwal_snapshot` induknya (§2.17) — baris hanya dibuat untuk snapshot yang baru dibentuk, tidak pernah ditimpa, dan menjadi final begitu snapshot induknya dirujuk pengukuran pertama. Indikator bertipe `manual` tetap dapat memiliki baris `jadwal_snapshot` tanpa baris `jadwal_snapshot_komponen` (tabel anak kosong bila indikator tidak memiliki komponen aktif).

---

### 2.19 `penanggung_jawab`

Riwayat penugasan penanggung jawab per indikator. Baris tidak pernah dihapus.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `indikator_id` | uuid | FK → indikator.id | |
| `user_id` | uuid | FK → users.id | |
| `tanggal_mulai_berlaku` | date | not null | |
| `ditetapkan_oleh` | uuid | FK → users.id | |
| `alasan` | text | nullable | Wajib diisi di level aplikasi setiap kali indikator sudah punya penanggung jawab sebelumnya (pergantian); boleh kosong hanya pada penugasan pertama kali |
| `created_at` | timestamp | not null | |

**Aturan resolusi "penugasan efektif":** untuk suatu `indikator_id` dan tanggal acuan `T`, penanggung jawab efektif adalah baris dengan `tanggal_mulai_berlaku` maksimum yang ≤ T. Query referensi:

```sql
SELECT *
FROM penanggung_jawab
WHERE indikator_id = :indikator_id
  AND tanggal_mulai_berlaku <= :tanggal_acuan
ORDER BY tanggal_mulai_berlaku DESC
LIMIT 1;
```

**Relasi dengan `rencana_aksi`:** PIC rencana aksi adalah PIC indikator yang berlaku menurut resolusi di atas — tidak ada mekanisme penugasan terpisah untuk rencana aksi (lihat §2.23). Penugasan sebagai PIC di sini **tidak** memberikan izin apa pun secara langsung; izin untuk menyusun/mengubah/mengajukan tetap harus datang dari grant `user_permission_granted` (§2.7) yang ber-scope unit yang sama — keduanya harus sinkron secara operasional, tetapi merupakan dua mekanisme berbeda.

---

### 2.20 `pengukuran`

Nilai capaian aktual per indikator, per tahun, per periode. Baris tidak dapat dihapus permanen jika `nilai`/`catatan` sudah terisi.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `indikator_id` | uuid | FK → indikator.id | |
| `tahun` | int | not null | |
| `periode_id` | uuid | FK → periode.id | |
| `jadwal_snapshot_id` | uuid | FK → jadwal_snapshot.id | Konteks beku yang dirujuk untuk tampilan (nama, satuan, arah, tipe perhitungan, target/baseline pembanding) |
| `nilai` | numeric | **nullable** | `0` sah; `null` = belum diisi. Untuk indikator bertipe `rasio_persen`/`penjumlahan`, kolom ini menjadi **nilai turunan** — dihitung sistem dari `pengukuran_komponen` (lihat §2.28) dan **tidak dapat diketik langsung oleh PIC** (read-only di UI). Untuk tipe `manual`, perilaku lama berlaku sepenuhnya: nilai diketik langsung |
| `sumber_nilai` | enum(`komponen`,`manual`) | not null | Mencatat asal nilai untuk kebutuhan audit: `komponen` bila diturunkan dari `pengukuran_komponen`, `manual` bila diketik langsung |
| `catatan` | text | nullable | Wajib diisi saat pengajuan (transisi `draft → diajukan`) jika (a) nilai memburuk menurut `indikator.arah` dibandingkan pengukuran berstatus `disahkan` **terakhir secara kronologis** untuk indikator yang sama — nilai yang stagnan/sama **tidak** memicu kewajiban ini; atau (b) `indikator.wajib_catatan = true`. Pengukuran pertama suatu indikator (tanpa pembanding historis) tidak wajib mengisi catatan. Perbandingan dilakukan atas **nilai turunan** untuk indikator bertipe komponen |
| `status_alur` | enum(`draft`,`diajukan`,`diverifikasi`,`dikembalikan`,`disahkan`) | not null, default `draft` | |
| `versi` | integer | not null, default 1 | **Optimistic locking** — dinaikkan setiap perubahan |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Gerbang rencana aksi (keras):** pengajuan `pengukuran` (transisi `draft → diajukan`) untuk periode P pada indikator I **ditolak** selama `rencana_aksi` untuk (indikator I × tahun) belum berstatus `disahkan` (lihat §2.23).

**Larangan pembuatan untuk indikator arsip:** permintaan `pengukuran:create` **ditolak sistem** apabila `indikator.status = arsip` pada indikator terkait, terlepas dari permission/scope yang dimiliki pengguna (lihat §2.12).

**Kelengkapan komponen:** untuk indikator bertipe `rasio_persen`/`penjumlahan`, pengajuan pengukuran **ditolak** bila ada `indikator_komponen` aktif yang nilainya pada `pengukuran_komponen` masih `null` — kelengkapan komponen adalah syarat tambahan di samping aturan catatan berbasis `arah`/`wajib_catatan` di atas (lihat §2.28).

**Pemisahan tugas pada jalur verifikasi/pengesahan (wajib dibaca sebelum menerapkan permission):** aktor yang memegang izin `pengukuran:verifikasi`/`pengukuran:sahkan` **tidak otomatis** boleh memverifikasi/mengesahkan setiap pengukuran — bila pengajuan dilakukan lewat jalur PIC ber-scope unit, aktor yang sama dengan `pengukuran.created_by` **dilarang keras** melanjutkan transisi `diajukan → diverifikasi` maupun `diverifikasi → disahkan` atas baris itu sendiri. Aturan lengkapnya, termasuk pengecualian untuk jalur Perencanaan, ada di §4 (Pemisahan Tugas) — bukan bagian dari resolusi izin (§3), melainkan validasi bisnis tambahan yang berjalan setelah izin dinyatakan boleh.

**Jalur koreksi setelah disahkan:** transisi `disahkan → dikembalikan` hanya dapat dilakukan lewat permission khusus `pengukuran:buka_kembali` (dimiliki peran Perencanaan/Superadmin), mensyaratkan `alasan` wajib diisi, dan **hanya tersedia sebelum** `jadwal_tahunan.penutupan` tercapai. Setelah penutupan, satu-satunya jalur koreksi adalah membuka kembali jadwal itu sendiri (`jadwal_tahunan.status: ditutup → aktif` via `jadwal:buka_kembali`), melakukan koreksi, lalu menutup jadwal kembali — seluruh rangkaian ini tercatat di `audit_log` (lihat §2.15, §2.32).

**Constraint:** `unique(indikator_id, tahun, periode_id)`.

---

### 2.21 `status_capaian`

Penilaian akhir atas satu `pengukuran`. Maksimal satu baris aktif per pengukuran; baris lama tetap sebagai riwayat (soft replace, tidak dihapus).

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `pengukuran_id` | uuid | FK → pengukuran.id | |
| `status` | enum(`tercapai`,`belum_tercapai`) | not null | |
| `sumber` | enum(`manual`,`data_sumber`) | not null | **Fase Awal: selalu `manual`.** Nilai `data_sumber` **-- (kolom tersedia, jalur pengisian/pemakaian menyusul fase lanjutan)** — belum ada jalur integrasi otomatis yang mengisi nilai ini pada Fase Awal |
| `ditetapkan_oleh` | uuid | FK → users.id, **nullable** | `null` **hanya** jika `sumber = data_sumber` (ditetapkan sistem, bukan manusia); pada Fase Awal kolom ini **selalu terisi** karena seluruh baris bersumber manual |
| `created_at` | timestamp | not null | |

**Catatan implementasi "aktif":** karena tidak ada kolom boolean `is_aktif` eksplisit pada skema sumber, status aktif ditentukan sebagai baris dengan `created_at` terbaru per `pengukuran_id`. Menambahkan indeks komposit `(pengukuran_id, created_at DESC)` disarankan untuk mempercepat query "ambil status aktif terkini".

**Catatan hubungan dengan alur pengukuran:** penetapan `status_capaian` selalu berupa aksi manual terpisah oleh Perencanaan/Superadmin, dilakukan **setelah** pengukuran berstatus `disahkan` — dua momen yang berbeda dalam alur. Sistem tidak menetapkan `status_capaian` secara otomatis begitu pengukuran disahkan.

---

### 2.22 `pengaturan`

Modul setelan aplikasi bergaya key-value, menampung nilai identitas dan preferensi presentasional yang dapat diubah tanpa deployment ulang.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `kunci` | string | **unique**, not null | Mis. `instansi.nama`, `instansi.alamat`, `instansi.telepon`, `instansi.surel`, `instansi.laman`, `instansi.logo`, `aplikasi.nama`, `aplikasi.label_unit`, `tampilan.zona_waktu`, `tampilan.format_tanggal`, `tampilan.format_angka`, `laporan.header`, `laporan.footer`, dan grup **`berkas`**: `berkas.unggahan_aktif`, `berkas.ukuran_maks_kb`, `berkas.format_diizinkan`, `berkas.tautan_selalu_diizinkan` |
| `nilai` | text | nullable | Nilai tersimpan sebagai string; ditafsirkan sesuai `tipe` saat dibaca |
| `tipe` | string | not null | mis. `string`, `text`, `number`, `boolean`, `url`, `file` — menentukan cara parsing dan tampilan form setelan |
| `grup` | string | not null | Pengelompokan tampilan pada halaman setelan, mis. `identitas`, `aplikasi`, `tampilan`, `laporan`, `berkas` |
| `updated_by` | uuid | FK → users.id, nullable | Diisi saat setelan pertama kali diubah dari nilai default hasil seed |
| `updated_at` | timestamp | not null | |

**Constraint:** `unique(kunci)`.

**Kunci grup `berkas` (kebijakan storage bukti dukung):**

| Kunci | Tipe | Default | Keterangan |
|---|---|---|---|
| `berkas.unggahan_aktif` | boolean | `true` | Saklar utama mode unggahan file di seluruh aplikasi |
| `berkas.ukuran_maks_kb` | integer | `10240` | Batas ukuran default dipakai bila `jenis_berkas.ukuran_maks_kb` kosong |
| `berkas.format_diizinkan` | teks | `pdf,docx,xlsx,jpg,jpeg,png` | Daftar format default dipakai bila `jenis_berkas.format_diizinkan` kosong |
| `berkas.tautan_selalu_diizinkan` | boolean | `true` | Menandai bahwa mode `tautan` dan `teks` selalu tersedia sebagai jalur alternatif tanpa memakai storage |

**Aturan integritas dan cakupan:**
- Nilai default di-seed saat instalasi (migrasi/seeder) untuk seluruh kunci awal di atas; aplikasi membaca nilai lewat accessor yang di-cache, bukan query langsung berulang pada tiap render.
- **Fallback grup `berkas`:** nilai pada `jenis_berkas` (`format_diizinkan`, `ukuran_maks_kb`, serta mode yang diizinkan) **menimpa** default setelan di atas; setelan hanya berlaku sebagai nilai fallback dan sebagai saklar kebijakan tingkat aplikasi (lihat §2.29). Batas tegas: kunci grup `berkas` adalah **preferensi operasional**, bukan aturan bisnis — daftar mode yang diizinkan per persyaratan tetap milik `jenis_berkas` (kewenangan Tim Perencanaan), bukan milik setelan.
- Permission `pengaturan:update` dimiliki oleh **dua** peran — `superadmin` dan `admin` (lihat §2.4–§2.5, catatan pemisahan tugas) — dan tidak diberikan ke peran lain; pembacaan nilai setelan terjadi otomatis saat render halaman/laporan tanpa memerlukan permission khusus.
- Setiap perubahan nilai tercatat di `audit_log` (`nilai_lama`/`nilai_baru` berisi pasangan kunci-nilai), termasuk perubahan kunci grup `berkas` — masuk daftar peristiwa teraudit (lihat §2.32).
- Halaman Setelan Aplikasi menampilkan panel **read-only "Penggunaan penyimpanan bukti dukung"**: jumlah berkas mode `file`, total `ukuran_bytes`, dan jumlah bukti mode `tautan`/`teks` — supaya keputusan "mana yang perlu diunggah, mana yang cukup tautan" diambil berdasarkan angka, bukan perkiraan.
- Batas cakupan tegas: kunci yang boleh dikelola lewat tabel ini hanya **teks dan preferensi presentasional/operasional** (identitas instansi/aplikasi, label tampilan, format tanggal/angka, header/footer ekspor, kebijakan storage bukti dukung). Nilai enum/status, nama permission, dan aturan bisnis substantif atas persyaratan bukti dukung **tidak** disimpan di sini — seluruhnya tetap berupa konstanta pada kode aplikasi atau milik `jenis_berkas`.
- Branding halaman login berada **di luar cakupan** tabel ini — tampilan halaman login diatur di level realm Keycloak, bukan oleh aplikasi SAKIP.

---

### 2.23 `rencana_aksi`

Header rencana aksi, satu baris per kombinasi indikator × tahun. Menjadi gerbang wajib sebelum pengukuran periode manapun pada indikator tersebut dapat diajukan (lihat §2.20).

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `indikator_id` | uuid | FK → indikator.id, not null | |
| `tahun` | int | not null | |
| `unit_id` | uuid | FK → unit.id, not null | Salinan unit pemilik indikator pada saat rencana aksi disusun |
| `jadwal_tahunan_id` | uuid | FK → jadwal_tahunan.id, not null | |
| `penanggung_jawab_id` | uuid | FK → users.id, not null | PIC efektif pada saat penyusunan — hasil resolusi `penanggung_jawab` (§2.19) pada tanggal penyusunan |
| `uraian` | text | nullable | |
| `status_alur` | enum(`draft`,`diajukan`,`diverifikasi`,`dikembalikan`,`disahkan`) | not null, default `draft` | |
| `versi` | int | not null, default 1 | Optimistic locking |
| `alasan_revisi` | text | nullable | |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |
| `disahkan_at` | timestamp | nullable | |
| `disahkan_by` | uuid | FK → users.id, nullable | |

**Constraint:** `unique(indikator_id, tahun)` — rencana aksi disusun tepat sekali per (indikator × tahun), bukan per periode; target per periode disimpan pada `rencana_aksi_target` (§2.24).

**Posisi dalam alur:** rencana aksi disusun **setelah** daftar periode pada jadwal tahunan tersusun (`jadwal_periode`, §2.16 — target triwulan butuh daftar periodenya) dan **sebelum** jendela pengisian periode pertama dibuka. Jendela penyusunannya sendiri hidup pada `jadwal_tahunan.rencana_aksi_mulai`/`rencana_aksi_selesai` (§2.15) — jendela tingkat tahun, bukan tingkat periode.

**Aturan jendela waktu:** deadline `rencana_aksi_selesai` bersifat **mutlak bagi PIC** (izin ber-scope unit lewat grant, §2.7). **Perencanaan dikecualikan** — permission `rencana_aksi:create`/`update`/`ajukan` milik Perencanaan diperoleh lewat peran (§2.4–§2.5) sehingga bersifat **global (tanpa scope unit)**, dan hanya dibatasi oleh `jadwal_tahunan.penutupan` — pola yang identik dengan pengukuran (lihat §2.16, §3).

**Status alur:** mengikuti pola pengukuran — `draft → diajukan → diverifikasi → disahkan`, dengan `dikembalikan` (alasan wajib) sebagai jalur revisi. Permission `rencana_aksi:buka_kembali` (Perencanaan/Superadmin; alasan wajib; hanya tersedia sebelum `jadwal_tahunan.penutupan`) menangani transisi `disahkan → dikembalikan`, sepenuhnya paralel dengan `pengukuran:buka_kembali` (§2.20).

**Gerbang kelengkapan saat pengajuan:** pengajuan rencana aksi (`draft → diajukan`) **ditolak** bila ada `indikator_komponen` aktif yang belum memiliki `rencana_aksi_target` pada salah satu periode yang diharapkan menurut `jadwal_periode` — ini adalah gerbang kelengkapan, bukan sekadar catatan peringatan.

**Pemisahan tugas pada jalur verifikasi/pengesahan:** aturan yang sama seperti pengukuran (§2.20) berlaku di sini — bila rencana aksi diajukan lewat jalur PIC ber-scope unit, aktor pengaju tidak boleh melanjutkan sendiri transisi verifikasi/pengesahannya. Rinciannya di §4.

**PIC rencana aksi = PIC indikator:** tidak ada mekanisme penugasan terpisah untuk rencana aksi. `penanggung_jawab_id` mencatat PIC yang berlaku **pada saat penyusunan** (jejak historis); bila PIC indikator berganti di tengah tahun, hak pengisian rencana aksi tetap mengikuti `penanggung_jawab` yang berlaku saat itu (lihat §2.19), bukan nilai `penanggung_jawab_id` yang sudah tersimpan — dan tetap disyaratkan memiliki grant izin yang sesuai (§2.7).

---

### 2.24 `rencana_aksi_target`

Target per periode per komponen di bawah satu `rencana_aksi`. Target diinput pada level komponen (angka mentah), bukan sebagai skor final indikator.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `rencana_aksi_id` | uuid | FK → rencana_aksi.id, not null | |
| `periode_id` | uuid | FK → periode.id, not null | |
| `komponen_id` | uuid | FK → indikator_komponen.id, not null | |
| `nilai` | numeric | **nullable** | `0` sah; `null` = belum diisi |
| `keterangan` | text | nullable | |
| `updated_by` | uuid | FK → users.id | |
| `updated_at` | timestamp | not null | |

**Constraint:** `unique(rencana_aksi_id, periode_id, komponen_id)`.

**Sifat nilai turunan:** perkiraan skor indikator pada tampilan rencana aksi dihitung dari nilai komponen memakai mesin perhitungan yang sama dengan pengukuran (lihat §2.28) — perkiraan itu tidak disimpan sebagai kolom, murni hasil tampilan.

**Sifat kumulatif:** target triwulan bersifat **kumulatif** — nilai suatu periode mencakup capaian periode-periode sebelumnya dalam tahun yang sama (mis. target Triwulan II = target kumulatif Januari–Juni, bukan hanya April–Juni). Sistem menampilkan **peringatan, bukan blokir**, bila nilai suatu periode lebih kecil dari periode sebelumnya pada komponen yang sama.

**Rekonsiliasi dengan target tahunan PK:** total target komponen pada periode terakhir seharusnya setara dengan hasil hitung target `target_tahunan` tahun tersebut. Ketidaksetaraan **tidak memblokir** pengajuan — sistem menampilkan peringatan dan **mewajibkan alasan** pada saat pengajuan rencana aksi. Target tahunan PK tetap hanya dapat diubah lewat revisi PK resmi (§2.13); deviasi pada rencana aksi wajib terlihat di layar dan tercatat di audit, tidak disesuaikan secara diam-diam.

---

### 2.25 `kegiatan`

Aktivitas konkret yang dilaksanakan unit pada suatu periode, sebagai bukti pendukung capaian indikator. Tidak dimiliki oleh satu rencana aksi/indikator secara langsung — keterkaitannya dengan rencana aksi dan komponen dinyatakan lewat `klaim_kegiatan` (§2.26).

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `unit_id` | uuid | FK → unit.id, not null | |
| `tahun` | int | not null | |
| `periode_id` | uuid | FK → periode.id, not null | Periode **rencana** pelaksanaan |
| `nama` | string | not null | |
| `tujuan` | text | not null | |
| `sasaran_peserta` | int | nullable | Target jumlah peserta |
| `keterangan_peserta` | varchar | nullable | mis. "tim penyusun SPMI dari 30 PTS" |
| `lokasi` | varchar | nullable | |
| `tanggal_rencana` | date | nullable | |
| `tanggal_realisasi` | date | nullable | |
| `anggaran` | numeric | nullable | **-- (kolom tersedia, jalur pengisian/pemakaian menyusul fase lanjutan — modul anggaran)** |
| `status` | enum(`rencana`,`terlaksana`,`tidak_terlaksana`,`ditunda`,`batal`) | not null, default `rencana` | |
| `realisasi_peserta` | int | nullable | |
| `justifikasi` | text | nullable | **Wajib** diisi bila `status` bernilai `tidak_terlaksana`, `ditunda`, atau `batal` |
| `kegiatan_asal_id` | uuid | FK → kegiatan.id, nullable | Menandai kegiatan lanjutan hasil geser periode |
| `uraian_pelaksanaan` | text | nullable | |
| `kendala` | text | nullable | |
| `strategi_tindaklanjut` | text | nullable | |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Kegiatan tidak dihapus saat tidak terlaksana:** kegiatan yang batal berjalan **tidak dihapus** — statusnya diubah menjadi `tidak_terlaksana` (atau `ditunda`/`batal`) **dengan `justifikasi` wajib diisi**. Bila kegiatan digeser ke periode berikutnya, dibuat **baris kegiatan baru** pada periode tujuan dengan `kegiatan_asal_id` menunjuk kegiatan asal — konsekuensi yang disadari: kegiatan yang sama tampil di dua periode (periode asal + periode tujuan), keduanya sah, dibedakan oleh status dan tautan `kegiatan_asal_id`.

**Narasi per kegiatan:** `uraian_pelaksanaan`, `kendala`, `strategi_tindaklanjut` diisi per kegiatan, bukan diketik ulang sebagai satu blok panjang di level indikator. Kolom "Progress Kegiatan / Kendala dan Masalah / Strategi tindaklanjut" pada rekapitulasi indikator × periode **dihasilkan otomatis** dari daftar kegiatan yang diklaim pada rencana aksi indikator itu (lihat §2.26, §2.32).

**Hak ubah:** kegiatan dapat diperbarui (status, realisasi, narasi) oleh PIC unitnya (izin scope unit lewat grant, §2.7) atau Perencanaan (izin global lewat peran, §2.5) selama `jadwal_tahunan` belum `penutupan`; setelah penutupan hanya lewat `jadwal:buka_kembali` (§2.15).

**Gerbang kelengkapan bukti pelaksanaan (SPJ):** transisi status `rencana → terlaksana` **ditolak** bila ada `jenis_berkas` aktif bertanda `wajib` bertahap `kegiatan` yang berlaku bagi kegiatan ini namun belum terpenuhi (lihat §2.29–§2.30). Transisi ke `tidak_terlaksana`/`ditunda`/`batal` tidak melalui gerbang ini — syaratnya tetap `justifikasi` wajib di atas. Setelah kegiatan berstatus `terlaksana`, `berkas` yang menempel padanya menjadi imutabel (§2.30).

**Kegiatan gagal tetap dapat diklaim:** kegiatan berstatus `batal`/`tidak_terlaksana` **tetap boleh diklaim** ke rencana aksi — kegiatan yang diklaim tapi gagal adalah bukti mengapa suatu komponen tidak bergerak; statusnya ikut ditampilkan pada rekapitulasi.

---

### 2.26 `klaim_kegiatan`

Menyatakan kegiatan mana yang mendukung suatu rencana aksi/indikator, dan berdampak pada komponen mana.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `rencana_aksi_id` | uuid | FK → rencana_aksi.id, not null | |
| `kegiatan_id` | uuid | FK → kegiatan.id, not null | |
| `komponen_id` | uuid | FK → indikator_komponen.id, **nullable** | `null` = kegiatan diklaim sebagai pendukung rencana aksi tanpa menunjuk komponen tertentu |
| `arah_dampak` | enum(`menambah`,`mengurangi`) | not null, default `menambah` | |
| `catatan` | varchar | nullable | |
| `sumber_klaim` | enum(`rencana_aksi`,`pengukuran`) | not null | Mencatat tahap saat klaim dibuat — saat penyusunan rencana aksi atau saat pengisian pengukuran; keduanya menulis ke tabel yang sama |
| `pengukuran_id` | uuid | FK → pengukuran.id, nullable | Diisi bila klaim dilakukan pada tahap pengukuran |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |

**Constraint:** `unique(rencana_aksi_id, kegiatan_id, komponen_id)`. Karena PostgreSQL memperlakukan `NULL` sebagai nilai berbeda pada setiap baris (dua baris dengan `komponen_id = NULL` tidak dianggap duplikat oleh unique index standar), implementasinya memakai **`COALESCE(komponen_id, '00000000-0000-0000-0000-000000000000'::uuid)`** (atau nilai sentinel setara) pada definisi index unik, agar klaim tanpa komponen tertentu tetap tunduk pada aturan keunikan per (`rencana_aksi_id`, `kegiatan_id`).

**Klaim tidak mengubah nilai komponen secara otomatis:** nilai `pengukuran_komponen`/`rencana_aksi_target` tetap diisi manual oleh PIC. Menjumlahkan otomatis dari klaim berisiko penghitungan ganda — mis. satu PTS yang hadir di tiga kegiatan tetap dihitung satu kali pada komponen "jumlah PTS yang menerima fasilitasi". Fungsi klaim murni: (a) dokumentasi dukungan & arah dampak, (b) bahan rekapitulasi otomatis (§2.32), (c) pengingat di layar pengisian pengukuran — daftar kegiatan terkait periode itu ditampilkan sebagai pembanding saat PIC mengisi angka komponen.

**Validasi unit:** `kegiatan.unit_id` harus sama dengan unit `rencana_aksi`/indikator yang diklaim; klaim lintas unit **ditolak sistem**.

**Hak hapus:** klaim dapat dihapus oleh PIC pembuat klaim atau Perencanaan, selama induknya (`rencana_aksi`) belum `disahkan`; penghapusan tercatat di `audit_log`.

---

### 2.27 `indikator_komponen`

Definisi komponen angka mentah yang membentuk suatu indikator, dipakai oleh mesin perhitungan berbasis komponen (§2.28). Definisi bersifat data-driven, dikelola lewat aplikasi — tidak di-hardcode.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `indikator_id` | uuid | FK → indikator.id, not null | |
| `kode` | varchar | not null | mis. `n`, `t`, `a`, `b` |
| `label` | text | not null | mis. "n = respon pengguna layanan yang puas" |
| `peran` | enum(`pembilang`,`penyebut`,`penjumlah`) | not null | |
| `bobot` | numeric | not null, default 1 | |
| `urutan` | int | not null | |
| `satuan` | varchar | nullable | |
| `aktif` | boolean | not null, default `true` | |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Constraint:** `unique(indikator_id, kode)`.

**Pengelolaan:** permission granular **`komponen:create`**, **`komponen:read`**, **`komponen:update`**, **`komponen:delete`** — `create`/`read` dipegang seluruh peran sesuai §2.4 (Pegawai/Pimpinan/Admin terbatas pada `read`), sedangkan `update`/`delete` (bertipe `sensitif`, lihat §2.3) hanya dipegang Perencanaan dan Superadmin. Definisi komponen dapat diubah/ditambah lewat aplikasi — mis. saat Kepmen IKU dari kementerian pusat berubah — tanpa memerlukan penyesuaian kode/hardcode. Perubahan (tambah/ubah/nonaktifkan) tercatat di `audit_log` dengan `nilai_lama`/`nilai_baru`.

**Validasi terhadap `indikator.tipe_perhitungan` (lihat §2.12):** kombinasi peran komponen aktif harus memenuhi syarat tipe perhitungan indikator induknya — `rasio_persen` butuh ≥1 `pembilang` aktif & tepat 1 `penyebut` aktif; `penjumlahan` butuh ≥1 `penjumlah` aktif. Penyimpanan yang melanggar syarat ini ditolak.

**Relasi dengan snapshot:** definisi komponen dibekukan ke `jadwal_snapshot_komponen` (§2.18) setiap kali `jadwal_tahunan` diaktifkan/dibuka kembali, sehingga perubahan definisi di tengah tahun tidak mengubah makna data historis yang sudah dirujuk pengukuran.

---

### 2.28 `pengukuran_komponen`

Nilai realisasi per komponen pada suatu `pengukuran`. Untuk indikator bertipe `rasio_persen`/`penjumlahan`, kumpulan baris ini menjadi sumber penghitungan `pengukuran.nilai`.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `pengukuran_id` | uuid | FK → pengukuran.id, not null | |
| `komponen_id` | uuid | FK → indikator_komponen.id, not null | |
| `nilai` | numeric | nullable | `0` sah; `null` = belum diisi |
| `updated_by` | uuid | FK → users.id | |
| `updated_at` | timestamp | not null | |

**Constraint:** `unique(pengukuran_id, komponen_id)`.

**Mesin perhitungan (data-driven, satu tingkat):**

- **`rasio_persen`:**

  ```
  nilai = ( Σ(pembilangᵢ × bobotᵢ) ÷ (penyebut × bobot) ) × 100
  ```

  Bentuk ini menutup varian multi-suku (mis. `(a + b) / t × 100`) maupun varian berbobot (mis. `Σ(nᵢ × kᵢ) / t × 100`) — seluruhnya dinyatakan lewat kombinasi komponen berperan `pembilang` (boleh banyak, masing-masing berbobot) dan tepat satu komponen berperan `penyebut`.

- **`penjumlahan`:**

  ```
  nilai = Σ(penjumlahᵢ × bobotᵢ)
  ```

  mis. IKU jumlah dosen naik jabatan fungsional = (Lektor × bobot) + (Lektor Kepala × bobot) + (Guru Besar × bobot).

- **`manual`:** nilai diketik langsung ke `pengukuran.nilai`, komponen tidak wajib ada.

**Aturan integritas mesin perhitungan:**
- **Pembagian nol:** bila `penyebut = 0` pada `rasio_persen`, nilai **tidak dapat dihitung** — disimpan sebagai `null` pada `pengukuran.nilai` dan ditampilkan sebagai teks **"tidak dapat dihitung"**, bukan `0` dan bukan galat sistem.
- **Pembulatan:** nilai hasil hitung disimpan sesuai `indikator.presisi` dan ditampilkan sesuai `indikator.desimal_tampilan` (kolom lama, tetap berlaku tanpa perubahan makna).
- **Kelengkapan komponen sebagai syarat pengajuan:** lihat §2.20 — pengajuan pengukuran ditolak bila ada komponen aktif bernilai `null`.
- **Satu tingkat perhitungan:** mesin ini hanya mendukung satu tingkat (rasio atau penjumlahan, dengan suku berjumlah banyak dan berbobot). Formula **bertingkat** (sub-skor → nilai komposit → digabung lagi) **tidak dibangun** — indikator seperti Predikat SAKIP dan Nilai Zona Integritas (yang secara konseptual tersusun dari sub-skor 30/30/15/25) memakai `tipe_perhitungan = manual`, nilainya diketik langsung sebagai angka akhir tanpa diturunkan dari sub-skornya. Kebutuhan formula bertingkat di masa depan adalah pengembangan lanjutan, bukan sesuatu yang dapat dikonfigurasi dari layar definisi komponen.

---

### 2.29 `jenis_berkas`

Katalog persyaratan bukti dukung yang ditetapkan **Tim Perencanaan**, per tahap dan (opsional) per indikator. Substansi persyaratan — nama, tahap, wajib/opsional, mode bukti yang diizinkan, `semua_mode_wajib` — adalah wewenang Perencanaan; kebijakan teknis unggahan tingkat aplikasi (saklar unggahan, format & ukuran default) adalah wewenang terpisah milik pemegang `pengaturan:update` (Admin/Superadmin, lihat §2.22).

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `nama` | string | not null | |
| `tahap` | enum(`rencana_aksi`,`pengukuran`,**`kegiatan`**) | not null | Tahap kepatuhan yang mensyaratkan bukti ini; `kegiatan` menaungi persyaratan pertanggungjawaban pelaksanaan kegiatan (SPJ) |
| `indikator_id` | uuid | FK → indikator.id, **nullable** | `null` = berlaku untuk semua indikator |
| `wajib` | boolean | not null, default `false` | |
| `keterangan` | text | nullable | |
| `izinkan_file` | boolean | not null, default `true` | Mode unggahan file diizinkan untuk persyaratan ini |
| `izinkan_tautan` | boolean | not null, default `false` | Mode tautan (URL) diizinkan |
| `izinkan_teks` | boolean | not null, default `false` | Mode keterangan teks diizinkan |
| `semua_mode_wajib` | boolean | not null, default `false` | `false` = cukup **minimal satu** mode yang diizinkan terisi; `true` = **seluruh** mode yang diizinkan wajib terisi |
| `urutan` | int | not null, default `0` | Urutan tampil pada daftar persyaratan |
| `format_diizinkan` | varchar | nullable | mis. `pdf,docx,xlsx,jpg,png`; hanya bermakna bila `izinkan_file = true`; kosong = memakai default dari `pengaturan` |
| `ukuran_maks_kb` | int | nullable | Hanya bermakna bila `izinkan_file = true`; kosong = memakai nilai default dari `pengaturan` |
| `aktif` | boolean | not null, default `true` | |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Pengelolaan:** permission granular **`jenis_berkas:create`**, **`jenis_berkas:read`**, **`jenis_berkas:update`**, **`jenis_berkas:delete`** — `create`/`read` dipegang seluruh peran sesuai §2.4 (Pegawai/Pimpinan/Admin terbatas pada `read`), sedangkan `update`/`delete` (bertipe `sensitif`, lihat §2.3) hanya dipegang Perencanaan dan Superadmin. `tahap` adalah pilihan Perencanaan saat menetapkan persyaratan — berkas bertahap `rencana_aksi` wajib dilampirkan saat penyusunan/pengajuan rencana aksi; berkas bertahap `pengukuran` wajib dilampirkan saat pengisian/pengajuan pengukuran; berkas bertahap `kegiatan` wajib dilampirkan saat kegiatan dinyatakan `terlaksana` (lihat §2.25, §2.30). Satu indikator dapat memiliki persyaratan pada ketiga tahap sekaligus.

**Tiga mode bukti dukung yang dikenal sistem:** `file` (unggahan langsung), `tautan` (URL/link ke dokumen yang disimpan di tempat lain), `teks` (keterangan tertulis). Satu persyaratan dapat mengizinkan lebih dari satu mode sekaligus; PIC memilih di antara mode yang diizinkan saat memenuhi persyaratan (lihat §2.30). Tidak ada daftar mode yang di-hardcode per jenis persyaratan — keleluasaan ini memberi ruang operasional sekaligus menjaga storage, karena persyaratan yang tidak memerlukan arsip fisik cukup diminta sebagai tautan atau teks.

**Validasi saat penyimpanan (Perencanaan):**
- Minimal satu dari `izinkan_file`/`izinkan_tautan`/`izinkan_teks` bernilai `true` — penyimpanan tanpa satu pun mode aktif **ditolak sistem**.
- `format_diizinkan` dan `ukuran_maks_kb` hanya bermakna bila `izinkan_file = true`; nilainya tetap tersimpan bila diisi, tetapi tidak dipakai ketika mode file tidak diizinkan.
- Bila `wajib = true` dan hanya mode `file` yang diizinkan sementara `berkas.unggahan_aktif` sedang bernilai `false` pada setelan aplikasi (§2.22), sistem menampilkan **peringatan** saat penyimpanan: persyaratan tersebut berpotensi tidak dapat dipenuhi PIC (lihat penandaan `tidak_dapat_dipenuhi` pada §2.30).

**Gerbang kelengkapan:**
- **Rencana aksi & pengukuran:** transisi `draft → diajukan` **ditolak** bila ada `jenis_berkas` aktif bertanda `wajib` pada tahap tersebut yang belum terpenuhi untuk induknya — "terpenuhi" dinilai sesuai mode (file terunggah, tautan terisi, atau teks terisi), dengan menghormati aturan `tidak_dapat_dipenuhi` (§2.30).
- **Kegiatan:** transisi status `rencana → terlaksana` **ditolak** bila ada `jenis_berkas` aktif bertanda `wajib` bertahap `kegiatan` yang belum terpenuhi untuk kegiatan tersebut (lihat §2.25). Transisi ke `tidak_terlaksana`/`ditunda`/`batal` **tidak** memerlukan bukti pelaksanaan — syaratnya tetap `justifikasi` wajib, karena kegiatan yang gagal justru tidak memiliki SPJ.
- Pada kedua gerbang, aturan `semua_mode_wajib` dihormati: bila `true`, seluruh mode yang diizinkan pada persyaratan itu harus terpenuhi; bila `false`, cukup minimal satu mode.

---

### 2.30 `berkas`

Wadah bukti dukung, polimorfik terhadap **enam** jenis induk: `rencana_aksi`, `pengukuran`,
`kegiatan`, `renstra`, `renstra_pk`, dan `regulasi`. Mendukung tiga mode pengiriman bukti —
file, tautan, teks — sesuai mode yang diizinkan pada `jenis_berkas` terkait (§2.29) untuk
tiga induk pertama; untuk `renstra`/`renstra_pk`/`regulasi`, mode bebas dipilih pengunggah
tanpa persyaratan bergerbang dari `jenis_berkas`.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `jenis_berkas_id` | uuid | FK → jenis_berkas.id, **nullable** | **-- (kolom tersedia, nullable secara permanen)**. `null` = lampiran bebas di luar daftar persyaratan bergerbang, boleh memakai mode apa pun. Selalu `null` untuk `berkasable_type = renstra`/`renstra_pk`/`regulasi`, karena ketiga induk ini tidak memiliki katalog persyaratan bergerbang |
| `berkasable_type` | varchar | not null | Enam nilai: `rencana_aksi` / `pengukuran` / `kegiatan` / `renstra` / `renstra_pk` / `regulasi` |
| `berkasable_id` | uuid | not null | |
| `mode` | enum(`file`,`tautan`,`teks`) | not null | Cara bukti dikirim |
| `nama_asli` | varchar | **nullable** | Wajib hanya bila `mode = file`; `NULL` pada mode lain |
| `path` | varchar | **nullable** | Wajib hanya bila `mode = file`; `NULL` pada mode lain |
| `mime` | varchar | **nullable** | Wajib hanya bila `mode = file`; `NULL` pada mode lain |
| `ukuran_bytes` | bigint | **nullable** | Wajib hanya bila `mode = file`; `NULL` pada mode lain |
| `tautan` | varchar(2048) | **nullable** | Wajib diisi bila `mode = tautan`; divalidasi berskema `http`/`https` |
| `isi_teks` | text | **nullable** | Wajib diisi bila `mode = teks` |
| `uploaded_by` | uuid | FK → users.id, not null | |
| `created_at` | timestamp | not null | |
| `dihapus_pada` | timestamp | nullable | Soft delete |
| `dihapus_oleh` | uuid | FK → users.id, nullable | |

**Enam induk polimorfik:** mode bukti (file/tautan/teks) dan seluruh aturan mode (§2.29,
validasi skema tautan, kewajiban `isi_teks`) berlaku sama untuk keenam induk. Tiga induk
lama (`rencana_aksi`, `pengukuran`, `kegiatan`) tetap tunduk pada katalog persyaratan
`jenis_berkas` dan gerbang kelengkapannya; tiga induk baru (`renstra`, `renstra_pk`,
`regulasi`) tidak memiliki katalog persyaratan — lampirannya selalu bersifat bebas
(`jenis_berkas_id = null`), dipakai untuk melampirkan dokumen dasar (naskah Renstra, dokumen
Perjanjian Kinerja, salinan produk hukum) dalam mode apa pun.

**Banyak baris per persyaratan:** satu persyaratan (`jenis_berkas`) dapat dipenuhi oleh **lebih dari satu baris** `berkas` — mis. laporan sebagai file dan dokumentasi sebagai tautan pada persyaratan yang sama — terutama bila `semua_mode_wajib = true` pada persyaratan tersebut. Pola yang sama berlaku bagi lampiran bebas pada `renstra`/`renstra_pk`/`regulasi` — satu induk dapat memiliki banyak baris `berkas` tanpa batas jumlah.

**Validasi mode:** mode yang dipilih PIC **harus** termasuk salah satu mode yang diizinkan (`izinkan_file`/`izinkan_tautan`/`izinkan_teks`) pada `jenis_berkas` terkait; permintaan dengan mode di luar daftar **ditolak sistem**. Lampiran bebas (`jenis_berkas_id = null`, termasuk seluruh lampiran pada `renstra`/`renstra_pk`/`regulasi`) dikecualikan dari validasi ini dan boleh memakai mode apa pun.

**Penanda `tidak_dapat_dipenuhi`:** ketersediaan mode tidak boleh memacetkan alur. Bila `berkas.unggahan_aktif` bernilai `false` pada setelan aplikasi (§2.22) sementara suatu persyaratan hanya mengizinkan mode `file`, persyaratan itu ditandai **`tidak_dapat_dipenuhi`** pada tampilan kerja: penandaan ini **tidak memblokir** gerbang kelengkapan (§2.29) maupun gerbang lampiran PK (§2.15 gerbang keempat), tercatat di `audit_log`, dan tampil pada rekapitulasi serta halaman kerja Perencanaan agar terlihat dan dapat diperbaiki (menambahkan mode `tautan`/`teks` pada persyaratan itu, atau mengaktifkan kembali unggahan file).

**Imutabilitas — mengikuti status induk (per induk berbeda):**
- lampiran `renstra` **tidak dapat dihapus** setelah baris `renstra` yang bersangkutan berstatus `aktif`;
- lampiran `renstra_pk` **tidak dapat dihapus** setelah `jadwal_tahunan` tahun tersebut berstatus `aktif`;
- lampiran `regulasi` **tidak dapat dihapus** selama regulasi yang bersangkutan masih dirujuk oleh `renstra.regulasi_id` atau `indikator.regulasi_id` yang aktif;
- lampiran `kegiatan` **tidak dapat dihapus** setelah kegiatan yang bersangkutan berstatus `terlaksana`;
- lampiran `rencana_aksi`/`pengukuran` **tidak dapat dihapus** setelah induknya berstatus `disahkan`.

Sebelum batas di atas tercapai, Perencanaan (izin global lewat peran) dan pengunggah/PIC unit terkait (izin `berkas:delete` ber-scope unit lewat grant, untuk `rencana_aksi`/`pengukuran`/`kegiatan`) dapat menghapus lampiran; setiap penghapusan tercatat di `audit_log` (soft delete lewat `dihapus_pada`/`dihapus_oleh`). Setelah `jadwal_tahunan.penutupan`, koreksi atas lampiran `rencana_aksi`/`pengukuran`/`kegiatan` hanya lewat `jadwal:buka_kembali` — aturan ini tidak berlaku bagi lampiran `renstra`/`renstra_pk`/`regulasi`, yang batas imutabilitasnya murni mengikuti status induknya masing-masing seperti tercantum di atas.

**Penyimpanan mode `file`:** berkas mode file disimpan di disk VPS (`storage/app/berkas/...`) dan diakses lewat route ber-permission (streamed download) — **bukan** URL publik. Untuk induk `rencana_aksi`/`pengukuran`/`kegiatan`, jenis dan ukuran file divalidasi terhadap `jenis_berkas.format_diizinkan`/`ukuran_maks_kb`; nilai default (bila `jenis_berkas` tidak menetapkannya) diambil dari kunci grup `berkas` pada `pengaturan` (§2.22). Untuk induk `renstra`/`renstra_pk`/`regulasi` (lampiran bebas, tanpa `jenis_berkas`), validasi format/ukuran memakai langsung nilai default kunci grup `berkas` pada `pengaturan`.

**Lampiran bebas di level kegiatan:** lampiran pada `berkasable_type = kegiatan` tanpa `jenis_berkas_id` tersedia sebagai **lampiran bebas** (bukan persyaratan bergerbang) — bukti pelaksanaan kegiatan tambahan (daftar hadir, dokumentasi) menempel pada kegiatannya, boleh memakai mode apa pun, dan ikut tampil pada rekapitulasi indikator × periode.

**Lampiran dokumen dasar (`renstra`/`renstra_pk`/`regulasi`):** halaman Renstra menampilkan lampiran dokumen Renstra beserta rujukan `regulasi_id` dan ringkasan `dasar_hukum` (§2.9, §2.33); halaman Perjanjian Kinerja menampilkan lampiran dokumen PK beserta `nomor_pk`/`tanggal_pk` (§2.10); halaman "Dasar Aturan" menampilkan CRUD `regulasi` beserta lampirannya (§2.33). Ketiga jenis lampiran ini murni bersifat bebas (`jenis_berkas_id = null`) — tidak ada katalog persyaratan bergerbang untuk dokumen dasar, kecuali gerbang aktivasi jadwal yang mensyaratkan minimal satu lampiran `renstra_pk` (§2.15).

---

### 2.31 `rekomendasi_pimpinan`

Catatan rekomendasi/arahan atas capaian suatu indikator pada suatu periode, ditetapkan Perencanaan pada Fase Awal.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `indikator_id` | uuid | FK → indikator.id, not null | |
| `tahun` | int | not null | |
| `periode_id` | uuid | FK → periode.id, not null | |
| `isi` | text | not null | |
| `ditetapkan_oleh` | uuid | FK → users.id, not null | |
| `created_at` | timestamp | not null | |

**Pola "aktif":** baris terbaru per (`indikator_id`, `tahun`, `periode_id`) adalah yang berlaku; baris lama tetap tersimpan sebagai riwayat (soft replace, tidak dihapus) — pola yang sama dengan `status_capaian` (§2.21).

**Pengisi pada Fase Awal:** permission **`rekomendasi:tetapkan`** (bertipe `sensitif`, lihat §2.3) dipegang **Perencanaan**, bukan Pimpinan — alur approval/persetujuan Pimpinan masih ditunda ke Fase Lanjutan, dan Pimpinan belum masuk alur aktif. Pimpinan tetap **read-only** atas data ini.

**Independensi dari status `pengukuran`:** rekomendasi melekat pada kombinasi indikator × periode, terpisah dari siklus status `pengukuran` — dapat diisi setelah rapat evaluasi triwulan tanpa menunggu pengukuran periode itu berstatus `disahkan`.

---

### 2.32 `audit_log`

Pencatatan append-only seluruh peristiwa penting sistem.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `actor_id` | uuid | FK → users.id | Pengguna yang melakukan tindakan |
| `waktu` | timestamp | not null | |
| `tindakan` | varchar | not null | mis. `renstra.aktivasi`, `pengukuran.kembalikan`, `indikator.pindah_unit`, `unit.hapus`, `jadwal.aktivasi`, `jadwal.buka_kembali`, `pengaturan.ubah`, `rencana_aksi.ajukan`, `rencana_aksi.verifikasi`, `rencana_aksi.kembalikan`, `rencana_aksi.sahkan`, `rencana_aksi.buka_kembali`, `kegiatan.buat`, `kegiatan.ubah_status`, `kegiatan.geser_periode`, `klaim_kegiatan.tambah`, `klaim_kegiatan.hapus`, `indikator_komponen.ubah`, `indikator.ubah_tipe_perhitungan`, `jenis_berkas.ubah`, `berkas.unggah`, `berkas.hapus`, **`berkas.tandai_tidak_dapat_dipenuhi`** (penandaan otomatis saat mode `file` satu-satunya yang diizinkan sementara unggahan dinonaktifkan, termasuk pada gerbang lampiran PK §2.15), `rekomendasi_pimpinan.tetapkan`, **`regulasi.buat`/`regulasi.ubah`/`regulasi.hapus`** (perubahan katalog dasar aturan, §2.33), **`renstra.ubah_regulasi`/`indikator.ubah_regulasi`** (perubahan rujukan `regulasi_id`), **`role_permissions.ubah`** (isi peran ditambah/dikurangi), **`user_roles.tambah`/`user_roles.ubah`/`user_roles.hapus`** (penetapan/pergantian/pencabutan peran pengguna), **`user_permission_granted.tambah`/`user_permission_granted.hapus`** (grant izin), **`user_permission_denials.tambah`/`user_permission_denials.hapus`** (deny izin), serta peristiwa **percobaan tindakan yang ditolak** (mis. gerbang rencana aksi belum disahkan, komponen belum lengkap, berkas wajib belum lengkap, gerbang kegiatan `rencana → terlaksana` belum lengkap, gerbang lampiran PK belum lengkap, permintaan izin yang berakhir tolak pada resolusi §3) |
| `objek_tipe` | varchar | not null | Nama entitas terkait, mis. `renstra`, `pengukuran`, `indikator`, `jadwal_snapshot`, `pengaturan`, `rencana_aksi`, `kegiatan`, `klaim_kegiatan`, `indikator_komponen`, `jenis_berkas`, `berkas`, `rekomendasi_pimpinan`, **`regulasi`**, **`role_permissions`, `user_roles`, `user_permission_granted`, `user_permission_denials`** |
| `objek_id` | uuid | not null | ID baris entitas terkait |
| `nilai_lama` | jsonb | nullable | Snapshot kondisi sebelum perubahan |
| `nilai_baru` | jsonb | nullable | Snapshot kondisi sesudah perubahan |
| `alasan` | text | nullable | Wajib diisi (validasi aplikasi) untuk tindakan tertentu: koreksi PK, pengembalian pengukuran, pergantian penanggung jawab, penghapusan unit, `jadwal:buka_kembali`, `rencana_aksi:buka_kembali`, penandaan kegiatan `tidak_terlaksana`/`ditunda`/`batal`, **perubahan/penghapusan `regulasi` (`regulasi:update`/`regulasi:delete`)**, **perubahan isi peran (`role_permissions`), penetapan/pergantian peran pengguna (`user_roles`), setiap grant (`user_permission_granted`) dan setiap deny (`user_permission_denials`)**, dan tindakan sensitif lain yang ditetapkan PRD |
| `dasar_izin` | jsonb | **nullable** | **Wajib diisi** untuk aksi atas permission bertanda `permissions.sensitif = true` (lihat §2.3, §3.5): daftar sumber izin yang membuat aksi diizinkan (peran mana / grant mana yang cocok), atau deny mana yang memicu penolakan. `null` untuk tindakan yang tidak melalui gerbang permission sensitif |

**Sifat:** tabel ini tidak memiliki endpoint update/delete di aplikasi — hanya `INSERT`. Tidak ada `updated_at`/`deleted_at` karena baris bersifat final begitu ditulis.

**Catatan actor pada peristiwa otomatis:** baris yang mencatat pembuatan `jadwal_snapshot` beserta `jadwal_snapshot_komponen` (tindakan `jadwal.aktivasi`/`jadwal.buka_kembali`, lihat §2.17–§2.18) selalu memakai `actor_id` = pengguna Perencanaan/Superadmin yang menjalankan aksi aktivasinya, bukan nilai sistem/null — meski proses pembuatan barisnya sendiri berjalan otomatis di dalam transaksi yang sama. Perubahan pada tabel `pengaturan` (§2.22) mengikuti pola yang sama: `actor_id` = pengguna Superadmin yang mengubah nilai setelan.

**Jejak perubahan definisi berbasis komponen:** perubahan pada `indikator_komponen` dan `jenis_berkas` dicatat dengan `nilai_lama`/`nilai_baru` dan `alasan` — inilah jejak yang menjaga data historis tetap dapat dipertanggungjawabkan meski formula perhitungan dan katalog persyaratan berkas dapat diubah dari layar aplikasi, tanpa memerlukan deployment kode baru.

**Jejak perubahan model akses:** karena izin dievaluasi hidup saat request (§3) dan tidak lagi disalin ke baris statis per pengguna, jejak "kenapa orang ini boleh melakukan sesuatu" tidak bisa lagi dibaca langsung dari satu baris izin. Kolom `dasar_izin` di atas, ditambah pencatatan wajib pada `role_permissions`, `user_roles`, `user_permission_granted`, dan `user_permission_denials`, adalah pengganti fungsi tersebut. Halaman **"Jelaskan izin pengguna"** (permission `pengguna:read`, dipegang Admin/Superadmin — tidak ada permission baru untuk halaman ini) membaca gabungan sumber-sumber ini untuk menampilkan izin efektif seorang pengguna per unit, lengkap dengan asal tiap izin (peran/grant) dan deny yang berlaku.

### 2.33 `regulasi`

Katalog dasar aturan (produk hukum) yang menjadi acuan penyusunan Renstra maupun penetapan
indikator kinerja — mis. Kepmen yang menetapkan IKU, Permendikbud, atau Perpres yang menjadi
rujukan kebijakan. Entitas ini memusatkan rujukan dasar hukum agar dapat ditelusuri terstruktur
per Renstra maupun per indikator, tanpa mengunggah dokumen yang sama berulang-ulang.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `jenis` | enum(`kepmen`,`permen`,`perpres`,`keputusan_lainnya`) | not null | Jenis produk hukum |
| `nomor` | varchar | not null | Nomor dokumen |
| `tahun` | int | not null | Tahun penetapan |
| `tentang` | text | not null | Pokok pengaturan |
| `tanggal` | date | nullable | Tanggal penetapan |
| `tautan_sumber` | varchar(2048) | nullable | Sumber resmi (JDIH/laman kementerian) |
| `catatan` | text | nullable | |
| `aktif` | boolean | not null, default `true` | |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Constraint:** `unique(jenis, nomor, tahun)`.

**Rujukan dari entitas lain:** `renstra.regulasi_id` (§2.9) dan `indikator.regulasi_id`
(§2.12) menunjuk ke tabel ini sebagai rujukan terstruktur dasar hukum; keduanya **nullable**,
karena tidak setiap Renstra/indikator memiliki satu produk hukum tunggal sebagai acuannya
pada saat data pertama kali dimasukkan. Kolom `renstra.dasar_hukum` (teks bebas, §2.9)
**tetap dipertahankan** sebagai ringkasan yang dibaca cepat, berdampingan dengan rujukan
terstruktur `regulasi_id` — keduanya tidak saling menggantikan.

**Lampiran dokumen:** `regulasi` menjadi salah satu induk polimorfik `berkas`
(`berkasable_type = regulasi`, lihat §2.30) — dokumen sumber produk hukum (mis. salinan PDF
Kepmen) dapat dilampirkan dalam mode file/tautan/teks seperti induk lain.

**Imutabilitas:** lampiran `berkas` pada `regulasi` tidak dapat dihapus selama regulasi
tersebut masih dirujuk oleh `renstra` atau `indikator` yang berstatus aktif (lihat aturan
lengkap pada §2.30).

**Pengelolaan:** permission granular **`regulasi:create`**, **`regulasi:read`**,
**`regulasi:update`**, **`regulasi:delete`** — `update` dan `delete` bertanda `sensitif =
true` (lihat §2.3). Isi peran:

- **Perencanaan** dan **Superadmin** — keempat aksi.
- **Admin**, **Pimpinan**, **Pegawai/PIC** — `regulasi:read` saja, agar dasar aturan tampil
  pada halaman Renstra/indikator dan terbaca konteksnya. Admin tidak memperoleh wewenang
  substantif atas katalog ini, konsisten dengan pemisahan tugas administrasi teknis vs
  wewenang substantif (lihat §2.4).

**Halaman kerja:** CRUD `regulasi` beserta lampirannya tersedia pada halaman kerja tersendiri
("Dasar Aturan"); halaman Renstra menampilkan lampiran dokumen, rujukan `regulasi_id`, dan
ringkasan `dasar_hukum` sekaligus; halaman indikator menampilkan rujukan `regulasi_id` bila
diisi.

**Audit:** pembuatan/ubah/hapus `regulasi` tercatat dengan `nilai_lama`/`nilai_baru` dan
`alasan`; perubahan `renstra.regulasi_id`/`indikator.regulasi_id` turut tercatat sebagai
perubahan pada baris `renstra`/`indikator` yang bersangkutan (lihat §2.32).

---

---

## 3. Algoritma Resolusi Izin

Bagian ini mendefinisikan secara eksplisit bagaimana sistem menjawab pertanyaan otorisasi **"boleh(kode_permission, unit_target?)"** untuk seorang aktor. Resolusi ini menggantikan model lama "salinan baris izin per pengguna" — permission tidak lagi disalin, melainkan dihitung dari kombinasi peran (§2.4–§2.6), grant (§2.7), dan deny (§2.8) **setiap kali permintaan dievaluasi**.

### 3.1 Pertanyaan yang dijawab

Setiap pemeriksaan otorisasi berbentuk `boleh(kode_permission, unit_target?)`. Parameter `unit_target` bersifat opsional:
- Untuk aksi yang menyasar record ber-unit (mis. mengubah pengukuran milik unit A), `unit_target = A` disertakan.
- Pertanyaan **tanpa** `unit_target` hanya sah diajukan untuk permission bertipe `global` (`permissions.butuh_scope = global`, §2.3).

### 3.2 Langkah resolusi

1. **Fail closed.** Bila tidak ada baris `permissions` aktif (`aktif = true`) dengan kode yang dimaksud, jawabannya **langsung tolak** — tidak ada permission yang "dianggap boleh secara default".
2. **Susun himpunan allow.** Allow terdiri dari:
   - seluruh permission yang berasal dari peran pengguna (`user_roles` → `role_permissions`, §2.6→§2.5), yang selalu diperlakukan **global**; dan
   - baris `user_permission_granted` milik pengguna yang cocok dengan permission yang ditanyakan (§2.7).
3. **Susun himpunan deny.** Deny terdiri dari baris `user_permission_denials` milik pengguna yang cocok dengan permission yang ditanyakan (§2.8).
4. **Pencocokan scope.**
   - Untuk pertanyaan dengan `unit_target = U`: deny cocok bila `unit_id IS NULL` **atau** `unit_id = U`; grant cocok bila `unit_id = U` (grant global, `unit_id IS NULL`, hanya cocok untuk permission bertipe `global`).
   - Untuk pertanyaan **tanpa** `unit_target`: deny ber-`unit_id` tertentu **tidak** menghalangi (izin untuk unit lain tetap berlaku secara terpisah); deny dengan `unit_id IS NULL` selalu menghalangi.
5. **Presedens: DENY MENANG.**
   - Ada deny yang cocok → **TOLAK**, terlepas dari allow apa pun yang cocok.
   - Tidak ada deny yang cocok, tetapi ada allow yang cocok → **IZINKAN**.
   - Tidak ada allow yang cocok (dengan atau tanpa deny) → **TOLAK**.
6. **Jangan campur dengan validasi bisnis.** Jendela waktu (`jadwal_tahunan.penutupan`, `jadwal_periode.pengisian_selesai`, `jadwal_tahunan.rencana_aksi_selesai`), kepemilikan unit atas record yang diakses, dan gerbang kelengkapan (rencana aksi belum disahkan, komponen belum lengkap, berkas wajib belum lengkap) adalah **validasi bisnis** yang berjalan **setelah** langkah 1–5 di atas menyatakan "boleh". Kontraknya: resolusi izin menjawab "apakah aktor ini secara prinsip boleh melakukan aksi ini", validasi bisnis menjawab "apakah saat ini/untuk record ini aksi tersebut masih sah dilakukan". Kedua lapisan ini **tidak boleh digabung** dalam satu pemeriksaan — kegagalan validasi bisnis tidak pernah diperlakukan sebagai "izin ditolak", dan sebaliknya.
7. **Tidak ada evaluasi izin di sisi klien.** Resolusi hanya dijalankan di server (satu service resolusi terpusat, lihat §3.5). Halaman React dapat menyembunyikan tombol/menu berdasarkan hasil pemeriksaan dari server (mis. lewat props halaman), tetapi ini murni kenyamanan tampilan — server **selalu** mengevaluasi ulang setiap permintaan yang benar-benar mengubah/mengambil data, tanpa mempercayai keputusan yang sudah dibuat di klien sebelumnya.

### 3.3 Lokasi scope unit

- Permission hasil peran (`role_permissions`, §2.5) **selalu global** — tabel ini sengaja tidak memiliki kolom `unit_id`.
- Scope unit hidup **hanya** pada `user_permission_granted` (§2.7), dan dapat dicabut per unit lewat `user_permission_denials` (§2.8).
- Konsekuensi pola pemakaian:
  - **"PIC unit A"** = peran `pegawai` (izin baca dasar, global) **+** grant `pengukuran:create`/`update`, `rencana_aksi:create`/`update`/`ajukan`, `kegiatan:create`/`update` dengan `unit_id = A`.
  - **"Perencanaan global"** = peran `perencanaan` (izin `create`/`update`/`ajukan` sudah global lewat isi peran, tanpa perlu grant tambahan).

### 3.4 Implementasi: satu service resolusi terpusat

Resolusi izin dijalankan lewat **satu service/Policy/Gate terpusat** yang dipanggil dari seluruh controller/handler backend — bukan logika allow/deny yang tersebar dan diduplikasi di masing-masing controller. Ini menjamin langkah 1–6 di §3.2 selalu diterapkan konsisten, dan menjadi satu-satunya titik yang perlu diubah/diaudit bila aturan resolusi berubah.

### 3.5 Audit dasar izin untuk aksi sensitif

Untuk setiap aksi atas permission bertanda `permissions.sensitif = true` (§2.3), baris `audit_log` yang dihasilkan **wajib** mengisi kolom `dasar_izin` (§2.32) dengan sumber izin yang membuat aksi tersebut diizinkan (peran mana, dan/atau grant mana yang cocok) — atau, bila aksi ditolak akibat deny, deny mana yang memicu penolakan. Kebutuhan ini muncul justru karena izin dievaluasi hidup: jejak "kenapa orang ini boleh melakukan ini" tidak lagi dapat dibaca dari satu baris izin statis seperti pada model lama.

---

## 4. Pemisahan Tugas (Segregation of Duties)

Bagian ini adalah **aturan bisnis**, bukan bagian dari resolusi izin di §3 — aturan ini berjalan **setelah** aktor dinyatakan lolos pemeriksaan `boleh(...)`, dan tidak dapat "dinonaktifkan" lewat pemberian grant apa pun. Ini yang membedakannya dari deny (§2.8): deny bersifat administratif dan dapat ditambah/dicabut, sedangkan aturan pemisahan tugas di bawah ini adalah logika tetap pada service layer.

### 4.1 F1 — Larangan pengaju jalur PIC menyetujui pengukurannya sendiri (keras)

Pengaju `pengukuran`/`rencana_aksi` yang mengisi lewat izin ber-scope unit (jalur PIC, lewat grant §2.7) **TIDAK DAPAT** memverifikasi atau mengesahkan baris yang ia ajukan sendiri. Aturan ini **keras** — tidak dapat dikonfigurasi lewat mekanisme apa pun, termasuk grant/deny — supaya tidak bisa "dinonaktifkan" secara administratif oleh siapa pun, termasuk Superadmin.

**Implementasi:** pada setiap transisi `diajukan → diverifikasi` dan `diverifikasi → disahkan` (baik untuk `pengukuran.status_alur` maupun `rencana_aksi.status_alur`), sistem membandingkan `actor_id` aktor yang menjalankan transisi dengan `pengukuran.created_by`/`rencana_aksi.created_by` baris yang bersangkutan. Bila keduanya sama **dan** pengajuan berasal dari jalur izin ber-scope unit, transisi **ditolak** — terlepas dari permission `pengukuran:verifikasi`/`pengukuran:sahkan`/`rencana_aksi:verifikasi`/`rencana_aksi:sahkan` apa pun yang dimiliki aktor tersebut.

### 4.2 F2 — Pengecualian bagi jalur Perencanaan (self_approval bertanda)

Jalur **Perencanaan** yang mengisi pengukuran/rencana aksi atas nama unit (lewat permission global hasil peran, dipakai saat tenggat PIC terlewat atau untuk keperluan backfill) **diizinkan** diverifikasi/disahkan oleh orang yang sama. Konsekuensinya:

- Aksi tersebut diberi **penanda `self_approval`** pada baris `audit_log` yang mencatat transisi verifikasi/pengesahannya.
- Penanda ini ikut ditampilkan sebagai indikator di dashboard/laporan Perencanaan — memberi visibilitas atas kejadian ini, bukan menyembunyikannya.

**Alasan keputusan:** bila jalur ini diblokir sepenuhnya seperti F1, LLDIKTI Wilayah XVI wajib menugaskan minimal dua akun Perencanaan agar pengisian yang terlambat masih dapat disahkan — kondisi yang belum tentu terpenuhi pada Fase Awal. Aturan keras yang setara F1 untuk jalur Perencanaan tetap dapat diberlakukan di kemudian hari bila jumlah personel Perencanaan sudah mencukupi.

### 4.3 F3 — Relasi dengan resolusi izin

F1 dan F2 **tidak menggantikan** resolusi izin pada §3: aktor tetap harus lolos `boleh(pengukuran:verifikasi | pengukuran:sahkan | rencana_aksi:verifikasi | rencana_aksi:sahkan, unit_target?)` terlebih dahulu. F1/F2 adalah validasi bisnis tambahan yang berjalan sesudahnya, khusus untuk mencegah pengaju menyetujui pekerjaannya sendiri — persis pola yang sama seperti gerbang jendela waktu dan gerbang kelengkapan berkas (§3.2 langkah 6).

---

## 5. Ringkasan Constraint Unik (Cross-Reference)

| Tabel | Constraint |
|---|---|
| `users` | unique(`keycloak_id`) |
| `permissions` | unique(`kode`) |
| `roles` | unique(`kode`) |
| `role_permissions` | unique(`role_id`, `permission_id`) |
| `user_roles` | unique(`user_id`) — satu pengguna, tepat satu peran, pada Fase Awal |
| `user_permission_granted` | unique(`user_id`, `permission_id`, `unit_id`) — implementasi index memakai `COALESCE(unit_id, sentinel)` karena PostgreSQL memperlakukan `NULL` sebagai nilai berbeda antarbaris |
| `user_permission_denials` | unique(`user_id`, `permission_id`, `unit_id`) — pola `COALESCE` yang sama dengan `user_permission_granted` |
| `regulasi` | unique(`jenis`, `nomor`, `tahun`) |
| `renstra_pk` | unique(`renstra_id`, `tahun`) |
| `target_tahunan` | unique(`indikator_id`, `tahun`) |
| `jadwal_tahunan` | partial unique index (`renstra_id`, `tahun`) `WHERE status = 'aktif'` |
| `jadwal_tahunan` (level aplikasi) | aktivasi mensyaratkan EMPAT gerbang: `renstra_pk` tersedia; seluruh indikator aktif memiliki `target_tahunan`; `tahun` berada dalam rentang Renstra; minimal satu lampiran `berkas` pada `renstra_pk` terkait (gerbang keempat, dapat ditandai `tidak_dapat_dipenuhi` tanpa memblokir aktivasi bila unggahan file dimatikan) |
| `jadwal_periode` | unique(`jadwal_id`, `periode_id`) |
| `jadwal_snapshot` | unique(`jadwal_id`, `indikator_id`) — menjamin idempotensi pembuatan snapshot |
| `pengukuran` | unique(`indikator_id`, `tahun`, `periode_id`) |
| `pengaturan` | unique(`kunci`) |
| `rencana_aksi` | unique(`indikator_id`, `tahun`) |
| `rencana_aksi_target` | unique(`rencana_aksi_id`, `periode_id`, `komponen_id`) |
| `klaim_kegiatan` | unique(`rencana_aksi_id`, `kegiatan_id`, `komponen_id`) — implementasi index memakai `COALESCE(komponen_id, sentinel)` karena PostgreSQL memperlakukan `NULL` sebagai nilai berbeda antarbaris |
| `indikator_komponen` | unique(`indikator_id`, `kode`) |
| `pengukuran_komponen` | unique(`pengukuran_id`, `komponen_id`) |
| `renstra` (level aplikasi) | tidak boleh 2 baris `aktif` dengan rentang `[tahun_mulai, tahun_akhir]` beririsan; direkomendasikan exclusion constraint (`EXCLUDE USING gist` + `btree_gist`) sebagai lapisan pertahanan kedua |
| `renstra` (level aplikasi) | tidak dapat ditransisikan keluar dari `aktif` selama masih ada `jadwal_tahunan` berstatus `aktif` yang mengacunya |
| `indikator` (level aplikasi) | `pengukuran:create` ditolak untuk indikator berstatus `arsip` |
| `indikator` (level aplikasi) | `rasio_persen` wajib ≥1 komponen `pembilang` aktif & tepat 1 komponen `penyebut` aktif; `penjumlahan` wajib ≥1 komponen `penjumlah` aktif |
| `periode` (level aplikasi) | tepat satu baris `is_nilai_akhir = true` |
| `pengukuran` (level aplikasi) | pengajuan ditolak selama `rencana_aksi` (indikator × tahun) belum `disahkan`; ditolak bila ada komponen aktif bernilai `null` |
| `rencana_aksi` (level aplikasi) | pengajuan ditolak bila ada komponen aktif tanpa `rencana_aksi_target` pada salah satu periode yang diharapkan |
| `klaim_kegiatan` (level aplikasi) | ditolak bila `kegiatan.unit_id` berbeda dari unit rencana aksi/indikator yang diklaim |
| `berkas` (level aplikasi) | imutabilitas per induk (enam nilai `berkasable_type`): `renstra` tidak dapat dihapus setelah `renstra` berstatus `aktif`; `renstra_pk` tidak dapat dihapus setelah `jadwal_tahunan` tahun tersebut `aktif`; `regulasi` tidak dapat dihapus selama masih dirujuk `renstra`/`indikator` aktif; `kegiatan` tidak dapat dihapus setelah kegiatan `terlaksana`; `rencana_aksi`/`pengukuran` tidak dapat dihapus setelah induknya `disahkan` |
| `jenis_berkas` (level aplikasi) | minimal satu dari `izinkan_file`/`izinkan_tautan`/`izinkan_teks` bernilai `true`; penyimpanan tanpa satu pun mode aktif ditolak |
| `berkas` (level aplikasi) | mode wajib termasuk mode yang diizinkan pada `jenis_berkas` terkait, kecuali lampiran bebas (`jenis_berkas_id = null`); `tautan` wajib berskema `http`/`https` bila `mode = tautan`; `isi_teks` wajib terisi bila `mode = teks`; `nama_asli`/`path`/`mime`/`ukuran_bytes` wajib terisi bila `mode = file` |
| `kegiatan` (level aplikasi) | transisi status `rencana → terlaksana` ditolak bila ada `jenis_berkas` aktif bertanda `wajib` bertahap `kegiatan` yang belum terpenuhi |
| `kegiatan` (level aplikasi) | tidak dihapus saat tidak terlaksana — wajib berubah status + `justifikasi`; geser periode wajib lewat baris baru + `kegiatan_asal_id` |
| `user_permission_granted` (level aplikasi) | grant untuk permission `butuh_scope = unit` ditolak bila `unit_id` kosong; grant untuk permission `butuh_scope = global` ditolak bila `unit_id` terisi |
| `pengukuran`/`rencana_aksi` (level aplikasi, pemisahan tugas) | transisi verifikasi/pengesahan ditolak bila aktor = pengaju pada jalur izin ber-scope unit (F1, §4.1), kecuali jalur Perencanaan yang ditandai `self_approval` (F2, §4.2) |

---

## 6. Ringkasan Kolom/Entitas "Tersedia tapi Belum Dipakai" (Fase Awal)

Daftar eksplisit kolom/entitas yang ada di skema sejak migrasi pertama namun jalur pengisian/pemakaiannya ditunda ke Fase Lanjutan — dicantumkan agar tim pengembang tidak keliru mengira ini kolom yang "lupa dipakai" atau bug:

| Tabel | Kolom/Aspek | Status Fase Awal |
|---|---|---|
| `jadwal_tahunan` | `pakai_persetujuan_pimpinan` | Selalu `false`; tidak dicek di logika alur pengesahan |
| `jadwal_tahunan` | `persetujuan_mulai` | Selalu `null`; tidak ditampilkan sebagai jendela aktif di UI |
| `jadwal_tahunan` | `persetujuan_selesai` | Selalu `null`; tidak ditampilkan sebagai jendela aktif di UI |
| `status_capaian` | `sumber` (nilai `data_sumber`) | Nilai enum ini valid secara skema namun **tidak pernah ditulis** oleh kode Fase Awal — seluruh baris ditulis dengan `sumber = 'manual'` |
| `kegiatan` | `anggaran` | Kolom tersedia sejak migrasi pertama; jalur pengisian/pemakaian (modul anggaran, rekonsiliasi realisasi anggaran) menyusul Fase Lanjutan — Fase Awal tidak menampilkan maupun memvalidasi nilai ini |
| `berkas` | `jenis_berkas_id` | **Nullable secara permanen**, bukan sementara — dipakai penuh sejak Fase Awal untuk membedakan lampiran wajib (mengacu `jenis_berkas`) dari lampiran bebas (`null`); dicantumkan di sini semata agar tidak disangka kolom yang seharusnya selalu terisi |
| `berkas` | `nama_asli`/`path`/`mime`/`ukuran_bytes` | **Nullable secara permanen sejak diperkenalkannya mode bukti dukung** — bukan kolom yang "lupa diisi": ketiganya wajib hanya pada baris `mode = file`, dan bernilai `NULL` pada baris `mode = tautan`/`teks` sebagai konsekuensi normal desain multi-mode |
| `user_roles` | constraint `unique(user_id)` | **Batas Fase Awal yang disadari, bukan celah desain.** Struktur tabel sudah berbentuk pivot; multi-peran per pengguna (satu pengguna memegang lebih dari satu peran sekaligus) dapat dibuka di Fase Lanjutan hanya dengan melepas constraint ini — tidak memerlukan migrasi struktural baru |
| UI pengelolaan akses | matrix permission penuh | Fase Awal menyediakan **3 form**: (1) assign peran (`user_roles`), (2) kelola grant izin per unit (`user_permission_granted`), (3) kelola deny izin (`user_permission_denials`), ditambah halaman "Jelaskan izin pengguna" (§2.32). UI matrix permission penuh (menampilkan/mengubah seluruh kombinasi peran × permission dalam satu tampilan tabel) ditunda ke Fase Lanjutan |
| `user_permission_granted` | masa berlaku grant (`berlaku_sampai`) | **Belum berupa kolom skema pada Fase Awal** — dicatat di sini sebagai kebutuhan yang mungkin muncul di Fase Lanjutan (grant yang otomatis kedaluwarsa pada tanggal tertentu, mis. penugasan sementara). Bila dibutuhkan, penambahannya adalah migrasi kolom baru bertipe `date, nullable` pada `user_permission_granted`, bukan perubahan struktural |

Catatan tambahan: permission `pengukuran:setujui` dan peran approval Pimpinan juga "tersedia tapi belum dipakai" secara fungsional (bukan kolom skema, melainkan kode alur) — didefinisikan penuh di katalog permission (`permissions`, §2.3), tapi belum ada state machine/UI yang memanggilnya pada Fase Awal. Kolom `rekomendasi_pimpinan.ditetapkan_oleh` secara skema menerima id pengguna mana pun, tetapi pada Fase Awal secara operasional selalu diisi pengguna Perencanaan (§2.31) — pengisian oleh Pimpinan sendiri adalah perluasan Fase Lanjutan yang tidak memerlukan migrasi baru.

---

## 7. Prinsip Desain yang Mendasari Skema

1. UUID dipakai sebagai primary key di seluruh tabel — memudahkan referensi lintas tabel tanpa bocor informasi urutan/volume data, dan cukup aman untuk sinkronisasi/replikasi di masa depan bila diperlukan.
2. Snapshot ditempatkan di atas referensi langsung untuk konteks historis (`jadwal_snapshot`, `jadwal_snapshot_komponen`), memisahkan "apa yang berlaku sekarang" (master) dari "apa yang berlaku saat pengukuran dilakukan" (snapshot) — termasuk cara hitungnya, bukan hanya nilainya. Pembuatan baris snapshot dijaga idempoten dan diperlakukan abadi setelah dirujuk, sehingga tidak ada jalur restatement data historis yang tidak sengaja.
3. Riwayat dicatat sebagai baris baru, bukan mutasi in-place — berlaku untuk `penanggung_jawab`, `status_capaian`, dan `rekomendasi_pimpinan`. Ketiga tabel ini sengaja tidak memiliki mekanisme update-in-place atas makna intinya (siapa PJ efektif / apa status capaian aktif / rekomendasi mana yang berlaku); nilainya dihitung dari baris terbaru.
4. Optimistic locking eksplisit (`pengukuran.versi`, `rencana_aksi.versi`) dipilih ketimbang mengandalkan `updated_at` sebagai penanda versi — integer lebih murah dibandingkan dan tidak rentan masalah presisi timestamp/timezone.
5. Audit diperlakukan sebagai warga kelas satu, bukan tempelan belakangan — `audit_log` dirancang append-only sejak awal dengan kolom `nilai_lama`/`nilai_baru` berformat JSONB agar fleksibel menampung struktur berbeda-beda per jenis entitas tanpa memerlukan tabel audit terpisah per entitas; cakupannya diperluas eksplisit untuk mencakup seluruh entitas baru pada alur rencana aksi, kegiatan, komponen, dan model akses RBAC (kolom `dasar_izin` khusus untuk aksi sensitif, §3.5).
6. Skema dibangun penuh sejak awal, fiturnya bertahap — filosofi ini menghindari migrasi besar/berisiko di kemudian hari dengan menyediakan kolom Fase Lanjutan sejak MVP (§6), sembari menjaga logika aplikasi Fase Awal tetap sederhana dan tidak memproses cabang kode yang belum relevan.
7. Pertahanan berlapis untuk aturan lintas-baris yang kompleks (rentang tahun Renstra beririsan, syarat aktivasi jadwal, idempotensi snapshot, keunikan klaim/grant/deny dengan kolom nullable): validasi utama selalu berada di service layer sebagai bagian dari satu transaksi atomik; constraint database (partial unique index, exclusion constraint, index unik ber-`COALESCE`) disertakan sebagai lapisan pertahanan kedua yang menutup celah race condition antar-request, bukan sebagai pengganti validasi aplikasi.
8. Penamaan entitas disesuaikan agar tidak bertabrakan dengan kosakata evaluasi eksternal — `unit` dipilih dan bukan "unit kerja" — sekaligus tetap mencerminkan makna aslinya sebagai kelompok organisasi pemilik indikator dan scope permission, bukan satuan ukur.
9. Perhitungan berbasis komponen bersifat data-driven dan satu tingkat — definisi cara hitung (`indikator.tipe_perhitungan`, `indikator_komponen`) hidup di data, dapat diubah lewat aplikasi tanpa deployment kode baru, tetapi sengaja dibatasi pada satu tingkat rasio/penjumlahan agar tetap dapat diverifikasi dan dibekukan penuh ke snapshot; formula bertingkat yang lebih kompleks diserahkan ke tipe `manual` alih-alih dipaksakan ke dalam mesin perhitungan yang belum tentu dapat memodelkannya dengan benar.
10. Klaim (dokumentasi keterkaitan) dan pengukuran (nilai capaian) dipisahkan tegas sebagai dua tabel berbeda dengan tanggung jawab berbeda — `klaim_kegiatan` murni mendokumentasikan dukungan dan arah dampak, sama sekali tidak menulis ke `pengukuran_komponen`/`rencana_aksi_target`, untuk mencegah penghitungan ganda yang sulit dideteksi bila kedua tanggung jawab itu digabung dalam satu mekanisme otomatis.
11. Model hak akses dipisah tegas menjadi **data yang dapat dipelihara** (peran dan isinya, `roles`/`role_permissions`) dan **pengecualian eksplisit per pengguna** (`user_permission_granted`/`user_permission_denials`), dievaluasi hidup saat request lewat satu service resolusi terpusat (§3) — bukan disalin ke baris statis per pengguna. Pemisahan ini membuat perubahan katalog/isi peran otomatis berlaku bagi seluruh pemegangnya, sekaligus memungkinkan sistem membedakan "tidak diberi" dari "sengaja dicabut", pembedaan yang dibutuhkan saat evaluasi AKIP/ZI mempertanyakan mengapa seseorang tidak dapat melakukan sesuatu meski perannya memungkinkan.
12. Pemisahan tugas (§4) sengaja ditempatkan sebagai validasi bisnis tetap di service layer, terpisah dari mekanisme deny yang dapat dikonfigurasi (§2.8) — supaya aturan "pengaju tidak boleh menyetujui pekerjaannya sendiri" tidak dapat dinonaktifkan lewat pemberian grant apa pun, termasuk oleh Superadmin.

---
