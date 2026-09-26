import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { RegulasiFailureNotice } from '@/Components/RegulasiFailureNotice';
import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Edit3, ExternalLink, Eye, FileText, Plus, Search, Trash2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { RegulasiCreateModal } from '@/Pages/Regulasi/Partials/RegulasiCreateModal';
import type { Paginated, RegulasiJenis, RegulasiSummary } from '@/types/regulasi';

interface RegulasiIndexProps {
    regulasi: Paginated<RegulasiSummary>;
    filters: {
        q: string;
        status: 'aktif' | 'nonaktif' | null;
    };
    can: Record<string, boolean>;
}

const jenisLabel: Record<RegulasiJenis, string> = {
    kepmen: 'Keputusan Menteri',
    permen: 'Peraturan Menteri',
    perpres: 'Peraturan Presiden',
    keputusan_lainnya: 'Keputusan lainnya',
};

function cleanPaginationLabel(label: string): string {
    return label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace('Previous', 'Sebelumnya')
        .replace('Next', 'Berikutnya');
}

export default function RegulasiIndex({ regulasi, filters, can }: RegulasiIndexProps) {
    const recovery = useAuthRecovery();
    const [recoveryUnknown, setRecoveryUnknown] = useState(false);
    const [recoveryMessage, setRecoveryMessage] = useState('');
    const [query, setQuery] = useState(filters.q);
    const [status, setStatus] = useState(filters.status ?? '');
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
        <AuthenticatedLayout title="Dasar Aturan" breadcrumbs={[{ label: 'Dasar Aturan' }]}>
            <Head title="Dasar Aturan" />

            <div className="space-y-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="max-w-3xl">
                        <h2 className="text-base font-semibold text-ink">Katalog regulasi dan dokumen sumber</h2>
                        <p className="mt-1 text-sm leading-6 text-muted">
                            Kelola dasar hukum yang dapat dirujuk oleh Renstra dan Indikator. File tersimpan privat dan setiap perubahan sensitif dicatat pada audit log.
                        </p>
                    </div>
                    {can['regulasi:create'] && (
                        <Button
                            type="button"
                            onClick={() => setCreateOpen(true)}
                            variant="primary"
                            className="gap-2"
                        >
                            <Plus className="h-4 w-4" aria-hidden="true" />
                            Tambah dasar aturan
                        </Button>
                    )}
                </div>

                <Card className="overflow-visible">
                    <form onSubmit={applyFilters} className="grid gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-end">
                        <Input
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
                                <button
                                    type="button"
                                    onClick={() => setCreateOpen(true)}
                                    className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline cursor-pointer"
                                >
                                    <Plus className="h-4 w-4" aria-hidden="true" /> Tambah dasar aturan
                                </button>
                            )}
                        </div>
                    </Card>
                ) : (
                    <>
                        <Card className="hidden md:block">
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[780px] text-left text-sm">
                                    <thead className="border-b border-border bg-page text-xs font-semibold text-ink">
                                        <tr>
                                            <th className="px-5 py-3.5">Regulasi</th>
                                            <th className="px-5 py-3.5">Tentang</th>
                                            <th className="px-5 py-3.5">Lampiran</th>
                                            <th className="px-5 py-3.5">Status</th>
                                            <th className="px-5 py-3.5 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {regulasi.data.map((item) => (
                                            <tr key={item.id} className="align-top transition-colors hover:bg-page">
                                                <td className="px-5 py-4">
                                                    <p className="font-semibold text-ink">{item.nomor}</p>
                                                    <p className="mt-1 text-xs text-muted">{jenisLabel[item.jenis]} · {item.tahun}</p>
                                                </td>
                                                <td className="max-w-xl px-5 py-4">
                                                    <p className="line-clamp-2 leading-6 text-ink">{item.tentang}</p>
                                                    {item.tautan_sumber && (
                                                        <a href={item.tautan_sumber} target="_blank" rel="noreferrer" className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                                                            Sumber resmi <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
                                                        </a>
                                                    )}
                                                </td>
                                                <td className="px-5 py-4 text-ink">{item.berkas_count} berkas</td>
                                                <td className="px-5 py-4">
                                                    <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${item.aktif ? 'bg-success/10 text-success' : 'bg-soft text-muted'}`}>
                                                        {item.aktif ? 'Aktif' : 'Nonaktif'}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-4">
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
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>

                        <ul className="space-y-3 md:hidden">
                            {regulasi.data.map((item) => (
                                <li key={item.id} className="rounded-xl border border-border bg-surface p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="font-semibold text-ink">{item.nomor}</p>
                                            <p className="mt-1 text-xs text-muted">{jenisLabel[item.jenis]} · {item.tahun}</p>
                                        </div>
                                        <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${item.aktif ? 'bg-success/10 text-success' : 'bg-soft text-muted'}`}>
                                            {item.aktif ? 'Aktif' : 'Nonaktif'}
                                        </span>
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

                {regulasi.last_page > 1 && (
                    <nav aria-label="Paginasi regulasi" className="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <p className="text-muted">Menampilkan {regulasi.from}–{regulasi.to} dari {regulasi.total} data</p>
                        <div className="flex flex-wrap gap-1">
                            {regulasi.links.map((link, index) => (
                                link.url ? (
                                    <Link
                                        key={`${link.label}-${index}`}
                                        href={link.url}
                                        preserveScroll
                                        className={`rounded-lg px-3 py-2 text-sm font-semibold transition-colors ${link.active ? 'bg-primary text-white' : 'border border-border bg-surface text-ink hover:bg-soft'}`}
                                    >
                                        {cleanPaginationLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span key={`${link.label}-${index}`} className="rounded-lg border border-border px-3 py-2 text-sm text-muted opacity-60">
                                        {cleanPaginationLabel(link.label)}
                                    </span>
                                )
                            ))}
                        </div>
                    </nav>
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
