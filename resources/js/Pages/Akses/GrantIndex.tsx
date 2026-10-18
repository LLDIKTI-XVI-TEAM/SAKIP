import { useRef, useState, type ChangeEvent, type FormEvent, type MouseEvent } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, KeyRound, Pencil, Plus, Trash2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
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
import { CabutSemuaIzinUnitModal } from '@/Components/Access/CabutSemuaIzinUnitModal';
import { GrantUserAutocomplete } from '@/Components/Access/GrantUserAutocomplete';
import { IzinUnitCheckboxes } from '@/Components/Access/IzinUnitCheckboxes';
import { KelolaIzinUnitModal } from '@/Components/Access/KelolaIzinUnitModal';
import { belumDiketahui, failureClass, galatIzin, pesanGagal } from '@/Components/Access/izinUnitFeedback';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { KelompokIzinUnit, PilihanIzin } from '@/types/izinUnit';

interface GrantIndexProps {
    grants: { data: KelompokIzinUnit[]; links: PaginationLinkItem[]; from: number | null; to: number | null; total: number };
    filters: { search: string; unit_id: string };
    units: { id: string; nama: string }[];
    unitPermissions: PilihanIzin[];
    can: { create_grant: boolean; kelola_grant: boolean; revoke_grant: boolean };
}

const cellClass = 'min-w-0 p-0 md:px-4 md:py-3.5';
const mobileLabel = 'mr-2 text-xs text-muted md:hidden';
const iconLinkClass = 'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-surface text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25';

export default function GrantIndex({ grants, filters, units, unitPermissions, can }: GrantIndexProps) {
    const recovery = useAuthRecovery();
    const [search, setSearch] = useState(filters.search);
    const [unitId, setUnitId] = useState(filters.unit_id);
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [createFailure, setCreateFailure] = useState<string | null>(null);
    const [dikelola, setDikelola] = useState<KelompokIzinUnit | null>(null);
    const [dicabut, setDicabut] = useState<KelompokIzinUnit | null>(null);
    const createTrigger = useRef<HTMLButtonElement | null>(null);
    const createForm = useForm<{ user_id: string; permission_ids: string[]; unit_id: string; alasan: string }>({ user_id: '', permission_ids: [], unit_id: '', alasan: '' });
    const hasFilter = filters.search !== '' || filters.unit_id !== 'all';

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
                            <p className="mt-0.5 max-w-2xl text-xs text-muted">Izin unit (grant) memberi pengguna hak isi pada unit tertentu tanpa mengubah peran utamanya. Setiap baris memuat seluruh izin satu pengguna pada satu unit; rincian pemberian ada di halaman detail.</p>
                        </div>
                        {can.create_grant && <Button type="button" size="sm" className="shrink-0" onClick={openCreate}>
                            <Plus className="h-4 w-4" aria-hidden="true" />
                            Beri izin unit
                        </Button>}
                    </CardHeader>
                    {/* Grid mobile: setiap pengguna-unit tampil sebagai kartu ringkas tanpa scroll horizontal. */}
                    <Table wrapperClassName="overflow-x-visible" className="block md:table">
                        <caption className="sr-only">Daftar izin unit per pengguna dan unit</caption>
                        <TableHeader className="max-md:sr-only">
                            <TableRow>
                                <TableHead className="whitespace-normal">Pengguna</TableHead>
                                <TableHead>Unit</TableHead>
                                <TableHead>Izin</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="block md:table-row-group">
                            {grants.data.length === 0 && <TableRow className="block md:table-row">
                                <TableCell colSpan={5} className="block py-12 text-center md:table-cell">
                                    <KeyRound className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                    {/* Halaman kosong padahal total > 0: nomor halaman melewati halaman terakhir, mis. baris terakhirnya baru dicabut. */}
                                    {grants.total > 0 ? <>
                                        <p className="mt-2 text-sm font-semibold text-ink">Tidak ada izin unit di halaman ini.</p>
                                        <Button type="button" variant="outline" size="sm" className="mt-4" onClick={() => filterBy(filters.search, filters.unit_id)}>Ke halaman pertama</Button>
                                    </> : <>
                                        <p className="mt-2 text-sm font-semibold text-ink">{hasFilter ? 'Tidak ada izin unit yang sesuai' : 'Belum ada izin unit'}</p>
                                        <p className="mt-1 text-xs text-muted">{hasFilter ? 'Coba kata kunci atau unit lain.' : 'Izin unit tampil di sini setelah diberikan kepada pengguna.'}</p>
                                    </>}
                                </TableCell>
                            </TableRow>}
                            {grants.data.map((kelompok) => {
                                const sasaran = `${kelompok.user_name} di ${kelompok.unit_nama ?? 'unit ini'}`;
                                return <TableRow key={kelompok.key} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                    <TableCell className={`col-span-2 ${cellClass}`}>
                                        <p className="break-words text-sm font-semibold text-ink">{kelompok.user_name}</p>
                                        <p className="break-all text-xs text-muted">{kelompok.user_email}</p>
                                        <div className="mt-1">
                                            {kelompok.user_roles.length > 0
                                                ? <span className="text-xs text-muted">{kelompok.user_roles.join(', ')}</span>
                                                : <Badge variant="warning">Belum memiliki peran</Badge>}
                                        </div>
                                    </TableCell>
                                    <TableCell className={`col-span-2 ${cellClass}`}>
                                        <span className={mobileLabel}>Unit</span>
                                        <span className="break-words text-sm text-ink">{kelompok.unit_nama ?? '—'}</span>
                                    </TableCell>
                                    <TableCell className={`col-span-2 ${cellClass}`}>
                                        <span className={mobileLabel}>Izin</span>
                                        <ul aria-label={`Izin ${kelompok.user_name}`} className="mt-1 flex flex-wrap gap-1.5 md:mt-0">
                                            {kelompok.izin.map((izin) => <li key={izin.grant_id}>
                                                <Badge variant={izin.aktif ? 'primary' : 'muted'}>{izin.aktif ? izin.label : `${izin.label} (nonaktif)`}</Badge>
                                            </li>)}
                                        </ul>
                                    </TableCell>
                                    <TableCell className={cellClass}>
                                        <span className={mobileLabel}>Status</span>
                                        {kelompok.tidak_berlaku.length === 0
                                            ? <Badge variant="success">Berlaku</Badge>
                                            : <>
                                                <Badge variant="warning">Tidak berlaku</Badge>
                                                <p className="mt-1 text-xs text-muted">{kelompok.tidak_berlaku.join(' · ')}</p>
                                            </>}
                                    </TableCell>
                                    <TableCell className={`${cellClass} md:text-right`}>
                                        <div className="flex flex-wrap justify-end gap-1.5">
                                            <Tooltip content="Lihat detail" align="right">
                                                <Link href={`/akses/grant/${kelompok.user_id}/${kelompok.unit_id}`} aria-label={`Lihat detail izin unit ${sasaran}`} className={iconLinkClass}>
                                                    <Eye className="h-4 w-4" aria-hidden="true" />
                                                </Link>
                                            </Tooltip>
                                            {can.kelola_grant && <Tooltip content="Kelola izin" align="right">
                                                <Button type="button" size="sm" variant="outline" className="h-8 w-8 p-0" aria-label={`Kelola izin unit ${sasaran}`} onClick={() => setDikelola(kelompok)}>
                                                    <Pencil className="h-4 w-4" aria-hidden="true" />
                                                </Button>
                                            </Tooltip>}
                                            {can.revoke_grant && <Tooltip content="Cabut semua izin" align="right">
                                                <Button type="button" size="sm" variant="outline" className="h-8 w-8 p-0 text-danger hover:bg-danger/10" aria-label={`Cabut semua izin unit ${sasaran}`} onClick={() => setDicabut(kelompok)}>
                                                    <Trash2 className="h-4 w-4" aria-hidden="true" />
                                                </Button>
                                            </Tooltip>}
                                        </div>
                                    </TableCell>
                                </TableRow>;
                            })}
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
                    <Button type="submit" form="grant-create-form" isLoading={createForm.processing} disabled={Boolean(recovery.recovery) || createForm.data.permission_ids.length === 0}>Simpan</Button>
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
                    <IzinUnitCheckboxes
                        id="grant-izin"
                        options={unitPermissions}
                        value={createForm.data.permission_ids}
                        onChange={(ids) => createForm.setData('permission_ids', ids)}
                        disabled={createForm.processing}
                        error={galatIzin(createForm.errors as Record<string, string | undefined>)}
                    />
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

            <KelolaIzinUnitModal kelompok={dikelola} options={unitPermissions} onClose={() => setDikelola(null)} />
            <CabutSemuaIzinUnitModal kelompok={dicabut} onClose={() => setDicabut(null)} />
        </AuthenticatedLayout>
    );
}
