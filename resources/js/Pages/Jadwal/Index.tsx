import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarRange, Eye, Pencil, Plus, Search } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Pagination } from '@/Components/Pagination';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { statusJadwal } from '@/lib/jadwal';
import type { SharedPageProps } from '@/types/auth';
import type { JadwalIndexProps } from '@/types/periode-jadwal';

const iconAction =
    'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-surface text-muted shadow-2xs transition-colors hover:border-primary/40 hover:bg-soft hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

export default function JadwalIndex({ jadwal, filters, can }: JadwalIndexProps) {
    const { errors = {} } = usePage<SharedPageProps>().props;
    const formatTanggal = useFormatTanggal();
    const [query, setQuery] = useState(filters.q);
    const [tahun, setTahun] = useState(filters.tahun ? String(filters.tahun) : '');
    const [status, setStatus] = useState(filters.status ?? '');
    const summaryRef = useRef<HTMLDivElement>(null);
    const hasErrors = Object.keys(errors).length > 0;
    useEffect(() => {
        if (hasErrors) summaryRef.current?.focus();
    }, [hasErrors, errors]);
    const existingPair = Boolean(filters.renstra_id && filters.tahun && jadwal.total > 0);
    const filtered = Boolean(filters.q || filters.tahun || filters.status || filters.renstra_id);
    const hasActiveFilters = Boolean(query.trim() || tahun.trim() || status || filtered);

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            '/jadwal',
            { q: query, tahun, status, renstra_id: filters.renstra_id ?? undefined, sort: filters.sort },
            { replace: true, preserveState: 'errors' },
        );
    };

    const resetFilters = () => {
        setQuery('');
        setTahun('');
        setStatus('');
        router.get(
            '/jadwal',
            { q: '', tahun: '', status: '', sort: filters.sort },
            { replace: true, preserveState: 'errors' },
        );
    };

    return (
        <AuthenticatedLayout
            title="Jadwal Tahunan"
            breadcrumbs={[{ label: 'Jadwal Tahunan' }]}
            headerActions={
                can.create &&
                !existingPair && (
                    <Link
                        href="/jadwal/create"
                        className="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-semibold text-white shadow-xs transition-all duration-150 hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:ring-offset-1"
                    >
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Susun jadwal
                    </Link>
                )
            }
        >
            <Head title="Jadwal Tahunan" />
            <div className="space-y-5">
                {existingPair && (
                    <div role="status" className="rounded-lg border border-info/30 bg-info/5 p-4 text-sm">
                        <h2 className="font-semibold">Jadwal tahun ini sudah tersedia</h2>
                        <p className="mt-1">
                            Satu Renstra dan tahun memakai satu jadwal. Buka kalender yang tersedia di bawah.
                        </p>
                    </div>
                )}

                <Card className="overflow-visible">
                    {hasErrors && (
                        <div
                            ref={summaryRef}
                            role="alert"
                            tabIndex={-1}
                            className="mx-4 mt-4 rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-danger/30"
                        >
                            <p className="font-semibold">Periksa filter jadwal</p>
                            <ul className="mt-1 list-inside list-disc">
                                {Object.entries(errors).map(([field, message]) => (
                                    <li key={field}>{message}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                    <form
                        onSubmit={applyFilters}
                        className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_10rem_12rem_auto] lg:items-start"
                    >
                        <Input
                            label="Cari Renstra"
                            placeholder="Cari nama atau kode Renstra..."
                            value={query}
                            maxLength={100}
                            error={errors.q}
                            onChange={(event) => setQuery(event.target.value)}
                            autoComplete="off"
                        />
                        <Input
                            label="Tahun pelaporan"
                            type="number"
                            placeholder="Contoh: 2026"
                            value={tahun}
                            error={errors.tahun}
                            onChange={(event) => setTahun(event.target.value)}
                        />
                        <Select
                            label="Status jadwal"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                            options={[
                                { value: '', label: 'Semua status' },
                                { value: 'draft', label: 'Draft' },
                                { value: 'aktif', label: 'Aktif' },
                                { value: 'ditutup', label: 'Ditutup' },
                            ]}
                        />
                        {/* Sejajar dengan input: tinggi label + margin di atas field. */}
                        <div className="flex gap-2 lg:pt-[26px]">
                            <Button type="submit" variant="outline" className="h-[42px] gap-1.5">
                                <Search className="h-4 w-4" aria-hidden="true" />
                                Terapkan
                            </Button>
                            {hasActiveFilters && (
                                <Button type="button" variant="ghost" className="h-[42px]" onClick={resetFilters}>
                                    Reset
                                </Button>
                            )}
                        </div>
                    </form>
                    {filters.renstra_id && (
                        <Link
                            href="/jadwal"
                            className="mx-4 mb-4 inline-block rounded text-xs text-primary underline focus:ring-2 focus:ring-primary"
                        >
                            Tampilkan semua Renstra
                        </Link>
                    )}
                </Card>

                <Card>
                    {/* Grid mobile seperti Master Periode: tanpa scroll horizontal di layar sempit. */}
                    <Table wrapperClassName="overflow-x-visible" className="block md:table">
                        <caption className="sr-only">Daftar jadwal tahunan</caption>
                        <TableHeader className="hidden md:table-header-group">
                            <TableRow>
                                {['Renstra', 'Tahun', 'Status', 'Penutupan', 'Aksi'].map((label) => (
                                    <TableHead
                                        key={label}
                                        className={`whitespace-normal ${label === 'Aksi' ? 'text-right' : ''}`}
                                    >
                                        {label}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody className="block md:table-row-group">
                            {jadwal.data.length === 0 && (
                                <TableRow className="block md:table-row">
                                    <TableCell colSpan={5} className="block py-12 text-center md:table-cell">
                                        <CalendarRange className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                        <p className="mt-2 text-sm font-semibold text-ink">Tidak ada jadwal</p>
                                        <p className="mt-1 text-xs text-muted">
                                            {filtered
                                                ? 'Coba ubah kata kunci, tahun, atau status pada filter.'
                                                : 'Belum ada jadwal tahunan yang disusun.'}
                                        </p>
                                    </TableCell>
                                </TableRow>
                            )}
                            {jadwal.data.map((item) => {
                                const aksi = item.can_update ? 'Edit jadwal' : 'Lihat jadwal';

                                return (
                                    <TableRow key={item.id} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                        <TableCell className="col-span-2 min-w-0 break-words p-0 text-sm font-semibold md:max-w-96 md:px-4 md:py-3.5">
                                            {item.renstra.nama}
                                        </TableCell>
                                        <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5">
                                            <span className="mr-2 text-muted md:hidden">Tahun</span>
                                            {item.tahun}
                                        </TableCell>
                                        <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5">
                                            <Badge variant={statusJadwal[item.status].variant}>
                                                {statusJadwal[item.status].label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5">
                                            <span className="mr-2 text-muted md:hidden">Penutupan</span>
                                            <span className="whitespace-nowrap">{formatTanggal(item.penutupan)}</span>
                                        </TableCell>
                                        <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5">
                                            <div className="flex min-w-0 justify-end">
                                                <Tooltip content={aksi} align="right">
                                                    <Link
                                                        href={`/jadwal/${item.id}`}
                                                        className={iconAction}
                                                        aria-label={`${aksi} ${item.renstra.nama} ${item.tahun}`}
                                                    >
                                                        {item.can_update ? (
                                                            <Pencil className="h-4 w-4" aria-hidden="true" />
                                                        ) : (
                                                            <Eye className="h-4 w-4" aria-hidden="true" />
                                                        )}
                                                    </Link>
                                                </Tooltip>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                    <Pagination
                        links={jadwal.links}
                        from={jadwal.from}
                        to={jadwal.to}
                        total={jadwal.total}
                        ariaLabel="Paginasi jadwal tahunan"
                        className="border-t border-border px-5 py-3.5"
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
