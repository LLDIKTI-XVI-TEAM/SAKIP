import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Input } from '@/Components/Input';
import { Button } from '@/Components/Button';
import { Badge } from '@/Components/Badge';
import { WorkReadinessList } from '@/Components/Access/WorkReadinessList';
import type { IndicatorSummary, UnitSummary, WorkReadiness } from '@/types/penanggung-jawab';

interface Props {
    assignments: { data: { indicator: IndicatorSummary; unit: UnitSummary | null; pic: { id: string; nama: string };
        tanggal_mulai_berlaku: string; readiness: WorkReadiness; blocked_reason: string | null }[]; next_page_url: string | null };
    filters: { q: string; tanggal_acuan: string; unit_id: string | null };
}
export default function Index({ assignments, filters }: Props) {
    const [query, setQuery] = useState(filters.q);
    const [date, setDate] = useState(filters.tanggal_acuan);
    const [loading, setLoading] = useState(false);
    return <AuthenticatedLayout title="Monitoring Penanggung Jawab" breadcrumbs={[{ label: 'Perencanaan' }, { label: 'Penanggung Jawab' }]}>
        <Head title="Monitoring Penanggung Jawab" />
        <div className="space-y-6">
            <form className="flex flex-col items-end gap-3 rounded-xl border border-border bg-surface p-4 sm:flex-row" onSubmit={(event) => {
                event.preventDefault();
                router.get('/penanggung-jawab', { q: query, tanggal_acuan: date, ...(filters.unit_id ? { unit_id: filters.unit_id } : {}) },
                    { preserveState: true, onStart: () => setLoading(true), onFinish: () => setLoading(false) });
            }}>
                <Input label="Cari indikator, unit, atau PJ" type="search" value={query} maxLength={100} onChange={(event) => setQuery(event.target.value)} />
                <Input label="Tanggal acuan" type="date" value={date} required onChange={(event) => setDate(event.target.value)} />
                <Button type="submit" isLoading={loading} className="shrink-0">Terapkan</Button>
            </form>
            {filters.unit_id && <Link href="/penanggung-jawab" className="text-sm font-medium text-primary underline">Hapus filter unit</Link>}
            <p className="sr-only" aria-live="polite">{assignments.data.length} indikator ditampilkan pada halaman ini.</p>
            {assignments.data.length === 0 ? <div className="rounded-xl border border-border bg-surface p-8 text-center">
                <h2 className="font-semibold text-ink">Tidak ada PJ aktif dengan izin kerja kurang</h2>
                <p className="mt-2 text-sm text-muted">Coba ubah pencarian atau tanggal acuan. Indikator tanpa PJ dan pengguna nonaktif tidak termasuk daftar ini.</p>
            </div> : <div className="space-y-4">{assignments.data.map((row) => <article key={row.indicator.id} className="rounded-xl border border-border bg-surface p-5">
                <div className="flex flex-col justify-between gap-4 sm:flex-row">
                    <div className="min-w-0"><p className="text-xs font-semibold text-primary">{row.indicator.kode} · {row.unit?.nama ?? 'Unit tidak tersedia'}</p>
                        <h2 className="mt-1 break-words text-base font-semibold text-ink">{row.indicator.nama}</h2>
                        <p className="mt-2 text-sm text-muted">PJ: <span className="font-medium text-ink">{row.pic.nama}</span> · Berlaku {row.tanggal_mulai_berlaku}</p>
                        {row.blocked_reason && <p className="mt-2 text-sm text-warning-dark">{row.blocked_reason}</p>}
                    </div>
                    <div className="flex shrink-0 flex-col items-start gap-3 sm:items-end">
                        <Badge variant="warning">{row.readiness.missing.length} izin belum tersedia</Badge>
                        <Link href={`/perencanaan/indikator/${row.indicator.id}/penanggung-jawab`} className="text-sm font-semibold text-primary underline underline-offset-4">Lihat penugasan</Link>
                    </div>
                </div>
                <details className="mt-4 border-t border-border pt-3">
                    <summary className="cursor-pointer text-sm font-medium text-ink focus-visible:outline-primary">Rincian 7 izin kerja</summary>
                    <WorkReadinessList readiness={row.readiness} />
                </details>
            </article>)}</div>}
            {assignments.next_page_url && <Link href={assignments.next_page_url} className="inline-flex rounded-lg border border-border bg-surface px-4 py-2 text-sm font-semibold text-ink hover:bg-soft">Halaman berikutnya</Link>}
        </div>
    </AuthenticatedLayout>;
}
