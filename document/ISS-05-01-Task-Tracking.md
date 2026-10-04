# ISS-05.01 — Tracking Penyusunan Target Rencana Aksi per Periode (base `development`)

Sumber: `ISS-05.01` (US-05.01, 8 SP, P0) — Plan Modul 11: 11.1, 11.2,
11.5, 11.6; PRD §14; Workflow §7; Data Model §2.23–2.24; Keputusan
Penyelarasan Q7/Q20; CONTEXT.md; `document/SAKIP_ENGINEERING_STANDARDS.md`.

> Aturan update: session pelaksana ubah `[ ]` → `[x]` + isi Bukti.
> Main session verifikasi lalu update `document/TASK-REGISTRY.md`.
> Satu session = satu task (F-01..F-07 berurutan; JANGAN paralel —
> tiap fase membangun di atas fase sebelumnya).
> SELURUH fase dikerjakan di SATU branch yang sama:
> `feature/iss-05-01-target-rencana-aksi` (dari `development@0a1426e`),
> SATU pull request untuk seluruh ISS-05.01 di akhir (setelah F-07
> hijau). Jangan buat branch per fase; jangan commit/push tanpa
> perintah eksplisit.
> DB dev TIDAK BOLEH di-reset/disentuk; test backend hanya di
> PostgreSQL disposable baru (pola `PR-42-CI-Test-Context.md` §3).

## Batas scope (jangan melebar)

IN: 11.1 (header + unique), 11.2 (target per periode per komponen +
skor turunan server + 0-vs-null), 11.5 (warning turun non-blokir),
11.6 (warning + alasan wajib deviasi vs PK — tempat alasan di D5).
OUT (issue lain): 11.3 alur ajukan (ISS-05.03), 11.4 bagian `ajukan`
+ `buka_kembali`, 11.7, 11.8, bukti dukung (ISS-05.02),
`rencana_aksi_versi`-freeze (ISS-05.03). Guard jendela create/update
(AC-5) BOLEH karena menempel mutasi fase ini; gerbang kelengkapan
pengajuan (11.3/§2.24-gate) DILARANG di sini.

## Task handoff

### F-01 · Keputusan D1–D7 + ADR bila menyimpang

- [x] Status: selesai (D1–D7 PUTUS + ADR 0004/0005; tanpa kode)
- Untuk apa: mengunci D1–D7 dari analisis main session sebelum
  eksekusi, agar tak terulang putaran review PR-42.
- Yang dibuat:
  1. Putuskan D1 (`komponen_id` NULL untuk manual — deviasi Data
     Model §2.24, catat alasan Q7 "tanpa komponen semu"), D2
     (resolver himpunan-komponen-efektif: snapshot bila jadwal aktif
     else master), D3 (resolver periode-efektif: `jadwal_periode`
     minus pra-`tahun_mulai_berlaku`/Q20), D4 (guard jendela pola
     pengukuran), D5 (kolom alasan deviasi di header RA), D6
     (`versi` int+1 sesuai Data Model, FE kirim `expected_versi`),
     D7 (buang `jadwal_snapshot_id` dari stub).
  2. Tulis/ubah ADR hanya untuk deviasi vs Data Model (D1, D5 bila
     kolom baru); sisanya cukup catatan di Bukti + komentar domain
     di kode nanti (tanpa nomor issue di source, Standards §1).
- DoD: tiap D1–D7 berstatus PUTUS dengan rujukan dokumen; ADR (bila
  ada) di `docs/adr/`.
- Selesai: 2026-10-04 | Bukti: D1–D7 PUTUS. D1 PUTUS `komponen_id` NULL satu-baris-per-periode untuk manual tanpa komponen semu (deviasi Data Model §2.24+ERD; Q7; PRD §14.3; Plan 11.2; Workflow §7) → ADR `docs/adr/0004-rencana-aksi-target-manual-null.md`. D2 PUTUS himpunan-komponen-efektif = snapshot-bila-jadwal-aktif else master (Data Model §2.17–§2.18; PRD §12.5/§17; Workflow §5–§6; Plan 11.2) catatan saja. D3 PUTUS periode-efektif = `jadwal_periode` minus pra-`tahun_mulai_berlaku`/Q20 = Tidak berlaku, bukan missing/nol/null (Q20; PRD §10.5/§14.7/§23; Data Model §2.15–§2.16 + ADR 0003; gerbang 11.3 hanya atas himpunan efektif) catatan saja. D4 PUTUS guard jendela pola pengukuran — PIC mutlak `rencana_aksi_mulai/selesai`, Perencanaan global sampai `penutupan` (Data Model §2.15/§2.23; PRD §12.2–§12.3; Workflow §7; Plan 11.4; Q16/Q18; Q32) catatan saja. D5 PUTUS kolom baru `alasan_deviasi_pk` (text nullable) di header, `alasan_revisi` tidak dipakai ulang (PRD §14.5; Workflow §7; Data Model §2.23–§2.24; Plan 11.6) → ADR `docs/adr/0005-rencana-aksi-alasan-deviasi-pk.md`. D6 PUTUS `versi` int+1, FE kirim `expected_versi`, stale → 409 muat-ulang (Data Model §2.23 + integritas §27; PRD §27; Standards §5) catatan saja. D7 PUTUS buang `jadwal_snapshot_id` dari stub `RencanaAksi`, tabel snapshot tetap untuk D2 (Data Model §2.23/PRD §14.2 tanpa kolom tsb vs stub; Data Model §2.17–§2.18 tetap) catatan saja. Presedens Q32→ADR→Standards→Plan 11→Workflow §7→Model §2.23–2.24→PRD §14. Branch `feature/iss-05-01-target-rencana-aksi` @`0a1426e`; tanpa commit; DB dev utuh; verifikasi baca saja.

```text
Prompt handoff F-01:
Kerjakan F-01 dari document/ISS-05-01-Task-Tracking.md di branch
ISS-05.01 (base development). Putuskan D1-D7 + tulis ADR bila
menyimpang dari Data Model. Ikuti CONTEXT.md + Standards §1.
Update checkbox + Bukti. JANGAN commit.
```

### F-02 · Persistensi: migrasi + model + factory/seeder

- [x] Status: selesai (setelah F-01)
- Untuk apa: tabel + model + data uji sesuai keputusan F-01.
- Yang dibuat:
  1. Migrasi BARU `rencana_aksi` (kolom §2.23 + kolom alasan deviasi
     D5; `status_alur` default `draft`, `versi` default 1;
     unique(`indikator_id`,`tahun`); FK + down() aman) dan
     `rencana_aksi_target` (`komponen_id` NULL-able sesuai D1;
     `nilai` nullable; unique(`rencana_aksi_id`,`periode_id`,
     `komponen_id`); FK + down() aman). Konstanta beku lokal, tanpa
     import `App\...` (pola R2-04b/R2-09).
  2. Model `RencanaAksi`/`RencanaAksiTarget` penuh (relasi, casts,
     scope; selaraskan stub + D7), factory + seeder fixture
     (deterministik, pola R2-16; JANGAn seed data produksi).
- Standards: §5, §11. Verifikasi: fresh migrate + seed di DB
  disposable; `pint` + `phpstan` hijau.
- DoD: constraint DB terbukti menolak duplikat (2 test migrasi);
  factory hijau dipakai fase berikut.
- Selesai: 2026-10-04 | Bukti: migrasi BARU `2026_10_04_043803_align_rencana_aksi_header_d1_d5_d7` (tanpa sentuh migrasi lama; konstanta beku lokal, tanpa import App, hanya Schema/DB — pola R2-04b/R2-09): tambah `alasan_deviasi_pk` text nullable (D5/ADR 0005); drop `jadwal_snapshot_id` dari header via backup eksak `_backup_rencana_aksi_jadwal_snapshot_20261004` + down() fail-closed (D7; tabel snapshot tetap untuk D2); pastikan dua partial unique index NULL-aware target (D1/ADR 0004, preseden klaim_kegiatan) idempoten via IF NOT EXISTS, down() membiarkannya (milik migrasi dasar). Target tak perlu ALTER (base `2026_09_18_030002` sudah nullable + partial unique + FK restrict). Model `RencanaAksi` penuh (HasFactory; konstanta status; fillable tanpa snapshot + alasan deviasi; $attributes draft/1 cermin default DB; relasi indikator/unit/jadwalTahunan/penanggungJawab/creator/disahkanOleh/targets/versions/latestVersion; scope draft/disahkan pola BuktiDukung) + `RencanaAksiTarget` penuh (casts nilai decimal:12 pola PengukuranKomponen; relasi rencanaAksi/periode/komponen-nullable/updatedBy pola Pengaturan; scope manual/berkomponen). Factory `RencanaAksiFactory`/`RencanaAksiTargetFactory` (induk eksplisit pemanggil + bawaan deterministik; updated_at now). Seeder `RencanaAksiFixtureSeeder` standalone deterministik pola R2-16 (creator lookup email tetap + peran katalog; sync indikator create-only vs mutable; header historis tak ditulis ulang; target manual firstOrCreate per periode agar nilai pengguna tak tertimpa; TIDAK terdaftar di DatabaseSeeder). Penyelarasan minimal akibat D7 di luar model: hapus klausa snapshot-header pada `SubmissionPrerequisites` + `PresentPengukuran` (cek versi-sah + unit dipertahankan; tanpa logika baru — phpstan menuntut hijau). Test baru `tests/Feature/RencanaAksi/RencanaAksiPersistenceTest.php` 2 test (header duplikat indikator×tahun → 23505; target duplikat manual-NULL + komponen → 23505; tiap upaya dalam savepoint agar abort PG tak meracuni transaksi luar). Verifikasi PG disposable BARU podman `postgres:17-alpine` port 5445 (DB/user `sakip_test`; dev `sakip_db:5433` tak tersentuh, tak pernah dinyalakan): fresh migrate 28/28 + DatabaseSeeder OK; fixture-seed 2× idempoten OK (1 RA + 4 target; kolom alasan ada, snapshot nihil); rollback-1 + migrate ulang OK (down jujur di DB kosong); `pint --test` passed (10 file); `phpstan` 0 errors; Pest sempit `RencanaAksiPersistenceTest` 2 passed/9 assertions. Keterbatasan: full suite tak dijalankan (narrowest F-02 saja); container disposable dihapus setelah run. Belum di-commit.

```text
Prompt handoff F-02:
Kerjakan F-02 dari document/ISS-05-01-Task-Tracking.md (setelah
F-01). Migrasi + model + factory/seeder sesuai detail task. Ikuti
Standards §5,§11 + skill laravel-best-practices. Update checkbox +
Bukti. JANGAN commit. Test hanya di PG disposable (dev utuh).
```

### F-03 · Domain tulis: header auto-draft + simpan target + guard

- [x] Status: selesai (setelah F-02)
- Untuk apa: use-case penyusunan (AC-1/2/4/5/6, TEST-1/2/4/5/6).
- Yang dibuat (`app/Actions/RencanaAksi/`, tipis-controller +
  FormRequest, pola PR-42):
  1. `EnsureDraftRencanaAksi` (auto-buat header `draft`:
     unit-id snapshot dari indikator terkunci, jadwal aktif,
     PIC-efektif saat itu sebagai `penanggung_jawab_id`, tolak
     indikator `arsip` via `IndikatorArsipGuard`, tolak tanpa
     jadwal aktif; idempoten via unique).
  2. `SimpanTargetPeriode` (transaksi terkunci: re-auth
     `ResolveLockedActor`, lock header/rows, kandidat final →
     nilai turunan server via `evaluate`, NULL vs 0, periode
     non-efektif D3 ditolak/dikecualikan, guard jendela D4 422
     PIC-tanpa-izin vs Perencanaan-sampai-penutupan, `versi`+1 +
     stale-`expected_versi` 409, audit `rencana_aksi.ubah` +
     denied di luar transaksi).
  3. Manual: satu baris `komponen_id=NULL` per periode (D1);
     nonmanual: per komponen-efektif D2; skor turunan TIDAK
     disimpan (tampilan saja).
- Test: TEST-1/2/4/5 (+ stale + arsip-guard). Standards:
  §2–§5, §7, §11 + `laravel-best-practices` +
  `testing-best-practices`.
- DoD: tiap AC di atas hijau; direct request tanpa izin → 403.
- Selesai: 2026-10-04 | Bukti: Action `app/Actions/RencanaAksi/EnsureDraftRencanaAksi.php` (lock User-ACL via ResolveLockedActor lalu Indikator→Jadwal→Penugasan→RencanaAksi; guard arsip; jadwal aktif renstra×tahun status aktif; PIC-efektif today Asia/Makassar; idempoten first-check + tangkap 23505; audit buat/buat_ditolak) + `app/Actions/RencanaAksi/SimpanTargetPeriode.php` (lock User→Header→Indikator→Jadwal→Snapshot→Komponen→PeriodeJadwal→Target; decide ulang unit-scope header.unit_id; arsip-guard; status draft/dikembalikan; expected_versi 409; D2 snapshot-bila-aktif else master; D3 jadwal_periode minus periode_mulai snapshot + tolak pra-berlaku; D4 PIC mutlak rencana_aksi_mulai/selesai + PIC-efektif vs Perencanaan sampai penutupan/koreksi-sah; kandidat per-periode wajib lengkap per himpunan efektif; turunan via CalculatePengukuran tanpa disimpan; NULL vs 0 via present-nullable-numeric; versi+1; audit ubah/ubah_ditolak luar tx). Request `app/Http/Requests/RencanaAksi/EnsureDraftRencanaAksiRequest.php` + `SimpanTargetPeriodeRequest.php` (authorize user-nonnull, sintaks + prohibited server-fields). Controller tipis `app/Http/Controllers/RencanaAksi/StoreRencanaAksiDraft.php` + `UpdateRencanaAksiTarget.php` (validated → Action → redirect back). Route POST `/rencana-aksi/ensure-draft` + POST `/rencana-aksi/{rencanaAksi}/target` whereUuid. Test `tests/Feature/RencanaAksi/RencanaAksiTargetTest.php` 6 test (manual-NULL, nonmanual-per-komponen + 0-vs-null + penyebut-0 tersimpan, D3 non-efektif ditolak, D4 PIC-422 vs Perencanaan-lolos, stale-409 via postJson + 403 tanpa-izin + audit ditolak, arsip-guard 422). Perbaikan ikut: migrasi F-02 `2026_10_04_043803` tambah penggantian fungsi `guard_referenced_schedule_snapshot()` tanpa rujukan `rencana_aksi.jadwal_snapshot_id` (D7; up fix, down restore) karena trigger lama merujuk kolom yang sudah di-drop sehingga INSERT/UPDATE snapshot selalu 42703. Verifikasi: `php vendor/bin/pint --dirty` passed; `php vendor/bin/phpstan analyse` passed 0 errors; Pest disposable baru `sakip_test_f03` 127.0.0.1:5452 (user/db sakip_test) `tests/Feature/RencanaAksi/` 8 passed (2 persistensi + 6 F-03) 55 assertions. DB dev utuh (container dev exited, tak disentuh; test hanya disposable). Tanpa commit.

```text
Prompt handoff F-03:
Kerjakan F-03 dari document/ISS-05-01-Task-Tracking.md (setelah
F-02). Domain tulis + guard + test sesuai detail task. Ikuti
Standards §2-§5,§7,§11 + skill laravel/testing-best-practices.
Update checkbox + Bukti. JANGAN commit. Test hanya di PG disposable.
```

### F-04 · Baca + preview: matriks periode × komponen + warning + deviasi

- [x] Status: selesai (setelah F-03)
- Untuk apa: halaman butuh satu payload lengkap (AC-3/6, TEST-3/6).
- Yang dibuat: `IndexRencanaAksi` Action (header + periode-efektif
  + target existing + estimasi skor per periode via `evaluate` +
  flag warning turun-per-periode (11.5, data-bukan-blokir) +
  deviasi periode-terakhir vs target PK + `can.*`) + controller +
  route. Skor turunan DILARANG dihitung di React.
- Test: TEST-3 (warning tanpa blokir) + TEST-6 (deviasi tanpa alasan
  vs dengan alasan — tempat alasan D5; submit di sini = simpan,
  bukan ajukan-11.3).
- DoD: payload memuat semua yang dibutuhkan FE tanpa query susulan.
- Selesai: 2026-10-04 | Bukti: Action `app/Actions/RencanaAksi/IndexRencanaAksi.php` (read-only tanpa lock/transaksi; D2/D3 cermin `SimpanTargetPeriode` tanpa kunci — snapshot-bila-aktif else master, periode-efektif minus pra-berlaku = `tidak_berlaku`; skor per periode via `CalculatePengukuran`, gagal-hitung → `tidak_dapat_dihitung` tanpa 500; warning 11.5 per-komponen vs periode-efektif-sebelumnya hanya bila keduanya numerik — null = belum-diisi, data-bukan-blokir; deviasi 11.6 = skor periode-efektif-terakhir vs salinan beku `jadwal_snapshot.target` (AC-6 "target PK snapshot") toleransi `bccomp(presisi)`, tak-dapat-dinilai → alasan tak-diperlukan; `can.view/update` via `PermissionResolver` — Policy menyusul F-05; tanpa `editability`/jendela di payload agar gerbang tulis tak ganda) + controller `ShowRencanaAksi` (findOrFail 404 + `rencana_aksi:read` unit-scope 403 pola `ShowVerifikasi`, render `RencanaAksi/Show` — halaman React milik F-06) + route GET `/rencana-aksi/{rencanaAksi}` (`rencana-aksi.show`, whereUuid). Test `tests/Feature/RencanaAksi/RencanaAksiIndexTest.php` 4 test (TEST-3 manual turun→flag+tetap-tersimpan, sejajar/null→tanpa-flag; TEST-6 deviasi tanpa-alasan vs berisi-alasan tersimpan-status-tetap-draft vs setara-tanpa-alasan; nonmanual skor-terhitung/warning-per-komponen/penyebut-0→tak-dapat-dihitung+deviasi-tak-dapat-dinilai; 403-tanpa-izin + can.read-only-vs-penuh). Verifikasi: Pest `tests/Feature/RencanaAksi` 12/12 (245 assertions) di PG disposable `127.0.0.1:5452` DB `sakip_test` (F-03 6/6 ikut hijau; DB dev utuh); `pint --dirty` passed; `phpstan` 0 errors (2 iterasi: perbaiki `sortBy` multi-callback → kunci komposit + PHPDoc generik → plain `Collection` pola F-03). Tanpa commit.

```text
Prompt handoff F-04:
Kerjakan F-04 dari document/ISS-05-01-Task-Tracking.md (setelah
F-03). Read/preview + test sesuai detail task. Standar dan aturan
sama seperti F-03. Update checkbox + Bukti. JANGAN commit.
```

### F-05 · Authorization sweep: scope/deny/PIC-efektif (backend only)

- [x] Status: selesai (setelah F-04)
- Untuk apa: SECURITY issue + matriks Q13/Q14/Q32 (unit-scope PIC
  vs global Perencanaan, deny menang, PIC-efektif vs historis —
  TANPA 11.8 penuh, cukup hak-tulis-mengikuti-PJ-berlaku).
- Yang dibuat: Policy `RencanaAksiPolicy` + allow-then-deny test
  per mutation (pola R2-05): cabut grant/deny di tengah → 403 +
  audit penolakan; lintas-unit → 403; Perencanaan global lolos
  tanpa scope; jendela AC-5 kedua jalur (TEST-5); PIC berganti →
  A ditolak/B lolos, header tetap A.
- DoD: SECURITY hijau; tidak ada jalur grant-bypass.
- Selesai: 2026-10-04 | Bukti: Policy `app/Policies/RencanaAksiPolicy.php` baru (pure-resolver + audit tepi buat/ubah_ditolak; viewAny global, view/create/update unit-scope via `PermissionResolver::resolve`; Q13 PIC-grant vs Perencanaan-peran-global, Q14 deny-menang, Q32 PIC-bukan-role; tanpa 11.8/ajukan) + registrasi `Gate::policy(RencanaAksi::class)` di `AppServiceProvider` + `EnsureDraftRencanaAksiRequest::authorize` via `Gate::allows('create',[RencanaAksi::class,indikator])` (UUID-hilang→validasi) + `SimpanTargetPeriodeRequest::authorize` via `Gate::allows('update',header)` + `ShowRencanaAksi` via `Gate::authorize('view')` (ganti `abort_unless` resolver; 404-sebelum-403 dipertahankan). Action tetap re-auth transaksi (`ResolveLockedActor` + `decide` unit header/indikator terkunci) sehingga Gate-lolos + cabut-tengah →403 + audit transaksi; `IndexRencanaAksi::can` tetap resolver (keputusan identik Policy, read-only). Test baru `tests/Feature/RencanaAksi/RencanaAksiAuthorizationTest.php` 9 test (create/update allow-then-deny mock call#1-allow→call#2+deny `revoked_inside_transaction` →403 + versi/mutasi nihil + audit ditolak + callCount≥2; cabut-grant-nyata →403 + audit; deny-menang atas grant/peran untuk create/update/read; lintas-unit →403 dua mutasi; Perencanaan-tanpa-grant lolos create/update/read; jendela PIC-422 vs Perencanaan-lolos; PIC-berganti A-422/B-lolos header-tetap-A; baca admin-403/pimpinan-ok/deny-403). Verifikasi: Pest `tests/Feature/RencanaAksi` 21/21 (297 assertions) di PG disposable `127.0.0.1:5452` DB `sakip_test` (9 baru + F-03 6/6 + F-04 4/4 + persistensi 2/2 ikut hijau; DB dev utuh, `sakip_db:5433` exited); `pint --dirty` passed (1 reorder import); `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff F-05:
Kerjakan F-05 dari document/ISS-05-01-Task-Tracking.md (setelah
F-04). Policy + allow-then-deny sweep sesuai detail task. Standar
dan aturan sama. Update checkbox + Bukti. JANGAN commit.
```

### F-06 · Frontend: matriks target + warning + deviasi

- [x] Status: selesai (setelah F-05)
- Untuk apa: UX penyusunan (D-Frontend issue + 11.2/11.5/11.6 DoD).
- Yang dibuat: halaman + komponen reusable (tabel periode ×
  komponen; manual = kolom langsung; nonmanual = per komponen +
  skor turunan read-only reaktif dari payload/preview server;
  warning turun + deviasi-vs-PK + alasan; `useForm`; loading/
  error/konflik; token DS; tanpa raw hex/`dark:*`; mobile tanpa
  overflow) + Vitest (11.2 reaktif + 11.5 warning-tanpa-blokir).
- Skill: `inertia-react-development` + `tailwindcss-development`
  + Standards §9 + design-system.
- DoD: `typecheck` + `bun run test` hijau; 0-vs-null terdiferensiasi
  di tampilan.
- Selesai: 2026-10-04 | Bukti: Halaman `resources/js/Pages/RencanaAksi/Show.tsx` (key=id cegah draft bocor; `useForm` expected_versi/uraian/alasan_deviasi_pk/nilai-map + `transform` → targets periode×komponen-efektif, nilai `""`→null, keterangan dipertahankan; skor/deviasi/warning murni payload server tanpa hitung React; `can.update` read-only; Tidak-berlaku disabled; 0=`"0"`+helper vs null=`""`+`Belum diisi`; deviasi D5 warning non-blokir; ringkasan galat focus + konflik expected_versi + reload `router.get` + requestError cancel/network/403; `useFormatNilai`/`useFormatTanggal`; token DS, `overflow-x-auto min-w-[680px]`, tanpa raw-hex/`dark:*`) + reusable `MatriksTarget.tsx` (thead periode×komponen + skor-server + status; manual satu-kolom, nonmanual per-komponen + `skor-*`/`peringatan-*` testid) + `types.ts` (kontrak `IndexRencanaAksi`, `kunciSel`, status label). Tanpa ubah backend (glue `rencana-aksi.show`/`target.update` existing cukup). Vitest `tests/Frontend/RencanaAksiMatriks.test.tsx` 4/4 (11.2 skor-tetap-50.00-setelah-edit + tanpa-field-skor-di-payload; 11.5 warning-tampil-tombol-tetap-aktif; 0-vs-null + tidak-berlaku-tak-dikirim; 409-focus + draf-dipertahankan + reload). Verifikasi: `bun run typecheck` 0 error; `bun run test` 36 file/247 test hijau (4 baru inklusif); `bun run lint` TERBLOKIR environment (`Cannot find module 'isexe'`); grep `dark:|#[0-9a-fA-F]|as any|role===` nihil di `RencanaAksi/`; DB dev utuh, tanpa commit.

```text
Prompt handoff F-06:
Kerjakan F-06 dari document/ISS-05-01-Task-Tracking.md (setelah
F-05). Halaman + komponen + FE test sesuai detail task. Ikuti skill
inertia/tailwind + Standards §9 + design-system. Update checkbox +
Bukti. JANGAN commit.
```

### F-07 · Verifikasi akhir: CI-mirror + traceability + DoD issue

- [x] Status: selesai (setelah F-06)
- Untuk apa: buktikan DoD issue terpenuhi sebelum PR.
- Yang dibuat: CI-mirror 9 job (pola `PR-42-CI-Test-Context.md`;
  full Pest via CI bila timeout lokal) + matriks AC×TEST×bukti +
  pastikan tak ada klaim melanggar PRD/Workflow/Data Model/Plan/
  Penyelarasan/Stories baseline; catat sisa non-goal (11.3, 11.7,
  bukti, versi-freeze) eksplisit.
- DoD: semua AC + TEST-1..6 + SECURITY hijau (atau TERBLOKIR
  tercatat + CI Ubuntu penentu); siap PR.
- Selesai: 2026-10-04 | Bukti: CI-mirror 9 job — [1 Scope] PASS logika (`node --test .github/ci/changes.test.mjs` 4/4; `node .github/ci/changes.mjs` cetak `Jalur CI: full` lalu gagal tulis GITHUB_OUTPUT-undefined = CI-only env, ekspektasi; tanpa ubah kode). [2 Pint] PASS (`php vendor/bin/pint --test` full passed). [3 PHPStan] PASS (`analyse --no-progress --memory-limit=-1`, 0 errors). [4 Backend] PASS fokus + TERBLOKIR full: `tests/Feature/RencanaAksi` 21/21 (297 assertions) di PG disposable `127.0.0.1:5452` DB `sakip_test` (container `sakip_test_f03`; env override pgsql/sakip_test/sakip_test; DB dev utuh — `sakip_db` exited, `.env` dev `sakip/sakip_user` tak tersentuh); full `php artisan test --compact` TERBLOKIR timeout lokal 600s (warning openssl KeycloakTokenValidation pre-existing; penentu CI Ubuntu). [5 TS] PASS (`bun run typecheck` 0 error). [6 FE Test] PASS (`bun run test` 36 file/247 test, 4 RencanaAksi inklusif). [7 Build] PASS (`bun run build` 23.16s, hanya chunk-size warning). [8 Security] PASS parsial + TERBLOKIR: `bun audit` PASS (No vulnerabilities); `composer validate --strict` + `composer audit --locked` TERBLOKIR (binary `composer` tak ada di PATH, jangan install — gap vs CI sama seperti PR-42). [9 FE Lint] TERBLOKIR pre-existing (`bun run lint` → `Cannot find module 'isexe'` dari `node_modules/which`, identik F-06; tanpa fix; stash-proof dilewati sadar-risiko karena worktree F-01..F-06 untracked). Deviasi versi lokal vs CI: PHP 8.4.24 vs 8.3; Bun 1.3.6 vs 1.3.11; Node 24.11.1 vs 24.19.0. Matriks AC×TEST×bukti: AC-1/TEST-1 → `test_manual_menyimpan_satu_baris_null_per_periode` (header draft auto + idempoten + NULL-vs-nilai + tanpa `skor_turunan` tersimpan + audit ubah) + `test_target_menolak_duplikat_periode_komponen` (23505 NULL-aware) + FE `membedakan nol eksplisit dari belum diisi…` (0=`"0"` vs null=`""`+`Belum diisi`). AC-2/TEST-2 → `test_nonmanual_menyimpan_per_komponen_efektif_dan_nol_berbeda_dari_null` (per-komponen-efektif D2, turunan server via evaluate tanpa disimpan, 0-vs-null, penyebut-0) + FE `11.2 reaktif dari payload server tanpa hitung ulang di React` (skor-tetap-50.00-setelah-edit + tanpa-field-skor-di-payload). AC-3/TEST-3 → `test_peringatan_turun_tidak_memblokir_penyimpanan` (flag turun + tetap-tersimpan; sejajar/null tanpa-flag) + FE `11.5 peringatan turun tampil tanpa memblokir penyimpanan`. AC-4/TEST-4 → `test_periode_non_efektif_ditolak` (D3 jadwal_periode minus pra-berlaku = Tidak-berlaku) + FE `menonaktifkan periode tidak berlaku` + tak-dikirim. AC-5/TEST-5 → `test_jendela_pic_ditutup_tetapi_perencanaan_sampai_penutupan` + `test_jendela_pic_ditutup_tetapi_perencanaan_lolos` (PIC-422 vs Perencanaan-lolos) + `test_pic_berganti…` (A-422/B-lolos header-tetap-A). AC-6/TEST-6 → `test_deviasi_pk_butuh_alasan_dan_tersimpan_sebagai_simpan` (tanpa-alasan vs berisi-alasan tersimpan-status-tetap-draft vs setara-tanpa-alasan) + `test_nonmanual_menampilkan_skor_turunan_peringatan_dan_deviasi` (deviasi = skor periode-efektif-terakhir vs salinan beku `jadwal_snapshot.target`, toleransi bccomp; tak-dapat-dinilai → alasan tak-diperlukan); penegasan: submit-di-sini = simpan, BUKAN ajukan-11.3. SECURITY → 9 test `RencanaAksiAuthorizationTest` (allow-then-deny call#1-allow→call#2+deny →403 + versi/mutasi nihil + audit ditolak; cabut-grant-nyata →403; deny-menang; lintas-unit →403; Perencanaan-tanpa-grant lolos; baca admin-403/pimpinan-ok/deny-403) + `test_versi_stale_ditolak_409_dan_tanpa_izin_403` + `test_indikator_arsip_ditolak` + `test_baca_menolak_tanpa_izin_dan_menandai_can` + `IndexRencanaAksi::can` read-only-vs-penuh. Persistensi DoD → 2 test (`test_header_menolak_duplikat_indikator_tahun` + target-duplikat → 23505, savepoint per upaya). FE DoD → 4/4 `RencanaAksiMatriks.test.tsx` (11.2 + 11.5 + 0-vs-null/tidak-berlaku + 409-focus + draf-dipertahankan + reload). Baseline: tanpa klaim melanggar PRD §14, Workflow §7, Data Model §2.23–§2.24 (§2.15–§2.18 snapshot, §27 integritas), Plan 11.1/11.2/11.5/11.6, Q7/Q20 (D1/D3), Q13/Q14/Q32 (auth), US-05.01; grep nihil `ajukan|buka_kembali|rencana_aksi_versi|versi-freeze|bukti_dukung|semua_mode_wajib` di Actions/Controllers/Requests/Policy, nihil `editability|can.ajukan` di payload, nihil hitung `evaluate|CalculatePengukuran|rumus|formula` di React; D1→ADR 0004, D5→ADR 0005; `alasan_revisi` hanya fillable+factory-null existing (tak dipakai ulang, deviasi via `alasan_deviasi_pk`); `jadwal_snapshot_id` hanya FK tabel-anak `JadwalSnapshotKomponen` untuk D2 (header sudah drop D7); relasi `versions()/latestVersion()` model inert (tanpa logika freeze). Non-goal eksplisit (belum dikerjakan): 11.3 alur-ajukan + gerbang-kelengkapan-pengajuan (ISS-05.03), 11.7 buka-kembali, bukti-dukung (ISS-05.02/Modul 13), `rencana_aksi_versi`-freeze (ISS-05.03); guard jendela AC-5 termasuk (menempel mutasi), gerbang kelengkapan 11.3 dikecualikan. Tanpa commit/push.

```text
Prompt handoff F-07:
Kerjakan F-07 dari document/ISS-05-01-Task-Tracking.md (setelah
F-06). Verifikasi akhir + matriks traceability. Laporkan per gate
PASS/FAIL/TERBLOKIR. Update checkbox + Bukti. JANGAN commit.
```
