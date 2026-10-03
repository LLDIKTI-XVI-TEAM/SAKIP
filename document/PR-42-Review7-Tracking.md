# PR-42 — Tracking Review Putaran 7 (review 4 temuan, HEAD `075bbbc` + R6-06)

Sumber: review 4 temuan (token CRUD komponen; izin granular vs
validasi; alasan formula; gate `komponen:read` final-set) terhadap
HEAD `075bbbc` + worktree R6-06. Status awal putaran (main session,
terverifikasi baca source): KEEMPAT masih terbuka — tidak satu pun
dinegasikan oleh R5/R6 maupun `075bbbc`.

> Aturan update: session pelaksana ubah `[ ]` → `[x]` + isi Bukti.
> Main session verifikasi lalu update `document/TASK-REGISTRY.md`.
> Jangan pindah branch. Jangan commit/push tanpa perintah eksplisit.
> Jangan sentuh: `tasks/`, `.agents/`, `.claude/`, `*.json` tooling,
> `composer.json/lock`. DB dev (`sakip_db:5433`) tidak boleh disentuh;
> test backend hanya di PostgreSQL disposable baru
> (lihat `document/PR-42-CI-Test-Context.md` §3).

## Urutan eksekusi

Batch 1 paralel (disjoint): R7-01 + R7-02. Lalu sekuensial: R7-03,
R7-04 (ketiganya menyentuh `ChangeIndicatorFormula`; R7-03 dulu
karena menambah field Request yang dipakai modal, R7-04 terakhir).

Kontrak yang TIDAK berubah di putaran ini:

- Data Model §2.12/§2.27 + Plan §2.15, ADR 0001–0003, Standards §1
  (tanpa nomor issue di source), §2, §4, §5, §7, §9, §11, §12,
  `CONTEXT.md`, `document/design-system.md`.
- Final-gate komposisi, lock-order, bump monotonik (R6-03), syntax
  tunggal (R6-04), capability gate (R5-02) tetap.

### R7-01 · Token versi pada CRUD komponen normal (backend + halaman Komponen)

- [x] Status: done (paralel dengan R7-02)
- Untuk apa: dua tab komponen tanpa compare versi → tab-B menimpa
  tab-A lalu bump tanpa konflik. Row lock hanya serialisasi.
- Yang dibuat:
  1. `Store/Update/DestroyIndikatorKomponenRequest`: tambah
     `expected_updated_at` (`required|date` + pesan pola
     UpdateIndikator: wajib-muat-ulang / format-tak-valid).
  2. Controller store/update/destroy: SETELAH parent
     `lockForUpdate`, bandingkan token vs `updated_at` induk
     (pola `ChangeIndicatorFormula`: kosong → 409 `konflik`,
     format-invalid → 422, beda → 409; tanpa mutasi/audit sukses).
     WAJIB `unset($data['expected_updated_at'])` sebelum
     `buat`/`update` (bukan kolom fillable). Fail-closed untuk
     pemanggil Action-langsung tidak berlaku di sini (controller
     layer; Request required sudah menutup HTTP).
  3. Controller `index`: ekspos `updated_at` ISO induk pada payload
     `indikator` (tambah key; JANGAN ubah shape lain).
  4. `Komponen/Index.tsx`: sertakan `expected_updated_at` dari
     `indikator.updated_at` pada POST/PUT/DELETE; tampilkan error
     `konflik`/`expected_updated_at` apa adanya (pola existing
     `errs.komponen || errs.konflik || ...` sudah ada di delete —
     samakan di create/update); tambah `updated_at?: string|null`
     pada `IndikatorKinerjaData` halaman ini.
- Test: tab-A mutasi → tab-B token lama 409 + data utuh (satu kasus
  update + satu kasus store; delete mengikuti pola sama bila murah)
  + tanpa-token → 422/409 + tanpa mutasi; FE (token terkirim;
  konflik tampil). `bun run typecheck` + `bun run test` hijau.
- DoD: tidak ada mutasi komponen tanpa token valid; konflik
  terdeteksi sebelum validasi domain/mutasi.
- Selesai: 2026-10-02 | Bukti: 3 Request tambah `expected_updated_at` (`required|date` + pesan pola UpdateIndikator: wajib-muat-ulang / format-tak-valid); controller: helper `pastikanTokenMutakhir` (kosong→409 `konflik`, format-invalid→422, beda→409, pola `ChangeIndicatorFormula`) dipanggil SETELAH parent `lockForUpdate` SEBELUM validasi domain/mutasi/audit pada store/update/destroy + `unset($data['expected_updated_at'])` sebelum buat/update (bukan fillable) + `index` ekspos `updated_at` ISO induk (tambah key, shape lain utuh); `Komponen/Index.tsx`: POST/PUT/DELETE kirim `expected_updated_at` dari `indikator.updated_at`, error `komponen||konflik||expected_updated_at` tampil apa adanya di create (alert form) + update/delete (audit modal, modal koreksi tak ditutup diam-diam), tipe `updated_at?` ditambah; test backend baru `KomponenStaleTokenTest.php` 5 passed/53 assertions (index ISO + store/update-web+JSON-409/delete stale→konflik tanpa mutasi/audit + tanpa-token/format-salah→422 tanpa mutasi); test terkait diselaraskan kirim token segar (`IndikatorKomponenHttpTest`, `KomponenMutationContractTest`, `FormulaMutationRegressionTest` 1 baris delete, `KomponenSyntaxUnifiedTest` 2 situs, `SasaranIndikatorTest` 2 situs; deny-403 pegawai tak diubah — authorize mendahului validasi); FE baru `KomponenStaleToken.test.tsx` 6 passed (token terkirim POST/PUT/DELETE + konflik tampil); gate: `bun run typecheck` hijau, `bun run test` 31 file/211 test hijau, `pint --test` passed, `phpstan` 0 errors, Pest di PG disposable podman `postgres:17-alpine` port 5461 — `IndikatorKomponen/` 63 passed/452 assertions + `SasaranIndikatorTest` 63 passed/369 assertions (dev `sakip_db:5433` tak tersentuh; container `sakip_test_r701` dihapus). Belum di-commit.

```text
Prompt handoff R7-01:
Kerjakan R7-01 dari document/PR-42-Review7-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Pasang stale-token pada CRUD
komponen normal (3 Request + controller + halaman Komponen + test)
sesuai detail task; HANYA sentuh app/Http/{Requests/Indikator/
StoreIndikatorKomponenRequest,UpdateIndikatorKomponenRequest,
DestroyIndikatorKomponenRequest,Controllers/Indikator/
IndikatorKomponenController}, resources/js/Pages/Indikator/Komponen/
Index.tsx, dan test terkait. Ikuti skill laravel-best-practices +
testing-best-practices + inertia-react-development +
tailwindcss-development + Standards §2,§4,§5,§7,§9,§11. Update
checkbox R7-01 + Bukti (HANYA seksi R7-01). JANGAN commit. JANGAN
sentuh ChangeIndicatorFormula*, routes/, migrasi, composer, tasks/,
.agents/, .claude/, *.json tooling. Test backend hanya di PG
disposable baru (dev 5433 utuh; hapus container).
```

### R7-02 · Izin granular sebelum validasi domain pada PATCH formula (backend only)

- [x] Status: done (paralel dengan R7-01)
- Untuk apa: payload invalid dari aktor tanpa `komponen:update`
  berhenti 422 sebelum 403 + tanpa audit penolakan (bocor info
  validitas + tanpa jejak).
- Yang dibuat (HANYA `ChangeIndicatorFormula.php` + test backend):
  hitung delta (`newComponents`/`changedComponents` — kode existing
  dipindah ke ATAS) → cek granular create/update (denied → 403 +
  audit seperti semula) → BARU `pastikanDefinisiValid`. Cek
  id-asing/duplikat-kode (bukan info validitas formula) boleh tetap
  sebelum cek izin. Tanpa ubah pesan, audit sukses, lock, token,
  bump.
- Test: aktor `indikator:update` allow + `komponen:update` deny
  kirim payload invalid → 403 (BUKAN 422) + tanpa mutasi + audit
  denied; kontrol allow+invalid → tetap 422. JANGAN ubah test lain
  kecuali bergantung urutan lama (catat).
- DoD: tidak ada respons validitas formula untuk pemanggil tanpa
  izin mutation yang sesuai delta.
- Selesai: 2026-10-03 | Bukti: File diubah: app/Actions/Perencanaan/ChangeIndicatorFormula.php (§ granular new/changed + denied 403 dipindah ke ATAS sebelum pastikanDefinisiValid; id-asing/duplikat-kode tetap sebelum izin; pesan/audit-sukses/lock/token/bump utuh), tests/Feature/IndikatorKomponen/FormulaGranularBeforeValidationTest.php (baru 2 test: deny-update+invalid → 403 + tanpa mutasi + audit indikator.ubah_ditolak komponen:update; allow+invalid sama → 422 tipe_perhitungan). Tanpa ubah test lain (FormulaMutationRegression 32/32 utuh). Verifikasi: pint --test PASS (full); phpstan 0 errors (full); Pest PG disposable baru 127.0.0.1:5546 (postgres:17-alpine) — baru 2/2 (14 asersi), regresi IndikatorKomponen+SasaranIndikatorTest+Stale 136/136 (923 asersi); dev sakip_db:5433 utuh (Up, tak dipakai test); container sakip_test_r702 dihapus (podman ps nihil).

```text
Prompt handoff R7-02:
Kerjakan R7-02 dari document/PR-42-Review7-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Pindahkan cek granular sebelum
pastikanDefinisiValid sesuai detail task; HANYA sentuh
app/Actions/Perencanaan/ChangeIndicatorFormula.php dan test backend.
Ikuti skill laravel-best-practices + testing-best-practices +
Standards §2,§4,§5,§7,§11. Update checkbox R7-02 + Bukti (HANYA
seksi R7-02). JANGAN commit. JANGAN sentuh file R7-01/R7-03/R7-04,
routes/, migrasi, composer, tasks/, .agents/, .claude/, *.json
tooling. Test backend hanya di PG disposable baru (dev 5433 utuh;
hapus container).
```

### R7-03 · Alasan pengguna untuk PATCH formula (backend + FormulaModal)

- [x] Status: done (SETELAH R7-01+R7-02 selesai; perilaku keduanya utuh)
- Untuk apa: mutasi child via formula tanpa rationale, padahal
  `komponen.ubah` sensitif (PUT wajib alasan min 5).
- Yang dibuat:
  1. `ChangeIndicatorFormulaRequest`: `alasan`
     (`required|string|min:5|max:1000` + pesan pola komponen;
     sanitasi mengikuti `AlasanAudit` bila tersedia di layer ini,
     JANGAN duplikat helper).
  2. Action: teruskan alasan tervalidasi ke audit `komponen.buat`/
     `komponen.ubah` (ganti teks generik); `indikator.ubah` boleh
     menyebut alasan yang sama (executor putuskan minimal,
     catat). Tanpa alasan valid → tanpa mutasi/audit (422 —
     tempatkan konsisten dengan pola existing, catat).
  3. `FormulaModal.tsx` + FE test: input alasan (wajib min 5,
     error tampil), terkirim sebagai `alasan`; tanpa token baru,
     tanpa raw hex, tanpa `dark:*`.
- Test: tanpa/pendek alasan → 422 + tanpa mutasi; valid → audit
  child memuat alasan operator. `bun run typecheck` + test hijau.
- DoD: tidak ada mutasi formula tanpa rationale teraudit.
- Selesai: 2026-10-03 | Bukti: File diubah: app/Http/Requests/Indikator/ChangeIndicatorFormulaRequest.php (`alasan` `required|string|min:5|max:1000` + pesan pola komponen `Alasan perubahan formula ...`, sanitasi didelegasikan ke boundary audit `AuditLogger`/`WriteAuditLog` tanpa duplikat helper), app/Actions/Perencanaan/ChangeIndicatorFormula.php (guard fail-closed 422 `alasan` kosong/pendek di dalam transaksi SETELAH cek token SEBELUM mutasi/audit — HTTP sebelumnya sudah 422 di FormRequest; audit `komponen.buat`/`komponen.ubah` ganti teks generik menjadi `<teks existing>. Alasan: <alasan operator>`; `@param` + docblock diselaraskan), resources/js/Pages/Perencanaan/SasaranIndikator/FormulaModal.tsx (field `Alasan Perubahan Formula` via `Textarea` existing + validasi klien cermin backend + reset per-buka + error tampil; tanpa token baru/raw hex/`dark:*`), resources/js/types/sasaran-indikator.ts (`FormulaPayload` + `alasan: string`). Keputusan penempatan alasan pada audit `indikator.ubah`: rationale operator di-APPEND sebagai ` Alasan: <alasan>` pada teks existing (teks existing utuh agar jejak/kueri lama tetap cocok; rationale atomik tertelusur di parent maupun child; sanitasi tulisan mengikuti boundary `WriteAuditLog::AuditReason::sanitize` + `AlasanAudit` untuk `komponen.*`). Test backend baru tests/Feature/IndikatorKomponen/FormulaAlasanTest.php 3 passed/17 assertions (tanpa-alasan→422 + tanpa mutasi/audit; alasan `abc`→422 + tanpa mutasi; valid→`komponen.ubah`/`komponen.buat`/`indikator.ubah` memuat alasan operator). Test lain yang PATCH formula diselaraskan HANYA tambah `alasan` valid (perilaku/assertion utuh): FormulaMutationRegressionTest 9 situs, FormulaGranularBeforeValidationTest 2 situs, KomponenMutationContractTest 5 situs, KomponenSyntaxUnifiedTest 2 situs, SasaranIndikatorTest 6 situs. FE tests/Frontend/FormulaTransisi.test.tsx: helper `isiAlasan` + payload `toEqual` + `alasan` + 2 test baru (tanpa-alasan, alasan-pendek → error + PATCH tak dipanggil; submit kosong ikut assert pesan alasan). Verifikasi: `bun run typecheck` hijau; `bun run test` 31 file/213 test hijau; `pint --dirty` passed; `phpstan` 0 errors (full); Pest di PG disposable baru `sakip_test_r703` (postgres:17-alpine) 127.0.0.1:5573 — `IndikatorKomponen/` 68 passed/483 assertions + `SasaranIndikatorTest` 63 passed/369 assertions (total 131/131, 852 asersi); dev `sakip_db:5433` tak tersentuh (Up, tanpa koneksi test); container `sakip_test_r703` dihapus (`podman ps` tinggal keycloak/db/app). Belum di-commit.

```text
Prompt handoff R7-03:
Kerjakan R7-03 dari document/PR-42-Review7-Tracking.md di branch
feature/iss-02-04-sasaran-indikator, SETELAH R7-01+R7-02. Tambahkan
alasan tervalidasi pada PATCH formula (Request + Action + modal +
test) sesuai detail task. Ikuti skill laravel-best-practices +
testing-best-practices + inertia-react-development +
tailwindcss-development + Standards §2,§4,§5,§7,§9,§11. Update
checkbox R7-03 + Bukti (HANYA seksi R7-03). JANGAN commit. JANGAN
sentuh file R7-01/R7-02/R7-04 di luar kebutuhan, routes/, migrasi,
composer, tasks/, .agents/, .claude/, *.json tooling. Test backend
hanya di PG disposable baru (dev 5433 utuh; hapus container).
```

### R7-04 · Gate `komponen:read` untuk final-set formula (backend only)

- [x] Status: done (SETELAH R7-03 selesai; perilaku R7-01+R7-02+R7-03 utuh, termasuk alasan R7-03)
- Untuk apa: akun tanpa `komponen:read` bisa memutasi child
  tersembunyi via final-set (cukup tebak tanpa UUID).
- Yang dibuat (HANYA `ChangeIndicatorFormula.php` + test backend):
  resolve ulang `komponen:read` terkunci; denied → 403 + audit
  penolakan (pola denied existing; JANGAN menyebutkan UUID child)
  SEBELUM memuat/menerapkan final-set (sebelum lock child —
  setelah lock parent + cek token boleh, catat urutan). Tanpa ubah
  perilaku allow (termasuk alasan R7-03 + auditnya).
- Test: allow update + deny read → 403 + tanpa mutasi + audit
  denied (payload manual+`komponen:[]` maupun campur); kontrol
  allow semua → sukses. JANGAN ubah test lain kecuali bergantung
  perilaku lama (catat).
- DoD: final-set tak tersentuh tanpa `komponen:read` efektif.
- Selesai: 2026-10-03 | Bukti: File diubah: app/Actions/Perencanaan/ChangeIndicatorFormula.php (resolve `$readDecision` via lockedActor `komponen:read` sejajar create/update SEBELUM parent lock agar urutan kunci aktor/ACL → indikator → child → sasaran utuh; cek denied 403 + audit pola existing diposisikan SETELAH parent lock + cek token + guard alasan R7-03, SEBELUM lock/muat child — alasan generik `... wewenang baca komponen tidak lagi berlaku.` TANPA UUID child, `dasarIzin` dari read-decision; docblock + komentar urutan diselaraskan; perilaku allow + alasan/audit R7-03 utuh), tests/Feature/IndikatorKomponen/FormulaReadGateTest.php (baru 3 test: deny-read + manual/`komponen:[]` → 403 + tanpa mutasi + audit `indikator.ubah_ditolak` `komponen:read`/`ditolak` + alasan tanpa UUID child + tanpa audit sukses; deny-read + campur ubah/tambah → 403 sama; kontrol allow semua + campur → redirect sukses + mutasi teraplikasi + `komponen.ubah`/`komponen.buat`/`indikator.ubah` memuat alasan operator). Tanpa ubah test lain (R7-01/R7-02/R7-03 + regression utuh). Urutan final: aktor/ACL `indikator:update` → resolve create/update/read terkunci → parent `lockForUpdate` → token (409/422) → alasan R7-03 (422) → GATE READ (403+audit) → child `lockForUpdate`/muat final-set → id-asing/duplikat → granular R7-02 → `pastikanDefinisiValid` → persist + audit. Alasan penempatan: sebelum lock child agar child tersembunyi tak termuat/dimutasi via tebakan ID; setelah token agar stale 409 tetap didahulukan; setelah alasan agar allow-422 R7-03 utuh dan missing-alasan tetap 422 + nol audit. Verifikasi: `pint --test` PASS (full); `phpstan` 0 errors (full); Pest PG disposable baru `127.0.0.1:5574` (`postgres:17-alpine`, DB/user `sakip_test`) — baru 3/3 (27 asersi), regresi `IndikatorKomponen/` 71/71 (510 asersi) + `SasaranIndikatorTest` 63/63 (369 asersi); dev `sakip_db:5433` utuh (running, tak dipakai test); container `sakip_test_r704` dihapus (`podman ps` nihil). Belum di-commit.

```text
Prompt handoff R7-04:
Kerjakan R7-04 dari document/PR-42-Review7-Tracking.md di branch
feature/iss-02-04-sasaran-indikator, SETELAH R7-03. Pasang gate
komponen:read sebelum final-set sesuai detail task; HANYA sentuh
app/Actions/Perencanaan/ChangeIndicatorFormula.php dan test backend.
Ikuti skill laravel-best-practices + testing-best-practices +
Standards §2,§3,§5,§7,§11. Update checkbox R7-04 + Bukti (HANYA
seksi R7-04). JANGAN commit. JANGAN sentuh file R7-01/R7-02/R7-03,
routes/, migrasi, composer, tasks/, .agents/, .claude/, *.json
tooling. Test backend hanya di PG disposable baru (dev 5433 utuh;
hapus container).
```

### R7-05 · Catatan non-kode (tanpa aksi)

- [x] Status: closed tanpa kode (main session).
- Tech Debt tetap backlog/runbook seperti sebelumnya.
