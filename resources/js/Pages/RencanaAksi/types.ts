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
