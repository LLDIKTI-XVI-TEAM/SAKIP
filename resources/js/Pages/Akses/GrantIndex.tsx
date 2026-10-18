import { useRef, useState, type ChangeEvent, type FormEvent, type MouseEvent } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { KeyRound, Plus, Trash2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardHeader } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { Pagination, type PaginationLinkItem } from '@/Components/Pagination';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Textarea } from '@/Components/Textarea';
import { Tooltip } from '@/Components/Tooltip';
import { GrantUserAutocomplete } from '@/Components/Access/GrantUserAutocomplete';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';

interface GrantItem {
    id: string;
    user_name: string;
    user_email: string;
    user_roles: string[];
    permission_kode: string;
    permission_label: string;
    unit_nama: string | null;
    alasan: string;
    diberikan_oleh_nama: string;
    created_at: string | null;
    /** Alasan grant tidak berlaku menurut kriteria resolver server; kosong berarti berlaku. */
    tidak_berlaku: string[];
}

interface GrantIndexProps {
    grants: { data: GrantItem[]; links: PaginationLinkItem[]; from: number | null; to: number | null; total: number };
    filters: { search: string; unit_id: string };
    units: { id: string; nama: string }[];
    unitPermissions: { id: string; kode: string; label: string }[];
    can: { create_grant: boolean; revoke_grant: boolean };
}

const cellClass = 'min-w-0 p-0 md:px-4 md:py-3.5';
const mobileLabel = 'mr-2 text-xs text-muted md:hidden';
const failureClass = 'rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-ink';

// Pesan server (mis. penolakan wewenang atau grant di luar cakupan unit) lebih spesifik daripada teks baku.
function pesanGagal(response: { status: number; data: unknown }): string {
    const { data } = response;
    if (typeof data === 'object' && data !== null && 'message' in data && typeof data.message === 'string' && data.message !== '') return data.message;
    return response.status === 403
        ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.'
        : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.';
}

const belumDiketahui = (aksi: string, sebab: string) => `${sebab} Hasil ${aksi} izin belum diketahui. Periksa daftar terbaru sebelum mencoba kembali.`;
const checkboxClass = 'h-4 w-4 shrink-0 accent-primary';

export default function GrantIndex({ grants, filters, units, unitPermissions, can }: GrantIndexProps) {
    const formatTanggal = useFormatTanggal();
    const recovery = useAuthRecovery();
    const [search, setSearch] = useState(filters.search);
    const [unitId, setUnitId] = useState(filters.unit_id);
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [createFailure, setCreateFailure] = useState<string | null>(null);
    const [grantToRevoke, setGrantToRevoke] = useState<GrantItem | null>(null);
    const [revokeFailure, setRevokeFailure] = useState<string | null>(null);
    const createTrigger = useRef<HTMLButtonElement | null>(null);
    const createForm = useForm<{ user_id: string; permission_ids: string[]; unit_id: string; alasan: string }>({ user_id: '', permission_ids: [], unit_id: '', alasan: '' });
    const revokeForm = useForm({ alasan: '' });
    const hasFilter = filters.search !== '' || filters.unit_id !== 'all';
    const dipilih = createForm.data.permission_ids;
    const semuaDipilih = unitPermissions.length > 0 && unitPermissions.every((permission) => dipilih.includes(permission.id));
    // Validasi per butir datang sebagai permission_ids.N; tampil di grup checkbox yang sama.
    const createErrors = createForm.errors as Record<string, string | undefined>;
    const izinError = createErrors.permission_ids ?? Object.entries(createErrors).find(([key]) => key.startsWith('permission_ids.'))?.[1];

    // Urutan katalog dipertahankan agar daftar yang dikirim tidak bergantung pada urutan klik.
    const toggleIzin = (id: string) => createForm.setData('permission_ids', unitPermissions
        .map((permission) => permission.id)
        .filter((pid) => (pid === id ? !dipilih.includes(id) : dipilih.includes(pid))));

    const filterBy = (nextSearch: string, nextUnitId: string) => {
        router.get('/akses/grant', {
            search: nextSearch.trim() || undefined,
            unit_id: nextUnitId !== 'all' ? nextUnitId : undefined,
        }, { preserveState: true, replace: true, preserveScroll: true });
    };

    const changeUnit = (event: ChangeEvent<HTMLSelectElement>) => {
        setUnitId(event.target.value);
        filterBy(search, event.target.value);
    };

    const resetFilters = () => {
        setSearch('');
        setUnitId('all');
        router.get('/akses/grant', {}, { preserveState: true, replace: true });
    };

    const openCreate = (event: MouseEvent<HTMLButtonElement>) => {
        createTrigger.current = event.currentTarget;
        createForm.reset();
        createForm.clearErrors();
        setCreateFailure(null);
        setIsCreateOpen(true);
    };

    const closeCreate = () => {
        if (createForm.processing) return;
        setIsCreateOpen(false);
        createTrigger.current?.focus();
    };

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        if (createForm.processing || recovery.recovery) return;
        setCreateFailure(null);
        createForm.post('/akses/grant', {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
                createTrigger.current?.focus();
            },
            onCancel: () => setCreateFailure(belumDiketahui('pemberian', 'Permintaan dibatalkan.')),
            onNetworkError: () => {
                setCreateFailure(belumDiketahui('pemberian', 'Koneksi terputus.'));
                return false;
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: '/akses/grant', mutation: true })) return false;
                setCreateFailure(pesanGagal(response));
                return false;
            },
        });
    };

    const openRevoke = (grant: GrantItem) => {
        revokeForm.reset();
        revokeForm.clearErrors();
        setRevokeFailure(null);
        setGrantToRevoke(grant);
    };

    const submitRevoke = () => {
        if (!grantToRevoke || revokeForm.processing || recovery.recovery) return;
        const path = `/akses/grant/${grantToRevoke.id}`;
        setRevokeFailure(null);
        revokeForm.delete(path, {
            preserveScroll: true,
            onSuccess: () => setGrantToRevoke(null),
            onCancel: () => setRevokeFailure(belumDiketahui('pencabutan', 'Permintaan dibatalkan.')),
            onNetworkError: () => {
                setRevokeFailure(belumDiketahui('pencabutan', 'Koneksi terputus.'));
                return false;
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'delete', path, mutation: true })) return false;
                setRevokeFailure(pesanGagal(response));
                return false;
            },
        });
    };

    return (
        <AuthenticatedLayout title="Izin Unit" breadcrumbs={[{ label: 'Izin Unit' }]}>
            <Head title="Izin Unit" />
            <div className="space-y-5">
                <Card className="overflow-visible">
                    <form
                        role="search"
                        aria-label="Filter izin unit"
                        onSubmit={(event) => { event.preventDefault(); filterBy(search, unitId); }}
                        className="grid gap-3 p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,16rem)_auto] md:items-end"
                    >
                        <Input id="grant-search" type="search" label="Cari izin unit" placeholder="Nama, email, izin, atau unit" maxLength={100} value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Select id="grant-unit-filter" label="Filter unit" value={unitId} onChange={changeUnit}>
                            <option value="all">Semua unit</option>
                            {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                        </Select>
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" variant="outline">Cari</Button>
                            {hasFilter && <Button type="button" variant="ghost" onClick={resetFilters}>Hapus filter</Button>}
                        </div>
                    </form>
                </Card>

                <Card>
                    <CardHeader className="items-start px-4 py-4">
                        <div className="min-w-0">
                            <h2 className="text-base font-semibold tracking-tight text-ink">Daftar izin unit</h2>
                            <p className="mt-0.5 max-w-2xl text-xs text-muted">Izin unit (grant) memberi pengguna hak isi pada unit tertentu tanpa mengubah peran utamanya. Pemberian dan pencabutan tercatat di jejak audit.</p>
                        </div>
                        {can.create_grant && <Button type="button" size="sm" className="shrink-0" onClick={openCreate}>
                            <Plus className="h-4 w-4" aria-hidden="true" />
                            Beri izin unit
                        </Button>}
                    </CardHeader>
                    {/* Grid mobile: setiap izin tampil sebagai kartu ringkas tanpa scroll horizontal. */}
                    <Table wrapperClassName="overflow-x-visible" className="block md:table">
                        <caption className="sr-only">Daftar izin unit</caption>
                        <TableHeader className="max-md:sr-only">
                            <TableRow>
                                <TableHead className="whitespace-normal">Pengguna</TableHead>
                                <TableHead>Izin</TableHead>
                                <TableHead>Unit</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="whitespace-normal">Pemberian</TableHead>
                                {can.revoke_grant && <TableHead className="text-right">Aksi</TableHead>}
                            </TableRow>
                        </TableHeader>
                        <TableBody className="block md:table-row-group">
                            {grants.data.length === 0 && <TableRow className="block md:table-row">
                                <TableCell colSpan={can.revoke_grant ? 6 : 5} className="block py-12 text-center md:table-cell">
                                    <KeyRound className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                    <p className="mt-2 text-sm font-semibold text-ink">{hasFilter ? 'Tidak ada izin unit yang sesuai' : 'Belum ada izin unit'}</p>
                                    <p className="mt-1 text-xs text-muted">{hasFilter ? 'Coba kata kunci atau unit lain.' : 'Izin unit tampil di sini setelah diberikan kepada pengguna.'}</p>
                                </TableCell>
                            </TableRow>}
                            {grants.data.map((grant) => <TableRow key={grant.id} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                <TableCell className={`col-span-2 ${cellClass}`}>
                                    <p className="break-words text-sm font-semibold text-ink">{grant.user_name}</p>
                                    <p className="break-all text-xs text-muted">{grant.user_email}</p>
                                    <div className="mt-1">
                                        {grant.user_roles.length > 0
                                            ? <span className="text-xs text-muted">{grant.user_roles.join(', ')}</span>
                                            : <Badge variant="warning">Belum memiliki peran</Badge>}
                                    </div>
                                </TableCell>
                                <TableCell className={`col-span-2 ${cellClass}`}>
                                    <span className={mobileLabel}>Izin</span>
                                    <span className="break-words text-sm text-ink">{grant.permission_label}</span>
                                </TableCell>
                                <TableCell className={cellClass}>
                                    <span className={mobileLabel}>Unit</span>
                                    <span className="break-words text-sm text-ink">{grant.unit_nama ?? '—'}</span>
                                </TableCell>
                                <TableCell className={cellClass}>
                                    <span className={mobileLabel}>Status</span>
                                    {grant.tidak_berlaku.length === 0
                                        ? <Badge variant="success">Berlaku</Badge>
                                        : <>
                                            <Badge variant="warning">Tidak berlaku</Badge>
                                            <p className="mt-1 text-xs text-muted">{grant.tidak_berlaku.join(' · ')}</p>
                                        </>}
                                </TableCell>
                                <TableCell className={`col-span-2 ${cellClass}`}>
                                    <p className="whitespace-pre-wrap break-words text-sm text-ink"><span className={mobileLabel}>Alasan</span>{grant.alasan}</p>
                                    <p className="mt-1 text-xs text-muted">
                                        {grant.diberikan_oleh_nama} · {grant.created_at ? <time dateTime={grant.created_at}>{formatTanggal(grant.created_at, { withTime: true })}</time> : '—'}
                                    </p>
                                </TableCell>
                                {can.revoke_grant && <TableCell className={`col-span-2 ${cellClass} md:text-right`}>
                                    <div className="flex justify-end">
                                        <Tooltip content="Cabut izin unit" align="right">
                                            <Button type="button" size="sm" variant="outline" className="h-8 w-8 p-0 text-danger hover:bg-danger/10" aria-label={`Cabut izin unit ${grant.user_name}`} onClick={() => openRevoke(grant)}>
                                                <Trash2 className="h-4 w-4" aria-hidden="true" />
                                            </Button>
                                        </Tooltip>
                                    </div>
                                </TableCell>}
                            </TableRow>)}
                        </TableBody>
                    </Table>
                </Card>
                <Pagination links={grants.links} from={grants.from} to={grants.to} total={grants.total} ariaLabel="Halaman izin unit" />
            </div>

            <Modal
                isOpen={isCreateOpen}
                onClose={closeCreate}
                size="lg"
                title="Beri izin unit"
                description="Pengguna mendapat hak isi pada unit yang dipilih tanpa mengubah peran utamanya."
                footer={<>
                    <Button type="button" variant="outline" disabled={createForm.processing} onClick={closeCreate}>Batal</Button>
                    <Button type="submit" form="grant-create-form" isLoading={createForm.processing} disabled={Boolean(recovery.recovery) || dipilih.length === 0}>Simpan</Button>
                </>}
            >
                <form id="grant-create-form" onSubmit={submitCreate} className="space-y-4" aria-busy={createForm.processing}>
                    {createFailure && <p role="alert" className={failureClass}>{createFailure}</p>}
                    <GrantUserAutocomplete
                        id="grant-user"
                        label="Pengguna"
                        value={createForm.data.user_id}
                        onChange={(id) => createForm.setData('user_id', id)}
                        disabled={createForm.processing}
                        error={createForm.errors.user_id}
                    />
                    <fieldset disabled={createForm.processing} aria-describedby={izinError ? 'grant-izin-error' : undefined}>
                        <legend className="mb-1.5 text-sm font-semibold text-ink">Izin <span className="text-danger" aria-hidden="true">*</span></legend>
                        <div className="rounded-lg border border-border p-3">
                            <label className="flex items-center gap-2 border-b border-border pb-2 text-sm font-medium text-ink">
                                <input type="checkbox" className={checkboxClass} checked={semuaDipilih} onChange={() => createForm.setData('permission_ids', semuaDipilih ? [] : unitPermissions.map((permission) => permission.id))} />
                                Pilih semua izin
                            </label>
                            <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                {unitPermissions.map((permission) => <label key={permission.id} className="flex items-center gap-2 text-sm text-ink">
                                    <input type="checkbox" className={checkboxClass} checked={dipilih.includes(permission.id)} onChange={() => toggleIzin(permission.id)} />
                                    {permission.label}
                                </label>)}
                            </div>
                        </div>
                        {izinError && <p id="grant-izin-error" role="alert" className="mt-1.5 text-xs font-medium text-danger">{izinError}</p>}
                    </fieldset>
                    <Select id="grant-unit" label="Unit" required value={createForm.data.unit_id} onChange={(event) => createForm.setData('unit_id', event.target.value)} disabled={createForm.processing} error={createForm.errors.unit_id}>
                        <option value="" disabled>Pilih unit</option>
                        {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}</option>)}
                    </Select>
                    <Textarea
                        id="grant-alasan"
                        label="Alasan pemberian (opsional)"
                        maxLength={1000}
                        rows={3}
                        placeholder="Contoh: penugasan pengisian capaian lintas pokja sesuai SK."
                        value={createForm.data.alasan}
                        onChange={(event) => createForm.setData('alasan', event.target.value)}
                        disabled={createForm.processing}
                        error={createForm.errors.alasan}
                        helperText="Boleh dikosongkan. Maksimal 1.000 karakter. Tercatat di jejak audit."
                    />
                    <AuthRecoveryNotice recovery={recovery.recovery} pending={createForm.processing} />
                </form>
            </Modal>

            {/* Tetap terpasang agar penutupan dialog native mengembalikan fokus ke tombol pemicu. */}
            <AuditReasonModal
                open={grantToRevoke !== null}
                title={`Cabut izin unit ${grantToRevoke?.user_name ?? ''}?`}
                description={grantToRevoke ? `${grantToRevoke.permission_label} pada ${grantToRevoke.unit_nama ?? 'unit ini'}. Pengguna tidak lagi memakai izin ini pada unit tersebut.` : ''}
                reason={revokeForm.data.alasan}
                error={revokeForm.errors.alasan}
                busy={revokeForm.processing}
                submitDisabled={revokeForm.data.alasan.trim() === '' || Boolean(recovery.recovery)}
                notice={<>
                    {revokeFailure && <p role="alert" className={`mb-4 ${failureClass}`}>{revokeFailure}</p>}
                    <AuthRecoveryNotice recovery={recovery.recovery} pending={revokeForm.processing} />
                </>}
                placeholder="Contoh: penugasan pengisian triwulan telah selesai."
                confirmLabel="Cabut izin"
                destructive
                onReasonChange={(value) => revokeForm.setData('alasan', value)}
                onClose={() => { if (!revokeForm.processing) setGrantToRevoke(null); }}
                onConfirm={submitRevoke}
            />
        </AuthenticatedLayout>
    );
}
