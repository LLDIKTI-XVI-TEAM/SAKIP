import type { RecoveryState } from '@/lib/authRecovery';

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
