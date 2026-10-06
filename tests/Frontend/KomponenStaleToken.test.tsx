import type { ReactNode } from 'react';
import { act, cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import KomponenIndex from '@/Pages/Indikator/Komponen/Index';
import { COMPONENTS, definition, REVISION } from './indikatorFixtures';

vi.mock('@inertiajs/react', async (original) => ({ ...(await original<typeof import('@inertiajs/react')>()), Head: () => null }));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
afterEach(() => { cleanup(); vi.restoreAllMocks(); });
const KONFLIK = 'Data indikator kinerja telah diperbarui oleh pengguna lain. Silakan muat ulang halaman.';
async function openEditor() {
    const user = userEvent.setup(); render(<KomponenIndex editor={definition()} />);
    await waitFor(() => expect((screen.getByRole('button', { name: 'Atur Formula' }) as HTMLButtonElement).disabled).toBe(false));
    await user.click(screen.getByRole('button', { name: 'Atur Formula' })); return user;
}
// Endpoint per baris telah dipensiunkan; create/update/delete mengirim satu PATCH definisi.
describe('Token dan konflik intent komponen atomik', () => {
    it.each(['create', 'update', 'delete'] as const)('menyertakan token baseline dan alasan pada %s', async (intent) => {
        const patch = vi.spyOn(router, 'patch').mockImplementation(() => undefined);
        const user = await openEditor();
        if (intent === 'create') { await user.click(screen.getAllByRole('button', { name: 'Tambah Komponen' }).at(-1)!); await user.type(screen.getByLabelText('Kode komponen 3'), 'X'); await user.type(screen.getByLabelText('Label komponen 3'), 'Tambahan'); }
        if (intent === 'update') await user.type(screen.getByLabelText('Label komponen 1'), ' baru');
        if (intent === 'delete') await user.click(screen.getByRole('button', { name: 'Hapus komponen 1' }));
        await user.type(screen.getByLabelText(/alasan perubahan/i), 'Perbaikan definisi indikator.');
        await user.click(screen.getByRole('button', { name: 'Simpan' }));
        expect(patch).toHaveBeenCalledTimes(1);
        expect(patch.mock.calls[0]?.[0]).toBe('/perencanaan/indikator/ind-1/formula');
        expect(patch.mock.calls[0]?.[1]).toMatchObject({ expected_updated_at: REVISION, alasan: 'Perbaikan definisi indikator.', return_to: 'komponen', hapus_komponen_ids: intent === 'delete' ? [COMPONENTS[0].id] : [] });
    });
    it.each(['expected_updated_at', 'konflik', 'hapus_komponen_ids.0'])('menampilkan error %s dan mempertahankan draft', async (field) => {
        const patch = vi.spyOn(router, 'patch').mockImplementation(() => undefined);
        const user = await openEditor(); await user.type(screen.getByLabelText('Label komponen 1'), ' draft');
        await user.click(screen.getByRole('button', { name: 'Simpan' }));
        const options = patch.mock.calls[0]?.[2] as unknown as { onError: (errors: Record<string, string>) => void; onFinish: () => void };
        await act(async () => { options.onError({ [field]: KONFLIK }); options.onFinish(); });
        expect(screen.getByText(KONFLIK)).toBeTruthy();
        expect((screen.getByLabelText('Label komponen 1') as HTMLInputElement).value).toBe('Capaian aktual draft');
        expect(screen.getByRole('heading', { name: 'Atur Formula — IKU-01' })).toBeTruthy();
    });
});
