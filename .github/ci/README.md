# Pemeriksaan CI

Workflow `CI` berjalan untuk PR dan push ke `development`/`main`, serta dapat
dipicu manual melalui `workflow_dispatch`. Direct forward push dokumen tim tetap
didukung. Konfigurasi ini tidak mengubah branch protection atau ruleset.

## Job dan concurrency

Ada sembilan job: `CI Scope`, PHP Formatting, PHP Static Analysis, Backend Tests,
TypeScript, Frontend Tests, Production Build, Dependency Security, dan Frontend Lint.

Run pada PR yang sama memakai grup berdasarkan nomor PR; commit baru membatalkan
run PR sebelumnya. Setiap push dan pemicu manual memakai grup berdasarkan
`github.run_id` dengan `cancel-in-progress` nonaktif. Karena grupnya berbeda,
push dokumen PM tidak membatalkan run kode yang sedang berjalan, termasuk pada
branch yang sama. Run tetap tunduk pada kapasitas dan antrean runner GitHub.

Vitest memakai paralelisme bawaannya dan PHPStan tidak diberi batas memori
internal. Kapasitas runner/mesin tetap berlaku; timeout tetap menghentikan proses
macet. Cache unduhan Composer/Bun tetap dipakai dengan install sesuai lockfile.

## Jalur dokumen saja

Job `CI Scope` memakai Node 24.19.0 dengan cache package manager nonaktif. Job ini
memeriksa seluruh delta PR dari merge-base, atau seluruh rentang `before..after`
untuk push. Hanya `README.md` serta file di `document/` dengan ekstensi
md/txt/pdf/docx/xlsx/png/jpg/jpeg/svg/webp/drawio yang termasuk docs-only.
Pemindahan file mempertimbangkan path lama dan baru.

Application checks hanya dilewati jika `CI Scope` berhasil dan secara eksplisit
menghasilkan `run_app=false`. Docs-only menjalankan satu job scope dan melewati
delapan job aplikasi. Perubahan campuran, path baru/tidak dikenal, source, test,
config, workflow/CI logic, manifest/lockfile, migration, diff kosong, serta metadata
atau history yang tidak dapat diverifikasi menjalankan seluruh pemeriksaan.
`workflow_dispatch` juga menjalankan seluruh pemeriksaan.

Jika selector gagal atau output tidak tersedia/tidak valid, delapan job aplikasi
tetap dijalankan. Pembatalan workflow tetap dihormati. Workflow tetap dipicu untuk
perubahan dokumen, sehingga check tidak tertahan karena filter path pada trigger.

Uji selector secara lokal dari root repository:

```sh
node --test .github/ci/changes.test.mjs
```

## Dependency Security

- `composer validate --strict`: validitas manifest dan konsistensi lockfile.
- `composer audit --locked`: advisory seluruh dependency PHP terkunci, termasuk dev.
- `bun audit`: advisory seluruh dependency frontend pada `bun.lock`.

Temuan audit maupun kegagalan mengambil advisory menggagalkan job. Tidak ada
auto-fix, perubahan lockfile oleh CI, atau pengecualian advisory saat ini.
Audit bersih hanya menyatakan tidak ada advisory yang dilaporkan pada saat run.

## Frontend Lint

Jalankan `bun install --frozen-lockfile`, kemudian `bun run lint`.
ESLint memeriksa correctness dasar, pola rawan bug, dan aturan React Hooks pada
source TypeScript dan test frontend; warning juga menggagalkan job. Lint tidak
mengatur whitespace, quotes, semicolon, pengurutan import, atau menjalankan Prettier.

Parser Babel hanya membaca sintaks TS/TSX. Pilihan ini diperlukan karena peer
range `typescript-eslint` yang diperiksa belum mencakup TypeScript 7 proyek.
Pemeriksaan tipe tetap menjadi job TypeScript (`bun run typecheck`). Dependency
lint merupakan dev dependency dan tidak diimpor oleh aplikasi.
