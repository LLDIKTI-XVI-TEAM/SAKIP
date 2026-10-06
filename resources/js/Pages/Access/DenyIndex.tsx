import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle, Plus, Search, ShieldAlert, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/Components/Button';
import { Badge } from '@/Components/Badge';
import { Card, CardContent } from '@/Components/Card';
import { Pagination } from '@/Components/Pagination';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/Components/Table';
import { EmptyState } from '@/Components/EmptyState';
import { DenyMutationDialog } from '@/Components/Access/DenyMutationDialog';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { SharedPageProps } from '@/types/auth';
import type { DenyIndexProps, DenyRow } from '@/types/deny';

export default function DenyIndex({ denies, pagination, filters, can }: DenyIndexProps) {
    const formatTanggal = useFormatTanggal();
    const {
        props: { auth },
        flash,
    } = usePage<SharedPageProps>();
    const [dismissed, setDismissed] = useState<typeof flash | null>(null);
    const [modal, setModal] = useState<{ deny: DenyRow | null } | null>(null);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const title = useRef<HTMLHeadingElement>(null);
    const search = useForm({ q: filters.q });
    const message =
        flash.denyStatus === 'created'
            ? 'Deny berhasil ditambahkan.'
            : flash.denyStatus === 'revoked'
            ? 'Deny berhasil dicabut.'
            : null;

    useEffect(() => {
        if (modal || !trigger.current) return;
        if (trigger.current.isConnected) trigger.current.focus();
        else title.current?.focus();
    }, [modal]);

    return (
        <AuthenticatedLayout
            title="Pembatasan Izin"
            breadcrumbs={[
                { label: 'Manajemen Akses' },
                { label: 'Pembatasan Izin' }
            ]}
        >
            <Head title="Pembatasan Izin" />
            {message && dismissed !== flash && (
                <div className="fixed bottom-4 right-4 z-50 flex w-[calc(100%-2rem)] max-w-sm items-start gap-3 rounded-xl border border-success/30 bg-surface p-4 shadow-xl">
                    <CheckCircle aria-hidden="true" className="h-5 w-5 shrink-0 text-success" />
                    <p role="status" className="flex-1 text-sm font-medium text-ink">
                        {message}
                    </p>
                    <button
                        type="button"
                        aria-label="Tutup notifikasi"
                        onClick={() => setDismissed(flash)}
                        className="-m-1 rounded-lg p-2 text-muted hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                        <X aria-hidden="true" className="h-4 w-4" />
                    </button>
                </div>
            )}

            <h2
                ref={title}
                tabIndex={-1}
                className="sr-only"
            >
                Pembatasan izin eksplisit
            </h2>

            <div className="space-y-6">
                {/* Filter & Action Bar */}
                <Card>
                    <CardContent className="p-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                        <form
                            role="search"
                            className="relative flex-1"
                            onSubmit={(event) => {
                                event.preventDefault();
                                if (!search.processing)
                                    search.get('/akses/deny', { preserveState: false });
                            }}
                        >
                            <Search
                                className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
                                aria-hidden="true"
                            />
                            <input
                                id="deny-list-search"
                                type="search"
                                maxLength={100}
                                value={search.data.q}
                                onChange={(event) => search.setData('q', event.target.value)}
                                placeholder="Cari nama atau email pengguna..."
                                aria-label="Cari nama atau email pengguna"
                                className="w-full pl-9 pr-4 py-2 text-xs rounded-lg border border-border bg-surface text-ink placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors"
                            />
                        </form>

                        <div className="flex items-center gap-2.5 self-end md:self-auto">
                            {filters.q && (
                                <Link
                                    href="/akses/deny"
                                    className="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink hover:bg-soft focus:ring-2 focus:ring-primary transition-colors h-8"
                                >
                                    Reset
                                </Link>
                            )}
                            {can.manageDeny && (
                                <Button
                                    type="button"
                                    variant="primary"
                                    size="sm"
                                    className="gap-1.5 shadow-xs shrink-0"
                                    onClick={(event) => {
                                        trigger.current = event.currentTarget;
                                        setModal({ deny: null });
                                    }}
                                >
                                    <Plus className="h-4 w-4" aria-hidden="true" />
                                    Tambah deny
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Main Data Table */}
                <Card className="overflow-hidden border-border bg-surface shadow-xs">
                    {denies.length === 0 ? (
                        <div className="p-8">
                            <EmptyState
                                icon={ShieldAlert}
                                title="Tidak ada pembatasan izin yang sesuai"
                                description="Tidak ditemukan riwayat deny aktif dengan kriteria pencarian saat ini."
                                variant="inline"
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12 text-center whitespace-nowrap">NO</TableHead>
                                        <TableHead className="min-w-[220px] whitespace-nowrap">PENGGUNA</TableHead>
                                        <TableHead className="min-w-[200px] whitespace-nowrap">IZIN & LINGKUP</TableHead>
                                        <TableHead className="min-w-[240px] whitespace-nowrap">ALASAN AUDIT</TableHead>
                                        <TableHead className="min-w-[180px] whitespace-nowrap">DITETAPKAN OLEH</TableHead>
                                        {can.manageDeny && (
                                            <TableHead className="text-right w-28 whitespace-nowrap">AKSI</TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {denies.map((deny, index) => {
                                        const rowNumber = (pagination.current_page - 1) * 20 + index + 1;
                                        return (
                                            <TableRow
                                                key={deny.id}
                                                className="transition-colors hover:bg-soft/40"
                                            >
                                                <TableCell className="text-center font-mono text-xs text-muted">
                                                    {rowNumber}
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <h3 className="break-words font-semibold text-ink text-sm">
                                                            {deny.user.nama}
                                                            {deny.user.status !== 'aktif' && (
                                                                <span className="font-normal text-muted text-xs">
                                                                    {' '}(nonaktif)
                                                                </span>
                                                            )}
                                                        </h3>
                                                        <p className="mt-0.5 text-xs text-muted break-all">
                                                            {deny.user.email}
                                                        </p>
                                                        <Badge
                                                            variant={
                                                                deny.user.status === 'aktif' ? 'success' : 'muted'
                                                            }
                                                            size="sm"
                                                            dot
                                                            className="mt-1"
                                                        >
                                                            {deny.user.status === 'aktif'
                                                                ? 'Akun aktif'
                                                                : 'Nonaktif'}
                                                        </Badge>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="space-y-1">
                                                        <div className="flex flex-wrap items-center gap-1.5">
                                                            <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded-md bg-danger/10 text-danger border border-danger/20">
                                                                {deny.permission.kode}
                                                            </span>
                                                            {!deny.permission.aktif && (
                                                                <span className="text-[11px] text-muted">
                                                                    (izin nonaktif)
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div>
                                                            <span className="inline-flex items-center rounded-md border border-border bg-soft px-2 py-0.5 text-[11px] font-medium text-ink">
                                                                {deny.unit?.nama ?? 'Global'}
                                                                {deny.unit?.status === 'nonaktif' &&
                                                                    ' (unit nonaktif)'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="max-w-md rounded-lg bg-soft/60 border border-border/60 p-2 text-xs text-ink leading-relaxed whitespace-pre-wrap break-words">
                                                        {deny.alasan}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">
                                                    <p className="text-xs font-semibold text-ink">
                                                        {deny.ditetapkan_oleh.nama}
                                                    </p>
                                                    <p className="text-[11px] text-muted mt-0.5">
                                                        <time dateTime={deny.created_at}>
                                                            {formatTanggal(deny.created_at, { withTime: true })}
                                                        </time>
                                                    </p>
                                                </TableCell>
                                                {can.manageDeny && (
                                                    <TableCell className="text-right whitespace-nowrap">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            className="border-danger/30 text-danger hover:bg-danger/10 hover:border-danger/50"
                                                            aria-label={`Cabut deny ${deny.user.nama} ${deny.permission.kode}`}
                                                            onClick={(event) => {
                                                                trigger.current = event.currentTarget;
                                                                setModal({ deny });
                                                            }}
                                                        >
                                                            Cabut deny
                                                        </Button>
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    )}

                    <Pagination
                        from={pagination.from ?? (denies.length > 0 ? (pagination.current_page - 1) * (pagination.per_page ?? 20) + 1 : 0)}
                        to={pagination.to ?? (denies.length > 0 ? (pagination.current_page - 1) * (pagination.per_page ?? 20) + denies.length : 0)}
                        total={pagination.total ?? denies.length}
                        currentPage={pagination.current_page}
                        lastPage={pagination.last_page}
                        perPage={pagination.per_page ?? 20}
                        prevUrl={pagination.prev_page_url}
                        nextUrl={pagination.next_page_url}
                        resourceName="pembatasan izin"
                        showPerPage={true}
                        onPageChange={(page) => {
                            router.get(
                                '/akses/deny',
                                { ...filters, page, per_page: pagination.per_page ?? 20 },
                                { preserveState: false, preserveScroll: true }
                            );
                        }}
                        onPerPageChange={(newPerPage) => {
                            router.get(
                                '/akses/deny',
                                { ...filters, page: 1, per_page: newPerPage },
                                { preserveState: false, preserveScroll: true }
                            );
                        }}
                    />
                </Card>
            </div>

            {modal && (
                <DenyMutationDialog
                    key={modal.deny?.id ?? 'create'}
                    deny={modal.deny}
                    actorId={auth.user?.id}
                    onClose={() => setModal(null)}
                    onSaved={() => {
                        search.setData('q', '');
                        setModal(null);
                    }}
                />
            )}
        </AuthenticatedLayout>
    );
}
