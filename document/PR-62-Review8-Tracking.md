# PR-62 Review8 Tracking — Codex 3 temuan (base `e391d1b`)

Sumber: review Codex terbaru atas PR #62. Semua NEW (lanjutan Review7).
Satu session = satu task. V1 → V2 BERURUTAN (migration overlap). JANGAN paralel. JANGAN commit/push tanpa perintah. DB dev JANGAN reset; PG disposable saja. Skill wajib: `laravel-best-practices` + `testing-best-practices`.

## V1 · Guard simpan-404 + rollback non-destruktif (F2, F3)

- Status: done
- F2 save-404: `SimpanTargetPeriodeRequest::authorize()` lolos saat header UUID tak ada → `exists` jalan → oracle 404-vs-422 dan 403-vs-404. Terapkan abort(404) sebelum validasi seperti `PreviewTargetPeriodeRequest` (T1/Review6).
- F3 down() U2: hanya NULL-kan pin yang benar-benar diisi up() (catat ID ter-update, mis. tabel sisi/marker deterministik) ATAU jadikan rollback non-destruktif terhadap pin yang tak dapat dibedakan dari backfill. Jangan hapus jepit sah pra-existing.
- Regression: UUID asing simpan → 404/403 konsisten tanpa 422; rollback U2 → pin pra-existing utuh, pin backfill dikembalikan.
- DoD: regression + RA suite hijau; rollback+migrate bersih; pint + phpstan 0 errors.
- Selesai: 2026-10-06 | Bukti: F2 `SimpanTargetPeriodeRequest::authorize()` kini `abort(404)` bila header tak ditemukan (cermin `PreviewTargetPeriodeRequest` T1; lookup mendahului Gate; `return true`/`return false` ditolak). F3 migrasi `2026_10_05_160925_backfill_snapshot_draf_rencana_aksi_u2` kini tulis tabel sisi `_backfill_snapshot_draf_rencana_aksi_u2_ids` (kandidat `IS NULL` + `ON CONFLICT DO NOTHING` sebelum `UPDATE`); `down()` hanya NULL-kan baris bertanda yang masih pegang nilai backfill persis lalu `DROP` penanda, no-op non-destruktif bila penanda/backup hilang (perbaiki `UPDATE..FROM..JOIN` → koma-`FROM` agar valid PG). Baru `tests/Feature/RencanaAksi/RencanaAksiReview8V1Test.php` 4 regresi (asing+dengan-izin valid+invalid→404; asing+tanpa-izin valid+invalid→403/404 tanpa 422 + sanity simpan sah; down() pin-backfill→NULL + pra-existing-utuh + penanda terekam; down() tanpa-penanda non-destruktif). Kontrol negatif: tanpa fix F2 gagal persis `Expected 404 but received 422`. File diubah: `app/Http/Requests/RencanaAksi/SimpanTargetPeriodeRequest.php`, `database/migrations/2026_10_05_160925_backfill_snapshot_draf_rencana_aksi_u2.php`, baru `tests/Feature/RencanaAksi/RencanaAksiReview8V1Test.php`. Verifikasi PG disposable podman `postgres:17-alpine` port 5475 DB/user `sakip_test` (container `sakip_test_v1` dihapus; dev `sakip_db:5433` utuh): RA 82/82 hijau (981 assertions; V1 4/4 + U2 2/2 inklusif); rollback step=1 + migrate U2 bersih; `pint --dirty` + `pint --test` passed; `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff V1:
Kerjakan V1 dari document/PR-62-Review8-Tracking.md (F2+F3) di branch feature/iss-05-01-target-rencana-aksi (lanjut working tree, JANGAN commit). Skill laravel-best-practices + testing-best-practices. PG disposable, rollback+migrate bersih, pint + phpstan hijau. Update checkbox + Bukti. JANGAN commit.
```

## V2 · Bekukan penambahan komponen pasca-terbit (F1)

- Status: done
- F1: INSERT komponen baru ke snapshot v2 yang sedang ditampilkan/dijepit lama tetap lolos (belum dirujuk pin). Putuskan + catat: (a) finalisasi atomik snapshot+komponen sebelum status publik lalu tolak seluruh INSERT komponen pasca-publik, atau (b) ubah identitas versi tiap komposisi berubah. Rekomendasi: (a) — selaras immutable-sejak-terbit U1.
- Regression: snapshot terbit + tambah komponen langsung → ditolak; koreksi sah via sisipan berversi tetap terbuka.
- DoD: sama seperti V1 (migrasi trigger bila dipilih: down() aman).
- Selesai: 2026-10-06 | Bukti: KEPUTUSAN (a) finalisasi atomik, BUKAN (b) ubah identitas versi. Alasan: identitas versi stabil sebagai token konkurensi baca-tulis (`expected_snapshot_id`+`expected_snapshot_versi`); opsi (b) memaksa bump semu tiap sisipan sehingga token basi + rekonsiliasi transisi berisik tanpa koreksi resmi, selaras U1 immutable-sejak-terbit. Migrasi BARU `2026_10_06_032010_bekukan_komposisi_snapshot_terbit_rencana_aksi_v2` (tanpa sentuh migrasi lama): tambah `jadwal_snapshot.komposisi_final` boolean default-false + backfill TRUE existing (pasang guard baru DULU baru backfill agar UPDATE finalisasi tak ditolak guard lama U1); guard baru izinkan satu-satunya UPDATE snapshot = penguncian (kolom non-flag identik + NEW true; no-op flag-sama lolos) + tolak INSERT komponen bila induk final walau belum dirujuk (celah F1 tertutup) + pertahankan guard rujukan U1 untuk draf-belum-final; UPDATE/DELETE komponen tetap selalu ditolak. `down()` aman non-destruktif: pulihkan fungsi U1 persis baru drop kolom (tanpa data pengguna). Model `JadwalSnapshot` +fillable/casts `komposisi_final`. Baru `tests/Feature/RencanaAksi/RencanaAksiReview8V2Test.php` 3 regresi (v2 tampil+pin-lama + INSERT C→23514 + komposisi utuh 2; v3 berversi + komponen lolos + finalisasi + simpan jepit-maju v3; mutasi-data ditolak + finalisasi/no-op lolos + un-finalisasi ditolak + down() lepas-flag + data utuh + guard U1 pulih + up() finalisasi ulang). File diubah: `database/migrations/2026_10_06_032010_bekukan_komposisi_snapshot_terbit_rencana_aksi_v2.php` (baru), `app/Models/JadwalSnapshot.php`, baru `tests/Feature/RencanaAksi/RencanaAksiReview8V2Test.php`. Verifikasi PG disposable podman `postgres:17-alpine` port 5476 DB/user `sakip_test` (container `sakip_test_v2` dihapus; dev `sakip_db:5433` utuh): RA 85/85 hijau (1014 assertions; V2 3/3 + V1 4/4 inklusif); rollback step=1 + migrate V2 bersih; `pint --dirty` + `pint --test` passed; `phpstan` 0 errors. Tanpa commit.

```text
Prompt handoff V2:
Kerjakan V2 dari document/PR-62-Review8-Tracking.md (F1) di branch yang sama (setelah V1), JANGAN commit. Aturan dan verifikasi sama. Update checkbox + Bukti. JANGAN commit.
```

## Verifikasi akhir (setelah V2)

- RA suite penuh + FE + pint + phpstan + typecheck. Re-review cukup delta setelah `e391d1b`.
