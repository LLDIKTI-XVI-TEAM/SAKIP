import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Pending from '@/Pages/Auth/Pending';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal<typeof import('@inertiajs/react')>();
    return { ...original, Head: () => null };
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('Akun menunggu aktivasi', () => {
    it('menampilkan dan menyalin ID akun yang dipakai operator bootstrap', async () => {
        const id = '1380daa1-7af3-4884-aa0c-178614d7de78';
        const writeText = vi.fn().mockResolvedValue(undefined);
        const user = userEvent.setup();
        Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } });

        render(<Pending auth={{ user: { id, nama: 'Calon Admin', email: 'calon@example.test', is_active: false } }} />);

        expect(screen.getByText(id)).toBeTruthy();
        await user.click(screen.getByRole('button', { name: 'Salin ID akun' }));
        expect(writeText).toHaveBeenCalledWith(id);
        expect(screen.getByRole('status').textContent).toContain('ID akun tersalin');
    });
});
