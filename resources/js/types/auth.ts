import type { PageProps } from "@inertiajs/core";

export interface AuthUser {
    id: string;
    nama: string;
    email: string;
    is_active: boolean;
    role: string | null;
}

export interface SharedPageProps extends PageProps {
    auth: {
        user: AuthUser | null;
        can: {
            dashboard: boolean;
            pengukuran: boolean;
            verifikasi: boolean;
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
            manageRolePermissions: boolean;
            jenisBerkas?: boolean;
            storagePolicy?: boolean;
            storagePolicyUpdate?: boolean;
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
    interface PageFlashData { authRecoveryNotice?: 'no_replay' }
}
