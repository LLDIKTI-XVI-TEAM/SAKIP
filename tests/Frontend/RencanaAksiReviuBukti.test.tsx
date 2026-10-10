import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import Reviu from '@/Pages/RencanaAksi/Reviu';
import type { RencanaAksiDetail } from '@/Pages/RencanaAksi/types';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));
vi.mock('@/hooks/useFormatTanggal', () => ({ useFormatTanggal: () => () => '01 Januari 2026' }));

afterEach(() => cleanup());

const dasar = (bukti: RencanaAksiDetail['bukti_dukungs']): RencanaAksiDetail => ({
    id: 'ra-1', versi: 1, status: 'diverifikasi', tahun: 2026, nomor_pengajuan: 1, jalur_pengajuan: 'pic',
    diajukan_pada: null, uraian: 'Uraian pengajuan', indikator: { kode: 'IK-01', nama: 'Indikator pengujian' },
    unit_kerja: { id: 'unit-1', nama: 'Unit pengujian' }, pic: null, konteks_tidak_lengkap: [], bukti_count: bukti.length,
    bukti_dukungs: bukti, target_periode: [], diajukan_oleh: null, disahkan_pada: null, disahkan_oleh: null, self_approval: false,
    can: { view: true, ratify: false, evidence: true },
});

it('menampilkan tautan unduh hanya untuk bukti mode file yang memiliki URL server', () => {
    render(<Reviu rencanaAksi={dasar([
        { id: 'b1', jenis_berkas_id: null, menggantikan_id: null, alasan_koreksi: null, mode: 'file', nama_asli: 'bukti.pdf', mime: 'application/pdf', ukuran_bytes: 10, tautan: null, isi_teks: null, download_url: '/rencana-aksi/ra-1/bukti/b1/unduh' },
        { id: 'b2', jenis_berkas_id: null, menggantikan_id: null, alasan_koreksi: null, mode: 'tautan', nama_asli: null, mime: null, ukuran_bytes: null, tautan: 'https://contoh.test/dokumen', isi_teks: null, download_url: null },
    ])} />);

    expect(screen.getByRole('link', { name: 'Unduh berkas' }).getAttribute('href')).toBe('/rencana-aksi/ra-1/bukti/b1/unduh');
    expect(screen.getAllByRole('link', { name: 'Unduh berkas' })).toHaveLength(1);
    expect(screen.getByText('https://contoh.test/dokumen')).toBeTruthy();
});

it('menandai pengesahan oleh pengaju sendiri pada jalur Perencanaan', () => {
    render(<Reviu rencanaAksi={{ ...dasar([]), status: 'disahkan', jalur_pengajuan: 'perencanaan', disahkan_oleh: { id: 'u-1', nama: 'Perencana Satu' }, self_approval: true }} />);

    expect(screen.getByText('Perencana Satu')).toBeTruthy();
    expect(screen.getByText(/Disahkan sendiri oleh pengaju/)).toBeTruthy();
});
