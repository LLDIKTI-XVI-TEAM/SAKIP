import type { Page } from '@inertiajs/core';
import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Index from '@/Pages/Periode/Index';
import type { PeriodeIndexProps } from '@/types/periode-jadwal';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ props: { auth: { can: { periode: true, jadwal: true } } } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const props: PeriodeIndexProps = {
    periode: {
        data: [
            {
                id: 'periode-1',
                nama: 'Triwulan I',
                urutan: 1,
                aktif: true,
                is_nilai_akhir: false,
                revisi: 3,
                metadata_locked: false,
                metadata_locked_reason: null,
            },
        ],
        current_page: 2,
        last_page: 2,
        from: 21,
        to: 21,
        total: 21,
        links: [
            { url: '/periode?page=1', label: '1', active: false },
            { url: '/periode?page=2', label: '2', active: true },
        ],
    },
    current_final: { id: 'final-lain-halaman', nama: 'Triwulan IV', revisi: 9 },
    filters: { q: '', status: null, sort: 'urutan' },
    can: { create: true, update: true, replaceFinal: true },
};
const page = (flash: Page['flash'] = {}): Page => ({
    component: 'Periode/Index',
    props: { ...props, errors: {} },
    url: '/periode',
    version: null,
    clearHistory: false,
    encryptHistory: false,
    rescuedProps: [],
    rememberedState: {},
    flash,
});
beforeEach(() => {
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'put').mockImplementation(() => undefined);
    vi.spyOn(router, 'get').mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

it('pergantian final memakai singleton lintas halaman serta kedua revisi koheren', async () => {
    const user = userEvent.setup();
    render(<Index {...props} />);
    const trigger = screen.getByRole('button', { name: 'Ganti periode nilai akhir' });
    await user.click(trigger);
    expect(screen.getByText('Triwulan IV')).toBeTruthy();
    await user.click(screen.getByRole('button', { name: 'Simpan pergantian' }));
    expect(router.post).toHaveBeenCalledWith(
        '/periode/ganti-nilai-akhir',
        {
            periode_lama_id: 'final-lain-halaman',
            revisi_lama: 9,
            periode_pengganti_id: 'periode-1',
            revisi_pengganti: 3,
        },
        expect.any(Object),
    );
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => {
        options?.onSuccess?.(page({ success: 'Pergantian tersimpan.' }));
    });
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(document.activeElement).toBe(trigger);
});

it('koreksi nama mengirim metadata terkunci tetap dan mempertahankan input pada penolakan', async () => {
    render(
        <Index
            {...props}
            filters={{ ...props.filters, q: 'Triwulan', status: 'nonaktif' }}
            periode={{
                ...props.periode,
                data: [
                    {
                        ...props.periode.data[0],
                        aktif: false,
                        is_nilai_akhir: true,
                        metadata_locked: true,
                        metadata_locked_reason: 'Dipakai jadwal aktif/ditutup.',
                    },
                ],
            }}
        />,
    );
    fireEvent.click(screen.getByRole('button', { name: 'Koreksi nama Triwulan I' }));
    expect(screen.getByLabelText<HTMLInputElement>(/^Urutan/).disabled).toBe(true);
    expect(screen.getByLabelText<HTMLInputElement>('Nilai akhir · Diisi manual').disabled).toBe(true);
    fireEvent.change(screen.getByLabelText(/^Nama periode/), { target: { value: 'Triwulan Pertama' } });
    fireEvent.click(screen.getByRole('button', { name: 'Simpan perubahan' }));
    expect(vi.mocked(router.put).mock.calls[0][1]).toEqual({
        nama: 'Triwulan Pertama',
        urutan: '1',
        aktif: false,
        is_nilai_akhir: true,
        revisi: 3,
    });
    await act(async () => {
        vi.mocked(router.put).mock.calls[0][2]?.onError?.({ revisi: 'Periode sudah berubah.' });
    });
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>(/^Nama periode/).value).toBe('Triwulan Pertama');
    expect(screen.getByLabelText<HTMLInputElement>('Cari periode').value).toBe('Triwulan');
    expect(screen.getByLabelText<HTMLSelectElement>('Status').value).toBe('nonaktif');
    expect(document.activeElement?.textContent).toContain('Periode sudah berubah.');
});

it('filter mengikuti props daftar hasil redirect hanya setelah penyimpanan terkonfirmasi', async () => {
    const user = userEvent.setup();
    const { rerender } = render(
        <Index
            {...props}
            filters={{ ...props.filters, q: 'Triwulan', status: 'nonaktif' }}
            periode={{ ...props.periode, data: [{ ...props.periode.data[0], aktif: false }] }}
        />,
    );
    const trigger = screen.getByRole('button', { name: 'Edit Triwulan I' });
    await user.click(trigger);
    await user.type(screen.getByLabelText(/^Nama periode/), ' dikoreksi');
    await user.click(screen.getByRole('button', { name: 'Simpan perubahan' }));
    const options = vi.mocked(router.put).mock.calls[0][2];
    rerender(<Index {...props} />);
    await act(async () => {
        options?.onSuccess?.(page({ success: 'Periode berhasil disimpan.' }));
    });
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.getByLabelText<HTMLInputElement>('Cari periode').value).toBe('');
    expect(screen.getByLabelText<HTMLSelectElement>('Status').value).toBe('');
    expect(document.activeElement).toBe(trigger);
    await user.click(screen.getByRole('button', { name: 'Terapkan filter' }));
    expect(router.get).toHaveBeenCalledWith(
        '/periode',
        { q: '', status: '', sort: 'urutan' },
        expect.any(Object),
    );
});

it('403 respons akhir tanpa bukti penolakan sebelum mutasi mempertahankan input sebagai hasil unknown', async () => {
    render(<Index {...props} filters={{ ...props.filters, q: 'Triwulan', status: 'nonaktif' }} />);
    fireEvent.click(screen.getByRole('button', { name: 'Edit Triwulan I' }));
    const name = screen.getByLabelText<HTMLInputElement>(/^Nama periode/);
    fireEvent.change(name, { target: { value: 'Nama koreksi' } });
    const button = screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan perubahan' });
    fireEvent.click(button);
    await act(async () => {
        expect(vi.mocked(router.put).mock.calls[0][2]?.onHttpException?.({ status: 403, data: {}, headers: {} })).toBe(false);
    });
    expect(screen.getByText(/Hasil penyimpanan belum terkonfirmasi karena akses ditolak/)).toBeTruthy();
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(name.value).toBe('Nama koreksi');
    expect(screen.getByLabelText<HTMLInputElement>('Cari periode').value).toBe('Triwulan');
    expect(screen.getByLabelText<HTMLSelectElement>('Status').value).toBe('nonaktif');
    expect(button.disabled).toBe(true);
    expect(screen.getByRole('link', { name: 'Periksa master terbaru' }).getAttribute('href')).toBe('/periode');
    fireEvent.click(button);
    expect(router.put).toHaveBeenCalledTimes(1);
    expect(router.get).not.toHaveBeenCalled();
});

it('konfigurasi kosong menawarkan satu periode pertama aktif dan final', () => {
    render(<Index {...props} periode={{ ...props.periode, data: [], total: 0, last_page: 1 }} current_final={null} />);
    fireEvent.click(screen.getByRole('button', { name: 'Tambah periode' }));
    expect(screen.getByLabelText<HTMLInputElement>('Nilai akhir · Diisi manual').checked).toBe(true);
    expect(screen.getByLabelText<HTMLInputElement>('Nilai akhir · Diisi manual').disabled).toBe(true);
    expect(screen.getByLabelText<HTMLInputElement>('Aktif').checked).toBe(true);
    expect(screen.queryByRole('button', { name: 'Ganti periode nilai akhir' })).toBeNull();
});

it('flag final periode nonaktif yang belum terkunci dapat dikoreksi sebelum diaktifkan sebagai periode biasa', async () => {
    const user = userEvent.setup();
    render(
        <Index
            {...props}
            periode={{ ...props.periode, data: [{ ...props.periode.data[0], aktif: false, is_nilai_akhir: true }] }}
        />,
    );
    await user.click(screen.getByRole('button', { name: 'Edit Triwulan I' }));
    const final = screen.getByLabelText<HTMLInputElement>('Nilai akhir · Diisi manual');
    expect(final.disabled).toBe(false);
    await user.click(final);
    await user.click(screen.getByLabelText('Aktif'));
    expect(final.checked).toBe(false);
    expect(final.disabled).toBe(true);
    await user.click(screen.getByRole('button', { name: 'Simpan perubahan' }));
    expect(vi.mocked(router.put).mock.calls[0][1]).toMatchObject({ aktif: true, is_nilai_akhir: false, revisi: 3 });
});

it('periode final aktif tetap memakai pergantian utuh', () => {
    render(
        <Index {...props} periode={{ ...props.periode, data: [{ ...props.periode.data[0], is_nilai_akhir: true }] }} />,
    );
    fireEvent.click(screen.getByRole('button', { name: 'Edit Triwulan I' }));
    expect(screen.getByLabelText<HTMLInputElement>('Nilai akhir · Diisi manual').disabled).toBe(true);
    expect(screen.getByLabelText<HTMLInputElement>('Aktif').disabled).toBe(true);
});

it('unknown dan callback tanpa flash sukses tidak menutup modal atau memberi replay', async () => {
    render(<Index {...props} filters={{ ...props.filters, q: 'Triwulan', status: 'nonaktif' }} />);
    fireEvent.click(screen.getByRole('button', { name: 'Edit Triwulan I' }));
    fireEvent.change(screen.getByLabelText(/^Nama periode/), { target: { value: 'Nama koreksi' } });
    fireEvent.click(screen.getByRole('button', { name: 'Simpan perubahan' }));
    await act(async () => {
        vi.mocked(router.put).mock.calls[0][2]?.onSuccess?.(page());
    });
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(screen.getByLabelText<HTMLInputElement>(/^Nama periode/).value).toBe('Nama koreksi');
    expect(screen.getByLabelText<HTMLInputElement>('Cari periode').value).toBe('Triwulan');
    expect(screen.getByLabelText<HTMLSelectElement>('Status').value).toBe('nonaktif');
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Simpan perubahan' }).disabled).toBe(true);
    expect(router.put).toHaveBeenCalledTimes(1);
    expect(screen.getByRole('link', { name: 'Periksa master terbaru' })).toBeTruthy();
});

it('izin create-only tidak menampilkan edit atau pergantian final', () => {
    render(<Index {...props} can={{ create: true, update: false, replaceFinal: false }} />);
    expect(screen.getByRole('button', { name: 'Tambah periode' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Edit Triwulan I' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Ganti periode nilai akhir' })).toBeNull();
});

it('menampilkan placeholder pencarian dan dapat mereset filter ketika filter aktif', () => {
    render(<Index {...props} filters={{ ...props.filters, q: 'Triwulan', status: 'aktif' }} />);
    expect(screen.getByPlaceholderText('Cari nama periode pelaporan...')).toBeTruthy();
    const resetButton = screen.getByRole('button', { name: 'Reset filter' });
    fireEvent.click(resetButton);

    expect(screen.getByLabelText<HTMLInputElement>('Cari periode').value).toBe('');
    expect(screen.getByLabelText<HTMLSelectElement>('Status').value).toBe('');
    expect(router.get).toHaveBeenCalledWith(
        '/periode',
        { q: '', status: '', sort: 'urutan' },
        { preserveState: false, replace: true },
    );
});

