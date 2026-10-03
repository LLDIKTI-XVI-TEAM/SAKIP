# PR-42 — Tracking Review Putaran 3 (HEAD `99234d7`)

Sumber: Final re-review PR #42 — ISS-02.04, HEAD
`99234d7e818c5051aec2b67ea99f82471b21afe9`, base
`development@73f22ac856307009c0928d7ec127c42806a19544`.
Verdict review: **REQUEST CHANGES — NOT READY FOR MERGE**.
CI exact HEAD: **9/9 PASS** — run #36873194441.
Backend exact HEAD: **855 tests / 7.634 assertions PASS**.
Reviewer: Diyoncrz18 (komentar, 2026-10-02). Codex: usage-limit, tanpa hasil substantif.

> Aturan update: session pelaksana ubah `[ ]` → `[x]` + isi Bukti.
> Main session verifikasi lalu update `document/TASK-REGISTRY.md`.
> Jangan pindah branch. Jangan commit/push tanpa perintah eksplisit.
> Jangan sentuh: `tasks/`, `.agents/`, `.claude/`, `*.json` tooling,
> `composer.json/lock`. DB dev (`sakip_db:5433`) tidak boleh disentuh;
> test backend hanya di PostgreSQL disposable baru
> (lihat `document/PR-42-CI-Test-Context.md` §3).

## Keputusan user FINAL (2026-10-02, mengikat)

1. R3-01 Major → **Opsi A atomik**. Invarian Data Model dipertahankan;
   tanpa state draft invalid yang persisted; tanpa ADR baru; tanpa
   perubahan Data Model/Plan/PRD.
2. Tech Debt 1 (controller-heavy komponen) → **BACKLOG ISS-02.06**.
   PR-42 hanya mempertahankan parent-lock R2-26.
3. Tech Debt 3 (dokumen `PR-42-*`) → **PERTAHANKAN** sampai merge;
   bereskan pasca-merge agar riwayat review tidak hilang.
4. Minor scope (pindah-unit Plan 2.8/ISS-02.05) → **diterima apa adanya**,
   tanpa bongkar implementasi stabil.
5. Tech Debt 4 (rollback lifecycle) → keputusan lama berlaku:
   strict-THROW, reviewer sudah diberi tahu di body PR.

Kontrak yang mengikat R3-01 (jangan diubah di task ini):

- `document/SAKIP - Data Model.md` §2.12 + §2.27: `rasio_persen` wajib
  ≥1 `pembilang` aktif + tepat 1 `penyebut` aktif; `penjumlahan` wajib
  ≥1 `penjumlah` aktif; **penyimpanan indikator dengan tipe itu ditolak
  bila syarat tidak terpenuhi**. `manual` tidak memakai komponen angka.
- `document/SAKIP - Plan Pengembangan.md` §2.15 (DoD validasi) + §2.14
  (guard `manual` di KomponenController).
- `document/SAKIP_ENGINEERING_STANDARDS.md` §2 (controller adapter tipis →
  Action; tanpa operasi setengah jadi), §4, §5 (transaksi + lock), §7
  (audit), §11, §12.
- `CONTEXT.md` (glosarium Indikator/Unit/Sasaran).
- Pola existing: `UpdateIndikator` 2c lock Regulasi(S) → Indikator(X) →
  Sasaran(S); `IndikatorKomponenController` parent(X) → child; stale token
  wajib + fail-closed (R2-22/R2-27); grandfather regulasi (R2-24).

## Task handoff (satu session = satu task)

### R3-01 · [MAJOR] Transisi formula atomik — hapus transien invalid (Opsi A)

- [x] Status: done
- Untuk apa: hapus pengecualian R2-26 (`manual→nonmanual` dilewati
  validasi) yang menghasilkan persisted state resmi
  `tipe=rasio_persen/penjumlahan + components=[] + is_valid=false`,
  bertentangan dengan Data Model §2.12/§2.27. Kembalikan invariant
  penuh + sediakan SATU jalur transisi atomik agar workflow tidak
  kembali buntu seperti R2-25.
- Yang dibuat (minimal, tanpa migrasi, tanpa ADR baru):
  1. `app/Actions/Perencanaan/UpdateIndikator.php` §4c: HAPUS cabang
     `$manualKeNonmanual` (lewati validasi). Setiap perubahan
     `tipe_perhitungan` — termasuk `manual→nonmanual` — WAJIB dinilai
     via `IndikatorPerhitunganService::validateDefinisiKomponen`
     pada state kandidat (clone + relasi existing) sebelum `update`;
     gagal → 422 `tipe_perhitungan` + messages service. Tipe tak
     berubah tetap dilewati tanpa overhead (perilaku R2-25).
  2. Audit `StoreIndikator`: pastikan TIDAK ada jalur create
     nonmanual-invalid. Opsi yang diterima (pilih satu, terkecil yang
     menutup lubang): (a) tolak create `rasio_persen/penjumlahan`
     tanpa komponen lengkap via validasi yang sama (karena baris baru
     selalu tanpa komponen), dengan pesan mengarahkan buat `manual`
     dulu lalu transisi atomik; ATAU (b) kunci create ke `manual`
     bila Request mengizinkan nonmanual. Jangan ubah provenance/
     lock/regulasi existing. Catat pilihan di Bukti.
  3. Sediakan SATU use-case transisi atomik tipe + komponen dalam satu
     transaksi (nama mengikuti domain existing, mis.
     `ChangeIndicatorFormula` atau perluasan terkontrol
     `UpdateIndikator` + payload komponen — pilih yang paling kecil
     dan konsisten Standards §2; JANGAN dua pola sekaligus):
     lock `Indikator FOR UPDATE` (urutan kunci global tetap:
     Regulasi(S) → Indikator(X) → Sasaran(S); tanpa inversi vs
     KomponenController parent→child); kandidat = tipe baru +
     konfigurasi komponen final (existing + payload); nilai penuh via
     service; gagal → 422 tanpa mutasi/audit sukses; sukses → update
     tipe + upsert komponen + audit (`indikator.ubah` + audit
     komponen mengikuti pola existing) dalam SATU commit. Re-auth
     (`ResolveLockedActor`), stale-token, dan grandfather regulasi
     tetap berlaku bila tersentuh.
  4. `IndikatorKomponenController` guard `manual` DIPERTAHANKAN
     (langsung tambah/ubah komponen saat `manual` tetap 422) —
     transisi hanya via jalur atomik (3). Komentar merujuk jalur
     atomik, tanpa pindah orchestration ke Action (itu backlog
     ISS-02.06).
  5. Frontend minimal: edit umum TIDAK boleh menyimpan nonmanual
     invalid. Opsi terkecil: (a) nonaktifkan opsi nonmanual pada
     `IndikatorModal` edit bila komponen belum lengkap + helper text
     mengarahkan ke alur transisi atomik; ATAU (b) sambungkan modal
     ke endpoint atomik bila (3) menyediakan UI siap. Pesan 422
     `tipe_perhitungan` tampil apa adanya. `bun run typecheck` hijau.
     Tanpa token design-system baru, tanpa `dark:*`, tanpa raw hex
     (lihat `document/design-system.md`).
  6. Test (konsistensi dulu: pola `SasaranIndikatorTest.php` + file
     Stale/LockOrder existing; JANGAN buat file test baru kecuali
     menghindari konflik tulis bersamaan):
     - `manual→rasio_persen` tanpa komponen → 422 + tipe lama utuh +
       tanpa `indikator.ubah`.
     - `manual→penjumlahan` tanpa komponen → 422 (sama).
     - transisi atomik `manual→rasio + pembilang + penyebut` → sukses
       + contract valid; `manual→penjumlahan + penjumlah` → sukses.
     - `rasio berkomponen→manual` tetap 422 + arahan Kelola Komponen
       (regresi R2-26 dipertahankan).
     - create `rasio_persen` tanpa komponen → ditolak/dikunci sesuai
       pilihan (2) + tanpa baris tersimpan.
     - Tulis ulang 2 test R2-26 yang menegaskan transien-invalid lolos
       (`test_r226_manual_ke_rasio...`, `test_r226_manual_ke_penjumlahan...`)
       menjadi penolakan; pertahankan asersi konkurensi parent-lock.
- Standards: §2, §4, §5, §7, §11. Skill: `laravel-best-practices`
  (Action tipis, validasi, lock) + `testing-best-practices` (Wajib
  sebelum tulis test: coverage perilaku + failure mode, fixture
  eksplisit, isolasi DB disposable).
- Verifikasi: `php vendor/bin/pint --test` file tersentuh;
  `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress --memory-limit=1G`
  0 errors; `bun run typecheck` + `bun run test` hijau; Pest focused +
  regresi modul (`SasaranIndikator`, `Stale`, `LockOrder`,
  `KomponenHttp`, `Policy`) hijau di PG disposable (JANGAN DB dev;
  hapus container setelah run).
- DoD: tidak ada jalur simpan (create/update) yang meninggalkan
  `nonmanual + komponen tak lengkap` persisted; `validateDefinisiKomponen`
  adalah satu-satunya penentu validitas; satu jalur atomik hijau
  end-to-end; grep `manualKeNonmanual` nihil di `app/`;
  `formulaContract.is_valid=false` tidak pernah muncul pada baris
  tersimpan nonmanual.
- Selesai: 2026-10-02 | Bukti: Opsi A atomik tegak. File tersentuh: `app/Actions/Perencanaan/UpdateIndikator.php` (§4c hapus `$manualKeNonmanual`, semua perubahan tipe via `validateDefinisiKomponen`), `app/Actions/Perencanaan/StoreIndikator.php` (pilihan Store (a): tolak create nonmanual-invalid via service yang sama + arahan manual-dulu-lalu-atomik), BARU `app/Actions/Perencanaan/ChangeIndicatorFormula.php` + `app/Http/Requests/Indikator/ChangeIndicatorFormulaRequest.php` + `app/Http/Controllers/Perencanaan/ChangeIndicatorFormula.php`, endpoint atomik `PATCH /perencanaan/indikator/{indikator}/formula` (`perencanaan.indikator.formula`, lock Regulasi(S)→Indikator(X)→Sasaran(S), parent→child, stale-token + re-auth dua izin), `app/Http/Controllers/Indikator/IndikatorKomponenController.php` (guard manual dipertahankan + komentar rujuk atomik), `resources/js/Pages/Perencanaan/SasaranIndikator/IndikatorModal.tsx` (opsi nonmanual disabled saat edit manual + helper atomik, 422 tampil apa adanya), `routes/web.php`, `tests/Feature/Perencanaan/SasaranIndikatorTest.php` (2 test R2-26 ditulis ulang jadi 422 + 5 test R3-01 baru; `test_2` create dialihkan ke manual). Gate: `pint --test` PASS (file tersentuh); `phpstan` 0 errors; `bun run typecheck` hijau; `bun run test` 26 file/145 test PASS. Pest PG disposable baru (container `sakip_test_r301`, port 5455, DB sakip_test): focused r301 5/5 (31 asersi), r226 4/4 (31 asersi), `SasaranIndikatorTest` 63/63 (363 asersi), regresi Stale+LockOrder+KomponenHttp+Policy 33/33 (186 asersi); dev DB sakip_db:5433 tak tersentuh; container dihapus pasca-run. Grep DoD `manualKeNonmanual` nihil di `app/`. HEAD `99234d7`, branch `feature/iss-02-04-sasaran-indikator`, tanpa commit.

```text
Prompt handoff R3-01:
Kerjakan R3-01 dari document/PR-42-Review3-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Tegakkan Opsi A atomik sesuai
detail task (hapus pengecualian manual→nonmanual, audit Store,
sediakan satu jalur transisi atomik + frontend minimal + tulis ulang
test transien). Ikuti Standards §2,§4,§5,§7,§11 + skill
laravel-best-practices + testing-best-practices. Update checkbox +
Bukti. JANGAN commit. JANGAN sentuh tasks/, .agents/, .claude/,
*.json tooling, composer.json/lock. Test backend hanya di PG
disposable baru (lihat PR-42-CI-Test-Context.md §3); DB dev 5433
tak tersentuh.
```

### R3-02 · [MINOR] Scope Plan 2.8 / ISS-02.05 — diterima, tanpa aksi

- [x] Status: closed tanpa kode (keputusan user 2026-10-02).
- Catatan: `PATCH pindah-unit` + `PindahUnitModal` tetap di PR ini;
  implementasi coherent (edit umum tolak unit beda, endpoint khusus,
  audit `indikator.pindah_unit`). Reviewer eksplisit tidak menyarankan
  bongkar. 42 commits / 80 files / >10k additions dicatat sebagai
  konteks review, bukan DoD.

### R3-03 · Tech Debt 1 — controller-heavy komponen → BACKLOG ISS-02.06

- [ ] Status: backlog (keputusan user 2026-10-02, di luar PR-42).
- Catatan: `IndikatorKomponenController@store/update/destroy` tetap
  memegang transaksi + parent-lock + guard + audit (benar secara
  concurrency R2-26, belum ideal Standards §2). Ekstraksi ke
  `Store/Update/DestroyIndikatorKomponen` Action dikerjakan di
  ISS-02.06, bukan PR ini.

### R3-04 · Tech Debt 2 — test monolith → BACKLOG-02 (konfirmasi)

- [ ] Status: backlog (sudah BACKLOG-02 sejak R2-10, dikonfirmasi
  2026-10-02). Usulan split reviewer selaras dengan daftar di
  `PR-42-Review2-Tracking.md` BACKLOG-02
  (`SasaranCrudTest`, `IndikatorCrudTest`, `IndikatorAuthorizationTest`,
  `IndikatorRegulasiTest`, `IndikatorLifecycleTest`,
  `IndikatorUnitTransferTest`, `IndikatorFormulaTransitionTest`,
  `IndikatorProvenanceTest`, `IndikatorConcurrencyTest`).
  R3-01 tetap menambah test pada file existing (konsistensi dulu).

### R3-05 · Tech Debt 3+4 — docs + rollback, tanpa aksi kode

- [x] Status: closed tanpa kode (keputusan user 2026-10-02).
- Docs (`PR-42-CI-Test-Context`, `PR-42-Review2-Tracking`,
  `PR-42-Task-Tracking`, file ini, `TASK-REGISTRY`) dipertahankan
  sampai merge; bukan source-of-truth permanen pasca-merge.
- Rollback lifecycle tetap strict-THROW (fail-closed); kebijakan
  forward-only/manual-restoration sudah di body PR; bukan bug.
