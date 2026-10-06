# ADR 0002 — Immutabilitas Hierarki Lintas Renstra

Status: diterima (2026-09-29, PR-42 TASK-42-07).
Acuan: `document/SAKIP - Data Model.md §2.11-2.12`, `Plan 2.5`, `CONTEXT.md`, Q6 grilling PR-42.

## Konteks

Hierarki `Renstra → Sasaran → Indikator` mengikat dependensi operasional:
`target_tahunan`, `jadwal_snapshot`, `rencana_aksi`, dan `pengukuran` merujuk
jadwal Renstra asal. Pemindahan master lintas Renstra (Sasaran pindah
`renstra_id`, atau Indikator pindah ke Sasaran beda Renstra) membuat master
berada di hierarki baru sementara dependensi tetap di jadwal lama —
histori terbelah diam-diam.

Alternatif yang dipertimbangkan:

- (a) Pindah bebas — ditolak: merusak snapshot/laporan lintas jadwal.
- (b) Pindah boleh selama belum punya snapshot — ditolak: kondisi balapan
  (snapshot dibuat konkuren setelah cek) + aturan ganda yang sulit diaudit.
- (c) Kunci mati lintas-Renstra, jalur resmi buat-baru + arsipkan lama —
  dipilih.

## Keputusan

1. Sasaran yang telah memiliki Indikator tidak boleh pindah `renstra_id`.
2. Indikator tidak boleh pindah ke Sasaran pada Renstra berbeda —
   tanpa pengecualian "belum punya snapshot".
3. Penegakan dua lapis:
   - Aplikasi: `UpdateIndikator` mengunci kedua Sasaran deterministik
     (terurut) + validasi `renstra_id` sama → 422; guard model
     `SasaranStrategis::updating` menolak bila ber-Indikator.
   - Database: trigger `check_indikator_same_renstra` dan
     `check_sasaran_renstra_immutability` (PostgreSQL, `ERRCODE 23514`)
     sebagai penjaga terakhir untuk jalur query builder.
4. Jalur resmi perubahan lintas-Renstra: buat baris baru di Renstra tujuan
   + arsipkan baris lama (`is_aktif=false` = `arsip`, ISS-02.05) —
   histori utuh di kedua sisi.

## Konsekuensi

- `PUT` lintas-Renstra → 422 dengan pesan spesifik; SQL langsung → 23514.
- Tidak ada migrasi hierarki diam-diam; setiap "pindah" tercatat sebagai
  kelahiran + pengarsipan terpisah di audit.
- Perubahan memerlukan migrasi + revisi ADR ini (sulit dibalik).
