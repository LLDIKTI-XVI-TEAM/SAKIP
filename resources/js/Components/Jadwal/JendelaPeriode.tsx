import { Info, Trash2 } from 'lucide-react';
import { Badge } from '@/Components/Badge';
import { Card, CardHeader } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Table, TableHead, TableHeader, TableRow } from '@/Components/Table';
import type { JadwalPeriode, PeriodeOption } from '@/types/periode-jadwal';

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
    /** Periode aktif yang belum dipilih. */
    options: PeriodeOption[];
    onAdd: (periodeId: string) => void;
    onRemove: (periodeId: string) => void;
    onChange: (periodeId: string, field: (typeof windowFields)[number]['key'], value: string) => void;
}

export function JendelaPeriode({
    periode,
    errors,
    editable,
    disabled,
    options,
    onAdd,
    onRemove,
    onChange,
}: JendelaPeriodeProps) {
    return (
        <Card>
            <CardHeader>
                <h2 className="font-semibold">Jendela periode</h2>
                {editable && (
                    <div className="w-full sm:w-60">
                        {/* Nilai selalu kosong: memilih langsung menambah baris lalu dropdown kembali ke placeholder. */}
                        <Select
                            id="tambah-periode"
                            aria-label="Tambah periode"
                            value=""
                            disabled={disabled || options.length === 0}
                            onChange={(event) => onAdd(event.target.value)}
                        >
                            <option value="" disabled>
                                {options.length > 0 ? '+ Tambah periode' : 'Semua periode sudah dipilih'}
                            </option>
                            {options.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.nama}
                                    {option.is_nilai_akhir ? ' · Periode nilai akhir' : ''}
                                </option>
                            ))}
                        </Select>
                    </div>
                )}
            </CardHeader>
            <Table className="block w-full text-left text-sm lg:table lg:table-fixed">
                <caption className="sr-only">Jendela pengisian dan review setiap periode</caption>
                <TableHeader className="hidden lg:table-header-group">
                    <TableRow>
                        <TableHead rowSpan={2} className="w-[19%] whitespace-normal px-5">
                            Periode
                        </TableHead>
                        <TableHead scope="colgroup" colSpan={2} className="whitespace-normal px-3">
                            Pengisian
                        </TableHead>
                        <TableHead scope="colgroup" colSpan={2} className="whitespace-normal px-3">
                            Review
                        </TableHead>
                    </TableRow>
                    <TableRow>
                        {/* Label input per sel tetap lengkap ("Mulai Review") untuk pembaca layar dan tampilan HP. */}
                        {['Mulai', 'Selesai', 'Mulai', 'Target selesai'].map((label, index) => (
                            <TableHead key={index} className="h-auto whitespace-normal px-3 py-2">
                                {label}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
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
