import React, { useState, useMemo } from 'react';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Building2, Pencil, Plus, Power, Trash2, X } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { Card } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useLabelUnit } from '@/hooks/useLabelUnit';

interface UnitItem {
    id: string;
    nama: string;
    status: 'aktif' | 'nonaktif';
    is_active: boolean;
    version_token?: string;
    snapshot?: {
        nama: string;
        status: string;
    };
    expected_nama?: string;
    expected_status?: string;
    indikators_count: number;
    rencana_aksis_count: number;
    kegiatans_count: number;
    grants_count: number;
    denies_count?: number;
    jadwal_snapshots_count?: number;
    is_deletable: boolean;
    can: {
        update: boolean;
        delete: boolean;
    };
}

interface UnitIndexProps {
    units: UnitItem[];
    can: {
        create: boolean;
    };
}

// Jumlah keterkaitan per unit: dasar pertimbangan sebelum menonaktifkan atau menghapus unit.
const JUMLAH = [
    ['indikators_count', 'Indikator'],
    ['rencana_aksis_count', 'Rencana aksi'],
    ['kegiatans_count', 'Kegiatan'],
    ['grants_count', 'Grant izin'],
] as const;

const ALASAN_TIDAK_DAPAT_DIHAPUS = 'Masih terkait indikator, rencana aksi, kegiatan, snapshot jadwal, atau izin.';

export default function UnitIndex({ units, can }: UnitIndexProps) {
    const page = usePage();
    const pageErrors = (page.props as { errors?: Record<string, string> }).errors;
    const recovery = useAuthRecovery();
    const labelUnit = useLabelUnit();
    const lu = labelUnit.toLowerCase();

    const [search, setSearch] = useState('');
    const [filterStatus, setFilterStatus] = useState<'' | 'aktif' | 'nonaktif'>('');
    const [statusError, setStatusError] = useState<string | null>(null);

    // Modals state
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingUnit, setEditingUnit] = useState<UnitItem | null>(null);
    const [togglingUnit, setTogglingUnit] = useState<UnitItem | null>(null);
    const [isToggling, setIsToggling] = useState(false);
    const [toggleError, setToggleError] = useState('');
    const [deletingUnit, setDeletingUnit] = useState<UnitItem | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [deleteReason, setDeleteReason] = useState('');
    const [deleteError, setDeleteError] = useState('');

    // Form Tambah Unit
    const createForm = useForm({
        nama: '',
        status: 'aktif' as 'aktif' | 'nonaktif',
    });

    // Form Edit Unit
    const editForm = useForm<{
        nama: string;
        status: 'aktif' | 'nonaktif';
        version_token: string;
        expected_nama: string;
        expected_status: string;
        snapshot: { nama: string; status: string } | null;
    }>({
        nama: '',
        status: 'aktif',
        version_token: '',
        expected_nama: '',
        expected_status: '',
        snapshot: null,
    });

    const filteredUnits = useMemo(() => {
        return units.filter((u) => {
            const matchesSearch = u.nama.toLowerCase().includes(search.toLowerCase());
            const matchesStatus = filterStatus === '' || u.status === filterStatus;

            return matchesSearch && matchesStatus;
        });
    }, [units, search, filterStatus]);

    const activeCount = units.filter((u) => u.is_active).length;
    const editErrors = editForm.errors as Record<string, string | undefined>;
    const editConflict = editErrors.version_token || editErrors.konflik || editErrors.snapshot || editErrors.expected_state;

    const handleOpenCreate = () => {
        createForm.reset();
        createForm.clearErrors();
        setStatusError(null);
        setIsCreateOpen(true);
    };

    const handleOpenEdit = (unit: UnitItem) => {
        setEditingUnit(unit);
        editForm.setData({
            nama: unit.nama,
            status: unit.status,
            version_token: unit.version_token ?? '',
            expected_nama: unit.nama,
            expected_status: unit.status,
            snapshot: {
                nama: unit.nama,
                status: unit.status,
            },
        });
        editForm.clearErrors();
        setStatusError(null);
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (createForm.processing || Boolean(recovery.recovery)) return;

        createForm.post('/unit', {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
                setStatusError(null);
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: '/unit', mutation: true })) {
                    return false;
                }
                const serverMsg = typeof response.data === 'object' && response.data !== null && 'message' in response.data && typeof response.data.message === 'string'
                    ? response.data.message
                    : null;
                const message = serverMsg || (response.status === 403
                    ? 'Izin tindakan ditolak. Anda tidak memiliki wewenang untuk menambahkan unit organisasi.'
                    : 'Gagal menambahkan unit organisasi.');
                createForm.setError('nama', message);
                return false;
            },
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingUnit || editForm.processing || Boolean(recovery.recovery)) return;

        editForm.post(`/unit/${editingUnit.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingUnit(null);
                setStatusError(null);
            },
            onError: (errors: Record<string, string>) => {
                const message = errors.version_token || errors.konflik || errors.snapshot || errors.expected_state || errors.status || errors.nama || Object.values(errors)[0] || 'Gagal menyimpan perubahan unit organisasi.';
                setStatusError(message);
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/unit/${editingUnit.id}`, mutation: true })) {
                    return false;
                }
                const serverMsg = typeof response.data === 'object' && response.data !== null && 'message' in response.data && typeof response.data.message === 'string'
                    ? response.data.message
                    : null;
                const message = serverMsg || (response.status === 403
                    ? 'Izin tindakan ditolak. Anda tidak memiliki wewenang untuk mengubah unit organisasi.'
                    : 'Gagal menyimpan perubahan unit organisasi.');
                setStatusError(message);
                return false;
            },
        });
    };

    const handleOpenToggle = (unit: UnitItem) => {
        setTogglingUnit(unit);
        setToggleError('');
        setStatusError(null);
    };

    const handleCloseToggle = () => {
        if (!isToggling) setTogglingUnit(null);
    };

    const handleToggleStatus = () => {
        const unit = togglingUnit;
        if (!unit || isToggling || Boolean(recovery.recovery)) return;
        const nextStatus = unit.status === 'aktif' ? 'nonaktif' : 'aktif';
        setToggleError('');
        setIsToggling(true);
        router.post(`/unit/${unit.id}`, {
            nama: unit.nama,
            status: nextStatus,
            version_token: unit.version_token ?? '',
            expected_nama: unit.nama,
            expected_status: unit.status,
            snapshot: {
                nama: unit.nama,
                status: unit.status,
            },
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setTogglingUnit(null);
            },
            onError: (errors: Record<string, string>) => {
                const message = errors.version_token || errors.konflik || errors.snapshot || errors.expected_state || errors.status || errors.nama || Object.values(errors)[0] || 'Gagal mengubah status unit organisasi.';
                setToggleError(message);
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/unit/${unit.id}`, mutation: true })) {
                    return false;
                }
                const serverMsg = typeof response.data === 'object' && response.data !== null && 'message' in response.data && typeof response.data.message === 'string'
                    ? response.data.message
                    : null;
                const message = serverMsg || (response.status === 403
                    ? 'Izin tindakan ditolak. Anda tidak memiliki wewenang untuk mengubah status unit organisasi.'
                    : 'Gagal mengubah status unit organisasi.');
                setToggleError(message);
                return false;
            },
            onFinish: () => {
                setIsToggling(false);
            },
        });
    };

    const handleOpenDelete = (unit: UnitItem) => {
        setDeletingUnit(unit);
        setDeleteReason('');
        setDeleteError('');
        setStatusError(null);
    };

    const handleCloseDelete = () => {
        if (isDeleting) return;
        setDeletingUnit(null);
        setDeleteReason('');
        setDeleteError('');
    };

    const handleDeleteSubmit = () => {
        if (!deletingUnit || isDeleting || Boolean(recovery.recovery)) return;

        setIsDeleting(true);
        router.delete(`/unit/${deletingUnit.id}`, {
            data: {
                alasan: deleteReason.trim(),
            },
            preserveScroll: true,
            onSuccess: () => {
                setDeletingUnit(null);
                setDeleteReason('');
                setDeleteError('');
            },
            onError: (errors: Record<string, string>) => {
                const message = errors.alasan || errors.error || Object.values(errors)[0] || 'Gagal menghapus unit organisasi.';
                setDeleteError(message);
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'delete', path: `/unit/${deletingUnit.id}`, mutation: true })) {
                    return false;
                }
                const serverMsg = typeof response.data === 'object' && response.data !== null && 'message' in response.data && typeof response.data.message === 'string'
                    ? response.data.message
                    : null;
                const message = serverMsg || (response.status === 403
                    ? 'Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan dengan indikator kinerja, rencana aksi, kegiatan, snapshot jadwal, atau izin terkait.'
                    : 'Gagal menghapus unit organisasi. Silakan coba kembali.');
                setDeleteError(message);
                return false;
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    return (
        <AuthenticatedLayout
            title={`Master ${labelUnit}`}
            breadcrumbs={[{ label: `Master ${labelUnit}` }]}
            headerActions={
                can.create && (
                    <Button type="button" size="sm" onClick={handleOpenCreate}>
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Tambah {lu}
                    </Button>
                )
            }
        >
            <Head title={`Master ${labelUnit}`} />

            <div className="space-y-5">
                {(statusError || pageErrors?.status) && (
                    <ErrorAlert message={statusError || pageErrors?.status || ''} onDismiss={() => setStatusError(null)} />
                )}

                <Card className="overflow-visible">
                    <div className="grid gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_12rem]">
                        <Input
                            label={`Cari ${lu}`}
                            placeholder={`Cari nama ${lu}...`}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            autoComplete="off"
                        />
                        <Select
                            label="Status"
                            value={filterStatus}
                            onChange={(e) => setFilterStatus(e.target.value as '' | 'aktif' | 'nonaktif')}
                            options={[
                                { value: '', label: `Semua status (${units.length})` },
                                { value: 'aktif', label: `Aktif (${activeCount})` },
                                { value: 'nonaktif', label: `Nonaktif (${units.length - activeCount})` },
                            ]}
                        />
                    </div>
                </Card>

                <Card>
                    {/* Grid mobile: jumlah keterkaitan tampil berpasangan tanpa scroll horizontal. */}
                    <Table wrapperClassName="overflow-x-visible" className="block md:table">
                        <caption className="sr-only">Master {lu}</caption>
                        {/* Di mobile header hanya disembunyikan visual (bukan hidden) agar pembaca layar tetap mendapat relasi header–sel. */}
                        <TableHeader className="max-md:sr-only">
                            <TableRow>
                                <TableHead className="whitespace-normal">Nama {lu}</TableHead>
                                {JUMLAH.map(([key, label]) => (
                                    <TableHead key={key} className="whitespace-normal text-right">
                                        {label}
                                    </TableHead>
                                ))}
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="block md:table-row-group">
                            {filteredUnits.length === 0 && (
                                <TableRow className="block md:table-row">
                                    <TableCell colSpan={7} className="block py-12 text-center md:table-cell">
                                        <Building2 className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                        <p className="mt-2 text-sm font-semibold text-ink">
                                            {units.length === 0 ? `Belum ada ${lu}` : `Tidak ada ${lu} yang cocok`}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">
                                            {units.length === 0
                                                ? `${labelUnit} menjadi pemilik indikator kinerja, rencana aksi, dan kewenangan operasional SAKIP.`
                                                : 'Coba ubah kata kunci atau status pada filter.'}
                                        </p>
                                    </TableCell>
                                </TableRow>
                            )}
                            {filteredUnits.map((unit) => (
                                <TableRow key={unit.id} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                    <TableCell className="col-span-2 min-w-0 break-words p-0 text-sm font-semibold md:px-4 md:py-3.5">
                                        {unit.nama}
                                    </TableCell>
                                    {JUMLAH.map(([key, label]) => (
                                        <TableCell key={key} className="min-w-0 p-0 tabular-nums md:px-4 md:py-3.5 md:text-right">
                                            <span className="mr-2 text-muted md:hidden">{label}</span>
                                            {unit[key]}
                                        </TableCell>
                                    ))}
                                    <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5">
                                        <Badge variant={unit.is_active ? 'success' : 'muted'}>
                                            {unit.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="min-w-0 p-0 md:px-4 md:py-3.5 md:text-right">
                                        <div className="flex min-w-0 flex-wrap items-center justify-end gap-1.5">
                                            {unit.can.update && (
                                                <>
                                                    <Tooltip content={unit.is_active ? 'Nonaktifkan' : 'Aktifkan'} align="right">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleOpenToggle(unit)}
                                                            aria-label={`${unit.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${unit.nama}`}
                                                            className="h-8 w-8 p-0"
                                                        >
                                                            <Power className="h-4 w-4" aria-hidden="true" />
                                                        </Button>
                                                    </Tooltip>
                                                    <Tooltip content="Edit" align="right">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleOpenEdit(unit)}
                                                            aria-label={`Edit ${unit.nama}`}
                                                            className="h-8 w-8 p-0"
                                                        >
                                                            <Pencil className="h-4 w-4" aria-hidden="true" />
                                                        </Button>
                                                    </Tooltip>
                                                </>
                                            )}
                                            {unit.can.delete ? (
                                                <Tooltip content="Hapus" align="right">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleOpenDelete(unit)}
                                                        aria-label={`Hapus ${unit.nama}`}
                                                        className="h-8 w-8 border-danger/30 p-0 text-danger hover:bg-danger/10 focus:ring-danger/25"
                                                    >
                                                        <Trash2 className="h-4 w-4" aria-hidden="true" />
                                                    </Button>
                                                </Tooltip>
                                            ) : (
                                                !unit.is_deletable && (
                                                    <Tooltip content={ALASAN_TIDAK_DAPAT_DIHAPUS} align="right">
                                                        {/* aria-disabled, bukan disabled: tombol tetap dapat difokus agar alasannya terbaca. */}
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            aria-disabled="true"
                                                            aria-label={`${unit.nama} tidak dapat dihapus: ${ALASAN_TIDAK_DAPAT_DIHAPUS}`}
                                                            className="h-8 w-8 cursor-not-allowed p-0 text-muted opacity-50 hover:bg-surface"
                                                        >
                                                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                                                        </Button>
                                                    </Tooltip>
                                                )
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            </div>

            <Modal
                isOpen={isCreateOpen}
                onClose={() => setIsCreateOpen(false)}
                title={`Tambah ${lu}`}
                size="lg"
                footer={
                    <>
                        <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)} disabled={createForm.processing}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="unit-create-form"
                            disabled={createForm.processing || Boolean(recovery.recovery)}
                            isLoading={createForm.processing}
                        >
                            Simpan {lu}
                        </Button>
                    </>
                }
            >
                <form id="unit-create-form" onSubmit={handleCreateSubmit} className="space-y-4">
                    <Input
                        id="create_unit_nama"
                        label={`Nama ${lu}`}
                        value={createForm.data.nama}
                        onChange={(e) => createForm.setData('nama', e.target.value)}
                        placeholder="Contoh: Bagian Umum"
                        autoComplete="off"
                        required
                        error={createForm.errors.nama}
                    />
                    <StatusCheckbox
                        id="create_unit_aktif"
                        checked={createForm.data.status === 'aktif'}
                        error={createForm.errors.status}
                        onChange={(checked) => {
                            createForm.setData('status', checked ? 'aktif' : 'nonaktif');
                            if (createForm.errors.status) createForm.clearErrors('status');
                        }}
                    />
                    <AuthRecoveryNotice recovery={recovery.recovery} pending={createForm.processing} />
                </form>
            </Modal>

            <Modal
                isOpen={!!editingUnit}
                onClose={() => setEditingUnit(null)}
                title={`Edit ${lu}`}
                size="lg"
                footer={
                    <>
                        <Button type="button" variant="outline" onClick={() => setEditingUnit(null)} disabled={editForm.processing}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="unit-edit-form"
                            disabled={editForm.processing || Boolean(recovery.recovery)}
                            isLoading={editForm.processing}
                        >
                            Simpan perubahan
                        </Button>
                    </>
                }
            >
                <form id="unit-edit-form" onSubmit={handleEditSubmit} className="space-y-4">
                    {editConflict && <ErrorAlert message={editConflict} />}
                    <Input
                        id="edit_unit_nama"
                        label={`Nama ${lu}`}
                        value={editForm.data.nama}
                        onChange={(e) => editForm.setData('nama', e.target.value)}
                        autoComplete="off"
                        required
                        error={editForm.errors.nama}
                    />
                    <StatusCheckbox
                        id="edit_unit_aktif"
                        checked={editForm.data.status === 'aktif'}
                        error={editForm.errors.status}
                        onChange={(checked) => {
                            editForm.setData('status', checked ? 'aktif' : 'nonaktif');
                            if (editForm.errors.status) editForm.clearErrors('status');
                        }}
                    />
                    <AuthRecoveryNotice recovery={recovery.recovery} pending={editForm.processing} />
                </form>
            </Modal>

            <Modal
                isOpen={!!togglingUnit}
                onClose={handleCloseToggle}
                showCloseButton={!isToggling}
                title={togglingUnit?.is_active ? `Nonaktifkan ${lu}?` : `Aktifkan ${lu}?`}
                size="md"
                footer={
                    <>
                        <Button type="button" variant="outline" onClick={handleCloseToggle} disabled={isToggling}>
                            Batal
                        </Button>
                        <Button type="button" onClick={handleToggleStatus} disabled={Boolean(recovery.recovery)} isLoading={isToggling}>
                            {togglingUnit?.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    {toggleError && <ErrorAlert message={toggleError} />}
                    <p className="text-sm text-ink">
                        Status <span className="font-semibold">{togglingUnit?.nama}</span> akan diubah menjadi{' '}
                        {togglingUnit?.is_active ? 'Nonaktif' : 'Aktif'}.
                    </p>
                    <AuthRecoveryNotice recovery={recovery.recovery} pending={isToggling} />
                </div>
            </Modal>

            <AuditReasonModal
                open={!!deletingUnit}
                title={`Hapus ${deletingUnit?.nama ?? lu}?`}
                description={`Hanya ${lu} tanpa keterkaitan data yang dapat dihapus. Penghapusan bersifat permanen.`}
                reason={deleteReason}
                error={deleteError || undefined}
                busy={isDeleting}
                submitDisabled={deleteReason.trim().length < 5 || Boolean(recovery.recovery)}
                notice={<AuthRecoveryNotice recovery={recovery.recovery} pending={isDeleting} />}
                confirmLabel="Hapus permanen"
                destructive
                onReasonChange={(value) => {
                    setDeleteReason(value);
                    if (value.trim().length >= 5) setDeleteError('');
                }}
                onClose={handleCloseDelete}
                onConfirm={handleDeleteSubmit}
            />
        </AuthenticatedLayout>
    );
}

function ErrorAlert({ message, onDismiss }: { message: string; onDismiss?: () => void }) {
    return (
        <div role="alert" className="flex items-start gap-2.5 rounded-lg border border-danger/30 bg-danger/5 p-3.5 text-sm text-danger-dark">
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
            <p className="flex-1 font-medium">{message}</p>
            {onDismiss && (
                <button
                    type="button"
                    onClick={onDismiss}
                    aria-label="Tutup pesan kesalahan"
                    className="rounded p-0.5 transition-colors hover:bg-danger/10 focus:outline-none focus:ring-2 focus:ring-danger/30"
                >
                    <X className="h-4 w-4" aria-hidden="true" />
                </button>
            )}
        </div>
    );
}

function StatusCheckbox({ id, checked, error, onChange }: { id: string; checked: boolean; error?: string; onChange: (checked: boolean) => void }) {
    return (
        <div>
            <label htmlFor={id} className="flex items-center gap-2 text-sm text-ink">
                <input
                    id={id}
                    type="checkbox"
                    checked={checked}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? `${id}-error` : undefined}
                    onChange={(e) => onChange(e.target.checked)}
                    className="h-4 w-4 accent-primary"
                />
                Aktif
            </label>
            {error && (
                <p id={`${id}-error`} role="alert" className="mt-1 text-xs text-danger">
                    {error}
                </p>
            )}
        </div>
    );
}
