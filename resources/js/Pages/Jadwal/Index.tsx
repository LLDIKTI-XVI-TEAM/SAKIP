import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Table } from '@/Components/Table';
import type { SharedPageProps } from '@/types/auth';
import type { JadwalIndexProps } from '@/types/periode-jadwal';

export default function JadwalIndex({ jadwal, filters, can }: JadwalIndexProps) {
    const { auth, errors = {} } = usePage<SharedPageProps>().props;
    const [query, setQuery] = useState(filters.q);
    const [tahun, setTahun] = useState(filters.tahun ? String(filters.tahun) : '');
    const [status, setStatus] = useState(filters.status ?? '');
    const summaryRef = useRef<HTMLDivElement>(null);
    const hasErrors = Object.keys(errors).length > 0;
    useEffect(() => {
        if (hasErrors) summaryRef.current?.focus();
    }, [hasErrors, errors]);
    const existingPair = Boolean(filters.renstra_id && filters.tahun && jadwal.total > 0);
    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            '/jadwal',
            { q: query, tahun, status, renstra_id: filters.renstra_id ?? undefined, sort: filters.sort },
            { replace: true, preserveState: 'errors' },
        );
    };

    return (
        <AuthenticatedLayout title="Jadwal tahunan" breadcrumbs={[{ label: 'Jadwal Tahunan' }]}>
            <Head title="Jadwal tahunan" />
            <nav aria-label="Pengelolaan kalender" className="mb-6 flex gap-6 border-b border-border text-sm">
                <Link
                    href="/jadwal"
                    aria-current="page"
                    className="border-b-2 border-primary pb-3 font-semibold text-primary"
                >
                    Jadwal tahunan
                </Link>
                {auth.can.periode && (
                    <Link href="/periode" className="pb-3 text-muted hover:text-primary">
                        Master periode
                    </Link>
                )}
            </nav>
            <div className="space-y-5">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <p className="text-sm text-muted">
                        Kalender pengisian dan review untuk setiap Renstra dan tahun pelaporan.
                    </p>
                    {can.create && !existingPair && (
                        <Link
                            href="/jadwal/create"
                            className="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/30"
                        >
                            <Plus className="h-4 w-4" aria-hidden="true" />
                            Susun jadwal
                        </Link>
                    )}
                </div>
                {existingPair && (
                    <div role="status" className="rounded-lg border border-info/30 bg-info/5 p-4 text-sm">
                        <h2 className="font-semibold">Jadwal tahun ini sudah tersedia</h2>
                        <p className="mt-1">
                            Satu Renstra dan tahun memakai satu jadwal. Buka kalender yang tersedia di bawah.
                        </p>
                    </div>
                )}
                <Card>
                    <CardContent className="p-4">
                        {hasErrors && (
                            <div
                                ref={summaryRef}
                                role="alert"
                                tabIndex={-1}
                                className="mb-4 rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm focus:outline-none focus:ring-2 focus:ring-danger/30"
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
                            className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end"
                        >
                            <Input
                                label="Cari Renstra"
                                value={query}
                                maxLength={100}
                                error={errors.q}
                                onChange={(event) => setQuery(event.target.value)}
                                autoComplete="off"
                            />
                            <Input
                                label="Tahun pelaporan"
                                type="number"
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
                            <Button type="submit" variant="outline">
                                Terapkan filter
                            </Button>
                        </form>
                        {filters.renstra_id && (
                            <Link
                                href="/jadwal"
                                className="mt-3 inline-block rounded text-xs text-primary underline focus:ring-2 focus:ring-primary"
                            >
                                Tampilkan semua Renstra
                            </Link>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <Table className="block w-full text-left text-sm md:table">
                        <caption className="sr-only">Daftar jadwal tahunan</caption>
                        <thead className="hidden bg-soft text-xs text-muted md:table-header-group">
                            <tr>
                                {['Renstra', 'Tahun', 'Status', 'Penutupan', 'Aksi'].map((label) => (
                                    <th key={label} scope="col" className="px-4 py-3 font-medium">
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="block divide-y divide-border md:table-row-group">
                            {jadwal.data.length === 0 && (
                                <tr className="block md:table-row">
                                    <td colSpan={5} className="block px-4 py-10 text-center text-muted md:table-cell">
                                        Tidak ada jadwal ditemukan.
                                    </td>
                                </tr>
                            )}
                            {jadwal.data.map((item) => (
                                <tr key={item.id} className="grid grid-cols-2 gap-3 p-4 md:table-row">
                                    <td className="col-span-2 break-words font-semibold md:max-w-96 md:px-4 md:py-4">
                                        {item.renstra.nama}
                                    </td>
                                    <td className="md:px-4 md:py-4">
                                        <span className="mr-2 text-xs text-muted md:hidden">Tahun</span>
                                        {item.tahun}
                                    </td>
                                    <td className="md:px-4 md:py-4">
                                        <Badge variant={item.status === 'aktif' ? 'success' : 'muted'}>
                                            {item.status === 'aktif'
                                                ? 'Aktif'
                                                : item.status === 'ditutup'
                                                  ? 'Ditutup'
                                                  : 'Draft'}
                                        </Badge>
                                    </td>
                                    <td className="md:px-4 md:py-4">
                                        <span className="mr-2 text-xs text-muted md:hidden">Penutupan</span>
                                        {item.penutupan ?? '—'}
                                    </td>
                                    <td className="md:px-4 md:py-4">
                                        <Link
                                            href={`/jadwal/${item.id}`}
                                            className="inline-flex rounded-lg border border-border px-3 py-2 text-xs font-semibold text-primary hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/30"
                                        >
                                            {item.can_update ? 'Edit jadwal' : 'Lihat jadwal'}
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                    {jadwal.last_page > 1 && (
                        <nav
                            aria-label="Halaman jadwal"
                            className="flex flex-wrap items-center justify-between gap-3 border-t border-border p-4 text-xs text-muted"
                        >
                            <span>
                                {jadwal.from ?? 0}–{jadwal.to ?? 0} dari {jadwal.total} jadwal
                            </span>
                            <div className="flex flex-wrap gap-1">
                                {jadwal.links.map((link, index) =>
                                    link.url ? (
                                        <Link
                                            key={index}
                                            href={link.url}
                                            aria-current={link.active ? 'page' : undefined}
                                            className={`rounded px-3 py-2 focus:ring-2 focus:ring-primary ${link.active ? 'bg-primary text-white' : 'hover:bg-soft'}`}
                                        >
                                            {link.label
                                                .replace('&laquo;', '‹')
                                                .replace('&raquo;', '›')
                                                .replace('Previous', 'Sebelumnya')
                                                .replace('Next', 'Berikutnya')}
                                        </Link>
                                    ) : null,
                                )}
                            </div>
                        </nav>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
