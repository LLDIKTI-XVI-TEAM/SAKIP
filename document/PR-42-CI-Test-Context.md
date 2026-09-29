# Konteks Session Baru — CI Test PR-42 (ISS-02.04)

> Tempel seluruh isi file ini sebagai pesan pertama di session baru.
> Session ini KHUSUS testing. Jangan ubah kode kecuali test memaksa
> (dan bila mengubah, catat di Bukti + jalankan ulang gate terdampak).

## 1. Misi

Jalankan quality gate CI untuk branch `feature/iss-02-04-sasaran-indikator`
(PR-42, `[ISS-02.04] Sasaran & Indikator`, base `development`) dan laporkan
hasil per gate: PASS / FAIL / TERBLOKIR (dengan sebab + bukti command).

Acuan perilaku: `document/SAKIP - PRD.md §11`, `Data Model §2.11-2.12`,
`US/ISS-02.04`, Q32, `SAKIP_ENGINEERING_STANDARDS.md` §11-§12,
`document/PR-42-Task-Tracking.md` (TASK-42-01 s/d 07 semua `[x]`),
`.github/workflows/ci.yml` (9 jobs).

## 2. Keadaan awal yang diketahui (dari session sebelumnya)

Perubahan milik session ini (uncommitted, di atas HEAD `9c28798`):

- Backend beku `jenis_agregasi` (TASK-42-01): dihapus dari
  `Store/UpdateIndikatorRequest` + `Store/UpdateIndikator`
  (DB default `terakhir` tetap); 2 test baru di
  `tests/Feature/Perencanaan/SasaranIndikatorTest.php`
  (`test_store_mengabaikan_...`, `test_update_mengabaikan_...`).
- Frontend modal (TASK-42-04): `jenis_agregasi` hilang dari
  `IndikatorModal.tsx`; input `desimal_tampilan` 0-4 ditambah.
- Dokumen: `CONTEXT.md` (baru), `docs/adr/0001-...md`,
  `docs/adr/0002-...md` (baru), `document/PR-42-Task-Tracking.md`.
- Session paralel menambah (sudah di working tree): guard
  `regulasi:read` pada write (TASK-02), provenance seeder (TASK-03),
  2 test regresi komponen/UUID (TASK-05).

Hasil verifikasi session lalu:

- `php -l` 4 file backend: OK.
- `php vendor/bin/pint --test` (5 file): passed.
- `phpstan analyse` (perlu `-d memory_limit=1G ... --memory-limit=1G`): passed, 0 errors.
- `bun run typecheck`: hijau. `bun run test`: 23 file / 129 test hijau.
- Pest TERBLOKIR: host `db` tak teresolusi (butuh PG disposable, lihat §3).
- `eslint` TERBLOKIR: `Cannot find module 'isexe'` (pre-existing,
  unrelated dengan PR-42).

## 3. Aturan environment (wajib, Standards §5/§11)

- JANGAN pakai database development. Container `sakip_db` (`5433`)
  adalah DB dev — bukan target reset.
- Buat PostgreSQL disposable BARU (mis. port `5434`), DB + user
  `sakip_test`, lalu verifikasi isolasi sebelum `RefreshDatabase`
  boleh jalan:
  `APP_ENV=testing`, tanpa config cache, `APP_KEY` khusus testing,
  koneksi `pgsql` → host/port container testing,
  `SAKIP_TEST_ALLOW_DATABASE_RESET=1` hanya pada proses itu,
  cache/session/mail `array`, queue `sync` (lihat `tests/TestCase.php`
  + README "Backend test dan isolasi database").
- PHP lokal 8.4 vs CI 8.3 — catat deviasi bila ada failure yang
  tampak version-specific. Bun lokal 1.3.6 vs CI 1.3.11; Node 24.11.1
  vs CI 24.19.0 (untuk `bun run test` saja).

## 4. Urutan gate (cermin CI, cepat → berat)

1. `git status --short --branch` + `git diff --stat` — catat file berubah.
2. `php vendor/bin/pint --test` (seluruh proyek, mode test).
3. `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress --memory-limit=1G`.
4. Backend focused (setelah §3 siap):
   `php artisan config:clear` lalu
   `php vendor/bin/pest tests/Feature/Perencanaan/SasaranIndikatorTest.php`
   (ekspektasi mencakup 2 test `jenis_agregasi` + `preserves_unsubmitted`).
5. Backend full: `php artisan test` (atau `vendor/bin/pest`);
   wajib mencakup `tests/Feature/Authorization`,
   `tests/Feature/RegulasiFeatureTest.php`,
   `tests/Feature/IndikatorKomponen/`, `tests/Feature/Renstra/`.
6. `bun install --frozen-lockfile` (bila node_modules diragukan) lalu
   `bun run typecheck`.
7. `bun run test` (skrip memakai Node untuk vitest; baseline 23/129).
8. `bun run build` (berat — boleh terakhir).
9. `bun run lint` (ekspektasi: gagal env `isexe`; bila tetap gagal,
   buktikan pre-existing via `git stash` + run ulang, JANGAN di-fix
   diam-diam di session ini — laporkan saja).
10. `composer validate --strict` bila composer tersedia; bila tidak,
    catat TERBLOKIR (jangan install composer sendiri).

## 5. Fokus PR-42 (jangan hanya angka global)

- 2 test baru `jenis_agregasi` harus lulus (bukti TASK-42-01).
- `test_update_indikator_preserves_unsubmitted_fields` tetap hijau
  (desimal/is_aktif dipertahankan).
- Test TASK-02 (deny `regulasi:read` pada write → 403 + audit;
  regulasi nonaktif → 422) dan TASK-05 (Komponen capability,
  `renstra_id` invalid → 404) harus hijau.
- Grep bukti: tidak ada `jenis_agregasi` tersisa di
  `app/Http/{Requests/Indikator,Controllers/Perencanaan}` dan payload
  `IndikatorModal.tsx`.
- Frontend: tidak ada test khusus modal SasaranIndikator yang pecah
  (suite tidak punya file untuknya — catat sebagai gap bila relevan).

## 6. Format laporan akhir (wajib)

Tabel per gate: Gate | Command persis | Hasil (PASS/FAIL/TERBLOKIR) |
Bukti (ringkasan output) | Keterbatasan. Lalu: daftar failure
pre-existing vs akibat perubahan (dengan SHA/diff), file yang diubah
session ini (harus NIHIL kecuali diminta), dan gate yang belum
dijalankan. Jangan klaim PR-ready bila satu gate belum hijau.
