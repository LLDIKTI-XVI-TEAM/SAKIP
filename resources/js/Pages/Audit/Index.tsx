import { Head, Link, router, usePage } from '@inertiajs/react';
import { History, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Pagination } from '@/Components/Pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { jenisPelakuLabel, type AuditIndexProps } from '@/types/audit';

const cellClass = 'min-w-0 p-0 md:px-4 md:py-3.5';

export default function AuditIndex({ audit, filters }: AuditIndexProps) {
    const formatTanggal = useFormatTanggal();
    const { errors } = usePage().props;
    const [memuat, setMemuat] = useState(false);

    const terapkan = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        // Isian kosong tidak dikirim agar URL filter tetap ringkas dan dapat dibagikan.
        const isian = Object.fromEntries([...new FormData(event.currentTarget).entries()].filter(([, nilai]) => String(nilai).trim() !== ''));
        router.get('/audit', isian, {
            preserveState: true,
            replace: true,
            onStart: () => setMemuat(true),
            onFinish: () => setMemuat(false),
        });
    };

    return (
        <AuthenticatedLayout title="Jejak Audit" breadcrumbs={[{ label: 'Jejak Audit' }]}>
            <Head title="Jejak Audit" />

            <div className="space-y-5">
                <Card className="overflow-visible">
                    {/* Key mengikuti filter server agar isian kembali selaras setelah navigasi. */}
                    <form key={JSON.stringify(filters)} onSubmit={terapkan} aria-label="Filter jejak audit" className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Input type="date" name="dari" label="Dari tanggal" defaultValue={filters.dari} error={errors.dari} />
                        <Input type="date" name="sampai" label="Sampai tanggal" defaultValue={filters.sampai} error={errors.sampai} />
                        <Input name="aktor" label="Aktor" defaultValue={filters.aktor} placeholder="Nama atau email" maxLength={100} error={errors.aktor} />
                        <Input name="tindakan" label="Tindakan" defaultValue={filters.tindakan} placeholder="Mis. ditolak" maxLength={100} error={errors.tindakan} />
                        <Input name="objek_tipe" label="Jenis objek" defaultValue={filters.objek_tipe} placeholder="Mis. rencana_aksi" maxLength={100} error={errors.objek_tipe} />
                        <Input name="objek_id" label="ID objek" defaultValue={filters.objek_id} placeholder="UUID objek" error={errors.objek_id} />
                        <div className="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-3">
                            <Button type="submit" variant="outline" disabled={memuat}>
                                <Search className="h-4 w-4" aria-hidden="true" />
                                {memuat ? 'Memuat…' : 'Terapkan'}
                            </Button>
                            <Link href="/audit" className="text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/20">
                                Atur ulang
                            </Link>
                        </div>
                    </form>
                </Card>

                {audit.data.length === 0 ? (
                    <Card>
                        <div className="px-6 py-14 text-center">
                            <History className="mx-auto h-8 w-8 text-muted" aria-hidden="true" />
                            <h3 className="mt-3 text-base font-semibold text-ink">Belum ada catatan audit yang sesuai</h3>
                            <p className="mx-auto mt-1 max-w-md text-sm text-muted">
                                {audit.total > 0
                                    ? 'Halaman ini kosong. Kembali ke halaman pertama untuk melihat catatan terbaru.'
                                    : 'Ubah atau atur ulang filter untuk melihat catatan lain.'}
                            </p>
                        </div>
                    </Card>
                ) : (
                    <Card>
                        {/* Grid mobile: setiap catatan tampil sebagai kartu ringkas tanpa scroll horizontal. */}
                        <Table wrapperClassName="overflow-x-visible" className="block md:table">
                            <caption className="sr-only">Catatan jejak audit, terbaru lebih dulu</caption>
                            <TableHeader className="max-md:sr-only">
                                <TableRow>
                                    <TableHead>Waktu</TableHead>
                                    <TableHead>Pelaku</TableHead>
                                    <TableHead>Tindakan</TableHead>
                                    <TableHead>Objek</TableHead>
                                    <TableHead className="text-right"><span className="sr-only">Detail</span></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="block md:table-row-group">
                                {audit.data.map((baris) => (
                                    <TableRow key={baris.id} className="grid min-w-0 grid-cols-1 gap-2 p-4 md:table-row">
                                        <TableCell className={`${cellClass} text-muted md:whitespace-nowrap`}>
                                            {formatTanggal(baris.waktu, { withTime: true })}
                                        </TableCell>
                                        <TableCell className={cellClass}>
                                            {baris.aktor ? (
                                                <>
                                                    <p className="break-words font-semibold text-ink">{baris.aktor.nama}</p>
                                                    <p className="break-all text-muted">{baris.aktor.email}</p>
                                                </>
                                            ) : (
                                                <p className="font-semibold text-ink">{jenisPelakuLabel[baris.actor_type] ?? baris.actor_type}</p>
                                            )}
                                        </TableCell>
                                        <TableCell className={cellClass}>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="break-all font-mono font-semibold text-ink">{baris.tindakan}</span>
                                                {baris.ditolak && <Badge variant="danger">Ditolak</Badge>}
                                            </div>
                                            {baris.ditolak && baris.alasan && (
                                                <p className="mt-1 max-w-md whitespace-pre-line break-words text-muted">{baris.alasan}</p>
                                            )}
                                        </TableCell>
                                        <TableCell className={cellClass}>
                                            <p className="break-all font-mono text-ink">{baris.objek_tipe}</p>
                                            <p className="break-all font-mono text-muted">{baris.objek_id}</p>
                                        </TableCell>
                                        <TableCell className={`${cellClass} md:text-right`}>
                                            <Link
                                                href={`/audit/${baris.id}`}
                                                aria-label={`Lihat detail audit ${baris.tindakan}`}
                                                className="text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/20"
                                            >
                                                Detail
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>
                )}

                <Pagination links={audit.links} from={audit.from} to={audit.to} total={audit.total} ariaLabel="Paginasi jejak audit" />
            </div>
        </AuthenticatedLayout>
    );
}
