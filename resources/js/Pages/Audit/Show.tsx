import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/Components/Badge';
import { Card, CardHeader, CardTitle } from '@/Components/Card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { jenisPelakuLabel, type AuditDetail, type AuditJson } from '@/types/audit';

interface AuditShowProps {
    audit: AuditDetail;
}

interface BarisPerubahan {
    data: string;
    lama: AuditJson | undefined;
    baru: AuditJson | undefined;
    berubah: boolean;
}

const cellClass = 'min-w-0 p-0 align-top md:px-4 md:py-3.5';

const objekJson = (nilai: AuditJson): nilai is { [key: string]: AuditJson } =>
    typeof nilai === 'object' && nilai !== null && !Array.isArray(nilai);

/** Membandingkan kunci tingkat atas snapshot; payload yang bukan objek dibandingkan sebagai satu nilai. */
function barisPerubahan(lama: AuditJson, baru: AuditJson): BarisPerubahan[] {
    const sebagaiObjek = (nilai: AuditJson) => (objekJson(nilai) ? nilai : nilai === null ? {} : { nilai });
    const kiri = sebagaiObjek(lama);
    const kanan = sebagaiObjek(baru);

    return [...new Set([...Object.keys(kiri), ...Object.keys(kanan)])].map((data) => ({
        data,
        lama: kiri[data],
        baru: kanan[data],
        berubah: JSON.stringify(kiri[data]) !== JSON.stringify(kanan[data]),
    }));
}

function NilaiAudit({ nilai }: { nilai: AuditJson | undefined }) {
    if (nilai === undefined) return <span className="text-muted">Tidak dicatat</span>;
    if (nilai === null || nilai === '') return <span className="text-muted">Kosong</span>;
    if (typeof nilai === 'boolean') return <span>{nilai ? 'Ya' : 'Tidak'}</span>;
    if (typeof nilai !== 'object') return <span className="whitespace-pre-line break-words">{String(nilai)}</span>;
    if (Array.isArray(nilai)) {
        return nilai.length === 0 ? <span className="text-muted">Kosong</span> : (
            <ul className="space-y-1">
                {nilai.map((item, urutan) => (
                    <li key={urutan} className="min-w-0"><NilaiAudit nilai={item} /></li>
                ))}
            </ul>
        );
    }
    const isi = Object.entries(nilai);

    return isi.length === 0 ? <span className="text-muted">Kosong</span> : (
        <dl className="space-y-1.5">
            {isi.map(([kunci, anak]) => (
                <div key={kunci} className="min-w-0">
                    <dt className="break-all font-mono text-muted">{kunci}</dt>
                    <dd className="min-w-0 border-l border-border pl-3"><NilaiAudit nilai={anak} /></dd>
                </div>
            ))}
        </dl>
    );
}

export default function AuditShow({ audit }: AuditShowProps) {
    const formatTanggal = useFormatTanggal();
    const perubahan = barisPerubahan(audit.nilai_lama, audit.nilai_baru);
    const jenisPelaku = jenisPelakuLabel[audit.actor_type] ?? audit.actor_type;
    const info: [string, ReactNode][] = [
        ['Waktu', formatTanggal(audit.waktu, { withDay: true, withTime: true })],
        ['Pelaku', audit.aktor ? (
            <>
                <span className="block font-semibold">{audit.aktor.nama}</span>
                <span className="block break-all text-muted">{audit.aktor.email}</span>
            </>
        ) : jenisPelaku],
        ['Jenis pelaku', jenisPelaku],
        ['Sumber pencatatan', <span className="font-mono">{audit.sumber}</span>],
        ['Objek', (
            <>
                <span className="block break-all font-mono">{audit.objek_tipe}</span>
                <span className="block break-all font-mono text-muted">{audit.objek_id}</span>
            </>
        )],
        ...(audit.operator_reference ? [['Referensi operator', <span className="break-all font-mono">{audit.operator_reference}</span>] as [string, ReactNode]] : []),
        ...(audit.runtime_identity ? [['Identitas runtime', <span className="break-all font-mono">{audit.runtime_identity}</span>] as [string, ReactNode]] : []),
        ['Alasan', audit.alasan ?? '—'],
    ];

    return (
        <AuthenticatedLayout title="Detail Jejak Audit" breadcrumbs={[{ label: 'Jejak Audit', href: '/audit' }, { label: 'Detail' }]}>
            <Head title="Detail Jejak Audit" />

            <div className="space-y-5">
                <Link href="/audit" className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke daftar
                </Link>

                <Card>
                    <CardHeader className="px-5">
                        <h2 className="min-w-0 break-all font-mono text-base font-semibold text-ink">{audit.tindakan}</h2>
                        <Badge variant={audit.ditolak ? 'danger' : 'muted'}>{audit.ditolak ? 'Ditolak' : 'Tercatat'}</Badge>
                    </CardHeader>
                    <dl className="grid gap-4 p-5 text-sm sm:grid-cols-2">
                        {info.map(([label, nilai]) => (
                            <div key={label} className="min-w-0">
                                <dt className="text-xs font-medium text-muted">{label}</dt>
                                <dd className="mt-1 whitespace-pre-line break-words text-ink">{nilai}</dd>
                            </div>
                        ))}
                    </dl>
                </Card>

                <Card>
                    <CardHeader className="px-5">
                        <CardTitle>Perubahan data</CardTitle>
                    </CardHeader>
                    {perubahan.length === 0 ? (
                        <p className="px-5 py-8 text-sm text-muted">Tidak ada perubahan data yang dicatat.</p>
                    ) : (
                        <Table wrapperClassName="overflow-x-visible" className="block md:table">
                            <caption className="sr-only">Perbandingan nilai lama dan nilai baru</caption>
                            <TableHeader className="max-md:sr-only">
                                <TableRow>
                                    <TableHead>Data</TableHead>
                                    <TableHead>Nilai lama</TableHead>
                                    <TableHead>Nilai baru</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="block md:table-row-group">
                                {perubahan.map((baris) => (
                                    <TableRow key={baris.data} className="grid min-w-0 grid-cols-1 gap-2 p-4 md:table-row">
                                        <TableCell className={cellClass}>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="break-all font-mono font-semibold text-ink">{baris.data}</span>
                                                {baris.berubah && <Badge variant="warning">Berubah</Badge>}
                                            </div>
                                        </TableCell>
                                        <TableCell className={cellClass}>
                                            <span className="mb-1 block text-muted md:hidden">Nilai lama</span>
                                            <NilaiAudit nilai={baris.lama} />
                                        </TableCell>
                                        <TableCell className={cellClass}>
                                            <span className="mb-1 block text-muted md:hidden">Nilai baru</span>
                                            <NilaiAudit nilai={baris.baru} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Card>

                <Card role="region" aria-labelledby="judul-dasar-izin">
                    <CardHeader className="px-5">
                        <CardTitle id="judul-dasar-izin">Dasar izin</CardTitle>
                    </CardHeader>
                    <div className="p-5 text-xs text-ink">
                        {audit.dasar_izin === null ? (
                            <p className="text-sm text-muted">Tidak ada dasar izin yang dicatat untuk tindakan ini.</p>
                        ) : (
                            <NilaiAudit nilai={audit.dasar_izin} />
                        )}
                    </div>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
