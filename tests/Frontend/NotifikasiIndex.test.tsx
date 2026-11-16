import { cleanup, render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import NotifikasiIndex from '@/Pages/Notifikasi/Index';

vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: import('react').ReactNode }) => <main>{children}</main> }));
vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...original,
        Head: () => null,
        Link: ({ children, href, ...props }: { children: import('react').ReactNode; href?: string }) => <a href={href} {...props}>{children}</a>,
        usePage: () => ({ props: { pengaturan: {} } }),
    };
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

const item = (id: string, dibaca: boolean) => ({ id, jenis: 'pengukuran.dikembalikan', judul: `Pengukuran IKU 2.1 TW IV 2026 dikembalikan ${id}`, ringkasan: 'lampiran SPJ belum ada', dibaca, dibuat_pada: '2026-11-04T09:00:00+08:00' });
const paginator = (data: ReturnType<typeof item>[], banyakHalaman = false) => ({
    data, from: data.length ? 1 : null, to: data.length || null, total: banyakHalaman ? 21 : data.length,
    links: banyakHalaman
        ? [{ url: null, label: '&laquo; Previous', active: false }, { url: '/notifikasi?page=1', label: '1', active: true }, { url: '/notifikasi?page=2', label: '2', active: false }, { url: '/notifikasi?page=2', label: 'Next &raquo;', active: false }]
        : [{ url: null, label: '&laquo; Previous', active: false }, { url: '/notifikasi?page=1', label: '1', active: true }, { url: null, label: 'Next &raquo;', active: false }],
});

describe('Halaman daftar notifikasi', () => {
    it('menandai item belum dibaca dengan Baru dan nama aksesibel tombol memuat judul, Baru, dan ringkasan', () => {
        render(<NotifikasiIndex notifikasi={paginator([item('a', false), item('b', true)])} />);

        const baru = screen.getByRole('button', { name: /Baru.*Pengukuran IKU 2.1 TW IV 2026 dikembalikan a.*lampiran SPJ belum ada/ });
        expect(baru).toBeTruthy();
        const lama = screen.getByRole('button', { name: /Pengukuran IKU 2.1 TW IV 2026 dikembalikan b/ });
        expect(lama.textContent).not.toContain('Baru');
    });

    it('klik item memanggil POST buka dan menonaktifkan tombol itu selama berjalan', async () => {
        const { router } = await import('@inertiajs/react');
        vi.spyOn(router, 'post').mockImplementation(() => undefined);
        const user = userEvent.setup();
        render(<NotifikasiIndex notifikasi={paginator([item('a', false), item('b', false)])} />);

        await user.click(screen.getByRole('button', { name: /dikembalikan a/ }));

        expect(router.post).toHaveBeenCalledWith('/notifikasi/a/buka', {}, expect.any(Object));
        const options = vi.mocked(router.post).mock.calls[0][2];
        await act(async () => { options?.onStart?.({} as never); });
        const tombol = screen.getByRole('button', { name: /dikembalikan a/ }) as HTMLButtonElement;
        expect(tombol.disabled).toBe(true);
        expect(tombol.getAttribute('aria-busy')).toBe('true');
        expect(tombol.textContent).toContain('Membuka…');
        expect((screen.getByRole('button', { name: /dikembalikan b/ }) as HTMLButtonElement).disabled).toBe(false);
        await act(async () => { options?.onFinish?.({} as never); });
        expect((screen.getByRole('button', { name: /dikembalikan a/ }) as HTMLButtonElement).disabled).toBe(false);
    });

    it('menampilkan empty state dan paginasi', () => {
        const { unmount } = render(<NotifikasiIndex notifikasi={paginator([])} />);
        expect(screen.getByText('Belum ada notifikasi.')).toBeTruthy();
        expect(screen.queryByRole('navigation', { name: 'Paginasi notifikasi' })).toBeNull();
        unmount();

        render(<NotifikasiIndex notifikasi={paginator([item('a', false)], true)} />);
        expect(screen.getByRole('navigation', { name: 'Paginasi notifikasi' })).toBeTruthy();
    });
});
