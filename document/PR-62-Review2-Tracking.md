# PR-62 Review2 Tracking — Codex 12 temuan (base `a4ff121` + working tree M1/M2 belum commit)

Sumber: review Codex terbaru atas HEAD `a4ff121` (Codex tidak melihat M1/M2 karena belum commit).
Triase main session:
- COVERED (tidak dikerjakan ulang, cukup verifikasi regresi): T2 (tanpa snapshot → tolak) = M2 `pastikanSnapshotTersedia`; T3 (pembuat draf = PIC efektif) = M1; T4 (guard jendela saat create) = M1.
- NEW (dikerjakan di sini): T1, T5, T6, T7, T8, T9, T10, T11, T12.
Satu session = satu task. N1 → N2 → N3 BERURUTAN (file overlapping). JANGAN paralel. JANGAN commit/push tanpa perintah. DB dev JANGAN reset; PG disposable saja. Skill wajib: `laravel-best-practices` + `testing-best-practices` (+ FE skill untuk N3).

## N1 · Guard domain tulis: scope koreksi + unit + timestamp (T1, T8, T9, T11)

- Status: done
- T1 koreksi per-periode: saat jadwal lewat penutupan, validasi `lingkup_koreksi.periode_ids` harus terhadap setiap `periode_id` dalam REQUEST (bukan yang tersimpan); tanpa target lama pun tetap divalidasi. File kemungkinan `SimpanTargetPeriode.php` (correction path).
- T8 unit nonaktif: jalur update harus kunci + tolak bila unit header nonaktif (seperti jalur create). Perencanaan global tidak mengecualikan ini.
- T9 unit header vs snapshot: bila indikator pindah unit pasca-aktivasi (snapshot = A, master = B), tolak pembuatan sampai snapshot koreksi tersedia (atau pakai unit snapshot sebagai identitas tahun itu — putuskan satu, catat di Bukti). Jangan biarkan auth pakai B sementara pengukuran menarget A.
- T11 timestamp: `RencanaAksi` nonaktifkan timestamp otomatis + create tidak isi `created_at/updated_at` (nullable) → isi eksplisit / aktifkan timestamp model + bump `updated_at` tiap mutasi target.
- Regression minimal per temuan (T1: kirim periode luar lingkup → ditolak walau tak ada target lama; T8: unit dinonaktifkan → update ditolak; T9: pindah unit pasca-aktivasi → create ditolak; T11: header baru punya timestamp terisi + berubah saat simpan).
- DoD: regression baru + `tests/Feature/RencanaAksi` penuh hijau; pint + phpstan 0 errors.
- Selesai: 2026-10-05 | Bukti: N1 dikerjakan di atas working tree M1/M2 (HEAD `a4ff121`, tanpa commit). File diubah: `app/Actions/RencanaAksi/SimpanTargetPeriode.php` (T1: `dalamKoreksiSah` kini memvalidasi tiap `periode_id` REQUEST terhadap `lingkup_koreksi.periode_ids`, bukan target tersimpan; T8: kunci `sharedLock` + tolak bila unit header nonaktif SEBELUM cabang Perencanaan sehingga jalur global tidak dikecualikan — perlu karena grant unit-scoped gugur saat unit nonaktif tetapi izin peran Perencanaan tetap lolos resolver), `app/Actions/RencanaAksi/EnsureDraftRencanaAksi.php` (T9: `pastikanSnapshotTersedia` menolak create bila `snapshot.unit_id != indikator.unit_id`), `app/Models/RencanaAksi.php` (T11: hapus `$timestamps = false` sehingga `created_at/updated_at` terisi otomatis + `save()` tiap mutasi target menaikkan `updated_at`; kolom sudah ada nullable dari migrasi dasar, tanpa migrasi baru), `tests/Feature/RencanaAksi/RencanaAksiWriteGuardTest.php` (baru, 4 regresi: koreksi tolak periode luar lingkup tanpa target lama + terima yang dalam lingkup; update ditolak unit nonaktif utk Perencanaan 422 `unit_id` dan PIC 403; create ditolak 422 `snapshot` saat unit snapshot tak selaras; header baru punya timestamp + `updated_at` naik saat simpan). Keputusan T9: TOLAK create sampai snapshot koreksi tersedia (bukan pakai unit snapshot sebagai identitas). Alasan: identitas unit tahun itu mengikuti snapshot beku — konsisten dengan `PengukuranKinerja::targetUnitId()` (= `jadwal_snapshot.unit_id`), `SubmissionPrerequisites` (syarat `rencana_aksi.unit_id` cocok unit snapshot pengukuran), dan `RencanaAksiPolicy::update` (pakai `header.unit_id`); mengadopsi diam-diam unit snapshot akan menyembunyikan transfer kepemilikan dan membuka split-brain auth (create pakai master B, downstream pakai snapshot A), sedangkan fail-closed memaksa snapshot koreksi eksplisit beraudit, selaras filosofi T2/M2. Verifikasi: `tests/Feature/RencanaAksi` 33/33 hijau (29 lama + 4 baru), `AuthorizationHttpRegressionTest + CanonicalPengukuranTest` 18/18, `PengukuranWorkflowTest + PreviewPengukuranTest` 9/9 (PG disposable `sakip_test` via 127.0.0.1:5433; DB dev tak disentuh); `pint` bersih; `phpstan --memory-limit=1G` 0 errors.

```text
Prompt handoff N1:
Kerjakan N1 dari document/PR-62-Review2-Tracking.md (T1+T8+T9+T11) di branch feature/iss-05-01-target-rencana-aksi (lanjut working tree M1/M2, JANGAN commit). Skill laravel-best-practices + testing-best-practices. PG disposable saja, pint + phpstan hijau. Update checkbox + Bukti. JANGAN commit.
```

## N2 · Konsistensi baca + lock order (T5, T7)

- Status: done
- T5 lock order: `EnsureDraftRencanaAksi` kunci indikator→header, `SimpanTargetPeriode` kunci header→indikator → risiko deadlock antar transaksi bersamaan. Samakan urutan lock (atau tambah retry terdokumentasi — putuskan satu, catat di Bukti).
- T7 snapshot baca konsisten: controller muat header sebelum action + query terpisah tanpa transaksi baca → `expected_versi` bisa tak mewakili target yang ditampilkan. Pakai transaksi baca konsisten / muat ulang + verifikasi versi header setelah seluruh data dibaca. File: `ShowRencanaAksi.php` + `IndexRencanaAksi.php`.
- Regression: T5 (bukti sekuensial dua-koneksi seperti pola LockOrderTest bila ada, atau minimal dokumentasi urutan + test tidak-deadlock); T7 (tulis di tengah baca → payload konsisten, tanpa konflik palsu).
- DoD: sama seperti N1.
- Selesai: 2026-10-05 | Bukti: N2 di atas working tree N1 (HEAD `a4ff121`, tanpa commit). Keputusan T5: SAMAKAN URUTAN deterministik RencanaAksi→Indikator→Jadwal→Snapshot di kedua jalur tulis, TANPA retry (retry hanya menyembunyikan inversi; antrean kunci menserialkan = 40P01 hilang). `EnsureDraftRencanaAksi` tambah kunci ordering `RencanaAksi::where(indikator_id,tahun)->lockForUpdate()->first()` (mungkin nihil) SEBELUM kunci Indikator — docblock urutan diperbarui; `SimpanTargetPeriode` tanpa ubah urutan (sudah header→indikator→jadwal) + docblock kanonik + catatan bacaan-tanpa-kunci tak ikut urutan. T7: `IndexRencanaAksi::handle` kini `DB::transaction` + muat ulang header via `sharedLock` (akuisisi header-dahulu = antre dgn penulis, bukan deadlock) + seluruh query turunan di transaksi yang sama + verifikasi ulang versi header setelah baca, diulang maks 3x sebelum fail-closed; `expected_versi` dari versi terverifikasi, bukan model pra-transaksi; `ShowRencanaAksi` tambah komentar (pra-load hanya utk 404 + Gate, `unit_id` imutabel). File diubah: `app/Actions/RencanaAksi/EnsureDraftRencanaAksi.php`, `app/Actions/RencanaAksi/SimpanTargetPeriode.php` (docblock), `app/Actions/RencanaAksi/IndexRencanaAksi.php`, `app/Http/Controllers/RencanaAksi/ShowRencanaAksi.php` (komentar), baru `tests/Feature/RencanaAksi/RencanaAksiLockOrderTest.php` (T5: create→update sekuensial sukses + idempoten; dua-koneksi dua ronde Ensure↔Simpan pegang-header-dahulu → waiter 55P03 bukan 40P01 + kunci kedua indikator lolos, DatabaseMigrations pola R2-21), baru `tests/Feature/RencanaAksi/RencanaAksiReadConsistencyTest.php` (T7: model basi v2 + tulis v3 → payload segar v3 + target v3; GET→simpan→GET→simpan tanpa 409 palsu). Verifikasi: `tests/Feature/RencanaAksi` 37/37 hijau (33 lama inkl. N1 + 4 baru) di PG disposable podman `postgres:17-alpine` port 5462 DB/user `sakip_test` (container `sakip_test_n2` dihapus; dev `sakip_db` utuh); `pint --dirty` passed; `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff N2:
Kerjakan N2 dari document/PR-62-Review2-Tracking.md (T5+T7) di branch yang sama (setelah N1), JANGAN commit. Aturan dan verifikasi sama. Update checkbox + Bukti. JANGAN commit.
```

## N3 · Payload + FE: metadata snapshot, versi form, batas matriks (T10, T6, T12)

- Status: done
- T10 metadata dari snapshot: payload indikator (nama, satuan, arah, tipe, desimal_tampilan) masih dari master → ambil dari `jadwal_snapshot` untuk konteks RA ini.
- T6 segarkan token versi: `Show.tsx` key = ID tak berubah → `useForm` tak remount, `expected_versi` usang → 409 kedua. Sertakan versi dalam key atau sinkronkan form saat props berubah.
- T12 batas matriks: validasi tolak >100 sel sementara UI kirim semua kombinasi periode×komponen (konfigurasi sah 10×11 = 110 tak bisa disimpan). Selaraskan: naikkan/pecah payload sesuai batas domain terdokumentasi atau batasi konfigurasi konsisten — putuskan satu, catat di Bukti (cek dulu angka 100 di validator).
- Regression: T10 (master metadata drift → label tetap snapshot); T6 (simpan 2× berurutan tanpa reload → tanpa 409 palsu); T12 (matriks besar sah → tersimpan).
- Verifikasi: Pest terkait + `bun run typecheck` + `bun run test` (Vitest). Skill tambah `inertia-react-development`.
- DoD: sama + FE hijau.
- Selesai: 2026-10-05 | Bukti: N3 di atas working tree N2 (HEAD `a4ff121`, tanpa commit). T10: `IndexRencanaAksi::handle` kini membangun `indikatorPayload` dari master lalu menimpa `nama/satuan/arah/tipe_perhitungan/presisi/desimal_tampilan` dari `JadwalSnapshot` bila ada (kode/status/tahun_mulai_berlaku tetap master; tanpa snapshot fallback master untuk jadwal draft). T6: `Show.tsx` key `id` → `` `${id}::${versi}` `` sehingga `useForm` remount saat Inertia mengembalikan versi baru pasca-simpan; versi sama (validasi gagal) tetap mempertahankan draf. T12 KEPUTUSAN: NAIKKAN batas `targets` `max:100` → `max:600` (= 50 komponen × 12 periode; 50 dari `ChangeIndicatorFormulaRequest`, 12 periode bulanan). Angka 100 lama arbitrer dan menolak matriks sah 10×11=110 yang dikirim utuh oleh halaman; pecah-payload menambah transaksi parsial/UX, batasi-konfigurasi menolak konfigurasi sah — single-request tetap atomik satu transaksi, 600 baris masih murah. File diubah: `app/Actions/RencanaAksi/IndexRencanaAksi.php`, `app/Http/Requests/RencanaAksi/SimpanTargetPeriodeRequest.php`, `resources/js/Pages/RencanaAksi/Show.tsx`, baru `tests/Feature/RencanaAksi/RencanaAksiPayloadMatrixTest.php` (T10 drift-master→tetap snapshot; T12 10 periode×11 penjumlah=110 tersimpan, skor `110.00` terhitung), `tests/Frontend/RencanaAksiMatriks.test.tsx` (+1 T6: rerender versi 2 → post `expected_versi:2`). Verifikasi: `tests/Feature/RencanaAksi` 39/39 hijau (37 lama inkl. N1/N2 + 2 baru) di PG disposable podman `postgres:17-alpine` port 5463 DB `sakip_test` (container `sakip_test_n3` dihapus; dev `sakip_db` utuh); Vitest 36 file/250 test hijau (RencanaAksiMatriks 5/5); `bun run typecheck` hijau; `pint --dirty` passed; `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff N3:
Kerjakan N3 dari document/PR-62-Review2-Tracking.md (T10+T6+T12) di branch yang sama (setelah N2), JANGAN commit. Aturan backend sama + skill inertia-react-development untuk FE. Verifikasi Pest + typecheck + vitest. Update checkbox + Bukti. JANGAN commit.
```

## Verifikasi akhir (setelah N3)

- `tests/Feature/RencanaAksi` penuh + FE test + pint + phpstan + typecheck. Pastikan T2/T3/T4 tetap covered oleh M1/M2 (regresi M1/M2 hijau). Re-review cukup delta setelah `a4ff121`.
