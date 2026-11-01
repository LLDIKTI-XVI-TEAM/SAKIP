import { useState, type ReactNode } from 'react';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import DokumenResmi, { type DokumenResmiProps } from '@/Pages/Laporan/DokumenResmi';
import { findActiveNavigation, getSidebarNavEntries } from '@/lib/navigation/sidebarNavigation';

const request = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn<(url: string, options: {
    preserveScroll: boolean; onSuccess: (page: { props: { flash?: { error?: string } } }) => void; onError: () => void;
}) => void>(), processing: false,
    errors: {} as Record<string, string>, upload: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: string }) => <a href={href}>{children}</a>,
    usePage: () => ({ props: {} }),
    useForm: (initial: Record<string, unknown>) => {
        const [data, setData] = useState(initial);
        if ('file' in data) request.upload = data;
        return { data, setData: (key: string | Record<string, unknown>, value?: unknown) => setData((old) => typeof key === 'string' ? { ...old, [key]: value } : key),
            get: request.get, post: request.post, errors: request.errors, processing: request.processing };
    },
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children, title }: { children: ReactNode; title: string }) => <main><h1>{title}</h1>{children}</main> }));
beforeEach(() => { request.get.mockReset(); request.post.mockReset(); request.errors = {}; request.processing = false; request.upload = {}; });
afterEach(cleanup);

function props(): DokumenResmiProps {
    return { filters: { jadwal_id: 'j1', periode_id: '', jenis: 'ra' },
        pilihan_jadwal: [{ id: 'j1', label: '2026' }, { id: 'j2', label: '2027' }], pilihan_periode: [{ id: 'p1', nama: 'Triwulan I' }],
        jadwal: { id: 'j1', tahun: 2026, status: 'ditutup' }, periode_jadwal_id: null,
        arsip: { data: [{ id: 'b1', nama_asli: 'ra-resmi.pdf', keterangan: 'RA perubahan', pengunggah: 'Pengunggah uji', waktu: '2026-10-29T08:00:00+08:00',
            menggantikan_id: null, alasan_koreksi: null, digantikan: false, url: '/laporan/dokumen-resmi/arsip/b1' }], links: [], from: 1, to: 1, total: 1 },
        can: { unggah: true, unduh: true }, alasan_unduh: null, batas_kb: 10240 };
}

it('menu mengikuti capability ekspor dan berada tepat sesudah Rekapitulasi', () => {
    expect(findActiveNavigation(getSidebarNavEntries({ laporan_read: true }), '/laporan/dokumen-resmi').activeItemId).toBeNull();
    const entries = getSidebarNavEntries({ laporan_read: true, dokumenResmi: true });
    const active = findActiveNavigation(entries, '/laporan/dokumen-resmi');
    expect(active.activeItemId).toBe('dokumen-resmi');
    const children = active.activeGroup?.children.map((item) => item.id) ?? [];
    expect(children.indexOf('dokumen-resmi')).toBe(children.indexOf('rekapitulasi') + 1);
});

it('memakai unduhan biasa dan tidak menyediakan edit atau hapus arsip', () => {
    render(<DokumenResmi {...props()} />);
    expect(screen.getByRole('link', { name: /Unduh PDF ra-resmi.pdf/ }).getAttribute('href')).toBe('/laporan/dokumen-resmi/arsip/b1');
    expect(screen.queryByRole('button', { name: /hapus|edit/i })).toBeNull();
    expect(screen.getByText(/Jadwal ditutup; unggah arsip tetap tersedia/)).toBeTruthy();
});

it('menampilkan alasan server ketika unduhan dibatasi, tanpa menampilkan tautan file', () => {
    const reason = 'Dokumen resmi memuat seluruh unit; akses Anda dibatasi pada sebagian unit.';
    render(<DokumenResmi {...props()} can={{ unggah: false, unduh: false }} alasan_unduh={reason} />);
    expect(screen.queryByRole('link', { name: /Unduh PDF/ })).toBeNull();
    expect(screen.getByRole('button', { name: 'Unduh PDF ra-resmi.pdf' }).hasAttribute('disabled')).toBe(true);
    expect(screen.getByText(reason)).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Unggah PDF' })).toBeNull();
});

it('mengisi keterangan pengganti, mewajibkan alasan dan mempertahankan form saat validasi gagal', () => {
    request.errors = { alasan_koreksi: 'Alasan penggantian wajib diisi.' };
    render(<DokumenResmi {...props()} />);
    fireEvent.click(screen.getByRole('button', { name: /Unggah pengganti ra-resmi.pdf/ }));
    const dialog = screen.getByRole('dialog');
    expect((within(dialog).getByLabelText('Keterangan dokumen (opsional)') as HTMLInputElement).value).toBe('RA perubahan');
    expect(within(dialog).getByLabelText(/^Alasan penggantian/).hasAttribute('required')).toBe(true);
    expect(request.upload.menggantikan_id).toBe('b1');
    fireEvent.change(within(dialog).getByLabelText(/^Alasan penggantian/), { target: { value: 'Halaman tertukar' } });
    fireEvent.submit(within(dialog).getByRole('button', { name: 'Simpan arsip' }).closest('form')!);
    expect(request.post).toHaveBeenCalledWith('/laporan/dokumen-resmi/arsip', expect.objectContaining({ preserveScroll: true }));
    expect(request.upload.alasan_koreksi).toBe('Halaman tertukar');
    expect(within(dialog).getByText('Alasan penggantian wajib diisi.')).toBeTruthy();
    expect(screen.getByRole('dialog')).toBeTruthy();
});

it('unggah awal pada periode memakai induk periode jadwal dan input file PDF', () => {
    const data = props();
    render(<DokumenResmi {...data} filters={{ ...data.filters, jenis: 'triwulan', periode_id: 'p1' }} periode_jadwal_id="jp1" />);
    fireEvent.click(screen.getByRole('button', { name: 'Unggah PDF' }));
    const file = new File(['pdf'], 'laporan.pdf', { type: 'application/pdf' });
    const input = screen.getByLabelText(/^Berkas PDF/);
    fireEvent.change(input, { target: { files: [file] } });
    expect(input.getAttribute('accept')).toBe('.pdf,application/pdf');
    expect(request.upload).toMatchObject({ jadwal_id: 'j1', periode_jadwal_id: 'jp1', menggantikan_id: null, file });
    expect(screen.queryByLabelText('Alasan penggantian')).toBeNull();
});

it('modal hanya ditutup setelah server mengonfirmasi keberhasilan tanpa flash error', () => {
    render(<DokumenResmi {...props()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Unggah PDF' }));
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan arsip' }).closest('form')!);
    act(() => request.post.mock.calls[0][1].onSuccess({ props: { flash: { error: 'Berkas gagal disimpan.' } } }));
    expect(screen.getByRole('dialog')).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: 'Batal' }));
    fireEvent.click(screen.getByRole('button', { name: 'Unggah PDF' }));
    fireEvent.submit(screen.getByRole('button', { name: 'Simpan arsip' }).closest('form')!);
    act(() => request.post.mock.calls[1][1].onSuccess({ props: {} }));
    expect(screen.queryByRole('dialog')).toBeNull();
});

it('perubahan filter menahan unggahan dan membersihkan periode sebelum menerapkan jadwal baru', () => {
    render(<DokumenResmi {...props()} />);
    fireEvent.change(screen.getByLabelText('Tahun / jadwal'), { target: { value: 'j2' } });
    expect(screen.getByRole('button', { name: 'Unggah PDF' }).hasAttribute('disabled')).toBe(true);
    expect(screen.queryByRole('link', { name: /Unduh PDF/ })).toBeNull();
    fireEvent.click(screen.getByRole('button', { name: 'Terapkan' }));
    expect(request.get).toHaveBeenCalledWith('/laporan/dokumen-resmi', expect.objectContaining({ preserveState: false }));
});

it('riwayat pengganti tetap terlihat namun tidak dapat diganti ulang', () => {
    const data = props();
    data.arsip.data[0].digantikan = true;
    render(<DokumenResmi {...data} />);
    expect(screen.getByText('Riwayat — sudah diganti')).toBeTruthy();
    expect(screen.queryByRole('button', { name: /Unggah pengganti/ })).toBeNull();
});
