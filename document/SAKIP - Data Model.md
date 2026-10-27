# DATA MODEL — SAKIP LLDIKTI Wilayah XVI

> **Klarifikasi final LLDIKTI Wilayah XVI — 24 September 2026**  
> Bagian ini adalah kontrak terbaru dan **menggantikan keputusan Q31 atau teks lama yang bertentangan**. Role bawaan SAKIP berjumlah **lima**: `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`. **PIC bukan role sistem**; PIC adalah konteks operasional yang dibentuk oleh penugasan dan grant unit. Detail keputusan dicatat sebagai **Q32** pada dokumen Keputusan Penyelarasan.


**Status dokumen:** Versi konsolidasi draf — menunggu pembahasan bersama Tim Perencanaan. 34 entitas (§2.1–§2.34) ditambah tiga entitas rancangan Q39/Q40 (§2.35–§2.37), skema lengkap dirancang sejak Fase Awal (MVP), mencakup alur penuh dasar aturan (regulasi) → Renstra → Perjanjian Kinerja → jadwal & periode → rencana aksi → kegiatan → pengukuran berbasis komponen → rekomendasi Pimpinan → status capaian, serta model hak akses **RBAC dengan pengecualian eksplisit** (peran, grant, deny) yang dievaluasi saat request.


> **Q32 aktif:** role final berjumlah lima; `pic` bukan baris pada `roles`. Assignment PJ dan Grant Unit tetap entitas terpisah. Detail final berada pada §8.

**Basis data target:** PostgreSQL — dipilih secara sadar karena beberapa kapabilitas yang dipakai langsung oleh skema ini: tipe kolom `jsonb` untuk `audit_log` (menampung struktur nilai lama/baru yang berbeda-beda per entitas tanpa memerlukan tabel audit terpisah per entitas), **exclusion constraint** (`EXCLUDE USING gist` dengan ekstensi `btree_gist`) sebagai lapisan pertahanan kedua untuk menegakkan rentang tahun Renstra yang tidak boleh beririsan, **unique constraint** untuk menjamin satu `jadwal_tahunan` per kombinasi Renstra-tahun lintas seluruh status (§2.15), dan penanganan **NULL pada index unik** lewat `COALESCE` untuk constraint yang melibatkan kolom nullable — dipakai pada `klaim_kegiatan.komponen_id` maupun pada `user_permission_granted.unit_id`/`user_permission_denials.unit_id`, karena PostgreSQL memperlakukan `NULL` sebagai nilai berbeda antarbaris pada unique index standar.

**Catatan cakupan:** Seluruh tabel dan kolom pada dokumen ini dibuat pada migrasi Laravel sejak Fase Awal, termasuk keenam tabel model hak akses (`permissions`, `roles`, `role_permissions`, `user_roles`, `user_permission_granted`, `user_permission_denials`). Yang ditunda ke Fase Lanjutan hanya jalur pemakaian/UI atas kolom-kolom tertentu; kolom itu sendiri tetap ada di skema agar tidak perlu migrasi besar/berisiko di kemudian hari, dan diberi anotasi eksplisit `-- (kolom tersedia, jalur pengisian/pemakaian menyusul fase lanjutan)`.

**Pengecualian rancangan Q39–Q51 (13 Oktober 2026):** entitas, kolom, dan nilai bertanda **Rancangan Qxx — belum diimplementasikan** belum ada pada migrasi dan memerlukan migrasi tambahan: `renstra_pk_versi` (§2.36), `renstra_pk_versi_target` (§2.37), `indikator.dinilai_tahunan` dan `jadwal_snapshot.dinilai_tahunan` (§2.12, §2.17), kolom narasi `pengukuran` dan nilai `pengukuran.status_perhitungan = belum_dapat_diukur` (§2.20), induk `berkas` `jadwal_tahunan`, `jadwal_periode`, dan `renstra_pk_versi` (§2.30). Perubahan aturan pada kolom yang sudah ada juga belum diterapkan: `jenis_berkas.ukuran_maks_kb` paling besar 102400 (Q44, §2.29). Dokumen ini mencatat kontrak datanya, bukan bukti implementasi. Bagian Q39/Q50/Q65 yang diterapkan ISS-02.11 (migrasi `2026_10_19_000002`–`2026_10_19_000004`): `renstra_pk.nomor_pk` nullable (§2.10), kolom `berkas.keterangan` untuk lampiran PK (§2.30), dan kolom `target_tahunan.alasan_deviasi_renstra` (§2.13). Bagian Q42 yang diterapkan ISS-06.08 (migrasi `2026_10_19_000001`): `kegiatan.anggaran` wajib untuk data baru beserta CHECK `>= 0` (§2.25), nilai `jenis_berkas.tahap = kegiatan_perencanaan`, kolom `jenis_berkas.hanya_kegiatan_beranggaran` (§2.29), dan induk `berkas` `jenis_berkas` (§2.30); penjumlahan anggaran (§2.26, termasuk key snapshot `anggaran_kegiatan` §2.34) dan pembatasan anggaran per kegiatan menurut akses detail (Q42 §42.5) pada halaman rencana aksi dan review versi diterapkan ISS-06.08 tanpa migrasi, sedangkan tampilan total per IKU pada dashboard mengikuti ISS-10.01.

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
    USERS ||--o{ RENSTRA_PK_VERSI : "menyetujui revisi (rancangan Q40)"
    USERS ||--o{ REKOMENDASI_PIMPINAN : "menyetujui"

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

    INDIKATOR ||--o{ TARGET_TAHUNAN : "memiliki target PK per tahun (Q39)"
    INDIKATOR ||--o{ TARGET_RENSTRA : "memiliki target Renstra per tahun (Q39)"
    INDIKATOR ||--o{ RENSTRA_PK_VERSI_TARGET : "diberi target per versi PK (rancangan Q40)"
    INDIKATOR ||--o{ PENANGGUNG_JAWAB : "memiliki riwayat PJ"
    INDIKATOR ||--o{ JADWAL_SNAPSHOT : "dibekukan ke"
    INDIKATOR ||--o{ PENGUKURAN : "diukur pada"
    INDIKATOR ||--o{ RENCANA_AKSI : "memiliki rencana aksi per tahun"
    INDIKATOR ||--o{ INDIKATOR_KOMPONEN : "memiliki komponen"
    INDIKATOR ||--o{ JENIS_BERKAS : "membatasi jenis berkas (nullable)"
    INDIKATOR ||--o{ REKOMENDASI_PIMPINAN : "menerima rekomendasi"

    RENSTRA_PK ||--o{ JADWAL_TAHUNAN : "direferensikan saat aktivasi"
    RENSTRA_PK ||--o{ BERKAS : "berkasable (polimorfik)"
    RENSTRA_PK ||--o{ RENSTRA_PK_VERSI : "memiliki versi PK (rancangan Q40)"
    RENSTRA_PK_VERSI ||--o{ RENSTRA_PK_VERSI_TARGET : "menyimpan target per indikator (rancangan Q40)"
    RENSTRA_PK_VERSI ||--o{ BERKAS : "berkasable (polimorfik, rancangan Q40)"
    REGULASI ||--o{ RENSTRA : "menjadi dasar hukum (nullable)"
    REGULASI ||--o{ INDIKATOR : "menjadi dasar hukum (nullable)"
    REGULASI ||--o{ BERKAS : "berkasable (polimorfik)"
    RENSTRA ||--o{ BERKAS : "berkasable (polimorfik)"

    PERIODE ||--o{ PENGUKURAN : "menjadi satuan waktu"
    PERIODE ||--o{ JADWAL_PERIODE : "direferensikan"
    PERIODE ||--o{ RENCANA_AKSI_TARGET : "menjadi satuan waktu target"
    PERIODE ||--o{ KEGIATAN : "menjadi periode rencana"
    PERIODE ||--o{ REKOMENDASI_PIMPINAN : "menjadi periode evaluasi"
    PERIODE ||--o{ RENSTRA_PK_VERSI : "menjadi periode mulai berlaku (rancangan Q40)"

    JADWAL_TAHUNAN ||--o{ JADWAL_PERIODE : "menentukan periode diharapkan"
    JADWAL_TAHUNAN ||--o{ JADWAL_SNAPSHOT : "menghasilkan (saat aktivasi)"
    JADWAL_TAHUNAN ||--o{ RENCANA_AKSI : "menaungi"
    JADWAL_TAHUNAN ||--o{ BERKAS : "berkasable (polimorfik, arsip Rencana Aksi tahunan, rancangan Q48)"
    JADWAL_PERIODE ||--o{ BERKAS : "berkasable (polimorfik, arsip Laporan Kinerja Triwulan, rancangan Q48)"

    JADWAL_SNAPSHOT ||--o{ PENGUKURAN : "menjadi konteks beku"
    JADWAL_SNAPSHOT ||--o{ JADWAL_SNAPSHOT_KOMPONEN : "membekukan komponen"

    RENCANA_AKSI ||--o{ RENCANA_AKSI_TARGET : "memiliki target per periode per komponen"
    RENCANA_AKSI ||--o{ KLAIM_KEGIATAN : "menerima klaim"
    RENCANA_AKSI ||--o{ BERKAS : "berkasable (polimorfik)"

    INDIKATOR_KOMPONEN ||--o{ RENCANA_AKSI_TARGET : "menjadi target"
    INDIKATOR_KOMPONEN ||--o{ PENGUKURAN_KOMPONEN : "diukur pada"
    INDIKATOR_KOMPONEN ||--o{ KLAIM_KEGIATAN : "menjadi sasaran dampak (nullable)"

    KEGIATAN ||--o{ KLAIM_KEGIATAN : "diklaim"
    KEGIATAN ||--o{ BERKAS : "berkasable (polimorfik, bukti SPJ, RAB Q42 & lampiran bebas)"
    KEGIATAN ||--o{ KEGIATAN : "digeser dari (kegiatan_asal_id)"

    PENGUKURAN ||--o{ STATUS_CAPAIAN : "memiliki status akhir"
    PENGUKURAN ||--o{ PENGUKURAN_KOMPONEN : "memiliki nilai komponen"
    PENGUKURAN ||--o{ KLAIM_KEGIATAN : "sumber klaim (nullable)"
    PENGUKURAN ||--o{ BERKAS : "berkasable (polimorfik)"

    JENIS_BERKAS ||--o{ BERKAS : "menentukan jenis, tahap & mode yang diizinkan (nullable)"
    JENIS_BERKAS ||--o{ BERKAS : "berkasable (polimorfik, contoh berkas, Q42)"

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
        string nomor_pk "nullable sejak Q50"
        date tanggal_pk
        uuid created_by FK
        timestamp created_at
        timestamp updated_at
    }

    RENSTRA_PK_VERSI {
        uuid id PK
        uuid renstra_pk_id FK "rancangan Q40"
        int nomor_versi
        varchar nomor_surat "nullable pada versi 1"
        date tanggal_surat "nullable pada versi 1"
        uuid periode_mulai_id FK "nullable pada versi 1"
        text alasan "nullable pada versi 1"
        uuid disetujui_oleh FK "nullable pada versi 1"
        timestamp disetujui_pada "nullable pada versi 1"
        timestamp created_at
    }

    RENSTRA_PK_VERSI_TARGET {
        uuid id PK
        uuid renstra_pk_versi_id FK "rancangan Q40"
        uuid indikator_id FK
        numeric nilai "nullable, finite, nonnegatif"
        text alasan_deviasi_renstra "nullable"
    }

    SASARAN {
        uuid id PK
        uuid renstra_id FK
        varchar kode "mengikuti PK sejak Q50"
        string nama
        text keterangan
        int urutan
    }

    INDIKATOR {
        uuid id PK
        uuid sasaran_id FK
        uuid unit_id FK
        uuid regulasi_id FK "nullable"
        varchar kode "mengikuti PK sejak Q50"
        string nama
        text definisi
        string satuan
        smallint presisi
        smallint desimal_tampilan
        enum arah
        enum tipe_perhitungan
        int tahun_mulai_berlaku
        enum status
        boolean wajib_catatan "disembunyikan dan tidak dipakai sejak Q45"
        boolean dinilai_tahunan "Q47"
        uuid created_by FK
        string created_by_role
        timestamp created_at
        timestamp updated_at
    }

    TARGET_TAHUNAN {
        uuid id PK
        uuid indikator_id FK
        int tahun
        numeric nilai "nullable, finite, nonnegatif"
        numeric baseline "nullable, finite, nonnegatif"
        text alasan_deviasi_renstra "nullable, Q65"
        uuid updated_by FK
        timestamp updated_at
    }

    TARGET_RENSTRA {
        uuid id PK
        uuid indikator_id FK
        int tahun
        numeric nilai "nullable, finite, nonnegatif"
        uuid updated_by FK
        timestamp updated_at
    }

    PERIODE {
        uuid id PK
        string nama
        int urutan
        boolean aktif
        boolean is_nilai_akhir
        smallint bulan_mulai
        smallint hari_mulai
        smallint bulan_selesai
        smallint hari_selesai
    }

    JADWAL_TAHUNAN {
        uuid id PK
        uuid renstra_id FK
        int tahun
        boolean pakai_persetujuan_pimpinan
        date persetujuan_mulai "nullable"
        date persetujuan_selesai "nullable"
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
    }

    JADWAL_SNAPSHOT {
        uuid id PK
        uuid jadwal_id FK
        uuid indikator_id FK
        int nomor_versi
        uuid menggantikan_id FK "nullable"
        text alasan_koreksi "nullable"
        text rujukan_koreksi "nullable"
        uuid periode_mulai_id FK
        uuid unit_id
        string nama
        text definisi
        string satuan
        smallint presisi
        smallint desimal_tampilan
        enum arah
        enum tipe_perhitungan
        boolean dinilai_tahunan "Q47"
        numeric target "nullable"
        numeric baseline "nullable"
        boolean komposisi_final
    }

    JADWAL_SNAPSHOT_KOMPONEN {
        uuid id PK
        uuid jadwal_snapshot_id FK
        uuid komponen_id FK
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
        uuid snapshot_draf_id "nullable"
        uuid penanggung_jawab_id FK
        text uraian "nullable"
        enum status_alur
        int versi
        text alasan_revisi "nullable"
        text alasan_deviasi_pk "nullable"
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
        uuid komponen_id FK "nullable, NULL untuk indikator manual"
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
        string nama "hingga 500 karakter sejak Q49"
        text tujuan "nullable sejak Q49"
        int sasaran_peserta "nullable"
        varchar keterangan_peserta "nullable"
        varchar lokasi "nullable"
        date tanggal_rencana "nullable"
        date tanggal_realisasi "nullable"
        numeric anggaran "rupiah, nonnegatif, wajib sejak Q42; null hanya data lama, data awal 2026, periode lampau 2026"
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
        enum status_perhitungan "nilai belum_dapat_diukur rancangan Q47"
        text alasan_tidak_dapat_dihitung "nullable"
        text catatan "nullable, opsional sejak Q45"
        text progres_kegiatan "rancangan Q45, wajib saat diajukan"
        text kendala_masalah "rancangan Q45, wajib saat diajukan"
        text strategi_tindak_lanjut "rancangan Q45, wajib saat diajukan"
        text tindak_lanjut_rekomendasi "rancangan Q45, nullable"
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
        enum tahap "termasuk kegiatan_perencanaan (Q42)"
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
        varchar berkasable_type "enum 7 nilai + 3 rancangan Q40/Q48"
        uuid berkasable_id
        uuid menggantikan_id FK "nullable"
        text alasan_koreksi "nullable"
        enum mode
        varchar nama_asli "nullable, wajib utk mode file"
        varchar path "nullable, wajib utk mode file"
        varchar mime "nullable, wajib utk mode file"
        bigint ukuran_bytes "nullable, wajib utk mode file"
        varchar tautan "nullable, wajib utk mode tautan"
        text isi_teks "nullable, wajib utk mode teks"
        text keterangan "nullable, Q65"
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
        enum status "Q51"
        uuid disetujui_oleh FK "nullable, Q51"
        varchar disetujui_oleh_role "nullable, Q51"
        timestamp disetujui_pada "nullable, Q51"
        text rujukan_sumber "nullable, Q51"
        int versi "Q55"
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

Representasi lokal identitas SSO. Password tidak disimpan di SAKIP.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `keycloak_id` | varchar | unique, not null | Subject/ID unik dari Keycloak |
| `nama` | varchar | not null | Disinkronkan dari claim yang diizinkan |
| `email` | varchar | not null | Disinkronkan dari claim yang diizinkan |
| `nomor_telepon` | varchar | nullable | Profil lokal bila diperlukan |
| `status` | enum(`aktif`,`nonaktif`) | not null, default `nonaktif` | Status akses aplikasi |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Onboarding final (Q38):** login pertama Keycloak yang valid membuat baris `users` dengan `status = aktif` dan **tanpa baris `user_roles`**. User diarahkan ke halaman “Menunggu penetapan peran”. Admin menetapkan role melalui Penetapan Peran. User tanpa role memiliki 0 permission. `status = nonaktif` berarti akun dinonaktifkan; default kolom tetap `nonaktif` untuk jalur insert lain.

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

Katalog permission source-controlled yang disinkronkan melalui seeder.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `kode` | varchar | unique, not null | format `entitas:aksi` |
| `entitas` | varchar | not null | |
| `aksi` | varchar | not null | |
| `keterangan` | text | nullable | |
| `butuh_scope` | enum(`global`,`unit`) | not null, default `global` | |
| `sensitif` | boolean | not null, default false | |
| `aktif` | boolean | not null, default true | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

Hanya 7 permission yang `butuh_scope = unit`:

```text
pengukuran:create
pengukuran:update
rencana_aksi:create
rencana_aksi:update
rencana_aksi:ajukan
kegiatan:create
kegiatan:update
```

`rencana_aksi:read` dan `kegiatan:read` adalah global; detailnya per unit dibatasi unit berizin, yaitu seluruh unit bagi allow dari peran Superadmin, Perencanaan, atau Pimpinan dan unit dengan grant unit aktif bagi pengguna lain, tanpa grant baca per unit (Q54 §54.1). Tambahkan permission global `delegasi:update` sebagai gate pengelolaan Grant Unit. Permission tidak dikenal/tidak aktif harus fail closed.

**Perubahan katalog dan preset Q45, Q48, dan Q51 (13 Oktober 2026; status implementasi: preset Pimpinan `rekomendasi:tetapkan` sudah melalui ISS-09.01, `laporan:unggah` belum):** permission global baru **`laporan:unggah`** (preset Perencanaan dan Superadmin) untuk mengunggah arsip PDF bertanda tangan pada induk `berkas` `jadwal_tahunan`/`jadwal_periode` (§2.30); unduhan arsip mengikuti `laporan:ekspor`. `laporan:unggah` **tidak** bertanda sensitif (`sensitif = false`) karena unggahan bersifat append-only dan tidak mengubah data; unggahan tetap tercatat di `audit_log` (Q48 §48.2 butir 2 dan 4). Preset Pimpinan memperoleh `rekomendasi:tetapkan` di samping Perencanaan, tanpa permission baru untuk persetujuan rekomendasi (Q45 §45.2, Q51 §51.2; §2.31).

### 2.4 `roles`

Katalog role bawaan Fase Awal.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `kode` | varchar | unique, not null | salah satu 5 kode final |
| `nama` | varchar | not null | |
| `keterangan` | text | nullable | |
| `is_sistem` | boolean | not null, default true | |
| `urutan` | int | not null | |
| `aktif` | boolean | not null, default true | |

Kode role final:

```text
superadmin
admin
perencanaan
pimpinan
pegawai
```

Tidak ada row `pic`. Istilah PIC adalah konteks operasional, bukan role database.

### 2.5 `role_permissions`

Preset permission global tiap role.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `role_id` | uuid | FK → roles.id | |
| `permission_id` | uuid | FK → permissions.id | |
| `created_at` | timestamp | not null | |

**Constraint:** `unique(role_id, permission_id)`.

Tabel ini **tidak memiliki `unit_id`**. Isi role tidak diedit lewat UI. Preset didefinisikan dalam kode dan disinkronkan melalui seeder/release. Bila ada delta, audit mencatat before/after dan sumber/alasan perubahan. Seeder rerun tanpa delta harus idempoten dan tidak membuat audit palsu.

Permission unit-scoped tidak boleh dimasukkan sebagai preset global untuk menggantikan Grant Unit.

### 2.6 `user_roles`

Penetapan role utama user.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `user_id` | uuid | FK → users.id, unique | maksimal satu role per user pada Fase Awal |
| `role_id` | uuid | FK → roles.id | |
| `diberikan_oleh` | uuid | FK → users.id, nullable | null hanya untuk bootstrap terkontrol bila diperlukan |
| `sumber_pemberian` | varchar | not null | provenance assignment |
| `audit_id` | uuid | FK → audit_log.id, nullable | |
| `created_at` | timestamp | not null | |

User boleh **belum memiliki baris** `user_roles`, terutama setelah JIT onboarding SSO. Kondisi tersebut menghasilkan nol permission sampai Admin menetapkan role. Tidak ada migrasi Pegawai→PIC karena role PIC tidak ada.

### 2.7 `user_permission_granted`

Grant eksplisit kepada user. Untuk hak kerja operasional, grant dibatasi pada permission unit-scoped final.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `user_id` | uuid | FK → users.id | target harus aktif |
| `permission_id` | uuid | FK → permissions.id | |
| `unit_id` | uuid | FK → unit.id, nullable | wajib untuk permission unit-scoped |
| `alasan` | text | not null, CHECK tidak kosong (`length(trim(alasan)) > 0`) | Sejak Q43 §43.3 alasan pemberian grant opsional bagi pengguna; bila dikosongkan, server mengisi **alasan baku** (pola Q37 §37.2) sehingga kolom tetap terisi. Pencabutan grant tetap wajib alasan pengguna yang dicatat di `audit_log` (Q43 §43.2) |
| `diberikan_oleh` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |

**Constraint:** unique efektif pada (`user_id`, `permission_id`, `unit_id`) dengan penanganan NULL PostgreSQL.

Hanya 7 permission berikut yang sah sebagai Grant Unit:

```text
pengukuran:create
pengukuran:update
rencana_aksi:create
rencana_aksi:update
rencana_aksi:ajukan
kegiatan:create
kegiatan:update
```

Form pemberian (satu atau beberapa izin sekaligus), kelola, dan pencabutan Grant Unit digerbangi `delegasi:update` (Perencanaan, Admin, Superadmin sesuai preset). Sejak Q62, Izin Unit ditampilkan per pasangan pengguna × unit dan dapat dikelola sekaligus (izin baru diberikan, izin yang dilepas dicabut) tanpa mengubah baris grant di tempat; audit tetap per izin. Grant tidak mengubah role dan tidak membuat/menghapus assignment `penanggung_jawab`.

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

**Revisi Renstra (Q40 §40.2):** atribut Renstra tetap direvisi **in place** dengan audit. Lampiran revisi **boleh ditambahkan** setelah Renstra `aktif`, sedangkan lampiran yang sudah ada tetap tidak dapat dihapus (§2.30). Lampiran baru hanya diterima pada Renstra berstatus `draft` atau `aktif`; Renstra `nonaktif` atau `diarsipkan` tidak menerima lampiran baru (Q40 §40.2 butir 5). Perubahan target Renstra (§2.35) saat Renstra `aktif` tercatat di audit dengan nilai lama dan baru sebagai riwayat revisi target Renstra (Q60). Versi penuh Renstra (sasaran dan indikator ikut berversi) tidak dibangun saat ini (backlog tahap 3, Q46 §46.3); penggantian Renstra karena kebijakan kementerian ditangani dengan Renstra baru, dan Renstra lama dinonaktifkan sesuai aturan di atas. Status implementasi: sudah (ISS-02.10).

---

### 2.10 `renstra_pk`

Perjanjian Kinerja tahunan per Renstra, masuk penuh sejak Fase Awal.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `renstra_id` | uuid | FK → renstra.id | |
| `tahun` | int | not null | |
| `nomor_pk` | string | **nullable** sejak Q50 (semula not null) | Nomor dokumen resmi PK; **opsional** karena PK 2026 tidak bernomor (Q50 §50.1). PK tetap dikenali dari (`renstra_id`, `tahun`) dan `tanggal_pk`. Status implementasi: sudah (ISS-02.11, migrasi `2026_10_19_000002`); PK tanpa nomor ditampilkan sebagai "PK {tahun}" |
| `tanggal_pk` | date | not null | Tidak divalidasi terhadap tanggal penetapan Renstra; PK boleh bertanggal lebih awal (Q50 §50.6) |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Constraint:** `unique(renstra_id, tahun)` — satu PK per tahun per Renstra.
**Aturan operasional:** koreksi atas `nomor_pk`/`tanggal_pk` setelah PK dipakai jadwal aktif (mis. salah ketik; jalur koreksi teraudit ISS-02.09) wajib menyertakan alasan dan tercatat di `audit_log` (nilai lama vs baru); perubahan PK sebelum PK dipakai jadwal aktif tetap teraudit, tetapi alasannya opsional (Q43 §43.3). Revisi Renstra akibat terbitnya Kepmen IKU baru diperlakukan sebagai **edit in place** pada baris `renstra` yang sama (bukan Renstra baru), dengan `audit_log.alasan` memuat nomor & tanggal Kepmen (lihat juga §2.9). Ketentuan lama bahwa nomor/tanggal surat persetujuan Eselon 1 kementerian pusat dicantumkan pada kolom `alasan` **digantikan Q40**: nomor dan tanggal surat persetujuan Eselon I menjadi isian terstruktur pada versi PK (`renstra_pk_versi.nomor_surat`/`tanggal_surat`, §2.36, rancangan).

**Isian target PK (Q39):** baseline dan target tahun PK untuk setiap indikator aktif Renstra, dikelompokkan per sasaran, diisi Tim Perencanaan di halaman PK dan disimpan pada `target_tahunan` (§2.13); `renstra_pk` sendiri tidak menyimpan kolom target. Target Renstra tahun yang sama (§2.35) ditampilkan sebagai pembanding menurut arah indikator (Q65 §65.2). Target PK disimpan bernilai penuh (Q50 §50.2), dan baseline tampil sebagai data pendukung, bukan isi dokumen PK (Q50 §50.3). Target PK tetap dapat diubah setelah snapshot terbentuk, dengan alasan opsional dan tanpa mengubah snapshot (Q65 §65.1). Status implementasi: sudah (ISS-02.11).

**Revisi PK berversi (Q40, rancangan tahap 3):** revisi PK disimpan sebagai versi pada `renstra_pk_versi` (§2.36) beserta target per indikator pada `renstra_pk_versi_target` (§2.37); versi 1 = PK awal. `renstra_pk` tetap satu baris per (`renstra_id`, `tahun`) sebagai induk versi. Prasyarat: alur koreksi snapshot berversi (ISS-03.03). Status implementasi: belum (ISS-02.12).

**Lampiran dokumen PK:** `renstra_pk` menjadi salah satu induk polimorfik `berkas`
(`berkasable_type = renstra_pk`, lihat §2.30) — dokumen Perjanjian Kinerja resmi dapat
dilampirkan dalam mode file/tautan/teks. Aktivasi `jadwal_tahunan` mensyaratkan **minimal
satu** lampiran pada `renstra_pk` tahun tersebut sebagai salah satu gerbang aktivasi (lihat
§2.15, gerbang keempat). Lampiran ini tidak dapat dihapus setelah `jadwal_tahunan` tahun
tersebut berstatus `aktif` (lihat §2.30). Lampiran dokumen PK per versi memakai induk
`renstra_pk_versi` (§2.30, §2.36, rancangan Q40).

**PK perubahan karena anggaran (Q50 §50.7):** sebelum revisi PK berversi tersedia (ISS-02.12),
PK perubahan yang hanya mengubah anggaran diunggah sebagai **lampiran tambahan** pada
`renstra_pk` tahun tersebut beserta keterangan; target dan snapshot tidak berubah. PK perubahan
yang mengubah target memakai revisi berversi; bila terbit sebelum ISS-02.12 selesai,
penanganannya ditetapkan PM sebagai keputusan baru.

---

### 2.11 `sasaran`

Tujuan strategis di bawah Renstra.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `renstra_id` | uuid | FK → renstra.id | |
| `kode` | varchar(50) | not null | Kode sasaran yang tampil di UI dan laporan; sejak Q50 §50.4 mengikuti PK, Rencana Aksi, dan laporan triwulan (**S1–S4**). Kolom sudah ada di aplikasi (`sasaran_strategis.kode`) |
| `nama` | string | not null | |
| `keterangan` | text | nullable | |
| `urutan` | int | not null | Menentukan urutan tampil di UI/laporan |

**Sasaran S4 (Q50 §50.5):** sasaran yang hanya ada di PK (S4, dengan indikator IKU 4.1) dicatat di bawah Renstra 2025–2029 tanpa target Renstra (§2.35).

---

### 2.12 `indikator`

Unit ukur kinerja konkret.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `sasaran_id` | uuid | FK → sasaran.id | |
| `unit_id` | uuid | FK → unit.id, **NOT NULL** | Setiap indikator wajib dimiliki tepat satu unit |
| `regulasi_id` | uuid | FK → regulasi.id, **nullable** | Rujukan dasar aturan per indikator kinerja (mis. produk hukum yang menetapkan IKU tersebut), agar dasar hukum dapat ditelusuri per indikator tanpa mengunggah dokumen yang sama berulang (lihat §2.33) |
| `kode` | varchar(50) | not null | Kode indikator; sejak Q50 §50.4 mengikuti PK, Rencana Aksi, dan laporan triwulan (**IKU 1.1–IKU 4.1**). Nomor IKU pada Renstra (IKU 1–8) dicatat sebagai keterangan pada `definisi` (definisi operasional), tanpa kolom baru. Kolom sudah ada di aplikasi (`indikator_kinerjas.kode`) |
| `nama` | string | not null | |
| `definisi` | text | nullable | |
| `satuan` | varchar | not null | mis. "%", "orang", "dokumen" |
| `presisi` | smallint | not null, default 2 | Jumlah digit desimal disimpan |
| `desimal_tampilan` | smallint | not null, default 2 | Jumlah digit desimal ditampilkan (dapat berbeda dari presisi penyimpanan) |
| `arah` | enum(`naik_baik`,`turun_baik`) | not null, default `naik_baik` | Menentukan arah nilai yang dianggap membaik; dipakai menilai deviasi target PK terhadap target Renstra (Q39, §2.13), membaca tanda selisih terhadap target PK (Q47 §47.3, §2.21), dan disalin ke `jadwal_snapshot.arah` sebagai bagian konteks beku (lihat §2.17). Pemakaiannya untuk menentukan wajib-tidaknya catatan saat pengajuan pengukuran digantikan narasi wajib Q45 (§2.20) |
| `tipe_perhitungan` | enum(`rasio_persen`,`penjumlahan`,`manual`) | not null, default `manual` | Menentukan apakah `pengukuran.nilai` diketik manual atau diturunkan dari `pengukuran_komponen` (lihat §2.20, §2.27, §2.28). Disalin ke `jadwal_snapshot.tipe_perhitungan` sebagai konteks beku |
| `tahun_mulai_berlaku` | int | not null | |
| `status` | enum(`aktif`,`arsip`) | not null, default `aktif` | |
| `wajib_catatan` | boolean | not null, default `false` | **Dipertahankan tanpa dipakai sejak Q45:** disembunyikan dari form indikator tanpa menghapus kolomnya dan tidak lagi mewajibkan `catatan`, karena kewajiban catatan digantikan narasi wajib pengukuran (§2.20, §6). Semula: jika true, catatan wajib diisi setiap pengajuan pengukuran indikator ini |
| `dinilai_tahunan` | boolean | not null, default `false` | Label UI "Dinilai tahunan": nilai indikator baru tersedia pada periode nilai akhir (`periode.is_nilai_akhir`), sehingga target rencana aksi pada periode sebelumnya boleh kosong (§2.24). Diubah Perencanaan di Sasaran & Indikator (`indikator:update`) dan tercatat di `audit_log`; disalin ke `jadwal_snapshot.dinilai_tahunan` (§2.17), dan perubahan setelah jadwal aktif berlaku pada jadwal berikutnya; koreksi tahun berjalan lewat koreksi snapshot (Q35). Tidak mengubah `tipe_perhitungan`, formula, maupun komponen (Q47 §47.1). Data awal 2026: hanya IKU 1.3 yang ditandai |
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

Target PK per indikator per tahun.

> **Q39 (13 Oktober 2026):** `target_tahunan` (tabel aplikasi `target_kinerjas`) menyimpan **target PK**. Editornya dipindah ke halaman Perjanjian Kinerja (§2.10) dan diisi Tim Perencanaan setelah angka disepakati dengan tim kerja di luar aplikasi; PJ dan PIC melihatnya baca-saja. Target per tahun dalam rentang Renstra disimpan terpisah sebagai target Renstra (`target_renstra`, §2.35), yang hanya menjadi pembanding dan tidak dipakai menghitung capaian. Snapshot, rekonsiliasi Rencana Aksi (ADR-0008), dan pengukuran tetap membaca target PK melalui snapshot. Target PK dan target Renstra sama-sama disimpan dengan permission `target:update` yang sudah ada, tanpa permission baru (Q39 §39.3 butir 3). Status implementasi: sudah (ISS-02.11); editor di halaman indikator dihapus.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `indikator_id` | uuid | FK → indikator.id | |
| `tahun` | int | not null | |
| `nilai` | numeric | **nullable**, finite, nonnegatif (`>= 0`) | Target PK (Q39); `0` = nilai sah; `null` = belum diisi |
| `baseline` | numeric | **nullable**, finite, nonnegatif (`>= 0`) | Nilai baseline (capaian tahun sebelumnya) yang dipakai sebagai acuan saat menyusun target tahun ini; `0` = nilai sah; `null` = belum diisi; ditampilkan pada halaman PK sebagai data pendukung, bukan isi dokumen PK (Q50 §50.3; semula "ditampilkan pada dokumen PK"), serta pada rekapitulasi indikator × periode, dan disalin ke `jadwal_snapshot.baseline` saat aktivasi jadwal |
| `alasan_deviasi_renstra` | text | nullable | Alasan deviasi target PK terhadap target Renstra tahun yang sama (§2.35) menurut `indikator.arah`: `naik_baik` bila target PK < target Renstra, `turun_baik` bila target PK > target Renstra; nilai yang sama tidak menyimpang, dan perbandingan memakai nilai tersimpan tanpa pembulatan atau toleransi. Deviasi memunculkan peringatan dan tidak menolak nilai target PK, tetapi alasan ini wajib diisi bila deviasi terjadi; kewajiban itu ditegakkan server saat simpan, di dalam transaksi yang mengunci indikator (Q39 §39.2, Q43 §43.2, Q65 §65.2). Tidak dinilai bila target Renstra belum ada (keterangan "target Renstra belum ada") atau target PK belum diisi. Disimpan kosong bila tidak menyimpang atau tidak dinilai; tercatat pada `nilai_lama`/`nilai_baru` audit `target_tahunan.simpan` |
| `updated_by` | uuid | FK → users.id | |
| `updated_at` | timestamp | not null | |

**Constraint:** `unique(indikator_id, tahun)`.

**Domain nilai — Q33:** `baseline` dan `nilai` (target tahunan PK) boleh `null` secara independen. Jika diisi, keduanya harus berupa desimal biasa yang finite dan nonnegatif (`>= 0`). Nilai `0` merupakan nilai sah dan berbeda dari `null`. `NaN`, infinity, dan nilai negatif ditolak. Tidak ada batas universal maksimum `100`; nilai di atas `100` tetap dapat sah selama sesuai definisi/presisi indikator dan kapasitas storage. Ketentuan ini khusus untuk baseline dan target tahunan PK dan tidak otomatis berlaku pada realisasi/pengukuran, nilai komponen, atau target periode Rencana Aksi. Rujukan keputusan: **Q33 — Domain Nilai Baseline dan Target Tahunan PK** pada `SAKIP - Keputusan Penyelarasan.md`. Domain yang sama berlaku untuk target Renstra (`target_renstra.nilai`, §2.35; Q39 §39.1) dan untuk target versi PK (`renstra_pk_versi_target.nilai`, §2.37; Q40).

**Presisi target PK (Q50 §50.2):** `nilai` menyimpan **nilai penuh** yang dipakai pengukuran (mis. 94,05; 80,95; 92,86), bukan nilai tercetak pada dokumen PK yang dibulatkan (94; 81; 92,9). Status capaian dan selisih terhadap target PK memakai nilai tersimpan; tampilan mengikuti `indikator.desimal_tampilan`. Data awal 2026 memakai nilai penuh; konfirmasi nilai resmi tercatat pada tindak lanjut Keputusan Penyelarasan.

**Pemetaan schema aplikasi:** entitas ini dipetakan ke `target_kinerjas`, dengan `nilai` bernama `target_tahunan`. Kedua kolom `target_tahunan` dan `baseline` memakai `numeric(30,12)` nullable serta CHECK nonnegatif dan bukan `NaN`; infinity juga ditolak oleh kapasitas tipe numeric tersebut. Kolom `alasan_deviasi_renstra` ditambahkan pada tabel yang sama (migrasi `2026_10_19_000004`).

**Aturan revisi (diselaraskan Q39–Q40):** target PK tahun Y diubah lewat editor di halaman PK; baris `jadwal_snapshot` untuk tahun tersebut baru terbentuk saat jadwal tahun itu diaktifkan dan otomatis membawa nilai terkini (termasuk `baseline`). Tahun yang jadwalnya sudah beku (snapshot sudah terbentuk dan/atau sudah dirujuk pengukuran) tidak tersentuh oleh perubahan master ini: target PK tetap boleh diubah di halaman PK setelah snapshot terbentuk, tetapi snapshot tidak ikut berubah (Q65 §65.1); perubahan target PK resmi setelah snapshot terbentuk memakai revisi PK berversi (Q40, §2.36–§2.37, rancangan) yang menerbitkan versi `jadwal_snapshot` baru. Kenaikan target tahun-tahun mendatang setelah target terlampaui dilakukan pada target Renstra (§2.35, Q40 §40.2) — ketentuan lama yang melakukannya dengan mengubah baris master ini digantikan Q39/Q40. Aturan ini berlaku untuk **target tahunan PK**; target komponen per periode pada rencana aksi memiliki jalur revisinya sendiri (lihat §2.23–§2.24).

---

### 2.14 `periode`

Master satuan waktu pelaporan (global, tidak terikat Renstra/tahun tertentu). Tanggal mulai dan selesai periode disimpan tanpa tahun dan berlaku setiap tahun (Q70 §70.1).

**Seed awal:** pada tabel kosong, buat Triwulan I–IV aktif, urutan 1–4, bertanggal standar 1 Jan–31 Mar, 1 Apr–30 Jun, 1 Jul–30 Sep, dan 1 Okt–31 Des, hanya Triwulan IV bernilai akhir. Keempat baris dibuat dalam satu transaksi dengan lock konfigurasi master; tabel nonkosong dipertahankan tanpa penambahan/perubahan dan dilaporkan ke operator. Rujukan: [addendum seed ISS-03.01](SAKIP%20-%20Keputusan%20Penyelarasan.md).

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `nama` | string | not null | mis. "Triwulan I", "Semester II", "Tahunan" |
| `urutan` | int | not null | |
| `aktif` | boolean | not null, default `true` | |
| `is_nilai_akhir` | boolean | not null, default `false` | **Tepat satu** baris bernilai `true` di seluruh konfigurasi aktif — ditegakkan di level aplikasi (validasi sebelum simpan). Nilai periode ini **diisi manual**; tidak ada perhitungan agregasi otomatis dari periode-periode lain |
| `bulan_mulai` | smallint | nullable | Bulan tanggal mulai (1–12), tanpa tahun (Q70 §70.1 butir 1) |
| `hari_mulai` | smallint | nullable | Hari tanggal mulai; harus tersedia pada bulan tersebut setiap tahun, sehingga 29 Februari ditolak |
| `bulan_selesai` | smallint | nullable | Bulan tanggal selesai (1–12), tanpa tahun |
| `hari_selesai` | smallint | nullable | Hari tanggal selesai; aturan sama dengan `hari_mulai` |

**Constraint:** CHECK `periode_tanggal_valid`: keempat kolom tanggal terisi atau kosong bersamaan; bulan 1–12; hari 1 sampai jumlah hari bulan tersebut pada tahun non-kabisat; dan (`bulan_mulai`, `hari_mulai`) tidak setelah (`bulan_selesai`, `hari_selesai`), sehingga satu periode tidak melintasi pergantian tahun. Migrasi #165 mengisi tanggal standar seed pada baris bernama persis `Triwulan I`–`Triwulan IV`; periode lain dilengkapi lewat Master Periode.

**Aturan tanggal (level aplikasi, di bawah lock konfigurasi master eksklusif, Q70 §70.1 butir 2):**
- Periode aktif wajib bertanggal, termasuk periode pengganti saat pergantian periode nilai akhir; periode nonaktif boleh belum bertanggal.
- Rentang periode aktif tidak boleh tumpang tindih dengan rentang periode aktif lain; rentang yang bersebelahan diterima.
- Rentang pada tahun Y dibentuk dari tanggal tanpa tahun ditambah Y. Rentang ini dipakai untuk keterangan pada tampilan periode, jadwal, rencana aksi, kegiatan, dan pengukuran (dihitung dari tanggal master yang berlaku saat ditampilkan dan tidak dibekukan pada snapshot, Q70 §70.4 butir 3), untuk batas awal jendela pengisian (§2.16), dan untuk validasi tanggal realisasi kegiatan (Q74 §74.2 butir 3, menyusul ISS-06.03).
- Perubahan tanggal periode tidak mengubah jadwal, kegiatan, maupun pengukuran yang sudah tersimpan, dan tidak memvalidasi ulang draf jadwal; validasi memakai tanggal yang berlaku saat data disimpan atau diaktifkan (Q70 §70.1 butir 4).
- Perubahan tanggal dicatat di `audit_log` (`periode.ubah`) dengan nilai lama/baru dan `dasar_izin`.

**Catatan relasi:** baris pada tabel ini bersifat global lintas tahun/Renstra. Daftar periode yang *diharapkan* pada satu jadwal tahun tertentu — beserta jendela pengisiannya — ditentukan oleh baris `jadwal_periode` (lihat §2.16), bukan langsung oleh tabel ini. Periode yang sama juga menjadi satuan waktu bagi `rencana_aksi_target` (§2.24) dan `kegiatan` (§2.25).

---

### 2.15 `jadwal_tahunan`

Jendela penutupan tingkat tahun dan (opsional) persetujuan pimpinan, per Renstra per tahun; jendela penyusunan rencana aksi digantikan Q41 (lihat kolom `rencana_aksi_mulai`/`rencana_aksi_selesai`). Daftar periode yang diharapkan pada tahun tersebut beserta jendela pengisian masing-masing periode disimpan terpisah pada `jadwal_periode` (§2.16) — pemisahan ini memungkinkan satu jadwal tahunan memiliki susunan periode yang berbeda dari tahun lain (mis. Triwulan I–IV pada satu tahun, kombinasi lain pada tahun berikutnya) tanpa mengubah struktur tabel ini.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `renstra_id` | uuid | FK → renstra.id | |
| `tahun` | int | not null | |
| `pakai_persetujuan_pimpinan` | boolean | not null, default `false` | **-- (kolom tersedia, jalur pengisian/pemakaian menyusul fase lanjutan)**. Fase Awal: selalu `false`, tidak memengaruhi alur pengesahan |
| `persetujuan_mulai` | date | nullable | **-- (kolom tersedia, jalur pengisian/pemakaian menyusul fase lanjutan)** |
| `persetujuan_selesai` | date | nullable | **-- (kolom tersedia, jalur pengisian/pemakaian menyusul fase lanjutan)** |
| `rencana_aksi_mulai` | — | — | **Dihapus oleh migrasi #159:** tidak dipakai sejak Q41 dan dihapus dari form jadwal, lalu dari skema beserta CHECK `jadwal_tahunan_urutan_ra` (§6). Semula: awal jendela penyusunan `rencana_aksi` tingkat tahun, disusun setelah `jadwal_periode` tersusun dan sebelum jendela pengisian periode pertama dibuka (digantikan Q41) |
| `rencana_aksi_selesai` | — | — | **Dihapus oleh migrasi #159:** sejak Q41 tidak lagi menjadi batas PIC; PIC menulis rencana aksi sejak jadwal `aktif` sampai `penutupan` dengan kunci per periode (§2.16, §2.23). Kolom kemudian dihapus dari skema beserta CHECK `jadwal_tahunan_urutan_ra` (§6). Semula: batas mutlak bagi PIC (izin ber-scope unit lewat grant, §2.7) untuk menyusun/mengubah/mengajukan `rencana_aksi`, dengan Perencanaan dikecualikan (digantikan Q41) |
| `penutupan` | date | not null | Tanggal batas akhir seluruh aktivitas jadwal tahun ini — termasuk batas akhir bagi Perencanaan mengisi/mengubah pengukuran maupun rencana aksi, batas akhir verifikasi/pengembalian/pengesahan pengukuran (Q17, Q70 §70.2 butir 3), dan batas akhir bagi PIC menulis rencana aksi (Q41) (lihat §2.16, §2.23). Wajib **setelah** `pengisian_selesai` terakhir pada `jadwal_periode` jadwal ini (Q70 §70.2 butir 5; ditegakkan di level aplikasi saat draf dan revisi jendela disimpan) |
| `status` | enum(`draft`,`aktif`,`ditutup`) | not null, default `draft` | |
| `renstra_pk_id` | uuid | FK → renstra_pk.id | **Wajib diisi saat aktivasi** |
| `activated_at` | timestamp | nullable | Diisi otomatis saat transisi ke `aktif`. Pada aktivasi Jadwal 2026, kolom ini mencatat **waktu aktivasi sebenarnya**. Periode yang jendelanya telah berakhir dihitung sebagai periode lampau; sistem tidak memalsukan tanggal aktivasi dan tidak memakai flag backfill. |
| `closed_at` | timestamp | nullable | Diisi otomatis saat transisi ke `ditutup` |

**Syarat aktivasi (divalidasi di service layer, dalam satu transaksi, sebelum transisi status → `aktif`) — EMPAT gerbang:**
1. `renstra_pk` untuk kombinasi (`renstra_id`, `tahun`) sudah ada (lihat §2.10) — jika belum, aktivasi ditolak.
2. Seluruh indikator berstatus `aktif` yang berada di bawah Renstra ini sudah memiliki `target_tahunan` (target PK, Q39) untuk tahun Y (lihat §2.13) — indikator berstatus `arsip` dikecualikan dari syarat ini. Target Renstra (§2.35) bukan syarat aktivasi.
3. `jadwal_tahunan.tahun` berada di dalam rentang `[renstra.tahun_mulai, renstra.tahun_akhir]`.
4. **(Gerbang lampiran PK)** Baris `renstra_pk` yang dirujuk pada syarat 1 memiliki **minimal satu** lampiran `berkas` (`berkasable_type = renstra_pk`, lihat §2.30) — mode bebas (file/tautan/teks), sehingga persyaratan ini tidak dapat memacetkan alur. Sejak Q44 saklar unggahan (`berkas.unggahan_aktif`, §2.22) dihapus dan unggahan file selalu aktif, sehingga gerbang ini tidak lagi dapat ditandai **`tidak_dapat_dipenuhi`**; penanda yang sudah tercatat di `audit_log` tetap menjadi histori (lihat §2.30). Semula, bila unggahan file dinonaktifkan sementara belum ada lampiran mode `tautan`/`teks`, gerbang ini ditandai `tidak_dapat_dipenuhi` dan tidak memblokir aktivasi (digantikan Q44).

Jika salah satu dari keempat syarat gagal, aktivasi ditolak dan pesan kesalahan menyebutkan syarat mana yang tidak terpenuhi (pengecualian gerbang 4 bertanda `tidak_dapat_dipenuhi` digantikan Q44).

**Pembuatan snapshot saat aktivasi/buka_kembali:** begitu transisi ke `aktif` berhasil (baik aktivasi awal maupun `jadwal:buka_kembali` dari `ditutup → aktif`), sistem membentuk baris `jadwal_snapshot` untuk tiap indikator aktif yang **belum** memiliki snapshot pada jadwal ini (lihat §2.17), lengkap dengan baris anak `jadwal_snapshot_komponen` yang membekukan definisi komponen indikator tersebut (lihat §2.18). Proses ini **idempoten**: baris yang sudah ada tidak pernah ditimpa, sehingga penambahan indikator baru yang sah hanya menambah baris baru tanpa mengganggu snapshot lama. Baris `audit_log` yang mencatat peristiwa ini (`jadwal.aktivasi` atau `jadwal.buka_kembali`) memakai `actor_id` = pengguna Perencanaan/Superadmin yang menjalankan aksinya — bukan nilai sistem, meski pembuatan barisnya berjalan otomatis di dalam transaksi yang sama.

**Koreksi setelah penutupan:** satu-satunya jalur koreksi pengukuran, rencana aksi, maupun kegiatan setelah `penutupan` tercapai adalah `jadwal:buka_kembali` (`ditutup → aktif`), lalu melakukan koreksi, lalu `jadwal:tutup` kembali — seluruh rangkaian ini tercatat di `audit_log` (lihat §2.20, §2.23, §2.25). Dalam sesi koreksi tersebut, PIC hanya dapat mengubah rencana aksi pada periode yang jendela pengisiannya dibuka kembali secara resmi (ISS-14.03, Q19); di luar itu koreksi rencana aksi dilakukan Perencanaan (Q41 §41.2 butir 6). Pengecualian: unggahan arsip dokumen resmi pada induk `jadwal_tahunan`/`jadwal_periode` (§2.30) tetap dapat dilakukan setelah jadwal `ditutup` tanpa `jadwal:buka_kembali`, karena tidak mengubah data; penutupan hanya menghentikan mutasi data kinerja (Q48 §48.2 butir 5).

**Arsip Rencana Aksi tahunan (rancangan Q48 — belum diimplementasikan):** `jadwal_tahunan` menjadi induk polimorfik `berkas` (`berkasable_type = jadwal_tahunan`, §2.30) untuk PDF Rencana Aksi tahunan bertanda tangan; satu jadwal boleh memiliki lebih dari satu berkas, mis. Rencana Aksi awal dan perubahan (Q48 §48.2 butir 1). Unggahan tidak mengubah data, status, maupun snapshot (Q48 §48.2 butir 4).

**Constraint:** satu baris per kombinasi (`renstra_id`, `tahun`) lintas seluruh status (`draft`, `aktif`, `ditutup`), sesuai [addendum ISS-03.01](SAKIP%20-%20Keputusan%20Penyelarasan.md), ditegakkan lewat **unique constraint**:
```sql
ALTER TABLE jadwal_tahunan
    ADD CONSTRAINT jadwal_tahunan_renstra_tahun_unik UNIQUE (renstra_id, tahun);
```
Keunikan berlaku per pasangan Renstra–tahun; aturan rentang/status Renstra pada §2.9 tetap berlaku secara terpisah. Jadwal existing digunakan kembali sesuai status dan permission, termasuk jalur `jadwal:buka_kembali` untuk koreksi setelah penutupan, tanpa membuat jadwal kedua. Constraint ini menggantikan indeks parsial `jadwal_tahunan_aktif_unik`. Rollout harus berhenti bila ada beberapa jadwal pada pasangan yang sama, termasuk kombinasi yang sah menurut skema lama, sampai tersedia keputusan penyelesaian data yang eksplisit; tidak boleh menghapus, menggabungkan, atau memindahkan referensi historis otomatis.

---

### 2.16 `jadwal_periode`

Daftar periode yang diharapkan pada suatu `jadwal_tahunan`, beserta jendela waktu pengisian masing-masing periode (jendela reviu dihapus Q70 §70.2). Tabel ini adalah **sumber kebenaran tunggal** untuk menentukan periode apa saja yang wajib diisi pada tahun tersebut (mis. Triwulan I–IV, atau Semester I–II) — status tampilan "Belum mengisi"/"Tidak mengisi" pada dashboard dihitung dari kombinasi indikator × periode yang diharapkan menurut tabel ini, bukan dari seluruh baris `periode` global yang ada.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `jadwal_id` | uuid | FK → jadwal_tahunan.id | |
| `periode_id` | uuid | FK → periode.id | |
| `pengisian_mulai` | date | not null | |
| `pengisian_selesai` | date | not null | Batas mutlak bagi PIC (izin `pengukuran:create`/`update` yang di-scope per unit lewat grant, lihat §2.7) untuk membuat/mengubah/mengajukan pengukuran pada periode ini. Setelah tanggal ini terlampaui, PIC tidak dapat lagi membuat/mengubah/mengajukan pengukuran periode berjalan. Sejak Q41 tanggal ini juga mengunci periode tersebut pada rencana aksi bagi PIC (lihat aturan integritas di bawah dan §2.23) |
| `reviu_mulai` | — | — | **Dihapus oleh migrasi #165 (Q70 §70.2 butir 6):** jendela reviu dihapus dari jadwal dan dari revisi jendela PIC; nilai lama tetap tercatat di payload audit. Semula: awal jendela verifikasi/pengesahan oleh Perencanaan, yang menggerbangi verifikasi sebelum tanggal ini |
| `reviu_selesai` | — | — | **Dihapus oleh migrasi #165 (Q70 §70.2 butir 6):** penanda reviu terlambat tidak lagi dibuat (Q70 §70.2 butir 4). Semula: target akhir reviu; aksi reviu setelah tanggal ini diberi penanda terlambat, dan `penutupan` wajib setelah tanggal ini |

**Constraint:** `unique(jadwal_id, periode_id)` — satu periode hanya boleh muncul sekali per jadwal tahunan. CHECK `jadwal_periode_urutan_tanggal`: `pengisian_mulai <= pengisian_selesai` (semula juga mengurutkan kolom reviu; dipersempit oleh migrasi #165).

**Aturan integritas:**
- Batas `pengisian_selesai` bersifat mutlak hanya bagi PIC dengan izin ber-scope unit (grant, §2.7). Peran **Perencanaan** (izin global lewat `role_permissions`, §2.5) dikecualikan dari batas ini dan tetap dapat mengisi/mengubah pengukuran sampai `jadwal_tahunan.penutupan` tercapai.
- **Tanpa jendela reviu (Q70 §70.2):** verifikasi, pengembalian, dan pengesahan pengukuran dapat dilakukan sejak pengukuran diajukan sampai `jadwal_tahunan.penutupan`. Penanda reviu terlambat tidak lagi dibuat; penanda yang sudah tercatat di `audit_log` tetap ditampilkan sebagai histori.
- **Batas awal jendela pengisian (Q70 §70.2 butir 2 dan 7, §70.4 butir 1–2):** `pengisian_mulai` tidak boleh sebelum tanggal 1 bulan terakhir periode pada tahun jadwal (mis. Triwulan IV 1 Desember), dihitung dari tanggal periode (§2.14); batas akhir tidak diatur, sehingga pengisian Triwulan IV pada Januari tahun berikutnya sah. Ditegakkan di level aplikasi saat draf disimpan (seluruh jendela; periode yang belum bertanggal ditolak), saat revisi jendela PIC (hanya bila `pengisian_mulai` jendela itu diubah; perpanjangan `pengisian_selesai` saja tidak diperiksa), dan saat aktivasi memakai tanggal periode yang berlaku (kalender tidak valid). Jadwal yang sudah aktif tidak diubah otomatis.
- Indikator berstatus `arsip` **tidak dihitung** sebagai kewajiban pengisian pada kombinasi indikator × periode manapun di tabel ini.
- Periode dengan `periode.is_nilai_akhir = true` (mis. "Tahunan") tetap dapat memiliki baris di tabel ini seperti periode lain; nilainya diisi manual, tidak dihitung otomatis dari agregasi periode-periode lain (lihat §2.14).
- Rencana aksi disusun **setelah** daftar periode pada jadwal tahunan tersusun (target periode pada rencana aksi butuh daftar periode ini) — lihat §2.23.
- **Kunci per periode rencana aksi (Q41 §41.2):** setelah `pengisian_selesai` periode P terlewati (tanggal bisnis Asia/Makassar), periode P terkunci bagi PIC pada rencana aksi — simpan draf, pengajuan, maupun versi baru setelah dikembalikan atau dibuka kembali tidak boleh mengubah `rencana_aksi_target` periode P maupun klaim kegiatan tahap RA atas kegiatan periode P; pelanggaran ditolak sebagai validasi bisnis (422) dan diaudit. Perencanaan dikecualikan sampai `jadwal_tahunan.penutupan`. Periode yang sudah lampau saat aktivasi jadwal terkunci bagi PIC sejak aktivasi (Q41 §41.2, Q46). Kunci mengikuti nilai `pengisian_selesai` yang berlaku, termasuk hasil revisi resmi jendela pengisian (Q18, ISS-03.06): perpanjangan jendela pengisian periode P ikut menggeser kunci rencana aksi periode P bagi PIC (Q41 §41.2 butir 5).

**Arsip Laporan Kinerja Triwulan (rancangan Q48 — belum diimplementasikan):** `jadwal_periode` menjadi induk polimorfik `berkas` (`berkasable_type = jadwal_periode`, §2.30) untuk PDF Laporan Kinerja Triwulan bertanda tangan per periode; aturannya sama dengan arsip Rencana Aksi tahunan (§2.15), termasuk tetap dapat diunggah setelah jadwal `ditutup` (Q48 §48.2 butir 4–5).

---

### 2.17 `jadwal_snapshot`

Salinan beku konteks indikator (termasuk konteks cara hitung) per jadwal, berversi. Versi 1 dibuat otomatis oleh logika aplikasi saat `jadwal_tahunan` bertransisi ke `aktif` — baik pada aktivasi awal maupun pada `jadwal:buka_kembali`; imutabilitasnya dijaga trigger database (lihat perilaku kunci). Masuk penuh sejak Fase Awal sebagai fondasi integritas historis.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `jadwal_id` | uuid | FK → jadwal_tahunan.id | |
| `indikator_id` | uuid | FK → indikator.id | Referensi ke master, untuk ketertelusuran — **bukan** untuk menarik data terkini |
| `nomor_versi` | int | not null, default 1 | Versi konteks indikator pada jadwal ini; koreksi menambah versi baru (Q35 §35.1) |
| `menggantikan_id` | uuid | FK → jadwal_snapshot.id, nullable | Versi sebelumnya yang digantikan; `NULL` pada versi 1 |
| `alasan_koreksi` | text | nullable | Alasan koreksi; diisi pada versi koreksi (ISS-03.03) |
| `rujukan_koreksi` | text | nullable | Rujukan resmi koreksi; diisi pada versi koreksi (ISS-03.03). Pada versi yang diterbitkan revisi PK yang mengubah target berisi surat persetujuan Eselon I (Q40 §40.1, rancangan) |
| `periode_mulai_id` | uuid | FK → periode.id, not null | Periode pertama snapshot berlaku; versi aktivasi memakai periode jadwal dengan urutan terendah; versi akibat revisi PK memakai periode pertama yang belum ditutup (Q40 §40.1, rancangan) |
| `unit_id` | uuid | (salinan, bukan FK aktif secara semantik) | Salinan nilai `indikator.unit_id` saat aktivasi |
| `nama` | string | salinan | Salinan `indikator.nama` saat aktivasi |
| `definisi` | text | salinan | Salinan `indikator.definisi` saat aktivasi |
| `satuan` | string | salinan | Salinan `indikator.satuan` saat aktivasi |
| `presisi` | smallint | salinan | Salinan `indikator.presisi` saat aktivasi |
| `desimal_tampilan` | smallint | salinan | Salinan `indikator.desimal_tampilan` saat aktivasi |
| `arah` | enum(`naik_baik`,`turun_baik`) | salinan | Salinan `indikator.arah` saat aktivasi — konteks beku arah membaik bagi pengukuran yang merujuk snapshot ini (lihat §2.20); pemakaiannya untuk menilai kewajiban catatan digantikan narasi wajib Q45 |
| `tipe_perhitungan` | enum(`rasio_persen`,`penjumlahan`,`manual`) | salinan | Salinan `indikator.tipe_perhitungan` saat aktivasi — menentukan apakah pengukuran yang merujuk snapshot ini dihitung dari komponen atau diketik manual, tanpa terpengaruh perubahan definisi komponen di tengah tahun |
| `dinilai_tahunan` | boolean | salinan | Salinan `indikator.dinilai_tahunan` saat aktivasi — konteks beku yang menentukan apakah target rencana aksi pada periode sebelum periode nilai akhir boleh kosong (§2.23–§2.24). Termasuk kolom konteks yang dijaga guard snapshot; perubahan penanda setelah jadwal aktif berlaku pada jadwal berikutnya; koreksi tahun berjalan lewat koreksi snapshot (Q35 §35.1, Q47 §47.1 butir 2) |
| `target` | numeric | nullable, salinan | Salinan `target_tahunan.nilai` (target PK, Q39) untuk `(indikator_id, tahun jadwal)` saat aktivasi; versi yang diterbitkan revisi PK membawa target versi PK tersebut (§2.37, rancangan Q40) |
| `baseline` | numeric | nullable, salinan | Salinan `target_tahunan.baseline` untuk `(indikator_id, tahun jadwal)` saat aktivasi; versi yang diterbitkan revisi PK membawa baseline yang sama dengan versi sebelumnya, karena baseline bukan isi dokumen PK dan tidak berversi (Q50 §50.3, §2.37, rancangan Q40) |
| `komposisi_final` | boolean | not null, default false | `true` sejak snapshot terbit; setelah itu komponen tidak dapat disisipkan (§2.18) |

**Constraint:** `unique(jadwal_id, indikator_id, nomor_versi)` — setiap versi konteks indikator pada satu jadwal unik; pembuatan versi 1 saat aktivasi tetap idempoten per (`jadwal_id`, `indikator_id`).

**Perilaku kunci:**
- **Idempoten:** pembuatan versi 1 dilakukan hanya untuk pasangan (`jadwal_id`, `indikator_id`) yang belum memiliki snapshot pada jadwal tersebut — baik saat aktivasi awal maupun saat `jadwal:buka_kembali`. Baris yang sudah ada **tidak pernah ditimpa**.
- **Beku sejak terbit (Q35 §35.1):** begitu jadwal bertransisi ke `aktif`, seluruh snapshot jadwal tersebut difinalkan (`komposisi_final = true`) — oleh aplikasi dan oleh trigger database `finalisasi_snapshot_saat_jadwal_aktif`. Guard `guard_referenced_schedule_snapshot` menolak UPDATE kolom konteks dan DELETE; UPDATE yang diterima hanya no-op atau transisi `komposisi_final` `false → true`.
- **Koreksi selalu versi baru:** koreksi tidak pernah in-place, baik sebelum maupun sesudah snapshot dirujuk. Koreksi menyisipkan baris `nomor_versi` + 1 dengan `menggantikan_id`, `alasan_koreksi`, dan `rujukan_koreksi` (alur pengguna: ISS-03.03); versi lama tetap menjadi rujukan histori. Persetujuan revisi PK yang mengubah target (Q40, rancangan §2.36) juga menerbitkan versi baru dengan `periode_mulai_id` = periode pertama yang belum ditutup dan `rujukan_koreksi` = surat persetujuan Eselon I; periode yang sudah dilaporkan tetap memakai versi lama. Revisi yang hanya mengganti dokumen PK tidak menerbitkan versi.
- **Tidak ada restatement:** perubahan pada `indikator`/`target_tahunan`/`indikator_komponen` master setelah snapshot terbentuk tidak pernah mempropagasi ke baris snapshot yang sudah ada — tidak ada mekanisme restatement data historis pada Fase Awal maupun Fase Lanjutan. Baris baru untuk indikator yang sama hanya terbentuk lagi saat jadwal tahun berikutnya diaktifkan, dengan kondisi master terkini pada saat itu. Konsekuensinya, perubahan definisi komponen (§2.27) di tengah tahun **tidak** mengubah makna data historis yang sudah dibekukan.

---

### 2.18 `jadwal_snapshot_komponen`

Salinan beku definisi komponen indikator pada suatu `jadwal_snapshot`, dibuat pada saat yang sama dengan baris induknya. Tabel anak ini melengkapi §2.17 agar cara hitung berbasis komponen ikut membeku, bukan hanya nilai target/baseline.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `jadwal_snapshot_id` | uuid | FK → jadwal_snapshot.id | |
| `komponen_id` | uuid | FK → indikator_komponen.id | Komponen master yang dibekukan, untuk ketertelusuran |
| `kode` | varchar | salinan | Salinan `indikator_komponen.kode` saat aktivasi |
| `label` | text | salinan | Salinan `indikator_komponen.label` saat aktivasi |
| `peran` | enum(`pembilang`,`penyebut`,`penjumlah`) | salinan | Salinan `indikator_komponen.peran` saat aktivasi |
| `bobot` | numeric | salinan | Salinan `indikator_komponen.bobot` saat aktivasi |
| `urutan` | int | salinan | Salinan `indikator_komponen.urutan` saat aktivasi |

**Perilaku kunci:** mengikuti sifat idempoten dan beku `jadwal_snapshot` induknya (§2.17) — baris hanya dibuat untuk snapshot yang baru dibentuk dan tidak pernah ditimpa. UPDATE dan DELETE selalu ditolak; INSERT ditolak bila induk sudah `komposisi_final = true` atau sudah dirujuk, sehingga komposisi komponen tidak berubah setelah snapshot terbit — koreksi komposisi wajib lewat versi snapshot baru (Q35 §35.1). Indikator bertipe `manual` tetap dapat memiliki baris `jadwal_snapshot` tanpa baris `jadwal_snapshot_komponen` (tabel anak kosong bila indikator tidak memiliki komponen aktif).

---

### 2.19 `penanggung_jawab`

Riwayat assignment penanggung jawab per indikator. Baris tidak dihapus untuk menjaga histori.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `indikator_id` | uuid | FK → indikator.id | |
| `user_id` | uuid | FK → users.id | target harus ada dan aktif saat penetapan |
| `tanggal_mulai_berlaku` | date | not null | |
| `ditetapkan_oleh` | uuid | FK → users.id | Perencanaan/Superadmin sesuai permission |
| `alasan` | text | nullable | Opsional; sejak Q43 §43.3 tidak lagi ditampilkan pada form penetapan maupun pergantian PJ. Semula wajib untuk pergantian setelah assignment pertama (digantikan Q43) |
| `created_at` | timestamp | not null | |
| `urutan` | bigint | not null, generated always as identity | Urutan pencatatan penugasan, diisi database; penentu PJ efektif bila beberapa baris memiliki tanggal sama |

**Kontrak dasar — Plan §4.3–4.4 / Issue #54:** PJ efektif pada tanggal T adalah baris dengan `tanggal_mulai_berlaku` terbesar yang `<= T`, lalu `urutan` terbesar bila tanggalnya sama; pergantian menambah histori tanpa overwrite (kewajiban alasan pergantian digantikan Q43 §43.3).

**Aturan PJ — Diratifikasi Q34; butir 1 (pergantian di tanggal yang sama) diimplementasikan pada [#70](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/70):** PJ-01 (tanggal mutasi lampau/mendatang), PJ-03 (no-op ditolak dan mantan PJ dapat kembali), PJ-04 (assignment mendatang dipertahankan), dan PJ-05 (guard indikator arsip/Renstra diarsipkan/unit nonaktif) sesuai Q34 §34.1 butir 2–6. PJ-02 (unique(`indikator_id`, `tanggal_mulai_berlaku`) tanpa tie-break) digantikan butir 1: pergantian PJ pada tanggal yang sama diperbolehkan dengan tepat satu PJ efektif menurut tanggal efektif lalu urutan penugasan (`urutan`), bukan `created_at` atau UUID. Unique per tanggal dilepas dan diganti index non-unique; histori existing dipertahankan tanpa koreksi. Evidence dan dampak rekonsiliasi dirujuk pada [matriks provenance ISS-04.01](SAKIP%20-%20User%20Issues.md#status-review-dan-traceability-iss-0401--8-oktober-2026).

**Kontrak final Q32:**

- tidak ada constraint PIC operasional (bukan role);
- user aktif mana pun dapat ditunjuk;
- assignment tidak memberi permission;
- hak kerja dinilai PermissionResolver dari role/grant yang sah dan explicit deny pada unit indikator;
- calon PJ dengan izin kerja belum lengkap menghasilkan warning, bukan blokir;
- sistem perlu query/report **PJ aktif tanpa hak isi**;
- perubahan role tidak mengakhiri assignment;
- revoke grant harus eksplisit, beralasan, dan teraudit.

### 2.20 `pengukuran`

Nilai capaian aktual per indikator, per tahun, per periode. Baris tidak dapat dihapus permanen jika `nilai`/`catatan` sudah terisi.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `indikator_id` | uuid | FK → indikator.id | |
| `tahun` | int | not null | |
| `periode_id` | uuid | FK → periode.id | |
| `jadwal_snapshot_id` | uuid | FK → jadwal_snapshot.id | Konteks beku yang dirujuk untuk tampilan (nama, satuan, arah, tipe perhitungan, target/baseline pembanding) |
| `nilai` | numeric | **nullable** | `0` sah; `null` bila `status_perhitungan` bukan `terhitung` (belum diisi, tidak dapat dihitung, atau belum dapat diukur — rancangan Q47). Untuk indikator bertipe `rasio_persen`/`penjumlahan`, kolom ini menjadi **nilai turunan** — dihitung sistem dari `pengukuran_komponen` (lihat §2.28) dan **tidak dapat diketik langsung oleh PIC** (read-only di UI). Untuk tipe `manual`, perilaku lama berlaku sepenuhnya: nilai diketik langsung |
| `sumber_nilai` | enum(`komponen`,`manual`) | not null | Mencatat asal nilai untuk kebutuhan audit: `komponen` bila diturunkan dari `pengukuran_komponen`, `manual` bila diketik langsung |
| `status_perhitungan` | enum(`belum_diisi`,`terhitung`,`tidak_dapat_dihitung`; rancangan Q47: `belum_dapat_diukur`) | not null, default `belum_diisi`; CHECK `nilai_status_cocok` | Status hasil realisasi; kolom dan CHECK sudah ada di aplikasi (tabel `pengukuran_kinerjas`). CHECK `nilai_status_cocok`: `terhitung` ⇒ `nilai` terisi; `belum_diisi` dan `tidak_dapat_dihitung` (penyebut nol, Q9, §2.28) ⇒ `nilai` NULL. Pengajuan ditolak selama `belum_diisi`. **Nilai `belum_dapat_diukur` — Rancangan Q47 — belum diimplementasikan:** realisasi belum tersedia pada periode tersebut; ditetapkan eksplisit oleh pengisi, bukan disimpulkan dari `nilai` kosong; `nilai` NULL dan CHECK `nilai_status_cocok` diperluas untuk nilai ini; label UI "Belum dapat diukur"; berbeda dari realisasi `0` dan dari `tidak_dapat_dihitung` (Q47 §47.4) |
| `alasan_tidak_dapat_dihitung` | text | nullable | Alasan bila `status_perhitungan = tidak_dapat_dihitung`; wajib terisi saat pengajuan dengan status tersebut (Q9). Kolom sudah ada di aplikasi |
| `catatan` | text | nullable | Keterangan tambahan **opsional** (Q45 §45.1). Kewajiban lama **digantikan Q45** oleh narasi wajib di bawah: semula wajib diisi saat pengajuan (transisi `draft → diajukan`) jika (a) nilai memburuk menurut `indikator.arah` dibandingkan pengukuran berstatus `disahkan` **terakhir secara kronologis** untuk indikator yang sama — nilai yang stagnan/sama tidak memicu kewajiban ini; atau (b) `indikator.wajib_catatan = true`; pengukuran pertama tanpa pembanding historis tidak wajib, dan perbandingan dilakukan atas nilai turunan untuk indikator bertipe komponen |
| `progres_kegiatan` | text | nullable saat draf; wajib saat diajukan | **Rancangan Q45 — belum diimplementasikan.** Narasi progres kegiatan per indikator per periode |
| `kendala_masalah` | text | nullable saat draf; wajib saat diajukan | **Rancangan Q45 — belum diimplementasikan.** Narasi kendala/masalah |
| `strategi_tindak_lanjut` | text | nullable saat draf; wajib saat diajukan | **Rancangan Q45 — belum diimplementasikan.** Narasi tindak lanjut/strategi penyelesaian |
| `tindak_lanjut_rekomendasi` | text | nullable; wajib saat diajukan bila ada rekomendasi disetujui pada periode sebelumnya | **Rancangan Q45 — belum diimplementasikan.** Tindak lanjut atas rekomendasi Pimpinan; wajib bila terdapat `rekomendasi_pimpinan` (§2.31) berstatus `disetujui` pada periode sebelumnya untuk indikator yang sama (Q45 §45.2 butir 3); draf rekomendasi tidak memicu kewajiban ini (Q51 §51.1 butir 2) |
| `status_alur` | enum(`draft`,`diajukan`,`diverifikasi`,`dikembalikan`,`disahkan`) | not null, default `draft` | |
| `versi` | integer | not null, default 1 | **Optimistic locking** — dinaikkan setiap perubahan |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Gerbang rencana aksi (keras):** pengajuan `pengukuran` (transisi `draft → diajukan`) untuk periode P pada indikator I **ditolak** selama `rencana_aksi` untuk (indikator I × tahun) belum berstatus `disahkan` (lihat §2.23).

**Larangan pembuatan untuk indikator arsip:** permintaan `pengukuran:create` **ditolak sistem** apabila `indikator.status = arsip` pada indikator terkait, terlepas dari permission/scope yang dimiliki pengguna (lihat §2.12). Sejak keputusan PM 15 Oktober 2026 (komentar review PR #107, melengkapi Q37 §37.1 butir 5): guard indikator arsip pada Pengukuran berlaku untuk kelima perintah — simpan draf, ajukan, verifikasi, kembalikan, dan sahkan — dengan pesan `Indikator berstatus arsip; pengukuran tidak dapat diubah, diajukan, atau direviu.`; penolakan diaudit tanpa mengubah header; detail/histori tetap terbaca sesuai otorisasi.

**Kelengkapan komponen:** untuk indikator bertipe `rasio_persen`/`penjumlahan`, pengajuan pengukuran **ditolak** bila ada `indikator_komponen` aktif yang nilainya pada `pengukuran_komponen` masih `null` — kecuali pengukuran berstatus `belum_dapat_diukur` (rancangan Q47, di bawah). Kelengkapan komponen adalah syarat tambahan di samping narasi wajib di bawah (semula: aturan catatan berbasis `arah`/`wajib_catatan`, digantikan Q45) (lihat §2.28).

**Realisasi belum dapat diukur (rancangan Q47 — belum diimplementasikan):** pengukuran dapat diajukan dengan `status_perhitungan = belum_dapat_diukur` bila nilainya belum tersedia pada periode tersebut; gerbang kelengkapan komponen dikecualikan, sedangkan narasi wajib tetap berlaku dan menjelaskan mengapa nilai belum tersedia. Perencanaan menilai alasan itu saat verifikasi dan dapat mengembalikan pengukuran (Q47 §47.4 butir 1–2). Status ini tidak dihitung sebagai capaian 0 dan tidak diberi status capaian (§2.21). Pada periode nilai akhir (`periode.is_nilai_akhir`) status ini tidak dapat dipakai: realisasi wajib terisi, kecuali `tidak_dapat_dihitung` menurut Q9 (Q47 §47.4 butir 4).

**Narasi wajib (rancangan Q45 — belum diimplementasikan):** pengajuan (`draft → diajukan`) **ditolak** bila `progres_kegiatan`, `kendala_masalah`, atau `strategi_tindak_lanjut` kosong, serta bila `tindak_lanjut_rekomendasi` kosong padahal terdapat rekomendasi berstatus `disetujui` pada periode sebelumnya untuk indikator yang sama (Q51 §51.1 butir 2). Narasi per kegiatan (`kegiatan.uraian_pelaksanaan`/`kendala`/`strategi_tindaklanjut`, §2.25) tetap tersedia dan opsional; rekapitulasi menampilkan narasi pengukuran bersama daftar kegiatan yang diklaim. Gerbang narasi dan tindak lanjut rekomendasi dikecualikan untuk periode lampau 2026 (Q46 §46.1).

**Pemisahan tugas pada jalur verifikasi/pengesahan (wajib dibaca sebelum menerapkan permission):** aktor yang memegang izin `pengukuran:verifikasi`/`pengukuran:sahkan` **tidak otomatis** boleh memverifikasi/mengesahkan setiap pengukuran — bila pengajuan dilakukan lewat jalur PIC ber-scope unit, aktor yang sama dengan `pengukuran_versi.diajukan_by` **dilarang keras** melanjutkan transisi `diajukan → diverifikasi` maupun `diverifikasi → disahkan` atas baris itu sendiri. Aturan lengkapnya, termasuk pengecualian untuk jalur Perencanaan, ada di §4 (Pemisahan Tugas) — bukan bagian dari resolusi izin (§3), melainkan validasi bisnis tambahan yang berjalan setelah izin dinyatakan boleh.

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

**Periode tanpa target dan realisasi belum dapat diukur (rancangan Q47 — belum diimplementasikan):** capaian persen tidak dihitung, dan penetapan `status_capaian` manual **tidak tersedia** (permintaan penetapan ditolak) untuk pengukuran pada periode tanpa target, yaitu periode yang target rencana aksinya kosong (§2.24), maupun pengukuran `status_perhitungan = belum_dapat_diukur` (§2.20) (Q47 §47.3 butir 1 dan 4). Periode tanpa target ditampilkan sebagai "Tanpa target periode", bukan "Belum tercapai", dengan realisasi, target PK, dan **selisih terhadap target PK** (realisasi − target PK snapshot dalam satuan indikator, tandanya dibaca menurut `arah`); pengukuran belum dapat diukur — termasuk pada periode tanpa target — ditampilkan sebagai "Belum dapat diukur" dan tidak dihitung sebagai capaian 0 (Q47 §47.3 butir 3, §47.4). Selisih adalah nilai turunan tampilan, tidak disimpan, dan juga ditampilkan pada periode bertarget sebagai informasi tambahan (Q47 §47.3 butir 2). Status capaian dan selisih memakai target PK tersimpan bernilai penuh (Q50 §50.2). Setelah revisi PK berversi tersedia, capaian akhir tahun dinilai terhadap target PK akhir, yaitu versi snapshot yang berlaku pada periode nilai akhir (Q40 §40.1 butir 6, rancangan §2.36).

---

### 2.22 `pengaturan`

Modul setelan aplikasi bergaya key-value, menampung nilai identitas dan preferensi presentasional yang dapat diubah tanpa deployment ulang.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `kunci` | string | **unique**, not null | Mis. `instansi.nama`, `instansi.alamat`, `instansi.telepon`, `instansi.surel`, `instansi.laman`, `instansi.logo`, `aplikasi.nama`, `aplikasi.label_unit`, `tampilan.zona_waktu`, `tampilan.format_tanggal`, `tampilan.format_angka`, `laporan.header`, `laporan.footer`, dan grup **`berkas`**: `berkas.unggahan_aktif`, `berkas.ukuran_maks_kb`, `berkas.format_diizinkan`, `berkas.tautan_selalu_diizinkan` (grup `berkas` tidak lagi dikelola dari UI sejak Q44, lihat di bawah) |
| `nilai` | text | nullable | Nilai tersimpan sebagai string; ditafsirkan sesuai `tipe` saat dibaca |
| `tipe` | string | not null | mis. `string`, `text`, `number`, `boolean`, `url`, `file` — menentukan cara parsing dan tampilan form setelan |
| `grup` | string | not null | Pengelompokan tampilan pada halaman setelan, mis. `identitas`, `aplikasi`, `tampilan`, `laporan`, `berkas` |
| `updated_by` | uuid | FK → users.id, nullable | Diisi saat setelan pertama kali diubah dari nilai default hasil seed |
| `updated_at` | timestamp | not null | |

**Constraint:** `unique(kunci)`.

**Kunci grup `berkas` (kebijakan storage bukti dukung — histori, digantikan Q44):**

> **Q44 (13 Oktober 2026):** menu Kebijakan Storage dan halaman Batas Unggahan Berkas dihapus; kunci di bawah tidak lagi dikelola dari UI dan tidak lagi menjadi sumber batas unggahan. Server menegakkan batas teknis dari konfigurasi aplikasi: default **10 MB** (10240 KB) dan format `pdf,docx,xlsx,jpg,jpeg,png` untuk lampiran tanpa persyaratan dan untuk persyaratan yang mengosongkan format atau ukuran, serta batas atas **100 MB** (102400 KB) per berkas yang tidak boleh dilampaui `jenis_berkas.ukuran_maks_kb` (§2.29–§2.30). Saklar unggahan dihapus; unggahan file selalu aktif. Perubahan batas teknis dilakukan lewat konfigurasi deployment dan dicatat pada catatan rilis. Status implementasi: belum.

| Kunci | Tipe | Default | Keterangan |
|---|---|---|---|
| `berkas.unggahan_aktif` | boolean | `true` | Saklar utama mode unggahan file di seluruh aplikasi — dihapus Q44; unggahan file selalu aktif |
| `berkas.ukuran_maks_kb` | integer | `10240` | Batas ukuran default dipakai bila `jenis_berkas.ukuran_maks_kb` kosong — digantikan default konfigurasi server (Q44) |
| `berkas.format_diizinkan` | teks | `pdf,docx,xlsx,jpg,jpeg,png` | Daftar format default dipakai bila `jenis_berkas.format_diizinkan` kosong — digantikan default konfigurasi server (Q44) |
| `berkas.tautan_selalu_diizinkan` | boolean | `true` | Menandai bahwa mode `tautan` dan `teks` selalu tersedia sebagai jalur alternatif tanpa memakai storage — tidak lagi dikelola dari UI (Q44) |

**Aturan integritas dan cakupan:**
- Nilai default di-seed saat instalasi (migrasi/seeder) untuk seluruh kunci awal di atas; aplikasi membaca nilai lewat accessor yang di-cache, bukan query langsung berulang pada tiap render.
- **Fallback batas unggahan (Q44):** nilai pada `jenis_berkas` (`format_diizinkan`, `ukuran_maks_kb`, serta mode yang diizinkan) berlaku untuk persyaratannya; bila format atau ukuran dikosongkan, default batas teknis konfigurasi server yang berlaku, dan `ukuran_maks_kb` tidak boleh melampaui batas atas konfigurasi server (lihat §2.29). Ketentuan lama — kunci grup `berkas` sebagai nilai fallback dan saklar kebijakan tingkat aplikasi — digantikan Q44. Batas tegas tetap: daftar mode yang diizinkan per persyaratan milik `jenis_berkas` (kewenangan Tim Perencanaan), bukan milik setelan.
- Permission `pengaturan:update` dimiliki oleh **dua** peran — `superadmin` dan `admin` (lihat §2.4–§2.5, catatan pemisahan tugas) — dan tidak diberikan ke peran lain; pembacaan nilai setelan terjadi otomatis saat render halaman/laporan tanpa memerlukan permission khusus.
- Setiap perubahan nilai tercatat di `audit_log` (`nilai_lama`/`nilai_baru` berisi pasangan kunci-nilai) — masuk daftar peristiwa teraudit (lihat §2.32); alasan perubahan setelan opsional (Q43 §43.3). Kunci grup `berkas` tidak lagi diubah dari UI sejak Q44.
- Panel **read-only "Penggunaan penyimpanan bukti dukung"** (jumlah berkas mode `file`, total `ukuran_bytes`, dan jumlah bukti mode `tautan`/`teks`) **dihapus Q44 §44.1**; tampilan daftar berkas terunggah masuk backlog.
- Batas cakupan tegas: kunci yang boleh dikelola lewat tabel ini hanya **teks dan preferensi presentasional/operasional** (identitas instansi/aplikasi, label tampilan, format tanggal/angka, header/footer ekspor). Kebijakan storage bukti dukung dikeluarkan dari cakupan oleh Q44; batas teknis unggahan berada di konfigurasi server. Nilai enum/status, nama permission, dan aturan bisnis substantif atas persyaratan bukti dukung **tidak** disimpan di sini — seluruhnya tetap berupa konstanta pada kode aplikasi atau milik `jenis_berkas`.
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
| `snapshot_draf_id` | uuid | nullable, rujukan non-FK | Jepit versi `jadwal_snapshot` yang menjadi konteks draf: diisi versi terbaru saat draf dibuat dan dimajukan ke versi terbaru setiap simpan; `NULL` bila jadwal belum pernah aktif. Snapshot yang dirujuk kolom ini diperlakukan sebagai dirujuk oleh guard snapshot |
| `penanggung_jawab_id` | uuid | FK → users.id, not null | PIC efektif pada saat penyusunan — hasil resolusi `penanggung_jawab` (§2.19) pada tanggal penyusunan |
| `uraian` | text | nullable | |
| `status_alur` | enum(`draft`,`diajukan`,`diverifikasi`,`dikembalikan`,`disahkan`) | not null, default `draft` | |
| `versi` | int | not null, default 1 | Optimistic locking |
| `alasan_revisi` | text | nullable | Alasan revisi/buka-kembali; tidak dipakai untuk deviasi target PK |
| `alasan_deviasi_pk` | text | nullable | Alasan deviasi total target periode efektif terakhir terhadap target tahunan PK snapshot. Boleh kosong saat draf (hanya peringatan); wajib saat pengajuan bila terdapat deviasi (Q34, ADR-0008) |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |
| `disahkan_at` | timestamp | nullable | |
| `disahkan_by` | uuid | FK → users.id, nullable | |

**Constraint:** `unique(indikator_id, tahun)` — rencana aksi disusun tepat sekali per (indikator × tahun), bukan per periode; target per periode disimpan pada `rencana_aksi_target` (§2.24).

**Posisi dalam alur:** rencana aksi disusun **setelah** daftar periode pada jadwal tahunan tersusun (`jadwal_periode`, §2.16 — target triwulan butuh daftar periodenya). Sejak Q41 tidak ada jendela penyusunan: rencana aksi memuat target per periode dan daftar kegiatan per periode (Q42), dan PIC efektif dengan grant yang sah dapat menyusun, mengubah, serta mengajukannya sepanjang tahun, sejak jadwal tahun tersebut `aktif` sampai `penutupan`. Ketentuan lama — disusun **sebelum** jendela pengisian periode pertama dibuka, dalam jendela tingkat tahun `jadwal_tahunan.rencana_aksi_mulai`/`rencana_aksi_selesai` (§2.15) — digantikan Q41.

**Aturan jendela waktu dan kunci per periode (Q41):** PIC (izin ber-scope unit lewat grant, §2.7) dapat menulis rencana aksi sejak jadwal `aktif` sampai `jadwal_tahunan.penutupan`. Periode P terkunci bagi PIC setelah `jadwal_periode.pengisian_selesai` periode P terlewati (tanggal bisnis Asia/Makassar, §2.16): simpan draf, pengajuan, dan versi baru setelah dikembalikan atau dibuka kembali tidak boleh mengubah `rencana_aksi_target` periode P maupun klaim kegiatan tahap RA (`sumber_klaim = rencana_aksi`) atas kegiatan periode P; pelanggaran ditolak sebagai validasi bisnis (422) dan diaudit. Periode lampau 2026 saat aktivasi jadwal sudah terkunci bagi PIC sejak aktivasi (Q41 §41.2, Q46). Kunci mengikuti nilai `pengisian_selesai` yang berlaku, termasuk perpanjangan resmi jendela pengisian (Q18; Q41 §41.2 butir 5). Dalam sesi koreksi setelah penutupan (`jadwal:buka_kembali`), PIC hanya dapat mengubah rencana aksi pada periode yang jendela pengisiannya dibuka kembali secara resmi (ISS-14.03, Q19); di luar itu koreksi dilakukan Perencanaan (Q41 §41.2 butir 6). **Perencanaan dikecualikan** — permission `rencana_aksi:create`/`update`/`ajukan` milik Perencanaan diperoleh lewat peran (§2.4–§2.5) sehingga bersifat **global (tanpa scope unit)**, dan hanya dibatasi oleh `jadwal_tahunan.penutupan` — pola yang identik dengan pengukuran (lihat §2.16, §3). Batas mutlak `rencana_aksi_selesai` bagi PIC digantikan Q41.

**Status alur:** mengikuti pola pengukuran — `draft → diajukan → diverifikasi → disahkan`, dengan `dikembalikan` (alasan wajib) sebagai jalur revisi. Permission `rencana_aksi:buka_kembali` (Perencanaan/Superadmin; alasan wajib; tersedia sebelum `jadwal_tahunan.penutupan` atau setelah `penutupan` di dalam sesi koreksi resmi yang mencakup `rencana_aksi` dan indikator tersebut, sesuai [Keputusan PM — PR #100](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/100#issuecomment-6161432383)) menangani transisi `disahkan → dikembalikan`, sejalan dengan pengukuran:buka_kembali, kecuali waktu sahnya (§2.20). Sejak Q41, buka-kembali dipakai bila target periode yang belum terkunci perlu diubah; rencana aksi yang sudah disahkan tidak dibuka kembali hanya untuk memindahkan kegiatan yang mundur, karena kegiatan itu ditangani lewat kegiatan lanjutan (§2.25–§2.26; Q41 §41.4).

**Guard review (Q37 §37.1):** verifikasi, pengembalian, dan pengesahan memakai `businessErrors` yang sama dan ditolak bila indikator berstatus `arsip` atau unit konteks RA `nonaktif`, termasuk pada jalur F2 dan request langsung. Guard diperiksa server-side pada mutation boundary; penolakan diaudit tanpa mengubah header/payload versi. Detail dan histori tetap dapat dibaca sesuai otorisasi, dan UI menampilkan alasan dari server. Renstra `diarsipkan` sudah tertutup oleh guard jadwal karena tidak dapat keluar dari `aktif` selama mempunyai jadwal aktif (§2.9); tidak ditambahkan guard Renstra arsip terpisah.

**Alasan review (Q37 §37.2):** verifikasi cukup konfirmasi, tanpa alasan pengguna; audit memakai alasan baku server dan `dasar_izin`. Pengembalian wajib alasan pengguna yang divalidasi dan disanitasi di backend. Permission sensitif tidak otomatis mewajibkan alasan pengguna (§2.32, §3.5).

**Penerima pengembalian (Q37 §37.3):** penerima ditentukan dari PJ efektif saat pengembalian melalui `PenugasanIndikator::effectiveOn()`, bukan `penanggung_jawab_id` historis pada header atau pengaju historis. `rencana_aksi_versi.diajukan_by` dan audit tetap mencatat pengaju historis. ISS-05.04 menetapkan penerima dan event setelah commit (tidak pada rollback); pengiriman in-app menunggu ISS-12.02 dan tautan tetap diotorisasi saat dibuka.

**Versi pengajuan beku:** setiap pengajuan tersimpan sebagai baris `rencana_aksi_versi` (§2.34) berisi provenance beku dan snapshot isi pengajuan; yang disahkan pada transisi akhir adalah snapshot versi tersebut, bukan header.

**Gerbang kelengkapan saat pengajuan:** pengajuan rencana aksi (`draft → diajukan`, atau `dikembalikan → diajukan` sebagai revisi langsung tanpa kembali ke `draft`) **ditolak** bila ada periode efektif yang targetnya belum berstatus `terhitung`. Periode efektif adalah periode `jadwal_periode` mulai `periode_mulai_id` pada `jadwal_snapshot` terbaru (§2.17). Status `belum_diisi` (nonmanual: ada komponen `jadwal_snapshot_komponen` yang belum bernilai; manual: nilai belum diisi) dan `tidak_dapat_dihitung` (mis. penyebut nol) sama-sama menolak; angka `0` sah. Ini adalah gerbang kelengkapan, bukan sekadar catatan peringatan.

**Pengecualian target periode kosong:** pada indikator dinilai tahunan menurut snapshot (`jadwal_snapshot.dinilai_tahunan`, §2.17), periode sebelum periode nilai akhir boleh tanpa target — seluruh target periode itu kosong, berarti **tidak ditargetkan** dan berbeda dari `0`; target periode nilai akhir tetap wajib dan direkonsiliasi dengan target PK (Q47 §47.2 butir 1 dan 3). Target periode lampau 2026 juga boleh kosong (Q46 §46.1 butir 6, Q47 §47.2 butir 4). Pada indikator lain, target kosong tetap menolak pengajuan per periode (Q47 §47.2 butir 2).

**Gerbang kegiatan saat pengajuan (Q42; butir (a) diterapkan oleh ISS-06.02, butir (b) diterapkan oleh ISS-06.08):** pengajuan rencana aksi juga **ditolak** bila (a) tidak ada minimal satu kegiatan yang diklaim pada tahap RA (`klaim_kegiatan.sumber_klaim = rencana_aksi`, §2.26) — tidak setiap periode wajib berisi kegiatan; atau (b) ada `jenis_berkas` aktif bertanda `wajib` bertahap `kegiatan_perencanaan` (mis. RAB untuk kegiatan beranggaran lebih dari 0) yang belum terpenuhi untuk salah satu kegiatan yang diklaim tahap RA (§2.29). RAB (Excel atau PDF) hanya diwajibkan untuk kegiatan beranggaran lebih dari 0 (Q42 §42.3 butir 1): persyaratan yang bertanda `jenis_berkas.hanya_kegiatan_beranggaran = true` hanya diperiksa untuk kegiatan dengan `anggaran > 0`, sedangkan persyaratan lain pada tahap yang sama diperiksa untuk setiap kegiatan (Q42 §42.3 butir 3, §2.29). Persyaratan yang diperiksa saat rencana aksi indikator X diajukan adalah persyaratan global (`jenis_berkas.indikator_id = null`) ditambah persyaratan khusus indikator X; untuk kegiatan bersama, persyaratan khusus indikator lain diperiksa saat rencana aksi indikator tersebut diajukan (Q42 §42.4 butir 4). Gerbang ini dikecualikan untuk kegiatan pada periode lampau 2026 dan untuk RA 2026 yang dicatat Perencanaan sebagai data awal (Q46 §46.1). Butir (b) juga menolak kegiatan yang anggarannya belum diisi (data lama) dengan pesan "anggaran belum diisi", dengan pengecualian yang sama (asumsi kerja ISS-06.08, menunggu persetujuan PM). Tanpa izin baca kegiatan pada unit, pesan penolakan hanya menyebut jumlah kekurangan tanpa nama kegiatan.

**Pemisahan tugas pada jalur verifikasi/pengesahan:** aturan yang sama seperti pengukuran (§2.20) berlaku di sini — bila rencana aksi diajukan lewat jalur PIC ber-scope unit, aktor pengaju tidak boleh melanjutkan sendiri transisi verifikasi/pengesahannya. Rinciannya di §4.

**PIC rencana aksi = PIC indikator:** tidak ada mekanisme penugasan terpisah untuk rencana aksi. `penanggung_jawab_id` mencatat PIC yang berlaku **pada saat penyusunan** (jejak historis); bila PIC indikator berganti di tengah tahun, hak pengisian rencana aksi tetap mengikuti `penanggung_jawab` yang berlaku saat itu (lihat §2.19), bukan nilai `penanggung_jawab_id` yang sudah tersimpan — dan tetap disyaratkan memiliki grant izin yang sesuai (§2.7).

---

### 2.24 `rencana_aksi_target`

Target per periode di bawah satu `rencana_aksi`. Indikator nonmanual diinput per komponen (angka mentah), bukan sebagai skor final indikator; indikator manual memiliki satu target langsung per periode tanpa komponen semu (Q7, Q34, ADR-0007).

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `rencana_aksi_id` | uuid | FK → rencana_aksi.id, not null | |
| `periode_id` | uuid | FK → periode.id, not null | |
| `komponen_id` | uuid | FK → indikator_komponen.id, **nullable** | `NULL` wajib untuk indikator manual; wajib terisi komponen efektif untuk indikator nonmanual |
| `nilai` | numeric | **nullable** | `0` sah; `null` = belum diisi. Pada periode yang boleh tanpa target — periode sebelum periode nilai akhir pada indikator dinilai tahunan, atau periode lampau 2026 — `null` berarti **tidak ditargetkan** dan berbeda dari `0` (Q47 §47.2; label UI Rencana Aksi "Tidak ditargetkan"; pengukuran "Tanpa target periode"; §2.23) |
| `keterangan` | text | nullable | |
| `updated_by` | uuid | FK → users.id | |
| `updated_at` | timestamp | not null | |

**Constraint:** satu unique index `ra_target_unik (rencana_aksi_id, periode_id, komponen_id) NULLS NOT DISTINCT` — manual: tepat satu baris `komponen_id IS NULL` per periode; nonmanual: unik per komponen. Menggantikan dua partial unique index semula (ADR-0007 butir 2, amandemen 10 Oktober 2026); membutuhkan PostgreSQL ≥ 15.

**Sifat nilai turunan:** perkiraan skor indikator pada tampilan rencana aksi dihitung dari nilai komponen memakai mesin perhitungan yang sama dengan pengukuran (lihat §2.28) — perkiraan itu tidak disimpan sebagai kolom, murni hasil tampilan.

**Sifat kumulatif:** target triwulan bersifat **kumulatif** — nilai suatu periode mencakup capaian periode-periode sebelumnya dalam tahun yang sama (mis. target Triwulan II = target kumulatif Januari–Juni, bukan hanya April–Juni). Sistem menampilkan **peringatan, bukan blokir**, bila nilai suatu periode lebih kecil dari periode sebelumnya pada komponen yang sama.

**Kunci per periode (Q41):** baris target pada periode yang sudah terkunci tidak dapat diubah PIC; Perencanaan dikecualikan sampai `penutupan` (lihat §2.16, §2.23).

**Rekonsiliasi dengan target tahunan PK:** total target komponen pada periode terakhir seharusnya setara dengan hasil hitung target `target_tahunan` tahun tersebut. Ketidaksetaraan **tidak memblokir** pengajuan — sistem menampilkan peringatan dan **mewajibkan alasan** pada saat pengajuan rencana aksi, disimpan di `rencana_aksi.alasan_deviasi_pk` (§2.23). Penyimpanan draf tidak mewajibkan alasan. Target PK acuan rekonsiliasi dibaca dari snapshot; setelah snapshot terbit, target itu hanya berubah lewat versi snapshot baru dari revisi PK berversi (§2.13; Q40, rancangan §2.36–§2.37) atau koreksi snapshot (Q35), bukan lewat penyesuaian rencana aksi; deviasi pada rencana aksi wajib terlihat di layar dan tercatat di audit, tidak disesuaikan secara diam-diam. Revisi PK tidak mengubah rencana aksi secara otomatis: selisih target periode terakhir dengan target PK baru tampil sebagai deviasi yang wajib dijelaskan, dan target periode yang belum terkunci disesuaikan lewat buka-kembali (ISS-05.06; Q40 §40.1).

---

### 2.25 `kegiatan`

Aktivitas konkret yang dilaksanakan unit pada suatu periode, sebagai bukti pendukung capaian indikator. Tidak dimiliki oleh satu rencana aksi/indikator secara langsung — keterkaitannya dengan rencana aksi dan komponen dinyatakan lewat `klaim_kegiatan` (§2.26).

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `unit_id` | uuid | FK → unit.id, not null | |
| `tahun` | int | not null | |
| `periode_id` | uuid | FK → periode.id, not null | Periode **rencana** pelaksanaan |
| `nama` | string | not null; hingga 500 karakter (Q49 §49.1 butir 1) | Status implementasi: diterapkan oleh PR #97 (migrasi `2026_10_14_000001`, validasi request, dan form) (Q49 §49.1 butir 3) |
| `tujuan` | text | **nullable** sejak Q49 (semula not null) | Opsional (Q49 §49.1 butir 2); status implementasi sama dengan `nama` |
| `sasaran_peserta` | int | nullable | Target jumlah peserta |
| `keterangan_peserta` | varchar | nullable | mis. "tim penyusun SPMI dari 30 PTS" |
| `lokasi` | varchar | nullable | |
| `tanggal_rencana` | date | nullable | |
| `tanggal_realisasi` | date | nullable | Wajib saat kegiatan ditandai `terlaksana`; harus berada di dalam rentang tanggal periode kegiatan pada tahun kegiatan (Q74 §74.2 butir 3, Q70 §70.1 butir 3). Status implementasi: ISS-06.03 |
| `biaya_terpakai` | numeric | nullable; nonnegatif (`>= 0`) | **Rupiah, Q74 §74.2:** satu angka per kegiatan, wajib saat kegiatan ditandai `terlaksana`; `0` sah dan nilai di atas `anggaran` tidak ditolak. `null` selama kegiatan belum dicatat terlaksana. Tidak dipakai dalam penjumlahan anggaran (§2.26; Q74 §74.2 butir 6). Status implementasi: kolom, CHECK `kegiatan_biaya_terpakai_nonnegatif`, dan validasi bilangan bulat 0 sampai 15 digit diterapkan ISS-06.03 |
| `anggaran` | numeric | nullable hanya untuk data lama, data awal 2026, dan periode lampau 2026; nonnegatif (`>= 0`) | **Rupiah, wajib sejak Q42:** diisi saat kegiatan dibuat atau diubah; `0` sah dan kegiatan beranggaran nol tetap dapat diklaim; `null` hanya pada data sebelum Q42 dan diperlakukan sebagai belum diisi, tidak dihitung sebagai `0` (Q42 §42.2 butir 1–2). Kegiatan data awal 2026 dan kegiatan pada periode lampau juga boleh tanpa anggaran (tercatat belum diisi), karena Rencana Aksi resmi hanya memuat anggaran per Rincian Output (Q46 §46.1 butir 3). Status implementasi: input dan validasi (bilangan bulat 0 sampai 15 digit) serta CHECK `kegiatan_anggaran_nonnegatif` diterapkan ISS-06.08; penjumlahan diterapkan ISS-06.08 (§2.26). Semula: kolom tersedia tanpa jalur pengisian sampai fase lanjutan (digantikan Q42) |
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

**Kegiatan tidak dihapus saat tidak terlaksana:** kegiatan yang batal berjalan **tidak dihapus** — statusnya diubah menjadi `tidak_terlaksana` (atau `ditunda`/`batal`) **dengan `justifikasi` wajib diisi**. Bila kegiatan digeser ke periode berikutnya, dibuat **baris kegiatan baru** pada periode tujuan dengan `kegiatan_asal_id` menunjuk kegiatan asal — konsekuensi yang disadari: kegiatan yang sama tampil di dua periode (periode asal + periode tujuan), keduanya sah, dibedakan oleh status dan tautan `kegiatan_asal_id`. Kegiatan lanjutan diklaim ke rencana aksi pada tahap pengukuran (`sumber_klaim = pengukuran`, §2.26); rencana aksi yang sudah disahkan tidak dibuka kembali hanya untuk memindahkan kegiatan (Q41 §41.4). Kegiatan lanjutan hanya dapat ditujukan ke periode lain pada tahun yang sama; kegiatan yang mundur ke tahun berikutnya dicatat sebagai kegiatan baru pada rencana aksi tahun itu. Berkas kegiatan asal, termasuk RAB, tidak disalin ke kegiatan lanjutan (Q41 §41.4 butir 5).

**Kegiatan dari halaman Rencana Aksi dan salin kegiatan (Q42 §42.1, Q49 §49.2; diterapkan oleh ISS-06.02, sedangkan anggaran dan RAB mengikuti ISS-06.08):** kegiatan ditambahkan dari halaman rencana aksi per periode; aksi ini membuat baris `kegiatan` (per unit × periode) sekaligus klaimnya dengan `sumber_klaim = rencana_aksi` (§2.26). Saat menambah, PIC dapat menyalinnya ke satu atau lebih periode lain pada rencana aksi yang sama: setiap salinan menjadi baris `kegiatan` tersendiri dengan klaim sendiri; `nama`, `tujuan`, dan `anggaran` ikut disalin dan dapat diubah per kegiatan, sedangkan berkas (termasuk RAB) tidak ikut disalin. Salinan hanya dapat dibuat untuk periode yang belum terkunci (Q41 §41.2) dan tidak memakai `kegiatan_asal_id`, yang khusus menandai kegiatan lanjutan hasil geser periode.

**Anggaran dan RAB (Q42; RAB, gerbangnya, dan pembatasan tampilan anggaran per kegiatan diterapkan ISS-06.08, tampilan dashboard per IKU mengikuti ISS-10.01):** kegiatan beranggaran lebih dari 0 wajib dilengkapi RAB (Excel atau PDF) sebagai `berkas` induk `kegiatan` pada persyaratan tahap `kegiatan_perencanaan`, yang diperiksa saat rencana aksi diajukan (§2.23, §2.29); aplikasi tidak membuat template RAB (Q42 §42.3). Rincian realisasi belanja, pagu induk, dan rekonsiliasi anggaran tidak dikelola (Q42 §42.2 butir 3). Anggaran per kegiatan dan berkas RAB mengikuti akses detail kegiatan yang dibatasi unit (Q12, Q49 §49.3); Perencanaan dan Pimpinan dapat melihat seluruhnya, dan total anggaran per IKU pada dashboard mengikuti akses dashboard (Q42 §42.5). Penjumlahan anggaran: §2.26.

**Narasi per kegiatan (diperbarui Q45 §45.1):** `uraian_pelaksanaan`, `kendala`, `strategi_tindaklanjut` tetap tersedia per kegiatan dan bersifat **opsional**. Narasi resmi per indikator × periode diisi pada pengukuran (`progres_kegiatan`, `kendala_masalah`, `strategi_tindak_lanjut`, §2.20); rekapitulasi menampilkan narasi pengukuran bersama daftar kegiatan yang diklaim (lihat §2.26). Ketentuan lama — kolom "Progress Kegiatan / Kendala dan Masalah / Strategi tindaklanjut" pada rekapitulasi indikator × periode dihasilkan otomatis dari narasi kegiatan tanpa narasi di level indikator — digantikan Q45.

**Hak ubah:** kegiatan dapat diperbarui (status, realisasi, narasi) oleh PIC unitnya (izin scope unit lewat grant, §2.7) atau Perencanaan (izin global lewat peran, §2.5) selama `jadwal_tahunan` belum `penutupan`; setelah penutupan hanya lewat `jadwal:buka_kembali` (§2.15).

**Gerbang kelengkapan bukti pelaksanaan (SPJ):** transisi status `rencana → terlaksana` **ditolak** bila ada `jenis_berkas` aktif bertanda `wajib` bertahap `kegiatan` (tahap pelaksanaan kegiatan, Q42 §42.4 butir 2) yang berlaku bagi kegiatan ini namun belum terpenuhi (lihat §2.29–§2.30). Persyaratan tahap `kegiatan_perencanaan` tidak diperiksa di sini, melainkan saat rencana aksi diajukan (§2.23). Transisi ke `tidak_terlaksana`/`ditunda`/`batal` tidak melalui gerbang ini — syaratnya tetap `justifikasi` wajib di atas. Setelah kegiatan berstatus `terlaksana`, `berkas` yang menempel padanya menjadi imutabel (§2.30).

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
| `sumber_klaim` | enum(`rencana_aksi`,`pengukuran`) | not null | Mencatat tahap saat klaim dibuat — saat penyusunan rencana aksi atau saat pengisian pengukuran; keduanya menulis ke tabel yang sama. Sejak Q42, klaim `rencana_aksi` dibuat bersamaan dengan kegiatan yang ditambahkan dari halaman rencana aksi (Q42 §42.1 butir 2); kegiatan lanjutan hasil geser periode diklaim `pengukuran` (Q41 §41.4), dan persyaratan tahap `kegiatan_perencanaan` tidak diperiksa ulang untuk kegiatan lanjutan tersebut (Q41 §41.4 butir 5) |
| `pengukuran_id` | uuid | FK → pengukuran.id, nullable | Diisi bila klaim dilakukan pada tahap pengukuran |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |

**Constraint:** `unique(rencana_aksi_id, kegiatan_id, komponen_id)`. Karena PostgreSQL memperlakukan `NULL` sebagai nilai berbeda pada setiap baris (dua baris dengan `komponen_id = NULL` tidak dianggap duplikat oleh unique index standar), implementasinya memakai **`COALESCE(komponen_id, '00000000-0000-0000-0000-000000000000'::uuid)`** (atau nilai sentinel setara) pada definisi index unik, agar klaim tanpa komponen tertentu tetap tunduk pada aturan keunikan per (`rencana_aksi_id`, `kegiatan_id`).

**Klaim tidak mengubah nilai komponen secara otomatis:** nilai `pengukuran_komponen`/`rencana_aksi_target` tetap diisi manual oleh PIC. Menjumlahkan otomatis dari klaim berisiko penghitungan ganda — mis. satu PTS yang hadir di tiga kegiatan tetap dihitung satu kali pada komponen "jumlah PTS yang menerima fasilitasi". Fungsi klaim murni: (a) dokumentasi dukungan & arah dampak, (b) bahan rekapitulasi otomatis (§2.32), (c) pengingat di layar pengisian pengukuran — daftar kegiatan terkait periode itu ditampilkan sebagai pembanding saat PIC mengisi angka komponen.

**Validasi unit:** `kegiatan.unit_id` harus sama dengan unit `rencana_aksi`/indikator yang diklaim; klaim lintas unit **ditolak sistem**.

**Hak hapus:** klaim dapat dihapus oleh PIC pembuat klaim atau Perencanaan, selama induknya (`rencana_aksi`) berstatus draf atau dikembalikan (klaim ikut dibekukan saat pengajuan; keputusan PM 16 Oktober 2026 pada PR #111); penghapusan tercatat di `audit_log`. PIC tidak dapat menambah atau menghapus klaim tahap RA atas kegiatan pada periode yang sudah terkunci; Perencanaan dikecualikan sampai `penutupan` (Q41 §41.2, §2.16).

**Penjumlahan anggaran (Q42 §42.2 butir 4–6; diterapkan ISS-06.08, tampilan dashboard per IKU mengikuti ISS-10.01):** anggaran kegiatan yang diklaim dijumlahkan otomatis per periode, per rencana aksi, dan per IKU. Kegiatan yang diklaim pada lebih dari satu rencana aksi/IKU ditampilkan penuh di setiap IKU dengan penanda **"kegiatan bersama"**, sedangkan total per unit dan per tahun dihitung dari **kegiatan unik** agar tidak terhitung ganda. Penanda dan total adalah nilai turunan tampilan, tidak disimpan. Kegiatan dengan `anggaran` kosong (data lama, data awal 2026, atau periode lampau 2026; Q46 §46.1 butir 3) tidak dihitung sebagai `0`, dan total menandai keberadaannya. Kegiatan lanjutan hasil geser periode (`kegiatan_asal_id`) menyalin `anggaran` kegiatan asal dan dapat diubah; kegiatan asal berstatus `ditunda` tidak dihitung dalam total setelah lanjutannya diklaim (Q69 §69.6), sehingga anggarannya hanya terhitung pada kegiatan lanjutan. Total yang sudah dibekukan pada `rencana_aksi_versi` tidak berubah (Q42 §42.2 butir 6).

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

**Pengelolaan:** permission granular **`komponen:create`**, **`komponen:read`**, **`komponen:update`**, **`komponen:delete`**. Preset lima role lama tetap mengikuti §2.4: Pegawai/Pimpinan/Admin terbatas pada `komponen:read`, sedangkan `update`/`delete` (bertipe `sensitif`, lihat §2.3) hanya dipegang Perencanaan dan Superadmin. **Hak bawaan PIC operasional (bukan role) atas `komponen:read` masih OPEN pada Q31 dan tidak boleh diasumsikan dari role Pegawai.** Definisi komponen dapat diubah/ditambah lewat aplikasi — mis. saat Kepmen IKU dari kementerian pusat berubah — tanpa memerlukan penyesuaian kode/hardcode. Perubahan (tambah/ubah/nonaktifkan) tercatat di `audit_log` dengan `nilai_lama`/`nilai_baru`.

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
- **Pembagian nol:** bila `penyebut = 0` pada `rasio_persen`, nilai **tidak dapat dihitung** — disimpan sebagai `null` pada `pengukuran.nilai` dengan `status_perhitungan = tidak_dapat_dihitung` (§2.20) dan ditampilkan sebagai teks **"tidak dapat dihitung"**, bukan `0` dan bukan galat sistem. Status ini berbeda dari `belum_dapat_diukur` (rancangan Q47).
- **Pembulatan:** nilai hasil hitung disimpan sesuai `indikator.presisi` dan ditampilkan sesuai `indikator.desimal_tampilan` (kolom lama, tetap berlaku tanpa perubahan makna).
- **Kelengkapan komponen sebagai syarat pengajuan:** lihat §2.20 — pengajuan pengukuran ditolak bila ada komponen aktif bernilai `null`.
- **Satu tingkat perhitungan:** mesin ini hanya mendukung satu tingkat (rasio atau penjumlahan, dengan suku berjumlah banyak dan berbobot). Formula **bertingkat** (sub-skor → nilai komposit → digabung lagi) **tidak dibangun** — indikator seperti Predikat SAKIP dan Nilai Zona Integritas (yang secara konseptual tersusun dari sub-skor 30/30/15/25) memakai `tipe_perhitungan = manual`, nilainya diketik langsung sebagai angka akhir tanpa diturunkan dari sub-skornya. Kebutuhan formula bertingkat di masa depan adalah pengembangan lanjutan, bukan sesuatu yang dapat dikonfigurasi dari layar definisi komponen.

---

### 2.29 `jenis_berkas`

Katalog persyaratan bukti dukung yang ditetapkan **Tim Perencanaan**, per tahap dan (opsional) per indikator. Substansi persyaratan — nama, tahap, wajib/opsional, mode bukti yang diizinkan, `semua_mode_wajib` — serta format dan ukuran maksimum per persyaratan adalah wewenang Perencanaan (Q44 §44.1 butir 1). Sejak Q44, saklar unggahan dihapus dan format/ukuran default beserta batas atas ukuran merupakan batas teknis dari konfigurasi server (§2.22); ketentuan lama yang menempatkan saklar unggahan dan format/ukuran default sebagai wewenang pemegang `pengaturan:update` (Admin/Superadmin) digantikan Q44.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `nama` | string | not null | |
| `tahap` | enum(`rencana_aksi`,`pengukuran`,**`kegiatan`**,**`kegiatan_perencanaan`**) | not null | Tahap kepatuhan yang mensyaratkan bukti ini; `kegiatan` menaungi persyaratan pelaksanaan kegiatan (SPJ, mis. undangan, dokumentasi, SP). **Nilai `kegiatan_perencanaan` (Q42, diterapkan ISS-06.08):** persyaratan tahap perencanaan kegiatan (mis. RAB), berlaku per kegiatan dan diperiksa saat rencana aksi diajukan untuk setiap kegiatan yang diklaim tahap RA (Q42 §42.4) |
| `indikator_id` | uuid | FK → indikator.id, **nullable** | `null` = berlaku untuk semua indikator |
| `wajib` | boolean | not null, default `false` | |
| `hanya_kegiatan_beranggaran` | boolean | not null, default `false` | **Q42, diterapkan ISS-06.08.** Hanya bermakna pada tahap `kegiatan_perencanaan`: `true` = persyaratan hanya diperiksa untuk kegiatan dengan `anggaran > 0` (dipakai persyaratan RAB); `false` = berlaku untuk setiap kegiatan yang diklaim tahap RA (Q42 §42.3 butir 3) |
| `keterangan` | text | nullable | |
| `izinkan_file` | boolean | not null, default `true` | Mode unggahan file diizinkan untuk persyaratan ini |
| `izinkan_tautan` | boolean | not null, default `false` | Mode tautan (URL) diizinkan |
| `izinkan_teks` | boolean | not null, default `false` | Mode keterangan teks diizinkan |
| `semua_mode_wajib` | boolean | not null, default `false` | `false` = cukup **minimal satu** mode yang diizinkan terisi; `true` = **seluruh** mode yang diizinkan wajib terisi |
| `urutan` | int | not null, default `0` | Urutan tampil pada daftar persyaratan |
| `format_diizinkan` | varchar | nullable | mis. `pdf,docx,xlsx,jpg,png`; hanya bermakna bila `izinkan_file = true`; kosong = default batas teknis konfigurasi server `pdf,docx,xlsx,jpg,jpeg,png` (Q44 §44.2 butir 1; semula default dari `pengaturan`, digantikan Q44) |
| `ukuran_maks_kb` | int | nullable; paling besar 102400 (validasi aplikasi, Q44) | Hanya bermakna bila `izinkan_file = true`; kosong = default batas teknis konfigurasi server 10240 KB (10 MB). Tidak boleh melampaui batas atas konfigurasi server 102400 KB (100 MB), divalidasi saat persyaratan disimpan dan saat unggah (Q44 §44.2 butir 1; semula default dari `pengaturan`, digantikan Q44) |
| `aktif` | boolean | not null, default `true` | |
| `created_by` | uuid | FK → users.id | |
| `created_at` | timestamp | not null | |
| `updated_at` | timestamp | not null | |

**Pengelolaan:** permission granular **`jenis_berkas:create`**, **`jenis_berkas:read`**, **`jenis_berkas:update`**, **`jenis_berkas:delete`**. Preset lima role lama tetap mengikuti §2.4: Pegawai/Pimpinan/Admin terbatas pada `jenis_berkas:read`, sedangkan `update`/`delete` (bertipe `sensitif`, lihat §2.3) hanya dipegang Perencanaan dan Superadmin. **Hak bawaan PIC operasional (bukan role) atas `jenis_berkas:read` masih OPEN pada Q31.** `tahap` adalah pilihan Perencanaan saat menetapkan persyaratan — berkas bertahap `rencana_aksi` wajib dilampirkan saat penyusunan/pengajuan rencana aksi; berkas bertahap `pengukuran` wajib dilampirkan saat pengisian/pengajuan pengukuran; berkas bertahap `kegiatan` wajib dilampirkan saat kegiatan dinyatakan `terlaksana` (lihat §2.25, §2.30); berkas bertahap `kegiatan_perencanaan` (Q42) wajib dilampirkan pada setiap kegiatan yang diklaim tahap RA sebelum rencana aksi diajukan (§2.23). Satu indikator dapat memiliki persyaratan pada keempat tahap sekaligus (semula tiga tahap, sebelum Q42). Perubahan persyaratan tetap teraudit, tetapi alasannya opsional (Q43 §43.3 butir 3).

**Tiga mode bukti dukung yang dikenal sistem:** `file` (unggahan langsung), `tautan` (URL/link ke dokumen yang disimpan di tempat lain), `teks` (keterangan tertulis). Satu persyaratan dapat mengizinkan lebih dari satu mode sekaligus; PIC memilih di antara mode yang diizinkan saat memenuhi persyaratan (lihat §2.30). Tidak ada daftar mode yang di-hardcode per jenis persyaratan — keleluasaan ini memberi ruang operasional sekaligus menjaga storage, karena persyaratan yang tidak memerlukan arsip fisik cukup diminta sebagai tautan atau teks.

**Validasi saat penyimpanan (Perencanaan):**
- Minimal satu dari `izinkan_file`/`izinkan_tautan`/`izinkan_teks` bernilai `true` — penyimpanan tanpa satu pun mode aktif **ditolak sistem**.
- `format_diizinkan` dan `ukuran_maks_kb` hanya bermakna bila `izinkan_file = true`; nilainya tetap tersimpan bila diisi, tetapi tidak dipakai ketika mode file tidak diizinkan.
- `ukuran_maks_kb` di atas 102400 (batas atas 100 MB konfigurasi server) **ditolak sistem** (Q44 §44.2 butir 1).
- *(Dihapus Q44 §44.1 butir 2; histori)* Semula: bila `wajib = true` dan hanya mode `file` yang diizinkan sementara `berkas.unggahan_aktif` bernilai `false` pada setelan aplikasi, sistem menampilkan peringatan saat penyimpanan bahwa persyaratan berpotensi tidak dapat dipenuhi PIC. Sejak saklar unggahan dihapus, peringatan ini tidak lagi muncul.

**Gerbang kelengkapan:**
- **Rencana aksi & pengukuran:** transisi `draft → diajukan` **ditolak** bila ada `jenis_berkas` aktif bertanda `wajib` pada tahap tersebut yang belum terpenuhi untuk induknya — "terpenuhi" dinilai sesuai mode (file terunggah, tautan terisi, atau teks terisi). Pengecualian penanda `tidak_dapat_dipenuhi` akibat unggahan dimatikan tidak lagi terjadi sejak Q44 (histori, §2.30).
- **Perencanaan kegiatan (Q42, diterapkan ISS-06.08):** saat rencana aksi diajukan, setiap kegiatan yang diklaim tahap RA diperiksa terhadap persyaratan `wajib` bertahap `kegiatan_perencanaan` yang berlaku baginya (berkas induk `kegiatan`), termasuk RAB untuk kegiatan beranggaran lebih dari 0; pengajuan ditolak bila ada yang belum terpenuhi (§2.23). Persyaratan yang berlaku saat rencana aksi indikator X diajukan = persyaratan global (`indikator_id = null`) + persyaratan khusus indikator X; kegiatan bersama diperiksa terhadap persyaratan khusus indikator lain saat rencana aksi indikator tersebut diajukan (Q42 §42.4 butir 4). Dikecualikan untuk periode lampau 2026 dan RA 2026 yang dicatat sebagai data awal (Q46 §46.1).
- **Kegiatan:** transisi status `rencana → terlaksana` **ditolak** bila ada `jenis_berkas` aktif bertanda `wajib` bertahap `kegiatan` yang belum terpenuhi untuk kegiatan tersebut (lihat §2.25). Transisi ke `tidak_terlaksana`/`ditunda`/`batal` **tidak** memerlukan bukti pelaksanaan — syaratnya tetap `justifikasi` wajib, karena kegiatan yang gagal justru tidak memiliki SPJ.
- Pada setiap gerbang di atas, aturan `semua_mode_wajib` dihormati: bila `true`, seluruh mode yang diizinkan pada persyaratan itu harus terpenuhi; bila `false`, cukup minimal satu mode.

**Contoh berkas (Q42 §42.3 butir 2 dan §42.4 butir 3; diterapkan ISS-06.08):** persyaratan dapat dilampiri contoh berkas generik (mis. contoh RAB) sebagai `berkas` dengan induk `jenis_berkas` (§2.30). Contoh diunggah Perencanaan dan dapat diunduh PIC yang memenuhi persyaratan tersebut; contoh berkas bukan bukti pemenuhan persyaratan. Contoh berkas hanya tersedia untuk persyaratan tahap `kegiatan_perencanaan`, unggah/hapusnya memakai `jenis_berkas:update`, unduhnya memakai `jenis_berkas:read`, dan contoh ikut dihapus lunak saat persyaratannya dihapus.

---

### 2.30 `berkas`

Wadah bukti dukung, polimorfik terhadap **enam** jenis induk: `rencana_aksi`, `pengukuran`,
`kegiatan`, `renstra`, `renstra_pk`, dan `regulasi`. Mendukung tiga mode pengiriman bukti —
file, tautan, teks — sesuai mode yang diizinkan pada `jenis_berkas` terkait (§2.29) untuk
tiga induk pertama; untuk `renstra`/`renstra_pk`/`regulasi`, mode bebas dipilih pengunggah
tanpa persyaratan bergerbang dari `jenis_berkas`. Q40, Q42, dan Q48 menambah **empat induk
rancangan** (belum diimplementasikan): `jenis_berkas` (contoh berkas, Q42), `jadwal_tahunan`
(arsip Rencana Aksi tahunan, Q48), `jadwal_periode` (arsip Laporan Kinerja Triwulan, Q48), dan
`renstra_pk_versi` (lampiran dokumen PK per versi, Q40; tahap 3).

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `jenis_berkas_id` | uuid | FK → jenis_berkas.id, **nullable** | **-- (kolom tersedia, nullable secara permanen)**. `null` = lampiran bebas di luar daftar persyaratan bergerbang, boleh memakai mode apa pun. Selalu `null` untuk `berkasable_type = renstra`/`renstra_pk`/`regulasi`/`jenis_berkas` dan ketiga induk rancangan, karena induk-induk ini tidak memiliki katalog persyaratan bergerbang |
| `berkasable_type` | varchar | not null | Tujuh nilai: `rencana_aksi` / `pengukuran` / `kegiatan` / `renstra` / `renstra_pk` / `regulasi` / `jenis_berkas` (Q42, diterapkan ISS-06.08); ditambah tiga nilai rancangan: `jadwal_tahunan` / `jadwal_periode` (Q48) / `renstra_pk_versi` (Q40) |
| `berkasable_id` | uuid | not null | |
| `menggantikan_id` | uuid | FK → berkas.id, nullable | Berkas lama yang digantikan baris ini pada koreksi append-only (Q25); berkas lama tetap utuh. Kolom sudah ada di aplikasi |
| `alasan_koreksi` | text | nullable | Alasan atau keterangan penggantian, diisi pada baris pengganti. Kolom sudah ada di aplikasi |
| `keterangan` | text | nullable | Keterangan isi berkas yang bukan pengganti, mis. "Rencana Aksi perubahan" atau "PK perubahan anggaran" (Q48 §48.2 butir 6, Q50 §50.7). Pada lampiran PK (induk `renstra_pk`) opsional, paling banyak 1.000 karakter, diisi saat unggah, tidak dapat diubah setelah unggah, dan tercatat pada audit `berkas.unggah` (Q65 §65.3); status implementasi: sudah (ISS-02.11, migrasi `2026_10_19_000003`). Pemakaian untuk induk lain ditetapkan saat ISS-10.05 |
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
Perjanjian Kinerja, salinan produk hukum) dalam mode apa pun. Keempat induk rancangan juga
tidak memiliki katalog persyaratan; aturannya dijabarkan pada paragraf arsip dan contoh
berkas di bawah serta §2.29 dan §2.36.

**Banyak baris per persyaratan:** satu persyaratan (`jenis_berkas`) dapat dipenuhi oleh **lebih dari satu baris** `berkas` — mis. laporan sebagai file dan dokumentasi sebagai tautan pada persyaratan yang sama — terutama bila `semua_mode_wajib = true` pada persyaratan tersebut. Pola yang sama berlaku bagi lampiran bebas pada `renstra`/`renstra_pk`/`regulasi` dan induk rancangan — satu induk dapat memiliki banyak baris `berkas` tanpa batas jumlah, mis. Rencana Aksi tahunan awal dan perubahan pada satu `jadwal_tahunan` (Q48 §48.2 butir 1).

**Validasi mode:** mode yang dipilih PIC **harus** termasuk salah satu mode yang diizinkan (`izinkan_file`/`izinkan_tautan`/`izinkan_teks`) pada `jenis_berkas` terkait; permintaan dengan mode di luar daftar **ditolak sistem**. Lampiran bebas (`jenis_berkas_id = null`, termasuk seluruh lampiran pada `renstra`/`renstra_pk`/`regulasi`) dikecualikan dari validasi ini dan boleh memakai mode apa pun.

**Penanda `tidak_dapat_dipenuhi` (histori, digantikan Q44 §44.1 butir 2):** sejak saklar unggahan dihapus, unggahan file selalu aktif sehingga penanda ini tidak lagi dibuat; penanda yang sudah tercatat di `audit_log` tetap tampil sebagai histori. Semula: bila `berkas.unggahan_aktif` bernilai `false` pada setelan aplikasi sementara suatu persyaratan hanya mengizinkan mode `file`, persyaratan itu ditandai `tidak_dapat_dipenuhi` pada tampilan kerja, tidak memblokir gerbang kelengkapan (§2.29) maupun gerbang lampiran PK (§2.15 gerbang keempat), tercatat di `audit_log`, dan tampil pada rekapitulasi serta halaman kerja Perencanaan.

**Imutabilitas — mengikuti status induk (per induk berbeda):**
- lampiran `renstra` **tidak dapat dihapus** setelah baris `renstra` yang bersangkutan berstatus `aktif`; lampiran baru (termasuk lampiran revisi) hanya dapat ditambahkan selama Renstra berstatus `draft` atau `aktif`, tidak pada Renstra `nonaktif`/`diarsipkan` (Q40 §40.2 butir 2 dan 5);
- lampiran `renstra_pk` **tidak dapat dihapus** setelah `jadwal_tahunan` tahun tersebut berstatus `aktif`;
- lampiran `regulasi` **tidak dapat dihapus** selama regulasi yang bersangkutan masih dirujuk oleh `renstra.regulasi_id` atau `indikator.regulasi_id` yang aktif;
- lampiran `kegiatan` **tidak dapat dihapus** setelah kegiatan yang bersangkutan berstatus `terlaksana`; *(Q42, diterapkan ISS-06.08)* berkas yang memenuhi persyaratan tahap `kegiatan_perencanaan` (mis. RAB) **tidak dapat dihapus maupun diganti** sejak rencana aksi yang mengklaim kegiatan itu diajukan — koreksi lewat pengembalian rencana aksi, lalu berkas pengganti (Q25), mengikuti pola bukti rencana aksi Q36 (Q42 §42.4 butir 5); unggahan berkas baru tetap diterima;
- lampiran `rencana_aksi`/`pengukuran` hanya dapat ditambah, diganti, atau dihapus selama induknya berstatus `draft`/`dikembalikan` (khusus pengukuran, tidak ada endpoint hapus; koreksi lewat pengembalian beralasan dan versi baru, Q36 §36.2); sejak `diajukan` **tidak dapat dihapus** (koreksi lewat pengembalian beralasan), dan imutabel setelah `disahkan` (Q36);
- *(rancangan Q48)* arsip pada `jadwal_tahunan`/`jadwal_periode` **tidak dapat dihapus** sejak diunggah; salah unggah dikoreksi dengan berkas pengganti (`menggantikan_id`, `alasan_koreksi`) mengikuti pola append-only Q25 (Q48 §48.2 butir 3);
- *(rancangan Q40)* lampiran `renstra_pk_versi` mengikuti versi PK yang tidak diubah setelah terbentuk (Q40 §40.1 butir 2); rinciannya ditetapkan bersama ISS-02.12;
- *(Q42, diterapkan ISS-06.08)* contoh berkas pada induk `jenis_berkas` bukan bukti: dapat ditambah, diganti, atau dihapus Perencanaan kapan saja; penghapusannya wajib alasan (Q43 §43.2 butir 2), dan contoh berkas tidak dibekukan ke versi pengajuan (Q42 §42.3 butir 4).

Sebelum batas di atas tercapai, Perencanaan (izin global lewat peran) dan pengunggah/PIC unit terkait (untuk `rencana_aksi`/`pengukuran`/`kegiatan`; hak mutasi turun dari izin induk ber-scope unit, mis. `rencana_aksi:update` atau `pengukuran:update`; `berkas:*` bukan permission unit-scoped dan hanya berfungsi sebagai gerbang deny, Q32.3) dapat menghapus lampiran dengan alasan (Q43 §43.2 butir 2); setiap penghapusan tercatat di `audit_log` (soft delete lewat `dihapus_pada`/`dihapus_oleh`). Setelah `jadwal_tahunan.penutupan`, koreksi atas lampiran `rencana_aksi`/`pengukuran`/`kegiatan` hanya lewat `jadwal:buka_kembali` — aturan ini tidak berlaku bagi lampiran `renstra`/`renstra_pk`/`regulasi`, yang batas imutabilitasnya murni mengikuti status induknya masing-masing seperti tercantum di atas, dan tidak berlaku bagi arsip pada `jadwal_tahunan`/`jadwal_periode`, yang tetap dapat diunggah setelah jadwal `ditutup` (Q48 §48.2 butir 5).

**Penyimpanan mode `file`:** berkas mode file disimpan di disk VPS (`storage/app/berkas/...`) dan diakses lewat route ber-permission (streamed download) — **bukan** URL publik; penyimpanan tetap privat (Q44 §44.1 butir 4). Untuk induk `rencana_aksi`/`pengukuran`/`kegiatan`, jenis dan ukuran file divalidasi terhadap `jenis_berkas.format_diizinkan`/`ukuran_maks_kb`. Bila persyaratan mengosongkan format atau ukuran, dan untuk lampiran tanpa persyaratan (`renstra`/`renstra_pk`/`regulasi`, lampiran bebas kegiatan, serta induk rancangan), berlaku default batas teknis konfigurasi server: 10 MB dan `pdf,docx,xlsx,jpg,jpeg,png`; tidak ada berkas yang boleh melampaui batas atas 100 MB (Q44 §44.2 butir 1). Ketentuan lama yang mengambil default dari kunci grup `berkas` pada `pengaturan` digantikan Q44 (§2.22).

**Arsip dokumen resmi (rancangan Q48 — belum diimplementasikan):** PDF bertanda tangan elektronik (mode `file`) yang diunggah kembali sebagai arsip resmi — Rencana Aksi tahunan pada induk `jadwal_tahunan` (boleh lebih dari satu berkas, mis. awal dan perubahan) dan Laporan Kinerja Triwulan pada induk `jadwal_periode`. Diunggah Perencanaan dengan `laporan:unggah` dan diunduh pemegang `laporan:ekspor`; PK tetap dilampirkan pada `renstra_pk` (per versi pada `renstra_pk_versi` setelah revisi berversi tersedia) dengan permission PK (Q48 §48.2 butir 1–2). Arsip append-only: tidak dihapus dan dikoreksi dengan berkas pengganti (Q48 §48.2 butir 3). Unggahan tidak mengubah data, status, maupun snapshot, tercatat di `audit_log`, dan tetap dapat dilakukan setelah jadwal tahunan `ditutup` karena penutupan hanya menghentikan mutasi data kinerja (Q48 §48.2 butir 4–5). Keterangan unggahan yang bukan pengganti (mis. "Rencana Aksi perubahan", atau lampiran tambahan PK karena perubahan anggaran, Q50 §50.7) disimpan pada kolom rancangan `berkas.keterangan`; `alasan_koreksi` tetap khusus untuk berkas pengganti (Q48 §48.2 butir 6). Arsip pada induk `jadwal_tahunan`/`jadwal_periode` hanya menerima berkas PDF, dengan batas ukuran mengikuti batas teknis konfigurasi server (Q44 §44.2; Q48 §48.2 butir 7).

**Lampiran bebas di level kegiatan:** lampiran pada `berkasable_type = kegiatan` tanpa `jenis_berkas_id` tersedia sebagai **lampiran bebas** (bukan persyaratan bergerbang) — bukti pelaksanaan kegiatan tambahan (daftar hadir, dokumentasi) menempel pada kegiatannya, boleh memakai mode apa pun, dan ikut tampil pada rekapitulasi indikator × periode.

**Lampiran dokumen dasar (`renstra`/`renstra_pk`/`regulasi`):** halaman Renstra menampilkan lampiran dokumen Renstra beserta rujukan `regulasi_id` dan ringkasan `dasar_hukum` (§2.9, §2.33); halaman Perjanjian Kinerja menampilkan lampiran dokumen PK beserta `nomor_pk`/`tanggal_pk` (§2.10), termasuk lampiran tambahan PK perubahan anggaran (Q50 §50.7); halaman "Dasar Aturan" menampilkan CRUD `regulasi` beserta lampirannya (§2.33). Ketiga jenis lampiran ini murni bersifat bebas (`jenis_berkas_id = null`) — tidak ada katalog persyaratan bergerbang untuk dokumen dasar, kecuali gerbang aktivasi jadwal yang mensyaratkan minimal satu lampiran `renstra_pk` (§2.15).

---

### 2.31 `rekomendasi_pimpinan`

Catatan rekomendasi/arahan atas capaian suatu indikator pada suatu periode. Sejak Q45 §45.2 dan Q51, Perencanaan menyusun **draf** dan Pimpinan **menyetujui** (boleh mengubah isinya dulu) atau menulis rekomendasi langsung; semula ditetapkan Perencanaan pada Fase Awal (digantikan Q45/Q51). Status implementasi: tabel, draf, persetujuan, dan pembacaan rekomendasi yang berlaku tersedia (ISS-09.01); gerbang tindak lanjut pada pengajuan pengukuran (ISS-07.03) dan Laporan Kinerja Triwulan (ISS-10.05) belum.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `indikator_id` | uuid | FK → indikator.id, not null | |
| `tahun` | int | not null | |
| `periode_id` | uuid | FK → periode.id, not null | |
| `isi` | text | not null | |
| `ditetapkan_oleh` | uuid | FK → users.id, not null | Penyusun baris: Perencanaan untuk draf, atau Pimpinan yang menulis rekomendasi langsung (Q51 §51.1 butir 1) |
| `status` | enum(`draf`,`disetujui`) | not null, default `draf` | Hanya `disetujui` yang berlaku; draf tidak tampil bagi PIC (Q51 §51.1 butir 2). Rekomendasi yang ditulis langsung oleh Pimpinan tersimpan langsung `disetujui`, demikian pula rekomendasi data awal 2026 yang dicatat Perencanaan (di bawah) |
| `disetujui_oleh` | uuid | FK → users.id, nullable | Penyetuju: Pimpinan, atau peran lain sebagai cadangan bila Pimpinan berhalangan — Perencanaan, termasuk Superadmin (Q51 §51.2 butir 2) |
| `disetujui_oleh_role` | varchar | nullable | Salinan kode peran (`roles.kode`) penyetuju saat persetujuan; nilai selain `pimpinan` (mis. `perencanaan` atau `superadmin`) menjadi penanda persetujuan cadangan, dan penanda ini juga tercatat di `audit_log` (Q51 §51.2 butir 2) |
| `disetujui_pada` | timestamp | nullable | Waktu persetujuan |
| `rujukan_sumber` | text | nullable | Rujukan dokumen sumber bagi rekomendasi yang dicatat dari dokumen resmi, mis. Laporan Kinerja Triwulan I 2026; diisi pada pencatatan data awal 2026 (Q51 §51.1 butir 5) |
| `versi` | int | not null, default 1 | Token konkurensi draf; dinaikkan setiap kali draf diubah atau disetujui (Q55 §55.1 butir 2) |
| `created_at` | timestamp | not null | |

**Data awal 2026 (Q51 §51.1 butir 5):** rekomendasi TW I–II dari laporan triwulan yang sudah ditandatangani Pimpinan dicatat Perencanaan langsung berstatus `disetujui`, dengan penanda persetujuan cadangan (`disetujui_oleh_role = perencanaan`) dan rujukan laporan sumbernya pada `rujukan_sumber`.

**Konsistensi (Q51, Q55):** `disetujui_oleh`, `disetujui_oleh_role`, dan `disetujui_pada` terisi jika dan hanya jika `status = disetujui` (CHECK `rekomendasi_persetujuan_cocok`). Isi baris `draf` dapat diubah sebelum disetujui (Q51 §51.1 butir 1) dan perubahannya tercatat di `audit_log`; baris `disetujui` tidak diubah maupun dihapus, ditegakkan trigger basis data. Paling banyak satu baris `draf` per (`indikator_id`, `tahun`, `periode_id`), ditegakkan unique index parsial (Q55 §55.1 butir 1). Penetapan pada kombinasi yang sama dijalankan satu per satu, dan permintaan yang membawa draf atau rekomendasi berlaku yang sudah diganti ditolak sebagai konflik versi (Q55 §55.1 butir 2–3).

**Pola "aktif" (diperbarui Q51):** rekomendasi yang berlaku per (`indikator_id`, `tahun`, `periode_id`) adalah baris berstatus `disetujui` dengan `disetujui_pada` terbaru; hanya baris itu yang tampil pada pengukuran periode berikutnya dan Laporan Kinerja Triwulan (Q48) serta memicu `pengukuran.tindak_lanjut_rekomendasi` pada periode berikutnya (§2.20; Q45 §45.2 butir 3, Q51 §51.1 butir 2). Kewajiban ini berlaku lintas tahun: rekomendasi pada periode terakhir suatu tahun ditindaklanjuti pada periode pertama jadwal tahun berikutnya bila indikatornya masih diukur (Q51 §51.1 butir 4). Perubahan atas rekomendasi yang sudah disetujui membentuk baris baru dan melalui persetujuan yang sama (Q51 §51.2 butir 3); baris lama tetap tersimpan sebagai riwayat (soft replace, tidak dihapus) — pola yang sama dengan `status_capaian` (§2.21). Versi pengajuan pengukuran membekukan rekomendasi yang tampil bersama tindak lanjutnya, sehingga persetujuan atau revisi sesudahnya tidak mengubah tampilan versi itu (Q55 §55.1 butir 5); pengajuan dari tampilan yang rekomendasinya sudah diganti ditolak sebagai konflik (Q55 §55.1 butir 6). Semula: baris terbaru tanpa memandang status adalah yang berlaku (digantikan Q51).

**Pengisi dan permission (digantikan Q45 §45.2 dan Q51 §51.2):** permission **`rekomendasi:tetapkan`** (bertipe `sensitif`, lihat §2.3) dipegang **Pimpinan** dan **Perencanaan** melalui preset seeder; simpan draf dan setujui dibedakan oleh `status`, bukan oleh permission (Q51 §51.2 butir 1). Ketentuan lama — permission hanya dipegang Perencanaan dan Pimpinan read-only karena alur persetujuan Pimpinan ditunda ke Fase Lanjutan — digantikan Q45/Q51.

**Independensi dari status `pengukuran`:** rekomendasi melekat pada kombinasi indikator × periode, terpisah dari siklus status `pengukuran` — dapat diisi setelah rapat evaluasi triwulan tanpa menunggu pengukuran periode itu berstatus `disahkan`. Rekomendasi tetap opsional per indikator per periode (Q51 §51.1 butir 3).

---

### 2.32 `audit_log`

Pencatatan append-only seluruh peristiwa penting sistem.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `actor_id` | uuid | FK → users.id | Pengguna yang melakukan tindakan |
| `waktu` | timestamp | not null | |
| `tindakan` | varchar | not null | mis. `renstra.aktivasi`, `pengukuran.kembalikan`, `indikator.pindah_unit`, `unit.hapus`, `jadwal.aktivasi`, `jadwal.buka_kembali`, `pengaturan.ubah`, `rencana_aksi.ajukan`, `rencana_aksi.verifikasi`, `rencana_aksi.kembalikan`, `rencana_aksi.sahkan`, `rencana_aksi.buka_kembali`, `kegiatan.buat`, `kegiatan.ubah_status`, `kegiatan.geser_periode`, `kegiatan.catat_realisasi`, `kegiatan.terlaksana_ditolak`, `klaim_kegiatan.tambah`, `klaim_kegiatan.hapus`, `indikator_komponen.ubah`, `indikator.ubah_tipe_perhitungan`, `jenis_berkas.ubah`, `berkas.unggah`, `berkas.hapus`, **`berkas.tandai_tidak_dapat_dipenuhi`** (histori: penandaan otomatis saat mode `file` satu-satunya yang diizinkan sementara unggahan dinonaktifkan, termasuk pada gerbang lampiran PK §2.15; tidak lagi terjadi sejak Q44), `rekomendasi_pimpinan.tetapkan`, **`regulasi.buat`/`regulasi.ubah`/`regulasi.hapus`** (perubahan katalog dasar aturan, §2.33), **`renstra.ubah_regulasi`/`indikator.ubah_regulasi`** (perubahan rujukan `regulasi_id`), **`role_permissions.ubah`** (isi peran ditambah/dikurangi), **`user_roles.tambah`/`user_roles.ubah`/`user_roles.hapus`** (penetapan/pergantian/pencabutan peran pengguna), **`user_permission_granted.tambah`/`user_permission_granted.hapus`** (grant izin), **`user_permission_denials.tambah`/`user_permission_denials.hapus`** (deny izin), serta peristiwa **percobaan tindakan yang ditolak** (mis. gerbang rencana aksi belum disahkan, komponen belum lengkap, berkas wajib belum lengkap, gerbang kegiatan `rencana → terlaksana` belum lengkap, gerbang lampiran PK belum lengkap, permintaan izin yang berakhir tolak pada resolusi §3) |
| `objek_tipe` | varchar | not null | Nama entitas terkait, mis. `renstra`, `pengukuran`, `indikator`, `jadwal_snapshot`, `pengaturan`, `rencana_aksi`, `kegiatan`, `klaim_kegiatan`, `indikator_komponen`, `jenis_berkas`, `berkas`, `rekomendasi_pimpinan`, **`regulasi`**, **`role_permissions`, `user_roles`, `user_permission_granted`, `user_permission_denials`** |
| `objek_id` | uuid | not null | ID baris entitas terkait |
| `nilai_lama` | jsonb | nullable | Snapshot kondisi sebelum perubahan |
| `nilai_baru` | jsonb | nullable | Snapshot kondisi sesudah perubahan |
| `alasan` | text | nullable | **Wajib diisi (validasi aplikasi; daftar diperbarui Q43 §43.2)** untuk: (1) pengembalian dan buka-kembali — pengembalian pengukuran/rencana aksi, `pengukuran:buka_kembali`, `rencana_aksi:buka_kembali`, `jadwal:buka_kembali`, serta penutupan jadwal `jadwal:tutup` (Q52 §52.1); (2) penghapusan data atau berkas — mis. penghapusan unit, `regulasi:delete`, `berkas.hapus`; (3) koreksi atau revisi data sah — koreksi PK, revisi PK (Q40), koreksi snapshot (Q35); (4) perubahan akses — penetapan/pergantian peran pengguna (`user_roles`), penonaktifan dan pengaktifan kembali akun (Q38), deny dan pencabutannya (`user_permission_denials`), pencabutan grant; (5) justifikasi kegiatan `tidak_terlaksana`/`ditunda`/`batal`, dan alasan deviasi terhadap target PK (ADR-0008). Alasan target PK yang menyimpang dari target Renstra menurut arah indikator (Q39) tetap wajib, tetapi tidak memakai kolom ini: alasannya disimpan pada `target_tahunan.alasan_deviasi_renstra` dan tercatat pada `nilai_lama`/`nilai_baru` (Q65 §65.2 butir 11). Kewajiban lama yang tidak disebut Q43 §43.3 tetap berlaku, mis. **perubahan `regulasi` (`regulasi:update`)** dan **perubahan isi peran (`role_permissions`)**. **Opsional sejak Q43 §43.3:** pemberian grant (kolom `user_permission_granted.alasan` diisi alasan baku server bila kosong, §2.7), perubahan target PK, perubahan persyaratan berkas, pindah unit indikator, aktivasi jadwal, perubahan setelan aplikasi, perubahan PK sebelum dipakai jadwal aktif, serta penetapan dan pergantian penanggung jawab (field alasan dihapus dari form, §2.19). Semula juga wajib untuk pergantian penanggung jawab dan setiap grant (digantikan Q43) |
| `dasar_izin` | jsonb | **nullable** | **Wajib diisi** untuk aksi atas permission bertanda `permissions.sensitif = true` (lihat §2.3, §3.5): daftar sumber izin yang membuat aksi diizinkan (peran mana / grant mana yang cocok), atau deny mana yang memicu penolakan. `null` untuk tindakan yang tidak melalui gerbang permission sensitif |

**Pembedaan sensitif dan alasan (Q37 §37.2):** `permissions.sensitif = true` mewajibkan `dasar_izin`, bukan alasan dari pengguna. Verifikasi RA memakai alasan baku server; pengembalian RA wajib alasan pengguna yang divalidasi/disanitasi. Kewajiban alasan untuk tindakan lain mengikuti PRD dan daftar di atas. Penolakan guard review indikator arsip/unit nonaktif tetap diaudit tanpa meninggalkan mutasi domain yang ditolak.

**Sifat:** tabel ini tidak memiliki endpoint update/delete di aplikasi — hanya `INSERT`. Tidak ada `updated_at`/`deleted_at` karena baris bersifat final begitu ditulis.

**Penyamaran kunci rahasia (Q58):** sebelum baris disimpan, `WriteAuditLog` mengganti nilai di bawah kunci `nilai_lama`, `nilai_baru`, dan `dasar_izin` yang cocok pola rahasia (token, password, secret, authorization, credential, api/access/private key, serta `path` atau `*_path`; tanpa membedakan huruf besar-kecil, termasuk kunci bertingkat) dengan penanda `[disamarkan]`. Kunci yang hanya mirip (mis. `primary_key`, `jalur_pengajuan`) disimpan utuh. Penyamaran tidak menggagalkan mutasi pemanggil dan bukan izin mencatat rahasia; pemilihan field tetap di producer. Baris yang tersimpan sebelum Q58 tidak diubah dan disamarkan kembali dengan pola yang sama saat ditampilkan di halaman Jejak Audit.

**Catatan actor pada peristiwa otomatis:** baris yang mencatat pembuatan `jadwal_snapshot` beserta `jadwal_snapshot_komponen` (tindakan `jadwal.aktivasi`/`jadwal.buka_kembali`, lihat §2.17–§2.18) selalu memakai `actor_id` = pengguna Perencanaan/Superadmin yang menjalankan aksi aktivasinya, bukan nilai sistem/null — meski proses pembuatan barisnya sendiri berjalan otomatis di dalam transaksi yang sama. Perubahan pada tabel `pengaturan` (§2.22) mengikuti pola yang sama: `actor_id` = pengguna Superadmin yang mengubah nilai setelan.

**Target Renstra (§2.35):** perubahan tercatat dengan `tindakan` `target_renstra.simpan` (objek `target_renstra`; `nilai_lama`/`nilai_baru` berisi `indikator_id`, `tahun`, `nilai`, dengan `nilai_lama` kosong pada pengisian pertama) dan penolakan dengan `target_renstra.simpan_ditolak` (objek `indikator`); `target_renstra.simpan` menjadi sumber riwayat revisi target Renstra (Q40 §40.2 butir 3).

**Target PK (§2.13):** perubahan tercatat dengan `tindakan` `target_tahunan.simpan` (objek `target_tahunan`; `nilai_lama`/`nilai_baru` berisi `indikator_id`, `tahun`, `baseline`, `target_tahunan`, dan `alasan_deviasi_renstra`, dengan `nilai_lama` kosong pada pengisian pertama). `rujukan_sumber` yang diisi hanya dicatat pada `nilai_baru` dan tidak disimpan pada target PK; kolom `alasan` hanya memuat alasan perubahan yang opsional (Q65 §65.1–§65.2). Penolakan tercatat dengan `target_tahunan.simpan_ditolak` (objek `indikator`) tanpa nilai yang diajukan. Penyimpanan tanpa perubahan tidak diaudit (Q65 §65.2 butir 10).

**Peristiwa rancangan Q39–Q51 (belum diimplementasikan):** pembentukan versi PK (Q40, §2.36) dan unggahan arsip dokumen resmi (Q48 §48.2, §2.30) tercatat di tabel ini dengan `nilai_lama`/`nilai_baru`; kode `tindakan`-nya ditetapkan saat implementasi.

**Penanda dinilai tahunan (Q47 §47.1):** perubahan `indikator.dinilai_tahunan` diaudit sebagai `indikator.ubah`.

**Rekomendasi Pimpinan (Q51, Q55; ISS-09.01):** pembuatan draf baru dicatat sebagai `rekomendasi_pimpinan.tetapkan` dan perubahan draf sebagai `rekomendasi_pimpinan.ubah_draf`. Setiap persetujuan, termasuk rekomendasi yang langsung disetujui tanpa draf (`nilai_lama` kosong), dicatat sebagai `rekomendasi_pimpinan.setujui` dengan `nilai_baru.persetujuan_cadangan` sebagai penanda persetujuan cadangan (Q51 §51.2 butir 2). Penolakan dicatat sebagai `rekomendasi_pimpinan.tetapkan_ditolak`; seluruhnya membawa `dasar_izin`.

**Jejak perubahan definisi berbasis komponen:** perubahan pada `indikator_komponen` dan `jenis_berkas` dicatat dengan `nilai_lama`/`nilai_baru` dan `alasan` (alasan perubahan `jenis_berkas` opsional sejak Q43 §43.3 butir 3) — inilah jejak yang menjaga data historis tetap dapat dipertanggungjawabkan meski formula perhitungan dan katalog persyaratan berkas dapat diubah dari layar aplikasi, tanpa memerlukan deployment kode baru.

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
- **Admin**, **Pimpinan**, **Pegawai** — `regulasi:read` saja, agar dasar aturan tampil
  pada halaman Renstra/indikator dan terbaca konteksnya. Admin tidak memperoleh wewenang
  substantif atas katalog ini, konsisten dengan pemisahan tugas administrasi teknis vs
  wewenang substantif (lihat §2.4).
- **PIC** — hak bawaan `regulasi:read` **masih OPEN pada Q31** sampai preset permission PIC operasional (bukan role)
  dikonfirmasi; jangan menyamakannya otomatis dengan Pegawai hanya karena model lama menggunakan
  Pegawai sebagai basis operasional PIC.

**Halaman kerja:** CRUD `regulasi` beserta lampirannya tersedia pada halaman kerja tersendiri
("Dasar Aturan"); halaman Renstra menampilkan lampiran dokumen, rujukan `regulasi_id`, dan
ringkasan `dasar_hukum` sekaligus; halaman indikator menampilkan rujukan `regulasi_id` bila
diisi.

**Audit:** pembuatan/ubah/hapus `regulasi` tercatat dengan `nilai_lama`/`nilai_baru` dan
`alasan`; perubahan `renstra.regulasi_id`/`indikator.regulasi_id` turut tercatat sebagai
perubahan pada baris `renstra`/`indikator` yang bersangkutan (lihat §2.32).

---

### 2.34 `rencana_aksi_versi`

Riwayat versi pengajuan `rencana_aksi`: satu baris per pengajuan (submit) pada sebuah rencana aksi, menyimpan provenance beku (siapa mengajukan, lewat jalur apa, dengan dasar izin apa) beserta `snapshot` isi pengajuan saat itu. Transisi `diverifikasi → disahkan` mengesahkan **snapshot versi ini**, bukan header; `rencana_aksi.disahkan_at`/`disahkan_by` hanya ringkasan status terkini.

> **Kepemilikan kontrak:** ISS-05.03 (final) adalah pemilik dan satu-satunya pembentuk versi, lewat `POST /rencana-aksi/{id}/ajukan`; ISS-05.04 (verifikasi) dan ISS-05.05 (pengesahan) adalah konsumen. Konfirmasi pemilik tercatat di [PR #66 · issuecomment-6084316378](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/66#issuecomment-6084316378). Perubahan bentuk `snapshot` wajib dicatat di Data Model lebih dahulu dan diselaraskan dengan produsen maupun konsumennya.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `rencana_aksi_id` | uuid | FK → rencana_aksi.id, not null | |
| `jadwal_snapshot_id` | uuid | FK → jadwal_snapshot.id, not null | Snapshot terbaru yang dipakai saat pengajuan, sama dengan `rencana_aksi.snapshot_draf_id`. Karena `jadwal_snapshot` beku sejak terbit (Q35), target PK, baseline, nama/satuan/arah/tipe/presisi, periode mulai, dan definisi komponen (kode/label/peran/bobot) ikut beku lewat relasi ini |
| `nomor` | int | not null | Nomor urut pengajuan (nomor terbesar + 1 di bawah kunci header); `unique(rencana_aksi_id, nomor)`; versi terbaru = `nomor` terbesar |
| `diajukan_by` | uuid | FK → users.id, not null | Pengaju; dasar keras F1/F2 (§4) — bukan `rencana_aksi.created_by` |
| `diajukan_at` | timestamp | not null | Waktu pengajuan; sekaligus kunci urutan antrean pengesahan |
| `jalur_pengajuan` | enum(`pic`,`perencanaan`) | not null | `perencanaan` bila allow `rencana_aksi:ajukan` berasal dari peran (termasuk Superadmin), selain itu `pic`; dibekukan saat submit |
| `dasar_izin_pengajuan` | jsonb | not null | Dasar keputusan izin saat submit — bentuk di bawah |
| `disahkan_by` | uuid | FK → users.id, nullable | Diisi sekali saat pengesahan |
| `disahkan_at` | timestamp | nullable | Diisi sekali saat pengesahan |
| `snapshot` | jsonb | not null | Isi pengajuan beku — kontrak di bawah |

**Sifat beku:** `UPDATE`/`DELETE` baris versi ditolak trigger basis data `rencana_aksi_versi_immutable` (fungsi `reject_submission_version_mutation`) dan `TRUNCATE` ditolak `rencana_aksi_versi_no_truncate`; satu-satunya pengecualian adalah pengisian sekali `disahkan_by` + `disahkan_at` dari `NULL` saat pengesahan. Perbaikan pengajuan dilakukan dengan membuat versi baru — bukan menimpa snapshot lama. Perubahan peran, grant, atau penanggung jawab setelah submit tidak menulis ulang provenance versi.

**Bentuk `dasar_izin_pengajuan`:** keputusan resolver `rencana_aksi:ajukan` pada unit header saat submit (`permission`, `keputusan`, `sumber_allow`, `deny`, dan kunci basis resolver lainnya), ditambah `unit_id` (unit header), `penugasan_id`, dan `pic_id` (penugasan penanggung jawab efektif pada tanggal submit, zona Asia/Makassar; boleh `null` pada jalur Perencanaan).

**Kontrak isi `snapshot` (jsonb):** semua nilai desimal berupa string dan UUID berhuruf kecil.

| Key | Tipe | Isi |
|---|---|---|
| `indikator` | object | `{kode, nama}`; `nama` dari snapshot beku, `kode` dari master |
| `unit_kerja` | object | `{id, nama}` unit header |
| `pic` | object\|null | `{id, nama}` PJ efektif pada tanggal submit (zona Asia/Makassar), sama dengan `dasar_izin_pengajuan.pic_id`; null pada jalur Perencanaan tanpa PJ |
| `uraian` | string\|null | `rencana_aksi.uraian` |
| `alasan_deviasi_pk` | string\|null | `rencana_aksi.alasan_deviasi_pk` saat submit |
| `deviasi_pk` | object | `{status, skor, target_pk, presisi}`: skor periode efektif terakhir dibandingkan target PK beku setelah keduanya dibulatkan HALF_UP pada presisi snapshot; `status` ∈ `setara`, `deviasi`, `tidak_dapat_dinilai` (target PK atau skor null); `skor`/`target_pk` adalah angka hasil pembulatan |
| `target_periode` | array | Hanya periode efektif, urut `periode_urutan`: `{periode_id, periode_nama, periode_urutan, nilai, status_perhitungan, keterangan, komponen[]}`. `nilai` = keluaran mesin hitung sehingga pembaca yang menghitung ulang memperoleh angka sama; `status_perhitungan` = `terhitung` pada versi yang berhasil. Rancangan Q47: periode tanpa target (indikator dinilai tahunan sebelum periode nilai akhir, atau periode lampau 2026, §2.23) dibekukan dengan `nilai = null` dan `status_perhitungan = tidak_ditargetkan`, bukan `belum_diisi` (Q47 §47.2 butir 5). Manual: `komponen = []`, `keterangan` = keterangan sel manual. Nonmanual: `keterangan = null` di baris, `komponen[] = {komponen_id, kode, label, nilai, keterangan}` seluruh komponen snapshot |
| `persyaratan_bukti` | array | Keluaran evaluator bukti bersama tahap `rencana_aksi` apa adanya (definisi persyaratan + `pemenuhan`), dibekukan saat submit (Q24) |
| `bukti_dukungs` | array | Bukti berlaku saat submit: `{id, jenis_berkas_id, menggantikan_id, alasan_koreksi, mode, nama_asli, mime, ukuran_bytes, tautan, isi_teks, path}`; `path` privat hanya dipakai server untuk unduhan beku, tidak pernah dikirim ke props |
| `persyaratan_kegiatan` | array | Diterapkan ISS-06.08 (Q42 §42.4): per kegiatan berklaim tahap RA, urut `kegiatan_id`: `{kegiatan_id, dikecualikan, persyaratan, berkas}`; `persyaratan` adalah keluaran evaluator tahap `kegiatan_perencanaan` (global + khusus indikator RA, penanda `hanya_kegiatan_beranggaran` diterapkan), `berkas` adalah metadata berkas berlaku tanpa path; contoh berkas tidak dibekukan. Versi sebelum ISS-06.08 tidak memiliki key ini |
| `anggaran_kegiatan` | object | Diterapkan ISS-06.08 (Q42 §42.2 butir 6): total anggaran kegiatan RA saat submit, `{total, belum_diisi, per_periode: {<periode_id>: {total, belum_diisi}}}`; `total` string rupiah bulat dari kegiatan unik klaim tahap RA (klaim yang dikunci dan dibekukan saat submit), `belum_diisi` jumlah kegiatan beranggaran kosong yang tidak dihitung, kegiatan asal `ditunda` tidak dihitung setelah lanjutannya diklaim (Q69 §69.6). Tidak dihitung ulang; versi sebelum ISS-06.08 tidak memiliki key ini. |
| `klaim` | array | Klaim tahap RA (`sumber_klaim = rencana_aksi`) yang dibekukan saat submit (Q24; Q42 §42.1), urut `id`: `{id, kegiatan_id, komponen_id, arah_dampak, catatan, periode_id, kegiatan: {nama, tujuan, anggaran, status}}`; `anggaran` berupa string desimal atau `null`. Diterapkan ISS-06.02; bentuk diusulkan kepada pemilik kontrak (ISS-05.03) melalui PR ISS-06.02. Keluaran evaluator persyaratan tahap `kegiatan_perencanaan` per kegiatan beserta buktinya (Q42 §42.4) dibekukan ISS-06.08 pada key terpisah `persyaratan_kegiatan`. Versi sebelum ISS-06.02 tidak memiliki key ini (ditampilkan sebagai konteks tidak lengkap) |

Key yang hilang ditampilkan sebagai "konteks tidak lengkap" pada layar reviu, bukan galat; kunci yang tidak dikenali diabaikan. Setiap versi merujuk tepat satu `jadwal_snapshot` (§2.17) yang membekukan konteks indikatornya. Unduhan bukti hanya melayani bukti yang masih berlaku pada induknya; karena bukti tidak dapat diubah sejak induk diajukan (Q36), bukti versi yang sedang direviu dan versi resmi terakhir selalu termasuk di dalamnya. Akses bukti versi resmi lama setelah buka-kembali menjadi bagian ISS-05.06.

Versi yang isi target, komponen, atau bukti bekunya tidak lengkap (termasuk tautan, isi teks, atau nama berkas sesuai mode bukti) tidak dapat diverifikasi atau disahkan, tetapi tetap dapat dikembalikan agar penanggung jawab mengajukan versi pengganti; guard izin, status, jadwal, indikator, unit, dan keselarasan konteks tetap berlaku.

---

### 2.35 `target_renstra`

Target Renstra per indikator per tahun dalam rentang Renstra — lapis pembanding dari dua lapis target (Q39 §39.1). Diisi Tim Perencanaan di halaman Sasaran & Indikator, sekali per Renstra sesuai dokumen Renstra. Menjadi pembanding target PK menurut `arah` (`target_tahunan`, §2.13; Q65 §65.2), tetapi **tidak** dipakai menghitung capaian dan tidak dibekukan ke snapshot; capaian dinilai terhadap target PK melalui snapshot (Q39 §39.4 butir 1).

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `indikator_id` | uuid | FK → indikator.id, not null, on delete restrict | |
| `tahun` | int | not null | Harus berada dalam rentang `[renstra.tahun_mulai, renstra.tahun_akhir]` Renstra induk indikator (melalui sasaran) dan tidak sebelum `indikator.tahun_mulai_berlaku`; tahun di luar rentang ditolak (validasi aplikasi) |
| `nilai` | numeric(30,12) | **nullable**, finite, nonnegatif (`>= 0`) | Target Renstra; domain nilai Q33 (Q39 §39.1 butir 4): `0` sah dan berbeda dari `null`, tanpa batas universal `100`. `null` atau baris tidak ada = "target Renstra belum ada" |
| `updated_by` | uuid | FK → users.id, not null, on delete restrict | |
| `created_at` | timestamp(6) | nullable | |
| `updated_at` | timestamp(6) | not null | |

**Constraint:** `unique(indikator_id, tahun)` — paling banyak satu nilai per indikator per tahun.

**Permission:** disimpan dengan `target:update` yang sudah ada; tidak ada permission baru (Q39 §39.3 butir 3).

**Revisi dan riwayat (Q40 §40.2 butir 3):** perubahan `nilai` tercatat di `audit_log` dengan nilai lama dan baru tanpa alasan pengguna (Q60). Riwayat revisi target Renstra dibaca dari `audit_log`; tidak ada tabel riwayat terpisah. Bila target terlampaui sebelum akhir periode Renstra, target tahun-tahun berikutnya dinaikkan dengan cara ini.

**Akses riwayat (ISS-02.10):** riwayat revisi target Renstra adalah bagian data target, bukan Jejak Audit, sehingga cukup `indikator:read`; deny `audit:read` (Q56 §56.1 butir 2) tidak memblokirnya. Isinya hanya waktu, nama aktor, tahun, nilai lama, dan nilai baru, tanpa email dan `dasar_izin`.

**Pembanding target PK (Q39 §39.2):** target Renstra tahun yang sama ditampilkan di samping isian target PK. Deviasi target PK terhadap target Renstra menurut `indikator.arah` memunculkan peringatan dan mewajibkan `target_tahunan.alasan_deviasi_renstra` (§2.13). Indikator tanpa target Renstra — mis. IKU baru di tengah periode Renstra, atau IKU 4.1 (sasaran S4) yang hanya ada di PK (Q50 §50.5) — tetap dapat diberi target PK dengan keterangan "target Renstra belum ada".

**Bukan gerbang aktivasi:** kekosongan target Renstra tidak menghalangi aktivasi jadwal (§2.15; Q39 §39.1 butir 5).

---

### 2.36 `renstra_pk_versi`

> **Rancangan Q40 — belum diimplementasikan** (ISS-02.12, tahap 3; prasyarat alur koreksi snapshot berversi ISS-03.03, Q40 §40.1 butir 9).

Versi Perjanjian Kinerja. Setiap revisi PK yang disetujui **Perencanaan** di aplikasi, umumnya pada akhir tahun setelah persetujuan Eselon I, membentuk satu versi baru; versi sebelumnya tidak diubah (Q40 §40.1 butir 1–2). Versi 1 = **PK awal**. **PK akhir** adalah versi yang berlaku pada periode nilai akhir dan menjadi acuan capaian akhir tahun (Q40 §40.1 butir 6). PK awal dan PK akhir sama-sama disimpan dan dapat diunduh karena keduanya menjadi lampiran laporan kinerja (Q40 §40.1 butir 3).

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `renstra_pk_id` | uuid | FK → renstra_pk.id, not null | PK induk, satu per Renstra-tahun (§2.10) |
| `nomor_versi` | int | not null | `1` = PK awal; setiap revisi = nomor terbesar + 1 |
| `nomor_surat` | varchar | nullable | Nomor surat persetujuan Eselon I sebagai isian tersendiri (Q40 §40.1 butir 2), menggantikan pencantuman pada kolom `alasan` (§2.10). Kosong pada versi 1; wajib pada versi revisi |
| `tanggal_surat` | date | nullable | Tanggal surat persetujuan Eselon I; aturan wajibnya sama dengan `nomor_surat` |
| `periode_mulai_id` | uuid | FK → periode.id, nullable | Periode mulai berlaku. Versi revisi: periode pertama yang belum ditutup saat persetujuan; periode yang sudah dilaporkan tetap memakai target versi lama (Q40 §40.1 butir 4). Kriteria "belum ditutup" ditetapkan saat refinement bersama ISS-03.03. Versi 1: kosong = berlaku sejak periode pertama tahun PK |
| `alasan` | text | nullable | Alasan revisi; wajib pada versi revisi (Q43 §43.2 butir 3) dan tercatat di `audit_log` |
| `disetujui_oleh` | uuid | FK → users.id, nullable | Pengguna Perencanaan yang menyetujui revisi (Q40 §40.1 butir 1); wajib pada versi revisi |
| `disetujui_pada` | timestamp | nullable | Waktu persetujuan; wajib pada versi revisi |
| `created_at` | timestamp | not null | |

**Constraint:** `unique(renstra_pk_id, nomor_versi)`.

**Append-only:** baris versi tidak diubah atau dihapus setelah terbentuk. Koreksi salah ketik nomor atau tanggal PK tetap memakai jalur koreksi teraudit pada `renstra_pk` (ISS-02.09), bukan versi baru; perubahan target resmi memakai versi baru (Q40 §40.1 butir 8).

**Lampiran per versi:** setiap versi revisi menyimpan lampiran dokumen PK bertanda tangan sebagai `berkas` induk `renstra_pk_versi` (§2.30), dengan permission PK (Q40 §40.1 butir 2, Q48 §48.2 butir 1–2).

**Dampak ke snapshot dan rencana aksi:** persetujuan versi yang mengubah target menerbitkan versi `jadwal_snapshot` baru bagi indikator terdampak, dengan `periode_mulai_id` = `renstra_pk_versi.periode_mulai_id` dan `rujukan_koreksi` = surat persetujuan Eselon I (§2.17; Q35 §35.1, Q40 §40.1 butir 4). Versi yang hanya mengganti dokumen tanpa mengubah target tidak menerbitkan versi snapshot (Q40 §40.1 butir 5). Rencana aksi tidak diubah otomatis; selisih dengan target PK baru tampil sebagai deviasi yang wajib dijelaskan, dan target periode yang belum terkunci disesuaikan lewat buka-kembali (§2.24; Q40 §40.1 butir 7).

**Ditetapkan saat refinement (ISS-02.12):** kapan versi 1 dibentuk (saat PK dicatat atau saat revisi pertama) beserta sumber datanya (`target_tahunan` dan lampiran `renstra_pk` yang sudah ada), serta apakah `target_tahunan` ikut diperbarui ke target versi terbaru. Sebelum ISS-02.12 tersedia, PK perubahan yang hanya mengubah anggaran dicatat sebagai lampiran tambahan `renstra_pk` (Q50 §50.7, §2.10).

---

### 2.37 `renstra_pk_versi_target`

> **Rancangan Q40 — belum diimplementasikan** (ISS-02.12, tahap 3).

Target PK per indikator pada satu versi PK (Q40 §40.1 butir 2). Setiap versi memuat target seluruh indikator aktif Renstra pada tahun PK, termasuk yang tidak berubah, sehingga satu versi dapat dibaca utuh tanpa versi lain.

| Kolom | Tipe | Constraint | Keterangan |
|---|---|---|---|
| `id` | uuid | PK | |
| `renstra_pk_versi_id` | uuid | FK → renstra_pk_versi.id, not null | |
| `indikator_id` | uuid | FK → indikator.id, not null | |
| `nilai` | numeric | **nullable**, finite, nonnegatif (`>= 0`) | Target PK pada versi ini; domain nilai Q33 dan disimpan bernilai penuh (Q50 §50.2). Versi `jadwal_snapshot` yang diterbitkan revisi membawa nilai ini sebagai `target` (§2.17) |
| `alasan_deviasi_renstra` | text | nullable | Alasan bila target versi ini di bawah target Renstra tahun yang sama (`naik_baik`) atau di atasnya (`turun_baik`); wajib bila deviasi terjadi, tanpa menolak nilai — aturan sama dengan `target_tahunan.alasan_deviasi_renstra` (§2.13), karena PK revisi yang sah dapat lebih rendah (Q39 §39.2 butir 2) |

**Constraint:** `unique(renstra_pk_versi_id, indikator_id)`.

**Baseline tidak berversi:** baseline bukan isi dokumen PK dan tetap disimpan pada `target_tahunan.baseline` (Q50 §50.3, §2.13).

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
6. **Jangan campur dengan validasi bisnis.** Jendela waktu (`jadwal_tahunan.penutupan`, `jadwal_periode.pengisian_selesai` termasuk kunci per periode rencana aksi Q41; `jadwal_tahunan.rencana_aksi_selesai` dihapus oleh migrasi #159 setelah tidak dipakai sejak Q41), kepemilikan unit atas record yang diakses, dan gerbang kelengkapan (rencana aksi belum disahkan, komponen belum lengkap, berkas wajib belum lengkap) adalah **validasi bisnis** yang berjalan **setelah** langkah 1–5 di atas menyatakan "boleh". Kontraknya: resolusi izin menjawab "apakah aktor ini secara prinsip boleh melakukan aksi ini", validasi bisnis menjawab "apakah saat ini/untuk record ini aksi tersebut masih sah dilakukan". Kedua lapisan ini **tidak boleh digabung** dalam satu pemeriksaan — kegagalan validasi bisnis tidak pernah diperlakukan sebagai "izin ditolak", dan sebaliknya.
7. **Tidak ada evaluasi izin di sisi klien.** Resolusi hanya dijalankan di server (satu service resolusi terpusat, lihat §3.5). Halaman React dapat menyembunyikan tombol/menu berdasarkan hasil pemeriksaan dari server (mis. lewat props halaman), tetapi ini murni kenyamanan tampilan — server **selalu** mengevaluasi ulang setiap permintaan yang benar-benar mengubah/mengambil data, tanpa mempercayai keputusan yang sudah dibuat di klien sebelumnya.

### 3.3 Lokasi scope unit

- Permission hasil peran (`role_permissions`, §2.5) **selalu global** — tabel ini sengaja tidak memiliki kolom `unit_id`.
- **Q31:** fakta bahwa `pic` sekarang merupakan role resmi tidak mengubah lokasi scope ini. Jika suatu permission harus terbatas unit, scope-nya tetap tidak dapat disimpan pada `role_permissions`.
- Scope unit hidup **hanya** pada `user_permission_granted` (§2.7), dan dapat dicabut per unit lewat `user_permission_denials` (§2.8).
- Konsekuensi pola pemakaian:
  - **Jalur kerja PIC unit A setelah Q31** = PIC operasional (bukan role) sudah ada sebagai klasifikasi resmi, tetapi preset permission-nya masih OPEN. Scope unit tetap berasal dari grant `user_permission_granted` untuk permission `butuh_scope = unit`; penugasan indikator tetap berasal dari `penanggung_jawab`. Jangan menyimpulkan PIC operasional (bukan role) sendiri memberi akses global, dan jangan menambah constraint eligibility `penanggung_jawab` sebelum ada konfirmasi.
  - **"Perencanaan global"** = peran `perencanaan` (izin `create`/`update`/`ajukan` sudah global lewat isi peran, tanpa perlu grant tambahan).

### 3.4 Implementasi: satu service resolusi terpusat

Resolusi izin dijalankan lewat **satu service/Policy/Gate terpusat** yang dipanggil dari seluruh controller/handler backend — bukan logika allow/deny yang tersebar dan diduplikasi di masing-masing controller. Ini menjamin langkah 1–6 di §3.2 selalu diterapkan konsisten, dan menjadi satu-satunya titik yang perlu diubah/diaudit bila aturan resolusi berubah.

### 3.5 Audit dasar izin untuk aksi sensitif

Untuk setiap aksi atas permission bertanda `permissions.sensitif = true` (§2.3), baris `audit_log` yang dihasilkan **wajib** mengisi kolom `dasar_izin` (§2.32) dengan sumber izin yang membuat aksi tersebut diizinkan (peran mana, dan/atau grant mana yang cocok) — atau, bila aksi ditolak akibat deny, deny mana yang memicu penolakan. Kebutuhan ini muncul justru karena izin dievaluasi hidup: jejak "kenapa orang ini boleh melakukan ini" tidak lagi dapat dibaca dari satu baris izin statis seperti pada model lama.

**Q37 §37.2:** kewajiban `dasar_izin` untuk permission sensitif tidak otomatis mewajibkan alasan pengguna. Alasan mengikuti kontrak tindakan di §2.32 dan PRD; verifikasi RA cukup konfirmasi dengan alasan audit baku server, sedangkan pengembalian RA wajib alasan pengguna.

---

## 4. Pemisahan Tugas (Segregation of Duties)

Bagian ini adalah **aturan bisnis**, bukan bagian dari resolusi izin di §3 — aturan ini berjalan **setelah** aktor dinyatakan lolos pemeriksaan `boleh(...)`, dan tidak dapat "dinonaktifkan" lewat pemberian grant apa pun. Ini yang membedakannya dari deny (§2.8): deny bersifat administratif dan dapat ditambah/dicabut, sedangkan aturan pemisahan tugas di bawah ini adalah logika tetap pada service layer.

### 4.1 F1 — Larangan pengaju jalur PIC menyetujui pengukurannya sendiri (keras)

Pengaju `pengukuran`/`rencana_aksi` yang mengisi lewat izin ber-scope unit (jalur PIC, lewat grant §2.7) **TIDAK DAPAT** memverifikasi atau mengesahkan baris yang ia ajukan sendiri. Aturan ini **keras** — tidak dapat dikonfigurasi lewat mekanisme apa pun, termasuk grant/deny — supaya tidak bisa "dinonaktifkan" secara administratif oleh siapa pun, termasuk Superadmin.

**Q31 tidak mengubah dasar F1:** larangan ditentukan oleh jalur/provenance pengajuan scoped, bukan semata-mata oleh nama role user saat ini. Pergantian role setelah pengajuan tidak boleh menghapus larangan historis.

**Implementasi:** pada setiap transisi `diajukan → diverifikasi` dan `diverifikasi → disahkan` (baik untuk `pengukuran.status_alur` maupun `rencana_aksi.status_alur`), sistem membandingkan `actor_id` aktor yang menjalankan transisi dengan `pengukuran_versi.diajukan_by`/`rencana_aksi_versi.diajukan_by` versi pengajuan beku yang bersangkutan. Bila keduanya sama **dan** pengajuan berasal dari jalur izin ber-scope unit, transisi **ditolak** — terlepas dari permission `pengukuran:verifikasi`/`pengukuran:sahkan`/`rencana_aksi:verifikasi`/`rencana_aksi:sahkan` apa pun yang dimiliki aktor tersebut. Ini menyelaraskan ke Q15/Q32 (Q31 sudah superseded).

### 4.2 F2 — Pengecualian bagi jalur Perencanaan (self_approval bertanda)

Jalur **Perencanaan** yang mengisi pengukuran/rencana aksi atas nama unit (lewat permission global hasil peran, dipakai saat tenggat operasional terlewat atau untuk pengisian periode lampau 2026) **diizinkan** diverifikasi/disahkan oleh orang yang sama. Konsekuensinya:

- Aksi tersebut diberi **penanda `self_approval`** pada baris `audit_log` yang mencatat transisi verifikasi/pengesahannya.
- Penanda ini ikut ditampilkan sebagai indikator di dashboard/laporan Perencanaan — memberi visibilitas atas kejadian ini, bukan menyembunyikannya.

**Alasan keputusan:** bila jalur ini diblokir sepenuhnya seperti F1, LLDIKTI Wilayah XVI wajib menugaskan minimal dua akun Perencanaan agar pengisian yang terlambat masih dapat disahkan — kondisi yang belum tentu terpenuhi pada Fase Awal. Aturan keras yang setara F1 untuk jalur Perencanaan tetap dapat diberlakukan di kemudian hari bila jumlah personel Perencanaan sudah mencukupi.

### 4.3 F3 — Relasi dengan resolusi izin

F1 dan F2 **tidak menggantikan** resolusi izin pada §3: aktor tetap harus lolos `boleh(pengukuran:verifikasi | pengukuran:sahkan | rencana_aksi:verifikasi | rencana_aksi:sahkan, unit_target?)` terlebih dahulu. F1/F2 adalah validasi bisnis tambahan yang berjalan sesudahnya, khusus untuk mencegah pengaju menyetujui pekerjaannya sendiri — persis pola yang sama seperti gerbang jendela waktu dan gerbang kelengkapan berkas (§3.2 langkah 6).

---

## 5. Ringkasan Constraint Unik (Cross-Reference)


**Catatan Q32 untuk constraint akses:** tidak ada role `pic`. `user_roles` tetap `unique(user_id)` tetapi barisnya boleh belum ada untuk user JIT yang belum diaktifkan/ditetapkan role. `penanggung_jawab.user_id` hanya mensyaratkan user ada dan aktif pada saat penetapan; assignment tidak memberi permission.

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
| `renstra_pk` | unique(`renstra_id`, `tahun`) — penanda PK sejak `nomor_pk` nullable (Q50 §50.1, §2.10) |
| `target_tahunan` | unique(`indikator_id`, `tahun`); `nilai` dan `baseline` nullable, nonnegatif, finite (§2.13); level aplikasi (Q39, Q65 §65.2): `alasan_deviasi_renstra` wajib bila target PK menyimpang dari target Renstra menurut `arah`, ditegakkan saat simpan |
| `target_renstra` | unique(`indikator_id`, `tahun`); `nilai` nullable, nonnegatif, finite; level aplikasi: `tahun` dalam rentang Renstra (§2.35) |
| `renstra_pk_versi` (rancangan Q40) | unique(`renstra_pk_id`, `nomor_versi`); level aplikasi: `nomor_surat`, `tanggal_surat`, `periode_mulai_id`, `alasan`, dan persetujuan wajib pada versi revisi; baris tidak diubah setelah terbentuk (§2.36) |
| `renstra_pk_versi_target` (rancangan Q40) | unique(`renstra_pk_versi_id`, `indikator_id`); `nilai` nullable, nonnegatif, finite (§2.37) |
| `penanggung_jawab` | index non-unique (`indikator_id`, `tanggal_mulai_berlaku`); unique per tanggal dilepas oleh Q34 butir 1 ([#70](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/70)), penugasan pada tanggal sama diurutkan dengan `urutan`, lihat §2.19 |
| `periode` | CHECK `periode_tanggal_valid` (tanggal tanpa tahun terisi atau kosong bersamaan, sah setiap tahun, mulai tidak setelah selesai; Q70 §70.1); level aplikasi: periode aktif wajib bertanggal dan rentangnya tidak tumpang tindih dengan periode aktif lain (§2.14) |
| `jadwal_tahunan` | unique (`renstra_id`, `tahun`) lintas status `draft`, `aktif`, dan `ditutup` (addendum ISS-03.01) |
| `jadwal_tahunan` (level aplikasi) | aktivasi mensyaratkan EMPAT gerbang: `renstra_pk` tersedia; seluruh indikator aktif memiliki `target_tahunan`; `tahun` berada dalam rentang Renstra; minimal satu lampiran `berkas` pada `renstra_pk` terkait (gerbang keempat; penanda `tidak_dapat_dipenuhi` saat unggahan file dimatikan tidak lagi terjadi sejak Q44, histori) |
| `jadwal_periode` | unique(`jadwal_id`, `periode_id`); CHECK `jadwal_periode_urutan_tanggal` (`pengisian_mulai <= pengisian_selesai`, Q70; §2.16); level aplikasi: `pengisian_mulai` paling cepat tanggal 1 bulan terakhir periode (Q70 §70.2 butir 2; §2.16) |
| `jadwal_snapshot` | unique(`jadwal_id`, `indikator_id`, `nomor_versi`) — versi 1 tetap dibuat idempoten per (`jadwal_id`, `indikator_id`); koreksi (Q35) dan revisi PK berversi (rancangan Q40) menambah versi baru (§2.17) |
| `pengukuran` | unique(`indikator_id`, `tahun`, `periode_id`); CHECK `nilai_status_cocok` antara `status_perhitungan` dan `nilai`, diperluas untuk `belum_dapat_diukur` (rancangan Q47, §2.20) |
| `pengaturan` | unique(`kunci`) |
| `rencana_aksi` | unique(`indikator_id`, `tahun`) |
| `rencana_aksi_target` | unique(`rencana_aksi_id`, `periode_id`, `komponen_id`) `NULLS NOT DISTINCT` — `komponen_id IS NULL` untuk manual tetap tepat satu baris per periode (§2.24, ADR-0007) |
| `klaim_kegiatan` | unique(`rencana_aksi_id`, `kegiatan_id`, `komponen_id`) — implementasi index memakai `COALESCE(komponen_id, sentinel)` karena PostgreSQL memperlakukan `NULL` sebagai nilai berbeda antarbaris |
| `indikator_komponen` | unique(`indikator_id`, `kode`) |
| `pengukuran_komponen` | unique(`pengukuran_id`, `komponen_id`) |
| `renstra` (level aplikasi) | tidak boleh 2 baris `aktif` dengan rentang `[tahun_mulai, tahun_akhir]` beririsan; direkomendasikan exclusion constraint (`EXCLUDE USING gist` + `btree_gist`) sebagai lapisan pertahanan kedua |
| `renstra` (level aplikasi) | tidak dapat ditransisikan keluar dari `aktif` selama masih ada `jadwal_tahunan` berstatus `aktif` yang mengacunya |
| `indikator` (level aplikasi) | `pengukuran:create` ditolak untuk indikator berstatus `arsip` |
| `indikator` (level aplikasi) | `rasio_persen` wajib ≥1 komponen `pembilang` aktif & tepat 1 komponen `penyebut` aktif; `penjumlahan` wajib ≥1 komponen `penjumlah` aktif |
| `periode` (level aplikasi) | tepat satu baris `is_nilai_akhir = true` |
| `pengukuran` (level aplikasi) | pengajuan ditolak selama `rencana_aksi` (indikator × tahun) belum `disahkan`; ditolak bila ada komponen aktif bernilai `null`, kecuali `status_perhitungan = belum_dapat_diukur` (rancangan Q47), yang tidak boleh dipakai pada periode nilai akhir; ditolak bila narasi wajib kosong atau tindak lanjut atas rekomendasi `disetujui` periode sebelumnya kosong (rancangan Q45/Q51), kecuali periode lampau 2026 (Q46 §46.1) |
| `rencana_aksi` (level aplikasi) | pengajuan ditolak bila ada komponen aktif tanpa `rencana_aksi_target` pada salah satu periode yang diharapkan, kecuali periode yang boleh tanpa target (Q47; §2.23); ditolak bila tidak ada minimal satu kegiatan berklaim tahap RA atau persyaratan wajib tahap `kegiatan_perencanaan` belum terpenuhi atau anggaran kegiatan belum diisi (Q42, ISS-06.02/ISS-06.08), kecuali RA data awal 2026 lewat jalur Perencanaan (Q46 §46.1 butir 3, Q63); persyaratan wajib tahap `kegiatan_perencanaan` dan anggaran juga dikecualikan untuk kegiatan pada periode lampau 2026 (Q42, Q46 §46.1 butir 1 dan 3, Q63 §63.1 butir 4); PIC tidak dapat mengubah target maupun daftar kegiatan periode yang terkunci (Q41 §41.2) |
| `klaim_kegiatan` (level aplikasi) | ditolak bila `kegiatan.unit_id` berbeda dari unit rencana aksi/indikator yang diklaim; PIC tidak dapat menambah atau menghapus klaim tahap RA pada periode yang terkunci (Q41 §41.2); salinan kegiatan hanya ke periode yang belum terkunci (rancangan Q49 §49.2) |
| `berkas` (level aplikasi) | imutabilitas per induk (tujuh nilai `berkasable_type`): `renstra` tidak dapat dihapus setelah `renstra` berstatus `aktif`; `renstra_pk` tidak dapat dihapus setelah `jadwal_tahunan` tahun tersebut `aktif`; `regulasi` tidak dapat dihapus selama masih dirujuk `renstra`/`indikator` aktif; `kegiatan` tidak dapat dihapus setelah kegiatan `terlaksana`, dan seluruh berkas tahap `kegiatan_perencanaan` milik kegiatan tidak dapat dihapus maupun diganti sejak salah satu RA pengklaimnya diajukan, sejalan dengan snapshot `persyaratan_kegiatan` (Q42, ISS-06.08); `jenis_berkas` (contoh) dapat dihapus kapan saja dengan alasan; `rencana_aksi`/`pengukuran` hanya dapat ditambah/diganti/dihapus selama induk `draft`/`dikembalikan` (Q36); rancangan Q48: arsip `jadwal_tahunan`/`jadwal_periode` tidak dapat dihapus, dikoreksi dengan berkas pengganti, dan tetap dapat diunggah setelah jadwal `ditutup`; penghapusan berkas wajib alasan (Q43 §43.2) |
| `jenis_berkas` (level aplikasi) | minimal satu dari `izinkan_file`/`izinkan_tautan`/`izinkan_teks` bernilai `true`; penyimpanan tanpa satu pun mode aktif ditolak; `ukuran_maks_kb` paling besar 102400 (Q44 §44.2) |
| `berkas` (level aplikasi) | mode wajib termasuk mode yang diizinkan pada `jenis_berkas` terkait, kecuali lampiran bebas (`jenis_berkas_id = null`); `tautan` wajib berskema `http`/`https` bila `mode = tautan`; `isi_teks` wajib terisi bila `mode = teks`; `nama_asli`/`path`/`mime`/`ukuran_bytes` wajib terisi bila `mode = file` |
| `kegiatan` (level aplikasi) | transisi status `rencana → terlaksana` ditolak bila ada `jenis_berkas` aktif bertanda `wajib` bertahap `kegiatan` yang belum terpenuhi |
| `kegiatan` (level aplikasi) | tidak dihapus saat tidak terlaksana — wajib berubah status + `justifikasi`; geser periode wajib lewat baris baru + `kegiatan_asal_id` |
| `kegiatan` (level aplikasi) | `anggaran` wajib dan nonnegatif saat kegiatan dibuat atau diubah, `null` hanya data lama, kegiatan data awal 2026, dan kegiatan periode lampau 2026 (Q42 §42.2, Q46 §46.1 butir 3); `nama` hingga 500 karakter dan `tujuan` opsional (Q49 §49.1) |
| `rekomendasi_pimpinan` (level aplikasi dan basis data, Q51, Q55) | `disetujui_oleh`, `disetujui_oleh_role`, dan `disetujui_pada` terisi jika dan hanya jika `status = disetujui`; paling banyak satu baris `draf` per indikator × tahun × periode; baris `disetujui` tidak dapat diubah atau dihapus; yang berlaku = baris `disetujui` dengan `disetujui_pada` terbaru per indikator × tahun × periode (§2.31) |
| `user_permission_granted` (level aplikasi) | grant untuk permission `butuh_scope = unit` ditolak bila `unit_id` kosong; grant untuk permission `butuh_scope = global` ditolak bila `unit_id` terisi |
| `pengukuran`/`rencana_aksi` (level aplikasi, pemisahan tugas) | transisi verifikasi/pengesahan ditolak bila aktor = pengaju pada jalur izin ber-scope unit (F1, §4.1), kecuali jalur Perencanaan yang ditandai `self_approval` (F2, §4.2) |

---

## 6. Ringkasan Kolom/Entitas "Tersedia tapi Belum Dipakai" (Fase Awal)

Daftar eksplisit kolom/entitas yang ada di skema sejak migrasi pertama namun jalur pengisian/pemakaiannya ditunda ke Fase Lanjutan — dicantumkan agar tim pengembang tidak keliru mengira ini kolom yang "lupa dipakai" atau bug. Sejak Q41–Q45, daftar ini juga memuat kolom yang dipertahankan di skema tetapi **tidak dipakai lagi**:

| Tabel | Kolom/Aspek | Status Fase Awal |
|---|---|---|
| `jadwal_tahunan` | `pakai_persetujuan_pimpinan` | Selalu `false`; tidak dicek di logika alur pengesahan |
| `jadwal_tahunan` | `persetujuan_mulai` | Selalu `null`; tidak ditampilkan sebagai jendela aktif di UI |
| `jadwal_tahunan` | `persetujuan_selesai` | Selalu `null`; tidak ditampilkan sebagai jendela aktif di UI |
| `jadwal_tahunan` | `rencana_aksi_mulai`/`rencana_aksi_selesai` | **Keluar dari daftar ini:** kolom dan CHECK `jadwal_tahunan_urutan_ra` dihapus oleh migrasi #159 (§2.15). Semula: tidak dipakai lagi sejak Q41; jendela penyusunan rencana aksi dihapus dari form dan tidak dibaca |
| `pengaturan` | kunci grup `berkas` (`berkas.unggahan_aktif`, `berkas.ukuran_maks_kb`, `berkas.format_diizinkan`, `berkas.tautan_selalu_diizinkan`) | **Tidak dipakai lagi sejak Q44:** tidak dibaca maupun dikelola dari UI; batas teknis unggahan berasal dari konfigurasi server; baris dan audit lama tetap sebagai histori (§2.22) |
| `indikator` | `wajib_catatan` | **Tidak dipakai lagi sejak Q45:** disembunyikan dari form indikator dan tidak mewajibkan `catatan`; kolom dipertahankan (§2.12, §2.20) |
| `status_capaian` | `sumber` (nilai `data_sumber`) | Nilai enum ini valid secara skema namun **tidak pernah ditulis** oleh kode Fase Awal — seluruh baris ditulis dengan `sumber = 'manual'` |
| `kegiatan` | `anggaran` | **Keluar dari daftar ini sejak Q42:** anggaran diinput, ditampilkan, divalidasi, dan dijumlahkan (§2.25–§2.26); status implementasi: belum (ISS-06.08). Semula: jalur pengisian/pemakaian menyusul Fase Lanjutan dan Fase Awal tidak menampilkan maupun memvalidasi nilai ini (digantikan Q42) |
| `berkas` | `jenis_berkas_id` | **Nullable secara permanen**, bukan sementara — dipakai penuh sejak Fase Awal untuk membedakan lampiran wajib (mengacu `jenis_berkas`) dari lampiran bebas (`null`); dicantumkan di sini semata agar tidak disangka kolom yang seharusnya selalu terisi |
| `berkas` | `nama_asli`/`path`/`mime`/`ukuran_bytes` | **Nullable secara permanen sejak diperkenalkannya mode bukti dukung** — bukan kolom yang "lupa diisi": ketiganya wajib hanya pada baris `mode = file`, dan bernilai `NULL` pada baris `mode = tautan`/`teks` sebagai konsekuensi normal desain multi-mode |
| `user_roles` | constraint `unique(user_id)` | **Batas Fase Awal yang disadari.** Struktur pivot mendukung maksimum satu role ketika baris tersedia; user JIT boleh belum memiliki role. Multi-peran dapat dibuka di fase lanjutan dengan perubahan constraint/kontrak eksplisit. |
| UI pengelolaan akses | role-permission mutation | Fase Awal menyediakan 3 form mutasi (Assign Peran, Grant Unit, Deny), halaman “Jelaskan Izin Pengguna”, dan halaman “Peran & Izin” read-only. Tidak ada mutation UI untuk `role_permissions`; preset berubah hanya lewat code/seeder/release. |
| `user_permission_granted` | masa berlaku grant (`berlaku_sampai`) | **Belum berupa kolom skema pada Fase Awal** — dicatat di sini sebagai kebutuhan yang mungkin muncul di Fase Lanjutan (grant yang otomatis kedaluwarsa pada tanggal tertentu, mis. penugasan sementara). Bila dibutuhkan, penambahannya adalah migrasi kolom baru bertipe `date, nullable` pada `user_permission_granted`, bukan perubahan struktural |

Catatan tambahan: permission `pengukuran:setujui` dan peran approval Pimpinan juga "tersedia tapi belum dipakai" secara fungsional (bukan kolom skema, melainkan kode alur) — didefinisikan penuh di katalog permission (`permissions`, §2.3), tapi belum ada state machine/UI yang memanggilnya pada Fase Awal. Kolom `rekomendasi_pimpinan.ditetapkan_oleh` secara skema menerima id pengguna mana pun; semula pada Fase Awal selalu diisi pengguna Perencanaan dan pengisian oleh Pimpinan ditunda ke Fase Lanjutan — digantikan Q45 §45.2 dan Q51: Pimpinan mengisi dan menyetujui rekomendasi, Perencanaan menyusun draf dan menjadi cadangan bertanda (§2.31).

---

## 7. Prinsip Desain yang Mendasari Skema

1. UUID dipakai sebagai primary key di seluruh tabel — memudahkan referensi lintas tabel tanpa bocor informasi urutan/volume data, dan cukup aman untuk sinkronisasi/replikasi di masa depan bila diperlukan.
2. Snapshot ditempatkan di atas referensi langsung untuk konteks historis (`jadwal_snapshot`, `jadwal_snapshot_komponen`), memisahkan "apa yang berlaku sekarang" (master) dari "apa yang berlaku saat pengukuran dilakukan" (snapshot) — termasuk cara hitungnya, bukan hanya nilainya. Pembuatan baris snapshot dijaga idempoten dan baris beku sejak terbit; koreksi selalu lewat versi baru (Q35 §35.1), sehingga tidak ada jalur restatement data historis yang tidak sengaja.
3. Riwayat dicatat sebagai baris baru, bukan mutasi in-place — berlaku untuk `penanggung_jawab`, `status_capaian`, dan `rekomendasi_pimpinan`. Ketiga tabel ini sengaja tidak memiliki mekanisme update-in-place atas makna intinya (siapa PJ efektif / apa status capaian aktif / rekomendasi mana yang berlaku); nilainya dihitung dari baris terbaru — untuk rekomendasi, baris terbaru berstatus `disetujui` (Q51, §2.31).
4. Optimistic locking eksplisit (`pengukuran.versi`, `rencana_aksi.versi`) dipilih ketimbang mengandalkan `updated_at` sebagai penanda versi — integer lebih murah dibandingkan dan tidak rentan masalah presisi timestamp/timezone.
5. Audit diperlakukan sebagai warga kelas satu, bukan tempelan belakangan — `audit_log` dirancang append-only sejak awal dengan kolom `nilai_lama`/`nilai_baru` berformat JSONB agar fleksibel menampung struktur berbeda-beda per jenis entitas tanpa memerlukan tabel audit terpisah per entitas; cakupannya diperluas eksplisit untuk mencakup seluruh entitas baru pada alur rencana aksi, kegiatan, komponen, dan model akses RBAC (kolom `dasar_izin` khusus untuk aksi sensitif, §3.5).
6. Skema dibangun penuh sejak awal, fiturnya bertahap — filosofi ini menghindari migrasi besar/berisiko di kemudian hari dengan menyediakan kolom Fase Lanjutan sejak MVP (§6), sembari menjaga logika aplikasi Fase Awal tetap sederhana dan tidak memproses cabang kode yang belum relevan.
7. Pertahanan berlapis untuk aturan lintas-baris yang kompleks (rentang tahun Renstra beririsan, syarat aktivasi jadwal, idempotensi snapshot, keunikan klaim/grant/deny dengan kolom nullable): validasi utama selalu berada di service layer sebagai bagian dari satu transaksi atomik; constraint database (partial unique index, exclusion constraint, index unik ber-`COALESCE`) disertakan sebagai lapisan pertahanan kedua yang menutup celah race condition antar-request, bukan sebagai pengganti validasi aplikasi.
8. Penamaan entitas disesuaikan agar tidak bertabrakan dengan kosakata evaluasi eksternal — `unit` dipilih dan bukan "unit kerja" — sekaligus tetap mencerminkan makna aslinya sebagai kelompok organisasi pemilik indikator dan scope permission, bukan satuan ukur.
9. Perhitungan berbasis komponen bersifat data-driven dan satu tingkat — definisi cara hitung (`indikator.tipe_perhitungan`, `indikator_komponen`) hidup di data, dapat diubah lewat aplikasi tanpa deployment kode baru, tetapi sengaja dibatasi pada satu tingkat rasio/penjumlahan agar tetap dapat diverifikasi dan dibekukan penuh ke snapshot; formula bertingkat yang lebih kompleks diserahkan ke tipe `manual` alih-alih dipaksakan ke dalam mesin perhitungan yang belum tentu dapat memodelkannya dengan benar.
10. Klaim (dokumentasi keterkaitan) dan pengukuran (nilai capaian) dipisahkan tegas sebagai dua tabel berbeda dengan tanggung jawab berbeda — `klaim_kegiatan` murni mendokumentasikan dukungan dan arah dampak, sama sekali tidak menulis ke `pengukuran_komponen`/`rencana_aksi_target`, untuk mencegah penghitungan ganda yang sulit dideteksi bila kedua tanggung jawab itu digabung dalam satu mekanisme otomatis.
11. Model hak akses dipisah tegas menjadi **data yang dapat dipelihara** (peran dan isinya, `roles`/`role_permissions`) dan **pengecualian eksplisit per pengguna** (`user_permission_granted`/`user_permission_denials`), dievaluasi hidup saat request lewat satu service resolusi terpusat (§3) — bukan disalin ke baris statis per pengguna. Pemisahan ini membuat perubahan katalog/isi peran otomatis berlaku bagi seluruh pemegangnya, sekaligus memungkinkan sistem membedakan "tidak diberi" dari "sengaja dicabut", pembedaan yang dibutuhkan saat evaluasi AKIP/ZI mempertanyakan mengapa seseorang tidak dapat melakukan sesuatu meski perannya memungkinkan.
12. Pemisahan tugas (§4) sengaja ditempatkan sebagai validasi bisnis tetap di service layer, terpisah dari mekanisme deny yang dapat dikonfigurasi (§2.8) — supaya aturan "pengaju tidak boleh menyetujui pekerjaannya sendiri" tidak dapat dinonaktifkan lewat pemberian grant apa pun, termasuk oleh Superadmin.
13. **Role dan assignment indikator merupakan konsep berbeda setelah Q32.** `roles/user_roles` menyimpan klasifikasi utama pengguna, sedangkan `penanggung_jawab` menyimpan histori user yang ditugaskan ke indikator. PIC operasional tidak menghapus tabel assignment; eligibility final hanya mensyaratkan user aktif dan assignment tidak memberikan permission. Demikian pula, scope unit tetap hidup pada `user_permission_granted`, bukan pada `role_permissions`.

---


## 8. Catatan Penyelarasan Q32 — Baseline Final 24 September 2026

### 8.1 Role dan Authorization

- Role final: `superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`.
- Tidak ada PIC operasional (bukan role).
- `role_permissions` global dan read-only dari aplikasi; mutation hanya via code/seeder/release.
- Grant Unit memakai `delegasi:update` dan hanya 7 permission unit-scoped.
- Assign Role dan Deny memakai `akses:update`.
- User tanpa role = 0 permission.
- Deny selalu menang.

### 8.2 Onboarding User

JIT SSO membuat `users.status = nonaktif`, tanpa `user_roles`. Setelah Admin mengaktifkan dan memberikan role, resolver dapat menghasilkan permission efektif. *(Digantikan Q38: akun SSO baru langsung `aktif` tanpa peran; lihat §2.1.)*

### 8.3 Penanggung Jawab

Assignment historis berdiri sendiri dari role dan grant. Tidak ada FK/constraint yang mensyaratkan role tertentu. Query monitoring wajib dapat menemukan PJ aktif yang tidak mempunyai grant kerja yang dibutuhkan.

### 8.4 Periode Lampau 2026

Tidak ditambahkan `is_backfill`. Status periode lampau merupakan hasil perhitungan terhadap jendela pengisian pada waktu aktivasi Jadwal 2026. Tahun 2025 tidak dibuat sebagai pengukuran; hanya baseline.

> **Diperbarui Q46 (13 Oktober 2026):** TW I–III 2026 menjadi periode lampau yang diisi Perencanaan; TW IV 2026 menjadi triwulan pertama workflow normal PIC operasional, dengan pelaporan awal Januari 2027 (Q46 §46.1 butir 2). Gerbang baru Q42–Q45 — rencana aksi minimal satu kegiatan, RAB, persyaratan tahap `kegiatan_perencanaan`, narasi wajib, dan tindak lanjut rekomendasi — dikecualikan untuk periode lampau; kunci per periode (Q41 §41.2) bukan bagian pengecualian ini, karena periode lampau terkunci bagi PIC sejak aktivasi sedangkan Perencanaan yang mengisinya tidak terikat kunci (Q46 §46.1 butir 1, dikoreksi). RA 2026 yang dicatat sebagai data awal dikecualikan dari gerbang kegiatan minimal dan RAB, dan kegiatan data awal serta kegiatan periode lampau boleh tanpa anggaran (Q46 §46.1 butir 3). Data awal 2026 diisi Perencanaan dari dokumen resmi 2026; target periode lampau boleh kosong, sehingga target TW I–III dicatat kosong (Q46 §46.1 butir 6, Q47 §47.2 butir 4).

### 8.5 Komponen Indikator

- IKU 3: dua input `sakip` dan `zi_wbk`, formula `(sakip + zi_wbk) / 2`.
- IKU 8: rasio `n/t × 100%`, `t = total publikasi seluruh PTS wilayah kerja`, tanpa hardcode 84.

> **Catatan Q47 dan Q50 (13 Oktober 2026):** "IKU 3" dan "IKU 8" di atas memakai nomor Renstra; kode indikatornya pada PK, Rencana Aksi, dan laporan triwulan adalah **IKU 1.3** dan **IKU 3.3** (Q50 §50.4 butir 3, §2.12). IKU 1.3 ditandai dinilai tahunan pada data awal 2026 (Q47 §47.1 butir 4).

### 8.6 Migrasi dari Implementasi Lama

Jika database development telah memiliki row `roles.kode = pic`, migration corrective harus menghapus/menonaktifkan row tersebut secara aman setelah memastikan tidak ada foreign key aktif yang akan orphan. Fixture/test harus dibangun ulang untuk lima role. Data produksi belum memiliki user existing yang perlu dimigrasikan menjadi PIC.

---
