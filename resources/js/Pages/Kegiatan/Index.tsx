import { useState, type FormEvent } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Plus } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { ActionMenu } from '@/Components/ActionMenu';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Pagination } from '@/Components/Pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { statusKegiatan, type KegiatanIndexProps } from '@/types/kegiatan';

export default function Index({ kegiatan, filters, unitOptions, periodeOptions, can }: KegiatanIndexProps) {
    const [search, setSearch] = useState(filters);
    const apply = (event: FormEvent) => { event.preventDefault(); router.get('/kegiatan', search, { replace: true, preserveState: false }); };
    return <AuthenticatedLayout title="Kegiatan" breadcrumbs={[{ label: 'Kegiatan' }]} headerActions={can.create && <Link href="/kegiatan/buat" className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white"><Plus className="h-4 w-4" aria-hidden="true" />Buat kegiatan</Link>}>
        <Head title="Kegiatan" />
        <Card className="mb-5 p-4 sm:p-6">
            <form onSubmit={apply} className="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <Input label="Tahun" type="number" min={1} max={9999} value={search.tahun} onChange={(e) => setSearch({ ...search, tahun: e.target.value })} />
                <Select label="Unit" value={search.unit_id} onChange={(e) => setSearch({ ...search, unit_id: e.target.value })} options={[{ value: '', label: 'Semua unit' }, ...unitOptions.map((o) => ({ value: o.id, label: o.nama }))]} />
                <Select label="Periode" value={search.periode_id} onChange={(e) => setSearch({ ...search, periode_id: e.target.value })} options={[{ value: '', label: 'Semua periode' }, ...periodeOptions.map((o) => ({ value: o.id, label: o.nama }))]} />
                <Select label="Status" value={search.status} onChange={(e) => setSearch({ ...search, status: e.target.value })} options={[{ value: '', label: 'Semua status' }, ...statusKegiatan]} />
                <div className="flex items-center gap-3"><Button type="submit" variant="outline">Terapkan</Button><Link href="/kegiatan" className="text-sm text-primary hover:underline">Reset</Link></div>
            </form>
        </Card>
        <Card>
            <Table><TableHeader><TableRow><TableHead>Kegiatan</TableHead><TableHead>Unit</TableHead><TableHead>Tahun / Periode</TableHead><TableHead>Tanggal rencana</TableHead><TableHead>Status</TableHead><TableHead>Aksi</TableHead></TableRow></TableHeader>
                <TableBody>{kegiatan.data.length === 0 ? <TableRow><TableCell colSpan={6} className="py-12 text-center text-muted">Belum ada kegiatan yang sesuai dengan filter.</TableCell></TableRow> : kegiatan.data.map((item) => <TableRow key={item.id}>
                    <TableCell className="min-w-56"><span className="font-semibold">{item.nama}</span><p className="mt-1 max-w-sm whitespace-pre-wrap break-words text-muted">{item.tujuan}</p></TableCell>
                    <TableCell>{item.unit.nama}</TableCell><TableCell>{item.tahun}<p className="text-muted">{item.periode.nama}</p></TableCell>
                    <TableCell>{item.tanggal_rencana ?? '—'}</TableCell><TableCell>{statusKegiatan.find((s) => s.value === item.status)?.label ?? item.status}</TableCell>
                    <TableCell>{item.can.update && <ActionMenu trigger={<MoreHorizontal className="h-4 w-4" aria-hidden="true" />} triggerLabel={`Aksi kegiatan ${item.nama}`} items={[{ key: 'edit', label: 'Ubah', icon: Pencil, href: `/kegiatan/${item.id}/edit` }]} />}</TableCell>
                </TableRow>)}</TableBody>
            </Table>
            <Pagination {...kegiatan} ariaLabel="Halaman kegiatan" className="border-t border-border p-4" />
        </Card>
    </AuthenticatedLayout>;
}
