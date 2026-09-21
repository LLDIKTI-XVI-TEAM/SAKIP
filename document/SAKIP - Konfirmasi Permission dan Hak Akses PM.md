# DOKUMEN KONFIRMASI PERMISSION & HAK AKSES SISTEM SAKIP
**Kepada:** Project Manager & Pemangku Kepentingan Sistem SAKIP  
**Status:** Draf untuk Ditinjau & Dikonfirmasi (*For Review & Sign-Off*)  
**Versi:** 1.0  
**Tanggal:** 20 September 2026  
**Referensi Spesifikasi:** PRD §7, Data Model §2.3–§2.8, Workflow §13 & §19  

---

## 1. TUJUAN & PANDUAN PENINJAUAN

Dokumen ini disusun untuk mengonfirmasi pemetaan seluruh **70 kode permission** yang ada di sistem SAKIP kepada Project Manager sebelum implementasi backend gate, service layer, dan controller diselesaikan. 

Secara arsitektur, permission di SAKIP dibagi menjadi:
1. **Izin Terbuka untuk Semua Role (Akses Umum / Read-Only Transparan):** Data publik internal instansi yang dapat dilihat oleh semua pemangku kepentingan untuk mendukung keterbukaan informasi kinerja.
2. **Izin Khusus Role Tertentu (*Role-Restricted*):** Wewenang strategis, teknis, atau verifikatif yang dibatasi ketat pada peran tertentu guna menjaga integritas dan pemisahan tugas (*Separation of Duties*).
3. **Izin Bersyarat Berbasis Unit / PIC (*Scoped & Conditional*):** Wewenang operasional unit yang tidak diberikan otomatis oleh role, melainkan memerlukan penugasan khusus (*Grant Unit* + PIC aktif + jadwal pengisian aktif).
4. **Izin Sensitif (*Sensitive Actions*):** 21 tindakan berdampak tinggi yang wajib mencatat alasan resmi dan **`dasar_izin`** ke dalam `audit_log`.

---

## 2. REKAPITULASI KLASIFIKASI AKSES (RINGKASAN EKSEKUTIF)

| Kategori Akses | Jumlah Permission | Karakteristik Utama | Daftar Role Penerima |
|:---|:---:|:---|:---|
| **A. Terbuka untuk Semua Role** | **5** | Data publik internal, katalog regulasi, dan dasbor umum | Seluruh 5 Role (Superadmin, Admin, Perencanaan, Pimpinan, Pegawai) |
| **B. Hanya Khusus Perencanaan & Superadmin** | **44** | Pengelolaan Renstra, Sasaran, Indikator, Target, PK, Jadwal, Verifikasi, Pengesahan, dan Formula | Perencanaan, Superadmin |
| **C. Hanya Khusus Admin & Superadmin** | **8** | Tata kelola akun pengguna, master unit, hak akses (grant/deny), dan setelan teknis | Admin, Superadmin |
| **D. Khusus Operasional Unit (Pegawai via Grant)** | **9** | Pengisian draf rencana aksi, pelaporan capaian, dan pencatatan kegiatan inisiatif | Pegawai (Wajib Grant Unit + PIC Indikator Aktif), Perencanaan, Superadmin |
| **E. Khusus Pemantauan Eksekutif (Pimpinan)** | **3** | Baca laporan kinerja, ekspor laporan, dan lihat rencana aksi unit | Pimpinan, Perencanaan, Superadmin |
| **F. Cadangan Fase Lanjutan (*Staging Future*)** | **1** | Persetujuan formal Pengukuran oleh Pimpinan (`pengukuran:setujui`) | Disiapkan di Role Pimpinan (belum aktif di Fase Awal) |
| **Total Seluruh Permission** | **70** | | |

---

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

### B. Izin yang HANYA Khusus Role Tertentu (65 Permission)

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
| 12 | `pengaturan:update`| `pengaturan`| `update` | Admin, Superadmin | **Ya** | Mengubah teks label aplikasi, batasan ukuran unggahan file | [ ] Disetujui |
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
| 25 | `target:update` | `target` | `update` | Perencanaan, Superadmin | Tidak | Mengoreksi salah input master target tahunan via snapshot pengganti | [ ] Disetujui |
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
| 56 | `berkas:upload` | `berkas` | `upload` | Global | Diturunkan dari hak mutasi data induk | Tidak | Mengunggah bukti PDF / link dokumen (terkunci jika disahkan) | [ ] Disetujui |
| 57 | `berkas:delete` | `berkas` | `delete` | Global | Perencanaan & Pemegang hak mutasi induk | **Ya** | Menghapus lampiran bukti dukung (terkunci jika disahkan) | [ ] Disetujui |

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
| 67 | `rekomendasi:tetapkan` | `rekomendasi` | `tetapkan` | Perencanaan, Superadmin | **Ya** | Menetapkan rekomendasi tindak lanjut evaluasi pimpinan | [ ] Disetujui |

---

#### 8. Pelaporan Eksekutif & Persetujuan Pimpinan (Khusus PIMPINAN & PERENCANAAN)

| No | Kode Permission | Entitas | Aksi | Role yang Berhak | Sensitif | Keterangan untuk PM | Status Konfirmasi PM |
|:---:|:---|:---|:---|:---|:---:|:---|:---:|
| 68 | `laporan:read` | `laporan` | `read` | Perencanaan, Pimpinan, Admin, Superadmin | Tidak | Membuka matriks tabel rekapitulasi laporan kinerja | [ ] Disetujui |
| 69 | `laporan:ekspor` | `laporan` | `ekspor` | Perencanaan, Pimpinan, Superadmin | Tidak | Mengunduh file laporan resmi (Excel / PDF SAKIP) | [ ] Disetujui |
| 70 | `pengukuran:setujui` | `pengukuran` | `setujui` | **Pimpinan**, Superadmin | Tidak | **Fase Lanjutan:** Approval pimpinan setelah disahkan Perencanaan | [ ] Disetujui |

---

## 4. POIN-POIN KUNCI YANG MEMBUTUHKAN PERSETUJUAN RESMI PM

Mohon konfirmasi Project Manager untuk 5 aturan invariant arsitektur berikut:

### [?] Pertanyaan 1: Batas Kewenangan Role Admin Teknis
> **Aturan Spesifikasi:** Role `admin` hanya berwenang mengelola pengguna, hak akses, master unit, dan preferensi aplikasi. Admin **tidak memiliki izin bawaan** untuk membuat renstra, menetapkan target, mengisi capaian, maupun mengesahkan data kinerja.  
> **Konfirmasi PM:** Apakah pembatasan ketat bagi Admin ini disetujui untuk mencegah konflik audit ZI/AKIP?  
> - [ ] **SETUJU** (Admin murni teknis, tidak boleh menyentuh angka kinerja)  
> - [ ] **ADA CATATAN KHUSUS:** ___________________________  

### [?] Pertanyaan 2: Syarat Pengisian oleh Pegawai Unit (Triple-Lock)
> **Aturan Spesifikasi:** Pegawai unit tidak bisa langsung mengisi data capaian begitu saja. Pegawai harus memenuhi 3 syarat sekaligus: (1) Punya grant unit, (2) Ditunjuk sebagai PIC aktif indikator tersebut, dan (3) Jadwal pengisian sedang terbuka.  
> **Konfirmasi PM:** Apakah aturan *triple-lock* ini sudah sesuai dengan SOP operasional instansi?  
> - [ ] **SETUJU** (Wajib terdaftar PIC aktif & jendela jadwal masih buka)  
> - [ ] **ADA CATATAN KHUSUS:** ___________________________  

### [?] Pertanyaan 3: Peran Pimpinan di Fase Awal (Murni Monitoring)
> **Aturan Spesifikasi:** Di Fase Awal, peran Pimpinan murni melihat dasbor, laporan, pohon sasaran, dan mengunduh ekspor laporan. Permission `pengukuran:setujui` tetap disimpan di katalog database namun belum dipasang di alur UI Fase Awal.  
> **Konfirmasi PM:** Apakah alur Pimpinan murni membaca (*read-only*) ini sudah tepat untuk rilis awal?  
> - [ ] **SETUJU** (Pimpinan tidak dibebani approval teknis di Fase Awal)  
> - [ ] **ADA CATATAN KHUSUS:** ___________________________  

### [?] Pertanyaan 4: Integritas Hapus Unit (`unit:delete`)
> **Aturan Spesifikasi:** Unit kerja yang sudah pernah memiliki riwayat indikator, rencana aksi, atau kegiatan **dilarang keras untuk dihapus (*hard-delete*)**, dan hanya bisa dinonaktifkan. Hapus unit hanya diizinkan bagi Superadmin untuk unit kosong yang baru dibuat dan salah ketik.  
> **Konfirmasi PM:** Apakah proteksi integritas data unit ini disetujui?  
> - [ ] **SETUJU** (Unit yang punya riwayat haram dihapus)  
> - [ ] **ADA CATATAN KHUSUS:** ___________________________  

### [?] Pertanyaan 5: Penanganan Koreksi Pasca-Pengesahan (`buka_kembali`)
> **Aturan Spesifikasi:** Pengembalian draf yang sedang direviu memakai `kembalikan` (alur normal), sedangkan pembatalan data yang sudah disahkan memakai `buka_kembali` (alur luar biasa / sensitif wajib alasan audit).  
> **Konfirmasi PM:** Apakah pemisahan dua aksi koreksi ini disetujui?  
> - [ ] **SETUJU** (Pemisahan status normal vs luar biasa sudah tepat)  
> - [ ] **ADA CATATAN KHUSUS:** ___________________________  

---

## 5. LEMBAR PENGESAHAN PROJECT MANAGER

| Disusun Oleh | Ditinjau & Disetujui Oleh |
|:---:|:---:|
| <br><br>___________________________<br>**Tim Pengembang SAKIP**<br>Tanggal: 20 September 2026 | <br><br>___________________________<br>**Project Manager SAKIP**<br>Tanggal: ____________________ |
