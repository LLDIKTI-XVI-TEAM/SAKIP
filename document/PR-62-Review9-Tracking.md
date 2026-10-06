# PR-62 Review9 Tracking — Codex 2 temuan race (base `54da66d`)

Sumber: review Codex terbaru atas PR #62. Semua NEW (pengerasan Review8).
Satu session = satu task. W1 → W2 BERURUTAN. JANGAN paralel. JANGAN commit/push tanpa perintah. DB dev JANGAN reset; PG disposable saja. Skill wajib: `laravel-best-practices` + `testing-best-practices`.

## W1 · Serialisasi INSERT vs finalisasi snapshot (F1)

- Status: done
- F1: finalisasi (`komposisi_final=false→true`) vs INSERT komponen bersamaan: SELECT biasa tak kunci induk → B baca false basi → lolos trigger → commit setelah A. Kunci baris induk dengan mode berkonflik (`SELECT ... FOR UPDATE` / setara di trigger/fungsi finalisasi + guard INSERT) atau mekanisme serialisasi setara, agar tak ada INSERT commit setelah terbit. Koreksi berversi tetap terbuka.
- Regression: dua-koneksi (finalisasi vs INSERT bersamaan) → INSERT ditolak/serialisasi benar; pola bukti sekuensial seperti LockOrderTest bila paralel 2-proses tak feasible (catat batas).
- DoD: regression + RA suite hijau; rollback+migrate bersih; pint + phpstan 0 errors.
- Selesai: 2026-10-06 | Bukti: Migrasi BARU `2026_10_06_042531_serialisasi_insert_vs_finalisasi_snapshot_review9_w1` (tanpa sentuh migrasi lama): guard INSERT kini `SELECT ks.komposisi_final ... FOR UPDATE` + sisi finalisasi `PERFORM 1 ... FOR UPDATE` (UPDATE sudah pegang lock; dipertegas agar dua arah berkonflik, pemenang-kunci-menang; yang menunggu lihat commit terbaru: INSERT-setelah-final→23514, final-setelah-INSERT→mencakup komponen). Guard rujukan U1 + tolak UPDATE/DELETE komponen utuh; koreksi berversi tetap terbuka. Baru `tests/Feature/RencanaAksi/RencanaAksiReview9W1Test.php` 2 regresi (definisi fungsi mengandung FOR UPDATE + INSERT-guard; dua-koneksi ronde-1 finalisasi→INSERT 55P03 lalu INSERT-ulang→23514 + komposisi utuh 2, ronde-2 INSERT→finalisasi 55P03 tanpa mutasi parsial + v3 berversi lolos lalu final). Kontrol negatif: tanpa fix 0/2 gagal (INSERT lolos tanpa tunggu + definisi tanpa FOR UPDATE). Verifikasi PG disposable podman `postgres:17-alpine` port 5477 DB/user `sakip_test` (container `sakip_test_w1` dihapus; dev `sakip_db:5433` utuh): RA 87/87 hijau (1025 assertions; W1 2/2 + V2 3/3 inklusif); rollback step=1 + migrate W1 bersih; `pint --dirty` fixed 1 file + `pint --test` passed; `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff W1:
Kerjakan W1 dari document/PR-62-Review9-Tracking.md (F1) di branch feature/iss-05-01-target-rencana-aksi (lanjut working tree, JANGAN commit). Skill laravel-best-practices + testing-best-practices. PG disposable, rollback+migrate bersih, pint + phpstan hijau. Update checkbox + Bukti. JANGAN commit.
```

## W2 · Penanda backfill atomik (F2)

- Status: done
- F2: penanda ID backfill dipilih di statement terpisah dari UPDATE → write aplikasi di antaranya ikut tertanda tanpa dibackfill → down() hapus pin sah. Gabungkan UPDATE + pencatatan via `UPDATE ... RETURNING` / CTE atomik dalam satu statement.
- Regression: simulasi write di antara (atau bukti statement tunggal) + rollback → pin sah utuh.
- DoD: sama seperti W1.
- Selesai: 2026-10-06 | Bukti: up() U2 kini SATU statement atomik `WITH updated AS (UPDATE rencana_aksi ... RETURNING ra.id) INSERT INTO penanda SELECT id FROM updated` (edit file `2026_10_05_160925_backfill_snapshot_draf_rencana_aksi_u2.php` dalam PR yang sama — overlay migrasi baru tak dapat menutup jendela dua-snapshot pada fresh migrate; dev `sakip_db:5433` utuh karena migrasi sudah tercatat berjalan). down() tak berubah (tetap non-destruktif: hanya NULL-kan bertanda bernilai backfill persis + no-op tanpa penanda). Baru `tests/Feature/RencanaAksi/RencanaAksiReview9W2Test.php` 2 regresi (bentuk statement tunggal: CTE+RETURNING+FROM updated, tanpa SELECT penanda terpisah; perilaku: pin sah terisi-sebelum-up() — beda maupun kebetulan-sama dengan peta — tak masuk penanda, kontrol positif ter-backfill+tertanda, lalu down() pertahankan semua pin sah + drop penanda). Kontrol negatif: tanpa fix 0/1 gagal (pola CTE tak ditemukan). Verifikasi PG disposable podman `postgres:17-alpine` port 5477 DB/user `sakip_test` (container `sakip_test_w2` dihapus; dev utuh): RA 89/89 hijau (1041 assertions; W2 2/2); rollback step=1 + migrate bersih; `pint --dirty` + `--test` passed; `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff W2:
Kerjakan W2 dari document/PR-62-Review9-Tracking.md (F2) di branch yang sama (setelah W1), JANGAN commit. Aturan dan verifikasi sama. Update checkbox + Bukti. JANGAN commit.
```

## Verifikasi akhir (setelah W2)

- RA suite penuh + FE + pint + phpstan + typecheck. Re-review cukup delta setelah `54da66d`.
