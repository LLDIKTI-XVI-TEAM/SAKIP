import { cleanup, render, screen, within } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';

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
                pengaturan: {
                    'aplikasi.nama': 'SAKIP LLDIKTI XVI',
                },
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

afterEach(cleanup);

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
});
