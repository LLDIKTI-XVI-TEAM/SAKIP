# PR-42 — Tracking Review Putaran 4 (HEAD `ab8fb24`)

Sumber: Re-review PR #42 — ISS-02.04, HEAD
`ab8fb2473b3612916dd9f7d7cc9dfb862950151c`, base
`development@73f22ac856307009c0928d7ec127c42806a19544`.
Verdict: **REQUEST CHANGES — NOT READY FOR MERGE**.
CI exact HEAD: run #36976768913 — **9/9 PASS**.
Backend exact HEAD (CI Ubuntu): **860 passed / 7.596 assertions**.
Mergeable: yes.

> Aturan update: session pelaksana ubah `[ ]` → `[x]` + isi Bukti.
> Main session verifikasi lalu update `document/TASK-REGISTRY.md`.
> Jangan pindah branch. Jangan commit/push tanpa perintah eksplisit.
> Jangan sentuh: `tasks/`, `.agents/`, `.claude/`, `*.json` tooling,
> `composer.json/lock`. DB dev (`sakip_db:5433`) tidak boleh disentuh;
> test backend hanya di PostgreSQL disposable baru
> (lihat `document/PR-42-CI-Test-Context.md` §3).

## Ringkasan review (yang mengikat task ini)

- Blocker + Major review putaran 3 SUDAH fixed di HEAD ini (lock
  inversion Regulasi↔Indikator, stale-token optional). Tidak diulang.
- R3-01 benar secara domain (nonmanual invalid tidak persisted) tetapi
  menimbulkan Blocker baru: endpoint atomik `PATCH
  /perencanaan/indikator/{id}/formula` ada tanpa consumer UI —
  create nonmanual → 422, edit modal opsi nonmanual disabled, tombol
  `Kelola Komponen` hanya untuk tipe nonmanual. User normal tidak bisa
  membentuk indikator nonmanual tanpa direct HTTP/seeder (Issue #26
  mensyaratkan 3 tipe + AC-F3 nonmanual).
- `ChangeIndicatorFormula` menduplikasi create-komponen lengkap milik
  ISS-02.06 (`IndikatorKomponenController`) — dua mutation surface bisa
  drift. Perlu shared service + contract test + catatan traceability
  bahwa bagian ISS-02.06 ditarik maju karena kebutuhan atomicity.
- Minor scope (44 commits / 84 files / 11k additions) diterima tanpa
  bongkar. Tech debt test monolith (~3,1k baris) tetap BACKLOG-02.

Kontrak yang TIDAK berubah di putaran ini:

- Data Model §2.12/§2.27 + Plan §2.15 (invarian nonmanual), ADR
  0001–0003, Standards §2 (controller tipis → Action; tanpa setengah
  jadi), §4/§5/§7/§11/§12, `CONTEXT.md`, `document/design-system.md`
  (token semantik, tanpa raw hex, tanpa `dark:*`).
- Kontrak endpoint atomik: `PATCH
  /perencanaan/indikator/{indikator}/formula`
  (`perencanaan.indikator.formula`), body
  `{tipe_perhitungan, komponen[], expected_updated_at}`, lock
  Regulasi(S)→Indikator(X)→Sasaran(S), re-auth
  `indikator:update` + `komponen:create`, stale-token fail-closed.
  R4 putaran ini DILARANG mengubah kontrak request/response endpoint
  tersebut (agar R4-01 dan R4-02 tidak konflik).

## Task handoff (satu session = satu task; R4-01 dan R4-02 paralel aman bila sekat file dipatuhi)

### R4-01 · [BLOCKER] User-facing flow transisi formula atomik (frontend only)

- [x] Status: done
- Untuk apa: beri user jalan membentuk indikator nonmanual secara
  valid lewat aplikasi (tanpa direct HTTP/seeder).
- Yang dibuat (HANYA frontend + types + FE test; JANGAN sentuh
  `app/`, `routes/`, migrasi):
  1. `resources/js/Pages/Perencanaan/SasaranIndikator/FormulaModal.tsx`
     BARU (pola `PindahUnitModal.tsx`): prop `indikator` (+
     `updated_at` sebagai stale token) + `onClose`; pilih tipe target
     (`rasio_persen`/`penjumlahan`/`manual`) + daftar komponen dinamis
     (tambah/hapus baris: kode, label, peran, bobot, urutan, satuan,
     aktif; default peran mengikuti tipe seperti Komponen Index);
     validasi klien minimal (kode regex + distinct, label/peran/bobot/
     urutan wajib, penyebut bobot > 0); submit `PATCH
     /perencanaan/indikator/{id}/formula` via Inertia (`useForm`/
     `router`, kirim `expected_updated_at` dari model saat dibuka);
     state loading/error/success pola existing; error server
     (`tipe_perhitungan`, `komponen.*`, `konflik`,
     `expected_updated_at`) tampil apa adanya (`role=alert`);
     sukses → tutup + reset hanya di `onSuccess` (pola pindah-unit).
  2. `Index.tsx`: tombol/aksi per baris `Atur Formula` (ikon
     `Calculator`/`Sigma` yang sudah ada atau sepadan; gate
     `can.indikator_update`; `title` + `aria-label="Atur formula
     indikator ${kode}"`) + state `formulaTarget` + render
     `FormulaModal`. Tombol tampil untuk semua tipe (termasuk
     `manual` — itu jalan keluar dari buntu). `Kelola Komponen`
     dipertahankan apa adanya.
  3. `IndikatorModal.tsx`: helper text edit-manual mengarahkan ke
     aksi `Atur Formula` (bukan "transisi formula atomik" generik);
     selebihnya JANGAN diubah (opsi disabled tetap).
  4. `resources/js/types/sasaran-indikator.ts`: tipe payload
     `FormulaKomponenInput` + `IndikatorTipePerhitungan` reuse
     (tanpa `any` baru).
  5. Styling: token design-system saja (`text-danger`, `info`,
     `warning`, `soft`, `border`); TANPA raw hex, TANPA `dark:*`
     (grep DoD).
- Test (file BARU `tests/Frontend/FormulaTransisi.test.tsx`, pola
  `PindahUnitIndikator.test.tsx`; JANGAN ubah test existing):
  gate tombol (render + aria-label), validasi klien (submit kosong
  tanpa PATCH), submit valid memanggil PATCH dengan
  `{tipe_perhitungan, komponen, expected_updated_at}`, error server
  tampil apa adanya. `bun run typecheck` + `bun run test` hijau.
- DILARANG: mengubah `app/`, `routes/web.php`, kontrak endpoint,
  perilaku `Kelola Komponen`, capability server, atau menambah
  token warna baru.
- Verifikasi: `bun run typecheck`, `bun run test` (file baru +
  regresi), grep `dark:|#[0-9a-fA-F]{3,6}` nihil pada file tersentuh.
- DoD: indikator `manual` bisa menjadi `rasio_persen`/`penjumlahan`
   valid MELAUI UI (pilih tipe + isi komponen → submit → tersimpan);
   error tak-lengkap/konflik tampil jelas; tanpa direct HTTP.
- Selesai: 2026-10-02 | Bukti: Flow transisi formula atomik via UI tegak (frontend only). File tersentuh: BARU `resources/js/Pages/Perencanaan/SasaranIndikator/FormulaModal.tsx` (pola `PindahUnitModal`: `useForm` PATCH `/perencanaan/indikator/{id}/formula` body `{tipe_perhitungan, komponen, expected_updated_at}` dari `updated_at` saat dibuka; tipe target + baris komponen dinamis kode/label/peran/bobot/urutan/satuan/aktif, default peran mengikuti tipe seperti Komponen Index; validasi klien kode regex + distinct, label/peran/bobot/urutan wajib, penyebut bobot > 0; error server `tipe_perhitungan`/`komponen.*`/`konflik`/`expected_updated_at` tampil apa adanya `role=alert`; tutup + reset hanya di `onSuccess`), `Index.tsx` (tombol `Atur Formula` ikon `Sigma` per baris semua tipe, gate `can.indikator_update`, `title` + `aria-label`, state `formulaTarget` + render `FormulaModal`; `Kelola Komponen` utuh), `IndikatorModal.tsx` (helper text edit-manual mengarah ke aksi `Atur Formula`; opsi disabled tetap), `resources/js/types/sasaran-indikator.ts` (`FormulaKomponenInput` + `KomponenPeran` + `FormulaPayload`, reuse `IndikatorTipePerhitungan`, tanpa `any`), BARU `tests/Frontend/FormulaTransisi.test.tsx` (9 test pola pindah-unit: gate tombol, validasi klien tanpa PATCH, PATCH valid, error server). Gate: `bun run typecheck` hijau; `bun run test` 27 file/154 test PASS (9 baru, 0 regresi); grep `dark:|#[0-9a-fA-F]{3,6}` nihil pada file tersentuh; token semantik saja. Kontrak endpoint tak berubah; `app/`/`routes/` tak tersentuh. HEAD `ab8fb24`, branch `feature/iss-02-04-sasaran-indikator`, tanpa commit.

```text
Prompt handoff R4-01:
Kerjakan R4-01 dari document/PR-42-Review4-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Buat FormulaModal + tombol Atur
Formula + types + FE test baru sesuai detail task; HANYA sentuh
resources/js (Index.tsx, IndikatorModal.tsx helper text,
FormulaModal.tsx baru, types) + tests/Frontend/FormulaTransisi.test.tsx.
Ikuti skill inertia-react-development + tailwindcss-development +
Standards §9 + design-system (tanpa raw hex, tanpa dark:*). Update
checkbox + Bukti. JANGAN commit. JANGAN sentuh app/, routes/,
migrasi, composer, tasks/, .agents/, .claude/, *.json tooling.
```

### R4-02 · [MAJOR] Shared component-mutation service (backend only)

- [x] Status: done
- Untuk apa: satukan dua mutation surface create-komponen
  (`ChangeIndicatorFormula` atomik milik ISS-02.04 + store normal
  `IndikatorKomponenController` milik ISS-02.06) agar invariant tidak
  drift saat rule ISS-02.06 berubah.
- Yang dibuat (HANYA `app/` + test backend; JANGAN sentuh
  `resources/js`, `routes/web.php` — kontrak endpoint tetap):
  1. Service domain BARU mis.
     `app/Services/Kinerja/KomponenMutationService.php` (nama boleh
     menyesuaikan konvensi `Services/Kinerja` existing): SATU fungsi
     create-komponen tervalidasi dipakai kedua jalur — normalisasi
     (trim kode/label/satuan, default aktif true) + aturan sintaks
     per-item (kode regex + max, label max, peran in, bobot
     numeric/decimal 0,12/min/max, urutan int, penyebut bobot > 0;
     cermin `ChangeIndicatorFormulaRequest` + `StoreIndikatorKomponenRequest`)
     + `validateDefinisiKomponen` tetap satu-satunya penentu akhir
     di Action (JANGAN pindahkan final-gate ke service bila itu
     mengubah arsitektur; service = sintaks + normalisasi +
     audit-snapshot + unique-map, bukan final-gate) + snapshot audit
     bobot-eksak-string (gantikan duplikat `formatAuditSnapshot` di
     kedua file dengan delegasi) + pemeta `QueryException` unique →
     pesan `kode` (gantikan duplikat `isUniqueConstraintViolation`).
  2. `ChangeIndicatorFormula` + `IndikatorKomponenController@store`
     delegasi ke service (perilaku, pesan, audit, lock-order,
     re-auth, stale-token identik; tanpa ubah kontrak request/
     response). `update`/`destroy` komponen DILARANG disentuh
     (di luar kebutuhan atomic-create; tetap milik ISS-02.06).
  3. Komentar traceability di service + kedua pemanggil: bagian
     ISS-02.06 (Plan §2.14) sengaja ditarik maju karena kebutuhan
     atomicity R3-01/Opsi A; delen keduanya memakai invariant sama
     (`IndikatorPerhitunganService::validateDefinisiKomponen`).
     Tanpa nomor issue/PR di komentar source (Standards §1 —
     rationale domain saja; traceability rinci di sini).
  4. Contract test backend BARU (mis.
     `tests/Feature/IndikatorKomponen/KomponenMutationContractTest.php`,
     pola fixture disposable existing): payload SAMA via (a) POST
     normal + (b) PATCH formula → pesan validasi identik untuk tiap
     kasus invalid (kode duplikat, peran campur, penyebut bobot 0,
     tanpa penyebut) + bentuk persisted + audit `komponen.buat`
     identik untuk kasus valid. JANGAN ubah test existing.
- Standards: §1 (tanpa nomor issue di source), §2 (Action→Service),
  §4, §5, §7, §11. Skill: `laravel-best-practices` +
  `testing-best-practices` (Wajib sebelum tulis test).
- Verifikasi: `php vendor/bin/pint --test` file tersentuh;
  `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress --memory-limit=1G`
  0 errors; Pest contract baru + regresi
  (`SasaranIndikator`, `KomponenHttp`, `Stale`, `LockOrder`,
  `Policy`) hijau di PG disposable (dev 5433 utuh; hapus container).
- DoD: satu implementasi sintaks/normalisasi/audit/unique untuk
  create-komponen; kedua jalur hijau + pesan identik; tanpa perubahan
  kontrak endpoint; grep `formatAuditSnapshot` + duplikat unique-map
  tinggal SATU definisi (di service).
- Selesai: 2026-10-02 | Bukti: Service BARU `app/Services/Kinerja/KomponenMutationService.php` (normalisasi trim + aktif-default, penyebut-bobot>0, `buat`/`modelKandidat`/`atributCreate`, `formatAuditSnapshot`, `isUniqueConstraintViolation` + pesan tunggal); delegasi `app/Actions/Perencanaan/ChangeIndicatorFormula.php` (kandidat + create + audit + unique via service, lock/re-auth/stale-token utuh) + `app/Http/Controllers/Indikator/IndikatorKomponenController.php` (store via `buat`; update/destroy hanya ganti snapshot/unique ke service, logika utuh); contract BARU `tests/Feature/IndikatorKomponen/KomponenMutationContractTest.php` 5/5 (45 asersi: valid persisted+audit identik, duplikat, penyebut-nol, campur, tanpa-penyebut). Kontrak PATCH formula tak berubah (routes/js/migrasi/composer untouched). Gate: `pint --test` PASS full; `phpstan` 0 errors full; Pest PG disposable baru container `sakip_test_r402` port 5456 — kontrak 5/5, `SasaranIndikatorTest` 63/63 (363), `KomponenHttp` 9/9 (41), Stale+LockOrder+Policy+Fixture+Model 30/30 (193); dev `sakip_db:5433` utuh (masih running, tak dipakai test); container dihapus pasca-run. Grep DoD: `function formatAuditSnapshot` + `function isUniqueConstraintViolation` tinggal 1 definisi tiapnya di service; pemanggil hanya delegasi. Tanpa commit. HEAD `ab8fb24`, branch `feature/iss-02-04-sasaran-indikator`.

```text
Prompt handoff R4-02:
Kerjakan R4-02 dari document/PR-42-Review4-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Ekstrak shared
KomponenMutationService + delegasikan ChangeIndicatorFormula dan
IndikatorKomponenController@store + contract test baru sesuai detail
task; HANYA sentuh app/Services/Kinerja/, 2 pemanggil di app/, dan
1 file test backend baru. Ikuti Standards §1,§2,§4,§5,§7,§11 + skill
laravel-best-practices + testing-best-practices. Kontrak endpoint
DILARANG berubah. Update checkbox + Bukti. JANGAN commit. JANGAN
sentuh resources/js, routes/, migrasi, composer, tasks/, .agents/,
.claude/, *.json tooling. Test backend hanya di PG disposable baru
(dev 5433 utuh; hapus container).
```

### R4-03 · Catatan non-kode (tanpa aksi)

- [x] Status: closed tanpa kode (main session).
- Minor scope (44 commits / 84 files / 11k) diterima tanpa bongkar.
- Test monolith tetap BACKLOG-02. Tracking docs `PR-42-*` dievaluasi
  pasca-merge (bukan source-of-truth permanen). Rollback lifecycle
  tetap runbook operator (strict-THROW).
