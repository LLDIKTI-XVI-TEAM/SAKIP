# CONTEXT — SAKIP LLDIKTI Wilayah XVI

Glosarium kanonis domain. Bahasa dokumen mengikat; nama model/tabel hanya alias implementasi.

## Glosarium

- **Sasaran**: tujuan strategis di bawah Renstra. Alias implementasi: `SasaranStrategis`.
- **Indikator**: unit ukur kinerja konkret di bawah Sasaran, dimiliki tepat satu Unit. Alias implementasi: `IndikatorKinerja`.
- **Unit**: kelompok organisasi pemilik Indikator sekaligus scope izin. Bukan satuan ukur (itu `satuan`) dan bukan `unit kerja` evaluasi ZI/SAKIP.
- **Regulasi**: produk hukum (kepmen/permen/perpres/keputusan_lainnya) yang dirujuk Renstra/Indikator.
- **Renstra**: payung strategis multi-tahun.
- **Aktif / Arsip (Indikator)**: `aktif` dapat jadi target pengisian baru; `arsip` tidak jadi kewajiban baru tapi histori utuh dan tetap terbaca di laporan.
- **PIC operasional**: konteks pekerjaan (user + penugasan + grant + jendela/guard valid), bukan role sistem.
- **Penanggung Jawab**: assignment historis user ↔ Indikator, tidak memberi permission.
- **Versi Pengukuran**: versi beku pengajuan pengukuran yang telah disahkan; setelah sah tidak dapat diubah. Alias implementasi: `KinerjaSnapshot` (tabel `pengukuran_versi`).
- **Status Capaian**: penilaian akhir manual (`tercapai`/`belum_tercapai`) atas satu Versi Pengukuran yang disahkan. Satu status berlaku per versi; revisi menambah baris baru dan baris lama tetap sebagai riwayat. Tidak tersedia untuk periode tanpa target, `belum_dapat_diukur`, maupun `tidak_dapat_dihitung`.
- **Dasar Izin**: snapshot keputusan resolver otorisasi yang dicatat di `audit_log` untuk aksi sensitif; ditentukan server, bukan payload klien.
