import React from 'react';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Pagination } from '@/Components/Pagination';

afterEach(cleanup);

describe('Pagination Component (SIMPEG Style)', () => {
    const mockLinks = [
        { url: null, label: '&laquo; Sebelumnya', active: false },
        { url: '/perjanjian-kinerja?page=1', label: '1', active: true },
        { url: '/perjanjian-kinerja?page=2', label: '2', active: false },
        { url: '/perjanjian-kinerja?page=2', label: 'Berikutnya &raquo;', active: false },
    ];

    it('merender info paginasi, dropdown per-halaman, dan nomor halaman aktif', () => {
        render(
            <Pagination
                links={mockLinks}
                from={1}
                to={10}
                total={25}
                currentPage={1}
                lastPage={3}
                perPage={10}
                resourceName="data"
            />
        );

        // Check meta text
        const meta = screen.getByTestId('pagination-meta');
        expect(meta.textContent).toContain('1 - 10 dari 25 data');

        // Check select per-page options
        const select = screen.getByLabelText('Pilih jumlah data per halaman') as HTMLSelectElement;
        expect(select).toBeDefined();
        expect(select.value).toBe('10');
        expect(screen.getByRole('option', { name: '10' })).toBeDefined();
        expect(screen.getByRole('option', { name: '25' })).toBeDefined();
        expect(screen.getByRole('option', { name: '50' })).toBeDefined();
        expect(screen.getByRole('option', { name: '100' })).toBeDefined();

        // Check active page
        const nav = screen.getByLabelText('Navigasi halaman');
        const activePage = nav.querySelector('[aria-current="page"]');
        expect(activePage).not.toBeNull();
        expect(activePage?.textContent).toBe('1');
    });

    it('mendukung passing objek pagination tunggal', () => {
        render(
            <Pagination
                pagination={{
                    links: mockLinks,
                    from: 11,
                    to: 20,
                    total: 35,
                    current_page: 2,
                    last_page: 4,
                    per_page: 10,
                }}
            />
        );

        const meta = screen.getByTestId('pagination-meta');
        expect(meta.textContent).toContain('11 - 20 dari 35 data');
    });

    it('menghitung otomatis from dan to saat tidak diberikan di objek pagination', () => {
        render(
            <Pagination
                pagination={{
                    total: 3,
                    current_page: 1,
                    last_page: 1,
                }}
                resourceName="penugasan"
            />
        );

        const meta = screen.getByTestId('pagination-meta');
        expect(meta.textContent).toContain('1 - 3 dari 3 penugasan');
    });

    it('memanggil onPerPageChange saat opsi jumlah data diubah', () => {
        const handlePerPageChange = vi.fn();
        render(
            <Pagination
                links={mockLinks}
                from={1}
                to={10}
                total={25}
                perPage={10}
                onPerPageChange={handlePerPageChange}
            />
        );

        const select = screen.getByLabelText('Pilih jumlah data per halaman');
        fireEvent.change(select, { target: { value: '25' } });

        expect(handlePerPageChange).toHaveBeenCalledWith(25);
    });

    it('memanggil onPageChange saat tombol halaman atau navigasi diklik', () => {
        const handlePageChange = vi.fn();
        render(
            <Pagination
                links={mockLinks}
                from={1}
                to={10}
                total={25}
                currentPage={1}
                lastPage={2}
                onPageChange={handlePageChange}
            />
        );

        // Click next page button
        const nextButton = screen.getByLabelText('Halaman berikutnya');
        fireEvent.click(nextButton);

        expect(handlePageChange).toHaveBeenCalledWith(2, expect.anything());
    });

    it('merender ellipsis ketika jumlah halaman melebihi 7', () => {
        render(
            <Pagination
                from={41}
                to={50}
                total={100}
                currentPage={5}
                lastPage={10}
                perPage={10}
            />
        );

        const ellipses = screen.getAllByText('...');
        expect(ellipses.length).toBeGreaterThan(0);
    });

    it('tidak me-render apapun jika total === 0', () => {
        const { container } = render(
            <Pagination
                links={[]}
                from={0}
                to={0}
                total={0}
            />
        );

        expect(container.firstChild).toBeNull();
    });

    it('tetap merender selector per-halaman ketika hanya ada 1 halaman dan total > 0', () => {
        render(
            <Pagination
                from={1}
                to={5}
                total={5}
                currentPage={1}
                lastPage={1}
                perPage={10}
            />
        );

        // Dropdown per-page should still be visible
        expect(screen.getByLabelText('Pilih jumlah data per halaman')).toBeDefined();
        // Page buttons nav should not be shown when lastPage <= 1
        expect(screen.queryByLabelText('Navigasi halaman')).toBeNull();
    });

    it('menghitung totalPages dan meta dengan benar saat total > perPage (kasus 32 izin dengan perPage 25)', () => {
        render(
            <Pagination
                total={32}
                currentPage={2}
                perPage={25}
                resourceName="izin"
            />
        );

        const meta = screen.getByTestId('pagination-meta');
        expect(meta.textContent).toContain('26 - 32 dari 32 izin');

        // Page buttons should render page 1 and 2
        const nav = screen.getByLabelText('Navigasi halaman');
        expect(nav).toBeDefined();
        expect(screen.getByText('1')).toBeDefined();
        const active = nav.querySelector('[aria-current="page"]');
        expect(active?.textContent).toBe('2');
    });

    it('mencegah bug from melebihi to/total (kasus 26 - 25 dari 25)', () => {
        render(
            <Pagination
                from={26}
                to={25}
                total={25}
                currentPage={2}
                perPage={25}
                resourceName="izin"
            />
        );

        const meta = screen.getByTestId('pagination-meta');
        // Should clamp so from never exceeds to/total
        expect(meta.textContent).toContain('25 - 25 dari 25 izin');
    });

    it('menormalisasi absolute url dari backend Laravel menjadi relative path', () => {
        render(
            <Pagination
                total={50}
                currentPage={1}
                perPage={25}
                nextUrl="http://localhost:8000/akses/izin-peran?page=2"
                resourceName="izin"
            />
        );

        const nextLink = screen.getByLabelText('Halaman berikutnya') as HTMLAnchorElement;
        expect(nextLink.getAttribute('href')).toBe('/akses/izin-peran?page=2');
    });
});

