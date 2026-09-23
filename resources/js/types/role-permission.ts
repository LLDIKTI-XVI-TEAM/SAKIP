export type RoleOption = { id: string; kode: string; nama: string };
export type PermissionRow = {
    id: string;
    kode: string;
    keterangan: string | null;
    butuh_scope: "global" | "unit";
    aktif: boolean;
    attached: boolean;
    editable: boolean;
    non_editable_reason: "scoped" | "inactive" | "unknown" | null;
};
export type RolePermissionStatus = "added" | "revoked" | "unchanged";
export type ChangeRolePermissionForm = {
    permission_id: string;
    operation: "add" | "revoke";
    alasan: string;
    expected_state: string;
};
export interface RolePermissionIndexProps {
    roles: RoleOption[];
    selectedRole: RoleOption | null;
    expectedState: string | null;
    permissions: PermissionRow[];
    pagination: {
        page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { role: string | null; view: "attached" | "available"; q: string };
    can: { manageRolePermissions: boolean };
    affectsActorRole: boolean;
    receiptId: string | null;
}
