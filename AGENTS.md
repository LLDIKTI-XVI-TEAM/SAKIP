# AGENTS.md — Panduan Kerja Agent SAKIP

## 1. Tujuan

File ini adalah **entry point kerja agent** untuk repository **SAKIP LLDIKTI Wilayah XVI**.

Fungsi `AGENTS.md` adalah mengarahkan agent agar:

- bekerja dari source of truth yang benar;
- membaca dokumen yang relevan sebelum implementasi/review;
- mengikuti aturan coding yang sudah ditetapkan di `document/`;
- menjaga scope, Git, database, dan data sensitif;
- memverifikasi hasil sebelum menyatakan selesai;
- menyinkronkan User Story dan User Issue setelah task selesai;
- tidak mengklaim completion melebihi evidence yang tersedia.

`AGENTS.md` **tidak menduplikasi** aturan detail yang sudah ada di folder `document/`.
Jika detail teknis, domain, arsitektur, UI, testing, atau workflow sudah ditetapkan di dokumen resmi, **ikuti dokumen tersebut**.

Instruksi sistem/developer tetap lebih tinggi.
Instruksi pengguna terbaru yang eksplisit berlaku untuk scope yang disebutkan.

Gunakan Bahasa Indonesia untuk komunikasi, laporan review, dokumentasi domain, dan penjelasan hasil kerja.

---

## 2. Quick Start

Untuk task non-trivial, gunakan alur berikut:

```text
Task
→ Verifikasi repository / branch / HEAD
→ Identifikasi User Story / User Issue / Plan task
→ Baca dokumen relevan di document/
→ Baca current code + tests
→ Susun plan seperlunya
→ Implementasi / review
→ Verifikasi
→ Update User Story + User Issue
→ Final diff review
→ Selesai
```

Jangan mulai implementasi hanya berdasarkan:

- judul issue;
- komentar PR lama;
- memory percakapan;
- satu file code;
- test yang belum diverifikasi;
- branch/HEAD lama.

Jika task bergantung pada kondisi repository saat ini, refresh state terbaru terlebih dahulu.

---

## 3. Folder `document/` adalah Pusat Referensi Proyek

Agent wajib menggunakan folder:

```text
document/
```

sebagai pusat dokumentasi resmi proyek.

Gunakan dokumen sesuai tanggung jawabnya.

| Dokumen | Digunakan untuk |
|---|---|
| `SAKIP - PRD.md` | scope, requirement, acceptance criteria produk |
| `SAKIP - User Stories.md` | kebutuhan pengguna dan Acceptance Criteria |
| `SAKIP - User Issues.md` | scope development, implementation task, test, DoD |
| `SAKIP - Data Model.md` | entity, field, relasi, constraint, integritas data |
| `SAKIP - Workflow.md` | actor, state, transition, prerequisite, window |
| `SAKIP - System Architecture.md` | struktur sistem dan boundary arsitektur |
| `SAKIP - Architecture Decision Records.md` | keputusan arsitektural permanen |
| `SAKIP - Plan Pengembangan.md` | task, dependency, urutan kerja, DoD |
| `SAKIP - Keputusan Penyelarasan.md` | keputusan/addendum domain terbaru |
| `SAKIP_ENGINEERING_STANDARDS.md` | aturan coding, architecture implementation, testing, review |
| `design-system.md` | aturan UI, komponen, layout, accessibility |
| `TASK-REGISTRY.md` | tracking task bila digunakan |

Jangan menyalin kembali isi dokumen-dokumen tersebut ke `AGENTS.md`.

---

## 4. Dokumen yang Harus Dibaca Berdasarkan Task

### Backend / Feature

Baca minimal:

```text
User Issue
→ User Story
→ PRD bagian terkait
→ Data Model / Workflow bila relevan
→ System Architecture
→ ADR terkait bila ada
→ Engineering Standards bagian relevan
→ current code + tests
```

### Frontend / UI

Baca minimal:

```text
User Issue
→ User Story
→ PRD bagian terkait
→ design-system.md
→ System Architecture
→ Engineering Standards bagian frontend/testing
→ existing page/component/test
```

### Database / Migration / Concurrency

Baca minimal:

```text
User Issue
→ Data Model
→ Workflow bila lifecycle terdampak
→ System Architecture
→ ADR terkait
→ Engineering Standards bagian database/concurrency/testing
→ existing migration + test
```

### Authorization / Permission

Baca minimal:

```text
User Issue
→ User Story
→ PRD bagian akses
→ Data Model
→ Workflow
→ Keputusan Penyelarasan terbaru
→ System Architecture
→ ADR terkait
→ Engineering Standards bagian authorization
→ canonical resolver/policy + tests
```

### Pull Request Review

Gunakan:

```text
refresh HEAD
→ identifikasi Issue/User Story
→ baca dokumen relevan
→ review delta
→ review current source + tests
→ cek review threads
→ cek exact-head CI
→ klasifikasikan finding
→ verdict
```

Jika HEAD berubah dari review sebelumnya, **review delta terbaru terlebih dahulu**.

---

## 5. Source of Truth

Gunakan dokumen berdasarkan ownership-nya.

Contoh:

- requirement produk → PRD;
- kebutuhan pengguna → User Story;
- task development → User Issue;
- schema/constraint → Data Model;
- state transition → Workflow;
- arsitektur → System Architecture;
- keputusan arsitektur → ADR;
- penulisan kode/testing → Engineering Standards;
- UI → Design System.

Kode dan test membuktikan **status implementasi**.
Kode dan test **tidak otomatis menjadi requirement baru**.

Jika implementasi berbeda dari dokumen:

1. identifikasi perbedaannya;
2. cek dokumen pemilik keputusan;
3. jangan mengubah requirement agar cocok dengan code;
4. jangan membuat keputusan bisnis baru tanpa dasar.

Jika keputusan lama sudah dinyatakan superseded, gunakan keputusan terbaru.

Contoh:

```text
Q32 supersedes Q31
```

---

## 6. Jangan Mengarang Requirement

Agent tidak boleh mengarang:

- formula;
- presisi atau pembulatan;
- semantics `0` / `null`;
- role;
- permission;
- scope unit;
- hak PIC;
- pengecualian deny;
- self-approval;
- timezone/deadline;
- snapshot/correction/backfill;
- database field;
- Keycloak claim;
- status UAT/deployment;
- nomor User Story/Issue yang belum diverifikasi.

Jika belum pasti, bedakan:

- **Dikonfirmasi dokumen**
- **Terverifikasi implementasi**
- **Inferensi engineering**
- **Memerlukan keputusan stakeholder**

Inferensi engineering bukan requirement baru.

---

## 7. Aturan Coding

Seluruh detail aturan penulisan kode mengikuti:

```text
document/SAKIP_ENGINEERING_STANDARDS.md
```

Jangan menduplikasi aturan tersebut di sini.

Agent wajib membaca bagian yang relevan sebelum mengubah code.

Baseline arsitektur tetap mengikuti pola proyek:

```text
Route
→ Middleware/Auth
→ FormRequest/Policy
→ Controller
→ Action
→ Service/Model
→ DB/Audit
→ Inertia/Redirect/JSON
```

Detail penerapan, pengecualian, authorization, transaction, concurrency, React, testing, performance, dan quality gate mengikuti Engineering Standards.

Untuk UI, ikuti:

```text
document/design-system.md
```

Untuk keputusan arsitektural, ikuti:

```text
document/SAKIP - System Architecture.md
document/SAKIP - Architecture Decision Records.md
```

Jangan membuat arsitektur alternatif hanya karena terlihat lebih menarik secara engineering.

---

## 8. Task Lifecycle

Gunakan lifecycle berikut:

```text
DISCOVER
→ ALIGN
→ PLAN
→ IMPLEMENT
→ VERIFY
→ DOCUMENT
→ REVIEW
→ COMPLETE
```

### DISCOVER

- verifikasi repository;
- verifikasi branch/HEAD;
- baca issue/task;
- cek dependency;
- baca current code/test;
- cek finding/review lama bila relevan.

### ALIGN

Cocokkan task dengan dokumen yang relevan.

Jika ada konflik material antar source of truth, jangan membuat keputusan sendiri pada bagian yang terdampak.

### PLAN

Untuk task substansial, tentukan:

- tujuan;
- scope;
- non-goal;
- Acceptance Criteria;
- layer/file yang terdampak;
- risiko authorization/data/concurrency;
- test yang diperlukan;
- dokumentasi yang mungkin perlu disinkronkan.

Jangan over-plan task sederhana.

### IMPLEMENT

Ikuti dokumen dan Engineering Standards.

Gunakan solusi terkecil yang memenuhi requirement dan safety.

Jangan menambah abstraction/dependency/compatibility layer tanpa kebutuhan nyata.

### VERIFY

Jalankan verifikasi sesuai risiko.

Contoh:

- focused backend test;
- frontend test;
- typecheck/lint/build;
- PostgreSQL test;
- concurrency test;
- browser smoke;
- exact-head CI.

Jangan mengklaim test lulus jika hanya membaca source test.

### DOCUMENT

Setelah task selesai secara implementation + verification:

1. periksa User Story;
2. periksa User Issue;
3. update bagian yang benar-benar selesai;
4. periksa apakah dokumen arsitektur/domain lain ikut terdampak.

### REVIEW

Sebelum menyatakan selesai:

- baca semua file berubah;
- review full diff;
- cek regression;
- cek authorization/security;
- cek dokumentasi;
- cek perubahan unrelated.

### COMPLETE

Gunakan Definition of Done Agent pada bagian akhir file ini.

---

## 9. Sinkronisasi User Story dan User Issue

Ini **wajib** untuk task implementasi.

Setelah task selesai, agent wajib memeriksa:

```text
document/SAKIP - User Stories.md
document/SAKIP - User Issues.md
```

untuk task yang terkait.

### Izin implisit terbatas

Permintaan implementasi sebuah task memberikan izin terbatas untuk:

- mengubah `[ ]` menjadi `[x]` jika item benar-benar selesai;
- menyelaraskan status implementasi yang memang sudah terbukti;
- menandai test/DoD yang benar-benar telah dipenuhi.

Izin ini **tidak** mengizinkan agent untuk:

- mengubah requirement;
- menghapus Acceptance Criteria;
- menurunkan DoD;
- menambah scope;
- mengubah keputusan bisnis;
- mengubah wording requirement agar cocok dengan code;
- mencentang UAT/deployment yang belum terjadi.

### Partial task

Jika task hanya memenuhi sebagian issue:

```text
yang selesai → [x]
yang belum selesai → tetap [ ]
```

Jangan menandai seluruh issue selesai.

### Completion semantics

Selalu bedakan:

```text
Implemented
≠ Verified
≠ Merged
≠ UAT Accepted
≠ Deployed
```

CI hijau bukan bukti UAT selesai.
Build sukses bukan bukti deployment selesai.

---

## 10. Update Dokumen Lain

User Story dan User Issue selalu diperiksa setelah task implementasi.

Dokumen lain hanya diperbarui bila benar-benar terdampak.

| Dokumen | Update bila |
|---|---|
| PRD | requirement/scope berubah |
| Data Model | entity/field/relation/constraint berubah |
| Workflow | actor/state/transition/window berubah |
| System Architecture | boundary/component/data flow berubah |
| ADR | keputusan arsitektural permanen baru/berubah |
| Design System | kontrak UI reusable berubah |
| Engineering Standards | standar engineering berubah |
| Plan Pengembangan | dependency/task/DoD resmi berubah |

Jangan mengubah banyak dokumen hanya karena satu file code disentuh.

---

## 11. Git dan Workspace Safety

### Default read-only

Task seperti:

- analisis;
- review;
- audit;
- status;

bersifat read-only kecuali pengguna meminta perubahan.

### Jangan gunakan worktree

Jangan menggunakan Git worktree kecuali pengguna secara eksplisit meminta.

### Jangan merusak working tree

Jangan:

- reset perubahan pengguna;
- overwrite file unrelated;
- switch branch berisiko;
- delete/move file tanpa izin;
- cleanup otomatis file yang bukan milik task.

### Artefak QA

Screenshot/log/video/output tool harus berada di luar repository.

### Database

Sebelum test/migration yang memutasi database, verifikasi target connection secara nyata.

`APP_ENV=testing` saja tidak cukup.

Database SIMPEG tidak boleh digunakan untuk SAKIP.

### Sensitive data

Jangan commit/publish:

- secret;
- token;
- password;
- session;
- credential;
- data privat yang tidak diperlukan.

---

## 12. Commit Rules

Commit hanya dilakukan jika pengguna memberikan izin commit.

Izin implementasi **bukan** izin commit.
Izin commit **bukan** izin push.
Izin push **bukan** izin merge.

### Sebelum commit

Wajib:

1. cek `git status`;
2. review full diff;
3. pastikan file hanya dalam scope task;
4. pastikan sinkronisasi User Story/User Issue sudah dilakukan bila applicable;
5. pastikan test/verifikasi yang relevan sudah dijalankan;
6. pastikan tidak ada secret atau artefak lokal;
7. stage file secara eksplisit.

Hindari blanket staging seperti:

```text
git add .
```

jika working tree memiliki perubahan unrelated.

### Format commit

Gunakan Conventional Commits.

Prefix menggunakan bahasa Inggris:

```text
feat:
fix:
refactor:
test:
docs:
chore:
```

Deskripsi commit menggunakan Bahasa Indonesia.

Contoh:

```text
feat: tambahkan penjelasan izin efektif pengguna
fix: cegah target tersimpan pada snapshot final
refactor: pindahkan orkestrasi formula ke action
test: tambah regresi race finalisasi snapshot
docs: sinkronkan status issue target tahunan
chore: perbarui dependency source map
```

### Isi commit

Satu commit sebaiknya mewakili satu concern yang logis dan dapat direview/revert bersama.

Jangan:

- membuat micro-commit per file tanpa alasan;
- mencampur refactor unrelated;
- memasukkan cleanup besar ke feature commit;
- menambahkan footer AI/generated/co-author kecuali diminta;
- rewrite history hanya untuk merapikan gaya commit tanpa izin.

Untuk perubahan non-trivial, gunakan commit body bila membantu menjelaskan alasan atau constraint penting.

### Setelah commit

Jika user hanya meminta commit:

```text
jangan push otomatis
```

Jika push juga diminta:

- verifikasi branch tujuan;
- verifikasi commit yang akan dikirim;
- jangan merge otomatis.

---

## 13. Pull Request dan GitHub Mutation

Membaca/review PR tidak memberi izin untuk mutation.

Jangan melakukan tanpa instruksi eksplisit:

- comment;
- resolve thread;
- request changes;
- approve;
- push;
- merge.

Jika diminta posting review:

1. refresh HEAD;
2. jika HEAD berubah, review delta;
3. refresh CI;
4. pastikan finding fixed tidak diulang;
5. anchor review ke exact HEAD;
6. baru posting.

---

## 14. Pull Request Review Rules

Saat review PR:

### Refresh

Periksa:

- HEAD;
- base/current development;
- draft/state;
- mergeability;
- CI;
- review comments/threads.

### Jangan ulang finding lama

Thread unresolved tidak otomatis berarti bug masih aktif.

Verifikasi current source.

### Severity

Gunakan:

- **Blocker**
- **Major**
- **Minor**
- **Tech Debt**

### Action Pattern

Review sebaiknya menjelaskan mapping layer fitur yang relevan agar ownership logic jelas.

### Reviewer feedback

Komentar reviewer manusia atau AI adalah hipotesis sampai diverifikasi terhadap:

- current HEAD;
- requirement;
- source;
- tests;
- runtime evidence.

Jangan mengubah code hanya untuk menutup komentar yang ternyata false positive.

---

## 15. Testing dan Evidence

Aturan detail mengikuti Engineering Standards.

Prinsip minimum:

- test sesuai risiko;
- PostgreSQL untuk behavior PostgreSQL-specific;
- concurrency test untuk race;
- browser smoke untuk UI yang memerlukannya;
- exact-head evidence untuk PR readiness.

Jangan menyatakan:

```text
CI hijau
```

jika CI yang diperiksa bukan untuk HEAD saat ini.

Jangan menyatakan:

```text
UAT selesai
```

berdasarkan automated tests saja.

---

## 16. Scope dan Anti-Overengineering

Jangan:

- memperluas scope karena “sekalian”;
- refactor lintas modul tanpa kebutuhan;
- menambah framework/dependency tanpa kebutuhan;
- membuat generic abstraction untuk kasus kecil;
- membuat compatibility layer spekulatif;
- mengubah contract response secara oportunistis.

Jika menemukan issue unrelated:

1. laporkan;
2. jelaskan impact;
3. jangan memperbaiki otomatis kecuali diperlukan agar task aman atau pengguna memberi izin.

---

## 17. SAKIP Bukan SIMPEG

SAKIP adalah aplikasi terpisah.

Jangan:

- menggunakan database SIMPEG;
- menyalin schema SIMPEG;
- menyalin role/permission SIMPEG;
- memindahkan aturan SIMPEG ke SAKIP tanpa keputusan resmi;
- menganggap environment/deployment SIMPEG sama dengan SAKIP.

Gunakan hanya requirement SAKIP.

---

## 18. Delegasi / Subagent

Root agent tetap bertanggung jawab atas hasil akhir.

Gunakan subagent hanya jika memberikan manfaat nyata, misalnya:

- review independen;
- eksplorasi kompleks;
- verifikasi area khusus.

Jangan membuat delegasi berlapis atau pekerjaan tumpang tindih.

Output subagent adalah evidence tambahan, bukan source of truth baru.

---

## 19. Definition of Done Agent

Agent tidak boleh menyatakan task implementasi selesai sebelum memeriksa item applicable berikut:

```text
[ ] Repository / branch / HEAD telah diverifikasi
[ ] User Story / User Issue terkait telah diidentifikasi
[ ] Dokumen domain relevan telah dibaca
[ ] Engineering Standards bagian relevan telah diikuti
[ ] Architecture / ADR diperiksa bila relevan
[ ] Implementasi berada pada layer yang benar
[ ] Authorization / business rule tetap server-side sesuai kontrak
[ ] Test/verifikasi relevan telah dijalankan
[ ] Browser verification dilakukan bila diperlukan
[ ] Full diff telah dibaca ulang
[ ] Tidak ada perubahan unrelated yang terbawa
[ ] User Story terkait telah diperiksa
[ ] User Issue terkait telah diperiksa
[ ] Checkbox/status hanya diperbarui berdasarkan evidence
[ ] Dokumen lain diperiksa apakah terdampak
[ ] Blocker/dependency tersisa dilaporkan
[ ] Implemented / Verified / Merged / UAT / Deployed tidak dicampur
```

Jika item wajib masih belum terpenuhi, gunakan status yang jujur seperti:

```text
in progress
partial
blocked
not ready for merge
```

bukan `done`.

---

## 20. Prinsip Akhir

Alur kerja agent SAKIP harus tetap sederhana:

```text
TASK
→ READ RELEVANT DOCUMENTS
→ IMPLEMENT / REVIEW
→ VERIFY
→ UPDATE USER STORY / USER ISSUE
→ FINAL DIFF REVIEW
→ DONE
```

`AGENTS.md` mengatur **workflow agent**.

Folder `document/` mengatur **requirement, arsitektur, domain, coding standard, dan keputusan proyek**.

Jangan duplikasi aturan yang sudah kanonis di `document/`.
Arahkan agent ke dokumen yang benar, ikuti source of truth, dan jangan mengklaim completion melebihi evidence.
