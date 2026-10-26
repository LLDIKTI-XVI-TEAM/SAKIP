import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import KegiatanPanel from './KegiatanPanel';
import type { RencanaAksiKegiatan, RencanaAksiShow } from './types';

/**
 * Props halaman kegiatan satu Rencana Aksi. Payload `rencanaAksi` sama dengan
 * `RencanaAksi/Show` (sumber `IndexRencanaAksi`); `kegiatan` null bila aktor
 * tidak berhak membaca kegiatan unit — halaman menampilkan state tanpa data,
 * bukan 403.
 */
export interface KegiatanRencanaAksiProps {
    rencanaAksi: RencanaAksiShow;
    kegiatan: RencanaAksiKegiatan | null;
}

/**
 * Halaman kegiatan per periode di dalam Rencana Aksi (Q74 §74.1): satu-satunya
 * permukaan tambah, ubah, klaim, berkas, dan status kegiatan. Otorisasi,
 * kunci periode, dan capability tetap dari server; React hanya menampilkan
 * `kegiatan.can.tambah`/`kegiatan.can.kelola` dan `periode_dapat_ditulis`.
 */
export default function KegiatanRencanaAksi({ rencanaAksi, kegiatan }: KegiatanRencanaAksiProps) {
    const judul = `Kegiatan ${rencanaAksi.indikator.kode} · ${rencanaAksi.tahun}`;
    const halamanRencanaAksi = `/rencana-aksi/${rencanaAksi.id}`;
    // Sama dengan detail RA: hanya periode efektif yang tampil, dan periode di
    // luar `periode_dapat_ditulis` tidak menawarkan tambah kegiatan. Daftar itu
    // sudah dihitung server (efektif ∩ lingkup koreksi − terkunci bagi aktor),
    // jadi klien tidak menurunkan ulang aturan kunci dari `koreksi` +
    // `periode_terkunci`. Tampilan saja; server tetap menolak di bawah lock.
    const periode = [...rencanaAksi.periode].sort((a, b) => a.urutan - b.urutan).filter((baris) => baris.efektif);
    const dapatDitulis = new Set(rencanaAksi.periode_dapat_ditulis ?? []);
    const terkunci = periode.filter((baris) => !dapatDitulis.has(baris.id)).map((baris) => baris.id);

    return (
        <AuthenticatedLayout
            title={judul}
            breadcrumbs={[
                { label: 'Rencana Aksi', href: '/rencana-aksi' },
                { label: `${rencanaAksi.indikator.kode} · ${rencanaAksi.tahun}`, href: halamanRencanaAksi },
                { label: 'Kegiatan' },
            ]}
            headerActions={
                <Link href={halamanRencanaAksi} className="ml-auto inline-flex h-9 shrink-0 translate-y-1 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke rincian
                </Link>
            }
        >
            <Head title={judul} />
            <div className="space-y-5">
                <Card>
                    <CardHeader>
                        <div className="min-w-0 flex-1 space-y-1.5">
                            <CardTitle className="break-words">
                                {rencanaAksi.indikator.kode} · {rencanaAksi.indikator.nama}
                            </CardTitle>
                            <p className="text-xs text-muted">Tahun {rencanaAksi.tahun}</p>
                        </div>
                        <Badge status={rencanaAksi.status_alur} />
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt className="text-muted">Unit penanggung jawab</dt>
                                <dd className="mt-1 font-medium">{rencanaAksi.unit?.nama ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Periode efektif</dt>
                                <dd className="mt-1 font-medium">{periode.length > 0 ? `${periode.length} periode` : 'Belum ada'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Penanggung jawab</dt>
                                <dd className="mt-1 font-medium">{rencanaAksi.penanggung_jawab?.nama ?? '—'}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {kegiatan === null ? (
                    <Card role="status">
                        <CardContent className="space-y-2 p-4 sm:p-6">
                            <p className="text-sm font-semibold text-ink">Kegiatan tidak dapat ditampilkan</p>
                            <p className="text-sm text-muted">
                                Anda tidak memiliki izin membaca kegiatan pada unit rencana aksi ini. Hubungi pengelola izin bila akses
                                diperlukan.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <KegiatanPanel
                        rencanaAksiId={rencanaAksi.id}
                        periode={periode}
                        komponen={rencanaAksi.komponen}
                        terkunci={terkunci}
                        kegiatan={kegiatan}
                        // Halaman ini tidak memuat form target; tidak ada draf yang perlu disimpan sebelum mengelola kegiatan.
                        kotor={false}
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
