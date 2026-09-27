import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Calendar,
    FileSpreadsheet,
    FileText,
    Filter,
    Plus,
    Search,
    ShieldAlert,
    ShieldCheck,
    X,
    Eye,
    Pencil,
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Badge } from '@/Components/Badge';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { Paginated, RenstraPkSummary, RenstraSummary } from '@/types/perjanjian-kinerja';

interface IndexProps {
    perjanjianKinerja: Paginated<RenstraPkSummary>;
    renstras: RenstraSummary[];
    filters: {
        renstra_id?: string;
        tahun?: string | number;
        q?: string;
    };
    can: {
        create: boolean;
    };
}

export default function Index({ perjanjianKinerja, renstras, filters, can }: IndexProps) {
    const formatTanggal = useFormatTanggal();
    const [renstraId, setRenstraId] = useState(filters.renstra_id ?? '');
    const [tahun, setTahun] = useState(filters.tahun ? String(filters.tahun) : '');
    const [search, setSearch] = useState(filters.q ?? '');

    const applyFilters = (newFilters: { renstra_id?: string; tahun?: string; q?: string }) => {
        router.get(
            '/perjanjian-kinerja',
            {
                renstra_id: newFilters.renstra_id || undefined,
                tahun: newFilters.tahun || undefined,
                q: newFilters.q || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters({ renstra_id: renstraId, tahun, q: search });
    };

    const resetFilters = () => {
        setRenstraId('');
        setTahun('');
        setSearch('');
        applyFilters({});
    };

    const hasActiveFilters = Boolean(renstraId || tahun || search);

    return (
        <AuthenticatedLayout
            title="Perjanjian Kinerja"
            breadcrumbs={[{ label: 'Perjanjian Kinerja' }]}
        >
            <Head title="Perjanjian Kinerja" />

            <div className="mx-auto max-w-7xl space-y-6">
                {/* Header Section */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">
                            Perjanjian Kinerja (PK)
                        </h1>
                        <p className="mt-1 text-sm text-muted">
                            Pencatatan dokumen komitmen kinerja tahunan LLDIKTI Wilayah XVI beserta lampiran legalnya.
                        </p>
                    </div>

                    {can.create && (
                        <div className="shrink-0">
                            <Link href="/perjanjian-kinerja/create">
                                <Button className="w-full sm:w-auto">
                                    <Plus className="h-4 w-4" aria-hidden="true" />
                                    Catat PK Baru
                                </Button>
                            </Link>
                        </div>
                    )}
                </div>

                {/* Filter Toolbar */}
                <Card>
                    <CardContent className="p-4 sm:p-5">
                        <form onSubmit={handleSearchSubmit} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <Select
                                    label="Filter Periode Renstra"
                                    value={renstraId}
                                    onChange={(e) => {
                                        setRenstraId(e.target.value);
                                        applyFilters({ renstra_id: e.target.value, tahun, q: search });
                                    }}
                                >
                                    <option value="">Semua Renstra</option>
                                    {renstras.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.nama} ({r.tahun_mulai} - {r.tahun_selesai})
                                        </option>
                                    ))}
                                </Select>
                            </div>

                            <div>
                                <Input
                                    type="number"
                                    label="Filter Tahun"
                                    placeholder="Contoh: 2026"
                                    value={tahun}
                                    onChange={(e) => setTahun(e.target.value)}
                                    onBlur={() => applyFilters({ renstra_id: renstraId, tahun, q: search })}
                                />
                            </div>

                            <div className="sm:col-span-2 lg:col-span-1">
                                <Input
                                    label="Cari Nomor PK"
                                    placeholder="Ketik nomor surat..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>

                            <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-1">
                                <Button type="submit" variant="primary" className="flex-1">
                                    <Search className="h-4 w-4" aria-hidden="true" />
                                    Filter
                                </Button>
                                {hasActiveFilters && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={resetFilters}
                                        title="Reset filter"
                                    >
                                        <X className="h-4 w-4" aria-hidden="true" />
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Data Table */}
                <Card>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-ink">
                            <thead className="border-b border-border bg-soft text-xs uppercase tracking-wider text-muted">
                                <tr>
                                    <th scope="col" className="px-5 py-3 font-semibold">Tahun & Renstra</th>
                                    <th scope="col" className="px-5 py-3 font-semibold">Nomor Dokumen PK</th>
                                    <th scope="col" className="px-5 py-3 font-semibold">Tanggal PK</th>
                                    <th scope="col" className="px-5 py-3 font-semibold">Status Jadwal</th>
                                    <th scope="col" className="px-5 py-3 font-semibold">Lampiran</th>
                                    <th scope="col" className="px-5 py-3 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {perjanjianKinerja.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-5 py-12 text-center text-muted">
                                            <FileText className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                            <p className="mt-2 text-sm font-semibold text-ink">
                                                Tidak ada data Perjanjian Kinerja
                                            </p>
                                            <p className="mt-1 text-xs text-muted">
                                                {hasActiveFilters
                                                    ? 'Coba sesuaikan atau bersihkan kata kunci filter pencarian Anda.'
                                                    : 'Belum ada Perjanjian Kinerja yang dicatat dalam sistem.'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    perjanjianKinerja.data.map((item) => {
                                        const isJadwalAktif = item.jadwal_tahunan?.status === 'aktif';

                                        return (
                                            <tr key={item.id} className="hover:bg-soft/50 transition-colors">
                                                <td className="px-5 py-4 whitespace-nowrap">
                                                    <div className="flex items-center gap-2">
                                                        <span className="inline-flex items-center justify-center rounded-lg bg-primary/10 px-2.5 py-1 font-bold text-primary">
                                                            {item.tahun}
                                                        </span>
                                                        <span className="text-xs text-muted truncate max-w-[180px]" title={item.renstra.nama}>
                                                            {item.renstra.nama}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-5 py-4 font-semibold text-ink">
                                                    <Link
                                                        href={`/perjanjian-kinerja/${item.id}`}
                                                        className="hover:text-primary transition-colors inline-block"
                                                    >
                                                        {item.nomor_pk}
                                                    </Link>
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap text-muted">
                                                    {item.tanggal_pk ? formatTanggal(item.tanggal_pk) : '-'}
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap">
                                                    {isJadwalAktif ? (
                                                        <Badge variant="success">
                                                            <ShieldCheck className="h-3 w-3 mr-1" aria-hidden="true" />
                                                            Jadwal Aktif
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="muted">
                                                            Belum Aktif
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap text-muted">
                                                    {item.berkas && item.berkas.length > 0 ? (
                                                        <span className="inline-flex items-center gap-1.5 text-xs font-medium text-ink bg-soft px-2.5 py-1 rounded-md">
                                                            <FileText className="h-3.5 w-3.5 text-primary" aria-hidden="true" />
                                                            {item.berkas.length} Berkas
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-muted/70">Tanpa Lampiran</span>
                                                    )}
                                                </td>
                                                <td className="px-5 py-4 whitespace-nowrap text-right text-sm">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Link
                                                            href={`/perjanjian-kinerja/${item.id}`}
                                                            className="inline-flex items-center justify-center rounded-lg p-1.5 text-muted hover:bg-soft hover:text-primary transition-colors"
                                                            title="Lihat Detail PK"
                                                        >
                                                            <Eye className="h-4 w-4" aria-hidden="true" />
                                                        </Link>
                                                        <Link
                                                            href={`/perjanjian-kinerja/${item.id}/edit`}
                                                            className="inline-flex items-center justify-center rounded-lg p-1.5 text-muted hover:bg-soft hover:text-amber-600 transition-colors"
                                                            title="Edit Dokumen PK"
                                                        >
                                                            <Pencil className="h-4 w-4" aria-hidden="true" />
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {perjanjianKinerja.links && perjanjianKinerja.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-border px-5 py-3 text-sm text-muted">
                            <div>
                                Menampilkan {perjanjianKinerja.from ?? 0} s/d {perjanjianKinerja.to ?? 0} dari {perjanjianKinerja.total} data
                            </div>
                            <div className="flex items-center gap-1">
                                {perjanjianKinerja.links.map((link, idx) => {
                                    if (!link.url) {
                                        return (
                                            <span
                                                key={idx}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                className="px-3 py-1.5 text-xs text-muted/50"
                                            />
                                        );
                                    }
                                    return (
                                        <Link
                                            key={idx}
                                            href={link.url}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className={`rounded-md px-3 py-1.5 text-xs font-semibold transition-colors ${
                                                link.active
                                                    ? 'bg-primary text-white'
                                                    : 'text-ink hover:bg-soft'
                                            }`}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
