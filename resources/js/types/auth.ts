import type { PageProps } from "@inertiajs/core";

export interface RoleAssignmentOutcome {
    receipt_id: string;
    status: 'assigned' | 'changed' | 'unchanged';
    has_active_pj: boolean;
}

export interface AuthUser {
    id: string;
    nama: string;
    email: string;
    status: 'aktif' | 'nonaktif';
    role: string | null;
}

export interface SharedPageProps extends PageProps {
    auth: {
        user: AuthUser | null;
        can: {
            dashboard: boolean;
            pengukuran: boolean;
            verifikasi: boolean;
            rencanaAksi?: boolean;
            aktivasi: boolean;
            regulasi: boolean;
            renstra?: boolean;
            'renstra:create'?: boolean;
            'renstra:read'?: boolean;
            'renstra:update'?: boolean;
            'renstra:delete'?: boolean;
            assignRole: boolean;
            manageDeny: boolean;
            unit: boolean;
            grant: boolean;
            pengaturan: boolean;
            'pengaturan:update'?: boolean;
            viewRolePermissions: boolean;
            viewEffectivePermissions?: boolean;
            jenisBerkas?: boolean;
            storagePolicy?: boolean;
            storagePolicyUpdate?: boolean;
            sasaranIndikator?: boolean;
            pk?: boolean;
            periode?: boolean;
            jadwal?: boolean;
            'pk:create'?: boolean;
            'pk:update'?: boolean;
        };
    };
    flash?: {
        success?: string | null;
        error?: string | null;
        warning?: string | null;
        message?: string | null;
        [key: string]: unknown;
    };
    pengaturan?: Record<string, string | number | boolean | null>;
}

declare module '@inertiajs/core' {
    interface PageFlashData { success?: string | null; error?: string | null; warning?: string | null; message?: string | null; authRecoveryNotice?: 'no_replay'; logoutNotice?: 'sso_unavailable'; roleAssignmentOutcome?: RoleAssignmentOutcome; indikatorMutation?: unknown }
}
