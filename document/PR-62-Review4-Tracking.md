# PR-62 Review4 Tracking — Codex 4 temuan (base `52b7b8d`)

Sumber: review Codex terbaru atas PR #62. Semua NEW (lanjutan P1-P3).
Satu session = satu task. Q1 → Q2 BERURUTAN (file overlapping). JANGAN paralel. JANGAN commit/push tanpa perintah. DB dev JANGAN reset; PG disposable saja. Skill wajib: `laravel-best-practices` + `testing-best-practices` (+ FE bila perlu).

## Q1 · Token wajib + guard unit di baca/pratinjau (F1, F4)

- Status: done
- F1 token wajib: `expected_snapshot_id/versi` saat ini opsional demi klien lama → request tanpa token melewati blok pembanding sepenuhnya. Wajibkan kedua kunci (boleh null hanya bila konteks memang tanpa snapshot). Semua jalur tulis menegakkan token.
- F4 guard unit di baca: `IndexRencanaAksi` + preview memuat snapshot terbaru milik B untuk header milik A tanpa guard (tulis sudah menolak). Terapkan guard keselarasan yang sama sebelum membangun payload baca/pratinjau (tolak + tanpa ekspos konteks lintas-unit).
- Regression: tanpa token → ditolak (kecuali konteks tanpa snapshot); header A + snapshot B → baca dan preview ditolak.
- DoD: regression + RA suite hijau; pint + phpstan 0 errors.
- Selesai: 2026-10-05 | Bukti: F1 `SimpanTargetPeriodeRequest` token `present+nullable` (null hanya sah bila tanpa snapshot; pesan `present` generik + `targets.*.nilai.present` spesifik) + `SimpanTargetPeriode::handle` hapus `array_key_exists` bypass → selalu bandingkan token vs snapshot terkunci (hilang→null→409 bila snapshot ada); F4 `IndexRencanaAksi::handle` + `PreviewTargetPeriode::handle` guard unit (`snapshot.unit_id` vs `header.unit_id`) segera setelah `snapshotEfektif`, sebelum payload, pesan generik tanpa ekspos lintas-unit (422 `snapshot`). Migrasi 11 file uji lama ke token v1/v2/null (tanpa-snapshot→null, v2→v2) agar RA suite hijau. Baru `tests/Feature/RencanaAksi/RencanaAksiReview4Q1Test.php` 5 regresi (tanpa-token→422 web+JSON versi tetap target nihil; null-eksplisit-dengan-snapshot→409 + audit ubah_ditolak; null-dengan-tanpa-snapshot-draft→tersimpan v2; baca A+B→422 snapshot + Action throw tanpa bocor nama/id B; preview A+B→422 snapshot tanpa kunci periode/deviasi + versi tetap). Verifikasi PG disposable podman `postgres:17-alpine` port 5471 DB/user `sakip_test` (container `sakip_test_q1`; dev `sakip_db:5433` utuh): RA 4 batch hijau (19+9+19+9; Q1 5/5 inklusif); `pint --dirty` passed; `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff Q1:
Kerjakan Q1 dari document/PR-62-Review4-Tracking.md (F1+F4) di branch feature/iss-05-01-target-rencana-aksi (lanjut working tree, JANGAN commit). Skill laravel-best-practices + testing-best-practices. PG disposable, pint + phpstan hijau. Update checkbox + Bukti. JANGAN commit.
```

## Q2 · Pratinjau terikat token + validasi set-based (F2, F3)

- Status: done
- F2 preview terikat token: endpoint preview selalu pakai snapshot terbaru tanpa terima/bandingkan token halaman. Kirim token snapshot ke preview dan tolak konteks usang (atau hitung eksplisit terhadap snapshot immutable dari token). Simpan dan preview harus konsisten: yang ditampilkan = yang dipakai simpan.
- F3 set-based: loop per-sel `exists()` periode (s/d 600 query) + shared lock tertahan. Kumpulkan ID periode unik, validasi sekaligus (atau andalkan `exists:periode,id` di request) sebelum proses sel.
- Regression: halaman v1 + snapshot v2 terbit → preview pakai token lama ditolak/usang (bukan tampilkan v2 diam-diam); preview matriks besar tanpa N+1 (assert query count atau minimal perilaku sama).
- Verifikasi: Pest + typecheck/vitest bila FE disentuh.
- DoD: sama seperti Q1 + FE hijau bila disentuh.
- Selesai: 2026-10-05 | Bukti: F2 `PreviewTargetPeriodeRequest` token `present+nullable` (cermin simpan) + `PreviewTargetPeriode::handle` bandingkan token vs snapshot terbaru terkunci → usang/null-dengan-snapshot ditolak 409 `expected_snapshot_id` (tanpa kunci periode/deviasi; null sah hanya tanpa snapshot); FE `Show→TargetPreview` kirim `expectedSnapshotId/Versi` + payload preview sertakan token + pesan 409 muat-ulang. F3 `SimpanTargetPeriode::pastikanTargetsSah` + `PreviewTargetPeriode::pastikanTargetsPratinjau` set-based (`whereIn` ID unik sekali, bukan `exists` per-sel; lock/sharedLock tak tertahan 600 query). Migrasi `RencanaAksiPreviewTest` (3 file) + `RencanaAksiReview4Q1Test` preview ke token v1 agar capai guard. Baru `tests/Feature/RencanaAksi/RencanaAksiReview4Q2Test.php` 5 regresi (v1-usang→409 tanpa periode/deviasi + v2→200 target_pk 150; tanpa-token→422 + null-dengan-snapshot→409; null-tanpa-snapshot→200 skor 25.00 tanpa tulis/audit; preview-vs-simpan konsisten v2 + tolak v1; 12-periode set-based ≤3 query `periode` via Action langsung + invalid-UUID→422 + simpan 12 OK). FE `RencanaAksiPreview.test.tsx` kirim token + asersi 409. Verifikasi PG disposable podman `postgres:17-alpine` port 5472 DB/user `sakip_test` (container `sakip_test_q2`; dev `sakip_db:5433` utuh): RA 61 hijau (13+17+10+21; Q2 5/5 inklusif); vitest 260/260 (39 file); `tsc --noEmit` hijau; `pint --dirty` passed (Q2Test ordered-imports); `phpstan --memory-limit=1G` 0 errors. Tanpa commit.

```text
Prompt handoff Q2:
Kerjakan Q2 dari document/PR-62-Review4-Tracking.md (F2+F3) di branch yang sama (setelah Q1), JANGAN commit. Skill backend + inertia-react-development bila FE disentuh. Update checkbox + Bukti. JANGAN commit.
```

## Verifikasi akhir (setelah Q2)

- RA suite penuh + FE + pint + phpstan + typecheck. Re-review cukup delta setelah `52b7b8d`.
