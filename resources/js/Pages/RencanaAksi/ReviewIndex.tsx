import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, Eye } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Card, CardHeader } from '@/Components/Card';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import Pagination from '@/Pages/Pengukuran/Pagination';
import type { ReviewIndexProps } from './review-types';

export default function ReviewIndex({ daftar, pagination, filters }: ReviewIndexProps) {
    return (
        <AuthenticatedLayout title="Review Rencana Aksi" breadcrumbs={[{ label: 'Rencana Aksi', href: '/rencana-aksi' }, { label: 'Review' }]}>
            <Head title="Review Rencana Aksi" />
            <div className="space-y-5">
                <Link href="/rencana-aksi" className="inline-flex items-center gap-1.5 rounded text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke daftar rencana aksi
                </Link>
                <Card>
                    <CardHeader>
                        <div className="space-y-1.5">
                            <p className="text-sm text-muted">Periksa versi pengajuan yang telah dibekukan sebelum memverifikasi atau mengembalikan rencana aksi.</p>
                            <p className="text-xs text-muted"><span className="font-semibold text-ink">{pagination.total}</span> rencana aksi pada status yang dipilih</p>
                        </div>
                    </CardHeader>
                    <div className="bg-soft/30 p-4 sm:px-6">
                        <div className="w-full sm:w-56">
                            <Select label="Status rencana aksi" value={filters.status} onChange={(event) => router.get('/rencana-aksi/review', { status: event.target.value }, { preserveScroll: true })}>
                                <option value="diajukan">Diajukan</option>
                                <option value="diverifikasi">Diverifikasi</option>
                                <option value="dikembalikan">Dikembalikan</option>
                                <option value="disahkan">Disahkan</option>
                                <option value="semua">Semua status</option>
                            </Select>
                        </div>
                    </div>
                </Card>
                <Card>
                    <Table className="table-fixed xl:table-auto">
                        <caption className="sr-only">Antrean review versi pengajuan rencana aksi</caption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Indikator</TableHead>
                                <TableHead className="hidden xl:table-cell">Unit</TableHead>
                                <TableHead className="hidden xl:table-cell">Tahun</TableHead>
                                <TableHead className="hidden xl:table-cell">Pengajuan</TableHead>
                                <TableHead className="hidden xl:table-cell">Status</TableHead>
                                <TableHead className="w-28 text-right xl:w-auto">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {daftar.length === 0 ? (
                                <TableRow><TableCell colSpan={6} className="py-12 text-center text-sm text-muted">
                                    <ClipboardCheck className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                    <p className="mt-2 font-semibold text-ink">Belum ada rencana aksi pada status yang dipilih.</p>
                                    <p className="mt-1 text-xs text-muted">Pilih status lain untuk melihat pengajuan yang tersedia.</p>
                                </TableCell></TableRow>
                            ) : daftar.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell className="max-w-sm break-words">
                                        <p className="font-semibold">{item.indikator.kode}<span className="font-normal text-muted xl:hidden"> · {item.tahun}</span></p>
                                        <p className="mt-0.5 text-muted">{item.indikator.nama}</p>
                                        <p className="mt-1 text-xs text-muted xl:hidden">{item.unit_nama ?? 'Konteks versi belum lengkap'}</p>
                                        <div className="mt-2 flex flex-wrap items-center gap-2 xl:hidden">
                                            <span className="text-xs text-muted">Versi {item.nomor_pengajuan}</span>
                                            <Badge status={item.status_alur} size="sm" />
                                        </div>
                                    </TableCell>
                                    <TableCell className="hidden xl:table-cell">{item.unit_nama ?? 'Konteks versi belum lengkap'}</TableCell>
                                    <TableCell className="hidden xl:table-cell">{item.tahun}</TableCell>
                                    <TableCell className="hidden xl:table-cell">Versi {item.nomor_pengajuan}</TableCell>
                                    <TableCell className="hidden xl:table-cell"><Badge status={item.status_alur} size="sm" /></TableCell>
                                    <TableCell className="text-right">
                                        <Link href={`/rencana-aksi/${item.id}/review`} aria-label={`Review ${item.indikator.kode} ${item.tahun}`} className="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25"><Eye className="h-3.5 w-3.5" aria-hidden="true" />Review</Link>
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
