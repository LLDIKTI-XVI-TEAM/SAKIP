import type { PaginationLinkItem } from '@/Components/Pagination';

/** Nilai JSON audit sebagaimana dikirim server; kunci rahasia sudah disamarkan sebelum menjadi props. */
export type AuditJson = string | number | boolean | null | AuditJson[] | { [key: string]: AuditJson };

export interface AuditRingkasan {
    id: string;
    waktu: string;
    tindakan: string;
    objek_tipe: string;
    objek_id: string;
    actor_type: string;
    sumber: string;
    aktor: { nama: string; email: string } | null;
    alasan: string | null;
    ditolak: boolean;
}

export interface AuditDetail extends AuditRingkasan {
    operator_reference: string | null;
    runtime_identity: string | null;
    nilai_lama: AuditJson;
    nilai_baru: AuditJson;
    dasar_izin: AuditJson;
}

export interface AuditFilters {
    dari: string;
    sampai: string;
    aktor: string;
    tindakan: string;
    objek_tipe: string;
    objek_id: string;
}

export interface AuditIndexProps {
    audit: {
        data: AuditRingkasan[];
        from: number | null;
        to: number | null;
        total: number;
        links: PaginationLinkItem[];
    };
    filters: AuditFilters;
}

/** Label jenis pelaku sesuai provenance yang diterima writer audit server. */
export const jenisPelakuLabel: Record<string, string> = {
    user: 'Pengguna',
    system: 'Sistem',
    operator: 'Operator',
};
