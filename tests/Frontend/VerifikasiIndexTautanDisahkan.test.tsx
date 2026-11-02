import { useState, type ReactNode } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import VerifikasiIndex from '@/Pages/Verifikasi/Index';
import type { PengukuranPagination } from '@/Pages/Pengukuran/types';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href, 'aria-label': ariaLabel, className }: { children: ReactNode; href: string; 'aria-label'?: string; className?: string }) => (
        <a href={href} aria-label={ariaLabel} className={className}>{children}</a>
    ),
    usePage: () => ({ props: { pengaturan: {} } }),
    useForm: (initial: Record<string, unknown>) => {
        const [data, setData] = useState(initial);

        return {
            data,
            setData: (key: string | Record<string, unknown>, value?: unknown) => {
                setData((current) => (typeof key === 'string' ? { ...current, [key]: value } : key));
            },
            get: () => undefined,
            processing: false,
            errors: {},
        };
    },
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => (
        <main>
            <div role="region" aria-label="Aksi header">{headerActions}</div>
            {children}
        </main>
    ),
}));

afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

const pagination: PengukuranPagination = { current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null };
const filters = { status: 'diajukan' as const, renstra_id: '', tahun: '', periode_id: '', unit_id: '' };
const selectedFilters = { renstra_id: null, periode_id: null, unit_id: null };

function tampilan(can?: { buka_kembali?: boolean }) {
    return <VerifikasiIndex
        pengukurans={[]}
        pagination={pagination}
        filters={filters}
        selected_filters={selectedFilters}
        can={can}
    />;
}

function aksiHeader(): HTMLElement {
    return screen.getByRole('region', { name: 'Aksi header' });
}

it('menampilkan tautan Pengukuran Disahkan di header saat can.buka_kembali true', () => {
    render(tampilan({ buka_kembali: true }));

    const tautan = within(aksiHeader()).getByRole('link', { name: 'Buka antrean pengukuran disahkan untuk koreksi' });
    expect(tautan.getAttribute('href')).toBe('/verifikasi/disahkan');
    expect(tautan.textContent).toContain('Pengukuran Disahkan');
});

it('menyembunyikan tautan Pengukuran Disahkan saat can.buka_kembali false', () => {
    render(tampilan({ buka_kembali: false }));

    expect(within(aksiHeader()).queryByRole('link', { name: /Pengukuran Disahkan/i })).toBeNull();
    expect(within(aksiHeader()).queryByRole('link')).toBeNull();
    expect(document.querySelector('a[href="/verifikasi/disahkan"]')).toBeNull();
});

it('menyembunyikan tautan Pengukuran Disahkan saat prop can absen', () => {
    render(tampilan());

    expect(within(aksiHeader()).queryByRole('link', { name: /Pengukuran Disahkan/i })).toBeNull();
    expect(within(aksiHeader()).queryByRole('link')).toBeNull();
    expect(document.querySelector('a[href="/verifikasi/disahkan"]')).toBeNull();
});
