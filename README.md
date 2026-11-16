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
- **Database**: PostgreSQL 18
- **Styling**: Tailwind CSS v4 (Token `@theme`)
- **Containerization**: Podman 5.8 & Podman-compose 1.6
- **Testing**: Pest / PHPUnit (Backend) + Vitest (Frontend)

---

## 🛠️ Menjalankan Aplikasi Secara Lokal

1. **Jalankan Backend**:
   ```powershell
   php artisan serve
   ```
2. **Jalankan Frontend (HMR Dev Server)**:
   ```powershell
   npm run dev
   ```
3. Akses melalui browser di `http://localhost:8000`.

---

## 🧪 Pengujian Otomatis (DoD)

```powershell
# Backend Feature Tests
php artisan test

# Frontend Unit Tests
npm run test
```
