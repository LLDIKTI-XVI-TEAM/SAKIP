# SAKIP — Architecture Decision Records

> **Status dokumen:** Draft untuk review tim. File ini menggabungkan ADR awal SAKIP dalam satu dokumen agar mudah dibaca/dibagikan. ADR baru ditambahkan pada dokumen ini dengan nomor berurutan berikutnya; jangan membuat file ADR terpisah di `docs/adr/` atau `document/adr/` agar penomoran tidak bentrok.

## Tujuan

Architecture Decision Record (ADR) mencatat keputusan teknis yang berdampak lintas fitur dan perlu tetap dapat dijelaskan setelah issue/PR selesai. ADR tidak menggantikan PRD, Data Model, Workflow, Plan Pengembangan, Keputusan Penyelarasan, atau Engineering Standards.

## Status ADR

- `Proposed`: belum disetujui.
- `Accepted`: keputusan berlaku.
- `Deprecated`: tidak dipakai untuk pekerjaan baru tetapi belum sepenuhnya dihapus.
- `Superseded`: digantikan ADR lain.

ADR-0001 sampai ADR-0006 merekam keputusan yang **sudah berlaku pada source-of-truth proyek**, sehingga `Status keputusan` ditandai `Accepted`. ADR-0007/0008 diterima melalui Keputusan Penyelarasan Q34. ADR berstatus `Proposed` belum boleh diklaim sebagai keputusan stakeholder sampai ada bukti ratifikasi. File dokumentasinya sendiri tetap draft sampai direview/ditambahkan melalui proses tim.

## Indeks

1. ADR-0001 — Laravel + Inertia Monolith.
2. ADR-0002 — Canonical Permission Resolver dan Explicit Deny-Wins.
3. ADR-0003 — Action Pattern untuk Use Case Server.
4. ADR-0004 — Snapshot Historis Immutable dan Koreksi Berbasis Versi.
5. ADR-0005 — Atomic Indicator Definition Writer.
6. ADR-0006 — Private File Storage dan Authorized Streaming.
7. ADR-0007 — Target Manual Rencana Aksi Menggunakan `komponen_id = NULL`.
8. ADR-0008 — Kolom Alasan Deviasi Target PK pada Rencana Aksi.

---

# ADR-0001 — Laravel + Inertia Monolith sebagai Arsitektur Aplikasi

- **Status keputusan:** Accepted
- **Status dokumen:** Draft untuk review tim
- **Scope:** Application architecture, frontend/backend boundary, HTTP contract

## Context

SAKIP membutuhkan aplikasi internal dengan authentication berbasis session, authorization server-side, workflow domain yang kuat, file privat, audit, dan UI interaktif. Requirement produk menetapkan Laravel sebagai backend dan Inertia + React/TypeScript sebagai frontend, serta secara eksplisit tidak memilih API terpisah sebagai boundary utama UI.

Membuat SPA dengan REST API terpisah akan menambah contract, auth surface, token lifecycle, serialization duplication, dan risiko business rule tersebar tanpa kebutuhan produk yang terbukti.

## Decision

SAKIP menggunakan **Laravel monolith + Inertia + React/TypeScript**.

Boundary utama:

```text
Browser React/Inertia
→ Laravel web routes/session
→ FormRequest/Policy
→ Controller
→ Action/Service/Model
→ PostgreSQL / private storage
```

Keputusan ini berarti:

1. routing halaman dan mutation dimiliki Laravel;
2. authentication UI menggunakan Laravel session setelah OIDC/Keycloak;
3. Inertia menjadi bridge server-page props ke React;
4. React tidak menjadi independent business backend;
5. endpoint JSON terbatas boleh digunakan untuk kebutuhan seperti autocomplete/editor/preview, tetapi tetap berada di monolith dan tidak otomatis menjadi public API;
6. `api.php`, bearer token, atau REST layer generik tidak dibuat tanpa requirement baru yang disetujui.

## Alternatives Considered

### REST API + SPA terpisah

Tidak dipilih sebagai baseline karena tidak dibutuhkan untuk scope MVP dan memperluas auth/contract surface.

### Blade/Alpine sebagai UI utama

Tidak dipilih karena kontrak frontend menetapkan Inertia + React/TypeScript.

### Microservices

Tidak dipilih karena domain belum membutuhkan independent deployment/bounded runtime dan akan menambah operational complexity.

## Consequences

### Positive

- satu deployment unit;
- session/auth boundary sederhana;
- authorization dan domain rule tetap dekat dengan server state;
- tidak ada duplikasi API/page contract untuk sebagian besar flow;
- React tetap dapat memberi UX interaktif.

### Trade-offs

- backend dan frontend dirilis bersama;
- shared domain monolith membutuhkan disiplin modular agar tidak berubah menjadi controller-heavy code;
- JSON endpoint internal tetap perlu contract/test yang jelas agar tidak menjadi API informal yang tidak terkontrol.

## Guardrails

- jangan membuat API terpisah tanpa requirement eksplisit;
- jangan memindahkan authorization/business calculation ke React;
- Inertia props harus bounded dan allowlisted;
- endpoint JSON tetap menggunakan session auth, authorization, dan validation;
- dependency/framework baru harus punya kebutuhan nyata dan izin scope.

## Verification

Review architecture dapat memeriksa:

- route berada pada Laravel web boundary;
- controller tidak menjadi domain service;
- page React menerima props/capability dari server;
- mutation tidak mempercayai decision/calculated value dari client;
- tidak ada bearer/API auth baru tanpa keputusan.

## References

- `document/SAKIP - PRD.md` §6 Arsitektur & Stack Teknis.
- `document/SAKIP - Plan Pengembangan.md` P.3 Aturan penempatan logika.
- `document/SAKIP_ENGINEERING_STANDARDS.md` §2 Arsitektur Laravel/Inertia.


---

# ADR-0002 — Canonical Permission Resolver dan Explicit Deny-Wins

- **Status keputusan:** Accepted
- **Status dokumen:** Draft untuk review tim
- **Scope:** Authorization, RBAC, grant/deny, unit scope, audit provenance

## Context

SAKIP menggunakan role baseline, explicit grant, explicit deny, dan beberapa permission dengan scope unit. Authorization tidak boleh bergantung pada nama role di UI atau disalin ke banyak service karena hasil effective permission harus konsisten pada seluruh endpoint.

Q32 juga menetapkan bahwa PIC bukan role sistem. PIC operasional/penanggung jawab adalah konteks domain yang berbeda dari role authorization.

## Decision

Gunakan **satu canonical permission resolver** sebagai sumber keputusan effective permission.

Implementasi canonical saat ini:

`App\Services\Authorization\PermissionResolver`

Compatibility wrapper `App\Services\PermissionResolver` sudah dihapus; seluruh consumer memakai namespace canonical di atas dan **tidak ada resolver kedua**.

Effective permission mengikuti prinsip:

```text
active user
+ official active role
+ known active permission
+ valid scope (bila diperlukan)
+ role allow / explicit grant
- matching explicit deny
= effective permission
```

Explicit deny yang cocok selalu menang terhadap allow.

Unknown permission, user tidak aktif, tidak memiliki role resmi aktif, atau scope invalid → fail-closed.

## Decision Details

1. Role baseline dan explicit grant adalah sumber allow.
2. Explicit deny adalah veto ketika cocok dengan permission/scope.
3. Unit-scoped permission hanya dievaluasi dengan scope unit yang valid sesuai katalog resmi.
4. Status unit/user menjadi bagian fail-closed behavior ketika relevan.
5. PIC tidak dibuat menjadi role baru.
6. Business invariant seperti ownership, workflow state, window, dan segregation of duties tetap diperiksa terpisah; effective permission bukan bypass seluruh invariant.
7. React hanya menerima capability/result; React tidak menghitung effective permission.
8. Aksi sensitif menyimpan provenance `dasar_izin` bila diwajibkan contract.

## Live Re-authorization

Authorization awal pada FormRequest/Policy tidak selalu cukup untuk mutation sensitif.

Bila ACL/state dapat berubah antara request validation dan persist, Action harus mengevaluasi ulang state authoritative setelah lock yang sesuai. `App\Services\Authorization\ResolveLockedActor` menyediakan pola lock/re-evaluation untuk use case yang sesuai.

## Alternatives Considered

### Role check tersebar (`if role == ...`)

Ditolak karena mudah drift, tidak menghormati grant/deny, dan menyulitkan audit provenance.

### Authorization hanya di frontend

Ditolak karena dapat dibypass dengan direct request.

### Superadmin unconditional bypass

Tidak menjadi baseline karena explicit deny dan invariant bisnis tetap harus dihormati sesuai kontrak.

## Consequences

### Positive

- keputusan akses konsisten;
- deny semantics terpusat;
- provenance lebih mudah diaudit;
- PIC tidak tercampur dengan role;
- endpoint direct request tetap aman walau UI salah/stale.

### Trade-offs

- beberapa mutation perlu recheck/locking sehingga flow lebih kompleks;
- consumer baru wajib memakai namespace canonical; compatibility wrapper sudah dihapus sehingga tidak ada jalur legacy yang tersisa.

## Guardrails

- jangan membuat resolver izin kedua;
- jangan hardcode role allowlist di React;
- jangan menambah unit-scoped permission/grant baru tanpa keputusan katalog;
- jangan menganggap permission menghapus invariant workflow/domain;
- jangan mengabaikan deny untuk superadmin;
- audit rejection harus menyimpan decision/provenance yang benar tanpa mengubah domain.

## Verification

Minimum scenario sesuai risiko:

- role allow;
- explicit grant;
- explicit deny menang;
- invalid/inactive scope;
- unknown permission;
- inactive user;
- object/unit lain;
- ACL berubah di antara pemeriksaan awal dan mutation untuk flow sensitif.

## References

- `document/SAKIP - PRD.md` §7 Hak Akses.
- `document/SAKIP - Data Model.md` §3 Algoritma Resolusi Izin dan §4 Segregation of Duties.
- `document/SAKIP - Keputusan Penyelarasan.md` Q32.
- `document/SAKIP_ENGINEERING_STANDARDS.md` §3 Auth dan authorization.
- `app/Services/Authorization/PermissionResolver.php`.
- `app/Services/Authorization/ResolveLockedActor.php`.


---

# ADR-0003 — Action Pattern untuk Use Case Server

- **Status keputusan:** Accepted
- **Status dokumen:** Draft untuk review tim
- **Scope:** Laravel request architecture, mutation ownership, transaction/audit boundary

## Context

Business flow SAKIP mencakup authorization, workflow transition, locking, snapshot, calculation, audit, dan data integrity. Menempatkan seluruh logika dalam controller membuat ownership transaction dan invariant tidak jelas serta menyulitkan test/reuse.

Sebaliknya, menambah banyak layer generik untuk semua CRUD juga tidak diinginkan.

## Decision

Gunakan baseline flow:

```text
Route
→ Middleware/authentication
→ FormRequest/Policy
→ Controller
→ Action/use case
→ Service/Model
→ Inertia/redirect/JSON + DB/audit
```

### Route

Hanya route declaration, middleware, binding, dan naming.

### FormRequest / Policy

Menangani request validation dan authorization awal sesuai kebutuhan.

### Controller

Adapter tipis:

- menerima request/model;
- memanggil use case;
- mengembalikan response.

Tidak menjadi owner transaction, domain calculation, heavy query, atau audit.

### Action

Owner orchestration satu use case:

- load/lock authoritative state;
- re-authorization bila diperlukan;
- invariant/workflow check;
- transaction;
- service/domain call;
- persistence;
- audit;
- result.

### Service

Digunakan untuk behavior domain yang dipakai ulang atau boundary khusus. Service tidak wajib untuk delegasi satu baris.

## Atomic Use Case Exception

Satu Action boleh mengelola beberapa row/intent bila validitas domain hanya dapat dinilai pada final state kolektif dan memecahnya akan memungkinkan intermediate invalid state.

Contoh yang disetujui: perubahan definisi indikator + komponen secara atomik.

Ini bukan izin membuat satu generic command Action untuk use case yang tidak berhubungan.

## Alternatives Considered

### Fat Controller

Ditolak karena transaction/audit/invariant bercampur dengan HTTP adapter.

### Repository/DTO/Service wajib pada semua CRUD

Ditolak sebagai overengineering; abstraksi dipakai hanya bila ada kebutuhan nyata.

### Satu Action per row tanpa melihat atomicity

Ditolak bila domain membutuhkan final-state validation lintas row.

## Consequences

### Positive

- mutation owner jelas;
- controller tipis;
- transaction/audit lebih mudah direview;
- authorization recheck dapat ditempatkan dekat state lock;
- use case lebih mudah diuji.

### Trade-offs

- jumlah Action class bertambah;
- reviewer harus memastikan Action tidak menjadi god-object lintas use case;
- read path tetap perlu bounded query/presentation contract yang rapi.

## Guardrails

- satu Action = satu use case/cohesive transaction;
- jangan menaruh business rule utama di React;
- jangan menaruh transaction/audit di controller;
- jangan memecah atomic mutation bila dapat meninggalkan invalid intermediate state;
- jangan menambah generic repository/service hanya untuk memenuhi bentuk arsitektur.

## Verification

Review minimal memeriksa:

- controller hanya adapter;
- FormRequest/Policy tidak menjadi satu-satunya live guard untuk state yang dapat berubah;
- transaction/audit berada pada mutation boundary;
- Action memakai service/model secara jelas;
- response/props tetap mengikuti contract.

## References

- `document/SAKIP_ENGINEERING_STANDARDS.md` §2 Arsitektur Laravel/Inertia.
- `document/SAKIP - Plan Pengembangan.md` P.3 Aturan penempatan logika.
- GitHub Issue #46 — refactor backend ke Action Pattern dan keputusan Phase D.


---

# ADR-0004 — Snapshot Historis Immutable dan Koreksi Berbasis Versi

- **Status keputusan:** Accepted
- **Status dokumen:** Draft untuk review tim
- **Scope:** Jadwal snapshot, historical consistency, correction/versioning, concurrency

## Context

Master SAKIP seperti indikator, target tahunan, baseline, unit, dan definisi komponen dapat berubah. Pengukuran dan laporan historis tetap harus dapat dijelaskan menggunakan konteks yang berlaku saat periode/jadwal tersebut dibekukan.

Jika histori membaca master live, perubahan setelahnya dapat mengubah arti angka lama tanpa audit/restatement resmi.

## Decision

Gunakan snapshot sebagai immutable historical context.

Pada event domain yang ditetapkan (misalnya aktivasi jadwal atau jalur koreksi resmi), server membentuk snapshot yang menyalin field yang diperlukan untuk interpretasi historis.

Invariant utama:

1. perubahan master setelah snapshot terbentuk **tidak** otomatis memperbarui snapshot lama;
2. definisi perhitungan yang diperlukan histori ikut dibekukan, termasuk komponen pada indikator non-manual;
3. consumer downstream menggunakan snapshot/context version yang benar, bukan fallback diam-diam ke live master bila contract mengharuskan snapshot;
4. koreksi histori menggunakan mekanisme version/correction yang disahkan, bukan overwrite generik;
5. snapshot yang sudah dirujuk oleh histori tidak boleh dimutasi secara oportunistis;
6. stale/version identity harus mampu mendeteksi perubahan context yang relevan.

## Transactional Publication

Snapshot parent dan seluruh child definition yang menjadi satu context harus dipublikasikan secara konsisten.

Bila implementasi memakai lifecycle `draft/unfinalized → populate children → final`, seluruh langkah publication harus berada dalam transaction/locking yang mencegah consumer melihat context setengah jadi.

Exact field/lifecycle tetap mengikuti Data Model/Workflow/issue implementasi yang berlaku.

## Alternatives Considered

### Selalu membaca master live

Ditolak karena menyebabkan historical restatement diam-diam.

### Copy hanya nilai target tetapi tidak definisi formula

Ditolak untuk indikator data-driven karena histori formula dapat berubah walau angka target tetap sama.

### Overwrite snapshot lama ketika ada koreksi

Ditolak karena menghilangkan provenance dan mengubah arti histori.

## Consequences

### Positive

- histori dapat direproduksi;
- master bebas berkembang tanpa merusak data lama;
- audit/version dapat menjelaskan koreksi resmi;
- pengukuran dan laporan memakai konteks yang stabil.

### Trade-offs

- schema bertambah;
- correction flow lebih kompleks;
- publisher/consumer membutuhkan version/stale handling;
- concurrency publication perlu test multi-connection.

## Guardrails

- jangan fail-open dari frozen context ke master live jika snapshot diwajibkan;
- jangan update/delete snapshot historis secara generik;
- jangan menganggap buka-kembali jadwal sebagai izin overwrite histori tanpa contract;
- jangan mengklaim race safety hanya dari sequential test;
- perubahan snapshot correction harus ter-audit dan ter-version sesuai domain.

## Verification

Test sesuai risiko harus membuktikan:

- perubahan master tidak mengubah histori existing;
- snapshot parent + child konsisten;
- stale context ditolak atau direkonsiliasi sesuai contract;
- koreksi menghasilkan version/context baru bila diwajibkan;
- concurrent publication/mutation tidak menghasilkan partially-published snapshot.

## References

- `document/SAKIP - PRD.md` §12.5 Snapshot, §17.9 Pembekuan Cara Hitung.
- `document/SAKIP - Data Model.md` `jadwal_snapshot` dan `jadwal_snapshot_komponen`.
- `document/SAKIP - Workflow.md` §5 Aktivasi Jadwal & Pembentukan Snapshot.
- `document/SAKIP_ENGINEERING_STANDARDS.md` §5 Database dan concurrency, §6 Kontrak domain kinerja.


---

# ADR-0005 — Atomic Indicator Definition Writer

- **Status keputusan:** Accepted
- **Status dokumen:** Draft untuk review tim
- **Scope:** Indicator formula/metadata mutation, component CRUD transport, Phase D

## Context

Definisi indikator non-manual tidak selalu dapat divalidasi per komponen secara independen. Perubahan tipe perhitungan, role komponen, kode, bobot, urutan, tambah/nonaktif/hapus, dan metadata formula dapat memerlukan validasi terhadap **final state seluruh definisi**.

Tiga endpoint mutation komponen per-row memungkinkan beberapa masalah:

- intermediate formula tidak valid;
- multi-row transition tidak atomik;
- authorization delta sulit dievaluasi sebagai satu intent;
- transaction/audit tersebar;
- type transition dapat gagal di tengah perubahan.

## Decision

Gunakan satu canonical mutation transport:

`PATCH /perencanaan/indikator/{indikator}/formula`

Flow:

```text
Route
→ ChangeIndicatorFormulaRequest
→ thin Controller
→ ChangeIndicatorFormula Action
→ KomponenMutationService / IndikatorPerhitunganService
→ transaction + audit
```

Writer menerima final mutation intent yang diperlukan dan memvalidasi final state sebelum commit.

Legacy mutation routes berikut dipensiunkan setelah seluruh supported caller bermigrasi:

- `POST /indikator/{indikator}/komponen`;
- `PUT /indikator/{indikator}/komponen/{komponen}`;
- `DELETE /indikator/{indikator}/komponen/{komponen}`.

Read/editor contract tetap tersedia melalui:

`GET /indikator/{indikator}/komponen`

## Authorization

Retirement route tidak mengubah permission semantics.

Action tetap menentukan actual delta dan mengevaluasi permission granular yang relevan, misalnya create/update/delete komponen sesuai perubahan nyata.

Deny, live state, stale revision, historical references, dan invariant final formula tetap ditegakkan di server.

## Alternatives Considered

### Pertahankan tiga writer per-row sebagai canonical

Ditolak karena final-state invariant lintas komponen tidak selalu dapat dijaga aman tanpa orchestration tambahan.

### Pertahankan legacy routes sebagai compatibility contract permanen

Tidak dipilih. Source-of-truth Phase D menyetujui retirement setelah caller bermigrasi dan regression evidence membuktikan endpoint lama tidak memutasi domain/audit.

### Generic command endpoint untuk seluruh indikator

Tidak dipilih; atomic writer hanya mencakup cohesive definition/formula use case.

## Consequences

### Positive

- final state dapat divalidasi sebelum persist;
- type transition + component mutation atomik;
- satu mutation owner;
- transaction/audit terpusat;
- stale token dan granular authorization dapat dievaluasi terhadap delta yang benar.

### Trade-offs

- payload lebih kaya daripada single-row CRUD;
- frontend editor perlu menyusun mutation intent;
- conflict resolution/rebase harus berhati-hati agar legacy route tidak hidup kembali.

## Guardrails

- jangan restore tiga legacy mutation routes tanpa keputusan baru;
- `GET` read route tidak menjadi writer;
- actual delta menentukan permission granular;
- deletion/nonactivation intent harus eksplisit;
- historical references tidak boleh rusak;
- no-op tidak boleh menghasilkan revision/audit palsu;
- seluruh mutation + audit berada dalam atomic boundary yang sesuai.

## Verification

Regression minimum:

- add/update/delete/nonactivate pada final state;
- swap/reuse kode secara atomik;
- strict identity kode sesuai contract;
- stale revision;
- permission create/update/delete berdasarkan actual delta;
- historical-reference guard;
- no-op;
- direct request ke legacy mutation routes gagal tanpa parent/child/revision/audit mutation;
- supported frontend caller memakai atomic writer.

## References

- GitHub Issue #46 — Phase D Indikator Komponen dan AC-8 exception.
- `document/SAKIP_ENGINEERING_STANDARDS.md` §2.
- `app/Actions/Perencanaan/ChangeIndicatorFormula.php`.
- `app/Services/Kinerja/KomponenMutationService.php`.
- `app/Services/Kinerja/IndikatorPerhitunganService.php`.


---

# ADR-0006 — Private File Storage dan Authorized Streaming

- **Status keputusan:** Accepted
- **Status dokumen:** Draft untuk review tim
- **Scope:** Evidence/document attachment storage, download authorization, privacy

## Context

SAKIP menyimpan dokumen legal dan bukti dukung yang tidak boleh menjadi static public asset. Beberapa lampiran terkait Renstra, PK, regulasi, rencana aksi, kegiatan, atau pengukuran memiliki authorization/object scope sendiri.

Menaruh file langsung di public disk atau memberi URL filesystem/static akan melewati authorization server dan meningkatkan risiko IDOR/data leakage.

## Decision

File SAKIP disimpan di **private storage** dan diakses melalui **authorized streamed route**.

Flow:

```text
Browser request download
→ Laravel route/auth
→ object + permission/scope validation
→ resolve safe storage record
→ stream private file
```

Path/nama file yang dipercaya berasal dari record server, bukan filesystem path bebas dari client.

Mode bukti `tautan` dan `teks` mengikuti kontrak domainnya dan tidak dipaksa menjadi file lokal.

## Security Rules

- tidak menggunakan public symlink sebagai akses bukti privat;
- jangan mengirim absolute private path ke client;
- validate object ownership/unit/stage/status sesuai domain;
- validate size/MIME/extension sesuai policy yang berlaku;
- hindari path traversal;
- jangan log isi file/token/credential;
- external URL pada mode tautan tidak boleh di-fetch server tanpa kebutuhan dan proteksi SSRF yang sesuai;
- DB transaction tidak dianggap otomatis rollback operasi filesystem.

## Alternatives Considered

### Public disk + obscure filename

Ditolak. Obscurity bukan authorization.

### Signed URL langsung tanpa server object authorization

Tidak menjadi baseline karena requirement saat ini menetapkan route streamed ber-permission. Perubahan delivery mechanism memerlukan keputusan baru dan threat review.

### Menyimpan file base64 dalam database/props

Ditolak untuk baseline karena meningkatkan payload/storage cost dan berisiko membawa file ke browser history/props.

## Consequences

### Positive

- setiap download dapat dievaluasi terhadap permission/object state terbaru;
- file tidak terekspos sebagai static asset;
- audit/access control dapat diterapkan pada boundary server.

### Trade-offs

- aplikasi menjadi data path download;
- filesystem + database consistency harus ditangani eksplisit;
- deployment perlu memastikan permission/capacity private storage benar.

## Guardrails

- file mode `file` tetap private;
- authorization object dilakukan setiap download;
- jangan menerima path storage arbitrary dari request;
- jangan menghapus file existing sebagai kompensasi spekulatif;
- malware scan hanya diklaim bila infra benar-benar tersedia dan scope mengharuskan.

## Verification

Test sesuai risiko:

- authorized actor dapat stream file yang benar;
- unauthorized/cross-unit actor ditolak;
- malformed identifier/path tidak membuka file lain;
- deleted/immutable parent mengikuti lifecycle domain;
- response tidak membocorkan path internal;
- upload validation dan storage-failure compensation diuji bila area tersebut berubah.

## References

- `document/SAKIP - PRD.md` §18 Bukti Dukung dan §6 Arsitektur.
- `document/SAKIP - Data Model.md` entitas `jenis_berkas` dan `berkas`.
- `document/SAKIP_ENGINEERING_STANDARDS.md` §7 Bukti dukung dan audit.
- `document/SAKIP - Plan Pengembangan.md` P.1 private storage baseline.


---

# ADR-0007 — Target Manual Rencana Aksi Menggunakan `komponen_id = NULL`

- **Status keputusan:** Accepted (Q34, 9 Oktober 2026)
- **Status dokumen:** Draft untuk review tim
- **Scope:** `rencana_aksi_target`, ISS-05.01 Penyusunan Target Rencana Aksi per Periode

## Context

Data Model §2.24 dan ERD mendefinisikan `rencana_aksi_target.komponen_id` sebagai FK `NOT NULL` dengan `unique(rencana_aksi_id, periode_id, komponen_id)`. PRD §14.3 dan Plan 11.2 mengikuti definisi yang sama.

Keputusan Penyelarasan **Q7** menetapkan indikator `manual` memiliki satu target langsung per periode, indikator nonmanual memakai target per komponen dengan skor turunan, dan **tanpa komponen semu**. Struktur Data Model tidak dapat menampung target manual tanpa melanggar Q7.

## Decision

1. `rencana_aksi_target.komponen_id` nullable. Baris indikator manual wajib `komponen_id IS NULL`, tepat satu baris per (`rencana_aksi_id`, `periode_id`). Baris nonmanual wajib `komponen_id NOT NULL` dan merujuk komponen efektif.
2. Keunikan ditegakkan database dengan dua partial unique index: (`rencana_aksi_id`, `periode_id`) `WHERE komponen_id IS NULL` dan (`rencana_aksi_id`, `periode_id`, `komponen_id`) `WHERE komponen_id IS NOT NULL`.
3. Validasi server membedakan tipe: manual menolak `komponen_id` terisi; nonmanual menolak `komponen_id` kosong. `nilai` tetap nullable: `0` sah, `null` berarti belum diisi.
4. Snapshot komponen kosong untuk indikator manual adalah keadaan sah (Data Model §2.18).

## Alternatives Considered

### Komponen semu per indikator manual

Ditolak karena melanggar Q7, mengotori master `indikator_komponen`, ikut membeku ke `jadwal_snapshot_komponen`, dan membuat gerbang kelengkapan pengajuan ambigu.

### Tabel terpisah untuk target manual

Ditolak karena menduplikasi skema dan memecah jalur baca/tulis permanen hanya untuk satu kasus nullable.

## Consequences

- Data Model §2.24 diselaraskan melalui Q34.
- Gerbang kelengkapan pengajuan (Plan 11.3, ISS-05.03) untuk indikator manual memeriksa satu baris `NULL` per periode efektif, bukan per komponen.

## Verification

- `tests/Feature/RencanaAksi/RencanaAksiTargetTest.php` dan `RencanaAksiPersistenceTest.php`: keunikan, nilai `0` vs `null`, dan pembedaan manual/nonmanual.

## References

- `document/SAKIP - Keputusan Penyelarasan.md` Q7, Q34.
- `document/SAKIP - Data Model.md` §2.18, §2.24.
- `document/SAKIP - PRD.md` §14.3; `document/SAKIP - Plan Pengembangan.md` 11.1–11.2.
- Migration `2026_10_04_043803_align_rencana_aksi_header_d1_d5_d7.php`.


---

# ADR-0008 — Kolom Alasan Deviasi Target PK pada Rencana Aksi

- **Status keputusan:** Accepted (Q34, 9 Oktober 2026)
- **Status dokumen:** Draft untuk review tim
- **Scope:** header `rencana_aksi`, ISS-05.01 (penyimpanan draf) dan ISS-05.03 (pengajuan)

## Context

PRD §14.5, Workflow §7, dan Data Model §2.24 menetapkan rekonsiliasi target periodik terhadap target tahunan PK sebagai **peringatan + alasan wajib**, bukan blokir. Data Model §2.23 hanya menyediakan `alasan_revisi`; Plan 11.6 membolehkan `alasan_revisi` atau kolom alasan pengajuan yang relevan. Memakai `alasan_revisi` akan mencampur alasan deviasi dengan alasan revisi/buka-kembali.

## Decision

1. Tambah kolom `alasan_deviasi_pk` (text, nullable) pada header `rencana_aksi` untuk alasan deviasi total target periode efektif terakhir terhadap target tahunan PK **snapshot**. `alasan_revisi` tidak dipakai untuk makna ini.
2. **Penyimpanan draf (ISS-05.01):** alasan boleh kosong. Deviasi hanya menghasilkan peringatan non-blokir; draf tetap dapat disimpan.
3. **Pengajuan (ISS-05.03):** alasan wajib non-kosong bila total periode efektif terakhir tidak setara target PK (toleransi `indikator.presisi`). Validasi ini ditegakkan server pada Action pengajuan di dalam transaksi terkunci milik ISS-05.03, bukan pada penyimpanan draf.
4. Nilai alasan tampil pada payload baca/preview dan setiap perubahan tercatat di `audit_log`.

## Alternatives Considered

### Pakai ulang `alasan_revisi`

Ditolak karena mencampur dua makna dan membuat validasi serta riwayat ambigu.

### Hanya dicatat di `audit_log`

Ditolak karena alasan tidak queryable untuk payload baca/preview dan tidak tampil persisten di layar.

## Consequences

- Data Model §2.23 diselaraskan melalui Q34.
- Target PK tetap hanya berubah melalui revisi PK resmi.

## Verification

- `tests/Feature/RencanaAksi/RencanaAksiIndexTest.php` (`test_deviasi_pk_butuh_alasan_dan_tersimpan_sebagai_simpan`, `test_nonmanual_menampilkan_skor_turunan_peringatan_dan_deviasi`) dan `RencanaAksiPreviewTest.php` untuk peringatan non-blokir pada draf.
- Validasi wajib saat pengajuan diuji pada ISS-05.03.

## References

- `document/SAKIP - PRD.md` §14.5; `document/SAKIP - Workflow.md` §7.
- `document/SAKIP - Data Model.md` §2.23–§2.24; `document/SAKIP - Plan Pengembangan.md` 11.6.
- `document/SAKIP - User Issues.md` ISS-05.01 AC-6, ISS-05.03.
- `document/SAKIP - Keputusan Penyelarasan.md` Q34.


---
