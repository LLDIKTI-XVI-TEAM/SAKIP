import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterAll, afterEach, beforeEach, expect, it, vi } from 'vitest';
import Show from '@/Pages/PerjanjianKinerja/Show';
import type { RenstraPkSummary } from '@/types/perjanjian-kinerja';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { pengaturan: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
const originalShowModal = HTMLDialogElement.prototype.showModal;
const originalClose = HTMLDialogElement.prototype.close;
beforeEach(() => {
    HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    HTMLDialogElement.prototype.close = function () { this.open = false; };
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });
afterAll(() => {
    HTMLDialogElement.prototype.showModal = originalShowModal;
    HTMLDialogElement.prototype.close = originalClose;
});

const pk: RenstraPkSummary = {
    id: 'pk-1', renstra_id: 'ren-1', tahun: 2026, nomor_pk: 'PK/LLDIKTI16/2026/001', tanggal_pk: '2026-01-15',
    created_at: '2026-01-15T02:00:00Z', updated_at: '2026-01-15T02:00:00Z',
    creator: { id: 'user-1', nama: 'Superadmin' },
    renstra: { id: 'ren-1', kode: 'REN-2025', nama: 'Renstra 2025-2029', tahun_mulai: 2025, tahun_selesai: 2029 },
    berkas: [
        { id: 'berkas-1', mode: 'file', nama_asli: 'naskah-pk.pdf', mime: 'application/pdf', ukuran_bytes: 2048, tautan: null, isi_teks: null,
            created_at: '2026-01-15T02:00:00Z', pengunggah: { id: 'user-1', nama: 'Superadmin' } },
        { id: 'berkas-2', mode: 'tautan', nama_asli: null, mime: null, ukuran_bytes: null, tautan: 'https://contoh.go.id/pk', isi_teks: null },
    ],
};

it('detail PK memakai pola detail bersama, menautkan unduhan ber-otorisasi, dan menamai tombol hapus per lampiran', () => {
    render(<Show pk={pk} jadwal_status={null} is_jadwal_aktif={false} can={{ update: true, delete_berkas: true }} />);
    expect(screen.getByRole('link', { name: 'Kembali ke daftar' }).getAttribute('href')).toBe('/perjanjian-kinerja');
    expect(screen.getByRole('heading', { name: 'PK/LLDIKTI16/2026/001' })).toBeTruthy();
    expect(screen.getByText('Belum Aktif')).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Unduh file' }).getAttribute('href')).toBe('/perjanjian-kinerja/pk-1/berkas/berkas-1/unduh');
    expect(screen.getByText(/Diunggah oleh Superadmin/)).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Hapus lampiran naskah-pk.pdf' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Hapus lampiran Lampiran Dokumen #2' })).toBeTruthy();
    expect(screen.queryByText(/lampiran yang ada tidak dapat dihapus/)).toBeNull();
});

it('jadwal aktif menampilkan penanda terkunci tanpa tombol hapus, dan tanpa izin baca berkas tidak ada tautan unduh', () => {
    render(<Show pk={pk} jadwal_status="aktif" is_jadwal_aktif can={{ update: false, delete_berkas: true, read_berkas: false }} />);
    expect(screen.getByText(/^Jadwal tahunan aktif: lampiran yang ada tidak dapat dihapus/)).toBeTruthy();
    expect(screen.queryByRole('button', { name: /^Hapus lampiran/ })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Unduh file' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Buka tautan' })).toBeNull();
    expect(screen.queryByRole('button', { name: /Edit Perjanjian Kinerja/ })).toBeNull();
});
