# PR-62 Review7 Tracking — Codex 3 temuan (base `988b558`)

Sumber: review Codex terbaru atas PR #62. Semua NEW (lanjutan Review6 T2). Target: tidak ada temuan susulan lagi di area ini.
Satu session = satu task. U1 → U2 BERURUTAN (file overlapping). JANGAN paralel. JANGAN commit/push tanpa perintah. DB dev JANGAN reset; PG disposable saja. Skill wajib: `laravel-best-practices` + `testing-best-practices`.

## U1 · Rekonsiliasi: jangan hapus input baru + bekukan sejak dibaca (F1, F2)

- Status: done
- F1 input baru vs basi: v1(B) → v2(tanpa B) → v3(B lagi) + user isi B di v3 → upsert dulu lalu purge hapus karena kunci masih di `$jejak['kunci']`; request sukses + versi naik tapi input hilang. Perbaiki: purge baris basi SEBELUM upsert, ATAU kecualikan dimensi yang eksplisit dikirim dalam konteks snapshot terbaru (pilih + catat; pastikan koreksi parsial — periode tak terkirim — tidak ikut terpurge).
- F2 bekukan sejak dibaca: header jepit v1 sampai save pertama v2, sementara baca/preview sudah pakai v2 (mutabel, trigger hanya cek pin). Perbaiki: saat baca/preview resolve snapshot terbaru untuk draf, majukan jepit (pin-on-read dalam transaksi baca, teraudit ringan/tanpa audit bila pola repo begitu) ATAU jadikan snapshot immutable sejak terbit. Pilih + catat; token ID+versi harus selalu mewakili konteks yang ditampilkan.
- Regression: F1 (v3 isi B baru → tersimpan, versi naik 1×, nilai utuh); F2 (v2 tampil di halaman → mutasi langsung v2 ditolak trigger/terdeteksi).
- DoD: regression + RA suite hijau; pint + phpstan 0 errors.
- Selesai: 2026-10-06 | Bukti: Keputusan F1 = kecualikan dimensi terkirim bernilai efektif-kini (BUKAN purge-sebelum-upsert). Alasan: purge-sebelum meninggalkan baris kosong (null) untuk kiriman-dikosongkan sehingga menyimpang dari kontrak T2 (basi terkirim-kosong dibuang bagai tak ada); pengecualian hanya bernilai (nilai/keterangan non-null) + efektif-kini — kiriman kosong tetap dibersihkan, parsial tak-terkirim dipertahankan (tak ada di himpunan basi), kiriman basi tak-efektif-kini tetap milik bersihkanDimensiTakEfektif. Implementasi `SimpanTargetPeriode::kunciKirimBernilaiEfektif` + `array_diff` sebelum `buangKunciBasi`; docblock purge diperbarui. Keputusan F2 = immutable-sejak-terbit (BUKAN pin-on-read). Alasan: pin-on-read memajukan jepit tanpa membersihkan → bacaan kedua bangkitkan 100 (jejak hilang) + parsial berikutnya bangkitkan periode tak-terkirim; bersih-saat-baca jadikan GET destruktif tanpa audit. Immutable menutup jendela v2 tanpa tulis-di-baca (migrasi baru `2026_10_05_130000_snapshot_immutable_sejak_terbit_rencana_aksi_u1`: UPDATE/DELETE snapshot selalu 23514, UPDATE/DELETE komponen selalu 23514, INSERT komponen pertahankan guard rujukan; down() kembalikan varian jepit Review6 T2). Sisipan komponen via INSERT langsung diterima sadar-risiko (hanya tambah baris kosong, koreksi resmi tetap berversi). Adaptasi 2 fixture anti-pola mutasi in-place pra-draf ke buat-langsung-benar (tanpa ubah ekspektasi versi): `RencanaAksiPreviewTest::buatFixtureManual(string $tipe)` + `buatFixtureRasio` tanpa UPDATE; `RencanaAksiFrozenSnapshotTest::buatFixtureManualRasio(suffix, mulaiKedua)` + `buatFixtureRasioMulaiKedua` tanpa UPDATE. Baru `tests/Feature/RencanaAksi/RencanaAksiReview7U1Test.php` 2 regresi (F1: v3 parsial p1 A=55 B=200 tersimpan utuh, versi 2→3, p2 A=30 dipertahankan, p2 B basi terbuang; F2: v2 terbit langsung 23514 walau belum dijepit + token halaman v2 + jepit tetap v1 + versi tetap). Kontrol negatif (stash fix): F1 gagal `No query results` (B=200 terhapus), F2 gagal pin tetap v1 (tanpa immutable mutasi lolos). Verifikasi PG disposable podman `postgres:17-alpine` port 5476 DB/user `sakip_test` (container `sakip_test_u1` dihapus; dev `sakip_db:5433` utuh): U1 2/2 hijau; fokus 11/11 (U1+T2+Frozen+Preview); RA penuh 76/76 hijau (929 assertions); rollback+re-migrate U1 bersih; `pint --dirty` passed; `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff U1:
Kerjakan U1 dari document/PR-62-Review7-Tracking.md (F1+F2) di branch feature/iss-05-01-target-rencana-aksi (lanjut working tree, JANGAN commit). Skill laravel-best-practices + testing-best-practices. PG disposable, pint + phpstan hijau. Update checkbox + Bukti. JANGAN commit.
```

## U2 · Backfill jepit untuk draf lama saat migrasi (F3)

- Status: done
- F3: migrasi penambah `snapshot_draf_id` membiarkan NULL tanpa backfill; draf lama tak terlindungi sampai save berikutnya. Manfaatkan tabel backup pemetaan snapshot lama dari migrasi sebelumnya untuk backfill deterministik (NULL hanya bila memang tak ada snapshot/peta; catat kriteria di Bukti + down() aman).
- Regression: seed draf lama + jalankan migrasi → pin terisi sesuai peta backup; tanpa peta → NULL + perilaku fail-closed yang benar (baca/simpan menolak atau meminta konteks).
- DoD: rollback + migrate penuh bersih; RA suite hijau; pint + phpstan 0 errors.
- Selesai: 2026-10-06 | Bukti: Keputusan = backfill deterministik ke snapshot asal dari `_backup_rencana_aksi_jadwal_snapshot_20261004` (BUKAN tebak terbaru agar jejak transisi pin→terbaru tetap deteksi basi). Kriteria NULL: tanpa baris backup (draf pasca-cutover D7/tabel hilang) ATAU peta NULL ATAU snapshot rujukan sudah tak ada (INNER JOIN jadwal_snapshot); NULL = fail-closed telusur-penuh pin 0. Interaksi trigger U1 aman: backfill UPDATE rencana_aksi, guard terpasang pada jadwal_snapshot/komponen (bukan header) → tanpa 23514. down() aman: hanya NULL-kan baris yang masih = peta backup (jepit maju ke koreksi baru dipertahankan). Baru `2026_10_05_160925_backfill_snapshot_draf_rencana_aksi_u2` (guard hasColumn/hasTable, tanpa import App) + baru `RencanaAksiReview7U2Test` 2 regresi (peta→pin v1 + non-NULL tak ditimpa + yatim tetap NULL + 23514 utuh + down selektif; tanpa-peta→NULL + pin 0 + baca token v1 + simpan null-token 409 web+JSON + simpan benar sembuh ke v1). Verifikasi PG disposable podman postgres:17-alpine port 5476 DB/user sakip_test (container sakip_test_u2; dev sakip_db:5433 utuh): U2 2/2 (34 assertions); RA penuh 78/78 (963 assertions, 76 lama inkl U1 + 2 baru); rollback --step=2 + migrate bersih; migrate:fresh bersih; pint passed (fix no_unused_imports U2Test); phpstan 0 errors. Tanpa commit.

```text
Prompt handoff U2:
Kerjakan U2 dari document/PR-62-Review7-Tracking.md (F3) di branch yang sama (setelah U1), JANGAN commit. Aturan dan verifikasi sama (termasuk rollback+migrate bersih). Update checkbox + Bukti. JANGAN commit.
```

## Verifikasi akhir (setelah U2)

- RA suite penuh + FE + pint + phpstan + typecheck. Re-review cukup delta setelah `988b558`.
