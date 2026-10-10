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
    };
    unit: { id: string; nama: string } | null;
    jadwal: {
        id: string;
        tahun: number;
        status: string;
        rencana_aksi_mulai: string | null;
        rencana_aksi_selesai: string | null;
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
    deviasi_pk: RencanaAksiDeviasiPk;
    can: {
        view: boolean;
        update: boolean;
    };
}

export const rencanaAksiStatusPerhitungan: Record<string, string> = {
    belum_diisi: 'Belum diisi',
    terhitung: 'Terhitung',
    tidak_dapat_dihitung: 'Tidak dapat dihitung',
};

/** Kunci sel matriks `periode_id::komponen_id` (`manual` untuk indikator manual). */
export function kunciSel(periodeId: string, komponenId: string | null): string {
    return `${periodeId}::${komponenId ?? 'manual'}`;
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
 * Kontrak halaman reviu pengesahan (ISS-05.05) — dibaca dari snapshot versi
 * beku; tidak pernah memakai relasi live untuk status beku.
 */
export interface RencanaAksiSahkan {
    id: string;
    versi: number;
    status: string;
    nomor_pengajuan: number;
    can: {
        ratify: boolean;
    };
}

export interface RencanaAksiBukti {
    id: string;
    jenis_berkas_id: string | null;
    menggantikan_id: string | null;
    alasan_koreksi: string | null;
    mode: 'file' | 'tautan' | 'teks';
    nama_asli: string | null;
    mime: string | null;
    ukuran_bytes: number | null;
    tautan: string | null;
    isi_teks: string | null;
    download_url: string | null;
}

export interface RencanaAksiKomponenBeku {
    komponen_id: string | null;
    kode: string | null;
    label: string | null;
    nilai: string | number | null;
}

export interface RencanaAksiRingkas {
    id: string;
    versi: number;
    status: 'draft' | 'diajukan' | 'dikembalikan' | 'diverifikasi' | 'disahkan' | string;
    tahun: number;
    nomor_pengajuan: number;
    jalur_pengajuan: 'pic' | 'perencanaan' | null;
    diajukan_pada: string | null;
    uraian: string | null;
    indikator: { kode: string; nama: string };
    unit_kerja: { id: string; nama: string };
    pic: { id: string; nama: string } | null;
    konteks_tidak_lengkap: string[];
    bukti_count: number;
    bukti_dukungs: RencanaAksiBukti[];
    target_periode: { periode_id: string | null; nilai: string | number | null; status_perhitungan: string | null; komponen: RencanaAksiKomponenBeku[] }[];
    can: { view: boolean };
}

export interface RencanaAksiDetail extends RencanaAksiRingkas {
    diajukan_oleh: { id: string; nama: string } | null;
    disahkan_pada: string | null;
    target_periode: {
        periode_id: string | null;
        periode_nama: string | null;
        periode_urutan: number | null;
        nilai: string | number | null;
        status_perhitungan: string | null;
        komponen: RencanaAksiKomponenBeku[];
    }[];
    bukti_dukungs: RencanaAksiBukti[];
    can: { view: boolean; ratify: boolean; evidence: boolean };
}

export interface RencanaAksiPagination {
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
