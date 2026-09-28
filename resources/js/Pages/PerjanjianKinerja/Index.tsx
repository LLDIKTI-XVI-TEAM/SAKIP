import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Calendar,
    ChevronDown,
    Eye,
    FileSpreadsheet,
    FileText,
    Pencil,
    Plus,
    Search,
    ShieldCheck,
    X,
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Badge } from '@/Components/Badge';
import { HoverScrollText } from '@/Components/HoverScrollText';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/Table';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { Paginated, RenstraPkSummary, RenstraSummary, StorageSettings } from '@/types/perjanjian-kinerja';
import { PerjanjianKinerjaCreateModal } from './Partials/PerjanjianKinerjaCreateModal';
import { PerjanjianKinerjaEditModal } from './Partials/PerjanjianKinerjaEditModal';

interface IndexProps {
    perjanjianKinerja: Paginated<RenstraPkSummary>;
    renstras: RenstraSummary[];
    storageSettings?: StorageSettings;
    filters: {
        renstra_id?: string;
        tahun?: string | number;
        q?: string;
    };
    can: {
        create: boolean;
        update?: boolean;
    };
}

export default function Index({ perjanjianKinerja, renstras, storageSettings, filters, can }: IndexProps) {
    const formatTanggal = useFormatTanggal();
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [editingPk, setEditingPk] = useState<RenstraPkSummary | null>(null);
    const [renstraId, setRenstraId] = useState(filters.renstra_id ?? '');
    const [tahun, setTahun] = useState(filters.tahun ? String(filters.tahun) : '');
    const [search, setSearch] = useState(filters.q ?? '');
    const [isFilterRenstraHovered, setIsFilterRenstraHovered] = useState(false);

    const selectedFilterRenstra = renstras.find((r) => r.id === renstraId);

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
            title="Perjanjian Kinerja (PK)"
            breadcrumbs={[{ label: 'Perjanjian Kinerja (PK)' }]}
        >
            <Head title="Perjanjian Kinerja (PK)" />

            <div className="mx-auto max-w-7xl space-y-6">
                {/* Header Action Bar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-muted">
                        Pencatatan dokumen komitmen kinerja tahunan LLDIKTI Wilayah XVI beserta lampiran legalnya.
                    </p>

                    {can.create && (
                        <div className="shrink-0">
                            <Button
                                size="sm"
                                onClick={() => setIsCreateModalOpen(true)}
                                className="w-full sm:w-auto gap-1.5"
                            >
                                <Plus className="h-4 w-4" aria-hidden="true" />
                                Catat PK Baru
                            </Button>
                        </div>
                    )}
                </div>

                {/* Filter Toolbar */}
                <Card>
                    <CardContent className="p-4 sm:p-5">
                        <form onSubmit={handleSearchSubmit} className="flex flex-col gap-3 lg:flex-row lg:items-end">
                            <div className="flex-1">
                                <label
                                    htmlFor="filter_renstra_id"
                                    className="mb-1.5 block text-sm font-medium text-ink whitespace-nowrap"
                                >
                                    Filter Periode Renstra
                                </label>
                                <div
                                    className="relative w-full h-[42px] rounded-lg border border-border bg-surface px-3.5 py-2 text-sm transition-colors flex items-center justify-between cursor-pointer hover:border-border-dark focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                                    onMouseEnter={() => setIsFilterRenstraHovered(true)}
                                    onMouseLeave={() => setIsFilterRenstraHovered(false)}
                                >
                                    <div className="min-w-0 flex-1 pr-6 overflow-hidden">
                                        {selectedFilterRenstra ? (
                                            <HoverScrollText
                                                text={`${selectedFilterRenstra.nama} (${selectedFilterRenstra.tahun_mulai} - ${selectedFilterRenstra.tahun_selesai})`}
                                                isParentHovered={isFilterRenstraHovered}
                                                className="text-sm font-normal text-ink"
                                                textClassName="font-normal"
                                            />
                                        ) : (
                                            <span className="text-ink text-sm select-none">
                                                Semua Renstra
                                            </span>
                                        )}
                                    </div>

                                    <div className="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-muted">
                                        <ChevronDown className="h-4 w-4" aria-hidden="true" />
                                    </div>

                                    <select
                                        id="filter_renstra_id"
                                        name="filter_renstra_id"
                                        aria-label="Filter Periode Renstra"
                                        value={renstraId}
                                        onChange={(e) => setRenstraId(e.target.value)}
                                        className="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20"
                                    >
                                        <option value="">Semua Renstra</option>
                                        {renstras.map((r) => (
                                            <option key={r.id} value={r.id}>
                                                {r.nama} ({r.tahun_mulai} - {r.tahun_selesai})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="w-full lg:w-36">
                                <Input
                                    type="number"
                                    label="Filter Tahun"
                                    placeholder="Contoh: 2026"
                                    value={tahun}
                                    onChange={(e) => setTahun(e.target.value)}
                                />
                            </div>

                            <div className="flex-1">
                                <Input
                                    label="Cari Nomor PK"
                                    placeholder="Ketik nomor surat..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>

                            <div className="flex items-center gap-2 shrink-0">
                                <Button type="submit" variant="primary" size="md" className="gap-1.5 px-4 shrink-0">
                                    <Search className="h-4 w-4" aria-hidden="true" />
                                    Filter
                                </Button>
                                {hasActiveFilters && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="md"
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
                <Card className="overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>TAHUN & RENSTRA</TableHead>
                                <TableHead>NOMOR DOKUMEN PK</TableHead>
                                <TableHead>TANGGAL PK</TableHead>
                                <TableHead>STATUS JADWAL</TableHead>
                                <TableHead>LAMPIRAN</TableHead>
                                <TableHead className="text-right">AKSI</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {perjanjianKinerja.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="px-5 py-12 text-center text-muted">
                                        <FileText className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                        <p className="mt-2 text-sm font-semibold text-ink">
                                            Tidak ada data Perjanjian Kinerja
                                        </p>
                                        <p className="mt-1 text-xs text-muted">
                                            {hasActiveFilters
                                                ? 'Coba sesuaikan atau bersihkan kata kunci filter pencarian Anda.'
                                                : 'Belum ada Perjanjian Kinerja yang dicatat dalam sistem.'}
                                        </p>
                                    </TableCell>
                                </TableRow>
                            ) : (
                                perjanjianKinerja.data.map((item) => {
                                    const isJadwalAktif = item.jadwal_tahunan?.status === 'aktif';

                                    return (
                                        <TableRow key={item.id}>
                                            <TableCell className="whitespace-nowrap">
                                                <div className="flex items-center gap-2">
                                                    <span className="inline-flex items-center justify-center rounded-lg bg-primary/10 px-2.5 py-1 font-bold text-primary shrink-0">
                                                        {item.tahun}
                                                    </span>
                                                    <Link
                                                        href={`/perjanjian-kinerja/${item.id}`}
                                                        className="min-w-0 max-w-[200px] overflow-hidden block hover:text-primary transition-colors cursor-pointer group"
                                                    >
                                                        <HoverScrollText
                                                            text={item.renstra.nama}
                                                            className="text-xs font-normal text-muted group-hover:text-primary transition-colors"
                                                            textClassName="font-normal text-muted group-hover:text-primary transition-colors"
                                                        />
                                                    </Link>
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-semibold text-ink">
                                                <Link
                                                    href={`/perjanjian-kinerja/${item.id}`}
                                                    className="hover:text-primary transition-colors inline-block"
                                                >
                                                    {item.nomor_pk}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-muted">
                                                {item.tanggal_pk ? formatTanggal(item.tanggal_pk) : '-'}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
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
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-muted">
                                                {(item.berkas_count ?? item.berkas?.length ?? 0) > 0 ? (
                                                    <span className="inline-flex items-center gap-1.5 text-xs font-medium text-ink bg-soft px-2.5 py-1 rounded-md">
                                                        <FileText className="h-3.5 w-3.5 text-primary" aria-hidden="true" />
                                                        {item.berkas_count ?? item.berkas?.length} Berkas
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-muted/70">Tanpa Lampiran</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-right text-sm">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Link
                                                        href={`/perjanjian-kinerja/${item.id}`}
                                                        className="inline-flex items-center justify-center rounded-lg p-1.5 text-muted hover:bg-soft hover:text-primary transition-colors"
                                                        title="Lihat Detail PK"
                                                    >
                                                        <Eye className="h-4 w-4" aria-hidden="true" />
                                                    </Link>
                                                    {can.update && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setEditingPk(item)}
                                                            className="inline-flex items-center justify-center rounded-lg p-1.5 text-muted hover:bg-soft hover:text-amber-600 transition-colors cursor-pointer"
                                                            title="Edit Dokumen PK"
                                                        >
                                                            <Pencil className="h-4 w-4" aria-hidden="true" />
                                                        </button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>

                    {/* Pagination */}
                    {perjanjianKinerja.links && perjanjianKinerja.links.length > 3 && (
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-border px-5 py-3.5 text-sm text-muted">
                            <div>
                                Menampilkan {perjanjianKinerja.from ?? 0} s/d {perjanjianKinerja.to ?? 0} dari {perjanjianKinerja.total} data
                            </div>
                            <nav aria-label="Navigasi halaman" className="flex items-center gap-1">
                                {perjanjianKinerja.links.map((link, idx) => {
                                    if (!link.url) {
                                        return (
                                            <span
                                                key={idx}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                className="rounded-lg border border-border px-3 py-1.5 text-xs text-muted/50"
                                            />
                                        );
                                    }
                                    return (
                                        <Link
                                            key={idx}
                                            href={link.url}
                                            preserveScroll
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                                                link.active
                                                    ? 'bg-primary text-white'
                                                    : 'border border-border bg-surface text-ink hover:bg-soft'
                                            }`}
                                        />
                                    );
                                })}
                            </nav>
                        </div>
                    )}
                </Card>
            </div>

            {can.create && (
                <PerjanjianKinerjaCreateModal
                    isOpen={isCreateModalOpen}
                    onClose={() => setIsCreateModalOpen(false)}
                    renstras={renstras}
                    storageSettings={storageSettings}
                />
            )}

            {can.update && (
                <PerjanjianKinerjaEditModal
                    isOpen={Boolean(editingPk)}
                    onClose={() => setEditingPk(null)}
                    pk={editingPk}
                    isJadwalAktif={editingPk?.jadwal_tahunan?.status === 'aktif'}
                    storageSettings={storageSettings}
                />
            )}
        </AuthenticatedLayout>
    );
}
