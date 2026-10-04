import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import type { DefinitionEditor, DefinitionRow } from './definition';
import { newRow } from './definition';
import type { IndikatorTipePerhitungan } from '@/types/sasaran-indikator';

interface Props {
    rows: DefinitionRow[];
    deleted: string[];
    tipe: IndikatorTipePerhitungan;
    onRows: (rows: DefinitionRow[]) => void;
    onDeleted: (ids: string[]) => void;
    can: DefinitionEditor['can'];
    disabled: boolean;
    errors: Record<string, string | undefined>;
    prefix: string;
}

export function DefinitionFields({ rows, deleted, tipe, onRows, onDeleted, can, disabled, errors, prefix }: Props) {
    const update = (key: string, values: Partial<DefinitionRow>) => {
        onRows(rows.map((row) => row.client_key === key ? { ...row, ...values } : row));
    };
    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-sm font-semibold text-ink">Definisi Komponen</h3>
                {can.create && (
                    <Button type="button" size="sm" disabled={disabled} onClick={() => onRows([...rows, newRow(tipe, rows.length + 1)])}>
                        Tambah Komponen
                    </Button>
                )}
            </div>
            <p className="text-xs text-muted">Perubahan disimpan bersama. Nonaktifkan komponen untuk mempertahankan riwayat; hapus hanya bila tidak lagi dirujuk.</p>
            {tipe === 'manual' && rows.some((row) => row.aktif && !deleted.includes(row.id ?? '')) && (
                <p role="status" className="text-sm text-warning-dark">Tipe manual memerlukan seluruh komponen nonaktif. Nonaktifkan baris yang masih aktif sebelum menyimpan.</p>
            )}
            {errors.komponen && <p role="alert" className="text-sm text-danger">{errors.komponen}</p>}
            {rows.length === 0 && <p className="rounded-lg bg-soft p-4 text-sm text-muted">Belum ada komponen. Tambahkan definisi untuk tipe rasio atau penjumlahan.</p>}
            {rows.map((row, index) => {
                const removed = Boolean(row.id && deleted.includes(row.id));
                const editable = row.id ? can.update : can.create;
                return (
                    <fieldset key={row.client_key} disabled={disabled} className="space-y-3 rounded-lg border border-border p-4">
                        <legend className="px-1 text-xs font-semibold text-ink">
                            Komponen {index + 1}{removed ? ' · Akan dihapus' : !row.aktif ? ' · Nonaktif' : ''}
                        </legend>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Input
                                id={`${prefix}-${row.client_key}-kode`} label={`Kode komponen ${index + 1}`}
                                value={row.kode} disabled={!editable || removed}
                                onChange={(event) => update(row.client_key, { kode: event.target.value })} error={errors[`komponen.${index}.kode`]}
                            />
                            <Input
                                id={`${prefix}-${row.client_key}-label`} label={`Label komponen ${index + 1}`}
                                value={row.label} disabled={!editable || removed}
                                onChange={(event) => update(row.client_key, { label: event.target.value })} error={errors[`komponen.${index}.label`]}
                            />
                            <Select
                                id={`${prefix}-${row.client_key}-peran`} label={`Peran komponen ${index + 1}`}
                                value={row.peran} disabled={!editable || removed} error={errors[`komponen.${index}.peran`]}
                                onChange={(event) => {
                                    const peran = event.target.value;
                                    if (peran === 'pembilang' || peran === 'penyebut' || peran === 'penjumlah') update(row.client_key, { peran });
                                }}
                            >
                                <option value="pembilang">Pembilang</option>
                                <option value="penyebut">Penyebut</option>
                                <option value="penjumlah">Penjumlah</option>
                            </Select>
                            <Input
                                id={`${prefix}-${row.client_key}-bobot`} label={`Bobot komponen ${index + 1}`} inputMode="decimal"
                                value={row.bobot} disabled={!editable || removed}
                                onChange={(event) => update(row.client_key, { bobot: event.target.value })} error={errors[`komponen.${index}.bobot`]}
                            />
                            <Input
                                id={`${prefix}-${row.client_key}-urutan`} label={`Urutan komponen ${index + 1}`} type="number" min={1}
                                value={row.urutan} disabled={!editable || removed}
                                onChange={(event) => update(row.client_key, { urutan: event.target.value })} error={errors[`komponen.${index}.urutan`]}
                            />
                            <Input
                                id={`${prefix}-${row.client_key}-satuan`} label={`Satuan komponen ${index + 1}`}
                                value={row.satuan} disabled={!editable || removed}
                                onChange={(event) => update(row.client_key, { satuan: event.target.value })} error={errors[`komponen.${index}.satuan`]}
                            />
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox" checked={row.aktif} disabled={!editable || removed}
                                    onChange={(event) => update(row.client_key, { aktif: event.target.checked })}
                                />
                                Komponen {index + 1} aktif
                            </label>
                            {(!row.id || can.delete) && (
                                <Button type="button" variant="ghost" size="sm" onClick={() => {
                                    if (!row.id) onRows(rows.filter((item) => item.client_key !== row.client_key));
                                    else onDeleted(removed ? deleted.filter((id) => id !== row.id) : [...deleted, row.id]);
                                }}>
                                    {removed ? 'Batalkan hapus' : `Hapus komponen ${index + 1}`}
                                </Button>
                            )}
                        </div>
                    </fieldset>
                );
            })}
        </div>
    );
}
