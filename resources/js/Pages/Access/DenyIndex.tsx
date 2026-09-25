import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CheckCircle, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/Components/Button';
import { DenyMutationDialog } from '@/Components/Access/DenyMutationDialog';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { primaryButton, secondaryButton } from '@/Pages/Auth/AuthShell';
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
    return <AuthenticatedLayout title="Pembatasan Izin" hasCustomHeading>
        <Head title="Pembatasan Izin" />
        {message && dismissed !== flash && <div className="fixed bottom-4 right-4 z-50 flex w-[calc(100%-2rem)] max-w-sm items-start gap-3 rounded-xl border border-success/30 bg-surface p-4 shadow-lg"><CheckCircle aria-hidden="true" className="h-5 w-5 shrink-0 text-success" /><p role="status" className="flex-1 text-sm font-medium">{message}</p><button type="button" aria-label="Tutup notifikasi" onClick={() => setDismissed(flash)} className="-m-2 rounded-lg p-3 text-muted hover:bg-soft focus:ring-2 focus:ring-primary"><X aria-hidden="true" className="h-4 w-4" /></button></div>}
        <section className="rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-4"><div><h1 ref={title} tabIndex={-1} className="text-lg font-semibold">Pembatasan izin eksplisit</h1><p className="mt-2 max-w-2xl text-sm text-muted">Deny membatasi izin meskipun peran atau grant mengizinkannya. Periksa pengguna, izin, dan cakupan sebelum menyimpan.</p></div>{can.manageDeny && <Button type="button" className={primaryButton} onClick={(event) => { trigger.current = event.currentTarget; setModal({ deny: null }); }}>Tambah deny</Button>}</div>
            <form role="search" className="my-6 flex flex-wrap items-end gap-3" onSubmit={(event) => { event.preventDefault(); if (!search.processing) search.get('/akses/deny', { preserveState: false }); }}>
                <div className="min-w-0 flex-1"><label htmlFor="deny-list-search" className="block text-sm font-medium">Cari nama atau email</label><input id="deny-list-search" type="search" maxLength={100} value={search.data.q} onChange={(event) => search.setData('q', event.target.value)} className="mt-2 w-full rounded-lg border border-border bg-surface p-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary" /></div><Button type="submit" className={secondaryButton} isLoading={search.processing}>Cari</Button>{filters.q && <Link href="/akses/deny" className="rounded py-3 text-sm text-primary underline focus:ring-2 focus:ring-primary">Reset</Link>}
            </form>
            {denies.length === 0 ? <p className="rounded-lg bg-soft p-4 text-sm text-muted">Tidak ada pembatasan izin yang sesuai.</p> : <ul className="divide-y divide-border">
                {denies.map((deny) => <li key={deny.id} className="flex flex-col gap-4 py-5 lg:flex-row lg:justify-between"><div className="min-w-0 space-y-2 text-sm">
                    <h2 className="break-words font-semibold">{deny.user.nama}{!deny.user.is_active && <span className="font-normal text-muted"> · Akun nonaktif</span>}</h2><p className="break-all text-muted">{deny.user.email}</p>
                    <p className="break-words"><span className="font-medium">{deny.permission.kode}</span>{!deny.permission.aktif && ' (izin nonaktif)'} · <span className="rounded bg-soft px-2 py-1">{deny.unit?.nama ?? 'Global'}{deny.unit?.status === 'nonaktif' && ' (unit nonaktif)'}</span></p>
                    <p className="whitespace-pre-wrap break-words"><span className="font-medium">Alasan: </span>{deny.alasan}</p><p className="break-words text-xs text-muted">Ditetapkan oleh {deny.ditetapkan_oleh.nama} · <time dateTime={deny.created_at}>{formatTanggal(deny.created_at, { withTime: true })}</time></p>
                </div>{can.manageDeny && <Button type="button" variant="outline" className={`${secondaryButton} self-start lg:shrink-0`} aria-label={`Cabut deny ${deny.user.nama} ${deny.permission.kode}`} onClick={(event) => { trigger.current = event.currentTarget; setModal({ deny }); }}>Cabut deny</Button>}</li>)}
            </ul>}
            {(pagination.prev_page_url || pagination.next_page_url) && <nav aria-label="Halaman pembatasan izin" className="mt-6 flex items-center justify-between gap-3 text-sm">{pagination.prev_page_url ? <Link href={pagination.prev_page_url} className="rounded text-primary underline focus:ring-2 focus:ring-primary">Sebelumnya</Link> : <span className="text-muted">Sebelumnya</span>}<span>Halaman {pagination.current_page}</span>{pagination.next_page_url ? <Link href={pagination.next_page_url} className="rounded text-primary underline focus:ring-2 focus:ring-primary">Berikutnya</Link> : <span className="text-muted">Berikutnya</span>}</nav>}
        </section>
        {modal && <DenyMutationDialog key={modal.deny?.id ?? 'create'} deny={modal.deny} actorId={auth.user?.id} onClose={() => setModal(null)} onSaved={() => { search.setData('q', ''); setModal(null); }} />}
    </AuthenticatedLayout>;
}
