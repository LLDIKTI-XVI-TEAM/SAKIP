import { useState, type FormEvent } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Eye, Search, UserCheck } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Pagination } from '@/Components/Pagination';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { SharedPageProps } from '@/types/auth';
import type { MonitoringProps } from '@/types/penanggung-jawab';

const cellClass = 'min-w-0 p-0 md:px-4 md:py-3.5';
// Di HP, empat sel pertama selebar kartu; badge izin dan tombol lihat berbagi baris terakhir.
const cellPenuh = `${cellClass} col-span-2`;
const iconAction = 'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-surface text-muted shadow-2xs transition-colors hover:border-primary/40 hover:bg-soft hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
const opsiIzin = [{ value: '', label: 'Semua' }, { value: 'kurang', label: 'Belum lengkap' }, { value: 'lengkap', label: 'Lengkap' }];

/** Unit dan indikator dikontrol agar opsi indikator menyempit sesuai unit; isian lain cukup dibaca dari form saat diterapkan. */
function FilterMonitoring({ filters, pilihan, errors }: Pick<MonitoringProps, 'filters' | 'pilihan'> & { errors: Partial<Record<string, string>> }) {
    const [unitId, setUnitId] = useState(filters.unit_id ?? '');
    const [indikatorId, setIndikatorId] = useState(filters.indikator_id ?? '');
    const [memuat, setMemuat] = useState(false);
    const pilihUnit = (id: string) => {
        setUnitId(id);
        if (id !== '' && pilihan.indikator.find((item) => item.id === indikatorId)?.unit_id !== id) setIndikatorId('');
    };
    const terapkan = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        // Isian kosong tidak dikirim agar URL filter tetap ringkas dan dapat dibagikan.
        const isian = Object.fromEntries([...new FormData(event.currentTarget).entries()].filter(([, nilai]) => String(nilai).trim() !== ''));
        router.get('/penanggung-jawab', isian, { preserveState: true, replace: true, onStart: () => setMemuat(true), onFinish: () => setMemuat(false) });
    };

    return <form onSubmit={terapkan} aria-label="Filter monitoring penanggung jawab" className="grid gap-3 p-4 sm:grid-cols-2 sm:items-end lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)]">
        <Input name="q" type="search" label="Cari PJ, indikator, atau unit" defaultValue={filters.q} maxLength={100} placeholder="Nama PJ, kode, atau unit…" error={errors.q} />
        <Select name="unit_id" label="Unit" value={unitId} error={errors.unit_id} onChange={(event) => pilihUnit(event.target.value)}
            options={[{ value: '', label: 'Semua unit' }, ...pilihan.unit.map((unit) => ({ value: unit.id, label: unit.nama }))]} />
        <Select name="indikator_id" label="Indikator" value={indikatorId} error={errors.indikator_id} onChange={(event) => setIndikatorId(event.target.value)}
            options={[{ value: '', label: 'Semua indikator' }, ...pilihan.indikator.filter((item) => unitId === '' || item.unit_id === unitId)
                .map((item) => ({ value: item.id, label: `${item.kode} — ${item.nama}` }))]} />
        <Select name="izin" label="Izin kerja" defaultValue={filters.izin ?? ''} error={errors.izin} options={opsiIzin} />
        <Input name="tanggal_acuan" type="date" label="Tanggal acuan" defaultValue={filters.tanggal_acuan} required error={errors.tanggal_acuan} />
        <div className="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-5">
            <Button type="submit" variant="outline" isLoading={memuat} className="gap-1.5">
                {!memuat && <Search className="h-4 w-4" aria-hidden="true" />}
                Terapkan
            </Button>
            <Link href="/penanggung-jawab" className="text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/20">Atur ulang</Link>
        </div>
    </form>;
}

export default function Index({ assignments, filters, pilihan }: MonitoringProps) {
    const formatTanggal = useFormatTanggal();
    const { auth, errors } = usePage<SharedPageProps>().props;
    // Monitoring cukup penanggung_jawab:update; daftar Sasaran & Indikator butuh indikator:read.
    const bolehBacaIndikator = auth.can.sasaranIndikator === true;
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

            <Card className="overflow-visible">
                {/* Key mengikuti filter server agar isian kembali selaras setelah navigasi. */}
                <FilterMonitoring key={JSON.stringify(filters)} filters={filters} pilihan={pilihan} errors={errors} />
            </Card>

            <p className="sr-only" aria-live="polite">{assignments.total} penanggung jawab ditemukan.</p>

            {assignments.data.length === 0 ? <Card>
                <div className="px-6 py-14 text-center">
                    <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <UserCheck className="h-6 w-6" aria-hidden="true" />
                    </span>
                    <h2 className="mt-4 text-base font-semibold text-ink">Belum ada penanggung jawab yang sesuai</h2>
                    <p className="mx-auto mt-1 max-w-md text-sm leading-6 text-muted">{assignments.total > 0
                        ? 'Halaman ini kosong. Kembali ke halaman pertama untuk melihat data.'
                        : 'Ubah atau atur ulang filter. Indikator tanpa penanggung jawab pada tanggal acuan tidak termasuk daftar ini.'}</p>
                </div>
            </Card> : <Card>
                {/* Grid mobile: setiap penugasan tampil sebagai kartu ringkas tanpa scroll horizontal. */}
                <Table wrapperClassName="overflow-x-visible" className="block md:table">
                    <caption className="sr-only">Penanggung jawab efektif per indikator pada tanggal acuan</caption>
                    <TableHeader className="max-md:sr-only">
                        <TableRow>
                            <TableHead className="w-[30%]">Indikator</TableHead>
                            <TableHead className="min-w-40">Unit</TableHead>
                            <TableHead>Penanggung Jawab</TableHead>
                            <TableHead>Berlaku sejak</TableHead>
                            <TableHead>Izin kerja</TableHead>
                            <TableHead className="text-right"><span className="sr-only">Aksi</span></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody className="block md:table-row-group">
                        {assignments.data.map((row) => <TableRow key={row.indicator.id} className="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-2 p-4 md:table-row">
                            <TableCell className={`${cellPenuh} md:max-w-sm`}>
                                <p className="font-mono font-semibold text-ink">{row.indicator.kode}</p>
                                <p className="mt-0.5 break-words text-muted">{row.indicator.nama}</p>
                                {row.blocked_reason && <p className="mt-1 text-warning-dark">{row.blocked_reason}</p>}
                            </TableCell>
                            <TableCell className={cellPenuh}>
                                <span className="text-muted md:hidden">Unit: </span>{row.unit?.nama ?? 'Unit tidak tersedia'}
                            </TableCell>
                            <TableCell className={cellPenuh}>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-muted md:hidden">PJ:</span>
                                    <span className="font-semibold text-ink">{row.pic.nama}</span>
                                    {row.pic.status !== 'aktif' && <Badge variant="muted">Nonaktif</Badge>}
                                </div>
                            </TableCell>
                            <TableCell className={`${cellPenuh} md:whitespace-nowrap`}>
                                <span className="text-muted md:hidden">Berlaku sejak </span><span>{formatTanggal(row.tanggal_mulai_berlaku)}</span>
                            </TableCell>
                            <TableCell className={cellClass}>
                                <Badge variant={row.readiness.complete ? 'success' : 'warning'}>
                                    {row.readiness.complete ? 'Lengkap' : `${row.readiness.missing.length} izin belum tersedia`}
                                </Badge>
                            </TableCell>
                            <TableCell className={`${cellClass} md:text-right`}>
                                <Tooltip content="Lihat penugasan" align="right">
                                    <Link href={`/perencanaan/indikator/${row.indicator.id}/penanggung-jawab`} aria-label={`Lihat penugasan ${row.indicator.kode}`} className={iconAction}>
                                        <Eye className="h-4 w-4" aria-hidden="true" />
                                    </Link>
                                </Tooltip>
                            </TableCell>
                        </TableRow>)}
                    </TableBody>
                </Table>
            </Card>}

            <Pagination links={assignments.links} from={assignments.from} to={assignments.to} total={assignments.total} ariaLabel="Paginasi monitoring penanggung jawab" />
        </div>
    </AuthenticatedLayout>;
}
