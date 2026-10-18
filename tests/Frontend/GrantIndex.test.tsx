import type { ReactNode } from 'react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import GrantIndex from '@/Pages/Akses/GrantIndex';

const methods = ['showModal', 'close'] as const;
const originals = methods.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });
});
afterAll(() => methods.forEach((name, index) => {
    const descriptor = originals[index];
    if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
}));

const currentPage = vi.hoisted(() => ({ props: { auth: { user: { id: 'admin-1' }, can: { audit: false } }, errors: {} }, flash: {} }));

vi.mock('@inertiajs/react', async (original) => ({
    ...await original<typeof import('@inertiajs/react')>(),
    Head: () => null,
    usePage: () => currentPage,
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children, title, breadcrumbs }: { children: ReactNode; title: string; breadcrumbs?: { label: string }[] }) => <main>
        <h1>{title}</h1>
        <nav aria-label="Breadcrumb">{breadcrumbs?.map((item) => item.label).join(' / ')}</nav>
        {children}
    </main>,
}));

const izin = (permission_id: string, label: string, aktif = true) => ({ grant_id: `grant-${permission_id}`, permission_id, label, aktif });
const kelompok = (override: Record<string, unknown> = {}) => ({
    key: 'user-2:unit-1',
    user_id: 'user-2',
    user_name: 'Pegawai Test',
    user_email: 'pegawai@example.test',
    user_roles: ['Pegawai'],
    unit_id: 'unit-1',
    unit_nama: 'Bagian Akademik',
    tidak_berlaku: [] as string[],
    izin: [izin('perm-1', 'Mengisi pengukuran'), izin('perm-2', 'Mengubah pengukuran')],
    ...override,
});
type Props = Parameters<typeof GrantIndex>[0];
const props = (override: Partial<Props> = {}): Props => ({
    grants: { data: [kelompok()], links: [], from: 1, to: 1, total: 1 },
    filters: { search: '', unit_id: 'all' },
    units: [{ id: 'unit-1', nama: 'Bagian Akademik' }],
    unitPermissions: [
        { id: 'perm-1', kode: 'pengukuran:create', label: 'Mengisi pengukuran' },
        { id: 'perm-2', kode: 'pengukuran:update', label: 'Mengubah pengukuran' },
        { id: 'perm-3', kode: 'kegiatan:create', label: 'Membuat kegiatan' },
    ],
    can: { create_grant: true, kelola_grant: true, revoke_grant: true },
    ...override,
});
const tokenOnly = (html: string) => {
    expect(html).not.toMatch(/#[0-9a-f]{6}\b/i);
    expect(html).not.toMatch(/text-\[(9|10|11)px\]/);
    expect(html).not.toMatch(/\b(?:bg|text|border|ring)-(?:slate|gray|blue|red|rose|amber)-\d{2,3}\b/);
};

beforeEach(() => {
    vi.spyOn(router, 'get').mockImplementation(() => undefined);
    vi.spyOn(router, 'post').mockImplementation(() => undefined);
    vi.spyOn(router, 'put').mockImplementation(() => undefined);
    vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify({
        items: [{ id: 'user-9', nama: 'Pengguna Baru', email: 'baru@example.test', status: 'aktif', roles: ['Pegawai'] }],
        page: 1,
        hasMore: false,
    }), { status: 200 })));
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

async function bukaDanIsiFormBeri(user: ReturnType<typeof userEvent.setup>, pilihan: string[] = ['Mengisi pengukuran'], alasan = 'Penugasan khusus input data triwulan') {
    await user.click(screen.getByRole('button', { name: 'Beri izin unit' }));
    await user.type(await screen.findByPlaceholderText(/Cari nama atau email pengguna/i), 'Peng');
    await user.click(await screen.findByRole('option', { name: /Pengguna Baru/i }));
    const dialog = screen.getByRole('dialog');
    for (const label of pilihan) await user.click(within(dialog).getByRole('checkbox', { name: label }));
    await user.selectOptions(within(dialog).getByLabelText(/^Unit/), 'unit-1');
    if (alasan !== '') await user.type(within(dialog).getByLabelText(/^Alasan pemberian/), alasan);
    await user.click(within(dialog).getByRole('button', { name: 'Simpan' }));
    return vi.mocked(router.post).mock.calls[0][2];
}

describe('Halaman Izin Unit', () => {
    it('memakai satu nama halaman pada judul dan breadcrumb', () => {
        render(<GrantIndex {...props()} />);
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Izin Unit');
        expect(screen.getByRole('navigation', { name: 'Breadcrumb' }).textContent).toBe('Izin Unit');
    });

    it('satu baris per pengguna dan unit memuat jumlah izin dimiliki, status dari server, tanpa daftar izin dan kolom Pemberian', () => {
        render(<GrantIndex {...props({ grants: { data: [
            kelompok(),
            kelompok({ key: 'user-3:unit-2', user_id: 'user-3', user_name: 'Tanpa Peran', user_roles: [], unit_id: 'unit-2', unit_nama: 'Unit Lama', tidak_berlaku: ['Unit nonaktif', 'Akun nonaktif'], izin: [izin('perm-9', 'Izin lama', false)] }),
            kelompok({ key: 'user-4:unit-1', user_id: 'user-4', user_name: 'PJ Lengkap', izin: [izin('perm-1', 'Mengisi pengukuran'), izin('perm-2', 'Mengubah pengukuran'), izin('perm-3', 'Membuat kegiatan')] }),
        ], links: [], from: 1, to: 3, total: 3 } })} />);
        expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual(['Pengguna', 'Unit', 'Izin dimiliki', 'Status', 'Aksi']);
        const [, berlaku, tidakBerlaku, lengkap] = screen.getAllByRole('row');
        expect(within(berlaku).getByText('Pegawai Test')).toBeTruthy();
        expect(within(berlaku).getByText('Bagian Akademik')).toBeTruthy();
        expect(within(berlaku).getByText('2 dari 3')).toBeTruthy();
        expect(within(berlaku).queryByText('Lengkap')).toBeNull();
        expect(within(berlaku).queryByText('Mengisi pengukuran')).toBeNull();
        expect(within(berlaku).getByText('Berlaku')).toBeTruthy();
        // Izin di luar pilihan aktif (mis. izin nonaktif) tidak dihitung karena tidak berlaku.
        expect(within(tidakBerlaku).getByText('0 dari 3')).toBeTruthy();
        expect(within(tidakBerlaku).getByText('Belum memiliki peran')).toBeTruthy();
        expect(within(tidakBerlaku).getByText('Tidak berlaku')).toBeTruthy();
        expect(within(tidakBerlaku).getByText('Unit nonaktif · Akun nonaktif')).toBeTruthy();
        expect(within(lengkap).getByText('3 dari 3')).toBeTruthy();
        expect(within(lengkap).getByText('Lengkap')).toBeTruthy();
        expect(screen.queryByText('Pemberian')).toBeNull();
    });

    it('aksi baris berisi detail, kelola, dan cabut semua sesuai capability server', () => {
        const { unmount } = render(<GrantIndex {...props()} />);
        expect(screen.getByRole('link', { name: 'Lihat detail izin unit Pegawai Test di Bagian Akademik' }).getAttribute('href')).toBe('/akses/grant/user-2/unit-1');
        expect(screen.getByRole('button', { name: 'Kelola izin unit Pegawai Test di Bagian Akademik' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Cabut semua izin unit Pegawai Test di Bagian Akademik' })).toBeTruthy();
        unmount();

        render(<GrantIndex {...props({ can: { create_grant: false, kelola_grant: false, revoke_grant: false } })} />);
        expect(screen.getByRole('link', { name: /Lihat detail izin unit/ })).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Beri izin unit' })).toBeNull();
        expect(screen.queryByRole('button', { name: /Kelola izin unit/ })).toBeNull();
        expect(screen.queryByRole('button', { name: /Cabut semua izin unit/ })).toBeNull();
    });

    it('tabel memakai grid HP tanpa scroll horizontal, dan halaman beserta form hanya memakai token design system', async () => {
        const user = userEvent.setup();
        const { container } = render(<GrantIndex {...props()} />);
        const table = screen.getByRole('table');
        expect(table.className).toContain('block');
        expect(table.className).toContain('md:table');
        expect(table.parentElement?.className).not.toContain('overflow-x-auto');
        tokenOnly(container.innerHTML);

        await bukaDanIsiFormBeri(user);
        tokenOnly(document.body.innerHTML);
    });

    it('filter berlabel dan menyaring unit di server', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...props()} />);
        expect(screen.getByLabelText('Cari izin unit')).toBeTruthy();
        await user.selectOptions(screen.getByLabelText('Filter unit'), 'unit-1');
        expect(router.get).toHaveBeenCalledWith('/akses/grant', { search: undefined, unit_id: 'unit-1' }, expect.objectContaining({ preserveState: true }));
    });

    it('pesan kosong membedakan filter aktif dan belum ada data', () => {
        const kosong = { data: [], links: [], from: null, to: null, total: 0 };
        const { unmount } = render(<GrantIndex {...props({ grants: kosong, filters: { search: 'zzz', unit_id: 'all' } })} />);
        expect(screen.getByText('Tidak ada izin unit yang sesuai')).toBeTruthy();
        unmount();
        render(<GrantIndex {...props({ grants: kosong })} />);
        expect(screen.getByText('Belum ada izin unit')).toBeTruthy();
    });

    it('halaman melewati halaman terakhir (mis. baris terakhirnya baru dicabut) menawarkan kembali ke halaman pertama dengan filter tetap', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...props({ grants: { data: [], links: [], from: null, to: null, total: 16 }, filters: { search: 'Peg', unit_id: 'unit-1' } })} />);
        expect(screen.getByText('Tidak ada izin unit di halaman ini.')).toBeTruthy();
        expect(screen.queryByText('Belum ada izin unit')).toBeNull();
        await user.click(screen.getByRole('button', { name: 'Ke halaman pertama' }));
        expect(router.get).toHaveBeenCalledWith('/akses/grant', { search: 'Peg', unit_id: 'unit-1' }, expect.objectContaining({ preserveState: true }));
    });

    it('membuka lagi Cabut semua untuk baris yang sama mengosongkan alasan dan galat sebelumnya', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...props()} />);
        const tombol = screen.getByRole('button', { name: 'Cabut semua izin unit Pegawai Test di Bagian Akademik' });
        await user.click(tombol);
        let dialog = screen.getByRole('dialog', { name: 'Cabut semua izin unit Pegawai Test?' });
        await user.type(within(dialog).getByRole('textbox'), 'Penugasan selesai');
        await user.click(within(dialog).getByRole('button', { name: 'Cabut semua' }));
        const options = vi.mocked(router.put).mock.calls[0][2];
        // Inertia selalu memanggil onFinish setelah onHttpException; tanpa itu modal masih sibuk dan Batal diabaikan.
        await act(async () => {
            options?.onHttpException?.({ status: 403, data: { message: 'Izin tindakan ditolak.' }, headers: {} });
            options?.onFinish?.({} as never);
        });
        expect(within(dialog).getByRole('alert').textContent).toContain('Izin tindakan ditolak.');

        await user.click(within(dialog).getByRole('button', { name: 'Batal' }));
        await user.click(tombol);
        dialog = screen.getByRole('dialog', { name: 'Cabut semua izin unit Pegawai Test?' });
        expect(within(dialog).getByRole<HTMLTextAreaElement>('textbox').value).toBe('');
        expect(within(dialog).queryByRole('alert')).toBeNull();
    });

    it('dibuka dari halaman Penanggung Jawab: modal Beri izin unit langsung terbuka dengan PJ dan unitnya terisi', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...props({ beri: { user: { id: 'user-9', nama: 'PJ Unit', email: 'pj@example.test', status: 'aktif', roles: ['Pegawai'] }, unit_id: 'unit-1' } })} />);
        const dialog = screen.getByRole('dialog', { name: 'Beri izin unit' });
        expect(within(dialog).getByText('PJ Unit')).toBeTruthy();
        expect(within(dialog).getByLabelText<HTMLSelectElement>(/^Unit/).value).toBe('unit-1');

        await user.click(within(dialog).getByRole('checkbox', { name: 'Mengisi pengukuran' }));
        await user.click(within(dialog).getByRole('button', { name: 'Simpan' }));
        expect(router.post).toHaveBeenCalledWith('/akses/grant', expect.objectContaining({ user_id: 'user-9', unit_id: 'unit-1', permission_ids: ['perm-1'] }), expect.anything());
    });

    it('paginasi memakai komponen bersama', () => {
        render(<GrantIndex {...props({ grants: { data: [kelompok()], links: [
            { url: null, label: '&laquo; Previous', active: false },
            { url: '/akses/grant?page=1', label: '1', active: true },
            { url: '/akses/grant?page=2', label: '2', active: false },
            { url: '/akses/grant?page=2', label: 'Next &raquo;', active: false },
        ], from: 1, to: 15, total: 20 } })} />);
        const nav = screen.getByRole('navigation', { name: 'Halaman izin unit' });
        expect(within(nav).getByRole('link', { name: 'Berikutnya ›' }).getAttribute('href')).toBe('/akses/grant?page=2');
    });

    it('beri izin: beberapa izin terkirim urut katalog dan alasan boleh kosong', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...props()} />);
        await bukaDanIsiFormBeri(user, ['Membuat kegiatan', 'Mengisi pengukuran'], '');
        expect(screen.getByLabelText<HTMLTextAreaElement>('Alasan pemberian (opsional)').required).toBe(false);
        expect(router.post).toHaveBeenCalledWith('/akses/grant', expect.objectContaining({ permission_ids: ['perm-1', 'perm-3'], unit_id: 'unit-1', alasan: '' }), expect.anything());
    });

    it('beri izin: pilih semua dan Simpan menunggu minimal satu izin', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...props()} />);
        await user.click(screen.getByRole('button', { name: 'Beri izin unit' }));
        const dialog = screen.getByRole('dialog');
        const simpan = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Simpan' });
        expect(simpan.disabled).toBe(true);
        const semua = within(dialog).getByRole<HTMLInputElement>('checkbox', { name: 'Pilih semua izin' });
        await user.click(semua);
        expect(within(dialog).getAllByRole<HTMLInputElement>('checkbox').every((box) => box.checked)).toBe(true);
        expect(simpan.disabled).toBe(false);
        await user.click(semua);
        expect(within(dialog).getAllByRole<HTMLInputElement>('checkbox').some((box) => box.checked)).toBe(false);
    });

    it('beri izin: galat validasi tampil sekali di field, galat koneksi di banner tanpa menandai field', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...props()} />);
        const options = await bukaDanIsiFormBeri(user);
        const sudahAda = 'Pengguna sudah memiliki izin ini pada unit tersebut: Mengisi pengukuran.';
        await act(async () => { options?.onError?.({ permission_ids: sudahAda }); });
        expect(within(screen.getByRole('group', { name: /^Izin/ })).getByRole('alert').textContent).toBe(sudahAda);
        expect(screen.getAllByText(sudahAda)).toHaveLength(1);

        let handled: unknown;
        await act(async () => { handled = options?.onNetworkError?.(new Error('offline') as never); });
        expect(handled).toBe(false);
        expect(screen.getByText(/Hasil pemberian izin belum diketahui/)).toBeTruthy();
        expect(screen.getByLabelText(/^Alasan pemberian/).getAttribute('aria-invalid')).not.toBe('true');
    });

    it('kelola izin: izin dimiliki sudah tercentang, menambah dan mencabut terkirim dengan daftar yang dilihat', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...props()} />);
        await user.click(screen.getByRole('button', { name: 'Kelola izin unit Pegawai Test di Bagian Akademik' }));
        const dialog = screen.getByRole('dialog', { name: /Kelola izin unit/ });
        const simpan = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Simpan perubahan' });
        expect(within(dialog).getByRole<HTMLInputElement>('checkbox', { name: 'Mengisi pengukuran' }).checked).toBe(true);
        expect(within(dialog).getByRole<HTMLInputElement>('checkbox', { name: 'Membuat kegiatan' }).checked).toBe(false);
        expect(within(dialog).getByRole('status').textContent).toBe('Belum ada perubahan.');
        expect(simpan.disabled).toBe(true);

        await user.click(within(dialog).getByRole('checkbox', { name: 'Membuat kegiatan' }));
        await user.click(within(dialog).getByRole('checkbox', { name: 'Mengubah pengukuran' }));
        expect(within(dialog).getByRole('status').textContent).toBe('1 izin diberikan, 1 izin dicabut.');
        const alasan = within(dialog).getByLabelText<HTMLTextAreaElement>(/^Alasan perubahan/);
        expect(alasan.required).toBe(true);
        expect(simpan.disabled).toBe(true);
        await user.type(alasan, 'Penyesuaian tugas PJ');
        await user.click(simpan);

        expect(router.put).toHaveBeenCalledWith('/akses/grant/kelola', expect.objectContaining({
            user_id: 'user-2', unit_id: 'unit-1', permission_ids: ['perm-1', 'perm-3'], expected_permission_ids: ['perm-1', 'perm-2'], alasan: 'Penyesuaian tugas PJ',
        }), expect.anything());
    });

    it('kelola izin: hanya menambah membuat alasan opsional, dan konflik data meminta muat ulang', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...props()} />);
        await user.click(screen.getByRole('button', { name: /Kelola izin unit Pegawai Test/ }));
        const dialog = screen.getByRole('dialog', { name: /Kelola izin unit/ });
        await user.click(within(dialog).getByRole('checkbox', { name: 'Membuat kegiatan' }));
        expect(within(dialog).getByLabelText<HTMLTextAreaElement>('Alasan perubahan (opsional)').required).toBe(false);
        await user.click(within(dialog).getByRole('button', { name: 'Simpan perubahan' }));

        const options = vi.mocked(router.put).mock.calls[0][2];
        const konflik = 'Izin unit pengguna ini telah berubah. Muat ulang data sebelum menyimpan.';
        await act(async () => { options?.onError?.({ expected_permission_ids: konflik } as never); });
        expect(within(dialog).getByRole('alert').textContent).toContain(konflik);
        expect(within(dialog).getByRole('button', { name: 'Muat ulang data' })).toBeTruthy();
    });

    it('cabut semua memakai modal alasan audit dan mengirim daftar kosong', async () => {
        const user = userEvent.setup();
        render(<GrantIndex {...props()} />);
        await user.click(screen.getByRole('button', { name: 'Cabut semua izin unit Pegawai Test di Bagian Akademik' }));
        const dialog = screen.getByRole('dialog', { name: 'Cabut semua izin unit Pegawai Test?' });
        const konfirmasi = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Cabut semua' });
        expect(konfirmasi.disabled).toBe(true);
        await user.type(within(dialog).getByRole('textbox'), 'Penugasan selesai');
        await user.click(konfirmasi);

        expect(router.put).toHaveBeenCalledWith('/akses/grant/kelola', {
            user_id: 'user-2', unit_id: 'unit-1', permission_ids: [], expected_permission_ids: ['perm-1', 'perm-2'], alasan: 'Penugasan selesai',
        }, expect.anything());
    });
});
