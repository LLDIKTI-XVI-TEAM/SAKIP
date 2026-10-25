import type { ReactNode } from 'react';
import { cleanup, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Show, { type RekomendasiShowProps } from '@/Pages/Rekomendasi/Show';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, preserveScroll: _preserveScroll, ...props }: { href: string; children: ReactNode; preserveScroll?: boolean }) => <a href={href} {...props}>{children}</a>,
    usePage: () => ({ props: { pengaturan: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children, title }: { children: ReactNode; title: string }) => <main><h1>{title}</h1>{children}</main> }));

const lama: RekomendasiShowProps['rekomendasi'] = {
    id: 'r1', isi: 'Perluas pendampingan.\nPrioritaskan unit yang membutuhkan.', rujukan_sumber: 'Laporan sintetis', status_tampilan: 'digantikan',
    penyusun: 'Penyusun sintetis', created_at: '2026-03-01T09:00:00+08:00', penyetuju: 'Penyetuju sintetis', disetujui_pada: '2026-03-02T09:00:00+08:00',
    disetujui_oleh_role: 'superadmin', persetujuan_cadangan: true,
};
const props: RekomendasiShowProps = {
    rekomendasi: lama, indikator: { kode: 'IKU 1.1', nama: 'Indikator pengujian', unit: 'Unit sintetis' }, periode: { nama: 'Triwulan I', tahun: 2026 },
    berlaku_url: '/rekomendasi/r2', kelola_url: '/rekomendasi?jadwal_id=j1&periode_id=p1', can: { viewFollowUp: true }, tindak_lanjut: null,
    riwayat: { data: [lama, { ...lama, id: 'r2', isi: 'Arahan terbaru.', status_tampilan: 'berlaku' },
        { ...lama, id: 'r3', isi: 'Usulan baru.', status_tampilan: 'draf', penyetuju: null, disetujui_pada: null, disetujui_oleh_role: null, persetujuan_cadangan: false }],
    links: [], from: 1, to: 3, total: 3 },
};
afterEach(cleanup);

describe('Detail Rekomendasi Pimpinan', () => {
    it('menampilkan metadata, status pengganti, rangkaian bertautan, dan navigasi kelola tanpa mutasi', () => {
        render(<Show {...props} />);
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Detail Rekomendasi Pimpinan');
        expect(screen.getByText('Indikator pengujian')).toBeTruthy();
        expect(screen.getByText('Laporan sintetis')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Kelola di daftar' }).getAttribute('href')).toBe(props.kelola_url);
        expect(screen.getByRole('link', { name: 'Kelola di daftar' }).textContent).toBe('');
        expect(screen.getByRole('link', { name: 'Kembali ke daftar' }).getAttribute('href')).toBe(props.kelola_url);
        expect(screen.getByRole('link', { name: 'Lihat rekomendasi yang berlaku' }).getAttribute('href')).toBe('/rekomendasi/r2');
        const tabel = screen.getByRole('table');
        expect(tabel.className).toContain('md:table');
        expect(within(tabel).getByText('Sedang dibuka')).toBeTruthy();
        expect(within(tabel).getAllByRole('link').map((link) => link.getAttribute('href'))).toEqual(['/rekomendasi/r1', '/rekomendasi/r2', '/rekomendasi/r3']);
        expect(within(tabel).getAllByRole('link').every((link) => link.textContent === '')).toBe(true);
        expect(screen.getAllByText('Persetujuan cadangan · Superadmin').length).toBeGreaterThan(0);
        expect(screen.queryByRole('button', { name: /Setujui|Ubah/ })).toBeNull();
        expect(screen.getByText('Belum ada tindak lanjut')).toBeTruthy();
    });

    it('memisahkan status pengukuran saat ini dari tindak lanjut pengajuan terdahulu', () => {
        render(<Show {...props} tindak_lanjut={{ isi: 'Jawaban beku untuk R1.', nomor: 1, diajukan_pada: '2026-04-10T09:00:00+08:00',
            status_pengukuran: 'disahkan', pengajuan_terdahulu: true, periode: 'Triwulan II', tahun: 2026 }} />);
        expect(screen.getByText('Jawaban beku untuk R1.')).toBeTruthy();
        expect(screen.getByText('Tindak lanjut dari pengajuan #1')).toBeTruthy();
        expect(screen.getByText('Status pengukuran saat ini')).toBeTruthy();
        expect(screen.getByText('Disahkan')).toBeTruthy();
        expect(screen.getByText('Tindak lanjut ini berasal dari pengajuan terdahulu.')).toBeTruthy();
        expect(screen.queryByText('Belum ada tindak lanjut')).toBeNull();
    });

    it('menyembunyikan seluruh panel saat server menolak izin baca pengukuran', () => {
        const { tindak_lanjut: _tindakLanjut, ...tanpaPanel } = props;
        render(<Show {...tanpaPanel} can={{ viewFollowUp: false }} />);
        expect(screen.queryByRole('heading', { name: 'Tindak lanjut' })).toBeNull();
        expect(screen.queryByText('Belum ada tindak lanjut')).toBeNull();
    });
});
