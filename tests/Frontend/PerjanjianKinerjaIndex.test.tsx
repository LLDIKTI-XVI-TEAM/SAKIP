import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterAll, afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Index from '@/Pages/PerjanjianKinerja/Index';
import type { Paginated, RenstraPkSummary } from '@/types/perjanjian-kinerja';

vi.mock('@inertiajs/react', async (original) => ({
    ...await original<typeof import('@inertiajs/react')>(),
    Head: () => null,
    usePage: () => ({ props: { auth: { user: {} }, pengaturan: {} } }),
    Link: ({ children, href, className, title }: { children: ReactNode; href: string; className?: string; title?: string }) => (
        <a href={href} className={className} title={title}>{children}</a>
    ),
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, headerAction }: { children: ReactNode; headerAction?: ReactNode }) => (
        <main>
            {headerAction}
            {children}
        </main>
    ),
}));

const originalShowModal = HTMLDialogElement.prototype.showModal;
const originalClose = HTMLDialogElement.prototype.close;

beforeEach(() => {
    HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    HTMLDialogElement.prototype.close = function () { this.open = false; };
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

afterAll(() => {
    HTMLDialogElement.prototype.showModal = originalShowModal;
    HTMLDialogElement.prototype.close = originalClose;
});

const createPkItem = (overrides: Partial<RenstraPkSummary> = {}): RenstraPkSummary => ({
    id: 'pk-uuid-default',
    renstra_id: 'renstra-uuid-1',
    tahun: 2026,
    nomor_pk: 'PK/LLDIKTI16/2026/001',
    tanggal_pk: '2026-01-15T00:00:00.000000Z',
    creator: { id: 'user-1', nama: 'Admin' },
    created_at: '2026-01-15T00:00:00.000000Z',
    updated_at: '2026-01-15T00:00:00.000000Z',
    renstra: {
        id: 'renstra-uuid-1',
        kode: 'RENSTRA-2025',
        nama: 'Renstra 2025-2029',
        tahun_mulai: 2025,
        tahun_selesai: 2029,
    },
    ...overrides,
});

describe('PerjanjianKinerja Index Schedule Status Badges', () => {
    it('menampilkan badge "Aktif" saat jadwal tahunan berstatus aktif', () => {
        const pkAktif = createPkItem({
            id: 'pk-aktif',
            nomor_pk: 'PK-AKTIF-001',
            jadwal_tahunan: {
                id: 'jadwal-1',
                status: 'aktif',
                is_terkunci: true,
                activated_at: '2026-01-01T00:00:00.000000Z',
            },
        });

        const paginated: Paginated<RenstraPkSummary> = {
            data: [pkAktif],
            current_page: 1,
            last_page: 1,
            from: 1,
            to: 1,
            total: 1,
            links: [],
        };

        render(
            <Index
                perjanjianKinerja={paginated}
                renstras={[pkAktif.renstra]}
                filters={{}}
                can={{ create: true }}
            />
        );

        expect(screen.getByText('Aktif')).toBeTruthy();
        expect(screen.queryByText('Ditutup')).toBeNull();
        expect(screen.queryByText('Belum Aktif')).toBeNull();
    });

    it('menampilkan badge "Ditutup" (bukan Belum Aktif) saat jadwal tahunan berstatus ditutup', () => {
        const pkDitutup = createPkItem({
            id: 'pk-ditutup',
            nomor_pk: 'PK-DITUTUP-001',
            jadwal_tahunan: {
                id: 'jadwal-2',
                status: 'ditutup',
                is_terkunci: true,
                activated_at: '2026-01-01T00:00:00.000000Z',
            },
        });

        const paginated: Paginated<RenstraPkSummary> = {
            data: [pkDitutup],
            current_page: 1,
            last_page: 1,
            from: 1,
            to: 1,
            total: 1,
            links: [],
        };

        render(
            <Index
                perjanjianKinerja={paginated}
                renstras={[pkDitutup.renstra]}
                filters={{}}
                can={{ create: true }}
            />
        );

        expect(screen.getByText('Ditutup')).toBeTruthy();
        expect(screen.queryByText('Aktif')).toBeNull();
        expect(screen.queryByText('Belum Aktif')).toBeNull();
    });

    it('menampilkan badge "Belum Aktif" saat jadwal tahunan bernilai null atau berstatus draft', () => {
        const pkBelumAktif = createPkItem({
            id: 'pk-belum-aktif',
            nomor_pk: 'PK-DRAFT-001',
            jadwal_tahunan: {
                id: 'jadwal-3',
                status: 'draft',
                is_terkunci: false,
                activated_at: null,
            },
        });

        const pkTanpaJadwal = createPkItem({
            id: 'pk-tanpa-jadwal',
            nomor_pk: 'PK-NO-JADWAL-002',
            jadwal_tahunan: null,
        });

        const paginated: Paginated<RenstraPkSummary> = {
            data: [pkBelumAktif, pkTanpaJadwal],
            current_page: 1,
            last_page: 1,
            from: 1,
            to: 2,
            total: 2,
            links: [],
        };

        render(
            <Index
                perjanjianKinerja={paginated}
                renstras={[pkBelumAktif.renstra]}
                filters={{}}
                can={{ create: true }}
            />
        );

        const badges = screen.getAllByText('Belum Aktif');
        expect(badges).toHaveLength(2);
        expect(screen.queryByText('Aktif')).toBeNull();
        expect(screen.queryByText('Ditutup')).toBeNull();
    });
});
