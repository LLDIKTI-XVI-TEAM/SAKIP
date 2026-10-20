import React, { useState, useMemo } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import {
    JenisBerkasModal,
    type JenisBerkasFormData,
    type IndikatorOption,
    type BatasUnggahan,
} from './Partials/JenisBerkasModal';
import { ContohBerkas, type ContohBerkasItem } from './Partials/ContohBerkas';

export interface JenisBerkasItem {
    id: string;
    nama: string;
    tahap: 'rencana_aksi' | 'pengukuran' | 'kegiatan' | 'kegiatan_perencanaan';
    indikator_id: string | null;
    wajib: boolean;
    keterangan: string | null;
    izinkan_file: boolean;
    izinkan_tautan: boolean;
    izinkan_teks: boolean;
    semua_mode_wajib: boolean;
    hanya_kegiatan_beranggaran?: boolean;
    urutan: number;
    format_diizinkan: string | null;
    ukuran_maks_kb: number | null;
    aktif: boolean;
    contoh_berkas?: ContohBerkasItem[];
    created_at?: string;
    updated_at?: string;
    indikator?: {
        id: string;
        kode: string;
        nama: string;
        status?: 'aktif' | 'arsip';
    } | null;
}

interface IndexProps {
    jenisBerkasList: JenisBerkasItem[];
    indikators: IndikatorOption[];
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
    batasUnggahan: BatasUnggahan;
}

const defaultFormData: JenisBerkasFormData = {
    nama: '',
    tahap: 'pengukuran',
    indikator_id: '',
    wajib: false,
    keterangan: '',
    izinkan_file: true,
    izinkan_tautan: false,
    izinkan_teks: false,
    semua_mode_wajib: false,
    hanya_kegiatan_beranggaran: false,
    urutan: 0,
    format_diizinkan: '',
    ukuran_maks_kb: null,
    aktif: true,
};

const TAHAP: Record<JenisBerkasItem['tahap'], string> = {
    rencana_aksi: 'Rencana Aksi',
    pengukuran: 'Pengukuran',
    kegiatan: 'Kegiatan (SPJ)',
    kegiatan_perencanaan: 'Perencanaan Kegiatan',
};

const modeBukti = (item: JenisBerkasItem) =>
    [item.izinkan_file && 'File', item.izinkan_tautan && 'Tautan', item.izinkan_teks && 'Teks'].filter(Boolean).join(', ');

const batasFile = (item: JenisBerkasItem) =>
    `${item.format_diizinkan ? item.format_diizinkan.split(',').map((format) => format.trim()).join(', ') : 'Format bawaan'} · ${
        item.ukuran_maks_kb ? `maks ${item.ukuran_maks_kb.toLocaleString('id-ID')} KB` : 'ukuran bawaan'
    }`;

export default function JenisBerkasIndex({
    jenisBerkasList = [],
    indikators = [],
    can = { create: false, update: false, delete: false },
    batasUnggahan,
}: IndexProps) {
    const [selectedTahap, setSelectedTahap] = useState<'' | JenisBerkasItem['tahap']>('');
    const [searchQuery, setSearchQuery] = useState<string>('');

    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const form = useForm<JenisBerkasFormData>(defaultFormData);
    const deleteForm = useForm({ alasan: '', expected_updated_at: '' });
    const formData = form.data;
    const [modeError, setModeError] = useState<string | null>(null);
    const formErrors = { ...form.errors, ...(modeError ? { modes: modeError } : {}) };
    const isSubmitting = form.processing || deleteForm.processing;

    const [isAuditModalOpen, setIsAuditModalOpen] = useState(false);
    const [auditAction, setAuditAction] = useState<'update' | 'delete' | 'delete-contoh' | null>(null);
    const [targetItem, setTargetItem] = useState<JenisBerkasItem | null>(null);
    const [targetContoh, setTargetContoh] = useState<ContohBerkasItem | null>(null);
    const [auditReason, setAuditReason] = useState('');
    const [auditError, setAuditError] = useState<string | null>(null);

    // Filter list
    const filteredList = useMemo(() => {
        return jenisBerkasList.filter((item) => {
            const matchesTahap = selectedTahap === '' || item.tahap === selectedTahap;
            const matchesSearch =
                item.nama.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (item.keterangan && item.keterangan.toLowerCase().includes(searchQuery.toLowerCase())) ||
                (item.indikator && (
                    item.indikator.kode.toLowerCase().includes(searchQuery.toLowerCase()) ||
                    item.indikator.nama.toLowerCase().includes(searchQuery.toLowerCase())
                ));
            return matchesTahap && matchesSearch;
        });
    }, [jenisBerkasList, selectedTahap, searchQuery]);

    const handleOpenCreate = () => {
        setIsEditing(false);
        form.setDefaults(defaultFormData);
        form.setData(defaultFormData);
        form.clearErrors();
        setModeError(null);
        setAuditReason('');
        setIsFormModalOpen(true);
    };

    const handleOpenEdit = (item: JenisBerkasItem) => {
        setIsEditing(true);
        setTargetItem(item);
        const data: JenisBerkasFormData = {
            id: item.id,
            nama: item.nama,
            tahap: item.tahap,
            indikator_id: item.indikator_id || '',
            wajib: item.wajib,
            keterangan: item.keterangan || '',
            izinkan_file: item.izinkan_file,
            izinkan_tautan: item.izinkan_tautan,
            izinkan_teks: item.izinkan_teks,
            semua_mode_wajib: item.semua_mode_wajib,
            hanya_kegiatan_beranggaran: item.hanya_kegiatan_beranggaran === true,
            urutan: item.urutan,
            format_diizinkan: item.format_diizinkan || '',
            ukuran_maks_kb: item.ukuran_maks_kb,
            aktif: item.aktif !== false,
            expected_updated_at: item.updated_at || item.created_at,
        };
        form.setDefaults(data);
        form.setData(data);
        form.clearErrors();
        setModeError(null);
        setAuditReason('');
        setAuditError(null);
        setIsFormModalOpen(true);
    };

    const handleFormChange = <K extends keyof JenisBerkasFormData>(field: K, value: JenisBerkasFormData[K]) => {
        form.setData((previous) => ({ ...previous, [field]: value }));
        form.clearErrors(field);
        setModeError(null);
    };

    const handleFormSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        if (isSubmitting) return;
        if (!formData.izinkan_file && !formData.izinkan_tautan && !formData.izinkan_teks) {
            setModeError('Minimal satu mode bukti (file, tautan, atau teks) harus diizinkan.');
            return;
        }
        if (isEditing) {
            setAuditReason('');
            setAuditError(null);
            setIsAuditModalOpen(true);
            setAuditAction('update');
            return;
        }
        form.transform((data) => ({
            ...data,
            format_diizinkan: data.format_diizinkan.trim() || null,
            ukuran_maks_kb: data.ukuran_maks_kb === '' ? null : data.ukuran_maks_kb,
        }));
        form.post('/jenis-berkas', {
            preserveScroll: true,
            onSuccess: () => setIsFormModalOpen(false),
        });
    };

    const handleOpenDelete = (item: JenisBerkasItem) => {
        setTargetItem(item);
        setAuditAction('delete');
        setAuditReason('');
        setAuditError(null);
        setIsAuditModalOpen(true);
    };

    const handleOpenDeleteContoh = (item: JenisBerkasItem, contoh: ContohBerkasItem) => {
        setTargetItem(item);
        setTargetContoh(contoh);
        setAuditAction('delete-contoh');
        setAuditReason('');
        setAuditError(null);
        setIsAuditModalOpen(true);
    };

    const handleConfirmAudit = () => {
        if (isSubmitting) return;
        const trimmed = auditReason.trim();
        if (trimmed.length < 5) {
            setAuditError('Alasan perubahan wajib diisi minimal 5 karakter.');
            return;
        }
        if (trimmed.length > 1000) {
            setAuditError('Alasan perubahan maksimal 1.000 karakter.');
            return;
        }
        setAuditError(null);
        if (auditAction === 'update' && formData.id) {
            form.transform((data) => ({
                ...data,
                alasan: trimmed,
                format_diizinkan: data.format_diizinkan.trim() || null,
                ukuran_maks_kb: data.ukuran_maks_kb === '' ? null : data.ukuran_maks_kb,
            }));
            form.put(`/jenis-berkas/${formData.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsAuditModalOpen(false);
                    setIsFormModalOpen(false);
                    setAuditReason('');
                    setAuditError(null);
                },
                onError: (errors) => {
                    if (errors.alasan || errors.konflik || errors.expected_updated_at) {
                        setAuditError(errors.alasan || errors.konflik || errors.expected_updated_at);
                    } else {
                        setIsAuditModalOpen(false);
                    }
                },
            });
        } else if (targetItem && (auditAction === 'delete' || (auditAction === 'delete-contoh' && targetContoh))) {
            const hapusContoh = auditAction === 'delete-contoh' && targetContoh;
            deleteForm.transform(() => hapusContoh
                ? { alasan: trimmed }
                : { alasan: trimmed, expected_updated_at: targetItem.updated_at || targetItem.created_at });
            const url = hapusContoh ? `/jenis-berkas/${targetItem.id}/contoh/${hapusContoh.id}` : `/jenis-berkas/${targetItem.id}`;
            deleteForm.delete(url, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsAuditModalOpen(false);
                    setTargetItem(null);
                    setTargetContoh(null);
                    setAuditReason('');
                    setAuditError(null);
                },
                onError: (errors) => setAuditError(errors.alasan || errors.konflik || errors.expected_updated_at || 'Gagal menghapus berkas atau persyaratan.'),
            });
        }
    };

    const showAksi = can.update || can.delete;
    const tahapOptions = [
        { value: '', label: `Semua tahap (${jenisBerkasList.length})` },
        ...(Object.keys(TAHAP) as JenisBerkasItem['tahap'][]).map((tahap) => ({
            value: tahap,
            label: `${TAHAP[tahap]} (${jenisBerkasList.filter((item) => item.tahap === tahap).length})`,
        })),
    ];

    return (
        <AuthenticatedLayout
            title="Persyaratan Berkas"
            breadcrumbs={[{ label: 'Persyaratan Berkas' }]}
            headerActions={
                can.create && (
                    <Button type="button" size="sm" data-testid="btn-tambah-persyaratan" onClick={handleOpenCreate}>
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Tambah persyaratan
                    </Button>
                )
            }
        >
            <Head title="Persyaratan Berkas" />

            <div className="space-y-5">
                <Card className="overflow-visible">
                    <div className="grid gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_14rem]">
                        <Input
                            id="pencarian-jenis-berkas"
                            label="Cari persyaratan"
                            placeholder="Cari nama, keterangan, atau indikator..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            autoComplete="off"
                        />
                        <Select
                            label="Tahap"
                            value={selectedTahap}
                            onChange={(e) => setSelectedTahap(e.target.value as '' | JenisBerkasItem['tahap'])}
                            options={tahapOptions}
                        />
                    </div>
                </Card>

                <Card>
                    {/* Grid mobile: ketentuan bukti tampil di bawah nama tanpa scroll horizontal. */}
                    <Table wrapperClassName="overflow-x-visible" className="block md:table">
                        <caption className="sr-only">Persyaratan berkas</caption>
                        {/* Di mobile header hanya disembunyikan visual (bukan hidden) agar pembaca layar tetap mendapat relasi header–sel. */}
                        <TableHeader className="max-md:sr-only">
                            <TableRow>
                                {['Persyaratan', 'Tahap', 'Lingkup', 'Ketentuan bukti', 'Status', ...(showAksi ? ['Aksi'] : [])].map((label) => (
                                    <TableHead key={label} className={`whitespace-normal ${label === 'Aksi' ? 'text-right' : ''}`}>
                                        {label}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody className="block md:table-row-group">
                            {filteredList.length === 0 && (
                                <TableRow className="block md:table-row">
                                    <TableCell colSpan={showAksi ? 6 : 5} className="block py-12 text-center md:table-cell">
                                        <FileText className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                        <p className="mt-2 text-sm font-semibold text-ink">
                                            {jenisBerkasList.length === 0 ? 'Belum ada persyaratan' : 'Tidak ada persyaratan yang cocok'}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">
                                            {jenisBerkasList.length === 0
                                                ? 'Persyaratan menentukan bukti dukung yang harus dipenuhi PIC pada setiap tahap.'
                                                : 'Coba ubah kata kunci atau tahap pada filter.'}
                                        </p>
                                    </TableCell>
                                </TableRow>
                            )}
                            {filteredList.map((item) => (
                                <TableRow key={item.id} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                    <TableCell className="col-span-2 min-w-0 p-0 md:max-w-72 md:px-4 md:py-3.5">
                                        <p className="break-words text-sm font-semibold text-ink">{item.nama}</p>
                                        {item.keterangan && (
                                            <p className="mt-0.5 line-clamp-2 break-words text-xs text-muted">{item.keterangan}</p>
                                        )}
                                        {item.tahap === 'kegiatan_perencanaan' && (
                                            <ContohBerkas jenisBerkasId={item.id} nama={item.nama} contoh={item.contoh_berkas ?? []} canUpdate={can.update}
                                                onHapus={(contoh) => handleOpenDeleteContoh(item, contoh)} />
                                        )}
                                    </TableCell>
                                    <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5">
                                        <span className="mr-2 text-muted md:hidden">Tahap</span>
                                        {TAHAP[item.tahap] ?? item.tahap}
                                    </TableCell>
                                    <TableCell className="min-w-0 p-0 md:max-w-56 md:px-4 md:py-3.5">
                                        <span className="mr-2 text-muted md:hidden">Lingkup</span>
                                        {item.indikator ? (
                                            <>
                                                <span className="font-semibold text-primary">{item.indikator.kode}</span>
                                                <span className="mt-0.5 line-clamp-1 text-muted">{item.indikator.nama}</span>
                                            </>
                                        ) : (
                                            <span className="text-muted">Semua indikator</span>
                                        )}
                                    </TableCell>
                                    <TableCell className="col-span-2 min-w-0 p-0 md:px-4 md:py-3.5">
                                        <span className="mr-2 text-muted md:hidden">Ketentuan</span>
                                        <span className={item.wajib ? 'font-semibold text-ink' : 'text-muted'}>
                                            {item.wajib ? 'Wajib' : 'Opsional'}
                                        </span>
                                        {item.semua_mode_wajib && <span className="text-muted"> · semua mode</span>}
                                        {item.hanya_kegiatan_beranggaran && <span className="text-muted"> · hanya kegiatan beranggaran</span>}
                                        <p className="mt-0.5 text-ink">{modeBukti(item)}</p>
                                        {item.izinkan_file && <p className="mt-0.5 break-words text-muted">{batasFile(item)}</p>}
                                    </TableCell>
                                    <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5">
                                        <Badge variant={item.aktif !== false ? 'success' : 'muted'}>
                                            {item.aktif !== false ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </TableCell>
                                    {showAksi && (
                                        <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5 md:text-right">
                                            <div className="flex min-w-0 flex-wrap items-center justify-end gap-1.5 md:flex-nowrap">
                                                {can.update && (
                                                    <Tooltip content="Edit" align="right">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleOpenEdit(item)}
                                                            aria-label={`Edit ${item.nama}`}
                                                            className="h-8 w-8 p-0"
                                                        >
                                                            <Pencil className="h-4 w-4" aria-hidden="true" />
                                                        </Button>
                                                    </Tooltip>
                                                )}
                                                {can.delete && (
                                                    <Tooltip content="Hapus" align="right">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleOpenDelete(item)}
                                                            aria-label={`Hapus ${item.nama}`}
                                                            className="h-8 w-8 border-danger/30 p-0 text-danger hover:bg-danger/10 focus:ring-danger/25"
                                                        >
                                                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                                                        </Button>
                                                    </Tooltip>
                                                )}
                                            </div>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            </div>

            {/* Modal Tambah / Ubah */}
            <JenisBerkasModal
                isOpen={isFormModalOpen && !isAuditModalOpen}
                isEditing={isEditing}
                data={formData}
                errors={formErrors}
                indikators={indikators}
                isLoading={isSubmitting}
                batasUnggahan={batasUnggahan}
                onChange={handleFormChange}
                onClose={() => {
                    if (!isSubmitting) setIsFormModalOpen(false);
                }}
                onSubmit={handleFormSubmit}
            />

            {/* Modal Alasan Audit (Sensitif) */}
            <AuditReasonModal
                open={isAuditModalOpen}
                title={
                    auditAction === 'delete-contoh'
                        ? `Hapus contoh ${targetContoh?.nama_asli ?? 'berkas'}?`
                        : auditAction === 'delete'
                        ? `Hapus ${targetItem?.nama ?? 'persyaratan'}?`
                        : 'Simpan perubahan persyaratan?'
                }
                description={
                    auditAction === 'delete-contoh'
                        ? 'Contoh berkas tidak lagi dapat diunduh PIC. Masukkan alasan penghapusan untuk rekaman audit.'
                        : auditAction === 'delete'
                        ? 'Penghapusan katalog jenis berkas bersifat sensitif. Persyaratan yang dihapus tidak lagi berlaku untuk pengajuan berikutnya. Masukkan alasan penghapusan untuk rekaman audit.'
                        : 'Perubahan katalog persyaratan bukti dukung bersifat sensitif dan akan dicatat pada audit trail dengan rekaman kondisi sebelum dan sesudah perubahan. Masukkan alasan perubahan.'
                }
                reason={auditReason}
                error={auditError || undefined}
                busy={isSubmitting}
                confirmLabel={auditAction === 'delete-contoh' ? 'Hapus contoh' : auditAction === 'delete' ? 'Hapus persyaratan' : 'Simpan perubahan'}
                destructive={auditAction === 'delete' || auditAction === 'delete-contoh'}
                onReasonChange={setAuditReason}
                onClose={() => {
                    if (!isSubmitting) {
                        setIsAuditModalOpen(false);
                        setAuditReason('');
                        setAuditError(null);
                    }
                }}
                onConfirm={handleConfirmAudit}
            />
        </AuthenticatedLayout>
    );
}
