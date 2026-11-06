import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';

let mockPengaturan: Record<string, unknown> = {
    'aplikasi.nama': 'SAKIP LLDIKTI XVI',
};
let mockUrl = '/pengaturan';
let mockCan: Record<string, boolean> = { dashboard: true, pengaturan: true };
let mockRole = 'admin';
let mockNotifikasi: number | null = 0;

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
                notifikasi_belum_dibaca: mockNotifikasi,
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
    mockNotifikasi = 0;
    vi.useRealTimers();
});

describe('AuthenticatedLayout Lonceng Notifikasi', () => {
    const lonceng = () => screen.getAllByRole('link', { name: /^Notifikasi/ });

    it('menampilkan angka belum dibaca dari props di header desktop dan mobile tanpa timer', () => {
        vi.useFakeTimers();
        mockNotifikasi = 3;
        render(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);

        const links = lonceng();
        expect(links).toHaveLength(2);
        for (const link of links) {
            expect(link.getAttribute('href')).toBe('/notifikasi');
            expect(link.getAttribute('aria-label')).toBe('Notifikasi, 3 belum dibaca');
            expect(link.textContent).toContain('3');
        }
        expect(vi.getTimerCount()).toBe(0);
    });

    it('membatasi badge pada 99+ dan menyembunyikan badge saat nol', () => {
        mockNotifikasi = 120;
        const { unmount } = render(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);
        expect(lonceng()[0].textContent).toContain('99+');
        expect(lonceng()[0].getAttribute('aria-label')).toBe('Notifikasi, 120 belum dibaca');
        unmount();

        mockNotifikasi = 0;
        render(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);
        expect(lonceng()[0].getAttribute('aria-label')).toBe('Notifikasi');
        expect(lonceng()[0].textContent).toBe('');
    });

    it('tidak merender lonceng saat server mengirim null (akun tanpa peran aktif)', () => {
        mockNotifikasi = null;
        render(<AuthenticatedLayout><div>Konten</div></AuthenticatedLayout>);
        expect(screen.queryAllByRole('link', { name: /^Notifikasi/ })).toHaveLength(0);
    });
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
        // Buka grup Manajemen Akses untuk memeriksa submenu Izin Unit
        const groupButton = screen.getByRole('button', { name: 'Manajemen Akses' });
        expect(groupButton).toBeTruthy();
        fireEvent.click(groupButton);
        expect(screen.getByRole('link', { name: 'Izin Unit' }).getAttribute('href')).toBe('/akses/grant');

        mockCan = { dashboard: true, grant: false };
        rerender(
            <AuthenticatedLayout>
                <div>Konten</div>
            </AuthenticatedLayout>
        );
        expect(screen.queryByRole('link', { name: 'Izin Unit' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Manajemen Akses' })).toBeNull();
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
    it('menandai Pengaturan dan tidak menawarkan menu Kebijakan Storage', () => {
        mockUrl = '/pengaturan';
        mockCan = { dashboard: true, pengaturan: true };
        render(<AuthenticatedLayout title="Pengaturan Sistem"><div>Konten Pengaturan</div></AuthenticatedLayout>);
        const nav = screen.getByRole('navigation', { name: 'Navigasi utama' });
        expect(within(nav).getByRole('link', { name: 'Pengaturan' }).getAttribute('aria-current')).toBe('page');
        expect(within(nav).queryByRole('link', { name: 'Kebijakan Storage' })).toBeNull();
    });
});
