import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileText, Plus, Save, Search, X } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { CustomSelect, CustomSelectOption } from '@/Components/CustomSelect';
import { DeleteIconButton } from '@/Components/DeleteIconButton';
import { EditIconButton } from '@/Components/EditIconButton';
import { EmptyState } from '@/Components/EmptyState';
import { HoverScrollText } from '@/Components/HoverScrollText';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { Pagination } from '@/Components/Pagination';
import { RenstraFormFields } from '@/Components/RenstraFormFields';
import { ViewIconButton } from '@/Components/ViewIconButton';
import { RenstraEditModal } from './Partials/RenstraEditModal';
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

const statusFilterOptions: CustomSelectOption[] = [
    { value: 'draft', label: 'Draft' },
    { value: 'aktif', label: 'Aktif' },
    { value: 'nonaktif', label: 'Nonaktif' },
    { value: 'diarsipkan', label: 'Diarsipkan' },
];

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

    // State Modal Edit Renstra
    const [editOpen, setEditOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<RenstraSummary | null>(null);

    const openEditModal = (item: RenstraSummary) => {
        setEditingItem(item);
        setEditOpen(true);
    };

    const closeEditModal = () => {
        setEditOpen(false);
        setEditingItem(null);
    };

    const applyFilters = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/renstra', { q: query || undefined, status: status || undefined }, {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setQuery('');
        setStatus('');
        router.get('/renstra', {}, {
            preserveState: true,
            replace: true,
        });
    };

    const hasActiveFilters = Boolean(query || status);

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
            headerAction={
                can['renstra:create'] ? (
                    <Button
                        size="sm"
                        onClick={openCreateModal}
                        className="w-full sm:w-auto gap-1.5"
                    >
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Tambah Renstra
                    </Button>
                ) : null
            }
        >
            <Head title="Master Renstra" />

            <div className="mx-auto max-w-7xl space-y-4 mt-1.5 sm:mt-2">
                {/* Filter Toolbar */}
                <Card className="overflow-visible relative z-20">
                    <CardContent className="p-4 sm:p-5">
                        <form onSubmit={applyFilters} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="flex-1 min-w-0">
                                <Input
                                    name="q"
                                    label="Pencarian Dokumen"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Cari berdasarkan nama atau kode Renstra..."
                                />
                            </div>

                            <div className="w-full sm:w-56 min-w-0">
                                <CustomSelect
                                    id="status-filter"
                                    name="status"
                                    label="Status Dokumen"
                                    placeholder="Semua Status"
                                    emptyOptionLabel="Semua Status"
                                    options={statusFilterOptions}
                                    value={status}
                                    onChange={(val) => setStatus(String(val ?? ''))}
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

                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-ink">
                            <thead className="border-b border-border bg-soft text-xs font-semibold uppercase tracking-wider text-muted">
                                <tr>
                                    <th scope="col" className="px-5 py-3.5">Kode & Nama Renstra</th>
                                    <th scope="col" className="px-5 py-3.5">Periode Tahun</th>
                                    <th scope="col" className="px-5 py-3.5">Status</th>
                                    <th scope="col" className="px-5 py-3.5">Rujukan Regulasi</th>
                                    <th scope="col" className="px-5 py-3.5">Naskah Lampiran</th>
                                    <th scope="col" className="px-5 py-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {renstra.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="p-6">
                                            <EmptyState
                                                title="Tidak ada data Renstra ditemukan"
                                                description={hasActiveFilters ? "Coba sesuaikan kata kunci pencarian atau filter status dokumen." : "Belum ada dokumen Master Renstra yang tersimpan di sistem."}
                                                variant="inline"
                                            />
                                        </td>
                                    </tr>
                                ) : (
                                    renstra.data.map((item) => (
                                        <tr key={item.id} className="transition-colors hover:bg-soft/40">
                                            <td className="px-5 py-4 max-w-md">
                                                <Link
                                                    href={`/renstra/${item.id}`}
                                                    className="group block hover:text-primary transition-colors cursor-pointer"
                                                >
                                                    <HoverScrollText
                                                        text={item.nama}
                                                        className="font-semibold text-ink group-hover:text-primary transition-colors text-sm"
                                                        textClassName="font-semibold text-ink group-hover:text-primary transition-colors text-sm"
                                                    />
                                                    <div className="text-xs font-mono text-muted group-hover:text-primary/80 transition-colors mt-0.5">
                                                        {item.kode}
                                                    </div>
                                                </Link>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap font-mono text-sm">
                                                {item.tahun_mulai} - {item.tahun_selesai}
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <Badge variant={statusBadgeVariant[item.status]} dot>
                                                    {statusLabel[item.status]}
                                                </Badge>
                                            </td>
                                            <td className="px-5 py-4 text-xs text-muted">
                                                {item.regulasi?.nomor || item.regulasi_nomor ? (
                                                    <span className="font-medium text-ink">{item.regulasi?.nomor || item.regulasi_nomor}</span>
                                                ) : (
                                                    <span className="text-muted italic">Tidak ada</span>
                                                )}
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap text-xs text-muted">
                                                {item.berkas_count === null ? (
                                                    <span>Akses dibatasi</span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 font-medium text-ink">
                                                        <FileText className="h-3.5 w-3.5 text-muted" aria-hidden="true" />
                                                        {item.berkas_count} lampiran
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <ViewIconButton
                                                        href={`/renstra/${item.id}`}
                                                        label={`Lihat detail ${item.kode}`}
                                                        tooltip="Lihat Detail"
                                                        tooltipAlign="right"
                                                    />

                                                    {can['renstra:update'] && item.status !== 'diarsipkan' && (
                                                        <EditIconButton
                                                            onClick={() => openEditModal(item)}
                                                            label={`Edit ${item.kode}`}
                                                            tooltip="Edit Dokumen"
                                                            tooltipAlign="right"
                                                        />
                                                    )}

                                                    {item.can_delete && (
                                                        <DeleteIconButton
                                                            onClick={() => openDelete(item)}
                                                            label={`Hapus ${item.kode}`}
                                                            tooltip="Hapus Renstra"
                                                            tooltipAlign="right"
                                                        />
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <Pagination pagination={renstra} resourceName="Renstra" />
                </div>
            </div>

            <AuditReasonModal
                open={deleteOpen}
                title="Konfirmasi Hapus Renstra"
                description={`Apakah Anda yakin ingin menghapus data Renstra "${selected?.nama}" (${selected?.kode})? Tindakan ini akan dicatat dalam audit log.`}
                reason={deleteForm.data.alasan}
                error={reasonError ?? deleteForm.errors.alasan ?? (deleteForm.errors as Record<string, string | undefined>).renstra}
                busy={deleteForm.processing}
                confirmLabel="Hapus Renstra"
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
                title={
                    <div className="flex items-center gap-2.5">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 font-bold text-primary shrink-0">
                            <FileText className="h-4 w-4" />
                        </div>
                        <span>Tambah Master Renstra</span>
                    </div>
                }
                footer={
                    <div className="flex items-center justify-end gap-3 w-full">
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
                            isLoading={createForm.processing}
                            disabled={createForm.processing}
                        >
                            <Save className="h-4 w-4" aria-hidden="true" />
                            Simpan Renstra
                        </Button>
                    </div>
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

            {/* Modal Pop-up Edit Renstra */}
            <RenstraEditModal
                isOpen={editOpen}
                onClose={closeEditModal}
                renstra={editingItem}
                regulasiOptions={regulasiPilihan}
                canReadRegulasi={can.readRegulasi === true}
                canUploadAttachment={can.uploadAttachment}
            />
        </AuthenticatedLayout>
    );
}
