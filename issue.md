# [ISS-13.01] Setelan Identitas & Preferensi Presentasional

**Terkait User Story:** `US-13.01`  
**Prioritas:** 🟡 P1  
**Story Points:** 5  
**Labels:** `feature`, `settings-audit`, `P1`, `audit-sensitive`, `frontend`  
**Plan:** Modul 9: 9.1–9.6  
**PRD:** §26  
**Workflow:** §18  
**Data Model/Entitas:** `pengaturan`

---

## User Story
> **Sebagai** Admin atau Superadmin,  
> **Saya ingin** mengubah identitas instansi/aplikasi, label unit, zona waktu, format tanggal/angka, dan header/footer laporan,  
> **Sehingga** preferensi tampilan dapat berubah tanpa deployment kode.

---

## Acceptance Criteria
- [x] **AC-1:** Given kunci yang termasuk whitelist presentasional, When diperbarui, Then nilai tersimpan dan cache diperbarui.
- [x] **AC-2:** Given perubahan disimpan, Then audit merekam nilai lama/baru beserta dasar izin.
- [x] **AC-3:** Given pengguna tanpa permission (`pengaturan:update`), Then 403.
- [x] **AC-4:** Given pengguna mencoba mengubah enum/status/permission/aturan bisnis lewat tabel pengaturan, Then sistem menolak karena di luar cakupan whitelist.

---

## Implementation Tasks

### A. Persistence & Seeder
- [x] Buat `database/seeders/PengaturanSeeder.php` untuk 13 kunci default (identitas, aplikasi, tampilan, laporan).
- [x] Daftarkan `PengaturanSeeder` ke `DatabaseSeeder.php`.

### B. Service & Caching Layer
- [x] Buat `app/Services/PengaturanService.php` dengan `WHITELIST` konstan, layer cache, auto-casting, dan invalidasi cache.
- [x] Integrasikan pencatatan audit log via `AuditLogger` dengan `dasar_izin` untuk setiap kunci yang berubah.

### C. Backend Otorisasi & Controllers
- [x] Buat `app/Http/Requests/Pengaturan/UpdatePengaturanRequest.php` dengan penegakan izin `pengaturan:update` dan validasi whitelist ketat.
- [x] Buat `app/Http/Controllers/Pengaturan/IndexPengaturan.php`.
- [x] Buat `app/Http/Controllers/Pengaturan/UpdatePengaturan.php`.
- [x] Daftarkan rute di `routes/web.php` (`/pengaturan`).
- [x] Tambahkan capability `pengaturan` pada `app/Http/Middleware/HandleInertiaRequests.php`.

### D. Frontend / UX
- [x] Tambahkan menu "Pengaturan" pada sidebar navigasi `resources/js/Layouts/AuthenticatedLayout.tsx`.
- [x] Buat halaman `resources/js/Pages/Pengaturan/Index.tsx` dengan seksi/tab terstruktur, token design system, dan feedback interaktif.

### E. Automated Tests
- [x] Buat `tests/Feature/PengaturanTest.php` untuk menguji AC-1 s.d. AC-4.
