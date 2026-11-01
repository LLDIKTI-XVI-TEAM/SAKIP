# Kesiapan Infrastruktur dan Layanan Eksternal — Checklist sebelum UAT dan Produksi

> **Traceability:** Issue [#186](https://github.com/LLDIKTI-XVI-TEAM/SAKIP/issues/186) · `ISS-15.02` · `US-15.02` · Q2 · Q30 · Q46 §46.3 · Plan P.1, P.2, P.4
> **Basis dokumen:** `development` `91f313e2` (1 November 2026)
> **Sifat dokumen:** artefak kesiapan operasional. Bukan bukti deployment, bukan penerimaan UAT, dan bukan persetujuan produksi.

## Cara memakai dokumen ini

1. Readiness aplikasi (§1) dan readiness operasional (§2) dinilai terpisah. Produksi tidak dinyatakan siap hanya karena test aplikasi atau CI lulus.
2. Status hanya boleh salah satu dari:
   - `Belum ditetapkan` — owner atau keputusan belum ada.
   - `Menunggu bukti` — owner sudah ditetapkan, bukti belum tercatat.
   - `Siap berbukti` — bukti tercatat: tanggal (WITA), pelaksana, lingkungan, dan hasil.
   - `Belum diaktifkan` — layanan sengaja belum dipakai pada rilis ini, misalnya notifikasi eksternal (Q46 §46.3).
3. Status UAT dan status produksi dicatat terpisah. Siap untuk UAT tidak berarti siap produksi (Q2).
4. Owner ditulis sebagai jabatan atau peran organisasi (mis. "PM SAKIP", "Pengelola infrastruktur LLDIKTI Wilayah XVI", "Tim Perencanaan"), tanpa nama pribadi, kontak, atau akun. Owner diisi hanya setelah ditetapkan PM bersama pengelola infrastruktur; sebelum itu tulis `Belum ditetapkan`.
5. Bukti berupa tanggal, pelaksana (jabatan), lingkungan, dan hasil uji, mis. "respons `/up` 200" atau "pemulihan basis data berhasil". Bukti **tidak** memuat hostname, domain, alamat IP, URL realm, client ID/secret, password, token, isi `.env`, atau tangkapan layar berisi data sensitif. Nilai tersebut disimpan di luar repositori.
6. Kolom **Diverifikasi oleh/tanggal** diisi PM atau pengelola infrastruktur setelah memeriksa bukti.
7. Layanan tanpa owner atau tanpa bukti tetap tercatat sebagai blocker/risiko (§5) dan tidak dianggap selesai.

**Semantik status:** `Implemented` ≠ `Verified` ≠ `Merged` ≠ `UAT Accepted` ≠ `Deployed`. Tahapan siap diuji, evaluasi/perbaikan, diterima, dan produksi mengikuti ISS-15.03.

---

## 1. Readiness Aplikasi

Bagian ini dibuktikan dari repositori dan CI, lalu diulang pada lingkungan tujuan untuk SHA yang dirilis. Lulusnya bagian ini **tidak** membuktikan kesiapan lingkungan tujuan (§2).

| Item | Bukti di repositori | Bukti pada lingkungan tujuan | Yang tidak dibuktikan | Status UAT | Status produksi | Diverifikasi oleh/tanggal |
|---|---|---|---|---|---|---|
| CI exact HEAD | `.github/workflows/ci.yml`: sembilan pemeriksaan, tanpa job deploy | SHA rilis dan tautan run CI yang lulus 9/9. Untuk SHA yang hanya berisi perubahan dokumen, jalankan CI manual (`workflow_dispatch`) agar kesembilan pemeriksaan benar-benar berjalan | Konfigurasi, data, dan layanan lingkungan tujuan | Belum ditetapkan | Belum ditetapkan | — |
| Build aset | Plan P.2: `bun install`, lalu `bun run build` | Build dengan Bun pada SHA rilis | `Containerfile` dan `compose.yaml` hanya untuk pengembangan dan memasang `nodejs`/`npm`, berbeda dari Plan P.1–P.2; belum ada artefak deployment produksi | Belum ditetapkan | Belum ditetapkan | — |
| Migrasi | Plan P.2: `php artisan migrate --force` setelah build aset | Hasil migrasi pada SHA rilis | Backup sebelum migrasi (§2 baris 6) | Belum ditetapkan | Belum ditetapkan | — |
| Seeder konfigurasi | `DatabaseSeeder` hanya memanggil `AccessCatalogSeeder`, `PengaturanSeeder`, dan `PeriodeSeeder`, tanpa fixture testing (`SeedProduksiTanpaFixtureTest`, ISS-15.01 AC-3) | Hasil seeder pada SHA rilis | Data awal 2026 (ISS-15.01) | Belum ditetapkan | Belum ditetapkan | — |
| Health check | Route `/up` (`bootstrap/app.php`) | Respons `/up` 200 | `/up` hanya membuktikan aplikasi dapat boot; tidak memeriksa basis data, queue, Keycloak, maupun storage | Belum ditetapkan | Belum ditetapkan | — |
| Akun pertama | `php artisan sakip:bootstrap-superadmin` setelah `AccessCatalogSeeder`; referensi operator dan alasan wajib dan dicatat di audit (`App\Actions\Auth\BootstrapSuperadmin`; test perintah `BootstrapSuperadminCommandTest`) | Superadmin pertama terbentuk dan tercatat di Jejak Audit | Akun UAT lain (ISS-15.01 AC-5) | Belum ditetapkan | Belum ditetapkan | — |
| Identitas build | Belum ada tag atau GitHub Release; `main` belum diperbarui sejak 18 September 2026 | SHA/versi yang diuji dan dirilis | — | Belum ditetapkan | Belum ditetapkan | — |

---

## 2. Readiness Operasional (AC-1, AC-4)

| No | Layanan | Kebutuhan minimum (acuan) | Owner | Status UAT | Status produksi | Bukti | Blocker/risiko | Diverifikasi oleh/tanggal |
|---|---|---|---|---|---|---|---|---|
| 1 | Realm/client Keycloak | Client khusus SAKIP, OIDC Authorization Code Flow berbasis session (Plan P.1; PRD §6). Kunci `KEYCLOAK_*` ada di `.env.example`; nilainya disimpan di luar repositori | Belum ditetapkan | Belum ditetapkan | Belum ditetapkan | — | Owner belum ditetapkan. Menahan akun UAT lima role dan PIC operasional (`SAKIP - Data Awal 2026.md` §6; ISS-15.01 AC-5) | — |
| 2 | Domain/HTTPS | Domain per lingkungan dan sertifikat TLS untuk lalu lintas produksi (Plan P.1; Q30) | Belum ditetapkan | Belum ditetapkan | Belum ditetapkan | — | Owner belum ditetapkan. Domain menentukan alamat redirect Keycloak (baris 1) | — |
| 3 | VPS dan web server | PHP sesuai Laravel 13 (`composer.json`: `^8.3`) dengan ekstensi `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`; Nginx/Apache ke `public/index.php`; Bun untuk build aset (Plan P.1) | Belum ditetapkan | Belum ditetapkan | Belum ditetapkan | — | Owner belum ditetapkan; belum ada artefak deployment produksi | — |
| 4 | PostgreSQL | Basis data dan akun khusus SAKIP (Plan P.1), versi ≥ 15 (README) | Belum ditetapkan | Belum ditetapkan | Belum ditetapkan | — | Owner belum ditetapkan | — |
| 5 | Storage privat dan kapasitas | Disk `local` (`storage/app/private`); berkas hanya diakses lewat route berizin (ADR-0006); batas unggahan dari `SAKIP_UPLOAD_*` (`catatan-rilis-ISS-13.06.md`); pemantauan kapasitas disk (Plan P.1; Q44) | Belum ditetapkan | Belum ditetapkan | Belum ditetapkan | — | Owner dan kapasitas belum ditetapkan. Tanpa pemantauan, disk penuh menggagalkan unggahan berkas | — |
| 6 | Backup dan uji pemulihan | Backup basis data dan storage privat; uji pemulihan berbukti (§4). Repositori tidak memuat paket atau command backup, sehingga backup sepenuhnya prosedur operasional | Belum ditetapkan | Belum ditetapkan | Belum ditetapkan | — | Owner, jadwal, retensi, dan lokasi backup belum ditetapkan; uji pemulihan belum ada (AC-3) | — |
| 7 | Queue worker | Worker untuk koneksi `database` (tabel `jobs` dan `failed_jobs`). Job saat ini hanya `CleanupStorageFileJob` (`tries` 3), dijadwalkan bila penghapusan berkas lampiran PK dari storage gagal | Belum ditetapkan | Belum ditetapkan | Belum ditetapkan | — | Owner belum ditetapkan. Tanpa worker, berkas lampiran PK yang gagal dihapus tidak pernah dibersihkan | — |
| 8 | Scheduler | Cron yang menjalankan `php artisan schedule:run` setiap menit | Belum ditetapkan | Belum ditetapkan | Belum ditetapkan | — | Belum ada tugas terjadwal (`routes/console.php`); dibutuhkan pengingat ISS-12.x setelah Tahap 2 (Q46 §46.3). Owner tetap ditetapkan sebelum produksi | — |
| 9 | Health check dan monitoring | Pemeriksaan `/up` berkala; pemantauan log aplikasi, kapasitas disk, dan ketersediaan basis data | Belum ditetapkan | Belum ditetapkan | Belum ditetapkan | — | Owner belum ditetapkan. `/up` saja tidak cukup (§1) | — |
| 10 | Notifikasi WA/Email | Daftar periksa §3 | Belum ditetapkan | Belum diaktifkan | Belum diaktifkan | — | Ditunda setelah Tahap 2 (Q46 §46.3; ISS-12.01–ISS-12.07, ISS-13.04). Belum ada kelas notifikasi atau konfigurasi WhatsApp; hanya kunci `MAIL_*` bawaan Laravel | — |
| 11 | Dukungan insiden dan pemeliharaan | Kanal pelaporan insiden, jam dukungan, jendela pemeliharaan, dan eskalasi (Q30) | Belum ditetapkan | Belum ditetapkan | Belum ditetapkan | — | Owner belum ditetapkan | — |
| 12 | Bootstrap Superadmin | Pelaksana `sakip:bootstrap-superadmin` di setiap lingkungan baru, dengan referensi operator dan alasan (§1) | Belum ditetapkan | Belum ditetapkan | Belum ditetapkan | — | Owner belum ditetapkan. Tanpa Superadmin pertama, peran pengguna lain tidak dapat ditetapkan lewat aplikasi | — |
| 13 | Credential deployment | Konfigurasi lingkungan (`.env`), client secret Keycloak, dan kredensial basis data disimpan di luar repositori dengan akses terbatas | Belum ditetapkan | Belum ditetapkan | Belum ditetapkan | — | Owner, lokasi penyimpanan, dan pihak yang berhak mengakses belum ditetapkan | — |

---

## 3. Notifikasi Eksternal (AC-2)

Notifikasi WA/Email tidak diaktifkan pada rilis ini; urutan pengerjaannya mengikuti rencana sprint setelah Tahap 2 (Q46 §46.3). Sebelum diaktifkan pada ISS-12.01–ISS-12.07 dan ISS-13.04, setiap butir berikut harus berstatus `Siap berbukti`.

| Kebutuhan | Keterangan | Owner | Status | Bukti |
|---|---|---|---|---|
| Provider WA/Email | Penyedia layanan pengiriman | Belum ditetapkan | Belum diaktifkan | — |
| Template pesan | Isi pesan per jenis notifikasi Q4: pembukaan jadwal, pengingat PIC H-7/H-3/H-1, rekap Perencanaan H-3/H-1, dan pengembalian berkas | Belum ditetapkan | Belum diaktifkan | — |
| Sumber kontak | Asal nomor/email penerima dan pemilik datanya | Belum ditetapkan | Belum diaktifkan | — |
| Jam kirim dan zona waktu | Jam pengiriman dan zona waktu acuannya | Belum ditetapkan | Belum diaktifkan | — |
| Pengirim | Nomor/email pengirim resmi | Belum ditetapkan | Belum diaktifkan | — |
| Credential | Disimpan pada konfigurasi lingkungan tujuan, di luar repositori | Belum ditetapkan | Belum diaktifkan | — |
| Retry | Batas pengulangan dengan identitas pengiriman yang idempoten (System Architecture §16) | Belum ditetapkan | Belum diaktifkan | — |
| Bukti delivery | Bukti pesan sampai ke penerima; status diterima atau masuk antrean dari provider bukan bukti delivery (System Architecture §16) | Belum ditetapkan | Belum diaktifkan | — |

---

## 4. Uji Pemulihan (AC-3)

Backup dianggap siap hanya bila pemulihannya pernah diuji dan berbukti. Kriteria minimum di bawah ini adalah usulan; kebijakan final ditetapkan owner backup (§2 baris 6).

1. Basis data dan storage privat dipulihkan dari backup ke lingkungan terpisah, tanpa menimpa lingkungan yang sedang dipakai.
2. Aplikasi hasil pemulihan dapat boot (`/up` 200) dan data contoh dapat dibaca.
3. Sampel berkas dapat diunduh lewat route berizin.
4. Lama pemulihan dan titik data terakhir yang pulih dicatat.

| Tanggal | Pelaksana | Lingkungan | Cakupan | Hasil | Catatan |
|---|---|---|---|---|---|
| — | — | — | — | Belum ada uji pemulihan | Owner backup belum ditetapkan (§2 baris 6) |

---

## 5. Blocker dan Risiko Rilis (AC-4)

| No | Blocker/risiko | Dampak | Menahan | Tindak lanjut |
|---|---|---|---|---|
| 1 | Owner layanan §2 belum ditetapkan | Kesiapan UAT dan produksi tidak dapat diverifikasi | ISS-15.02 AC-1; UAT; produksi | PM bersama pengelola infrastruktur menetapkan owner (Q30) |
| 2 | Realm/client Keycloak lingkungan UAT belum tersedia | Akun UAT lima role dan PIC operasional tidak dapat dibuat | ISS-15.01 AC-5 | §2 baris 1 |
| 3 | Uji pemulihan belum ada | Backup belum terbukti dapat dipulihkan | ISS-15.02 AC-3; produksi | §4 |
| 4 | Notifikasi eksternal belum diaktifkan | Tidak ada pemberitahuan atau pengingat WA/Email | ISS-12.01–ISS-12.07, ISS-13.04 | Setelah Tahap 2 (Q46 §46.3); §3 |
| 5 | Belum ada artefak deployment produksi dan dokumen Deployment & Operations | Urutan rilis dan prosedur pemulihan belum baku | Produksi | System Architecture §17 |
| 6 | Belum ada identitas rilis (tag/Release) | Build yang diuji dan dirilis belum dapat dicatat | ISS-15.03 AC-5 | ISS-15.03 |

---

## 6. Riwayat Verifikasi

| Tanggal | Oleh | Perubahan/kesimpulan |
|---|---|---|
| — | — | Belum ada verifikasi PM atau pengelola infrastruktur |
