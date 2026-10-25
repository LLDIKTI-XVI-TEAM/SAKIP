import type { RecoveryState } from '@/lib/authRecovery';

/** Saran baseline dari realisasi akhir tahun sebelumnya; hanya `disahkan` membawa nilai dan nama periode. */
export interface SaranBaseline {
    status: 'disahkan' | 'belum_disahkan' | 'tidak_dapat_dihitung' | 'negatif' | 'belum_ada';
    nilai: string | null;
    periode: string | null;
}

export interface TargetEditorData {
    indikator_id: string;
    tahun: number;
    baseline_year: number;
    indikator: { id: string; kode: string; nama: string; satuan: string; presisi: number; desimal_tampilan: number; status: string; tahun_mulai_berlaku: number; arah: string };
    renstra: { id: string; nama: string; status: string; tahun_mulai: number; tahun_selesai: number };
    baseline: string | null;
    target_tahunan: string | null;
    has_snapshot: boolean;
    expected_state: string;
    can: { update: boolean };
    read_only_reason: string | null;
    /** Pembanding target Renstra tahun yang sama; `null` berarti target Renstra belum ada. */
    target_renstra: string | null;
    alasan_deviasi_renstra: string | null;
    status_deviasi: string;
    /** `null` berarti tanpa saran dan keterangan: baseline tersimpan, baca saja, tidak berhak membaca pengukuran, atau jadwal T−1 tanpa periode nilai akhir/versi efektif. */
    saran_baseline: SaranBaseline | null;
}

export interface TargetEditorProps {
    editor: TargetEditorData;
    /** Route simpan target PK pada konteks Perjanjian Kinerja. */
    path: string;
    loading?: boolean;
    loadError?: string;
    retryYear?: number;
    loadRecovery?: RecoveryState | null;
    onClose: () => void;
    onLoad: (year: number) => void;
    onSaved: (message: string) => void;
}
