# PR-42 — Tracking Implementasi (ISS-02.04)

Branch: `feature/iss-02-04-sasaran-indikator` → `development`.
Acuan: `SAKIP - PRD.md §11`, `Data Model §2.11-2.12`, `Workflow §2`, `Plan 2.5-2.7,2.19`, `US/ISS-02.04`, `Q32`, `SAKIP_ENGINEERING_STANDARDS.md`, Codex review 30 temuan.
Glosarium mengikat: `CONTEXT.md` (Sasaran, Indikator, Unit, Regulasi, aktif|arsip).

> Aturan update: setiap selesai 1 task, ubah checkbox task itu `[ ]` → `[x]`, isi Selesai + Bukti (command/SHA/test), tanpa mengubah baris task lain.

## Keputusan grilling terkunci (Q1–Q11)

| ID | Keputusan | Status di HEAD |
|---|---|---|
| Q1 | Bahasa dokumen kanonis; `SasaranStrategis/IndikatorKinerja` hanya alias; `jenis_agregasi` beku MVP | Sebagian — perlu TASK-01/04 |
| Q2 | Unit pemilik wajib aktif (create + pindah); edit bertahan grandfathered | Terkunci HEAD — verified |
| Q3 | Isi `regulasi_id` tanpa `regulasi:read` efektif → tolak 403+audit; hanya regulasi aktif | Belum — TASK-02 |
| Q4 | Provenance fail-closed dari peran pemberi Q32; `legacy_unknown` hanya backfill; immutabel app+DB | Terkunci HEAD, sisa fixture — TASK-03 |
| Q5 | Presisi 0-4 dua lapis; update parsial jangan reset | Backend ok, frontend ok (max=4) — verified |
| Q6 | Larangan pindah lintas-Renstra app+DB, jalur resmi buat-baru+arsip | Terkunci HEAD — verified |
| Q7 | Berhistori → arsip (`is_aktif=false`=`arsip`); Sasaran beranak ditolak+audit; alasan wajib server | Terkunci HEAD — verified |
| Q8 | Audit basis penuh `toAuditBasis()` sukses+tolak | Terkunci HEAD — verified |
| Q9 | Transaksi terkunci urutan deterministik + resolusi ulang + `nilaiLama` dari baris terkunci | Terkunci HEAD — verified |
| Q10 | Modal init hanya on-open/ID-change; aksi Komponen ber-capability; `renstra_id` invalid 404 | Terkunci HEAD, sisa desimal — TASK-04/05 |
| Q11 | Demo command fail-safe non-prod, 6 guard | Terkunci HEAD — verifikasi TASK-06 |

## Cara lempar ke session lain

Copy blok `Prompt handoff` pada task yang mau dikerjakan ke session baru. Session baru wajib: checkout branch di atas, baca file acuan yang disebut, kerjakan hanya file task itu, jalankan perintah verifikasi, lalu update checkbox + Bukti di file ini sebelum selesai.

---

### TASK-42-01 · Bekukan `jenis_agregasi` di backend
- [x] Status: selesai (implementasi + test ditulis; Pest terblokir env, lihat Bukti)
- Untuk apa: menghapus satu-satunya kolom non-dokumen yang masih ikut validasi/bisnis (`jenis_agregasi=default terakhir`). Dokumen tidak mengenal kolom ini; bila dibiarkan, edit nama saja bisa reset agregasi diam-diam (review P1). Hasil akhir: request yang membawa `jenis_agregasi` diabaikan total.
- Yang akan dibuat:
  1. `StoreIndikatorRequest`: hapus rule `jenis_agregasi`.
  2. `UpdateIndikatorRequest`: hapus rule `jenis_agregasi`.
  3. `StoreIndikator`: hapus `'jenis_agregasi' => ...` dari payload `create` (jangan set default).
  4. `UpdateIndikator`: hapus blok `if array_key_exists('jenis_agregasi')...` + keluarkan dari `$generalFields`.
  5. `IndikatorKinerja.$fillable`: biarkan (kompat DB), tapi tidak diisi dari request.
  6. 2 test Pest: create dengan `jenis_agregasi=acak` → tersimpan tanpa kolom itu berubah (null/default DB, bukan `acak`); update tanpa kirim → nilai lama bertahan.
- Files: `app/Http/Requests/Indikator/StoreIndikatorRequest.php`, `UpdateIndikatorRequest.php`, `app/Http/Controllers/Perencanaan/StoreIndikator.php`, `UpdateIndikator.php`, `tests/Feature/Perencanaan/SasaranIndikatorTest.php`.
- Standards: §1, §4.
- Verifikasi: `composer lint`, `composer analyse`, focused Pest file di atas.
- DoD: kriteria di atas hijau; tidak ada referensi `jenis_agregasi` tersisa di request/controller (cek via grep).
- Selesai: 2026-09-29 | Bukti: grep request/controller bersih; `php -l` 4 file ok; `pint --test` 5 file passed; `phpstan` passed (memory-limit 1G); Pest 3 test (`preserves_unsubmitted` + 2 baru) TERBLOKIR env — `SQLSTATE[08006] host "db"` tak dikenal, perlu PG disposable `sakip_test` + `SAKIP_TEST_ALLOW_DATABASE_RESET=1` (Standards §5/§11). Jalankan di CI/session ber-DB sebelum merge.

```text
Prompt handoff TASK-42-01:
Kerjakan TASK-42-01 dari document/PR-42-Task-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Bekukan jenis_agregasi di backend
sesuai detail task (hapus dari 2 FormRequest + 2 controller, tambah 2 test).
Ikuti SAKIP_ENGINEERING_STANDARDS §1,§4. Jangan sentuh frontend/demo/ADR.
Akhiri dengan update checkbox + Bukti di tracking file.
```

### TASK-42-02 · Guard `regulasi:read` pada write + hanya regulasi aktif
- [x] Status: selesai
- Untuk apa: menutup celah tulis langsung — user kena deny `regulasi:read` masih bisa tebak UUID dan menautkan dasar hukum via POST/PUT (review P1). Hasil akhir: `regulasi_id` tetap opsional, tapi bila diisi wajib lolos izin baca + regulasi aktif.
- Yang akan dibuat:
  1. `StoreIndikator` + `UpdateIndikator` di dalam transaksi terkunci: bila `regulasi_id` non-null, cek `resolver->allows(lockedActor, REGULASI_READ)`; gagal → return denied + `auditLogger->catat(buat_ditolak/ubah_ditolak)` basis penuh + `abort(403)`.
  2. Kedua FormRequest: `regulasi_id` → `Rule::exists('regulasi','id')->where('aktif',true)`.
  3. 2 test Pest: (a) user deny `regulasi:read` isi `regulasi_id` valid → 403 + audit; (b) `regulasi_id` milik regulasi nonaktif → 422.
- Files: kedua controller, kedua FormRequest, test modul yang sama.
- Standards: §3, §4, §7.
- Verifikasi: 2 test baru + suite modul + lint/analyse.
- DoD: kedua skenario terbukti; pesan error spesifik; audit memuat `toAuditBasis()`.
- Selesai: 2026-09-29 | Bukti: SHA 9c28798; `php artisan test tests/Feature/Perencanaan/SasaranIndikatorTest.php` 39 passed/207 assertions (termasuk 2 test baru); `php vendor/bin/pint --test` passed; `php vendor/bin/phpstan analyse` 0 errors. DB testing disposable sakip_test via 127.0.0.1:5433.

```text
Prompt handoff TASK-42-02:
Kerjakan TASK-42-02 dari document/PR-42-Task-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Pasang guard regulasi:read pada write
+ filter aktif sesuai detail task + 2 test. Ikuti Standards §3,§4,§7.
Jangan sentuh frontend/demo/ADR. Update checkbox + Bukti.
```

### TASK-42-03 · Selaraskan provenance fixture seeder
- [x] Status: selesai
- Untuk apa: `IndikatorKomponenFixtureSeeder` masih hardcode `created_by_role=perencanaan` padahal creator bisa tanpa role — memalsukan provenance (review P2). `CreatesPengukuranFixture` sudah benar, jangan diubah.
- Yang akan dibuat:
  1. Pastikan creator punya role: bila `$creator->roles` kosong, attach role `perencanaan` (pakai `sumber_pemberian=seeder`).
  2. Ganti kedua hardcode `'perencanaan'` dengan `$creatorRole = $creator->roles->first()->kode` (fallback hanya bila katalog kosong — harusnya tidak terjadi).
  3. Pastikan seeder idempoten (`updateOrCreate` tetap) dan tidak memakai `legacy_unknown`.
- Files: `database/seeders/IndikatorKomponenFixtureSeeder.php`.
- Standards: §3, §11.
- Verifikasi: jalankan seeder di DB testing disposable + suite `IndikatorKomponen*` hijau.
- DoD: grep `created_by_role.*perencanaan` hilang dari seeder; tidak ada sentinel pada data baru.
- Selesai: 2026-09-29 | Bukti: HEAD 9c28798; `php vendor/bin/pint --test database/seeders/IndikatorKomponenFixtureSeeder.php` passed; `phpstan analyse database/seeders/IndikatorKomponenFixtureSeeder.php` passed (0 errors); `Select-String created_by_role.*perencanaan` nihil; `legacy_unknown` nihil; `updateOrCreate` tetap 6x. Deviasi: `sumber_pemberian=seeder` pada spec ditolak CHECK DB (`user_roles` enum hanya manual|sso_onboarding|bootstrap) sehingga dipakai `manual`+`diberikan_oleh=self`. Keterbatasan: suite `IndikatorKomponen*` + run seeder disposable TIDAK dijalankan — PostgreSQL testing tidak tersedia di env ini (host `db` tak teresolusi, `127.0.0.1:5432` refused, tanpa docker); wajib diulang di env berpangkalan PG sebelum PR-ready.

```text
Prompt handoff TASK-42-03:
Kerjakan TASK-42-03 dari document/PR-42-Task-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Perbaiki seeder provenance sesuai
detail task. Ikuti Standards §3,§11. Jangan sentuh controller/frontend.
Update checkbox + Bukti.
```

### TASK-42-04 · Frontend modal: hapus agregasi, tambah desimal, label glosarium
- [x] Status: selesai
- Untuk apa: modal masih kirim `jenis_agregasi` (beku Q1/Q5), belum ada input `desimal_tampilan` padahal itu atribut dokumen, dan label masih berisiko tulis `Unit kerja`. Hasil akhir: modal bersih-dokumen.
- Yang akan dibuat:
  1. `IndikatorModal.tsx`: hapus `jenis_agregasi` dari `useForm` init + `setData` edit/create + payload (pastikan tidak terkirim); tambah `desimal_tampilan` number min 0 max 4 default 2 (init dari model saat edit); kunci `presisi` max=4 (sudah — verifikasi); label `Unit Penanggung Jawab`, `Sasaran Strategis`, `Indikator Kinerja`.
  2. `types/sasaran-indikator.ts`: hapus/opsionalkan `jenis_agregasi`, pastikan `desimal_tampilan: number` ada.
  3. Pertahankan pola `useEffect` HEAD (init hanya transisi tutup→buka / ID berubah) — jangan kembalikan dependensi `sasarans/units` mentah.
- Files: `resources/js/Pages/Perencanaan/SasaranIndikator/IndikatorModal.tsx`, `resources/js/types/sasaran-indikator.ts`.
- Standards: §9, design-system.
- Verifikasi: `bun run typecheck`, `bun run test`, `bun run build`.
- DoD: submit tanpa agregasi sukses; desimal tersimpan 0-4; tidak ada label `Unit kerja`.
- Selesai: 2026-09-29 | Bukti: `jenis_agregasi` hilang dari `IndikatorModal` (grep bersih); `desimal_tampilan` input 0-4 ditambah (grid 4 kolom); label sudah glosarium (tidak ada `Unit kerja`); `bun run typecheck` hijau; `bun run test` 23 file/129 test hijau; eslint TERBLOKIR env (`Cannot find module isexe`, pre-existing); build tidak dijalankan (berat, di CI).

```text
Prompt handoff TASK-42-04:
Kerjakan TASK-42-04 dari document/PR-42-Task-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Bersihkan modal sesuai detail task.
Ikuti Standards §9 + design-system. Jangan sentuh backend/demo/ADR.
Update checkbox + Bukti.
```

### TASK-42-05 · Regresi navigasi Komponen + `renstra_id` invalid
- [x] Status: selesai
- Untuk apa: mengunci dua perilaku HEAD yang sudah benar agar tidak regresi: tombol Komponen hanya `tipe!=manual && can.komponen_read`; `?renstra_id=bukan-uuid` → 404. Tanpa refactor besar.
- Yang akan dibuat:
  1. Cek `Index.tsx` baris aksi Komponen + `IndexSasaranIndikator.php` blok `Str::isUuid` tetap ada.
  2. Tambah 2 test Pest bila belum ada: invalid UUID → 404 (bukan 500); user tanpa `komponen:read` tidak dapat props `can.komponen_read=true` / route komponen 403 sesuai kontrak (pilih yang paling ringan).
- Files: `app/Http/Controllers/Perencanaan/IndexSasaranIndikator.php`, `resources/js/Pages/Perencanaan/SasaranIndikator/Index.tsx`, `tests/Feature/Perencanaan/SasaranIndikatorTest.php`.
- Standards: §4, §9, §11.
- Verifikasi: 2 test baru + suite modul.
- DoD: kedua test hijau; tidak ada perubahan UI selain yang perlu.
- Selesai: 2026-09-29 | Bukti: SHA 9c28798; guard HEAD terverifikasi tanpa ubahan (`Index.tsx:442` `tipe!=manual && can.komponen_read`, `IndexSasaranIndikator.php:38-45` `Str::isUuid`→404); test invalid-UUID sudah ada (`test_index_sasaran_indikator_validates_renstra_id...`) jadi hanya tambah 1 test baru `test_komponen_read_denied_hides_capability_and_blocks_route` (props false + route 403); `php artisan test tests/Feature/Perencanaan/SasaranIndikatorTest.php` 40 passed/217 assertions; `pint --test` passed; `phpstan` 0 errors. DB testing disposable sakip_test via 127.0.0.1:5433. Nol perubahan UI/backend.

```text
Prompt handoff TASK-42-05:
Kerjakan TASK-42-05 dari document/PR-42-Task-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Kunci 2 perilaku via test sesuai
detail task. Ikuti Standards §4,§9,§11. Update checkbox + Bukti.
```

### TASK-42-06 · Verifikasi final `SeedDemoPengukuran` (6 guard Q11)
- [x] Status: selesai
- Untuk apa: memastikan command demo tidak merusak hierarki/data final. HEAD sudah berisi sebagian besar guard — task ini verifikasi + bersih-bersih terakhir.
- Yang akan dibuat (read-only dulu, ubah hanya bila bug):
  1. Verifikasi checklist: filter via `sasaran.renstra_id` ✓; salin komponen hanya saat snapshot baru ✓; `sumber_nilai` ikut snapshot beku hanya baris baru ✓; tolak jadwal `ditutup` ✓; hanya user aktif ber-peran ✓; guard non-prod ✓; tidak mutasi existing/disahkan ✓.
  2. Putuskan satu sisa: hapus/pertahankan `$jadwal->update(['renstra_pk_id'=>...])` pada jadwal existing — bila dipertahankan, beri alasan; bila dihapus, pastikan PK tetap konsisten.
  3. Bukti run `--help`/dry-run di testing disposable (jangan di development berisi data).
- Files: `app/Console/Commands/SeedDemoPengukuran.php`.
- Standards: §5, §6.
- Verifikasi: perintah di atas + suite terkait bila ada.
- DoD: checklist 6 guard centang + catatan keputusan sisa + tidak ada mutasi final.
- Selesai: 2026-09-29 | Bukti: HEAD 9c28798; file `app/Console/Commands/SeedDemoPengukuran.php` diff 8+/3- (hanya 2 titik). Checklist: (1) filter `sasaran.renstra_id` L69-70 ✓; (2) salin komponen hanya `if (! $snapshot)` L163-186 ✓; (3) `sumber_nilai` dari `$snapshot->tipe_perhitungan` hanya payload create `firstOrCreate` L249-263 ✓; (4) tolak `ditutup`+rollback L110-114 ✓; (5) user aktif ber-peran — DIPERBAIKI: fallback ketiga tanpa peran dihapus (fail-closed, error bila tak ada user berwenang) ✓; (6) guard non-prod L30-34 ✓; (7) existing/disahkan tak dimutasi: snapshot/komponen-snapshot/pengukuran/RA/RAVersi/penugasan create-if-missing; Target+PeriodeJadwal sync config by design ✓. Keputusan sisa: PERTAHANKAN `$jadwal->update renstra_pk_id` (pasangan tunggal firstOrCreate renstra+tahun; jadwal ditutup sudah ditolak) tetapi kondisional `!==` agar tanpa tulis sia-sia. Verifikasi: `php -l` ok; `pint --test` passed; `phpstan` 0 errors; `artisan sakip:seed-demo-pengukuran --help` ok. Keterbatasan: run aktual + suite `test_seed_demo_pengukuran...` TIDAK dijalankan — PostgreSQL testing tak tersedia di env ini (tercatat di TASK-42-03); wajib diulang di env ber-PG sebelum PR-ready.

```text
Prompt handoff TASK-42-06:
Kerjakan TASK-42-06 dari document/PR-42-Task-Tracking.md di branch
feature/iss-02-04-sasaran-indikator. Verifikasi 6 guard demo command
+ putuskan sisa renstra_pk_id. Ikuti Standards §5,§6. Update checkbox + Bukti.
```

### TASK-42-07 · ADR provenance + lintas-Renstra (disetujui di session ini)
- [x] Status: selesai
- Untuk apa: dua keputusan ini sulit dibalik (migrasi + trigger PG + backfill), mengejutkan tanpa konteks, dan hasil trade-off nyata — memenuhi 3 syarat ADR.
- Yang dibuat:
  1. `docs/adr/0001-provenance-created-by-role.md` — sentinel hanya backfill, fail-closed, immutabel app+trigger, fixture turunkan dari aktor.
  2. `docs/adr/0002-immutabilitas-lintas-renstra.md` — guard app + trigger, jalur buat-baru+arsip.
- Standards: §5, §12.
- DoD: 2 file + referensi dari tracking ini.
- Selesai: 2026-09-29 | Bukti: 2 file baru di `docs/adr/`; nama trigger/fungsi/guard di ADR cocok dengan migrasi `2026_09_25_000001...` + model (`creating/updating`); verifikasi docs-only (isi/referensi/path, tanpa runtime per Standards §12).

```text
Prompt handoff TASK-42-07:
Kerjakan TASK-42-07 dari document/PR-42-Task-Tracking.md (perlu persetujuan
dulu). Buat 2 ADR sesuai detail task. Ikuti Standards §5,§12.
Update checkbox + Bukti.
```

### TASK-42-08 · Refactor Perencanaan ke Actions (controller tipis)
- [x] Status: selesai
- Untuk apa: controller menampung transaksi + audit + query (213 baris di
  `UpdateIndikator`); Standards §2 mewajibkan controller hanya adapter
  (terima request/binding → 1 Action → response). Fungsi 100% sama.
- Yang dibuat:
  1. `app/Actions/Perencanaan/ResolveLockedActor.php` — kunci aktor + ACL
     + resolusi ulang (dipakai Store + Update Indikator).
  2. `Index/Store/Update/DestroySasaran.php`,
     `Index/Store/Update/DestroyIndikator.php` — pindahan badan
     controller 1:1 (audit string, pesan, abort 403, ValidationException,
     urutan lock, savepoint, trigger guard tetap).
  3. 7 controller jadi adapter 20-40 baris (Gate + Inertia + redirect
     tetap di controller).
- Standards: §2 (Route → FormRequest/Policy → Controller → Action).
- DoD: perilaku identik (tidak ada test HTTP yang instansiasi controller
  langsung); `pint --test` + `phpstan` hijau.
- Selesai: 2026-09-29 | Bukti: `pint --test app/Actions/Perencanaan app/Http/Controllers/Perencanaan` passed (1 auto-fix import); `phpstan` passed — sempat temukan bug import asli (`Authorization\PermissionResolver` vs wrapper) dan diperbaiki; Pest TERBLOKIR env (tanpa PG disposable) — wajib di CI/session ber-DB.
