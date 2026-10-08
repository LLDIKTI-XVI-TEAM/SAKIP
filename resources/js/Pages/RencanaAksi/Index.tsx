import { Head, Link, useForm } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';

export interface BarisRencanaAksi {
    indikator_id: string;
    kode: string;
    nama: string;
    unit_nama: string | null;
    tahun: number;
    pj_nama: string | null;
    milik_saya: boolean;
    rencana_aksi: { id: string; status_alur: string } | null;
    /** Capability server: `buat` sudah memuat izin unit, PJ efektif, dan jendela. */
    can: { buat: boolean; buka: boolean };
}

interface RencanaAksiIndexProps {
    daftar: BarisRencanaAksi[];
    pagination: { current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };
}

/** Satu form per baris agar status proses dan pesan gagal tidak bercampur antarbaris. */
function TombolBuat({ baris }: { baris: BarisRencanaAksi }) {
    const form = useForm({ indikator_id: baris.indikator_id, tahun: baris.tahun });
    const galat = Object.values(form.errors)[0];

    return (
        <div className="flex flex-col items-end gap-1">
            <Button
                type="button"
                size="sm"
                className="whitespace-nowrap"
                isLoading={form.processing}
                onClick={() => form.post('/rencana-aksi/ensure-draft')}
                aria-label={`Buat Rencana Aksi ${baris.kode} ${baris.tahun}`}
            >
                Buat Rencana Aksi
            </Button>
            {galat && (
                <p role="alert" className="max-w-56 text-right text-xs text-danger">
                    {galat}
                </p>
            )}
        </div>
    );
}

export default function RencanaAksiIndex({ daftar, pagination }: RencanaAksiIndexProps) {
    return (
        <AuthenticatedLayout title="Rencana Aksi" breadcrumbs={[{ label: 'Rencana Aksi' }]}>
            <Head title="Rencana Aksi" />

            <Card>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Indikator</TableHead>
                            <TableHead className="hidden md:table-cell">Unit</TableHead>
                            <TableHead className="hidden md:table-cell">Tahun</TableHead>
                            <TableHead className="hidden md:table-cell">Penanggung Jawab</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {daftar.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={6} className="py-12 text-center text-sm text-muted">
                                    Belum ada indikator pada jadwal aktif.
                                </TableCell>
                            </TableRow>
                        ) : (
                            daftar.map((baris) => (
                                <TableRow key={`${baris.indikator_id}:${baris.tahun}`}>
                                    <TableCell className="max-w-sm">
                                        <div className="font-semibold">
                                            {baris.kode}
                                            <span className="font-normal text-muted md:hidden"> · {baris.tahun}</span>
                                            {baris.milik_saya && (
                                                <Badge variant="primary" size="sm" className="ml-2 md:hidden">
                                                    Anda
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="mt-0.5 text-muted">{baris.nama}</div>
                                    </TableCell>
                                    <TableCell className="hidden md:table-cell">{baris.unit_nama ?? '—'}</TableCell>
                                    <TableCell className="hidden md:table-cell">{baris.tahun}</TableCell>
                                    <TableCell className="hidden md:table-cell">
                                        <span className="inline-flex items-center gap-2">
                                            {baris.pj_nama ?? '—'}
                                            {baris.milik_saya && (
                                                <Badge variant="primary" size="sm">
                                                    Anda
                                                </Badge>
                                            )}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        {baris.rencana_aksi ? (
                                            <Badge status={baris.rencana_aksi.status_alur} size="sm" />
                                        ) : (
                                            <span className="text-muted">Belum dibuat</span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {baris.can.buat ? (
                                            <TombolBuat baris={baris} />
                                        ) : baris.can.buka && baris.rencana_aksi ? (
                                            <Link
                                                href={`/rencana-aksi/${baris.rencana_aksi.id}`}
                                                aria-label={`Buka Rencana Aksi ${baris.kode} ${baris.tahun}`}
                                                className="inline-flex h-9 items-center justify-center whitespace-nowrap rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25"
                                            >
                                                Buka
                                            </Link>
                                        ) : (
                                            <span className="text-muted">—</span>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>

                {pagination.last_page > 1 && (
                    <nav aria-label="Halaman rencana aksi" className="flex items-center justify-between gap-3 border-t border-border p-4 text-sm">
                        {pagination.prev_page_url ? (
                            <Link href={pagination.prev_page_url} className="rounded text-primary underline focus:ring-2 focus:ring-primary">
                                Sebelumnya
                            </Link>
                        ) : (
                            <span className="text-muted">Sebelumnya</span>
                        )}
                        <span className="text-ink">
                            Halaman {pagination.current_page} dari {pagination.last_page}
                        </span>
                        {pagination.next_page_url ? (
                            <Link href={pagination.next_page_url} className="rounded text-primary underline focus:ring-2 focus:ring-primary">
                                Berikutnya
                            </Link>
                        ) : (
                            <span className="text-muted">Berikutnya</span>
                        )}
                    </nav>
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
