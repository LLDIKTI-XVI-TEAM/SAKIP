import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Search, UserCheck } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Input } from '@/Components/Input';
import { Button } from '@/Components/Button';
import { Badge } from '@/Components/Badge';
import { Card } from '@/Components/Card';
import { WorkReadinessList } from '@/Components/Access/WorkReadinessList';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { SharedPageProps } from '@/types/auth';
import type { IndicatorSummary, UnitSummary, WorkReadiness } from '@/types/penanggung-jawab';

interface Props {
    assignments: { data: { indicator: IndicatorSummary; unit: UnitSummary | null; pic: { id: string; nama: string };
        tanggal_mulai_berlaku: string; readiness: WorkReadiness; blocked_reason: string | null }[]; next_page_url: string | null };
    filters: { q: string; tanggal_acuan: string; unit_id: string | null };
}

const linkAction = 'inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25';

export default function Index({ assignments, filters }: Props) {
    const formatTanggal = useFormatTanggal();
    const [query, setQuery] = useState(filters.q);
    const [date, setDate] = useState(filters.tanggal_acuan);
    const [loading, setLoading] = useState(false);
    // Monitoring cukup penanggung_jawab:update; daftar Sasaran & Indikator butuh indikator:read.
    const bolehBacaIndikator = usePage<SharedPageProps>().props.auth.can.sasaranIndikator === true;
    return <AuthenticatedLayout title="Monitoring Penanggung Jawab" breadcrumbs={[
        { label: 'Perencanaan' },
        { label: 'Sasaran & Indikator', href: bolehBacaIndikator ? '/perencanaan/sasaran-indikator' : undefined },
        { label: 'Monitoring Penanggung Jawab' },
    ]}>
        <Head title="Monitoring Penanggung Jawab" />
        <div className="space-y-5">
            {bolehBacaIndikator && <Link href="/perencanaan/sasaran-indikator" className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Kembali ke Sasaran & Indikator
            </Link>}

            <Card className="p-4">
                <form className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-end" onSubmit={(event) => {
                    event.preventDefault();
                    router.get('/penanggung-jawab', { q: query, tanggal_acuan: date, ...(filters.unit_id ? { unit_id: filters.unit_id } : {}) },
                        { preserveState: true, onStart: () => setLoading(true), onFinish: () => setLoading(false) });
                }}>
                    <Input label="Cari indikator, unit, atau PJ" type="search" value={query} maxLength={100} placeholder="Kode, nama indikator, unit, atau PJ…" onChange={(event) => setQuery(event.target.value)} />
                    <Input label="Tanggal acuan" type="date" value={date} required onChange={(event) => setDate(event.target.value)} />
                    <Button type="submit" variant="outline" isLoading={loading} className="h-[42px] gap-1.5">
                        {!loading && <Search className="h-4 w-4" aria-hidden="true" />}
                        Terapkan
                    </Button>
                </form>
                {filters.unit_id && <p className="mt-3 flex flex-wrap items-center gap-2 text-sm text-muted">
                    <Badge variant="primary" size="sm">Filter unit aktif</Badge>
                    <Link href="/penanggung-jawab" className="font-semibold text-primary hover:underline">Hapus filter unit</Link>
                </p>}
            </Card>

            <p className="sr-only" aria-live="polite">{assignments.data.length} indikator ditampilkan pada halaman ini.</p>

            {assignments.data.length === 0 ? <Card>
                <div className="px-6 py-14 text-center">
                    <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <UserCheck className="h-6 w-6" aria-hidden="true" />
                    </span>
                    <h2 className="mt-4 text-base font-semibold text-ink">{assignments.next_page_url ? 'Belum ditemukan PJ dengan izin kerja kurang pada halaman ini' : 'Tidak ada PJ aktif dengan izin kerja kurang pada halaman ini'}</h2>
                    <p className="mx-auto mt-1 max-w-md text-sm leading-6 text-muted">{assignments.next_page_url
                        ? 'Masih ada penugasan yang belum diperiksa. Lanjutkan ke halaman berikutnya.'
                        : 'Coba ubah pencarian atau tanggal acuan. Indikator tanpa PJ dan pengguna nonaktif tidak termasuk daftar ini.'}</p>
                </div>
            </Card> : <div className="space-y-4">{assignments.data.map((row) => <Card key={row.indicator.id} className="p-5">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="min-w-0">
                        <h2 className="break-words text-base font-semibold text-ink">{row.indicator.nama}</h2>
                        <p className="mt-1 text-sm text-muted"><span className="font-mono">{row.indicator.kode}</span> · {row.unit?.nama ?? 'Unit tidak tersedia'}</p>
                        <p className="mt-2 text-sm text-muted">PJ: <span className="font-medium text-ink">{row.pic.nama}</span> · Berlaku {formatTanggal(row.tanggal_mulai_berlaku)}</p>
                        {row.blocked_reason && <p className="mt-2 text-sm text-warning-dark">{row.blocked_reason}</p>}
                    </div>
                    <div className="flex shrink-0 flex-wrap items-center gap-3 sm:flex-col sm:items-end">
                        <Badge variant="warning">{row.readiness.missing.length} izin belum tersedia</Badge>
                        <Link href={`/perencanaan/indikator/${row.indicator.id}/penanggung-jawab`} className={linkAction}>Lihat penugasan</Link>
                    </div>
                </div>
                <details className="mt-4 border-t border-border pt-3">
                    <summary className="cursor-pointer text-sm font-medium text-ink focus-visible:outline-primary">Rincian 7 izin kerja</summary>
                    <WorkReadinessList readiness={row.readiness} />
                </details>
            </Card>)}</div>}

            {assignments.next_page_url && <div className="flex justify-end">
                <Link href={assignments.next_page_url} className={linkAction}>Halaman berikutnya</Link>
            </div>}
        </div>
    </AuthenticatedLayout>;
}
