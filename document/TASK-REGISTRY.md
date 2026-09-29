# TASK REGISTRY — Main Session

Peran session ini: **koordinator + monitoring**. Session ini TIDAK mengeksekusi
task baru. Setiap task baru dipecah menjadi handoff `.md` di `document/`,
dikerjakan session lain, lalu statusnya dicatat di sini.

## Alur kerja

1. User memberi task baru → main session memecah jadi task kecil + tulis
   file handoff `document/<NAMA>-Task-Tracking.md` (scope, files, DoD,
   prompt handoff siap-tempel, seperti pola `PR-42-Task-Tracking.md`).
2. Session lain mengeksekusi via prompt handoff, update checkbox + Bukti
   di file handoff tersebut.
3. Main session memverifikasi laporan (diff, gate, traceability) lalu
   update tabel status di bawah. Commit + push tetap lewat main session
   bila diminta eksplisit.

## Aturan

- Satu session = satu task. Jangan campur eksekusi ke main session.
- Setiap selesai 1 task: session pelaksana update file handoff;
  main session update tabel registry ini.
- Setiap task selesai → main session spawn subagent CI-mirror dengan
  `document/PR-42-CI-Test-Context.md` ditempel sebagai pesan pertama;
  push hanya setelah laporan CI-mirror hijau (atau sisa gap tercatat
  eksplisit sebagai TERBLOKIR/pre-existing).
- Standar kode selalu `document/SAKIP_ENGINEERING_STANDARDS.md`;
  glosarium selalu `CONTEXT.md`.

## Register

| ID | Task | Handoff file | Status | Pelaksana | Bukti |
|---|---|---|---|---|---|
| PR-42-01 | Bekukan `jenis_agregasi` backend | `document/PR-42-Task-Tracking.md` | done | main session | pint + phpstan passed; Pest terblokir env |
| PR-42-02 | Guard `regulasi:read` pada write | `document/PR-42-Task-Tracking.md` | done | session paralel | commit `bb8fb55` |
| PR-42-03 | Provenance fixture seeder | `document/PR-42-Task-Tracking.md` | done | session paralel | commit `bb8fb55` |
| PR-42-04 | Frontend modal (agregasi/desimal/label) | `document/PR-42-Task-Tracking.md` | done | main session | typecheck + 129 test hijau |
| PR-42-05 | Regresi komponen/UUID | `document/PR-42-Task-Tracking.md` | done | session paralel | — |
| PR-42-06 | Verifikasi demo command | `document/PR-42-Task-Tracking.md` | done | session paralel | — |
| PR-42-07 | ADR provenance + lintas-Renstra | `document/PR-42-Task-Tracking.md` | done | main session | `docs/adr/0001,0002` |
| PR-42-08 | Refactor Perencanaan ke Actions | `document/PR-42-Task-Tracking.md` | done | main session | pint + phpstan passed |
| PR-42-CI | CI test semua perubahan | `document/PR-42-CI-Test-Context.md` | run 1 done (SHA `44cd450`): BELUM PR-ready | subagent ses_f1248c268 | hijau: pint, typecheck, 129 FE test, build, bun audit; merah: phpstan is_aktif + backend 53 failed (tunggu R2-04c); terblokir: eslint isexe, composer audit |
| R2-01 | [BLOCKER] Keluarkan SeedDemo dari PR-42 | `document/PR-42-Review2-Tracking.md` | done, belum commit | subagent ses_f126e5049 | grep nihil + pint/phpstan passed; Pest klaim paralel (belum re-verifikasi); commit menyusul bila diminta |
| R2-04 | Rekonsiliasi lifecycle (tahap 1 analisis) | `document/PR-42-Review2-Tracking.md` | tahap 1 done, 7/7 keputusan user FINAL | subagent ses_f126e5029 | lanjut R2-04b → R2-04c |
| R2-04b | Lifecycle: migrasi skema + model | `document/PR-42-Review2-Tracking.md` | open | — | hanya migrasi baru + model |
| R2-04c | Lifecycle: reader/guard/arsip + ADR | `document/PR-42-Review2-Tracking.md` | done, jawaban user FINAL, belum commit | subagent ses_f123afbaff | 1) hanya create baru diblokir 2) reaktivasi = BACKLOG-01 3) backfill manual wajib (prosedur di migrasi) |
| BACKLOG-01 | Alur reaktivasi arsip→aktif | — (issue berikutnya, di luar PR-42) | open | — | diputuskan 2026-09-29, belum dipecah |
| R2-02 | Pindah-unit endpoint khusus (backend) | `document/PR-42-Review2-Tracking.md` | done, 5 OPEN QUESTION, belum commit | subagent ses_f122752eaf | PATCH pindah-unit + PUT tolak unit beda; pint/phpstan passed |
| R2-03 | Pindah-unit UI terpisah | `document/PR-42-Review2-Tracking.md` | open | — | setelah R2-02 |
| R2-05 | Re-auth konsisten semua mutation | `document/PR-42-Review2-Tracking.md` | open | — | — |
| R2-06 | Design System tokens | `document/PR-42-Review2-Tracking.md` | open | — | — |
| R2-07 | Browser smoke + QA | `document/PR-42-Review2-Tracking.md` | open | — | setelah R2-03 + R2-06 |
| R2-08 | Konsolidasi PermissionResolver | `document/PR-42-Review2-Tracking.md` | open | — | — |
| R2-09 | Migration deterministik | `document/PR-42-Review2-Tracking.md` | done | subagent ses_f125ef9f8 | konstanta beku lokal; perilaku identik; pint/phpstan passed |
| R2-04b | Lifecycle: migrasi skema + model | `document/PR-42-Review2-Tracking.md` | revisi done, 1 OPEN QUESTION | subagent revisi | NULL→throw; fallback user dihapus (throw+SQL manual); backup per-baris; 1 tanya down() baris pasca-cutover |
| R2-10 | Bersihkan komentar + pecah test | `document/PR-42-Review2-Tracking.md` | open | — | — |
