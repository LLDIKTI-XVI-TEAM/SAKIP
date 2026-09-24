# SAKIP — Keputusan Penyelarasan Dokumen

Tanggal baseline awal: **18 September 2026**  
Pembaruan terakhir: **20 September 2026**  
Branch acuan: `development`  
Commit branch saat pembaruan terakhir: `6307164c39b084a81e9827499c26f692e1d27576`

> Dokumen ini menjadi catatan keputusan penyelarasan lintas dokumen SAKIP. PRD menetapkan perilaku produk, Data Model menetapkan struktur dan integritas data, Workflow menetapkan alur, Plan Pengembangan menetapkan task/dependency/Definition of Done, User Stories dan User Issues menerjemahkan kontrak tersebut ke kebutuhan dan pekerjaan implementasi. Bila terdapat keputusan bisnis baru yang menggantikan baseline lama, perubahan harus terlebih dahulu dicatat di dokumen ini lalu diselaraskan ke seluruh sumber terdampak.

---

## Status dan dasar keputusan

Pengguna menyampaikan bahwa PM telah memperbarui Q4 pada branch `development` dan menyetujui penggunaan rekomendasi per pertanyaan grill untuk sisanya, lalu meminta dokumen diselaraskan. Dokumen ini mencatat hasil penyelarasan tersebut; bukan klaim bahwa implementasi, UAT, penyediaan layanan, atau pengesahan setiap angka operasional sudah selesai.

Pembaruan **20 September 2026 (Q31)** sempat mencatat enam role berdasarkan klarifikasi awal. **Klarifikasi final 24 September 2026 (Q32) menggantikan Q31 untuk implementasi aktif:** role bawaan final berjumlah lima dan PIC bukan role sistem. Histori Q31 dipertahankan untuk traceability.

1. `superadmin`
2. `admin`
3. `perencanaan`
4. `pic`
5. `pimpinan`
6. `pegawai`

Keputusan ini menggantikan baseline lama yang hanya mendefinisikan lima role (`superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`).

Prinsip penyelarasan yang tetap berlaku:

- Q4 mengikuti perubahan PM pada `d04ae5e`. Klarifikasi langsung pengguna menetapkan pengingat **hanya H-7, H-3, H-1**; scheduler yang berjalan harian tidak berarti pesan dikirim setiap hari.
- Q5 mengikuti rekomendasi terbaru setelah analisis Excel: **empat subskor SAKIP dan satu nilai ZI**, dengan subtotal SAKIP dan hasil gabungan ditampilkan. Ini menggantikan rekomendasi awal dua nilai final.
- Q31 menetapkan enam role resmi dan menambahkan `pic` sebagai role sistem. Keputusan ini **tidak menghapus mekanisme `penanggung_jawab` indikator** karena role dan penugasan indikator menjawab dua hal yang berbeda.
- PRD menetapkan perilaku, Data Model menetapkan struktur/integritas, Workflow menetapkan alur, Plan menetapkan task/Dependency/DoD. Perubahan pada satu sumber harus diselaraskan ke sumber terdampak; tanggal file bukan hierarki pengesahan otomatis.
- PM mengelola baseline dan keputusan pengganti; Tim Perencanaan mengesahkan aturan bisnis/definisi angka serta UAT; penanggung jawab teknis menetapkan implementasi. Nama penerima walkthrough/UAT dan pemilik layanan tetap harus dicatat.
- Parameter yang belum tersedia tetap terbuka. Persetujuan rekomendasi untuk meminta definisi/pihak/tanggal tidak otomatis menyediakan jawabannya.
- Informasi dari SSO/Keycloak hanya menentukan identitas pengguna. Role, permission, grant, deny, penugasan PIC, dan seluruh business guard tetap dikelola oleh SAKIP.

---

## Pemetaan Q1–Q31

| ID | Ketentuan yang diadopsi | Rujukan utama |
|---|---|---|
| Q1 | PM mengelola baseline/catatan keputusan; Perencanaan mengesahkan domain dan UAT; teknis menetapkan implementasi. | Pembuka PRD; Plan P.4 |
| Q2 | Pisahkan siap diuji, evaluasi/perbaikan, diterima, dan produksi. Target integrasi sebelum 9 November 2026 dan evaluasi/revisi 16–30 November berasal dari Q4 PM; tanggal penerimaan/go-live belum ditetapkan. | PRD pembuka, §28, §32; Plan P.4 |
| Q3 | Tetapkan daftar penggunaan pertama: tahun/periode, indikator, unit/PIC, data awal dan arsip wajib; Perencanaan memverifikasi sebelum aktivasi. Input manual/backfill, tanpa menambah impor. | PRD pembuka, §12.6; Plan S.1/P.4 |
| Q4 | In-app baseline MVP; WA/email sebelum 9 November 2026. Pembukaan jadwal, pengingat PIC H-7/H-3/H-1, rekap Perencanaan H-3/H-1, serta pengembalian berkas. | PRD §28; Workflow §22; Plan 14; Model pengaturan |
| Q5 | IKU 3 lima input, masing-masing koefisien 0,5; subtotal SAKIP merupakan tampilan turunan. Mesin formula bertingkat generik tetap ditunda. | PRD §17.10–17.11; Plan 5.13/5.17 |
| Q6 | Definisi pembilang/penyebut/satuan/waktu/sumber IKU 8 harus ditetapkan Perencanaan sebelum produksi. Tidak memilih jumlah PTS hanya karena contoh 84. | PRD §17.11.2 |
| Q7 | Indikator manual punya satu target langsung per periode; nonmanual target komponen dan skor turunan; tanpa komponen semu. | PRD §14.3; Model rencana_aksi_target |
| Q8 | Realisasi memakai basis yang sebanding dengan target kumulatif dan definisi per indikator; rasio tidak dijumlahkan dan populasi tidak dihitung ganda. | PRD §14.4, §17 |
| Q9 | Penyebut nol faktual dengan komponen lengkap boleh diajukan/disahkan beralasan sebagai tidak dapat dihitung; dibedakan dari belum diisi dan bukan nol. | PRD §17.4, §19.4, §23 |
| Q10 | Koreksi salah input terhadap sumber resmi menerbitkan versi snapshot pengganti beralasan/bukti, mempertahankan versi lama dan memerlukan pengesahan baru untuk hasil resmi yang dikoreksi. | PRD §12.5–12.7; Model jadwal_snapshot |
| Q11 | Laporan disahkan mempertahankan target RA, klaim, narasi dan bukti yang diperiksa; perubahan kerja tidak memperbarui laporan historis diam-diam. | PRD §14.6, §22.4; Model tabel versi |
| Q12 | Detail RA/kegiatan dan bukti PIC dibatasi unit berizin, termasuk deny; ringkasan umum tetap sesuai baseline. | PRD §7; Model permission; Workflow §19 |
| Q13 | Jalur kerja PIC atas RA/pengukuran memerlukan cakupan unit yang sah dan PIC indikator efektif; setelah Q31, jalur operasional normal juga menggunakan role `pic`. Perencanaan tetap memakai kewenangan global; kegiatan tetap kolaboratif sesuai permission unit. | PRD §7, §13, §14.8, §19.3; Q31 |
| Q14 | Admin tidak memiliki izin substantif secara bawaan; grant eksplisit beralasan dapat memberi pengecualian sesuai katalog, tetap tunduk deny/scope/business guard/waktu/F1. Grant tidak otomatis mengubah role seseorang menjadi PIC dan tidak otomatis menjadikannya penanggung jawab indikator. | PRD §7.3–7.6; Q31 |
| Q15 | F1/F2 berlaku untuk RA dan Pengukuran. Gunakan identitas/jalur pengaju yang dibekukan saat pengajuan, bukan pembuat draft atau role terkini; self-approval Perencanaan ditandai. | PRD §7.6; Workflow §20; Model versi |
| Q16 | Jadwal normal mewajibkan jendela RA selesai sebelum pengisian pertama; indikator baru/revisi/backfill/pembukaan resmi memakai jalur pengecualian teraudit. | PRD §12.3; Plan 3.4 |
| Q17 | Perencanaan dapat menyelesaikan reviu sampai penutupan tahunan; lewat batas reviu diberi penanda terlambat. | PRD §12.3, §31 |
| Q18 | Perencanaan berwenang dapat mengubah jendela resmi PIC dengan alasan, batas baru, dan audit batas lama. Di luar jendela PIC tetap terkunci. | PRD §12.3; Plan 3.12 |
| Q19 | Koreksi setelah penutupan memerlukan buka kembali dengan lingkup dan durasi eksplisit; tanggal penutupan asli dipertahankan. PIC memerlukan aksi pembukaan jendela terpisah Q18 dalam waktu/lingkup sesi koreksi, bukan otomatis dari buka tahun. | PRD §12.6, §19.5 |
| Q20 | Indikator baru memiliki periode mulai berlaku; periode sebelumnya Tidak berlaku dan tidak menjadi target/missing/nol. | PRD §10.5, §14.7, §23 |
| Q21 | Backfill dapat menerima skor historis final tanpa komponen, dengan sumber/alasan/penanda dan alur pengesahan; tidak mengubah tipe master atau membuat komponen rekaan. | PRD §12.6, §17.6 |
| Q22 | Bukti kegiatan memakai union persyaratan global dan indikator yang diklaim; klaim baru setelah terlaksana memeriksa bukti tambahan. | PRD §15.6; Workflow §8–10 |
| Q23 | Saat unggahan mati, hanya kewajiban mode file dikecualikan; mode lain tetap diwajibkan sesuai kombinasi. Penanda/audit menjelaskan pengecualian. Pengecualian khusus lampiran PK existing tetap berlaku. | PRD §18.5–18.7 dan §12.4 |
| Q24 | Persyaratan substantif dibekukan saat versi diajukan. Perubahan berlaku pengajuan berikutnya; proses berjalan harus dikembalikan beralasan jika ingin memakai ketentuan baru; hasil sah tidak otomatis invalid. | PRD §18.11; Model tabel versi |
| Q25 | Koreksi bukti kegiatan append-only: bukti baru, alasan dan hubungan menggantikan, dengan bukti lama tetap utuh; bukan rollback status untuk menghapus. | PRD §15.6, §18.8 |
| Q26 | Koreksi klaim mengikuti sumbernya: perencanaan mengikuti RA, pengukuran mengikuti pengukuran terkait; versi historis tidak dihapus. | PRD §16.5 |
| Q27 | Status capaian terkait versi disahkan. Versi koreksi yang disahkan mulai Belum ditetapkan; penilaian sebelumnya tetap histori. | PRD §19.5, §21 |
| Q28 | Anggaran kegiatan tidak diinput/ditampilkan/divalidasi pada MVP; kolom nullable untuk fase lanjutan. | PRD §5.2, §15.1; Model kegiatan |
| Q29 | Perencanaan mengesahkan satu contoh keluaran Excel untuk UAT. Pisahkan baseline/target/realisasi/persentase; kesetaraan informasi tidak mewajibkan menyalin susunan sumber yang ambigu. | PRD §22.4, §24; Plan ekspor |
| Q30 | PM/pengelola infrastruktur menetapkan pemilik dan kesiapan Keycloak, UAT/produksi, domain/HTTPS, storage, backup/pemulihan, notifikasi dan pemeliharaan. Seed konfigurasi terpisah dari data uji. | PRD pembuka/§6; Plan P.4 |
| **Q31** | **LLDIKTI XVI menetapkan enam role resmi: `superadmin`, `admin`, `perencanaan`, `pic`, `pimpinan`, `pegawai`. Role `pic` adalah role sistem tersendiri dan tidak lagi diperlakukan sebagai Pegawai yang “menjadi PIC” hanya karena grant. Namun role `pic` tidak menggantikan `penanggung_jawab`: user role PIC tetap harus ditugaskan ke indikator yang relevan dan lolos scope/grant, deny, jadwal, status, serta business guard lain sebelum dapat mengerjakan RA/Pengukuran. Permission mutasi PIC yang membutuhkan scope unit tidak boleh menjadi akses global hanya karena berasal dari role.** | Klarifikasi langsung LLDIKTI XVI 20 Sep 2026; sinkronisasi wajib PRD §7/§13, Data Model §2.4/§2.19, Workflow §19/§21/§23, Plan Modul 1/4, User Stories/Issues |

---

# Keputusan Q31 — Enam Role Resmi SAKIP *(SUPERSEDED oleh Q32)*

## Status Q31 setelah klarifikasi 24 September 2026

Q31 dipertahankan sebagai **histori keputusan** karena memang pernah digunakan tim pada 20 September 2026. Namun jawaban resmi lanjutan LLDIKTI pada 24 September 2026 menetapkan baseline yang berbeda. Sejak Q32, seluruh ketentuan Q31 yang menyatakan `pic` sebagai role keenam, preset role PIC, migrasi Pegawai→PIC, atau eligibility PJ berbasis role **tidak lagi berlaku sebagai requirement implementasi**.

## 31.1 Daftar role sistem

Role sistem pada Fase Awal ditetapkan menjadi **enam**:

| Kode Role | Label | Fungsi utama |
|---|---|---|
| `superadmin` | Super Admin | Akses penuh/break-glass, pemulihan teknis, dan seluruh permission katalog; tindakan substantif tetap diaudit. |
| `admin` | Admin | Pengelolaan akun, unit, akses, dan setelan aplikasi; bukan pengelola utama substansi kinerja. |
| `perencanaan` | Perencanaan | Pengelola substansi SAKIP: Renstra, Sasaran, Indikator, Target, PK, Jadwal, penugasan PIC, verifikasi/pengesahan, status capaian, laporan, dan domain terkait sesuai permission. |
| `pic` | PIC | Pengguna operasional yang dapat menyusun/mengajukan Rencana Aksi dan Pengukuran untuk indikator yang menjadi tanggung jawabnya, setelah seluruh scope dan business guard terpenuhi. |
| `pimpinan` | Pimpinan | Pemantauan dashboard/laporan dan akses baca sesuai scope MVP; approval aktif Pimpinan tetap fase lanjutan kecuali diputuskan lain. |
| `pegawai` | Pegawai | Pengguna umum dengan akses baca/fitur dasar sesuai preset; bukan otomatis PIC dan tidak otomatis memiliki hak mutasi RA/Pengukuran. |

Semua role di atas merupakan role sistem (`roles.is_sistem = true`) dan tidak dapat dihapus melalui operasi normal aplikasi.

---

## 31.2 Role PIC berbeda dari Penanggung Jawab Indikator

Dua konsep berikut **harus tetap dipisahkan**:

### Role PIC

Menjawab:

> “Jenis/peran utama pengguna ini di dalam aplikasi SAKIP apa?”

Contoh:

```text
User: Budi
Role: PIC
```

### `penanggung_jawab`

Menjawab:

> “User tersebut bertanggung jawab atas indikator yang mana dan sejak kapan?”

Contoh:

```text
Budi
Role: PIC

Penanggung Jawab:
- Indikator A, mulai 1 Januari 2026
- Indikator B, mulai 1 Januari 2026
```

Karena itu:

```text
role = pic
```

**tidak berarti** user tersebut otomatis boleh mengubah seluruh indikator.

Sebaliknya, jalur PIC normal harus mempertimbangkan:

```text
Role PIC
    +
Penanggung Jawab indikator efektif
    +
Scope/grant unit yang sesuai
    +
Tidak ada deny yang cocok
    +
Jendela waktu masih sah
    +
Status objek memungkinkan
    +
Gerbang kelengkapan terpenuhi
    =
Aksi PIC diizinkan
```

`penanggung_jawab` tetap append-only dan historis. Pergantian PIC membuat baris penugasan baru; histori lama tidak ditimpa.

---

## 31.3 Dampak pada model RBAC

Baseline RBAC tetap mempertahankan:

- `permissions`
- `roles`
- `role_permissions`
- `user_roles`
- `user_permission_granted`
- `user_permission_denied`

Keputusan Q31 **tidak mengubah** prinsip:

- deny menang atas allow;
- permission tidak dikenal/tidak aktif fail closed;
- permission dan business rule adalah lapisan berbeda;
- React tidak mengevaluasi permission sendiri;
- SSO/Keycloak hanya menentukan identitas, bukan permission;
- satu user tetap memegang tepat satu role pada MVP (`unique(user_id)` di `user_roles`) sampai ada keputusan resmi membuka multi-role.

Perubahan utama adalah katalog role:

Sebelum Q31:

```text
superadmin
admin
perencanaan
pimpinan
pegawai
```

Sesudah Q31:

```text
superadmin
admin
perencanaan
pic
pimpinan
pegawai
```

---

## 31.4 Scope permission PIC

Karena `role_permissions` pada baseline tidak mempunyai `unit_id`, permission dari role diperlakukan global. Oleh sebab itu, permission mutasi yang secara bisnis harus terikat unit/indikator **tidak boleh menjadi global hanya karena user memiliki role PIC**.

Permission operasional yang membutuhkan scope, antara lain:

- `pengukuran:create`
- `pengukuran:update`
- `rencana_aksi:read`
- `rencana_aksi:create`
- `rencana_aksi:update`
- `rencana_aksi:ajukan`
- `kegiatan:read`
- `kegiatan:create`
- `kegiatan:update`

tetap mengikuti mekanisme scope unit/grant yang berlaku.

Dengan demikian:

```text
Role PIC
```

memberikan klasifikasi/peran operasional, tetapi **bukan bypass scope**.

Untuk Rencana Aksi dan Pengukuran, sistem tetap memeriksa `penanggung_jawab` indikator efektif.

Untuk Kegiatan, sifat kolaboratif per unit tetap mengikuti permission/scope unit dan tidak harus selalu mensyaratkan PIC indikator tertentu, sesuai baseline Q13.

---

## 31.5 Pemisahan Pegawai dan PIC

Setelah Q31, konsep lama berikut tidak lagi menjadi baseline:

```text
Pegawai
+ grant unit
+ penanggung_jawab
= PIC
```

Baseline baru:

```text
PIC
+ penanggung_jawab indikator
+ scope/grant unit
+ business guard
= jalur kerja PIC
```

Role `pegawai` tetap role tersendiri dan tidak otomatis memperoleh:

- hak membuat/mengubah/mengajukan Rencana Aksi;
- hak membuat/mengubah Pengukuran;
- status sebagai penanggung jawab indikator.

Grant eksplisit tetap dapat digunakan untuk pengecualian administratif sesuai katalog dan audit, tetapi **grant tidak mengubah role** dan **grant tidak menciptakan histori `penanggung_jawab`**.

---

## 31.6 Penetapan PIC terhadap indikator

Penetapan PIC operasional tetap dilakukan melalui entitas `penanggung_jawab`.

Aturan baseline setelah Q31:

1. Perencanaan/Superadmin yang berwenang menetapkan atau mengganti PIC indikator.
2. Penugasan menyimpan `user_id`, `indikator_id`, dan `tanggal_mulai_berlaku`.
3. Pergantian tidak menimpa baris lama; dibuat baris baru.
4. PIC efektif pada tanggal T adalah penugasan terbaru dengan `tanggal_mulai_berlaku <= T`.
5. Perubahan role/PIC setelah suatu versi RA/Pengukuran diajukan tidak mengubah provenance historis versi tersebut.
6. Jalur operasional normal PIC menggunakan user dengan role `pic`.
7. Perencanaan tetap memiliki jalur globalnya sendiri dan tidak harus berpura-pura menjadi PIC untuk melakukan kewenangan Perencanaan.
8. Superadmin tetap break-glass sesuai permission dan audit.

Jika di masa depan LLDIKTI menetapkan bahwa role lain juga boleh menjadi `penanggung_jawab` indikator secara formal, perubahan tersebut harus dicatat sebagai keputusan baru dan diselaraskan lintas dokumen; jangan diasumsikan oleh implementasi.

---

## 31.7 Dampak pada UI pengelolaan akses

Form **Assign Peran** wajib menampilkan enam pilihan:

```text
Super Admin
Admin
Perencanaan
PIC
Pimpinan
Pegawai
```

Tidak boleh lagi hanya menampilkan lima pilihan lama.

Halaman “Jelaskan izin pengguna” harus mampu menjelaskan:

- role utama user;
- permission dari `role_permissions`;
- grant per unit;
- deny yang berlaku;
- untuk konteks Rencana Aksi/Pengukuran, status penugasan PIC efektif bila relevan.

Contoh:

```text
User: Budi
Role: PIC

Indikator A
- Role PIC: ya
- Penanggung jawab efektif: ya
- Grant unit: ya
- Deny: tidak
- Jendela pengisian: aktif
=> boleh mengisi

Indikator B
- Role PIC: ya
- Penanggung jawab efektif: tidak
=> tidak boleh mengisi
```

---

## 31.8 Dampak pada dashboard dan navigasi

Role PIC dapat memiliki pengalaman kerja yang berbeda dari Pegawai.

Contoh informasi/fitur yang relevan bagi PIC:

- indikator yang menjadi tanggung jawabnya;
- Rencana Aksi yang perlu dibuat/diperbaiki;
- Pengukuran periode aktif;
- Kegiatan unit yang dapat diakses;
- bukti dukung;
- deadline/jendela pengisian;
- notifikasi pengembalian;
- status “menunggu verifikasi”.

Role Pegawai tidak otomatis memperoleh seluruh menu kerja PIC.

Detail final sidebar/menu tetap mengikuti permission efektif (`can.*`) dari server, bukan hardcode role di React.

---

## 31.9 Dampak pada audit dan provenance

Q31 tidak mengubah aturan provenance yang sudah disepakati.

Untuk Rencana Aksi dan Pengukuran:

- `diajukan_by`
- `diajukan_at`
- `jalur_pengajuan`
- `dasar_izin_pengajuan`

tetap dibekukan pada versi.

`jalur_pengajuan = pic` berarti pengajuan dilakukan melalui jalur operasional PIC yang sah pada saat submit.

Perubahan user dari `pic` ke role lain setelah submit tidak:

- mengubah `diajukan_by`;
- mengubah `jalur_pengajuan`;
- menghapus F1;
- mengubah siapa PIC historis pada versi tersebut.

Aturan F1 tetap berlaku:

> Pengaju melalui jalur PIC tidak boleh memverifikasi atau mengesahkan versi yang diajukannya sendiri.

---

## 31.10 Dokumen yang wajib diselaraskan setelah Q31

Keputusan Q31 berdampak langsung pada:

1. `SAKIP - PRD.md`
   - daftar role;
   - preset permission;
   - Form Assign Peran;
   - hubungan role PIC dengan grant/scope;
   - hubungan PIC dengan `penanggung_jawab`;
   - tabel/matriks role.

2. `SAKIP - Data Model.md`
   - `roles.kode`;
   - narasi lima role → enam role;
   - preset `role_permissions`;
   - aturan `penanggung_jawab`;
   - contoh dan constraint/test terkait role.

3. `SAKIP - Workflow.md`
   - Form Assign Peran;
   - flow PIC;
   - teks lama “PIC (peran Pegawai)”;
   - tabel Role vs Aksi;
   - flow notifikasi dan task list bila role disebut eksplisit.

4. `SAKIP - Plan Pengembangan.md`
   - migrasi/seed role;
   - seeder `role_permissions`;
   - UI Assign Peran;
   - test per role;
   - demo seeder;
   - test dashboard/read permission;
   - seluruh DoD yang masih menyebut “kelima role”.

5. `SAKIP - User Stories.md`
   - US-01.03 Assign Peran;
   - US-01.04 Grant;
   - US-04.01 Penetapan PIC;
   - story RA/Pengukuran yang mengandalkan jalur PIC.

6. `SAKIP - User Issues.md`
   - ISS-01.03;
   - ISS-04.01;
   - issue Rencana Aksi/Pengukuran/PIC;
   - automated test dan dependency yang masih menyebut lima role atau PIC sebagai Pegawai.

7. `design-system.md`
   - hanya perlu penyesuaian bila navigasi/dashboard role ditulis secara eksplisit; prinsip desain visual tidak berubah.

Dokumen lain tidak boleh diperbarui secara parsial sehingga sebagian masih menganggap PIC sebagai Pegawai dan sebagian sudah menganggap PIC role tersendiri.

---

# Keputusan Q32 — Klarifikasi Final LLDIKTI 24 September 2026

Q32 merupakan keputusan final berdasarkan jawaban klarifikasi resmi LLDIKTI Wilayah XVI kepada tim magang pada **24 September 2026**. Bila Q32 bertentangan dengan Q31 atau teks sebelumnya, **Q32 yang berlaku**.

## 32.1 Lima Role Bawaan — PIC Bukan Role

Role bawaan SAKIP adalah:

```text
superadmin
admin
perencanaan
pimpinan
pegawai
```

Tidak ada role `pic`. Istilah **PIC** tetap dipakai pada proses bisnis untuk menyebut pengguna yang diberi tanggung jawab dan hak kerja pada unit/indikator tertentu, tetapi bukan nilai pada `roles.kode`.

Konsekuensi:

1. hapus `pic` dari katalog role, seeder, dropdown Assign Peran, fixture, demo user, dan automated test;
2. tidak ada preset `role_permissions` untuk `pic`;
3. tidak ada migrasi `pegawai → pic`;
4. tidak boleh membuat role per-unit seperti `pic_akademik`;
5. teks “PIC” pada workflow harus dibaca sebagai **aktor operasional**, bukan role database.

## 32.2 PIC Operasional, Grant Unit, dan Penanggung Jawab

Tiga konsep harus dipisahkan:

```text
Role utama user
    ↓
Pegawai / Perencanaan / dst.

Penanggung Jawab
    ↓
assignment historis user ↔ indikator

Grant Unit
    ↓
hak kerja eksplisit pada unit tertentu
```

`penanggung_jawab` adalah penugasan data beriwayat. Siapa pun dapat ditunjuk selama user ada dan berstatus aktif. Penetapan dilakukan oleh **Perencanaan** atau **Superadmin**.

Assignment PJ **tidak memberikan izin**. Agar dapat mengerjakan Rencana Aksi/Kegiatan/Pengukuran, user harus memiliki grant unit yang sesuai. UI penugasan wajib memberi **peringatan, bukan blokir**, ketika calon PJ belum memiliki grant yang diperlukan, dan sistem menyediakan daftar **“PJ aktif tanpa hak isi”**.

Perubahan role user tidak menghapus assignment PJ dan tidak mencabut grant secara otomatis. Pencabutan hak isi harus menjadi aksi eksplisit, beralasan, dan teraudit.

## 32.3 Permission Unit-Scoped Final dan `delegasi:update`

Hanya **7 permission** berikut yang boleh diberikan sebagai grant per unit:

```text
pengukuran:create
pengukuran:update
rencana_aksi:create
rencana_aksi:update
rencana_aksi:ajukan
kegiatan:create
kegiatan:update
```

`rencana_aksi:read` dan `kegiatan:read` adalah permission global, bukan permission unit-scoped.

Form Grant Unit digerbangi permission **`delegasi:update`**. Preset pemegangnya:

- Perencanaan;
- Admin;
- Superadmin.

Form Assign Peran dan Explicit Deny tetap digerbangi **`akses:update`**. Deny selalu menang atas allow; permission tidak dikenal atau user tanpa role harus fail closed.

## 32.4 Isi Role Tidak Diedit dari UI

Tidak ada UI mutasi `role_permissions`, termasuk untuk Superadmin. Isi role adalah preset yang didefinisikan di kode dan disinkronkan melalui seeder/release.

UI yang tersedia hanya **“Peran & Izin” read-only**, digerbangi `pengguna:read`, untuk menampilkan daftar role beserta permission bawaannya.

Jika preset role berubah melalui rilis, seeder harus:

- membandingkan nilai lama dan nilai baru;
- menyinkronkan secara idempoten;
- mencatat audit perubahan dengan before/after dan alasan/sumber rilis;
- tidak menghasilkan audit palsu bila tidak ada perubahan.

## 32.5 Onboarding SSO Final

Onboarding akun SSO menggunakan pola **just-in-time, status belum aktif**:

```text
Login Keycloak valid
        ↓
create/update users berdasarkan keycloak_id
        ↓
status = nonaktif
        ↓
TANPA ROLE
        ↓
halaman "Akun belum diaktifkan"
        ↓
Admin mengaktifkan + menetapkan role
```

Kontrak data yang diminta stakeholder adalah `users.status` enum(`aktif`,`nonaktif`) default `nonaktif`. User tanpa role memiliki **nol permission**.

Login tidak ditolak di Keycloak hanya karena user belum aktif di SAKIP; identitas boleh diprovisikan, tetapi akses aplikasi tetap fail closed sampai aktivasi dan assign role selesai.

## 32.6 Logout Final

Logout dipisahkan menjadi dua aksi:

1. **Keluar** — POST + CSRF, mengakhiri session Laravel (`invalidate` + regenerate token). Setelah itu tampilkan pesan bahwa sesi SSO LLDIKTI masih aktif.
2. **Keluar dari semua aplikasi (SSO)** — aksi POST terpisah yang juga memanggil Keycloak `end_session_endpoint`.

Tombol keluar biasa tidak boleh otomatis mengakhiri sesi Keycloak karena realm digunakan bersama aplikasi lain.

## 32.7 Penggunaan Pertama: Jadwal 2026

Penggunaan pertama SAKIP adalah **Jadwal Tahunan 2026**.

```text
2026
├── TW I  → periode lampau, diisi Perencanaan
├── TW II → periode lampau, diisi Perencanaan
├── TW III → workflow normal PIC operasional
└── TW IV  → workflow normal PIC operasional
```

Tahun 2025 **tidak di-backfill sebagai pengukuran**; 2025 hanya menjadi baseline indikator.

Tidak boleh dibuat kolom/flag khusus `is_backfill`. Status periode lampau dihitung dari jendela pengisian yang sudah berakhir ketika Jadwal Tahunan diaktifkan.

Untuk periode lampau:

- gerbang RA sudah disahkan dikecualikan;
- kelengkapan komponen dikecualikan;
- berkas wajib dikecualikan;
- **PK tahun berjalan tetap wajib**;
- pengisian dilakukan oleh Perencanaan.

Tahun aktif tidak boleh di-hardcode; tahun aktif adalah tahun yang mempunyai Jadwal Tahunan berstatus aktif.

## 32.8 Rencana Aksi 2026

Rencana Aksi 2026 sudah disusun oleh Perencanaan. Data tersebut harus dicatat di aplikasi dengan status **disahkan** sebelum pengajuan TW III karena gerbang pengajuan mensyaratkan RA yang sah.

Penetapan PIC operasional melalui workflow normal per periode dimulai pada Rencana Aksi berikutnya (2027). Initial setup 2026 diperlakukan sebagai data awal yang diverifikasi Perencanaan, bukan migrasi role PIC.

## 32.9 IKU 8 — Definisi Final

IKU 8 bertipe `rasio_persen` dengan formula:

```text
n / t × 100%
```

Penyebut `t` adalah **total publikasi seluruh PTS di wilayah kerja**. Angka 84 adalah jumlah PTS wilayah kerja dan **bukan** penyebut IKU 8; angka tersebut berasal dari indikator lain.

Target PK 59,5 tetap digunakan apa adanya. Nilai `t` diisi Perencanaan dari sumber resmi dan tidak boleh di-hardcode.

## 32.10 IKU 3 — Baseline, Target, dan Formula Final

IKU 3 menggunakan dua skor input:

```text
sakip  = Skor SAKIP
zi_wbk = Skor ZI-WBK
```

Formula:

```text
(sakip + zi_wbk) / 2
```

Baseline 2025 = **74,2**, berasal dari Nilai SAKIP saja dan disimpan apa adanya. Target PK 2026 = **76,25**, berasal dari gabungan SAKIP + ZI-WBK. Karena cakupannya berbeda, selisih `76,25 - 74,2` **tidak boleh dipresentasikan sebagai kenaikan/penurunan kinerja atau tren yang sebanding**.

Data TW II:

- `76,25` = target resmi;
- `66,395` = skor komposit target revisi, **bukan realisasi**;
- `87,08` = hasil rumus workbook yang rusak, **bukan capaian**.

`66,395` dan `87,08` tidak boleh di-seed/import sebagai pengukuran/realisasi.

## 32.11 Dampak Minimum ke Dokumen dan Kode

Q32 wajib diselaraskan minimal ke:

1. PRD;
2. Data Model;
3. Workflow;
4. Plan Pengembangan;
5. User Stories;
6. User Issues;
7. Design System;
8. katalog role/permission/seeder;
9. Assign Role;
10. Grant Unit;
11. Role & Permission UI;
12. SSO provisioning/logout;
13. fixture dan automated test.

Dokumen, issue, dan kode yang masih menyatakan `pic` sebagai role keenam dinyatakan **stale** sampai diselaraskan dengan Q32.

---

# Bukti aritmetika IKU 3

Sumber historis: `document/Pengukuran Kinerja  Triwulan 2026.xlsx` pada commit `54c9489`, SHA-256 `66840a50d3048e9aa0a1c10c6d7b8158b80b95d006d2df9feefd209fdc09054b`. Workbook/transkrip sudah dihapus PM dari development melalui `17761d8`; keduanya tidak dipulihkan oleh penyelarasan ini.

| Bukti | Input/perhitungan | Makna yang terverifikasi |
|---|---|---|
| Pengukuran Triwulan I G16:G19 | 23 + 24 + 11,5 + 19 = 77,5 | Jumlah empat subskor SAKIP |
| Pengukuran Triwulan I G20 dan F14 | ZI 75; (77,5 + 75) / 2 = 76,25 | Rumus gabungan dengan koefisien setara 0,5 |
| Pengukuran Triwulan II G16:G20/F14 | SAKIP 79,75; ZI 53,04; gabungan 66,395 | Hasil aritmetika komponen yang tersimpan |
| Pengukuran Triwulan II H14/H15 | H14 = 76,25 tetap; H15 = F14/H14 × 100 = 87,0754098… | Persamaan tersimpan, bukan bukti pengesahan makna target/realisasi |
| PK 2026 H10/I10 | Baseline 74,2 dari SAKIP saja; target 76,25 dari gabungan | Cakupan baseline dan target berbeda |

**Interpretasi final Q32:** bukti workbook di atas dipertahankan hanya sebagai bukti historis/aritimethic. Implementasi **tidak** memakai lima komponen tersebut sebagai input IKU 3. Kontrak final adalah dua skor input: `sakip` dan `zi_wbk`, masing-masing koefisien 0,5, dengan formula `(sakip + zi_wbk) / 2`. Nilai 66,395 adalah skor komposit target revisi TW II dan 87,08 adalah hasil formula workbook yang rusak; keduanya bukan realisasi.

---

# Tindak lanjut yang masih membutuhkan data atau penetapan

Keputusan bisnis yang sebelumnya OPEN pada daftar klarifikasi 24 September 2026 telah ditutup oleh Q32. Yang masih memerlukan penetapan operasional/eksternal adalah:

| Item | Pemilik keputusan | Status / yang masih diperlukan |
|---|---|---|
| Penerima walkthrough/UAT, tanggal penerimaan dan tanggal produksi | PM + Perencanaan | Belum tercatat final; target integrasi proyek tidak otomatis sama dengan go-live. |
| Contoh keluaran Excel penerimaan | Perencanaan | Layout/kolom/presisi/narasi final masih perlu disahkan untuk UAT. |
| Infrastruktur produksi | PM/pengelola infrastruktur | Domain/HTTPS, owner layanan, backup/pemulihan, storage, monitoring, dan credential deployment perlu dibuktikan. |
| Operasional notifikasi eksternal | Pemilik layanan + Perencanaan | Provider, jam kirim, zona waktu, template, nomor/email pengirim, retry, dan bukti delivery perlu ditetapkan. |
| Daftar penetapan awal 2026 | Perencanaan | Daftar unit, mapping indikator→unit, PJ, dan grant turunan dari penetapan PJ perlu disiapkan sebelum operasional TW III. |

## Item klarifikasi yang sudah RESOLVED oleh Q32

- tahun penggunaan pertama = 2026;
- TW I–II = periode lampau oleh Perencanaan; TW III–IV normal;
- IKU 8 penyebut = total publikasi seluruh PTS;
- 76,25 = target; 66,395/87,08 bukan realisasi;
- baseline IKU 3 = 74,2 dan tidak dibandingkan sebagai tren terhadap target gabungan;
- tidak ada role PIC;
- Grant Unit tetap wajib untuk hak kerja PIC operasional;
- PJ dapat menunjuk user aktif mana pun dan tidak memberi permission;
- role-permission tidak diedit dari UI;
- tidak ada migrasi Pegawai→PIC;
- onboarding JIT = nonaktif + tanpa role;
- logout lokal default + logout SSO terpisah.

---

# Catatan implementasi

Pilihan engineering seperti constraint, provenance pengajuan, tabel versi, dan validasi merupakan kontrak implementasi untuk memenuhi keputusan yang disetujui; tidak berarti kode atau migrasinya sudah tersedia.

Pembaruan Q31 mengubah baseline role, tetapi **tidak otomatis membuktikan bahwa kode, seeder, migration, UI, automated test, maupun issue GitHub sudah diperbarui**. Sebelum implementasi dianggap konsisten, seluruh dokumen terdampak pada §31.10 harus diselaraskan dan perubahan kode harus mengikuti baseline baru.

Tidak ada aplikasi, workbook, data produksi, panduan agent lokal, atau pengaturan Git yang diubah hanya dengan penyelarasan isi dokumen ini.
