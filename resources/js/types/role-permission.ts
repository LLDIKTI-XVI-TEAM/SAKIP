import type { PaginationLinkItem } from "@/Components/Pagination";

export type RoleOption = {
    id: string;
    kode: string;
    nama: string;
    aktif: boolean;
    jumlah_izin: number;
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
        links: PaginationLinkItem[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { role: string | null; q: string };
    can: { viewRolePermissions: boolean };
}
