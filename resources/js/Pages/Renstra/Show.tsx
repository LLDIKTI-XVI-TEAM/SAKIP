import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    BookOpen,
    Download,
    Edit3,
    ExternalLink,
    FileText,
    Link2,
    Lock,
    Target,
    Trash2,
    Type,
    User,
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent } from '@/Components/Card';
import type { BerkasRenstra, RenstraDetail, RenstraStatus } from '@/types/renstra';

interface ShowRenstraProps {
    renstra: RenstraDetail;
    can?: {
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

function formatBytes(bytes: number | null): string {
    if (!bytes || bytes <= 0) return '-';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function ShowRenstra({
    renstra,
    can = { update: false, delete: false, deleteAttachment: false },
}: ShowRenstraProps) {
    const isAktif = renstra.status === 'aktif' || Boolean(renstra.is_aktif);
    const berkasList = Array.isArray(renstra.berkas) ? renstra.berkas : [];
    const pembuatNama = typeof renstra.pembuat === 'object' && renstra.pembuat !== null
        ? (renstra.pembuat.nama || renstra.pembuat.name || 'Sistem')
        : typeof renstra.pembuat === 'string' && renstra.pembuat.trim() !== ''
        ? renstra.pembuat
        : 'Sistem';
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
            title={`Detail Renstra: ${renstra.kode}`}
            breadcrumbs={[
                { label: 'Master Renstra', href: '/renstra' },
                { label: renstra.kode },
            ]}
        >
            <Head title={`Detail Renstra: ${renstra.kode}`} />

            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-xl font-bold tracking-tight text-ink">{renstra.nama}</h1>
                            <Badge variant={statusBadgeVariant[renstra.status]} dot>
                                {statusLabel[renstra.status]}
                            </Badge>
                        </div>
                        <p className="mt-1 text-sm font-mono text-muted">{renstra.kode}</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href="/renstra"
                            className="inline-flex items-center gap-2 rounded-lg border border-border bg-surface px-3.5 py-2 text-sm font-semibold text-ink shadow-xs transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/20"
                        >
                            <ArrowLeft className="h-4 w-4 text-muted" aria-hidden="true" />
                            Kembali ke Master
                        </Link>

                        {can.update && renstra.status !== 'diarsipkan' && (
                            <Link
                                href={`/renstra/${renstra.id}/edit`}
                                className="inline-flex items-center gap-2 rounded-lg border border-border bg-surface px-3.5 py-2 text-sm font-semibold text-ink shadow-xs transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/20"
                            >
                                <Edit3 className="h-4 w-4" aria-hidden="true" />
                                Edit Dokumen
                            </Link>
                        )}

                        {can.delete && renstra.status === 'draft' && (berkasList.length === 0 || can.deleteAttachment) && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    deleteRenstraForm.reset();
                                    setRenstraReasonError(undefined);
                                    setDeleteRenstraOpen(true);
                                }}
                                className="text-danger border-danger/30 hover:bg-danger/10 hover:text-danger"
                            >
                                <Trash2 className="h-4 w-4" aria-hidden="true" />
                                Hapus Renstra
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="md:col-span-2 space-y-6">
                        <Card>
                            <CardContent className="p-5 sm:p-6 space-y-5">
                                <h2 className="text-base font-semibold text-ink border-b border-border pb-3">
                                    Informasi Utama
                                </h2>

                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <div className="text-xs font-medium text-muted">Periode Pelaksanaan</div>
                                        <div className="mt-1 font-mono text-sm font-semibold text-ink">
                                            {renstra.tahun_mulai} - {renstra.tahun_selesai}
                                        </div>
                                    </div>

                                    <div>
                                        <div className="text-xs font-medium text-muted">Status Dokumen</div>
                                        <div className="mt-1">
                                            <Badge variant={statusBadgeVariant[renstra.status]} dot>
                                                {statusLabel[renstra.status]}
                                            </Badge>
                                        </div>
                                    </div>

                                    <div>
                                        <div className="text-xs font-medium text-muted">Penyusun / Pembuat</div>
                                        <div className="mt-1 flex items-center gap-1.5 text-sm text-ink">
                                            <User className="h-4 w-4 text-muted" aria-hidden="true" />
                                            <span>{pembuatNama}</span>
                                        </div>
                                    </div>
                                </div>

                                {renstra.deskripsi && (
                                    <div className="border-t border-border pt-4">
                                        <div className="text-xs font-medium text-muted mb-1">Deskripsi / Ringkasan Renstra</div>
                                        <div className="text-sm text-ink leading-relaxed whitespace-pre-line bg-soft/50 rounded-lg p-3.5 border border-border/60">
                                            {renstra.deskripsi}
                                        </div>
                                    </div>
                                )}

                                {renstra.dasar_hukum && (
                                    <div className="border-t border-border pt-4">
                                        <div className="text-xs font-medium text-muted mb-1">Uraian Dasar Hukum</div>
                                        <div className="text-sm text-ink leading-relaxed whitespace-pre-line bg-soft/50 rounded-lg p-3.5 border border-border/60">
                                            {renstra.dasar_hukum}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="p-5 sm:p-6 space-y-4">
                                <div className="flex items-center justify-between border-b border-border pb-3">
                                    <div className="flex items-center gap-2">
                                        <FileText className="h-5 w-5 text-primary" aria-hidden="true" />
                                        <h2 className="text-base font-semibold text-ink">Naskah Renstra & Lampiran</h2>
                                    </div>
                                    <span className="text-xs text-muted">
                                        {berkasList.length} berkas terlampir
                                    </span>
                                </div>

                                {isAktif && (
                                    <div className="flex items-start gap-2.5 rounded-lg border border-border bg-soft/60 p-3 text-xs text-muted leading-relaxed">
                                        <Lock className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted" aria-hidden="true" />
                                        <span>
                                            Renstra berstatus aktif. Naskah lampiran bersifat imutabel dan tidak dapat dihapus demi kepatuhan audit.
                                        </span>
                                    </div>
                                )}

                                {berkasList.length === 0 ? (
                                    <div className="rounded-xl border border-dashed border-border bg-soft/40 p-8 text-center">
                                        <FileText className="mx-auto h-8 w-8 text-muted opacity-40 mb-2" aria-hidden="true" />
                                        <p className="text-sm font-medium text-ink">Belum ada lampiran naskah</p>
                                        <p className="text-xs text-muted mt-1">
                                            Lampiran dapat ditambahkan melalui menu Edit Renstra sebelum dokumen disahkan menjadi aktif.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        {berkasList.map((item) => (
                                            <div
                                                key={item.id}
                                                className="flex flex-col gap-3 rounded-lg border border-border bg-surface p-3.5 sm:flex-row sm:items-center sm:justify-between shadow-xs hover:border-border/80 transition-colors"
                                            >
                                                <div className="flex items-start gap-3 min-w-0">
                                                    <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                        {item.mode === 'file' && <FileText className="h-4 w-4" aria-hidden="true" />}
                                                        {item.mode === 'tautan' && <Link2 className="h-4 w-4" aria-hidden="true" />}
                                                        {item.mode === 'teks' && <Type className="h-4 w-4" aria-hidden="true" />}
                                                    </span>

                                                    <div className="min-w-0 flex-1">
                                                        {item.mode === 'file' && (
                                                            <>
                                                                <div className="truncate text-sm font-semibold text-ink">
                                                                    {item.nama_asli ?? 'Berkas Renstra'}
                                                                </div>
                                                                <div className="text-xs text-muted">
                                                                    {formatBytes(item.ukuran_bytes)} - {item.mime ?? 'Berkas digital'}
                                                                </div>
                                                            </>
                                                        )}

                                                        {item.mode === 'tautan' && (
                                                            <>
                                                                <div className="text-xs font-medium text-muted">Tautan Dokumen</div>
                                                                <a
                                                                    href={item.tautan ?? '#'}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline truncate max-w-full"
                                                                >
                                                                    <span className="truncate">{item.tautan}</span>
                                                                    <ExternalLink className="h-3 w-3 shrink-0" aria-hidden="true" />
                                                                </a>
                                                            </>
                                                        )}

                                                        {item.mode === 'teks' && (
                                                            <>
                                                                <div className="text-xs font-medium text-muted">Kutipan Naskah</div>
                                                                <div className="mt-1 text-xs text-ink whitespace-pre-line bg-soft/50 p-2.5 rounded-md border border-border/60">
                                                                    {item.isi_teks}
                                                                </div>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="flex items-center justify-end gap-2 self-end sm:self-center">
                                                    {item.mode === 'file' && (
                                                        <a
                                                            href={`/renstra/${renstra.id}/berkas/${item.id}/download`}
                                                            className="inline-flex items-center gap-1.5 rounded-md bg-soft px-3 py-1.5 text-xs font-semibold text-ink transition-colors hover:bg-border focus:outline-none focus:ring-2 focus:ring-primary/20"
                                                        >
                                                            <Download className="h-3.5 w-3.5 text-muted" aria-hidden="true" />
                                                            Unduh
                                                        </a>
                                                    )}

                                                    {can.deleteAttachment && renstra.status === 'draft' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => openDeleteBerkas(item)}
                                                            className="rounded-md p-1.5 text-muted transition-colors hover:bg-danger/10 hover:text-danger focus:outline-none focus:ring-2 focus:ring-danger/20"
                                                            title="Hapus Lampiran"
                                                        >
                                                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                                                            <span className="sr-only">Hapus lampiran</span>
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardContent className="p-5 space-y-4">
                                <div className="flex items-center gap-2 border-b border-border pb-3">
                                    <BookOpen className="h-4 w-4 text-primary" aria-hidden="true" />
                                    <h3 className="text-sm font-semibold text-ink">Rujukan Regulasi</h3>
                                </div>

                                {renstra.regulasi ? (
                                    <div className="space-y-3">
                                        <div>
                                            <div className="text-xs text-muted">Nomor & Tahun</div>
                                            <div className="mt-0.5 text-sm font-semibold text-ink">
                                                {renstra.regulasi.nomor} ({renstra.regulasi.tahun})
                                            </div>
                                        </div>

                                        <div>
                                            <div className="text-xs text-muted">Tentang</div>
                                            <div className="mt-0.5 text-xs text-ink leading-relaxed">
                                                {renstra.regulasi.tentang}
                                            </div>
                                        </div>

                                        <div className="pt-1">
                                            <Link
                                                href={`/regulasi/${renstra.regulasi.id}`}
                                                className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                                            >
                                                Buka Dasar Aturan
                                                <ExternalLink className="h-3 w-3" aria-hidden="true" />
                                            </Link>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="rounded-lg bg-soft/50 p-3.5 text-center text-xs text-muted">
                                        Tidak ada rujukan regulasi langsung yang ditautkan pada Renstra ini.
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="p-5 space-y-3 text-xs text-muted leading-relaxed">
                                <h3 className="text-sm font-semibold text-ink border-b border-border pb-2">
                                    Catatan Tata Kelola
                                </h3>
                                <p>
                                    Renstra merupakan dokumen induk yang menjadi fondasi penetapan Sasaran Strategis, Perjanjian Kinerja (PK), dan Pengukuran Capaian IKU Triwulanan.
                                </p>
                                <p>
                                    Perubahan status dari Draft menjadi Aktif mengunci naskah lampiran dan mewajibkan pencatatan alasan audit untuk setiap modifikasi data.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

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
                destructive
                onReasonChange={(reason) => deleteRenstraForm.setData('alasan', reason)}
                onClose={() => setDeleteRenstraOpen(false)}
                onConfirm={confirmDeleteRenstra}
            />
        </AuthenticatedLayout>
    );
}
