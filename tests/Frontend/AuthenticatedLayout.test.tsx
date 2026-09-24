import { cleanup, render, screen, within } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';

let mockPengaturan: Record<string, unknown> = {
    'aplikasi.nama': 'SAKIP LLDIKTI XVI',
};

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
                    user: { id: 'admin-id', nama: 'Superadmin', email: 'admin@example.test', is_active: true, role: 'admin' },
                    can: { dashboard: true, pengaturan: true },
                },
                flash: {},
                pengaturan: mockPengaturan,
            },
            url: '/pengaturan',
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
});

describe('AuthenticatedLayout Breadcrumbs', () => {
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
