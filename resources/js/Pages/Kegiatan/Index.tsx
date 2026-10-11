import { useState, type FormEvent } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, MoreHorizontal, Pencil, Plus } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { ActionMenu } from '@/Components/ActionMenu';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Pagination } from '@/Components/Pagination';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { statusKegiatan, type KegiatanIndexProps } from '@/types/kegiatan';

export default function Index({ kegiatan, filters, unitOptions, periodeOptions, can }: KegiatanIndexProps) {
    const [search, setSearch] = useState(filters);
    const formatTanggal = useFormatTanggal();
    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get('/kegiatan', search, { replace: true, preserveState: false });
    };

    return (
        <AuthenticatedLayout
            title="Kegiatan"
            breadcrumbs={[{ label: 'Kegiatan' }]}
            headerActions={can.create && (
                <Link href="/kegiatan/buat" className="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-semibold text-white shadow-xs transition-all duration-150 hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:ring-offset-1">
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    Buat kegiatan
                </Link>
            )}
        >
            <Head title="Kegiatan" />
            <div className="space-y-5">
                <Card>
                    <CardContent className="p-4">
                        <form onSubmit={apply} className="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            <Input label="Tahun" type="number" min={1} max={9999} value={search.tahun} onChange={(event) => setSearch({ ...search, tahun: event.target.value })} />
                            <Select label="Unit" value={search.unit_id} onChange={(event) => setSearch({ ...search, unit_id: event.target.value })} options={[{ value: '', label: 'Semua unit' }, ...unitOptions.map((option) => ({ value: option.id, label: option.nama }))]} />
                            <Select label="Periode" value={search.periode_id} onChange={(event) => setSearch({ ...search, periode_id: event.target.value })} options={[{ value: '', label: 'Semua periode' }, ...periodeOptions.map((option) => ({ value: option.id, label: option.nama }))]} />
                            <Select label="Status" value={search.status} onChange={(event) => setSearch({ ...search, status: event.target.value })} options={[{ value: '', label: 'Semua status' }, ...statusKegiatan]} />
                            <div className="flex items-center gap-3">
                                <Button type="submit" variant="outline" className="h-[42px]">Terapkan</Button>
                                <Link href="/kegiatan" className="rounded text-sm text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/30">Reset</Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
                <Card>
                    <Table wrapperClassName="overflow-x-visible" className="block lg:table">
                        <TableCaption className="sr-only">Daftar kegiatan</TableCaption>
                        <TableHeader className="hidden lg:table-header-group">
                            <TableRow>
                                {['Kegiatan', 'Unit', 'Tahun / Periode', 'Tanggal rencana', 'Status', 'Aksi'].map((label) => (
                                    <TableHead key={label} className={`whitespace-normal ${label === 'Aksi' ? 'text-right' : ''}`}>{label}</TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody className="block lg:table-row-group">
                            {kegiatan.data.length === 0 ? (
                                <TableRow className="block lg:table-row">
                                    <TableCell colSpan={6} className="block py-12 text-center lg:table-cell">
                                        <CalendarDays className="mx-auto h-8 w-8 text-muted" aria-hidden="true" />
                                        <p className="mt-2 text-sm font-semibold">Belum ada kegiatan yang sesuai dengan filter.</p>
                                    </TableCell>
                                </TableRow>
                            ) : kegiatan.data.map((item) => (
                                <TableRow key={item.id} className="grid min-w-0 grid-cols-2 gap-3 p-4 lg:table-row">
                                    <TableCell className="col-span-2 min-w-0 p-0 [overflow-wrap:anywhere] lg:max-w-sm lg:px-4 lg:py-3.5">
                                        <span className="text-sm font-semibold">{item.nama}</span>
                                        <p className="mt-1 whitespace-pre-wrap text-muted">{item.tujuan}</p>
                                    </TableCell>
                                    <TableCell className="col-span-2 min-w-0 p-0 [overflow-wrap:anywhere] lg:max-w-64 lg:px-4 lg:py-3.5">
                                        <span className="mr-2 text-muted lg:hidden">Unit</span>
                                        {item.unit.nama}
                                    </TableCell>
                                    <TableCell className="min-w-0 p-0 lg:px-4 lg:py-3.5">
                                        <span className="mr-2 text-muted lg:hidden">Tahun</span>
                                        {item.tahun}
                                        <p className="text-muted">{item.periode.nama}</p>
                                    </TableCell>
                                    <TableCell className="min-w-0 p-0 lg:px-4 lg:py-3.5">
                                        <span className="mb-1 block text-muted lg:hidden">Tanggal rencana</span>
                                        {formatTanggal(item.tanggal_rencana)}
                                    </TableCell>
                                    <TableCell className="min-w-0 p-0 lg:px-4 lg:py-3.5">
                                        <Badge variant="muted">{statusKegiatan.find((status) => status.value === item.status)?.label ?? item.status}</Badge>
                                    </TableCell>
                                    <TableCell className="min-w-0 p-0 lg:px-4 lg:py-3.5">
                                        <div className="flex justify-end">
                                            {item.can.update && (
                                                <Tooltip content="Aksi kegiatan" align="right">
                                                    <ActionMenu trigger={<MoreHorizontal className="h-4 w-4" aria-hidden="true" />} triggerLabel={`Aksi kegiatan ${item.nama}`} items={[{ key: 'edit', label: 'Ubah', icon: Pencil, href: `/kegiatan/${item.id}/edit` }]} />
                                                </Tooltip>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination {...kegiatan} ariaLabel="Halaman kegiatan" className="border-t border-border px-5 py-3.5" />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
