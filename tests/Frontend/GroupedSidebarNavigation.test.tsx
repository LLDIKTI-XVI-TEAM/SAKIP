import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import type { SharedPageProps } from '@/types/auth';
import {
    findActiveNavigation,
    getSidebarNavEntries,
    isNavItemActive,
    normalizePath,
    SIDEBAR_NAVIGATION_SCHEMA,
} from '@/lib/navigation/sidebarNavigation';
import { createViewportMock } from './viewportFixtures';

const DESKTOP_WIDTH = 1440;
const MOBILE_WIDTH = 390;
const viewport = createViewportMock(DESKTOP_WIDTH);

let mockUrl = '/dashboard';
let mockCan: Partial<NonNullable<SharedPageProps['auth']['can']>> = {
    dashboard: true,
    regulasi: true,
    renstra: true,
    sasaranIndikator: true,
    pk: true,
    periode: true,
    jadwal: true,
    rencanaAksi: true,
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

beforeAll(() => viewport.install());
afterAll(() => viewport.restore());
beforeEach(() => viewport.reset(DESKTOP_WIDTH));

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
        rencanaAksi: true,
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
            expect(pelaksanaan.group.children.map((c) => c.label)).toEqual(['Rencana Aksi', 'Pengukuran Kinerja', 'Verifikasi & Pengesahan']);
            expect(pelaksanaan.group.children.map((c) => c.canKey)).toEqual(['rencanaAksi', 'pengukuran', 'verifikasi']);
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

        expect(allItems).toHaveLength(20); // 1 direct item + 19 submenus
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

const renderLayout = () => render(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);
const getDrawer = () => document.getElementById('application-navigation') as HTMLElement;
const getMainColumn = () => document.getElementById('main-content')?.parentElement as HTMLElement;
const groupButton = (name: string) => screen.getByRole('button', { name });
const expectDrawerClosed = (drawer: HTMLElement) => {
    expect(drawer.getAttribute('aria-hidden')).toBe('true');
    expect(drawer.hasAttribute('inert')).toBe(true);
    expect(drawer.getAttribute('role')).toBeNull();
    expect(drawer.getAttribute('aria-modal')).toBeNull();
    expect(drawer.className).toContain('-translate-x-full');
};
const openMobileDrawer = async (user: ReturnType<typeof userEvent.setup>) => {
    const trigger = screen.getByRole('button', { name: 'Buka navigasi' });
    await user.click(trigger);
    const drawer = getDrawer();
    const closeButton = within(drawer).getByRole('button', { name: 'Tutup navigasi' });
    await waitFor(() => expect(document.activeElement).toBe(closeButton));
    return { trigger, drawer, closeButton };
};

describe('M-01 — Sinkronisasi grup aktif saat pathname berubah', () => {
    it('M01-A: navigasi antar-halaman dalam grup yang sama membuka kembali grup yang ditutup manual', () => {
        mockUrl = '/renstra';
        const { rerender } = renderLayout();
        const parent = groupButton('Perencanaan Kinerja');
        expect(parent.getAttribute('aria-expanded')).toBe('true');

        fireEvent.click(parent);
        expect(parent.getAttribute('aria-expanded')).toBe('false');

        mockUrl = '/perjanjian-kinerja';
        rerender(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);

        // Instance yang sama (tidak di-unmount) tetap menerima perubahan pathname.
        expect(groupButton('Perencanaan Kinerja')).toBe(parent);
        expect(parent.getAttribute('aria-expanded')).toBe('true');
        expect(screen.getByRole('link', { name: 'Perjanjian Kinerja' }).getAttribute('aria-current')).toBe('page');
        expect(screen.getByRole('link', { name: 'Master Renstra' }).getAttribute('aria-current')).toBeNull();
    });

    it('M01-B: penutupan manual bertahan pada rerender tanpa perubahan pathname', () => {
        mockUrl = '/renstra';
        const { rerender } = renderLayout();
        const parent = groupButton('Perencanaan Kinerja');

        fireEvent.click(parent);
        rerender(<AuthenticatedLayout><div>Konten baru</div></AuthenticatedLayout>);
        rerender(<AuthenticatedLayout><div>Konten baru lagi</div></AuthenticatedLayout>);

        expect(parent.getAttribute('aria-expanded')).toBe('false');
        expect(document.getElementById(parent.getAttribute('aria-controls')!)?.hasAttribute('hidden')).toBe(true);
    });

    it('M01-C: pindah ke grup lain membuka grup baru dan tetap mendukung multi-expand', () => {
        mockUrl = '/renstra';
        const { rerender } = renderLayout();

        mockUrl = '/jadwal';
        rerender(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);

        expect(groupButton('Siklus & Periode').getAttribute('aria-expanded')).toBe('true');
        expect(screen.getByRole('link', { name: 'Jadwal Tahunan' }).getAttribute('aria-current')).toBe('page');
        // Grup sebelumnya tidak dipaksa tertutup (bukan accordion).
        expect(groupButton('Perencanaan Kinerja').getAttribute('aria-expanded')).toBe('true');
        expect(screen.getByRole('link', { name: 'Master Renstra' }).getAttribute('aria-current')).toBeNull();
    });

    it('M01-D: perubahan query string saja tidak membuka kembali grup yang ditutup manual', () => {
        mockUrl = '/renstra?tab=aktif';
        const { rerender } = renderLayout();
        const parent = groupButton('Perencanaan Kinerja');

        fireEvent.click(parent);
        mockUrl = '/renstra?tab=arsip';
        rerender(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);

        expect(parent.getAttribute('aria-expanded')).toBe('false');
    });

    it('M01-E: urutan maju/mundur riwayat menjaga grup dan item aktif tetap sinkron', () => {
        mockUrl = '/renstra';
        const { rerender, unmount } = renderLayout();
        const parent = groupButton('Perencanaan Kinerja');

        mockUrl = '/perjanjian-kinerja';
        rerender(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);
        fireEvent.click(parent);

        // Back ke /renstra dengan instance yang sama (mis. kunjungan preserveState).
        mockUrl = '/renstra';
        rerender(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);
        expect(parent.getAttribute('aria-expanded')).toBe('true');
        expect(screen.getByRole('link', { name: 'Master Renstra' }).getAttribute('aria-current')).toBe('page');
        expect(screen.getByRole('link', { name: 'Perjanjian Kinerja' }).getAttribute('aria-current')).toBeNull();

        // Popstate Inertia memasang ulang halaman; state awal tetap mengikuti URL baru.
        unmount();
        mockUrl = '/jadwal';
        renderLayout();
        expect(groupButton('Siklus & Periode').getAttribute('aria-expanded')).toBe('true');
        expect(groupButton('Perencanaan Kinerja').getAttribute('aria-expanded')).toBe('false');
        expect(screen.getByRole('link', { name: 'Jadwal Tahunan' }).getAttribute('aria-current')).toBe('page');
    });
});

describe('M-02 — Drawer navigasi pada viewport mobile', () => {
    beforeEach(() => viewport.reset(MOBILE_WIDTH));

    it('M02-A: drawer awalnya tertutup lalu terbuka sebagai dialog modal yang menonaktifkan konten utama', async () => {
        const user = userEvent.setup();
        renderLayout();
        const drawer = getDrawer();
        expectDrawerClosed(drawer);
        expect(getMainColumn().hasAttribute('inert')).toBe(false);
        expect(screen.getByRole('main')).toBeTruthy();

        const { trigger } = await openMobileDrawer(user);

        expect(trigger.getAttribute('aria-expanded')).toBe('true');
        expect(drawer.getAttribute('role')).toBe('dialog');
        expect(drawer.getAttribute('aria-modal')).toBe('true');
        expect(drawer.getAttribute('aria-hidden')).toBeNull();
        expect(drawer.hasAttribute('inert')).toBe(false);
        expect(screen.getByRole('dialog', { name: 'Navigasi utama' })).toBe(drawer);
        expect(getMainColumn().getAttribute('aria-hidden')).toBe('true');
        expect(getMainColumn().hasAttribute('inert')).toBe(true);
        expect(screen.queryByRole('main')).toBeNull();
    });

    it('M02-B: tombol tutup di drawer menutup drawer dan mengembalikan fokus ke tombol pembuka', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { trigger, drawer, closeButton } = await openMobileDrawer(user);

        await user.click(closeButton);

        expectDrawerClosed(drawer);
        expect(document.activeElement).toBe(trigger);
        expect(trigger.getAttribute('aria-label')).toBe('Buka navigasi');
    });

    it('M02-C: Escape menutup drawer dan memulihkan fokus', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { trigger, drawer } = await openMobileDrawer(user);

        await user.keyboard('{Escape}');

        expectDrawerClosed(drawer);
        expect(document.activeElement).toBe(trigger);
    });

    it('M02-D: klik backdrop menutup drawer', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { drawer } = await openMobileDrawer(user);
        const backdrop = drawer.previousElementSibling as HTMLElement;
        expect(backdrop.getAttribute('aria-hidden')).toBe('true');

        await user.click(backdrop);

        expectDrawerClosed(drawer);
    });

    it('M02-E: Tab dan Shift+Tab berputar di dalam kontrol drawer yang terlihat', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { drawer, closeButton } = await openMobileDrawer(user);
        const lastVisibleControl = within(drawer).getByRole('button', { name: 'Pengaturan Sistem' });

        await user.tab({ shift: true });
        expect(document.activeElement).toBe(lastVisibleControl);
        // Link pada submenu tertutup tidak boleh menjadi tujuan fokus.
        expect((document.activeElement as HTMLElement).closest('[hidden]')).toBeNull();

        await user.tab();
        expect(document.activeElement).toBe(closeButton);

        await user.tab();
        expect(document.activeElement).toBe(within(drawer).getByRole('link', { name: 'Dashboard' }));
        expect(drawer.contains(document.activeElement)).toBe(true);
    });

    it('M02-F: expand grup di drawer lalu memilih submenu menutup drawer', async () => {
        const user = userEvent.setup();
        renderLayout();
        const { drawer } = await openMobileDrawer(user);
        const parent = within(drawer).getByRole('button', { name: 'Perencanaan Kinerja' });

        await user.click(parent);
        expect(parent.getAttribute('aria-expanded')).toBe('true');
        const submenu = document.getElementById(parent.getAttribute('aria-controls')!) as HTMLElement;
        const childLink = within(submenu).getByRole('link', { name: 'Dasar Aturan' });

        await user.click(childLink);

        expectDrawerClosed(drawer);
    });

    it('M02-G: viewport desktop tetap memakai sidebar statis non-modal', async () => {
        viewport.reset(DESKTOP_WIDTH);
        renderLayout();
        const drawer = getDrawer();

        expect(drawer.getAttribute('role')).toBeNull();
        expect(drawer.getAttribute('aria-modal')).toBeNull();
        expect(drawer.getAttribute('aria-hidden')).toBeNull();
        expect(drawer.hasAttribute('inert')).toBe(false);
        expect(getMainColumn().hasAttribute('inert')).toBe(false);
        expect(within(drawer).getByRole('navigation', { name: 'Navigasi utama' })).toBeTruthy();
    });

    it('perubahan viewport mobile ke desktop melepas mode modal dan listener dibersihkan saat unmount', async () => {
        const user = userEvent.setup();
        const { unmount } = renderLayout();
        const { drawer } = await openMobileDrawer(user);

        viewport.setWidth(DESKTOP_WIDTH);
        expect(drawer.getAttribute('role')).toBeNull();
        expect(drawer.getAttribute('aria-modal')).toBeNull();
        expect(getMainColumn().hasAttribute('inert')).toBe(false);

        unmount();
        expect(viewport.listenerCount()).toBe(0);
    });
});

describe('Q-02 — Pencocokan rute default dan khusus', () => {
    const activeItem = (url: string) => findActiveNavigation(getSidebarNavEntries(mockCan), url).activeItemId;
    const allItems = SIDEBAR_NAVIGATION_SCHEMA.flatMap((entry) => entry.type === 'item' ? [entry.item] : entry.group.children);

    it('setiap menu aktif pada href-nya dan rute turunannya', () => {
        allItems.forEach((item) => {
            expect(activeItem(item.href)).toBe(item.id);
            expect(activeItem(`${item.href}/detail-uji`)).toBe(item.id);
        });
    });

    it.each([
        ['/renstra', 'renstra'],
        ['/renstra/a9c1e784-1111-2222-3333-444455556666/edit', 'renstra'],
        ['/renstra-lain', null],
        ['/renstra/?tab=arsip#bagian', 'renstra'],
        ['/pengaturan', 'pengaturan'],
        ['/pengaturan/', 'pengaturan'],
        ['/pengaturan/storage', 'storage-policy'],
        ['/pengaturan/storage/', 'storage-policy'],
        ['/pengaturan/storage/riwayat', 'storage-policy'],
        ['/pengaturan/storage?tab=format', 'storage-policy'],
        ['/pengaturan/storage-lain', 'pengaturan'],
        ['/perencanaan/indikator/ind-1234/editor', 'sasaran-indikator'],
        ['/perencanaan/indikator/ind-1234/target-tahunan/2026/editor', 'sasaran-indikator'],
        ['/indikator/ind-1234/komponen', 'sasaran-indikator'],
        ['/penanggung-jawab', 'sasaran-indikator'],
        ['/penanggung-jawab-lain', null],
        ['/perencanaan/indikator', null],
        ['/akses/grant-lain', null],
        ['/dashboard?periode=2', 'dashboard'],
    ])('%s → %s', (url, expected) => {
        expect(activeItem(url)).toBe(expected);
    });

    it('tidak ada URL yang membuat dua menu sekaligus aktif', () => {
        const urls = [
            ...allItems.flatMap((item) => [item.href, `${item.href}/`, `${item.href}/x/y`, `${item.href}?q=1`]),
            '/pengaturan/storage-lain',
            '/perencanaan/indikator/ind-1/penanggung-jawab',
        ];
        urls.forEach((url) => {
            const matches = allItems.filter((item) => isNavItemActive(item, url));
            expect(matches.length, url).toBeLessThanOrEqual(1);
        });
    });
});

describe('UI-01-A — Sidebar tanpa profil dan aksi logout', () => {
    it('sidebar hanya memuat brand dan navigasi tanpa identitas pengguna atau tombol keluar', () => {
        renderLayout();
        const drawer = getDrawer();

        expect(within(drawer).getByText('SAKIP LLDIKTI XVI')).toBeTruthy();
        expect(within(drawer).getByRole('navigation', { name: 'Navigasi utama' })).toBeTruthy();
        expect(within(drawer).getByRole('link', { name: 'Dashboard' })).toBeTruthy();
        expect(within(drawer).getAllByRole('button').map((button) => button.textContent)).toEqual(expect.arrayContaining([
            'Perencanaan Kinerja', 'Siklus & Periode', 'Pelaksanaan & Evaluasi', 'Master Data & Referensi', 'Manajemen Akses', 'Pengaturan Sistem',
        ]));

        expect(drawer.textContent).not.toContain('Auditor QA');
        expect(drawer.textContent).not.toMatch(/superadmin/i);
        expect(drawer.textContent).not.toContain('Keluar dari SAKIP');
        expect(drawer.textContent).not.toContain('Keluar dari layanan terhubung');
        expect(within(drawer).queryByRole('button', { name: /Keluar|Menu akun/, hidden: true })).toBeNull();
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
