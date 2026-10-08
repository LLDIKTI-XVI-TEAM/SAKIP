export interface WorkPermission { permission: string; label: string; allowed: boolean; reason: string }
export interface WorkReadiness { complete: boolean; permissions: WorkPermission[]; missing: string[]; available: string[] }
export interface Assignment { id: string; tanggal_mulai_berlaku: string; pic: { id: string; nama: string; status: string } | null; alasan: string | null; ditetapkan_oleh: { id: string; nama: string } | null; created_at: string | null; state?: string }
export interface IndicatorSummary { id: string; kode: string; nama: string; status: string }
export interface UnitSummary { id: string; nama: string; status: string }
export interface AssignmentDetailProps {
    indicator: IndicatorSummary; unit: UnitSummary | null; renstra: { id: string; kode: string; nama: string; status: string } | null;
    effective: Assignment | null; readiness: WorkReadiness | null;
    history: { data: Assignment[]; prev_page_url: string | null; next_page_url: string | null };
    has_history: boolean; expected_state: string; tanggal_acuan: string; today: string; blocked_reason: string | null;
    can: { assign: boolean }; saved_assignment_id: string | null;
}
