# PR-62 Review10 Tracking — Codex P1 (base `c1523c0`)

Sumber: review kode Codex atas PR #62 pada commit `c1523c07` (submitted `2026-10-08T07:32:34Z`) — **satu temuan P1**: *"Finalisasikan snapshot sebelum jadwal dipublikasikan"*. Security review pada PR yang sama (`2026-10-08T07:36:16Z`) menyatakan tidak ada temuan keamanan.

Satu session = satu task. JANGAN commit/push tanpa perintah. DB dev JANGAN reset (PG disposable saja). Skill wajib: `laravel-best-practices` + `testing-best-practices`; FE: `inertia-react-development` + `tailwindcss-development`.

---

## F1 · Snapshot hasil aktivasi tidak pernah difinalkan (`komposisi_final` tetap `false`)

- **Status: SELESAI (2026-10-08)** — temuan diverifikasi di HEAD `c1523c0` (bukan false positive), diperbaiki, dan diuji. Bukti implementasi ada di bagian akhir dokumen.

**Bukti**

| # | Temuan | Lokasi |
|---|---|---|
| 1 | `createSnapshots()` membuat parent **tanpa** `komposisi_final` → default kolom `false` (`NOT NULL`, default `false`), menyisipkan seluruh child dalam satu statement, lalu lanjut audit + aktivasi. Tidak ada transisi `false→true`. | `app/Actions/Jadwal/ActivateJadwal.php:161–189` (create baris 169, child insert baris 179) |
| 2 | **Tidak ada kode aplikasi mana pun** yang men-set `komposisi_final = true`; hanya `$fillable`/`$casts` model. | grep `komposisi_final` di `app/` → hanya `app/Models/JadwalSnapshot.php:18,20` |
| 3 | Guard INSERT komponen hanya menolak bila induk **sudah** final; selama `false`, child tetap bisa disisipkan. | `database/migrations/2026_10_06_042531_serialisasi_insert_vs_finalisasi_snapshot_review9_w1.php:73–77` |
| 4 | Backfill `false→true` di migrasi V2 hanya **sekali jalan saat migrasi**; snapshot yang dibuat sesudahnya (yaitu hasil aktivasi) tetap `false` selamanya. | `database/migrations/2026_10_06_032010_bekukan_komposisi_snapshot_terbit_rencana_aksi_v2.php:104` |
| 5 | Publisher ini **satu-satunya** jalur pembuatan snapshot di `app/` → perbaikan terlokalisasi, tidak ada jalur kedua yang perlu ditutup. | grep `JadwalSnapshot::create\|JadwalSnapshotKomponen::insert` di `app/` |
| 6 | Test di HEAD mengunci perilaku keliru sebagai ekspektasi. | `tests/Feature/Jadwal/JadwalActivationTest.php:86` (`'komposisi_final' => false`) |

**Dampak (kenapa P1 nyata).** Snapshot terbit dipakai RA read/preview/save, tetapi komposisinya masih dapat disisipi child tambahan tanpa perubahan ID maupun `nomor_versi`. Pembaca berikutnya melihat rumus yang berbeda untuk snapshot yang sama, dan stale token tetap lolos karena tidak ada versi yang berubah. Jadi temuan ini bukan sekadar "flag kosmetik": ia mematahkan jaminan beku-yang-diklaim-kan pada ADR-0004 dan U1/V2/W1.

**Konteks keputusan.** Celah ini sudah tercatat sebagai "celah laten" di handoff 2026-10-06 (item open #2) dan saat itu dikategorikan materi ISS-03.03/follow-up #53. Codex kini menaikkannya menjadi **P1 pada HEAD**. Karena itu urusan pertama implementasi adalah konfirmasi pemilik PR: dikerjakan sekarang sebagai pengerasan PR #62, atau tetap ditunda dengan catatan eksplisit bahwa P1 belum ditutup (jangan diam-diam dilewati).

**Kondisi data saat ini.** DB dev hanya punya 2 snapshot dan keduanya **sudah final** (hasil backfill V2 saat migrasi diterapkan 2026-10-08) → tidak ada data yang perlu diperbaiki sekarang; risiko terletak pada aktivasi berikutnya. `jadwal_snapshot` tidak memiliki `created_at`/`updated_at` dan modelnya `public $timestamps = false`, sehingga finalisasi tidak mengubah kolom lain dan lolos guard UPDATE (daftar kolom yang dibandingkan trigger memang tetap).

---

## Keputusan implementasi

| # | Keputusan | Alasan |
|---|---|---|
| D1 | Setelah seluruh child satu snapshot tersimpan, finalkan parent-nya **di dalam transaksi aktivasi yang sama** | Inti temuan; transaksi sudah memegang lock sehingga finalisasi serial dengan guard INSERT (W1) |
| D2 | Ikut finalkan snapshot **existing** milik jadwal yang diaktivasi yang masih `false` (satu statement setelah loop) | Permintaan eksplisit Codex; snapshot existing yang `false` hanya mungkin berasal dari jalur bug ini, jadi ini merapikan keluaran bug sendiri |
| D3 | Migrasi data sekali jalan: finalkan snapshot `false` pada jadwal berstatus `aktif`/`ditutup` | Menutup jendela untuk jadwal yang sudah terlanjur aktif sebelum perbaikan; `draft` tidak dibatasi karena secara desain belum punya snapshot terbit |
| D4 | Audit `jadwal_snapshot.buat` ditulis **setelah** finalisasi dan menyertakan `komposisi_final => true` | Satu baris audit mewakili satu snapshot yang benar-benar terbit beku; hindari audit yang menyatakan state yang tidak pernah final |
| D5 | Batasan follow-up: saat ISS-03.03 (koreksi berversi) dibangun, draft koreksi yang belum terbit harus **dikecualikan** dari finalisasi massal D2 | Mencegah D2 membekukan draft koreksi sebelum waktunya; catat sebagai constraint, jangan implementasikan sekarang |

Alternatif yang dipertimbangkan dan ditolak: (a) membuat parent langsung `komposisi_final = true` — mustahil, guard INSERT akan menolak child-nya; (b) menambah guard baca `komposisi_final = true` di sisi RA — mengubah kontrak baca dan tidak menyelesaikan akar masalah.

---

## Langkah implementasi

1. `ActivateJadwal::createSnapshots()` — setelah `JadwalSnapshotKomponen::insert(...)` untuk satu snapshot: `JadwalSnapshot::whereKey($snapshot->id)->update(['komposisi_final' => true])`, lalu tulis audit (D4).
2. Setelah loop, satu statement untuk snapshot existing milik jadwal tersebut yang masih `false` (D2). Dijalankan setelah loop agar snapshot baru sudah berstatus final dan tidak terhitung dua kali.
3. Migrasi baru `database/migrations/2026_10_08_000003_finalisasi_snapshot_terbit.php` (D3): `UPDATE jadwal_snapshot SET komposisi_final = true WHERE komposisi_final = false AND jadwal_id IN (SELECT id FROM jadwal_tahunan WHERE status IN ('aktif','ditutup'))`, dengan log jumlah baris; `down()` **non-reversible** dan itu didokumentasikan di komentar (mengembalikan ke `false` akan membuka celah lagi).
4. Sesuaikan test yang mengunci perilaku lama:
   - `tests/Feature/Jadwal/JadwalActivationTest.php:86` → harapan `true`.
   - `tests/Feature/Jadwal/JadwalActivationTest.php:213–236` (`test_existing_draft_snapshots_are_kept_and_only_missing_pairs_are_created`): fingerprint lama vs baru akan berbeda pada kolom flag untuk snapshot pre-existing. Ubah assertion agar menyatakan niat aslinya — **konten beku (semua kolom selain `komposisi_final`) tetap identik**, sementara flag boleh berubah `false→true`. Jangan melemahkan jadi "contains" longgar tanpa alasan.
   - Audit payload (bila ada assertion atas `nilaiBaru` `jadwal_snapshot.buat`) disesuaikan dengan D4.
5. Regression baru (wajib, ini inti bukti P1 tertutup):
   - Aktivasi jadwal berkomponen → seluruh parent hasil aktivasi `komposisi_final = true`.
   - Sesudah aktivasi, `INSERT` child ke snapshot tersebut **ditolak 23514** — bukti end-to-end bahwa komposisi benar-benar beku.
   - Snapshot `false` yang sudah ada sebelum aktivasi → ikut final setelah aktivasi (D2).
   - Replay aktivasi idempoten (`changed = false`, `snapshot_created_count = 0`) → tidak ada flag/baris yang berubah.
   - Bila D3 dikerjakan: pre-check + backfill teruji, `down()` non-reversible terdokumentasi, serta jadwal `draft` tidak ikut tersentuh.
6. Verifikasi: suite Jadwal + RencanaAksi (frozen snapshot, Review8 V2, Review9 W1) + RA penuh + FE + `pint` + `phpstan` (0 error) + `typecheck`; rollback + migrate bersih di PG disposable. DB dev tidak disentuh.

## Definition of Done

- [x] F1 tertutup: snapshot hasil aktivasi final, dan child tidak dapat disisipkan pasca-terbit (dibuktikan regression).
- [x] D2/D3/D4 terimplementasi sesuai keputusan; D5 tercatat sebagai constraint follow-up.
- [x] Regression + suite terkait hijau; rollback + migrate bersih.
- [x] `pint` passed; `phpstan` 0 error.
- [x] Tidak ada perubahan di luar scope modul Jadwal + test/doc terkait.
- [x] Laporan + bukti disampaikan; belum di-commit (menunggu perintah).

## Prompt handoff

```text
Kerjakan F1 dari document/PR-62-Review10-Tracking.md di branch feature/iss-05-01-target-rencana-aksi
(lanjut working tree, JANGAN commit). Skill laravel-best-practices + testing-best-practices.
Patuhi keputusan D1–D5 pada dokumen itu. Wajib: regression "child insert pasca-aktivasi ditolak 23514",
penyesuaian test fingerprint existing-snapshot, verifikasi PG disposable (dev JANGAN disentuh), pint + phpstan hijau.
Update checkbox + Bukti di dokumen ini. JANGAN commit.
```

## Implementasi & Bukti (F1)

**Perubahan**
1. `app/Actions/Jadwal/ActivateJadwal.php` — `createSnapshots()`: setelah seluruh child satu snapshot tersimpan, parent difinalkan (`JadwalSnapshot::whereKey($snapshot->id)->update(['komposisi_final' => true])`) **sebelum** audit ditulis; audit `jadwal_snapshot.buat` kini menyertakan `komposisi_final => true` (D1 + D4).
2. `app/Actions/Jadwal/ActivateJadwal.php` — `handle()`: satu statement membekukan snapshot existing milik jadwal tersebut yang masih `false` (D2), dan jumlahnya dicatat di audit `jadwal.aktivasi` sebagai `snapshot_finalized_existing_count`.
3. `database/migrations/2026_10_08_000003_finalisasi_snapshot_terbit.php` (D3): finalisasi snapshot tertinggal pada jadwal `aktif`/`ditutup`; `down()` sengaja tanpa aksi (mengembalikan ke `false` akan membuka celah lagi) — didokumentasikan di komentar.
4. Test: ekspektasi `komposisi_final` hasil aktivasi menjadi `true`; dua test snapshot existing memakai helper baru `tanpaFlagFinalisasi()` di `tests/Support/JadwalActivationFixtures.php` (konten beku wajib identik, flag boleh berubah) plus assert bahwa flag benar-benar final; regression baru `test_snapshot_hasil_aktivasi_final_sehingga_komposisi_beku()` membuktikan seluruh parent final dan **INSERT child pasca-aktivasi ditolak `23514`** (dibungkus savepoint agar transaksi `RefreshDatabase` tidak ter-abort sehingga jumlah komponen tetap dapat diverifikasi).

**Hasil verifikasi** (PG disposable `postgres:17-alpine` port 5457, DB/user `sakip_test`; dev `sakip_db` tidak disentuh)
- `migrate:fresh` bersih termasuk migrasi baru; `migrate:rollback --step=1` + `migrate` bersih (migrasi D3 turun/naik tanpa error).
- `tests/Feature/Jadwal` 56/56 (470 assertions) — termasuk regression baru.
- `tests/Feature/RencanaAksi` + `tests/Integration/Jadwal` hijau.
- `tests/Integration` penuh + 11 file `tests/Feature` root yang belum pernah dijalankan: **210/210 (4839 assertions)**.
- `pint --test` PASS (474 file); `phpstan` 0 error (`--memory-limit=1G`).

**Temuan sampingan yang ikut ditutup.** Saat verifikasi, `tests/Integration/Jadwal/JadwalActivationConcurrencyTest::test_indicator_inserted_during_enumeration_rolls_back_with_conflict` gagal karena mencari indikator berkode `'IKU-BARU'` — padahal sejak commit `953a6ed` kode indikator dibangkitkan server. Jadi test itu **sudah rusak sejak commit tersebut** dan tidak terdeteksi karena suite `tests/Integration/Jadwal` belum dijalankan saat itu. Diperbaiki: identitas diambil dari nilai kembalian `StoreIndikator`, dan `kode` dibuang dari payload fixture (`newIndicatorPayload()`).

## Tindak lanjut — dua temuan Codex berikutnya (`2026-10-08T10:12:51Z`)

**Status: SELESAI (2026-10-08).** Review berikutnya atas commit `7600aef5` (pra-rewrite; isi identik dengan `0053765`) memuat dua temuan inline. Keduanya terverifikasi benar dan sama-sama menyangkut migrasi pada perubahan ini.

### F2 · P1 · `2026_10_08_000003_finalisasi_snapshot_terbit.php:28` — serialisasi backfill dengan aktivasi

- **Temuan:** bila migrasi berjalan sementara worker versi lama masih dapat mengaktifkan jadwal, transaksi aktivasi yang belum commit tidak terlihat oleh `UPDATE` migrasi (maupun status `aktif`-nya). Snapshot terbit itu tetap `false`, replay aktivasi kemudian no-op, dan komposisinya tetap dapat disisipi komponen.
- **Perbaikan:** `up()` menjalankan lock + backfill di dalam transaksi eksplisit dan mengambil **advisory lock eksklusif** bernama sama dengan jalur aktivasi (`Periode::lockConfiguration()` → `sakip:periode-konfigurasi`), sehingga migrasi menunggu aktivasi in-flight commit lebih dahulu.
- **Bukti:** `test_lock_migrasi_berkonflik_dengan_lock_bersama_jalur_aktivasi` (dua koneksi: lock bersama dipegang → lock eksklusif migrasi tertahan lalu gagal `55P03` dalam batas `lock_timeout`) dan `test_urutan_langkah_migrasi_penyiapan_data_dan_lock_serialisasi` (lock diambil sebelum `UPDATE`; nama kunci sinkron dengan `Periode.php`).

### F3 · P2 · `2026_10_08_000001_kode_otomatis_sasaran_indikator.php:27` — preflight duplikat sebelum backfill

- **Temuan:** pada database berkode duplikat, `000001` sudah ter-commit dan menimpa `urutan` yang diatur pengguna, baru kemudian `000002` gagal memasang indeks unik. Karena `down()` tidak memulihkan nilai lama, rollout yang sengaja dihentikan tetap meninggalkan urutan yang berubah dan data aslinya hilang.
- **Perbaikan:** backfill `urutan` **dipindah ke `000002`**, sehingga urutannya menjadi *pre-check duplikat → backfill → unique index*. `000001` kini murni menambah kolom — tetap dapat diterapkan lebih dahulu untuk membuka blokir tanpa menyentuh data.
- **Bukti:** `test_precheck_duplikat_menghentikan_migrasi_sebelum_backfill_menimpa_urutan` (duplikat → `RuntimeException`, `urutan` manual 42/43 utuh), `test_migrasi_kolom_tidak_mengubah_data` (`000001` tanpa backfill/`update()`), `test_precheck_lolos_backfill_menyelaraskan_urutan_dan_indeks_dipasang` (`SS-05` 99 → 5, kode legacy `SS-LAMA` dibiarkan, kedua indeks kembali terpasang).

**Verifikasi tindak lanjut** (PG disposable port 5458; dev `sakip_db` tidak disentuh): `KodeOtomatisMigrationTest` 5/5 (18 assertions); Perencanaan + Jadwal + RencanaAksi **245/245 (2120 assertions)**; `migrate:fresh`, `rollback --step=1`, dan `migrate` bersih; `pint` PASS (475 file); `phpstan` 0 error.

## Di luar lingkup

- ISS-03.03 (koreksi snapshot terkendali & versioning) — hanya constraint D5 yang dicatat.
- ISS-04.01 / issue #54 — blocker gate Issue #55 yang masih OPEN dan menahan PR #62; ditangani sebagai task terpisah.
