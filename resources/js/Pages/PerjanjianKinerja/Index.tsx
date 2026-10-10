import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, FileText, Pencil, Plus, Search } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card } from '@/Components/Card';
import { Pagination } from '@/Components/Pagination';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Tooltip } from '@/Components/Tooltip';
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
import { StatusJadwalBadge } from './Partials/StatusJadwal';

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
        upload_berkas?: boolean;
    };
}

const iconAction = 'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-surface text-muted shadow-2xs transition-colors hover:border-primary/40 hover:bg-soft hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20';

export default function Index({ perjanjianKinerja, renstras, storageSettings, filters, can }: IndexProps) {
    const formatTanggal = useFormatTanggal();
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [editingPk, setEditingPk] = useState<RenstraPkSummary | null>(null);
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
            title="Perjanjian Kinerja (PK)"
            breadcrumbs={[{ label: 'Perjanjian Kinerja (PK)' }]}
            headerActions={can.create && (
                <Button size="sm" onClick={() => setIsCreateModalOpen(true)} className="gap-1.5">
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    Tambah Perjanjian Kinerja
                </Button>
            )}
        >
            <Head title="Perjanjian Kinerja (PK)" />

            <div className="space-y-5">
                <Card className="overflow-visible">
                    <form onSubmit={handleSearchSubmit} className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.4fr)_9rem_minmax(0,1fr)_auto] lg:items-end">
                        <Select id="filter_renstra_id" label="Periode Renstra" value={renstraId} onChange={(e) => setRenstraId(e.target.value)}>
                            <option value="">Semua Renstra</option>
                            {renstras.map((r) => (
                                <option key={r.id} value={r.id}>
                                    {r.nama} ({r.tahun_mulai}–{r.tahun_selesai})
                                </option>
                            ))}
                        </Select>
                        <Input type="number" label="Tahun" placeholder="Contoh: 2026" value={tahun} onChange={(e) => setTahun(e.target.value)} />
                        <Input label="Cari nomor PK" placeholder="Ketik nomor dokumen…" value={search} onChange={(e) => setSearch(e.target.value)} />
                        <div className="flex gap-2">
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
                </Card>

                <Card>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-20">Tahun</TableHead>
                                <TableHead>Nomor Dokumen PK</TableHead>
                                <TableHead>Tanggal PK</TableHead>
                                <TableHead>Status Jadwal</TableHead>
                                <TableHead>Lampiran</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
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
                                    const statusJadwal = item.jadwal_tahunan?.status;
                                    const jumlahBerkas = item.berkas_count ?? item.berkas?.length ?? 0;

                                    return (
                                        <TableRow key={item.id}>
                                            <TableCell className="whitespace-nowrap font-mono text-sm font-semibold">{item.tahun}</TableCell>
                                            <TableCell className="max-w-md">
                                                <Link href={`/perjanjian-kinerja/${item.id}`} className="text-sm font-semibold text-ink hover:text-primary hover:underline">
                                                    {item.nomor_pk}
                                                </Link>
                                                <p className="mt-0.5 truncate text-xs text-muted" title={item.renstra.nama}>{item.renstra.nama}</p>
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-muted">
                                                {item.tanggal_pk ? formatTanggal(item.tanggal_pk) : '-'}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                <StatusJadwalBadge status={statusJadwal} />
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-sm">
                                                {jumlahBerkas > 0 ? `${jumlahBerkas} berkas` : <span className="text-muted">-</span>}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Tooltip content="Lihat Detail">
                                                        <Link href={`/perjanjian-kinerja/${item.id}`} className={iconAction} aria-label={`Lihat detail PK ${item.nomor_pk}`}>
                                                            <Eye className="h-4 w-4" aria-hidden="true" />
                                                        </Link>
                                                    </Tooltip>
                                                    {can.update && (
                                                        <Tooltip content="Edit Perjanjian Kinerja" align="right">
                                                            <button type="button" onClick={() => setEditingPk(item)} className={iconAction} aria-label={`Edit PK ${item.nomor_pk}`}>
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

                    <Pagination
                        links={perjanjianKinerja.links}
                        from={perjanjianKinerja.from}
                        to={perjanjianKinerja.to}
                        total={perjanjianKinerja.total}
                        ariaLabel="Paginasi Perjanjian Kinerja"
                        className="border-t border-border px-5 py-3.5"
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
                    isJadwalAktif={editingPk?.jadwal_tahunan?.status === 'aktif'}
                    isJadwalTerkunci={Boolean(editingPk?.jadwal_tahunan?.is_terkunci ?? (editingPk?.jadwal_tahunan?.status === 'aktif' || editingPk?.jadwal_tahunan?.status === 'ditutup' || Boolean(editingPk?.jadwal_tahunan?.activated_at)))}
                    jadwalStatus={editingPk?.jadwal_tahunan?.status ?? null}
                    storageSettings={storageSettings}
                    canUploadBerkas={can.upload_berkas ?? true}
                />
            )}
        </AuthenticatedLayout>
    );
}
