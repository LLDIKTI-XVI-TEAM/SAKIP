# Tahapan UAT dan Rilis — Siap Diuji, Evaluasi/Perbaikan, Diterima, dan Produksi

> **Traceability:** Issue [#187](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/187) · `ISS-15.03` · `US-15.03` · Q1 · Q2 · Q4 · Q46 §46.3 · Q80 §80.2 · Plan P.4 · PRD Pembuka, §32
> **Basis dokumen:** `development` `26a1da03` (2 November 2026)
> **Sifat dokumen:** artefak governance penerimaan. Bukan bukti UAT selesai, bukan penerimaan, dan bukan persetujuan produksi.

## Cara memakai dokumen ini

1. Status rilis hanya salah satu dari empat status Q2: `Siap diuji`, `Evaluasi/perbaikan`, `Diterima`, dan `Produksi` (§2). Status tidak dilompati: build yang belum pernah `Siap diuji` tidak dapat `Diterima`, dan build yang belum `Diterima` tidak dapat masuk `Produksi`.
2. Status dicatat per paket UAT (§4), yaitu satu build beserta daftar ISS yang diuji (K6). Hasil per ISS dan acceptance criteria dicatat pada §5.
3. Build diidentifikasi dengan SHA penuh commit `development` (K5). Penerimaan hanya berlaku untuk SHA yang diuji. Perubahan apa pun menghasilkan build baru yang masuk lagi sebagai paket `Siap diuji`.
4. Baris tidak dihapus atau ditimpa. Perbaikan atas temuan menghasilkan paket baru, dan kolom **Daftar perbaikan** paket lama merujuk paket penggantinya.
5. Pihak ditulis sebagai jabatan atau peran organisasi (mis. "PM SAKIP", "Tim Perencanaan"), tanpa nama pribadi, kontak, atau akun. Nama dan bukti persetujuan disimpan PM di luar repositori (K7).
6. Penerima UAT, penguji UAT, tanggal penerimaan, dan tanggal produksi diisi hanya setelah ditetapkan PM bersama Tim Perencanaan. Sebelum itu tulis `Belum ditetapkan` (AC-4).
7. Dokumen ini tidak memuat kredensial, hostname, domain, alamat IP, isi `.env`, nilai kinerja resmi, maupun tangkapan layar berisi data pribadi. Bukti uji yang memuat data tersebut disimpan PM di luar repositori; dokumen ini hanya memuat rujukannya.

**Semantik status:** `Implemented` ≠ `Verified` ≠ `Merged` ≠ `UAT Accepted` ≠ `Deployed` (`SAKIP - Data Awal 2026.md`). Centang AC, TEST, dan DoD pada User Issues, issue GitHub, maupun checklist lain hanya menyatakan `Implemented` atau `Verified`, bukan status pada dokumen ini.

### Keputusan PM

| Kode | Tanggal | Keputusan |
|---|---|---|
| K3 | 25 Oktober 2026 | Artefak tahapan UAT disimpan sebagai Markdown di `document/` tanpa data pribadi maupun kredensial, karena repositori publik. |
| K4 | 2 November 2026 | Dokumen tahapan berdiri sendiri, terpisah dari `SAKIP - Kesiapan Infrastruktur.md` (ISS-15.02). Kesiapan operasional dan tahapan penerimaan dinilai terpisah (Plan P.4). |
| K5 | 2 November 2026 | Identitas build adalah SHA penuh commit `development` per paket UAT. Tag dan GitHub Release tidak dibuat pada ISS-15.03. |
| K6 | 2 November 2026 | Status dicatat per paket UAT yang memuat daftar ISS, bukan per ISS. |
| K7 | 2 November 2026 | Pihak penetap dan penerima dicatat dengan jabatan atau peran organisasi. Nama dan bukti persetujuan disimpan PM di luar repositori. |

---

## 1. Kalender Target (AC-3)

Tanggal berikut adalah target pengembangan. Tidak satu pun otomatis menjadi tanggal penerimaan atau tanggal produksi (go-live).

| Target | Tanggal | Dasar | Sifat |
|---|---|---|---|
| Integrasi (batas Tahap 1) | Sebelum 9 November 2026 | Q2; Plan, tahap pengerjaan Q46 §46.3 | Target pengembangan. Bukan tanggal penerimaan maupun go-live. |
| Evaluasi/revisi | 16–30 November 2026 | Q2 | Target pelaksanaan UAT dan perbaikan. Status paket tetap mengikuti §2; berakhirnya periode ini tidak membuat paket `Diterima`. |
| Batas Tahap 2 | Sebelum pelaporan TW IV 2026 (awal Januari 2027) | Plan, tahap pengerjaan Q46 §46.3 | Target pengembangan. Bukan tanggal produksi. |
| Notifikasi WA/Email | Sebelum 9 November 2026 (Q4) | Q4, digantikan Q46 §46.3 | Tidak berlaku lagi. Notifikasi eksternal mengikuti ISS-12.x setelah Tahap 2 dengan gate Q80 §80.1. |
| Penerimaan UAT | Belum ditetapkan | Q2; Keputusan Penyelarasan, Tindak lanjut | Ditetapkan PM bersama Tim Perencanaan, lalu dicatat pada §6. |
| Produksi | Belum ditetapkan | Q2; Keputusan Penyelarasan, Tindak lanjut | Ditetapkan PM bersama Tim Perencanaan, lalu dicatat pada §6. |

---

## 2. Empat Status Rilis (AC-1, AC-2)

### 2.1 Hubungan dengan semantik status

| Tahap | Semantik (`SAKIP - Data Awal 2026.md`) | Arti |
|---|---|---|
| Sebelum UAT | `Implemented`, `Verified`, `Merged` | Perubahan selesai secara teknis: test dan CI lulus, PR direview, lalu masuk `development`. |
| `Siap diuji` | — | Build `Merged` terpasang di lingkungan UAT dan dicatat sebagai paket (§4). |
| `Evaluasi/perbaikan` | — | UAT menemukan acceptance criteria yang belum terpenuhi (§5). |
| `Diterima` | `UAT Accepted` | Tim Perencanaan mengesahkan hasil UAT atas build tersebut (§6). |
| `Produksi` | `Deployed` | Build yang diterima berjalan di lingkungan produksi (§6). |

Pemasangan build di lingkungan UAT bukan `Deployed`. Perubahan yang selesai secara teknis atau CI yang hijau tidak membuat suatu perubahan `Siap diuji`, apalagi `Diterima` (AC-1; Plan P.4).

### 2.2 Pihak yang menetapkan (Q1)

| Status | Menetapkan | Peran lain |
|---|---|---|
| `Siap diuji` | PM mencatat paket. | Tim teknis menyiapkan build, CI, dan pemasangan di lingkungan UAT. Pengelola infrastruktur menyediakan lingkungan UAT (ISS-15.02). |
| `Evaluasi/perbaikan` | PM mencatat hasil uji dari penguji UAT. | Penguji UAT ditetapkan PM bersama Tim Perencanaan (belum ditetapkan). Tim teknis memperbaiki lewat alur PR yang berlaku. |
| `Diterima` | Tim Perencanaan mengesahkan; PM mencatat. | — |
| `Produksi` | PM bersama Tim Perencanaan menetapkan tanggal; PM mencatat. | Pengelola infrastruktur memasang build (Q30). |

### 2.3 Syarat masuk dan keluar

#### `Siap diuji`

Syarat masuk:

1. Seluruh ISS dalam cakupan paket sudah `Merged` ke `development`.
2. CI exact HEAD lulus 9/9 untuk SHA paket. SHA yang hanya berisi perubahan dokumen menjalankan CI manual (`SAKIP - Kesiapan Infrastruktur.md` §1).
3. Lingkungan UAT tersedia (ISS-15.02): readiness aplikasi `SAKIP - Kesiapan Infrastruktur.md` §1 berstatus UAT `Siap berbukti` untuk SHA paket, dan layanan §2 yang belum siap tercatat sebagai blocker/risiko pada §5 dokumen tersebut.
4. Akun UAT lima role dan PIC operasional tersedia (ISS-15.01 AC-5).
5. Cakupan ISS beserta PR-nya dan acceptance criteria yang diuji tercatat pada §4.

Syarat keluar:

- ke `Evaluasi/perbaikan` bila ada hasil `Tidak lulus` pada §5;
- ke `Diterima` bila seluruh acceptance criteria dalam cakupan lulus dan syarat masuk `Diterima` terpenuhi.

#### `Evaluasi/perbaikan`

Syarat masuk: hasil uji `Tidak lulus` tercatat pada §5 beserta acceptance criteria, temuan, dan tindak lanjutnya (AC-2).

Syarat keluar: perbaikan `Merged` ke `development`, lalu build baru dicatat sebagai paket baru `Siap diuji` dan diuji ulang. Paket lama tidak berpindah ke `Diterima`; kolom **Daftar perbaikan**-nya merujuk paket pengganti.

Temuan yang bukan kegagalan acceptance criteria dicatat sebagai `Usulan di luar AC` dan tidak menahan penerimaan. Bila PM bersama Tim Perencanaan menjadikannya syarat penerimaan, ketentuan itu dicatat sebagai keputusan baru di `SAKIP - Keputusan Penyelarasan.md` dan diturunkan ke issue terkait sebelum diuji.

#### `Diterima`

Syarat masuk:

1. Seluruh acceptance criteria dalam cakupan paket lulus pada SHA paket, dan tidak ada hasil `Tidak lulus` yang masih terbuka.
2. Tim Perencanaan mengesahkan hasil UAT (Q1).
3. Keputusan tercatat pada §6: pihak, tanggal, build, cakupan, bentuk persetujuan, dan catatan keputusan (AC-5).
4. Untuk penerimaan Fase Awal (UAT final), gate Q32 terpenuhi (Plan P.5).

Syarat keluar: ke `Produksi` bila syarat masuk `Produksi` terpenuhi. Build lain, termasuk perbaikan setelah penerimaan, masuk lagi sebagai paket `Siap diuji`.

#### `Produksi`

Syarat masuk:

1. Build yang dipasang adalah SHA yang `Diterima`.
2. Untuk rilis Fase Awal, cakupan penerimaan memuat seluruh kriteria penerimaan PRD §32.
3. Readiness `SAKIP - Kesiapan Infrastruktur.md` §1–§2 berstatus produksi `Siap berbukti` atau `Belum diaktifkan`.
4. Uji pemulihan pada lingkungan produksi tercatat pada `SAKIP - Kesiapan Infrastruktur.md` §4 (Q80 §80.2).
5. Notifikasi eksternal tetap `Belum diaktifkan` sampai gate Q80 §80.1 terpenuhi.
6. Nilai `Asumsi kerja` pada `SAKIP - Data Awal 2026.md` tidak dipakai di produksi sebelum dikonfirmasi Perencanaan (ISS-15.01).
7. Tanggal produksi ditetapkan PM bersama Tim Perencanaan dan tercatat pada §6.

Syarat keluar: tidak ada. Rilis berikutnya memakai paket baru.

---

## 3. Acuan Kriteria Penerimaan

Dokumen ini tidak menyalin kriteria penerimaan. Acuannya:

| Lingkup | Acuan |
|---|---|
| Fase Awal (MVP) | PRD §32 Kriteria Penerimaan (Fase Awal) |
| Per ISS dalam paket | Acceptance criteria dan Definition of Done pada `SAKIP - User Issues.md` dan issue GitHub terkait |
| Gate sebelum UAT final | Plan P.5 (Q32) |
| Keluaran Excel | `SAKIP - Acuan Keluaran Excel.md` (ISS-10.04) |
| Data awal 2026 | `SAKIP - Data Awal 2026.md` (ISS-15.01) |
| Kesiapan operasional | `SAKIP - Kesiapan Infrastruktur.md` (ISS-15.02) |

---

## 4. Paket UAT

Kode paket berurutan: `Paket-01`, `Paket-02`, dan seterusnya. Kolom **Cakupan** mencatat setiap ISS beserta nomor PR yang memasukkannya ke `development` (mis. `ISS-xx.yy · #N`). Kolom **Status** hanya berisi salah satu dari empat status §2.

| Paket | Build (SHA penuh `development`) | Cakupan (ISS · PR) | Siap diuji (tanggal, dicatat oleh) | Status | Hasil UAT (§5) | Daftar perbaikan | Diterima (§6) | Produksi (§6) |
|---|---|---|---|---|---|---|---|---|
| — | — | — | Belum ada paket | — | — | — | Belum ditetapkan | Belum ditetapkan |

Paket pertama baru dapat dicatat setelah lingkungan UAT tersedia (ISS-15.02) serta akun UAT lima role dan PIC operasional tersedia (ISS-15.01 AC-5).

---

## 5. Hasil Uji dan Daftar Perbaikan (AC-2)

Kolom **Hasil** hanya berisi salah satu dari:

- `Lulus` — acceptance criteria terpenuhi pada SHA paket;
- `Tidak lulus` — acceptance criteria belum terpenuhi; paket masuk `Evaluasi/perbaikan`;
- `Usulan di luar AC` — bukan kegagalan acceptance criteria (§2.3).

| Paket | ISS · AC | Tanggal uji (WITA) | Penguji (jabatan) | Hasil | Temuan | Tindak lanjut (issue/PR) | Uji ulang (paket, hasil) |
|---|---|---|---|---|---|---|---|
| — | — | — | — | Belum ada hasil uji | — | — | — |

---

## 6. Catatan Keputusan Penerimaan dan Produksi (AC-4, AC-5)

Kolom **Jenis** berisi `Penerimaan UAT` atau `Produksi`. Kolom **Bentuk persetujuan** mencatat bentuknya (mis. berita acara, surat, atau notulen rapat); dokumennya disimpan PM di luar repositori (K7).

| No | Jenis | Paket · build (SHA penuh) | Cakupan | Pihak (jabatan) | Tanggal (WITA) | Bentuk persetujuan | Catatan keputusan |
|---|---|---|---|---|---|---|---|
| — | — | — | — | Belum ditetapkan | Belum ditetapkan | — | Belum ada penerimaan maupun produksi. Penerima UAT, tanggal penerimaan, dan tanggal produksi belum ditetapkan PM bersama Tim Perencanaan (AC-4). |

---

## 7. Riwayat Dokumen

| Tanggal | Oleh | Perubahan |
|---|---|---|
| 2 November 2026 | PM SAKIP | Dokumen disusun (ISS-15.03, keputusan K3–K7). Belum ada paket UAT. |
