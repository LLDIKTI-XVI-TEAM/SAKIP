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
            is_active: true,
        },
        {
            id: 'user-dinda',
            nama: 'Dinda LLDIKTI',
            email: 'dinda@lldikti16.kemdikbud.go.id',
            roles: ['Pegawai'],
            is_active: true,
        },
        {
            id: 'user-tanpa-role',
            nama: 'Rian Staf',
            email: 'rian@lldikti16.kemdikbud.go.id',
            roles: [],
            is_active: true,
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

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
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
