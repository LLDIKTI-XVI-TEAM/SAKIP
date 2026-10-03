# ADR 0003 — Lifecycle Indikator: Never-Delete + Status Arsip

Status: diterima (2026-09-29, PR-42 R2-04; menggantikan Q7 grilling PR-42).
Acuan: `document/PR-42-Review2-Tracking.md` (R2-04 keputusan FINAL),
`database/migrations/2026_09_30_064700_add_lifecycle_to_indikator_kinerjas_table.php`,
`CONTEXT.md` (Aktif/Arsip).

## Konteks

Q7 grilling PR-42 mengasumsikan `is_aktif=false` = `arsip` tanpa dasar
dokumen — asumsi itu DICABUT pada Review Putaran 2. Skema sumber tidak
mendefinisikan lifecycle indikator secara eksplisit, sementara kebutuhan
operasional menuntut: riwayat tidak boleh hilang (audit/laporan),
indikator usang tidak boleh menerima kerja baru (target/pengukuran/
rencana aksi), dan pemegang wewenang arsip harus eksplisit.

Alternatif yang dipertimbangkan:

- (a) Never-delete + `status` (`aktif`/`arsip`) + arsip via aksi khusus —
  dipilih: histori utuh, kosakata selaras dokumen (`arsip`), tanpa
  ambiguitas boolean.
- (b) Soft-delete generik (`deleted_at`) — ditolak: bukan kosakata
  dokumen, dan tanpa guard arsip yang eksplisit.
- (c) Hard-delete bila tanpa dependensi + nonaktifkan bila beranak —
  ditolak: melanggar never-delete mutlak; jejak baris polos hilang.

## Keputusan

1. Never-delete MUTLAK. `DestroyIndikator` SELALU mengarsipkan
   (`status = arsip` + audit `indikator.arsipkan`); jalur `delete()`
   fisik dihapus total dari Action, termasuk fail-safe 23503 (tak ada
   hard delete lagi sehingga tak relevan).
2. Skema: `status` enum (`aktif`, `arsip`, default `aktif`) + CHECK;
   `tahun_mulai_berlaku` int NOT NULL; `created_by` uuid NOT NULL
   REFERENCES users RESTRICT. Kolom `is_aktif` di-sunset (drop) pada
   migrasi cutover — sunset SELESAI di PR ini.
3. Backfill cutover:
   - `status` ← `is_aktif` (`true→aktif`, `false→arsip`); NULL → THROW
     (fail-closed, perbaiki manual).
   - `tahun_mulai_berlaku` ← `renstra.tahun_mulai` via
     `sasaran_strategis`; NULL → THROW (fail-closed).
   - `created_by` ← `audit_log.actor_id` baris `indikator.buat`
     tertua per indikator; tanpa jejak audit → THROW tanpa fallback
     pengguna otomatis (mengarang pemilik merusak provenance);
     operator mengisi UUID pemilik yang benar manual lalu migrate ulang.
   - Nilai `is_aktif` asli per baris dibackup ke tabel
     `_backup_indikator_is_aktif_20260930` SEBELUM drop; `down()`
     me-restore eksak dari backup (bukan derivasi dari `status`).
4. `is_aktif` keluar dari PUT umum: `Store/UpdateIndikatorRequest` +
   Action + `IndikatorModal` tidak lagi menerima/menulis flag aktif.
   Arsip/reaktivasi HANYA via endpoint khusus (`DELETE
   /perencanaan/indikator/{indikator}` → arsip); reaktivasi
   (arsip → aktif) TIDAK disediakan di PR ini.
5. Guard arsip dibuat DI PR INI: `IndikatorArsipGuard` menolak
   `pengukuran:create` + `rencana_aksi:create` untuk indikator
   `arsip` (422, pesan spesifik), untuk siapa pun termasuk
   Perencanaan/Superadmin. Belum ada endpoint create kedua modul itu
   di PR ini, sehingga guard adalah seam service-layer + test;
   endpoint yang lahir kemudian wajib memanggilnya sebelum mutasi.
6. Cascade hapus Sasaran → Indikator DIPERTAHANKAN (keputusan user).
   Sisa risiko yang dicatat: guard aplikasi menolak hapus Sasaran
   ber-Indikator sehingga cascade unreachable via app; cascade hanya
   terjangkau via SQL langsung/operasi DB di luar guard aplikasi.
7. Q7 lama digantikan ADR ini.

## Konsekuensi

- Arsip bersifat final di PR ini: tidak ada jalan kembali ke `aktif`
  lewat aplikasi; belum diputuskan apakah reaktivasi butuh alur
  tersendiri (open).
- `StoreIndikator` wajib mengisi `tahun_mulai_berlaku` (dari Renstra
  induk terkunci) + `created_by` (aktor terkunci) di dalam transaksi
  yang sama; fixture/seeder/test wajib menyertakan ketiga kolom
  lifecycle pada setiap pembuatan baris.
- Pembaca yang sebelumnya memakai `is_aktif` indikator (`Index`
  payload, guard hapus Regulasi, JenisBerkas) kini memakai `status`;
  kosakata audit/pesan: `arsip`/`diarsipkan` (bukan
  nonaktif/dinonaktifkan/dihapus).
- Perubahan memerlukan migrasi + revisi ADR ini (sulit dibalik).
