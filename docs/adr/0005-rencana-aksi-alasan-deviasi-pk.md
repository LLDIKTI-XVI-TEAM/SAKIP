# ADR 0005 — Kolom Alasan Deviasi Target PK pada Header Rencana Aksi

Status: diterima (2026-10-04, ISS-05.01 F-01).
Acuan: `document/SAKIP - PRD.md` §14.5, `document/SAKIP - Workflow.md` §7, `document/SAKIP - Data Model.md` §2.23–§2.24, `document/SAKIP - Plan Pengembangan.md` Modul 11.6, `document/SAKIP_ENGINEERING_STANDARDS.md` §1/§5.

## Konteks

Rekonsiliasi target-periodik vs target tahunan PK bersifat peringatan +
alasan wajib, bukan blokir: total target komponen periode terakhir
seharusnya setara hasil hitung `target_tahunan` tahun itu; ketidaksetaraan
tidak memblokir pengajuan, sistem menampilkan peringatan dan mewajibkan
alasan, tercatat di audit dan terlihat di layar (PRD §14.5, Workflow §7,
Data Model §2.24).

Data Model §2.23 header `rencana_aksi` hanya menyediakan `alasan_revisi`
(text, nullable) tanpa kolom khusus deviasi-vs-PK. Plan 11.6 membolehkan
`alasan_revisi` atau kolom alasan pengajuan yang relevan untuk menyimpan
alasan tersebut. Tanpa keputusan ini, alasan deviasi akan tercampur dengan
alasan revisi umum / buka-kembali, sehingga query, tampilan F-04, dan audit
tidak dapat membedakan kedua makna tersebut.

Alternatif yang dipertimbangkan:

- (a) Pakai ulang `alasan_revisi` — ditolak: mencampur dua makna
  (deviasi-vs-PK saat ajukan vs alasan revisi/buka-kembali), membuat
  validasi bersyarat (wajib-bila-deviasi) dan riwayat menjadi ambigu.
- (b) Hanya catat di `audit_log` tanpa kolom — ditolak: alasan tidak
  queryable untuk payload baca/preview, tidak tampil persisten di layar,
  melanggar syarat terlihat-di-layar dan tercatat.
- (c) Kolom baru di header + catat audit — dipilih: makna tunggal,
  validasi eksplisit, tampil di payload.

## Keputusan

1. Tambah kolom `alasan_deviasi_pk` (text, nullable) pada header
   `rencana_aksi`: alasan deviasi total target periode terakhir terhadap
   target tahunan PK snapshot. `alasan_revisi` tetap untuk keperluan
   revisi umum dan tidak dipakai untuk deviasi ini.
2. Aturan isi: wajib non-kosong saat pengajuan (`draft → diajukan`) bila
   total periode-efektif terakhir tidak setara target PK (toleransi
   `indikator.presisi`); boleh kosong bila setara. Validasi ditegakkan di
   server pada batas mutasi (Action + FormRequest), di dalam transaksi
   terkunci F-03.
3. Nilai ditampilkan pada payload baca/preview (F-04) dan setiap perubahan
   tercatat di `audit_log` (`nilai_lama`/`nilai_baru`) mengikuti pola
   alasan revisi yang ada.

## Konsekuensi

- Migrasi F-02 menambah kolom + model/factory mencakupnya; tidak ada
   perubahan pada `alasan_revisi` existing.
- Pengajuan deviasi tanpa alasan ditolak validasi; dengan alasan lolos
   (tetap non-blokir secara substansi). Target PK sendiri hanya berubah
   lewat revisi PK resmi.
- Perubahan memerlukan migrasi + revisi ADR ini (sulit dibalik).
