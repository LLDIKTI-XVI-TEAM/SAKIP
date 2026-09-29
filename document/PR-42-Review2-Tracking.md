# PR-42 — Tracking Review Putaran 2 (HEAD `bb8fb55`)

Sumber: Review PR #42 — ISS-02.04, dilakukan ulang pada HEAD
`bb8fb55aeb7d992f0442202121cc9b7cb561235d`.
Verdict review: **PARTIALLY ALIGNED — NOT READY FOR MERGE**.
CI pada HEAD tersebut hijau 9/9 job (catatan review; browser smoke belum ada).

> Aturan update: session pelaksana ubah `[ ]` → `[x]` + isi Bukti.
> Main session verifikasi lalu update `document/TASK-REGISTRY.md`.

## Dampak ke keputusan grilling lama (penting)

| Keputusan lama | Status baru |
|---|---|
| Q2: pindah unit via edit umum + audit `pindah_unit` | **DICABUT** — pindah unit milik ISS-02.05 (R2-02/R2-03) |
| Q7: `is_aktif=false` = `arsip` (asumsi lokal tracking) | **DICABUT** — belum selaras dokumen; putuskan di R2-04 |
| Q1–Q11 lainnya (glosarium, provenance, lock, audit, regulasi guard) | tetap berlaku |

## Task handoff (satu session = satu task, urut prioritas review)

### R2-01 · [BLOCKER] Keluarkan SeedDemoPengukuran dari PR-42
- [x] Status: selesai
- Untuk apa: command + testnya di luar scope ISS-02.04 ("jangan membuat
  snapshot; jangan membuat workflow RA/Pengukuran"). Pindah ke issue/task
  tersendiri setelah modul Jadwal/Snapshot/RA/Pengukuran tersedia.
- Yang dibuat:
  1. Hapus `app/Console/Commands/SeedDemoPengukuran.php` dari branch ini.
  2. Hapus/relokasi test terkait
     (`test_seed_demo_pengukuran_...` di `SasaranIndikatorTest.php` +
     referensi lain — grep `SeedDemoPengukuran`).
  3. Pastikan tidak ada referensi tersisa (route/command registration/
     seeder call) + `pint --test` + `phpstan` hijau.
- Standards: §2 (scope), §12.
- DoD: grep `SeedDemoPengukuran` nihil di `app/`, `routes/`, `tests/`,
  `database/`; gate statis hijau.
- Selesai: 2026-09-29 | Bukti: HEAD `bb8fb55`; `git rm app/Console/Commands/SeedDemoPengukuran.php` (auto-discover, tanpa registrasi manual di `routes/`); hapus `test_seed_demo_pengukuran_...` (86 baris) + 4 import yatim (`IndikatorKomponen`, `JadwalSnapshotKomponen`, `JadwalTahunan`, `PengukuranKinerja` — terpakai hanya oleh test itu); grep nihil di 4 scope; `pint --test` passed; `phpstan` 0 errors; suite `SasaranIndikatorTest.php` 39 passed/207 assertions (tepat −1 test). Logika Sasaran/Indikator tidak disentuh.

```text
Prompt handoff R2-01:
Kerjakan R2-01 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Keluarkan SeedDemoPengukuran +
test terkait dari PR sesuai detail task. Ikuti Standards §2,§12.
Update checkbox + Bukti. Jangan sentuh logika Sasaran/Indikator.
```

### R2-02 · [MAJOR] Pindah unit jadi endpoint khusus (backend)
- [ ] Status: belum (tergantung R2-04 untuk kontrak status; baca dulu)
- Untuk apa: Plan 2.8 = aksi khusus terpisah dari edit umum (ISS-02.05).
- Yang dibuat:
  1. `PATCH /perencanaan/indikator/{indikator}/pindah-unit`:
     Request (`unit_id` aktif + `alasan` ≥10) + Action khusus
     (lock deterministik, guard lintas-Renstra TIDAK berubah,
     audit `indikator.pindah_unit` saja) + route.
  2. `UpdateIndikator` + `UpdateIndikatorRequest`: tolak perubahan
     `unit_id` (harus sama dengan existing, bila beda → 422 arahkan
     ke endpoint pindah-unit). Hapus logika `isUnitChanged`/`alasan`
     dari Action update umum (pindah ke Action baru).
  3. Test: pindah via endpoint sukses + audit; pindah via PUT umum → 422.
- Standards: §2, §4, §5, §7.
- DoD: test baru hijau; tidak ada jalur edit umum yang mengubah unit.
- Selesai: — | Bukti: —

```text
Prompt handoff R2-02:
Kerjakan R2-02 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Pisahkan pindah-unit ke endpoint
khusus sesuai detail task. Ikuti Standards §2,§4,§5,§7.
Update checkbox + Bukti.
```

### R2-03 · [MAJOR] Pindah unit UI terpisah (frontend)
- [ ] Status: belum (setelah R2-02)
- Untuk apa: cerminkan pemisahan backend di modal.
- Yang dibuat:
  1. Hapus blok alasan-pindah + logika `unit_id` berubah dari
     `IndikatorModal.tsx` (unit tampil read-only saat edit).
  2. Aksi/modal "Pindah Unit" terpisah (pakai capability server;
     alasan wajib; state loading/error sesuai design-system).
- Files: `resources/js/Pages/Perencanaan/SasaranIndikator/*`.
- Standards: §9, design-system.
- Verifikasi: `bun run typecheck`, `bun run test`.
- DoD: edit umum tak bisa pindah unit dari UI; alur pindah khusus jalan.
- Selesai: — | Bukti: —

```text
Prompt handoff R2-03:
Kerjakan R2-03 dari document/PR-42-Review2-Tracking.md (setelah R2-02).
Pisahkan UI pindah-unit sesuai detail task. Ikuti Standards §9 +
design-system. Update checkbox + Bukti.
```

### R2-04 · [MAJOR] Rekonsiliasi lifecycle indikator vs Data Model
- [ ] Status: tahap 1 SELESAI + 7 keputusan user FINAL; eksekusi di R2-04b → R2-04c
- Keputusan user:
  1. Never-delete MUTLAK — hapus jalur `delete()` fisik total.
  2. Cascade hapus Sasaran→Indikator DIPERTAHANKAN (catat sisa risiko di ADR:
     guard tolak-hapus-beranak membuat cascade unreachable via app).
  3. Backfill `tahun_mulai_berlaku` ← `renstra.tahun_mulai` via Sasaran.
  4. `created_by` WAJIB (FK NOT NULL); legacy: derivasi actor audit
     `indikator.buat`, fallback tertua perencanaan/superadmin BILA audit
     kosong (asumsi terdokumentasi di ADR; bila audit selalu ada,
     fallback tak terpakai).
  5. `is_aktif` DIHAPUS dari PUT umum → endpoint arsip khusus;
     default pemegang: pemilik `indikator:delete` (Perencanaan,
     Superadmin — konfirmasi dari preset seeder di tahap 2).
  6. Guard tolak pengukuran/RA untuk arsip DIBUAT DI PR INI.
  7. Setuju ADR lifecycle baru (ganti Q7) + sunset `is_aktif`
     (drop kolom di migrasi tahap 2).
- Untuk apa: (lihat analisis tahap 1 di riwayat session — matriks
  opsi (a)/(b)/(c); user memilih end-state (a) dieksekusi bertahap).
- Tahap 2 dipecah: R2-04b (skema + model) → R2-04c (reader/guard/
  arsip-only + ADR + test). R2-02/R2-05/R2-08 menunggu R2-04c.
- Standards: §5, §12.
- Tahap 1: SELESAI (matriks + rekomendasi di riwayat session).
- Selesai tahap 1: 2026-09-29 | Bukti: analisis + 7 pertanyaan keputusan; user menjawab 7/7 (never-delete, cascade dipertahankan, backfill renstra.tahun_mulai, created_by wajib, is_aktif keluar dari PUT umum, guard arsip di PR ini, setuju ADR).

```text
Prompt handoff R2-04: SELESAI (tahap 1). Lanjut ke R2-04b.
```

### R2-04b · Lifecycle: migrasi skema + model (tanpa reader)
- [ ] Status: belum
- Untuk apa: cutover skema sesuai keputusan R2-04.
- Yang dibuat (HANYA 2 area ini, jangan sentuh reader):
  1. Migrasi BARU timestamp segar (JANGAN pakai prefix duplikat
     `2026_09_25_000001`; JANGAN ubah migrasi lama — itu R2-09):
     tambah `status` enum(`aktif`,`arsip`) default `aktif` + CHECK;
     backfill `true→aktif`/`false→arsip`; tambah
     `tahun_mulai_berlaku` int NOT NULL backfill dari
     `renstra.tahun_mulai` via `sasaran_strategis`; tambah
     `created_by` uuid NOT NULL REFERENCES users ON DELETE RESTRICT
     (backfill: `audit_log.actor_id` baris `indikator.buat` tertua
     per indikator; fallback: user tertua ber-role perencanaan/
     superadmin aktif — catat asumsi di pesan final);
     DROP kolom `is_aktif`. Daftar role/sentinel yang dibutuhkan
     ditulis sebagai KONSTANTA LOKAL beku di migrasi (tanpa import
     `App\...` apa pun, tanpa model — hanya `Schema`/`DB` facade).
  2. `app/Models/IndikatorKinerja.php`: ganti `is_aktif` → `status`
     (fillable + casts + scope bila ada); default `aktif`.
- Standards: §5 (migrasi eksplisit, down() aman, deterministik).
- DoD: fresh `migrate` + seed di DB disposable berjalan; `pint
  --test` + `phpstan` hijau; grep `is_aktif` nihil di 2 file ini.
- Selesai: — | Bukti: —

```text
Prompt handoff R2-04b:
Kerjakan R2-04b dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Hanya migrasi BARU + model
sesuai detail task; JANGAN sentuh reader/controller/frontend/
migrasi lama. Ikuti Standards §5. Update checkbox + Bukti.
```

### R2-04c · Lifecycle: reader/guard/arsip-only + ADR + test
- [ ] Status: belum (setelah R2-04b)
- Untuk apa: selaraskan seluruh pembaca ke `status` + tegakkan
  never-delete + guard arsip di PR ini (keputusan 6).
- Yang dibuat:
  1. `DestroyIndikator` Action: SELALU arsip (`status=arsip` +
     audit `indikator.arsipkan`); hapus jalur `delete()` fisik.
  2. Hapus `is_aktif` dari `Store/UpdateIndikatorRequest`,
     `Store/UpdateIndikator` Action, `IndikatorModal`
     (arsip/reaktivasi hanya via endpoint khusus; di sini cukup
     cabut dari edit umum).
  3. Guard service-layer: `pengukuran:create` + `rencana_aksi:create`
     ditolak untuk indikator `arsip` (siapa pun) + test.
  4. Ganti situs query `is_aktif` indikator (RegulasiService guard,
     JenisBerkas, Index payload, dsb.) ke `status`; kosakata
     audit/pesan → `arsip/diarsipkan`.
  5. ADR `docs/adr/0003-lifecycle-indikator.md` (ganti Q7; catat
     never-delete, cascade dipertahankan + risikonya, backfill,
     sunset `is_aktif` selesai).
- DoD: grep `is_aktif` nihil di `app/`+`resources/js` (di luar
  migrasi lama); guard-test hijau; `pint`+`phpstan` hijau.
- Temuan CI lokal SHA `44cd450` (subagent ses_f1248c268, PG disposable
  5434, `sakip_db` tak tersentuh): PHPStan 1 error
  `IndexSasaranIndikator.php:92` (`$is_aktif` undefined); backend
  minimal 116 passed/53 failed — dominan `null tahun_mulai_berlaku`
  + `is_aktif does not exist` (fixture seeder menulis kolom lama).
  Frontend hijau penuh (typecheck, 129 test, build). Ini daftar
  reader konkret yang WAJIB disentuh task ini (tak terbatas pada ini).
- Selesai: — | Bukti: —

```text
Prompt handoff R2-04c:
Kerjakan R2-04c dari document/PR-42-Review2-Tracking.md (setelah
R2-04b) di branch feature/iss-02-04-sasaran-indikator. Selaraskan
reader/guard/arsip + ADR sesuai detail task. Ikuti Standards
§2,§4,§5,§7,§12. Update checkbox + Bukti.
```

### R2-05 · [MAJOR] Re-authorization konsisten semua mutation
- [ ] Status: belum
- Untuk apa: pola `ResolveLockedActor` (lock aktor + lock ACL +
  resolve ulang + fail-closed) baru di Store/Update Indikator.
- Yang dibuat: terapkan pola yang sama di `Store/Update/DestroySasaran`
  + `DestroyIndikator` (abort/fail-closed bila resolve ulang DENY;
  audit penolakan di luar transaksi). Jangan ubah pesan/audit sukses.
- Standards: §3, §5, §7.
- DoD: test tiap action: cabut izin di antara authorize ↔ mutasi →
  mutasi gagal + audit penolakan (pola allow-then-deny seperti temuan
  review test L1589, bukan deny-sejak-awal).
- Selesai: — | Bukti: —

```text
Prompt handoff R2-05:
Kerjakan R2-05 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Samakan re-authorization sesuai
detail task + test allow-then-deny per action. Ikuti Standards §3,§5,§7.
Update checkbox + Bukti.
```

### R2-06 · [MAJOR] Kepatuhan Design System
- [ ] Status: belum
- Untuk apa: `Index.tsx` masih `text-amber-700/bg-amber-50/
  border-amber-200/emerald-*/blue-*`; `IndikatorModal.tsx` masih
  `dark:*`; aplikasi light-only, token semantik saja.
- Yang dibuat: ganti dengan token (`warning`, `warning-dark`,
  `success`, `info`, `info-dark`, `soft`, `border`) atau komponen
  reusable (Badge); hapus seluruh `dark:*` di modul ini.
- Standards: design-system + checklist review UI.
- Verifikasi: `bun run typecheck`, `bun run test`; screenshot
  sebelum/sesudah (desktop) sebagai bukti di PR.
- DoD: grep `amber-/emerald-/blue-*-700|dark:` nihil di modul
  SasaranIndikator; visual setara.
- Selesai: — | Bukti: —

```text
Prompt handoff R2-06:
Kerjakan R2-06 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Ganti warna mentah + dark:*
dengan token sesuai detail task. Update checkbox + Bukti.
```

### R2-07 · [MAJOR] Browser smoke + bukti QA frontend
- [ ] Status: belum (setelah R2-03 + R2-06)
- Untuk apa: CI tidak punya gate browser/E2E; Standards meminta smoke
  untuk perubahan halaman.
- Yang dibuat: smoke manual terdokumentasi (desktop + mobile):
  create/edit Sasaran, create/edit Indikator, validation errors,
  delete/nonaktifkan, permission denied, modal/error state,
  console + network errors. Tulis hasil sebagai checklist +
  temuan (bila ada, jadi task baru — jangan fix diam-diam).
- DoD: dokumen hasil smoke + tidak ada console error.
- Selesai: — | Bukti: —

```text
Prompt handoff R2-07:
Kerjakan R2-07 dari document/PR-42-Review2-Tracking.md (setelah R2-03
dan R2-06). Lakukan browser smoke sesuai detail task, tulis hasilnya.
Jangan ubah kode kecuali diminta. Update checkbox + Bukti.
```

### R2-08 · [MINOR] Konsolidasi PermissionResolver
- [ ] Status: belum
- Untuk apa: dua resolver (`Services` wrapper vs `Authorization`)
  membingungkan; Standards minta satu yang konsisten.
- Yang dibuat: tetapkan satu canonical (rekomendasi: wrapper
  `App\Services\PermissionResolver` bila `allows()` ditambahkan di
  sana, atau sebaliknya), migrasi `IndexSasaranIndikator` +
  pemakai lain, tanpa mengubah keputusan izin.
- DoD: satu import resolver di seluruh modul Perencanaan;
  `phpstan` + suite modul hijau.
- Selesai: — | Bukti: —

```text
Prompt handoff R2-08:
Kerjakan R2-08 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Konsolidasikan resolver sesuai
detail task tanpa mengubah perilaku izin. Update checkbox + Bukti.
```

### R2-09 · [MINOR] Migration deterministik
- [ ] Status: belum
- Untuk apa: migrasi `2026_09_25_000001...` import model +
  `RoleCatalog` mutable — fresh migrate masa depan bisa berbeda hasil.
- Yang dibuat: freeze daftar role/sentinel sebagai konstanta lokal
  di migrasi; ganti import model dengan query builder;
  pertimbangkan batch (hindari N+1) bila dataset besar.
- DoD: `phpstan` hijau; fresh-migrate + seeder di DB disposable
  terbukti sama; tidak ada `use App\...` mutable tersisa.
- Selesai: — | Bukti: —

```text
Prompt handoff R2-09:
Kerjakan R2-09 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Bekukan migrasi sesuai detail
task. Ikuti Standards §5. Update checkbox + Bukti.
```

### R2-10 · [MINOR] Bersihkan komentar tracking + pecah test besar
- [ ] Status: belum
- Untuk apa: komentar `Q3`, `Q6/ADR 0002`, `TASK-42-06` melanggar
  Standards (tanpa nomor issue/PR/sprint di source); test
  `SasaranIndikatorTest.php` ±2000 baris sulit dirawat.
- Yang dibuat:
  1. Tulis ulang komentar bernomor jadi rationale domain
     (pertahankan makna, buang label internal).
  2. Pecah test per concern (`SasaranCrudTest`,
     `IndikatorCrudTest`, `IndikatorAuthorizationTest`,
     `IndikatorProvenanceTest`, `IndikatorIntegrityTest`);
     helper (`buatUserDenganRole`, `pasangPresetRole`) ke trait
     bersama. Test demo ikut R2-01 bila belum keluar.
- DoD: grep `(Q[0-9]|TASK-[0-9]|ISS-|#42|#26)` nihil di `app/`;
  suite terpecah hijau penuh.
- Selesai: — | Bukti: —

```text
Prompt handoff R2-10:
Kerjakan R2-10 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Bersihkan komentar + pecah test
sesuai detail task. Update checkbox + Bukti.
```
