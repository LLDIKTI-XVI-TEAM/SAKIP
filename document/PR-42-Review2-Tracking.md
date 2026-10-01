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
- [x] Status: SELESAI (2026-09-29) — belum di-merge, belum di-commit (tergantung R2-04 untuk kontrak status; baca dulu)
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
- Selesai: 2026-09-29 | Bukti: endpoint `PATCH /perencanaan/indikator/{indikator}/pindah-unit` (`whereUuid`, name `perencanaan.indikator.pindah-unit`) + `PindahUnitIndikatorRequest` (`unit_id` wajib + hanya unit aktif, `alasan` wajib min 10) + Action `PindahUnitIndikator` (transaksi terkunci: lock aktor + ACL + resolve ulang + fail-closed; lock indikator + kedua unit deterministik terurut; tidak menyentuh sasaran sehingga tanpa guard lintas-Renstra; target nonaktif → 422; audit tunggal `indikator.pindah_unit` nilai lama/baru + tanpa audit `indikator.ubah` untuk delta unit; penolakan izin → audit `indikator.pindah_unit_ditolak` + 403); `UpdateIndikatorRequest` (`unit_id` wajib sama dengan existing via `Rule::in`, pesan mengarahkan ke endpoint pindah-unit) + Action `UpdateIndikator` (guard kesamaan unit di dalam transaksi, logika `isUnitChanged`/`alasan_pindah_unit` dihapus, audit `indikator.ubah` tanpa pengecualian unit); test ditulis ulang/ditambah di `SasaranIndikatorTest.php` (PUT umum berisi perubahan unit → 422 + pesan pindah-unit; PATCH sukses + audit tepat + tanpa `indikator.ubah`; PATCH target nonaktif → 422; PATCH tanpa/pendek alasan → 422; edit gabungan berisi perubahan unit ditolak utuh); `pint --test` passed; `phpstan` 0 errors; Pest TIDAK dijalankan (butuh PG disposable). Referensi `alasan_pindah_unit` tersisa hanya di `IndikatorModal.tsx` (scope R2-03 frontend). Belum di-commit.

```text
Prompt handoff R2-02:
Kerjakan R2-02 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Pisahkan pindah-unit ke endpoint
khusus sesuai detail task. Ikuti Standards §2,§4,§5,§7.
Update checkbox + Bukti.
```

### R2-03 · [MAJOR] Pindah unit UI terpisah (frontend)
- [x] Status: SELESAI (2026-09-30) — belum di-merge, belum di-commit
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
- Selesai: 2026-09-30 | Bukti: `IndikatorModal.tsx` mode edit: field unit jadi read-only (nama unit + teks penjelas mengarah ke aksi Pindah Unit; dropdown hanya saat create); `alasan_pindah_unit` dihapus dari form + blok kondisionalnya; submit edit mengunci `unit_id` ke nilai tersimpan via `transform` agar lolos `Rule::in` `UpdateIndikatorRequest`; `PindahUnitModal.tsx` baru (select unit tujuan aktif tanpa unit asal + alasan wajib min 10 + submit PATCH `/perencanaan/indikator/{id}/pindah-unit` + loading/error/success state; pesan sukses via flash server; modal tutup + reset hanya di `onSuccess`); tombol aksi per baris di `Index.tsx` gate `can.indikator_update`; grep `alasan_pindah_unit` nihil di `resources/`; `bun run typecheck` hijau; `bun run test` hijau 24 file/135 test (129 existing + 6 baru `tests/Frontend/PindahUnitIndikator.test.tsx`: gate tombol, opsi tujuan, validasi klien tanpa PATCH, PATCH valid berisi unit_id/alasan, edit read-only tanpa field alasan). Pest/PHP tidak dijalankan (sesuai instruksi task). Belum di-commit.

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
- [x] Status: SELESAI (2026-09-29) — belum di-merge, belum di-commit
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
- Selesai: 2026-09-29 | Bukti: `DestroyIndikator` arsip-only
  (`status=arsip` + audit `indikator.arsipkan`, tanpa `delete()`/23503);
  `is_aktif` dicabut dari Store/Update Request+Action+`IndikatorModal`;
  `StoreIndikator` mengisi `status/tahun_mulai_berlaku/created_by`;
  reader ke `status` (Index payload, guard hapus Regulasi, JenisBerkas);
  guard `IndikatorArsipGuard` (422) + test; ADR `docs/adr/0003`; grep
  `is_aktif` nihil di `app/`+`resources/js` kecuali domain Renstra;
  `pint --test` passed; `phpstan` 0 errors; `bun run typecheck` passed;
  Pest TIDAK dijalankan (butuh PG disposable). Reaktivasi tidak
  disediakan (open). Belum di-commit.

```text
Prompt handoff R2-04c:
Kerjakan R2-04c dari document/PR-42-Review2-Tracking.md (setelah
R2-04b) di branch feature/iss-02-04-sasaran-indikator. Selaraskan
reader/guard/arsip + ADR sesuai detail task. Ikuti Standards
§2,§4,§5,§7,§12. Update checkbox + Bukti.
```

### R2-05 · [MAJOR] Re-authorization konsisten semua mutation
- [x] Status: SELESAI (2026-09-30) — belum di-merge, belum di-commit
- Untuk apa: pola `ResolveLockedActor` (lock aktor + lock ACL +
  resolve ulang + fail-closed) baru di Store/Update Indikator.
- Yang dibuat: terapkan pola yang sama di `Store/Update/DestroySasaran`
  + `DestroyIndikator` (abort/fail-closed bila resolve ulang DENY;
  audit penolakan di luar transaksi). Jangan ubah pesan/audit sukses.
- Standards: §3, §5, §7.
- DoD: test tiap action: cabut izin di antara authorize ↔ mutasi →
  mutasi gagal + audit penolakan (pola allow-then-deny seperti temuan
  review test L1589, bukan deny-sejak-awal).
- Selesai: 2026-09-30 | Bukti: `Store/Update/DestroySasaran` + `DestroyIndikator` (arsip-only) kini memakai `ResolveLockedActor` dari lokasi sekarang tanpa pemindahan (milik R2-08): lock aktor + lock ACL + resolve ulang di dalam transaksi; akun nonaktif/DENY → audit penolakan di luar transaksi + abort 403 (`sasaran.buat_ditolak`/`sasaran.ubah_ditolak`/`sasaran.hapus_ditolak`/`indikator.hapus_ditolak`, objekId `Str::uuid` untuk buat / id baris untuk ubah-hapus-arsip, `dasar_izin` transaction-time); pesan/audit sukses tidak berubah; guard beranak `DestroySasaran` dipertahankan (status `has_children` → audit + `ValidationException` seperti semula, `dasar_izin` kini transaction-time); 4 test allow-then-deny baru di `SasaranIndikatorTest.php` (mock `PermissionResolver`: panggilan 1 allow agar lolos Gate, panggilan 2+ DENY `revoked_inside_transaction` → 403 + mutasi nihil + audit denied + `dasar_izin.alasan` transaction-time); `pint --test` passed (5 file); `phpstan` 0 errors; Pest TIDAK dijalankan (butuh PG disposable). Belum di-commit.

```text
Prompt handoff R2-05:
Kerjakan R2-05 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Samakan re-authorization sesuai
detail task + test allow-then-deny per action. Ikuti Standards §3,§5,§7.
Update checkbox + Bukti.
```

### R2-06 · [MAJOR] Kepatuhan Design System
- [x] Status: SELESAI (2026-09-30) — belum di-merge, belum di-commit
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
- Selesai: 2026-09-30 | Bukti: `resources/js/Pages/Perencanaan/SasaranIndikator/Index.tsx` 3 span mentah → `Badge` reusable (`Wajib Catatan` amber → `warning`/`warning-dark`; `Naik Baik` emerald → `success`; `Turun Baik` blue → `info`/`info-dark`; pill arah dipertahankan via `rounded-full`); `dark:*` nihil di seluruh modul SasaranIndikator (klaim `dark:*` di `IndikatorModal.tsx` pada deskripsi task tidak terbukti di HEAD — grep nihil sebelum maupun sesudah, tanpa perubahan di file itu); `JenisBerkas/` + `Indikator/Komponen/` digrep sebagai temuan (nihil `amber-/emerald-/blue-700/blue-50/blue-200/dark:`, tidak diubah sesuai instruksi); grep DoD (`amber-|emerald-|blue-|dark:`) nihil di modul; visual setara via deskripsi (ikon+label+ukuran `sm`/`text-[11px]` sama; bentuk pill dipertahankan; palet kuning/hijau/biru lembut → tint semantik; screenshot capture tidak tersedia di env ini); `bun run typecheck` hijau; `bun run test` hijau 24 file/135 test. Belum di-commit.

```text
Prompt handoff R2-06:
Kerjakan R2-06 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Ganti warna mentah + dark:*
dengan token sesuai detail task. Update checkbox + Bukti.
```

### R2-07 · [MAJOR] Browser smoke + bukti QA frontend
- [x] Status: selesai via bukti user (2026-09-30)
- Untuk apa: (tujuan awal di atas tercakup oleh bukti visual).
- Bukti: 5 screenshot di body PR (# GAMBAR: halaman Indikator, popup
  Tambah Sasaran/Indikator, Pengukuran, pengisian) + QA manual user.
  Checklist terstruktur formal direkomendasikan untuk fitur
  berikutnya, bukan blocker PR ini.
- Selesai: 2026-09-30 | Bukti: seksi # GAMBAR pada body PR-42.

```text
Prompt handoff R2-07:
Kerjakan R2-07 dari document/PR-42-Review2-Tracking.md (setelah R2-03
dan R2-06). Lakukan browser smoke sesuai detail task, tulis hasilnya.
Jangan ubah kode kecuali diminta. Update checkbox + Bukti.
```

### R2-08 · [MINOR] Konsolidasi PermissionResolver + rapikan layer
- [x] Status: SELESAI (2026-09-30) — belum di-merge, belum di-commit
- Untuk apa: dua resolver membingungkan; `ResolveLockedActor` bukan
  use-case (milik Service); controller indikator masih query
  `renstra_id` setelah Action.
- Yang dibuat:
  1. Tetapkan satu resolver canonical; migrasi pemakai modul ini.
  2. Pindahkan `ResolveLockedActor` → mis.
     `app/Services/Authorization/` (nama jelas), tanpa ubah perilaku.
  3. Action kembalikan `renstra_id` (atau relasi) agar controller
     murni adapter tanpa query parent.
- DoD: satu import resolver; helper di Service; controller tanpa
  query; `phpstan` + suite modul hijau.
- Selesai: 2026-09-30 | Bukti: `ResolveLockedActor` pindah `app/Actions/Perencanaan/` → `app/Services/Authorization/` (namespace baru, logika lock/resolve identik — `use App\Services\PermissionResolver` wrapper dipertahankan) + import baru di 7 Action (Store/Update/PindahUnitIndikator, Store/Update/DestroySasaran, DestroyIndikator); resolver canonical modul Perencanaan = `App\Services\PermissionResolver` wrapper (`resolve()`; `Authorization\PermissionResolver` tetap sebagai engine di bawahnya, bukan dihapus) — satu-satunya file menyimpang `IndexSasaranIndikator` dimigrasi (`allows()` → `resolve()->allowed`, keputusan izin identik); `Store/Update/PindahUnitIndikator` Action kini kembalikan `array{indikator, renstraId}` (renstraId dari baris terkunci dalam transaksi: Store pakai `$sasaran`, Update pakai `$targetSasaran`, PindahUnit via helper `renstraIdUntuk()` sharedLock) + 3 controller tanpa query (`SasaranStrategis::where` dihapus, pesan/route/flash tetap); `pint --test` passed (12 file); `phpstan` 0 errors; grep nihil untuk `Actions\Perencanaan\ResolveLockedActor` repo-wide + nihil `Authorization\PermissionResolver` di `app/Actions/Perencanaan`; Pest TIDAK dijalankan (butuh PG disposable). Belum di-commit.

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

### R2-10 · [MINOR] Bersihkan komentar tracking internal
- [x] Status: SELESAI (2026-09-30) — belum di-merge, belum di-commit
- Untuk apa: komentar `Q3`, `Q6/ADR 0002`, `TASK-42-06` melanggar
  Standards (tanpa nomor issue/PR/sprint di source).
- Yang dibuat: tulis ulang komentar bernomor jadi rationale domain
  (pertahankan makna, buang label internal). Pecah test besar
  DIPINDAH ke BACKLOG-02 (follow-up issue eksplisit).
- DoD: grep `(Q[0-9]|TASK-[0-9]|ISS-|#42|#26)` nihil di `app/`.
- Selesai: 2026-09-30 | Bukti: 5 file hanya teks komentar tanpa ubah logika — `IndexSasaranIndikator.php` (`(Q32)` → `tidak berwenang`), `StoreIndikator.php` ×3 (`pada keputusan Q32` → `pada keputusan izin`; `(Q3):` → `:`; `berdasarkan resolusi Q32` → `berdasarkan hasil resolusi izin`), `UpdateIndikator.php` (`(Q3):` → `:`), `PermissionCatalog.php` (`(ISS-01.04 / Q32)` → `lingkup unit`), `PermissionCodes.php` ×9 (7 grup ` (ISS-…)` dibuang; header scope-unit + docblock `unitScoped` tanpa `ISS-01.04, Q32`, `§6` dipertahankan); test-split `SasaranIndikatorTest` TIDAK dikerjakan (BACKLOG-02); verifikasi: grep DoD nihil di `app/`; `php vendor/bin/pint --test` 5 file passed; `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress --memory-limit=1G` 0 errors; Pest TIDAK dijalankan (sesuai instruksi). Belum di-commit.

```text
Prompt handoff R2-10:
Kerjakan R2-10 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator (setelah R2-08b). Bersihkan
komentar sesuai detail task. Update checkbox + Bukti. JANGAN commit.
```

### R2-11 · [CI MERAH] Fixture Grant test vs kontrak lifecycle
- [x] Status: selesai (belum di-commit)
- Untuk apa: `composer test` merah 1/597 —
  `test_grant_audits_preserve_permission_basis_and_inactive_revoke_cleanup`
  (`tests/Feature/GrantIzinTambahanUnitTest.php:268`) membuat
  `IndikatorKinerja` langsung tanpa kolom wajib pasca-R2-04b
  (`created_by_role` → guard model throw; plus `status`,
  `tahun_mulai_berlaku`, `created_by` NOT NULL). Test ditulis
  pra-lifecycle (masuk via merge development).
- Yang dibuat (HANYA file ini, jangan ubah logika grant):
  1. Lengkapi create `:268` dengan `status => aktif`,
     `tahun_mulai_berlaku` (2025, konsisten renstra 2025–2029 di test),
     `created_by => $this->superadminUser->id`, `created_by_role`
     dari peran aktual pembuat (pola `CreatesPengukuranFixture`).
  2. Grep file yang sama untuk create `IndikatorKinerja` lain yang
     kurang kolom wajib baru; perbaiki semua yang pecah.
- Standards: §11 (fixture eksplisit, deterministik).
- Verifikasi: `php vendor/bin/pint --test` file itu + focused Pest
  di DB disposable (JANGAN DB dev; lihat `PR-42-CI-Test-Context.md` §3).
- DoD: test merah menjadi hijau; tidak ada perubahan perilaku grant.
- Selesai: 2026-09-30 | Bukti: `tests/Feature/GrantIzinTambahanUnitTest.php:268` dilengkapi `status=aktif`, `tahun_mulai_berlaku=2025`, `created_by=$this->superadminUser->id`, `created_by_role=$this->superadminUser->roles->first()?->kode ?? 'superadmin'` (pola `CreatesPengukuranFixture`; fallback tak terpakai — aktor ber-role superadmin); grep file yang sama: satu-satunya `IndikatorKinerja::create` hanya `:268`, `Renstra::create` `:261` (`is_aktif=true`+`created_by`, tetap valid untuk domain Renstra) dan `SasaranStrategis::create` `:265` (tanpa kolom baru) tidak perlu diubah; logika grant/domain nihil disentuh; `php vendor/bin/pint --test` file itu passed; Pest focused `test_grant_audits_preserve_permission_basis_and_inactive_revoke_cleanup` 1 passed/21 assertions di PG disposable podman `postgres:17-alpine` port 5434 (DB `sakip_test`, fresh migrate incl. cutover lifecycle; dev `sakip_db:5433` tak tersentuh; container dihapus setelah run). Belum di-commit.

```text
Prompt handoff R2-11:
Kerjakan R2-11 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Selaraskan fixture dengan
kontrak lifecycle sesuai detail task; JANGAN ubah logika grant.
Ikuti Standards §11. Update checkbox + Bukti. JANGAN commit.
```

### R2-12 · PR description sesuai HEAD
- [x] Status: selesai (tanpa kode; hanya body PR remote + tracking; JANGAN commit)
- Untuk apa: body PR masih perilaku lama (fallback is_aktif,
  controller-oriented, audit/test lama). Traceability harus ikut HEAD.
- Yang dibuat: baca body via `gh pr view 42 --json body`; tulis ulang
  (scope ISS-02.04 + corrective ADR 0001–0003 + Action pattern +
  lifecycle arsip/never-delete + endpoint pindah-unit + gate terbaru);
  pertahankan referensi `Closes #26`; terapkan via `gh pr edit 42`.
- DoD: body mencerminkan HEAD; tidak ada klaim perilaku yang dihapus.
- Selesai: 2026-09-30 | Bukti: body ditulis ulang untuk HEAD `41976fc` (scope Plan 2.5–2.7/2.19 + ADR 0001–0003 + 9 Action `app/Actions/Perencanaan/` + lifecycle `aktif|arsip` + never-delete + `PATCH /perencanaan/indikator/{indikator}/pindah-unit` + migrasi `2026_09_30_064700_add_lifecycle` + gate pint/phpstan/typecheck/FE 129/Pest via CI); diterapkan via `gh pr edit 42 --body-file` (UTF-8 tanpa BOM); verifikasi baca-balik `gh pr view 42 --json body`: paragraf kunci hadir (Action pattern, ADR 0001–0003, PATCH pindah-unit, never-delete, migrasi lifecycle, `Closes #26`, `41976fc`, 129/129) dan klaim lama nihil (`10 passed (45 assertions`, `91 passed`, `controller-oriented`, `fallback penonaktifan`, `is_aktif = false` — penyebutan `is_aktif` tersisa hanya untuk sunset/backfill). Tanpa ubah kode; tanpa commit.

```text
Prompt handoff R2-12:
Kerjakan R2-12 dari document/PR-42-Review2-Tracking.md. Tulis ulang
body PR-42 sesuai HEAD lalu terapkan via gh pr edit. Tanpa kode.
Update checkbox + Bukti. JANGAN commit.
```

### R2-13 · [MINOR] Sanitasi alasan audit penolakan di Policy
- [x] Status: SELESAI (2026-09-30) — belum di-commit
- Untuk apa: `SasaranStrategisPolicy` + `IndikatorKinerjaPolicy` kirim
  `request()->input('alasan')` mentah ke audit append-only sebelum
  validasi selesai (tanpa bound max).
- Yang dibuat: helper sanitasi/bounding konsisten (trim + batas
  maks 1000, fallback pesan generik bila kosong) dipakai kedua Policy;
  test di FILE TEST BARU (jangan sentuh file test existing —
  hindari konflik dengan R2-05/R2-10).
- Standards: §7 (audit aman).
- DoD: payload panjang/kosong → audit tetap terbatas/waras; test baru
  hijau; `pint`+`phpstan` hijau.
- Selesai: 2026-09-30 | Bukti: helper shared `app/Support/AlasanAudit.php` baru (final; `BATAS_MAKS=1000`; trim + `mb_substr` UTF-8 per karakter; non-string/kosong → fallback) dipakai 4 situs di kedua Policy (inline `create` + `catatPenolakan` update/delete) tanpa ubah pesan fallback existing; allow/deny TIDAK berubah (semua skenario test menegaskan 403); test BARU `tests/Feature/Perencanaan/PolicyAlasanSanitasiTest.php` 8 passed/19 assertions (panjang→dibatasi 1000 incl. multibyte `é`×1500; kosong/spasi/tanpa-alasan→fallback generik; valid→utuh; mencakup jalur create + update kedua Policy); file test existing tak tersentuh; `php vendor/bin/pint --test` 4 file passed; `phpstan` 0 errors; Pest focused di PG disposable podman `postgres:17-alpine` port 5435 (DB `sakip_test`, fresh migrate; dev `sakip_db:5433` tak tersentuh; container `sakip_test_r213` dihapus setelah run). HEAD `5549e5e`. Belum di-commit.

```text
Prompt handoff R2-13:
Kerjakan R2-13 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Sanitasi alasan audit Policy
sesuai detail task; test di file BARU. Update checkbox + Bukti.
JANGAN commit.
```

### R2-08b · Resolver canonical langsung (new code)
- [x] Status: SELESAI (2026-09-30) — belum di-merge, belum di-commit
- Untuk apa: wrapper `App\Services\PermissionResolver` ber-docblock
  deprecated (canonical = `Authorization\`) tetapi code baru PR-42
  masih memakainya — kontradiktif (temuan 3 review putaran 3).
- Yang dibuat: modul Perencanaan (Actions + Policies + Requests yang
  ditambahkan PR ini) import langsung
  `App\Services\Authorization\PermissionResolver` (`decide()`/
  `allows()`); wrapper hanya untuk legacy di luar scope. Tanpa ubah
  keputusan izin. Lihat keputusan R2-08 (helper sudah di Service).
- DoD: grep `use App\Services\PermissionResolver;` nihil di file baru
  PR-42; `phpstan` + suite modul hijau.
- Selesai: 2026-09-30 | Bukti: 5 file import wrapper → canonical langsung tanpa ubah pemanggilan (engine `resolve()` identik dengan wrapper — warisan tanpa override; pembungkusan `decide()`→`PermissionDecision` di `Authorization\PermissionResolver:90-102` dipakai apa adanya, isi keputusan tak berubah): `app/Actions/Perencanaan/IndexSasaranIndikator.php`, `StoreIndikator.php` (+reorder import alfabetis), `UpdateIndikator.php` (+reorder), `app/Policies/SasaranStrategisPolicy.php`, `app/Policies/IndikatorKinerjaPolicy.php` (pakai `resolve()` karena butuh `toAuditBasis()`/`basis`/`allowed`); `Controllers/Perencanaan/*` + `Requests/Indikator/*` + `Requests/Sasaran/*` tidak memakai wrapper (basis Gate, nihil sebelum maupun sesudah — tak diubah); `Services/Authorization/ResolveLockedActor.php` + seluruh file legacy di luar daftar tak disentuh sesuai instruksi; verifikasi: `git grep "use App\Services\PermissionResolver;"` nihil pada 6 path scope; `php vendor/bin/pint --test` 5 file passed; `phpstan analyse` 0 errors; Pest TIDAK dijalankan (butuh PG disposable, sesuai instruksi). Belum di-commit.

```text
Prompt handoff R2-08b:
Kerjakan R2-08b dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator (setelah R2-08). Pakai resolver
canonical langsung sesuai detail task. Update checkbox + Bukti.
JANGAN commit.
```

### R2-08c · Selaraskan helper + mock ke canonical (folger R2-08b)
- [x] Status: SELESAI (2026-09-30) — belum di-merge, belum di-commit
- Untuk apa: 6 test allow-then-deny pecah 403-vs-302 pasca-R2-08b —
  mock wrapper `App\Services\PermissionResolver` tak mengintersep code
  canonical `App\Services\Authorization\PermissionResolver`
  (Policies + 3 Action); helper `ResolveLockedActor` masih type-hint
  wrapper. Fakta CI-mirror: call#1 Gate (canonical real) allow, call
  wrapper#1 di dalam transaksi (mock) allow → mutasi sukses 302,
  ekspektasi 403 gagal.
- Yang dibuat:
  1. `app/Services/Authorization/ResolveLockedActor.php`: hapus
     `use App\Services\PermissionResolver` wrapper → type-hint
     `PermissionResolver` kini resolve ke canonical satu namespace;
     logika lock/resolve IDENTIK, tanpa ubah perilaku.
  2. `tests/Feature/Perencanaan/SasaranIndikatorTest.php`: `use`
     wrapper → `use App\Services\Authorization\PermissionResolver`
     (+reorder alfabetis); seluruh `app()`/`app->instance()`/
     `createMock(PermissionResolver::class)` (6 test) kini mengikat
     canonical. Urutan mock allow-then-deny dipertahankan (call#1
     allow lolos Gate, call#2+ deny `revoked_inside_transaction` →
     403 + mutasi nihil + audit denied). Asersi/aturan bisnis nihil
     diubah.
  3. Wrapper `App\Services\PermissionResolver` tetap hidup untuk
     legacy di luar modul — TIDAK dihapus.
- DoD: grep `use App\Services\PermissionResolver;` nihil di modul
  Perencanaan (`app/Actions/Perencanaan`, Policies Sasaran/Indikator,
  Requests Sasaran/Indikator, helper, test); `pint`+`phpstan` hijau;
  6/6 allow-then-deny hijau di PG disposable.
- Selesai: 2026-09-30 | Bukti: helper 1 baris (`use` wrapper dihapus);
  test 1 import (+reorder) mencakup 7 situs (`use` + 6 `app->instance`,
  `app()`/`createMock` ikut via import); grep `use App\Services\PermissionResolver;` nihil pada 9 path scope modul + `Services\PermissionResolver` nihil di helper+test; wrapper legacy utuh di luar modul (Access/Unit/Renstra/Regulasi — tak disentuh); `php vendor/bin/pint --test` 2 file passed; `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress --memory-limit=1G` 0 errors; Pest focused 6 passed/45 assertions (`test_store_indikator_reauthorizes_actor_inside_transaction`, `test_update_indikator_menghentikan_mutasi_saat_resolusi_di_dalam_transaksi_menolak`, `test_store_sasaran_menghentikan_mutasi_saat_resolusi_di_dalam_transaksi_menolak`, `test_update_sasaran_menghentikan_mutasi_saat_resolusi_di_dalam_transaksi_menolak`, `test_destroy_sasaran_menghentikan_mutasi_saat_resolusi_di_dalam_transaksi_menolak`, `test_arsip_indikator_menghentikan_mutasi_saat_resolusi_di_dalam_transaksi_menolak`) di PG disposable podman `postgres:17-alpine` port 5438 (DB/user `sakip_test`, fresh migrate via `RefreshDatabase`; dev `sakip_db:5433` tak tersentuh; container `sakip_test_r208c` dihapus setelah run). HEAD `0085c91`. Belum di-commit.

```text
Prompt handoff R2-08c:
Kerjakan R2-08c dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator (folger R2-08b). Selaraskan helper
+ mock ke canonical sesuai detail task. Update checkbox + Bukti.
JANGAN commit.
```

### R2-14 · [BLOCKER] Sanitasi NUL/UTF-8 AlasanAudit
- [x] Status: SELESAI (2026-09-30) — belum di-commit
- Untuk apa: `AlasanAudit` tanpa guard NUL/invalid-UTF-8 → request
  unauthorized bisa picu 500 (bukan 403 + audit) karena PostgreSQL
  menolak NUL pada text (temuan 1 review putaran 3).
- Yang dibuat:
  1. `app/Support/AlasanAudit.php`: cek UTF-8 valid
     (`mb_check_encoding`), strip/tolak byte NUL, invalid →
     fallback generik; valid >1000 → truncate (perilaku lama tetap).
  2. Regression test: denied + NUL pada create/update Sasaran +
     Indikator (minimal 3: sasaran-create, indikator-create,
     update) → tetap 403 + audit tersimpan + tanpa 500.
- DoD: 3+ test hijau; `pint`+`phpstan` hijau.
- Selesai: 2026-09-30 | Bukti: `app/Support/AlasanAudit.php` (satu-satunya file app tersentuh): `mb_check_encoding` UTF-8 → invalid fallback generik; `str_contains "\0"` → `str_replace` strip NUL lalu lanjut trim + max 1000 + fallback kosong (perilaku lama utuh; signature/kontrak tak berubah); 5 regression test baru di `tests/Feature/Perencanaan/PolicyAlasanSanitasiTest.php` (sasaran-create NUL, indikator-create NUL, sasaran-update NUL, indikator-update NUL → masing-masing 403 + audit tersimpan ter-strip tanpa NUL; sasaran-create invalid-UTF-8 `\xFF\xFE` → 403 + fallback generik); `php vendor/bin/pint --test` 2 file passed; `phpstan` 0 errors; Pest focused 13 passed/33 assertions (8 existing + 5 baru) di PG disposable podman `postgres:17-alpine` port 5436 (DB `sakip_test`, fresh migrate; dev `sakip_db:5433` tak tersentuh; container `sakip_test_r214` dihapus setelah run). HEAD `0085c91`. Belum di-commit.

```text
Prompt handoff R2-14:
Kerjakan R2-14 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Perkuat sanitasi + regression
test sesuai detail task. Update checkbox + Bukti. JANGAN commit.
```

### R2-15 · [MAJOR] Vocab audit arsipkan_ditolak + controller murni
- [x] Status: SELESAI (2026-10-01) — belum di-commit
- Untuk apa: success `indikator.arsipkan` vs denied
  `indikator.hapus_ditolak` tidak konsisten (query `arsipkan*`
  melewatkan denied); + `DestroyIndikator` controller masih query
  `renstra_id` sendiri.
- Yang dibuat:
  1. Rename denied → `indikator.arsipkan_ditolak` di
     `DestroyIndikator` Action + `IndikatorKinerjaPolicy::delete`
     (+ pesan menyertakan kata arsip, bukan hapus).
  2. Perbarui asersi test yang mengharapkan `hapus_ditolak`
     (grep `hapus_ditolak` di tests/).
  3. `DestroyIndikator` Action kembalikan `['kode','renstraId']`;
     controller murni request → Action → redirect (hapus query).
- DoD: grep `hapus_ditolak` nihil di app/+tests/ (di luar migrasi/
  histori audit lama bila ada); `pint`+`phpstan` hijau.
- Selesai: 2026-10-01 | Bukti: `app/Actions/Perencanaan/DestroyIndikator.php` (denied `indikator.hapus_ditolak` → `indikator.arsipkan_ditolak` di docblock + audit luar-transaksi; return luar kini `['kode','renstraId']` — `renstraId` dari baris terkunci dalam transaksi via helper `renstraIdUntuk()` sharedLock pola `PindahUnitIndikator`, key `diarsipkan` dihapus; audit sukses `indikator.arsipkan` + abort `Anda tidak berwenang mengarsipkan indikator kinerja.` tak berubah) + `app/Policies/IndikatorKinerjaPolicy.php::delete` (`indikator.hapus_ditolak` → `indikator.arsipkan_ditolak`; fallback audit otomatis berkata arsip, deny Response generik tak berubah) + `app/Http/Controllers/Perencanaan/DestroyIndikator.php` (query `SasaranStrategis::where` + import dihapus; murni request → Action → redirect pakai `$hasil['renstraId']`; pesan/route/flash identik) + `tests/Feature/Perencanaan/SasaranIndikatorTest.php:1864` (satu-satunya situs `indikator.hapus_ditolak` di tests/ → `indikator.arsipkan_ditolak`; 4 asersi sukses `indikator.arsipkan` utuh); verifikasi: grep `indikator.hapus_ditolak` nihil di `app/`+`tests/`; `php vendor/bin/pint --test` 4 file passed; `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress --memory-limit=1G` 0 errors; Pest focused 6 passed/35 assertions (`test_arsip_indikator_..._menolak`, `test_8_destroy_indikator_...`, `test_destroy_indikator_with_extended_dependencies_...`, `test_7_unauthorized_direct_request_ditolak_403`, `test_destroy_request_validates_minimum_reason_length`, `test_guard_arsip_...`) di PG disposable podman `postgres:17-alpine` port 5439 (DB/user `sakip_test`, fresh migrate via `RefreshDatabase`; dev `sakip_db:5433` tak tersentuh; container `sakip_test_r215` dihapus setelah run). Catatan scope: `hapus_ditolak` non-indikator (sasaran/unit/renstra/berkas/regulasi/jenis_berkas/komponen) tetap ada — di luar scope R2-15; DoD literal dibaca sebagai `indikator.hapus_ditolak`. HEAD `1a0bb3f`. Belum di-commit.

```text
Prompt handoff R2-15:
Kerjakan R2-15 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Samakan vocab + murnikan
controller sesuai detail task. Update checkbox + Bukti. JANGAN commit.
```

### R2-16 · [MINOR] Seeder creator deterministik
- [x] Status: SELESAI (2026-10-01) — belum di-commit
- Untuk apa: `IndikatorKomponenFixtureSeeder` memakai `User::first()`
  (tergantung urutan data) + `updateOrCreate` membawa
  `created_by_role` immutable pada update.
- Yang dibuat: creator fixture deterministik (lookup email/keycloak
  fixture yang diketahui; buat bila belum ada) + pisahkan field
  create-only (`created_by_role`, `created_by`) dari field update
  (jangan sertakan pada update).
- DoD: seeder deterministik di DB kosong maupun berisi; bukan
  production path (tetap).
- Selesai: 2026-10-01 | Bukti: `database/seeders/IndikatorKomponenFixtureSeeder.php` satu-satunya file kode tersentuh — creator kini lookup deterministik `User::where email perencanaan@sakip.local orderBy id` (konvensi email fixture yang sudah ada di file; tanpa konvensi auth baru, login tetap Keycloak SSO + JIT); baris baru memakai `keycloak_id` tetap `fixture-perencanaan-sakip-local` (baris lama se-email dipakai apa adanya, tidak ditulis ulang); peran via katalog existing (`Role where kode perencanaan`, attach `manual`, pola provenance `CreatesPengukuranFixture` dipertahankan incl. throw bila katalog kosong); 6 `updateOrCreate` diganti helper `syncIndikator`/`syncKomponen` (payload update hanya field mutable; `created_by`/`created_by_role` hanya pada create; `kode`+kunci tetap ikut create); `php vendor/bin/pint --test` file itu passed; `phpstan` 0 errors; outcome di PG disposable podman `postgres:17-alpine` port 5441 (DB/user `sakip_test`, fresh migrate; dev `sakip_db:5433` tak tersentuh; container dihapus): seed-1 OK, seed-2 idempoten OK (1 user fixture + 2 IKU + 4 komponen, `created_by_role` perencanaan), seed-3 pasca-decoy user OK (creator tetap fixture, 2 IKU + 4 komponen menunjuk fixture); Pest focused `IndikatorKomponenFixtureTest` 3 passed/24 assertions. Temuan tengah jalan: helper awal lupa ikutkan `kode` pada create (ditangkap outcome check, sudah diperbaiki + gate diulang hijau). Belum di-commit.

```text
Prompt handoff R2-16:
Kerjakan R2-16 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Deterministikkan seeder sesuai
detail task. Update checkbox + Bukti. JANGAN commit.
```

### R2-17 · [P2] Guard lepas-rujuk regulasi + kunci regulasi (Store/Update Indikator)
- [x] Status: SELESAI (2026-10-01) — belum di-commit
- Untuk apa: (a) `regulasi_id=null` lolos guard `regulasi:read` —
  edit biasa bisa melepas dasar hukum tanpa izin baca; (b) baris
  regulasi tak dikunci — TOCTOU flag `aktif` antara validasi dan INSERT.
- Yang dibuat (Store + UpdateIndikator Action):
  1. Tanpa `regulasi:read` efektif → abaikan `regulasi_id` dari
     input (pertahankan nilai lama), JANGAN tulis null.
  2. Dengan izin baca: muat regulasi target `sharedLock` di dalam
     transaksi + cek ulang `aktif` (+404/422 bila hilang/nonaktif).
  3. Test: deny + null → nilai lama bertahan; regulasi dinonaktifkan
     tengah jalan → 422; tanpa izin + isi id → 403 (sudah ada).
- DoD: 3+ test hijau; `pint`+`phpstan` hijau.
- Selesai: 2026-10-01 | Bukti: `StoreIndikator` (guard 403 non-null tanpa baca dipertahankan + `5c` kunci `Regulasi::sharedLock` + cek ulang `aktif` → 422 `Rujukan regulasi tidak valid atau sudah nonaktif.`; null → tulis null tanpa kunci) + `UpdateIndikator` (`2b` deny+null → abaikan/pertahankan lama + 302, deny+isi → 403 existing; `4b` kunci `sharedLock` + cek ulang `aktif` → 422 pola unit/sasaran; pesan/audit sukses tak berubah) + 2 test baru di `SasaranIndikatorTest.php` (`test_r217_deny_null_diabaikan_nilai_lama_bertahan`: deny+null → 302 + nama berubah + regulasi lama bertahan + store-null sukses; `test_r217_regulasi_dinonaktifkan_via_db_ditolak_422`: flag `aktif=false` via DB langsung lalu request → 422 store+update; 403 isi-tanpa-izin tercakup test existing `test_regulasi_read_denied_blocks_store_and_update_with_audit` — tak diduplikasi); verifikasi: `php vendor/bin/pint --test` 3 file passed; `phpstan` 0 errors; Pest focused 5 passed/33 assertions + full `SasaranIndikatorTest.php` 50 passed/279 assertions di PG disposable podman `postgres:17-alpine` port 5442 (DB/user `sakip_test`, fresh migrate via `RefreshDatabase`; dev `sakip_db:5433` tak tersentuh; container `sakip_test_r217` dihapus setelah run). HEAD `ae19daa`. Belum di-commit.

```text
Prompt handoff R2-17:
Kerjakan R2-17 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Kuatkan regulasi sesuai detail
task + test. Update checkbox + Bukti. JANGAN commit.
```

### R2-18 · [P2] Seeder tak boleh reaktivasi arsip
- [x] Status: SELESAI (2026-10-01) — belum di-commit
- Untuk apa: `status` di payload mutable `syncIndikator` — rerun
  mengaktifkan kembali IKU arsip tanpa endpoint/audit.
- Yang dibuat: pindahkan `status` ke create-only (existing
  dipertahankan apa adanya). Test: arsipkan IKU lalu rerun seeder
  → tetap arsip.
- DoD: test hijau; `pint`+`phpstan` hijau.
- Selesai: 2026-10-01 | Bukti: `database/seeders/IndikatorKomponenFixtureSeeder.php` satu-satunya file kode tersentuh — `status => aktif` keluar dari `$iku3Mutable`/`$iku8Mutable` → masuk `$iku3CreateOnly`/`$iku8CreateOnly` (existing di-`update($mutable)` tanpa status, baris arsip dipertahankan; baris baru tetap `aktif` via create-only + default model); logika `syncIndikator`/`syncKomponen`/creator deterministik R2-16 tak berubah; test baru `test_rerun_seeder_tidak_mereaktivasi_iku_arsip` di `tests/Feature/IndikatorKomponen/IndikatorKomponenFixtureTest.php` (seed → arsipkan IKU-3 via model langsung → rerun seed → tetap `arsip` + 2 komponen `sakip`/`zi_wbk` sinkron); `php vendor/bin/pint --test` 2 file passed; `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress --memory-limit=1G` 0 errors; Pest focused `IndikatorKomponenFixtureTest` 4 passed/29 assertions (3 existing + 1 baru) di PG disposable podman `postgres:17-alpine` port 5442 (DB/user `sakip_test`, fresh migrate via `RefreshDatabase`; dev `sakip_db:5433` tak tersentuh; container `sakip_test_r218` dihapus setelah run). HEAD `ae19daa`. Belum di-commit.

```text
Prompt handoff R2-18:
Kerjakan R2-18 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Bekukan status existing sesuai
detail task + test. Update checkbox + Bukti. JANGAN commit.
```

### R2-19 · [P2] Tolak payload edit usang (stale-write guard)
- [x] Status: SELESAI (2026-10-01) — belum di-commit
- Untuk apa: lock serialisasi eksekusi tapi tak deteksi form usang —
  tab kedua menimpa perubahan tab pertama diam-diam.
- Yang dibuat (tanpa migrasi — pakai `updated_at` sebagai token):
  1. Frontend kirim `expected_updated_at` (dari model saat modal
     dibuka) pada PUT Sasaran/Indikator.
  2. Backend bandingkan dengan baris terkunci → beda → 409 +
     pesan muat-ulang (tanpa mutasi/audit sukses).
  3. Test: dua payload berurutan → kedua 409 + data pertama utuh.
- DoD: test hijau; `typecheck` + FE test hijau; `pint`+`phpstan`.
- Selesai: 2026-10-01 | Bukti: `UpdateSasaranRequest`/`UpdateIndikatorRequest` tambah `expected_updated_at` nullable date + pesan `Format timestamp versi tidak valid.`; controller `UpdateSasaran`/`UpdateIndikator` teruskan via `[...validated(), ...only('expected_updated_at')]`; Action `UpdateSasaran`/`UpdateIndikator` bandingkan `Carbon::parse(expected)->toISOString()` vs `updated_at ?? created_at` baris terkunci setelah `lockForUpdate` (sebelum mutasi/audit; null/kosong → lewati agar klien lama tetap jalan; format invalid → `expected_updated_at` 422; beda → `konflik` + `->status(409)` = 302+session error untuk web, 409 JSON); pesan sukses/audit sukses tak berubah; `IndexSasaranIndikator` ekspos `updated_at` ISO untuk sasaran+indikator + tipe TS `updated_at?`; `SasaranModal`/`IndikatorModal` kirim `expected_updated_at` dari model saat dibuka + tampilkan `konflik ?? expected_updated_at` apa adanya (`role=alert text-danger`, tanpa UI khusus); test baru `SasaranIndikatorStaleTest.php` 2 passed/20 assertions (indikator + sasaran: A 302 sukses → B web 302 `konflik` + B-json 409 `konflik` + data A utuh + `*.ubah` tepat 1); regresi `SasaranIndikatorTest.php` 50 passed/279 assertions (tanpa token tetap lolos = nullable); `bun run typecheck` hijau; `php vendor/bin/pint --test` 8 file passed; `phpstan` 0 errors; `bun run test` 26 file/145 test hijau; Pest di PG disposable podman `postgres:17-alpine` port 5444 (DB/user `sakip_test`, fresh migrate via `RefreshDatabase`; dev `sakip_db:5433` tak tersentuh; container `sakip_test_r219` dihapus). Belum di-commit.

```text
Prompt handoff R2-19:
Kerjakan R2-19 dari document/PR-42-Review2-Tracking.md (setelah
R2-17) di branch feature/iss-02-04-sasaran-indikator. Pasang guard
usang sesuai detail task + test. Update checkbox + Bukti. JANGAN commit.
```

### R2-20 · [P2] Kunci Renstra induk sebelum buat Sasaran
- [x] Status: SELESAI (2026-10-01) — belum di-commit
- Untuk apa: parent hanya divalidasi pre-transaksi — hapus Renstra
  konkuren → FK/500, bukan 422 terkontrol.
- Yang dibuat: `StoreSasaran` Action muat Renstra `sharedLock` +
  cek ulang di transaksi (404/422 bila hilang). Test:
  parent dihapus tengah jalan → gagal terkontrol.
- DoD: test hijau; `pint`+`phpstan` hijau.
- Selesai: 2026-10-01 | Bukti: `app/Actions/Perencanaan/StoreSasaran.php` satu-satunya file app tersentuh — tambah import `Renstra` + `ValidationException` (urutan alfabetis); cek ulang `Renstra::whereKey(...)->sharedLock()->first()` setelah re-auth (`$dasarIzin`) sebelum `create`, hilang → `ValidationException` `renstra_id: Renstra yang dipilih tidak valid.` (selaras pesan `StoreSasaranRequest`; padanan 422 web / 404-422 langsung, tanpa audit sukses; pesan/audit sukses `sasaran.buat` + audit `sasaran.buat_ditolak` tak berubah); test baru `test_r220_store_sasaran_gagal_terkontrol_saat_renstra_dihapus_tengah_jalan` di `SasaranIndikatorTest.php` (simulasi race sejati via `User::retrieved`: hapus Renstra via `DB::table` saat Action mengunci aktor di dalam transaksi — setelah validasi pra-transaksi lolos, sebelum cek terkunci; tanpa fix INSERT melanggar FK → 500; assert race berjalan + bukan 500 + 302→`assertSessionHasErrors(['renstra_id'])` atau 404/422 + `sasaran_strategis` nihil + `audit_log sasaran.buat` nihil); `php vendor/bin/pint --test` 2 file passed; `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress --memory-limit=1G` 0 errors; Pest focused 3 passed/17 assertions (`test_1_create_sasaran_...`, `test_store_sasaran_..._menolak`, `test_r220_...`) di PG disposable podman `postgres:17-alpine` port 5443 (DB/user `sakip_test`, fresh migrate via `RefreshDatabase`; dev `sakip_db:5433` + container sesi lain `sakip_test_r217:5442` tak tersentuh; container `sakip_test_r220` dihapus setelah run). HEAD `ae19daa`. Belum di-commit.

```text
Prompt handoff R2-20:
Kerjakan R2-20 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Kunci parent sesuai detail
task + test. Update checkbox + Bukti. JANGAN commit.
```

### R2-21 · [MAJOR] Global lock order Regulasi→Indikator
- [x] Status: SELESAI (2026-10-01) — belum di-commit
- Untuk apa: `UpdateIndikator` lock Indikator→Regulasi sedangkan
  delete Regulasi lock Regulasi→Indikator = inversi deadlock
  (40P01/500 pada request konkuren valid).
- Yang dibuat:
  1. Aturan global: Regulasi SELALU dikunci SEBELUM Indikator
     (bila `regulasi_id` non-null; null = lewati).
     Berlaku untuk Store/Update/PindahUnit Indikator (cek ketiganya).
  2. Periksa jalur delete Regulasi (`RegulasiService`) — sesuaikan
     MINIMAL bila urutannya berlawanan (JANGAN refactor modul Regulasi).
  3. Test dua-koneksi PostgreSQL: TxA pegang Indikator + TxB pegang
     Regulasi → lanjutkan keduanya → tanpa 40P01, outcome
     deterministik, tanpa mutasi/audit parsial (pakai 2 PDO +
     `lock_timeout` pendek; bila tak dimungkinkan, dokumentasikan
     batas bukti + test sekuensial pengganti).
- DoD: tidak ada jalur `Indikator→Regulasi` tersisa; test hijau;
  `pint`+`phpstan` hijau.
- Selesai: 2026-10-01 | Bukti: `StoreIndikator` (2c kunci `Regulasi::sharedLock` SEBELUM Unit/Sasaran/Renstra; 5c validasi `aktif` dari baris terkunci tanpa kunci ulang — urutan galat unit→sasaran→renstra→regulasi + pesan/audit sukses `indikator.buat` tak berubah; null = lewati) + `UpdateIndikator` (2c `sharedLock` regulasi tujuan SEBELUM `lockForUpdate` indikator — pola lock-dulu-validasi-kemudian 4b dipertahankan; deny+null→abaikan/403 R2-17 utuh) + `PindahUnitIndikator` (hanya komentar dokumentasi: `regulasi_id` tak dibaca/ditulis → tanpa kunci Regulasi sama sekali → tanpa jalur `Indikator→Regulasi`) + `RegulasiService::delete`/`referensiAktifTerkunci` TANPA perubahan (sudah `Regulasi(X)→Renstra refs(X)→Indikator refs(X)`, selaras aturan; bukan refactor) + `DestroyIndikator` ikut dicek (arsip: `Indikator(X)→Sasaran(S)`, tanpa kunci Regulasi → tanpa perubahan); urutan lock final — Store: `Regulasi(S)→Unit(S)→Sasaran(S)→Renstra(S)→INSERT`; Update: `Regulasi(S)→Indikator(X)→Sasaran(S)`; PindahUnit: `Indikator(X)→Unit(S pair terurut)→Sasaran(S)`; Delete-Regulasi: `Regulasi(X)→Renstra refs(X)→Indikator refs(X)`; test `tests/Feature/Perencanaan/RegulasiIndikatorLockOrderTest.php` 3 passed/24 assertions (update-taut regulasi kedua sukses+diaudit; hapus regulasi berujuk aktif ditolak tanpa mutasi parsial; dua-koneksi sekuensial: sisi-hapus pegang Regulasi X → sisi-ubah minta Regulasi S → menunggu 55P03 bukan 40P01 → sisi-hapus lanjut kunci Indikator X karena sisi-ubah tak pernah pegang; 2 ronde NOWAIT holder-sehat buktikan kedua arah saling-tunggu urutan lama; outcome deterministik + `indikator.ubah`/`regulasi.hapus*` nihil); batas bukti: sekuensial satu-proses (bukan 2-proses paralel ala `AccountConcurrencyTest::race`), tanpa klaim 40P01 end-to-end; temuan tengah jalan: (a) sonde gabungan INVALID — holder yang transaksinya sudah abort tak lagi menahan waiter pada stack PDO pgsql/PG17 (dibuktikan 5 probe mandiri dua-PDO) → dipecah dua ronde holder-sehat; (b) teardown `DatabaseMigrations` rollback menabrak down() lifecycle pre-existing (menolak baris pasca-cutover tanpa backup) → override `runDatabaseMigrations` teardown `migrate:fresh` + guard disposable (preseden `AccountConcurrencyTest`); verifikasi: `php vendor/bin/pint --test` 4 file passed; `phpstan` 0 errors; `SasaranIndikatorTest` 50 passed/279 assertions + `StaleTest` 2/2 + `RegulasiFeatureTest` 17/17 hijau di PG disposable podman `postgres:17-alpine` port 5447 (DB/user `sakip_test`, container `sakip_test_r221b` milik sesi ini, dihapus setelah run; dev `sakip_db:5433` + container sesi lain `sakip_test_r221:5446` tak tersentuh). Catatan: 2c `UpdateIndikator` + kerangka test sudah ada tak-tercommit di worktree saat sesi mulai (sesi paralel aktif); diverifikasi, diperbaiki (sonde + teardown), dilengkapi (Store/PindahUnit/cek-Regulasi/gates) di sini. HEAD `326de48`. Belum di-commit.

```text
Prompt handoff R2-21:
Kerjakan R2-21 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Samakan lock order + test
2-koneksi sesuai detail task. Update checkbox + Bukti. JANGAN commit.
```

### R2-22 · [MAJOR] Token stale wajib (bukan opsional)
- [x] Status: SELESAI (2026-10-01) — belum di-commit
- Untuk apa: tanpa token, direct caller menonaktifkan proteksi
  (R2-19 nullable = bypass permanen).
- Yang dibuat:
  1. `expected_updated_at`: `nullable` → `required|date` di
     UpdateSasaran + UpdateIndikator Request (frontend sudah kirim).
  2. Perbarui SEMUA test PUT existing yang tanpa token (tambah token
     fresh dari model) — ini bagian terbesar task; JANGAN hapus
     asersi lain.
  3. Regression test: stale payload TANPA token → ditolak (409) +
     tanpa overwrite + tanpa audit sukses (Sasaran + Indikator).
- DoD: tidak ada PUT-test tanpa token tersisa; suite hijau;
  `typecheck` + FE test hijau; `pint`+`phpstan` hijau.
- Selesai: 2026-10-01 | Bukti: `UpdateSasaranRequest`/`UpdateIndikatorRequest` (`required|date` + pesan `Timestamp versi wajib disertakan. Muat ulang halaman...`; logika banding-ISO + 409 `konflik` di Action R2-19 tak berubah, cabang null-skip dipertahankan sebagai defense-in-depth untuk pemanggil Action langsung); frontend NIHIL diubah (kedua modal sudah kirim `expected_updated_at` dari `updated_at` model; tipe `updated_at?` sudah ada); 19 PUT di `SasaranIndikatorTest.php` + 1 di `RegulasiIndikatorLockOrderTest.php` + 4 deny-path di `PolicyAlasanSanitasiTest.php` (403 via Policy sebelum validasi, token ditambah agar DoD literal terpenuhi) semuanya kini kirim token fresh (`fresh()->updated_at?->toISOString() ?? fresh()->created_at`); asersi lain NIHIL diubah; 2 regression baru di `SasaranIndikatorStaleTest.php` (T1 dibaca → mutasi lain jadi T2 → kirim TANPA token → web 302 `expected_updated_at` + JSON 422 `expected_updated_at`, tanpa overwrite, `*.ubah` tepat 1); verifikasi: `bun run typecheck` hijau; `php vendor/bin/pint --test` 6 file passed; `phpstan` 0 errors; `bun run test` 26 file/145 test hijau; Pest di PG disposable podman `postgres:17-alpine` port 5450 (DB/user `sakip_test`, fresh migrate via `RefreshDatabase`/`DatabaseMigrations`; dev `sakip_db:5433` + container sesi lain `sakip_test_r221:5446` tak tersentuh; container `sakip_test_r222` dihapus): StaleTest 4 passed/40 assertions (2 existing + 2 baru), SasaranIndikatorTest 50 passed/279 assertions, PolicyAlasanSanitasi 13 + LockOrder 3 = 16 passed/57 assertions. HEAD `326de48`. Belum di-commit.

```text
Prompt handoff R2-22:
Kerjakan R2-22 dari document/PR-42-Review2-Tracking.md (setelah
R2-21) di branch feature/iss-02-04-sasaran-indikator. Wajibkan token
+ perbarui test + regression sesuai detail task. Update checkbox +
Bukti. JANGAN commit.
```

### R2-23 · [NEW] Token versi monotonik (mikrodetik)
- [x] Status: SELESAI (2026-10-01) — belum di-commit
- Untuk apa: `timestamps()` presisi detik + tanpa kenaikan monotonik —
  2 save sedetik lolos guard usang. Preseden repo: `JenisBerkas`
  (`timestamp(6)` + `$dateFormat Y-m-d H:i:s.u` + monotonik).
- Yang dibuat:
  1. Migrasi BARU: `sasaran_strategis` + `indikator_kinerjas`
     `updated_at` (dan `created_at` bila satu paket) → timestamp(6);
     daftar beku lokal, tanpa import App.
  2. Kedua model: `$dateFormat = 'Y-m-d H:i:s.u'` + pastikan tiap
     update menaikkan nilai (bump bila sama).
  3. Compare `expected_updated_at` tetap (frontend tak berubah);
     tambah test: 2 save sedetik → kedua 409 + data pertama utuh.
- DoD: test hijau; `pint`+`phpstan` hijau.
- Selesai: 2026-10-01 | Bukti: migrasi BARU `2026_10_01_000001_upgrade_sasaran_indikator_timestamps_precision` (tanpa ubah migrasi lama; `TABLES`/`COLUMNS` beku lokal; hanya `Migration`/`DB` facade, tanpa import `App`; up: 4 kolom → `timestamp(6)` + backfill NULL + default `CURRENT_TIMESTAMP`; down: drop default + kembali `timestamp(0)`) + `SasaranStrategis`/`IndikatorKinerja` (`$dateFormat Y-m-d H:i:s.u` + hook `saving` monotonik pola `JenisBerkas:47-61` — `Carbon::now()`, bila `<=` original `updated_at` maka `+1µs`; `saving` mengalahkan `updateTimestamps` karena kolom sudah dirty; guard `updating` existing dipertahankan) + compare `expected_updated_at` di `UpdateSasaran`/`UpdateIndikator` NIHIL diubah (ISO-compare R2-19; `toISOString` terbukti 6 digit sehingga bump 1µs terdeteksi) + frontend NIHIL diubah + 2 test baru di `SasaranIndikatorStaleTest.php` (waktu dibekukan `Carbon::setTestNow` + `try/finally`: save A 302 + `updated_at` bergeser dari token → save B web 302 `konflik` + JSON 409 `konflik` + data A utuh + `*.ubah` tepat 1; sasaran + indikator); verifikasi: `php vendor/bin/pint --test` 4 file passed; `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress --memory-limit=1G` 0 errors; `bun run typecheck` hijau (tanpa perubahan kontrak); Pest di PG disposable podman `postgres:17-alpine` port 5453 (DB/user `sakip_test`, fresh migrate via `RefreshDatabase`; dev `sakip_db:5433` tak tersentuh; container `sakip_test_r223` dihapus): StaleTest 6 passed/62 assertions (4 existing + 2 baru), SasaranIndikatorTest 54 passed/301 assertions (regresi nihil vs baseline R2-25); kolom `timestamp(6)` terkonfirmasi via `information_schema` + round-trip down(→0)/up(→6) manual terbukti di DB disposable. HEAD `326de48`. Belum di-commit.

```text
Prompt handoff R2-23:
Kerjakan R2-23 dari document/PR-42-Review2-Tracking.md (setelah
R2-25) di branch feature/iss-02-04-sasaran-indikator. Monotonik-kan
token sesuai detail task + test. Update checkbox + Bukti. JANGAN commit.
```

### R2-24 · [NEW] Grandfather regulasi nonaktif tak berubah
- [x] Status: SELESAI (2026-10-01) — belum di-commit
- Untuk apa: regulasi lama yang dinonaktifkan belakangan membuat edit
  nama/satuan wajib melepas rujukan historis (pola unit di
  `RenstraMutationRequest:74-81` justru mengizinkan bertahan).
- Yang dibuat: di UpdateIndikator, bila `regulasi_id` SAMA dengan
  nilai terkunci → lewati syarat `aktif` (pertahankan); syarat aktif
  hanya untuk regulasi BARU yang ditautkan. Test: edit nama dengan
  regulasi lama nonaktif → 302 sukses + rujukan utuh; ganti ke
  regulasi nonaktif lain → 422.
- DoD: 2 test hijau; `pint`+`phpstan` hijau.
- Selesai: 2026-10-01 | Bukti: `UpdateIndikator` 4b — `! $targetRegulasiTerkunci` tetap 422 (baris hilang), `$regulasiTidakBerubah = (string) raw === (string) locked` lewati cek `aktif`, regulasi BARU nonaktif tetap 422 (pesan `Rujukan regulasi tidak valid atau sudah nonaktif.` + audit `indikator.ubah`/`ubah_ditolak` tak berubah; lock `Regulasi(S)` 2c R2-21 dipertahankan) + `UpdateIndikatorRequest` grandfather pola `RenstraMutationRequest:74-81` (`aktif=true OR id=current`, null → aktif-only) — DEVIASI dari instruksi "HANYA Action": tanpa ini validasi request menolak 422 sebelum Action (dibuktikan: `Rule::exists→where aktif` dievaluasi pra-transaksi); test baru `test_r224_regulasi_lama_nonaktif_tetap_dipertahankan_saat_edit_nama` (nonaktif via DB → edit nama same-id → 302 + `assertSessionHasNoErrors` + nama baru + regulasi_id utuh) + `test_r224_ganti_ke_regulasi_nonaktif_lain_ditolak_422` (ganti ke nonaktif LAIN → 422 + rujukan+nama lama utuh); verifikasi: `php vendor/bin/pint --test` 3 file passed; `phpstan` 0 errors; Pest `SasaranIndikatorTest` 52 passed/287 assertions (50 existing + 2 baru) + LockOrder/Stale/Policy 20 passed/97 assertions di PG disposable podman `postgres:17-alpine` port 5451 (DB/user `sakip_test`, fresh migrate via `RefreshDatabase`; dev `sakip_db:5433` + container sesi lain `sakip_test_r221:5446` tak tersentuh; container `sakip_test_r224` dihapus setelah run). Belum di-commit.

```text
Prompt handoff R2-24:
Kerjakan R2-24 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Grandfather regulasi sesuai
detail task + test. Update checkbox + Bukti. JANGAN commit.
```

### R2-25 · [NEW] Validasi komponen saat ubah tipe perhitungan
- [x] Status: SELESAI (2026-10-01) — belum di-commit
- Untuk apa: ubah tipe tanpa cek komponen langgar Data Model:789-792
  (rasio→manual dsb. = master inkonsisten, snapshot berikutnya rusak).
- Yang dibuat: di UpdateIndikator, validasi kandidat
  `tipe_perhitungan` + komponen aktif existing via
   `IndikatorPerhitunganService::validateDefinisiKomponen`
   (bangun state kandidat tanpa mutasi) secara atomik dalam
   transaksi; gagal → 422 + messages. Test: rasio berkomponen →
   manual ditolak; ke penjumlahan valid → lolos.
- DoD: test hijau; `pint`+`phpstan` hijau.
- Selesai: 2026-10-01 | Bukti: `UpdateIndikator` 4c — bila `tipe_perhitungan` SAMA dengan baris terkunci dilewati tanpa query tambahan; bila BERUBAH, state kandidat dibangun via `clone` + `setRelation('komponen', komponen()->get())` (baris terkunci tak termutasi) lalu dinilai `IndikatorPerhitunganService::validateDefinisiKomponen` atomik dalam transaksi setelah kunci sebelum `update`; gagal → `ValidationException` `tipe_perhitungan` + messages service (422 web); lock R2-21, grandfather R2-24, pesan/audit sukses `indikator.ubah`/`ubah_ditolak` tak berubah; test baru `test_r225_ubah_tipe_rasio_berkomponen_ke_manual_ditolak_422` (rasio valid pembilang+penyebut aktif → manual → 422 `tipe_perhitungan` + tipe/nama/peran/aktif utuh + `indikator.ubah` nihil) + `test_r225_ubah_tipe_rasio_tanpa_komponen_ke_manual_lolos` (rasio tanpa komponen → manual → 302 + tipe/nama baru + `indikator.ubah` tercatat); verifikasi: `php vendor/bin/pint --test` 2 file passed; `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress --memory-limit=1G` 0 errors; Pest `SasaranIndikatorTest` 54 passed/301 assertions (52 existing + 2 baru) + LockOrder/Stale/Policy 20 passed/97 assertions di PG disposable podman `postgres:17-alpine` port 5452 (DB/user `sakip_test`, fresh migrate via `RefreshDatabase`; dev `sakip_db:5433` + container sesi lain `sakip_test_r221:5446` tak tersentuh; container `sakip_test_r225` dihapus setelah run). Belum di-commit.

```text
Prompt handoff R2-25:
Kerjakan R2-25 dari document/PR-42-Review2-Tracking.md (setelah
R2-24) di branch feature/iss-02-04-sasaran-indikator. Validasi tipe
vs komponen sesuai detail task + test. Update checkbox + Bukti.
JANGAN commit.
```
