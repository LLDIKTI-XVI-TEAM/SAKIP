import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/react';
import { DashboardFilter } from '@/Components/Dashboard/DashboardFilter';
import type { DashboardFilterValue, DashboardPilihan } from '@/types/dashboard';

afterEach(() => {
    cleanup();
    // Riwayat panggilan router.get tidak boleh terbawa ke test berikutnya.
    vi.restoreAllMocks();
});
beforeEach(() => {
    vi.spyOn(router, 'get').mockImplementation(() => undefined);
});

const filter: DashboardFilterValue = { renstra_id: 'r1', tahun: 2026, periode_id: 'p2', sasaran_id: null, unit_id: null };
const pilihan: DashboardPilihan = {
    renstra: [{ id: 'r1', nama: 'Renstra 2025–2029' }],
    tahun: [2026, 2025],
    periode: [
        { id: 'p1', nama: 'Triwulan I' },
        { id: 'p2', nama: 'Triwulan II' },
    ],
    sasaran: [{ id: 's1', kode: 'S1', deskripsi: 'Meningkatnya mutu' }],
    unit: [
        { id: 'u1', nama: 'Bagian Umum' },
        { id: 'u2', nama: 'Bagian Kelembagaan' },
    ],
};

it('setiap filter memiliki label yang dapat ditemukan', () => {
    render(<DashboardFilter filter={filter} pilihan={pilihan} />);

    for (const label of ['Renstra', 'Tahun', 'Periode', 'Sasaran', 'Unit']) {
        expect(screen.getByLabelText(label)).toBeTruthy();
    }
    expect((screen.getByLabelText('Unit') as HTMLSelectElement).value).toBe('');
    expect(screen.getByRole('option', { name: 'Semua unit' })).toBeTruthy();
});

it('memilih unit memuat ulang dashboard dengan filter tersebut tanpa mengganti riwayat', () => {
    render(<DashboardFilter filter={filter} pilihan={pilihan} />);

    fireEvent.change(screen.getByLabelText('Unit'), { target: { value: 'u2' } });

    expect(router.get).toHaveBeenCalledWith(
        '/dashboard',
        { renstra_id: 'r1', tahun: 2026, periode_id: 'p2', unit_id: 'u2' },
        expect.objectContaining({ preserveState: true, preserveScroll: true, replace: true }),
    );
});

it('mengganti tahun mengosongkan periode, sasaran, dan unit agar tidak tertolak pada jadwal lain', () => {
    render(<DashboardFilter filter={{ ...filter, sasaran_id: 's1', unit_id: 'u1' }} pilihan={pilihan} />);

    fireEvent.change(screen.getByLabelText('Tahun'), { target: { value: '2025' } });

    expect(router.get).toHaveBeenCalledWith('/dashboard', { renstra_id: 'r1', tahun: 2025 }, expect.anything());
});

it('tanpa Renstra aktif, select Renstra kosong dan memilih Renstra lama memuat ulang dashboard', () => {
    render(<DashboardFilter filter={{ ...filter, renstra_id: null, tahun: null, periode_id: null }} pilihan={{ ...pilihan, tahun: [], periode: [], sasaran: [], unit: [] }} />);

    const renstra = screen.getByLabelText('Renstra') as HTMLSelectElement;
    expect(renstra.value).toBe('');
    expect(screen.getByRole('option', { name: 'Pilih Renstra' })).toBeTruthy();
    fireEvent.change(renstra, { target: { value: 'r1' } });
    expect(router.get).toHaveBeenCalledWith('/dashboard', { renstra_id: 'r1' }, expect.anything());
});
