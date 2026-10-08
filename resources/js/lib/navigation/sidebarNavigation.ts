import type { ComponentType } from 'react';
import type { SharedPageProps } from '@/types/auth';
import {
    LayoutDashboard,
    Target,
    CalendarDays,
    ChartNoAxesCombined,
    Database,
    ShieldCheck,
    Settings2,
    BookOpen,
    Layers,
    FileText,
    CalendarRange,
    FileSpreadsheet,
    CheckCircle2,
    Building2,
    UserCheck,
    UserPlus,
    KeyRound,
    Ban,
    Search,
    Settings,
    HardDrive,
} from 'lucide-react';

export type LucideIconType = ComponentType<{
    className?: string;
    'aria-hidden'?: boolean | 'true' | 'false';
}>;

export interface NavItemConfig {
    id: string;
    label: string;
    href: string;
    icon: LucideIconType;
    canKey: keyof NonNullable<SharedPageProps['auth']['can']>;
    matchPatterns?: (path: string) => boolean;
}

export interface NavGroupConfig {
    id: string;
    label: string;
    icon: LucideIconType;
    children: NavItemConfig[];
}

export type NavEntryConfig =
    | { type: 'item'; item: NavItemConfig }
    | { type: 'group'; group: NavGroupConfig };

export interface SidebarNavItem {
    id: string;
    label: string;
    href: string;
    icon: LucideIconType;
    visible: boolean;
    matchPatterns?: (path: string) => boolean;
}

export interface SidebarNavGroup {
    id: string;
    label: string;
    icon: LucideIconType;
    children: SidebarNavItem[];
}

export type SidebarNavEntry =
    | { type: 'item'; item: SidebarNavItem }
    | { type: 'group'; group: SidebarNavGroup };

/**
 * Skema kanonikal navigasi SAKIP:
 * - 1 item direct: Dashboard
 * - 6 grup berfitur sesuai hierarki bisnis SAKIP (18 child items)
 */
export const SIDEBAR_NAVIGATION_SCHEMA: NavEntryConfig[] = [
    {
        type: 'item',
        item: {
            id: 'dashboard',
            label: 'Dashboard',
            href: '/dashboard',
            icon: LayoutDashboard,
            canKey: 'dashboard',
            matchPatterns: (path: string): boolean => path === '/dashboard' || path.startsWith('/dashboard/'),
        },
    },
    {
        type: 'group',
        group: {
            id: 'perencanaan-kinerja',
            label: 'Perencanaan Kinerja',
            icon: Target,
            children: [
                {
                    id: 'regulasi',
                    label: 'Dasar Aturan',
                    href: '/regulasi',
                    icon: BookOpen,
                    canKey: 'regulasi',
                    matchPatterns: (path: string): boolean => path === '/regulasi' || path.startsWith('/regulasi/'),
                },
                {
                    id: 'renstra',
                    label: 'Master Renstra',
                    href: '/renstra',
                    icon: Layers,
                    canKey: 'renstra',
                    matchPatterns: (path: string): boolean => path === '/renstra' || path.startsWith('/renstra/'),
                },
                {
                    id: 'sasaran-indikator',
                    label: 'Sasaran & Indikator',
                    href: '/perencanaan/sasaran-indikator',
                    icon: Target,
                    canKey: 'sasaranIndikator',
                    matchPatterns: (path: string): boolean =>
                        path === '/perencanaan/sasaran-indikator' ||
                        path.startsWith('/perencanaan/sasaran-indikator/') ||
                        path.startsWith('/perencanaan/sasaran/') ||
                        path.startsWith('/perencanaan/indikator/') ||
                        path.startsWith('/indikator/') ||
                        path === '/penanggung-jawab' ||
                        path.startsWith('/penanggung-jawab/'),
                },
                {
                    id: 'perjanjian-kinerja',
                    label: 'Perjanjian Kinerja',
                    href: '/perjanjian-kinerja',
                    icon: FileText,
                    canKey: 'pk',
                    matchPatterns: (path: string): boolean =>
                        path === '/perjanjian-kinerja' || path.startsWith('/perjanjian-kinerja/'),
                },
            ],
        },
    },
    {
        type: 'group',
        group: {
            id: 'siklus-periode',
            label: 'Siklus & Periode',
            icon: CalendarDays,
            children: [
                {
                    id: 'periode',
                    label: 'Master Periode',
                    href: '/periode',
                    icon: CalendarDays,
                    canKey: 'periode',
                    matchPatterns: (path: string): boolean => path === '/periode' || path.startsWith('/periode/'),
                },
                {
                    id: 'jadwal',
                    label: 'Jadwal Tahunan',
                    href: '/jadwal',
                    icon: CalendarRange,
                    canKey: 'jadwal',
                    matchPatterns: (path: string): boolean => path === '/jadwal' || path.startsWith('/jadwal/'),
                },
            ],
        },
    },
    {
        type: 'group',
        group: {
            id: 'pelaksanaan-evaluasi',
            label: 'Pelaksanaan & Evaluasi',
            icon: ChartNoAxesCombined,
            children: [
                {
                    id: 'pengukuran',
                    label: 'Pengukuran Kinerja',
                    href: '/pengukuran',
                    icon: FileSpreadsheet,
                    canKey: 'pengukuran',
                    matchPatterns: (path: string): boolean => path === '/pengukuran' || path.startsWith('/pengukuran/'),
                },
                {
                    id: 'verifikasi',
                    label: 'Verifikasi & Pengesahan',
                    href: '/verifikasi',
                    icon: CheckCircle2,
                    canKey: 'verifikasi',
                    matchPatterns: (path: string): boolean => path === '/verifikasi' || path.startsWith('/verifikasi/'),
                },
            ],
        },
    },
    {
        type: 'group',
        group: {
            id: 'master-data',
            label: 'Master Data & Referensi',
            icon: Database,
            children: [
                {
                    id: 'unit',
                    label: 'Master Unit',
                    href: '/unit',
                    icon: Building2,
                    canKey: 'unit',
                    matchPatterns: (path: string): boolean => path === '/unit' || path.startsWith('/unit/'),
                },
                {
                    id: 'jenis-berkas',
                    label: 'Persyaratan Berkas',
                    href: '/jenis-berkas',
                    icon: FileText,
                    canKey: 'jenisBerkas',
                    matchPatterns: (path: string): boolean =>
                        path === '/jenis-berkas' || path.startsWith('/jenis-berkas/'),
                },
            ],
        },
    },
    {
        type: 'group',
        group: {
            id: 'manajemen-akses',
            label: 'Manajemen Akses',
            icon: ShieldCheck,
            children: [
                {
                    id: 'aktivasi',
                    label: 'Aktivasi Pengguna',
                    href: '/akses/aktivasi',
                    icon: UserCheck,
                    canKey: 'aktivasi',
                    matchPatterns: (path: string): boolean =>
                        path === '/akses/aktivasi' || path.startsWith('/akses/aktivasi/'),
                },
                {
                    id: 'peran',
                    label: 'Penetapan Peran',
                    href: '/akses/peran',
                    icon: UserPlus,
                    canKey: 'assignRole',
                    matchPatterns: (path: string): boolean =>
                        path === '/akses/peran' || path.startsWith('/akses/peran/'),
                },
                {
                    id: 'izin-peran',
                    label: 'Peran & Izin',
                    href: '/akses/izin-peran',
                    icon: KeyRound,
                    canKey: 'viewRolePermissions',
                    matchPatterns: (path: string): boolean =>
                        path === '/akses/izin-peran' || path.startsWith('/akses/izin-peran/'),
                },
                {
                    id: 'grant',
                    label: 'Izin Unit (Grant)',
                    href: '/akses/grant',
                    icon: ShieldCheck,
                    canKey: 'grant',
                    matchPatterns: (path: string): boolean => path === '/akses/grant' || path.startsWith('/akses/grant/'),
                },
                {
                    id: 'deny',
                    label: 'Pembatasan Izin',
                    href: '/akses/deny',
                    icon: Ban,
                    canKey: 'manageDeny',
                    matchPatterns: (path: string): boolean => path === '/akses/deny' || path.startsWith('/akses/deny/'),
                },
                {
                    id: 'jelaskan-izin',
                    label: 'Izin Pengguna',
                    href: '/akses/jelaskan-izin',
                    icon: Search,
                    canKey: 'viewEffectivePermissions',
                    matchPatterns: (path: string): boolean =>
                        path === '/akses/jelaskan-izin' || path.startsWith('/akses/jelaskan-izin/'),
                },
            ],
        },
    },
    {
        type: 'group',
        group: {
            id: 'pengaturan-sistem',
            label: 'Pengaturan Sistem',
            icon: Settings2,
            children: [
                {
                    id: 'pengaturan',
                    label: 'Pengaturan',
                    href: '/pengaturan',
                    icon: Settings,
                    canKey: 'pengaturan',
                    // Menjamin /pengaturan/storage tidak mencocokkan Pengaturan
                    matchPatterns: (path: string): boolean =>
                        (path === '/pengaturan' || path.startsWith('/pengaturan/')) &&
                        !path.startsWith('/pengaturan/storage'),
                },
                {
                    id: 'storage-policy',
                    label: 'Kebijakan Storage',
                    href: '/pengaturan/storage',
                    icon: HardDrive,
                    canKey: 'storagePolicy',
                    matchPatterns: (path: string): boolean =>
                        path === '/pengaturan/storage' || path.startsWith('/pengaturan/storage/'),
                },
            ],
        },
    },
];

/**
 * Menormalkan URL agar konsisten saat matching route:
 * - Menghilangkan query string dan hash
 * - Menghilangkan trailing slash (kecuali root '/')
 */
export function normalizePath(url: string | null | undefined): string {
    if (!url) return '/';
    const withoutHash = url.split('#')[0] ?? '';
    const withoutQuery = withoutHash.split('?')[0] ?? '';
    const trimmed = withoutQuery.replace(/\/+$/, '');
    return trimmed === '' ? '/' : trimmed;
}

/**
 * Mengecek apakah item navigasi sedang aktif untuk currentPath.
 * Menerima objek SidebarNavItem/NavItemConfig atau URL target string.
 */
export function isNavItemActive(
    itemOrHref: SidebarNavItem | NavItemConfig | string,
    currentPath: string
): boolean {
    const normalizedCurrent = normalizePath(currentPath);

    if (typeof itemOrHref === 'string') {
        const normalizedTarget = normalizePath(itemOrHref);
        return normalizedCurrent === normalizedTarget || normalizedCurrent.startsWith(`${normalizedTarget}/`);
    }

    if (itemOrHref.matchPatterns) {
        return itemOrHref.matchPatterns(normalizedCurrent);
    }

    const normalizedTarget = normalizePath(itemOrHref.href);
    return normalizedCurrent === normalizedTarget || normalizedCurrent.startsWith(`${normalizedTarget}/`);
}

/**
 * Menyusun data navigasi terkelompok berdasarkan capability server (auth.can).
 * Visibilitas semata-mata mengikuti capability server, bukan nama role.
 */
export function getSidebarNavEntries(
    can: Partial<NonNullable<SharedPageProps['auth']['can']>> | undefined
): SidebarNavEntry[] {
    const c = can ?? {};

    return SIDEBAR_NAVIGATION_SCHEMA.map((entry): SidebarNavEntry => {
        if (entry.type === 'item') {
            return {
                type: 'item',
                item: {
                    id: entry.item.id,
                    label: entry.item.label,
                    href: entry.item.href,
                    icon: entry.item.icon,
                    visible: Boolean(c[entry.item.canKey]),
                    matchPatterns: entry.item.matchPatterns,
                },
            };
        }

        const visibleChildren: SidebarNavItem[] = entry.group.children
            .filter((child) => Boolean(c[child.canKey]))
            .map((child) => ({
                id: child.id,
                label: child.label,
                href: child.href,
                icon: child.icon,
                visible: true,
                matchPatterns: child.matchPatterns,
            }));

        return {
            type: 'group',
            group: {
                id: entry.group.id,
                label: entry.group.label,
                icon: entry.group.icon,
                children: visibleChildren,
            },
        };
    });
}

/**
 * Mencari item aktif dan group aktif berdasarkan url saat ini.
 */
export function findActiveNavigation(
    entries: SidebarNavEntry[],
    url: string | null | undefined
): {
    activeItemId: string | null;
    activeGroupId: string | null;
    activeItem: SidebarNavItem | null;
    activeGroup: SidebarNavGroup | null;
} {
    const path = normalizePath(url);

    for (const entry of entries) {
        if (entry.type === 'item') {
            if (entry.item.visible && isNavItemActive(entry.item, path)) {
                return {
                    activeItemId: entry.item.id,
                    activeGroupId: null,
                    activeItem: entry.item,
                    activeGroup: null,
                };
            }
        } else {
            for (const child of entry.group.children) {
                if (child.visible && isNavItemActive(child, path)) {
                    return {
                        activeItemId: child.id,
                        activeGroupId: entry.group.id,
                        activeItem: child,
                        activeGroup: entry.group,
                    };
                }
            }
        }
    }

    return {
        activeItemId: null,
        activeGroupId: null,
        activeItem: null,
        activeGroup: null,
    };
}
