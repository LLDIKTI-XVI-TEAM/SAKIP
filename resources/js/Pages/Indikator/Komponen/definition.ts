import { http, HttpResponseError } from '@inertiajs/core';
import type { FormulaKomponenItem, IndikatorKinerjaItem, IndikatorTipePerhitungan } from '@/types/sasaran-indikator';

export interface DefinitionMetadata {
    id: string; kode: string; nama: string; satuan: string; tipe_perhitungan: IndikatorTipePerhitungan;
    presisi: number; desimal_tampilan?: number; unit_nama?: string | null; status?: 'aktif' | 'arsip';
}
export interface DefinitionEditor {
    indikator: DefinitionMetadata;
    komponen: FormulaKomponenItem[] | null;
    formulaContract: { tipe_perhitungan: string; formula_text: string; formula_unavailable?: string | null;
        is_valid: boolean; messages: string[]; komponen_list: Array<{ kode: string; label: string; peran: string; bobot: string; urutan: number; satuan: string | null }> } | null;
    validation: { is_valid: boolean; messages: string[] } | null;
    revision: string;
    can: { create: boolean; update: boolean; delete: boolean; update_indikator: boolean };
    pagination: { page: number; per_page: number; total: number; next_page: number | null; complete: boolean };
}
export interface DefinitionRow {
    client_key: string; id?: string; kode: string; label: string; peran: FormulaKomponenItem['peran'];
    bobot: string; urutan: number | string; satuan: string; aktif: boolean;
}
export function formRows(rows: FormulaKomponenItem[]): DefinitionRow[] {
    return rows.map((row) => ({ ...row, satuan: row.satuan ?? '', client_key: row.id }));
}
export function newRow(tipe: IndikatorTipePerhitungan, urutan: number): DefinitionRow {
    return { client_key: crypto.randomUUID(), kode: '', label: '', peran: tipe === 'penjumlahan' ? 'penjumlah' : 'pembilang', bobot: '1', urutan, satuan: '', aktif: true };
}
export function rowPayload(row: DefinitionRow) {
    return { ...(row.id ? { id: row.id } : {}), kode: row.kode, label: row.label, peran: row.peran,
        bobot: row.bobot, urutan: row.urutan, satuan: row.satuan || null, aktif: row.aktif };
}
/** Hanya intent berubah dikirim; baris lain tetap milik baseline server. */
export function changedFormRows(rows: DefinitionRow[], baseline: FormulaKomponenItem[]) {
    const originals = new Map(formRows(baseline).map((row) => [row.id, JSON.stringify(rowPayload(row))]));
    return rows.filter((row) => !row.id || originals.get(row.id) !== JSON.stringify(rowPayload(row)));
}
/** Memetakan indeks intent server ke baris draft tanpa mengubah kumpulan error sumber. */
export function definitionErrors(errors: Record<string, string | undefined>, rows: DefinitionRow[], submittedKeys: string[]) {
    const mapped: Record<string, string | undefined> = {};
    for (const [key, message] of Object.entries(errors)) {
        const match = /^komponen\.(\d+)\.(.+)$/.exec(key);
        if (!match) {
            mapped[key] = message;
            continue;
        }
        const index = rows.findIndex((row) => row.client_key === submittedKeys[Number(match[1])]);
        if (index >= 0) mapped[`komponen.${index}.${match[2]}`] = message;
    }
    return mapped;
}
function record(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
function isEditor(value: unknown): value is DefinitionEditor {
    if (!record(value) || !record(value.indikator) || !record(value.pagination) || !record(value.can)) return false;
    const page = value.pagination;
    return typeof value.indikator.id === 'string' && typeof value.revision === 'string'
        && Number.isInteger(page.page) && Number(page.page) > 0 && Number.isInteger(page.total)
        && Number(page.total) >= 0 && typeof page.complete === 'boolean'
        && (page.next_page === null || Number.isInteger(page.next_page))
        && (value.komponen === null || (Array.isArray(value.komponen) && value.komponen.every((row) => record(row)
            && typeof row.id === 'string' && typeof row.kode === 'string' && typeof row.label === 'string'
            && typeof row.bobot === 'string' && typeof row.aktif === 'boolean')));
}
/** Satu baseline hanya diterima setelah semua halaman terikat indikator dan revisi yang sama. */
export async function loadDefinition(url: string, signal: AbortSignal, initial?: DefinitionEditor): Promise<DefinitionEditor> {
    const read = async (page?: number, revision?: string) => {
        const query = page ? `?${new URLSearchParams({ page: String(page), expected_updated_at: revision ?? '' })}` : '';
        const response = await http.getClient().request({ method: 'get', url: `${url}${query}`, headers: { Accept: 'application/json' }, signal });
        if (response.status !== 200) throw new HttpResponseError('Editor tidak tersedia.', response);
        const parsed: unknown = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
        if (!isEditor(parsed)) throw new Error('Data editor tidak lengkap.');
        return parsed;
    };
    const first = initial ?? await read();
    if (!isEditor(first)) throw new Error('Data editor tidak lengkap.');
    if (first.komponen === null) return first;
    let current = first;
    const rows = [...first.komponen];
    const ids = new Set(rows.map((row) => row.id));
    if (ids.size !== rows.length) throw new Error('Identitas komponen berulang.');
    while (!current.pagination.complete) {
        const next = current.pagination.next_page;
        if (next === null || next !== current.pagination.page + 1 || rows.length >= first.pagination.total) throw new Error('Halaman editor tidak lengkap.');
        current = await read(next, first.revision);
        if (current.indikator.id !== first.indikator.id || current.revision !== first.revision
            || current.pagination.total !== first.pagination.total || current.pagination.page !== next || !current.komponen?.length) {
            throw new Error('Konfigurasi berubah saat dimuat. Muat ulang seluruh editor.');
        }
        for (const row of current.komponen) {
            if (ids.has(row.id)) throw new Error('Identitas komponen berulang.');
            ids.add(row.id); rows.push(row);
        }
    }
    if (rows.length !== first.pagination.total) throw new Error('Data komponen belum lengkap.');
    return { ...first, komponen: rows, pagination: { ...first.pagination, complete: true, next_page: null } };
}
export function hasParentMetadata(editor: DefinitionEditor): editor is DefinitionEditor & { indikator: IndikatorKinerjaItem } {
    const item = editor.indikator;
    return 'sasaran_strategis_id' in item && typeof item.sasaran_strategis_id === 'string'
        && 'unit_id' in item && typeof item.unit_id === 'string' && 'arah' in item
        && 'wajib_catatan' in item && 'regulasi_id' in item && 'definisi_operasional' in item;
}
export interface MutationOutcome { request_id: string; status: 'saved' | 'unchanged'; indikator_id: string; revision: string }
export function mutationOutcome(value: unknown, requestId: string, indikatorId?: string): MutationOutcome | null {
    if (!record(value) || value.request_id !== requestId || (value.status !== 'saved' && value.status !== 'unchanged')
        || typeof value.indikator_id !== 'string' || (indikatorId && value.indikator_id !== indikatorId)
        || typeof value.revision !== 'string' || value.revision === '') return null;
    return { request_id: requestId, status: value.status, indikator_id: value.indikator_id, revision: value.revision };
}
