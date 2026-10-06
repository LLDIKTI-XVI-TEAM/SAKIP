import type { ReactNode } from 'react';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, expect, it, vi } from 'vitest';
import KomponenIndex from '@/Pages/Indikator/Komponen/Index';
import { COMPONENTS, definition, REVISION } from './indikatorFixtures';

vi.mock('@inertiajs/react', async (original) => ({ ...(await original<typeof import('@inertiajs/react')>()), Head: () => null }));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
afterEach(() => { cleanup(); vi.restoreAllMocks(); });
const exact = '123456789.123456789012';
const editor = definition({ komponen: [{ ...COMPONENTS[0], bobot: exact }, COMPONENTS[1]] });

it('mempertahankan 12 digit bobot ketika membuka edit', async () => {
    const user = userEvent.setup();
    render(<KomponenIndex editor={editor} />);
    await waitFor(() => expect((screen.getAllByTitle('Ubah Komponen')[0] as HTMLButtonElement).disabled).toBe(false));
    await user.click(screen.getAllByTitle('Ubah Komponen')[0]);
    expect((screen.getByLabelText('Bobot komponen 1') as HTMLInputElement).value).toBe(exact);
});
it('draft yang dipertahankan memakai token asal setelah props baru', async () => {
    const user = userEvent.setup();
    const patch = vi.spyOn(router, 'patch').mockImplementation(() => undefined);
    const { rerender } = render(<KomponenIndex editor={editor} />);
    await waitFor(() => expect((screen.getAllByTitle('Ubah Komponen')[0] as HTMLButtonElement).disabled).toBe(false));
    await user.click(screen.getAllByTitle('Ubah Komponen')[0]);
    await user.type(screen.getByLabelText('Label komponen 1'), ' draft');
    rerender(<KomponenIndex editor={{ ...editor, revision: '2026-10-02T11:00:00.123456Z' }} />);
    await user.click(screen.getByRole('button', { name: 'Simpan' }));
    expect(patch.mock.calls[0]?.[1]).toHaveProperty('expected_updated_at', REVISION);
    expect(patch.mock.calls[0]?.[1]).toHaveProperty('komponen', [expect.objectContaining({ bobot: exact, label: 'Capaian aktual draft' })]);
});
it('mempertahankan pencarian dan filter peran di daftar komponen', async () => {
    const user = userEvent.setup(); render(<KomponenIndex editor={editor} />);
    await user.type(screen.getByLabelText('Cari Komponen'), 'aktual');
    expect(screen.queryByText('Total target')).toBeNull();
    await user.clear(screen.getByLabelText('Cari Komponen'));
    await user.selectOptions(screen.getByLabelText('Filter Peran'), 'penyebut');
    expect(screen.queryByText('Capaian aktual')).toBeNull();
    expect(screen.getByText('Total target')).toBeTruthy();
});
