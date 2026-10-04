import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { RegulasiFailureNotice } from '@/Components/RegulasiFailureNotice';
import React, { useEffect, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { ExternalLink, FileText, Plus, Search, X } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { CustomSelect, type CustomSelectOption } from '@/Components/CustomSelect';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Pagination } from '@/Components/Pagination';
import { Badge } from '@/Components/Badge';
import { EmptyState } from '@/Components/EmptyState';
import { ViewIconButton, EditIconButton, DeleteIconButton } from '@/Components/IconButton';
import { RegulasiCreateModal } from '@/Pages/Regulasi/Partials/RegulasiCreateModal';
import { RegulasiEditModal } from '@/Pages/Regulasi/Partials/RegulasiEditModal';
import type { Paginated, RegulasiJenis, RegulasiSummary } from '@/types/regulasi';

interface RegulasiIndexProps {
    regulasi: Paginated<RegulasiSummary>;
    filters: {
        q: string;
        status: 'aktif' | 'nonaktif' | null;
        per_page?: number;
    };
    can: Record<string, boolean>;
}

const statusOptions: CustomSelectOption[] = [
    { value: 'aktif', label: 'Aktif' },
    { value: 'nonaktif', label: 'Nonaktif' },
];

const jenisLabel: Record<RegulasiJenis, string> = {
    kepmen: 'Keputusan Menteri',
    permen: 'Peraturan Menteri',
    perpres: 'Peraturan Presiden',
    keputusan_lainnya: 'Keputusan lainnya',
};

export default function RegulasiIndex({ regulasi, filters, can }: RegulasiIndexProps) {
    const recovery = useAuthRecovery();
    const [recoveryUnknown, setRecoveryUnknown] = useState(false);
    const [recoveryMessage, setRecoveryMessage] = useState('');
    const [query, setQuery] = useState(filters.q);
    const [status, setStatus] = useState(filters.status ?? '');

    useEffect(() => {
        setQuery(filters.q);
        setStatus(filters.status ?? '');
    }, [filters.q, filters.status]);
    const [selected, setSelected] = useState<RegulasiSummary | null>(null);
    const [editingRegulasi, setEditingRegulasi] = useState<RegulasiSummary | null>(null);
    const [createOpen, setCreateOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [reasonError, setReasonError] = useState<string | undefined>();
    const deleteForm = useForm({ alasan: '' });

    const applyFilters = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/regulasi', {
            q: query || undefined,
            status: status || undefined,
            per_page: filters.per_page || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setQuery('');
        setStatus('');
        router.get('/regulasi', {
            per_page: filters.per_page || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const confirmDelete = () => {
        if (!selected || deleteForm.processing || recovery.recovery || recoveryUnknown) return;
        if (deleteForm.data.alasan.trim().length < 10) {
            setReasonError('Jelaskan alasan penghapusan minimal 10 karakter.');
            return;
        }

        deleteForm.delete(`/regulasi/${selected.id}`, {
            preserveScroll: true,
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'delete', path: `/regulasi/${selected.id}`, mutation: true })) return false;
                setRecoveryMessage(response.status === 403 ? 'Akses ditolak. Hasil tindakan sebelumnya belum dapat dipastikan. Periksa akses dan data terbaru.' : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                setRecoveryUnknown(true);
                return false;
            },
            onCancel: () => { setRecoveryUnknown(true); setRecoveryMessage('Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); },
            onNetworkError: () => { setRecoveryUnknown(true); setRecoveryMessage('Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); return false; },
            onSuccess: () => {
                setDeleteOpen(false);
                setSelected(null);
                deleteForm.reset();
            },
        });
    };

    const openDelete = (item: RegulasiSummary) => {
        setDeleteOpen(true);
        // Hasil attempt dan alasan tetap melekat pada target asal selama recovery.
        if (recoveryUnknown || recovery.recovery) return;
        deleteForm.clearErrors();
        deleteForm.reset();
        setReasonError(undefined);
        setSelected(item);
    };

    const deleteError = reasonError
        ?? deleteForm.errors.alasan
        ?? (deleteForm.errors as Record<string, string | undefined>).regulasi;

    return (
        <AuthenticatedLayout
            title="Dasar Aturan"
            breadcrumbs={[{ label: 'Dasar Aturan' }]}
            headerAction={
                can['regulasi:create'] ? (
                    <Button
                        type="button"
                        onClick={() => setCreateOpen(true)}
                        variant="primary"
                        size="sm"
                        className="w-full sm:w-auto gap-1.5"
                    >
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Tambah dasar aturan
                    </Button>
                ) : null
            }
        >
            <Head title="Dasar Aturan" />

            <div className="mx-auto max-w-7xl space-y-4 mt-1.5 sm:mt-2">
                <Card className="overflow-visible">
                    <form onSubmit={applyFilters} className="grid gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-end">
                        <Input
                            name="q"
                            label="Cari regulasi"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Cari nomor atau pokok pengaturan…"
                        />
                        <CustomSelect
                            id="status-filter"
                            name="status"
                            label="Status"
                            options={statusOptions}
                            value={status}
                            onChange={(val) => setStatus(String(val) as 'aktif' | 'nonaktif' | '')}
                            emptyOptionLabel="Semua status"
                        />
                        <div className="flex items-center gap-2 shrink-0">
                            <Button type="submit" variant="primary" className="gap-1.5 px-4 shrink-0">
                                <Search className="h-4 w-4" aria-hidden="true" />
                                Filter
                            </Button>
                            {Boolean(query || status) && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={resetFilters}
                                    title="Reset filter"
                                    aria-label="Reset filter pencarian"
                                >
                                    <X className="h-4 w-4" aria-hidden="true" />
                                </Button>
                            )}
                        </div>
                    </form>
                </Card>

                {regulasi.data.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        variant="box"
                        title={query || status ? 'Tidak ada dasar aturan yang cocok' : 'Belum ada dasar aturan'}
                        description={
                            query || status
                                ? 'Coba ubah kata kunci atau filter status untuk menemukan regulasi yang dicari.'
                                : 'Tambahkan regulasi rujukan pertama untuk Renstra dan Indikator Kinerja.'
                        }
                        action={can['regulasi:create'] ? (
                            <Button
                                type="button"
                                onClick={() => setCreateOpen(true)}
                                variant="primary"
                                size="sm"
                                className="gap-1.5"
                            >
                                <Plus className="h-4 w-4" aria-hidden="true" />
                                Tambah dasar aturan
                            </Button>
                        ) : undefined}
                    />
                ) : (
                    <Card className="overflow-hidden">
                        <Table className="min-w-[780px]">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-48">Regulasi</TableHead>
                                    <TableHead>Tentang</TableHead>
                                    <TableHead className="w-32">Lampiran</TableHead>
                                    <TableHead className="w-28">Status</TableHead>
                                    <TableHead className="w-32 text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {regulasi.data.map((item) => (
                                    <TableRow key={item.id} className="align-top">
                                        <TableCell>
                                            <p className="font-semibold text-ink">{item.nomor}</p>
                                            <p className="mt-1 text-xs text-muted">{jenisLabel[item.jenis]} · {item.tahun}</p>
                                        </TableCell>
                                        <TableCell className="max-w-xl">
                                            <p className="line-clamp-2 leading-relaxed text-ink">{item.tentang}</p>
                                            {item.tautan_sumber && (
                                                <a
                                                    href={item.tautan_sumber}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                                                >
                                                    Sumber resmi <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
                                                </a>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-ink">{item.berkas_count} berkas</TableCell>
                                        <TableCell>
                                            <Badge variant={item.aktif ? 'success' : 'muted'}>
                                                {item.aktif ? 'Aktif' : 'Nonaktif'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-1">
                                                {can['regulasi:read'] && (
                                                    <ViewIconButton
                                                        href={`/regulasi/${item.id}`}
                                                        label={`Lihat regulasi ${item.nomor}`}
                                                        tooltip="Lihat regulasi"
                                                    />
                                                )}
                                                {can['regulasi:update'] && (
                                                    <EditIconButton
                                                        onClick={() => setEditingRegulasi(item)}
                                                        label={`Edit regulasi ${item.nomor}`}
                                                        tooltip="Edit regulasi"
                                                    />
                                                )}
                                                {can['regulasi:delete'] && (
                                                    <DeleteIconButton
                                                        onClick={() => openDelete(item)}
                                                        label={`Hapus regulasi ${item.nomor}`}
                                                        tooltip="Hapus regulasi"
                                                    />
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <Pagination
                            pagination={regulasi}
                            perPage={Number(filters?.per_page ?? regulasi.per_page ?? 10)}
                            resourceName="dasar aturan"
                        />
                    </Card>
                )}
            </div>

            <AuditReasonModal
                open={deleteOpen}
                title={recovery.recovery || recoveryUnknown ? 'Pemulihan penghapusan dasar aturan' : 'Hapus dasar aturan?'}
                description={selected ? `${selected.nomor}/${selected.tahun} akan dihapus. Aksi ditolak bila masih dirujuk Renstra atau Indikator aktif.` : ''}
                reason={deleteForm.data.alasan}
                error={deleteError}
                busy={deleteForm.processing}
                submitDisabled={Boolean(recovery.recovery) || recoveryUnknown}
                notice={<><AuthRecoveryNotice recovery={recovery.recovery} pending={deleteForm.processing} />{!recovery.recovery && <RegulasiFailureNotice message={recoveryMessage} />}</>}
                confirmLabel="Hapus dasar aturan"
                destructive
                confirmVariant="danger"
                onReasonChange={(value) => {
                    deleteForm.setData('alasan', value);
                    setReasonError(undefined);
                    deleteForm.clearErrors();
                }}
                onClose={() => !deleteForm.processing && setDeleteOpen(false)}
                onConfirm={confirmDelete}
            />

            {can['regulasi:create'] && (
                <RegulasiCreateModal
                    isOpen={createOpen}
                    onClose={() => setCreateOpen(false)}
                />
            )}

            {can['regulasi:update'] && (
                <RegulasiEditModal
                    isOpen={Boolean(editingRegulasi)}
                    onClose={() => setEditingRegulasi(null)}
                    regulasi={editingRegulasi}
                />
            )}
        </AuthenticatedLayout>
    );
}
