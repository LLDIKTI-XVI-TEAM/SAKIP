import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Ban, CheckCircle, Plus, Trash2, X } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardHeader } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Pagination } from '@/Components/Pagination';
import { Table, TableBody, TableCell, TableCellLabel, TableEmptyRow, TableHead, TableHeader, TableRow, tableCellResponsive } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import { DenyMutationDialog } from '@/Components/Access/DenyMutationDialog';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { SharedPageProps } from '@/types/auth';
import type { DenyIndexProps, DenyRow } from '@/types/deny';

const sel = `${tableCellResponsive} block align-top md:table-cell`;
const barisKartu = 'block min-w-0 space-y-2 p-4 md:table-row md:space-y-0 md:p-0';

/** Alasan dipotong dua baris; tombol hanya tampil bila teks memang terpotong dan membuka teks penuh di baris itu. */
function AlasanRingkas({ id, teks }: { id: string; teks: string }) {
    const ref = useRef<HTMLParagraphElement>(null);
    const [terbuka, setTerbuka] = useState(false);
    const [terpotong, setTerpotong] = useState(false);
    useLayoutEffect(() => {
        const paragraf = ref.current;
        if (!paragraf || terbuka) return;
        const ukur = () => setTerpotong(paragraf.scrollHeight > paragraf.clientHeight);
        ukur();
        // Lebar kolom ikut lebar jendela, jadi status terpotong diukur ulang saat jendela berubah ukuran.
        window.addEventListener('resize', ukur);
        return () => window.removeEventListener('resize', ukur);
    }, [terbuka, teks]);
    return <>
        {/* Batas lebar mencegah alasan panjang merebut lebar kolom lain di desktop tanpa membuatnya terlalu sempit. */}
        <p id={id} ref={ref} className={`whitespace-pre-wrap text-xs text-ink [overflow-wrap:anywhere] md:min-w-48 md:max-w-64 ${terbuka ? '' : 'line-clamp-2'}`}>{teks}</p>
        {(terpotong || terbuka) && <button type="button" aria-expanded={terbuka} aria-controls={id} onClick={() => setTerbuka((nilai) => !nilai)} className="mt-1 rounded text-xs font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/25">
            {terbuka ? 'Ringkas' : 'Selengkapnya'}
        </button>}
    </>;
}

export default function DenyIndex({ denies, pagination, filters, can }: DenyIndexProps) {
    const formatTanggal = useFormatTanggal();
    const { props: { auth }, flash } = usePage<SharedPageProps>();
    const [dismissed, setDismissed] = useState<typeof flash | null>(null);
    const [modal, setModal] = useState<{ deny: DenyRow | null } | null>(null);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const title = useRef<HTMLHeadingElement>(null);
    const search = useForm({ q: filters.q });
    const message = flash.denyStatus === 'created' ? 'Pembatasan berhasil ditambahkan.' : flash.denyStatus === 'revoked' ? 'Pembatasan berhasil dicabut.' : null;
    useEffect(() => {
        if (modal || !trigger.current) return;
        if (trigger.current.isConnected) trigger.current.focus(); else title.current?.focus();
    }, [modal]);
    return <AuthenticatedLayout title="Pembatasan Izin" breadcrumbs={[{ label: 'Pembatasan Izin' }]}>
        <Head title="Pembatasan Izin" />
        {message && dismissed !== flash && <div className="fixed bottom-4 right-4 z-50 flex w-[calc(100%-2rem)] max-w-sm items-start gap-3 rounded-xl border border-success/30 bg-surface p-4 shadow-lg"><CheckCircle aria-hidden="true" className="h-5 w-5 shrink-0 text-success" /><p role="status" className="flex-1 text-sm font-medium">{message}</p><button type="button" aria-label="Tutup notifikasi" onClick={() => setDismissed(flash)} className="-m-2 rounded-lg p-3 text-muted hover:bg-soft focus:ring-2 focus:ring-primary"><X aria-hidden="true" className="h-4 w-4" /></button></div>}
        <div className="space-y-5">
            <Card className="overflow-visible">
                <form
                    role="search"
                    aria-label="Cari pembatasan izin"
                    onSubmit={(event) => { event.preventDefault(); if (!search.processing) search.get('/akses/deny', { preserveState: false }); }}
                    className="grid gap-3 p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end"
                >
                    <Input id="deny-list-search" type="search" label="Cari nama atau email" maxLength={100} value={search.data.q} onChange={(event) => search.setData('q', event.target.value)} />
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" variant="outline" isLoading={search.processing}>Cari</Button>
                        {filters.q && <Button type="button" variant="ghost" onClick={() => router.get('/akses/deny')}>Hapus filter</Button>}
                    </div>
                </form>
            </Card>

            <Card>
                <CardHeader className="px-4 py-4">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 ref={title} tabIndex={-1} className="text-base font-semibold tracking-tight text-ink focus:outline-none">Daftar pembatasan izin</h2>
                            <Badge variant="muted" size="sm">{`${pagination.total} pembatasan`}</Badge>
                        </div>
                        <p className="mt-0.5 text-xs text-muted">Pembatasan berlaku meskipun peran atau izin unit mengizinkan.</p>
                    </div>
                    {can.manageDeny && <Button type="button" size="sm" className="shrink-0" onClick={(event) => { trigger.current = event.currentTarget; setModal({ deny: null }); }}>
                        <Plus className="h-4 w-4" aria-hidden="true" />
                        Tambah pembatasan
                    </Button>}
                </CardHeader>
                {/* Grid mobile: setiap pembatasan tampil sebagai kartu ringkas tanpa scroll horizontal. */}
                <Table wrapperClassName="overflow-x-visible" className="block md:table">
                    <caption className="sr-only">Daftar pembatasan izin per pengguna, izin, dan cakupan</caption>
                    <TableHeader className="max-md:sr-only">
                        <TableRow>
                            <TableHead className="whitespace-normal">Pengguna</TableHead>
                            <TableHead>Izin</TableHead>
                            <TableHead>Cakupan</TableHead>
                            <TableHead>Alasan</TableHead>
                            <TableHead>Ditetapkan</TableHead>
                            <TableHead className="text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody className="block md:table-row-group">
                        {/* Halaman kosong padahal total > 0: nomor halaman melewati halaman terakhir, mis. baris terakhirnya baru dicabut. */}
                        {denies.length === 0 && (pagination.total > 0
                            ? <TableEmptyRow colSpan={6} icon={Ban} title="Tidak ada pembatasan di halaman ini." action={<Button type="button" variant="outline" size="sm" onClick={() => router.get('/akses/deny', filters.q ? { q: filters.q } : {})}>Ke halaman pertama</Button>} />
                            : <TableEmptyRow
                                colSpan={6}
                                icon={Ban}
                                title={filters.q ? 'Tidak ada pembatasan izin yang sesuai' : 'Belum ada pembatasan izin'}
                                description={filters.q ? 'Coba kata kunci nama atau email lain.' : 'Pembatasan izin tampil di sini setelah ditambahkan.'}
                            />)}
                        {denies.map((deny) => <TableRow key={deny.id} className={barisKartu}>
                            <TableCell className={`${sel} md:min-w-44`}>
                                <p className="break-words text-sm font-semibold text-ink">{deny.user.nama}</p>
                                <p className="break-all text-xs text-muted">{deny.user.email}</p>
                                {deny.user.status !== 'aktif' && <Badge variant="muted" size="sm" className="mt-1">Akun nonaktif</Badge>}
                            </TableCell>
                            <TableCell className={sel}>
                                <TableCellLabel>Izin</TableCellLabel>
                                <span className="inline-flex flex-wrap items-center gap-1.5">
                                    <span className="break-words text-xs font-medium text-ink">{deny.permission.kode}</span>
                                    {!deny.permission.aktif && <Badge variant="muted" size="sm">Izin nonaktif</Badge>}
                                </span>
                            </TableCell>
                            <TableCell className={sel}>
                                <TableCellLabel>Cakupan</TableCellLabel>
                                {deny.unit
                                    ? <span className="inline-flex flex-wrap items-center gap-1.5">
                                        <span className="break-words text-xs text-ink">{deny.unit.nama}</span>
                                        {deny.unit.status === 'nonaktif' && <Badge variant="muted" size="sm">Unit nonaktif</Badge>}
                                    </span>
                                    : <Badge variant="info" size="sm">Global</Badge>}
                            </TableCell>
                            <TableCell className={sel}>
                                <TableCellLabel>Alasan</TableCellLabel>
                                <AlasanRingkas id={`deny-alasan-${deny.id}`} teks={deny.alasan} />
                            </TableCell>
                            <TableCell className={sel}>
                                <TableCellLabel>Ditetapkan</TableCellLabel>
                                <span className="break-words text-xs text-ink">{deny.ditetapkan_oleh.nama}</span>
                                <time dateTime={deny.created_at} className="block text-xs text-muted md:whitespace-nowrap">{formatTanggal(deny.created_at, { withTime: true })}</time>
                            </TableCell>
                            <TableCell className={`${sel} md:text-right`}>
                                {can.manageDeny && <div className="flex justify-end">
                                    <Tooltip content="Cabut pembatasan" align="right">
                                        <Button type="button" size="sm" variant="outline" className="h-8 w-8 p-0 text-danger hover:bg-danger/10" aria-label={`Cabut pembatasan ${deny.user.nama} ${deny.permission.kode}`} onClick={(event) => { trigger.current = event.currentTarget; setModal({ deny }); }}>
                                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                                        </Button>
                                    </Tooltip>
                                </div>}
                            </TableCell>
                        </TableRow>)}
                    </TableBody>
                </Table>
            </Card>
            <Pagination links={pagination.links} from={pagination.from} to={pagination.to} total={pagination.total} ariaLabel="Halaman pembatasan izin" />
        </div>
        {modal && <DenyMutationDialog key={modal.deny?.id ?? 'create'} deny={modal.deny} actorId={auth.user?.id} onClose={() => setModal(null)} onSaved={() => { search.setData('q', ''); setModal(null); }} />}
    </AuthenticatedLayout>;
}
