import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronDown, Edit3, Eye, FileText, Plus, Search, Trash2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { RenstraFormFields } from '@/Components/RenstraFormFields';
import { Select } from '@/Components/Select';
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

function cleanPaginationLabel(label: string): string {
    return label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Sebelumnya')
        .replace('Next', 'Berikutnya');
}

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
        <AuthenticatedLayout title="Master Renstra" breadcrumbs={[{ label: 'Master Renstra' }]}>
            <Head title="Master Renstra" />

            <div className="space-y-6">
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h1 className="text-xl font-bold tracking-tight text-ink sm:text-2xl leading-tight">
                                    Master Rencana Strategis
                                </h1>
                                <p className="mt-0.5 text-sm text-muted leading-snug">
                                    Penyusunan dokumen induk Renstra, penetapan periode, dasar rujukan regulasi, dan naskah digital.
                                </p>
                            </div>

                            {can['renstra:create'] && (
                                <button
                                    type="button"
                                    onClick={openCreateModal}
                                    className="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer"
                                >
                                    <Plus className="h-4 w-4" aria-hidden="true" />
                                    <span>Tambah Renstra</span>
                                </button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4 sm:p-5">
                        <form onSubmit={applyFilters} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="flex-1">
                                <label htmlFor="search-input" className="mb-1 block text-xs font-medium text-ink">
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
                                <label htmlFor="status-select" className="mb-1 block text-xs font-medium text-ink">
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
                                    <th scope="col" className="px-5 py-3.5 text-left">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {renstra.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-5 py-12 text-center text-sm font-medium text-muted">
                                            Tidak ada data Renstra ditemukan
                                        </td>
                                    </tr>
                                ) : (
                                    renstra.data.map((item) => (
                                        <tr key={item.id} className="transition-colors hover:bg-soft/40">
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-ink">{item.nama}</div>
                                                <div className="text-xs font-mono text-muted">{item.kode}</div>
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
                                                <span className="inline-flex items-center gap-1.5 font-medium text-ink">
                                                    <FileText className="h-3.5 w-3.5 text-muted" aria-hidden="true" />
                                                    {item.berkas_count} lampiran
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 whitespace-nowrap text-left">
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

                                                    {can['renstra:update'] && item.status !== 'diarsipkan' && (
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

                                                    {can['renstra:delete'] && item.status === 'draft' && (
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
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {renstra.links.length > 3 && (
                        <div className="flex items-center justify-between border-t border-border px-5 py-3 bg-page text-xs text-muted">
                            <div>
                                Menampilkan <span className="font-semibold text-ink">{renstra.from ?? 0}</span> sampai{' '}
                                <span className="font-semibold text-ink">{renstra.to ?? 0}</span> dari{' '}
                                <span className="font-semibold text-ink">{renstra.total}</span> data
                            </div>
                            <div className="flex gap-1">
                                {renstra.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url ?? '#'}
                                        preserveState
                                        className={`rounded-md px-2.5 py-1 font-medium transition-colors ${
                                            link.active
                                                ? 'bg-primary text-white'
                                                : link.url
                                                ? 'text-muted hover:bg-soft hover:text-ink'
                                                : 'cursor-not-allowed opacity-40'
                                        }`}
                                    >
                                        {cleanPaginationLabel(link.label)}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}
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
                title="Tambah Master Renstra Baru"
                description="Isi identitas dokumen induk Renstra, penetapan rentang tahun, rujukan regulasi, dan naskah digital."
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
                            isLoading={createForm.processing}
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
                        setField={(field, value) => createForm.setData({ ...createForm.data, [field]: value })}
                    />
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
