import { Info, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardHeader } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Table } from '@/Components/Table';
import type { JadwalPeriode } from '@/types/periode-jadwal';

const windowFields = [
    { key: 'pengisian_mulai', label: 'Mulai pengisian' },
    { key: 'pengisian_selesai', label: 'Selesai pengisian' },
    { key: 'reviu_mulai', label: 'Mulai Review' },
    { key: 'reviu_selesai', label: 'Target Selesai Review' },
] as const;

interface JendelaPeriodeProps {
    periode: JadwalPeriode[];
    errors: Record<string, string | undefined>;
    editable: boolean;
    disabled: boolean;
    onAdd: () => void;
    onRemove: (periodeId: string) => void;
    onChange: (periodeId: string, field: (typeof windowFields)[number]['key'], value: string) => void;
}

export function JendelaPeriode({
    periode,
    errors,
    editable,
    disabled,
    onAdd,
    onRemove,
    onChange,
}: JendelaPeriodeProps) {
    return (
        <Card>
            <CardHeader>
                <div>
                    <h2 className="font-semibold">Jendela periode</h2>
                    <p className="mt-1 text-xs text-muted">
                        Periksa pengisian dan review seluruh periode dalam satu tampilan.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <Badge>{periode.length} periode</Badge>
                    {editable && (
                        <Button
                            id="tambah-periode"
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={disabled}
                            onClick={onAdd}
                        >
                            <Plus className="h-4 w-4" aria-hidden="true" />
                            Tambah periode
                        </Button>
                    )}
                </div>
            </CardHeader>
            <Table className="block w-full text-left text-sm lg:table lg:table-fixed">
                <caption className="sr-only">Jendela pengisian dan review setiap periode</caption>
                <thead className="hidden text-xs text-muted lg:table-header-group">
                    <tr className="bg-soft">
                        <th scope="col" rowSpan={2} className="w-[19%] px-5 py-3 font-medium">
                            Periode
                        </th>
                        <th scope="colgroup" colSpan={2} className="px-3 py-3 font-medium">
                            Pengisian
                        </th>
                        <th scope="colgroup" colSpan={2} className="px-3 py-3 font-medium">
                            Jendela Review
                        </th>
                    </tr>
                    <tr className="border-b border-border">
                        <th scope="col" className="px-3 py-2 font-medium">
                            Mulai
                        </th>
                        <th scope="col" className="px-3 py-2 font-medium">
                            Selesai
                        </th>
                        <th scope="col" className="px-3 py-2 font-medium">
                            Mulai Review
                        </th>
                        <th scope="col" className="px-3 py-2 font-medium">
                            Target Selesai Review
                        </th>
                    </tr>
                </thead>
                <tbody className="block divide-y divide-border lg:table-row-group">
                    {periode.length === 0 && (
                        <tr className="block lg:table-row">
                            <td colSpan={5} className="block p-6 text-center text-muted lg:table-cell">
                                Belum ada periode dipilih.
                                {editable && ' Tambahkan periode untuk menyusun jendelanya.'}
                            </td>
                        </tr>
                    )}
                    {periode.map((item, index) => (
                        <tr
                            key={item.periode_id}
                            className={`grid grid-cols-2 gap-3 p-4 lg:table-row ${item.is_nilai_akhir ? 'bg-secondary/5' : ''}`}
                        >
                            <td className="col-span-2 min-w-0 lg:px-5 lg:py-4">
                                <span className="block break-words font-semibold">{item.nama}</span>
                                <div className="mt-2 flex flex-wrap gap-1.5">
                                    {item.is_nilai_akhir && (
                                        <Badge variant="secondary" size="sm">
                                            Nilai akhir
                                        </Badge>
                                    )}
                                    {!item.aktif && (
                                        <Badge variant="warning" size="sm">
                                            Nonaktif
                                        </Badge>
                                    )}
                                </div>
                                {editable && (
                                    <button
                                        type="button"
                                        aria-label={`Hapus ${item.nama} dari draft`}
                                        disabled={disabled}
                                        onClick={() => onRemove(item.periode_id)}
                                        className="mt-2 inline-flex items-center gap-1 rounded py-1 text-xs text-danger hover:underline focus:outline-none focus:ring-2 focus:ring-danger/30 disabled:opacity-50"
                                    >
                                        <Trash2 className="h-3 w-3" aria-hidden="true" />
                                        Hapus dari draft
                                    </button>
                                )}
                            </td>
                            {windowFields.map(({ key, label }) => (
                                <td key={key} className="min-w-0 lg:px-2 lg:py-4">
                                    <Input
                                        id={`periode-${item.periode_id}-${key}`}
                                        name={`periode.${index}.${key}`}
                                        type="date"
                                        label={label}
                                        labelClassName="text-xs lg:sr-only"
                                        aria-label={`${item.nama}: ${label}`}
                                        required
                                        className="min-w-0 px-2 text-xs xl:text-sm"
                                        value={item[key]}
                                        disabled={disabled}
                                        error={errors[`periode.${index}.${key}`]}
                                        onChange={(event) => onChange(item.periode_id, key, event.target.value)}
                                    />
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </Table>
            <div className="flex items-start gap-2 border-t border-border px-4 py-4 text-xs leading-relaxed text-muted sm:px-6">
                <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
                <p>
                    Hanya periode terakhir boleh masuk tahun berikutnya. Akhir review adalah target; Perencanaan
                    dapat menyelesaikan review sampai penutupan.
                </p>
            </div>
        </Card>
    );
}
