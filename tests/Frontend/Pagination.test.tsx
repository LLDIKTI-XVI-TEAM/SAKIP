import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';
import Pagination from '@/Pages/Pengukuran/Pagination';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href }: { children: ReactNode; href: string }) => <a href={href}>{children}</a>,
}));

afterEach(cleanup);

const dasar = { current_page: 1, last_page: 3, total: 55, prev_page_url: null as string | null, next_page_url: '/rencana-aksi?page=2' };

describe('Pagination', () => {
    it('default berlabel pengukuran agar pemakai existing tak berubah', () => {
        render(<Pagination pagination={dasar} />);
        expect(screen.getByRole('navigation', { name: 'Halaman pengukuran' })).toBeTruthy();
        expect(screen.getByText('Halaman 1 dari 3 · 55 pengukuran')).toBeTruthy();
    });

    it('label rencana aksi terbaca di footer dan aria', () => {
        render(<Pagination pagination={dasar} label="rencana aksi" />);
        expect(screen.getByRole('navigation', { name: 'Halaman rencana aksi' })).toBeTruthy();
        expect(screen.getByText('Halaman 1 dari 3 · 55 rencana aksi')).toBeTruthy();
    });

    it('satu halaman tidak merender navigasi', () => {
        const { container } = render(<Pagination pagination={{ ...dasar, last_page: 1, total: 5 }} />);
        expect(container.firstChild).toBeNull();
    });
});
