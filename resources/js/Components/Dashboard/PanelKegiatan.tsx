import { AlertTriangle, CalendarCheck } from 'lucide-react';
import { Badge } from '@/Components/Badge';
import { Card } from '@/Components/Card';
import { KotakHitungan } from '@/Components/Dashboard/KotakHitungan';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { statusKegiatanConfig } from '@/types/kegiatan';
import type { DashboardKegiatan, StatusKegiatan } from '@/types/dashboard';

const STATUS: StatusKegiatan[] = ['rencana', 'terlaksana', 'tidak_terlaksana', 'ditunda', 'batal'];

/** Status kegiatan dari data kerja (Q45 §45.3): berubah langsung tanpa menunggu pengesahan, jadi bukan angka capaian resmi. */
export function PanelKegiatan({ kegiatan, periodeNama }: { kegiatan: DashboardKegiatan; periodeNama: string }) {
    const formatTanggal = useFormatTanggal();
    const kosong = STATUS.every((status) => kegiatan.per_status[status] === 0);

    return (
        <section aria-labelledby="status-kegiatan">
            <Card>
                <div className="flex flex-wrap items-start gap-3 px-5 pt-5 pb-3">
                    <span className="rounded-lg bg-primary/10 p-2 text-primary">
                        <CalendarCheck className="h-4 w-4" aria-hidden="true" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 id="status-kegiatan" className="text-base font-semibold tracking-tight text-ink">
                                Status kegiatan {periodeNama}
                            </h2>
                            <Badge variant="muted">Belum diverifikasi</Badge>
                        </div>
                        <p className="mt-0.5 text-xs text-muted">Data kerja yang dapat berubah sewaktu-waktu; bukan angka capaian resmi.</p>
                    </div>
                </div>

                <div className="space-y-4 px-5 pb-5">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {STATUS.map((status) => (
                            <KotakHitungan key={status} status={status} label={statusKegiatanConfig[status].label} nilai={kegiatan.per_status[status]} />
                        ))}
                    </div>

                    {kegiatan.terlambat_jumlah > 0 && (
                        <div className="rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-warning-dark">
                            <p className="flex items-center gap-2 text-sm font-semibold">
                                <AlertTriangle className="h-4 w-4 shrink-0" aria-hidden="true" />
                                {kegiatan.terlambat_jumlah} kegiatan melewati tanggal rencana dan belum terlaksana
                            </p>
                            {kegiatan.terlambat !== null && kegiatan.terlambat.length > 0 && (
                                <ul aria-label="Kegiatan terlambat" className="mt-2 space-y-1 text-xs">
                                    {kegiatan.terlambat.map((item) => (
                                        <li key={item.id} className="break-words">
                                            <span className="font-medium">{item.nama}</span> · {item.unit} · {formatTanggal(item.tanggal_rencana)}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {kegiatan.terlambat !== null && kegiatan.terlambat.length < kegiatan.terlambat_jumlah && (
                                <p className="mt-2 text-xs">Daftar hanya memuat kegiatan pada unit yang dapat Anda lihat, paling banyak 20.</p>
                            )}
                        </div>
                    )}

                    {kosong ? (
                        <p className="text-sm text-muted">Belum ada kegiatan pada periode ini.</p>
                    ) : (
                        <div>
                            {/* Enam kolom baru muat sebagai tabel mulai lg; di bawahnya panel terlalu sempit sehingga baris tetap kartu. */}
                            <Table wrapperClassName="overflow-x-visible" className="block lg:table">
                                <caption className="sr-only">Kegiatan per IKU {periodeNama}</caption>
                                <TableHeader className="hidden lg:table-header-group">
                                    <TableRow>
                                        <TableHead className="whitespace-normal">IKU</TableHead>
                                        {STATUS.map((status) => (
                                            <TableHead key={status} className="whitespace-normal text-right">
                                                {statusKegiatanConfig[status].label}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="block lg:table-row-group">
                                    {kegiatan.per_iku.length === 0 && (
                                        <TableRow className="block lg:table-row">
                                            <TableCell colSpan={STATUS.length + 1} className="block py-6 text-center text-sm text-muted lg:table-cell">
                                                Belum ada kegiatan yang diklaim IKU pada periode ini.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {kegiatan.per_iku.map((iku) => (
                                        <TableRow key={iku.id} className="grid min-w-0 grid-cols-2 gap-2 p-4 lg:table-row">
                                            <TableCell className="col-span-2 min-w-0 p-0 lg:max-w-sm lg:px-4 lg:py-3">
                                                <p className="text-xs font-semibold text-ink">{iku.kode}</p>
                                                <p className="mt-0.5 break-words text-xs text-muted">{iku.nama}</p>
                                            </TableCell>
                                            {STATUS.map((status) => (
                                                <TableCell key={status} className="min-w-0 p-0 tabular-nums lg:px-4 lg:py-3 lg:text-right">
                                                    <span className="mr-2 text-xs text-muted lg:hidden">{statusKegiatanConfig[status].label}</span>
                                                    {iku.jumlah[status]}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <p className="mt-2 text-xs text-muted">Kegiatan yang diklaim lebih dari satu IKU dihitung pada setiap IKU tersebut.</p>
                        </div>
                    )}
                </div>
            </Card>
        </section>
    );
}
