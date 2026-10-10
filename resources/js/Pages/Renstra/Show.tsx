import React, { useRef, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Lock, Trash2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { jenisRegulasiLabel } from '@/lib/regulasi';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Modal } from '@/Components/Modal';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { SharedPageProps } from '@/types/auth';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { BerkasLampiranItem } from '@/Components/BerkasLampiranItem';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import type { BerkasRenstra, RenstraDetail, RenstraStatus } from '@/types/renstra';

interface ShowRenstraProps {
    renstra: RenstraDetail;
    expected_state: string;
    lifecycle: { warnings: string[]; nonactivation_blocked: boolean };
    can?: {
        activate?: boolean;
        deactivate?: boolean;
        archive?: boolean;
        update?: boolean;
        delete?: boolean;
        deleteAttachment?: boolean;
    };
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

const transitions = {
    activate: { path: 'aktifkan', label: 'Aktifkan Renstra', description: 'Aktifkan dokumen ini sebagai Renstra yang berlaku. Naskah lampiran akan terkunci.' },
    deactivate: { path: 'nonaktifkan', label: 'Nonaktifkan Renstra', description: '' },
    archive: { path: 'arsipkan', label: 'Arsipkan Renstra', description: 'Arsipkan dokumen nonaktif ini. Pengarsipan bersifat final; seluruh histori tetap terbaca.' },
};
type StatusIntent = keyof typeof transitions;

export default function ShowRenstra(props: ShowRenstraProps) {
    return <RenstraDetailPage key={props.renstra.id} {...props} />;
}

function RenstraDetailPage({ renstra, expected_state, lifecycle, can = {} }: ShowRenstraProps) {
    const isAktif = renstra.status === 'aktif';
    const berkasList = renstra.berkas ?? [];
    const pembuatNama = renstra.pembuat?.nama ?? 'Sistem';
    const [intent, setIntent] = useState<StatusIntent | null>(null);
    const [statusFailure, setStatusFailure] = useState('');
    const submitting = useRef(false);
    const recovery = useAuthRecovery();
    const statusForm = useForm({ expected_state });
    const statusErrors: Record<string, string | undefined> = statusForm.errors;
    const statusBlocked = Boolean(recovery.recovery || statusFailure || statusErrors.expected_state);
    const unknownOutcome = () => setStatusFailure('Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
    const statusNotice = <>
        <AuthRecoveryNotice recovery={recovery.recovery} pending={statusForm.processing} />
        {!recovery.recovery && (statusFailure || statusErrors.expected_state) && <div role="alert" className="space-y-2 rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm">
            <p>{statusFailure || statusErrors.expected_state}</p>
            <Button type="button" variant="outline" disabled={statusForm.processing} onClick={() => router.get(`/renstra/${renstra.id}`, {}, { preserveState: false })}>Muat data terbaru</Button>
        </div>}
    </>;
    const openStatus = (next: StatusIntent) => {
        if (statusForm.processing || submitting.current || statusBlocked) return;
        statusForm.clearErrors();
        statusForm.setData('expected_state', expected_state);
        setIntent(next);
    };
    const confirmStatus = () => {
        if (!intent || submitting.current || statusForm.processing || statusBlocked) return;
        submitting.current = true;
        const path = `/renstra/${renstra.id}/${transitions[intent].path}`;
        statusForm.post(path, {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash as SharedPageProps['flash'];
                const error = page.flash.error ?? flash?.error;
                if (error) { setStatusFailure(error); return; }
                setIntent(null);
            },
            onHttpException: (response) => {
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) {
                    setStatusFailure(response.status === 403 ? 'Akses perubahan status ditolak. Periksa akses dan data terbaru.' : 'Perubahan status belum terkonfirmasi. Periksa data terbaru sebelum mencoba kembali.');
                }
                return false;
            },
            onNetworkError: () => { unknownOutcome(); return false; },
            onCancel: unknownOutcome,
            onFinish: () => { submitting.current = false; },
        });
    };
    const [selectedBerkas, setSelectedBerkas] = useState<BerkasRenstra | null>(null);
    const [deleteBerkasOpen, setDeleteBerkasOpen] = useState(false);
    const [reasonError, setReasonError] = useState<string | undefined>();
    const deleteBerkasForm = useForm({ alasan: '' });

    const [deleteRenstraOpen, setDeleteRenstraOpen] = useState(false);
    const [renstraReasonError, setRenstraReasonError] = useState<string | undefined>();
    const deleteRenstraForm = useForm({ alasan: '' });

    const openDeleteBerkas = (berkas: BerkasRenstra) => {
        setSelectedBerkas(berkas);
        deleteBerkasForm.reset();
        setReasonError(undefined);
        setDeleteBerkasOpen(true);
    };

    const confirmDeleteBerkas = () => {
        if (!selectedBerkas || deleteBerkasForm.processing) return;
        if (deleteBerkasForm.data.alasan.trim().length < 5) {
            setReasonError('Jelaskan alasan penghapusan minimal 5 karakter.');
            return;
        }

        deleteBerkasForm.delete(`/renstra/${renstra.id}/berkas/${selectedBerkas.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteBerkasOpen(false);
                setSelectedBerkas(null);
                deleteBerkasForm.reset();
            },
        });
    };

    const confirmDeleteRenstra = () => {
        if (deleteRenstraForm.processing) return;
        if (deleteRenstraForm.data.alasan.trim().length < 5) {
            setRenstraReasonError('Jelaskan alasan penghapusan minimal 5 karakter.');
            return;
        }

        deleteRenstraForm.delete(`/renstra/${renstra.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteRenstraOpen(false);
                deleteRenstraForm.reset();
            },
        });
    };

    return (
        <AuthenticatedLayout
            title="Detail Renstra"
            breadcrumbs={[
                { label: 'Master Renstra', href: '/renstra' },
                { label: renstra.kode },
            ]}
        >
            <Head title={`Detail Renstra: ${renstra.kode}`} />

            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link href="/renstra" className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        Kembali ke daftar
                    </Link>

                    <div className="flex flex-wrap items-center gap-2">
                        {can.update && (
                            <Link
                                href={`/renstra/${renstra.id}/edit`}
                                className="inline-flex h-9 items-center justify-center rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25"
                            >
                                Edit Dokumen
                            </Link>
                        )}

                        {can.delete && renstra.status === 'draft' && (berkasList.length === 0 || can.deleteAttachment) && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    deleteRenstraForm.reset();
                                    setRenstraReasonError(undefined);
                                    setDeleteRenstraOpen(true);
                                }}
                                className="border-danger/30 text-danger hover:bg-danger/10 hover:text-danger"
                            >
                                Hapus Renstra
                            </Button>
                        )}

                        {(['activate', 'deactivate', 'archive'] as const).map((action) => can[action] && <Button
                            key={action} type="button" size="sm" variant={action === 'activate' ? 'primary' : 'outline'}
                            className={action === 'deactivate' ? 'border-danger/30 text-danger hover:bg-danger/10 hover:text-danger' : undefined}
                            disabled={statusForm.processing || statusBlocked || (action === 'deactivate' && lifecycle.nonactivation_blocked)}
                            onClick={() => openStatus(action)}>
                            {transitions[action].label}
                        </Button>)}
                    </div>
                </div>

                {!intent && statusNotice}
                {can.deactivate && lifecycle.nonactivation_blocked && <p role="status" className="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-ink">Tutup seluruh Jadwal aktif sebelum menonaktifkan Renstra.</p>}
                {can.activate && lifecycle.warnings.length > 0 && <div role="status" className="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-ink">
                    {lifecycle.warnings.map((warning) => <p key={warning}>{warning}</p>)}
                </div>}
                {['nonaktif', 'diarsipkan'].includes(renstra.status) && <p className="rounded-lg border border-border bg-soft p-4 text-sm text-muted">Dokumen ini hanya dapat dibaca. Jadwal dan data historis tetap utuh.</p>}

                <Card>
                    <CardHeader className="items-start gap-4">
                        <div className="min-w-0">
                            <CardTitle>{renstra.nama}</CardTitle>
                            <p className="mt-1 font-mono text-sm text-muted">{renstra.kode}</p>
                        </div>
                        <Badge variant={statusBadgeVariant[renstra.status]} dot className="shrink-0">
                            {statusLabel[renstra.status]}
                        </Badge>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                            <div>
                                <dt className="text-xs font-semibold text-muted">Periode pelaksanaan</dt>
                                <dd className="mt-1 font-mono text-sm text-ink">{renstra.tahun_mulai}–{renstra.tahun_selesai}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold text-muted">Pembuat</dt>
                                <dd className="mt-1 text-sm text-ink">{pembuatNama}</dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-xs font-semibold text-muted">Deskripsi</dt>
                                <dd className="mt-1 whitespace-pre-wrap text-sm leading-6 text-ink">{renstra.deskripsi || 'Tidak ada deskripsi.'}</dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-xs font-semibold text-muted">Uraian dasar hukum</dt>
                                <dd className="mt-1 whitespace-pre-wrap text-sm leading-6 text-ink">{renstra.dasar_hukum || 'Tidak dicantumkan.'}</dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-xs font-semibold text-muted">Rujukan regulasi</dt>
                                <dd className="mt-1">
                                    {renstra.regulasi ? (
                                        <div className="space-y-1">
                                            <p className="text-sm font-semibold text-ink">{renstra.regulasi.nomor} ({renstra.regulasi.tahun})</p>
                                            <p className="text-xs text-muted">{jenisRegulasiLabel[renstra.regulasi.jenis]}</p>
                                            <p className="text-sm leading-6 text-ink">{renstra.regulasi.tentang}</p>
                                            <Link href={`/regulasi/${renstra.regulasi.id}`} className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                                                Buka dasar aturan <ExternalLink className="h-4 w-4" aria-hidden="true" />
                                            </Link>
                                        </div>
                                    ) : (
                                        <span className="text-sm text-muted">Tidak dicantumkan</span>
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Lampiran dokumen</CardTitle>
                        {isAktif && (
                            <span className="inline-flex items-center gap-1.5 text-xs text-muted">
                                <Lock className="h-3.5 w-3.5" aria-hidden="true" />
                                Terkunci selama Renstra aktif
                            </span>
                        )}
                    </CardHeader>
                    <CardContent>
                        {berkasList.length > 0 ? (
                            <ul className="space-y-3">
                                {berkasList.map((item) => (
                                    <BerkasLampiranItem
                                        key={item.id}
                                        berkas={item}
                                        action={can.deleteAttachment && renstra.status === 'draft' && (
                                            <button
                                                type="button"
                                                onClick={() => openDeleteBerkas(item)}
                                                className="rounded-md p-1.5 text-muted transition-colors hover:bg-danger/10 hover:text-danger focus:outline-none focus:ring-2 focus:ring-danger/20"
                                                aria-label={`Hapus lampiran ${item.nama_asli ?? item.mode}`}
                                            >
                                                <Trash2 className="h-4 w-4" aria-hidden="true" />
                                            </button>
                                        )}
                                    />
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm leading-6 text-muted">Belum ada lampiran dokumen pada Renstra ini.</p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Modal isOpen={intent !== null} title={intent ? transitions[intent].label : ''}
                description={intent ? transitions[intent].description : ''}
                onClose={() => { if (!statusForm.processing) setIntent(null); }}
                showCloseButton={!statusForm.processing}
                footer={<>
                    <Button type="button" variant="outline" disabled={statusForm.processing} onClick={() => setIntent(null)}>Batal</Button>
                    <Button type="button" variant={intent === 'deactivate' ? 'danger' : 'primary'} isLoading={statusForm.processing} disabled={statusBlocked} onClick={confirmStatus}>Konfirmasi</Button>
                </>}>
                <div className="space-y-3 text-sm text-ink">
                    <p>{renstra.nama} ({renstra.tahun_mulai}–{renstra.tahun_selesai})</p>
                    <p>Tindakan ini dicatat dalam audit. Jadwal dan data historis tetap utuh.</p>
                    {intent === 'activate' && lifecycle.warnings.map((warning) => <p key={warning} className="text-warning-dark">{warning}</p>)}
                    {statusNotice}
                    {Object.entries(statusErrors).filter(([field]) => field !== 'expected_state').map(([field, error]) => <p key={field} role="alert" className="text-danger">{error}</p>)}
                </div>
            </Modal>

            <AuditReasonModal
                open={deleteBerkasOpen}
                title="Hapus Lampiran Renstra"
                description={`Apakah Anda yakin ingin menghapus lampiran "${selectedBerkas?.nama_asli ?? selectedBerkas?.mode}"? Tindakan ini dicatat dalam audit log.`}
                reason={deleteBerkasForm.data.alasan}
                error={reasonError ?? deleteBerkasForm.errors.alasan ?? (deleteBerkasForm.errors as Record<string, string | undefined>).berkas}
                busy={deleteBerkasForm.processing}
                confirmLabel="Hapus Lampiran"
                destructive
                onReasonChange={(reason) => deleteBerkasForm.setData('alasan', reason)}
                onClose={() => setDeleteBerkasOpen(false)}
                onConfirm={confirmDeleteBerkas}
            />

            <AuditReasonModal
                open={deleteRenstraOpen}
                title="Hapus Dokumen Renstra"
                description={`Apakah Anda yakin ingin menghapus dokumen Renstra "${renstra.nama}" (${renstra.kode})? Tindakan ini dicatat dalam audit log.`}
                reason={deleteRenstraForm.data.alasan}
                error={renstraReasonError ?? deleteRenstraForm.errors.alasan ?? (deleteRenstraForm.errors as Record<string, string | undefined>).renstra}
                busy={deleteRenstraForm.processing}
                confirmLabel="Hapus Renstra"
                showAuditHint={false}
                destructive
                onReasonChange={(reason) => deleteRenstraForm.setData('alasan', reason)}
                onClose={() => setDeleteRenstraOpen(false)}
                onConfirm={confirmDeleteRenstra}
            />
        </AuthenticatedLayout>
    );
}
