# SAKIP LLDIKTI Wilayah XVI

Sistem Akuntabilitas Kinerja Instansi Pemerintah (SAKIP) untuk Lembaga Layanan Pendidikan Tinggi Wilayah XVI (Gorontalo, Sulawesi Utara, Sulawesi Tengah).

---

## 📚 Dokumen Spesifikasi & Perencanaan

Seluruh dokumentasi teknis dan bisnis telah dirapikan ke dalam folder [`document/`](file:///c:/MY%20FOLDER/LLDIKIT-MAGANG/SAKIP/document):

| Dokumen | Lokasi | Deskripsi |
|---|---|---|
| **PRD (Product Requirements Document)** | [`document/SAKIP - PRD.md`](file:///c:/MY%20FOLDER/LLDIKIT-MAGANG/SAKIP/document/SAKIP%20-%20PRD.md) | Spesifikasi produk lengkap, stakeholder, persona, FR/NFR, acceptance criteria |
| **Rencana Pengembangan (Plan)** | [`document/SAKIP - Plan Pengembangan.md`](file:///c:/MY%20FOLDER/LLDIKIT-MAGANG/SAKIP/document/SAKIP%20-%20Plan%20Pengembangan.md) | Rencana teknis modular & granular dengan scope, dependency, dan DoD |
| **Workflow Detail & State Machine** | [`document/SAKIP - Workflow.md`](file:///c:/MY%20FOLDER/LLDIKIT-MAGANG/SAKIP/document/SAKIP%20-%20Workflow.md) | Alur kerja, diagram alir bisnis, aturan batas waktu, dan protokol buka kembali |
| **Design System SAKIP** | [`document/design-system.md`](file:///c:/MY%20FOLDER/LLDIKIT-MAGANG/SAKIP/document/design-system.md) | Sistem token warna institusi (`#122E92`, `#D6AC48`), font Poppins, aturan komponen |
| **Hasil Rapat Pemantapan Konsep** | [`document/Rapat Pemantapan Konsep Pengembangan SAKIP - Hasil Rapi.txt`](file:///c:/MY%20FOLDER/LLDIKIT-MAGANG/SAKIP/document/Rapat%20Pemantapan%20Konsep%20Pengembangan%20SAKIP%20-%20Hasil%20Rapi.txt) | Transkrip dan intisari rapat pembahasan SAKIP bersama pimpinan/tim |
| **Data Referensi Riil 2026** | [`document/Pengukuran Kinerja  Triwulan 2026.xlsx`](file:///c:/MY%20FOLDER/LLDIKIT-MAGANG/SAKIP/document/Pengukuran%20Kinerja%20%20Triwulan%202026.xlsx) | Data riil IKU, target tahunan/triwulanan LLDIKTI XVI Tahun 2026 |

---

## 🚀 Tech Stack

- **Backend**: Laravel 13 (PHP 8.3)
- **Frontend**: React 19 + TypeScript (via Vite)
- **Adapter**: Inertia.js (`@inertiajs/react`)
- **Database development/CI**: PostgreSQL 17 (sesuai `compose.yaml`)
- **Styling**: Tailwind CSS v4 (Token `@theme`)
- **Containerization**: Podman 5.8 & Podman-compose 1.6
- **Testing**: PHPUnit (Backend) + Vitest / React Testing Library (Frontend)

---

## 🛠️ Menjalankan Aplikasi Secara Lokal

1. **Jalankan Backend**:
   ```powershell
   php artisan serve
   ```
2. **Jalankan Frontend (HMR Dev Server)**:
   ```powershell
   bun run dev
   ```
3. Akses melalui browser di `http://localhost:8000`.

---

## Bootstrap Super Admin pertama

Setelah skema dan katalog akses tersedia di lingkungan tujuan, calon Super Admin yang telah ditunjuk login melalui Keycloak sekali. Akunnya akan berstatus menunggu aktivasi. Ia menyalin **ID akun SAKIP** dari halaman tersebut dan menyampaikannya kepada operator yang berwenang bersama referensi otorisasi di luar aplikasi.

Operator menjalankan `php artisan sakip:bootstrap-superadmin` pada server SAKIP. Perintah meminta ID akun, menampilkan nama dan email untuk dicocokkan dengan otorisasi, lalu meminta identitas/referensi operator, alasan, dan satu konfirmasi. Untuk eksekusi non-interaktif, berikan ID sebagai argumen serta `--operator-reference`, `--reason`, `--confirm-user=<ID yang sama>`, dan `--no-interaction`.

Bootstrap hanya berlaku sekali. Pengulangan untuk akun yang sama tidak memulihkan hak yang kemudian dicabut; ID akun lain ditolak. Lima preset role yang sudah disepakati dipasang secara teraudit. Role `pic` tersedia tanpa preset bawaan selama keputusan Q31 masih terbuka; kondisi ini belum menutup gate UAT final/produksi pada Plan Q31.2. Setelah operator berhasil, pengguna menekan **Periksa status** pada halaman menunggu aktivasi.

---

## 🧪 CI dan quality gate

Workflow `.github/workflows/ci.yml` berjalan pada PR menuju `development`/`main`, push ke kedua branch tersebut, dan pemicu manual. Enam job berjalan independen sehingga setiap pemeriksaan memiliki hasil sendiri di GitHub:

| Check | Command | Cakupan |
| --- | --- | --- |
| PHP Formatting | `composer lint` | Pint seluruh proyek, mode `--test` tanpa menulis ulang source |
| PHP Static Analysis | `composer analyse` | Larastan/PHPStan level 3, `app/`, `routes/`, dan `database/` |
| Backend Tests | `composer test` | Unit dan feature tests dengan PostgreSQL disposable |
| TypeScript | `bun run typecheck` | Typecheck source frontend dan frontend tests |
| Frontend Tests | `bun run test` | Vitest + React Testing Library pada komponen aplikasi |
| Production Build | `bun run build` | Build asset Vite menggunakan runtime Bun |

CI memakai PHP 8.3, PostgreSQL 17, dan Bun 1.3.11. Instalasi frontend memakai `bun install --frozen-lockfile`; `bun.lock` menjadi lockfile tunggal. Gunakan versi runtime yang sama ketika mereproduksi kegagalan.

**Pengecualian runtime test:** Vitest/jsdom dijalankan dengan Node 24.19.0 melalui script `bun run test`. Pada verifikasi awal, runtime Bun 1.3.11 tidak kompatibel dengan EventTarget jsdom 30. Node dipakai khusus untuk tool test ini; instalasi dependency, typecheck, dev server, dan build tetap menggunakan Bun. Pastikan Node tersebut tersedia di PATH sebelum menjalankan test frontend.

```powershell
bun install --frozen-lockfile
bun run typecheck
bun run test
bun run build
```

### Backend test dan isolasi database

Jalankan bukti PHP melalui Podman dengan PHP 8.3 serta ekstensi `pdo_pgsql`, `mbstring`, dan `bcmath`. `composer qa` menggabungkan Pint, static analysis, dan backend tests untuk verifikasi lokal; CI menjalankan ketiganya pada job terpisah agar tidak mengulang seluruh pemeriksaan.

Job Backend Tests membuat service PostgreSQL baru untuk setiap run, tidak memakai database development atau credential deployment. Feature tests membuat fixture sintetis sendiri melalui `RefreshDatabase`, tanpa menjalankan seeder development. Sebelum menjalankan suite lokal, siapkan container/database disposable terpisah dan verifikasi:

- `APP_ENV=testing`, tanpa config cache, serta `APP_KEY` khusus testing.
- Koneksi `pgsql` menunjuk host/port container testing, database dan username **`sakip_test`**; tidak menggunakan koneksi read/write terpisah atau DB_URL development.
- `SAKIP_TEST_ALLOW_DATABASE_RESET=1` hanya pada proses yang memang diizinkan mereset database disposable tersebut.
- Cache, session, dan mail memakai driver `array`; queue memakai `sync`.

`tests/TestCase.php` memeriksa opt-in serta identitas database/koneksi tulis sebelum trait `RefreshDatabase` dapat menjalankan reset. Nama database dan opt-in merupakan guard tambahan, bukan pengganti verifikasi isolasi container. Jangan menjalankan suite pada database aplikasi atau menggunakan container `sakip_db` development sebagai target reset.

Di dalam container PHP testing yang telah dikonfigurasi dan diverifikasi tersebut:

```sh
composer install --no-interaction --prefer-dist
composer qa
```

Laporan JUnit backend/frontend disimpan sebagai artifact GitHub selama tujuh hari, termasuk ketika test gagal. CI hijau membuktikan pemeriksaan dan coverage yang tersedia: test alur pengukuran existing, karakterisasi kalkulasi server, serta interaksi komponen Button. Hasil ini belum membuktikan seluruh requirement PRD, integrasi Keycloak, seluruh aturan authorization, atau seluruh invariant snapshot selesai.
