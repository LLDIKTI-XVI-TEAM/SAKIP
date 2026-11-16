# Acuan Keluaran Excel — Checklist Penerimaan Contoh dan UAT

> **Traceability:** Issue [#178](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/178) · `ISS-10.04` · `US-10.04` · acuan format `ISS-10.03` ([#177](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/177)) dan `ISS-10.05` ([#179](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/179))
> **Basis dokumen:** `development` `3fcab85` (termasuk PR #203, 30 Oktober 2026)
> **Sifat dokumen:** acuan format dan checklist UAT. Bukan contoh resmi, bukan bukti UAT selesai, dan bukan pengganti persetujuan Perencanaan.

## Cara memakai dokumen ini

1. Contoh Excel resmi dan isi dokumen resmi **tidak** disimpan di repo karena repo publik (keputusan K3). Contoh disimpan PM; dokumen ini hanya mencatat identitas contoh dan letak informasi (sheet/kolom), **tanpa nilai**.
2. Informasi wajib diambil dari dokumen keputusan pada kolom **Dasar**, bukan dari contoh. Susunan sel contoh yang ambigu dicatat pada **Catatan kesetaraan**, bukan disalin (Q29; AC-2).
3. Status setiap keluaran (§1) hanya salah satu dari:
   - `Draf — belum disahkan Perencanaan`;
   - `Disahkan Perencanaan (<tanggal>)`.

   Selama status masih Draf, format keluaran tersebut belum final dan tidak boleh diklaim diterima (AC-3).
4. Status setiap baris informasi wajib (§2–§4) hanya salah satu dari:
   - `Menunggu contoh` — contoh belum diterima atau belum dibaca;
   - `Ada pada contoh` / `Tidak ada pada contoh` — hasil pencocokan dengan contoh;
   - `UAT: Setara` / `UAT: Setara dengan catatan` / `UAT: Tidak setara` — hasil pembandingan keluaran aplikasi (bagian 2).
5. Pengesah dicatat dengan jabatan, tanggal, dan bentuk persetujuan. Nama dan bukti persetujuan disimpan PM di luar repo (keputusan U2). Dokumen ini tidak memuat nilai kinerja resmi, nama pegawai, tanda tangan, maupun kredensial.
6. Unsur contoh tanpa dasar dokumen dicatat pada §5 sebagai "Tidak wajib — di luar SAKIP" (keputusan U4).
7. Perubahan format setelah status Disahkan dicatat pada §7; perubahan yang mengubah ketentuan ditetapkan sebagai keputusan baru di Keputusan Penyelarasan (AC-4).

**Semantik status:** `Implemented` ≠ `Verified` ≠ `Merged` ≠ `UAT Accepted` ≠ `Deployed`. Pengesahan contoh pada dokumen ini bukan penerimaan UAT keseluruhan maupun go-live (ISS-15.03).

### Keputusan PM

| Kode | Tanggal | Keputusan |
|---|---|---|
| K3 | 25 Oktober 2026 | Acuan disimpan sebagai checklist Markdown di `document/` tanpa data resmi, nilai kinerja resmi, nama pegawai, maupun kredensial. Contoh Excel resmi disimpan PM/Perencanaan di luar repo. |
| U1 | 29 Oktober 2026 | Contoh (a): Perencanaan ditanya lebih dulu apakah ada format kerja rekap. Bila ada, format itu menjadi contoh dan ekspor ISS-10.03 dibandingkan dengannya. Bila tidak ada, keluaran ekspor ISS-10.03 dengan data uji diajukan sebagai calon contoh. |
| U2 | 29 Oktober 2026 | Pengesah dicatat dengan jabatan, tanggal, dan bentuk persetujuan; nama dan bukti persetujuan disimpan PM di luar repo. |
| U3 | 29 Oktober 2026 | Dikerjakan bertahap: dokumen ini sebagai draf, lalu pencatatan pengesahan (beserta UAT (a) bila siap), lalu hasil UAT (b) dan (c). |
| U4 | 29 Oktober 2026 | Unsur contoh tanpa dasar dokumen (kop, blok tanda tangan, nomor dokumen) dicatat sebagai "Tidak wajib — di luar SAKIP". Bila Perencanaan mensyaratkannya di dalam Excel, ketentuannya ditetapkan sebagai keputusan baru sebelum pengesahan. |
| U5 | 30 Oktober 2026 | Pertanyaan U1 dan calon contoh (a) dikirim dalam satu permintaan: keluaran ekspor ISS-10.03 dengan data uji fiktif dilampirkan sejak awal dan dipakai hanya bila Perencanaan tidak memiliki format kerja rekap. Tanggapan diminta paling lambat 6 November 2026. |

---

## 1. Identitas Contoh dan Pengesahan

| Keluaran | Calon contoh | Format berkas | Diminta/diterima | Penyimpan | Pengesah (jabatan) | Tanggal dan bentuk persetujuan | Status |
|---|---|---|---|---|---|---|---|
| (a) Rekapitulasi indikator × periode | Menunggu jawaban Perencanaan (U1); calon contoh dari ekspor ISS-10.03 dengan data uji ikut dilampirkan (U5) | — | — / — | PM | — | — | Draf — belum disahkan Perencanaan |
| (b) Rencana Aksi tahunan | Rencana Aksi 2026 (23 Mei 2026), menunggu persetujuan dipakai sebagai contoh | — | — / sudah disimpan PM (pengantar Q47–Q51) | PM | — | — | Draf — belum disahkan Perencanaan |
| (c) Laporan Kinerja Triwulan | Laporan Kinerja Triwulan I dan II 2026 (20 Juli 2026), menunggu persetujuan dipakai sebagai contoh | — | — / sudah disimpan PM (pengantar Q47–Q51) | PM | — | — | Draf — belum disahkan Perencanaan |

---

## 2. Keluaran (a) — Rekapitulasi Indikator × Periode (ISS-10.03)

**Keluaran aplikasi:** `GET /laporan/rekap/ekspor` (PR #198). Workbook dua sheet, **Rekap** (satu baris per komponen; nilai indikator diulang) dan **Kegiatan**. Baris 1–6 memuat judul, jadwal, periode, waktu unduh, cakupan akses, dan catatan pembacaan; baris 8 memuat judul kolom.

| No | Informasi wajib | Dasar | Padanan SAKIP (kolom ekspor) | Letak pada contoh | Catatan kesetaraan | Status |
|---|---|---|---|---|---|---|
| A1 | Jadwal tahunan dan periode | PRD §24.1 | Baris 2 (`Jadwal`) dan 3 (`Periode`) | — | — | Menunggu contoh |
| A2 | PIC | PRD §22.4 | `PIC Historis`, `PIC Saat Ini` | — | — | Menunggu contoh |
| A3 | Sasaran dan indikator | PRD §22.4; Q50 §50.4 | `Kode Sasaran`, `Sasaran`, `Kode Indikator`, `Indikator`, `Satuan` | — | Kode mengikuti PK (S1–S4, IKU 1.1–IKU 4.1). Sasaran yang tidak tercatat pada versi beku ditulis "Tidak tercatat pada versi ini" ([K4 #177](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/177#issuecomment-6438390296)). | Menunggu contoh |
| A4 | Formula/cara hitung | PRD §22.4 | `Cara Hitung`, `Definisi` | — | — | Menunggu contoh |
| A5 | Baseline | PRD §22.4; ISS-10.03 AC-1 | `Baseline` | — | — | Menunggu contoh |
| A6 | Target PK tahunan dan target periode | PRD §22.4; ISS-10.03 AC-1; Q47 §47.2 | `Target PK`, `Target Periode` | — | Periode tanpa target ditulis "Tanpa target periode" (Q47 §47.3 butir 3). | Menunggu contoh |
| A7 | Realisasi | ISS-10.03 AC-1 | `Realisasi` | — | Realisasi belum tersedia ditulis "Belum dapat diukur", bukan 0 (Q47 §47.4 butir 3). | Menunggu contoh |
| A8 | Persentase capaian periode | PRD §22.4; ISS-10.03 AC-1 | `Capaian (%)` | — | Penyebut nol ditulis "Tidak dapat dihitung", bukan 0 (Q9; ISS-10.03 AC-5). | Menunggu contoh |
| A9 | Selisih terhadap target PK | PRD §22.4; Q47 §47.3 butir 1–2 | `Selisih terhadap Target PK` | — | — | Menunggu contoh |
| A10 | Nilai tiap komponen (target dan realisasi) | PRD §22.4 | `Kode Komponen`, `Komponen`, `Peran Komponen`, `Bobot`, `Target Komponen`, `Realisasi Komponen` | — | Indikator tanpa komponen ditulis "Tidak berlaku" pada kolom komponen. | Menunggu contoh |
| A11 | Narasi pengukuran: progres kegiatan, kendala/masalah, tindak lanjut/strategi, dan tindak lanjut atas rekomendasi | PRD §22.4; Q45 §45.1 butir 1; ISS-10.03 AC-1 | `Progres Kegiatan`, `Kendala / Masalah`, `Strategi Tindak Lanjut`, `Tindak Lanjut Rekomendasi` | — | — | Menunggu contoh |
| A12 | Daftar kegiatan yang diklaim beserta status, anggaran, dan penanda kegiatan bersama | PRD §22.4; Q42 §42.5 | `Detail Kegiatan`; sheet **Kegiatan**: `Kegiatan`, `Status Kegiatan`, `Anggaran (Rp)`, `Kegiatan Bersama`, `Sumber Klaim` | — | Detail kegiatan mengikuti akses unit pengunduh (Q42 §42.5). | Menunggu contoh |
| A13 | Narasi per kegiatan (opsional, bila diisi) | PRD §22.4; Q45 §45.1 butir 2 | Sheet **Kegiatan**: `Uraian Pelaksanaan`, `Kendala Kegiatan`, `Strategi Tindak Lanjut Kegiatan` | — | — | Menunggu contoh |
| A14 | Rekomendasi Pimpinan yang disetujui | PRD §22.4; Q51 §51.1; ISS-10.03 AC-1 | `Rekomendasi Pimpinan`, `Persetujuan Rekomendasi` | — | Persetujuan oleh selain Pimpinan ditandai "Persetujuan cadangan" (Q51 §51.2). | Menunggu contoh |
| A15 | Status bukti dukung | ISS-10.03 AC-1 | `Status Bukti`, `Bukti Wajib Terpenuhi`, `Bukti Wajib Total`, `Histori Bukti Tidak Dapat Dipenuhi` | — | — | Menunggu contoh |
| A16 | Penanda data belum resmi | Q48 §48.1 butir 2; Q47 §47.3–§47.4; ISS-10.03 AC-4 | `Status Laporan`, `Versi Pengukuran`, `Disahkan Pada`; teks "Belum disahkan" | — | Data yang belum disahkan tidak memuat angka realisasi resmi. | Menunggu contoh |

**Kolom tambahan ekspor (bukan informasi wajib):** `Kunci Rekap` (kolom tersembunyi penghubung kedua sheet), `Unit`, `Status Perhitungan`, `Status Capaian`, `Keterangan Capaian`, dan `Alasan Tidak Dapat Dihitung`. Kolom ini tidak dinilai sebagai kekurangan bila tidak ada pada contoh. `Status Capaian` mengikuti penetapan ISS-09.02 (PR #203): "Tercapai" atau "Belum tercapai" setelah ditetapkan pada versi pengukuran disahkan; selain itu "Belum ditetapkan", "Tidak dinilai" (periode tanpa target atau realisasi belum dapat diukur), atau "Tidak dapat dihitung".

---

## 3. Keluaran (b) — Rencana Aksi Tahunan (ISS-10.05)

**Keluaran aplikasi:** belum dibangun (ISS-10.05). Satu dokumen gabungan seluruh indikator untuk satu lembaga per jadwal tahunan (Q48 §48.1 butir 1). Angka dicetak menurut sumber pengesahannya ([K6 #179](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/179#issuecomment-6476048514)).

| No | Informasi wajib | Dasar | Padanan SAKIP (sumber data) | Letak pada contoh | Catatan kesetaraan | Status |
|---|---|---|---|---|---|---|
| B1 | Jadwal tahunan (tahun) untuk satu lembaga | Q48 §48.1 butir 1 | Jadwal tahunan `aktif`/`ditutup` | — | — | Menunggu contoh |
| B2 | Sasaran | Q48 §48.1 butir 1; Q50 §50.4 | Sasaran pada snapshot Rencana Aksi versi disahkan | — | Versi yang tidak membekukan sasaran ditulis "Tidak tercatat pada versi ini" ([K7 #179](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/179#issuecomment-6476048514)). | Menunggu contoh |
| B3 | Indikator (kode dan nama) | Q48 §48.1 butir 1; Q50 §50.4 | Kode dan nama indikator pada snapshot Rencana Aksi | — | Kode mengikuti PK (IKU 1.1–IKU 4.1). | Menunggu contoh |
| B4 | Satuan | Q48 §48.1 butir 1 | Konteks beku jadwal (`jadwal_snapshot`) | — | — | Menunggu contoh |
| B5 | Target PK | Q48 §48.1 butir 1 | Konteks beku jadwal (`jadwal_snapshot`) | — | Selalu dicetak (K6 #179). | Menunggu contoh |
| B6 | Target per periode seluruh indikator | Q48 §48.1 butir 1; Q47 §47.2 | Target periode Rencana Aksi versi disahkan | — | Periode tanpa target ditulis "Tanpa target periode" (Q47 §47.3 butir 3); Rencana Aksi belum disahkan ditulis "Belum disahkan". | Menunggu contoh |
| B7 | Daftar kegiatan per indikator per periode | Q48 §48.1 butir 1 | Kegiatan dan klaim pada snapshot Rencana Aksi versi disahkan | — | — | Menunggu contoh |
| B8 | Indikator yang Rencana Aksinya belum disahkan tetap tercantum | Q48 §48.1 butir 2 | Seluruh indikator jadwal | — | Ditulis "Belum disahkan" tanpa angka dari data yang belum disahkan (K6 #179). | Menunggu contoh |

---

## 4. Keluaran (c) — Laporan Kinerja Triwulan (ISS-10.05)

**Keluaran aplikasi:** belum dibangun (ISS-10.05). Satu dokumen gabungan seluruh indikator untuk satu lembaga per periode jadwal (Q48 §48.1 butir 1). Angka dicetak menurut sumber pengesahannya (K6 #179).

| No | Informasi wajib | Dasar | Padanan SAKIP (sumber data) | Letak pada contoh | Catatan kesetaraan | Status |
|---|---|---|---|---|---|---|
| C1 | Jadwal tahunan dan periode untuk satu lembaga | Q48 §48.1 butir 1 | Periode jadwal | — | — | Menunggu contoh |
| C2 | Indikator (kode dan nama) | Q48 §48.1 butir 1; Q50 §50.4 | Konteks beku jadwal | — | Kode mengikuti PK (IKU 1.1–IKU 4.1). | Menunggu contoh |
| C3 | Target PK | Q48 §48.1 butir 1 | Konteks beku jadwal (`jadwal_snapshot`) | — | Selalu dicetak (K6 #179). | Menunggu contoh |
| C4 | Target periode | Q48 §48.1 butir 1; Q47 §47.2 | Target periode Rencana Aksi versi disahkan | — | "Tanpa target periode" atau "Belum disahkan" seperti B6. | Menunggu contoh |
| C5 | Realisasi | Q48 §48.1 butir 1 | Pengukuran versi disahkan | — | "Belum dapat diukur" didahulukan bila periode juga tanpa target (Q47 §47.3 butir 3); pengukuran belum disahkan ditulis "Belum disahkan". | Menunggu contoh |
| C6 | Capaian atau selisih terhadap target PK | Q48 §48.1 butir 1; Q47 §47.3 butir 1–2 | Capaian dan selisih pada pengukuran versi disahkan | — | Periode tanpa target hanya memuat selisih; penyebut nol ditulis "Tidak dapat dihitung" (Q9). | Menunggu contoh |
| C7 | Analisis per indikator: progres kegiatan, kendala/masalah, tindak lanjut/strategi | Q48 §48.1 butir 1; Q45 §45.1 butir 1 | Narasi pengukuran versi disahkan | — | — | Menunggu contoh |
| C8 | Rekomendasi Pimpinan yang disetujui | Q48 §48.1 butir 1; Q51 §51.1 | Rekomendasi berstatus `disetujui` yang berlaku untuk indikator × periode | — | Draf rekomendasi tidak dicetak. | Menunggu contoh |
| C9 | Lampiran status kegiatan per indikator | Q48 §48.1 butir 1 | Status kegiatan yang diklaim pada pengukuran versi disahkan | — | Lampiran tambahan, bukan bagian tabel utama. | Menunggu contoh |
| C10 | Indikator yang pengukurannya belum disahkan tetap tercantum | Q48 §48.1 butir 2 | Seluruh indikator jadwal | — | Ditulis "Belum disahkan" tanpa angka dari data yang belum disahkan (K6 #179). | Menunggu contoh |

---

## 5. Unsur Dokumen Resmi di Luar SAKIP

Unsur berikut tidak dinilai sebagai kekurangan keluaran aplikasi.

| Unsur | Dasar | Catatan |
|---|---|---|
| Tabel Rincian Output (Rencana Aksi) dan realisasi anggaran per Rincian Output (laporan) | Q48 §48.1 butir 3; Q42 §42.2 butir 3 | Disusun di luar SAKIP |
| Dokumen Perjanjian Kinerja | Q48 §48.1 butir 4 | Tidak dihasilkan SAKIP; PDF diunggah lewat lampiran PK |
| Keluaran PDF dari aplikasi | Q48 §48.1 butir 5; PRD §24.2 | Fase lanjutan; PDF bertanda tangan diunggah kembali sebagai arsip (Q48 §48.2) |
| Tanda tangan elektronik | Q48 §48.2 butir 1 | Dilakukan di luar aplikasi |
| Kop, blok tanda tangan, dan nomor dokumen | U4 | Tidak wajib — di luar SAKIP, kecuali ditetapkan lain lewat keputusan baru |

---

## 6. Hasil UAT Pembandingan (Bagian 2)

UAT memakai data uji/fixture. Bukti tangkapan tidak boleh memuat nilai kinerja resmi atau nama pegawai. Hasil per informasi wajib dicatat pada kolom **Status** §2–§4; setiap `UAT: Tidak setara` diteruskan ke issue/PR pemiliknya.

| Keluaran | Build (SHA) | Tanggal | Lingkungan dan data uji | Kesimpulan | Tindak lanjut |
|---|---|---|---|---|---|
| (a) Rekapitulasi indikator × periode | — | — | — | Belum diuji | — |
| (b) Rencana Aksi tahunan | — | — | — | Belum diuji | — |
| (c) Laporan Kinerja Triwulan | — | — | — | Belum diuji | — |

---

## 7. Perubahan Format Setelah Pengesahan (AC-4)

| Tanggal | Keluaran | Perubahan | Disetujui (jabatan) | Rujukan keputusan/UAT |
|---|---|---|---|---|
| — | — | Belum ada | — | — |

---

## 8. Riwayat Dokumen

| Tanggal | Perubahan | Rujukan |
|---|---|---|
| 29 Oktober 2026 | Draf awal; ketiga keluaran berstatus Draf — belum disahkan Perencanaan | Issue #178 |
| 30 Oktober 2026 | Keterangan kolom `Status Capaian` diselaraskan dengan penetapan status capaian ISS-09.02 | PR #203 |
| 30 Oktober 2026 | Keputusan U5: calon contoh (a) dilampirkan pada permintaan pertama | Issue #178 |
