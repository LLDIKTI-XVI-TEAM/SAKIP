import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardList, Eye, Plus, ShieldCheck } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Tooltip } from '@/Components/Tooltip';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import Pagination from '@/Pages/Pengukuran/Pagination';
import type { PengukuranPagination } from '@/Pages/Pengukuran/types';

export interface BarisRencanaAksi {
    indikator_id: string;
    kode: string;
    nama: string;
    unit_nama: string | null;
    tahun: number;
    pj_nama: string | null;
    milik_saya: boolean;
    rencana_aksi: { id: string; status_alur: string } | null;
    /** Capability server: `create` sudah memuat PJ efektif, izin unit, unit snapshot, dan jendela. */
    can: { create: boolean };
}

interface RencanaAksiIndexProps {
    daftar: BarisRencanaAksi[];
    pagination: PengukuranPagination;
}

/** Satu form per baris agar status proses dan pesan gagal tidak bercampur antarbaris. */
function TombolBuat({ baris }: { baris: BarisRencanaAksi }) {
    const form = useForm({ indikator_id: baris.indikator_id, tahun: baris.tahun });
    // 429 ditampilkan sebagai pesan baris, bukan modal HTML bawaan Inertia.
    const [terlaluSering, setTerlaluSering] = useState(false);
    const galat = terlaluSering ? 'Terlalu sering. Coba lagi sebentar.' : Object.values(form.errors)[0];
    const buat = (): void => {
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

    return (
        <div className="flex flex-col items-start gap-1">
            <Tooltip content="Buat" align="right">
                <Button
                    type="button"
                    size="sm"
                    className="h-9 w-9 p-0"
                    isLoading={form.processing}
                    onClick={buat}
                    aria-label={`Buat Rencana Aksi ${baris.kode} ${baris.tahun}`}
                >
                    {!form.processing && <Plus className="h-4 w-4 shrink-0" aria-hidden="true" />}
                </Button>
            </Tooltip>
            {galat && (
                <p role="alert" className="max-w-56 text-left text-xs text-danger">
                    {galat}
                </p>
            )}
        </div>
    );
}

export default function RencanaAksiIndex({ daftar, pagination }: RencanaAksiIndexProps) {
    return (
        <AuthenticatedLayout
            title="Rencana Aksi"
            breadcrumbs={[{ label: 'Rencana Aksi' }]}
            headerActions={
                <div className="flex flex-wrap items-center gap-3">
                    <Link href="/rencana-aksi/review" className="inline-flex h-9 translate-y-1 items-center justify-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-semibold text-white shadow-xs transition-colors hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:ring-offset-1">
                        <ShieldCheck className="h-4 w-4" aria-hidden="true" />
                        Review Rencana Aksi
                    </Link>
                </div>
            }
        >
            <Head title="Rencana Aksi" />

            <Card>
                <Table className="table-fixed xl:table-auto">
                    <caption className="sr-only">Daftar indikator dan rencana aksi pada jadwal aktif</caption>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Indikator</TableHead>
                            <TableHead className="hidden xl:table-cell">Unit</TableHead>
                            <TableHead className="hidden xl:table-cell">Tahun</TableHead>
                            <TableHead className="hidden xl:table-cell">Penanggung Jawab</TableHead>
                            <TableHead className="hidden xl:table-cell">Status</TableHead>
                            <TableHead className="w-20 text-left xl:w-auto">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {daftar.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={6} className="py-12 text-center text-sm text-muted">
                                    <ClipboardList className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                    <p className="mt-2 font-semibold text-ink">Belum ada indikator pada jadwal aktif untuk unit yang dapat Anda akses.</p>
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
                                    <TableCell className="text-left">
                                        {baris.can.create ? (
                                            <TombolBuat baris={baris} />
                                        ) : baris.rencana_aksi ? (
                                            <Tooltip content="Buka" align="right">
                                                <Link
                                                    href={`/rencana-aksi/${baris.rencana_aksi.id}`}
                                                    aria-label={`Buka Rencana Aksi ${baris.kode} ${baris.tahun}`}
                                                    className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-surface text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25"
                                                >
                                                    <Eye className="h-4 w-4" aria-hidden="true" />
                                                </Link>
                                            </Tooltip>
                                        ) : (
                                            <span className="text-muted">—</span>
                                        )}
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
