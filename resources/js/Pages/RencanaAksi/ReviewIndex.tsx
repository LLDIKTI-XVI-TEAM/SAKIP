import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, Eye } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Card } from '@/Components/Card';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import Pagination from '@/Pages/Pengukuran/Pagination';
import type { ReviewIndexProps } from './review-types';

export default function ReviewIndex({ daftar, pagination, filters }: ReviewIndexProps) {
    return (
        <AuthenticatedLayout title="Review Rencana Aksi" breadcrumbs={[{ label: 'Rencana Aksi', href: '/rencana-aksi' }, { label: 'Review' }]}>
            <Head title="Review Rencana Aksi" />
            <div className="space-y-5">
                <Card className="overflow-visible">
                    <div className="flex flex-wrap items-end justify-between gap-3 p-4">
                        <div className="w-full sm:w-72">
                            <Select label="Status rencana aksi" value={filters.status} onChange={(event) => router.get('/rencana-aksi/review', { status: event.target.value }, { preserveScroll: true })}>
                                <option value="diajukan">Diajukan</option>
                                <option value="diverifikasi">Diverifikasi</option>
                                <option value="dikembalikan">Dikembalikan</option>
                                <option value="disahkan">Disahkan</option>
                                <option value="semua">Semua status</option>
                            </Select>
                        </div>
                        {/* Pagination tidak tampil bila hanya satu halaman, jadi jumlah antrean ditampilkan di sini. */}
                        <p className="text-sm text-muted"><span className="font-semibold text-ink">{pagination.total}</span> rencana aksi</p>
                    </div>
                </Card>
                <Card>
                    {/* Grid mobile: unit, tahun, dan versi tampil berpasangan tanpa scroll horizontal. */}
                    <Table wrapperClassName="overflow-x-visible" className="block md:table">
                        <caption className="sr-only">Antrean review versi pengajuan rencana aksi</caption>
                        <TableHeader className="hidden md:table-header-group">
                            <TableRow>
                                {['Indikator', 'Unit', 'Tahun', 'Pengajuan', 'Status', 'Aksi'].map((label) => (
                                    <TableHead key={label} className={`whitespace-normal ${label === 'Aksi' ? 'text-right' : ''}`}>
                                        {label}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody className="block md:table-row-group">
                            {daftar.length === 0 && (
                                <TableRow className="block md:table-row">
                                    <TableCell colSpan={6} className="block py-12 text-center md:table-cell">
                                        <ClipboardCheck className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                        <p className="mt-2 text-sm font-semibold text-ink">Belum ada rencana aksi pada status yang dipilih untuk unit yang dapat Anda akses.</p>
                                        <p className="mt-1 text-xs text-muted">Pilih status lain untuk melihat pengajuan yang tersedia.</p>
                                    </TableCell>
                                </TableRow>
                            )}
                            {daftar.map((item) => (
                                <TableRow key={item.id} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                    <TableCell className="col-span-2 min-w-0 break-words p-0 md:max-w-sm md:px-4 md:py-3.5">
                                        <p className="text-sm font-semibold text-ink">{item.indikator.kode}</p>
                                        <p className="mt-0.5 text-xs text-muted">{item.indikator.nama}</p>
                                    </TableCell>
                                    <TableCell className="col-span-2 min-w-0 break-words p-0 md:px-4 md:py-3.5">
                                        <span className="mr-2 text-muted md:hidden">Unit</span>
                                        {item.unit_nama ?? 'Konteks versi belum lengkap'}
                                    </TableCell>
                                    <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5">
                                        <span className="mr-2 text-muted md:hidden">Tahun</span>
                                        {item.tahun}
                                    </TableCell>
                                    <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5">
                                        <span className="mr-2 text-muted md:hidden">Pengajuan</span>
                                        Versi {item.nomor_pengajuan}
                                    </TableCell>
                                    <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5">
                                        <Badge status={item.status_alur} size="sm" />
                                    </TableCell>
                                    <TableCell className="min-w-0 p-0 text-right md:px-4 md:py-3.5">
                                        <Link
                                            href={`/rencana-aksi/${item.id}/review`}
                                            aria-label={`Review ${item.indikator.kode} ${item.tahun}`}
                                            className="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25"
                                        >
                                            <Eye className="h-3.5 w-3.5" aria-hidden="true" />
                                            Review
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination pagination={pagination} label="rencana aksi" />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
