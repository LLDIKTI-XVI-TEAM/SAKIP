# PR-62 Review Tracking — ISS-05.01 (base `a4ff121`)

Sumber: Review final HEAD `a4ff121aac363e549a9f8ee78cc9eaa47514a292`, base `development@0a1426e`.
Verdict reviewer: REQUEST CHANGES — 2 MAJOR (auth invariant + frozen snapshot), 0 Blocker, 0 Minor. CI 9/9 hijau tidak menutup finding domain.
Satu session = satu task. M1 → M2 BERURUTAN (sentuh file overlapping). JANGAN paralel. JANGAN commit/push tanpa perintah eksplisit. DB dev JANGAN reset; test hanya PG disposable (pola `PR-42-CI-Test-Context.md` §3). Skill wajib: `laravel-best-practices` + `testing-best-practices`.

## M1 · [MAJOR 1] Create draft tegakkan effective PIC + jendela RA

- Status: done
- File utama: `app/Actions/RencanaAksi/EnsureDraftRencanaAksi.php` (+ test `tests/Feature/RencanaAksi/RencanaAksiTargetTest.php` yang saat ini mengabadikan perilaku salah: ensure-draft lolos di luar window).
- Masalah: jalur unit-scoped PIC belum pastikan actor == PJ efektif, dan create belum tegakkan `rencana_aksi_mulai..rencana_aksi_selesai`. Grant benar tapi bukan PJ bisa buat draft atas nama PJ lain; draft bisa dibuat di luar window (penolakan baru saat simpan target).
- Yang dibuat: pada mutation boundary create — resolve permission → lock indikator/jadwal/PJ → bedakan global Perencanaan (jalur kewenangan resmi, ikut exception source-of-truth) vs unit-scoped PIC (syarat: actor == effective PJ hari-ini WITA AND hari-ini dalam RA window, else tolak + audit `buat_ditolak`). Perencanaan global tetap ikut jalur resminya.
- Regression minimal (4): same-unit grant tapi bukan PJ → ditolak; PIC efektif di luar window → ditolak; PIC efektif di dalam window → berhasil; Perencanaan global → ikut jalur resmi. Perbaiki test lama yang mengharapkan ensure-draft lolos di luar window.
- DoD: 4 regression hijau + regresi F-03/F-05 hijau; pint + phpstan 0 errors; tanpa ubah kontrak D1-D7/ADR.
- Selesai: 2026-10-05 | Bukti: EnsureDraftRencanaAksi::pastikanDapatMembuat (penutupan+koreksi utk semua jalur; PIC unit-scoped wajib actor==PIC efektif WITA + dalam rencana_aksi_mulai..selesai; Perencanaan/superadmin via jalurPerencanaan resmi) + 4 regression baru di RencanaAksiTargetTest (bukan-PJ ditolak, PIC luar-window ditolak, PIC dalam-window berhasil, Perencanaan luar-window berhasil) + perbaiki 2 test lama (RencanaAksiTargetTest::test_jendela_pic_ditutup_tetapi_perencanaan_sampai_penutupan, RencanaAksiAuthorizationTest::test_jendela_pic_ditutup_tetapi_perencanaan_lolos: PIC ensure-draft luar-window kini assertSessionHasErrors jendela + draft dibuat via Perencanaan). Verifikasi PG disposable (127.0.0.1:5433 sakip_test): tests/Feature/RencanaAksi 25 passed/317 assertions; pint passed; phpstan 0 errors. Tanpa ubah kontrak D1-D7/ADR. Tidak commit.

```text
Prompt handoff M1:
Kerjakan M1 dari document/PR-62-Review-Tracking.md di branch feature/iss-05-01-target-rencana-aksi (lanjut HEAD a4ff121, JANGAN commit). Tegakkan effective-PIC + jendela RA pada EnsureDraftRencanaAksi (PIC: actor==PJ efektif WITA + dalam window; Perencanaan: jalur global resmi). Perbaiki test yang mengabadikan perilaku salah + tambah 4 regression. Ikuti skill laravel-best-practices (security/architecture) + testing-best-practices. Verifikasi Pest sempit di PG disposable + pint + phpstan. Update checkbox + Bukti. JANGAN commit.
```

## M2 · [MAJOR 2] Frozen snapshot fail-closed (jangan fallback ke live master)

- Status: done
- File utama: `app/Actions/RencanaAksi/SimpanTargetPeriode.php`, `app/Actions/RencanaAksi/IndexRencanaAksi.php`, integrasi `EnsureDraftRencanaAksi.php`.
- Masalah: write/read hanya cari snapshot bila `jadwal.status === 'aktif'`, lalu fallback `$snapshot?->tipe ?? $indikator->tipe` dan `definisiEfektif()` baca master live. Akibat: jadwal aktif tanpa snapshot lolos pakai master; jadwal ditutup + correction path resmi pakai master terkini → makna RA berubah saat master berubah. Catatan: pernyataan D2 di PR body ("snapshot bila aktif else master") hanya di body, bukan ADR accepted, tidak mengalahkan kontrak frozen snapshot ISS-03.02.
- Yang dibuat: begitu RA terikat pada Jadwal yang pernah diaktifkan, resolve konteks indikator (tipe, presisi, target PK, periode_mulai_id, identitas komponen) dari frozen snapshot Jadwal tersebut; snapshot wajib-tapi-hilang → fail-closed (tolak + audit ditolak), bukan fallback master. Jika ada versioning snapshot resmi, resolve versi resmi terbaru; jangan ganti sumber ke live `IndikatorKinerja/Komponen` hanya karena status bukan aktif (termasuk correction/closed state).
- Regression minimal (4): jadwal aktif tanpa snapshot → create/save ditolak; master berubah setelah aktivasi → RA tetap pakai snapshot lama; jadwal ditutup + correction sah → tetap frozen snapshot; tipe/presisi/target PK/periode_mulai/identitas komponen tak ikut live master.
- DoD: 4 regression hijau + regresi F-03/F-04 hijau; pint + phpstan 0 errors. Bila perlu, koreksi redaksi D2 di tracking (tetap tanpa ubah ADR 0004/0005 kecuali diminta).
- Selesai: 2026-10-05 | Bukti: SimpanTargetPeriode::jadwalPernahDiaktifkan (is_terkunci/activated_at/aktif-ditutup) + snapshot versi terbaru wajib bila pernah-aktif else Validation snapshot + audit ubah_ditolak; definisiEfektif snapshot-only + fail-closed defensif; IndexRencanaAksi::snapshotEfektif selalu-resolve-terbaru + fail-closed bila pernah-aktif tanpa snapshot; EnsureDraft::pastikanSnapshotTersedia (aktif tanpa snapshot → buat_ditolak PIC+Perencanaan) + 4 regression baru RencanaAksiFrozenSnapshotTest (aktif-tanpa-snapshot tolak create/save/read; master-berubah tetap snapshot lama tulis+baca; ditutup+koreksi-sah pakai v2 target 150 bukan v1/master; tipe/presisi/targetPK/periode_mulai/identitas tak ikut master). Verifikasi PG disposable (127.0.0.1:5433 sakip_test): tests/Feature/RencanaAksi 29 passed/397 assertions (25 lama + 4 baru); pint --test passed; phpstan 0 errors. Tanpa ubah kontrak D1-D7/ADR. Tidak commit.

```text
Prompt handoff M2:
Kerjakan M2 dari document/PR-62-Review-Tracking.md (setelah M1) di branch yang sama, JANGAN commit. Buat frozen snapshot fail-closed di SimpanTargetPeriode + IndexRencanaAksi (+ integrasi EnsureDraft): RA terikat jadwal pernah-aktif selalu resolve dari snapshot; snapshot hilang → tolak; closed/correction tetap snapshot. Tambah 4 regression. Skill dan verifikasi sama seperti M1. Update checkbox + Bukti. JANGAN commit.
```

## BACKLOG (non-blocking, luar PR ini)

- Ekstrak shared resolver snapshot/periode/komponen (hilangkan duplikasi Index vs Simpan) — setelah M2.
- Konsolidasi ADR 0004/0005 ke Data Model/Plan.
- Evaluasi dokumen tracking/review temporary sebelum jadi permanen.

## Verifikasi akhir (setelah M2)

- Pest `tests/Feature/RencanaAksi` penuh di PG disposable + pint + phpstan. FE tak tersentuh (kecuali bila perlu, tambah typecheck/vitest). Re-review berikutnya cukup delta setelah `a4ff121` untuk 2 Major + risiko regresi baru.
