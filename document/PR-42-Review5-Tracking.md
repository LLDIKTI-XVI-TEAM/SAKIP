# PR-42 — Tracking Review Putaran 5 (HEAD `95ab43a`)

Sumber: Final re-review Dion PR #42 — ISS-02.04, HEAD
`95ab43a0f36ef41f56cd3698b3171e65b286f0f9`, base
`development@73f22ac856307009c0928d7ec127c42806a19544`.
Verdict: **REQUEST CHANGES — NOT READY FOR MERGE**.
CI exact HEAD: run #36986429994 — **9/9 PASS**.
Backend exact HEAD (CI Ubuntu): **865 passed / 7.508 assertions**.
Mergeable: yes.

> Aturan update: session pelaksana ubah `[ ]` → `[x]` + isi Bukti.
> Main session verifikasi lalu update `document/TASK-REGISTRY.md`.
> Jangan pindah branch. Jangan commit/push tanpa perintah eksplisit.
> Jangan sentuh: `tasks/`, `.agents/`, `.claude/`, `*.json` tooling,
> `composer.json/lock`. DB dev (`sakip_db:5433`) tidak boleh disentuh;
> test backend hanya di PostgreSQL disposable baru
> (lihat `document/PR-42-CI-Test-Context.md` §3).

## Ringkasan review (yang mengikat task ini)

- Blocker formula flow + Major duplikasi create-komponen putaran lalu
  **CLOSED** di HEAD ini. Tidak diulang.
- Disposisi current: **Blocker 0, Major 1, Minor 2**, Tech Debt
  (test monolith BACKLOG-02, controller orchestration, tracking docs,
  rollback runbook — tanpa aksi di putaran ini).
- Scope putaran ini (disuruh user): **Major + Minor 2** di bawah.
  Minor 1 (flow target manual di FormulaModal misleading) TIDAK masuk
  scope putaran ini.

Kontrak yang TIDAK berubah di putaran ini:

- Data Model §2.12/§2.27 + Plan §2.15 (invarian nonmanual), ADR
  0001–0003, Standards §1 (tanpa nomor issue di source), §2, §4, §5,
  §7, §11, §12, `CONTEXT.md`, `document/design-system.md`.
- Kontrak endpoint atomik `PATCH .../formula` tetap (R4).
- Final-gate komposisi tetap
  `IndikatorPerhitunganService::validateDefinisiKomponen`.

## Task handoff (satu session = satu task; R5-01 dan R5-02 paralel aman bila sekat file dipatuhi)

### R5-01 · [MAJOR] Final-state validation pada CRUD komponen normal (backend only)

- [x] Status: done, belum commit
- Untuk apa: tutup lubang invariant — CRUD normal
  (`IndikatorKomponenController@store/update/destroy`) hari ini bisa
  persist komposisi invalid (campur penjumlah ke rasio, ubah peran
  jadi invalid, hapus/nonaktifkan penyebut terakhir) sementara jalur
  atomik menjaganya. R3-01 melarang persisted invalid master; seluruh
  write surface wajib enforce.
- Yang dibuat (HANYA `app/` + test backend; JANGAN sentuh
  `resources/js`, `routes/`, migrasi):
  1. `store`: setelah lock parent + guard manual, muat existing
     components, append kandidat in-memory (via
     `KomponenMutationService::modelKandidat`), set pada clone
     indikator (`setRelation`), nilai
     `validateDefinisiKomponen`; invalid → 422 `tipe_perhitungan` +
     messages service + arahan Atur Formula (tanpa INSERT/audit);
     valid → `buat` + audit seperti semula. Pola kandidat mengikuti
     `UpdateIndikator` §4c / `ChangeIndicatorFormula` (konsistensi
     dulu; JANGAN ciptakan pola validasi ketiga).
  2. `update`: setelah lock parent + lock child + guard manual +
     kepemilikan, clone SET komponen (existing dengan target
     diganti nilai usulan via `modelKandidat`-setara in-memory —
     termasuk perubahan `peran`/`aktif`/`bobot`); nilai final;
     invalid → 422 (tanpa mutasi/audit); valid → update + audit.
  3. `destroy`: SETELAH cek rujukan (urutan pesan/galur existing
     dipertahankan: rujukan tetap ditolak dulu seperti semula),
     kandidat = existing minus target → nilai; invalid → tolak
     dengan redirect error yang mengarahkan ke Atur Formula
     (tanpa delete/audit); valid → delete + audit. Nonaktifkan via
     update (`aktif=false`) tercakup oleh (2) — bukan jalur ini.
  4. Pesan penolakan SERAGAM ketiga jalur: messages service +
     satu kalimat arahan ("Gunakan Atur Formula bila perubahan
     membutuhkan beberapa komponen berubah bersama-sama.").
     Kunci Regulasi tidak tersentuh (jalur ini tak baca/tulis
     regulasi); lock-order parent→child dipertahankan; re-auth,
     audit success/denied, dan urutan galat existing tidak berubah
     selain penolakan baru ini.
  5. Test: TULIS ULANG `KomponenMutationContractTest` yang kini
     menegaskan bug (POST campur lolos lalu validator invalid) →
     POST campur DITOLAK 422 + tanpa baris + tanpa audit; tambah
     update-menjadi-invalid → 422 utuh; destroy-penyebut-terakhir →
     ditolak utuh; destroy-komponen-perusak (membuat valid) →
     lolos; contract POST-vs-PATCH pesan identik dipertahankan
     untuk kasus yang tetap valid di kedua jalur. JANGAN ubah test
     lain kecuali yang terbukti bergantung pada perilaku bug
     (catat tiap perubahan di Bukti).
- Standards: §1, §2, §4, §5, §7, §11. Skill:
  `laravel-best-practices` + `testing-best-practices` (Wajib).
- Verifikasi: `php vendor/bin/pint --test` file tersentuh;
  `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress --memory-limit=1G`
  0 errors; Pest contract + regresi (`SasaranIndikator`,
  `KomponenHttp`, `Stale`, `LockOrder`, `Policy`) hijau di PG
  disposable (dev 5433 utuh; hapus container).
- DoD: tidak ada jalur CRUD normal yang meninggalkan komposisi
  invalid persisted; `validateDefinisiKomponen` menentukan semua
  write surface; pesan seragam + arahan atomik.
- Selesai: 2026-10-02 | Bukti: `IndikatorKomponenController` store (existing + `modelKandidat` → clone `setRelation` → `validateDefinisiKomponen`; invalid 422 `tipe_perhitungan` + messages + arahan, tanpa INSERT/audit) + update (existing dengan target diganti `normalisasiInput` in-memory termasuk peran/aktif/bobot; invalid 422 utuh) + destroy (SETELAH cek rujukan; existing minus target; invalid → redirect `error` berisi messages + arahan, tanpa delete/audit); pesan seragam via konstanta `ARAHAN_ATUR_FORMULA`; lock parent→child, guard manual, kepemilikan, re-auth FormRequest, audit sukses/denied, urutan galat existing dipertahankan; `app/Services/Kinerja/` tanpa perubahan (helper existing cukup). Test: `KomponenMutationContractTest` ditulis ulang 8/8 (campur/tanpa-penyebut kini 422 utuh; baru update-invalid 422 utuh, destroy-penyebut ditolak utuh via flash `error`, destroy-perusak lolos + audit; identik POST-vs-PATCH dipertahankan untuk valid/duplikat/penyebut-nol). Ubah test lain yang terbukti bergantung bug: `IndikatorKomponenHttpTest` (audit-update/delete → penjumlahan 2 penjumlah; snapshot → tambah penyebut; audit-eksak → tambah penyebut; duplikat/race/bobot-nol/manual/403 tak diubah karena FormRequest/auth mendahului) + `SasaranIndikatorTest::test_r226_konkurensi` (loop nonaktif HTTP → model langsung karena transien invalid kini ditolak; maksud serialisasi utuh). Gate: `pint --test` 4 file passed; `phpstan` 0 errors; Pest PG disposable podman `postgres:17-alpine` port 5457 (DB/user `sakip_test`): kontrak 8/8 (64 asersi), regresi `KomponenHttp` 9/9 (41) + `SasaranIndikator` 63/63 (359) + Stale+LockOrder+Policy 24/24 (145) = 96/96 (545 asersi); dev `sakip_db:5433` utuh (masih Up, tak dipakai test); container `sakip_test_r501` dihapus. Tanpa commit. HEAD `95ab43a`, branch `feature/iss-02-04-sasaran-indikator`.

```text
Prompt handoff R5-01:
Kerjakan R5-01 dari document/PR-42-Review5-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Pasang final-state validation
pada store/update/destroy komponen normal + tulis ulang contract
test sesuai detail task; HANYA sentuh
app/Http/Controllers/Indikator/IndikatorKomponenController.php,
app/Services/Kinerja/ (bila perlu helper kandidat), dan test backend
(contract + yang terbukti bergantung bug). Ikuti Standards
§1,§2,§4,§5,§7,§11 + skill laravel-best-practices +
testing-best-practices. Lock-order, re-auth, audit, urutan galat
existing dipertahankan. Update checkbox R5-01 + Bukti (HANYA seksi
R5-01). JANGAN commit. JANGAN sentuh resources/js, routes/,
migrasi, composer, tasks/, .agents/, .claude/, *.json tooling. Test
backend hanya di PG disposable baru (dev 5433 utuh; hapus container).
```

### R5-02 · [MINOR] Gate capability `komponen_create` pada Atur Formula (backend tipis + frontend)

- [x] Status: done (session pelaksana, belum diverifikasi main session)
- Untuk apa: tombol Atur Formula kini hanya gate
  `can.indikator_update`, padahal backend mensyaratkan
  `indikator:update` + `komponen:create` — user tanpa
  `komponen:create` baru ditolak 403 saat submit. Backend sudah
  benar (tanpa bypass); capability frontend harus mencerminkan
  effective permission.
- Yang dibuat:
  1. `app/Actions/Perencanaan/IndexSasaranIndikator.php`: tambah
     `'komponen_create' => $this->resolver->resolve($user,
     PermissionCodes::KOMPONEN_CREATE)->allowed` di payload `can`
     (pola `komponen_read` existing baris ~134; JANGAN ubah field
     lain). Pastikan konstanta permission benar (cek
     `PermissionCodes`/`PermissionCatalog` — kemungkinan
     `KOMPONEN_CREATE`).
  2. `resources/js/types/sasaran-indikator.ts`:
     `komponen_create?: boolean` pada
     `SasaranIndikatorCapabilities` (opsional seperti
     `komponen_read`, agar payload lama tetap aman).
  3. `Index.tsx`: gate tombol Atur Formula menjadi
     `can.indikator_update && can.komponen_create` (tombol
     `Kelola Komponen` dan lainnya JANGAN diubah).
  4. Test: tambah kasus di `tests/Frontend/FormulaTransisi.test.tsx`
     (tombol hidden bila `komponen_create=false` walau
     `indikator_update=true`; tampil bila keduanya true) +
     SATU asersi backend (payload `can` memuat `komponen_create`
     sesuai izin — di file test backend yang paling pas, mis.
     tambah test kecil baru atau perluas yang existing; JANGAN
     refactor test lain). `bun run typecheck` + `bun run test`
     hijau; tanpa token baru, tanpa raw hex, tanpa `dark:*`.
- DILARANG: mengubah authorization backend, kontrak endpoint,
  perilaku tombol lain, atau menambah permission baru.
- Verifikasi: `php vendor/bin/pint --test` (bila sentuh PHP) +
  `phpstan` 0 errors + `bun run typecheck` + `bun run test` hijau;
  Pest backend terkait hijau di PG disposable (bila tambah test
  backend).
- DoD: user `indikator:update` tanpa `komponen:create` tidak melihat
  tombol Atur Formula; backend behavior identik.
- Selesai: 2026-10-02 | Bukti: `IndexSasaranIndikator.php` tambah
  `'komponen_create' => resolve($user, 'komponen:create')->allowed`
  (literal — `PermissionCodes::KOMPONEN_CREATE` tidak ada; pola
  literal sama seperti `ChangeIndicatorFormula.php:84`;
  `komponen:create` terdaftar di `PermissionCatalog::ACTIONS`);
  `sasaran-indikator.ts` tambah `komponen_create?: boolean` opsional;
  `Index.tsx` gate Atur Formula menjadi
  `can.indikator_update && can.komponen_create` (tombol lain utuh);
  `FormulaTransisi.test.tsx` `fullCan` + `komponen_create: true` +
  2 kasus baru (hidden bila false walau update true; tampil bila
  keduanya true); backend baru
  `SasaranIndikatorKomponenCreateCapabilityTest.php` 2 passed /
  22 assertions (default true/true; deny `komponen:create` → false
  dengan `indikator_update` tetap true);
  verifikasi 2026-10-02: `pint --test` 2 file passed,
  `phpstan` 0 errors, `bun run typecheck` hijau,
  `bun run test` 27 file/156 test hijau
  (termasuk `FormulaTransisi` 11 passed),
  Pest regresi `SasaranIndikatorTest.php` 63 passed/363 assertions
  di PG disposable podman `postgres:17-alpine` port 5445
  (DB/user `sakip_test`, fresh migrate via `RefreshDatabase`;
  dev `sakip_db:5433` tak tersentuh; container `sakip_test_r502`
  dihapus); `Index.tsx` nihil raw hex / `dark:*`;
  tanpa token baru, tanpa raw hex, tanpa `dark:*`; backend behavior
  identik (tanpa ubah authorization/kontrak).

```text
Prompt handoff R5-02:
Kerjakan R5-02 dari document/PR-42-Review5-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Tambahkan capability
komponen_create + gate tombol Atur Formula + test sesuai detail task;
HANYA sentuh app/Actions/Perencanaan/IndexSasaranIndikator.php,
resources/js (Index.tsx, types), tests/Frontend/FormulaTransisi.test.tsx,
dan 1 test backend kecil. Ikuti skill inertia-react-development +
tailwindcss-development + Standards §2,§9 + design-system. Update
checkbox R5-02 + Bukti (HANYA seksi R5-02). JANGAN commit. JANGAN
sentuh file R5-01 (KomponenController, KomponenMutationService,
ContractTest), routes/, migrasi, composer, tasks/, .agents/,
.claude/, *.json tooling. Test backend hanya di PG disposable baru
(dev 5433 utuh; hapus container).
```

### R5-03 · Catatan non-kode (tanpa aksi)

- [x] Status: closed tanpa kode (main session).
- Minor 1 (FormulaModal manual misleading) di luar scope putaran ini.
- Test monolith tetap BACKLOG-02. Tracking docs dievaluasi
  pasca-merge. Rollback lifecycle tetap runbook operator.
