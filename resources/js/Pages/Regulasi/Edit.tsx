import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { RegulasiFailureNotice } from '@/Components/RegulasiFailureNotice';
import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Download, ExternalLink, FileText, Save, Trash2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { RegulasiFormFields } from '@/Components/RegulasiFormFields';
import type { BerkasRegulasi, RegulasiDetail, RegulasiFormData } from '@/types/regulasi';

interface EditRegulasiProps {
    regulasi: RegulasiDetail;
    can: Record<string, boolean>;
}

function formatBytes(value: number | null): string {
    if (value === null) return '';
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

interface ExistingAttachmentProps {
    attachment: BerkasRegulasi;
    canDelete: boolean;
    onDelete: (attachment: BerkasRegulasi) => void;
}

function ExistingAttachment({ attachment, canDelete, onDelete }: ExistingAttachmentProps) {
    return (
        <li className="rounded-lg border border-border bg-page px-4 py-3">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <div className="flex items-center gap-2 text-sm font-semibold text-ink">
                        <FileText className="h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
                        {attachment.mode === 'file' ? attachment.nama_asli : `Lampiran ${attachment.mode}`}
                    </div>
                    {attachment.mode === 'file' && (
                        <p className="mt-1 text-xs text-muted">{attachment.mime} · {formatBytes(attachment.ukuran_bytes)}</p>
                    )}
                    {attachment.mode === 'teks' && (
                        <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-ink">{attachment.isi_teks}</p>
                    )}
                </div>
                <div className="flex shrink-0 flex-wrap items-center gap-3">
                    {attachment.mode === 'file' && attachment.download_url && (
                        <a href={attachment.download_url} className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                            <Download className="h-4 w-4" aria-hidden="true" /> Unduh
                        </a>
                    )}
                    {attachment.mode === 'tautan' && attachment.tautan && (
                        <a href={attachment.tautan} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                            <ExternalLink className="h-4 w-4" aria-hidden="true" /> Buka tautan
                        </a>
                    )}
                    {canDelete && (
                        <button
                            type="button"
                            onClick={() => onDelete(attachment)}
                            className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-semibold text-danger transition-colors hover:bg-danger/10 focus:outline-none focus:ring-2 focus:ring-danger/20"
                        >
                            <Trash2 className="h-4 w-4" aria-hidden="true" /> Hapus
                        </button>
                    )}
                </div>
            </div>
        </li>
    );
}

export default function EditRegulasi({ regulasi, can }: EditRegulasiProps) {
    const recovery = useAuthRecovery();
    const [recoveryUnknown, setRecoveryUnknown] = useState(false);
    const [recoveryMessage, setRecoveryMessage] = useState('');
    const attachmentRecovery = useAuthRecovery();
    const [attachmentRecoveryUnknown, setAttachmentRecoveryUnknown] = useState(false);
    const [attachmentRecoveryMessage, setAttachmentRecoveryMessage] = useState('');
    const [auditOpen, setAuditOpen] = useState(false);
    const [reasonError, setReasonError] = useState<string | undefined>();
    const [selectedAttachment, setSelectedAttachment] = useState<BerkasRegulasi | null>(null);
    const [attachmentDeleteOpen, setAttachmentDeleteOpen] = useState(false);
    const [attachmentReasonError, setAttachmentReasonError] = useState<string | undefined>();
    const form = useForm<RegulasiFormData>({
        jenis: regulasi.jenis,
        nomor: regulasi.nomor,
        tahun: String(regulasi.tahun),
        tentang: regulasi.tentang,
        tanggal: regulasi.tanggal ?? '',
        tautan_sumber: regulasi.tautan_sumber ?? '',
        catatan: regulasi.catatan ?? '',
        aktif: regulasi.aktif,
        versi: regulasi.versi,
        alasan: '',
        lampiran: [],
        _method: 'put',
    });
    const saveRecovery = recovery.recovery ?? (attachmentRecovery.recovery ? { ...attachmentRecovery.recovery, outcome: 'unknown' as const } : null);
    const deleteRecovery = attachmentRecovery.recovery ?? (recovery.recovery ? { ...recovery.recovery, outcome: 'unknown' as const } : null);
    const deleteAttachmentForm = useForm({ alasan: '' });

    const requestAuditReason = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setReasonError(undefined);
        setAuditOpen(true);
    };

    const submit = () => {
        if (form.processing || recovery.recovery || attachmentRecovery.recovery || recoveryUnknown) return;
        if (form.data.alasan.trim().length < 10) {
            setReasonError('Jelaskan alasan perubahan minimal 10 karakter.');
            return;
        }

        form.post(`/regulasi/${regulasi.id}`, {
            forceFormData: true,
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'put', path: `/regulasi/${regulasi.id}`, mutation: true })) return false;
                setRecoveryMessage(response.status === 403 ? 'Akses ditolak. Hasil tindakan sebelumnya belum dapat dipastikan. Periksa akses dan data terbaru.' : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                setRecoveryUnknown(true);
                return false;
            },
            onCancel: () => { setRecoveryUnknown(true); setRecoveryMessage('Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); },
            onNetworkError: () => { setRecoveryUnknown(true); setRecoveryMessage('Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); return false; },
            onError: (errors) => {
                if (!errors.alasan) setAuditOpen(false);
            },
        });
    };

    const openAttachmentDelete = (attachment: BerkasRegulasi) => {
        setAttachmentDeleteOpen(true);
        // Hasil attempt dan alasan tetap melekat pada lampiran asal selama recovery.
        if (attachmentRecoveryUnknown || attachmentRecovery.recovery) return;
        deleteAttachmentForm.reset();
        deleteAttachmentForm.clearErrors();
        setAttachmentReasonError(undefined);
        setSelectedAttachment(attachment);
    };

    const deleteAttachment = () => {
        if (!selectedAttachment || deleteAttachmentForm.processing || recovery.recovery || attachmentRecovery.recovery || attachmentRecoveryUnknown) return;

        if (deleteAttachmentForm.data.alasan.trim().length < 10) {
            setAttachmentReasonError('Jelaskan alasan penghapusan minimal 10 karakter.');
            return;
        }

        deleteAttachmentForm.delete(`/regulasi/${regulasi.id}/berkas/${selectedAttachment.id}`, {
            preserveScroll: true,
            onHttpException: (response) => {
                if (attachmentRecovery.handleHttpException(response, { effectiveMethod: 'delete', path: `/regulasi/${regulasi.id}/berkas/${selectedAttachment.id}`, mutation: true })) return false;
                setAttachmentRecoveryMessage(response.status === 403 ? 'Akses ditolak. Hasil tindakan sebelumnya belum dapat dipastikan. Periksa akses dan data terbaru.' : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                setAttachmentRecoveryUnknown(true);
                return false;
            },
            onCancel: () => { setAttachmentRecoveryUnknown(true); setAttachmentRecoveryMessage('Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); },
            onNetworkError: () => { setAttachmentRecoveryUnknown(true); setAttachmentRecoveryMessage('Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); return false; },
            onSuccess: () => {
                setAttachmentDeleteOpen(false);
                setSelectedAttachment(null);
                deleteAttachmentForm.reset();
            },
        });
    };

    const attachmentDeleteError = attachmentReasonError
        ?? deleteAttachmentForm.errors.alasan
        ?? (deleteAttachmentForm.errors as Record<string, string | undefined>).berkas;
    const attachmentLabel = selectedAttachment?.mode === 'file'
        ? selectedAttachment.nama_asli
        : selectedAttachment?.mode === 'tautan'
            ? selectedAttachment.tautan
            : selectedAttachment?.isi_teks;

    return (
        <AuthenticatedLayout
            title="Edit Dasar Aturan"
            breadcrumbs={[{ label: 'Dasar Aturan', href: '/regulasi' }, { label: `${regulasi.nomor}/${regulasi.tahun}` }]}
        >
            <Head title={`Edit ${regulasi.nomor}`} />

            <div className="mx-auto max-w-5xl space-y-5">
                <Link href="/regulasi" className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke daftar
                </Link>

                {regulasi.berkas.length > 0 && (
                    <Card>
                        <CardHeader><CardTitle>Lampiran tersimpan</CardTitle></CardHeader>
                        <CardContent>
                            <ul className="space-y-3">
                                {regulasi.berkas.map((attachment) => (
                                    <ExistingAttachment
                                        key={attachment.id}
                                        attachment={attachment}
                                        canDelete={can['berkas:delete'] === true}
                                        onDelete={openAttachmentDelete}
                                    />
                                ))}
                            </ul>
                            <p className="mt-4 text-xs leading-5 text-muted">
                                Penghapusan lampiran memerlukan alasan audit dan akan ditolak bila regulasi masih dirujuk oleh Renstra atau Indikator aktif.
                            </p>
                        </CardContent>
                    </Card>
                )}

                <form onSubmit={requestAuditReason} noValidate>
                    <Card>
                        <CardContent>
                            <RegulasiFormFields
                                data={form.data}
                                errors={form.errors as Record<string, string | undefined>}
                                disabled={form.processing}
                                setField={(field, value) => form.setData({ ...form.data, [field]: value })}
                            />
                        </CardContent>
                        <div className="flex flex-col-reverse gap-3 border-t border-border bg-page px-6 py-4 sm:flex-row sm:justify-end">
                            <Link
                                href="/regulasi"
                                className="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-4 py-2 text-sm font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/20"
                            >
                                Batal
                            </Link>
                            <Button type="submit" isLoading={form.processing}>
                                <Save className="h-4 w-4" aria-hidden="true" />
                                Tinjau dan simpan
                            </Button>
                        </div>
                    </Card>
                </form>
            </div>

            <AuditReasonModal
                open={auditOpen}
                title="Catat alasan perubahan"
                description="Perubahan dasar aturan termasuk tindakan sensitif dan harus memiliki alasan yang dapat diaudit."
                reason={form.data.alasan}
                error={reasonError ?? form.errors.alasan}
                busy={form.processing}
                submitDisabled={Boolean(recovery.recovery || attachmentRecovery.recovery) || recoveryUnknown}
                notice={<><AuthRecoveryNotice recovery={saveRecovery} pending={form.processing} />{!saveRecovery && <RegulasiFailureNotice message={recoveryMessage} />}</>}
                confirmLabel="Simpan perubahan"
                onReasonChange={(value) => {
                    form.setData('alasan', value);
                    setReasonError(undefined);
                }}
                onClose={() => !form.processing && setAuditOpen(false)}
                onConfirm={submit}
            />

            <AuditReasonModal
                open={attachmentDeleteOpen}
                title={attachmentRecovery.recovery || attachmentRecoveryUnknown ? 'Pemulihan penghapusan lampiran' : 'Hapus lampiran?'}
                description={`Lampiran “${(attachmentLabel ?? '').slice(0, 120)}${attachmentLabel && attachmentLabel.length > 120 ? '…' : ''}” akan dihapus setelah transaksi berhasil. Aksi ini membutuhkan alasan audit.`}
                reason={deleteAttachmentForm.data.alasan}
                error={attachmentDeleteError}
                busy={deleteAttachmentForm.processing}
                submitDisabled={Boolean(recovery.recovery || attachmentRecovery.recovery) || attachmentRecoveryUnknown}
                notice={<><AuthRecoveryNotice recovery={deleteRecovery} pending={deleteAttachmentForm.processing} />{!deleteRecovery && <RegulasiFailureNotice message={attachmentRecoveryMessage} />}</>}
                confirmLabel="Hapus lampiran"
                destructive
                onReasonChange={(value) => {
                    deleteAttachmentForm.setData('alasan', value);
                    setAttachmentReasonError(undefined);
                    deleteAttachmentForm.clearErrors();
                }}
                onClose={() => !deleteAttachmentForm.processing && setAttachmentDeleteOpen(false)}
                onConfirm={deleteAttachment}
            />
        </AuthenticatedLayout>
    );
}
