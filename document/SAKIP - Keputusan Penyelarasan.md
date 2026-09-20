# SAKIP — Keputusan Penyelarasan Dokumen

Tanggal baseline awal: **18 September 2026**  
Pembaruan terakhir: **20 September 2026**  
Branch acuan: `development`  
Commit branch saat pembaruan terakhir: `6307164c39b084a81e9827499c26f692e1d27576`

> Dokumen ini menjadi catatan keputusan penyelarasan lintas dokumen SAKIP. PRD menetapkan perilaku produk, Data Model menetapkan struktur dan integritas data, Workflow menetapkan alur, Plan Pengembangan menetapkan task/dependency/Definition of Done, User Stories dan User Issues menerjemahkan kontrak tersebut ke kebutuhan dan pekerjaan implementasi. Bila terdapat keputusan bisnis baru yang menggantikan baseline lama, perubahan harus terlebih dahulu dicatat di dokumen ini lalu diselaraskan ke seluruh sumber terdampak.

---

## Status dan dasar keputusan

Pengguna menyampaikan bahwa PM telah memperbarui Q4 pada branch `development` dan menyetujui penggunaan rekomendasi per pertanyaan grill untuk sisanya, lalu meminta dokumen diselaraskan. Dokumen ini mencatat hasil penyelarasan tersebut; bukan klaim bahwa implementasi, UAT, penyediaan layanan, atau pengesahan setiap angka operasional sudah selesai.

Pembaruan **20 September 2026** menambahkan keputusan baru berdasarkan informasi langsung dari pihak **LLDIKTI Wilayah XVI** yang disampaikan kepada tim bahwa SAKIP menggunakan **enam role resmi**:

1. `superadmin`
2. `admin`
3. `perencanaan`
4. `pic`
5. `pimpinan`
6. `pegawai`

Empat dokumen resmi yang diterima dari LLDIKTI (PRD, Workflow, Data Model, dan Plan Pengembangan) masih memuat baseline lama lima role (`superadmin`, `admin`, `perencanaan`, `pimpinan`, `pegawai`). Karena itu, Q31 diperlakukan sebagai **keputusan pengganti yang harus diselaraskan ke dokumen-dokumen tersebut**, bukan alasan untuk mengubah aturan lain yang belum dikonfirmasi.

Yang sudah dipastikan oleh Q31 hanya:
- jumlah role resmi menjadi enam;
- `pic` adalah role tersendiri;
- katalog role, Assign Peran, seeder, test, dan penyebutan jumlah role harus diperbarui.

Yang **belum** dipastikan dan tidak boleh diasumsikan:
- apakah hanya user role `pic` yang boleh dipilih pada `penanggung_jawab`;
- preset permission bawaan role `pic`;
- apakah permission kerja PIC tetap seluruhnya melalui grant unit atau sebagian melekat pada role;
- mapping/migrasi user existing dari `pegawai` ke `pic`;
- perubahan lain pada workflow/domain di luar konsekuensi langsung penambahan role.

Prinsip penyelarasan yang tetap berlaku:

- Q4 mengikuti perubahan PM pada `d04ae5e`. Klarifikasi langsung pengguna menetapkan pengingat **hanya H-7, H-3, H-1**; scheduler yang berjalan harian tidak berarti pesan dikirim setiap hari.
- Q5 mengikuti rekomendasi terbaru setelah analisis Excel: **empat subskor SAKIP dan satu nilai ZI**, dengan subtotal SAKIP dan hasil gabungan ditampilkan. Ini menggantikan rekomendasi awal dua nilai final.
- Q31 menetapkan enam role resmi dan menambahkan `pic` sebagai role sistem tersendiri. Mekanisme `penanggung_jawab` indikator **tetap dipertahankan sebagaimana dokumen resmi**, tetapi hubungan final role `pic` dengan eligibility assignment dan preset permission masih menunggu konfirmasi.
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
| Q13 | Grant unit dan PIC efektif sama-sama diperlukan untuk menangani RA/pengukuran indikator. Perencanaan memakai pengecualian global; kegiatan tetap kolaboratif per unit. | PRD §13, §14.8, §19.3 |
| Q14 | Admin tidak memiliki izin substantif secara bawaan; grant eksplisit beralasan dapat memberi pengecualian, tetap tunduk deny/scope/PIC/waktu/F1. | PRD §7.3–7.6 |
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
| **Q31** | **LLDIKTI Wilayah XVI menetapkan enam role resmi SAKIP: `superadmin`, `admin`, `perencanaan`, `pic`, `pimpinan`, dan `pegawai`. Role `pic` merupakan role tersendiri dan karena itu katalog role, UI Assign Peran, seeder, test, dan seluruh penyebutan “lima/kelima peran” harus diselaraskan. Mekanisme `penanggung_jawab` indikator tetap dipertahankan karena sudah merupakan bagian dari dokumen resmi. Namun hubungan final antara role `pic`, eligibility menjadi `penanggung_jawab`, preset permission role `pic`, grant unit, serta migrasi user existing belum dinyatakan dalam dokumen resmi yang diterima dan **tidak boleh diasumsikan** sebelum dikonfirmasi.** | Klarifikasi langsung pihak LLDIKTI XVI yang disampaikan kepada tim, 20 Sep 2026; dokumen resmi PRD/Workflow/Data Model/Plan yang diterima masih memakai baseline lima role dan perlu diselaraskan |

---

# Keputusan Q31 — Enam Role Resmi SAKIP

## 31.1 Keputusan yang Sudah Dikonfirmasi

Berdasarkan informasi langsung dari pihak LLDIKTI Wilayah XVI yang disampaikan kepada tim pada **20 September 2026**, role resmi SAKIP berjumlah **enam**:

| Kode Role | Label |
|---|---|
| `superadmin` | Super Admin |
| `admin` | Admin |
| `perencanaan` | Perencanaan |
| `pic` | PIC |
| `pimpinan` | Pimpinan |
| `pegawai` | Pegawai |

Keputusan ini menggantikan daftar lima role yang masih tertulis pada dokumen resmi sebelumnya:

```text
superadmin
admin
perencanaan
pimpinan
pegawai
```

menjadi:

```text
superadmin
admin
perencanaan
pic
pimpinan
pegawai
```

Role `pic` adalah **role tersendiri** dan tidak boleh lagi hilang dari katalog role, dropdown Assign Peran, seeder, fixture, data demo, maupun automated test.

---

## 31.2 Status Dokumen Resmi yang Diterima

Empat dokumen yang diterima dari pihak LLDIKTI — **PRD, Workflow, Data Model, dan Plan Pengembangan** — masih merepresentasikan baseline lima role.

Karena itu penyelarasan Q31 dilakukan dengan prinsip:

> **Pertahankan isi resmi terlebih dahulu. Ubah hanya bagian yang memang terdampak keputusan enam role. Jangan menyisipkan aturan baru yang belum dinyatakan LLDIKTI.**

Konsekuensi langsung yang aman untuk diterapkan:

1. semua teks “lima peran” / “kelima peran” menjadi “enam peran” / “keenam peran”;
2. kode `pic` ditambahkan ke katalog role;
3. `pic` ditambahkan ke UI Assign Peran;
4. seeder role memasukkan `pic`;
5. data pengembangan/testing menyediakan user role `pic`;
6. test keberadaan role memperhitungkan enam role;
7. tabel/matriks role yang secara eksplisit mencantumkan role harus menyediakan kolom/baris PIC;
8. istilah yang secara eksplisit menyamakan PIC dengan Pegawai harus direview dan disesuaikan tanpa mengubah aturan bisnis yang belum dikonfirmasi.

---

## 31.3 `penanggung_jawab` Tetap Dipertahankan

Dokumen resmi telah memiliki entitas/alur `penanggung_jawab` untuk mencatat penugasan user terhadap indikator dan histori pergantiannya.

Q31 **tidak menghapus atau mengganti** mekanisme tersebut.

Secara konseptual ada dua data berbeda:

```text
roles / user_roles
    ↓
mencatat role utama user

penanggung_jawab
    ↓
mencatat user yang ditugaskan
ke indikator pada tanggal tertentu
```

Namun dokumen resmi yang diterima **belum menetapkan** constraint baru berikut:

```text
penanggung_jawab.user_id
WAJIB user dengan role = pic
```

Karena itu constraint tersebut **belum boleh dimasukkan sebagai keputusan final** hanya berdasarkan penambahan role `pic`.

Hal yang sudah pasti:

- `penanggung_jawab` tetap dibutuhkan;
- histori assignment tetap dipertahankan;
- penambahan role PIC tidak otomatis menghapus assignment per indikator.

Hal yang masih membutuhkan konfirmasi:

- apakah user yang dapat dipilih sebagai `penanggung_jawab` dibatasi hanya role `pic`;
- apa yang terjadi bila role user berubah setelah ia menjadi `penanggung_jawab`;
- apakah ada masa transisi untuk user existing yang sekarang diperlakukan sebagai PIC melalui mekanisme lama.

---

## 31.4 Preset Permission Role `pic` Belum Boleh Ditebak

Dokumen resmi lama menjelaskan model akses melalui:

- `permissions`
- `roles`
- `role_permissions`
- `user_roles`
- `user_permission_granted`
- `user_permission_denials`

dan menyatakan bahwa permission yang berasal dari `role_permissions` bersifat global karena tabel tersebut tidak memiliki `unit_id`.

Dokumen lama juga mendeskripsikan pekerjaan PIC melalui kombinasi permission scoped per unit dan penugasan PIC efektif.

Setelah Q31, **belum ada ketentuan resmi yang menyatakan permission apa saja yang harus dimasukkan langsung ke preset role `pic`**.

Karena itu penyelarasan dokumen tidak boleh langsung menyimpulkan:

```text
role pic
= otomatis memiliki seluruh
rencana_aksi:* dan pengukuran:* secara global
```

dan juga belum boleh menyimpulkan kebalikannya tanpa dasar bahwa:

```text
role pic
= selalu kosong dan semua izin
harus berasal dari grant
```

Keputusan final preset `role_permissions` untuk `pic` harus dikonfirmasi dengan pihak LLDIKTI/Tim Perencanaan sebelum dianggap kontrak implementasi.

Sambil menunggu, prinsip existing yang tidak berubah tetap dipertahankan:

- deny menang atas allow;
- permission tidak dikenal/tidak aktif fail closed;
- scope unit tetap dihormati;
- permission dan aturan bisnis adalah lapisan berbeda;
- keputusan authorization ada di server;
- React hanya menerima hasil evaluasi seperti `can.*`.

---

## 31.5 Pegawai dan PIC Tidak Lagi Boleh Dicantumkan sebagai Satu Role

Karena PIC kini dikonfirmasi sebagai role tersendiri, penyebutan seperti:

```text
Pegawai (Penanggung Jawab)
```

atau:

```text
PIC (Pegawai)
```

harus direview pada dokumen yang menyajikan daftar/matriks role.

Namun perubahan teks tersebut tidak otomatis menentukan seluruh kewenangan PIC.

Contoh penyelarasan aman:

Sebelum:

```text
Superadmin | Admin | Perencanaan | Pimpinan | Pegawai
```

Sesudah:

```text
Superadmin | Admin | Perencanaan | PIC | Pimpinan | Pegawai
```

Untuk tabel “Role vs Aksi”, nilai izin pada kolom PIC harus ditentukan dari kontrak permission yang telah dikonfirmasi, bukan diisi berdasarkan asumsi.

---

## 31.6 Form Assign Peran

UI **Assign Peran** pada MVP harus menampilkan enam pilihan:

```text
Super Admin
Admin
Perencanaan
PIC
Pimpinan
Pegawai
```

Aturan existing satu user satu role pada MVP tetap dipertahankan **selama belum ada keputusan baru yang mengubahnya**.

Q31 tidak membuka multi-role.

Perubahan role tetap wajib mengikuti mekanisme audit existing:

- aktor pemberi;
- alasan;
- nilai lama;
- nilai baru;
- waktu perubahan.

---

## 31.7 Dampak Minimal pada Seeder dan Testing

Seluruh seed/test yang masih mengasumsikan lima role harus diselaraskan menjadi enam role.

Minimal:

```text
superadmin
admin
perencanaan
pic
pimpinan
pegawai
```

Data development/testing harus memiliki minimal satu user untuk setiap role agar UI dan authorization dapat diuji.

Automated test minimal perlu membuktikan:

- role `pic` dapat disimpan;
- role `pic` muncul dalam Assign Peran;
- constraint satu user satu role tetap berlaku;
- perubahan role tetap teraudit;
- resolver tidak crash ketika user memiliki role `pic`.

Test mengenai **hak substantif role PIC** baru boleh dijadikan final setelah preset permission dan hubungannya dengan `penanggung_jawab` dikonfirmasi.

---

## 31.8 Bagian yang Belum Boleh Diasumsikan

Sampai ada klarifikasi lanjutan dari LLDIKTI, hal berikut tetap **OPEN**:

| Item | Status |
|---|---|
| Apakah hanya role `pic` yang eligible menjadi `penanggung_jawab` | Belum dikonfirmasi |
| Preset `role_permissions` untuk role `pic` | Belum dikonfirmasi |
| Apakah grant unit tetap wajib untuk semua aksi kerja PIC | Belum dikonfirmasi sebagai konsekuensi role baru |
| Dampak perubahan role PIC terhadap assignment `penanggung_jawab` aktif | Belum dikonfirmasi |
| Mapping user existing `pegawai` → `pic` | Belum dikonfirmasi |
| Menu/dashboard khusus PIC | Belum dikonfirmasi sebagai kontrak; harus mengikuti permission efektif |
| Perubahan workflow bisnis selain penambahan role | Tidak ada keputusan baru; jangan diubah |

Ketika jawaban resmi tersedia, keputusan tersebut harus dicatat sebagai Q32 atau revisi eksplisit Q31, lalu diselaraskan ke PRD, Workflow, Data Model, Plan, User Stories, dan User Issues.

---

## 31.9 Dokumen yang Wajib Diselaraskan

Q31 berdampak langsung minimal pada:

1. **SAKIP - PRD.md**
   - daftar role;
   - teks lima/kelima role;
   - Assign Peran;
   - tabel role;
   - penyebutan PIC yang masih dilekatkan ke Pegawai;
   - scope/permission PIC setelah mendapat keputusan final.

2. **SAKIP - Data Model.md**
   - `roles.kode`;
   - uraian role;
   - seeder/constraint/test role;
   - jangan menambah constraint eligibility `penanggung_jawab` sebelum dikonfirmasi.

3. **SAKIP - Workflow.md**
   - aktor/label PIC;
   - Form Assign Peran;
   - tabel Role vs Aksi;
   - teks yang menyamakan PIC dan Pegawai.

4. **SAKIP - Plan Pengembangan.md**
   - migrasi role;
   - seeder role;
   - UI Assign Peran;
   - demo data;
   - automated test;
   - DoD yang masih menyebut kelima role.

5. **User Stories / User Issues**
   - dependency jumlah role;
   - acceptance criteria Assign Peran;
   - issue/test role PIC;
   - kontrak PIC baru hanya setelah kewenangan final dikonfirmasi.

6. **design-system.md**
   - hanya bila ada komponen/menu yang eksplisit mengunci tampilan berdasarkan daftar role.

Penyelarasan tidak boleh membuat sebagian dokumen menganggap PIC sebagai role terpisah sementara dokumen lain masih menganggap PIC identik dengan Pegawai.

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

Lima komponen datar `perencanaan_kinerja`, `pengukuran_kinerja`, `pelaporan_kinerja`, `evaluasi_internal`, `zi` masing-masing penjumlah dengan bobot 0,5. Rumus bukan rata-rata lima angka; subtotal SAKIP tidak menjadi komponen tambahan. Bobot 30/30/15/25 pada penilaian sumber tidak dikalikan ulang pada skor input tersebut.

---

# Tindak lanjut yang masih membutuhkan data atau penetapan

| Item | Pemilik keputusan | Yang belum boleh diasumsikan |
|---|---|---|
| Penerima walkthrough/UAT, tanggal penerimaan dan produksi | PM bersama Perencanaan | Nama pihak dan tanggal belum tercatat; 9 November bukan otomatis go-live |
| Tahun/periode penggunaan pertama dan daftar data historis wajib | PM/Perencanaan | Tahun 2026 pada workbook bukan otomatis tahun aktivasi aplikasi |
| Definisi IKU 8 | Perencanaan | Penyebut 84 belum disahkan sebagai jumlah PTS/publikasi; blokir produksi indikator tersebut |
| Q5a: label target/realisasi TW II | Perencanaan | Interpretasi 76,25 target dan 66,395 realisasi belum disahkan sebagai data produksi |
| Q5b: baseline SAKIP saja dibanding gabungan | Perencanaan | Jangan menerbitkan tren/selisih seolah cakupannya setara tanpa keterangan/penetapan |
| Contoh keluaran Excel penerimaan | Perencanaan | Kolom/urutan/presisi/narasi/format final masih harus ditandatangani/disetujui |
| Pemilik dan konfigurasi layanan/deployment | PM/pengelola infrastruktur | Nama owner, provider, realm/client, domain, backup/pemulihan, nomor kontak dan template belum dibuktikan |
| Detail operasional notifikasi | Pemilik layanan/Perencanaan | Kontrak provider, jam kirim/zona waktu operasional serta bukti sampai penerima belum ditetapkan |
| Eligibility `penanggung_jawab` terhadap role `pic` | LLDIKTI/Tim Perencanaan | Belum dikonfirmasi apakah hanya user role `pic` yang boleh dipilih sebagai `penanggung_jawab`; jangan tambahkan constraint ini sebelum keputusan resmi |
| Preset final permission role `pic` | LLDIKTI/Tim Perencanaan + penanggung jawab teknis | Daftar permission bawaan role `pic` belum dinyatakan dalam dokumen resmi yang diterima; jangan menebak allow global maupun grant scoped final |
| Migrasi user existing ke role `pic` | LLDIKTI/PM/Perencanaan/Admin | User mana yang harus berubah dari `pegawai` menjadi `pic` belum boleh disimpulkan dari grant atau assignment existing |

---

# Catatan implementasi

Pilihan engineering seperti constraint, provenance pengajuan, tabel versi, dan validasi merupakan kontrak implementasi untuk memenuhi keputusan yang disetujui; tidak berarti kode atau migrasinya sudah tersedia.

Pembaruan Q31 mengubah **daftar role resmi** dari lima menjadi enam, tetapi **tidak otomatis menetapkan preset permission role `pic`, eligibility `penanggung_jawab`, strategi migrasi user, maupun perubahan workflow lain**. Kode, seeder, migration, UI, automated test, dan issue GitHub juga belum dianggap diperbarui hanya karena keputusan ini dicatat. Sebelum implementasi dianggap konsisten, dokumen terdampak pada §31.9 harus diselaraskan menggunakan keputusan yang sudah terkonfirmasi dan bagian yang masih terbuka harus tetap ditandai OPEN.

Tidak ada aplikasi, workbook, data produksi, panduan agent lokal, atau pengaturan Git yang diubah hanya dengan penyelarasan isi dokumen ini.
