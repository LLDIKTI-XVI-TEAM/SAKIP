export type RoleOption = {
    id: string;
    kode: string;
    nama: string;
    aktif: boolean;
};

export type PermissionRow = {
    id: string;
    kode: string;
    keterangan: string | null;
    butuh_scope: "global" | "unit";
    aktif: boolean;
    in_catalog: boolean;
};

export interface RolePermissionIndexProps {
    roles: RoleOption[];
    selectedRole: RoleOption | null;
    permissions: PermissionRow[];
    pagination: {
        page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { role: string | null; q: string };
    can: { viewRolePermissions: boolean };
}
