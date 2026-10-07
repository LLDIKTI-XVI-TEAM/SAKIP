# Referensi Developer — Pengesahan Rencana Aksi (ISS-05.05)

Transisi `diverifikasi → disahkan` untuk `rencana_aksi`, dengan pemisahan tugas F1 (keras, jalur PIC) dan F2 (pengecualian jalur Perencanaan beraudit). Commit: `3af3537` (`feat: tambahkan pengesahan rencana aksi dengan pemisahan tugas F1/F2`).

## 1. Peta file dan route

| Status | File | Peran (satu kalimat) |
|---|---|---|
| Dibuat | `app/Actions/RencanaAksi/SahkanRencanaAksi.php` | Satu-satunya pemilik logika bisnis pengesahan lewat `handle(User $actor, string $id, array $data)`. |
| Dibuat | `app/Policies/RencanaAksiPolicy.php` | Gerbang otorisasi `sahkan()`, `deleteEvidence()`, `usesPlanningPath()`, plus `capability()` dan `businessErrors()`. |
| Dibuat | `app/Http/Requests/RencanaAksi/SahkanRencanaAksiRequest.php` | Validasi input: hanya `versi`, sisanya `prohibited`. |
| Dibuat | `app/Http/Controllers/RencanaAksi/SahkanRencanaAksi.php` | Controller tipis invokabel yang mendelegasikan ke satu Action lalu redirect back. |
| Diubah | `app/Models/RencanaAksi.php` | Ditambah relasi `indikator()`, `jadwalSnapshot()`, `jadwalTahunan()`, `versions()`, `latestVersion()`, `ratifiedVersion()`, dan `targetUnitId()`. |
| Diubah | `app/Support/PermissionCodes.php` | Ditambah 4 konstanta: `RENCANA_AKSI_VERIFIKASI`, `RENCANA_AKSI_KEMBALIKAN`, `RENCANA_AKSI_SAHKAN` (`'rencana_aksi:sahkan'`), `RENCANA_AKSI_BUKA_KEMBALI`. |
| Diubah | `routes/web.php` | Registrasi satu route POST pengesahan (lihat tabel route). |
| Dibuat | `resources/js/Pages/RencanaAksi/types.ts` | Tipe `RencanaAksiSahkan` (`id`, `versi`, `status`, `nomor_pengajuan`, `can.ratify`). |
| Dibuat | `resources/js/Pages/RencanaAksi/Show.tsx` | Stub halaman status + tombol yang membuka dialog hanya bila `can.ratify && status === 'diverifikasi'`. |
| Dibuat | `resources/js/Pages/RencanaAksi/SahkanDialog.tsx` | Dialog konfirmasi `useForm({ versi })` yang POST ke URL sahkan tanpa field bisnis tambahan. |
| Dibuat | `tests/Feature/RencanaAksi/SahkanRencanaAksiTest.php` | 8 test feature terisolasi untuk AC-1–AC-6 + regresi provenance + stale concurrency. |
| Tidak disentuh | `app/Models/RencanaAksiVersi.php` | Model versi beku sudah ada; tidak ada perubahan skema pada issue ini. |

Route (di dalam grup `Route::middleware(['auth', 'active'])`):

| Method | URL | Nama | Constraint |
|---|---|---|---|
| `POST` | `/rencana-aksi/{id}/sahkan` | `rencana-aksi.sahkan` | `whereUuid('id')` |

Tidak ada route GET halaman/antrean — `Show.tsx` adalah stub tanpa route terdaftar.

## 2. Layer kanonis

Alur wajib: `Route → Middleware (`auth`, `active`) → FormRequest/Policy → Controller (tipis, 1 Action) → Action → Model/DB/Audit → Inertia`.

- `SahkanRencanaAksi` (controller) hanya memanggil `$action->handle($request->user(), $id, $request->validated())` lalu `redirect()->back()->with('success', ...)`.
- Seluruh keputusan izin dan bisnis hidup di `RencanaAksiPolicy` + `SahkanRencanaAksi` (action), dieksekusi server-side di dalam transaksi.
- **Larangan:** tanpa logika bisnis di controller maupun React. React hanya membaca `can.ratify` dari props; request langsung (curl/HTTP client) tetap ditolak bila permission/scope/deny/status tidak memenuhi, karena `authorize()` FormRequest hanya mengecek user login dan keputusan final ada di Action.

Pola ini meniru acuan yang sudah mapan: `PengukuranKinerjaPolicy::capability()/businessErrors()/usesPlanningPath()` dan `App\Actions\Pengukuran\ChangePengukuran::handle()` — bedanya action ini satu perintah (tanpa `match ($command)`), tanpa logika komponen/bukti/klaim, tanpa `RiwayatPengukuran`, dan alasan audit berupa string tetap `'Mengesahkan rencana aksi.'`.

## 3. Permission dan Policy

- Katalog: `rencana_aksi:sahkan` — permission **sensitif** (audit wajib memuat `dasar_izin`). Resolusi memakai `PermissionResolver::decide($actor, 'rencana_aksi:sahkan', $ra->targetUnitId())` dengan `targetUnitId()` mengembalikan `unit_id`. Peran Perencanaan/Superadmin memegangnya (allow global via role pada fixture); deny eksplisit (`user_permission_denied`) selalu menang.
- `capability(User $user, RencanaAksi $ra, string $permission)`: cek `resolver->allows(...)` dulu — gagal → deny `'Izin tindakan tidak tersedia atau telah dicabut.'`; lolos → gabungkan `businessErrors()` dan deny dengan pesan gabungan bila ada.
- `businessErrors()` dipakai ulang oleh Action **setelah** resolver; kegagalannya menjadi `ValidationException` (field `versi`), bukan deny palsu.
- `usesPlanningPath()`: `decide($user, 'rencana_aksi:update', ...)` allowed **dan** salah satu role penentu (`roles.id`) berkode `perencanaan`/`superadmin`. Dipakai untuk menetapkan `jalur_pengajuan` saat submit (bukan saat sahkan).

Daftar `businessErrors()` **berurutan persis seperti di code** (`app/Policies/RencanaAksiPolicy.php:68-109`):

1. Snapshot hilang/tidak cocok (`!$snapshot || indikator_id` beda atau `jadwal->tahun !== $ra->tahun`) → `'Snapshot jadwal tidak cocok dengan rencana aksi.'` — **early return**, pemeriksaan berikutnya dilewati.
2. `$ra->unit_id !== $snapshot->unit_id` → `'Unit rencana aksi tidak cocok dengan snapshot jadwal.'`
3. `$jadwal->renstra_id` beda dari `indikator->sasaranStrategis->renstra_id` → `'Renstra jadwal tidak cocok dengan indikator.'`
4. Unit snapshot berstatus nonaktif → `'Unit organisasi rencana aksi berstatus nonaktif.'`
5. `$jadwal->status !== 'aktif'` → `'Jadwal tahunan harus aktif.'`
6. Versi hilang atau `version->jadwal_snapshot_id !== $ra->jadwal_snapshot_id` → `'Versi pengajuan yang direviu belum tersedia atau tidak cocok.'` — **early return** dengan error nomor 2–5 yang sudah terkumpul.
7. F1: `$version->diajukan_by === $user->id && $version->jalur_pengajuan === 'pic'` → `'Pengaju jalur PIC tidak boleh menyetujui pengajuannya sendiri.'`
8. `$ra->status_alur !== 'diverifikasi'` → `'Status rencana aksi tidak sesuai untuk tindakan ini.'`
9. Lewat `penutupan` tanpa sesi koreksi resmi yang mencakup indikator + jenis objek `rencana_aksi` → `'Tahun sudah ditutup; diperlukan sesi koreksi resmi yang mencakup rencana aksi ini.'`

## 4. Action flow (`SahkanRencanaAksi::handle()`)

1. **Lock** dalam `DB::transaction`: `User::lockForUpdate()`, `RencanaAksi::lockForUpdate()`, `JadwalSnapshot::lockForUpdate()`, `JadwalTahunan::lockForUpdate()`; relasi `jadwalSnapshot` dipasang manual via `setRelation` agar Policy membaca baris terkunci yang sama.
2. **Resolver**: `decide($actor, 'rencana_aksi:sahkan', $ra->targetUnitId())`; `allowed === false` → `AuthorizationException('Izin tindakan tidak tersedia atau telah dicabut.')`.
3. **Business errors**: `$this->policy->businessErrors($actor, $ra)`, lalu cek stale `$ra->versi !== (int) $data['versi']` → tambah `'Data telah berubah. Muat ulang sebelum mengulangi tindakan.'`; bila ada error → `ValidationException::withMessages(['versi' => $errors])`. Catatan: `latestVersion` yang dipakai adalah turunan `orderByDesc('nomor')`, bukan input client.
4. **Mutasi atomik**: `auditState()` sebagai `$before`; `$selfApproval = $version->diajukan_by === $actor->id && $version->jalur_pengajuan === 'perencanaan'`; `$version->update(['disahkan_by' => $actor->id, 'disahkan_at' => now()])`; header: `status_alur = 'disahkan'`, `disahkan_by`, `disahkan_at`, `versi++`, `save()`; `unsetRelation('latestVersion')`.
5. **Audit sukses dalam tx** via `WriteAuditLog::handle()`: `tindakan = 'rencana_aksi.sahkan'`, `alasan = 'Mengesahkan rencana aksi.'`, `dasar_izin = $decision`, `nilai_lama`/`nilai_baru` dari `auditState()` (lihat §8), `nilai_baru` ditambah `'self_approval' => bool`.
6. **Audit ditolak di luar tx** (catch `AuthorizationException`/`ValidationException`, hanya bila record masih ada): `tindakan = 'rencana_aksi.ditolak'`, `alasan = 'Tindakan sahkan ditolak.'`, `nilai_lama = null`, `nilai_baru = ['tindakan_diminta' => 'sahkan', 'jenis_penolakan' => 'otorisasi'|'validasi_bisnis', 'alasan_penolakan' => message|errors()]`, lalu exception dilempar ulang.

## 5. F1/F2

Pemicu memakai **provenance beku versi** — `rencana_aksi_versi.diajukan_by` + `rencana_aksi_versi.jalur_pengajuan` — **bukan** `rencana_aksi.created_by` dan bukan role user saat ini. Lima keputusan yang terkunci di implementasi (tidak ditemukan artefak grilling Q1–Q5 di `document/`; pengunci kanonis adalah Q15 Keputusan Penyelarasan, Data Model §4.1–§4.2, Plan 1.21, dan AC ISS-05.05):

- **K1 (F1 keras):** pengaju jalur `pic` yang sama dengan aktor → tolak, walau ia kini berrole Perencanaan/berizin sahkan.
- **K2 (F2 izinkan):** pengaju jalur `perencanaan` yang masih lolos resolver → izinkan, catat `self_approval: true` pada `nilai_baru` audit.
- **K3 (F2 bukan bypass):** deny eksplisit atau permission hilang → tetap 403/`AuthorizationException`; F2 tidak melewati resolver.
- **K4 (provenance beku):** ganti role/grant/PIC setelah submit tidak mengubah `diajukan_by`/`jalur_pengajuan`; `created_by` tidak pernah dipakai untuk F1/F2.
- **K5 (jalur ditentukan saat submit):** `usesPlanningPath()` (cek `rencana_aksi:update` + role perencanaan/superadmin) hanya dipakai saat pengajuan untuk membekukan `jalur_pengajuan`; saat sahkan tidak ada reklasifikasi jalur.

## 6. Concurrency

- `expected_versi = header.versi` — counter integer pada `rencana_aksi` (default 1, `++` setiap mutasi), dikirim client sebagai `versi` dan dibandingkan di dalam lock.
- `rencana_aksi_versi.nomor` (unique per `rencana_aksi_id`) **tidak** dipakai untuk lock: ia menomori riwayat pengajuan (turunan `latestVersion` = nomor terbesar), sedangkan yang diserialkan adalah baris header. Memakai `nomor` sebagai token akan salah bila versi baru terbit di tengah jalan.
- Skenario paralel: dua reviewer kirim `versi: 1` bersamaan → transaksi diserialkan oleh `lockForUpdate` pada header; yang pertama commit (`versi` 1→2, status `disahkan`); yang kedua membaca `versi = 2 ≠ 1` (plus status bukan `diverifikasi`) → `ValidationException` berisi `'Data telah berubah. Muat ulang sebelum mengulangi tindakan.'` + audit `rencana_aksi.ditolak`. Tercakup `test_stale_concurrency_pengesahan_kedua_ditolak`.

## 7. Request validation (`SahkanRencanaAksiRequest`)

- `authorize()` hanya memastikan user login; otorisasi substantif di Policy/Action.
- Satu-satunya field diterima: `versi` (`required|integer|min:1`). **Tidak ada field `catatan`** — tidak seperti alur kembalikan, pengesahan tidak meminta alasan (alasan audit berupa string tetap server-side).
- Daftar `prohibited` + alasan tiap field (pesan: `'Kolom :attribute ditentukan server.'`):
  - `status_alur`, `status` — status hanya bergerak via state machine (`diverifikasi → disahkan`).
  - `self_approval` — penanda dihitung server dari provenance versi (F2), bukan klaim client.
  - `disahkan_by`, `disahkan_at` — identitas/waktu pengesah diisi dari aktor terautentikasi + `now()`.
  - `diajukan_by`, `jalur_pengajuan` — provenance beku milik versi pengajuan.
  - `jadwal_snapshot_id` — referensi snapshot milik header/versi; client tidak boleh menukar snapshot.

## 8. Audit shape

Sukses (`rencana_aksi.sahkan`, di dalam tx; tanpa secret/token):

```json
{
  "tindakan": "rencana_aksi.sahkan",
  "alasan": "Mengesahkan rencana aksi.",
  "dasar_izin": { "allowed": true, "roles": ["<uuid-role>"], "reason": "..." },
  "nilai_lama": { "status_alur": "diverifikasi", "versi": 1,
    "versi_pengajuan": { "id": "<uuid>", "nomor": 1, "diajukan_by": "<uuid>", "jalur_pengajuan": "pic", "dasar_izin_pengajuan": { "jalur": "pic", "unit_id": "<uuid>" }, "disahkan_by": null, "disahkan_at": null } },
  "nilai_baru": { "status_alur": "disahkan", "versi": 2,
    "versi_pengajuan": { "id": "<uuid>", "nomor": 1, "diajukan_by": "<uuid>", "jalur_pengajuan": "pic", "dasar_izin_pengajuan": { "...": "..." }, "disahkan_by": "<uuid-aktor>", "disahkan_at": "2026-03-15T09:00:00+08:00" },
    "self_approval": false }
}
```

Ditolak (`rencana_aksi.ditolak`, di luar tx):

```json
{
  "tindakan": "rencana_aksi.ditolak",
  "alasan": "Tindakan sahkan ditolak.",
  "nilai_lama": null,
  "nilai_baru": { "tindakan_diminta": "sahkan", "jenis_penolakan": "validasi_bisnis",
    "alasan_penolakan": { "versi": ["Pengaju jalur PIC tidak boleh menyetujui pengajuannya sendiri."] } }
}
```

`jenis_penolakan` = `otorisasi` bila `AuthorizationException` (dengan `alasan_penolakan` string), `validasi_bisnis` bila `ValidationException` (dengan `alasan_penolakan` map error).

## 9. Test

Fixture terisolasi di `setUp()`: membuat unit, renstra, sasaran, indikator, PK, periode, jadwal aktif, snapshot, dan RA `diverifikasi` langsung — **tanpa** memakai trait/fixture ISS-05.03/05.04. Provenance tiap test dibuat via `ajukanVersi($jalur, $diajukanBy)` yang **INSERT** `rencana_aksi_versi` langsung (tidak pernah UPDATE, lihat §11). Waktu dibekukan 15 Maret 2026 09:00; katalog akses dari `AccessCatalogSeeder`; Perencanaan lolos via role, PIC operasional via grant `rencana_aksi:ajukan` scoped unit.

| Test | AC | Yang dibuktikan |
|---|---|---|
| `test_1_f1_menolak_pengesahan_pengaju_jalur_pic` | AC-1 | Gate + HTTP menolak pengaju PIC; status tetap `diverifikasi`; ada audit `rencana_aksi.ditolak`. |
| `test_2_perencana_lain_mengesahkan_pengajuan_pic_secara_atomik` | AC-2 | Header `disahkan` + `versi` 2 + `disahkan_by/at` header dan versi terisi; audit `self_approval: false`. |
| `test_3_f2_mengizinkan_self_approval_jalur_perencanaan_dengan_catatan_audit` | AC-3 | Pengaju Perencanaan mengesahkan diri sendiri; audit `self_approval: true`. |
| `test_4_f2_tidak_melewati_deny_atau_permission_hilang` | AC-4 | Deny eksplisit → gate deny + HTTP 403; aktor tanpa permission juga 403. |
| `test_5_versi_bukan_terbaru_ditolak` | AC-5 | Header menunjuk snapshot baru (`nomor_versi` 2) sedang versi merujuk snapshot lama → error `versi`, `disahkan_at` tetap null. |
| `test_6_bukti_rujukan_versi_resmi_tidak_boleh_dihapus` | AC-6 | `deleteEvidence` allow sebelum sah, deny sesudah sah. |
| `test_regression_provenance_beku_setelah_perubahan_role` | REGRESI | `created_by` (drafter) ≠ `diajukan_by`; pengaju PIC yang kini berrole Perencanaan tetap ditolak F1. |
| `test_stale_concurrency_pengesahan_kedua_ditolak` | AC-2/AC-5 | Sahkan kedua dengan `versi: 1` basi → error `'Data telah berubah. ...'`; pemenang pertama tercatat. |

Menjalankan (PostgreSQL disposable `sakip_test`, sesuai `phpunit.xml` + guard `tests/TestCase.php`):

```powershell
$env:DB_CONNECTION = 'pgsql'; $env:DB_HOST = '<host-container-testing>'
$env:DB_DATABASE = 'sakip_test'; $env:DB_USERNAME = 'sakip_test'
$env:DB_PASSWORD = 'sakip_test'; $env:SAKIP_TEST_ALLOW_DATABASE_RESET = '1'
vendor/bin/pest tests/Feature/RencanaAksi/SahkanRencanaAksiTest.php
```

Jangan memakai database development/SIMPEG; `APP_ENV=testing` saja tidak cukup — guard menolak reset bila nama DB/username aktual bukan `sakip_test`.

Hasil terakhir: **8/8 lulus, 39 assertions; PHPStan 0 error; Pint passed.**

## 10. Non-goal dan follow-up terpisah

- **GET halaman/antrean:** `Show.tsx`/`SahkanDialog.tsx` stub presentasional; belum ada route GET, controller index/show, maupun query antrean. Tombol hanya tampil bila `can.ratify && status === 'diverifikasi'`.
- **Endpoint hapus-bukti:** `RencanaAksiPolicy::deleteEvidence()` sudah mendefinisikan gerbang (tolak bila `status_alur === 'disahkan'` atau `ratifiedVersion !== null`), tetapi belum ada route/controller untuk eksekusi hapus — milik ISS-05.02/freeze penuh.
- **Sinkron `rencanaAksiUnitScoped()`:** helper `PermissionCodes::rencanaAksiUnitScoped()` (filter `unitScoped()` berprefix `rencana_aksi:`) belum dipakai alur ini; sinkronisasi katalog/scope menyusul terpisah.

## 11. Gotcha

- **Trigger `reject_submission_version_mutation`** (`2026_09_18_030003`): `UPDATE`/`DELETE` pada `rencana_aksi_versi` ditolak kecuali pengisian sekali-gus `disahkan_by` + `disahkan_at` (keduanya null→terisi, field lain identik). Karena itu test memakai `RencanaAksiVersi::create()` (INSERT) untuk provenance, dan Action memakai `$version->update(...)` satu kali — jangan pernah `save()` versi dua kali atau menyentuh kolom lain.
- **Unique snapshot** `jadwal_snapshot(jadwal_id, indikator_id, nomor_versi)`: `test_5` wajib membuat snapshot pengganti dengan `nomor_versi: 2` agar tidak melanggar constraint.
- **`user_roles.user_id` unique (1-role-per-user):** regresi mensimulasikan pindah role via `DB::table('user_roles')->where('user_id', ...)->update(['role_id' => ...])`, bukan attach kedua — attach ganda melanggar constraint dan tidak mewakili realitas Fase Awal.
