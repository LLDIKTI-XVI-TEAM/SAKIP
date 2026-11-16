import { http } from '@inertiajs/core';
import { afterEach, expect, it, vi } from 'vitest';
import { loadDefinition, changedFormRows, rowPayload, formRows } from '@/Pages/Indikator/Komponen/definition';
import { COMPONENTS, definition, REVISION } from './indikatorFixtures';

afterEach(() => vi.restoreAllMocks());
const first = definition({ komponen: [COMPONENTS[0]], pagination: { page: 1, per_page: 50, total: 2, next_page: 2, complete: false } });
it('menggabungkan halaman dengan token yang sama dan mempertahankan validasi/formula halaman pertama', async () => {
    const request = vi.spyOn(http.getClient(), 'request').mockResolvedValue({ status: 200, headers: {}, data: JSON.stringify(definition({ komponen: [COMPONENTS[1]], formulaContract: null, validation: null, pagination: { page: 2, per_page: 50, total: 2, next_page: null, complete: true } })) });
    const loaded = await loadDefinition('/editor', new AbortController().signal, first);
    expect(request).toHaveBeenCalledWith(expect.objectContaining({ url: `/editor?page=2&expected_updated_at=${encodeURIComponent(REVISION)}` }));
    expect(loaded.komponen).toEqual(COMPONENTS); expect(loaded.formulaContract).toEqual(first.formulaContract); expect(loaded.validation).toEqual(first.validation);
    expect(loaded.pagination.complete).toBe(true);
});
it.each(['revision', 'indicator', 'duplicate', 'missing'] as const)('menolak halaman lanjutan dengan %s berbeda/tidak lengkap', async (failure) => {
    const next = definition({ komponen: failure === 'missing' ? [] : [failure === 'duplicate' ? COMPONENTS[0] : COMPONENTS[1]], pagination: { page: 2, per_page: 50, total: 2, next_page: null, complete: true } });
    if (failure === 'revision') next.revision = 'new'; if (failure === 'indicator') next.indikator = { ...next.indikator, id: 'other' };
    vi.spyOn(http.getClient(), 'request').mockResolvedValue({ status: 200, headers: {}, data: JSON.stringify(next) });
    await expect(loadDefinition('/editor', new AbortController().signal, first)).rejects.toThrow();
});
it('definisi lebih dari 50 baris tetap mengirim hanya satu intent berubah', () => {
    const baseline = Array.from({ length: 75 }, (_, index) => ({ ...COMPONENTS[0], id: `id-${index}`, kode: `K${index}`, urutan: index + 1 }));
    const rows = formRows(baseline); rows[74] = { ...rows[74], label: 'Perbaikan terakhir' };
    expect(changedFormRows(rows, baseline).map(rowPayload)).toEqual([expect.objectContaining({ id: 'id-74', label: 'Perbaikan terakhir' })]);
});
