import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import Index from '@/Pages/RencanaAksi/Index';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));
vi.mock('@/hooks/useLabelUnit', () => ({ useLabelUnit: () => 'Unit Kerja' }));

afterEach(() => cleanup());

const pagination = { current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null };

it('tab antrean aktif secara default dan menautkan tab disahkan', () => {
    render(<Index rencanaAksis={[]} pagination={pagination} status="antrean" />);

    expect(screen.getByRole('link', { name: 'Antrean' }).getAttribute('aria-current')).toBe('page');
    expect(screen.getByRole('link', { name: 'Disahkan' }).getAttribute('href')).toBe('/rencana-aksi?status=disahkan');
    expect(screen.getByText('Semua pengajuan telah diproses')).toBeTruthy();
});

it('tab disahkan menampilkan daftar monitoring read-only', () => {
    render(<Index rencanaAksis={[]} pagination={pagination} status="disahkan" />);

    expect(screen.getByRole('link', { name: 'Disahkan' }).getAttribute('aria-current')).toBe('page');
    expect(screen.getByText('Daftar Rencana Aksi Disahkan')).toBeTruthy();
    expect(screen.getByText('Belum ada rencana aksi disahkan')).toBeTruthy();
});
