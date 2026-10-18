import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { ClipboardList, Eye, MoreHorizontal, Plus, ShieldCheck } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { ActionMenu, type ActionMenuItem } from '@/Components/ActionMenu';
import { Card } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import Pagination from '@/Pages/Pengukuran/Pagination';
import type { BarisRencanaAksi, FilterStatusRencanaAksi, RencanaAksiIndexProps } from './types';

const OPSI_STATUS: { value: FilterStatusRencanaAksi; label: string }[] = [
    { value: 'semua', label: 'Semua status' },
    { value: 'belum_dibuat', label: 'Belum dibuat' },
    { value: 'draft', label: 'Draft' },
    { value: 'diajukan', label: 'Diajukan' },
    { value: 'diverifikasi', label: 'Diverifikasi' },
    { value: 'dikembalikan', label: 'Dikembalikan' },
    { value: 'disahkan', label: 'Disahkan' },
];

/**
 * Satu menu titik-tiga per baris dengan aksi dari capability server:
 * "Buat Rencana Aksi" (`create`), "Buka" untuk baris ber-header, dan
 * "Review" hanya ketika status non-draft + policy `view` mengizinkan.
 */
function AksiBaris({ baris }: { baris: BarisRencanaAksi }) {
    const form = useForm({ indikator_id: baris.indikator_id, tahun: baris.tahun });
    // 429 ditampilkan sebagai pesan baris, bukan modal HTML bawaan Inertia.
    const [terlaluSering, setTerlaluSering] = useState(false);
    const galat = terlaluSering ? 'Terlalu sering. Coba lagi sebentar.' : Object.values(form.errors)[0];
    const buat = (): void => {
        if (form.processing) {
            return;
        }
        setTerlaluSering(false);
        form.post('/rencana-aksi/ensure-draft', {
            onHttpException: (response) => {
                if (response.status !== 429) {
                    return;
                }
                setTerlaluSering(true);
                return false;
            },
        });
    };

    const aksi: ActionMenuItem[] = [
        ...(baris.can.create ? [{ key: 'buat', label: 'Buat Rencana Aksi', icon: Plus, ariaLabel: `Buat Rencana Aksi ${baris.kode} ${baris.tahun}`, onSelect: buat }] : []),
        ...(baris.rencana_aksi ? [
            { key: 'buka', label: 'Buka', icon: Eye, href: `/rencana-aksi/${baris.rencana_aksi.id}`, ariaLabel: `Buka Rencana Aksi ${baris.kode} ${baris.tahun}` },
            ...(baris.can.review ? [{ key: 'review', label: 'Review', icon: ShieldCheck, href: `/rencana-aksi/${baris.rencana_aksi.id}/review`, ariaLabel: `Review Rencana Aksi ${baris.kode} ${baris.tahun}` }] : []),
        ] : []),
    ];

    if (aksi.length === 0) {
        return <span className="text-muted">—</span>;
    }

    return (
        <div className="flex flex-col items-end gap-1">
            <ActionMenu
                items={aksi}
                trigger={<MoreHorizontal className="h-4 w-4" aria-hidden="true" />}
                triggerLabel={`Aksi ${baris.kode} ${baris.tahun}`}
                triggerClassName="h-8 w-8 px-0"
            />
            {form.processing && <p role="status" className="text-xs text-muted">Memproses…</p>}
            {galat && (
                <p role="alert" className="max-w-56 text-right text-xs text-danger">
                    {galat}
                </p>
            )}
        </div>
    );
}

export default function RencanaAksiIndex({ daftar, pagination, filters }: RencanaAksiIndexProps) {
    const adaFilter = filters.status !== 'semua';
    // Baris "Belum dibuat" belum berupa rencana aksi, jadi label jumlah mengikuti isinya.
    const labelJumlah = adaFilter && filters.status !== 'belum_dibuat' ? 'rencana aksi' : 'indikator';

    return (
        <AuthenticatedLayout title="Rencana Aksi" breadcrumbs={[{ label: 'Rencana Aksi' }]}>
            <Head title="Rencana Aksi" />

            <Card>
                {/* Filter status mempersatukan daftar dan antrean review (Q56). */}
                <div className="flex flex-wrap items-end justify-between gap-3 border-b border-border p-4">
                    <div className="w-full sm:w-72">
                        <Select
                            label="Status rencana aksi"
                            value={filters.status}
                            onChange={(event) => router.get('/rencana-aksi', { status: event.target.value }, { preserveScroll: true })}
                            options={OPSI_STATUS}
                        />
                    </div>
                    <p className="text-xs text-muted">
                        <span className="font-semibold text-ink">{pagination.total}</span> {labelJumlah}
                    </p>
                </div>
                <Table className="table-fixed xl:table-auto">
                    <caption className="sr-only">Daftar indikator dan rencana aksi pada jadwal aktif</caption>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Indikator</TableHead>
                            <TableHead className="hidden xl:table-cell">Unit</TableHead>
                            <TableHead className="hidden xl:table-cell">Tahun</TableHead>
                            <TableHead className="hidden xl:table-cell">Penanggung Jawab</TableHead>
                            <TableHead className="hidden xl:table-cell">Status</TableHead>
                            <TableHead className="w-24 text-right xl:w-auto">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {daftar.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={6} className="py-12 text-center text-sm text-muted">
                                    <ClipboardList className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                    {adaFilter ? (
                                        <>
                                            <p className="mt-2 font-semibold text-ink">Belum ada rencana aksi pada status yang dipilih untuk unit yang dapat Anda akses.</p>
                                            <p className="mt-1 text-xs text-muted">Pilih status lain untuk melihat daftar yang tersedia.</p>
                                        </>
                                    ) : (
                                        <p className="mt-2 font-semibold text-ink">Belum ada indikator pada jadwal aktif untuk unit yang dapat Anda akses.</p>
                                    )}
                                </TableCell>
                            </TableRow>
                        ) : (
                            daftar.map((baris) => (
                                <TableRow key={`${baris.indikator_id}:${baris.tahun}`}>
                                    <TableCell className="max-w-sm break-words">
                                        <div className="font-semibold">
                                            {baris.kode}
                                            <span className="font-normal text-muted xl:hidden"> · {baris.tahun}</span>
                                            {baris.milik_saya && (
                                                <Badge variant="primary" size="sm" className="ml-2">
                                                    Anda
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="mt-0.5 text-muted">{baris.nama}</div>
                                        <p className="mt-1 text-xs text-muted xl:hidden">{baris.unit_nama ?? '—'} · PJ: {baris.pj_nama ?? '—'}</p>
                                        <div className="mt-2 xl:hidden">
                                            {baris.rencana_aksi ? <Badge status={baris.rencana_aksi.status_alur} size="sm" /> : <span className="text-muted">Belum dibuat</span>}
                                        </div>
                                    </TableCell>
                                    <TableCell className="hidden xl:table-cell">{baris.unit_nama ?? '—'}</TableCell>
                                    <TableCell className="hidden xl:table-cell">{baris.tahun}</TableCell>
                                    <TableCell className="hidden xl:table-cell">{baris.pj_nama ?? '—'}</TableCell>
                                    <TableCell className="hidden xl:table-cell">
                                        {baris.rencana_aksi ? (
                                            <Badge status={baris.rencana_aksi.status_alur} size="sm" />
                                        ) : (
                                            <span className="text-muted">Belum dibuat</span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <AksiBaris baris={baris} />
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>

                <Pagination pagination={pagination} label="rencana aksi" />
            </Card>
        </AuthenticatedLayout>
    );
}
