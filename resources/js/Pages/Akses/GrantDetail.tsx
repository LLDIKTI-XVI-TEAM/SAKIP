import { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardHeader, CardTitle } from '@/Components/Card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { CabutSemuaIzinUnitModal } from '@/Components/Access/CabutSemuaIzinUnitModal';
import { KelolaIzinUnitModal } from '@/Components/Access/KelolaIzinUnitModal';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { SharedPageProps } from '@/types/auth';
import type { KelompokIzinUnit, PilihanIzin } from '@/types/izinUnit';

interface GrantDetailProps {
    kelompok: KelompokIzinUnit;
    unitPermissions: PilihanIzin[];
    can: { kelola_grant: boolean; revoke_grant: boolean };
}

const cellClass = 'min-w-0 p-0 md:px-4 md:py-3.5';
const mobileLabel = 'mr-2 text-xs text-muted md:hidden';

export default function GrantDetail({ kelompok, unitPermissions, can }: GrantDetailProps) {
    const formatTanggal = useFormatTanggal();
    const { props: { auth } } = usePage<SharedPageProps>();
    const [kelola, setKelola] = useState(false);
    const [cabut, setCabut] = useState(false);
    const berlaku = kelompok.tidak_berlaku.length === 0;
    const info: [string, string][] = [
        ['Email', kelompok.user_email],
        ['Peran', kelompok.user_roles.length > 0 ? kelompok.user_roles.join(', ') : 'Belum memiliki peran'],
        ['Unit', kelompok.unit_nama ?? '—'],
        ['Status', berlaku ? 'Berlaku' : `Tidak berlaku · ${kelompok.tidak_berlaku.join(' · ')}`],
    ];

    return (
        <AuthenticatedLayout title="Detail Izin Unit" breadcrumbs={[{ label: 'Izin Unit', href: '/akses/grant' }, { label: kelompok.user_name }]}>
            <Head title="Detail Izin Unit" />
            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link href="/akses/grant" className="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        Kembali ke daftar izin unit
                    </Link>
                    <div className="flex flex-wrap gap-2">
                        {can.kelola_grant && <Button type="button" size="sm" variant="outline" onClick={() => setKelola(true)}>
                            <Pencil className="h-4 w-4" aria-hidden="true" />
                            Kelola izin
                        </Button>}
                        {can.revoke_grant && <Button type="button" size="sm" variant="outline" className="text-danger hover:bg-danger/10" onClick={() => setCabut(true)}>
                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                            Cabut semua
                        </Button>}
                    </div>
                </div>

                <Card>
                    <CardHeader className="px-5">
                        <h2 className="min-w-0 break-words text-base font-semibold text-ink">{kelompok.user_name}</h2>
                        <Badge variant={berlaku ? 'success' : 'warning'}>{berlaku ? 'Berlaku' : 'Tidak berlaku'}</Badge>
                    </CardHeader>
                    <dl className="grid gap-4 p-5 text-sm sm:grid-cols-2">
                        {info.map(([label, nilai]) => <div key={label} className="min-w-0">
                            <dt className="text-xs font-medium text-muted">{label}</dt>
                            <dd className="mt-1 break-words text-ink">{nilai}</dd>
                        </div>)}
                    </dl>
                </Card>

                <Card>
                    <CardHeader className="px-5">
                        <CardTitle>Izin yang dimiliki</CardTitle>
                        <span className="text-xs text-muted">{kelompok.izin.length} izin</span>
                    </CardHeader>
                    {/* Grid mobile: setiap izin tampil sebagai kartu ringkas tanpa scroll horizontal. */}
                    <Table wrapperClassName="overflow-x-visible" className="block md:table">
                        <caption className="sr-only">Rincian izin unit {kelompok.user_name}</caption>
                        <TableHeader className="max-md:sr-only">
                            <TableRow>
                                <TableHead>Izin</TableHead>
                                <TableHead>Status izin</TableHead>
                                <TableHead className="whitespace-normal">Alasan pemberian</TableHead>
                                <TableHead>Diberikan oleh</TableHead>
                                <TableHead>Waktu</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="block md:table-row-group">
                            {kelompok.izin.map((izin) => <TableRow key={izin.grant_id} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                <TableCell className={`col-span-2 ${cellClass}`}>
                                    <span className="break-words text-sm font-semibold text-ink">{izin.label}</span>
                                </TableCell>
                                <TableCell className={`col-span-2 ${cellClass}`}>
                                    <span className={mobileLabel}>Status izin</span>
                                    {izin.ditolak
                                        ? <Badge variant="warning">Ditolak</Badge>
                                        : <Badge variant={izin.aktif ? 'success' : 'muted'}>{izin.aktif ? 'Aktif' : 'Izin nonaktif'}</Badge>}
                                </TableCell>
                                <TableCell className={`col-span-2 ${cellClass}`}>
                                    <p className="whitespace-pre-wrap break-words text-sm text-ink"><span className={mobileLabel}>Alasan</span>{izin.alasan}</p>
                                </TableCell>
                                <TableCell className={cellClass}>
                                    <span className={mobileLabel}>Oleh</span>
                                    <span className="break-words text-sm text-ink">{izin.diberikan_oleh_nama}</span>
                                </TableCell>
                                <TableCell className={cellClass}>
                                    <span className={mobileLabel}>Waktu</span>
                                    {izin.created_at ? <time dateTime={izin.created_at} className="text-sm text-ink">{formatTanggal(izin.created_at, { withTime: true })}</time> : '—'}
                                    {auth.can.audit && <Link
                                        href={`/audit?objek_tipe=user_permission_granted&objek_id=${izin.grant_id}`}
                                        aria-label={`Jejak audit ${izin.label}`}
                                        className="mt-1 block text-xs font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/20"
                                    >
                                        Jejak audit
                                    </Link>}
                                </TableCell>
                            </TableRow>)}
                        </TableBody>
                    </Table>
                </Card>
            </div>

            <KelolaIzinUnitModal kelompok={kelola ? kelompok : null} options={unitPermissions} onClose={() => setKelola(false)} />
            <CabutSemuaIzinUnitModal kelompok={cabut ? kelompok : null} onClose={() => setCabut(false)} />
        </AuthenticatedLayout>
    );
}
