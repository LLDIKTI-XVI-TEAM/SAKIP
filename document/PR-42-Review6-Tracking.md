# PR-42 — Tracking Review Putaran 6 (Codex, HEAD `143cfb6` + worktree R5)

Sumber: review Codex 4 temuan pada HEAD `143cfb6`
(CI GitHub HEAD itu: 9/9 PASS run #36986429994, Backend 865/7508 —
catatan: review Codex ditulis terhadap HEAD tanpa worktree R5).
Temuan-1 (final validation CRUD) SUDAH dikerjakan di worktree sebagai
R5-01 (belum commit); 3 temuan lain baru. Scope putaran ini (disuruh
user): temuan-2, 3, 4.

> Aturan update: session pelaksana ubah `[ ]` → `[x]` + isi Bukti.
> Main session verifikasi lalu update `document/TASK-REGISTRY.md`.
> Jangan pindah branch. Jangan commit/push tanpa perintah eksplisit.
> Jangan sentuh: `tasks/`, `.agents/`, `.claude/`, `*.json` tooling,
> `composer.json/lock`. DB dev (`sakip_db:5433`) tidak boleh disentuh;
> test backend hanya di PostgreSQL disposable baru
> (lihat `document/PR-42-CI-Test-Context.md` §3).

## Urutan eksekusi (wajib sekuensial — file bersama)

R6-04 → R6-02 → R6-03. Ketiganya menyentuh
`ChangeIndicatorFormula` dan/atau `KomponenMutationService` dan/atau
`ChangeIndicatorFormulaRequest`; JANGAN paralel. R6-04 dulu (fondasi
rules bersama), R6-02 memakai rules itu untuk payload pengganti,
R6-03 terakhir (touch parent di atas semantik final).

Kontrak yang TIDAK berubah di putaran ini:

- Data Model §2.12/§2.27 + Plan §2.15, ADR 0001–0003, Standards §1
  (tanpa nomor issue di source), §2, §4, §5, §7, §9, §11, §12,
  `CONTEXT.md`, `document/design-system.md`.
- Final-gate komposisi tetap
  `IndikatorPerhitunganService::validateDefinisiKomponen`; final-state
  validation CRUD normal tetap (R5-01); lock-order, re-auth,
  stale-token, audit, capability gate tetap.

### R6-01 · Temuan-1 final validation CRUD — SUDAH R5-01, tanpa aksi

- [x] Status: closed tanpa kode baru (main session).
- POST campur, PUT menjadi-invalid, DELETE komponen wajib di worktree
  SUDAH ditolak via kandidat final (R5-01, belum commit). Codex
  mereview HEAD tanpa worktree sehingga menemukannya masih terbuka.

### R6-02 · Muat komponen lama + penggantian/penonaktifan atomik (backend + frontend)

- [x] Status: done (jalan SETELAH R6-04 selesai; tanpa commit)
- Untuk apa: modal hari ini selalu mulai dari satu baris kosong dan
  action selalu `existing->concat(payload)` — rasio→penjumlahan/
  manual tak pernah bisa lewat alur ini (komponen lama bertahan dan
  menggagalkan 422), padahal tombol tampil untuk nonmanual.
- Yang dibuat:
  1. Backend (`ChangeIndicatorFormula` Action + Request, memakai
     rules bersama R6-04): dukung penggantian atomik — payload
     `komponen` menjadi definisi FINAL (ganti, bukan tambah):
     baris existing ber-`id` boleh diikutkan dengan `aktif:false`
     untuk penonaktifan; baris tanpa `id` = create; hapus fisik
     HANYA bila tak dirujuk, selain itu tolak dengan arahan.
     Kandidat final = hasil penerapan payload pada existing →
     `validateDefinisiKomponen` → invalid 422 tanpa mutasi/audit;
     valid → tipe + upsert/nonaktifkan + audit satu commit. Lock,
     re-auth, stale-token, audit, pesan: pola existing
     dipertahankan.
  2. Frontend (`FormulaModal.tsx`): saat dibuka untuk indikator
     nonmanual, muat daftar komponen existing sebagai baris awal
     (dengan `id`), bukan satu baris kosong; dukung ubah peran/
     `aktif`/tandai-hapus. Sumber existing: prop dari Index
     (tambah `komponen` pada item bila belum ada — tambah di
     `IndexSasaranIndikator` payload + types) atau endpoint read
     yang sudah ada; pilih yang terkecil tanpa endpoint baru bila
     memungkinkan. Untuk indikator `manual`, perilaku existing
     (mulai kosong) dipertahankan.
  3. Alternatif yang DITERIMA bila (1) terlalu besar: batasi UI —
     tombol Atur Formula hanya untuk transisi yang didukung, dan
     rasio→penjumlahan/manual DITOLAK di backend bila payload tak
     mampu mewakilinya (pesan eksplisit, bukan 422 samar). Catat
     pilihan di Bukti; JANGAN kontrak samar.
- Test: backend (ganti rasio→penjumlahan valid lolos; payload
  incomplete → 422 utuh; existing tak tersentuh saat 422) + FE
  (existing termuat sebagai baris; hapus/aktif-off terkirim).
  `bun run typecheck` + `bun run test` hijau. Tanpa token baru,
  tanpa raw hex, tanpa `dark:*`.
- DoD: rasio→penjumlahan dan rasio→manual BISA lewat UI end-to-end;
  tidak ada jalan buntu untuk tombol yang ditampilkan.
- Selesai: 2026-10-03 | Bukti: Pilihan desain: PENUH (bukan batasi-UI). Payload `komponen` adalah definisi FINAL: ber-`id` = update/nonaktifkan (`aktif:false`), tanpa `id` = create, omitted = hapus fisik bila tak dirujuk, selain itu 422 `komponen` eksplisit dengan arahan `aktif:false`. File diubah: app/Services/Kinerja/KomponenMutationService.php (aturanItem/validasiSintaks/modelKandidat tambah param opsional abaikanId → unique ignore diri sendiri; kompatibel mundur), app/Http/Requests/Indikator/ChangeIndicatorFormulaRequest.php (rules per-indeks dengan ignore-self + `komponen.N.id` nullable/uuid/exists scoped; withValidator tambah distinct trimmed + duplikat id + penyebut via layanan; messages tambah id + tetap pesanBersarang), app/Actions/Perencanaan/ChangeIndicatorFormula.php (FINAL: lock child FOR UPDATE, partisi id/baru, id-asing 422, izin granular create/update/delete kondisional fail-closed, cek rujuk 4 tabel → 422 arahan nonaktif, sintaks per-baris via layanan dengan ignore, kandidat final → validateDefinisiKomponen → 422 tanpa mutasi/audit, sukses upsert + hapus + audit komponen.buat/ubah/hapus + indikator.ubah satu commit), app/Actions/Perencanaan/IndexSasaranIndikator.php (eager + payload `komponen` per indikator), resources/js/types/sasaran-indikator.ts (FormulaKomponenInput.id? + IndikatorKomponenExisting + IndikatorKinerjaItem.komponen?), resources/js/Pages/Perencanaan/SasaranIndikator/FormulaModal.tsx (muat existing nonmanual via prop dengan id, manual tetap kosong; key id, helper existing, clear id error; tanpa dark:/hex), tests/Feature/IndikatorKomponen/FormulaReplaceAtomicTest.php (baru 5 test), tests/Frontend/FormulaTransisi.test.tsx (+3 test). Batasan dicatat: kode reuse dari baris omitted yang dihapus pada transisi sama ditolak duplikat (butuh dua langkah) — tak blokir DoD. Verifikasi: typecheck PASS; vitest 28f/181t PASS (FormulaTransisi 14/14); pint --test PASS; phpstan 0 errors; Pest PG disposable 127.0.0.1:5545 — baru 5/5 (39 asersi), IndikatorKomponen 34/34 (247), SasaranIndikatorTest 63/63 (359), Capability 2/2 (22); dev sakip_db:5433 utuh (Up, tak dipakai); container sakip_test_r602 dihapus (podman ps nihil).

```text
Prompt handoff R6-02:
Kerjakan R6-02 dari document/PR-42-Review6-Tracking.md di branch
feature/iss-02-04-sasaran-indikator, SETELAH R6-04 selesai. Dukung
penggantian/penonaktifan atomik + muat existing sesuai detail task;
pakai rules bersama R6-04 untuk payload. Ikuti skill
laravel-best-practices + testing-best-practices +
inertia-react-development + tailwindcss-development + Standards
§1,§2,§4,§5,§7,§9,§11. Update checkbox R6-02 + Bukti (HANYA seksi
R6-02). JANGAN commit. JANGAN sentuh routes/, migrasi, composer,
tasks/, .agents/, .claude/, *.json tooling. Test backend hanya di PG
disposable baru (dev 5433 utuh; hapus container).
```

### R6-03 · Naikkan versi induk pada setiap mutasi formula (backend only)

- [x] Status: done (jalan SETELAH R6-02 selesai; tanpa commit)
- Untuk apa: PATCH tipe-sama dan mutasi CRUD normal tak menyentuh
  baris induk → `updated_at` diam → dua tab memakai token sama
  tanpa konflik kedua 409 seperti janji modal.
- Yang dibuat (HANYA `app/` + test backend; JANGAN sentuh
  `resources/js`, `routes/`, migrasi): setiap mutasi definisi
  formula yang BERHASIL menyentuh/monotonik-naikkan `updated_at`
  induk di dalam transaksi yang sama — `ChangeIndicatorFormula`
  (selalu, bahkan bila tipe tak berubah) + store/update/destroy
  komponen normal (R5-01). Manfaatkan hook monotonik R2-23
  (`saving` +1µs bila clock tak maju; `touch()` melewatinya —
  verifikasi dengan test, JANGAN tulis logika bump kedua).
  Gagal/422/403: tanpa bump, tanpa mutasi, tanpa audit sukses
  (seperti semula).
- Test: tab-A mutasi → tab-B token lama DITOLAK 409 + data A utuh
  (atomik tipe-sama + satu kasus CRUD normal); bump tepat +1µs
  saat clock beku (pola StaleTest R2-23). JANGAN ubah test lain
  kecuali bergantung perilaku lama (catat).
- DoD: tidak ada mutasi formula berhasil tanpa versi induk naik;
  stale guard berlaku untuk semua update bertoken sesudahnya.
- Selesai: 2026-10-03 | Bukti: File diubah: app/Actions/Perencanaan/ChangeIndicatorFormula.php (§4: cabang tipe-sama picu dirty eksplisit + save() agar hook saving R2-23 menghitung versi; cabang tipe-berubah tetap via update() — tepat satu bump; dalam transaksi yang sama, gagal/422/403 tanpa bump), app/Http/Controllers/Indikator/IndikatorKomponenController.php (store/update/destroy: touch induk yang sama dalam transaksi setelah mutasi child sukses, sebelum audit), tests/Feature/IndikatorKomponen/FormulaParentVersionBumpTest.php (baru, 4 test). Tanpa logika bump kedua: penanda null pemicu-dirty selalu ditimpa hook (jam tak maju → orig+1µs); touch() polos no-op saat clock beku tepat pada nilai existing (save() lewati UPDATE bila tak dirty) sehingga pemicu eksplisit diperlukan — dibuktikan test clock-beku. Test: tab-A formula tipe-sama → tab-B putJson indikator token lama 409 + data A utuh; store normal → tab-B 409 + baris baru utuh; clock beku (pola StaleTest R2-23) bump TEPAT +1µs (assertSame tokenAwal+1µs; dua bump akan terbaca 2µs); 422 incomplete + 403 pegawai tanpa bump/mutasi. Tak ada test lain diubah (suite existing tak reuse token usang lintas mutasi). Verifikasi: pint --test 3 file PASS; phpstan 0 errors (whole project); Pest PG disposable baru 127.0.0.1:5545 (postgres:17-alpine) — baru 4/4 (32 asersi), IndikatorKomponen 38/38 (279 asersi), SasaranIndikatorTest+StaleTest 71/71 (447 asersi); dev sakip_db:5433 utuh (Up, tak dipakai test); container sakip_test_r603 dihapus (podman ps nihil).

```text
Prompt handoff R6-03:
Kerjakan R6-03 dari document/PR-42-Review6-Tracking.md di branch
feature/iss-02-04-sasaran-indikator, SETELAH R6-02 selesai. Bump
versi induk monotonik pada setiap mutasi formula berhasil sesuai
detail task; HANYA sentuh app/Actions/Perencanaan/
ChangeIndicatorFormula.php,
app/Http/Controllers/Indikator/IndikatorKomponenController.php,
dan test backend. Ikuti skill laravel-best-practices +
testing-best-practices + Standards §2,§4,§5,§7,§11. Update checkbox
R6-03 + Bukti (HANYA seksi R6-03). JANGAN commit. JANGAN sentuh
resources/js, routes/, migrasi, composer, tasks/, .agents/,
.claude/, *.json tooling. Test backend hanya di PG disposable baru
(dev 5433 utuh; hapus container).
```

### R6-04 · Pusatkan validasi sintaks di service bersama (backend only)

- [x] Status: done (jalan PERTAMA; tanpa commit)
- Untuk apa: `buat()` hanya cek bobot penyebut; regex/panjang/
  enum/presisi/rentang masih duplikat di dua FormRequest (bahkan
  pesan `max` tak sama) — jalur internal lain bisa menyimpan
  invalid dan kontrak bisa drift.
- Yang dibuat (HANYA `app/` + test backend; JANGAN sentuh
  `resources/js`, `routes/`, migrasi):
  1. `KomponenMutationService`: SATU validator sintaks lengkap per
     item (kode required/string/max50/regex/distinct-dalam-payload/
     unique-per-indikator, label required/max255, satuan
     nullable/max50, peran in, bobot numeric/decimal0-12/min/max +
     penyebut>0, urutan int/min/max, aktif boolean) + SATU peta
     pesan. `buat()`/`modelKandidat` menjalankan validator ini
     (gagal → ValidationException; modul panggilan tetap 422);
     ketidakcocokan DB-unique tetap dipetakan seperti semula.
  2. `StoreIndikatorKomponenRequest` + `ChangeIndicatorFormulaRequest`
     delegasi rules/pesan per-item ke service (bentuk error key
     `komponen.N.field` / flat existing DIPERTAHANKAN agar kontrak
     frontend tak berubah; pesan diseragamkan ke peta tunggal —
     catat tiap pesan yang berubah di Bukti).
  3. Contract test: tiap kasus sintaks invalid (kode regex, label
     >255, peran asing, bobot desimal >12, urutan 0, penyebut bobot
     0) menghasilkan pesan IDENTIK via POST normal dan PATCH
     formula. JANGAN ubah test lain kecuali bergantung pesan lama
     (catat).
- DoD: satu definisi rules + satu peta pesan; kedua endpoint kontrak
  identik; tidak ada jalur `buat()` yang lolos sintaks.
- Verifikasi: pint + phpstan 0 errors; Pest contract + regresi
  modul hijau di PG disposable (dev utuh; hapus container).
- Selesai: 2026-10-03 | Bukti: File diubah: app/Services/Kinerja/KomponenMutationService.php (aturanItem/aturanBersarang + pesanItem/pesanBersarang + tambahErrorPenyebutBilaNol + validasiSintaks; buat/modelKandidat kini validasiSintaks penuh), app/Http/Requests/Indikator/StoreIndikatorKomponenRequest.php (delegasi rules/messages/withValidator-penyebut ke service), app/Http/Requests/Indikator/ChangeIndicatorFormulaRequest.php (delegasi per-item + wrapper dipertahankan), tests/Feature/IndikatorKomponen/KomponenSyntaxUnifiedTest.php (baru, 6 kasus). Tanpa ubah call-site (signature buat/modelKandidat tetap); controller/Action tak disentuh. Error key DIPERTAHANKAN (flat vs komponen.N.field). Pesan diseragamkan ke peta tunggal — yang berubah di PATCH (dulu fallback Inggris → kini Indonesia, teks = jalur normal): komponen.*.kode.string/max, label.string/max, satuan.string/max, peran.string, bobot.decimal/min/max, urutan.integer/min/max, aktif.boolean; baru di kedua jalur (dulu fallback): kode.string, label.string, satuan.string/max, peran.string, aktif.boolean='Status aktif komponen harus bernilai benar atau salah.'; TETAP sama: kode.required/regex/unique/distinct, label.required, peran.required/in, bobot.required/numeric, penyebut>0, wrapper tipe/komponen/expected_updated_at. Tak ada test existing bergantung pesan lama (grep nihil) sehingga tak ada test diubah. Verifikasi: pint --test PASS; phpstan 0 errors (whole project); Pest di PG disposable 127.0.0.1:5544 — unified baru 6/6, IndikatorKomponen 29/29, SasaranIndikatorTest 63/63; dev sakip_db:5433 utuh (Up, tak dipakai test); container sakip_test_r604 dihapus (podman ps nihil).

```text
Prompt handoff R6-04:
Kerjakan R6-04 dari document/PR-42-Review6-Tracking.md di branch
feature/iss-02-04-sasaran-indikator (jalan PERTAMA sebelum R6-02).
Pusatkan validator sintaks + delegasikan kedua Request sesuai detail
task; HANYA sentuh app/Services/Kinerja/KomponenMutationService.php,
app/Http/Requests/Indikator/StoreIndikatorKomponenRequest.php,
app/Http/Requests/Indikator/ChangeIndicatorFormulaRequest.php
(+ penyesuaian call-site minimal bila signature berubah), dan test
backend. Ikuti skill laravel-best-practices +
testing-best-practices + Standards §1,§2,§4,§5,§11. Bentuk error key
dan kontrak endpoint DIPERTAHANKAN. Update checkbox R6-04 + Bukti
(HANYA seksi R6-04). JANGAN commit. JANGAN sentuh resources/js,
routes/, migrasi, composer, tasks/, .agents/, .claude/, *.json
tooling, file R6-02/R6-03. Test backend hanya di PG disposable baru
(dev 5433 utuh; hapus container).
```

### R6-05 · Catatan non-kode (tanpa aksi)

- [x] Status: closed tanpa kode (main session).
- Tech Debt putaran Codex (monolith, orchestration, docs, rollback)
  tetap backlog/runbook seperti sebelumnya.

### R6-06 · Integrasi stash R5/R6 dengan implementasi owner `075bbbc` (INTEGRASI, bukan fitur baru)

- [x] Status: done (tanpa commit; stash@{0} utuh, tidak di-pop/drop)
- Konteks: owner (Diyoncrz18) mendorong `075bbbc` ("jaga invariant
  formula dan otorisasi mutasi komponen", full backend 980/8919 +
  FE 203 + lint/build/browser QA) yang MENGIMPLEMENTASIKAN
  R5-01/R6-02/R6-03 dengan desain sendiri (plus re-auth hardening
  via `ResolveLockedActor` di KomponenController yang versi kita
  tidak punya): `pastikanDefinisiValid` + `bumpVersiFormula` di
  service, replace-semantics + modal muat existing + payload
  `komponen` di Index, `FormulaMutationRegressionTest`.
  Backup kerja kita ada di `stash@{0}` (JANGAN di-drop, JANGAN
  di-pop mentah — konflik besar dijamin).
- Matriks putusan (final, main session):
  - REDUNDAN → buang versi kita, pakai owner: R5-01 (validasi
    final CRUD), R6-02 (replace + modal existing), R6-03 (bump).
    Hapus file test yatim kita yang menegaskan SEMANTIK KITA dan
    akan merah terhadap owner:
    `FormulaReplaceAtomicTest.php`,
    `FormulaParentVersionBumpTest.php`. (JANGAN hapus
    `FormulaMutationRegressionTest.php` milik owner.)
  - UNIK → adaptasikan ke HEAD: R5-02 (capability
    `komponen_create` + gate tombol + 1-line fix TEST-UI-07 +
    `SasaranIndikatorKomponenCreateCapabilityTest.php` yang sudah
    di tree) dan R6-04 (validator sintaks tunggal + delegasi 2
    Request + `KomponenSyntaxUnifiedTest.php` yang sudah di tree).
    Ambil hunk relevan dari `stash@{0}` via
    `git show "stash@{0}" -- <path>` (baca dulu, JANGAN apply
    mentah), sesuaikan dengan bentuk HEAD (service owner kini
    berkonstruktor `perhitunganService`; pesan/key bisa berbeda —
    uji yang menentukan).
  - Verifikasi temuan-demi-temuan review Codex terhadap HASIL
    AKHIR (bukan klaim commit): (1) CRUD invalid ditolak, (2)
    rasio→penjumlahan/manual bisa via UI, (3) tiap mutasi formula
    menaikkan versi (tab-B 409), (4) pesan sintaks identik POST vs
    PATCH. Catat celah sisa (bila ada) di Bukti, JANGAN diamkan.
- Yang BOLEH disentuh: file R5-02/R6-04 hasil adaptasi + testnya +
    penghapusan 2 file yatim + seksi R6-06 ini. Yang DILARANG:
    mengubah implementasi owner selain yang dibutuhkan adaptasi,
    pop/drop stash, commit, routes/, migrasi, composer, tasks/,
    .agents/, .claude/, *.json tooling.
- Verifikasi: pint + phpstan 0 errors + typecheck + vitest hijau;
  Pest (contract owner + syntax + capability + regresi modul)
  hijau di PG disposable (dev 5433 utuh; hapus container).
- DoD: semua 4 temuan Codex tertutup di tree AKHIR; tidak ada test
  merah yatim; tidak ada duplikasi implementasi.
- Selesai: 2026-10-03 | Bukti: SUPERSEDED (terverifikasi benar): R5-01/R6-02/R6-03 milik owner dipakai utuh — R5-01 via `pastikanDefinisiValid` di store/update/destroy, R6-02 via replace-semantics + modal muat existing + payload `komponen` di Index, R6-03 via `bumpVersiFormula`; 2 file yatim kita DIHAPUS (`FormulaReplaceAtomicTest.php`, `FormulaParentVersionBumpTest.php` — semantik kita: hapus-fisik/+1µs-null-dirty, merah vs owner: nonaktif-omitted/bump-service). UNIK diadaptasi via `git show "stash@{0}" -- <path>` (tanpa apply mentah): R5-02 — `IndexSasaranIndikator.php` tambah `komponen_create` literal (konstanta `KOMPONEN_CREATE` tak ada di `PermissionCodes`, pola literal sama seperti `ChangeIndicatorFormula.php:84`), `sasaran-indikator.ts` tambah `komponen_create?: boolean`, `Index.tsx` gate Atur Formula menjadi `can.indikator_update && can.komponen_create` (tombol lain utuh), `FormulaTransisi.test.tsx` fullCan + 2 kasus gate baru, `SasaranIndikatorRefinement.test.tsx` TEST-UI-07 +`komponen_create:true` (1-line fix); R6-04 — `KomponenMutationService` tambah `aturanItem/aturanBersarang/pesanItem/pesanBersarang/tambahErrorPenyebutBilaNol/validasiSintaks` (dengan `abaikanId` opsional, kompatibel mundur) + `buat/modelKandidat` panggil `validasiSintaks`, konstruktor + `pastikanDefinisiValid`/`bumpVersiFormula` owner dipertahankan; `StoreIndikatorKomponenRequest` delegasi penuh rules/messages/penyebut ke service; `ChangeIndicatorFormulaRequest` rules owner DIPERTAHANKAN (tanpa unique — swap kode existing via temp-code owner, cf. `test_formula_final_dapat_menukar_kode_existing_tanpa_mengubah_id`), hanya withValidator-penyebut + messages diseragamkan via service + pesan `id.uuid/distinct`. Matriks final temuan Codex: (1) CRUD invalid ditolak — TERTUTUP (regression 32/32 invalid 422 utuh tanpa mutasi/audit); (2) rasio→penjumlahan/manual via UI — TERTUTUP (FE transisi final-set + manual-kosong hijau; backend `test_formula_ke_manual_menonaktifkan_child_tanpa_menghapus_identitas`); (3) tiap mutasi naikkan versi/tab-B 409 — TERTUTUP (`test_mutasi_valid_bump_parent_dan_token_tab_lama_ditolak` 3 metode); (4) pesan sintaks identik POST vs PATCH — TERTUTUP (unified 6/6). Celah sisa: nihil — duplikat kode lintas payload di PATCH mengandalkan `distinct` eksak (bukan trimmed) + cek final `kode`-flat/`unique`-DB seperti owner; reuse kode dari baris yang sedang diubah-away dalam payload sama tetap butuh dua langkah (bukan kasus DoD). File diubah: app/Actions/Perencanaan/IndexSasaranIndikator.php, resources/js/types/sasaran-indikator.ts, resources/js/Pages/Perencanaan/SasaranIndikator/Index.tsx, app/Services/Kinerja/KomponenMutationService.php, app/Http/Requests/Indikator/StoreIndikatorKomponenRequest.php, app/Http/Requests/Indikator/ChangeIndicatorFormulaRequest.php, tests/Frontend/FormulaTransisi.test.tsx, tests/Frontend/SasaranIndikatorRefinement.test.tsx (+ 2 test backend sudah-di-tree tanpa ubah: KomponenSyntaxUnifiedTest.php, SasaranIndikatorKomponenCreateCapabilityTest.php); dihapus: FormulaReplaceAtomicTest.php, FormulaParentVersionBumpTest.php; milik owner tak disentuh: ChangeIndicatorFormula.php, IndikatorKomponenController.php, FormulaMutationRegressionTest.php, FormulaModal.tsx. Verifikasi: typecheck PASS; vitest 30f/205t PASS (FormulaTransisi 16/16 incl. 2 gate baru; Refinement 7/7); pint --test PASS (whole project); phpstan 0 errors; Pest PG disposable 127.0.0.1:5546 (postgres:17-alpine, sakip_test) — unified 6/6 (55 asersi), capability 2/2 (22 asersi), owner-regression 32/32 (205 asersi), IndikatorKomponen 58/58 (399 asersi), SasaranIndikatorTest+Stale+Capability 73/73 (479 asersi); dev sakip_db:5433 Up utuh (select 1 OK, tak dipakai test); container sakip_test_r606 dihapus (podman ps nihil). Tanpa commit. Larangan dipatuhi: routes/migrasi/composer/tasks/.agents/.claude/*.json tak tersentuh.

```text
Prompt handoff R6-06:
Kerjakan R6-06 dari document/PR-42-Review6-Tracking.md di branch
feature/iss-02-04-sasaran-indikator, HEAD 075bbbc. Integrasikan
bagian UNIK stash@{0} (R5-02 gate + R6-04 sintaks) ke implementasi
owner dan hapus 2 file test yatim sesuai matriks; JANGAN pop/drop
stash; baca hunk via git show. Ikuti skill laravel-best-practices +
testing-best-practices + inertia-react-development +
tailwindcss-development + Standards §1,§2,§4,§5,§7,§9,§11. Update
checkbox R6-06 + Bukti (HANYA seksi R6-06; tandai R5-01/R6-02/R6-03
sebagai superseded-bila-benar). JANGAN commit. Test backend hanya
di PG disposable baru (dev 5433 utuh; hapus container).
```
