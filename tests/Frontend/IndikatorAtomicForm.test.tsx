import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { afterEach, expect, it, vi } from 'vitest';
import { IndikatorModal } from '@/Pages/Perencanaan/SasaranIndikator/IndikatorModal';
import { COMPONENTS, definition, indicator, REVISION } from './indikatorFixtures';
import type { DefinitionEditor } from '@/Pages/Indikator/Komponen/definition';

afterEach(() => { cleanup(); vi.restoreAllMocks(); });
const can = { sasaran_create: false, sasaran_update: false, sasaran_delete: false, indikator_create: true, indikator_read: true, indikator_update: true, indikator_delete: false, komponen_read: true, komponen_create: true, komponen_update: true, komponen_delete: true };
const common = { isOpen: true, sasarans: [{ id: 'sas-1', renstra_id: 'ren-1', kode: 'SS-01', deskripsi: 'Sasaran', urutan: 1, indikator_kinerjas: [] }], units: [{ id: 'unit-a', nama: 'Unit A' }], regulasis: [], can };
it('membuat nonmanual bersama definisi dalam satu POST', async () => {
    const user = userEvent.setup(); const post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
    render(<IndikatorModal {...common} indikator={null} editor={null} onClose={vi.fn()} />);
    await user.type(screen.getByLabelText(/Nama Indikator Kinerja/), 'Skor baru');
    await user.selectOptions(screen.getByLabelText(/Tipe Perhitungan/), 'penjumlahan'); await user.click(screen.getByRole('button', { name: 'Tambah Komponen' }));
    await user.type(screen.getByLabelText('Kode komponen 1'), 'A'); await user.type(screen.getByLabelText('Label komponen 1'), 'Skor pertama');
    await user.click(screen.getByRole('button', { name: 'Tambah Indikator' }));
    expect(post).toHaveBeenCalledTimes(1); expect(post.mock.calls[0]?.[1]).toMatchObject({ tipe_perhitungan: 'penjumlahan', komponen: [{ kode: 'A', label: 'Skor pertama', peran: 'penjumlah', bobot: '1', aktif: true }], request_id: expect.any(String) });
    expect(post.mock.calls[0]?.[1]).not.toHaveProperty('kode');
});
it('metadata repair menyertakan hanya child berubah dan token editor asal', async () => {
    const user = userEvent.setup(); const put = vi.spyOn(router, 'put').mockImplementation(() => undefined);
    const initial = definition(); const { rerender } = render(<IndikatorModal {...common} indikator={indicator()} editor={initial} onClose={vi.fn()} />);
    await user.type(screen.getByLabelText(/Nama Indikator Kinerja/), ' revisi'); await user.type(screen.getByLabelText('Label komponen 2'), ' repair');
    rerender(<IndikatorModal {...common} indikator={indicator({ updated_at: 'new' })} editor={{ ...initial, revision: 'new' }} onClose={vi.fn()} />);
    await user.click(screen.getByRole('button', { name: 'Simpan Perubahan' }));
    expect(put.mock.calls[0]?.[1]).toMatchObject({ expected_updated_at: REVISION, nama: 'Capaian kinerja revisi', komponen: [{ ...COMPONENTS[1], label: 'Total target repair' }] });
});
it('metadata-only tanpa child read tidak mengirim array kosong sebagai definisi', async () => {
    const user = userEvent.setup(); const put = vi.spyOn(router, 'put').mockImplementation(() => undefined);
    const editor: DefinitionEditor = definition({ komponen: null });
    render(<IndikatorModal {...common} indikator={indicator()} editor={editor} onClose={vi.fn()} />);
    expect(screen.queryByRole('button', { name: 'Komponen Formula' })).toBeNull(); await user.click(screen.getByRole('button', { name: 'Simpan Perubahan' }));
    expect(put.mock.calls[0]?.[1]).not.toHaveProperty('komponen'); expect(put.mock.calls[0]?.[1]).not.toHaveProperty('hapus_komponen_ids');
});
it('no-op induk menahan draft dan modal tanpa mengganti token', async () => {
    const user = userEvent.setup(); const put = vi.spyOn(router, 'put').mockImplementation(() => undefined); const close = vi.fn();
    render(<IndikatorModal {...common} indikator={indicator()} editor={definition()} onClose={close} />);
    await user.click(screen.getByRole('button', { name: 'Simpan Perubahan' }));
    const data = put.mock.calls[0]?.[1] as Record<string, unknown>;
    const options = put.mock.calls[0]?.[2] as unknown as { onSuccess: (value: unknown) => void; onFinish: () => void };
    await act(async () => { options.onSuccess({ props: {}, flash: { indikatorMutation: { request_id: data.request_id, indikator_id: 'ind-1', status: 'unchanged', revision: REVISION } } }); options.onFinish(); });
    expect(screen.getByText('Tidak ada perubahan.')).toBeTruthy(); expect(close).not.toHaveBeenCalled();
    await user.click(screen.getByRole('button', { name: 'Simpan Perubahan' })); expect(put.mock.calls[1]?.[1]).toHaveProperty('expected_updated_at', REVISION);
});
it('mempertahankan kedua error child pada baris editor 2 dan 3', async () => {
    const user = userEvent.setup();
    const put = vi.spyOn(router, 'put').mockImplementation(() => undefined);
    const third = { ...COMPONENTS[0], id: '00000000-0000-4000-8000-000000000003', kode: 'X', label: 'Tambahan', urutan: 3 };
    render(<IndikatorModal {...common} indikator={indicator()} editor={definition({ komponen: [...COMPONENTS, third] })} onClose={vi.fn()} />);
    await user.type(screen.getByLabelText('Label komponen 2'), ' revisi');
    await user.type(screen.getByLabelText('Label komponen 3'), ' revisi');
    await user.click(screen.getByRole('button', { name: 'Simpan Perubahan' }));
    const options = put.mock.calls[0]?.[2] as unknown as { onError: (errors: Record<string, string>) => void; onFinish: () => void };
    await act(async () => {
        options.onError({ 'komponen.0.label': 'Label kedua ditolak.', 'komponen.1.label': 'Label ketiga ditolak.', alasan: 'Alasan wajib diisi.' });
        options.onFinish();
    });
    expect(screen.getByLabelText('Label komponen 2').getAttribute('aria-invalid')).toBe('true');
    expect(screen.getByLabelText('Label komponen 3').getAttribute('aria-invalid')).toBe('true');
    expect(screen.getByLabelText('Label komponen 1').getAttribute('aria-invalid')).not.toBe('true');
    const alasan = screen.getByRole('textbox', { name: 'Alasan perubahan' });
    expect(alasan.getAttribute('aria-invalid')).toBe('true');
    expect(document.getElementById(alasan.getAttribute('aria-describedby') ?? '')?.textContent).toBe('Alasan wajib diisi.');
});
