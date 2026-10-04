import type { DefinitionEditor } from '@/Pages/Indikator/Komponen/definition';
import type { FormulaKomponenItem, IndikatorKinerjaItem } from '@/types/sasaran-indikator';

export const REVISION = '2026-10-02T10:00:00.123456Z';
export const COMPONENTS: FormulaKomponenItem[] = [
    { id: '00000000-0000-4000-8000-000000000001', kode: 'N', label: 'Capaian aktual', peran: 'pembilang', bobot: '1.000000000001', urutan: 1, satuan: 'Dokumen', aktif: true },
    { id: '00000000-0000-4000-8000-000000000002', kode: 'T', label: 'Total target', peran: 'penyebut', bobot: '1.000000000000', urutan: 2, satuan: 'Dokumen', aktif: true },
];
export function indicator(overrides: Partial<IndikatorKinerjaItem> = {}): IndikatorKinerjaItem {
    return { id: 'ind-1', sasaran_strategis_id: 'sas-1', regulasi_id: null, kode: 'IKU-01', nama: 'Capaian kinerja', definisi_operasional: null, satuan: '%', unit_id: 'unit-a', unit_nama: 'Unit A', arah: 'naik_baik', tipe_perhitungan: 'rasio_persen', presisi: 2, desimal_tampilan: 2, wajib_catatan: false, status: 'aktif', updated_at: REVISION, ...overrides };
}
export function definition(overrides: Partial<DefinitionEditor> = {}): DefinitionEditor {
    return { indikator: indicator(), komponen: COMPONENTS, formulaContract: { tipe_perhitungan: 'rasio_persen', formula_text: 'N / T × 100%', formula_unavailable: null, is_valid: true, messages: [], komponen_list: [] }, validation: { is_valid: true, messages: [] }, revision: REVISION, can: { create: true, update: true, delete: true, update_indikator: true }, pagination: { page: 1, per_page: 50, total: 2, next_page: null, complete: true }, ...overrides };
}
