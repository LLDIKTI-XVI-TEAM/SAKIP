import type { UserOption } from "@/Components/Access/GrantUserAutocomplete";

export interface ExplorerUnit { id: string; nama: string; status: "aktif" | "nonaktif"; }
export interface ExplorerUser extends UserOption {
    role: { id: string; kode: string; nama: string; aktif: boolean; in_catalog: boolean } | null;
}
export interface PermissionSource {
    id: string;
    kind: "role" | "grant" | "deny";
    label: string;
    unit: ExplorerUnit | null;
    alasan: string | null;
    effective: boolean;
}
export interface EffectivePermissionRow {
    id: string | null;
    kode: string;
    keterangan: string | null;
    scope: "global" | "unit";
    aktif: boolean;
    decision: { allowed: boolean; permission: string; reason: string; roles: string[]; grants: string[]; denies: string[] };
    status: string;
    explanation: string;
    sources: PermissionSource[];
}
export interface ExplorerPagination { page: number; prev_page_url: string | null; next_page_url: string | null; }
export interface EffectivePermissionIndexProps {
    selectedUser: ExplorerUser | null;
    selectedUnit: ExplorerUnit | null;
    permissions: EffectivePermissionRow[];
    diagnostics: EffectivePermissionRow[];
    pagination: ExplorerPagination;
    diagnosticPagination: ExplorerPagination;
    filters: { user_id: string | null; unit_id: string | null; q: string; scope: "" | "global" | "unit" };
}
