# ADR 0001 — Provenance `created_by_role` Fail-Closed dengan Sentinel Legacy

Status: diterima (2026-09-29, PR-42 TASK-42-07).
Acuan: `document/SAKIP - Data Model.md §2.12`, `Plan 2.7`, `CONTEXT.md`, Q4 grilling PR-42.

## Konteks

`indikator.created_by_role` adalah salinan kode peran pembuat saat pembuatan,
untuk konteks audit historis meski peran pengguna berubah kemudian.
Masalah yang harus dijawab:

1. Baris legacy sudah ada dengan nilai `NULL` (kolom baru via migrasi).
2. Pembuatan via job/import/seeder/kode internal dapat mem-bypass resolver Q32.
3. Peran aktor dapat berubah setelah pembuatan — atribusi belakangan
   (mis. baca `user_roles` saat migrasi) memalsukan provenance.
4. Nilai bebas/tebakan (mis. selalu isi `perencanaan`) membuat audit
   tidak bisa membedakan data terbukti vs data karangan.

Alternatif yang dipertimbangkan:

- (a) Kolom nullable bebas — ditolak: melanggar tujuan provenance, hasil audit
  beda antara data lama dan baru.
- (b) Default `perencanaan` untuk semua yang kosong — ditolak: memalsukan
  atribusi, melanggar fail-closed.
- (c) Derivasikan dari `user_roles` terkini saat migrasi — ditolak: membaca
  assignment saat migrasi, bukan bukti saat pembuatan.
- (d) Fail-closed dari keputusan Q32 + sentinel khusus legacy + immutabel —
  dipilih.

## Keputusan

1. Pembuatan baru wajib menyertakan `created_by_role` yang sah dari katalog
   peran resmi (`RoleCatalog::codes()`), diambil dari peran pemberi pada
   keputusan resolver Q32 (`sumber_allow.roles`) di dalam transaksi terkunci.
   Izin yang hanya bersumber grant langsung tanpa peran pemberi → tolak
   (`penolakan_provenance`), tidak mengarang peran.
2. Sentinel `legacy_unknown` hanya untuk baris legacy via backfill migrasi
   (query builder, bukan via model). Backfill menurunkan peran dari audit
   `indikator.buat` (`nilai_baru.created_by_role` → `dasar_izin.roles`);
   tanpa bukti → `legacy_unknown`.
3. `legacy_unknown` ditolak pada setiap INSERT baru (model `creating` +
   trigger `indikator_kinerjas_no_legacy_unknown_insert`).
4. Kolom `NOT NULL` + `CHECK (IN katalog + sentinel)`.
5. Immutabel setelah INSERT (guard `updating` + trigger
   `indikator_kinerjas_created_by_role_immutable`).
6. Fixture/seeder/test wajib menurunkan dari peran aktor pembuat,
   bukan hardcode.

## Konsekuensi

- Provenance selalu terbukti atau eksplisit-tidak-diketahui (legacy saja).
- Direct grant tanpa peran tidak bisa membuat Indikator (fail-closed ganda:
  izin + provenance).
- Baris `legacy_unknown` dapat dibedakan dari baris terbukti selamanya.
- Perubahan memerlukan migrasi + revisi ADR ini (sulit dibalik).
