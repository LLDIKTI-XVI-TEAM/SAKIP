import { act, cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useState } from 'react';
import { GrantUserAutocomplete, type UserOption } from '@/Components/Access/GrantUserAutocomplete';

describe('GrantUserAutocomplete Component', () => {
    const mockUsers: UserOption[] = [
        {
            id: 'user-dion',
            nama: 'Dion Kobi',
            email: 'dionkobi08@gmail.com',
            roles: ['Pimpinan'],
            status: 'aktif',
        },
        {
            id: 'user-dinda',
            nama: 'Dinda LLDIKTI',
            email: 'dinda@lldikti16.kemdikbud.go.id',
            roles: ['Pegawai'],
            status: 'aktif',
        },
        {
            id: 'user-tanpa-role',
            nama: 'Rian Staf',
            email: 'rian@lldikti16.kemdikbud.go.id',
            roles: [],
            status: 'aktif',
        },
    ];

    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn(async (url: string) => {
            const urlObj = new URL(url, 'http://localhost');
            const q = urlObj.searchParams.get('q') || '';
            const filtered = mockUsers.filter(
                (u) => u.nama.toLowerCase().includes(q.toLowerCase()) || u.email.toLowerCase().includes(q.toLowerCase())
            );
            return new Response(JSON.stringify({
                items: filtered,
                page: 1,
                hasMore: false,
            }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }));
    });

    it('merender label Pengguna Target dengan tanda bintang dan input placeholder yang benar', () => {
        render(
            <GrantUserAutocomplete
                value=""
                onChange={vi.fn()}
            />
        );

        expect(screen.getByText(/Pengguna Target/i)).toBeTruthy();
        expect(screen.getByText('*')).toBeTruthy();
        const input = screen.getByPlaceholderText(/Cari nama atau email pengguna.../i);
        expect(input).toBeTruthy();
    });

    it('tidak menjalankan pencarian jika input kurang dari 2 karakter', async () => {
        const user = userEvent.setup();
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        render(
            <GrantUserAutocomplete
                value=""
                onChange={vi.fn()}
                debounceMs={50}
            />
        );

        const input = screen.getByPlaceholderText(/Cari nama atau email pengguna.../i);
        await user.type(input, 'd');

        // Tunggu melebihi debounce
        await new Promise((resolve) => setTimeout(resolve, 100));

        expect(fetchMock).not.toHaveBeenCalled();
        expect(screen.queryByRole('listbox')).toBeNull();
    });

    it('menjalankan pencarian setelah minimal 2 karakter dan menampilkan hasil dengan nama, email, dan role', async () => {
        const user = userEvent.setup();

        render(
            <GrantUserAutocomplete
                value=""
                onChange={vi.fn()}
                debounceMs={50}
            />
        );

        const input = screen.getByPlaceholderText(/Cari nama atau email pengguna.../i);
        await user.type(input, 'di');

        // Menunggu suggestions muncul
        const listbox = await screen.findByRole('listbox');
        expect(listbox).toBeTruthy();

        // Dion Kobi dengan role Pimpinan
        expect(await screen.findByText('Dion Kobi')).toBeTruthy();
        expect(screen.getByText('dionkobi08@gmail.com')).toBeTruthy();
        expect(screen.getByText('Pimpinan')).toBeTruthy();

        // Dinda LLDIKTI dengan role Pegawai
        expect(screen.getByText('Dinda LLDIKTI')).toBeTruthy();
        expect(screen.getByText('dinda@lldikti16.kemdikbud.go.id')).toBeTruthy();
        expect(screen.getByText('Pegawai')).toBeTruthy();
    });

    it('menampilkan "Pengguna tidak ditemukan" jika hasil pencarian kosong', async () => {
        const user = userEvent.setup();

        render(
            <GrantUserAutocomplete
                value=""
                onChange={vi.fn()}
                debounceMs={50}
            />
        );

        const input = screen.getByPlaceholderText(/Cari nama atau email pengguna.../i);
        await user.type(input, 'zzzzzz');

        const emptyMessage = await screen.findByText(/Pengguna tidak ditemukan/i);
        expect(emptyMessage).toBeTruthy();
    });

    it('menampilkan "Tanpa Role" untuk pengguna yang tidak memiliki peran', async () => {
        const user = userEvent.setup();

        render(
            <GrantUserAutocomplete
                value=""
                onChange={vi.fn()}
                debounceMs={50}
            />
        );

        const input = screen.getByPlaceholderText(/Cari nama atau email pengguna.../i);
        await user.type(input, 'rian');

        expect(await screen.findByText('Rian Staf')).toBeTruthy();
        expect(screen.getByText('Tanpa Role')).toBeTruthy();
    });

    it('memilih pengguna target: menampilkan kartu pengguna terpilih dan memanggil onChange dengan user_id', async () => {
        const user = userEvent.setup();
        const handleChange = vi.fn();

        function TestWrapper() {
            const [val, setVal] = useState('');
            return (
                <GrantUserAutocomplete
                    value={val}
                    onChange={(id, selected) => {
                        setVal(id);
                        handleChange(id, selected);
                    }}
                    debounceMs={50}
                />
            );
        }

        render(<TestWrapper />);

        const input = screen.getByPlaceholderText(/Cari nama atau email pengguna.../i);
        await user.type(input, 'dion');

        const option = await screen.findByRole('option', { name: /Dion Kobi/i });
        await user.click(option);

        // onChange dipanggil dengan ID pengguna yang dipilih
        expect(handleChange).toHaveBeenCalledWith('user-dion', expect.objectContaining({
            id: 'user-dion',
            nama: 'Dion Kobi',
        }));

        // Field sekarang menampilkan pengguna terpilih di field yang sama
        expect(screen.getByText('Dion Kobi')).toBeTruthy();
        expect(screen.getByText('dionkobi08@gmail.com')).toBeTruthy();
        expect(screen.getByText('Pimpinan')).toBeTruthy();

        // Input pencarian tersembunyi digantikan oleh tampilan terpilih
        expect(screen.queryByPlaceholderText(/Cari nama atau email pengguna.../i)).toBeNull();

        // Tombol ganti/clear tersedia
        expect(screen.getByRole('button', { name: /Ganti pengguna target/i })).toBeTruthy();
    });

    it('tombol clear/reset mengosongkan pengguna terpilih dan kembali menampilkan input pencarian dengan fokus', async () => {
        const user = userEvent.setup();
        const handleChange = vi.fn();

        function TestWrapper() {
            const [val, setVal] = useState('user-dion');
            return (
                <GrantUserAutocomplete
                    value={val}
                    initialUser={mockUsers[0]}
                    onChange={(id, selected) => {
                        setVal(id);
                        handleChange(id, selected);
                    }}
                    debounceMs={50}
                />
            );
        }

        render(<TestWrapper />);

        // Pastikan Dion Kobi tampil
        expect(screen.getByText('Dion Kobi')).toBeTruthy();

        // Klik tombol clear/reset
        const clearBtn = screen.getByRole('button', { name: /Ganti pengguna target/i });
        await user.click(clearBtn);

        expect(handleChange).toHaveBeenCalledWith('', null);

        // Input pencarian muncul kembali
        const input = screen.getByPlaceholderText(/Cari nama atau email pengguna.../i);
        expect(input).toBeTruthy();
    });

    it('menampilkan pesan error ketika prop error diberikan', () => {
        render(
            <GrantUserAutocomplete
                value=""
                onChange={vi.fn()}
                error="Pengguna target wajib dipilih."
            />
        );

        const errorMsg = screen.getByRole('alert');
        expect(errorMsg.textContent).toBe('Pengguna target wajib dipilih.');
    });

    it('mendukung navigasi keyboard (ArrowDown, ArrowUp, Enter, Escape)', async () => {
        const user = userEvent.setup();
        const handleChange = vi.fn();

        function TestWrapper() {
            const [val, setVal] = useState('');
            return (
                <GrantUserAutocomplete
                    value={val}
                    onChange={(id, selected) => {
                        setVal(id);
                        handleChange(id, selected);
                    }}
                    debounceMs={50}
                />
            );
        }

        render(<TestWrapper />);

        const input = screen.getByPlaceholderText(/Cari nama atau email pengguna.../i);
        await user.type(input, 'di');

        await screen.findByRole('listbox');
        expect(await screen.findByText('Dion Kobi')).toBeTruthy();

        // Navigasi ke bawah dengan ArrowDown
        await user.keyboard('{ArrowDown}');
        // Enter untuk memilih item yang di-highlight
        await user.keyboard('{Enter}');

        expect(handleChange).toHaveBeenCalled();
        expect(screen.getByText('Dion Kobi')).toBeTruthy();
    });
});

it('endpoint Explorer tersedia tanpa mengubah default Grant dan respons lama diabaikan', async () => {
    const oldResponse: { resolve?: (value: Response) => void } = {};
    const freshResponse: { resolve?: (value: Response) => void } = {};
    const fetchMock = vi.fn()
        .mockImplementationOnce(() => new Promise<Response>((resolve) => { oldResponse.resolve = resolve; }))
        .mockImplementationOnce(() => new Promise<Response>((resolve) => { freshResponse.resolve = resolve; }));
    vi.stubGlobal('fetch', fetchMock);
    const user = userEvent.setup();
    render(<GrantUserAutocomplete value="" onChange={vi.fn()} endpoint="/akses/jelaskan-izin/opsi/pengguna" required={false} debounceMs={1} />);
    const input = screen.getByRole('combobox');
    await user.type(input, 'aa');
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
    await user.clear(input);
    await user.type(input, 'bb');
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
    expect(fetchMock.mock.calls[0][0]).toContain('/akses/jelaskan-izin/opsi/pengguna?');
    await act(async () => oldResponse.resolve?.(new Response(JSON.stringify({ items: [{ id: 'old', nama: 'Hasil Lama', email: 'old@example.test' }] }), { status: 200 })));
    expect(screen.queryByText('Hasil Lama')).toBeNull();
    expect(screen.getByText('Mencari pengguna...')).toBeTruthy();
    await act(async () => freshResponse.resolve?.(new Response(JSON.stringify({ items: [{ id: 'new', nama: 'Hasil Baru', email: 'new@example.test', status: 'nonaktif' }] }), { status: 200 })));
    expect(await screen.findByText('Hasil Baru')).toBeTruthy();
    await user.keyboard('{ArrowDown}');
    expect(input.getAttribute('aria-activedescendant')).toContain('-option-0');
    expect(screen.getByText('(nonaktif)')).toBeTruthy();
});

it('hasil pengguna halaman berikutnya tetap bounded dan dapat dipilih', async () => {
    const fetchMock = vi.fn(async (url: string) => {
        const second = new URL(url, 'http://localhost').searchParams.get('page') === '2';
        return new Response(JSON.stringify({ items: [{ id: second ? 'target-21' : 'target-1', nama: second ? 'Pengguna ke-21' : 'Pengguna pertama', email: 'duplicate@example.test' }], hasMore: !second }), { status: 200 });
    });
    vi.stubGlobal('fetch', fetchMock);
    const onChange = vi.fn();
    const user = userEvent.setup();
    render(<GrantUserAutocomplete value="" onChange={onChange} endpoint="/akses/jelaskan-izin/opsi/pengguna" debounceMs={1} />);
    await user.type(screen.getByRole('combobox'), 'duplicate');
    expect(await screen.findByText('Pengguna pertama')).toBeTruthy();
    await user.click(screen.getByRole('button', { name: 'Pengguna berikutnya' }));
    expect(await screen.findByText('Pengguna ke-21')).toBeTruthy();
    expect(screen.queryByText('Pengguna pertama')).toBeNull();
    await user.click(screen.getByRole('option', { name: /Pengguna ke-21/ }));
    expect(onChange).toHaveBeenCalledWith('target-21', expect.objectContaining({ id: 'target-21' }));
});

afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

it('keyboard tidak memilih hasil halaman lama selama halaman baru dimuat', async () => {
    const delayed: { resolve?: (value: Response) => void } = {};
    vi.stubGlobal('fetch', vi.fn(async (url: string) => {
        if (new URL(url, 'http://localhost').searchParams.get('page') === '2') return new Promise<Response>((resolve) => { delayed.resolve = resolve; });
        return new Response(JSON.stringify({ items: [{ id: 'old', nama: 'Pengguna lama', email: 'same@example.test' }], hasMore: true }), { status: 200 });
    }));
    const onChange = vi.fn();
    const user = userEvent.setup();
    render(<GrantUserAutocomplete value="" onChange={onChange} debounceMs={1} />);
    const input = screen.getByRole('combobox');
    await user.type(input, 'same');
    expect(await screen.findByText('Pengguna lama')).toBeTruthy();
    await user.click(screen.getByRole('button', { name: 'Pengguna berikutnya' }));
    await waitFor(() => expect(screen.getByText('Mencari pengguna...')).toBeTruthy());
    await user.click(input);
    await user.keyboard('{ArrowDown}{Enter}');
    expect(onChange).not.toHaveBeenCalled();
    expect(input.getAttribute('aria-activedescendant')).toBeNull();
    await act(async () => delayed.resolve?.(new Response(JSON.stringify({ items: [{ id: 'new', nama: 'Pengguna baru', email: 'same@example.test' }], hasMore: false }), { status: 200 })));
    expect(await screen.findByText('Pengguna baru')).toBeTruthy();
    await user.keyboard('{ArrowDown}{Enter}');
    expect(onChange).toHaveBeenCalledWith('new', expect.objectContaining({ id: 'new' }));
});

it('metadata pengguna terpilih mengikuti initialUser terbaru pada id yang sama', () => {
    const original = { id: 'same-id', nama: 'Nama Awal', email: 'same@example.test', status: 'aktif' as const };
    const { rerender } = render(<GrantUserAutocomplete value={original.id} initialUser={original} onChange={vi.fn()} />);
    rerender(<GrantUserAutocomplete value={original.id} initialUser={{ ...original, nama: 'Nama Baru', status: 'nonaktif' }} onChange={vi.fn()} />);
    expect(screen.getByText('Nama Baru')).toBeTruthy();
    expect(screen.queryByText('Nama Awal')).toBeNull();
    expect(screen.getByText('(nonaktif)')).toBeTruthy();
});

it.each(['/akses/grant/opsi/pengguna', '/akses/jelaskan-izin/opsi/pengguna'])(
    'pagination pengguna dapat pulih setelah halaman kedua gagal pada %s',
    async (endpoint) => {
        let secondPageAttempts = 0;
        const requestedPages: string[] = [];
        vi.stubGlobal('fetch', vi.fn(async (url: string) => {
            const params = new URL(url, 'http://localhost').searchParams;
            const page = params.get('page')!;
            requestedPages.push(page);
            expect(params.get('q')).toBe('sintetis');
            if (page === '2' && ++secondPageAttempts === 1) return new Response('', { status: 503 });
            return new Response(JSON.stringify({
                items: [{ id: 'target-' + page, nama: 'Pengguna Sintetis ' + page, email: 'sintetis@example.test' }],
                hasMore: page === '1',
            }), { status: 200 });
        }));
        const onChange = vi.fn();
        const user = userEvent.setup();
        render(<GrantUserAutocomplete value="" onChange={onChange} endpoint={endpoint} debounceMs={1} />);
        const input = screen.getByRole('combobox');
        await user.click(input);
        await user.paste('sintetis');
        expect(await screen.findByText('Pengguna Sintetis 1')).toBeTruthy();
        await user.click(screen.getByRole('button', { name: 'Pengguna berikutnya' }));
        expect(await screen.findByRole('alert')).toHaveProperty('textContent', 'Daftar pengguna belum dapat dimuat. Coba cari kembali.');
        expect(screen.queryByRole('option')).toBeNull();
        expect(screen.queryByRole('button', { name: 'Pengguna berikutnya' })).toBeNull();
        const previous = screen.getByRole('button', { name: 'Pengguna sebelumnya' });
        expect(previous.hasAttribute('disabled')).toBe(false);
        expect(input).toHaveProperty('value', 'sintetis');
        await user.click(previous);
        expect(await screen.findByText('Pengguna Sintetis 1')).toBeTruthy();
        await user.click(screen.getByRole('button', { name: 'Pengguna berikutnya' }));
        expect(await screen.findByText('Pengguna Sintetis 2')).toBeTruthy();
        expect(screen.queryByRole('alert')).toBeNull();
        expect(requestedPages).toEqual(['1', '2', '1', '2']);
        await user.click(screen.getByRole('option', { name: /Pengguna Sintetis 2/ }));
        expect(onChange).toHaveBeenCalledWith('target-2', expect.objectContaining({ id: 'target-2' }));
    }
);
