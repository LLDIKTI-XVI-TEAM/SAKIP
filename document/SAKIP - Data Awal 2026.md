# Data Awal 2026 — Checklist Verifikasi Sebelum Penggunaan Pertama

> **Traceability:** Issue [#138](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/138) · `ISS-15.01` · `US-15.01`
> **Basis dokumen:** `development` `322b59a` (termasuk Q70–Q74, 23 Oktober 2026)
> **Sifat dokumen:** artefak penerimaan/checklist. Bukan data produksi, bukan bukti UAT selesai, dan bukan pengganti keputusan Perencanaan.

## Cara memakai dokumen ini

1. Setiap baris wajib mengisi **Sumber** dan **Status konfirmasi**.
2. Status konfirmasi hanya boleh salah satu dari:
   - `Dikonfirmasi dokumen` — nilai/kode berasal dari dokumen resmi 2026 atau keputusan yang sudah ditetapkan.
   - `Asumsi kerja` — berasal dari tabel "Asumsi kerja data awal 2026" pada `SAKIP - Keputusan Penyelarasan.md`; **tidak dipakai di produksi** sebelum dikonfirmasi Perencanaan.
   - `Menunggu Perencanaan` — belum ada nilai/dokumen; jangan diisi dengan angka karangan.
3. Pengisian data awal hanya melalui **UI/Action aplikasi** oleh akun Perencanaan, bukan SQL manual atau seeder langsung ke tabel domain.
4. Kolom **Diverifikasi oleh/tanggal** diisi setelah Perencanaan/PM memeriksa baris tersebut (tanggal bisnis WITA).
5. Dokumen ini tidak memuat kredensial, token, atau password akun.

**Semantik status:** `Implemented` ≠ `Verified` ≠ `Merged` ≠ `UAT Accepted` ≠ `Deployed`. Centang pada dokumen ini hanya berarti bukti implementasi/verifikasi, bukan penerimaan UAT atau go-live.

---

## 1. Periode dan Master

### 1.1 Periode

| Data | Nilai | Sumber | Status konfirmasi | Jalur isi | Diverifikasi oleh/tanggal |
|---|---|---|---|---|---|
| Master periode | Triwulan I, II, III, IV (global, tanpa tahun) | `PeriodeSeeder`; Master Periode | Dikonfirmasi dokumen | Halaman Master Periode → `SavePeriode` | — |
| Nilai akhir | Triwulan IV (`is_nilai_akhir = true`) | PRD §12.1 | Dikonfirmasi dokumen | Halaman Master Periode → `SavePeriode` | — |
| Periode lampau 2026 | TW I–III (dihitung dari `pengisian_selesai` < tanggal aktivasi) | Q46 §46.1 butir 2 | Dikonfirmasi dokumen | Jadwal tahunan (aktivasi) | — |
| Periode normal 2026 | TW IV (workflow normal PIC operasional) | Q46 §46.1 butir 2 | Dikonfirmasi dokumen | Jadwal tahunan (aktivasi) | — |
| Tanggal periode (hari/bulan) | — belum ada | Q70 §70.1; status implementasi ISS-03.07: belum | Menunggu Perencanaan | Belum tersedia (butuh ISS-03.07) | — |

> **Gap Q70:** `periode` belum menyimpan tanggal mulai/selesai, dan `jadwal_periode` masih memuat `reviu_mulai`/`reviu_selesai`. Jangan mengisi tanggal periode di dokumen ini; implementasinya milik ISS-03.07.

### 1.2 Jadwal tahunan pertama

| Data | Nilai | Sumber | Status konfirmasi | Jalur isi | Diverifikasi oleh/tanggal |
|---|---|---|---|---|---|
| Tahun penggunaan pertama | 2026 | Q32.7; Q46 | Dikonfirmasi dokumen | — | — |
| Jendela pengisian TW I–IV | Menunggu konfigurasi | Jadwal tahunan | Menunggu Perencanaan | Halaman Jadwal (draft) → simpan/aktivasi | — |
| Aktivasi jadwal pertama | Belum dilakukan | — | Menunggu Perencanaan | `ActivateJadwal` (gate G1–G4) | — |

---

## 2. Indikator, PK, Target, dan Histori (AC-6)

### 2.1 Kode sasaran dan indikator

Kode mengikuti PK, Rencana Aksi, dan laporan triwulan (Q50 §50.4): **S1–S4** dan **IKU 1.1–IKU 4.1**.

| Kode PK | Indikator | Satuan | Sumber | Status konfirmasi | Jalur isi | Diverifikasi oleh/tanggal |
|---|---|---|---|---|---|---|
| S1–S4 | Kode sasaran strategis | — | PK 2026; Q50 §50.4 | Dikonfirmasi dokumen | Halaman Renstra → Sasaran & Indikator | — |
| S1–S4 | Nama rinci sasaran | — | PK 2026 | Menunggu Perencanaan | Halaman Renstra → Sasaran & Indikator | — |
| IKU 1.1 | Keunggulan layanan LLDIKTI | % | PK 2026; Q47 pengantar | Dikonfirmasi dokumen | Sasaran & Indikator; target PK | — |
| IKU 1.2 | Arsitektur Perguruan Tinggi Swasta (PTS) | % | PK 2026; Q47 pengantar | Dikonfirmasi dokumen | Sasaran & Indikator; target PK | — |
| IKU 1.3 | Tata kelola LLDIKTI yang berkualitas dan berintegritas | Nilai | PK 2026; Q47 pengantar | Dikonfirmasi dokumen | Sasaran & Indikator; target PK | — |
| IKU 2.1 | Fasilitasi peningkatan mutu pendidikan pada PTS oleh LLDIKTI | % | PK 2026; Q47 pengantar | Dikonfirmasi dokumen | Sasaran & Indikator; target PK | — |
| IKU 2.2 | Pencegahan dan penanganan kekerasan, narkoba, dan korupsi | % | PK 2026; Q47 pengantar | Dikonfirmasi dokumen | Sasaran & Indikator; target PK | — |
| IKU 3.1 | Fasilitasi pengembangan kemahasiswaan dan prestasi oleh LLDIKTI | % | PK 2026; Q47 pengantar | Dikonfirmasi dokumen | Sasaran & Indikator; target PK | — |
| IKU 3.2 | Jumlah dosen PTS yang meningkat jabatan fungsionalnya | Orang | PK 2026; Q47 pengantar | Dikonfirmasi dokumen | Sasaran & Indikator; target PK | — |
| IKU 3.3 | Fasilitasi peningkatan kinerja penelitian, publikasi, pengabdian kepada masyarakat, dan kemitraan PTS | % | PK 2026; Q47 pengantar | Dikonfirmasi dokumen | Sasaran & Indikator; target PK | — |
| S4 / IKU 4.1 | Nilai Kinerja Anggaran atas Pelaksanaan RKA-K/L | Nilai | PK 2026; Q50 §50.5 | Dikonfirmasi dokumen | Sasaran & Indikator; target PK | — |

> **Catatan S4/IKU 4.1:** hanya ada di PK. Dicatat di bawah Renstra 2025–2029 **tanpa target Renstra** (Q50 §50.5). Jangan mengisi target Renstra untuk baris ini.

### 2.2 Target PK 2026

| Data | Nilai | Sumber | Status konfirmasi | Jalur isi | Diverifikasi oleh/tanggal |
|---|---|---|---|---|---|
| Presisi target PK | Nilai penuh yang dipakai pengukuran (bukan nilai tercetak yang dibulatkan) | Q50 §50.2 | Dikonfirmasi dokumen | PK → target PK (`SaveTargetTahunan`) | — |
| Nilai target per indikator | — belum diisi; contoh nilai penuh yang tercatat: 94,05; 80,95; 92,86 | Q50 §50.2 | Menunggu Perencanaan (pemetaan per indikator & konfirmasi nilai resmi) | PK → target PK | — |
| Baseline 2026 | — belum ada | Tindak lanjut "Baseline 2026" (realisasi 2025 dari LKj 2025) | Menunggu Perencanaan | PK → editor target PK (Q73) | — |
| Penanda dinilai tahunan | Hanya **IKU 1.3** | Q47 §47.1 butir 4 | Dikonfirmasi dokumen | Sasaran & Indikator → penanda dinilai tahunan | — |

> **Gap Q72/Q73:** penolakan target PK yang menyimpang dari target Renstra dan alasan perubahan target PK (Q72), serta baseline otomatis (Q73), belum diimplementasikan. Nilai target PK 2026 bisa ditolak setelah ISS-02.14/ISS-02.15 aktif.

### 2.3 Target periode dan Rencana Aksi

| Data | Nilai | Sumber | Status konfirmasi | Jalur isi | Diverifikasi oleh/tanggal |
|---|---|---|---|---|---|
| Target TW I–III 2026 | Kosong / `tidak_ditargetkan` (bukan `0`) | Q47 §47.2 butir 4 | Dikonfirmasi dokumen | Rencana Aksi → target periode (`SimpanTargetPeriode`) | — |
| Target TW IV 2026 | Wajib (periode nilai akhir), direkonsiliasi dengan target PK | Q47 §47.2 butir 1; ADR-0008 | Dikonfirmasi dokumen | Rencana Aksi → target periode | — |
| Target TW IV IKU 1.2 | Tertulis 92,9 pada RA 2026; berbeda dengan target PK 94 | Tindak lanjut "Target TW IV IKU 1.2" | Menunggu Perencanaan | Rencana Aksi → target periode | — |
| Rencana Aksi 2026 | Status `disahkan` sebelum pengajuan TW IV | Q32.8 (diperbarui Q46) | Menunggu Perencanaan (isi) | Rencana Aksi → ajukan/sahkan | — |
| Kegiatan per triwulan | Boleh tanpa anggaran; tercatat belum diisi | Q46 §46.1 butir 3 | Dikonfirmasi dokumen | Rencana Aksi → kegiatan (`TambahKegiatanRencanaAksi`) | — |
| Kegiatan TW IV | Ditambahkan lewat klaim tahap pengukuran atau buka-kembali RA oleh Perencanaan | Q46 §46.1 butir 3 | Dikonfirmasi dokumen | Rencana Aksi → kegiatan | — |

### 2.4 Realisasi, narasi, dan rekomendasi TW I–II 2026

| Data | Nilai | Sumber | Status konfirmasi | Jalur isi | Diverifikasi oleh/tanggal |
|---|---|---|---|---|---|
| Realisasi TW I–II | — belum diisi | Laporan Kinerja Triwulan I dan II 2026 | Menunggu Perencanaan (data per indikator) | Pengukuran → isi/ajukan (`SubmitPengukuran`) | — |
| Narasi TW I–II | — belum diisi | Laporan Kinerja TW I–II 2026 | Menunggu Perencanaan | Pengukuran → narasi | — |
| Rekomendasi TW I–II | — belum diisi; langsung `disetujui` + penanda cadangan + `rujukan_sumber` | Q51 §51.1 butir 5 | Menunggu Perencanaan | Rekomendasi → Setujui (lihat §7) | — |
| Indikator IKU 1.3 | Belum diukur pada TW I–II; dinilai tahunan | Q47 §47.1 butir 4 | Dikonfirmasi dokumen | Pengukuran periode nilai akhir | — |
| IKU 4.1 | TW I 99,48; TW II 99,71 | Laporan Kinerja TW I–II 2026; Q47 §47.1 butir 4 | Dikonfirmasi dokumen | Pengukuran | — |

---

## 3. Asumsi Kerja — Tidak untuk Produksi (AC-7)

Sumber: `SAKIP - Keputusan Penyelarasan.md` bagian **"Asumsi kerja data awal 2026"**. Baris di bawah **hanya** dipakai untuk data uji/rancangan dan **tidak dipakai di produksi** sebelum dikonfirmasi Perencanaan (Q46 §46.1 butir 6).

| Data | Asumsi kerja | Sumber | Status konfirmasi |
|---|---|---|---|
| Formula IKU 1.2, 2.1, 2.2, 3.1 | Jumlah PTS yang memenuhi ÷ jumlah PTS wilayah × 100%; penyebut diisi per periode, tidak di-hardcode | Konsistensi target/realisasi dengan penyebut 84 | Asumsi kerja |
| IKU 1.3 | (skor SAKIP + skor ZI-WBK) / 2; dinilai tahunan | Q32.10; Q47 §47.1 | Asumsi kerja |
| IKU 1.1 | Persentase kepuasan layanan dari survei; diisi manual | Laporan TW I–II | Asumsi kerja |
| IKU 3.2 | Jumlah dosen PTS yang naik jabatan fungsional, kumulatif dalam tahun | PK 2026 (orang) | Asumsi kerja |
| IKU 4.1 | Nilai Kinerja Anggaran dari sistem kementerian; diisi manual tiap triwulan | Laporan TW I–II (99,48; 99,71) | Asumsi kerja |
| IKU 3.3 | Komponen tidak disetel sampai definisinya jelas; target PK 2026 59,5 | Q32.9 | Asumsi kerja |
| Unit dan PJ | IKU 1.1, 1.3, 4.1 → Bagian Umum; IKU 1.2 → Tim Kerja Penguatan Kualitas Kelembagaan dan Kerja Sama PT; IKU 2.1 dan 3.1 → Tim Kerja Penguatan Mutu Pembelajaran dan Kemahasiswaan; IKU 3.2 → Tim Kerja Penguatan Kualitas Sumber Daya; IKU 3.3 → Tim Kerja Penguatan Mutu Penelitian dan PkM; IKU 2.2 → sementara Tim Kerja Penguatan Mutu Pembelajaran dan Kemahasiswaan | Pembagian tim kerja pada Renstra 2025–2029 | Asumsi kerja |
| Target TW I–III 2026 | Kosong (tidak ditargetkan) | Q47 §47.2 butir 4 | Asumsi kerja |
| PK 2026 perubahan | Belum dipastikan apakah PK direvisi dan apakah target ikut berubah; bila hanya anggaran, cukup lampiran tambahan | Tindak lanjut "Rencana PK perubahan 2026"; Q50 §50.7 | Menunggu Perencanaan |

**Larangan:** asumsi di atas tidak boleh dipakai sebagai nilai produksi, tidak boleh disetel sebagai komponen/formula produksi, dan tidak boleh dianggap "Dikonfirmasi dokumen".

---

## 4. Matriks Verifikasi Gate Aktivasi (AC-2)

Gate aplikasi saat ini: `app/Services/Jadwal/JadwalActivationReadiness.php`. Aktivasi **ditolak** bila ada blocker atau ada gate berstatus `gagal`.

### 4.1 Gate

| Gate | Kondisi lolos | Pesan gagal | Bukti test |
|---|---|---|---|
| G1 | PK tahun jadwal tersedia | `Perjanjian Kinerja tahun ini belum dibuat.` | `JadwalActivationReadinessTest::test_pk_year_and_attachment_gates` |
| G2 | Semua indikator eligible punya target tahunan non-null (nilai `0` sah; baris absen/null = belum terisi) | `Belum ada indikator yang berlaku untuk dinilai.` / `{n} indikator belum memiliki target tahunan.` | `test_target_gate_requires_nonnull_value_for_every_eligible_indicator` |
| G3 | Tahun jadwal dalam rentang Renstra | `Tahun jadwal berada di luar rentang Renstra.` | `test_pk_year_and_attachment_gates` |
| G4 | PK memiliki lampiran | `Lampiran belum dapat dinilai karena Perjanjian Kinerja belum ada.` / `Perjanjian Kinerja belum memiliki lampiran.` | `test_pk_year_and_attachment_gates` |

### 4.2 Blocker

| Kode | Pesan |
|---|---|
| `sudah_aktif` | `Jadwal sudah aktif.` |
| `status` | `Hanya jadwal draft yang belum pernah diaktifkan dapat diaktifkan.` |
| `renstra_tidak_aktif` | `Renstra harus berstatus aktif sebelum jadwal diaktifkan.` |
| `kalender_tidak_valid` | `Kalender belum lengkap atau belum valid. Perbaiki dan simpan kalender terlebih dahulu.` |
| `periode_nonaktif` | `Seluruh periode yang dipilih harus aktif.` |
| `indikator_kosong` | `Belum ada indikator aktif yang berlaku pada tahun jadwal.` |
| `penutupan_lewat` | `Tanggal penutupan sudah lewat. Perbaiki tanggal penutupan sebelum aktivasi.` |
| `data_tidak_konsisten` | `{n} indikator memiliki data sumber yang tidak dapat dibekukan (nama terlalu panjang atau komponen tidak sesuai tipe perhitungan). Perbaiki pada modul indikator.` |

### 4.3 Di luar gate aplikasi — verifikasi manual

Gate aplikasi **tidak** memeriksa: kelengkapan unit/PJ, Rencana Aksi per triwulan, kegiatan, realisasi/narasi TW I–II, rekomendasi, dan tanggal periode. Bagian ini diverifikasi manual di checklist §1–§3 dan §6.

### 4.4 Bukti uji aktivasi

| Skenario | Hasil yang diharapkan | Bukti | Tanggal/oleh |
|---|---|---|---|
| Data belum lengkap (PK belum ada) | Aktivasi ditolak, pesan G1 | — | — |
| Target tahunan belum lengkap | Aktivasi ditolak, pesan G2 + jumlah | — | — |
| Tahun di luar rentang Renstra | Aktivasi ditolak, pesan G3 | — | — |
| Lampiran PK belum ada | Aktivasi ditolak, pesan G4 | — | — |
| Data lengkap | Aktivasi diterima; snapshot terbentuk | — | — |

---

## 5. Jalur Pengisian Teraudit (AC-4)

Semua pengisian data awal memakai Action aplikasi dan meninggalkan jejak `audit_log`. **Tidak ada** query manual tanpa jejak dan **tidak ada** mode backfill khusus (Q46 §46.1 butir 2).

| Data | Halaman/Route | Action | Event audit (`tindakan`) |
|---|---|---|---|
| Master periode | `/periode` | `SavePeriode` | `periode.tambah` / `periode.ubah` |
| Renstra | `/renstra` | `CreateRenstraAction`, `ChangeRenstraStatus` | `renstra.buat`, `renstra.activate`/`deactivate`/`archive` |
| Target Renstra (Renstra `draft`) | `/perencanaan/sasaran-indikator` → target Renstra | `SimpanTargetRenstra` | `target_renstra.simpan` |
| Target Renstra (Renstra `aktif`, termasuk pengisian pertama; Q71) | `/perencanaan/sasaran-indikator` → Target Renstra → Mode revisi | `MulaiRevisiTargetRenstra`, `SimpanDrafRevisiTargetRenstra`, `SimpanRevisiTargetRenstra` | `target_renstra.simpan` per sel dengan justifikasi pada `alasan` |
| Perjanjian Kinerja | `/perjanjian-kinerja` | `CreatePerjanjianKinerja`, `UpdatePerjanjianKinerja` | `renstra_pk.buat`, `renstra_pk.ubah` |
| Lampiran PK | Modal PK | `PerjanjianKinerjaAttachmentService::simpanLampiran` | `berkas.unggah` / `berkas.hapus` |
| Target PK / target tahunan | `/perjanjian-kinerja/{pk}/target/{indikator}` | `SaveTargetTahunan` | `target_tahunan.simpan` |
| Rencana Aksi (draf) | `/rencana-aksi/ensure-draft` | `EnsureDraftRencanaAksi` | `rencana_aksi.buat` |
| Target periode RA | `/rencana-aksi/{id}/target` | `SimpanTargetPeriode` | `rencana_aksi.ubah` |
| Kegiatan | `/rencana-aksi/{id}/kegiatan` | `TambahKegiatanRencanaAksi` | `kegiatan.buat` + `klaim_kegiatan.tambah` |
| Pengukuran (realisasi/narasi/ajukan) | `/pengukuran/{id}` | `SubmitPengukuran` | `pengukuran.ajukan` (draf: `pengukuran.draft`) |
| Verifikasi/pengesahan pengukuran | `/verifikasi/{id}` | `VerifyPengukuran`, `ApprovePengukuran` | `pengukuran.verifikasi`, `pengukuran.sahkan` |
| Rekomendasi | `/rekomendasi` | `TetapkanRekomendasi` | `rekomendasi_pimpinan.tetapkan` / `.setujui` / `.ubah_draf` |
| Unit | `/unit` | `CreateUnitAction` | `unit.tambah` |
| Penanggung Jawab | `/penanggung-jawab`, `/perencanaan/indikator/{id}/penanggung-jawab` | `AssignPenanggungJawab`, `ChangePenanggungJawab` (`AppendAssignment`) | `penanggung_jawab.tetapkan` / `penanggung_jawab.ganti` |

> Jalur periode lampau 2026 dihitung dari `pengisian_selesai` < tanggal aktivasi (`PeriodeTanpaTarget`), bukan flag backfill. Bukti test: `tests/Feature/PeriodeLampau/RencanaAksiPeriodeLampauMatrixTest.php`.

---

## 6. Akun UAT (AC-5)

**Tidak ada role `pic`.** PIC operasional adalah user ber-role **`pegawai`** yang diberi **grant unit** (dan dapat ditetapkan sebagai PJ) — bukan role tersendiri (Q32; Q31).

| Peran/aktor | Kode role | Kebutuhan akun | Status | Blocker | Diverifikasi oleh/tanggal |
|---|---|---|---|---|---|
| Superadmin | `superadmin` | 1 akun | Menunggu Keycloak | Realm Keycloak sesuai konfigurasi deployment | — |
| Admin | `admin` | 1 akun | Menunggu Keycloak | Realm Keycloak sesuai konfigurasi deployment | — |
| Perencanaan | `perencanaan` | 1 akun | Menunggu Keycloak | Realm Keycloak sesuai konfigurasi deployment | — |
| Pimpinan | `pimpinan` | 1 akun | Menunggu Keycloak | Realm Keycloak sesuai konfigurasi deployment | — |
| Pegawai | `pegawai` | 1 akun | Menunggu Keycloak | Realm Keycloak sesuai konfigurasi deployment | — |
| PIC operasional | `pegawai` + grant unit (+ PJ) | 1 akun pegawai dengan grant unit | Menunggu Keycloak + konfigurasi grant | Preset permission & eligibility assignment; grant harus disiapkan | — |

**Aturan:** dokumen ini hanya mencatat kode role, status, dan blocker. Password/token **tidak boleh** ditulis di repo, dokumen, issue, atau PR. Akun pertama di lingkungan baru dibuat lewat `php artisan sakip:bootstrap-superadmin` (bukan kredensial bersama di repo).

---

## 7. Rekomendasi TW I–II 2026 (AC-8)

Jalur sudah tersedia tanpa perubahan kode.

| Langkah | Aksi | Bukti |
|---|---|---|
| 1 | Buka `/rekomendasi`, pilih jadwal dan periode (TW I/TW II), klik **Tampilkan** | `Rekomendasi/Index.tsx` (`FilterPeriode`) |
| 2 | Pilih indikator → **Tulis rekomendasi** / **Ubah rekomendasi** | `Rekomendasi/Index.tsx` |
| 3 | Isi **Isi rekomendasi** (wajib, maks 10.000) dan **Rujukan sumber** (opsional, maks 1.000) — isi rujukan dengan identitas laporan triwulan sumber | `TetapkanRekomendasiRequest` |
| 4 | Klik **Setujui** (status `disetujui`) | `TetapkanRekomendasi` |
| 5 | Pastikan lencana **"Persetujuan cadangan · perencanaan"** muncul (bukan "Disetujui Pimpinan") | `RekomendasiPimpinan::isCadangan()`; UI `Index.tsx` |
| 6 | Pastikan `audit_log` mencatat `rekomendasi_pimpinan.setujui` + `persetujuan_cadangan` | `TetapkanRekomendasi` |

Izin yang dipakai: `rekomendasi:tetapkan` (dimiliki preset Perencanaan dan Pimpinan). Persetujuan oleh Perencanaan sah sebagai jalur cadangan dan **wajib** diberi penanda (Q51 §51.2).

---

## 8. Catatan Konfirmasi dan Gap

| Item | Status | Dampak ke data awal | Tindak lanjut |
|---|---|---|---|
| Q70 §70.1 tanggal periode | Belum (ISS-03.07) | Tanggal periode tidak dapat diisi | Tunggu ISS-03.07 |
| Q70 §70.2 hapus jendela reviu | Belum (ISS-03.07) | Form jadwal/revisi jendela PIC masih memakai jendela reviu | Tunggu ISS-03.07 |
| Q71 revisi target Renstra | Sudah (ISS-02.13) | Renstra 2025–2029 berstatus `aktif`, sehingga target Renstra data awal diisi lewat Mode revisi sebagai revisi ke-1 dengan justifikasi yang merujuk dokumen Renstra (Q71 §71.5 butir 1) | Isi lewat Mode revisi setelah ISS-02.13 merge |
| Q72 target PK menyimpang ditolak | Belum (ISS-02.14) | Target PK 2026 belum tunduk pada penolakan deviasi | Tunggu ISS-02.14 |
| Q73 baseline otomatis | Belum (ISS-02.15) | Baseline 2026 diisi manual/menunggu LKj 2025 | Tunggu ISS-02.15 |
| Q74 kegiatan dari RA + biaya terpakai | Belum (ISS-06.09, ISS-06.03) | Alur kegiatan masih memakai halaman Kegiatan | Tunggu ISS-06.09; ISS-06.03 (form realisasi) |
| Konfirmasi tertulis Q38–Q52, Q70–Q74 | Menunggu | Nilai resmi belum final | Catat pada tindak lanjut |
| Baseline 2026 dari LKj 2025 | Menunggu dokumen | Baseline kosong | Minta dokumen |
| Nilai target PK resmi | Menunggu konfirmasi | Nilai penuh vs tercetak | Konfirmasi Perencanaan |
| Formula/komponen indikator | Menunggu konfirmasi | Komponen produksi belum disetel | Konfirmasi Perencanaan |
| Unit/PJ final | Menunggu konfirmasi | Baris unit/PJ masih asumsi kerja | Konfirmasi Perencanaan |
| Akun UAT 5 role + PIC operasional | Menunggu Keycloak | UAT belum bisa dijalankan penuh | ISS-15.02 |

---

## 9. Riwayat Verifikasi

| Tanggal | Oleh | Perubahan/kesimpulan |
|---|---|---|
| — | — | Belum ada verifikasi Perencanaan |
