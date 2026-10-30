import { useId } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { formatRupiah } from '@/lib/rupiah';
import { BerkasPerencanaanPanel } from '@/Pages/Kegiatan/Partials/BerkasPerencanaanPanel';
import { RealisasiKegiatanPanel } from '@/Pages/Kegiatan/Partials/RealisasiKegiatanPanel';
import { StatusPelaksanaanCard } from '@/Pages/Kegiatan/Partials/StatusPelaksanaanCard';
import type { KegiatanEditProps } from './KegiatanEdit';

/**
 * Halaman catat realisasi kegiatan (Q74 §74.2 butir 1), terpisah dari form
 * rencana dan dibuka dari menu aksi halaman kegiatan Rencana Aksi. Props sama
 * dengan halaman ubah (`FormKegiatanRencanaAksi`); data rencana hanya tampil
 * sebagai konteks baca-saja.
 */
export default function KegiatanRealisasi({ rencanaAksi, kegiatan, can, pelaksanaan, opsiPeriodeTujuan, berkasPelaksanaan }: KegiatanEditProps) {
    const judulRencanaId = useId();
    const formatTanggal = useFormatTanggal();
    const halamanKegiatan = `/rencana-aksi/${rencanaAksi.id}/kegiatan`;
    const title = 'Catat realisasi kegiatan';

    return (
        <AuthenticatedLayout
            title={title}
            breadcrumbs={[
                { label: 'Rencana Aksi', href: '/rencana-aksi' },
                { label: rencanaAksi.kode ? `${rencanaAksi.kode} · ${rencanaAksi.tahun}` : `Tahun ${rencanaAksi.tahun}`, href: `/rencana-aksi/${rencanaAksi.id}` },
                { label: 'Kegiatan', href: halamanKegiatan },
                { label: title },
            ]}
        >
            <Head title={title} />
            <div className="mx-auto max-w-5xl space-y-5">
                <Link href={halamanKegiatan} className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/25">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke kegiatan rencana aksi
                </Link>
                <Card role="region" aria-labelledby={judulRencanaId}>
                    <CardHeader><CardTitle id={judulRencanaId}>Rencana kegiatan</CardTitle></CardHeader>
                    <CardContent>
                        <dl className="grid gap-x-4 gap-y-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div className="sm:col-span-2 lg:col-span-4">
                                <dt className="text-muted">Nama kegiatan</dt>
                                <dd className="mt-1 break-words font-medium text-ink">{kegiatan.nama}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Periode</dt>
                                <dd className="mt-1 font-medium text-ink">{kegiatan.periode_nama} {kegiatan.tahun}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Tanggal rencana</dt>
                                <dd className="mt-1 font-medium text-ink">{formatTanggal(kegiatan.tanggal_rencana)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Anggaran rencana</dt>
                                <dd className="mt-1 font-medium text-ink">{formatRupiah(kegiatan.anggaran)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Sasaran peserta</dt>
                                <dd className="mt-1 font-medium text-ink">{kegiatan.sasaran_peserta ?? '—'}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
                <StatusPelaksanaanCard kegiatan={kegiatan} ubahStatus={can.ubah_status} opsiPeriodeTujuan={opsiPeriodeTujuan} />
                <RealisasiKegiatanPanel key={kegiatan.id} kegiatan={kegiatan} can={can} pelaksanaan={pelaksanaan} halamanUbah={`${halamanKegiatan}/${kegiatan.id}/realisasi`} />
                {berkasPelaksanaan && <BerkasPerencanaanPanel kegiatanId={kegiatan.id} berkas={berkasPelaksanaan} tahap="pelaksanaan" />}
            </div>
        </AuthenticatedLayout>
    );
}
