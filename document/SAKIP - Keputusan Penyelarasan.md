# SAKIP — Keputusan Penyelarasan Dokumen

Tanggal: **18 September 2026**. Baseline sumber: branch `development`, commit `d04ae5e2d23ece22e71b7802ce45f9cba63ebf33`.

## Status dan dasar keputusan

Pengguna menyampaikan bahwa PM telah memperbarui Q4 pada development dan menyetujui penggunaan rekomendasi per pertanyaan grill untuk sisanya, lalu meminta dokumen diselaraskan. Dokumen ini mencatat hasil penyelarasan tersebut; bukan klaim bahwa implementasi, UAT, penyediaan layanan, atau pengesahan setiap angka operasional sudah selesai.

- Q4 mengikuti perubahan PM pada `d04ae5e`. Klarifikasi langsung pengguna menetapkan pengingat **hanya H-7, H-3, H-1**; scheduler yang berjalan harian tidak berarti pesan dikirim setiap hari.
- Q5 mengikuti rekomendasi terbaru setelah analisis Excel: **empat subskor SAKIP dan satu nilai ZI**, dengan subtotal SAKIP dan hasil gabungan ditampilkan. Ini menggantikan rekomendasi awal dua nilai final.
- PRD menetapkan perilaku, Data Model menetapkan struktur/integritas, Workflow menetapkan alur, Plan menetapkan task/Dependency/DoD. Perubahan pada satu sumber harus diselaraskan ke sumber terdampak; tanggal file bukan hierarki pengesahan otomatis.
- PM mengelola baseline dan keputusan pengganti; Tim Perencanaan mengesahkan aturan bisnis/definisi angka serta UAT; penanggung jawab teknis menetapkan implementasi. Nama penerima walkthrough/UAT dan pemilik layanan tetap harus dicatat.
- Parameter yang belum tersedia tetap terbuka. Persetujuan rekomendasi untuk meminta definisi/pihak/tanggal tidak otomatis menyediakan jawabannya.

## Pemetaan Q1–Q30

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

## Bukti aritmetika IKU 3

Sumber historis: `document/Pengukuran Kinerja  Triwulan 2026.xlsx` pada commit `54c9489`, SHA-256 `66840a50d3048e9aa0a1c10c6d7b8158b80b95d006d2df9feefd209fdc09054b`. Workbook/transkrip sudah dihapus PM dari development melalui `17761d8`; keduanya tidak dipulihkan oleh penyelarasan ini.

| Bukti | Input/perhitungan | Makna yang terverifikasi |
|---|---|---|
| Pengukuran Triwulan I G16:G19 | 23 + 24 + 11,5 + 19 = 77,5 | Jumlah empat subskor SAKIP |
| Pengukuran Triwulan I G20 dan F14 | ZI 75; (77,5 + 75) / 2 = 76,25 | Rumus gabungan dengan koefisien setara 0,5 |
| Pengukuran Triwulan II G16:G20/F14 | SAKIP 79,75; ZI 53,04; gabungan 66,395 | Hasil aritmetika komponen yang tersimpan |
| Pengukuran Triwulan II H14/H15 | H14 = 76,25 tetap; H15 = F14/H14 × 100 = 87,0754098… | Persamaan tersimpan, bukan bukti pengesahan makna target/realisasi |
| PK 2026 H10/I10 | Baseline 74,2 dari SAKIP saja; target 76,25 dari gabungan | Cakupan baseline dan target berbeda |

Lima komponen datar `perencanaan_kinerja`, `pengukuran_kinerja`, `pelaporan_kinerja`, `evaluasi_internal`, `zi` masing-masing penjumlah dengan bobot 0,5. Rumus bukan rata-rata lima angka; subtotal SAKIP tidak menjadi komponen tambahan. Bobot 30/30/15/25 pada penilaian sumber tidak dikalikan ulang pada skor input tersebut.

## Tindak lanjut yang masih membutuhkan data atau penetapan

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

Pilihan engineering seperti constraint, provenance pengajuan, tabel versi, dan validasi merupakan kontrak implementasi untuk memenuhi keputusan yang disetujui; tidak berarti kode atau migrasinya sudah tersedia. Perubahan hanya pada dokumen tim. Tidak ada aplikasi, workbook, data, panduan agent lokal, atau pengaturan Git yang diubah oleh penyelarasan isi dokumen ini.
