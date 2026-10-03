import React, { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Eye,
    FileText,
    Lock,
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
import { Badge } from '@/Components/Badge';
import { CustomSelect } from '@/Components/CustomSelect';
import { EmptyState } from '@/Components/EmptyState';
import { Pagination } from '@/Components/Pagination';
import { Tooltip } from '@/Components/Tooltip';
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
        per_page?: string | number;
    };
    can: {
        create: boolean;
        update?: boolean;
        upload_berkas?: boolean;
    };
}

export default function Index({ perjanjianKinerja, renstras, storageSettings, filters, can }: IndexProps) {
    const formatTanggal = useFormatTanggal();
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [editingPk, setEditingPk] = useState<RenstraPkSummary | null>(null);
    const [renstraId, setRenstraId] = useState(filters.renstra_id ?? '');
    const [tahun, setTahun] = useState(filters.tahun ? String(filters.tahun) : '');
    const [search, setSearch] = useState(filters.q ?? '');

    const renstraOptions = useMemo(() => {
        return renstras.map((r) => ({
            value: r.id,
            label: `${r.nama} (${r.tahun_mulai} - ${r.tahun_selesai})`,
        }));
    }, [renstras]);

    const applyFilters = (newFilters: {
        renstra_id?: string;
        tahun?: string;
        q?: string;
        per_page?: string | number;
    }) => {
        const perPageValue = newFilters.per_page !== undefined ? newFilters.per_page : filters.per_page;
        router.get(
            '/perjanjian-kinerja',
            {
                renstra_id: newFilters.renstra_id || undefined,
                tahun: newFilters.tahun || undefined,
                q: newFilters.q || undefined,
                per_page: perPageValue ? String(perPageValue) : undefined,
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
        applyFilters({ renstra_id: '', tahun: '', q: '', per_page: filters.per_page });
    };

    const hasActiveFilters = Boolean(renstraId || tahun || search);

    return (
        <AuthenticatedLayout
            title="Perjanjian Kinerja (PK)"
            breadcrumbs={[{ label: 'Perjanjian Kinerja (PK)' }]}
            headerAction={
                can.create ? (
                    <Button
                        size="sm"
                        onClick={() => setIsCreateModalOpen(true)}
                        className="w-full sm:w-auto gap-1.5"
                    >
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Catat PK Baru
                    </Button>
                ) : null
            }
        >
            <Head title="Perjanjian Kinerja (PK)" />

            <div className="mx-auto max-w-7xl space-y-4 mt-1.5 sm:mt-2">
                {/* Filter Toolbar */}
                <Card>
                    <CardContent className="p-4 sm:p-5">
                        <form onSubmit={handleSearchSubmit} className="flex flex-col gap-3 lg:flex-row lg:items-end">
                            <div className="flex-1 min-w-0">
                                <CustomSelect
                                    id="filter_renstra_id"
                                    name="filter_renstra_id"
                                    label="Periode Renstra"
                                    labelClassName="whitespace-nowrap"
                                    placeholder="Semua Renstra"
                                    emptyOptionLabel="Semua Renstra"
                                    options={renstraOptions}
                                    value={renstraId}
                                    onChange={(val) => setRenstraId(String(val ?? ''))}
                                />
                            </div>

                            <div className="w-full lg:w-36">
                                <Input
                                    type="number"
                                    label="Tahun"
                                    placeholder="2026"
                                    value={tahun}
                                    onChange={(e) => setTahun(e.target.value)}
                                />
                            </div>

                            <div className="flex-1">
                                <Input
                                    label="Cari Nomor PK"
                                    placeholder="Cari nomor surat..."
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
                                        aria-label="Reset filter pencarian"
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
                                    <TableCell colSpan={6} className="p-0">
                                        <EmptyState
                                            icon={FileText}
                                            title="Tidak ada data Perjanjian Kinerja"
                                            description={
                                                hasActiveFilters
                                                    ? 'Coba sesuaikan atau bersihkan kata kunci filter pencarian Anda.'
                                                    : 'Belum ada Perjanjian Kinerja yang dicatat dalam sistem.'
                                            }
                                        />
                                    </TableCell>
                                </TableRow>
                            ) : (
                                perjanjianKinerja.data.map((item) => {
                                    const statusJadwal = item.jadwal_tahunan?.status;

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
                                                {statusJadwal === 'aktif' ? (
                                                    <Badge variant="success">
                                                        <ShieldCheck className="h-3 w-3 mr-1" aria-hidden="true" />
                                                        Aktif
                                                    </Badge>
                                                ) : statusJadwal === 'ditutup' ? (
                                                    <Badge variant="secondary">
                                                        <Lock className="h-3 w-3 mr-1" aria-hidden="true" />
                                                        Ditutup
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
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Tooltip content="Lihat Detail" align="right">
                                                        <Link
                                                            href={`/perjanjian-kinerja/${item.id}`}
                                                            className="inline-flex items-center justify-center rounded-lg p-1.5 text-muted hover:bg-soft hover:text-primary transition-colors"
                                                            aria-label={`Lihat Detail PK ${item.nomor_pk}`}
                                                        >
                                                            <Eye className="h-4 w-4" aria-hidden="true" />
                                                        </Link>
                                                    </Tooltip>
                                                    {can.update && (
                                                        <Tooltip content="Edit Dokumen" align="right">
                                                            <button
                                                                type="button"
                                                                onClick={() => setEditingPk(item)}
                                                                className="inline-flex items-center justify-center rounded-lg p-1.5 text-muted hover:bg-soft hover:text-primary transition-colors cursor-pointer"
                                                                aria-label={`Edit Dokumen PK ${item.nomor_pk}`}
                                                            >
                                                                <Pencil className="h-4 w-4" aria-hidden="true" />
                                                            </button>
                                                        </Tooltip>
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
                    <Pagination
                        pagination={perjanjianKinerja}
                        perPage={Number(filters.per_page ?? perjanjianKinerja.per_page ?? 10)}
                        onPerPageChange={(newPerPage) => {
                            applyFilters({
                                renstra_id: renstraId,
                                tahun,
                                q: search,
                                per_page: newPerPage,
                            });
                        }}
                    />
                </Card>
            </div>

            {can.create && (
                <PerjanjianKinerjaCreateModal
                    isOpen={isCreateModalOpen}
                    onClose={() => setIsCreateModalOpen(false)}
                    renstras={renstras}
                    storageSettings={storageSettings}
                    canUploadBerkas={can.upload_berkas ?? true}
                />
            )}

            {can.update && (
                <PerjanjianKinerjaEditModal
                    isOpen={Boolean(editingPk)}
                    onClose={() => setEditingPk(null)}
                    pk={editingPk}
                    storageSettings={storageSettings}
                    canUploadBerkas={can.upload_berkas ?? true}
                />
            )}
        </AuthenticatedLayout>
    );
}
