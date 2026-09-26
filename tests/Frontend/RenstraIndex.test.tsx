import type { ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterAll, afterEach, beforeEach, expect, it, vi } from 'vitest';
import Index from '@/Pages/Renstra/Index';

vi.mock('@inertiajs/react', async (original) => ({
    ...await original<typeof import('@inertiajs/react')>(),
    Head: () => null,
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));

const originalShowModal = HTMLDialogElement.prototype.showModal;
const originalClose = HTMLDialogElement.prototype.close;

beforeEach(() => {
    HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    HTMLDialogElement.prototype.close = function () { this.open = false; };
});
afterEach(() => cleanup());
afterAll(() => {
    HTMLDialogElement.prototype.showModal = originalShowModal;
    HTMLDialogElement.prototype.close = originalClose;
});

it.each([
    { dapatUnggah: false, tampil: false },
    { dapatUnggah: true, tampil: true },
])('modal tambah Renstra mengikuti izin unggah lampiran: $dapatUnggah', async ({ dapatUnggah, tampil }) => {
    const user = userEvent.setup();

    render(
        <Index
            renstra={{ data: [], current_page: 1, last_page: 1, from: null, to: null, total: 0, links: [] }}
            filters={{ q: '', status: null }}
            can={{ 'renstra:create': true, uploadAttachment: dapatUnggah }}
        />
    );

    await user.click(screen.getByRole('button', { name: 'Tambah Renstra' }));

    expect(screen.queryByRole('button', { name: 'Tambah Lampiran' }) !== null).toBe(tampil);
    expect(screen.queryByText('Anda tidak memiliki izin untuk mengunggah atau menambahkan lampiran berkas.') !== null).toBe(!tampil);
});
