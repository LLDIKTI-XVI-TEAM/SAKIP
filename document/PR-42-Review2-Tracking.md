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

### R2-08 · [MINOR] Konsolidasi PermissionResolver + rapikan layer
- [ ] Status: belum (diperluas review putaran 3: +helper +adapter)
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
- [ ] Status: belum
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
- Selesai: — | Bukti: —

```text
Prompt handoff R2-13:
Kerjakan R2-13 dari document/PR-42-Review2-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Sanitasi alasan audit Policy
sesuai detail task; test di file BARU. Update checkbox + Bukti.
JANGAN commit.
```
