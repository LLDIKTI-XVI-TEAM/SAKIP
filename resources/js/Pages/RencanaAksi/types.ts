import type { PengukuranPagination } from '@/Pages/Pengukuran/types';
import type { TanggalPeriode } from '@/types/periode-jadwal';

/**
 * Kontrak halaman daftar Rencana Aksi (`DaftarRencanaAksi`): daftar indikator
 * × tahun sekaligus antrean review lewat filter status (Q59).
 */

/** `belum_dibuat` = indikator tanpa header rencana aksi; `semua` = tanpa saringan. */
export type FilterStatusRencanaAksi = 'semua' | 'belum_dibuat' | 'draft' | 'diajukan' | 'diverifikasi' | 'dikembalikan' | 'disahkan';

export interface BarisRencanaAksi {
    indikator_id: string;
    kode: string;
    nama: string;
    unit_nama: string | null;
    tahun: number;
    pj_nama: string | null;
    milik_saya: boolean;
    rencana_aksi: { id: string; status_alur: string } | null;
    /** Capability server: `create` sudah memuat PJ efektif, izin unit, unit snapshot, dan jendela; `review` mengikuti policy `view` dan status non-draft. */
    can: { create: boolean; review: boolean };
}

export interface RencanaAksiIndexProps {
    daftar: BarisRencanaAksi[];
    pagination: PengukuranPagination;
    filters: { status: FilterStatusRencanaAksi };
}

/**
 * Kontrak props halaman RencanaAksi/Show.
 *
 * Bentuk JSON mengikuti `App\Actions\RencanaAksi\IndexRencanaAksi::handle`
 * secara eksplisit: skor turunan, peringatan turun, dan deviasi vs target
 * PK dihitung server. React hanya menampilkan payload
 * dan tidak menghitung ulang skor turunan di klien.
 */
export interface RencanaAksiKomponen {
    komponen_id: string;
    kode: string;
    label: string;
    peran: string;
    bobot: string;
    urutan: number;
}

export interface RencanaAksiNilaiCell {
    komponen_id: string | null;
    kode: string | null;
    label: string | null;
    /** String desimal dari server atau null bila belum diisi. `0` tetap string `0...`, bukan null. */
    nilai: string | null;
    keterangan: string | null;
}

export type RencanaAksiStatusPerhitungan = 'belum_diisi' | 'terhitung' | 'tidak_dapat_dihitung' | string;

export interface RencanaAksiPeriode {
    id: string;
    nama: string | null;
    urutan: number;
    /** Tanggal Master Periode saat ini (bukan bagian versi beku); null bila periode master tidak ditemukan. */
    tanggal: TanggalPeriode | null;
    efektif: boolean;
    status: 'efektif' | 'tidak_berlaku';
    nilai: RencanaAksiNilaiCell[];
    skor: {
        nilai: string | null;
        status_perhitungan: RencanaAksiStatusPerhitungan;
    };
    peringatan_turun: boolean;
    /** Daftar komponen_id yang turun; `[null]` untuk indikator manual. */
    komponen_turun: (string | null)[];
    /** Batas `YYYY-MM-DD` bila periode sudah terkunci bagi PIC (dikirim ke semua aktor); null bila belum, tidak berlaku, atau tahun ditutup tanpa sesi koreksi. */
    batas_terkunci_pic: string | null;
    /** Target periode ini boleh kosong menurut `PeriodeTanpaTarget::bolehTanpaTarget` (periode lampau jadwal 2026, atau periode sebelum nilai akhir pada indikator dinilai tahunan); false untuk baris tidak berlaku. */
    boleh_tanpa_target: boolean;
}

export interface RencanaAksiDeviasiPk {
    dapat_dinilai: boolean;
    ada: boolean;
    alasan_diperlukan: boolean;
    alasan_terisi: boolean;
    skor_periode_terakhir: string | null;
    target_pk: string | null;
    periode_id: string | null;
}

export interface RencanaAksiKoreksi {
    /** True bila penutupan terlewati dan sesi koreksi sah (waktu + jenis_objek + indikator). Cermin gerbang tulis backend. */
    aktif: boolean;
    /** `lingkup_koreksi.periode_ids`; kosong bila kunci tak ada = tidak ada periode tercakup (gagal tertutup). */
    periode_ids: string[];
}

export interface RencanaAksiShow {
    id: string;
    tahun: number;
    status_alur: string;
    versi: number;
    expected_versi: number;
    /** Token konkurensi snapshot beku terbaru. Dikembalikan apa adanya saat simpan. */
    expected_snapshot_id: string;
    /** Nomor versi snapshot beku terbaru (`jadwal_snapshot.nomor_versi`). */
    expected_snapshot_versi: number;
    uraian: string | null;
    alasan_deviasi_pk: string | null;
    indikator: {
        id: string;
        kode: string;
        nama: string;
        satuan: string;
        arah: string;
        tipe_perhitungan: string;
        presisi: number;
        desimal_tampilan: number;
        status: string;
        tahun_mulai_berlaku: number;
        /** Penanda dinilai tahunan dari snapshot beku, bukan master. */
        dinilai_tahunan: boolean;
    };
    unit: { id: string; nama: string } | null;
    jadwal: {
        id: string;
        tahun: number;
        status: string;
        penutupan: string | null;
    };
    penanggung_jawab: { id: string; nama: string } | null;
    tipe_perhitungan: string;
    presisi: number;
    target_pk: string | number | null;
    baseline: string | number | null;
    komponen: RencanaAksiKomponen[];
    periode: RencanaAksiPeriode[];
    /** Lingkup koreksi dari `IndexRencanaAksi` agar UI menonaktifkan + tak mengirim periode luar lingkup. */
    koreksi: RencanaAksiKoreksi;
    /** Periode terkunci bagi aktor (batas pengisian lewat); kosong bagi Perencanaan. UI hanya-baca + tak dikirim. Opsional agar payload lama tetap terbaca sebagai tidak terkunci. */
    periode_terkunci?: string[];
    /** Periode yang dapat ditulis aktor (efektif ∩ lingkup koreksi − terkunci). Batas capability tambah kegiatan; kosong = tidak dapat menulis. */
    periode_dapat_ditulis: string[];
    deviasi_pk: RencanaAksiDeviasiPk;
    pengajuan: RencanaAksiPengajuan;
    /** Versi pengajuan terbaru (nomor terbesar); null bila belum pernah diajukan. */
    versi_pengajuan: RencanaAksiVersiPengajuan | null;
    can: {
        view: boolean;
        update: boolean;
        /** Eligibility pengajuan dari server (izin, jendela, status, jepit snapshot); kelengkapan isi ada di `pengajuan`. */
        submit: boolean;
    };
}

/** Kesiapan pengajuan dari evaluator server; `alasan` adalah teks server dan hanya ditampilkan. */
export interface RencanaAksiPengajuan {
    siap: boolean;
    alasan: string[];
}

export interface RencanaAksiVersiPengajuan {
    nomor: number;
    diajukan_oleh: string | null;
    /** ISO-8601 dari server. */
    diajukan_at: string;
}

export type BuktiMode = 'file' | 'tautan' | 'teks';

/** Satu persyaratan `jenis_berkas` tahap rencana aksi beserta pemenuhannya dari evaluator server. */
export interface BuktiPersyaratan {
    id: string;
    nama: string;
    keterangan: string | null;
    wajib: boolean;
    semua_mode_wajib: boolean;
    izinkan_file: boolean;
    izinkan_tautan: boolean;
    izinkan_teks: boolean;
    format_diizinkan: string;
    ukuran_maks_kb: number;
    pemenuhan: {
        terpenuhi: boolean;
        mode_terpenuhi: BuktiMode[];
        mode_kurang: BuktiMode[];
        mode_dikecualikan: BuktiMode[];
        /** Penanda anti-macet §18.7: unggahan nonaktif dan tidak ada mode alternatif. */
        tidak_dapat_dipenuhi: boolean;
        alasan_pengecualian: string | null;
    };
}

export interface BuktiItem {
    id: string;
    jenis_berkas_id: string | null;
    nama_persyaratan: string | null;
    mode: BuktiMode;
    nama_asli: string | null;
    mime: string | null;
    ukuran_bytes: number | null;
    tautan: string | null;
    isi_teks: string | null;
    /** URL unduh ber-otorisasi dari server; null untuk mode tautan/teks. */
    download_url: string | null;
    pengunggah: string | null;
    created_at: string | null;
}

/** Props panel bukti dari `IndexBuktiRencanaAksi`; null bila aktor tidak berhak melihat bukti. */
export interface RencanaAksiBukti {
    /** Q63: persyaratan wajib dikecualikan pada jalur Perencanaan (periode lampau/data awal). */
    dikecualikan: boolean;
    persyaratan: BuktiPersyaratan[];
    ringkasan: { lengkap: boolean; total_wajib: number; terpenuhi_wajib: number };
    daftar: BuktiItem[];
    unggahan: { ukuran_maks_kb: number; format_diizinkan: string };
    can: { upload: boolean; delete: boolean };
}

export const rencanaAksiStatusPerhitungan: Record<string, string> = {
    belum_diisi: 'Belum diisi',
    terhitung: 'Terhitung',
    tidak_dapat_dihitung: 'Tidak dapat dihitung',
    tidak_ditargetkan: 'Tidak ditargetkan',
};

/** Kunci sel matriks `periode_id::komponen_id` (`manual` untuk indikator manual). */
export function kunciSel(periodeId: string, komponenId: string | null): string {
    return `${periodeId}::${komponenId ?? 'manual'}`;
}

/** Nilai tersimpan (`numeric(30,12)`) tampil tanpa nol di belakang koma; operasi string, tanpa pembulatan. */
export function tanpaNolBelakang(nilai: string | number): string {
    const teks = String(nilai);
    return teks.includes('.') ? teks.replace(/\.?0+$/, '') : teks;
}

/** True bila periode boleh disunting di UI: tanpa koreksi aktif semua efektif boleh; bila koreksi aktif, hanya yang tercakup. */
export function dapatDisuntingPeriode(koreksi: RencanaAksiKoreksi, periodeId: string): boolean {
    return !koreksi.aktif || koreksi.periode_ids.includes(periodeId);
}

/** Baris pratinjau server (`PreviewTargetPeriode`): tanpa persistensi, tanpa formula di React. */
export interface RencanaAksiPreviewPeriode {
    id: string;
    efektif: boolean;
    skor: {
        nilai: string | null;
        status_perhitungan: RencanaAksiStatusPerhitungan;
    };
    peringatan_turun: boolean;
    komponen_turun: (string | null)[];
}

export interface RencanaAksiPreview {
    periode: RencanaAksiPreviewPeriode[];
    deviasi_pk: RencanaAksiDeviasiPk;
}

/**
 * Identitas RA pengklaim pada halaman ubah kegiatan (`KegiatanEdit`).
 * `kode` opsional: payload server saat ini hanya mengirim `id` dan `tahun`,
 * jadi breadcrumb memakai fallback tahun bila kode tidak tersedia.
 */
export interface RencanaAksiIdentitas {
    id: string;
    tahun: number;
    kode?: string | null;
}

/** Kegiatan yang diklaim RA (tahap RA atau pengukuran) per periode. */
export interface KegiatanRencanaAksiItem {
    id: string;
    kegiatan_id: string;
    periode_id: string;
    nama: string;
    status: string;
    komponen_id: string | null;
    arah_dampak: 'menambah' | 'mengurangi';
    catatan: string | null;
    /** Rupiah bulat; null berarti belum diisi (Q42 §42.2 butir 2). */
    anggaran: string | null;
    sumber_klaim: 'rencana_aksi' | 'pengukuran';
    /** Diklaim lebih dari satu IKU (Q42 §42.2 butir 4). */
    kegiatan_bersama: boolean;
}

/** Total anggaran kegiatan unik; `belum_diisi` = jumlah kegiatan beranggaran kosong yang tidak ikut dihitung. */
export interface TotalAnggaran {
    total: string;
    belum_diisi: number;
}

export interface RencanaAksiKegiatan {
    daftar: KegiatanRencanaAksiItem[];
    total: TotalAnggaran & { per_periode: Record<string, TotalAnggaran> };
    can: { tambah: boolean; kelola: boolean };
}
