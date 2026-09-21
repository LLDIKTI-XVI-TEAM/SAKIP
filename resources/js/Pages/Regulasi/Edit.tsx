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
    const [auditOpen, setAuditOpen] = useState(false);
    const [reasonError, setReasonError] = useState<string | undefined>();
    const [selectedAttachment, setSelectedAttachment] = useState<BerkasRegulasi | null>(null);
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
    const deleteAttachmentForm = useForm({ alasan: '' });

    const requestAuditReason = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setReasonError(undefined);
        setAuditOpen(true);
    };

    const submit = () => {
        if (form.data.alasan.trim().length < 10) {
            setReasonError('Jelaskan alasan perubahan minimal 10 karakter.');
            return;
        }

        form.post(`/regulasi/${regulasi.id}`, {
            forceFormData: true,
            onError: (errors) => {
                if (!errors.alasan) setAuditOpen(false);
            },
        });
    };

    const openAttachmentDelete = (attachment: BerkasRegulasi) => {
        deleteAttachmentForm.reset();
        deleteAttachmentForm.clearErrors();
        setAttachmentReasonError(undefined);
        setSelectedAttachment(attachment);
    };

    const deleteAttachment = () => {
        if (!selectedAttachment) return;

        if (deleteAttachmentForm.data.alasan.trim().length < 10) {
            setAttachmentReasonError('Jelaskan alasan penghapusan minimal 10 karakter.');
            return;
        }

        deleteAttachmentForm.delete(`/regulasi/${regulasi.id}/berkas/${selectedAttachment.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedAttachment(null);
                deleteAttachmentForm.reset();
            },
        });
    };

    const attachmentDeleteError = attachmentReasonError
        ?? deleteAttachmentForm.errors.alasan
        ?? (deleteAttachmentForm.errors as Record<string, string | undefined>).berkas;

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
                confirmLabel="Simpan perubahan"
                onReasonChange={(value) => {
                    form.setData('alasan', value);
                    setReasonError(undefined);
                }}
                onClose={() => !form.processing && setAuditOpen(false)}
                onConfirm={submit}
            />

            <AuditReasonModal
                open={selectedAttachment !== null}
                title="Hapus lampiran?"
                description="Lampiran akan dihapus dari metadata dan private storage setelah transaksi berhasil. Aksi ini membutuhkan alasan audit."
                reason={deleteAttachmentForm.data.alasan}
                error={attachmentDeleteError}
                busy={deleteAttachmentForm.processing}
                confirmLabel="Hapus lampiran"
                destructive
                onReasonChange={(value) => {
                    deleteAttachmentForm.setData('alasan', value);
                    setAttachmentReasonError(undefined);
                    deleteAttachmentForm.clearErrors();
                }}
                onClose={() => !deleteAttachmentForm.processing && setSelectedAttachment(null)}
                onConfirm={deleteAttachment}
            />
        </AuthenticatedLayout>
    );
}
