import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { RegulasiFailureNotice } from '@/Components/RegulasiFailureNotice';
import React, { useEffect, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Edit3, ExternalLink, Eye, FileText, Plus, Search, Trash2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { jenisRegulasiLabel } from '@/lib/regulasi';
import { tautanAman } from '@/lib/url';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Pagination } from '@/Components/Pagination';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { RegulasiCreateModal } from '@/Pages/Regulasi/Partials/RegulasiCreateModal';
import type { Paginated, RegulasiSummary } from '@/types/regulasi';

interface RegulasiIndexProps {
    regulasi: Paginated<RegulasiSummary>;
    filters: {
        q: string;
        status: 'aktif' | 'nonaktif' | null;
    };
    can: Record<string, boolean>;
}

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
    const [createOpen, setCreateOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [reasonError, setReasonError] = useState<string | undefined>();
    const deleteForm = useForm({ alasan: '' });

    const applyFilters = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/regulasi', { q: query || undefined, status: status || undefined }, {
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
            headerActions={can['regulasi:create'] && (
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
            )}
        >
            <Head title="Dasar Aturan" />

            <div className="space-y-5">
                <Card className="overflow-visible">
                    <form onSubmit={applyFilters} className="grid gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-end">
                        <Input
                            name="q"
                            label="Cari regulasi"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Cari nomor atau pokok pengaturan…"
                        />
                        <Select label="Status" value={status} onChange={(event) => setStatus(event.target.value)}>
                            <option value="">Semua status</option>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </Select>
                        <Button type="submit" variant="outline">
                            <Search className="h-4 w-4" aria-hidden="true" />
                            Terapkan
                        </Button>
                    </form>
                </Card>

                {regulasi.data.length === 0 ? (
                    <Card>
                        <div className="px-6 py-14 text-center">
                            <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <FileText className="h-6 w-6" aria-hidden="true" />
                            </span>
                            <h3 className="mt-4 text-base font-semibold text-ink">Belum ada dasar aturan yang sesuai</h3>
                            <p className="mx-auto mt-1 max-w-md text-sm leading-6 text-muted">
                                Ubah kata pencarian atau tambahkan regulasi pertama agar Renstra dan Indikator memiliki rujukan hukum terstruktur.
                            </p>
                            {can['regulasi:create'] && (
                                <Button type="button" variant="outline" size="sm" onClick={() => setCreateOpen(true)} className="mt-4 gap-1.5">
                                    <Plus className="h-4 w-4" aria-hidden="true" /> Tambah dasar aturan
                                </Button>
                            )}
                        </div>
                    </Card>
                ) : (
                    <>
                        <Card className="hidden md:block">
                            <Table className="min-w-[780px]">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Regulasi</TableHead>
                                        <TableHead>Tentang</TableHead>
                                        <TableHead>Lampiran</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Aksi</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {regulasi.data.map((item) => (
                                        <TableRow key={item.id} className="align-top">
                                            <TableCell>
                                                <p className="text-sm font-semibold text-ink">{item.nomor}</p>
                                                <p className="mt-1 text-xs text-muted">{jenisRegulasiLabel[item.jenis]} · {item.tahun}</p>
                                            </TableCell>
                                            <TableCell className="max-w-xl">
                                                <p className="line-clamp-2 text-sm leading-6 text-ink">{item.tentang}</p>
                                                {tautanAman(item.tautan_sumber) && (
                                                    <a href={tautanAman(item.tautan_sumber) ?? undefined} target="_blank" rel="noreferrer" className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                                                        Sumber resmi <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
                                                    </a>
                                                )}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-sm">{item.berkas_count} berkas</TableCell>
                                            <TableCell>
                                                <Badge variant={item.aktif ? 'success' : 'muted'}>{item.aktif ? 'Aktif' : 'Nonaktif'}</Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex justify-end gap-1">
                                                    {can['regulasi:read'] && (
                                                        <Link href={`/regulasi/${item.id}`} className="rounded-lg p-2 text-muted transition-colors hover:bg-primary/10 hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20" aria-label={`Lihat regulasi ${item.nomor}`}>
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    )}
                                                    {can['regulasi:update'] && (
                                                        <Link href={`/regulasi/${item.id}/edit`} className="rounded-lg p-2 text-muted transition-colors hover:bg-primary/10 hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20" aria-label={`Edit regulasi ${item.nomor}`}>
                                                            <Edit3 className="h-4 w-4" />
                                                        </Link>
                                                    )}
                                                    {can['regulasi:delete'] && (
                                                        <button type="button" onClick={() => openDelete(item)} className="rounded-lg p-2 text-muted transition-colors hover:bg-danger/10 hover:text-danger focus:outline-none focus:ring-2 focus:ring-danger/20" aria-label={`Hapus regulasi ${item.nomor}`}>
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>

                        <ul className="space-y-3 md:hidden">
                            {regulasi.data.map((item) => (
                                <li key={item.id} className="rounded-xl border border-border bg-surface p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="font-semibold text-ink">{item.nomor}</p>
                                            <p className="mt-1 text-xs text-muted">{jenisRegulasiLabel[item.jenis]} · {item.tahun}</p>
                                        </div>
                                        <Badge variant={item.aktif ? 'success' : 'muted'} className="shrink-0">{item.aktif ? 'Aktif' : 'Nonaktif'}</Badge>
                                    </div>
                                    <p className="mt-3 text-sm leading-6 text-ink">{item.tentang}</p>
                                    <div className="mt-4 flex items-center justify-between border-t border-border pt-3">
                                        <span className="text-xs text-muted">{item.berkas_count} lampiran</span>
                                        <div className="flex gap-2">
                                            {can['regulasi:read'] && <Link href={`/regulasi/${item.id}`} className="text-sm font-semibold text-primary">Lihat</Link>}
                                            {can['regulasi:update'] && <Link href={`/regulasi/${item.id}/edit`} className="text-sm font-semibold text-primary">Edit</Link>}
                                            {can['regulasi:delete'] && <button type="button" onClick={() => openDelete(item)} className="text-sm font-semibold text-danger">Hapus</button>}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                <Pagination links={regulasi.links} from={regulasi.from} to={regulasi.to} total={regulasi.total} ariaLabel="Paginasi regulasi" />
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
        </AuthenticatedLayout>
    );
}
