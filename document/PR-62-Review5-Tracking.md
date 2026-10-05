# PR-62 Review5 Tracking — Codex 3 temuan (base `0a1d339`)

Sumber: review Codex terbaru atas PR #62. Semua NEW (lanjutan Review4).
Satu session = satu task. S1 → S2 BERURUTAN (file overlapping: Preview/Simpan). JANGAN paralel. JANGAN commit/push tanpa perintah. DB dev JANGAN reset; PG disposable saja. Skill wajib: `laravel-best-practices` + `testing-best-practices` (+ FE bila perlu).

## S1 · Preview: ikat versi header + izin baca (F1, F2)

- Status: done
- F1 versi header di preview: kirim `expected_versi` ke endpoint preview dan validasi terhadap header terkunci sebelum hitung; usang → 409 seperti simpan (tanpa persistensi/audit). Mencegah skor/deviasi campuran (input form + target v2 tak terlihat).
- F2 izin baca dulu: `PreviewTargetPeriodeRequest::authorize()` syaratkan view DAN update bersama (sebelum validasi `exists`), agar tanpa `read` (atau kena deny) selalu 403 — bukan 422 yang membocorkan keberadaan UUID lintas unit.
- Regression: halaman v1 + header naik v2 → preview token lama ditolak 409; update-tanpa-read → preview selalu 403 (payload valid maupun tak valid).
- DoD: regression + RA suite hijau; pint + phpstan 0 errors (+ typecheck/vitest bila FE disentuh).
- Selesai: 2026-10-05 | Bukti: F1 `PreviewTargetPeriodeRequest` tambah `expected_versi required|integer|min:1` (cermin simpan) + `PreviewTargetPeriode::handle` bandingkan vs header `sharedLock` sebelum snapshot/hitung → usang 409 `expected_versi` tanpa tulis/audit; FE `TargetPreview` kirim `expectedVersi` + pesan 409 generik seperti simpan (`Data telah berubah…`), `Show` teruskan `rencanaAksi.expected_versi`. F2 `authorize()` kini `Gate::allows('view') && allows('update')` sebelum validasi `exists`. Migrasi payload preview lama ke `expected_versi` (`RencanaAksiPreviewTest` 4 panggilan + grant `read` pic, `Review4Q1` 1, `Review4Q2` 7 + asersi 422 kini termasuk `expected_versi`) + FE `RencanaAksiPreview.test.tsx` (prop `expectedVersi`, payload + pesan 409). Baru `RencanaAksiReview5S1Test` 2 regresi (v1→simpan v2→preview v1 409 tanpa periode/deviasi/tulis/audit + v2 OK; admin update-tanpa-read — peran tanpa read bawaan — preview valid+invalid selalu 403). Verifikasi PG disposable podman `postgres:17-alpine` port 5473 DB/user `sakip_test` (container `sakip_test_s1`; dev `sakip_db:5433` utuh): RA 63 hijau (15 preview/Q1/Q2/S1 + 26 target/snapshot/write/auth + 10 index/matrix/persist/read + 12 frozen/koreksi/lock/limit; S1 2/2); vitest RA 11/11 (4 file); `tsc --noEmit` hijau; `pint --dirty` passed; `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff S1:
Kerjakan S1 dari document/PR-62-Review5-Tracking.md (F1+F2) di branch feature/iss-05-01-target-rencana-aksi (lanjut working tree, JANGAN commit). Skill laravel-best-practices + testing-best-practices (+ inertia FE bila perlu: TargetPreview kirim expected_versi, tangani 409). PG disposable, pint + phpstan hijau. Update checkbox + Bukti. JANGAN commit.
```

## S2 · Bersihkan dimensi tak berlaku pada snapshot baru (F3)

- Status: done
- F3: saat snapshot koreksi mengubah tipe/komponen/periode_mulai, upsert hanya sel terkirim → baris snapshot lama melekat tanpa identitas, tersembunyi tapi bisa muncul kembali. Putuskan satu dan catat di Bukti: (a) hapus/nonaktifkan dimensi tak efektif saat konteks baru diterima (eksplisit + teraudit), atau (b) ikat tiap target pada snapshot asal + baca hanya pasangan cocok. Rekomendasi: (a) bila penghapusan aman diaudit; (b) bila histori per-snapshot harus utuh — putuskan dari kontrak audit/immutabilitas yang berlaku.
- Regression: snapshot v1 (komponen X) → koreksi v2 (tanpa X) → baris X tak lagi aktif/muncul; audit mencatat penyingkiran.
- DoD: sama seperti S1.
- Selesai: 2026-10-05 | Bukti: Keputusan (a) hapus eksplisit + teraudit. Alasan: histori per-snapshot yang utuh dijamin `rencana_aksi_versi` (kolom `snapshot` beku + trigger DB `reject_submission_version_mutation` + audit append-only), bukan tabel draf `rencana_aksi_target` (mutabel, tanpa trigger immutable, tak dirujuk versi beku) — sehingga penghapusan baris tak efektif aman selama dalam transaksi simpan yang sama dan terekam di `rencana_aksi.ubah` (selisih `nilai_lama`/`nilai_baru` + jumlah pada `alasan`). Opsi (b) ditolak: kolom snapshot-asal + filter pasangan-cocok di semua pembaca (Index/Preview/pengajuan) menduplikasi kontrak versi beku, menumpuk baris basi selamanya, dan tiap pembaca yang lupa filter = kebocoran lintas-konteks. Implementasi `SimpanTargetPeriode::bersihkanDimensiTakEfektif` setelah upsert, sebelum audit: hapus bila (periode ∉ efektif) ATAU (tipe manual: masih berkomponen) ATAU (tipe nonmanual: baris manual / komponen ∉ efektif); periode efektif yang tak terkirim (koreksi parsial 1-dari-N) dipertahankan. Baru `RencanaAksiReview5S2Test` 3 regresi: (1) v1 penjumlahan A+B → v2 tanpa B → baris B hilang dari DB, baca hanya A, audit `Membersihkan 1 baris…` + diff lama/baru, lalu v3 kembalikan B → nilai B `null` (bukan 100 basi, tanpa input user); (2) geser `periode_mulai` → 2 baris periode basi terhapus + teraudit; (3) ubah tipe → manual → 2 baris berkomponen terhapus + teraudit. Verifikasi PG disposable podman `postgres:17-alpine` port 5473 DB/user `sakip_test` (container `sakip_test_s2`; dev `sakip_db:5433` utuh): RA 66 hijau (63 lama + 3 S2); `pint` passed; `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff S2:
Kerjakan S2 dari document/PR-62-Review5-Tracking.md (F3) di branch yang sama (setelah S1), JANGAN commit. Aturan dan verifikasi sama. Update checkbox + Bukti. JANGAN commit.
```

## Verifikasi akhir (setelah S2)

- RA suite penuh + FE + pint + phpstan + typecheck. Re-review cukup delta setelah `0a1d339`.
