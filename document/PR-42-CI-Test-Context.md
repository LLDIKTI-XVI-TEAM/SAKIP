# Konteks Session CI-Mirror — PR-42 (ISS-02.04)

> Tempel seluruh isi file ini sebagai pesan pertama subagent CI.
> Session ini KHUSUS verifikasi. Jangan ubah kode kecuali gate memaksa
> (bila mengubah, catat di Bukti + jalankan ulang gate terdampak).

## 1. Misi

Replikakan 9 jobs `.github/workflows/ci.yml` secara lokal untuk branch
`feature/iss-02-04-sasaran-indikator` (base `development`, HEAD saat tulis:
`44cd450`) dan laporkan per gate: PASS / FAIL / TERBLOKIR (sebab + bukti).
Tujuan: push dipastikan lolos CI.

Status tracking saat tulis: TASK-42-01–07 done; R2-01 done
(`SeedDemoPengukuran` sudah dikeluarkan — grep verifikasi nihil di
`app/`, `routes/`, `tests/`, `database/`); R2-02–R2-10 open.

## 2. Cermin job CI → command lokal (urut cepat → berat)

| # | Job CI | Command lokal |
|---|---|---|
| 1 | CI Scope (`changes`) | `node --test .github/ci/changes.test.mjs` lalu `node .github/ci/changes.mjs` |
| 2 | PHP Formatting | `php vendor/bin/pint --test` (seluruh proyek) |
| 3 | PHP Static Analysis | `php vendor/bin/phpstan analyse --no-progress --memory-limit=-1` |
| 4 | Backend Tests | `php artisan test` (full; `composer test` = wrapper yang sama). DB via env override (lihat §3) |
| 5 | TypeScript | `bun run typecheck` |
| 6 | Frontend Tests | `bun run test` (vitest via Node) |
| 7 | Production Build | `bun run build` (berat — boleh terakhir) |
| 8 | Dependency Security | `bun audit` (jalan). `composer validate --strict` + `composer audit --locked` TERBLOKIR — binary `composer` tidak ada di PATH (jangan install sendiri; catat sebagai gap vs CI) |
| 9 | Frontend Lint | `bun run lint` (ekspektasi: gagal pre-existing `Cannot find module 'isexe'`; buktikan via `git stash` + run ulang, JANGAN di-fix diam-diam — laporkan saja) |

## 3. Aturan environment (wajib, Standards §5/§11)

- Target test yang TERBUKTI jalan: PostgreSQL di container `sakip_db`
  via `127.0.0.1:5433`, DB + user `sakip_test` / password `sakip_test`.
  Ini database TERPISAH dari dev (`sakip`) — reset `RefreshDatabase`
  tidak menyentuh data dev. Verifikasi isolasi via `tests/TestCase.php`
  (APP_ENV=testing, tanpa config cache, `SAKIP_TEST_ALLOW_DATABASE_RESET=1`
  hanya pada proses test).
- Baseline terbukti: suite `SasaranIndikatorTest.php` 39 passed (pasca R2-01).
- Deviasi versi vs CI (catat bila ada failure tampak version-specific):
  PHP lokal 8.4 vs CI 8.3; Bun lokal 1.3.6 vs CI 1.3.11;
  Node lokal 24.11.1 vs CI 24.19.0 (untuk `bun run test` saja).

## 4. Fokus PR-42 (jangan hanya angka global)

- Suite `SasaranIndikatorTest.php` hijau penuh (mencakup test TASK-02:
  deny `regulasi:read` → 403 + audit, regulasi nonaktif → 422;
  test TASK-05: `komponen:read` deny, `renstra_id` invalid → 404;
  `preserves_unsubmitted`; freeze `jenis_agregasi`).
- Grep bukti: `SeedDemoPengukuran` nihil di `app/`, `routes/`,
  `tests/`, `database/`; tidak ada `jenis_agregasi` di
  `app/Http/{Requests/Indikator,Controllers/Perencanaan}` dan payload
  `IndikatorModal.tsx`.
- Wajib mencakup: `tests/Feature/Authorization`,
  `tests/Feature/IndikatorKomponen/`, `tests/Feature/Renstra/`.

## 5. Format laporan akhir (wajib)

Tabel per gate: Gate | Command persis | Hasil (PASS/FAIL/TERBLOKIR) |
Bukti (ringkasan output) | Keterbatasan. Lalu: failure pre-existing vs
akibat perubahan (dengan SHA/diff), file yang diubah session ini
(harus NIHIL kecuali diminta), gate yang belum dijalankan.
Jangan klaim PR-ready bila satu gate belum hijau.
