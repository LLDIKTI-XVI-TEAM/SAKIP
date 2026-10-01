import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';

let mockPengaturan: Record<string, unknown> = {
    'aplikasi.nama': 'SAKIP LLDIKTI XVI',
};
let mockUrl = '/pengaturan';
let mockCan: Record<string, boolean> = { dashboard: true, pengaturan: true };
let mockRole = 'admin';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...original,
        Link: ({ children, href, ...props }: { children: React.ReactNode; href?: string }) => (
            <a href={href} {...props}>
                {children}
            </a>
        ),
        usePage: () => ({
            props: {
                auth: {
                    user: { id: 'admin-id', nama: 'Operator QA', email: 'admin@example.test', status: 'aktif', role: mockRole },
                    can: mockCan,
                },
                flash: {},
                pengaturan: mockPengaturan,
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
    mockPengaturan = {
        'aplikasi.nama': 'SAKIP LLDIKTI XVI',
    };
    mockUrl = '/pengaturan';
    mockCan = { dashboard: true, pengaturan: true };
    mockRole = 'admin';
});

describe('AuthenticatedLayout Breadcrumbs', () => {
    it('menu Grant mengikuti capability server, tanpa gate nama peran di klien', () => {
        mockRole = 'perencanaan';
        mockCan = { dashboard: true, grant: true };
        const { rerender } = render(
            <AuthenticatedLayout>
                <div>Konten</div>
            </AuthenticatedLayout>
        );
        expect(screen.getByRole('link', { name: 'Izin Unit (Grant)' }).getAttribute('href')).toBe('/akses/grant');
        mockCan = { dashboard: true, grant: false };
        rerender(
            <AuthenticatedLayout>
                <div>Konten</div>
            </AuthenticatedLayout>
        );
        expect(screen.queryByRole('link', { name: 'Izin Unit (Grant)' })).toBeNull();
    });

    it('merender jejak navigasi Dashboard > Pengaturan tanpa menambah SAKIP', () => {
        render(
            <AuthenticatedLayout
                title="Pengaturan Sistem"
                breadcrumbs={[
                    { label: 'Dashboard', href: '/dashboard' },
                    { label: 'Pengaturan' },
                ]}
            >
                <div>Konten Pengaturan</div>
            </AuthenticatedLayout>
        );

        const nav = screen.getByRole('navigation', { name: 'Jejak navigasi' });
        expect(nav).toBeTruthy();

        // Pastikan tidak ada teks SAKIP di dalam jejak navigasi
        expect(nav.textContent).not.toContain('SAKIP');

        // Pastikan teks Dashboard dan Pengaturan ada di dalam navigasi
        expect(nav.textContent).toContain('Dashboard');
        expect(nav.textContent).toContain('Pengaturan');

        // Dashboard adalah link menuju /dashboard
        const dashboardLink = within(nav).getByRole('link', { name: 'Dashboard' });
        expect(dashboardLink.getAttribute('href')).toBe('/dashboard');

        // Pengaturan adalah halaman aktif saat ini
        const currentPage = within(nav).getByText('Pengaturan');
        expect(currentPage.getAttribute('aria-current')).toBe('page');
    });

    it('menormalisasi breadcrumbs tanpa Dashboard eksplisit menjadi Dashboard > ... tanpa menambah SAKIP', () => {
        render(
            <AuthenticatedLayout
                title="Pengaturan Sistem"
                breadcrumbs={[{ label: 'Pengaturan' }]}
            >
                <div>Konten</div>
            </AuthenticatedLayout>
        );

        const nav = screen.getByRole('navigation', { name: 'Jejak navigasi' });
        expect(nav.textContent).not.toContain('SAKIP');
        expect(nav.textContent).toContain('Dashboard');
        expect(nav.textContent).toContain('Pengaturan');
    });

    it('hanya menandai breadcrumb terakhir sebagai halaman aktif dengan aria-current=page', () => {
        render(
            <AuthenticatedLayout
                title="Detail"
                breadcrumbs={[
                    { label: 'Induk' },
                    { label: 'Detail' },
                ]}
            >
                <div>Konten</div>
            </AuthenticatedLayout>
        );

        const nav = screen.getByRole('navigation', { name: 'Jejak navigasi' });
        const parentItem = within(nav).getByText('Induk');
        const detailItem = within(nav).getByText('Detail');

        // Induk bukan item terakhir sehingga tidak boleh memiliki aria-current="page"
        expect(parentItem.getAttribute('aria-current')).toBeNull();
        // Detail adalah item terakhir sehingga memiliki aria-current="page"
        expect(detailItem.getAttribute('aria-current')).toBe('page');
    });
});

describe('AuthenticatedLayout Logo Rendering', () => {
    it('merender logo bawaan jika instansi.logo belum terkonfigurasi', () => {
        mockPengaturan = {
            'aplikasi.nama': 'SAKIP LLDIKTI XVI',
        };

        const { container } = render(
            <AuthenticatedLayout title="Test Layout">
                <div>Konten</div>
            </AuthenticatedLayout>
        );

        const imgs = container.querySelectorAll('img');
        expect(imgs.length).toBeGreaterThan(0);
        imgs.forEach((img) => {
            expect(img.getAttribute('src')).toBe('/img/dikti16-favicon-blue-150x150.png');
        });
    });

    it('tidak merender tag img jika instansi.logo sengaja dikosongkan (null)', () => {
        mockPengaturan = {
            'aplikasi.nama': 'SAKIP LLDIKTI XVI',
            'instansi.logo': null,
        };

        const { container } = render(
            <AuthenticatedLayout title="Test Layout">
                <div>Konten</div>
            </AuthenticatedLayout>
        );

        const imgs = container.querySelectorAll('img');
        expect(imgs.length).toBe(0);
    });

    it('tidak merender tag img jika instansi.logo berupa string kosong', () => {
        mockPengaturan = {
            'aplikasi.nama': 'SAKIP LLDIKTI XVI',
            'instansi.logo': '',
        };

        const { container } = render(
            <AuthenticatedLayout title="Test Layout">
                <div>Konten</div>
            </AuthenticatedLayout>
        );

        const imgs = container.querySelectorAll('img');
        expect(imgs.length).toBe(0);
    });

    it('merender logo kustom jika instansi.logo terisi URL', () => {
        mockPengaturan = {
            'aplikasi.nama': 'SAKIP LLDIKTI XVI',
            'instansi.logo': 'https://example.com/logo.png',
        };

        const { container } = render(
            <AuthenticatedLayout title="Test Layout">
                <div>Konten</div>
            </AuthenticatedLayout>
        );

        const imgs = container.querySelectorAll('img');
        expect(imgs.length).toBeGreaterThan(0);
        imgs.forEach((img) => {
            expect(img.getAttribute('src')).toBe('https://example.com/logo.png');
        });
    });
});

describe('AuthenticatedLayout Navigation Active State', () => {
    it('hanya menandai Kebijakan Storage dan bukan Pengaturan ketika url adalah /pengaturan/storage', () => {
        mockUrl = '/pengaturan/storage';
        mockCan = { dashboard: true, pengaturan: true, storagePolicy: true };

        render(
            <AuthenticatedLayout title="Kebijakan Storage">
                <div>Konten Storage</div>
            </AuthenticatedLayout>
        );

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        const pengaturanLink = within(nav).getByRole('link', { name: 'Pengaturan' });
        const storageLink = within(nav).getByRole('link', { name: 'Kebijakan Storage' });

        expect(storageLink.getAttribute('aria-current')).toBe('page');
        expect(pengaturanLink.getAttribute('aria-current')).toBeNull();
    });

    it('hanya menandai Pengaturan dan bukan Kebijakan Storage ketika url adalah /pengaturan', () => {
        mockUrl = '/pengaturan';
        mockCan = { dashboard: true, pengaturan: true, storagePolicy: true };

        render(
            <AuthenticatedLayout title="Pengaturan Sistem">
                <div>Konten Pengaturan</div>
            </AuthenticatedLayout>
        );

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        const pengaturanLink = within(nav).getByRole('link', { name: 'Pengaturan' });
        const storageLink = within(nav).getByRole('link', { name: 'Kebijakan Storage' });

        expect(pengaturanLink.getAttribute('aria-current')).toBe('page');
        expect(storageLink.getAttribute('aria-current')).toBeNull();
    });
});

describe('AuthenticatedLayout Sidebar Grouping', () => {
    it('merender heading section grup menu dan menyembunyikan grup yang tidak memiliki item visible', () => {
        mockCan = {
            dashboard: true,
            pengaturan: true,
            pk: true,
            pengukuran: true,
            unit: true,
            aktivasi: true,
        };

        render(
            <AuthenticatedLayout title="Dashboard">
                <div>Konten</div>
            </AuthenticatedLayout>
        );

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        expect(within(nav).getByText('Utama')).toBeTruthy();
        expect(within(nav).getByText('Perencanaan & Kinerja')).toBeTruthy();
        expect(within(nav).getByText('Master Data')).toBeTruthy();
        expect(within(nav).getByText('Manajemen Akses')).toBeTruthy();
        expect(within(nav).getByText('Sistem')).toBeTruthy();
    });

    it('tidak merender heading grup jika semua item di dalamnya tidak visible', () => {
        mockCan = {
            dashboard: true,
        };

        render(
            <AuthenticatedLayout title="Dashboard">
                <div>Konten</div>
            </AuthenticatedLayout>
        );

        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        expect(within(nav).getByText('Utama')).toBeTruthy();
        expect(within(nav).queryByText('Perencanaan & Kinerja')).toBeNull();
        expect(within(nav).queryByText('Master Data')).toBeNull();
        expect(within(nav).queryByText('Manajemen Akses')).toBeNull();
        expect(within(nav).queryByText('Sistem')).toBeNull();
    });
});

describe('AuthenticatedLayout User Profile & Dropdown Logout', () => {
    it('merender profil pengguna di sidebar', () => {
        render(
            <AuthenticatedLayout title="Dashboard">
                <div>Konten</div>
            </AuthenticatedLayout>
        );

        expect(screen.getAllByText('Operator QA').length).toBeGreaterThan(0);
        expect(screen.getAllByText('admin').length).toBeGreaterThan(0);
    });

    it('membuka menu dropdown profil di navbar atas dan menampilkan aksi logout saat diklik', () => {
        render(
            <AuthenticatedLayout title="Dashboard">
                <div>Konten</div>
            </AuthenticatedLayout>
        );

        const profileBtn = screen.getByRole('button', { name: /Operator QA/i });
        expect(profileBtn).toBeTruthy();
        expect(screen.queryByRole('menu', { name: 'Menu akun' })).toBeNull();

        fireEvent.click(profileBtn);

        const menu = screen.getByRole('menu', { name: 'Menu akun' });
        expect(menu).toBeTruthy();
        expect(within(menu).getByRole('menuitem', { name: 'Keluar dari SAKIP' })).toBeTruthy();
        expect(within(menu).getByRole('menuitem', { name: 'Keluar dari layanan terhubung' })).toBeTruthy();

        // Menutup menu saat tombol ditekan kembali
        fireEvent.click(profileBtn);
        expect(screen.queryByRole('menu', { name: 'Menu akun' })).toBeNull();
    });
});

