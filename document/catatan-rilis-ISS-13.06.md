# Catatan rilis ISS-13.06 — batas unggahan dan penataan referensi

Status: implementasi lokal, belum dirilis. Acuan: Q44 §44.1–§44.3, PRD §18.5–§18.9 dan §26.3–§26.4, Data Model §2.22, Workflow §10 dan §18, Plan 9.10, US/ISS-13.06, issue [#128](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/128). Catatan disusun 20 Oktober 2026; kelulusan lokal bukan persetujuan UAT atau bukti deployment.

## Perilaku aplikasi

Halaman Kebijakan Storage, panel penggunaan penyimpanan, route kebijakan lama, dan mode Ubah batas teknis pada Persyaratan Berkas dihapus. Format dan ukuran dapat diubah lewat form persyaratan dengan permission `jenis_berkas:create` atau `jenis_berkas:update`; katalog permission dan preset peran tidak berubah. Alasan perubahan existing tetap diwajibkan karena penyesuaian alasan opsional merupakan cakupan ISS-13.05.

Unggahan file selalu aktif. Kunci grup `berkas` di `pengaturan` dipertahankan sebagai histori dan tidak lagi menentukan validasi, kelengkapan bukti, maupun gerbang lampiran PK. Pengajuan baru tidak menghasilkan penanda pengecualian akibat unggahan dimatikan. Audit lama tetap append-only dan dapat dibaca di Jejak Audit; snapshot yang sudah dibekukan tidak ditulis ulang.

Dasar Aturan berada di Master Data & Referensi dan Persyaratan Berkas berada di Perencanaan Kinerja. Visibilitas menu tetap mengikuti capability dari server. Penyimpanan privat, permission induk/berkas, unduhan berizin, optimistic locking, dan audit perubahan tetap berlaku.

## Konfigurasi deployment

| Variabel | Default | Aturan |
| --- | --- | --- |
| `SAKIP_UPLOAD_DEFAULT_MAX_KB` | `10240` | Ukuran bawaan untuk batas persyaratan kosong atau lampiran tanpa persyaratan; dipotong mengikuti ceiling efektif. |
| `SAKIP_UPLOAD_ALLOWED_EXTENSIONS` | `pdf,docx,xlsx,jpg,jpeg,png` | Daftar ekstensi CSV bawaan; pemeriksaan isi MIME dan ekstensi dijalankan bersama. |
| `SAKIP_UPLOAD_MAX_KB` | `102400` | Ceiling efektif dapat diturunkan; angka di atas `102400` dipotong menjadi `102400`. |

`config/uploads.php` dan `UploadLimits` menjadi satu pintu pembacaan. Format dan ukuran persyaratan yang diisi tetap berlaku dengan ceiling efektif; masing-masing nilai yang kosong memakai default konfigurasi secara independen. Nilai ukuran konfigurasi yang bukan integer positif memakai fallback aman. CSV kosong atau rusak memakai daftar default.

Pembaca mencakup lampiran Renstra, PK, Dasar Aturan, bukti Rencana Aksi, bukti Pengukuran, berkas perencanaan kegiatan/RAB, serta contoh berkas Jenis Berkas. Contoh berkas memakai default konfigurasi, bukan batas persyaratan yang sedang dicontohkan.

Perubahan env harus mengikuti prosedur deployment aplikasi, termasuk pembentukan ulang config cache dan restart proses yang menyimpan konfigurasi bila digunakan. Batas PHP `upload_max_filesize`, `post_max_size`, serta batas request web server tetap berlaku. Besaran total request perlu memperhitungkan seluruh lampiran dan overhead; menaikkan env aplikasi tidak menaikkan batas infrastruktur tersebut. Catat nilai efektif yang berubah pada catatan rilis deployment.

Tidak ada migrasi skema atau penulisan ulang data lama. Ceiling persyaratan ditegakkan di level aplikasi sesuai Data Model §2.22. Sebelum rilis produksi, operator memeriksa data yang melampaui batas dan mencatat hasil serta tindak lanjutnya; pemeriksaan produksi belum dijalankan dalam implementasi lokal ini. Query baca yang dapat digunakan:

```sql
SELECT id, ukuran_maks_kb
FROM jenis_berkas
WHERE ukuran_maks_kb > 102400;
```

Jika ceiling deployment diturunkan, periksa pula persyaratan di atas ceiling efektif tersebut. Berkas historis tidak dihapus atau diubah otomatis; validasi unggahan berikutnya memakai ceiling efektif.

## Batas cakupan dan penerimaan

- Bagian lampiran bebas kegiatan pada AC-3/TEST-3 masih terbuka: endpoint existing mewajibkan persyaratan tahap `kegiatan_perencanaan`. Pembuatan alur lampiran bebas kegiatan pada Plan 13.8/PRD §18.10 memerlukan issue tindak lanjut tersendiri. Jalur perencanaan kegiatan existing sudah menggunakan helper konfigurasi.
- Penyajian baru histori pengecualian pada rekapitulasi dan review historis Rencana Aksi tidak ditambahkan. Rekapitulasi mengikuti ISS-10.02 [#134](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/134); tampilan snapshot Pengukuran existing dipertahankan.
- File manager, perubahan permission/preset, perubahan alasan opsional ISS-13.05, serta penyesuaian infrastruktur deployment berada di luar cakupan.

Karena bagian lampiran bebas kegiatan masih terbuka, keseluruhan AC-3/TEST-3 dan issue ini tidak boleh ditandai selesai hanya berdasarkan pengujian jalur existing. Bukti otomatis mencakup clamp ceiling, konfigurasi yang diturunkan, ketergantungan default secara independen, pemeriksaan MIME/ekstensi, penolakan unggahan, permission/audit, gerbang tanpa waiver baru, serta pembacaan audit lama. Bukti browser mencakup navigasi, form, keyboard, galat, dan unggahan pada data sintetis; detail command, baseline working diff, environment, dan hasil berada dalam laporan QA lokal di luar repository.
