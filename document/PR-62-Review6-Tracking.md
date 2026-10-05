# PR-62 Review6 Tracking — Codex 4 temuan (base `881b3a5`)

Sumber: review Codex terbaru atas PR #62. Semua NEW (lanjutan Review5).
Satu session = satu task. T1 → T2 → T3 BERURUTAN (file overlapping). JANGAN paralel. JANGAN commit/push tanpa perintah. DB dev JANGAN reset; PG disposable saja. Skill wajib: `laravel-best-practices` + `testing-best-practices` (+ FE bila perlu).

## T1 · Preview tolak bila header tak ditemukan (F1)

- Status: done
- F1: UUID RA tak ada → `authorize()` lolos → `exists` jalan → oracle 422-vs-404 untuk UUID lintas unit. Kembalikan false di sini atau route-model binding sebelum FormRequest (pilih yang sesuai pola repo; tanpa ubah kontrak route publik bila bisa).
- Regression: UUID asing tanpa izin → selalu 403/404, tak ada 422; UUID asing dengan izin → 404.
- DoD: regression + RA suite hijau; pint + phpstan 0 errors.
- Selesai: 2026-10-05 | Bukti: F1 `PreviewTargetPeriodeRequest::authorize()` header tak ditemukan kini `abort(404)` SEBELUM validasi `exists` (bukan `return true`); lookup mendahului Gate agar urutan 404/403 tetap sama. Keputusan: abort-404, bukan murni `return false` (itu memberi 403 untuk kasus dengan-izin yang wajib 404) dan bukan implicit binding (repo memakai string+`whereUuid`+`findOrFail` di semua controller RA + `ShowRencanaAksi`; binding akan ubah signature + pecah `authorize()` yang expect string) — cermin pola repo `UpdateUnitRequest::authorize` (`findOrFail` sebelum `Gate`) + preseden `abort(404)` di `DestroyBerkasRenstraRequest`; kontrak route publik tak berubah (tetap string + `whereUuid`). Baru `tests/Feature/RencanaAksi/RencanaAksiReview6T1Test.php` 3 regresi (asing+dengan-izin valid+invalid→404; asing+tanpa-izin valid+invalid→403/404 tanpa 422; lintas-unit-tanpa-izin+invalid→403 + sanity header sah tetap 200). Kontrol negatif: tanpa fix 2/3 gagal dengan oracle persis (`Expected 404 but received 422`, `expected_snapshot_id`/`targets.0.periode_id tidak ditemukan). Verifikasi PG disposable podman `postgres:17-alpine` port 5474 DB/user `sakip_test` (container `sakip_test_t1`; dev `sakip_db:5433` utuh): RA 69 hijau (66 lama + 3 baru); `pint --dirty` passed; `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff T1:
Kerjakan T1 dari document/PR-62-Review6-Tracking.md (F1) di branch feature/iss-05-01-target-rencana-aksi (lanjut working tree, JANGAN commit). Skill laravel-best-practices + testing-best-practices. PG disposable, pint + phpstan hijau. Update checkbox + Bukti. JANGAN commit.
```

## T2 · Immutabilitas snapshot draf + rekonsiliasi transisi (F2, F3)

- Status: done
- F2 stale transisi: pembersihan hanya pasca-POST; v1(B) → v2(tanpa B, tanpa save) → v3(B lagi) = nilai v1 hidup kembali. Kaitkan target dengan snapshot asal atau rekonsiliasi saat snapshot berubah (bukan hanya pasca-POST). Putuskan + catat di Bukti.
- F3 bekukan snapshot draf: snapshot terbaru yang dipakai draf tak dilindungi trigger (hanya yang dirujuk pengukuran/versi). Buktinya test yang update snapshot langsung masih lolos. Jadikan snapshot terbit immutable global (trigger/pola repo) ATAU simpan rujukan snapshot di draf agar trigger+token mendeteksi perubahan. Putuskan + catat di Bukti. Perhatian: putusan berinteraksi dengan D7 (buang FK header) — bila perlu rujukan kembali dalam bentuk kolom non-FK/audit-safe, eksplisitkan sebagai revisi D7 yang sempit (tanpa mengembalikan FK otorisasi).
- Regression: F2 (lewati v2 tanpa save → v3 tak bangkitkan nilai basi); F3 (update snapshot langsung pasca-pakai draf → ditolak trigger/terdeteksi token).
 - DoD: sama seperti T1 (termasuk migrasi trigger bila dipilih — down() aman, pola R2-04b/R2-09).
- Selesai: 2026-10-05 | Bukti: Keputusan F2 = rekonsiliasi saat snapshot berubah (bukan hanya pasca-POST), BUKAN ikat target pada snapshot asal. Alasan: Review5 S2 sudah menolak kolom snapshot-asal per baris (menduplikasi kontrak versi beku, menumpuk baris basi, tiap pembaca yang lupa filter = kebocoran lintas-konteks) — lubang tersisa hanya jendela tanpa-simpan, ditutup via jepit header `rencana_aksi.snapshot_draf_id` + telusur versi antara di service baru `RekonsiliasiTargetDraf`: baris basi = dimensi tak efektif pada satu pun versi (jepit→terbaru); baca (Index/Preview) menyaring bagai tak ada tanpa efek samping, tulis (Simpan) membuang berdasarkan kunci dimensi SETELAH upsert (agar kiriman basi ikut terbuang) + memajukan jepit + audit `Rekonsiliasi transisi snapshot vP->vC: N baris basi dibersihkan`; sel tak efektif-kini tetap milik `bersihkanDimensiTakEfektif` (kontrak S2 utuh — 3 test S2 hijau tanpa ubah ekspektasi). Keputusan F3 = simpan rujukan snapshot di draf (kolom non-FK audit-safe, revisi D7 sempit tanpa kembalikan FK otorisasi; izin tetap unit-based, `snapshot_draf_id: prohibited` di request simpan, tak diekspos ke klien), BUKAN trigger immutable global. Alasan: global memblokir koreksi-sisipan sah (komponen pelengkap versi baru) + koreksi pra-draf dan menyimpang dari filosofi beku-berbasis-rujukan repo; trigger `guard_referenced_schedule_snapshot()` diperluas satu klausa `rencana_aksi.snapshot_draf_id` (migrasi baru `2026_10_05_120000_rekonsiliasi_snapshot_draf_rencana_aksi_f2_f3`, tanpa sentuh migrasi lama, konstanta/teks fungsi lokal, down() aman tanpa backup karena kolom nullable + NULL=fail-closed telusur-penuh — rollback+migrate penuh teruji). Jepit ditulis server saja (EnsureDraft saat buat, Simpan tiap simpan; draf lawas NULL = telusur-penuh fail-closed). Adaptasi 3 test lama yang mutasi snapshot in-place (anti-pola yang kini diblokir): Review4Q1 2 test + WriteGuard 1 test kini terbitkan koreksi berversi v2 (token ikut v2); FrozenSnapshotTest kini menegaskan penolakan 23514 + nilai tak berubah (target 777 ditolak, baca tetap 100). Baru `tests/Feature/RencanaAksi/RencanaAksiReview6T2Test.php` 2 regresi: (1) v1(A+B) → v2-tanpa-B tanpa save → v3: baca B null (bukan 100), A 50 utuh, simpan-v3 buang B + audit rekonsiliasi v1->v3 + jepit maju v3; (2) update/delete snapshot terjepit → 23514 via savepoint, sisipan koreksi v2 terbuka, simpan-v2 majukan jepit, mutasi v2 ikut diblokir. Kontrol negatif: tanpa filter baca, test F2 gagal persis temuan (`'100.000000000000'` bangkit, bukan null). Paruh token F3 (409 versi baru) tak diduplikasi — sudah dijamin `RencanaAksiSnapshotConcurrencyTest`. Perhatian T3: predikat periode-efektif kini hidup di 4 situs (Simpan/Index/Preview + `RekonsiliasiTargetDraf::periodeEfektifVersi`) — T3 wajib ubah keempatnya konsisten (ditandai di docblock service). Verifikasi PG disposable podman `postgres:17-alpine` port 5475 DB/user `sakip_test` (container `sakip_test_t2`; dev `sakip_db:5433` utuh): RA 71 hijau (66 lama + 3 T1 + 2 T2); pengukuran+authorization 143 hijau; rollback+migrate penuh bersih; `pint --dirty` passed; `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff T2:
Kerjakan T2 dari document/PR-62-Review6-Tracking.md (F2+F3) di branch yang sama (setelah T1), JANGAN commit. Aturan dan verifikasi sama. Update checkbox + Bukti. JANGAN commit.
```

## T3 · Periode-mulai snapshot didahulukan (F4)

- Status: done
- F4: tulis/baca/preview memakai master `tahun_mulai_berlaku` sebelum `snapshot.periode_mulai_id`; koreksi tahun master ke atas pasca-aktivasi membuat semua periode tak efektif. Bila snapshot ada → `periode_mulai_id` snapshot sumber efektivitas; tahun master hanya untuk konteks tanpa snapshot. Berlaku di Simpan + Index + Preview.
- Regression: snapshot periode_mulai=TW II + master dikoreksi ke atas → periode ≥ TW II tetap efektif.
- DoD: sama seperti T1.
- Selesai: 2026-10-05 | Bukti: Predikat periode-efektif disamakan di 4 situs — bila snapshot ada, `periode_mulai_id` snapshot satu-satunya sumber (tahun master diabaikan); tahun master hanya bila snapshot null (jadwal draf). `SimpanTargetPeriode::periodeEfektif` + `IndexRencanaAksi::periodeEfektifIds` + `PreviewTargetPeriode::periodeEfektifIds` = cek snapshot dulu, gerbang tahun hanya jalur tanpa-snapshot; `RekonsiliasiTargetDraf` = hapus fetch live `tahun_mulai_berlaku` + param `header/tahunMulai` di `efektifPadaKonteks` (selalu bersnapshot → tanpa gerbang tahun; `periodeEfektifVersi` memang sudah per-versi snapshot) + docblock F4 di keempat situs; import `IndikatorKinerja` tak terpakai dibuang. Baru `tests/Feature/RencanaAksi/RencanaAksiReview6T3Test.php` 3 regresi (fixture manual 3 triwulan, snapshot-mulai TW II, master 2025→2027, header 2026): tulis TW II+III lolos + TW I tetap 422; baca TW I efektif=false + TW II/III true beserta nilai tersimpan (rekonsiliasi tak menyaring sebagai basi); preview TW II/III ok + skor 25.00 + TW I 422. Kontrol negatif: kembalikan gerbang tahun dulu di Simpan → test tulis gagal persis (`No query results for model [RencanaAksiTarget]`, TW II ditolak tak tersimpan). Verifikasi PG disposable podman `postgres:17-alpine` port 5475 DB/user `sakip_test` (container `sakip_test_t3` dihapus; dev `sakip_db:5433` utuh): T3 3/3 hijau (38 assertions); RA penuh 74 hijau (71 lama inkl. T1+T2 + 3 baru, 898 assertions); `pint --dirty` passed; `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff T3:
Kerjakan T3 dari document/PR-62-Review6-Tracking.md (F4) di branch yang sama (setelah T2), JANGAN commit. Aturan dan verifikasi sama. Update checkbox + Bukti. JANGAN commit.
```

## Verifikasi akhir (setelah T3)

- RA suite penuh + FE + pint + phpstan + typecheck. Re-review cukup delta setelah `881b3a5`.
