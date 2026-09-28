import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Calendar,
    Download,
    ExternalLink,
    FileText,
    Pencil,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    User,
} from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Badge } from '@/Components/Badge';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { BerkasPk, RenstraPkSummary, StorageSettings } from '@/types/perjanjian-kinerja';
import { PerjanjianKinerjaEditModal } from './Partials/PerjanjianKinerjaEditModal';

interface ShowProps {
    pk: RenstraPkSummary;
    is_jadwal_aktif: boolean;
    storageSettings?: StorageSettings;
    can: {
        update: boolean;
        delete_berkas: boolean;
        read_berkas?: boolean;
    };
}

function formatBytes(value: number | null): string {
    if (value === null) return '';
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

export default function Show({ pk, is_jadwal_aktif, storageSettings, can }: ShowProps) {
    const formatTanggal = useFormatTanggal();
    const [selectedBerkas, setSelectedBerkas] = useState<BerkasPk | null>(null);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);

    const deleteForm = useForm<{
        alasan: string;
        berkas?: string;
    }>({
        alasan: '',
    });

    const openDeleteModal = (berkas: BerkasPk) => {
        setSelectedBerkas(berkas);
        deleteForm.setData('alasan', '');
        deleteForm.clearErrors();
        setIsDeleteModalOpen(true);
    };

    const closeDeleteModal = () => {
        setSelectedBerkas(null);
        setIsDeleteModalOpen(false);
    };

    const handleDeleteSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedBerkas) return;

        deleteForm.delete(`/perjanjian-kinerja/${pk.id}/berkas/${selectedBerkas.id}`, {
            onSuccess: () => {
                closeDeleteModal();
            },
        });
    };

    return (
        <AuthenticatedLayout
            title={`PK Tahun ${pk.tahun} - ${pk.nomor_pk}`}
            breadcrumbs={[
                { label: 'Perjanjian Kinerja (PK)', href: '/perjanjian-kinerja' },
                { label: `Tahun ${pk.tahun}` },
            ]}
        >
            <Head title={`Perjanjian Kinerja (PK) Tahun ${pk.tahun}`} />

            <div className="mx-auto max-w-5xl space-y-4">
                {/* Back and Action Toolbar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link
                        href="/perjanjian-kinerja"
                        className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
                    >
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        Kembali ke Daftar PK
                    </Link>

                    {can.update && (
                        <div className="shrink-0">
                            <Button
                                variant="outline"
                                onClick={() => setIsEditModalOpen(true)}
                            >
                                <Pencil className="h-4 w-4" aria-hidden="true" />
                                Edit Perjanjian Kinerja
                            </Button>
                        </div>
                    )}
                </div>

                {/* Banner Guard Imutabilitas */}
                {is_jadwal_aktif && (
                    <div className="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-950 dark:text-emerald-200">
                        <div className="flex items-start gap-3">
                            <ShieldCheck className="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400 mt-0.5" aria-hidden="true" />
                            <div>
                                <h3 className="text-sm font-bold">Jadwal Tahunan Berstatus Aktif (Terkunci)</h3>
                                <p className="mt-1 text-xs leading-relaxed text-emerald-900/80 dark:text-emerald-300">
                                    Tahun pelaksanaan ini telah diaktifkan dalam Jadwal Tahunan SAKIP. Sesuai prinsip imutabilitas dokumen hukum formal, lampiran berkas yang sudah ada tidak dapat dihapus. Pembaruan rincian tetap dimungkinkan dengan mencantumkan alasan perubahan beralasan.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Metadata Card */}
                <Card className="-mt-1.5 sm:-mt-2">
                    <CardHeader className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-border pb-4">
                        <div>
                            <div className="flex items-center gap-2.5">
                                <span className="inline-flex items-center rounded-lg bg-primary/10 px-3 py-1 font-bold text-primary">
                                    Tahun {pk.tahun}
                                </span>
                                <h1 className="text-lg font-bold tracking-tight text-ink sm:text-xl">
                                    {pk.nomor_pk}
                                </h1>
                            </div>
                            <p className="mt-1.5 text-sm text-muted">
                                Rencana Strategis: <span className="font-semibold text-ink">{pk.renstra?.nama}</span> ({pk.renstra?.tahun_mulai} - {pk.renstra?.tahun_selesai})
                            </p>
                        </div>

                        <div>
                            {is_jadwal_aktif ? (
                                <Badge variant="success">
                                    <ShieldCheck className="h-3.5 w-3.5 mr-1" aria-hidden="true" />
                                    Jadwal Aktif
                                </Badge>
                            ) : (
                                <Badge variant="muted">
                                    Jadwal Belum Aktif
                                </Badge>
                            )}
                        </div>
                    </CardHeader>

                    <CardContent className="pt-6">
                        <dl className="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                            <div className="rounded-lg bg-soft/50 p-3.5">
                                <dt className="text-xs font-semibold text-muted uppercase">Tanggal Penandatanganan</dt>
                                <dd className="mt-1 text-base font-semibold text-ink">
                                    {pk.tanggal_pk ? formatTanggal(pk.tanggal_pk) : '-'}
                                </dd>
                            </div>

                            <div className="rounded-lg bg-soft/50 p-3.5">
                                <dt className="text-xs font-semibold text-muted uppercase">Dicatat Oleh</dt>
                                <dd className="mt-1 text-base font-semibold text-ink flex items-center gap-2">
                                    <User className="h-4 w-4 text-primary" aria-hidden="true" />
                                    {pk.creator?.nama ?? 'Sistem'}
                                </dd>
                            </div>

                            <div className="rounded-lg bg-soft/50 p-3.5">
                                <dt className="text-xs font-semibold text-muted uppercase">Waktu Pencatatan Awal</dt>
                                <dd className="mt-1 text-sm font-medium text-ink">
                                    {formatTanggal(pk.created_at)}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {/* Lampiran Section */}
                <Card>
                    <CardHeader className="border-b border-border pb-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Lampiran Dokumen Legal PK</CardTitle>
                                <p className="mt-1 text-sm text-muted">
                                    Berkas dan naskah hukum komitmen Perjanjian Kinerja.
                                </p>
                            </div>
                            <span className="text-xs font-semibold text-muted bg-soft px-2.5 py-1 rounded-full">
                                {pk.berkas?.length ?? 0} Lampiran
                            </span>
                        </div>
                    </CardHeader>

                    <CardContent className="pt-6">
                        {(!pk.berkas || pk.berkas.length === 0) ? (
                            <div className="rounded-xl border border-dashed border-border bg-page p-8 text-center text-muted">
                                <FileText className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                <p className="mt-2 text-sm font-semibold text-ink">Belum Ada Lampiran Dokumen Legal</p>
                                <p className="mt-1 text-xs text-muted">
                                    Dokumen naskah PK dapat ditambahkan melalui menu Edit Perjanjian Kinerja.
                                </p>
                            </div>
                        ) : (
                            <ul className="space-y-4">
                                {pk.berkas.map((berkas, idx) => (
                                    <li
                                        key={berkas.id}
                                        className="rounded-xl border border-border bg-page p-4 transition-shadow hover:shadow-xs"
                                    >
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="inline-flex items-center rounded-md bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary uppercase">
                                                        {berkas.mode}
                                                    </span>
                                                    <h3 className="text-base font-semibold text-ink truncate">
                                                        {berkas.nama_asli || `Lampiran Dokumen #${idx + 1}`}
                                                    </h3>
                                                </div>

                                                {berkas.mode === 'file' && (
                                                    <p className="mt-1.5 text-xs text-muted">
                                                        Tipe MIME: <span className="font-mono">{berkas.mime || 'Aplikasi/Dokumen'}</span> · Ukuran: {formatBytes(berkas.ukuran_bytes)}
                                                    </p>
                                                )}

                                                {berkas.mode === 'tautan' && (
                                                    <div className="mt-2 text-xs">
                                                        {berkas.tautan ? (
                                                            <a
                                                                href={berkas.tautan}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="inline-flex items-center gap-1.5 text-primary hover:underline font-medium break-all"
                                                            >
                                                                <ExternalLink className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                                                {berkas.tautan}
                                                            </a>
                                                        ) : (
                                                            <span className="text-muted italic">Tautan dilindungi</span>
                                                        )}
                                                    </div>
                                                )}

                                                {berkas.mode === 'teks' && (
                                                    <div className="mt-2.5 rounded-lg bg-soft/60 p-3 text-sm text-ink whitespace-pre-wrap leading-relaxed">
                                                        {berkas.isi_teks ?? (
                                                            <span className="text-muted italic">Konten teks dilindungi</span>
                                                        )}
                                                    </div>
                                                )}

                                                <div className="mt-3 flex items-center gap-4 text-xs text-muted">
                                                    {berkas.pengunggah && (
                                                        <span>Diunggah oleh: <strong className="text-ink">{berkas.pengunggah.nama}</strong></span>
                                                    )}
                                                    {berkas.created_at && (
                                                        <span>Waktu: {formatTanggal(berkas.created_at)}</span>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-2 shrink-0">
                                                {berkas.mode === 'file' && can.read_berkas !== false && (
                                                    <a
                                                        href={`/perjanjian-kinerja/${pk.id}/berkas/${berkas.id}/unduh`}
                                                        className="inline-flex items-center gap-1.5 rounded-lg border border-primary/30 bg-primary/5 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/10 transition-colors"
                                                    >
                                                        <Download className="h-3.5 w-3.5" aria-hidden="true" />
                                                        Unduh Berkas
                                                    </a>
                                                )}

                                                {berkas.mode === 'tautan' && berkas.tautan && can.read_berkas !== false && (
                                                    <a
                                                        href={berkas.tautan}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-ink hover:bg-soft transition-colors"
                                                    >
                                                        <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
                                                        Buka
                                                    </a>
                                                )}

                                                {!is_jadwal_aktif && can.delete_berkas && (
                                                    <button
                                                        type="button"
                                                        onClick={() => openDeleteModal(berkas)}
                                                        className="inline-flex items-center justify-center rounded-lg p-1.5 text-muted hover:bg-danger/10 hover:text-danger transition-colors"
                                                        title="Hapus Lampiran"
                                                        aria-label="Hapus Lampiran"
                                                    >
                                                        <Trash2 className="h-4 w-4" aria-hidden="true" />
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {/* Modal Alasan Audit Hapus Lampiran menggunakan komponen reusable AuditReasonModal (konsisten dengan Persyaratan Berkas) */}
                <AuditReasonModal
                    open={isDeleteModalOpen}
                    title="Konfirmasi Hapus Lampiran"
                    description={
                        selectedBerkas
                            ? `Penghapusan berkas lampiran bersifat sensitif. Lampiran “${selectedBerkas.nama_asli ?? selectedBerkas.tautan ?? 'berkas'}” akan dihapus secara permanen. Masukkan alasan penghapusan untuk rekaman audit.`
                            : 'Penghapusan berkas lampiran bersifat sensitif. Masukkan alasan penghapusan untuk rekaman audit.'
                    }
                    reason={deleteForm.data.alasan}
                    error={deleteForm.errors.berkas || deleteForm.errors.alasan}
                    busy={deleteForm.processing}
                    submitDisabled={!deleteForm.data.alasan.trim()}
                    confirmLabel="Hapus Lampiran"
                    destructive={true}
                    onReasonChange={(reason) => {
                        deleteForm.setData('alasan', reason);
                        deleteForm.clearErrors('alasan');
                        deleteForm.clearErrors('berkas');
                    }}
                    onClose={() => {
                        if (!deleteForm.processing) {
                            closeDeleteModal();
                        }
                    }}
                    onConfirm={() => {
                        if (!selectedBerkas) return;
                        deleteForm.delete(`/perjanjian-kinerja/${pk.id}/berkas/${selectedBerkas.id}`, {
                            onSuccess: () => {
                                closeDeleteModal();
                            },
                        });
                    }}
                />
            </div>

            {can.update && (
                <PerjanjianKinerjaEditModal
                    isOpen={isEditModalOpen}
                    onClose={() => setIsEditModalOpen(false)}
                    pk={pk}
                    isJadwalAktif={is_jadwal_aktif}
                    storageSettings={storageSettings}
                />
            )}
        </AuthenticatedLayout>
    );
}
