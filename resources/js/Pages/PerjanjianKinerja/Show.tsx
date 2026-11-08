import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { BerkasLampiranItem } from '@/Components/BerkasLampiranItem';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { BerkasPk, RenstraPkSummary, RevisiPk, StorageSettings, TargetPkSasaran, VersiPk } from '@/types/perjanjian-kinerja';
import { PerjanjianKinerjaEditModal } from './Partials/PerjanjianKinerjaEditModal';
import { ID_NOMOR_SURAT } from './Partials/RevisiPkForm';
import { PkLockNotice, StatusJadwalBadge } from './Partials/StatusJadwal';
import { TargetPkTable } from './Partials/TargetPkTable';
import { ID_KARTU_VERSI, VersiPkPanel } from './Partials/VersiPkPanel';

interface ShowProps {
    pk: RenstraPkSummary;
    target_pk?: TargetPkSasaran[];
    jadwal_status?: string | null;
    is_jadwal_aktif: boolean;
    is_jadwal_terkunci?: boolean;
    alasan_wajib?: boolean;
    snapshot_url?: string | null;
    storageSettings?: StorageSettings;
    versi?: VersiPk[];
    revisi?: RevisiPk | null;
    can: {
        update: boolean;
        delete_berkas: boolean;
        read_berkas?: boolean;
        upload_berkas?: boolean;
        update_target?: boolean;
        revisi?: boolean;
    };
}

export default function Show({ pk, target_pk = [], jadwal_status, is_jadwal_aktif, is_jadwal_terkunci, alasan_wajib = false, snapshot_url, storageSettings, versi = [], revisi = null, can }: ShowProps) {
    const formatTanggal = useFormatTanggal();
    const [selectedBerkas, setSelectedBerkas] = useState<BerkasPk | null>(null);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [revisiTerbuka, setRevisiTerbuka] = useState(false);
    const isTerkunci = is_jadwal_terkunci ?? is_jadwal_aktif;

    const bukaRevisiDariTabel = () => {
        setRevisiTerbuka(true);
        document.getElementById(ID_KARTU_VERSI)?.scrollIntoView?.({ block: 'start' });
        // Panel yang sudah terbuka tidak dipasang ulang, jadi fokus dipindah di sini; panel baru memfokuskan dirinya.
        document.getElementById(ID_NOMOR_SURAT)?.focus();
    };

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

    return (
        <AuthenticatedLayout
            title="Detail Perjanjian Kinerja"
            breadcrumbs={[
                { label: 'Perjanjian Kinerja (PK)', href: '/perjanjian-kinerja' },
                { label: pk.nomor_pk ?? `PK ${pk.tahun}` },
            ]}
        >
            <Head title={`Perjanjian Kinerja (PK) Tahun ${pk.tahun}`} />

            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                    <Link href="/perjanjian-kinerja" className="inline-flex h-11 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25 sm:h-9">
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        Kembali ke daftar
                    </Link>
                    {can.update && (
                        <Button variant="outline" size="sm" className="h-11 gap-1.5 sm:h-9" onClick={() => setIsEditModalOpen(true)}>
                            <Pencil className="h-4 w-4" aria-hidden="true" />
                            Edit Perjanjian Kinerja
                        </Button>
                    )}
                </div>

                {isTerkunci && <PkLockNotice status={jadwal_status} />}
                {snapshot_url && <p className="text-sm text-muted">
                    Koreksi nomor atau tanggal PK tidak mengubah target Rencana Aksi dan pengukuran. Untuk koreksi target yang sudah dibekukan, perbaiki target PK lalu{' '}
                    <Link href={snapshot_url} className="font-semibold text-primary hover:underline">buka Snapshot Indikator</Link>.
                </p>}

                <Card>
                    <CardHeader className="items-start gap-4">
                        <div className="min-w-0">
                            <CardTitle className="break-words">{pk.nomor_pk ?? `PK ${pk.tahun}`}</CardTitle>
                            <p className="mt-1 text-sm text-muted">Tahun {pk.tahun} · {pk.renstra?.nama}</p>
                        </div>
                        <StatusJadwalBadge status={pk.jadwal_tahunan?.status} className="shrink-0" />
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                            <div>
                                <dt className="text-xs font-semibold text-muted">Tahun pelaksanaan</dt>
                                <dd className="mt-1 font-mono text-sm text-ink">{pk.tahun}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold text-muted">Renstra</dt>
                                <dd className="mt-1 text-sm text-ink">{pk.renstra?.nama} ({pk.renstra?.tahun_mulai}–{pk.renstra?.tahun_selesai})</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold text-muted">Tanggal penandatanganan</dt>
                                <dd className="mt-1 text-sm text-ink">{pk.tanggal_pk ? formatTanggal(pk.tanggal_pk) : 'Tidak dicantumkan'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold text-muted">Dicatat oleh</dt>
                                <dd className="mt-1 text-sm text-ink">{pk.creator?.nama ?? 'Sistem'} · {formatTanggal(pk.created_at)}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <VersiPkPanel
                    pk={pk}
                    versi={versi}
                    revisi={revisi}
                    canRevisi={can.revisi === true}
                    canReadBerkas={can.read_berkas !== false}
                    storageSettings={storageSettings}
                    terbuka={revisiTerbuka}
                    onBuka={() => setRevisiTerbuka(true)}
                    onTutup={() => setRevisiTerbuka(false)}
                />

                <TargetPkTable pkId={pk.id} tahun={pk.tahun} sasaran={target_pk} canUpdate={can.update_target === true} jadwalStatus={jadwal_status}
                    onRevisi={can.revisi === true ? bukaRevisiDariTabel : undefined} />

                <Card>
                    <CardHeader>
                        <CardTitle>Lampiran dokumen</CardTitle>
                        <span className="text-xs text-muted">{pk.berkas?.length ?? 0} lampiran</span>
                    </CardHeader>
                    <CardContent>
                        {pk.berkas && pk.berkas.length > 0 ? (
                            <ul className="space-y-3">
                                {pk.berkas.map((berkas, idx) => {
                                    const nama = berkas.nama_asli || `Lampiran Dokumen #${idx + 1}`;
                                    const dapatDibaca = can.read_berkas !== false;

                                    return (
                                        <BerkasLampiranItem
                                            key={berkas.id}
                                            berkas={{
                                                ...berkas,
                                                nama_asli: nama,
                                                tautan: dapatDibaca ? berkas.tautan : null,
                                                download_url: dapatDibaca ? `/perjanjian-kinerja/${pk.id}/berkas/${berkas.id}/unduh` : null,
                                            }}
                                            meta={(berkas.keterangan || berkas.pengunggah || berkas.created_at) && (
                                                <>
                                                    {berkas.keterangan && <span className="block break-words text-ink">Keterangan: {berkas.keterangan}</span>}
                                                    {berkas.pengunggah && <>Diunggah oleh {berkas.pengunggah.nama}</>}
                                                    {berkas.pengunggah && berkas.created_at && ' · '}
                                                    {berkas.created_at && formatTanggal(berkas.created_at)}
                                                </>
                                            )}
                                            action={!is_jadwal_aktif && can.delete_berkas && (
                                                <button
                                                    type="button"
                                                    onClick={() => openDeleteModal(berkas)}
                                                    className="rounded-md p-1.5 text-muted transition-colors hover:bg-danger/10 hover:text-danger focus:outline-none focus:ring-2 focus:ring-danger/20"
                                                    aria-label={`Hapus lampiran ${nama}`}
                                                >
                                                    <Trash2 className="h-4 w-4" aria-hidden="true" />
                                                </button>
                                            )}
                                        />
                                    );
                                })}
                            </ul>
                        ) : (
                            <p className="text-sm leading-6 text-muted">Belum ada lampiran dokumen pada Perjanjian Kinerja ini.</p>
                        )}
                    </CardContent>
                </Card>

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
                    isJadwalTerkunci={isTerkunci}
                    jadwalStatus={jadwal_status}
                    alasanWajib={alasan_wajib}
                    storageSettings={storageSettings}
                    canUploadBerkas={can.upload_berkas ?? true}
                />
            )}
        </AuthenticatedLayout>
    );
}
