# ADR 0004 — Target Manual Rencana Aksi memakai `komponen_id` NULL tanpa Komponen Semu

Status: diterima (2026-10-04, ISS-05.01 F-01).
Acuan: `document/SAKIP - Keputusan Penyelarasan.md` Q7, `document/SAKIP - Data Model.md` §2.24 + ERD `RENCANA_AKSI_TARGET`, `document/SAKIP - PRD.md` §14.3, `document/SAKIP - Plan Pengembangan.md` Modul 11.1–11.2, `document/SAKIP - Workflow.md` §7, `CONTEXT.md`, `document/SAKIP_ENGINEERING_STANDARDS.md` §1/§5.

## Konteks

Data Model §2.24 + ERD mendefinisikan `rencana_aksi_target.komponen_id` sebagai
FK `NOT NULL` dengan `unique(rencana_aksi_id, periode_id, komponen_id)`.
PRD §14.3 dan Plan 11.2 mengikuti definisi yang sama (satu baris per
kombinasi periode × komponen).

Kebutuhan operasional mensyaratkan indikator bertipe `manual` menyimpan satu
target langsung per periode, tanpa komponen. Penyelarasan Q7 memutuskan:
indikator manual punya satu target langsung per periode; nonmanual memakai
target komponen + skor turunan; tanpa komponen semu. Kontrak issue menegaskan
hal yang sama (`komponen_id = NULL` untuk manual).

Tanpa keputusan ini, pelaksana harus memilih antara melanggar Q7 (membuat
komponen semu agar kolom NOT NULL terpenuhi) atau melanggar kontrak
penyusunan (memaksa manual lewat jalur komponen).

Alternatif yang dipertimbangkan:

- (a) Komponen semu per indikator manual — ditolak: melanggar Q7 secara
  eksplisit, mengotori master `indikator_komponen`, ikut membeku ke
  `jadwal_snapshot_komponen`, dan membuat gerbang kelengkapan 11.3 ambigu
  (komponen palsu ikut diwajibkan).
- (b) Tabel terpisah untuk target manual — ditolak: duplikasi skema dan
  percabangan baca/tulis permanen untuk satu kasus nullable; satu tabel
  `rencana_aksi_target` tetap cukup.
- (c) `komponen_id` nullable + satu baris NULL per periode untuk manual —
  dipilih: memenuhi Q7 tanpa data palsu, nonmanual tetap ketat.

## Keputusan

1. `rencana_aksi_target.komponen_id` bersifat nullable. Baris manual wajib
   `komponen_id IS NULL`, tepat satu baris per (`rencana_aksi_id`,
   `periode_id`). Baris nonmanual wajib `komponen_id NOT NULL` merujuk
   komponen efektif (lihat catatan D2 F-01).
2. Keunikan ditegakkan ramah-NULL di PostgreSQL mengikuti preseden
   `klaim_kegiatan` (Data Model §2.26): unique index memakai
   `COALESCE(komponen_id, sentinel)` atau partial index setara, sehingga dua
   baris manual (`NULL`) pada kombinasi (`rencana_aksi_id`, `periode_id`)
   yang sama tetap ditolak database. Implementasi detail di F-02.
3. Validasi domain membedakan tipe: manual menolak `komponen_id` terisi;
   nonmanual menolak `komponen_id` NULL; `nilai` tetap nullable (`0` sah,
   `null` = belum diisi) sesuai §2.24. Skor turunan tidak dihitung untuk
   manual (tampilan memakai `nilai` langsung).
4. Snapshot komponen kosong untuk indikator manual adalah keadaan sah
   (Data Model §2.18: tabel anak boleh kosong bila tanpa komponen aktif).

## Konsekuensi

- Migrasi F-02 membuat `komponen_id` nullable + index unik NULL-aware +
   FK; `down()` aman. Model, factory, dan FormRequest menegakkan aturan
   butir 1–3 di atas.
- Gerbang kelengkapan pengajuan (Plan 11.3) untuk manual memeriksa satu
   baris NULL per periode-efektif, bukan per komponen.
- Perubahan memerlukan migrasi + revisi ADR ini (sulit dibalik).
