# PR-42 — Tracking Review Putaran 8 (Codex, HEAD `ec54c31`)

Sumber: review Codex 3 temuan (gate tombol vs final-set; unique
vs kandidat final; alasan terpotong di audit child) terhadap HEAD
`ec54c31`. Status awal putaran (main session, terverifikasi baca
source): KETIGA masih terbuka.

> Aturan update: session pelaksana ubah `[ ]` → `[x]` + isi Bukti.
> Main session verifikasi lalu update `document/TASK-REGISTRY.md`.
> Jangan pindah branch. Jangan commit/push tanpa perintah eksplisit.
> Jangan sentuh: `tasks/`, `.agents/`, `.claude/`, `*.json` tooling,
> `composer.json/lock`. DB dev (`sakip_db:5433`) tidak boleh disentuh;
> test backend hanya di PostgreSQL disposable baru
> (lihat `document/PR-42-CI-Test-Context.md` §3).

## Urutan eksekusi

Batch 1 paralel (disjoint): R8-01 + R8-02. Lalu R8-03 (menyentuh
Request + Action yang sama dengan R8-02).

Kontrak yang TIDAK berubah di putaran ini:

- Data Model §2.12/§2.27 + Plan §2.15, ADR 0001–0003, Standards §1
  (tanpa nomor issue di source), §2, §3, §4, §5, §7, §9, §11, §12,
  `CONTEXT.md`, `document/design-system.md`.
- Final-gate komposisi, lock-order, re-auth granular + read (R7-04),
  stale-token (R7-01), alasan required (R7-03), bump monotonik,
  syntax tunggal tetap. R8 hanya parity/presisi, bukan relaksasi.

### R8-01 · Parity gate tombol dengan izin final-set (backend tipis + frontend)

- [x] Status: done (paralel dengan R8-02)
- Untuk apa: tombol mensyaratkan `komponen_create` selalu, padahal
  API mensyaratkan read selalu + create/update sesuai delta —
  update-tanpa-create kehilangan tombol yang sah; create-tanpa-read
  melihat tombol yang tak bisa dipakai.
- Yang dibuat:
  1. `IndexSasaranIndikator.php`: tambah capability yang kurang
     (`komponen_read` sudah ada; tambah `komponen_update` —
     verifikasi nama konstanta di katalog; JANGAN ubah field lain).
  2. `types`: field opsional baru mengikuti pola existing.
  3. `Index.tsx`: gate tombol Atur Formula menjadi
     `indikator_update && komponen_read && (komponen_create ||
     komponen_update)` (atau bentuk setara yang didokumentasikan;
     tombol lain JANGAN diubah). Matriks parity gate-vs-API tulis
     di Bukti (kasus: update-only, create-only-tanpa-read,
     read-only, full).
  4. Test: FE (4 kombinasi menampilkan/menyembunyikan sesuai
     matriks) + SATU asersi backend payload `can`. `bun run
     typecheck` + test hijau; tanpa token baru, tanpa raw hex,
     tanpa `dark:*`.
- DILARANG: mengubah authorization backend, kontrak endpoint,
  perilaku tombol lain.
- DoD: setiap kombinasi izin menampilkan tepat tombol yang API-nya
  bisa dipakai (deny-at-submit tetap backend sebagai jaring akhir).
- Selesai: 2026-10-03 | Bukti: backend `IndexSasaranIndikator.php` tambah `komponen_update` via `resolve('komponen:update')` (katalog `PermissionCatalog::ACTIONS['komponen']` memuat `create,read,update,delete`; `PermissionCodes::KOMPONEN_READ` sudah ada, tidak ada konstanta CREATE/UPDATE sehingga pola string mentah dipertahankan seperti `komponen_create` existing; field lain utuh); `types/sasaran-indikator.ts` tambah `komponen_update?: boolean` mengikuti pola opsional existing; `Index.tsx` tambah `canAturFormula = can.indikator_update && can.komponen_read && (can.komponen_create || can.komponen_update)` + komentar parity, gate tombol Atur Formula `can.indikator_update && can.komponen_create` → `canAturFormula`, tombol lain utuh; tanpa token baru, tanpa raw hex, tanpa `dark:*` (grep nihil); Standards §2 (props `can.*` eksplisit), §3 (UI hanya baca `can.*`, deny-at-submit backend `ChangeIndicatorFormula`), §9 (tipe eksplisit, label/aria utuh). Matriks parity gate-vs-API (`ChangeIndicatorFormula`: `indikator:update` selalu + `komponen:read` selalu + `create/update` sesuai delta): | update-only (`indikator_update` T, `read` T, `create` F, `update` T) → TAMPIL / API pakai (edit tanpa tambah) → OK | create-tanpa-read (T,F,T,F) → SEMBUNYI / API 403 read-required → OK (sebelumnya tampil-tak-terpakai) | read-only (T,T,F,F) → SEMBUNYI / API butuh create/update untuk delta normal → OK | full (T,T,T,T) → TAMPIL / API pakai → OK |. Test: FE `FormulaTransisi.test.tsx` `fullCan` + `komponen_update:true`, 4 `it` R8-01 parity sesuai matriks + `indikator_update:false` sembunyi tetap; backend baru `tests/Feature/Perencanaan/SasaranIndikatorKomponenUpdateCapabilityTest.php` 1 asersi `can.komponen_update=true` (preset perencanaan). Gate: `bun run typecheck` PASS; `bun run test -- FormulaTransisi` 28 passed; `pint --test` PASS (full); `phpstan` 0 errors (full); Pest di PG disposable podman `postgres:17-alpine` `sakip_test_r801:5547` — baru 1 passed/9 assertions + regresi `CreateCapability+FormulaMutationRegression` 34 passed/227 assertions (dev `sakip_db:5433` tak tersentuh; container dihapus). Belum di-commit.

```text
Prompt handoff R8-01:
Kerjakan R8-01 dari document/PR-42-Review8-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Selaraskan gate tombol Atur
Formula dengan izin final-set sesuai detail task; HANYA sentuh
app/Actions/Perencanaan/IndexSasaranIndikator.php, resources/js
(Index.tsx, types), tests/Frontend/FormulaTransisi.test.tsx, dan 1
test backend kecil. Ikuti skill inertia-react-development +
tailwindcss-development + Standards §2,§3,§9 + design-system. Update
checkbox R8-01 + Bukti + matriks parity (HANYA seksi R8-01). JANGAN
commit. JANGAN sentuh file R8-02/R8-03, routes/, migrasi, composer,
tasks/, .agents/, .claude/, *.json tooling. Test backend hanya di
PG disposable baru (dev 5433 utuh; hapus container).
```

### R8-02 · Tunda cek unique hingga kandidat final (backend only)

- [x] Status: done (paralel dengan R8-01)
- Untuk apa: `Rule::unique` vs state DB lama menolak swap atomik
  yang valid (existing n→x + baru n), padahal final-set unik dan
  persist sudah pakai kode-sementara.
- Yang dibuat (HANYA `app/` + test backend; JANGAN sentuh
  `resources/js`, `routes/`, migrasi):
  1. `KomponenMutationService`: PISAHKAN validasi sintaks kandidat
     (tanpa cek unique-vs-DB-lama) dari pemeriksaan unique.
     `modelKandidat`/jalur kandidat TIDAK boleh menolak karena row
     existing masih berkode lama.
  2. Unique ditegakkan pada: duplikat dalam final-set (sudah ada —
     pertahankan) + constraint DB saat persist (sudah ada via
     kode-sementara + pemetaan pesan — pertahankan). `Rule::unique`
     pada Request untuk payload formula: longgarkan agar tidak
     menilai terhadap state lama (tetap tolak duplikat DALAM
     payload via `distinct`; error key DIPERTAHANKAN). Request
     store normal (single-row, tanpa swap) boleh mempertahankan
     unique-vs-DB (catat bila dipertahankan — itu bukan swap).
  3. Test: swap n→x + baru n via PATCH → sukses + final unik;
     duplikat final-set tetap 422; duplikat-vs-DB di luar swap
     tetap ditolak (sesuai keputusan (2)). JANGAN ubah test lain
     kecuali bergantung perilaku lama (catat).
- DoD: penggantian kode atomik yang valid tidak 422; tidak ada
  jalur `buat()` lolos sintaks; duplikat nyata tetap tertolak.
- Verifikasi: pint + phpstan 0 errors; Pest contract/syntax +
  regresi modul hijau di PG disposable (dev utuh; hapus container).
- Selesai: 2026-10-03 | Bukti: Keputusan Request normal: `StoreIndikatorKomponenRequest` PERTAHANKAN unique-vs-DB (single-row tanpa swap, bukan swap — tak diubah); `ChangeIndicatorFormulaRequest` sudah tanpa unique (hanya `distinct` dalam payload — tak diubah). File diubah: `app/Services/Kinerja/KomponenMutationService.php` (BARU `aturanSintaksItem` syntax-murni tanpa DB + `validasiSintaksKandidat`; `aturanItem` tetap syntax+unique untuk store normal; `aturanBersarang` kini syntax-only kandidat; `modelKandidat` pakai kandidat sehingga tak menolak karena row existing masih berkode lama; `buat` tetap validasi penuh sehingga tak ada jalur lolos sintaks; unique final-set di Action + kode-sementara/constraint-DB dipertahankan) + `tests/Feature/IndikatorKomponen/FormulaSwapKodeBaruTest.php` (baru 3/3: swap n→x + baru n via PATCH sukses + final unik; duplikat final-set 422 `kode` tanpa mutasi; POST duplikat-vs-DB 422 `kode` pesan utuh). Error key (`kode` flat, `komponen.N.field`) dan kontrak endpoint DIPERTAHANKAN; tanpa ubah test lain. Verifikasi: `pint --test` PASS (file tersentuh); `phpstan` 0 errors (full); Pest PG disposable baru 127.0.0.1:5546 (`postgres:17-alpine`, DB/user `sakip_test`) — baru 3/3 (16 asersi), `IndikatorKomponen/` 74/74 (526), `SasaranIndikatorTest`+`Authorization`+`Renstra` 305/305 (2789); dev `sakip_db:5433` utuh (Up, tak dipakai test); container `sakip_test_r802` dihapus (`podman ps` nihil). Tanpa commit.

```text
Prompt handoff R8-02:
Kerjakan R8-02 dari document/PR-42-Review8-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Pisahkan sintaks kandidat dari
unique-vs-DB-lama sesuai detail task; HANYA sentuh
app/Services/Kinerja/KomponenMutationService.php,
app/Http/Requests/Indikator/ChangeIndicatorFormulaRequest.php
(+ StoreIndikatorKomponenRequest bila perlu, catat), dan test
backend. Ikuti skill laravel-best-practices +
testing-best-practices + Standards §1,§2,§4,§5,§11. Error key dan
kontrak endpoint DIPERTAHANKAN. Update checkbox R8-02 + Bukti
(HANYA seksi R8-02). JANGAN commit. JANGAN sentuh resources/js,
routes/, migrasi, composer, tasks/, .agents/, .claude/, *.json
tooling, file R8-01/R8-03. Test backend hanya di PG disposable baru
(dev 5433 utuh; hapus container).
```

### R8-03 · Rationale utuh di audit child (backend + FormulaModal)

- [x] Status: done (jalan SETELAH R8-01+R8-02 selesai)
- Untuk apa: alasan 1000 karakter + prefiks konteks lalu dipangkas
  `AlasanAudit::BATAS_MAKS` (1000) → ekor rationale hilang di audit
  child; induk menyimpan bentuk berbeda. Mekanisme terkonfirmasi:
  `AuditReason::sanitize` tak memangkas (batas milik pemanggil);
  pemangkasnya `AlasanAudit::sanitasi` via `AuditLogger`.
- Yang dibuat (pilih SATU, terkecil yang menjamin utuh; catat):
  (a) rationale sebagai alasan UTAMA audit child (konteks sebagai
  field/suffix terpisah atau dihilangkan bila redundan dengan
  `nilaiBaru`/tipe event), atau (b) turunkan `max` input sebesar
  panjang prefiks terpanjang (variabel kode/label → hitung batas
  aman konservatif + pesan max eksplisit). DILARANG menaikkan
  `BATAS_MAKS` global atau mengubah sanitasi dipakai domain lain.
  `FormulaModal.tsx`: samakan batas klien + pesan; tanpa token
  baru, tanpa raw hex, tanpa `dark:*`.
- Test: alasan tepat-batas → audit child memuat SELURUH rationale
  (assert suffix/ekor); atas-batas → 422 pra-mutasi. `bun run
  typecheck` + test hijau.
- DoD: rationale operator batas-maksimal dapat dibaca utuh dari
  audit child; induk konsisten (tidak dua versi fakta).
- Selesai: 2026-10-03 | Bukti: Opsi (a): rationale sebagai alasan UTAMA audit child. Prefiks konteks (kode/label variabel, terpanjang ±371 char pada `komponen.buat`) + alasan 1000 char selalu memicu `AlasanAudit::sanitasi` via `AuditLogger` → ekor rationale hilang; konteks tersebut redundan karena `tindakan` + `objek_id` + snapshot `nilai_baru` (`formatAuditSnapshot` memuat kode/label/peran) tetap telusur. Opsi (b) ditolak: memangkas kapasitas operator 1000→±630 dengan batas konservatif atas panjang variabel + menambah pesan/validasi klien-server. `BATAS_MAKS` global dan sanitasi domain lain tak tersentuh; Request (`max:1000`) tak diubah; induk `indikator.ubah` utuh (jalur non-`komponen.*` tanpa pemangkas, tetap memuat rationale penuh → konsisten satu fakta). File diubah: `app/Actions/Perencanaan/ChangeIndicatorFormula.php` (2 audit child `komponen.buat`/`komponen.ubah` kini `alasan: $alasan` + komentar + docblock; induk/audit lain/lock/token/guard utuh) + BARU `tests/Feature/IndikatorKomponen/FormulaRationaleUtuhTest.php` (2/2: tepat-1000 char `…-EKOR-UTUH` → `assertSame` child ubah+buat + suffix + induk memuat penuh; 1001 char → 422 `alasan` pra-mutasi tanpa mutasi/audit). `FormulaModal.tsx` tanpa ubah: batas klien 1000 + pesan sudah selaras backend (opsi a mempertahankan 1000); tanpa token baru, raw hex, `dark:*`; tanpa ubah test lain. Gate: `bun run typecheck` PASS; `bun run test -- FormulaTransisi` 28 passed; `pint --test` PASS (file tersentuh); `phpstan` 0 errors (full); Pest PG disposable baru `postgres:17-alpine` `sakip_test_r803:5548` — baru 2/2 (13 asersi), `IndikatorKomponen/` 76/76 (539), `SasaranIndikatorTest`+`Authorization` 187/187 (1886); dev `sakip_db:5433` utuh (Up, tak dipakai test); container dihapus (`podman ps` tinggal keycloak/sakip_db/sakip_app). Tanpa commit.

```text
Prompt handoff R8-03:
Kerjakan R8-03 dari document/PR-42-Review8-Tracking.md di branch
feature/iss-02-04-sasaran-indikator, SETELAH R8-01+R8-02. Jamin
rationale utuh di audit child sesuai detail task (pilih opsi a/b).
Ikuti skill laravel-best-practices + testing-best-practices +
inertia-react-development + tailwindcss-development + Standards
§1,§2,§4,§5,§7,§9,§11. Batas global/sanitasi domain lain DILARANG
diubah. Update checkbox R8-03 + Bukti (HANYA seksi R8-03). JANGAN
commit. JANGAN sentuh file R8-01/R8-02 di luar kebutuhan, routes/,
migrasi, composer, tasks/, .agents/, .claude/, *.json tooling. Test
backend hanya di PG disposable baru (dev 5433 utuh; hapus container).
```

### R8-04 · Catatan non-kode (tanpa aksi)

- [x] Status: closed tanpa kode (main session).
- Tech Debt tetap backlog/runbook seperti sebelumnya.
