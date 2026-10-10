import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronDown, Edit3, Eye, FileText, Plus, Search, Trash2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { Pagination } from '@/Components/Pagination';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { RenstraFormFields } from '@/Components/RenstraFormFields';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import type { Paginated, RegulasiOption, RenstraFormData, RenstraStatus, RenstraSummary } from '@/types/renstra';

interface RenstraIndexProps {
    renstra: Paginated<RenstraSummary>;
    regulasiPilihan?: RegulasiOption[];
    filters: {
        q: string;
        status: RenstraStatus | null;
    };
    can: Record<string, boolean>;
}

const statusBadgeVariant: Record<RenstraStatus, 'muted' | 'success' | 'danger' | 'secondary'> = {
    draft: 'muted',
    aktif: 'success',
    nonaktif: 'danger',
    diarsipkan: 'secondary',
};

const statusLabel: Record<RenstraStatus, string> = {
    draft: 'Draft',
    aktif: 'Aktif',
    nonaktif: 'Nonaktif',
    diarsipkan: 'Diarsipkan',
};

export default function RenstraIndex({ renstra, regulasiPilihan = [], filters, can }: RenstraIndexProps) {
    const [query, setQuery] = useState(filters.q || '');
    const [status, setStatus] = useState<string>(filters.status || '');
    const [selected, setSelected] = useState<RenstraSummary | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [reasonError, setReasonError] = useState<string | undefined>();
    const deleteForm = useForm({ alasan: '' });

    // State & Form Modal Tambah Renstra
    const [createOpen, setCreateOpen] = useState(false);
    const currentYear = new Date().getFullYear();
    const createForm = useForm<RenstraFormData>({
        nama: '',
        kode: '',
        tahun_mulai: String(currentYear),
        tahun_selesai: String(currentYear + 4),
        deskripsi: '',
        dasar_hukum: '',
        regulasi_id: '',
        alasan: '',
        lampiran: [],
    });

    const openCreateModal = () => {
        createForm.reset();
        createForm.clearErrors();
        setCreateOpen(true);
    };

    const closeCreateModal = () => {
        if (createForm.processing) return;
        setCreateOpen(false);
        createForm.reset();
        createForm.clearErrors();
    };

    const handleCreateSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (createForm.processing) return;
        createForm.transform((data) => {
            if (can.readRegulasi === true) return data;

            const payload: Partial<RenstraFormData> = { ...data };
            delete payload.regulasi_id;

            return payload;
        });
        createForm.post('/renstra', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const applyFilters = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/renstra', { q: query || undefined, status: status || undefined }, {
            preserveState: true,
            replace: true,
        });
    };

    const openDelete = (item: RenstraSummary) => {
        setSelected(item);
        deleteForm.reset();
        setReasonError(undefined);
        setDeleteOpen(true);
    };

    const confirmDelete = () => {
        if (!selected || deleteForm.processing) return;
        if (deleteForm.data.alasan.trim().length < 5) {
            setReasonError('Jelaskan alasan penghapusan minimal 5 karakter.');
            return;
        }

        deleteForm.delete(`/renstra/${selected.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteOpen(false);
                setSelected(null);
                deleteForm.reset();
            },
        });
    };

    return (
        <AuthenticatedLayout
            title="Master Renstra"
            breadcrumbs={[{ label: 'Master Renstra' }]}
            headerActions={can['renstra:create'] && (
                <button
                    type="button"
                    onClick={openCreateModal}
                    className="relative top-1 sm:top-3 inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer"
                >
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    <span>Tambah Renstra</span>
                </button>
            )}
        >
            <Head title="Master Renstra" />

            <div className="space-y-6">
                <Card>
                    <CardContent className="p-4 sm:p-5">
                        <form onSubmit={applyFilters} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="flex-1">
                                <label htmlFor="search-input" className="sr-only">
                                    Pencarian Dokumen
                                </label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" aria-hidden="true" />
                                    <input
                                        id="search-input"
                                        type="text"
                                        value={query}
                                        onChange={(e) => setQuery(e.target.value)}
                                        placeholder="Cari berdasarkan nama atau kode Renstra..."
                                        className="w-full rounded-lg border border-border bg-surface py-2 pl-9 pr-3 text-sm text-ink placeholder:text-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                    />
                                </div>
                            </div>

                            <div className="w-full sm:w-48">
                                <label htmlFor="status-select" className="sr-only">
                                    Status Dokumen
                                </label>
                                <div className="relative">
                                    <select
                                        id="status-select"
                                        value={status}
                                        onChange={(e) => setStatus(e.target.value)}
                                        className="w-full appearance-none rounded-lg border border-border bg-surface py-2 pl-3 pr-9 text-sm text-ink focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer"
                                    >
                                        <option value="">Semua Status</option>
                                        <option value="draft">Draft</option>
                                        <option value="aktif">Aktif</option>
                                        <option value="nonaktif">Nonaktif</option>
                                        <option value="diarsipkan">Diarsipkan</option>
                                    </select>
                                    <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" aria-hidden="true" />
                                </div>
                            </div>

                            <Button type="submit" variant="primary">
                                Terapkan Filter
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                        <Table className="text-sm">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kode & Nama Renstra</TableHead>
                                    <TableHead>Periode Tahun</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Rujukan Regulasi</TableHead>
                                    <TableHead>Naskah Lampiran</TableHead>
                                    <TableHead>Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {renstra.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="px-5 py-12 text-center text-sm font-medium text-muted">
                                            Tidak ada data Renstra ditemukan
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    renstra.data.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="px-5 py-4">
                                                <div className="font-semibold text-ink">{item.nama}</div>
                                                <div className="text-xs font-mono text-muted">{item.kode}</div>
                                            </TableCell>
                                            <TableCell className="px-5 py-4 whitespace-nowrap font-mono text-sm">
                                                {item.tahun_mulai} - {item.tahun_selesai}
                                            </TableCell>
                                            <TableCell className="px-5 py-4 whitespace-nowrap">
                                                <Badge variant={statusBadgeVariant[item.status]} dot>
                                                    {statusLabel[item.status]}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="px-5 py-4 text-xs text-muted">
                                                {item.regulasi_nomor ? (
                                                    <span className="font-medium text-ink">{item.regulasi_nomor}</span>
                                                ) : (
                                                    <span className="text-muted italic">Tidak ada</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="px-5 py-4 whitespace-nowrap text-xs text-muted">
                                                {item.berkas_count === null ? (
                                                    <span>Akses dibatasi</span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 font-medium text-ink">
                                                        <FileText className="h-3.5 w-3.5 text-muted" aria-hidden="true" />
                                                        {item.berkas_count} lampiran
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="px-5 py-4 whitespace-nowrap text-left">
                                                <div className="flex items-center justify-start gap-1.5">
                                                    <Tooltip content="Lihat Detail">
                                                        <Link
                                                            href={`/renstra/${item.id}`}
                                                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-surface text-muted shadow-2xs transition-colors hover:border-primary/40 hover:bg-soft hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/20"
                                                            aria-label={`Lihat detail ${item.kode}`}
                                                        >
                                                            <Eye className="h-4 w-4" aria-hidden="true" />
                                                            <span className="sr-only">Detail {item.kode}</span>
                                                        </Link>
                                                    </Tooltip>

                                                    {item.can_update && (
                                                        <Tooltip content="Edit Dokumen">
                                                            <Link
                                                                href={`/renstra/${item.id}/edit`}
                                                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-surface text-muted shadow-2xs transition-colors hover:border-primary/40 hover:bg-soft hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                                                aria-label={`Edit ${item.kode}`}
                                                            >
                                                                <Edit3 className="h-4 w-4" aria-hidden="true" />
                                                                <span className="sr-only">Edit {item.kode}</span>
                                                            </Link>
                                                        </Tooltip>
                                                    )}

                                                    {item.can_delete && (
                                                        <Tooltip content="Hapus Renstra">
                                                            <button
                                                                type="button"
                                                                onClick={() => openDelete(item)}
                                                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-danger/30 bg-danger/5 text-danger shadow-2xs transition-colors hover:bg-danger/10 hover:border-danger/40 focus:outline-none focus:ring-2 focus:ring-danger/20 cursor-pointer"
                                                                aria-label={`Hapus ${item.kode}`}
                                                            >
                                                                <Trash2 className="h-4 w-4" aria-hidden="true" />
                                                                <span className="sr-only">Hapus {item.kode}</span>
                                                            </button>
                                                        </Tooltip>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>

                    <Pagination
                        links={renstra.links}
                        from={renstra.from}
                        to={renstra.to}
                        total={renstra.total}
                        ariaLabel="Paginasi Renstra"
                        className="border-t border-border bg-page px-5 py-3"
                    />
                </Card>
            </div>

            <AuditReasonModal
                open={deleteOpen}
                title="Konfirmasi Hapus Renstra"
                description={`Apakah Anda yakin ingin menghapus data Renstra "${selected?.nama}" (${selected?.kode})? Tindakan ini akan dicatat dalam audit log.`}
                reason={deleteForm.data.alasan}
                error={reasonError ?? deleteForm.errors.alasan ?? (deleteForm.errors as Record<string, string | undefined>).renstra}
                busy={deleteForm.processing}
                confirmLabel="Hapus Renstra"
                showAuditHint={false}
                destructive
                onReasonChange={(reason) => deleteForm.setData('alasan', reason)}
                onClose={() => setDeleteOpen(false)}
                onConfirm={confirmDelete}
            />

            {/* Modal Pop-up Tambah Renstra Baru */}
            <Modal
                isOpen={createOpen}
                onClose={closeCreateModal}
                size="3xl"
                title="Tambah Master Renstra Baru"
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={closeCreateModal}
                            disabled={createForm.processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="form-tambah-renstra"
                            variant="primary"
                            disabled={createForm.processing}
                        >
                            Simpan Renstra
                        </Button>
                    </>
                }
            >
                <form id="form-tambah-renstra" onSubmit={handleCreateSubmit} noValidate>
                    <RenstraFormFields
                        data={createForm.data}
                        errors={createForm.errors as Record<string, string | undefined>}
                        regulasiOptions={regulasiPilihan}
                        disabled={createForm.processing}
                        canReadRegulasi={can.readRegulasi === true}
                        canUploadAttachment={can.uploadAttachment}
                        setField={(field, value) => createForm.setData((prev) => ({ ...prev, [field]: value }))}
                        setLampiran={(updater) => createForm.setData((prev) => ({ ...prev, lampiran: updater(prev.lampiran) }))}
                    />
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
