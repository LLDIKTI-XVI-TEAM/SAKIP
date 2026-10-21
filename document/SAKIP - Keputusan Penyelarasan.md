# SAKIP — Keputusan Penyelarasan Dokumen

Tanggal baseline awal: **18 September 2026**  
Pembaruan terakhir: **16 Oktober 2026**

Branch acuan: `development`  
Basis commit sebelum pembaruan Q33: `b3adc237f09aafd8a8e3723f56d53a3107465105`  
Basis commit sebelum pembaruan Q34: `365688b4faaecb0d904b6e12d314fe25b9a30b75`  
Basis commit sebelum pembaruan Q35: `405fac1e16b768046879b7e6a418fa6d14d9af77`  
Basis commit sebelum pembaruan Q36: `db0a887923127dc7af4b667bdf98c5d91e9a06b1`

Basis commit sebelum keputusan review Q37 (semula bernomor Q36): `bba499436be3f919b24cea9ffbf6295ba1bc00bc`

Basis commit sebelum pembaruan Q39–Q51: `0a260708d3b6df328d82151f85bf274d29bdec3b`

Basis commit sebelum pembaruan Q52: `edd08533ec01170c8946f8f3b38ffdd633bbb0a0`

Basis commit sebelum pembaruan Q53: `80d32e533382446471469c5b3af6f6af387bec85`

Basis commit sebelum pembaruan Q54: `fe79a3136d38382c9bb4e9ae6a20ed182cc68d2d`

Basis commit sebelum pembaruan Q55: `32d54f628198d8a8f0e0bb1c3fe65fe27d332833`

Basis commit sebelum pembaruan Q56: `7851d3035ebc8f069c2a123e2242f2c6faef91fb`

Basis commit sebelum pembaruan Q57: `118bf003162217d30a77a732db723574174213e8`

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

**Keputusan 6 Oktober 2026 (Q33)** menegaskan domain nilai untuk baseline dan target tahunan PK: keduanya boleh `null` secara independen; jika diisi harus berupa desimal finite dan nonnegatif (`>= 0`); nilai `0` adalah nilai sah dan tidak sama dengan `null`; serta tidak ada batas universal maksimum `100`. Q33 hanya berlaku pada baseline dan target tahunan PK dan tidak otomatis berlaku pada realisasi, nilai komponen, atau target periode Rencana Aksi.

**Keputusan 9 Oktober 2026 (Q34)** meratifikasi aturan Penanggung Jawab indikator — termasuk pergantian PJ pada tanggal yang sama dengan satu PJ efektif deterministik — serta ADR-0007 (`komponen_id = NULL` untuk target manual Rencana Aksi) dan ADR-0008 (`alasan_deviasi_pk`, wajib saat pengajuan, bukan saat simpan draf).

**Keputusan 9 Oktober 2026 (Q35)** menetapkan bahwa snapshot jadwal yang sudah terbit tidak pernah diubah in-place (koreksi selalu lewat versi baru), bahwa `lingkup_koreksi` tanpa `periode_ids` berarti tidak ada periode tercakup (gagal tertutup) untuk Rencana Aksi dan Pengukuran, dan bahwa satu jadwal tahunan memuat paling banyak 12 periode.

**Keputusan 10 Oktober 2026 (Q36)** menetapkan bahwa bukti dukung `rencana_aksi`/`pengukuran` hanya dapat ditambah, diganti, atau dihapus selama induknya `draft`/`dikembalikan`; sejak `diajukan` koreksi bukti lewat pengembalian beralasan, dan setelah `disahkan` bukti imutabel.

**Keputusan 9 Oktober 2026 (Q37; penomoran diselaraskan 12 Oktober 2026)** menetapkan guard review Rencana Aksi pada indikator arsip/unit nonaktif, memisahkan audit permission sensitif dari kewajiban alasan pengguna, serta menetapkan PJ efektif sebagai penerima event pengembalian setelah commit. Pengiriman in-app menunggu ISS-12.02.

**Keputusan 13 Oktober 2026 (Q38)** menghapus langkah aktivasi: akun SSO baru langsung `aktif` tanpa role, dan penonaktifan akun memakai status `nonaktif` dari halaman Penetapan Peran.

**Rapat asistensi LLDIKTI 13 Oktober 2026 (Q39–Q46)** menetapkan dua lapis target dengan target PK sebagai acuan pengukuran (Q39); revisi PK berversi dan revisi Renstra ringan (Q40); Rencana Aksi tanpa jendela penyusunan dengan kunci per triwulan (Q41); kegiatan, anggaran, dan RAB di dalam Rencana Aksi, menggantikan Q28 (Q42); penyempitan kewajiban alasan (Q43); penghapusan Kebijakan Storage dengan batas teknis di server serta penataan menu referensi, menggantikan Q23 (Q44); narasi pelaporan wajib, Rekomendasi Pimpinan oleh Pimpinan, dan dashboard kegiatan (Q45); serta penggunaan pertama 2026 dan urutan pengerjaan (Q46). Status konfirmasi tertulis Tim Perencanaan dicatat pada pengantar Q39–Q46.

**Analisis dokumen resmi 2026 (Q47–Q51)**, ditetapkan PM pada 13 Oktober 2026, menetapkan indikator dinilai tahunan, periode tanpa target, dan realisasi "belum dapat diukur" (Q47); keluaran Rencana Aksi tahunan dan Laporan Kinerja Triwulan beserta unggahan PDF bertanda tangan (Q48); atribut kegiatan dan salin kegiatan (Q49); nomor PK opsional, presisi target, dan kode indikator mengikuti PK (Q50); serta alur draf dan persetujuan Rekomendasi Pimpinan (Q51). Dasar dan status konfirmasinya dicatat pada pengantar Q47–Q51.

**Keputusan 14 Oktober 2026 (Q52)** menetapkan penutupan resmi jadwal tahunan: alasan wajib untuk `jadwal:tutup`, tahun terkunci bila jadwal berstatus `ditutup` atau tanggal `penutupan` terlewati, serta penguncian kegiatan per tahun.

**Keputusan 15 Oktober 2026 (Q53)** menetapkan bahwa Penetapan Peran tidak dapat menurunkan peran superadmin aktif terakhir, melengkapi Q38 §38.2.

**Keputusan 16 Oktober 2026 (Q54)** menetapkan definisi unit berizin untuk detail Rencana Aksi dan Kegiatan serta mensyaratkan izin baca bagi izin tulis kegiatan, mempertegas Q12 dan Q49 §49.3.

**Keputusan 16 Oktober 2026 (Q55)** menetapkan satu draf terbuka, token draf dan rekomendasi berlaku, persetujuan langsung oleh penyetuju cadangan, serta pembekuan dan token rekomendasi pada pengajuan pengukuran, melengkapi Q51.

**Keputusan 17 Oktober 2026 (Q56)** menetapkan bahwa Admin tetap memegang `audit:read` bersama Superadmin, Perencanaan, dan Pimpinan, menyelaraskan PRD §25.4 dengan Plan Pengembangan, dan menerima secara sadar bahwa izin ini membuka payload audit lintas modul, termasuk draf.

**Keputusan 17 Oktober 2026 (Q57)** menetapkan bahwa revisi resmi jendela PIC dapat ikut menyesuaikan jendela reviu periode yang sama dalam satu aksi beralasan dan teraudit, sedangkan penanda reviu terlambat yang sudah tercatat tetap beku, melengkapi Q18 dan Q41 §41.1 butir 3.

**Keputusan 17 Oktober 2026 (Q60)** menetapkan bahwa target Renstra disimpan tanpa alasan pada Renstra `draft` maupun `aktif`, sedangkan audit tetap mencatat nilai lama dan baru per tahun, mengubah Q40 §40.2 butir 3 dan Q43 §43.2 butir 3.

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
| Q4 | In-app baseline MVP; WA/email sebelum 9 November 2026. Pembukaan jadwal, pengingat PIC H-7/H-3/H-1, rekap Perencanaan H-3/H-1, serta pengembalian berkas. **Tenggat WA/email digantikan Q46 §46.3 (17 Oktober 2026):** ISS-12.01–ISS-12.07 dan ISS-13.04 mengikuti rencana sprint setelah Tahap 2. | PRD §28; Workflow §22; Plan 14; Model pengaturan |
| Q5 | IKU 3 lima input, masing-masing koefisien 0,5; subtotal SAKIP merupakan tampilan turunan. Mesin formula bertingkat generik tetap ditunda. | PRD §17.10–17.11; Plan 5.13/5.17 |
| Q6 | Definisi pembilang/penyebut/satuan/waktu/sumber IKU 8 harus ditetapkan Perencanaan sebelum produksi. Tidak memilih jumlah PTS hanya karena contoh 84. | PRD §17.11.2 |
| Q7 | Indikator manual punya satu target langsung per periode; nonmanual target komponen dan skor turunan; tanpa komponen semu. **Diperbarui Q47:** indikator dinilai tahunan boleh tanpa target pada periode sebelum periode nilai akhir. | PRD §14.3; Model rencana_aksi_target; Q47 |
| Q8 | Realisasi memakai basis yang sebanding dengan target kumulatif dan definisi per indikator; rasio tidak dijumlahkan dan populasi tidak dihitung ganda. | PRD §14.4, §17 |
| Q9 | Penyebut nol faktual dengan komponen lengkap boleh diajukan/disahkan beralasan sebagai tidak dapat dihitung; dibedakan dari belum diisi dan bukan nol. **Q47** menambah realisasi "belum dapat diukur", yang berbeda dari tidak dapat dihitung. | PRD §17.4, §19.4, §23; Q47 |
| Q10 | Koreksi salah input terhadap sumber resmi menerbitkan versi snapshot pengganti beralasan/bukti, mempertahankan versi lama dan memerlukan pengesahan baru untuk hasil resmi yang dikoreksi. | PRD §12.5–12.7; Model jadwal_snapshot |
| Q11 | Laporan disahkan mempertahankan target RA, klaim, narasi dan bukti yang diperiksa; perubahan kerja tidak memperbarui laporan historis diam-diam. | PRD §14.6, §22.4; Model tabel versi |
| Q12 | Detail RA/kegiatan dan bukti PIC dibatasi unit berizin, termasuk deny; ringkasan umum tetap sesuai baseline. **Dipertegas Q54:** definisi unit berizin dan syarat izin baca bagi izin tulis kegiatan. | PRD §7; Model permission; Workflow §19; Q54 |
| Q13 | Jalur kerja PIC atas RA/pengukuran memerlukan cakupan unit yang sah dan PIC indikator efektif; setelah Q31, jalur operasional normal juga menggunakan role `pic`. Perencanaan tetap memakai kewenangan global; kegiatan tetap kolaboratif sesuai permission unit. | PRD §7, §13, §14.8, §19.3; Q31 |
| Q14 | Admin tidak memiliki izin substantif secara bawaan; grant eksplisit beralasan dapat memberi pengecualian sesuai katalog, tetap tunduk deny/scope/business guard/waktu/F1. Grant tidak otomatis mengubah role seseorang menjadi PIC dan tidak otomatis menjadikannya penanggung jawab indikator. **Diperbarui Q43:** alasan pemberian grant opsional; bila kosong, server mengisi alasan baku. | PRD §7.3–7.6; Q31; Q43 |
| Q15 | F1/F2 berlaku untuk RA dan Pengukuran. Gunakan identitas/jalur pengaju yang dibekukan saat pengajuan, bukan pembuat draft atau role terkini; self-approval Perencanaan ditandai. | PRD §7.6; Workflow §20; Model versi |
| Q16 | Jadwal normal mewajibkan jendela RA selesai sebelum pengisian pertama; indikator baru/revisi/backfill/pembukaan resmi memakai jalur pengecualian teraudit. **Digantikan Q41:** Rencana Aksi tidak lagi memakai jendela penyusunan; periode terkunci bagi PIC setelah jendela pengisiannya lewat. | PRD §12.3; Plan 3.4; Q41 |
| Q17 | Perencanaan dapat menyelesaikan reviu sampai penutupan tahunan; lewat batas reviu diberi penanda terlambat. | PRD §12.3, §31 |
| Q18 | Perencanaan berwenang dapat mengubah jendela resmi PIC dengan alasan, batas baru, dan audit batas lama. Di luar jendela PIC tetap terkunci. **Q41:** berlaku untuk jendela pengisian pengukuran; jendela penyusunan RA sudah dihapus. **Dilengkapi Q57:** revisi resmi dapat ikut menyesuaikan jendela reviu periode yang sama. | PRD §12.3; Plan 3.12; Q41; Q57 |
| Q19 | Koreksi setelah penutupan memerlukan buka kembali dengan lingkup dan durasi eksplisit; tanggal penutupan asli dipertahankan. PIC memerlukan aksi pembukaan jendela terpisah Q18 dalam waktu/lingkup sesi koreksi, bukan otomatis dari buka tahun. | PRD §12.6, §19.5 |
| Q20 | Indikator baru memiliki periode mulai berlaku; periode sebelumnya Tidak berlaku dan tidak menjadi target/missing/nol. | PRD §10.5, §14.7, §23 |
| Q21 | Backfill dapat menerima skor historis final tanpa komponen, dengan sumber/alasan/penanda dan alur pengesahan; tidak mengubah tipe master atau membuat komponen rekaan. | PRD §12.6, §17.6 |
| Q22 | Bukti kegiatan memakai union persyaratan global dan indikator yang diklaim; klaim baru setelah terlaksana memeriksa bukti tambahan. | PRD §15.6; Workflow §8–10 |
| Q23 | Saat unggahan mati, hanya kewajiban mode file dikecualikan; mode lain tetap diwajibkan sesuai kombinasi. Penanda/audit menjelaskan pengecualian. Pengecualian khusus lampiran PK existing tetap berlaku. **Digantikan Q44:** saklar unggahan dihapus sehingga unggahan file selalu aktif; histori penanda lama tetap tersimpan. | PRD §18.5–18.7 dan §12.4; Q44 |
| Q24 | Persyaratan substantif dibekukan saat versi diajukan. Perubahan berlaku pengajuan berikutnya; proses berjalan harus dikembalikan beralasan jika ingin memakai ketentuan baru; hasil sah tidak otomatis invalid. | PRD §18.11; Model tabel versi |
| Q25 | Koreksi bukti kegiatan append-only: bukti baru, alasan dan hubungan menggantikan, dengan bukti lama tetap utuh; bukan rollback status untuk menghapus. | PRD §15.6, §18.8 |
| Q26 | Koreksi klaim mengikuti sumbernya: perencanaan mengikuti RA, pengukuran mengikuti pengukuran terkait; versi historis tidak dihapus. | PRD §16.5 |
| Q27 | Status capaian terkait versi disahkan. Versi koreksi yang disahkan mulai Belum ditetapkan; penilaian sebelumnya tetap histori. | PRD §19.5, §21 |
| Q28 | Anggaran kegiatan tidak diinput/ditampilkan/divalidasi pada MVP; kolom nullable untuk fase lanjutan. **Digantikan Q42:** anggaran per kegiatan diinput, ditampilkan, dan dijumlahkan; RAB wajib untuk kegiatan beranggaran. | PRD §5.2, §15.1; Model kegiatan; Q42 |
| Q29 | Perencanaan mengesahkan satu contoh keluaran Excel untuk UAT. Pisahkan baseline/target/realisasi/persentase; kesetaraan informasi tidak mewajibkan menyalin susunan sumber yang ambigu. **Q48** menambah keluaran Rencana Aksi tahunan dan Laporan Kinerja Triwulan. | PRD §22.4, §24; Plan ekspor; Q48 |
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

> **Digantikan Q38 (13 Oktober 2026):** akun SSO baru langsung aktif tanpa role. Alur di bawah dipertahankan sebagai riwayat keputusan.

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

> **Diperbarui Q46 (13 Oktober 2026):** TW III 2026 ikut menjadi periode lampau yang diisi Perencanaan; TW IV 2026 menjadi triwulan pertama workflow normal PIC operasional. Gerbang baru Q42–Q45 juga dikecualikan untuk periode lampau, sedangkan kunci per triwulan (Q41) tetap berlaku bagi PIC (Q46 §46.1 butir 1). Diagram di bawah dipertahankan sebagai riwayat keputusan.
>
> **Diperjelas Q63 (19 Oktober 2026):** "berkas wajib dikecualikan" hanya berlaku pada jalur Perencanaan; PIC tidak pernah dikecualikan, termasuk dari gerbang minimal satu kegiatan (lihat §63.1).

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

> **Diperbarui Q46 (13 Oktober 2026):** RA 2026 yang dicatat Perencanaan sebagai data awal dikecualikan dari gerbang kegiatan minimal dan RAB (Q42); kegiatan TW IV ditambahkan lewat klaim tahap pengukuran atau buka-kembali RA oleh Perencanaan. Batas "sebelum pengajuan TW III" dibaca sebagai "sebelum pengajuan TW IV".

Rencana Aksi 2026 sudah disusun oleh Perencanaan. Data tersebut harus dicatat di aplikasi dengan status **disahkan** sebelum pengajuan TW III karena gerbang pengajuan mensyaratkan RA yang sah.

Penetapan PIC operasional melalui workflow normal per periode dimulai pada Rencana Aksi berikutnya (2027). Initial setup 2026 diperlakukan sebagai data awal yang diverifikasi Perencanaan, bukan migrasi role PIC.

## 32.9 IKU 8 — Definisi Final

> **Catatan Q50 (13 Oktober 2026):** "IKU 8" memakai nomor Renstra; pada PK, Rencana Aksi, dan laporan triwulan indikator ini berkode **IKU 3.3** (Q50 §50.4). Definisi IKU 8 pada Renstra 2025–2029 belum sama dengan definisi di bawah, dan target Renstra-nya turun tajam setelah 2026; klarifikasi definisi, arah, dan target 2027–2029 dicatat pada tindak lanjut. Target PK 2026 tetap 59,5.

IKU 8 bertipe `rasio_persen` dengan formula:

```text
n / t × 100%
```

Penyebut `t` adalah **total publikasi seluruh PTS di wilayah kerja**. Angka 84 adalah jumlah PTS wilayah kerja dan **bukan** penyebut IKU 8; angka tersebut berasal dari indikator lain.

Target PK 59,5 tetap digunakan apa adanya. Nilai `t` diisi Perencanaan dari sumber resmi dan tidak boleh di-hardcode.

## 32.10 IKU 3 — Baseline, Target, dan Formula Final

> **Catatan Q47 dan Q50 (13 Oktober 2026):** "IKU 3" memakai nomor Renstra; pada PK, Rencana Aksi, dan laporan triwulan indikator ini berkode **IKU 1.3** (Q50 §50.4). Indikator ini ditandai **dinilai tahunan** (Q47 §47.1).

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

# Keputusan Q33 — Domain Nilai Baseline dan Target Tahunan PK

**Keputusan pengguna: 6 Oktober 2026 (WITA).**

Q33 menutup ketidakjelasan domain nilai pada `target_tahunan` tanpa mengubah formula, target resmi, snapshot historis, permission, atau workflow lain yang sudah ditetapkan.

## 33.1 Kontrak Nilai

Untuk baseline dan target tahunan PK berlaku ketentuan berikut:

1. `baseline` dan target tahunan boleh `null` secara independen.
2. `null` berarti nilai belum diisi; `0` adalah nilai sah dan harus dibedakan dari `null`.
3. Jika diisi, nilai harus berupa desimal biasa yang **finite** dan **nonnegatif (`>= 0`)**.
4. `NaN`, infinity, nilai negatif, atau representasi non-desimal biasa tidak termasuk domain nilai yang sah.
5. Tidak ada batas universal maksimum `100`. Nilai di atas `100` tetap dapat sah selama sesuai definisi indikator, presisi indikator, dan kapasitas storage.
6. Ketentuan ini hanya berlaku pada **baseline dan target tahunan PK**. Aturan ini tidak otomatis diterapkan pada realisasi/pengukuran, nilai komponen, maupun target periode Rencana Aksi.

## 33.2 Dampak ke Kontrak Implementasi

- Data Model `target_tahunan` harus menyatakan domain nullable, finite, dan nonnegatif secara eksplisit.
- Validasi domain wajib ditegakkan server-side; constraint database digunakan sebagai pertahanan integritas untuk writer yang melewati UI.
- Q33 tidak menambah permission baru. Mutation tetap mengikuti permission target tahunan yang sudah ditetapkan.
- Q33 tidak mengubah snapshot historis existing dan tidak memberi izin untuk memutasi snapshot lama.
- Q33 tidak mengubah target resmi IKU 3 maupun keputusan Q32 mengenai baseline 74,2, target 76,25, dan larangan menampilkan selisih keduanya sebagai tren yang sebanding.

## 33.3 Traceability

Q33 diselaraskan minimal ke:

- `SAKIP - Data Model.md` §2.13 `target_tahunan`;
- `SAKIP - User Issues.md` — `ISS-02.07`;
- `SAKIP - User Stories.md` — `US-02.07` hanya bila Acceptance Criteria perlu dibuat lebih eksplisit;
- implementation dan regression test `ISS-02.07`.

Q33 adalah keputusan domain tersendiri. **Jangan merujuk ketentuan nonnegatif ini sebagai “Q5 Issue #28”**, karena Q5 pada dokumen ini membahas formula IKU 3 dan bukan domain baseline/target tahunan.

---

# Keputusan Q34 — Aturan Penanggung Jawab Indikator dan Struktur Target Rencana Aksi

**Keputusan PM (Dion), 9 Oktober 2026 (WITA), via WhatsApp atas konfirmasi tim pengembang.**

Q34 meratifikasi aturan Penanggung Jawab (PJ) yang berstatus *pending stakeholder decision* pada ISS-04.01 serta dua penyimpangan struktur Rencana Aksi dari Data Model yang dicatat sebagai ADR-0007/0008.

## 34.1 Aturan Penanggung Jawab Indikator

1. **Pergantian PJ pada tanggal yang sama diperbolehkan.** Histori penugasan tetap append-only. Sistem menentukan tepat satu PJ efektif secara deterministik berdasarkan tanggal efektif lalu urutan penugasan. Perubahan dari keadaan usang ditolak melalui locking dan validasi stale-state.
2. Tanggal penetapan boleh lampau maupun mendatang, tetapi tidak boleh mengubah data historis yang sudah dibekukan.
3. Penetapan ulang ke PJ yang sama ditolak bila tidak mengubah PJ efektif pada tanggal yang dipilih.
4. Mantan PJ boleh ditetapkan kembali.
5. Penugasan mendatang yang sudah ada tetap dipertahankan saat ditambahkan penugasan bertanggal lebih awal.
6. Indikator arsip, Renstra arsip, atau unit nonaktif tidak menerima perubahan PJ baru; histori tetap dapat dibaca sesuai akses.

**Status implementasi:** butir 2–6 sudah diimplementasikan pada ISS-04.01 (#68). Butir 1 diimplementasikan pada #70: unique `(indikator_id, tanggal_mulai_berlaku)` dilepas dan kolom identity `urutan` menentukan PJ efektif pada tanggal yang sama, bukan `created_at` atau UUID. Perubahan dikerjakan pada modul Penanggung Jawab tanpa mengubah business logic Rencana Aksi, disertai regression test pergantian tanggal sama, concurrency, histori, penugasan mendatang, serta integrasi Rencana Aksi dan Pengukuran.

## 34.2 Struktur Target dan Alasan Deviasi Rencana Aksi

1. **ADR-0007 disetujui:** target indikator manual memakai `rencana_aksi_target.komponen_id = NULL`, tepat satu baris per periode, tanpa komponen semu, dengan constraint database yang menjamin keunikannya.
2. **ADR-0008 disetujui:** kolom `rencana_aksi.alasan_deviasi_pk` terpisah dari `alasan_revisi`. Penyimpanan draf hanya menghasilkan peringatan deviasi; alasan wajib divalidasi saat pengajuan Rencana Aksi (ISS-05.03).

## 34.3 Dampak dan Traceability

- Data Model §2.23–§2.24, ERD, dan §5 diselaraskan dengan 34.2.
- `SAKIP - Architecture Decision Records.md`: ADR-0007/0008 menjadi `Accepted`.
- Penanda *pending stakeholder decision* PJ-01–PJ-05 pada PRD §13, Data Model §2.19/§5, Workflow §22.1, User Stories US-04.01, dan User Issues ISS-04.01 diganti status Q34 §34.1; PJ-02 dicatat sebagai digantikan butir 1 dan dilacak #70.
- Keputusan PJ tidak menjadi blocker khusus merge ISS-05.01 (PR #62) selama Rencana Aksi memakai resolver PJ kanonis modul Penanggung Jawab dan seluruh quality gate terpenuhi.

---

# Keputusan Q35 — Koreksi Snapshot, Lingkup Koreksi, dan Batas Periode Jadwal

**Keputusan PM (Dion), 9 Oktober 2026, melalui [komentar PR #62](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/62#issuecomment-6077136548)** atas usulan tindak lanjut review PR #62.

Q35 menutup tiga kontrak yang ditemukan saat review ISS-05.01: aturan dokumen masih mengizinkan koreksi snapshot in-place sebelum snapshot dirujuk, makna `lingkup_koreksi` tanpa daftar periode belum ditetapkan, dan jumlah periode per jadwal belum dibatasi.

## 35.1 Koreksi Snapshot Selalu Lewat Versi Baru

1. Snapshot jadwal (`jadwal_snapshot` beserta `jadwal_snapshot_komponen`) yang sudah terbit, yaitu dibentuk saat jadwal bertransisi ke `aktif`, tidak diubah in-place, baik sudah maupun belum dirujuk Rencana Aksi atau Pengukuran.
2. Koreksi konteks dilakukan dengan menyisipkan baris versi baru (`nomor_versi` + 1, `menggantikan_id` ke versi sebelumnya, `alasan_koreksi`, `rujukan_koreksi`). Versi lama tetap utuh untuk histori dan rujukan yang sudah ada.
3. Aturan lama "snapshot boleh dikoreksi selama belum dirujuk dan jadwal `aktif`" dicabut.

**Status implementasi (PR #62):** guard database `guard_referenced_schedule_snapshot` menolak UPDATE kolom konteks dan DELETE pada `jadwal_snapshot`; UPDATE yang diterima hanya no-op atau transisi `komposisi_final` `false → true`. UPDATE/DELETE `jadwal_snapshot_komponen` selalu ditolak, dan INSERT komponen ditolak bila induknya sudah final atau sudah dirujuk. Trigger `finalisasi_snapshot_saat_jadwal_aktif` memfinalkan seluruh snapshot jadwal setiap kali status jadwal berubah ke `aktif`. Alur koreksi berversi bagi pengguna dibangun pada ISS-03.03.

## 35.2 Lingkup Koreksi Gagal Tertutup

1. `jadwal_tahunan.lingkup_koreksi` tanpa `periode_ids` berarti **tidak ada periode yang tercakup**; tidak ada pengartian "semua periode".
2. Berlaku untuk Rencana Aksi dan Pengukuran.

**Status implementasi:** Pengukuran (`PengukuranKinerjaPolicy`, sudah di `development`) dan Rencana Aksi (`JendelaTulisRencanaAksi`, PR #62) memperlakukan `periode_ids` yang tidak ada sebagai daftar kosong. Penetapan lingkup koreksi tetap milik ISS-14.02.

## 35.3 Batas Periode per Jadwal

1. Satu jadwal tahunan memuat paling banyak **12 periode**.

**Status implementasi (PR #62):** `StoreJadwalRequest` memvalidasi `periode` `max:12` (`RencanaAksiMatrixLimitTest`: 13 periode ditolak, 12 diterima). Batas ini juga menjadi dasar ukuran matriks Rencana Aksi (50 komponen × 12 periode = 600 sel).

## 35.4 Dampak dan Traceability

- Data Model §2.17 (kolom versi, `komposisi_final`, constraint, dan perilaku), §2.18 (komponen beku sejak induk final), §2.23 (`snapshot_draf_id`), ERD, dan prinsip desain §7 butir 2 diselaraskan.
- PRD §12.5, Workflow §5, dan Plan 3.8 mengganti aturan "abadi setelah dirujuk / koreksi sebelum dirujuk" dengan 35.1.
- User Issues dan User Stories: ISS-02.09/US-02.09 AC-3, ISS-03.03/US-03.03 AC-1–AC-2 beserta task terkait, task imutabilitas ISS-03.04, ISS-03.01/US-03.01 AC-6 (35.3), dan catatan 35.2 pada ISS-14.02.
- Keputusan ini tidak membutuhkan perubahan kode di luar yang sudah ada pada PR #62.

---

# Keputusan Q36 — Jendela Mutasi Bukti Dukung Rencana Aksi dan Pengukuran

**Keputusan PM (Dion), 10 Oktober 2026 (WITA), dalam review PR #76 (ISS-05.02, keputusan D1).**

Q36 menutup ketidakselarasan antara PRD §18.8, Workflow §10.3/§10.6a, dan Data Model §2.30/§5 (lampiran `rencana_aksi`/`pengukuran` dapat dihapus sampai induk `disahkan`) dengan kontrak ISS-05.02 ("RA berstatus dapat diedit"), prinsip pembekuan Q24, dan implementasi Pengukuran yang sudah berjalan.

## 36.1 Aturan

1. Bukti dukung (`berkas`) berinduk `rencana_aksi` dan `pengukuran` hanya dapat **ditambah, diganti (`menggantikan_id`), atau dihapus** selama induknya berstatus `draft` atau `dikembalikan`.
2. Sejak induk berstatus `diajukan`, bukti dukung induk tersebut tidak dapat ditambah, diganti, maupun dihapus. Koreksi bukti dilakukan melalui pengembalian beralasan ke status `dikembalikan` oleh pemegang izin pengembalian, lalu bukti diperbaiki dan induk diajukan ulang.
3. Setelah induk `disahkan`, bukti dukung imutabel. Setelah `jadwal_tahunan.penutupan`, koreksi hanya lewat `jadwal:buka_kembali` (tidak berubah).
4. Penghapusan tetap soft delete (`dihapus_pada`/`dihapus_oleh`), beralasan, dan teraudit; file fisik dipertahankan.
5. Penolakan mutasi bukti di luar status pada butir 1 adalah validasi bisnis (422), bukan penolakan izin (Data Model §3.2 langkah 6).
6. Aturan untuk induk `kegiatan`, `renstra`, `renstra_pk`, dan `regulasi` tidak berubah.

## 36.2 Status implementasi

- **Rencana Aksi:** PR #76 (ISS-05.02).
  - `GerbangBuktiRencanaAksi::pelanggaranBisnis()` memakai `RencanaAksi::STATUS_DAPAT_DISUNTING` (`draft`, `dikembalikan`) untuk tambah dan hapus.
  - Dibuktikan oleh `RencanaAksiBuktiHttpTest::test_status_di_luar_draf_ditolak_sebagai_validasi_bisnis_bukan_izin`.
- **Pengukuran:** sudah sesuai di `development`. Bukti hanya masuk lewat aksi `draft`/`ajukan` pada status `draft`/`dikembalikan` (`PengukuranKinerjaPolicy`); koreksi lewat `menggantikan_id`; tidak ada endpoint hapus bukti pengukuran.
- Q36 tidak membutuhkan perubahan kode.

## 36.3 Dampak dan Traceability

- PRD §18.8; Workflow §10.3 langkah 6–7 beserta diagramnya, dan §10.6a; Data Model §2.30 (imutabilitas lampiran) dan §5 (constraint level aplikasi `berkas`) diselaraskan.
- Plan Pengembangan 13.6 (scope dan DoD guard imutabilitas bukti) dan tabel klasifikasi permission (`berkas:upload`/`berkas:delete`) diselaraskan.
- User Stories US-11.04 dan User Issues ISS-11.04: kontrak otorisasi hapus bukti diselaraskan dengan Q32.3 dan guard status induk Q36.
- User Issues ISS-05.02: catatan "Keputusan terbuka (D1)" diganti rujukan Q36, dan butir DoD dokumentasi diperbarui.
- Q24, Q25, Q32, dan Q35 tidak berubah.

# Keputusan Q37 — Guard Review, Alasan Verifikasi, dan Penerima Pengembalian Rencana Aksi

**Keputusan pengguna, 9 Oktober 2026**, melalui jawaban Q1–Q3 dalam percakapan analisis lokal untuk [ISS-05.04 / Issue #58](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/58).

Penomoran keputusan review menjadi Q37 mengikuti [review PR #93](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/93#issuecomment-6110484512), agar Q36 tetap menunjuk keputusan jendela mutasi bukti. Persetujuan awal substansi berasal dari jawaban pengguna dalam percakapan. Pada 12 Oktober 2026, pemilik keputusan SAKIP mengonfirmasi kewenangannya dan meratifikasi Q37 melalui [komentar keputusan D1 dan ratifikasi Q37 pada PR #93](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/93#issuecomment-6113303169). Ketiga keputusan tersebut dicatat utuh pada §37.1–§37.3; penomoran dan ratifikasi tidak mengubah substansinya serta tidak mengesahkan UAT atau deployment.

## 37.1 Guard Konteks Operasional Review

1. Verifikasi dan pengembalian Rencana Aksi ditolak bila indikator berstatus `arsip` atau unit konteks Rencana Aksi berstatus `nonaktif`. Detail dan histori existing tetap dapat dibaca sesuai otorisasi; penolakan tidak mengubah status atau payload versi lama.
2. Guard dipusatkan pada `businessErrors` yang sama untuk verifikasi, pengembalian (ISS-05.04), dan pengesahan (ISS-05.05), ditegakkan server-side termasuk untuk request langsung dan diperiksa ulang pada mutation boundary. F2 tidak melewati guard ini.
3. Penolakan diaudit; UI menerima capability dan alasan dari server agar alasan aksi tidak tersedia dapat ditampilkan.
4. Renstra `diarsipkan` sudah tertutup oleh guard jadwal: Renstra tidak dapat keluar dari `aktif` selama masih mempunyai jadwal aktif (Data Model §2.9 pada baseline ini). Keputusan ini tidak menambahkan guard Renstra arsip terpisah.
5. Aturan unit nonaktif selaras dengan Pengukuran. Pengecekan indikator arsip pada verifikasi Pengukuran menjadi tindak lanjut untuk keseragaman kedua modul; Q37 tidak mengklaim perubahan tersebut sudah diimplementasikan. Tambahan Pengukuran (ditetapkan PM 15 Oktober 2026 melalui komentar review PR #107): guard indikator `arsip` pada Pengukuran berlaku untuk kelima perintah — simpan draf, ajukan, verifikasi, kembalikan, dan sahkan — dengan pesan `Indikator berstatus arsip; pengukuran tidak dapat diubah, diajukan, atau direviu.`; penolakan diaudit tanpa mengubah header; detail/histori tetap terbaca. Opsi pembatasan ke perintah review saja ditolak: PIC masih dapat mengajukan pengukuran yang kemudian tidak mungkin direviu.

## 37.2 Konfirmasi Verifikasi dan Alasan Pengembalian

1. Verifikasi Rencana Aksi cukup konfirmasi; tidak mewajibkan alasan dari pengguna. Audit verifikasi memakai alasan baku yang ditentukan server dan mencatat `dasar_izin`.
2. Di antara verifikasi dan pengembalian, alasan pengguna wajib hanya untuk pengembalian; alasan divalidasi dan disanitasi di backend memakai helper canonical sebelum dicatat.
3. `permissions.sensitif = true` mewajibkan audit `dasar_izin`, bukan otomatis alasan pengguna. Kewajiban alasan mengikuti daftar aksi/aturan domain dalam PRD prinsip 6 dan Data Model §2.32; alasan untuk aksi lain yang sudah diwajibkan tetap berlaku.
4. Checklist dan pola modal `design-system.md` diselaraskan agar permission sensitif tidak disamakan dengan kewajiban input alasan.

## 37.3 PJ Efektif dan Event Setelah Commit

1. Penerima pengembalian adalah PJ efektif pada saat pengembalian, menggunakan `PenugasanIndikator::effectiveOn()`; penerima tidak ditentukan dari pembuat draf, `penanggung_jawab_id` historis pada header, atau pengaju historis.
2. Pengaju historis tetap tercatat pada `rencana_aksi_versi.diajukan_by` dan audit. Pergantian PJ tidak mengubah provenance versi yang direview.
3. ISS-05.04 menetapkan penentuan penerima dan event pengembalian setelah commit; transaksi yang rollback tidak memancarkan event tersebut. Tautan ke Rencana Aksi tetap diperiksa otorisasinya ketika dibuka.
4. Infrastruktur notifikasi belum tersedia pada baseline ini. Pengiriman in-app menunggu ISS-12.02; AC-3 ISS-05.04/US-05.04 diberi catatan pemisahan cakupan ini, tanpa mengklaim delivery sudah tersedia. Pemisahan yang sama berlaku untuk Pengukuran (ditetapkan PM 14 Oktober 2026): AC-7 ISS-07.03/US-07.03 dibuktikan lewat antrean verifikasi berbasis status, dan AC-3 ISS-08.01/US-08.01 tidak mencakup pengiriman notifikasi pengembalian. Pengiriman keduanya menjadi cakupan ISS-12.02 (AC-2 dan AC-1).

## 37.4 Dampak dan Traceability

- ISS-05.04/US-05.04: guard verifikasi/pengembalian, konfirmasi verifikasi, audit penolakan, serta AC-3 dan test penerima/event setelah commit diselaraskan.
- ISS-05.05/US-05.05: pengesahan memakai `businessErrors` yang sama dan menolak konteks indikator arsip/unit nonaktif, termasuk pada jalur F2.
- Data Model §2.23, §2.32, dan §3.5 serta pola modal/checklist design system diselaraskan. Rujukan Renstra menggunakan §2.9 sesuai struktur file yang diverifikasi.
- Q37 adalah keputusan requirement, bukan bukti implementasi atau kelulusan test. Dependency ISS-05.03 / #57 telah merged ke `development` melalui PR #92 pada 11 Oktober 2026; penyelarasan dan verifikasi consumer #58 tetap dibuktikan terpisah.

## 37.5 Keputusan Integrasi Antrean dan Guard Review (D1)

[Keputusan D1 pada PR #93](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/93#issuecomment-6113303169), disahkan pemilik keputusan SAKIP pada 12 Oktober 2026:

1. ISS-05.04 / PR #93 menjadi pemilik antrean `/rencana-aksi/review`, detail `/rencana-aksi/{id}/review`, `GerbangReviewRencanaAksi`, dan delegasi `RencanaAksiPolicy::businessErrors`. Antrean itu menyatu dengan daftar `/rencana-aksi` lewat filter status sejak Q59 (§59.1); kepemilikan gerbang, policy, dan detail review tidak berubah.
2. ISS-05.05 / PR #66 diselaraskan setelah #93 merged ke `development`: gunakan antrean, halaman review, dan guard yang sama, hapus implementasi paralel yang tumpang tindih, lalu tambahkan aksi Sahkan dan capability-nya pada surface tersebut.
3. Pengesahan tetap memeriksa izin, F1/F2, versi terbaru, status, konteks dan audit pada mutation boundary; F2 tidak melewati business guard konteks. Hasil integrasi #66 perlu direview dan diuji ulang untuk konflik, method ganda, pengesahan, F1/F2, transaksi/audit dan concurrency.
4. D1 menetapkan arah integrasi, bukan bukti #66 sudah diperbaiki atau persetujuan merge, QA/UAT, maupun deployment. Pengesahan dan buka-kembali tetap di luar scope #58; pengiriman in-app tetap ISS-12.02.

---

# Keputusan Q38 — Onboarding SSO Langsung Aktif Tanpa Peran

**Keputusan PM, 13 Oktober 2026**, untuk [ISS-01.08 / Issue #99](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/99). Keputusan ini menggantikan alur §32.5 "Onboarding SSO Final".

## 38.1 Alur Onboarding

```text
Login Keycloak valid
        ↓
create/update users berdasarkan keycloak_id
        ↓
status = aktif
        ↓
TANPA ROLE
        ↓
halaman "Menunggu penetapan peran"
        ↓
Admin menetapkan satu dari 5 role (alasan wajib, teraudit)
```

1. `users.status` tetap enum(`aktif`,`nonaktif`) dengan default DB `nonaktif`; onboarding JIT mengisi `aktif` secara eksplisit. Login ulang tidak mengubah status.
2. User tanpa role tetap memiliki **nol permission** (`no_role`); grant dan penugasan PJ tidak memberi akses tanpa role.
3. Persetujuan akses berpindah ke penetapan role: satu langkah admin yang wajib beralasan dan teraudit.
4. Menu dan endpoint Aktivasi Pengguna (`/akses/aktivasi`) dihapus. Riwayat audit `pengguna.aktivasi` dipertahankan.
5. Bootstrap Super Admin menerima kandidat aktif tanpa role yang tercatat onboarding SSO; aktivasi hanya dicatat bila kandidat legacy masih `nonaktif`.

## 38.2 Status Nonaktif, Penonaktifan, dan Pengaktifan Kembali

1. `nonaktif` berarti akun dinonaktifkan. Middleware `active` memblokir sesi akun nonaktif pada request berikutnya; halaman pending menampilkan "Akun tidak aktif".
2. Aktor dengan `akses:update` dapat menonaktifkan dan mengaktifkan kembali akun lain melalui Penetapan Peran dengan alasan wajib dan audit. Akun sendiri dan superadmin aktif terakhir tidak dapat dinonaktifkan. Penetapan Peran juga tidak dapat menurunkan peran superadmin aktif terakhir (Q53).
3. Peran dan grant tidak dihapus saat akun dinonaktifkan, sehingga pengaktifan kembali memulihkan akses yang sama.
4. Bila pengguna masih menjadi Penanggung Jawab aktif, UI menampilkan peringatan sebelum konfirmasi; penonaktifan tidak diblokir.

## 38.3 Akun yang Menunggu Aktivasi Saat Rilis

Sebelum deploy, akun `nonaktif` yang belum pernah diaktifkan didaftar dengan query read-only dan diaktifkan admin melalui halaman Aktivasi lama (beralasan, teraudit). Bila penonaktifan/pengaktifan kembali (§38.2) dirilis bersamaan, sisa akun dapat diaktifkan sesudah deploy melalui "Aktifkan kembali". Tidak ada migrasi otomatis. Keputusan PM 14 Oktober 2026 (PR #101): Bagian A dan Bagian B dideploy bersamaan setelah konfirmasi tertulis Q38 diterima; persetujuan rapat 13 Oktober 2026 (§38.5) cukup untuk merge ke `development`. Bila Bagian B belum siap, Bagian A tidak dideploy.

## 38.4 Kandidat Grant Unit dan Penanggung Jawab

Aturan Q32 tetap: kandidat Grant Unit dan PJ adalah user `aktif`, dengan atau tanpa role. Setelah Q38 daftar tersebut mencakup semua akun yang pernah login SSO; pembatasan ke user berperan menjadi keputusan terpisah bila diperlukan.

## 38.5 Dampak dan Traceability

- Menggantikan §32.5 dan butir "onboarding JIT = nonaktif + tanpa role" pada daftar item yang RESOLVED oleh Q32.
- Diselaraskan: User Issues ISS-01.01 dan ISS-01.08, User Stories US-01.01 dan US-01.08, Data Model §2.1 `users`, Workflow §21.1 dan pohon keputusan akses, PRD risiko "Onboarding memberi akses terlalu awal", Plan Pengembangan 1.3, design system (Prinsip UI), dan README bootstrap.
- Konfirmasi LLDIKTI: pada rapat asistensi 13 Oktober 2026 (lihat pengantar Q39–Q46), Tim Perencanaan menyetujui penghapusan langkah aktivasi (menit 01:46–02:50) dan meminta satu tombol di halaman peran untuk memutus seluruh akses pengguna (menit 02:50–03:31). PM menetapkan permintaan tersebut dipenuhi penonaktifan §38.2 (status `nonaktif`; peran dan grant disimpan untuk pengaktifan kembali); usulan menghapus peran tidak dipakai. Konfirmasi tertulis: menunggu, dicatat pada pengantar Q39–Q46.
- Q38 adalah keputusan requirement, bukan bukti implementasi atau kelulusan test.

---

# Pengantar Q39–Q46 — Rapat Asistensi LLDIKTI 13 Oktober 2026

| Item | Isi |
|---|---|
| Rapat | Rapat asistensi SAKIP via Zoom, Selasa, 13 Oktober 2026 (WITA) |
| Peserta | Tim Perencanaan LLDIKTI XVI, pendamping tim magang dari LLDIKTI XVI, PM (Dion), dan tim magang |
| Rujukan menit | Menit pada Q38 §38.5 dan Q39–Q46 merujuk rekaman rapat yang disimpan PM |
| Penetapan rincian | Butir yang tidak tegas di rapat ditetapkan PM pada 13 Oktober 2026 dan ditandai **(PM)** |
| Konfirmasi tertulis | **Menunggu.** Ringkasan keputusan dikirim PM kepada Tim Perencanaan; tanggal kirim, bentuk, dan hasilnya dicatat pada baris ini |

Sesuai Q1, aturan bisnis disahkan Tim Perencanaan. Sampai konfirmasi tertulis diterima, Q39–Q46 menjadi baseline kerja tim; koreksi dari Tim Perencanaan dicatat sebagai pembaruan pada Q terkait lalu diselaraskan ulang ke dokumen turunan.

| Q | Topik | Menggantikan atau mengubah |
|---|---|---|
| Q39 | Dua lapis target: target Renstra dan target PK | PRD §8, §11; Data Model §2.13 |
| Q40 | Revisi PK berversi dan revisi Renstra | PRD §5.2, §10.5–§10.6, §12.7; Data Model §2.10 |
| Q41 | Rencana Aksi sepanjang tahun dan kunci per triwulan | Q16; Q18 (sebagian); PRD §4 prinsip 7, §12.2–§12.3, §14.1 |
| Q42 | Kegiatan, anggaran, dan RAB dalam Rencana Aksi | Q28; PRD §5.2, §15, §18.3 |
| Q43 | Kewajiban alasan dipersempit | Q14 (sebagian); PRD §4 prinsip 6, §25.2; Data Model §2.32 |
| Q44 | Kebijakan Storage dihapus, batas teknis unggahan, dan penataan menu | Q23; PRD §18.7, §18.9, §26.3–§26.4 |
| Q45 | Narasi pelaporan, Rekomendasi Pimpinan, dan dashboard kegiatan | PRD §4 prinsip 5, §11 (`wajib_catatan`), §15.3, §20, §22.2 |
| Q46 | Penggunaan pertama 2026 dan urutan pengerjaan | Q32.7, Q32.8 (sebagian) |

Issue baru yang dibentuk dari keputusan ini (satu issue untuk satu User Story): US/ISS-02.10, 02.11, 02.12, 05.07, 06.08, 13.05, dan 13.06. Q39–Q46 adalah keputusan requirement, bukan bukti implementasi atau kelulusan test.

---

# Keputusan Q39 — Dua Lapis Target: Target Renstra dan Target PK

Sumber: rapat 13 Oktober 2026, menit 58:05–1:02:45, 1:06:49–1:07:32, 1:17:04–1:19:12, dan 1:27:37–1:28:16. Butir bertanda (PM) ditetapkan PM.

## 39.1 Dua lapis target

| Lapis | Diisi di | Isi | Fungsi |
|---|---|---|---|
| Target Renstra | Sasaran & Indikator, sekali per Renstra | Target per tahun untuk setiap tahun dalam rentang Renstra, sesuai dokumen Renstra | Pembanding dan batas bawah target PK; tidak dipakai menghitung capaian |
| Target PK | Perjanjian Kinerja, per Renstra per tahun | Baseline dan target tahun berjalan per indikator | Acuan target periode Rencana Aksi dan pengukuran capaian |

1. Perjanjian Kinerja bukan sekadar unggah dokumen. Halaman PK memuat isian baseline dan target tahun berjalan untuk setiap indikator aktif Renstra, dikelompokkan per sasaran; nomor, tanggal, dan lampiran dokumen PK bertanda tangan tetap dicatat. Nomor PK opsional (Q50 §50.1), dan baseline tidak termasuk isi dokumen PK (Q50 §50.3).
2. Target tahun berjalan tidak lagi diisi di halaman Sasaran & Indikator; halaman tersebut memuat target Renstra.
3. `target_tahunan` (tabel `target_kinerjas`) tetap menyimpan **target PK**, dan editornya dipindah ke halaman PK. Domain nilai Q33 tetap berlaku. Snapshot, rekonsiliasi Rencana Aksi (ADR-0008), dan pengukuran tetap membaca target PK melalui snapshot sehingga tidak berubah. (PM)
4. Target Renstra adalah data baru per indikator per tahun Renstra dengan domain nilai yang sama dengan Q33: nullable, finite, nonnegatif, dan `0` sah. (PM)
5. Gerbang aktivasi jadwal tetap: PK tahun tersebut tercatat, setiap indikator aktif memiliki target PK, tahun jadwal berada dalam rentang Renstra, dan PK memiliki lampiran.

## 39.2 Pembanding target Renstra

1. Saat target PK diisi, target Renstra tahun yang sama ditampilkan di sampingnya.
2. Untuk indikator `naik_baik`, target PK di bawah target Renstra memunculkan **peringatan dan alasan wajib**; untuk indikator `turun_baik`, peringatan muncul bila target PK di atas target Renstra. Sistem tidak menolak penyimpanan karena PK revisi yang sah dapat lebih rendah (menit 1:48:49). (PM, atas permintaan peringatan Tim Perencanaan pada menit 1:17:04)
3. Anjuran "minimal sama atau naik satu poin" ditampilkan sebagai panduan, bukan aturan sistem. (PM)
4. Indikator tanpa target Renstra (mis. IKU baru di tengah periode Renstra, atau IKU 4.1 yang hanya ada di PK, Q50 §50.5) tetap dapat diberi target PK dengan keterangan "target Renstra belum ada". (PM)
5. Alasan deviasi terhadap target Renstra disimpan bersama target PK dan tercatat di audit. (PM)

## 39.3 Penginput target PK

1. Target PK diinput **Tim Perencanaan** setelah angka disepakati dengan tim kerja di luar aplikasi; PJ dan PIC melihat target PK sebagai baca-saja. (PM; menit 1:06:49–1:07:32 menyebut Perencanaan memvalidasi angka)
2. Alur usulan target oleh PJ di dalam aplikasi tidak dibangun pada tahap ini; bila diperlukan, ditetapkan sebagai keputusan baru.
3. Target Renstra dan target PK sama-sama disimpan dengan permission `target:update` yang sudah ada; tidak ada permission baru. (PM, 13 Oktober 2026, saat penyelarasan Q47–Q51)

## 39.4 Acuan pengukuran

1. Capaian dinilai terhadap target PK melalui snapshot, bukan terhadap target Renstra. (menit 1:27:37–1:28:16)
2. Target periode Rencana Aksi dipecah dari target PK; selisih target periode terakhir dengan target PK tetap wajib dijelaskan saat pengajuan (ADR-0008).
3. Indikator dinilai tahunan boleh tanpa target pada periode sebelum periode nilai akhir. Pada periode tanpa target, capaian persen tidak dihitung dan realisasi dibandingkan langsung dengan target PK (Q47).

## 39.5 Dampak dan traceability

- PRD §5.1, §8, §11, §12.4, §14.5, §31–§32; Data Model §2.13 dan entitas baru target Renstra; Workflow §1, §3.3, §5; Plan modul 2; System Architecture §9.
- User Stories/Issues: US/ISS-02.07 dan 02.08 diberi catatan; issue baru **US/ISS-02.10** (target Renstra) dan **US/ISS-02.11** (input target PK di halaman PK beserta pembanding target Renstra).
- Status implementasi: ISS-02.10 (target Renstra) sudah; ISS-02.11 belum. Editor target PK saat ini berada di halaman indikator (`/perencanaan/indikator/{id}/target-tahunan/{tahun}`).

---

# Keputusan Q40 — Revisi PK Berversi dan Revisi Renstra

Sumber: rapat 13 Oktober 2026, menit 27:24, 35:46, 1:28:16–1:28:48, dan 1:47:57–1:52:06. Butir bertanda (PM) ditetapkan PM.

## 40.1 Revisi PK berversi

1. PK dapat direvisi, umumnya pada akhir tahun setelah persetujuan Eselon I. Di aplikasi, revisi disetujui **Perencanaan**. (menit 1:49:21)
2. Setiap revisi membentuk **versi PK baru** yang menyimpan nomor versi, nomor dan tanggal surat persetujuan Eselon I sebagai isian tersendiri, periode mulai berlaku, lampiran dokumen PK bertanda tangan, dan target per indikator. Versi sebelumnya tidak diubah. (PM)
3. PK awal dan PK akhir sama-sama disimpan dan dapat diunduh karena keduanya menjadi lampiran laporan kinerja. (menit 1:49:53)
4. Revisi berlaku mulai **periode pertama yang belum ditutup**; periode yang sudah dilaporkan tetap memakai target versi lama. Persetujuan revisi yang mengubah target menerbitkan versi `jadwal_snapshot` baru (Q35 §35.1) dengan `periode_mulai_id` periode tersebut dan `rujukan_koreksi` surat Eselon I. (PM)
5. Revisi boleh hanya mengganti dokumen (mis. karena perubahan anggaran) tanpa mengubah target; revisi seperti itu tidak menerbitkan versi snapshot. (menit 1:50:24) Sebelum revisi berversi tersedia, PK perubahan yang hanya mengubah anggaran diunggah sebagai lampiran tambahan PK (Q50 §50.7).
6. Capaian akhir tahun dinilai terhadap target **PK akhir**, yaitu versi yang berlaku pada periode nilai akhir. (PM)
7. Rencana Aksi tidak diubah otomatis. Selisih target periode terakhir dengan target PK baru tampil sebagai deviasi yang wajib dijelaskan (ADR-0008); target periode yang belum terkunci disesuaikan lewat buka-kembali (ISS-05.06). (PM)
8. Koreksi salah ketik nomor atau tanggal PK tetap memakai jalur koreksi teraudit (ISS-02.09); perubahan target resmi memakai revisi berversi.
9. Prasyarat implementasi: alur koreksi snapshot berversi (ISS-03.03), yang belum dikerjakan. Revisi PK dijadwalkan pada tahap 3 (§46.3).

## 40.2 Revisi Renstra

1. Atribut Renstra tetap direvisi **in place** dengan audit (PRD §10.5).
2. **Lampiran revisi boleh ditambahkan setelah Renstra aktif**; lampiran yang sudah ada tetap tidak dapat dihapus. Ini menyelaraskan implementasi, yang saat ini menolak penambahan lampiran pada Renstra bukan draf (`UpdateRenstraAction.php:127-131`), dengan PRD §10.6 yang hanya melarang penghapusan. (PM; menit 27:24)
3. Perubahan target Renstra saat Renstra `aktif` tidak memakai alasan (Q60) dan tercatat di audit dengan nilai lama dan baru; catatan tersebut ditampilkan sebagai riwayat revisi target Renstra. Bila target terlampaui sebelum akhir periode, target tahun-tahun berikutnya dinaikkan dengan cara ini. (PM; menit 1:28:16)
4. Versi penuh Renstra, yaitu sasaran dan indikator ikut berversi, **tidak** dibangun saat ini. Penggantian Renstra karena kebijakan kementerian ditangani dengan Renstra baru, dan Renstra lama dinonaktifkan sesuai aturan yang berlaku. (PM)
5. Lampiran revisi hanya dapat ditambahkan pada Renstra berstatus `draft` atau `aktif`; Renstra `nonaktif` atau diarsipkan tidak menerima lampiran baru. (PM, saat penyelarasan Q47–Q51)

## 40.3 Dampak dan traceability

- Menggantikan PRD §5.2 butir "Revisi target tahunan PK di tengah tahun", PRD §12.7, serta aturan Data Model §2.10 bahwa nomor dan tanggal surat Eselon I dicantumkan pada kolom `alasan`.
- PRD §5.2, §10.5, §10.6, §12.7, §14.5, §14.10, §18.8; Data Model §2.9, §2.10 (rancangan versi PK), §2.13, §2.17, §2.30; Workflow §2, §3.3, §13, §14; Plan modul 2 dan 3.
- User Stories/Issues: US/ISS-02.03, 02.08, dan 02.09 diberi catatan; ISS-03.03 menjadi prasyarat; issue baru **US/ISS-02.12** (revisi PK berversi); lampiran revisi dan riwayat target Renstra masuk **US/ISS-02.10**.
- Status implementasi: lampiran revisi dan riwayat target Renstra sudah (ISS-02.10); versi PK belum (ISS-02.12).

---

# Keputusan Q41 — Rencana Aksi Sepanjang Tahun dan Kunci per Triwulan

Sumber: rapat 13 Oktober 2026, menit 1:04:43–1:09:38, 1:19:12–1:24:52, dan 1:44:51–1:47:57. Butir bertanda (PM) ditetapkan PM.

## 41.1 Tanpa jendela penyusunan

1. Rencana Aksi tidak terikat jendela penyusunan. PIC efektif dengan grant yang sah dapat menyusun, mengubah, dan mengajukan RA **sejak jadwal tahun tersebut aktif sampai `penutupan`**. Jadwal hanya mengikat pelaporan realisasi melalui jendela pengisian per periode. (menit 1:20:22–1:24:52)
2. `jadwal_tahunan.rencana_aksi_mulai` dan `rencana_aksi_selesai` tidak lagi menjadi batas PIC dan dihapus dari form jadwal; kolomnya dibiarkan nullable sampai dihapus pada migrasi lanjutan. (PM)
3. Menggantikan Q16, PRD §4 prinsip 7 untuk bagian jendela penyusunan RA, §12.2 (kolom jendela RA), paragraf penutup §12.3, dan §14.1 bagian "sebelum jendela pengisian periode pertama dibuka". Q18 tetap berlaku untuk jendela pengisian pengukuran.

## 41.2 Kunci per triwulan

1. Periode P terkunci bagi PIC pada Rencana Aksi setelah `jadwal_periode.pengisian_selesai` periode P terlewati (tanggal bisnis Asia/Makassar). (menit 1:24:08–1:24:31)
2. Setiap perubahan RA oleh PIC — simpan draf, pengajuan, dan versi setelah dikembalikan atau dibuka kembali — tidak boleh mengubah target maupun daftar kegiatan periode yang terkunci. Pelanggaran ditolak sebagai validasi bisnis (422) dan diaudit. (PM)
3. Perencanaan dikecualikan sampai `penutupan`, sama seperti pengecualian Perencanaan pada jendela pengisian.
4. Periode lampau pada saat aktivasi jadwal (Q46) sudah terkunci bagi PIC sejak aktivasi.
5. Kunci mengikuti nilai `pengisian_selesai` yang berlaku, termasuk hasil revisi resmi jendela pengisian (Q18, ISS-03.06): perpanjangan jendela pengisian periode P ikut menggeser kunci RA periode P bagi PIC. (PM, saat penyelarasan Q47–Q51)
6. Dalam sesi koreksi setelah penutupan, PIC hanya dapat mengubah RA pada periode yang jendela pengisiannya dibuka kembali secara resmi (ISS-14.03, Q19); di luar itu koreksi RA dilakukan Perencanaan. (PM, saat penyelarasan Q47–Q51)

## 41.3 Aturan yang tetap berlaku

1. Pengajuan pengukuran suatu periode tetap mensyaratkan RA indikator tersebut **disahkan** (PRD §14.9). (PM)
2. RA tetap diverifikasi dan disahkan Perencanaan. Perencanaan boleh mengesahkan RA yang diinputnya sendiri dengan penanda self-approval (Q15, F2); yang dijaga ketat adalah jalur PIC (F1). (menit 1:46:46–1:47:57)
3. Target kumulatif per periode yang turun tetap berupa **peringatan**, bukan penolakan (PRD §14.4). (PM)
4. Deviasi target periode terakhir terhadap target PK tetap wajib dijelaskan saat pengajuan (ADR-0008).

## 41.4 Kegiatan yang mundur ke periode lain

1. Saat pelaporan, PIC menandai kegiatan yang tidak dilaksanakan sesuai rencana sebagai **ditunda** dengan justifikasi dan periode tujuan (PRD §15.2). (menit 1:44:51–1:45:51)
2. Sistem membuat kegiatan lanjutan pada periode tujuan yang tertaut ke kegiatan asal (`kegiatan_asal_id`); kegiatan lanjutan diklaim ke Rencana Aksi pada tahap pengukuran (`sumber_klaim = pengukuran`). (PM)
3. RA yang sudah disahkan **tidak dibuka kembali** hanya untuk memindahkan kegiatan; justifikasi kegiatan menjadi catatan resmi yang dilihat Perencanaan saat verifikasi pengukuran. (PM)
4. Buka-kembali RA oleh Perencanaan (ISS-05.06) dipakai bila target periode yang belum terkunci perlu diubah.
5. Kegiatan lanjutan hanya dapat ditujukan ke periode lain pada tahun yang sama; kegiatan yang mundur ke tahun berikutnya dicatat sebagai kegiatan baru pada Rencana Aksi tahun itu. Berkas kegiatan asal, termasuk RAB, tidak disalin; kegiatan lanjutan tertaut ke asalnya lewat `kegiatan_asal_id`, dan persyaratan tahap perencanaan kegiatan tidak diperiksa ulang untuk kegiatan lanjutan yang diklaim pada tahap pengukuran. (PM, saat penyelarasan Q47–Q51)

## 41.5 Dampak dan traceability

- PRD §4 prinsip 7, §5.1, §8 butir 6, §12.2, §12.3, §14.1, §14.6, §15.2, §28, §31; Data Model §2.15, §2.16, §2.23, §2.25–§2.26; Workflow §1, §4, §7, §8, §9, §13; Plan modul 3, 11 (task 11.9), dan 12.
- User Stories/Issues: US/ISS-03.01, 05.01, dan 05.03 diberi catatan; US/ISS-03.06, 05.06, 06.04, dan 06.05 diselaraskan; issue baru **US/ISS-05.07**.
- Status implementasi: belum. `JendelaTulisRencanaAksi` masih menolak PIC di luar jendela dan saat tanggal jendela kosong (baris 70–74).

---

# Keputusan Q42 — Kegiatan, Anggaran, dan RAB dalam Rencana Aksi

Sumber: rapat 13 Oktober 2026, menit 1:04:43–1:05:23 dan 1:28:47–1:44:27. Butir bertanda (PM) ditetapkan PM.

## 42.1 Kegiatan sebagai bagian Rencana Aksi

1. Rencana Aksi memuat target per periode **dan daftar kegiatan per periode**, bukan hanya angka. (menit 1:29:56–1:30:52)
2. Kegiatan ditambahkan dari halaman Rencana Aksi per periode. Aksi ini membuat kegiatan (per unit × periode, PRD §15.1) sekaligus klaimnya ke RA dengan `sumber_klaim = rencana_aksi`. (PM)
3. Pengajuan RA mensyaratkan **minimal satu kegiatan** yang diklaim pada tahap RA; tidak setiap periode wajib berisi kegiatan. (PM)
4. Struktur kegiatan dan klaim tidak berubah: satu kegiatan tetap dapat diklaim lebih dari satu RA/IKU dalam unit yang sama, dan klaim lintas unit tetap ditolak.
5. Nama kegiatan hingga 500 karakter, tujuan opsional, dan kegiatan dapat disalin ke periode lain saat ditambahkan (Q49).

## 42.2 Anggaran kegiatan

1. Setiap kegiatan mencatat **satu angka anggaran** dalam rupiah, nonnegatif; `0` sah untuk kegiatan tanpa anggaran, dan kegiatan beranggaran nol tetap dapat diklaim. (menit 1:31:49–1:33:55, 1:43:07)
2. Anggaran wajib diisi saat kegiatan dibuat atau diubah; `null` hanya ada pada data sebelum Q42 dan diperlakukan sebagai belum diisi. (PM)
3. Rincian realisasi belanja, pagu induk, dan rekonsiliasi anggaran tidak dikelola. (menit 1:40:03–1:42:29)
4. Anggaran dijumlahkan otomatis per periode, per RA, dan per IKU. Kegiatan yang diklaim lebih dari satu IKU ditampilkan penuh di setiap IKU dengan penanda **"kegiatan bersama"**; total per unit dan per tahun dihitung dari **kegiatan unik** agar tidak terhitung ganda. (PM)
5. Dashboard menampilkan total anggaran per IKU dengan drill-down sampai kegiatan. (menit 1:34:24–1:35:27)
6. Kegiatan lanjutan (Q41 §41.4) menyalin anggaran kegiatan asal dan anggarannya dapat diubah. Kegiatan asal yang `ditunda` dan sudah memiliki kegiatan lanjutan tidak dihitung dalam total, sehingga anggarannya hanya terhitung pada kegiatan lanjutan. Total yang sudah dibekukan pada versi RA tidak berubah. (PM, saat penyelarasan Q47–Q51)
7. Total pada halaman RA menghitung seluruh klaim, baik tahap RA maupun tahap pengukuran, karena butir 4 tidak membedakan tahap klaim; klaim tahap pengukuran tampil di halaman RA dengan label "Klaim pengukuran". (PM, 20 Oktober 2026, [review PR #148](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/148#issuecomment-6287372375))
8. Total per IKU dan per unit/tahun cukup tersedia di layanan sampai tampilan dashboard (butir 5, ISS-10.01). (PM, 20 Oktober 2026, [review PR #148](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/148#issuecomment-6287372375))
9. Total yang dibekukan pada versi RA (butir 6) hanya dihitung dari klaim tahap RA yang dikunci saat pengajuan, sehingga sama dengan daftar klaim beku; klaim tahap pengukuran tidak dikunci karena menguncinya berarti mengubah aturan versi RA lintas modul. Total pada review versi ("saat pengajuan") dan pada halaman RA dapat berbeda. (PM, 20 Oktober 2026, [review PR #148](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/148#issuecomment-6287372375))

## 42.3 RAB

1. RAB wajib diunggah untuk kegiatan dengan anggaran **lebih dari 0**, dalam format Excel atau PDF. (menit 1:34:24–1:37:30; batas anggaran lebih dari 0 oleh PM)
2. Aplikasi tidak membuat template RAB. Perencanaan dapat melampirkan **contoh berkas** generik pada persyaratan RAB; PIC mengunduh contoh tersebut lalu mengunggah RAB miliknya. (menit 1:36:21–1:37:30)
3. Persyaratan tahap perencanaan kegiatan dapat ditandai **hanya untuk kegiatan beranggaran** (`jenis_berkas.hanya_kegiatan_beranggaran`, boolean, default `false`). Persyaratan RAB memakai penanda ini sehingga hanya diperiksa untuk kegiatan dengan anggaran lebih dari 0; persyaratan lain pada tahap yang sama tetap berlaku untuk setiap kegiatan. (PM, saat penyelarasan Q47–Q51)
4. Contoh berkas bukan bukti: Perencanaan dapat menambah, mengganti, atau menghapusnya kapan saja, penghapusannya wajib alasan (Q43 §43.2 butir 2), dan contoh berkas tidak dibekukan ke versi pengajuan. (PM, saat penyelarasan Q47–Q51)

## 42.4 Persyaratan dokumen kegiatan dua tahap

1. **Tahap perencanaan kegiatan** (mis. RAB) berlaku per kegiatan dan diperiksa saat Rencana Aksi diajukan, untuk setiap kegiatan yang diklaim pada tahap RA. (PM)
2. **Tahap pelaksanaan kegiatan** (mis. undangan, dokumentasi, SP) memakai tahap `kegiatan` yang ada dan diperiksa saat kegiatan ditandai `terlaksana` (PRD §15.6). Q22 tetap berlaku. (menit 1:37:30–1:37:58)
3. Kedua tahap ditetapkan Perencanaan di Persyaratan Berkas. `jenis_berkas.tahap` memperoleh nilai baru `kegiatan_perencanaan`, dan persyaratan dapat dilampiri contoh berkas melalui induk `berkas` baru `jenis_berkas`. (PM)
4. Persyaratan tahap perencanaan kegiatan yang diperiksa saat RA indikator X diajukan adalah persyaratan global ditambah persyaratan khusus indikator X. Untuk kegiatan bersama, persyaratan khusus indikator lain diperiksa saat RA indikator tersebut diajukan. (PM, saat penyelarasan Q47–Q51)
5. Berkas yang dipakai memenuhi persyaratan tahap perencanaan kegiatan (mis. RAB) tidak dapat dihapus sejak Rencana Aksi yang mengklaim kegiatan tersebut diajukan; koreksinya lewat pengembalian RA atau berkas pengganti (Q25), mengikuti pola bukti Rencana Aksi (Q36). (PM, saat penyelarasan Q47–Q51)

## 42.5 Akses data anggaran

1. Anggaran per kegiatan dan berkas RAB mengikuti akses detail kegiatan, yaitu dibatasi unit (Q12); Perencanaan dan Pimpinan dapat melihat seluruhnya. (PM)
2. Total anggaran per IKU pada dashboard mengikuti akses dashboard.

## 42.6 Dampak dan traceability

- Menggantikan Q28 serta PRD §5.2 butir "Modul anggaran kegiatan penuh" sepanjang menyangkut input, tampilan, dan penjumlahan anggaran per kegiatan. Q12, Q22, Q24, dan Q25 tetap berlaku.
- PRD §5.1, §5.2, §14.7, §15.1, §15.6, §16, §18.2–§18.4, §18.6, §18.8, §22.4, §23, §25.1, §30; Data Model §2.25, §2.26, §2.29, §2.30, §6; Workflow §1, §7, §8, §9, §10; Plan modul 7, 8, 11, 12 (task 12.12), dan 13; System Architecture §9.
- User Stories/Issues: US/ISS-06.01 diberi catatan (PR #97 tidak diperluas; anggaran dikerjakan di ISS-06.08); US/ISS-06.02, 06.03, 10.01, dan 10.02 diselaraskan; US/ISS-11.01 diberi catatan; issue baru **US/ISS-06.08**.
- Status implementasi: input dan validasi anggaran, tahap persyaratan `kegiatan_perencanaan` dengan penanda `hanya_kegiatan_beranggaran`, contoh berkas, berkas RAB pada kegiatan, dan gerbang pengajuan RA diterapkan ISS-06.08 (PR pertama). Penjumlahan anggaran (§42.2 butir 4 dan 6, termasuk total beku pada versi RA dan pengecualian kegiatan asal `ditunda` yang memiliki lanjutan) dan pembatasan tampilan anggaran per kegiatan (§42.5) diterapkan pada PR kedua ISS-06.08; tampilan dashboard per IKU (§42.2 butir 5) mengikuti ISS-10.01. Catatan penerapan ISS-06.08 (asumsi kerja, menunggu persetujuan PM): (1) format RAB Excel/PDF (§42.3 butir 1) diterapkan lewat konfigurasi persyaratan oleh Perencanaan, yaitu mode file dengan `format_diizinkan` `xlsx,pdf` (Q44 §44.1), tanpa aturan format khusus RAB di kode karena tidak ada penanda RAB tersendiri; (2) kunci §42.4 butir 5 berlaku untuk seluruh berkas tahap `kegiatan_perencanaan` milik kegiatan sejak salah satu RA pengklaimnya diajukan, sejalan dengan snapshot versi RA yang membekukan seluruh berkas berlaku kegiatan tersebut; mengikuti pola Q36, berkas itu tidak dapat dihapus maupun diganti selama kunci berlaku agar berkas yang dibekukan tetap berlaku dan dapat diunduh, sedangkan unggahan baru tetap diterima (kegiatan bersama dapat butuh persyaratan indikator lain, §42.4 butir 4); koreksi lewat pengembalian RA, lalu berkas pengganti beralasan (Q25).

---

# Keputusan Q43 — Kewajiban Alasan Dipersempit

Sumber: rapat 13 Oktober 2026, menit 12:59, 45:13, dan 48:25–49:09. Butir bertanda (PM) ditetapkan PM.

## 43.1 Prinsip

Alasan tidak diwajibkan pada setiap aksi. Setiap aksi tetap tercatat di audit (aktor, waktu, tindakan, nilai lama dan baru, serta `dasar_izin` untuk permission sensitif sesuai Q37 §37.2). Alasan pengguna hanya diwajibkan bila memberi informasi yang tidak dapat dibaca dari data. (menit 45:13)

## 43.2 Aksi yang tetap wajib alasan (PM)

1. Pengembalian dan buka-kembali (Rencana Aksi, pengukuran, jadwal).
2. Penghapusan data atau berkas.
3. Koreksi atau revisi data yang sudah sah: koreksi PK, revisi PK (Q40), dan koreksi snapshot (Q35).
4. Perubahan akses: penetapan dan pergantian peran, penonaktifan dan pengaktifan kembali akun (Q38), pembatasan izin dan pencabutannya, serta pencabutan grant.
5. Justifikasi kegiatan `tidak_terlaksana`/`ditunda`/`batal`, alasan deviasi terhadap target PK (ADR-0008), dan alasan target PK di bawah target Renstra (Q39).

## 43.3 Aksi yang alasannya opsional (PM)

1. Pemberian grant unit. Kolom alasan grant tetap `NOT NULL`; bila pengguna mengosongkannya, server mengisi **alasan baku**, mengikuti pola alasan baku pada Q37 §37.2. (menit 12:59)
2. Perubahan target PK, yang sudah opsional.
3. Perubahan persyaratan berkas, pindah unit indikator, aktivasi jadwal, perubahan setelan aplikasi, dan perubahan PK sebelum PK dipakai jadwal aktif.
4. Kolom alasan pada penetapan dan pergantian **Penanggung Jawab** dihapus dari form; kolom basis datanya sudah nullable. (menit 48:25–49:09)
5. Perubahan target Renstra pada Renstra `draft` maupun `aktif` (Q60). Simpan target Renstra tidak memiliki field alasan sama sekali.

Kewajiban alasan dari keputusan sebelumnya yang tidak disebut pada §43.3 tetap berlaku.

## 43.4 Dampak dan traceability

- Mengubah Q14 (alasan grant), PRD §4 prinsip 6, §7.5, §13, §25.1–§25.2; Data Model §2.7, §2.19, §2.32; Workflow §21–§22; Plan modul 10 (task 10.7); design system (pola modal alasan).
- User Stories/Issues: US/ISS-01.04 dan 04.01 diberi catatan; issue baru **US/ISS-13.05** memuat rincian per request.
- Status implementasi: belum.

---

# Keputusan Q44 — Kebijakan Storage Dihapus, Batas Teknis Unggahan, dan Penataan Menu

Sumber: rapat 13 Oktober 2026, menit 19:18–25:12 dan 1:52:36. Butir bertanda (PM) ditetapkan PM.

## 44.1 Kebijakan Storage dihapus

1. Menu **Kebijakan Storage** (`/pengaturan/storage`) dan halaman **Batas Unggahan Berkas** dihapus. Format dan ukuran maksimum per persyaratan ditetapkan **Perencanaan** di Persyaratan Berkas. (menit 21:10–23:33)
2. Saklar global unggahan dihapus; unggahan file selalu aktif. Penanda `tidak_dapat_dipenuhi` akibat unggahan dimatikan (PRD §18.7 dan gerbang lampiran PK) tidak lagi terjadi; penanda yang sudah tercatat tetap menjadi histori. (PM)
3. Panel penggunaan penyimpanan dihapus. Tampilan daftar berkas terunggah (semacam file manager) masuk backlog. (menit 23:33)
4. Penyimpanan tetap privat, dan unduhan tetap melalui route berizin.
5. Menggantikan Q23, PRD §18.9, §26.3 butir batas unggahan, §26.4, serta US/ISS-11.06 dan US/ISS-13.02 (yang sudah diimplementasikan).

## 44.2 Batas teknis unggahan di server (PM)

1. Server tetap menegakkan batas teknis dari konfigurasi aplikasi, bukan dari UI:
   - default **10 MB** dan format `pdf,docx,xlsx,jpg,jpeg,png` untuk lampiran tanpa persyaratan (Renstra, PK, Dasar Aturan, lampiran bebas kegiatan) dan untuk persyaratan yang mengosongkan format atau ukuran;
   - batas atas **100 MB** per berkas yang tidak dapat dilampaui `jenis_berkas.ukuran_maks_kb`, divalidasi saat persyaratan disimpan dan saat unggah.
2. Nilai tersebut sama dengan default dan batas maksimum halaman Kebijakan Storage sebelumnya.
3. Perubahan batas teknis dilakukan melalui konfigurasi deployment dan dicatat pada catatan rilis.

## 44.3 Penataan menu referensi

1. **Dasar Aturan** dipindah ke grup **Master Data & Referensi** karena dirujuk seluruh modul. (menit 24:49)
2. **Persyaratan Berkas** dipindah ke grup **Perencanaan Kinerja** karena ditetapkan setelah IKU tersedia. (menit 24:09–25:12)
3. Permission tidak berubah; menu tetap tampil berdasarkan `can.*` dari server.

## 44.4 Dampak dan traceability

- PRD §5.1, §8, §12.4, §14.10, §18.1–§18.2, §18.5–§18.7, §18.9, §25.1, §26; Data Model §2.15, §2.22, §2.29, §2.30, §2.32; Workflow §3.3, §5, §10, §18; Plan modul 3, 9 (task 9.10), 11, dan 13; design system (navigasi); System Architecture §14.1; Engineering Standards §7.
- User Stories/Issues: US/ISS-03.02, 11.01, dan 13.02 diberi catatan; US/ISS-11.06 dibatalkan; issue baru **US/ISS-13.06**.
- Status implementasi: diterapkan secara lokal pada working diff ISS-13.06 (20 Oktober 2026), belum dirilis. AC-3/TEST-3 tetap parsial untuk lampiran bebas kegiatan Plan 13.8 yang belum memiliki endpoint; scope dan operasional konfigurasi dijelaskan pada [catatan rilis ISS-13.06](catatan-rilis-ISS-13.06.md). Kelulusan lokal bukan persetujuan UAT atau penyelesaian seluruh issue.

---

# Keputusan Q45 — Narasi Pelaporan, Rekomendasi Pimpinan, dan Dashboard Kegiatan

Sumber: rapat 13 Oktober 2026, menit 50:14–56:01, 1:09:38–1:13:00, dan 1:43:45–1:46:46. Butir bertanda (PM) ditetapkan PM.

## 45.1 Narasi pelaporan

1. Setiap pengukuran (indikator × periode) wajib memuat narasi **progres kegiatan**, **kendala/masalah**, dan **tindak lanjut/strategi penyelesaian** saat diajukan. (menit 51:04–51:59, 1:12:24–1:13:00) Narasi diisi per indikator per periode. (PM)
2. Narasi per kegiatan (`uraian_pelaksanaan`, `kendala`, `strategi_tindaklanjut`) tetap tersedia dan opsional; rekapitulasi menampilkan narasi pengukuran bersama daftar kegiatan yang diklaim. (PM)
3. Kewajiban `catatan` pada PRD §20 (nilai memburuk atau `wajib_catatan`) digantikan narasi wajib. Kolom `catatan` tetap sebagai keterangan tambahan opsional, dan saklar `wajib_catatan` disembunyikan dari form indikator tanpa menghapus kolomnya. (PM; menit 54:37–56:01)
4. Status kegiatan (terlaksana, tidak terlaksana, ditunda) dapat diperbarui sepanjang periode berjalan, misalnya setiap bulan; tidak ada entitas bulanan tersendiri. (menit 1:11:23–1:12:24)

## 45.2 Rekomendasi Pimpinan

> **Dikoreksi Q51 (13 Oktober 2026):** butir 1 dilengkapi alur draf oleh Perencanaan dan persetujuan oleh Pimpinan; persetujuan oleh Perencanaan sebagai cadangan diberi penanda. Butir 4 diubah: ISS-09.01 dikerjakan pada tahap 2.

1. Rekomendasi Pimpinan diisi **Pimpinan** di aplikasi; preset peran Pimpinan memperoleh `rekomendasi:tetapkan` melalui seeder (Q32.4). Perencanaan tetap memegang permission tersebut sebagai cadangan. (menit 51:59–52:32)
2. Rekomendasi tetap melekat pada indikator × periode dan independen dari status pengukuran (PRD §22.3).
3. Bila terdapat rekomendasi pada periode N, pengukuran periode N+1 wajib memuat isian **tindak lanjut atas rekomendasi** saat diajukan. (menit 52:32–53:02)
4. Dibangun bersama ISS-09.01, setelah pengukuran inti (§46.3). (PM)
5. Menggantikan PRD §4 prinsip 5 (pengisi rekomendasi), §5.1 butir rekomendasi, §22.2, dan Data Model §2.31 (pengisi Fase Awal).

## 45.3 Dashboard kegiatan

1. Dashboard menampilkan status kegiatan per periode secara real-time dengan label "belum diverifikasi"; kegiatan yang melewati tanggal rencana tetapi belum terlaksana diberi peringatan. (menit 1:10:11–1:11:23; label oleh PM)
2. Angka capaian resmi tetap hanya dari data yang disahkan (PRD §4 prinsip 1).
3. Total anggaran per IKU mengikuti Q42 §42.2.

## 45.4 Dampak dan traceability

- PRD §4 prinsip 1 dan 5, §5.1, §11 (`wajib_catatan`), §15.3, §20, §22, §23, §24, §32; Data Model §2.12, §2.20, §2.25, §2.31; Workflow §1, §11, §12, §17, §22; Plan modul 5, 6 (task 6.7), 7, dan 8; design system (contoh form pengukuran).
- User Stories/Issues: US/ISS-02.04 diberi catatan; US/ISS-07.01, 07.03, 09.01, 10.01, dan 10.02 diselaraskan.
- Status implementasi: belum.

---

# Keputusan Q46 — Penggunaan Pertama 2026 dan Urutan Pengerjaan

Sumber: rapat 13 Oktober 2026, menit 1:25:19–1:26:59, 1:29:56–1:30:52, dan 1:52:06–1:55:47. Butir §46.1 dan §46.3 ditetapkan PM.

## 46.1 Periode lampau 2026 (PM)

> **Diperjelas Q63 (19 Oktober 2026):** bukti wajib tahap `rencana_aksi` termasuk pengecualian, hanya pada jalur Perencanaan; PIC tidak pernah dikecualikan (§63.1).

1. Gerbang baru Q42–Q45 — RA minimal satu kegiatan, RAB, persyaratan tahap perencanaan kegiatan, narasi wajib, dan tindak lanjut rekomendasi — **dikecualikan untuk periode lampau**, sama seperti gerbang lain pada Q32.7. Kunci per triwulan (Q41 §41.2) bukan bagian pengecualian ini: periode lampau terkunci bagi PIC sejak aktivasi, sedangkan Perencanaan yang mengisinya memang tidak terikat kunci (Q41 §41.2 butir 3–4). *(Dikoreksi 13 Oktober 2026 saat penyelarasan Q47–Q51; semula kunci per triwulan ikut disebut sebagai gerbang yang dikecualikan.)*
2. Dengan pola pelaporan pada minggu pertama bulan setelah triwulan (menit 1:25:19), **TW III 2026** menjadi periode lampau yang diisi Perencanaan; **TW IV 2026** menjadi triwulan pertama workflow normal PIC operasional, dengan pelaporan awal Januari 2027. Status periode lampau tetap dihitung dari jendela pengisian terhadap waktu aktivasi, tanpa `is_backfill`.
3. RA 2026 yang dicatat Perencanaan sebagai data awal (Q32.8) dikecualikan dari gerbang kegiatan minimal dan RAB; kegiatan TW IV ditambahkan lewat klaim tahap pengukuran atau buka-kembali RA oleh Perencanaan. Kegiatan data awal dan kegiatan pada periode lampau boleh tanpa anggaran (tercatat belum diisi, Q42 §42.2 butir 2), karena Rencana Aksi resmi hanya memuat anggaran per Rincian Output. (Ketentuan anggaran ditambahkan PM saat penyelarasan Q47–Q51) *(Catatan 16 Oktober 2026: RA data awal 2026 dikenali dari tahun 2026 yang diajukan lewat jalur Perencanaan, tanpa kolom penanda (keputusan PM 16 Oktober 2026, PR #111).)*
4. Setiap issue yang menambah gerbang Q42–Q45 menyertakan pengecualian periode lampau beserta test-nya; issue kunci per triwulan (Q41) menyertakan test bahwa periode lampau terkunci bagi PIC.
5. Memperbarui Q32.7 ("TW III–IV normal"), Q32.8, dan PRD §12.6.
6. Data awal 2026 diisi Perencanaan dari dokumen resmi 2026 (pengantar Q47–Q51): target PK, target periode dan kegiatan Rencana Aksi, serta realisasi, narasi, dan rekomendasi TW I–II. Target periode lampau boleh kosong (Q47 §47.2), dan data yang belum dikonfirmasi mengikuti asumsi kerja pada bagian tindak lanjut.

## 46.2 Fokus pengerjaan

Tim Perencanaan meminta fondasi PK → Rencana Aksi → Kegiatan dimatangkan sebelum verifikasi/pengesahan pengukuran dan fitur tambahan dikerjakan, dengan asistensi berkala selama pengerjaan. (menit 1:29:56–1:30:25, 1:52:06–1:53:35)

## 46.3 Urutan pengerjaan (PM)

| Tahap | Batas | Cakupan |
|---|---|---|
| 1 | Sebelum integrasi 9 November 2026 (Q2) | Q38 (ISS-01.08); Q39 dan Q50 (ISS-02.10, ISS-02.11); lampiran revisi dan riwayat target Renstra (Q40 §40.2, ISS-02.10); Q41 dan Q47 (ISS-05.07, ISS-05.06); Q42 dan Q49 (ISS-06.01, ISS-06.02, ISS-06.08); Q43 (ISS-13.05); Q44 (ISS-13.06); pengecualian periode lampau (§46.1); di akhir tahap, penyiapan data awal 2026 (ISS-15.01) |
| 2 | Sebelum pelaporan TW IV 2026 (awal Januari 2027) | Pengukuran inti: ISS-07.01, ISS-07.03 (dengan narasi Q45 §45.1 dan tampilan Q47), ISS-07.06, ISS-08.01, ISS-08.02; pelaksanaan kegiatan: ISS-06.03, ISS-06.04, ISS-06.05; Rekomendasi Pimpinan: ISS-09.01 (Q45, Q51); dashboard dan rekap: ISS-10.01, ISS-10.02; keluaran dokumen resmi: ISS-10.05 (Q48); penutupan jadwal: ISS-14.01 |
| 3 | Sesudah tahap 2; revisi PK harus siap sebelum revisi PK pertama yang mengubah target | ISS-03.03 dan ISS-02.12 (revisi PK berversi); simulasi target; tampilan daftar berkas; versi penuh Renstra; ISS-13.03. ISS-09.01 dipindah ke tahap 2 (Q51) |

Issue yang tidak disebut pada tabel mengikuti rencana sprint yang berlaku. Termasuk di dalamnya notifikasi ISS-12.01–ISS-12.07 dan ISS-13.04, sehingga tenggat WA/email "sebelum 9 November 2026" pada Q4 tidak lagi berlaku (ditetapkan PM 17 Oktober 2026); item notifikasi in-app yang dipindahkan Q37 tetap menunggu ISS-12.02.

Status per 17 Oktober 2026 (`development` `f9fd9a7c`): selesai — ISS-01.08 (#101, #108, #114), ISS-05.06 (#100), ISS-06.01 (#97, termasuk Q49 §49.1), ISS-07.01 (#106), ISS-07.03 (#105), ISS-07.06 (#118), ISS-08.02 (#107), ISS-09.01 (#115), ISS-13.03 (#119), ISS-14.01 (#109, #117); dalam review — ISS-06.02 (PR #111); cakupan Tahap 1 lainnya belum dimulai.

## 46.4 Dampak dan traceability

- Q32.7 dan Q32.8; PRD §12.6, §33; Data Model §8.4; Workflow §15; Plan (prioritas dan urutan modul); User Issues §4.1; US/ISS-03.05 dan 07.05.
- Status implementasi: belum.

---

# Pengantar Q47–Q51 — Analisis Dokumen Resmi 2026

| Item | Isi |
|---|---|
| Dokumen | Renstra LLDIKTI Wilayah XVI 2025–2029 (Keputusan Kepala LLDIKTI Wilayah XVI Nomor 548/DST/LL16/PR.00.02/2026, 1 Mei 2026); Perjanjian Kinerja 2026 (21 April 2026); Rencana Aksi 2026 (23 Mei 2026); Laporan Kinerja Triwulan I dan II 2026 (20 Juli 2026) |
| Asal | Diterima dari Tim Perencanaan sebagai tindak lanjut rapat 13 Oktober 2026 (contoh dokumen PK, Rencana Aksi, dan pengukuran); disimpan PM |
| Penetapan | Q47–Q51 ditetapkan PM pada 13 Oktober 2026 berdasarkan analisis dokumen tersebut; seluruh butirnya berstatus (PM) |
| Konfirmasi tertulis | **Menunggu**, bersama konfirmasi Q38–Q46 |

Temuan yang mendasari keputusan:

1. Struktur dokumen resmi sama dengan model SAKIP: target per triwulan per indikator, kegiatan per indikator per triwulan, analisis per indikator (progres, kendala, tindak lanjut), dan rekomendasi per indikator. Target sembilan indikator identik di PK, Rencana Aksi, dan laporan triwulan.
2. Rencana Aksi 2026 mencantumkan target TW I–III bernilai 0 untuk semua indikator, sedangkan laporan triwulan menilai realisasi terhadap target PK dalam selisih poin (Q47).
3. Keempat dokumen operasional merupakan gabungan seluruh indikator untuk satu lembaga dan ditandatangani elektronik di luar aplikasi (Q48).
4. Rencana Aksi 2026 memuat sekitar 228 kegiatan; dua nama kegiatan melebihi 255 karakter (345 dan 263), tidak ada tujuan per kegiatan, dan banyak kegiatan berulang tiap triwulan (Q49).
5. PK 2026 tidak bernomor, mencetak target yang dibulatkan (94; 81; 92,9) sementara laporan memakai nilai penuh (94,05; 80,95; 92,86), dan memuat sasaran S4 serta IKU 4.1 yang tidak ada di Renstra (Q50).
6. Rekomendasi pada laporan triwulan bercampur kalimat orang pertama dan orang ketiga, yang menandakan rekomendasi disusun staf lalu disetujui pimpinan (Q51).

| Q | Topik | Menggantikan atau mengubah |
|---|---|---|
| Q47 | Indikator dinilai tahunan, periode tanpa target, dan realisasi belum dapat diukur | Q7 (sebagian); PRD §14.3, §14.7, §20, §21, §23 |
| Q48 | Rencana Aksi tahunan, Laporan Kinerja Triwulan, dan unggah PDF bertanda tangan | Melengkapi Q29; PRD §24 |
| Q49 | Atribut kegiatan dan salin kegiatan | PRD §15.1; validasi kegiatan PR #97 |
| Q50 | Nomor PK, presisi target, dan kode indikator | Data Model §2.10; PRD §8, §11 |
| Q51 | Draf dan persetujuan Rekomendasi Pimpinan | Q45 §45.2 butir 1 dan 4; Q46 §46.3 |

**Pemetaan kode indikator (Q50 §50.4):**

| Renstra 2025–2029 | PK, Rencana Aksi, dan laporan triwulan | Indikator | Satuan |
|---|---|---|---|
| IKU 1 | IKU 1.1 | Keunggulan layanan LLDIKTI | % |
| IKU 2 | IKU 1.2 | Arsitektur Perguruan Tinggi Swasta (PTS) | % |
| IKU 3 | IKU 1.3 | Tata kelola LLDIKTI yang berkualitas dan berintegritas | Nilai |
| IKU 4 | IKU 2.1 | Fasilitasi peningkatan mutu pendidikan pada PTS oleh LLDIKTI | % |
| IKU 5 | IKU 2.2 | Pencegahan dan penanganan kekerasan, narkoba, dan korupsi | % |
| IKU 6 | IKU 3.1 | Fasilitasi pengembangan kemahasiswaan dan prestasi oleh LLDIKTI | % |
| IKU 7 | IKU 3.2 | Jumlah dosen PTS yang meningkat jabatan fungsionalnya | Orang |
| IKU 8 | IKU 3.3 | Fasilitasi peningkatan kinerja penelitian, publikasi, pengabdian kepada masyarakat, dan kemitraan PTS | % |
| — | IKU 4.1 (sasaran S4) | Nilai Kinerja Anggaran atas Pelaksanaan RKA-K/L | Nilai |

**Ketentuan pelengkap (PM):**

1. **Glosarium istilah** dimuat di PRD: Program (Renstra), Kegiatan DIPA berkode 7733 dan 7735 (PK), Rincian Output (Rencana Aksi), dan Rencana Kegiatan/Aktivitas, yaitu kegiatan SAKIP (PRD §15). Keempat istilah dipakai dengan arti berbeda pada dokumen resmi.
2. **Peringatan verifikasi:** saat Perencanaan memverifikasi pengukuran, sistem memperingatkan bila realisasi atau narasi sama persis dengan periode sebelumnya. Peringatan tidak memblokir verifikasi (ISS-08.01).

Issue baru dari keputusan ini: **US/ISS-10.05** (Q48). Dampak lainnya diserap issue yang ada: US/ISS-02.11 (Q50), 05.07 (Q47), 06.01 dan 06.02 (Q49), 07.01, 07.03, 10.01, dan 10.02 (Q47), 08.01 (peringatan verifikasi), 09.01 (Q51), dan 15.01 (data awal). Q47–Q51 adalah keputusan requirement, bukan bukti implementasi atau kelulusan test.

---

# Keputusan Q47 — Indikator Dinilai Tahunan, Periode tanpa Target, dan Realisasi Belum Dapat Diukur

Sumber: Rencana Aksi 2026 (target TW I–III bernilai 0 untuk semua indikator) dan Laporan Kinerja Triwulan I–II 2026 (target triwulan 0; realisasi dinilai terhadap target PK dalam selisih poin; IKU 1.3 belum diukur; IKU 4.1 dan indikator berbasis jumlah PTS sudah terukur sejak TW I). Seluruh butir ditetapkan PM.

## 47.1 Indikator dinilai tahunan

1. Perencanaan dapat menandai indikator sebagai **dinilai tahunan**, yaitu indikator yang nilainya baru tersedia pada periode nilai akhir (`periode.is_nilai_akhir`). Penanda diubah di Sasaran & Indikator dan tercatat di audit.
2. Penanda ikut dibekukan pada snapshot jadwal bersama atribut indikator lain; perubahan setelah jadwal aktif mengikuti koreksi snapshot (Q35).
3. Penanda tidak mengubah tipe perhitungan, formula, maupun komponen indikator.
4. Data awal 2026: hanya **IKU 1.3** yang ditandai. IKU 4.1 tidak ditandai karena nilainya tersedia tiap triwulan (TW I 99,48; TW II 99,71).

## 47.2 Target periode

1. Untuk indikator dinilai tahunan, target Rencana Aksi pada periode sebelum periode nilai akhir **boleh kosong**. Target periode nilai akhir tetap wajib dan direkonsiliasi dengan target PK (ADR-0008).
2. Indikator lain tetap wajib memiliki target kumulatif di setiap periode (PRD §14.7), termasuk indikator berbasis jumlah PTS yang diukur tiap triwulan.
3. Target kosong berarti **tidak ditargetkan** dan berbeda dari target `0`, yang tetap sah bila memang ditargetkan nol.
4. Data awal 2026: target TW I–III yang tertulis 0 pada Rencana Aksi 2026 dicatat **kosong** untuk semua indikator, karena laporan triwulan tidak menilai realisasi terhadap angka tersebut dan periode itu adalah periode lampau (Q46 §46.1).
5. Pada versi Rencana Aksi yang dibekukan, periode tanpa target dicatat dengan status `tidak_ditargetkan`, bukan `belum_diisi`, sehingga laporan dapat membedakannya dari target yang belum diisi.

## 47.3 Penilaian pada periode tanpa target

1. Pada periode tanpa target, capaian persen dan status capaian (PRD §21) tidak dihitung. Tampilan menunjukkan realisasi, target PK, dan **selisih terhadap target PK** (realisasi − target PK dalam satuan indikator; tandanya dibaca menurut arah indikator).
2. Selisih terhadap target PK juga ditampilkan pada periode bertarget sebagai informasi tambahan; status capaian resmi tetap mengikuti PRD §21.
3. Dashboard menampilkan periode tersebut sebagai **"Tanpa target periode"**, bukan "Belum tercapai". Bila realisasinya juga belum dapat diukur (§47.4), yang tampil "Belum dapat diukur".
4. Penetapan status capaian manual (PRD §21) tidak tersedia untuk pengukuran pada periode tanpa target maupun pengukuran berstatus belum dapat diukur (§47.4).

## 47.4 Realisasi belum dapat diukur

1. Pengukuran dapat diajukan dengan realisasi **belum dapat diukur** bila nilainya belum tersedia pada periode tersebut. Status ini dicatat sebagai nilai tersendiri `status_perhitungan = belum_dapat_diukur` (dengan `nilai` kosong) di samping `belum_diisi`, `terhitung`, dan `tidak_dapat_dihitung`, bukan disimpulkan dari nilai kosong, dan gerbang kelengkapan komponen dikecualikan.
2. Narasi pengukuran (Q45 §45.1) tetap wajib dan menjelaskan mengapa nilai belum tersedia. Perencanaan menilainya saat verifikasi dan dapat mengembalikan pengukuran bila alasannya tidak dapat diterima.
3. Status ini berbeda dari realisasi `0` (nilai sah) dan dari "tidak dapat dihitung" karena penyebut nol (Q9). Dashboard dan rekap menampilkannya sebagai "Belum dapat diukur" dan tidak menghitungnya sebagai capaian 0.
4. Pada periode nilai akhir, realisasi wajib terisi, kecuali tidak dapat dihitung menurut Q9.

## 47.5 Dampak dan traceability

- Mengubah Q7 dan PRD §14.7 (target wajib di setiap periode); melengkapi Q9.
- PRD §11, §14.3, §14.4, §14.7, §17.7, §19.4, §20, §21, §23, §24; Data Model §2.12 (`indikator`), §2.17 (`jadwal_snapshot`), §2.20 (`pengukuran`), §2.24 (`rencana_aksi_target`); Workflow (penyusunan Rencana Aksi dan pengajuan pengukuran); Plan modul 2, 5, 7, 8, dan 11 (task 11.9); design system (tampilan status).
- User Stories/Issues: **US/ISS-05.07** (penanda dan target periode kosong); US/ISS-07.01 dan 07.03 (realisasi belum dapat diukur); US/ISS-10.01 dan 10.02 (tampilan); ISS-15.01 (data awal).
- Status implementasi: belum.

---

# Keputusan Q48 — Rencana Aksi Tahunan, Laporan Kinerja Triwulan, dan Unggah PDF Bertanda Tangan

Sumber: Rencana Aksi 2026, Laporan Kinerja Triwulan I–II 2026, dan Perjanjian Kinerja 2026; ketiganya gabungan seluruh indikator untuk satu lembaga dan ditandatangani elektronik di luar aplikasi. Seluruh butir ditetapkan PM.

## 48.1 Keluaran dari SAKIP

1. SAKIP menghasilkan dua dokumen gabungan untuk satu lembaga:
   - **Rencana Aksi tahunan** per jadwal tahunan: sasaran, indikator, satuan, target PK, dan target per periode seluruh indikator, serta daftar kegiatan per indikator per periode dari Rencana Aksi yang disahkan.
   - **Laporan Kinerja Triwulan** per periode: target PK, target periode, realisasi, dan capaian atau selisih terhadap target PK (Q47 §47.3) per indikator; analisis per indikator dari narasi pengukuran (Q45 §45.1); dan Rekomendasi Pimpinan yang disetujui (Q51). Status kegiatan per indikator dicetak sebagai lampiran tambahan.
2. Isi diambil dari data yang disahkan. Indikator yang Rencana Aksi atau pengukurannya belum disahkan tetap tercantum dengan keterangan "belum disahkan" dan tanpa angka resmi.
3. Tabel Rincian Output pada Rencana Aksi dan realisasi anggaran per Rincian Output pada laporan tetap disusun di luar SAKIP; SAKIP tidak mengelola struktur anggaran DIPA (selaras Q42 §42.2 butir 3).
4. Dokumen PK tidak dihasilkan SAKIP karena ditandatangani bersama Direktur Jenderal; SAKIP mencatat isi target dan lampirannya (Q39).
5. Format awal Excel, mengikuti ekspor Fase Awal (PRD §24.1) dan contoh keluaran yang disahkan Perencanaan (Q29); keluaran PDF tetap fase lanjutan (PRD §24.2).
6. Keluaran dibuat dan diunduh pemegang `laporan:ekspor` (preset Perencanaan, Pimpinan, dan Superadmin).

## 48.2 Unggah PDF bertanda tangan

1. PDF yang sudah ditandatangani elektronik diunggah kembali ke SAKIP sebagai arsip resmi:
   - Rencana Aksi tahunan pada jadwal tahunan; boleh lebih dari satu berkas, misalnya Rencana Aksi awal dan perubahan;
   - Laporan Kinerja Triwulan pada periode jadwal;
   - PK pada lampiran PK, dan per versi setelah revisi berversi tersedia (Q40).
2. Unggahan Rencana Aksi tahunan dan Laporan Kinerja Triwulan dilakukan Perencanaan dengan permission baru `laporan:unggah`; unduhannya mengikuti `laporan:ekspor`. Lampiran PK tetap mengikuti permission PK. `laporan:unggah` tidak bertanda sensitif karena unggahan bersifat append-only dan tidak mengubah data; unggahan tetap tercatat di audit (butir 4).
3. Berkas yang sudah diunggah tidak dihapus. Salah unggah dikoreksi dengan mengunggah berkas pengganti beserta keterangan, mengikuti pola append-only (Q25).
4. Unggahan tidak mengubah data, status, maupun snapshot.
5. Karena itu, unggahan arsip tetap dapat dilakukan setelah jadwal tahunan ditutup; penutupan jadwal (ISS-14.01) hanya menghentikan mutasi data kinerja. (PM, saat analisis issue GitHub 13 Oktober 2026)
6. Keterangan berkas (mis. "Rencana Aksi perubahan" atau "PK perubahan anggaran") disimpan pada kolom baru `berkas.keterangan` (nullable); `alasan_koreksi` tetap khusus untuk berkas pengganti. Ketentuan ini juga dipakai lampiran tambahan PK (Q50 §50.7). (PM, saat penyelarasan Q47–Q51)
7. Arsip Rencana Aksi tahunan dan Laporan Kinerja Triwulan hanya menerima berkas PDF, dengan batas ukuran mengikuti batas teknis Q44 §44.2. (PM, saat penyelarasan Q47–Q51)

## 48.3 Dampak dan traceability

- Melengkapi Q29 dan PRD §24.
- PRD §5.1, §7.2, §24, §30, §31; Data Model §2.30 (induk `berkas` baru `jadwal_tahunan` dan `jadwal_periode`) dan katalog permission (`laporan:unggah`); Workflow (alur buat, tanda tangan di luar aplikasi, dan unggah); Plan modul 8 (task 8.5) dan 13; System Architecture §14.
- User Stories/Issues: issue baru **US/ISS-10.05**, tahap 2 (Q46 §46.3); US/ISS-10.02 dan 14.01 diberi catatan.
- Bila pembuatan berkas memerlukan pustaka baru, keputusannya dicatat sebagai ADR.
- Status implementasi: belum.

---

# Keputusan Q49 — Atribut Kegiatan dan Salin Kegiatan

Sumber: Rencana Aksi 2026 — sekitar 228 kegiatan; dua nama kegiatan melebihi 255 karakter (345 dan 263); tidak ada tujuan per kegiatan; banyak kegiatan berulang tiap triwulan; beberapa nama kegiatan memuat nama orang atau kasus. Seluruh butir ditetapkan PM.

## 49.1 Atribut kegiatan

1. `nama` kegiatan hingga **500 karakter**.
2. `tujuan` kegiatan **opsional**.
3. Diterapkan di PR #97 (ISS-06.01) sebelum merge karena perubahannya kecil. Bila PR #97 sudah di-merge lebih dulu, perubahan dikerjakan di ISS-06.08. *(Tidak berlaku bagi ISS-06.08: PR #97 sudah menerapkan butir 1–2.)*

## 49.2 Salin kegiatan ke periode lain

1. Saat menambah kegiatan dari halaman Rencana Aksi (Q42 §42.1 butir 2), PIC dapat menyalinnya ke satu atau lebih periode lain pada Rencana Aksi yang sama.
2. Setiap salinan menjadi kegiatan tersendiri per unit × periode dengan klaim `sumber_klaim = rencana_aksi` sendiri; nama, tujuan, dan anggaran ikut disalin dan dapat diubah per kegiatan.
3. Berkas, termasuk RAB, tidak ikut disalin; persyaratan tahap perencanaan kegiatan (Q42 §42.4) diperiksa untuk setiap kegiatan.
4. Salinan hanya dapat dibuat untuk periode yang belum terkunci (Q41 §41.2).

## 49.3 Akses detail kegiatan

Detail kegiatan tetap dibatasi unit (Q12), karena nama kegiatan dapat memuat nama orang atau kasus. Rencana Aksi tahunan (Q48) hanya dibuat dan diunduh pemegang `laporan:ekspor`.

## 49.4 Dampak dan traceability

- PRD §15.1; Data Model §2.25 (`kegiatan.nama` hingga 500 karakter, `tujuan` nullable); Workflow (penambahan kegiatan dari Rencana Aksi); Plan modul 12; design system (batas field).
- User Stories/Issues: US/ISS-06.01 diberi catatan (validasi PR #97); US/ISS-06.02 (salin kegiatan).
- Status implementasi: §49.1 diterapkan oleh PR #97 (migrasi `2026_10_14_000001`, validasi request, dan form). §49.2 diterapkan oleh ISS-06.02; anggaran ikut disalin sejak ISS-06.08 menyediakan inputnya.

---

# Keputusan Q50 — Nomor PK, Presisi Target, dan Kode Indikator

Sumber: Perjanjian Kinerja 2026 (tanpa nomor dokumen; target tercetak dibulatkan; sasaran S4 dan IKU 4.1 tidak ada di Renstra; bertanggal 21 April 2026, sebelum penetapan Renstra pada 1 Mei 2026), Laporan Kinerja Triwulan I–II 2026 (rekomendasi TW I memakai target 94,05; 59,52; 92,86; alokasi anggaran Rp2,94 miliar di bawah PK), dan Renstra 2025–2029 (IKU 1–8). Seluruh butir ditetapkan PM.

## 50.1 Nomor PK

`nomor_pk` opsional karena PK 2026 tidak bernomor. PK tetap dikenali dari Renstra dan tahun (`unique(renstra_id, tahun)`) serta tanggal PK.

## 50.2 Presisi target PK

1. Target PK disimpan dengan **nilai penuh** yang dipakai pengukuran (mis. 94,05; 80,95; 92,86), bukan nilai tercetak pada dokumen PK yang dibulatkan (94; 81; 92,9).
2. Status capaian dan selisih terhadap target PK memakai nilai yang disimpan; tampilan mengikuti `desimal_tampilan` indikator.
3. Data awal 2026 memakai nilai penuh; konfirmasi nilai resmi dicatat pada tindak lanjut.

## 50.3 Baseline

Baseline tetap disimpan pada `target_tahunan` (Q33) dan diisi dari realisasi tahun sebelumnya, tetapi bukan isi dokumen PK. Halaman PK menampilkannya sebagai data pendukung.

## 50.4 Kode indikator

1. Kode sasaran dan indikator mengikuti PK, Rencana Aksi, dan laporan triwulan: **S1–S4** dan **IKU 1.1–IKU 4.1** (pemetaan pada pengantar Q47–Q51).
2. Nomor IKU pada Renstra (IKU 1–8) dicatat sebagai keterangan pada definisi operasional indikator; tidak ada kolom baru.
3. Rujukan lama pada dokumen ini tetap memakai nomor Renstra: "IKU 3" pada Q32.10 adalah IKU 1.3, dan "IKU 8" pada Q32.9 adalah IKU 3.3.

## 50.5 Sasaran S4 dan IKU 4.1

Sasaran S4 dan IKU 4.1 (Nilai Kinerja Anggaran atas Pelaksanaan RKA-K/L) hanya ada di PK. Keduanya dicatat di bawah Renstra 2025–2029 tanpa target Renstra (Q39 §39.2 butir 4).

## 50.6 Tanggal PK

Tidak ada validasi bahwa tanggal PK harus setelah tanggal penetapan Renstra.

## 50.7 PK perubahan karena anggaran

1. Sebelum revisi PK berversi tersedia (Q40, ISS-02.12), PK perubahan yang hanya mengubah anggaran diunggah sebagai **lampiran tambahan** pada PK tahun tersebut beserta keterangan; target dan snapshot tidak berubah.
2. PK perubahan yang mengubah target memakai revisi berversi (Q40). Bila revisi seperti itu terbit sebelum ISS-02.12 selesai, penanganannya ditetapkan PM sebagai keputusan baru.

## 50.8 Dampak dan traceability

- PRD §8, §11, §14.10, glosarium; Data Model §2.10 (`nomor_pk` nullable), §2.11 dan §2.12 (kode), §2.13 (presisi); Workflow (penyusunan PK); Plan modul 2.
- User Stories/Issues: US/ISS-02.11 (nomor PK opsional, nilai penuh, baseline); US/ISS-02.03 diberi catatan; ISS-15.01 (kode indikator, S4/IKU 4.1, nilai penuh).
- Status implementasi: belum. `StorePerjanjianKinerjaRequest` dan `UpdatePerjanjianKinerjaRequest` masih mewajibkan `nomor_pk`.

---

# Keputusan Q51 — Draf dan Persetujuan Rekomendasi Pimpinan

Sumber: Laporan Kinerja Triwulan I–II 2026 — rekomendasi per indikator bercampur kalimat orang pertama dan orang ketiga (TW I delapan butir, TW II enam butir). Seluruh butir ditetapkan PM.

## 51.1 Alur

1. Perencanaan menyusun **draf** rekomendasi per indikator × periode. Pimpinan **menyetujui** draf, dapat mengubah isinya sebelum menyetujui, atau menulis rekomendasi langsung.
2. Hanya rekomendasi **disetujui** yang berlaku: tampil pada pengukuran periode berikutnya dan Laporan Kinerja Triwulan (Q48), serta memicu tindak lanjut wajib (Q45 §45.2 butir 3). Draf tidak tampil bagi PIC.
3. Rekomendasi tetap opsional per indikator per periode dan independen dari status pengukuran (PRD §22.3).
4. Kewajiban tindak lanjut berlaku lintas tahun: rekomendasi pada periode terakhir suatu tahun ditindaklanjuti pada periode pertama jadwal tahun berikutnya bila indikatornya masih diukur.
5. Data awal 2026: rekomendasi TW I–II dari laporan triwulan yang sudah ditandatangani Pimpinan dicatat Perencanaan langsung berstatus `disetujui`, dengan penanda persetujuan cadangan dan rujukan laporan sumbernya (kolom rancangan `rekomendasi_pimpinan.rujukan_sumber`).

## 51.2 Permission dan penanda

1. Permission tetap `rekomendasi:tetapkan` pada preset Pimpinan dan Perencanaan (Q45 §45.2). Simpan draf dan setujui dibedakan oleh status rekomendasi, bukan oleh permission.
2. Persetujuan oleh Perencanaan adalah jalur cadangan bila Pimpinan berhalangan; persetujuan tersebut diberi penanda dan tercatat di audit. Penanda yang sama berlaku untuk persetujuan oleh peran lain selain Pimpinan, termasuk Superadmin.
3. Perubahan rekomendasi yang sudah disetujui membentuk baris baru (pola riwayat PRD §22.1) dan melalui persetujuan yang sama.

## 51.3 Dampak dan traceability

- Mengoreksi Q45 §45.2 butir 1 dan 4. ISS-09.01 dipindah dari tahap 3 ke tahap 2 (Q46 §46.3) agar Laporan Kinerja Triwulan TW IV 2026 memuat rekomendasi.
- PRD §4 prinsip 5, §22; Data Model §2.31 (`status` draf/disetujui, penyetuju, waktu persetujuan, dan salinan peran penyetuju untuk penanda cadangan); Workflow (Rekomendasi Pimpinan); Plan modul 6 (task baru 6.7 untuk ISS-09.01; rujukan lama "Plan 8.5" pada matriks User Stories/Issues tidak pernah menunjuk task yang ada dan digantikan 6.7).
- User Stories/Issues: US/ISS-09.01 diselaraskan.
- Status implementasi: belum.

---

# Keputusan Q52 — Penutupan Resmi Jadwal Tahunan

**Keputusan PM, 14 Oktober 2026**, untuk [ISS-14.01 / Issue #86](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/86). Melengkapi Q41 (penutupan tahun Rencana Aksi), Q43 §43.2 (daftar alasan wajib), dan Q48 §48.2 butir 5.

## 52.1 Alasan dan transisi

1. Penutupan jadwal (`jadwal:tutup`) wajib alasan pengguna. Alasan divalidasi dan disanitasi server, lalu dicatat di `audit_log` bersama `dasar_izin`. Butir ini menambah daftar Q43 §43.2.
2. Penutupan hanya dari status `aktif`. Status menjadi `ditutup` dan `closed_at` mencatat waktu penutupan sebenarnya; tanggal `penutupan` rencana dan snapshot tidak berubah.

## 52.2 Tahun terkunci

1. Tahun jadwal terkunci bila jadwal berstatus `ditutup` atau tanggal `penutupan` sudah terlewati pada tanggal bisnis (Asia/Makassar). Penutupan resmi boleh dilakukan sebelum tanggal `penutupan`.
2. Selama terkunci, mutasi normal Rencana Aksi (pembuatan, target, pengajuan, bukti, review), pengukuran, dan kegiatan ditolak sebagai validasi bisnis. Koreksi hanya lewat `jadwal:buka_kembali` dengan sesi koreksi (ISS-14.02).
3. Unggahan arsip PDF Rencana Aksi tahunan dan Laporan Kinerja Triwulan bukan mutasi yang dikunci (Q48 §48.2 butir 5).
4. Data tahun yang terkunci tetap dapat dibaca sesuai izin baca; daftar Rencana Aksi memuat tahun yang ditutup sebagai baris baca saja.

## 52.3 Kegiatan dan klaim

1. Kegiatan tidak terikat Renstra. Tahun kegiatan T terkunci bila ada jadwal non-draft tahun T yang terkunci menurut §52.2; tahun tanpa jadwal tidak terkunci.
2. Perubahan kegiatan memeriksa tahun lama dan tahun baru, sehingga kegiatan tidak dapat dipindahkan keluar dari tahun yang terkunci.
3. Kegiatan belum memiliki pengecualian sesi koreksi; ISS-14.02 menetapkan lingkupnya bila diperlukan.
4. Klaim kegiatan belum memiliki jalur tulis. Penulis klaim kelak memakai aturan yang sama.

## 52.4 Dampak dan traceability

- Diselaraskan: PRD §4 prinsip 6 dan §15.4; Data Model §2.32 kolom `alasan`; User Stories US-14.01; User Issues ISS-14.01.
- Status keputusan: baseline kerja PM; konfirmasi tertulis Tim Perencanaan menunggu bersama Q38–Q51.
- Q52 adalah keputusan requirement; implementasinya dibawa PR ISS-14.01.

---

# Keputusan Q53 — Superadmin Aktif Terakhir pada Penetapan Peran

**Keputusan PM, 15 Oktober 2026**, untuk [Issue #112](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/112), tindak lanjut ISS-01.08 / Issue #99 dari review PR #108. Melengkapi Q38 §38.2 butir 2.

## 53.1 Aturan

1. Penetapan Peran menolak perubahan peran bila target adalah superadmin aktif, peran tujuan bukan superadmin, dan tidak ada superadmin aktif lain. Penolakan dicatat di `audit_log` sebagai `user_roles.ditolak`.
2. Aturan ini juga berlaku untuk akun sendiri. Akun sendiri tetap boleh mengubah perannya selama masih ada superadmin aktif lain.
3. Superadmin aktif adalah akun berstatus `aktif` yang memegang peran `superadmin` yang aktif. Superadmin berstatus `nonaktif` tidak dihitung, dan perannya tetap boleh diturunkan.
4. Tidak ada hierarki peran baru: aktor dengan `akses:update` tetap dapat menetapkan peran superadmin.
5. Penurunan peran dan penonaktifan akun yang berjalan bersamaan diserialkan, sehingga keduanya tidak dapat menghabiskan superadmin aktif terakhir.

## 53.2 Dampak dan traceability

- Diselaraskan: Q38 §38.2 butir 2, Workflow §21.2, User Stories US-01.08, dan User Issues ISS-01.08.
- Status keputusan: baseline kerja PM.
- Q53 adalah keputusan requirement; implementasinya dibawa PR untuk Issue #112.

---

# Keputusan Q54 — Unit Berizin untuk Detail Rencana Aksi dan Kegiatan

**Keputusan PM, 16 Oktober 2026**, untuk [Issue #102](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/102) (tindak lanjut M1/M2 review PR #97). Menegaskan Q12 dan Q49 §49.3; Q14, Q32.2, dan Q32.3 tetap berlaku.

## 54.1 Definisi unit berizin

1. Pengguna yang memperoleh `rencana_aksi:read` atau `kegiatan:read` dari peran Superadmin, Perencanaan, atau Pimpinan membaca detail Rencana Aksi atau Kegiatan seluruh unit.
2. Pengguna lain yang memiliki izin baca tersebut hanya membaca detail pada unit tempat ia memegang grant unit aktif untuk salah satu dari tujuh permission unit-scoped Q32.3. Grant pada unit nonaktif tidak dihitung.
3. Penugasan penanggung jawab indikator tidak menjadikan unit berizin (Q32.2).
4. Deny global maupun deny per unit atas izin baca tetap menang.
5. `rencana_aksi:read` dan `kegiatan:read` tetap permission global (Q32.3); tidak ada grant baca per unit.

## 54.2 Cakupan

1. Aturan §54.1 berlaku pada daftar, detail (termasuk versi historis), antrean review, dan unduhan bukti Rencana Aksi; daftar dan form Kegiatan; serta klaim kegiatan pada halaman Pengukuran. Baris unit yang tidak berizin tidak dikirim ke halaman.
2. Ringkasan agregat dashboard tetap mengikuti baseline akses dashboard (Q12, Q42 §42.5 butir 2).

## 54.3 Izin tulis kegiatan mensyaratkan izin baca

1. Form buat dan ubah kegiatan, penyimpanan, serta tombol buat hanya tersedia pada unit yang juga dapat dibaca pengguna menurut §54.1.
2. Penerima grant `kegiatan:create`/`kegiatan:update` tanpa `kegiatan:read` ditolak sejak membuka form, dan penolakannya diaudit. Ketentuan sementara PR #97 (grant tulis kegiatan hanya bagi pemegang `kegiatan:read`) kini ditegakkan sistem.

> **Catatan Issue #123 (18 Oktober 2026):** form buat kegiatan berupa modal pada daftar Kegiatan, tanpa halaman tersendiri. Tombol buat dan pilihan unitnya mengikuti butir 1, sedangkan penolakan buat diaudit saat penyimpanan. Form ubah tetap ditolak dan diaudit saat dibuka. Aturan butir 1–2 tidak berubah.

## 54.4 Dampak dan traceability

- Diselaraskan: PRD §7.2–§7.4 dan §15.1; Workflow §8 butir 9; Data Model §2.3; User Stories dan User Issues US/ISS-06.01.
- Status keputusan: baseline kerja PM; konfirmasi tertulis Tim Perencanaan menunggu bersama Q38–Q52.
- Implementasi dibawa PR Issue #102.

---

# Keputusan Q55 — Draf, Token, dan Pembekuan Rekomendasi Pimpinan

**Keputusan PM, 16 Oktober 2026**, untuk [ISS-09.01 / Issue #103](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/103). Melengkapi Q51 §51.1–§51.2.

## 55.1 Aturan

1. Setiap indikator × tahun × periode memiliki paling banyak satu draf rekomendasi terbuka. Menyimpan draf saat draf sudah ada mengubah draf tersebut, dan perubahannya tercatat di `audit_log`. Batasan ini ditegakkan basis data, bukan hanya aplikasi.
2. Kolom `rekomendasi_pimpinan.versi` (integer, default 1) dinaikkan setiap kali draf diubah atau disetujui. Penyimpanan dan persetujuan mengirim versi draf yang dilihat pengguna. Bila draf sudah berubah, sudah disetujui, atau baru dibuat pengguna lain, permintaan ditolak sebagai konflik versi tanpa mengubah data, dan penolakannya tercatat di `audit_log`.
3. Penyimpanan dan persetujuan juga mengirim rekomendasi berlaku yang dilihat pengguna, atau penanda bahwa belum ada. Bila rekomendasi berlaku sudah diganti pengguna lain, permintaan ditolak sebagai konflik versi dengan cara yang sama. Penetapan pada indikator × tahun × periode yang sama dijalankan satu per satu.
4. Peran selain Pimpinan yang memegang `rekomendasi:tetapkan` juga dapat menulis rekomendasi yang langsung disetujui; persetujuan tersebut diberi penanda cadangan (Q51 §51.2 butir 2), dan pencatatan data awal 2026 memakai jalur ini (Q51 §51.1 butir 5). Draf tidak diwajibkan lebih dulu karena penyusun tetap dapat langsung menyetujui drafnya sendiri.
5. Versi pengajuan pengukuran membekukan rekomendasi periode sebelumnya yang tampil bersama tindak lanjutnya. Rekomendasi yang disetujui atau direvisi setelah pengajuan tidak mengubah tampilan versi itu.
6. Pengajuan pengukuran mengirim rekomendasi periode sebelumnya yang ditampilkan form, atau penanda bahwa tidak ada. Bila rekomendasi yang berlaku sudah berbeda saat pengajuan diproses, pengajuan ditolak sebagai konflik tanpa membentuk versi dan penolakannya tercatat di `audit_log`; rekomendasi yang diperiksa itulah yang dibekukan (butir 5).

## 55.2 Dampak dan traceability

- Diselaraskan: Data Model §2.31 dan §2.32, PRD §22.1, Workflow §17, User Stories US-09.01, dan User Issues ISS-09.01.
- Status keputusan: baseline kerja PM.
- Q55 adalah keputusan teknis ISS-09.01; implementasinya dibawa PR untuk Issue #103.

# Keputusan Q56 — Admin Memegang Izin Baca Jejak Audit

**Keputusan PM, 17 Oktober 2026**, untuk [ISS-13.03 / Issue #87](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/87). Menyelesaikan perbedaan PRD §25.4 dengan katalog permission dan Plan 10.3 pada Plan Pengembangan.

## 56.1 Aturan

1. `audit:read` termasuk preset Superadmin, Admin, Perencanaan, dan Pimpinan. Preset ini sudah berlaku sejak fondasi akses dan tidak berubah; Pegawai tetap tidak memilikinya.
2. Deny `audit:read` yang cocok tetap menolak akses halaman Jejak Audit bagi pemegang preset mana pun.
3. `audit:read` berlaku global dan membuka seluruh isi peristiwa audit lintas modul dan unit, termasuk `nilai_lama`/`nilai_baru` dari draf (misalnya narasi dan tautan bukti pengukuran, serta isi draf Rekomendasi Pimpinan), tanpa memeriksa izin baca modul objeknya. Server hanya menyamarkan kunci yang menyerupai rahasia, dan membaca jejak audit tidak dicatat sebagai peristiwa audit. Konsekuensi ini diterima secara sadar untuk Admin sebagai pengelola akses.

## 56.2 Dampak dan traceability

- Diselaraskan: PRD §25.4. Katalog permission dan Plan 10.3 pada Plan Pengembangan, User Stories US-13.03, dan User Issues ISS-13.03 sudah sesuai; preset dan kode tidak berubah.
- Butir 3 ditambahkan atas temuan M1 review PR #119: halaman Jejak Audit membuat `audit:read` efektif, sehingga Admin dapat membaca payload modul yang tidak tercakup izin baca perannya.
- Status keputusan: baseline kerja PM.
- Q56 menindaklanjuti review PR untuk Issue #87.

---

# Keputusan Q57 — Jendela Reviu dalam Revisi Resmi Jendela PIC

**Keputusan PM, 17 Oktober 2026**, untuk [ISS-03.06 / Issue #90](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/90). Melengkapi Q18 dan Q41 §41.1 butir 3 atas temuan review PR #120.

## 57.1 Aturan

1. Revisi resmi jendela PIC (Q18, `jadwal:update`) pada jadwal `aktif` yang belum melewati `penutupan` dapat mengubah `pengisian_mulai`, `pengisian_selesai`, `reviu_mulai`, dan `reviu_selesai` setiap periode anggota jadwal dalam satu aksi beralasan. Jendela reviu ikut dapat disesuaikan karena urutan `pengisian_selesai ≤ reviu_mulai ≤ reviu_selesai` ditegakkan constraint basis data dan aturan kalender, sehingga perpanjangan jendela pengisian umumnya menggeser jendela reviu periode yang sama.
2. Jendela reviu tetap jendela kerja Perencanaan (PRD §12.3); revisinya tidak memberi akses tambahan kepada PIC.
3. Audit `jadwal.revisi_jendela` menyimpan alasan, `dasar_izin`, serta seluruh jendela sebelum dan sesudah revisi, termasuk jendela reviu.
4. Penanda `reviu_terlambat` pada aksi reviu yang sudah terjadi tetap beku (Q17). Aksi reviu pengukuran sesudah revisi dinilai terhadap jendela reviu yang berlaku saat aksi tersebut: penanda terlambat dihitung dari `reviu_selesai` yang berlaku, dan reviu belum dapat dimulai sebelum `reviu_mulai` yang berlaku kecuali dalam sesi koreksi.

## 57.2 Dampak dan traceability

- Diselaraskan: PRD §12.3; User Stories US-03.06; User Issues ISS-03.06.
- Kode tidak berubah: perilaku ini sudah ditegakkan `RevisiJendelaPic` dan dibuktikan `JadwalRevisiJendelaTest` serta `PengukuranRevisiJendelaTest` pada PR #120.
- Status keputusan: baseline kerja PM.
- Q57 menindaklanjuti review PR untuk Issue #90.

---

# Keputusan Q58 — Penyamaran Kunci Rahasia pada Penulisan Jejak Audit

**Keputusan PM, 17 Oktober 2026**, untuk [Issue #122](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/122) (tindak lanjut review PR #119, ISS-13.03). Nomor Q57 yang semula dirujuk #122 telah dipakai oleh Keputusan Q57, sehingga keputusan ini bernomor Q58.

## 58.1 Aturan

1. `WriteAuditLog` menyamarkan nilai di bawah kunci yang cocok pola rahasia (token, password, secret, authorization, credential, api/access/private key, `path`) dengan penanda tersamar **sebelum** disimpan ke `nilai_lama`, `nilai_baru`, dan `dasar_izin`. Mutasi domain pemanggil tetap berjalan; penyamaran tidak pernah menggagalkan transaksi.
2. Pola kunci rahasia memiliki **satu sumber** yang dipakai penulisan (`WriteAuditLog`) dan tampilan (`AuditLogPresenter`); penyamaran tampilan tetap ada sebagai lapisan kedua untuk baris yang tersimpan sebelum keputusan ini.
3. Kunci yang hanya mirip (mis. `primary_key`, `jalur_pengajuan`) tidak disamarkan; pola dibuktikan test positif dan negatif terhadap nilai yang tersimpan di basis data, bukan hanya terhadap tampilan.
4. Pemilihan field audit tetap tanggung jawab producer (Engineering Standards §7); penyamaran adalah pengaman terakhir, bukan izin mencatat rahasia.
5. Baris audit yang sudah tersimpan tidak diubah (append-only).

## 58.2 Dampak dan traceability

- Diselaraskan pada PR implementasi: Data Model §2.32 (`audit_log`), PRD §25, Engineering Standards §7 (kontrak penulisan audit memuat penyamaran kunci rahasia).
- Issue #122: judul, body, dan AC-1–AC-4 dirujuk ke Q58; alternatif "tolak saat menulis" ditolak karena menggagalkan mutasi pemanggil dan dapat menolak data sah, sedangkan "pertahankan kontrak" membiarkan risiko writer mendatang (mis. kredensial notifikasi US-13.04) menyimpan token permanen di tabel append-only.
- Status keputusan: baseline kerja PM.

---

# Keputusan Q59 — Konsolidasi Antrean Review ke Halaman Rencana Aksi

**Keputusan pemilik produk SAKIP: 16 Oktober 2026 (WITA), melalui permintaan konsolidasi UI pada sesi kerja agent (branch `fix/ui-rencana-aksi`).** Pencatatan ini menyelaraskan §37.5 butir 1 (permukaan antrean) dan **bukan** ratifikasi PM, UAT, merge, maupun deployment. Nomor Q56–Q58 telah dipakai keputusan lain pada 17 Oktober 2026, sehingga keputusan ini bernomor Q59.

## 59.1 Aturan

1. Antrean review Rencana Aksi tidak lagi halaman terpisah: daftar `/rencana-aksi` memuat filter `status` (Semua status, Belum dibuat, Draft, Diajukan, Diverifikasi, Dikembalikan, Disahkan) yang menyaring server-side sebelum paginasi. Antrean reviu berbasis status (AC-7 ISS-05.03; D1 §37.5) tetap tersedia — hanya berpindah permukaan UI.
2. Halaman detail review tetap `/rencana-aksi/{id}/review` beserta gerbang, policy, dan seluruh aksi verifikasi/pengembalian/pengesahan/buka-kembali yang tidak berubah.
3. URL lama `/rencana-aksi/review` dipertahankan sebagai redirect ke `/rencana-aksi` agar tautan lama tidak 404; controller dan action daftar antrean lama dihapus.
4. Aksi per baris pada daftar disajikan satu menu titik-tiga (`ActionMenu`, komponen design system yang sama dengan daftar Sasaran & Indikator): "Buat Rencana Aksi" (capability `create` seperti semula), "Buka" untuk baris ber-header, dan "Review" hanya ketika server menandai capability-nya (status non-draft dan policy `view` mengizinkan — cermin penyaringan antrean lama atas keselarasan unit snapshot versi terbaru), sehingga tautan yang pasti ditolak tidak ditawarkan.
5. Tidak ada perubahan pada permission, resolver, `GerbangReviewRencanaAksi`, `RencanaAksiPolicy::businessErrors`, maupun aturan mutasi/audit.

## 59.2 Dampak dan traceability

- Diselaraskan: §37.5 butir 1 (antrean pindah permukaan), User Issues ISS-05.04/ISS-05.05 (surface antrean dan detail review), dan Engineering Standards (capability server-side yang ditawarkan UI).
- Test yang disesuaikan: `RencanaAksiIndexTest`, `ReviewRencanaAksiTest`, `RencanaAksiUnitBerizinTest`, `SahkanRencanaAksiTest`, serta test frontend `RencanaAksiIndex`/`RencanaAksiReview`.
- Status keputusan: baseline UI. Implementasi pada branch ini belum berarti merge, UAT, atau deployment.

---

# Keputusan Q60 — Perubahan Target Renstra Tanpa Alasan

**Keputusan PM, 17 Oktober 2026**, untuk [ISS-02.10 / Issue #125](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/125#issuecomment-6250734629). Mengubah Q40 §40.2 butir 3 dan Q43 §43.2 butir 3.

## 60.1 Aturan

1. Pengisian, perubahan, dan pengosongan target Renstra pada Renstra `draft` maupun `aktif` tidak memakai alasan; server menolak field `alasan` pada simpan target Renstra.
2. Audit tetap mencatat aktor, waktu, tindakan, nilai lama, nilai baru, dan `dasar_izin` untuk setiap tahun yang berubah; tahun yang nilainya tidak berubah tidak diaudit.
3. Riwayat revisi target Renstra dibaca dari audit tersebut (waktu, aktor, tahun, nilai lama dan baru).
4. Keputusan ini tidak mengubah alasan wajib untuk target PK di bawah target Renstra (Q43 §43.2 butir 5, ISS-02.11).

## 60.2 Dampak dan traceability

- Q40 §40.2 butir 3 dan Q43 §43.2 butir 3 diselaraskan; revisi target Renstra masuk Q43 §43.3 butir 5 (alasan tidak dipakai).
- ISS-13.05 dan Plan 10.7 tidak lagi mencakup target Renstra karena butirnya keluar dari Q43 §43.2.
- PRD, Data Model, Workflow, Plan Pengembangan, User Stories, dan User Issues merujuk Q60.
- Status implementasi: ISS-02.10 (PR #143).
- Status keputusan: baseline kerja PM.

---

# Keputusan Q61 — Pembanding Peringatan Verifikasi Pengukuran

**Keputusan PM: 19 Oktober 2026 (WITA), [Keputusan PM — M1 pada PR #144](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/144#issuecomment-6252830027).** Memperjelas ketentuan pelengkap butir 2 pada pengantar Q47–Q51, PRD §19.1, dan US/ISS-08.01. Q60 digunakan PR #143; nomor keputusan perlu diperiksa kembali saat integrasi. Pencatatan ini bukan persetujuan QA/UAT, merge, atau deployment.

## 61.1 Aturan pembanding

1. Periode pembanding adalah periode tepat sebelum periode pengukuran: urutan lebih kecil pada jadwal yang sama; untuk periode pertama, periode terakhir jadwal tahun sebelumnya berstatus `aktif`/`ditutup` yang juga mengukur indikator tersebut (Q51 §51.1 butir 4). Penentuannya memakai resolver yang sama dengan rekomendasi periode sebelumnya, bukan salinan logika.
2. Data pembanding hanya versi sah terakhir (`disahkan_at` terisi) pada periode tersebut. Periode tanpa versi sah tidak dilompati; tampilkan bahwa pembanding belum tersedia. Untuk lintas tahun, gunakan tahun dan jadwal pembanding, bukan syarat jadwal sama dengan pengukuran sekarang.
3. Narasi dianggap sama hanya jika `progres_kegiatan`, `kendala_masalah`, dan `strategi_tindak_lanjut` semuanya terisi dan identik persis. `tindak_lanjut_rekomendasi` dan `catatan` tidak dibandingkan.
4. Realisasi dibandingkan hanya jika konteks perhitungan sebanding; `0` berbeda dari `null`. Peringatan tidak memblokir keputusan. Data dan label pembanding tidak dibuka jika aktor tidak memiliki hak baca.

## 61.2 Traceability dan verifikasi

- GitHub #83 AC-7 / US-08.01 dan ISS-08.01 AC-6; Plan 5.6 dan 6.1.
- Regresi mencakup periode pertama tahun N terhadap periode terakhir tahun N−1 yang sah, indikator tidak diukur tahun sebelumnya, periode tanpa versi sah tidak dilompati, dan kesamaan periode dengan resolver rekomendasi. Ketentuan snapshot, izin baca, angka/narasi, serta nonblocking tetap berlaku.

---

# Keputusan Q62 — Izin Unit per Pengguna-Unit, Halaman Detail, dan Kelola Izin

**Keputusan PM: 18 Oktober 2026 (WITA), dalam perapian halaman Izin Unit (branch `fix/ui-izin-unit`).** Keputusan ini menambah operasi kelola pada kontrak "Form create/revoke Grant Unit" (Data Model §2.7) dan **bukan** ratifikasi UAT, merge, maupun deployment. Nomor Q60 (perubahan target Renstra tanpa alasan, ISS-02.10) dan Q61 (pembanding peringatan verifikasi pengukuran, ISS-08.01) telah dipakai, sehingga keputusan ini bernomor Q62.

## 62.1 Aturan

1. Daftar Izin Unit menampilkan satu baris per pasangan pengguna × unit dengan kolom Pengguna, Unit, Izin dimiliki, Status, dan Aksi. Kolom Izin dimiliki berisi jumlah izin katalog aktif yang dimiliki dari seluruh izin yang dapat diberikan (mis. "3 dari 7") dengan tanda "Lengkap" bila semuanya dimiliki; daftar izin berlabel singkat ada di halaman detail dan modal Kelola izin. Paginasi, pencarian, dan filter unit bekerja per pasangan; pasangan tampil bila pengguna, unit, atau salah satu izinnya cocok.
2. Pasangan berstatus "Tidak berlaku" bila akun atau unit nonaktif atau pengguna tidak memiliki peran resmi aktif; izin nonaktif ditandai per butir, dan izin yang terkena deny eksplisit ditandai di halaman detail. Status diturunkan dari resolver izin; daftar menilai peran seluruh pengguna di halaman dengan satu query, bukan per baris.
3. Halaman detail `/akses/grant/{pengguna}/{unit}` (gerbang `delegasi:update`) memuat identitas, status, dan rincian setiap izin: label, status izin, alasan pemberian, pemberi, dan waktu. Pasangan tanpa izin unit menghasilkan 404. Riwayat izin yang sudah dicabut tidak ditampilkan; tautan per izin ke Jejak Audit tampil hanya bila server memberi kemampuan baca audit.
4. Aksi pada baris dan halaman detail: Detail, Kelola izin, dan Cabut semua. Kelola izin mengirim daftar akhir izin pasangan itu; server menurunkan pemberian (izin baru) dan pencabutan (izin yang dilepas) dalam satu transaksi. Cabut semua adalah Kelola izin dengan daftar akhir kosong.
5. Form pemberian dapat memberi beberapa izin sekaligus untuk satu pasangan. Semua atau tidak sama sekali: bila salah satu izin sudah dimiliki, seluruh pilihan ditolak dan izin yang sudah ada disebutkan.
6. Audit tetap per izin dengan tindakan `user_permission_granted.tambah` dan `user_permission_granted.hapus`; tidak ada tindakan "ubah" dan baris grant tidak diubah di tempat.
7. Alasan: bila hanya menambah, alasan opsional dan server mengisi alasan baku (Q43 §43.3 butir 1); bila ada pencabutan, alasan wajib (Q43 §43.2 butir 4). Satu alasan berlaku untuk seluruh perubahan dalam satu simpan.
8. Penambahan mensyaratkan akun aktif, unit aktif, dan izin katalog yang aktif; pencabutan tetap boleh pada referensi nonaktif untuk pembersihan.
9. Kelola izin membawa daftar izin yang dilihat (`expected_permission_ids`). Bila berbeda dengan data tersimpan, permintaan ditolak dengan pesan muat ulang (pola `expected_*` modul Akses) tanpa perubahan. Simpan tanpa perubahan juga ditolak.
10. Urutan lock: pengguna terurut, peran aktor, unit, lock advisory pasangan pengguna × unit, permission, lalu grant pasangan itu. Otorisasi `delegasi:update` diulang di dalam transaksi dan penolakan diaudit di luar transaksi.
11. Endpoint pemberian (`POST /akses/grant`) dan pencabutan per izin (`DELETE /akses/grant/{id}`) tetap tersedia.
12. Halaman Penanggung Jawab indikator menampilkan tombol "Beri izin unit" bagi pemegang `delegasi:update` bila PJ efektif dan unitnya aktif. Tombol membuka form Beri izin unit dengan PJ dan unit itu sudah terisi; izin tetap dipilih dan disimpan secara eksplisit, sehingga Q32 §32.2 (penugasan PJ tidak memberi izin) tidak berubah.

## 62.2 Dampak dan traceability

- Diselaraskan: Data Model §2.7 (pemberian, kelola, dan pencabutan) dan User Issues ISS-01.04 (catatan Q62).
- Test: `GrantIzinTambahanUnitTest` (daftar per pasangan, detail, kelola, pemberian banyak izin), `MutationConcurrencyTest` (kelola menunggu lock aktor), serta test frontend `GrantIndex`, `GrantDetail`, dan `GrantMutationException`.
- Status keputusan: baseline UI dan kontrak kelola. Implementasi pada branch ini belum berarti merge, UAT, atau deployment.

---

# Keputusan Q63 — Pengecualian Gerbang Pengajuan Rencana Aksi pada Periode Lampau 2026

**Keputusan PM: 19 Oktober 2026 (WITA), [Review PM — PR #146](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/146#issuecomment-6263698110).** Menjelaskan Q32.7, Q46 §46.1 butir 3, Workflow §15 butir 4–5, dan ISS-03.05 mengenai pengecualian gerbang pengajuan Rencana Aksi pada periode lampau 2026. Nomor Q62 telah dipakai keputusan Izin Unit (PR #145), sehingga keputusan ini tetap bernomor Q63. Pencatatan ini bukan persetujuan QA/UAT, merge, atau deployment.

## 63.1 Aturan

1. Persyaratan **bukti wajib tahap `rencana_aksi`** dikecualikan **hanya pada jalur Perencanaan**, untuk RA data awal 2026 (RA tahun 2026 yang diajukan lewat jalur Perencanaan; penanda sama dengan kegiatan minimal/RAB, tanpa kolom penanda) dan RA yang seluruh periode efektifnya sudah lampau saat aktivasi jadwal 2026.
2. **PIC tidak pernah dikecualikan**, baik untuk bukti wajib tahap `rencana_aksi` maupun gerbang **minimal satu kegiatan**, termasuk pada RA yang seluruh periode efektifnya lampau. Ketentuan ini menggantikan perilaku cabang "seluruh periode lampau" tanpa syarat jalur yang berlaku sejak #111.
3. **Alasan deviasi target PK tetap wajib** di semua jalur (ADR-0008); tidak ada pengecualian periode lampau.
4. Pengecualian per kegiatan pada periode lampau untuk RAB dan persyaratan tahap `kegiatan_perencanaan` (Q42) tidak berubah.

## 63.2 Dasar

Q32.7 dan Workflow §15 butir 4 mengecualikan berkas wajib pada jalur pengisian periode lampau, sedangkan Q46 §46.1 butir 3 hanya menyebut kegiatan minimal dan RAB untuk RA data awal. Keputusan ini menyatakan secara eksplisit bahwa bukti wajib tahap `rencana_aksi` termasuk pengecualian, karena RA data awal dicatat Perencanaan dari dokumen resmi 2026 yang belum tentu memiliki berkas tahap RA. Pengecualian dibatasi pada jalur Perencanaan karena Workflow §15 butir 5 menetapkan pengisian periode lampau dilakukan Perencanaan dan ISS-03.05 melarang bypass otomatis bagi user scoped. Konsekuensi yang diterima: RA 2026 yang diajukan Perencanaan kapan pun sebelum penutupan (termasuk setelah buka-kembali) tetap dikecualikan dari bukti wajib tahap `rencana_aksi`, sama dengan perlakuan kegiatan minimal/RAB.

## 63.3 Dampak dan traceability

- Kode: `KesiapanPengajuan::jalurLampauDikecualikan()` mengembalikan true hanya untuk RA tahun 2026 pada jalur Perencanaan (RA seluruh-periode-lampau 2026 tercakup definisi ini); dipakai bersama gerbang bukti dan gerbang kegiatan. Panel bukti halaman RA menandai persyaratan wajib sebagai "Dikecualikan (periode lampau/data awal)" bila pengecualian berlaku.
- Dokumen: Q32.7, Q46 §46.1, Workflow §15 butir 4–5, ISS-03.05, dan US-03.05 merujuk Q63.
- Verifikasi: matriks periode lampau sisi Rencana Aksi — sel PIC ditolak dan Perencanaan lolos untuk bukti wajib serta kegiatan minimal pada RA seluruh-periode-lampau; regresi pengajuan/pengesahan RA tetap hijau.
- Status implementasi: PR #146 (bagian 1, verifikasi matriks).

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
| Daftar penetapan awal 2026 | Perencanaan | Daftar unit, mapping indikator→unit, PJ, dan grant turunan dari penetapan PJ perlu disiapkan sebelum operasional TW IV (Q46; semula TW III). Pemetaan unit sementara tercantum pada asumsi kerja di bawah. |
| Konfirmasi tertulis Q38–Q52 | PM + Perencanaan | Menunggu; tanggal kirim, bentuk, dan hasil dicatat pada pengantar Q39–Q46, pengantar Q47–Q51, dan Q52 §52.4. |
| Contoh dokumen PK, Rencana Aksi, dan Pengukuran | Perencanaan | **Selesai.** Diterima sebagai lima dokumen resmi 2026 dan dianalisis pada Q47–Q51. |
| Penjumlahan anggaran untuk kegiatan lintas IKU | Perencanaan | Q42 §42.2 memakai penanda "kegiatan bersama" dan total dari kegiatan unik; alternatif satu kegiatan hanya untuk satu IKU menunggu konfirmasi. |
| Formula dan komponen indikator 2026 | Perencanaan | Renstra 2025–2029 tidak memuat formula, komponen, satuan, maupun baseline. Asumsi kerja di bawah; komponen tidak disetel di produksi sebelum dikonfirmasi. |
| Definisi, arah, dan target 2027–2029 IKU 3.3 (IKU 8 Renstra) | Perencanaan | Definisi pada Renstra berbeda dengan Q32.9, dan target Renstra turun tajam setelah 2026 (59,5 → 25 → 22,22 → 20). Komponen tidak disetel sampai jelas. |
| Baseline 2026 | Perencanaan | Realisasi 2025 dari LKj 2025; dokumennya diminta. |
| Nilai target PK resmi | Perencanaan | Q50 §50.2 memakai nilai penuh (mis. 94,05) alih-alih nilai tercetak (94); perlu dikonfirmasi. |
| Target TW IV IKU 1.2 pada Rencana Aksi 2026 | Perencanaan | Tertulis 92,9, berbeda dengan target PK 94; data awal memakai target PK disertai catatan sampai dikonfirmasi. |
| Rencana PK perubahan 2026 | Perencanaan | Alokasi anggaran pada laporan triwulan Rp2,94 miliar di bawah PK; perlu dipastikan apakah PK direvisi dan apakah target ikut berubah (Q50 §50.7). |
| Lampiran matriks Renstra 2025–2029 | Perencanaan | Tidak ikut dalam PDF Renstra; diminta. |

## Asumsi kerja data awal 2026

Asumsi berikut dipakai untuk menyiapkan data uji dan rancangan data awal (ISS-15.01). Asumsi ini **tidak dipakai di produksi** sebelum dikonfirmasi Perencanaan.

| Data | Asumsi kerja | Dasar |
|---|---|---|
| Formula IKU 1.2, 2.1, 2.2, dan 3.1 | Jumlah PTS yang memenuhi ÷ jumlah PTS wilayah × 100%; penyebut diisi per periode, tidak di-hardcode | Target dan realisasi konsisten dengan penyebut 84: 94,05 = 79/84; 92,86 = 78/84; 80,95 = 68/84; 59,52 = 50/84; realisasi 90,48 = 76/84 dan 35,71 = 30/84 |
| IKU 1.3 | (skor SAKIP + skor ZI-WBK) / 2; dinilai tahunan | Q32.10; Q47 §47.1 |
| IKU 1.1 | Persentase kepuasan layanan dari survei; diisi manual | Laporan TW I–II |
| IKU 3.2 | Jumlah dosen PTS yang naik jabatan fungsional, kumulatif dalam tahun | PK 2026 (satuan orang) |
| IKU 4.1 | Nilai Kinerja Anggaran dari sistem kementerian; diisi manual tiap triwulan | Laporan TW I–II (99,48; 99,71) |
| IKU 3.3 | Komponen tidak disetel sampai definisinya jelas; target PK 2026 59,5 | Q32.9 |
| Unit dan PJ | IKU 1.1, 1.3, dan 4.1 → Bagian Umum; IKU 1.2 → Tim Kerja Penguatan Kualitas Kelembagaan dan Kerja Sama PT; IKU 2.1 dan 3.1 → Tim Kerja Penguatan Mutu Pembelajaran dan Kemahasiswaan; IKU 3.2 → Tim Kerja Penguatan Kualitas Sumber Daya; IKU 3.3 → Tim Kerja Penguatan Mutu Penelitian dan PkM; IKU 2.2 → sementara Tim Kerja Penguatan Mutu Pembelajaran dan Kemahasiswaan | Pembagian tim kerja pada Renstra 2025–2029 |
| Target TW I–III 2026 | Kosong (tidak ditargetkan) | Q47 §47.2 butir 4 |
| PK 2026 | Hanya berubah anggaran, sehingga cukup lampiran tambahan | Q50 §50.7 |

## Item klarifikasi yang sudah RESOLVED oleh Q32

- tahun penggunaan pertama = 2026;
- TW I–II = periode lampau oleh Perencanaan; TW III–IV normal (diperbarui Q46: TW III juga periode lampau, TW IV normal);
- IKU 8 penyebut = total publikasi seluruh PTS;
- 76,25 = target; 66,395/87,08 bukan realisasi;
- baseline IKU 3 = 74,2 dan tidak dibandingkan sebagai tren terhadap target gabungan;
- tidak ada role PIC;
- Grant Unit tetap wajib untuk hak kerja PIC operasional;
- PJ dapat menunjuk user aktif mana pun dan tidak memberi permission;
- role-permission tidak diedit dari UI;
- tidak ada migrasi Pegawai→PIC;
- onboarding JIT = nonaktif + tanpa role (digantikan Q38: aktif + tanpa role);
- logout lokal default + logout SSO terpisah.

---

# Catatan implementasi

Pilihan engineering seperti constraint, provenance pengajuan, tabel versi, dan validasi merupakan kontrak implementasi untuk memenuhi keputusan yang disetujui; tidak berarti kode atau migrasinya sudah tersedia.

Pembaruan Q31 mengubah baseline role, tetapi **tidak otomatis membuktikan bahwa kode, seeder, migration, UI, automated test, maupun issue GitHub sudah diperbarui**. Sebelum implementasi dianggap konsisten, seluruh dokumen terdampak pada §31.10 harus diselaraskan dan perubahan kode harus mengikuti baseline baru.

Tidak ada aplikasi, workbook, data produksi, panduan agent lokal, atau pengaturan Git yang diubah hanya dengan penyelarasan isi dokumen ini.

---

## Addendum ISS-03.01 — Keunikan Jadwal Tahunan

**Keputusan pengguna: 2 Oktober 2026; pencatatan ke dokumen tim: 3 Oktober 2026 (WITA).** Pada pembahasan Issue #30, pengguna menyetujui Q2: satu jadwal tahunan per kombinasi Renstra dan tahun, berlaku lintas seluruh status. Nomor Q2 ini khusus pembahasan ISS-03.01, bukan Q2 pada tabel keputusan awal di atas. Provenance persetujuan berasal dari percakapan pengguna yang direkam dalam catatan lokal `docs/issue-30-periode-jadwal/keputusan-q1-q10.md`, bagian Q2; substansi keputusan dicatat utuh di sini agar tidak bergantung pada akses ke artefak lokal. Pencatatan ini tidak menyatakan ratifikasi PM, UAT, atau penerapan ke database telah selesai.

- Pasangan (`renstra_id`, `tahun`) hanya boleh mempunyai satu `jadwal_tahunan`, baik berstatus `draft`, `aktif`, maupun `ditutup`. Jika sudah tersedia, gunakan jadwal tersebut; hak mengubah tetap mengikuti status, permission efektif, dan aturan bisnis. Keunikan ini berlaku per pasangan, bukan keunikan `tahun` lintas semua Renstra.
- Ketentuan ini menggantikan constraint lama yang hanya unik untuk status `aktif` pada PRD §12.2, Data Model §2.15, dan Plan 3.2. Indeks parsial lama memungkinkan beberapa jadwal pada satu pasangan; pola itu tidak lagi menjadi kontrak pembuatan jadwal setelah Q2.
- Jadwal ditutup tetap dipertahankan. Koreksi pascapenutupan memakai objek jadwal yang sama melalui `jadwal:buka_kembali` sesuai workflow existing, dengan histori, snapshot, dan audit tetap mengikuti aturan masing-masing. ISS-03.01 hanya menyediakan penyusunan draft; aktivasi, penutupan, dan buka kembali tetap task terpisah.
- Sebelum penerapan constraint lintas status, periksa pasangan yang mempunyai lebih dari satu jadwal. Kombinasi tersebut dapat sah menurut skema lama. Bila ditemukan, hentikan rollout dan minta keputusan penyelesaian data yang eksplisit; jangan otomatis menghapus, menggabungkan, memilih pemenang, atau memindahkan referensi historis. Persetujuan Q2 bukan izin koreksi data existing.

Penyelarasan ini terbatas pada keunikan jadwal. Ketentuan master periode dan seed Plan 3.1 tidak diubah oleh addendum ini.

## Addendum ISS-03.01 — Seed Awal Master Periode

**Keputusan pengguna: 3 Oktober 2026 (WITA), dalam tindak lanjut review M1 PR #60.** Pengguna menyetujui seed awal **Triwulan I, Triwulan II, Triwulan III, dan Triwulan IV**, semuanya aktif dengan urutan 1–4; hanya Triwulan IV ditandai `is_nilai_akhir=true`. Keputusan ini memperjelas isi seed Plan 3.1 dan tetap menyediakan pengelolaan melalui UI existing. Pencatatan ini bukan klaim ratifikasi PM, UAT, atau penerapan seed pada database persistent.

Seed berjalan melalui `PeriodeSeeder`, juga dipanggil `DatabaseSeeder`, hanya jika tabel `periode` kosong. Keempat baris dibuat atomik dengan lock konfigurasi yang sama dengan mutasi master. Jika sudah ada baris, termasuk konfigurasi kustom pengelola, seed selesai tanpa perubahan dan menampilkan pesan bahwa konfigurasi dipertahankan. Pengulangan tidak menggandakan, melengkapi, menimpa, atau menormalkan konfigurasi existing.

Master ini global, tanpa hardcode tahun atau pembuatan jadwal/pengukuran 2025/2026. Nama, urutan, status, dan nilai akhir selanjutnya dikelola melalui aturan master periode yang berlaku; seed bukan mekanisme reset konfigurasi.

## Addendum ISS-05.06 — Keputusan PM Buka-Kembali Rencana Aksi

**Keputusan PM: 14 Oktober 2026 (WITA), [komentar Keputusan PM — PR #100 (ISS-05.06)](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/100#issuecomment-6161432383), merujuk [review sebelumnya](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/pull/100#issuecomment-6161417285) pada HEAD `1286d39`.** Keputusan ini menggantikan addendum pemisahan tugas buka-kembali yang dihapus pada butir 2 (addendum tersebut tidak berlaku sebagai keputusan PM). Ini bukan nomor Q baru dan bukan klaim ratifikasi UAT, merge, maupun deployment.

1. Kunci per periode bagi PIC ditegakkan berbasis perubahan nilai di seluruh jalur (simpan, ajukan, capability, form), termasuk penilaian lingkup koreksi terhadap periode yang berubah (Q41 §41.1 butir 1, §41.2 butir 2 dan 6; ISS-05.07 AC-3).
2. Guard pembuka tidak boleh sama dengan pengesah beserta penanda darurat Superadmin dihapus; Perencanaan yang mengesahkan boleh membuka kembali. Kontrol tersisa permission sensitif, alasan wajib (Q43 §43.2), dan audit `dasar_izin`.
3. Pengajuan pengukuran selama RA dibuka kembali tetap ditahan sampai versi baru disahkan (PRD §14.9); banner tidak lagi menyatakan versi lama tetap berlaku.
4. Fallback bukti beku hanya untuk versi resmi (`disahkan_at` terisi) di unduhan maupun baca review.
5. `rencana_aksi:buka_kembali` tersedia sebelum `penutupan` atau setelah `penutupan` di dalam sesi koreksi resmi yang mencakup `rencana_aksi` dan indikator tersebut (Q19, Q35, Q41 §41.2 butir 6). `pengukuran:buka_kembali` tidak berubah oleh keputusan ini.
