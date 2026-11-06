import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, expect, it, vi } from 'vitest';
import PengirimanNotifikasi, { type PengirimanNotifikasiProps } from '@/Pages/Pengaturan/PengirimanNotifikasi';

vi.mock('@inertiajs/react', async (importOriginal) => ({ ...await importOriginal<typeof import('@inertiajs/react')>(), Head: () => null }));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children, title }: { children: ReactNode; title: string }) => <main><h1>{title}</h1>{children}</main> }));

const props: PengirimanNotifikasiProps = {
    filters: { status: '', kanal: '', penerima: '', mulai: '', selesai: '' },
    pengiriman: { data: [], total: 0, from: null, to: null, links: [] },
};

afterEach(() => { cleanup(); vi.restoreAllMocks(); });

it('menampilkan kondisi kosong dan menerapkan filter kanal lewat GET', async () => {
    const user = userEvent.setup();
    const visit = vi.spyOn(router, 'get').mockImplementation(() => {});
    render(<PengirimanNotifikasi {...props} />);
    expect(screen.getByText('Belum ada pengiriman yang sesuai')).toBeTruthy();
    await user.selectOptions(screen.getByLabelText('Kanal'), 'email');
    await user.type(screen.getByLabelText('ID penerima'), '550e8400-e29b-41d4-a716-446655440000');
    await user.click(screen.getByRole('button', { name: 'Terapkan filter' }));
    expect(visit).toHaveBeenCalledWith('/pengaturan/notifikasi/pengiriman', expect.objectContaining({ kanal: 'email', penerima: '550e8400-e29b-41d4-a716-446655440000' }), expect.any(Object));
});

it('membedakan simulasi dari bukti delivery tanpa aksi retry', () => {
    render(<PengirimanNotifikasi {...props} pengiriman={{ ...props.pengiriman, total: 1, data: [{ id: 'id-sintetis', status: 'diterima_provider', label_status: 'Simulasi diterima', kanal: 'email', penerima_user_id: 'penerima-sintetis', penerima_nama: 'Penerima Sintetis', simulasi: true, kode_hasil: null, percobaan: 1, created_at: '2026-11-06T01:30:00Z', dicoba_terakhir_pada: null }] }} />);
    expect(screen.getByText('Simulasi diterima')).toBeTruthy();
    expect(screen.getByText('Penerima Sintetis')).toBeTruthy();
    expect(screen.queryByText('penerima-sintetis')).toBeNull();
    expect(screen.getByText('Simulasi')).toBeTruthy();
    expect(screen.queryByRole('button', { name: /retry|coba ulang|kirim ulang/i })).toBeNull();
});
