import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { ClipboardList, Eye } from 'lucide-react';
import { Badge } from '@/Components/Badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import { StatusPerhitunganBadge } from '@/Components/Pengukuran/StatusPerhitunganBadge';
import { SelisihTargetPk } from '@/Components/Pengukuran/SelisihTargetPk';
import { STATUS_TAMPILAN } from '@/Components/Dashboard/statusTampilan';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import type { BarisStatusIndikator } from '@/types/dashboard';

const iconAction =
    'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-surface text-muted shadow-2xs transition-colors hover:border-primary/40 hover:bg-soft hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
const KOLOM = ['Indikator', 'Unit & PIC', 'Status', 'Realisasi', 'Target periode', 'Target PK', 'Selisih', 'Aksi'];
const KANAN = ['Realisasi', 'Target periode', 'Target PK', 'Selisih', 'Aksi'];

/** Tabel status indikator periode terpilih; di HP setiap baris menjadi kartu berlabel (`block md:table`). */
export function TabelStatusIndikator({ rows, periodeNama }: { rows: BarisStatusIndikator[]; periodeNama: string }) {
    const formatNilai = useFormatNilai();
    const angka = (nilai: string | null, row: BarisStatusIndikator) =>
        nilai === null ? null : `${formatNilai(nilai, row.indikator.desimal_tampilan)} ${row.indikator.satuan}`;

    return (
        <Table wrapperClassName="overflow-x-visible" className="block md:table">
            <caption className="sr-only">Status indikator {periodeNama}</caption>
            <TableHeader className="hidden md:table-header-group">
                <TableRow>
                    {KOLOM.map((label) => (
                        <TableHead key={label} className={`whitespace-normal ${KANAN.includes(label) ? 'text-right' : ''}`}>
                            {label}
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody className="block md:table-row-group">
                {rows.length === 0 && (
                    <TableRow className="block md:table-row">
                        <TableCell colSpan={KOLOM.length} className="block py-12 text-center md:table-cell">
                            <ClipboardList className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                            <p className="mt-2 text-sm font-semibold text-ink">Belum ada indikator pada periode ini</p>
                            <p className="mt-1 text-xs text-muted">Ubah filter atau pilih periode lain.</p>
                        </TableCell>
                    </TableRow>
                )}
                {rows.map((row) => (
                    <TableRow key={`${row.indikator.id}`} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                        <TableCell className="col-span-2 min-w-0 p-0 md:max-w-xs md:px-4 md:py-3.5">
                            <p className="text-xs font-semibold text-ink">{row.indikator.kode}</p>
                            <p className="mt-0.5 break-words text-xs leading-relaxed text-muted">{row.indikator.nama}</p>
                        </TableCell>
                        <TableCell className="col-span-2 min-w-0 p-0 md:px-4 md:py-3.5">
                            <p className="break-words font-medium text-ink">{row.unit.nama}</p>
                            <p className="mt-0.5 text-xs text-muted">PIC: {row.pic?.nama ?? '—'}</p>
                        </TableCell>
                        <TableCell className="col-span-2 min-w-0 p-0 md:px-4 md:py-3.5">
                            <div className="flex flex-wrap items-center gap-1.5">
                                <Badge variant={STATUS_TAMPILAN[row.status].variant}>
                                    {STATUS_TAMPILAN[row.status].label}
                                </Badge>
                                {row.status === 'dalam_proses' && row.status_alur && <Badge status={row.status_alur} />}
                            </div>
                            {row.self_approval && <p className="mt-1 text-xs font-medium text-info-dark">Persetujuan sendiri</p>}
                            {row.reviu_terlambat && <p className="mt-1 text-xs font-medium text-warning-dark">Reviu terlambat</p>}
                        </TableCell>
                        <Sel label="Realisasi">
                            {row.realisasi !== null ? (
                                <span className="font-semibold tabular-nums text-ink">{angka(row.realisasi, row)}</span>
                            ) : row.status_perhitungan && row.status_perhitungan !== 'terhitung' ? (
                                <StatusPerhitunganBadge status={row.status_perhitungan} />
                            ) : (
                                <span className="text-muted">—</span>
                            )}
                        </Sel>
                        <Sel label="Target periode">
                            {row.tanpa_target_periode ? (
                                <span className="inline-flex flex-col items-start md:items-end">
                                    <span className="text-muted">—</span>
                                    <span className="text-xs text-muted">Tidak ditargetkan</span>
                                </span>
                            ) : (
                                <span className="tabular-nums text-ink">{angka(row.target, row) ?? '—'}</span>
                            )}
                        </Sel>
                        <Sel label="Target PK">
                            <span className="tabular-nums text-ink">{angka(row.target_pk, row) ?? '—'}</span>
                        </Sel>
                        <Sel label="Selisih">
                            <SelisihTargetPk selisih={row.selisih} arah={row.indikator.arah} satuan={row.indikator.satuan} desimal={row.indikator.desimal_tampilan} />
                        </Sel>
                        <TableCell className="col-span-2 min-w-0 p-0 md:px-4 md:py-3.5 md:text-right">
                            {row.action && (
                                <div className="flex justify-end">
                                    <Tooltip content={row.action.label} align="right">
                                        <Link href={row.action.href} className={iconAction} aria-label={`${row.action.label} ${row.indikator.kode}`}>
                                            <Eye className="h-4 w-4" aria-hidden="true" />
                                        </Link>
                                    </Tooltip>
                                </div>
                            )}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function Sel({ label, children }: { label: string; children: ReactNode }) {
    return (
        <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5 md:text-right">
            <span className="mb-0.5 block text-xs text-muted md:hidden">{label}</span>
            {children}
        </TableCell>
    );
}
