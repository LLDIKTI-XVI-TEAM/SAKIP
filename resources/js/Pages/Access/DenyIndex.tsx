import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle, Search, ShieldAlert, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/Components/Button';
import { Badge } from '@/Components/Badge';
import { DenyMutationDialog } from '@/Components/Access/DenyMutationDialog';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { secondaryButton } from '@/Pages/Auth/AuthShell';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { SharedPageProps } from '@/types/auth';
import type { DenyIndexProps, DenyRow } from '@/types/deny';

export default function DenyIndex({ denies, pagination, filters, can }: DenyIndexProps) {
    const formatTanggal = useFormatTanggal();
    const { props: { auth }, flash } = usePage<SharedPageProps>();
    const [dismissed, setDismissed] = useState<typeof flash | null>(null);
    const [modal, setModal] = useState<{ deny: DenyRow | null } | null>(null);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const title = useRef<HTMLHeadingElement>(null);
    const search = useForm({ q: filters.q });
    const message = flash.denyStatus === 'created' ? 'Deny berhasil ditambahkan.' : flash.denyStatus === 'revoked' ? 'Deny berhasil dicabut.' : null;

    useEffect(() => {
        if (modal || !trigger.current) return;
        if (trigger.current.isConnected) trigger.current.focus(); else title.current?.focus();
    }, [modal]);

    return <AuthenticatedLayout title="Pembatasan Izin">
        <Head title="Pembatasan Izin" />
        {message && dismissed !== flash && (
            <div className="fixed bottom-4 right-4 z-50 flex w-[calc(100%-2rem)] max-w-sm items-start gap-3 rounded-xl border border-success/30 bg-surface p-4 shadow-xl">
                <CheckCircle aria-hidden="true" className="h-5 w-5 shrink-0 text-success" />
                <p role="status" className="flex-1 text-sm font-medium text-ink">{message}</p>
                <button type="button" aria-label="Tutup notifikasi" onClick={() => setDismissed(flash)} className="-m-1 rounded-lg p-2 text-muted hover:bg-soft focus:ring-2 focus:ring-primary"><X aria-hidden="true" className="h-4 w-4" /></button>
            </div>
        )}

        <div className="space-y-6">
            <div className="rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-xs">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <h2 ref={title} tabIndex={-1} className="text-base font-semibold text-ink">
                                Pembatasan izin eksplisit
                            </h2>
                        </div>
                        <p className="mt-1 max-w-2xl text-xs text-muted leading-relaxed">
                            Deny membatasi izin meskipun peran atau grant mengizinkannya. Periksa pengguna, izin, dan cakupan sebelum menyimpan.
                        </p>
                    </div>
                    {can.manageDeny && (
                        <Button
                            type="button"
                            variant="primary"
                            size="md"
                            className="self-start sm:self-auto shrink-0 shadow-xs"
                            onClick={(event) => {
                                trigger.current = event.currentTarget;
                                setModal({ deny: null });
                            }}
                        >
                            Tambah deny
                        </Button>
                    )}
                </div>

                <div className="mt-4 flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 p-3.5 text-xs text-ink">
                    <AlertTriangle className="h-4 w-4 shrink-0 text-warning-dark mt-0.5" aria-hidden="true" />
                    <p className="leading-relaxed">
                        <strong className="font-semibold text-warning-dark">Aturan Keamanan (Deny Wins):</strong> Pembatasan izin bersifat mutlak dan membatalkan hak akses yang berasal dari peran utama maupun grant unit.
                    </p>
                </div>

                <form role="search" className="mt-5 flex flex-wrap items-end gap-3" onSubmit={(event) => { event.preventDefault(); if (!search.processing) search.get('/akses/deny', { preserveState: false }); }}>
                    <div className="min-w-0 flex-1">
                        <label htmlFor="deny-list-search" className="block text-xs font-semibold uppercase tracking-wider text-muted">Cari nama atau email</label>
                        <div className="relative mt-1.5">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" aria-hidden="true" />
                            <input id="deny-list-search" type="search" maxLength={100} value={search.data.q} onChange={(event) => search.setData('q', event.target.value)} placeholder="Ketik nama atau alamat email..." className="w-full rounded-lg border border-border bg-surface pl-10 pr-3.5 py-2.5 text-sm text-ink placeholder:text-muted/60 focus:outline-none focus:ring-2 focus:ring-primary transition-colors" />
                        </div>
                    </div>
                    <Button type="submit" className={secondaryButton} isLoading={search.processing}>Cari</Button>
                    {filters.q && <Link href="/akses/deny" className="inline-flex items-center justify-center rounded-lg border border-border px-3.5 py-2.5 text-sm font-medium text-ink hover:bg-soft focus:ring-2 focus:ring-primary transition-colors">Reset</Link>}
                </form>
            </div>

            <section className="rounded-xl border border-border bg-surface overflow-hidden shadow-xs">
                {denies.length === 0 ? (
                    <div className="p-8 text-center text-sm text-muted">
                        <p className="font-medium text-ink">Tidak ada pembatasan izin yang sesuai.</p>
                        <p className="mt-1 text-xs text-muted">Tidak ditemukan riwayat deny aktif dengan kriteria pencarian saat ini.</p>
                    </div>
                ) : (
                    <ul className="divide-y divide-border">
                        {denies.map((deny) => (
                            <li key={deny.id} className="flex flex-col gap-4 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5 hover:bg-soft/30 transition-colors">
                                <div className="min-w-0 flex-1 space-y-2.5 text-sm">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="break-words font-semibold text-ink text-sm">
                                            {deny.user.nama}
                                            {deny.user.status !== 'aktif' && <span className="font-normal text-muted"> · Akun nonaktif</span>}
                                        </h3>
                                        <Badge variant={deny.user.status === 'aktif' ? 'success' : 'muted'} size="sm" dot>
                                            {deny.user.status === 'aktif' ? 'Akun aktif' : 'Nonaktif'}
                                        </Badge>
                                    </div>
                                    <p className="break-all text-xs text-muted">{deny.user.email}</p>

                                    <div className="flex flex-wrap items-center gap-2 pt-0.5">
                                        <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded-md bg-danger/10 text-danger border border-danger/20">
                                            {deny.permission.kode}
                                        </span>
                                        {!deny.permission.aktif && <span className="text-xs text-muted">(izin nonaktif)</span>}
                                        <span className="text-muted text-xs">·</span>
                                        <span className="inline-flex items-center rounded-md border border-border bg-soft px-2.5 py-0.5 text-xs font-medium text-ink">
                                            {deny.unit?.nama ?? 'Global'}{deny.unit?.status === 'nonaktif' && ' (unit nonaktif)'}
                                        </span>
                                    </div>

                                    <div className="rounded-lg bg-soft/60 border border-border/60 p-2.5 text-xs text-ink leading-relaxed">
                                        <span className="font-semibold text-muted">Alasan: </span>
                                        <span className="whitespace-pre-wrap break-words">{deny.alasan}</span>
                                    </div>

                                    <p className="break-words text-xs text-muted pt-0.5">
                                        Ditetapkan oleh <strong className="font-medium text-ink">{deny.ditetapkan_oleh.nama}</strong> · <time dateTime={deny.created_at}>{formatTanggal(deny.created_at, { withTime: true })}</time>
                                    </p>
                                </div>

                                {can.manageDeny && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className={`${secondaryButton} self-start sm:shrink-0 sm:self-auto border-danger/30 text-danger hover:bg-danger/10 hover:border-danger/50`}
                                        aria-label={`Cabut deny ${deny.user.nama} ${deny.permission.kode}`}
                                        onClick={(event) => {
                                            trigger.current = event.currentTarget;
                                            setModal({ deny });
                                        }}
                                    >
                                        Cabut deny
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                {(pagination.prev_page_url || pagination.next_page_url) && (
                    <nav aria-label="Halaman pembatasan izin" className="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-soft/30 px-4 py-3 sm:px-6 text-xs text-muted">
                        <div>
                            <span>Halaman <strong className="font-semibold text-ink">{pagination.current_page}</strong></span>
                        </div>
                        <div className="flex items-center gap-2">
                            {pagination.prev_page_url ? (
                                <Link href={pagination.prev_page_url} className="inline-flex items-center rounded-md border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink hover:bg-soft focus:ring-2 focus:ring-primary transition-colors">
                                    Sebelumnya
                                </Link>
                            ) : (
                                <span className="inline-flex items-center rounded-md border border-border/40 bg-surface/50 px-3 py-1.5 text-xs font-medium text-muted opacity-50 cursor-not-allowed">
                                    Sebelumnya
                                </span>
                            )}
                            {pagination.next_page_url ? (
                                <Link href={pagination.next_page_url} className="inline-flex items-center rounded-md border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink hover:bg-soft focus:ring-2 focus:ring-primary transition-colors">
                                    Berikutnya
                                </Link>
                            ) : (
                                <span className="inline-flex items-center rounded-md border border-border/40 bg-surface/50 px-3 py-1.5 text-xs font-medium text-muted opacity-50 cursor-not-allowed">
                                    Berikutnya
                                </span>
                            )}
                        </div>
                    </nav>
                )}
            </section>
        </div>

        {modal && <DenyMutationDialog key={modal.deny?.id ?? 'create'} deny={modal.deny} actorId={auth.user?.id} onClose={() => setModal(null)} onSaved={() => { search.setData('q', ''); setModal(null); }} />}
    </AuthenticatedLayout>;
}
