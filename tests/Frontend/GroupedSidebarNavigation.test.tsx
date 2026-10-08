import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import type { SharedPageProps } from '@/types/auth';
import {
    findActiveNavigation,
    getSidebarNavEntries,
    isNavItemActive,
    normalizePath,
    SIDEBAR_NAVIGATION_SCHEMA,
} from '@/lib/navigation/sidebarNavigation';

let mockUrl = '/dashboard';
let mockCan: Partial<NonNullable<SharedPageProps['auth']['can']>> = {
    dashboard: true,
    regulasi: true,
    renstra: true,
    sasaranIndikator: true,
    pk: true,
    periode: true,
    jadwal: true,
    pengukuran: true,
    verifikasi: true,
    unit: true,
    jenisBerkas: true,
    aktivasi: true,
    assignRole: true,
    viewRolePermissions: true,
    grant: true,
    manageDeny: true,
    viewEffectivePermissions: true,
    pengaturan: true,
    storagePolicy: true,
};
let mockRole = 'superadmin';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...original,
        Link: ({ children, href, onClick, ...props }: { children: React.ReactNode; href?: string; onClick?: () => void }) => (
            <a href={href} onClick={onClick} {...props}>
                {children}
            </a>
        ),
        usePage: () => ({
            props: {
                auth: {
                    user: { id: 'user-qa', nama: 'Auditor QA', email: 'qa@sakip.test', status: 'aktif', role: mockRole },
                    can: mockCan,
                },
                flash: {},
                pengaturan: { 'aplikasi.nama': 'SAKIP LLDIKTI XVI' },
            },
            url: mockUrl,
        }),
        useForm: () => ({
            post: vi.fn(),
            processing: false,
        }),
    };
});

vi.mock('@/hooks/useAuthRecovery', () => ({
    useAuthRecovery: () => ({
        recoveryState: null,
        resetRecoveryState: vi.fn(),
    }),
}));

beforeAll(() => {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        value: vi.fn().mockImplementation((query: string) => ({
            matches: true,
            media: query,
            onchange: null,
            addListener: vi.fn(),
            removeListener: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            dispatchEvent: vi.fn(),
        })),
    });
});

afterEach(() => {
    cleanup();
    mockUrl = '/dashboard';
    mockRole = 'superadmin';
    mockCan = {
        dashboard: true,
        regulasi: true,
        renstra: true,
        sasaranIndikator: true,
        pk: true,
        periode: true,
        jadwal: true,
        pengukuran: true,
        verifikasi: true,
        unit: true,
        jenisBerkas: true,
        aktivasi: true,
        assignRole: true,
        viewRolePermissions: true,
        grant: true,
        manageDeny: true,
        viewEffectivePermissions: true,
        pengaturan: true,
        storagePolicy: true,
    };
});

describe('Test A — Menu Grouping & Inventory Integrity', () => {
    it('memiliki total 7 entri navigasi utama (1 direct item + 6 groups)', () => {
        expect(SIDEBAR_NAVIGATION_SCHEMA).toHaveLength(7);
        const [dashboard, perencanaan, siklus, pelaksanaan, masterData, manajemenAkses, pengaturan] = SIDEBAR_NAVIGATION_SCHEMA;

        expect(dashboard.type).toBe('item');
        if (dashboard.type === 'item') {
            expect(dashboard.item.label).toBe('Dashboard');
            expect(dashboard.item.href).toBe('/dashboard');
            expect(dashboard.item.canKey).toBe('dashboard');
        }

        expect(perencanaan.type).toBe('group');
        if (perencanaan.type === 'group') {
            expect(perencanaan.group.label).toBe('Perencanaan Kinerja');
            expect(perencanaan.group.children.map((c) => c.label)).toEqual([
                'Dasar Aturan',
                'Master Renstra',
                'Sasaran & Indikator',
                'Perjanjian Kinerja',
            ]);
            expect(perencanaan.group.children.map((c) => c.canKey)).toEqual([
                'regulasi',
                'renstra',
                'sasaranIndikator',
                'pk',
            ]);
        }

        expect(siklus.type).toBe('group');
        if (siklus.type === 'group') {
            expect(siklus.group.label).toBe('Siklus & Periode');
            expect(siklus.group.children.map((c) => c.label)).toEqual(['Master Periode', 'Jadwal Tahunan']);
            expect(siklus.group.children.map((c) => c.canKey)).toEqual(['periode', 'jadwal']);
        }

        expect(pelaksanaan.type).toBe('group');
        if (pelaksanaan.type === 'group') {
            expect(pelaksanaan.group.label).toBe('Pelaksanaan & Evaluasi');
            expect(pelaksanaan.group.children.map((c) => c.label)).toEqual(['Pengukuran Kinerja', 'Verifikasi & Pengesahan']);
            expect(pelaksanaan.group.children.map((c) => c.canKey)).toEqual(['pengukuran', 'verifikasi']);
        }

        expect(masterData.type).toBe('group');
        if (masterData.type === 'group') {
            expect(masterData.group.label).toBe('Master Data & Referensi');
            expect(masterData.group.children.map((c) => c.label)).toEqual(['Master Unit', 'Persyaratan Berkas']);
            expect(masterData.group.children.map((c) => c.canKey)).toEqual(['unit', 'jenisBerkas']);
        }

        expect(manajemenAkses.type).toBe('group');
        if (manajemenAkses.type === 'group') {
            expect(manajemenAkses.group.label).toBe('Manajemen Akses');
            expect(manajemenAkses.group.children.map((c) => c.label)).toEqual([
                'Aktivasi Pengguna',
                'Penetapan Peran',
                'Peran & Izin',
                'Izin Unit (Grant)',
                'Pembatasan Izin',
                'Izin Pengguna',
            ]);
            expect(manajemenAkses.group.children.map((c) => c.canKey)).toEqual([
                'aktivasi',
                'assignRole',
                'viewRolePermissions',
                'grant',
                'manageDeny',
                'viewEffectivePermissions',
            ]);
        }

        expect(pengaturan.type).toBe('group');
        if (pengaturan.type === 'group') {
            expect(pengaturan.group.label).toBe('Pengaturan Sistem');
            expect(pengaturan.group.children.map((c) => c.label)).toEqual(['Pengaturan', 'Kebijakan Storage']);
            expect(pengaturan.group.children.map((c) => c.canKey)).toEqual(['pengaturan', 'storagePolicy']);
        }
    });

    it('tidak memiliki duplikasi label, id, atau href di antara child menu', () => {
        const allItems = SIDEBAR_NAVIGATION_SCHEMA.flatMap((entry) =>
            entry.type === 'item' ? [entry.item] : entry.group.children
        );
        const ids = new Set<string>();
        const labels = new Set<string>();
        const hrefs = new Set<string>();

        allItems.forEach((item) => {
            expect(ids.has(item.id)).toBe(false);
            expect(labels.has(item.label)).toBe(false);
            expect(hrefs.has(item.href)).toBe(false);
            ids.add(item.id);
            labels.add(item.label);
            hrefs.add(item.href);
        });

        expect(allItems).toHaveLength(19); // 1 direct item + 18 submenus
    });
});

describe('Test B — Expand & Collapse Behavior', () => {
    it('grup yang tidak aktif berada dalam kondisi collapsed secara default', () => {
        mockUrl = '/dashboard';
        render(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        const perencanaanBtn = within(nav).getByRole('button', { name: 'Perencanaan Kinerja' });

        expect(perencanaanBtn.getAttribute('aria-expanded')).toBe('false');
        const submenuPanel = document.getElementById(perencanaanBtn.getAttribute('aria-controls')!);
        expect(submenuPanel).toBeTruthy();
        expect(submenuPanel?.hasAttribute('hidden')).toBe(true);
    });

    it('klik parent membuka submenu dan klik ulang menutupnya kembali', () => {
        mockUrl = '/dashboard';
        render(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        const perencanaanBtn = within(nav).getByRole('button', { name: 'Perencanaan Kinerja' });
        const submenuPanel = document.getElementById(perencanaanBtn.getAttribute('aria-controls')!);

        // Klik pertama: expand
        fireEvent.click(perencanaanBtn);
        expect(perencanaanBtn.getAttribute('aria-expanded')).toBe('true');
        expect(submenuPanel?.hasAttribute('hidden')).toBe(false);
        expect(within(submenuPanel!).getByRole('link', { name: 'Dasar Aturan' })).toBeTruthy();

        // Klik kedua: collapse
        fireEvent.click(perencanaanBtn);
        expect(perencanaanBtn.getAttribute('aria-expanded')).toBe('false');
        expect(submenuPanel?.hasAttribute('hidden')).toBe(true);
    });

    it('mendukung multiple groups terbuka secara bersamaan (tidak memaksa accordion tunggal)', () => {
        mockUrl = '/dashboard';
        render(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        const perencanaanBtn = within(nav).getByRole('button', { name: 'Perencanaan Kinerja' });
        const aksesBtn = within(nav).getByRole('button', { name: 'Manajemen Akses' });

        fireEvent.click(perencanaanBtn);
        fireEvent.click(aksesBtn);

        expect(perencanaanBtn.getAttribute('aria-expanded')).toBe('true');
        expect(aksesBtn.getAttribute('aria-expanded')).toBe('true');

        const panelPerencanaan = document.getElementById(perencanaanBtn.getAttribute('aria-controls')!);
        const panelAkses = document.getElementById(aksesBtn.getAttribute('aria-controls')!);

        expect(panelPerencanaan?.hasAttribute('hidden')).toBe(false);
        expect(panelAkses?.hasAttribute('hidden')).toBe(false);
    });
});

describe('Test C — Active Navigation & Nested Route Matching', () => {
    it('mengenali rute Renstra index dan nested detail secara benar', () => {
        mockUrl = '/renstra/a9c1e784-1111-2222-3333-444455556666/edit';
        render(<AuthenticatedLayout><div>Edit Renstra</div></AuthenticatedLayout>);

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        const parentBtn = within(nav).getByRole('button', { name: 'Perencanaan Kinerja' });

        // Parent otomatis expanded karena URL cocok dengan salah satu child-nya
        expect(parentBtn.getAttribute('aria-expanded')).toBe('true');

        const renstraLink = within(nav).getByRole('link', { name: 'Master Renstra' });
        expect(renstraLink.getAttribute('aria-current')).toBe('page');

        // Submenu lain di grup yang sama tidak boleh memiliki aria-current=page
        const aturanLink = within(nav).getByRole('link', { name: 'Dasar Aturan' });
        expect(aturanLink.getAttribute('aria-current')).toBeNull();
    });

    it('mengenali rute nested indikator dan penanggung jawab ke Sasaran & Indikator', () => {
        const testUrls = [
            '/perencanaan/sasaran-indikator',
            '/perencanaan/sasaran-indikator/tambah',
            '/perencanaan/indikator/ind-1234/editor',
            '/perencanaan/indikator/ind-1234/penanggung-jawab',
            '/penanggung-jawab',
        ];

        const entries = getSidebarNavEntries(mockCan);
        testUrls.forEach((url) => {
            const active = findActiveNavigation(entries, url);
            expect(active.activeGroupId).toBe('perencanaan-kinerja');
            expect(active.activeItemId).toBe('sasaran-indikator');
        });
    });

    it('mencegah false positive antara /pengaturan dan /pengaturan/storage', () => {
        const entries = getSidebarNavEntries(mockCan);

        // Case 1: /pengaturan/storage
        const activeStorage = findActiveNavigation(entries, '/pengaturan/storage');
        expect(activeStorage.activeGroupId).toBe('pengaturan-sistem');
        expect(activeStorage.activeItemId).toBe('storage-policy');

        // Case 2: /pengaturan
        const activePengaturan = findActiveNavigation(entries, '/pengaturan');
        expect(activePengaturan.activeGroupId).toBe('pengaturan-sistem');
        expect(activePengaturan.activeItemId).toBe('pengaturan');

        // Case 3: /pengaturan/sistem/umum (sub-path dari pengaturan, bukan storage)
        const activeSubPengaturan = findActiveNavigation(entries, '/pengaturan/sistem/umum');
        expect(activeSubPengaturan.activeGroupId).toBe('pengaturan-sistem');
        expect(activeSubPengaturan.activeItemId).toBe('pengaturan');
    });

    it('menangani query parameters dan trailing slashes secara aman', () => {
        expect(normalizePath('/renstra/?tab=aktif&tahun=2026')).toBe('/renstra');
        expect(isNavItemActive('/renstra', '/renstra/?tab=aktif')).toBe(true);
        expect(isNavItemActive('/renstra', '/renstra/')).toBe(true);
    });
});

describe('Test D — Permission & Capability Filtering', () => {
    it('grup tidak dirender sama sekali jika seluruh child memiliki capability false', () => {
        mockCan = {
            dashboard: true,
            regulasi: false,
            renstra: false,
            sasaranIndikator: false,
            pk: false,
        };
        render(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        expect(within(nav).queryByRole('button', { name: 'Perencanaan Kinerja' })).toBeNull();
    });

    it('grup dirender jika minimal satu child memiliki capability true', () => {
        mockCan = {
            dashboard: true,
            regulasi: true,
            renstra: false,
            sasaranIndikator: false,
            pk: false,
        };
        render(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        const parentBtn = within(nav).getByRole('button', { name: 'Perencanaan Kinerja' });
        expect(parentBtn).toBeTruthy();

        // Buka parent
        fireEvent.click(parentBtn);
        expect(within(nav).getByRole('link', { name: 'Dasar Aturan' })).toBeTruthy();
        expect(within(nav).queryByRole('link', { name: 'Master Renstra' })).toBeNull();
        expect(within(nav).queryByRole('link', { name: 'Sasaran & Indikator' })).toBeNull();
        expect(within(nav).queryByRole('link', { name: 'Perjanjian Kinerja' })).toBeNull();
    });

    it('fail-closed: jika seluruh can false, navigasi utama tidak merender menu apapun', () => {
        mockCan = {};
        render(<AuthenticatedLayout><div>Konten Kosong</div></AuthenticatedLayout>);

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        expect(within(nav).queryByRole('link')).toBeNull();
        expect(within(nav).queryByRole('button')).toBeNull();
    });
});

describe('Test E — No Role-Based Bypass', () => {
    it('role superadmin tetap tidak menampilkan menu jika auth.can bernilai false', () => {
        mockRole = 'superadmin';
        mockCan = {
            dashboard: true,
            grant: false,
            manageDeny: false,
        };
        render(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        expect(within(nav).queryByRole('link', { name: 'Izin Unit (Grant)' })).toBeNull();
        expect(within(nav).queryByRole('link', { name: 'Pembatasan Izin' })).toBeNull();
    });

    it('role pegawai dapat melihat menu jika auth.can bernilai true tanpa gating nama peran', () => {
        mockRole = 'pegawai';
        mockCan = {
            dashboard: true,
            regulasi: true,
        };
        render(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        const parentBtn = within(nav).getByRole('button', { name: 'Perencanaan Kinerja' });
        expect(parentBtn).toBeTruthy();
        fireEvent.click(parentBtn);
        expect(within(nav).getByRole('link', { name: 'Dasar Aturan' })).toBeTruthy();
    });
});

describe('Test F — Mobile Navigation Drawer Integration', () => {
    it('drawer mobile merender grouped sidebar dan mendukung expand/collapse serta link click', () => {
        render(<AuthenticatedLayout><div>Konten Mobile</div></AuthenticatedLayout>);

        // Buka mobile drawer via tombol hamburger
        const openBtn = screen.getByRole('button', { name: 'Buka navigasi' });
        fireEvent.click(openBtn);

        // Cari aside drawer mobile
        const drawer = document.getElementById('application-navigation');
        expect(drawer).toBeTruthy();
        expect(drawer?.className).toContain('translate-x-0');

        // Dalam drawer, navigasi mobile merender grup
        const mobileNav = within(drawer!).getByRole('navigation', { name: 'Navigasi utama' });
        const perencanaanBtn = within(mobileNav).getByRole('button', { name: 'Perencanaan Kinerja' });
        expect(perencanaanBtn).toBeTruthy();

        // Expand di dalam mobile drawer
        fireEvent.click(perencanaanBtn);
        const childLink = within(mobileNav).getByRole('link', { name: 'Dasar Aturan' });
        expect(childLink).toBeTruthy();

        // Klik link anak harus memicu navigasi dan menutup drawer (translate-x-full)
        fireEvent.click(childLink);
        expect(drawer?.className).toContain('-translate-x-full');
    });
});

describe('Test H — Accessibility Semantics', () => {
    it('memiliki atribut ARIA semantik yang lengkap dan tidak ada nested interactive elements', () => {
        mockUrl = '/jadwal';
        render(<AuthenticatedLayout><div>Konten Aksesibilitas</div></AuthenticatedLayout>);

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        const parentBtn = within(nav).getByRole('button', { name: 'Siklus & Periode' });

        expect(parentBtn.getAttribute('type')).toBe('button');
        expect(parentBtn.getAttribute('aria-expanded')).toBe('true');
        const controlsId = parentBtn.getAttribute('aria-controls');
        expect(controlsId).toBeTruthy();

        const submenu = document.getElementById(controlsId!);
        expect(submenu).toBeTruthy();
        expect(submenu?.getAttribute('aria-labelledby')).toBe(parentBtn.id);

        const jadwalLink = within(submenu!).getByRole('link', { name: 'Jadwal Tahunan' });
        expect(jadwalLink.getAttribute('aria-current')).toBe('page');

        // Pastikan tidak ada tombol di dalam link atau link di dalam tombol
        const buttons = nav.querySelectorAll('button');
        buttons.forEach((btn) => {
            expect(btn.querySelector('a')).toBeNull();
            expect(btn.querySelector('button')).toBeNull();
        });

        const links = nav.querySelectorAll('a');
        links.forEach((a) => {
            expect(a.querySelector('button')).toBeNull();
            expect(a.querySelector('a')).toBeNull();
        });
    });
});
