# SAKIP - Konfirmasi Permission dan Hak Akses PM

**Proyek:** SAKIP — LLDIKTI Wilayah XVI  
**Status:** Draf untuk Review & Sign-Off Project Manager  
**Versi:** 2.0 — Scope Terbatas pada Issue Aktif  
**Tanggal:** 20 September 2026  
**Repository:** `LLDIKTI-XVI-TEAM/SAKIP`  
**Target branch:** `development`

---

## 1. Tujuan Dokumen

Dokumen ini digunakan untuk mengonfirmasi **permission dan hak akses yang benar-benar dibutuhkan oleh issue SAKIP yang sudah ada saat ini**.

Dokumen ini **bukan**:

- spesifikasi seluruh permission SAKIP;
- pengganti PRD, Workflow, Data Model, atau Plan Pengembangan;
- persetujuan seluruh 70 permission untuk semua modul;
- dokumen untuk menambah fitur baru;
- dokumen untuk menentukan alur Rencana Aksi, Kegiatan, Pengukuran, Dashboard, Laporan, atau Notifikasi yang belum menjadi issue aktif.

Prinsip penyusunan:

> **Yang dikonfirmasi PM hanya aturan akses yang diperlukan untuk menyelesaikan issue yang sudah ada.**

Jika suatu permission belum dibutuhkan oleh issue aktif, permission tersebut tidak dimasukkan sebagai keputusan pada dokumen ini.

---

## 2. Baseline Issue yang Dicakup

Pada saat dokumen ini disusun, repository memiliki 10 issue pengembangan berikut.

| GitHub Issue | ID | Judul | Permission/Hak Akses yang Relevan |
|---:|---|---|---|
| #1 | `ISS-01.01` | Implement Integrasi SSO Keycloak LLDIKTI XVI | Tidak memakai permission aplikasi pada login/callback; RBAC berlaku setelah session terbentuk |
| #2 | `ISS-01.02` | Implement Pengelolaan Master Unit Organisasi | `unit:create`, `unit:read`, `unit:update`, `unit:delete` |
| #3 | `ISS-01.03` | Implement Penetapan Peran Utama Pengguna (Assign Role) | `akses:update`, `pengguna:read` |
| #4 | `ISS-01.04` | Implement Pemberian Grant Izin Tambahan per Unit | `akses:update` + permission dengan `butuh_scope=unit` |
| #5 | `ISS-01.05` | Implement Pencabutan Izin Eksplisit (Deny) | `akses:update` |
| #6 | `ISS-01.07` | Implement Perubahan Isi Role Permissions Secara Terkendali | mekanisme akses administratif terproteksi; perlu konfirmasi PM pada §8.3 |
| #7 | `ISS-02.01` | Implement Pencatatan Dokumen Dasar Regulasi | `regulasi:create`, `regulasi:read`, `regulasi:update`, `regulasi:delete` |
| #8 | `ISS-13.01` | Implement Setelan Identitas & Preferensi Presentasional | `pengaturan:update` |
| #9 | `ISS-11.01` | Implement Konfigurasi Persyaratan Jenis Berkas | `jenis_berkas:create`, `jenis_berkas:read`, `jenis_berkas:update`, `jenis_berkas:delete` |
| #10 | `ISS-13.02` | Implement Kebijakan Storage & Saklar Unggah File | `pengaturan:update` |

Dengan batas tersebut, dokumen ini tidak memperluas scope ke issue lain yang belum dibuat.

---

## 3. Role Sistem yang Berlaku

Berdasarkan klarifikasi terbaru LLDIKTI Wilayah XVI, SAKIP memiliki enam role resmi:

| Kode | Label |
|---|---|
| `superadmin` | Super Admin |
| `admin` | Admin |
| `perencanaan` | Perencanaan |
| `pic` | PIC |
| `pimpinan` | Pimpinan |
| `pegawai` | Pegawai |

### 3.1 Batas keputusan role pada dokumen ini

Dokumen ini hanya membutuhkan keputusan berikut untuk Sprint/issue aktif:

1. keenam role harus tersedia pada katalog role;
2. Form Assign Role pada `ISS-01.03` harus dapat memilih keenam role;
3. satu user tetap memiliki satu role utama pada MVP;
4. role tidak ditentukan oleh Keycloak/SSO — SSO hanya menentukan identitas;
5. perubahan role harus diaudit.

Dokumen ini **tidak menentukan preset permission penuh role PIC** untuk seluruh sistem.

Preset PIC untuk modul Rencana Aksi, Kegiatan, Pengukuran, Dashboard, Laporan, dan modul lain tetap di luar scope dokumen ini sampai issue terkait masuk pengerjaan dan ada keputusan bisnis yang sesuai.

---

## 4. Prinsip Akses yang Berlaku untuk Issue Aktif

### 4.1 Authorization wajib server-side

Semua endpoint terproteksi harus mengevaluasi izin di backend melalui Policy/Gate/resolver.

React/Inertia hanya boleh menggunakan hasil seperti:

```text
can.*
```

untuk mengatur tampilan UI.

Menyembunyikan tombol di frontend tidak menggantikan authorization backend.

### 4.2 Unknown permission = fail closed

Kode permission yang tidak dikenal atau tidak aktif harus ditolak.

### 4.3 Deny menang

Jika user memiliki allow dari role atau grant, tetapi terdapat deny yang cocok, hasil akhirnya tetap:

```text
DENY
```

### 4.4 Grant tidak mengubah role

Grant unit:

- tidak mengubah `user_roles`;
- tidak otomatis menjadikan user sebagai role PIC;
- tidak menghapus deny;
- hanya menambah allow sesuai permission dan scope yang diberikan.

### 4.5 Aksi sensitif wajib dapat diaudit

Untuk permission sensitif yang termasuk scope issue aktif:

- alasan wajib bila issue mensyaratkannya;
- aktor dicatat;
- waktu dicatat;
- nilai lama/baru dicatat bila relevan;
- sumber keputusan izin (`dasar_izin`) dicatat.

---

## 5. Permission yang Masuk Scope Dokumen Ini

Hanya **15 kode permission utama** berikut yang perlu dikonfirmasi sebagai bagian langsung dari 10 issue aktif.

| No | Permission | Issue | Scope | Sensitif | Default Role yang Relevan |
|---:|---|---|---|---|---|
| 1 | `pengguna:read` | `ISS-01.03` | Global | Tidak | Admin, Superadmin |
| 2 | `akses:update` | `ISS-01.03`, `ISS-01.04`, `ISS-01.05`, terkait `ISS-01.07` | Global | **Ya** | Admin, Superadmin |
| 3 | `unit:create` | `ISS-01.02` | Global | Tidak | Admin, Superadmin |
| 4 | `unit:read` | `ISS-01.02` | Global | Tidak | Admin, Superadmin |
| 5 | `unit:update` | `ISS-01.02` | Global | Tidak | Admin, Superadmin |
| 6 | `unit:delete` | `ISS-01.02` | Global | **Ya** | **Superadmin saja** |
| 7 | `regulasi:create` | `ISS-02.01` | Global | Tidak | Perencanaan, Superadmin |
| 8 | `regulasi:read` | `ISS-02.01` | Global | Tidak | Lihat §5.1 |
| 9 | `regulasi:update` | `ISS-02.01` | Global | **Ya** | Perencanaan, Superadmin |
| 10 | `regulasi:delete` | `ISS-02.01` | Global | **Ya** | Perencanaan, Superadmin |
| 11 | `pengaturan:update` | `ISS-13.01`, `ISS-13.02` | Global | **Ya** | Admin, Superadmin |
| 12 | `jenis_berkas:create` | `ISS-11.01` | Global | Tidak | Perencanaan, Superadmin |
| 13 | `jenis_berkas:read` | `ISS-11.01` | Global | Tidak | Lihat §5.1 |
| 14 | `jenis_berkas:update` | `ISS-11.01` | Global | **Ya** | Perencanaan, Superadmin |
| 15 | `jenis_berkas:delete` | `ISS-11.01` | Global | **Ya** | Perencanaan, Superadmin |

### 5.1 Dua permission read yang memerlukan konfirmasi PIC

Baseline permission lama memberi:

- `regulasi:read`
- `jenis_berkas:read`

kepada seluruh lima role lama:

```text
Superadmin
Admin
Perencanaan
Pimpinan
Pegawai
```

Setelah Q31, role `pic` menjadi role keenam.

Dokumen ini **tidak mengasumsikan** apakah dua permission read tersebut otomatis diberikan kepada PIC.

**Konfirmasi PM:**

- [ ] **SETUJU:** role PIC juga memperoleh `regulasi:read` dan `jenis_berkas:read` secara default.
- [ ] **TIDAK:** role PIC tidak memperoleh keduanya secara default.
- [ ] **ADA CATATAN:** _______________________________________________

Sampai pilihan ini dikonfirmasi, implementasi preset PIC untuk dua permission tersebut berstatus **OPEN**.

---

## 6. Katalog Permission Scope Unit yang Dibutuhkan `ISS-01.04`

`ISS-01.04 — Pemberian Grant Izin Tambahan per Unit` membutuhkan katalog permission yang dapat dipilih pada Form Grant.

Berdasarkan dokumen permission yang ada, terdapat 9 permission dengan scope unit:

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

### Penting — bukan perluasan scope

Pencantuman 9 kode di atas **tidak berarti** issue Sprint 1 mengimplementasikan:

- halaman Rencana Aksi;
- CRUD Kegiatan;
- pengisian Pengukuran;
- workflow verifikasi/pengesahan;
- aturan jadwal modul tersebut.

Kode-kode tersebut hanya dibutuhkan sebagai **data katalog** agar mekanisme Grant Unit pada `ISS-01.04` dapat dibuat dan diuji secara generik.

### Konfirmasi PM

- [ ] **SETUJU:** Form Grant Unit hanya menerima permission berkatalog `butuh_scope=unit`, termasuk 9 kode di atas.
- [ ] **ADA CATATAN:** _______________________________________________

---

## 7. Hak Akses per Issue Aktif

### 7.1 `ISS-01.01` — SSO Keycloak

**Hak akses yang dikonfirmasi:**

- route login/pemicu redirect SSO: publik;
- callback OIDC: publik tetapi tervalidasi;
- halaman aplikasi setelah login: membutuhkan session Laravel;
- SSO hanya menentukan identitas user;
- role/permission/grant/deny tetap dikelola SAKIP;
- tidak ada permission aplikasi baru untuk login/callback.

**Di luar scope dokumen permission:**

- desain halaman login Keycloak;
- password lokal;
- reset password;
- permission modul sesudah login.

**Konfirmasi PM:**

- [ ] **SETUJU** dengan pemisahan identitas SSO dan authorization SAKIP.
- [ ] **ADA CATATAN:** _______________________________________________

---

### 7.2 `ISS-01.02` — Master Unit Organisasi

| Aksi | Admin | Superadmin |
|---|:---:|:---:|
| Baca unit | ✅ | ✅ |
| Tambah unit | ✅ | ✅ |
| Ubah/nonaktifkan unit | ✅ | ✅ |
| Hapus unit kosong | ❌ | ✅ |

Aturan tambahan:

- `unit:delete` hanya Superadmin;
- `unit:delete` merupakan aksi sensitif;
- unit yang masih memiliki histori/relasi terlindungi tidak boleh dihapus, termasuk oleh Superadmin;
- untuk unit berhistori gunakan nonaktif.

**Konfirmasi PM:**

- [ ] **SETUJU** — Admin tidak memiliki `unit:delete`; hanya Superadmin dapat menghapus unit yang benar-benar kosong.
- [ ] **ADA CATATAN:** _______________________________________________

---

### 7.3 `ISS-01.03` — Assign Role

Aktor yang dapat mengakses fitur:

```text
Admin
Superadmin
```

Permission:

```text
pengguna:read
akses:update
```

Aturan:

- enam role tersedia;
- tepat satu role utama per user pada MVP;
- alasan perubahan role wajib;
- perubahan diaudit;
- mengganti role tidak menulis ulang histori/provenance data lama.

**Konfirmasi PM:**

- [ ] **SETUJU** — Admin dan Superadmin dapat melakukan Assign Role.
- [ ] **SETUJU** — role PIC tersedia sebagai pilihan keenam.
- [ ] **ADA CATATAN:** _______________________________________________

---

### 7.4 `ISS-01.04` — Grant Permission per Unit

Aktor pengelola:

```text
Admin
Superadmin
```

Permission pengelola:

```text
akses:update
```

Aturan Grant:

- hanya permission `butuh_scope=unit`;
- user target wajib valid;
- unit target wajib valid;
- alasan wajib;
- kombinasi user-permission-unit tidak boleh duplikat;
- grant dapat diberikan tanpa mengubah role utama target;
- pencabutan grant diaudit;
- deny yang cocok tetap dapat mengalahkan grant.

**Konfirmasi PM:**

- [ ] **SETUJU** — Admin dan Superadmin dapat membuat/mencabut grant unit.
- [ ] **SETUJU** — grant tidak dibatasi hanya kepada role tertentu.
- [ ] **ADA CATATAN:** _______________________________________________

---

### 7.5 `ISS-01.05` — Explicit Deny

Aktor pengelola:

```text
Admin
Superadmin
```

Permission:

```text
akses:update
```

Jenis deny:

```text
Global deny
Unit-scoped deny
```

Aturan:

1. deny yang cocok selalu menang terhadap allow role/grant;
2. deny unit A tidak otomatis memblokir unit B;
3. deny global memblokir permission pada seluruh scope yang relevan;
4. revoke deny mengembalikan evaluasi ke role/grant yang masih sah;
5. create/revoke deny wajib diaudit.

**Konfirmasi PM:**

- [ ] **SETUJU** dengan prinsip **deny wins**.
- [ ] **SETUJU** dengan deny global dan deny per-unit.
- [ ] **ADA CATATAN:** _______________________________________________

---

### 7.6 `ISS-01.07` — Perubahan Isi Role Permissions

Issue ini memungkinkan penambahan/pencabutan permission dari `role_permissions` melalui mekanisme terkontrol.

Yang disepakati dari issue:

- perubahan tidak dilakukan dengan query SQL manual;
- alasan wajib;
- before/after diaudit;
- hasil berlaku pada seluruh pemegang role pada request berikutnya;
- deny individual tetap menang;
- tidak perlu membuat UI matrix permission penuh.

**Satu hal yang masih perlu keputusan PM adalah siapa aktornya.**

Lihat §8.3.

---

### 7.7 `ISS-02.01` — Dokumen Dasar Regulasi

| Permission | Admin | Superadmin | Perencanaan | Pimpinan | Pegawai | PIC |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| `regulasi:read` | ✅ | ✅ | ✅ | ✅ | ✅ | **OPEN** |
| `regulasi:create` | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `regulasi:update` | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `regulasi:delete` | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |

Aturan:

- `regulasi:update` sensitif;
- `regulasi:delete` sensitif;
- delete tetap tunduk delete guard issue;
- lampiran regulasi mengikuti akses terhadap induk Regulasi pada scope issue ini;
- dokumen ini tidak mengesahkan entitlement generik seluruh `berkas:*`.

**Konfirmasi PM:**

- [ ] **SETUJU** — create/update/delete hanya Perencanaan & Superadmin.
- [ ] **SETUJU** — Admin hanya read.
- [ ] **ADA CATATAN:** _______________________________________________

---

### 7.8 `ISS-13.01` — Pengaturan Presentasional

Permission:

```text
pengaturan:update
```

Default actor:

```text
Admin
Superadmin
```

Aturan:

- hanya whitelist setting presentasional yang ada pada issue;
- setting tidak boleh digunakan untuk mengubah role, permission, enum, status, atau aturan bisnis;
- perubahan diaudit;
- `pengaturan:update` sensitif.

**Konfirmasi PM:**

- [ ] **SETUJU** — Admin & Superadmin dapat mengubah setting presentasional.
- [ ] **SETUJU** — tabel pengaturan tidak digunakan sebagai bypass business rule.
- [ ] **ADA CATATAN:** _______________________________________________

---

### 7.9 `ISS-11.01` — Persyaratan Jenis Berkas

| Permission | Admin | Superadmin | Perencanaan | Pimpinan | Pegawai | PIC |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| `jenis_berkas:read` | ✅ | ✅ | ✅ | ✅ | ✅ | **OPEN** |
| `jenis_berkas:create` | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `jenis_berkas:update` | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `jenis_berkas:delete` | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |

Aturan:

- `jenis_berkas:update` sensitif;
- `jenis_berkas:delete` sensitif;
- perubahan master tidak boleh mengubah requirement snapshot submission lama;
- detail formula/flow submission bukan scope konfirmasi permission ini.

**Konfirmasi PM:**

- [ ] **SETUJU** — create/update/delete hanya Perencanaan & Superadmin.
- [ ] **SETUJU** — Admin hanya read.
- [ ] **ADA CATATAN:** _______________________________________________

---

### 7.10 `ISS-13.02` — Storage & Upload Policy

Permission:

```text
pengaturan:update
```

Default actor:

```text
Admin
Superadmin
```

Aturan:

- Admin/Superadmin dapat mengubah saklar upload dan fallback teknis yang memang ditentukan issue;
- statistik storage pada halaman yang sama tidak memperkenalkan permission baru;
- setting teknis tidak boleh mengubah substansi `jenis_berkas`;
- perubahan diaudit;
- `pengaturan:update` sensitif.

**Konfirmasi PM:**

- [ ] **SETUJU** — tidak diperlukan permission baru seperti `storage:update` atau `storage:read` untuk issue ini.
- [ ] **SETUJU** — seluruh mutasi issue menggunakan `pengaturan:update`.
- [ ] **ADA CATATAN:** _______________________________________________

---

## 8. Keputusan PM yang Benar-Benar Masih Terbuka

Bagian ini sengaja dibatasi pada keputusan yang memengaruhi issue aktif.

### 8.1 Hak read untuk role PIC pada dua issue master

Apakah role PIC mendapat default:

```text
regulasi:read
jenis_berkas:read
```

- [ ] YA
- [ ] TIDAK
- [ ] CATATAN: _______________________________________________

---

### 8.2 Hak Admin untuk Assign Role, Grant, dan Deny

Baseline issue menggunakan:

```text
Admin
Superadmin
```

untuk:

- Assign Role;
- Grant per Unit;
- Explicit Deny.

- [ ] **SETUJU**
- [ ] **UBAH:** _______________________________________________

---

### 8.3 Siapa yang boleh mengubah `role_permissions` pada `ISS-01.07`

Issue saat ini menyebut:

> Superadmin / pengelola teknis berwenang.

Agar tidak memperluas makna `akses:update` secara diam-diam, PM perlu memilih:

- [ ] **OPS-1 — Hanya Superadmin** boleh menambah/mencabut permission dari role.
- [ ] **OPS-2 — Admin dan Superadmin** boleh menambah/mencabut permission dari role.
- [ ] **OPS-3 — Lainnya:** _______________________________________________

Rekomendasi implementasi baru boleh mengikuti pilihan yang ditandatangani PM.

---

### 8.4 Validasi `unit:delete`

Konfirmasi:

- [ ] hanya Superadmin;
- [ ] unit wajib benar-benar kosong dari relasi yang dilindungi;
- [ ] alasan wajib;
- [ ] audit wajib;
- [ ] unit berhistori dinonaktifkan, bukan hard-delete.

Catatan PM: _______________________________________________

---

### 8.5 Daftar permission sensitif dalam scope issue aktif

Dalam **scope 10 issue saat ini**, permission yang ditandai sensitif adalah:

```text
akses:update
unit:delete
regulasi:update
regulasi:delete
pengaturan:update
jenis_berkas:update
jenis_berkas:delete
```

Total dalam scope dokumen ini:

```text
7 permission sensitif
```

Dokumen ini **tidak membahas jumlah sensitive permission untuk seluruh sistem**, karena permission modul lain belum menjadi scope issue aktif.

- [ ] **SETUJU**
- [ ] **ADA CATATAN:** _______________________________________________

---

## 9. Ringkasan Hak Akses Sprint/Issue Aktif

### Superadmin

Dalam scope issue aktif:

- kelola unit termasuk delete unit kosong;
- assign role;
- grant;
- deny;
- kelola Regulasi;
- kelola Pengaturan;
- kelola Jenis Berkas;
- perubahan role permissions sesuai keputusan §8.3.

### Admin

Dalam scope issue aktif:

- baca user untuk kebutuhan akses;
- assign role;
- grant;
- deny;
- create/read/update Unit;
- **tidak** delete Unit;
- read Regulasi;
- update Pengaturan;
- read Jenis Berkas;
- perubahan role permissions hanya jika PM memilih OPS-2 pada §8.3.

### Perencanaan

Dalam scope issue aktif:

- read Regulasi;
- create/update/delete Regulasi;
- read Jenis Berkas;
- create/update/delete Jenis Berkas.

Tidak memperoleh hak kelola user/role/grant/deny/unit/pengaturan dari dokumen ini.

### Pimpinan

Dalam scope issue aktif:

- read Regulasi;
- read Jenis Berkas.

Dokumen ini tidak membahas dashboard/laporan karena bukan issue aktif yang sedang dikonfirmasi.

### Pegawai

Dalam scope issue aktif:

- read Regulasi;
- read Jenis Berkas.

Grant dapat diberikan oleh Admin/Superadmin melalui mekanisme `ISS-01.04`, tetapi grant tidak mengubah role.

### PIC

Role PIC tersedia pada Assign Role.

Hak default:

- `regulasi:read` → **OPEN §8.1**
- `jenis_berkas:read` → **OPEN §8.1**

Hak modul lain tidak ditetapkan oleh dokumen ini.

---

## 10. Di Luar Scope Dokumen Ini

Agar scope tidak melebar, hal berikut **sengaja tidak dimintakan sign-off melalui dokumen ini**:

- seluruh matrix 70 permission SAKIP;
- permission Dashboard;
- permission Laporan/Ekspor;
- permission Rencana Aksi selain daftar kode scoped yang dibutuhkan Form Grant;
- permission Kegiatan selain daftar kode scoped yang dibutuhkan Form Grant;
- permission Pengukuran selain daftar kode scoped yang dibutuhkan Form Grant;
- verifikasi/pengesahan RA atau Pengukuran;
- `pengukuran:setujui`;
- `status_capaian:update`;
- `rekomendasi:tetapkan`;
- hak baca Audit Log;
- pengelolaan Periode/Jadwal;
- notifikasi;
- preset permission penuh role PIC;
- apakah hanya role PIC yang boleh menjadi `penanggung_jawab`;
- mapping/migrasi akun Pegawai menjadi PIC;
- F1/F2 detail pada workflow RA/Pengukuran;
- permission generic `berkas:*` di luar kebutuhan attachment issue yang sedang dikerjakan;
- fitur baru yang belum memiliki issue.

Keputusan tersebut harus dibahas ketika issue terkait benar-benar masuk scope atau ketika PM secara eksplisit meminta perluasan baseline.

---

## 11. Aturan Implementasi Setelah Sign-Off

Setelah PM memberi keputusan pada §8:

1. developer hanya mengimplementasikan permission yang dibutuhkan issue masing-masing;
2. backend tetap menjadi sumber keputusan authorization;
3. frontend hanya memakai `can.*`;
4. perubahan permission harus memiliki automated test sesuai issue;
5. sensitive action pada scope issue harus memiliki audit yang sesuai;
6. keputusan PM tidak otomatis membuat issue baru;
7. jika keputusan mengubah acceptance criteria issue existing, issue terkait harus diperbarui terlebih dahulu sebelum implementasi/merge;
8. permission yang tidak tercantum pada dokumen ini tidak boleh dianggap otomatis disetujui.

---

## 12. Checklist Sign-Off PM

### Role & Access Foundation

- [ ] Enam role sistem diakui untuk Assign Role.
- [ ] SSO hanya menentukan identitas; authorization dikelola SAKIP.
- [ ] Satu user satu role pada MVP.
- [ ] Deny menang terhadap allow.
- [ ] Grant tidak mengubah role.

### Unit & Access Management

- [ ] Admin dapat create/read/update Unit.
- [ ] `unit:delete` hanya Superadmin.
- [ ] Unit berhistori tidak boleh hard-delete.
- [ ] Admin/Superadmin dapat Assign Role.
- [ ] Admin/Superadmin dapat Grant Unit.
- [ ] Admin/Superadmin dapat membuat/revoke Deny.
- [ ] Aktor pengubah `role_permissions` sudah dipilih pada §8.3.

### Regulasi

- [ ] Create/update/delete: Perencanaan + Superadmin.
- [ ] Read: lima role baseline lama.
- [ ] Hak read PIC sudah diputuskan pada §8.1.

### Pengaturan

- [ ] `pengaturan:update`: Admin + Superadmin.
- [ ] Berlaku untuk issue Presentasional dan Storage.
- [ ] Setting tidak boleh dipakai mengubah business rule/permission.

### Jenis Berkas

- [ ] Create/update/delete: Perencanaan + Superadmin.
- [ ] Read: lima role baseline lama.
- [ ] Hak read PIC sudah diputuskan pada §8.1.

### Audit

- [ ] 7 permission sensitif dalam scope issue aktif pada §8.5 telah disetujui.
- [ ] Alasan/before-after/`dasar_izin` diterapkan sesuai kebutuhan issue.

---

## 13. Catatan Keputusan PM

Gunakan bagian ini bila terdapat perubahan dari opsi yang tersedia.

| No | Topik | Keputusan / Catatan PM |
|---:|---|---|
| 1 | Hak `regulasi:read` untuk PIC | |
| 2 | Hak `jenis_berkas:read` untuk PIC | |
| 3 | Aktor pengubah `role_permissions` | |
| 4 | `unit:delete` | |
| 5 | Grant per Unit | |
| 6 | Explicit Deny | |
| 7 | Permission sensitif dalam scope | |
| 8 | Catatan lain yang masih dalam 10 issue aktif | |

---

## 14. Lembar Pengesahan

Dengan menandatangani dokumen ini, Project Manager mengonfirmasi **hanya permission dan hak akses yang berada dalam scope 10 issue yang tercantum pada §2**.

Sign-off ini tidak otomatis mengesahkan permission modul di luar scope.

| Disusun Oleh | Ditinjau & Disetujui Oleh |
|---|---|
| **Tim Pengembang SAKIP** | **Project Manager SAKIP** |
| Nama: ______________________________ | Nama: ______________________________ |
| Tanggal: 20 September 2026 | Tanggal: ______________________________ |
| Tanda tangan: _______________________ | Tanda tangan: _______________________ |

---

## 15. Traceability Ringkas

| Keputusan | Issue Terkait |
|---|---|
| SSO hanya identitas | `ISS-01.01` |
| CRUD Unit + delete Superadmin | `ISS-01.02` |
| Assign 1 role utama | `ISS-01.03` |
| Grant permission unit | `ISS-01.04` |
| Explicit deny / deny wins | `ISS-01.05` |
| Perubahan `role_permissions` | `ISS-01.07` |
| Permission Regulasi | `ISS-02.01` |
| `pengaturan:update` presentasional | `ISS-13.01` |
| Permission Jenis Berkas | `ISS-11.01` |
| `pengaturan:update` storage | `ISS-13.02` |

---

**Catatan akhir:** apabila issue baru ditambahkan setelah dokumen ini ditandatangani, permission issue baru tidak otomatis dianggap tercakup. Tambahkan addendum atau revisi dokumen hanya untuk permission yang benar-benar dibutuhkan issue baru tersebut.
