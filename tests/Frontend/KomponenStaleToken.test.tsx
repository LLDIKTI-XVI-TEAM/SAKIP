import type { ReactNode } from 'react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import KomponenIndex from '@/Pages/Indikator/Komponen/Index';
import type { KomponenItem } from '@/Pages/Indikator/Komponen/Index';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

// jsdom tidak mengimplementasikan showModal/close pada <dialog>;
// polyfill no-op test-only agar AuditReasonModal dapat dirender.
if (typeof HTMLDialogElement !== 'undefined') {
    if (typeof HTMLDialogElement.prototype.showModal !== 'function') {
        HTMLDialogElement.prototype.showModal = function (this: HTMLDialogElement) {
            this.setAttribute('open', '');
        };
    }
    if (typeof HTMLDialogElement.prototype.close !== 'function') {
        HTMLDialogElement.prototype.close = function (this: HTMLDialogElement) {
            this.removeAttribute('open');
        };
    }
}

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

const UPDATED_AT = '2026-10-02T10:00:00.123456Z';
const KONFLIK = 'Data indikator kinerja telah diperbarui oleh pengguna lain. Silakan muat ulang halaman untuk melihat perubahan terkini.';

const KOMPONEN: KomponenItem[] = [
    { id: '00000000-0000-4000-8000-000000000011', indikator_id: 'ind-1', kode: 'n', label: 'Pembilang', peran: 'pembilang', bobot: 1, urutan: 1, satuan: 'Dokumen', aktif: true },
    { id: '00000000-0000-4000-8000-000000000022', indikator_id: 'ind-1', kode: 't', label: 'Penyebut', peran: 'penyebut', bobot: 1, urutan: 2, satuan: 'Dokumen', aktif: true },
];

function renderPage() {
    render(
        <KomponenIndex
            indikator={{
                id: 'ind-1',
                kode: 'IKU-01',
                nama: 'Persentase capaian kinerja',
                satuan: '%',
                tipe_perhitungan: 'rasio_persen',
                arah: 'naik_baik',
                presisi: 2,
                desimal_tampilan: 2,
                status: 'aktif',
                updated_at: UPDATED_AT,
                unit: { id: 'unit-a', nama: 'Unit A' },
            }}
            komponen={KOMPONEN}
            formulaContract={{
                tipe_perhitungan: 'rasio_persen',
                formula_text: 'n / t × 100%',
                is_valid: true,
                messages: [],
                komponen_list: [],
            }}
            validation={{ is_valid: true, messages: [] }}
            can={{ create: true, update: true, delete: true }}
        />
    );
}

describe('Stale token CRUD komponen normal', () => {
    it('menyertakan expected_updated_at dari indikator.updated_at pada POST create', async () => {
        const user = userEvent.setup();
        const postSpy = vi.spyOn(router, 'post').mockImplementation((() => undefined) as unknown as typeof router.post);
        renderPage();

        await user.click(screen.getByRole('button', { name: 'Tambah Komponen' }));
        await user.click(screen.getByRole('button', { name: 'Simpan Komponen' }));

        expect(postSpy).toHaveBeenCalledTimes(1);
        expect(postSpy.mock.calls[0]?.[0]).toBe('/indikator/ind-1/komponen');
        expect(postSpy.mock.calls[0]?.[1]).toEqual(expect.objectContaining({ expected_updated_at: UPDATED_AT }));
    });

    it('menampilkan error expected_updated_at apa adanya pada form create', async () => {
        const user = userEvent.setup();
        const postSpy = vi.spyOn(router, 'post').mockImplementation((() => undefined) as unknown as typeof router.post);
        renderPage();

        await user.click(screen.getByRole('button', { name: 'Tambah Komponen' }));
        await user.click(screen.getByRole('button', { name: 'Simpan Komponen' }));

        const options = postSpy.mock.calls[0]?.[2] as unknown as { onError?: (errors: Record<string, string>) => void };
        await act(async () => options.onError?.({ expected_updated_at: 'Format timestamp versi tidak valid.' }));

        expect(screen.getByText('Format timestamp versi tidak valid.')).toBeTruthy();
    });

    it('menyertakan expected_updated_at dan alasan pada PUT update', async () => {
        const user = userEvent.setup();
        const putSpy = vi.spyOn(router, 'put').mockImplementation((() => undefined) as unknown as typeof router.put);
        renderPage();

        await user.click(screen.getAllByTitle('Ubah Komponen')[0]);
        await user.click(screen.getByRole('button', { name: 'Lanjutkan Perubahan' }));
        await user.type(screen.getByLabelText(/alasan perubahan/i), 'Penyesuaian bobot final IKU.');
        await user.click(screen.getByRole('button', { name: 'Simpan Perubahan' }));

        expect(putSpy).toHaveBeenCalledTimes(1);
        expect(putSpy.mock.calls[0]?.[0]).toBe(`/indikator/ind-1/komponen/${KOMPONEN[0].id}`);
        expect(putSpy.mock.calls[0]?.[1]).toEqual(expect.objectContaining({
            alasan: 'Penyesuaian bobot final IKU.',
            expected_updated_at: UPDATED_AT,
        }));
    });

    it('menampilkan error konflik apa adanya pada audit update tanpa menutup modal', async () => {
        const user = userEvent.setup();
        const putSpy = vi.spyOn(router, 'put').mockImplementation((() => undefined) as unknown as typeof router.put);
        renderPage();

        await user.click(screen.getAllByTitle('Ubah Komponen')[0]);
        await user.click(screen.getByRole('button', { name: 'Lanjutkan Perubahan' }));
        await user.type(screen.getByLabelText(/alasan perubahan/i), 'Penyesuaian bobot final IKU.');
        await user.click(screen.getByRole('button', { name: 'Simpan Perubahan' }));

        const options = putSpy.mock.calls[0]?.[2] as unknown as { onError?: (errors: Record<string, string>) => void };
        await act(async () => options.onError?.({ konflik: KONFLIK }));

        expect(screen.getByRole('dialog', { name: 'Alasan Pembaruan Komponen' })).toBeTruthy();
        expect(screen.getByText(KONFLIK)).toBeTruthy();
    });

    it('menyertakan expected_updated_at dan alasan pada DELETE', async () => {
        const user = userEvent.setup();
        const deleteSpy = vi.spyOn(router, 'delete').mockImplementation((() => undefined) as unknown as typeof router.delete);
        renderPage();

        await user.click(screen.getAllByTitle('Hapus Komponen')[0]);
        await user.type(screen.getByLabelText(/alasan perubahan/i), 'Penghapusan komponen usang.');
        const dialog = screen.getByRole('dialog', { name: 'Konfirmasi Penghapusan Komponen' });
        await user.click(within(dialog).getByRole('button', { name: 'Hapus Komponen' }));

        expect(deleteSpy).toHaveBeenCalledTimes(1);
        expect(deleteSpy.mock.calls[0]?.[0]).toBe(`/indikator/ind-1/komponen/${KOMPONEN[0].id}`);
        expect(deleteSpy.mock.calls[0]?.[1]).toEqual(expect.objectContaining({
            data: expect.objectContaining({
                alasan: 'Penghapusan komponen usang.',
                expected_updated_at: UPDATED_AT,
            }),
        }));
    });

    it('menampilkan error konflik apa adanya pada audit delete', async () => {
        const user = userEvent.setup();
        const deleteSpy = vi.spyOn(router, 'delete').mockImplementation((() => undefined) as unknown as typeof router.delete);
        renderPage();

        await user.click(screen.getAllByTitle('Hapus Komponen')[0]);
        await user.type(screen.getByLabelText(/alasan perubahan/i), 'Penghapusan komponen usang.');
        const dialog = screen.getByRole('dialog', { name: 'Konfirmasi Penghapusan Komponen' });
        await user.click(within(dialog).getByRole('button', { name: 'Hapus Komponen' }));

        const options = deleteSpy.mock.calls[0]?.[1] as unknown as { onError?: (errors: Record<string, string>) => void };
        await act(async () => options.onError?.({ konflik: KONFLIK }));

        expect(screen.getByText(KONFLIK)).toBeTruthy();
    });
});
