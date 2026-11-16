import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowLeftRight, Eye } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader } from '@/Components/Card';
import { Badge, type BadgeVariant } from '@/Components/Badge';
import { Pagination, type PaginationLinkItem } from '@/Components/Pagination';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';

interface RekomendasiDetail {
    id: string;
    isi: string;
    rujukan_sumber: string | null;
    status_tampilan: 'draf' | 'berlaku' | 'digantikan';
    penyusun: string;
    created_at: string;
    penyetuju: string | null;
    disetujui_pada: string | null;
    disetujui_oleh_role: string | null;
    persetujuan_cadangan: boolean;
}

export interface RekomendasiShowProps {
    rekomendasi: RekomendasiDetail;
    indikator: { id: string; kode: string; nama: string; unit: string };
    periode: { nama: string; tahun: number };
    berlaku_url: string | null;
    kelola_url: string;
    pencarian_iku: string;
    pilihan_iku: {
        data: { id: string; kode: string; nama: string; status: 'draf' | 'disetujui'; url: string }[];
        current_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    riwayat: { data: RekomendasiDetail[]; links: PaginationLinkItem[]; from: number | null; to: number | null; total: number };
    can: { viewFollowUp: boolean };
    tindak_lanjut?: {
        isi: string;
        nomor: number;
        diajukan_pada: string;
        status_pengukuran: string;
        pengajuan_terdahulu: boolean;
        periode: string;
        tahun: number;
    } | null;
}

const status: Record<RekomendasiDetail['status_tampilan'], { label: string; variant: BadgeVariant }> = {
    draf: { label: 'Draf', variant: 'muted' },
    berlaku: { label: 'Berlaku', variant: 'success' },
    digantikan: { label: 'Digantikan', variant: 'secondary' },
};
const namaPeran: Record<string, string> = { pimpinan: 'Pimpinan', perencanaan: 'Perencanaan', superadmin: 'Superadmin', admin: 'Admin', pegawai: 'Pegawai' };
const linkClass = 'rounded font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/25';
const actionClass = 'inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border bg-surface text-muted shadow-2xs transition-colors hover:border-primary/40 hover:bg-soft hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/20';
const navigationClass = 'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25';
const cellClass = 'min-w-0 p-0 md:px-4 md:py-3.5';

function PersetujuanCadangan({ data }: { data: RekomendasiDetail }) {
    return data.persetujuan_cadangan && data.disetujui_oleh_role
        ? <Badge variant="warning">Persetujuan cadangan · {namaPeran[data.disetujui_oleh_role] ?? data.disetujui_oleh_role}</Badge>
        : null;
}

export default function Show({ rekomendasi, indikator, periode, berlaku_url, kelola_url, pencarian_iku, pilihan_iku, riwayat, can, tindak_lanjut }: RekomendasiShowProps) {
    const formatTanggal = useFormatTanggal();
    const penanda = status[rekomendasi.status_tampilan];
    const [pilihIku, setPilihIku] = useState(false);
    const pencarian = useForm({ cari_iku: pencarian_iku });

    return (
        <AuthenticatedLayout title="Detail Rekomendasi Pimpinan" breadcrumbs={[{ label: 'Rekomendasi Pimpinan', href: kelola_url }, { label: 'Detail' }]}>
            <Head title={`Detail Rekomendasi · ${indikator.kode}`} />
            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-end gap-2">
                    <Link href={kelola_url} className={navigationClass}>
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />Kembali ke daftar
                    </Link>
                    <Button type="button" variant="outline" size="sm" onClick={() => setPilihIku(true)} aria-haspopup="dialog">
                        <ArrowLeftRight className="h-4 w-4" aria-hidden="true" />Pilih IKU
                    </Button>
                </div>

                <Modal isOpen={pilihIku} onClose={() => setPilihIku(false)} title="Pilih IKU" size="lg"
                    description={`${periode.nama} ${periode.tahun} · Pilih IKU untuk membuka rekomendasi berlaku atau drafnya.`}>
                    <form className="mb-4 flex items-end gap-2" onSubmit={(event) => {
                        event.preventDefault();
                        pencarian.get(`/rekomendasi/${rekomendasi.id}`, { preserveState: true, preserveScroll: true });
                    }}>
                        <Input label="Cari IKU" placeholder="Kode atau nama IKU" maxLength={100} value={pencarian.data.cari_iku}
                            onChange={(event) => pencarian.setData('cari_iku', event.target.value)} error={pencarian.errors.cari_iku} />
                        <Button type="submit" isLoading={pencarian.processing}>Cari</Button>
                    </form>
                    <div aria-busy={pencarian.processing} className="divide-y divide-border">
                        {pilihan_iku.data.map((item) => <Link key={item.id} href={item.url}
                            className="flex min-w-0 items-start justify-between gap-3 rounded-lg p-3 transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25">
                            <span className="min-w-0"><span className="block text-sm font-semibold text-ink">{item.kode}</span>
                                <span className="mt-1 block break-words text-sm text-muted">{item.nama}</span>
                                {item.id === indikator.id && <span className="mt-1 block text-xs font-semibold text-muted">IKU saat ini</span>}</span>
                            <Badge variant={item.status === 'draf' ? 'muted' : 'success'}>{item.status === 'draf' ? 'Draf' : 'Berlaku'}</Badge>
                        </Link>)}
                        {pilihan_iku.data.length === 0 && <p className="py-6 text-sm text-muted">Tidak ada IKU yang cocok dan memiliki rekomendasi pada periode ini.</p>}
                    </div>
                    {(pilihan_iku.prev_page_url || pilihan_iku.next_page_url) && <nav aria-label="Halaman pilihan IKU" className="mt-4 flex items-center justify-between gap-3 border-t border-border pt-4">
                        {pilihan_iku.prev_page_url ? <Link href={pilihan_iku.prev_page_url} preserveState preserveScroll className={navigationClass}>Sebelumnya</Link> : <span />}
                        <span className="text-xs text-muted">Halaman {pilihan_iku.current_page}</span>
                        {pilihan_iku.next_page_url ? <Link href={pilihan_iku.next_page_url} preserveState preserveScroll className={navigationClass}>Berikutnya</Link> : <span />}
                    </nav>}
                </Modal>

                <Card>
                    <CardHeader className="items-start">
                        <div className="min-w-0 flex-1">
                            <h2 className="break-words text-base font-semibold text-ink">{indikator.nama}</h2>
                            <p className="mt-1 text-sm text-muted"><span className="font-mono">{indikator.kode}</span> · {periode.nama} {periode.tahun}</p>
                            <p className="mt-1 break-words text-sm text-muted">{indikator.unit}</p>
                        </div>
                        <Badge variant={penanda.variant}>{penanda.label}</Badge>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {rekomendasi.status_tampilan === 'digantikan' && berlaku_url && (
                            <p className="rounded-lg border border-border bg-soft p-3 text-sm text-ink">
                                Rekomendasi ini telah digantikan. <Link href={berlaku_url} className={linkClass}>Lihat rekomendasi yang berlaku</Link>
                            </p>
                        )}
                        <section aria-labelledby="isi-rekomendasi">
                            <h3 id="isi-rekomendasi" className="mb-2 text-sm font-semibold text-ink">Isi rekomendasi</h3>
                            <p className="max-w-prose whitespace-pre-wrap text-sm leading-relaxed text-ink [overflow-wrap:anywhere]">{rekomendasi.isi}</p>
                        </section>
                        <dl className="grid min-w-0 gap-4 border-t border-border pt-5 text-sm sm:grid-cols-2">
                            <div className="min-w-0"><dt className="text-muted">Penyusun</dt><dd className="mt-1 break-words font-medium text-ink">{rekomendasi.penyusun}</dd></div>
                            <div><dt className="text-muted">Waktu penyusunan</dt><dd className="mt-1 text-ink">{formatTanggal(rekomendasi.created_at, { withTime: true })}</dd></div>
                            <div className="min-w-0"><dt className="text-muted">Penyetuju</dt><dd className="mt-1 space-y-2 text-ink"><p className="break-words font-medium">{rekomendasi.penyetuju ?? 'Belum disetujui'}</p><PersetujuanCadangan data={rekomendasi} /></dd></div>
                            <div><dt className="text-muted">Waktu persetujuan</dt><dd className="mt-1 text-ink">{formatTanggal(rekomendasi.disetujui_pada, { withTime: true })}</dd></div>
                            <div className="min-w-0 sm:col-span-2"><dt className="text-muted">Rujukan sumber</dt><dd className="mt-1 whitespace-pre-wrap text-ink [overflow-wrap:anywhere]">{rekomendasi.rujukan_sumber ?? 'Tidak dicantumkan'}</dd></div>
                        </dl>
                    </CardContent>
                </Card>

                {can.viewFollowUp && <Card>
                    <CardHeader><h2 className="text-base font-semibold text-ink">Tindak lanjut</h2></CardHeader>
                    <CardContent>
                        {tindak_lanjut ? <div className="space-y-4">
                            <div>
                                <p className="text-sm font-semibold text-ink">Tindak lanjut dari pengajuan #{tindak_lanjut.nomor}</p>
                                <p className="mt-1 text-sm text-muted">{tindak_lanjut.periode} {tindak_lanjut.tahun} · Diajukan {formatTanggal(tindak_lanjut.diajukan_pada, { withTime: true })}</p>
                            </div>
                            <p className="max-w-prose whitespace-pre-wrap text-sm leading-relaxed text-ink [overflow-wrap:anywhere]">{tindak_lanjut.isi}</p>
                            <dl className="border-t border-border pt-4 text-sm">
                                <dt className="mb-2 text-muted">Status pengukuran saat ini</dt>
                                <dd><Badge status={tindak_lanjut.status_pengukuran} /></dd>
                            </dl>
                            {tindak_lanjut.pengajuan_terdahulu && <p className="text-sm text-muted">Tindak lanjut ini berasal dari pengajuan terdahulu.</p>}
                        </div> : <p className="text-sm text-muted">Belum ada tindak lanjut</p>}
                    </CardContent>
                </Card>}

                <Card>
                    <CardHeader><h2 className="text-base font-semibold text-ink">Rangkaian rekomendasi</h2></CardHeader>
                    <Table wrapperClassName="overflow-x-visible" className="block md:table">
                        <caption className="sr-only">Seluruh rekomendasi {indikator.kode} pada {periode.nama} {periode.tahun}, menurut waktu persetujuan dengan draf di akhir</caption>
                        <TableHeader className="max-md:sr-only"><TableRow><TableHead>Rekomendasi</TableHead><TableHead>Penyusun dan persetujuan</TableHead><TableHead>Detail</TableHead></TableRow></TableHeader>
                        <TableBody className="block md:table-row-group">
                            {riwayat.data.map((row) => <TableRow key={row.id} className="grid min-w-0 grid-cols-1 gap-3 p-4 md:table-row">
                                <TableCell className={`${cellClass} md:w-1/2`}>
                                    <div className="mb-2 flex flex-wrap items-center gap-2"><Badge variant={status[row.status_tampilan].variant}>{status[row.status_tampilan].label}</Badge>
                                        {row.id === rekomendasi.id && <span className="text-xs font-semibold text-muted">Sedang dibuka</span>}</div>
                                    <p className="line-clamp-3 whitespace-pre-wrap text-sm text-ink [overflow-wrap:anywhere]">{row.isi}</p>
                                </TableCell>
                                <TableCell className={cellClass}>
                                    <p className="break-words text-sm text-ink">Disusun {row.penyusun}</p>
                                    <p className="mt-1 text-xs text-muted">{formatTanggal(row.created_at, { withTime: true })}</p>
                                    {row.penyetuju && <div className="mt-2 space-y-1"><p className="break-words text-sm text-ink">Disetujui {row.penyetuju}</p><p className="text-xs text-muted">{formatTanggal(row.disetujui_pada, { withTime: true })}</p><PersetujuanCadangan data={row} /></div>}
                                </TableCell>
                                <TableCell className={cellClass}>
                                    <Tooltip content="Lihat detail" align="left">
                                        <Link href={`/rekomendasi/${row.id}`} aria-label={`Detail rekomendasi ${status[row.status_tampilan].label.toLowerCase()} ${formatTanggal(row.created_at, { withTime: true })}`} aria-current={row.id === rekomendasi.id ? 'page' : undefined} className={actionClass}>
                                            <Eye className="h-4 w-4" aria-hidden="true" />
                                        </Link>
                                    </Tooltip>
                                </TableCell>
                            </TableRow>)}
                        </TableBody>
                    </Table>
                    <Pagination {...riwayat} ariaLabel="Halaman rangkaian rekomendasi" className="border-t border-border p-4" />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
