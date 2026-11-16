import { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { ClipboardCheck, Eye, PenLine, Search } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { StatusCapaianBadge } from '@/Components/Pengukuran/StatusCapaianBadge';
import { TabTautan } from '@/Components/TabTautan';
import {
    Table, TableActionLink, TableBody, TableCell, TableCellLabel, TableEmptyRow, TableHead, TableHeader, TableRow, tableCellResponsive,
} from '@/Components/Table';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { useLabelUnit } from '@/hooks/useLabelUnit';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import Pagination from '@/Pages/Pengukuran/Pagination';
import type { BarisStatusCapaian, ModeStatusCapaian, StatusCapaianProps } from '@/types/statusCapaian';
import DialogStatusCapaian from './DialogStatusCapaian';

const sel = `${tableCellResponsive} block align-top md:table-cell`;
const selKanan = `${sel} md:text-right`;
const barisKartu = 'block min-w-0 space-y-2 p-4 md:table-row md:space-y-0 md:p-0';

export default function StatusCapaian({ kandidat, sudah_ditetapkan, pagination, pagination_status, can }: StatusCapaianProps) {
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const labelUnit = useLabelUnit();
    const { url } = usePage();
    const [dialog, setDialog] = useState<{ baris: BarisStatusCapaian; mode: ModeStatusCapaian } | null>(null);

    // Tab aktif dibaca dari URL agar bertahan saat pindah halaman dan setelah penetapan disimpan.
    const query = new URLSearchParams(url.split('?')[1] ?? '');
    const tabDitetapkan = query.get('tab') === 'ditetapkan';
    const tautanTab = (ditetapkan: boolean): string => {
        const params = new URLSearchParams(query);
        if (ditetapkan) params.set('tab', 'ditetapkan');
        else params.delete('tab');
        const teks = params.toString();

        return `/verifikasi/status-capaian${teks ? `?${teks}` : ''}`;
    };

    const angka = (baris: BarisStatusCapaian, nilai: string | null) => {
        if (nilai === null) return '-';
        const desimal = baris.indikator.desimal_tampilan ?? 0;
        return `${formatNilai(nilai, desimal)}${baris.indikator.satuan ? ` ${baris.indikator.satuan}` : ''}`;
    };
    const capaian = (baris: BarisStatusCapaian) => baris.capaian_persen === null ? '-' : `${formatNilai(baris.capaian_persen, 2)}%`;
    const periode = (baris: BarisStatusCapaian) => `${baris.periode.nama ?? '-'} ${baris.tahun}`;
    const versi = (baris: BarisStatusCapaian) => baris.versi_pengukuran === null ? 'Belum ada versi sah' : `Versi ${baris.versi_pengukuran.nomor}`;
    const sebutan = (baris: BarisStatusCapaian) => baris.indikator.kode ?? baris.indikator.nama ?? '';

    // Tiga kolom pertama sama di kedua daftar; rincian lain dibaca di halaman rincian yang tersedia dari setiap baris.
    const selIdentitas = (baris: BarisStatusCapaian) => (
        <>
            <TableCell className={sel}>
                <p className="text-xs font-bold text-ink">{baris.indikator.kode ?? '-'}</p>
                <p title={baris.indikator.nama ?? undefined} className="mt-0.5 line-clamp-2 whitespace-normal break-words text-xs leading-relaxed text-muted">{baris.indikator.nama ?? '-'}</p>
            </TableCell>
            <TableCell className={sel}>
                <TableCellLabel>{labelUnit}</TableCellLabel>
                <span className="break-words text-xs text-ink">{baris.unit.nama ?? '-'}</span>
            </TableCell>
            <TableCell className={sel}>
                <TableCellLabel>Periode</TableCellLabel>
                <span className="text-xs text-ink md:whitespace-nowrap">{periode(baris)}</span>
                <span className="block text-xs text-muted">{versi(baris)}</span>
            </TableCell>
        </>
    );
    const selCapaian = (baris: BarisStatusCapaian) => (
        <TableCell className={selKanan}>
            <TableCellLabel>Capaian</TableCellLabel>
            <span className="font-semibold tabular-nums text-ink">{capaian(baris)}</span>
        </TableCell>
    );

    // Tersedia di setiap baris, termasuk yang tidak dapat ditetapkan atau direvisi, agar angkanya tetap terbaca.
    const tautanRincian = (baris: BarisStatusCapaian) => (
        <TableActionLink
            href={`/verifikasi/status-capaian/${baris.id}`}
            label={`Lihat rincian status capaian ${sebutan(baris)}`}
            tooltip="Lihat rincian"
            icon={Eye}
        />
    );

    return (
        <AuthenticatedLayout
            title="Status Capaian"
            breadcrumbs={[{ label: 'Verifikasi Kinerja', href: '/verifikasi' }, { label: 'Status Capaian' }]}
        >
            <Head title="Status Capaian" />

            <div className="min-w-0 space-y-5">
                {!can.tetapkan && (
                    <div role="status" className="rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm text-ink">
                        Anda tidak memiliki izin menetapkan status capaian. Halaman ini hanya dapat dibaca.
                    </div>
                )}

                <Card>
                    <TabTautan
                        label="Daftar status capaian"
                        satuan="pengukuran"
                        items={[
                            { href: tautanTab(false), label: 'Belum ditetapkan', jumlah: pagination.total, aktif: !tabDitetapkan },
                            { href: tautanTab(true), label: 'Sudah ditetapkan', jumlah: pagination_status.total, aktif: tabDitetapkan },
                        ]}
                    />

                    {tabDitetapkan ? (
                        <Table wrapperClassName="overflow-x-visible" className="block md:table" aria-label="Status capaian yang sudah ditetapkan">
                            <TableHeader className="max-md:sr-only">
                                <TableRow>
                                    <TableHead className="whitespace-normal">KODE &amp; INDIKATOR</TableHead>
                                    <TableHead>{labelUnit.toUpperCase()}</TableHead>
                                    <TableHead>PERIODE</TableHead>
                                    <TableHead className="text-right">CAPAIAN</TableHead>
                                    <TableHead>STATUS</TableHead>
                                    <TableHead className="text-right">AKSI</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="block md:table-row-group">
                                {sudah_ditetapkan.length === 0 ? (
                                    <TableEmptyRow
                                        colSpan={6}
                                        icon={Search}
                                        title="Belum ada status capaian yang ditetapkan"
                                        description="Status yang ditetapkan pada versi pengukuran yang disahkan tampil di sini, beserta riwayat revisinya."
                                    />
                                ) : sudah_ditetapkan.map((baris) => (
                                    <TableRow key={baris.id} className={barisKartu}>
                                        {selIdentitas(baris)}
                                        {selCapaian(baris)}
                                        <TableCell className={sel}>
                                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 md:flex-col md:items-start md:whitespace-nowrap">
                                                {baris.status_berlaku && <StatusCapaianBadge kode={baris.status_berlaku.status} size="sm" />}
                                                <span className="text-xs text-muted">{formatTanggal(baris.status_berlaku?.ditetapkan_pada)}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className={selKanan}>
                                            <div className="inline-flex items-center gap-2">
                                                {tautanRincian(baris)}
                                                {can.tetapkan && baris.can.revisi && (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => setDialog({ baris, mode: 'revisi' })} aria-label={`Revisi status capaian ${sebutan(baris)}`}>
                                                        <PenLine className="h-3.5 w-3.5" aria-hidden="true" />
                                                        Revisi
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <Table wrapperClassName="overflow-x-visible" className="block md:table" aria-label="Kandidat penetapan status capaian">
                            <TableHeader className="max-md:sr-only">
                                <TableRow>
                                    <TableHead className="whitespace-normal">KODE &amp; INDIKATOR</TableHead>
                                    <TableHead>{labelUnit.toUpperCase()}</TableHead>
                                    <TableHead>PERIODE</TableHead>
                                    <TableHead className="text-right">REALISASI</TableHead>
                                    <TableHead className="text-right">CAPAIAN</TableHead>
                                    <TableHead className="text-right">AKSI</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="block md:table-row-group">
                                {kandidat.length === 0 ? (
                                    <TableEmptyRow
                                        colSpan={6}
                                        icon={Search}
                                        title={pagination.total === 0 ? 'Tidak ada pengukuran yang menunggu penetapan' : 'Tidak ada kandidat di halaman ini'}
                                        description={pagination.total === 0
                                            ? 'Semua versi pengukuran yang disahkan sudah memiliki status capaian, atau belum ada versi yang memenuhi syarat penetapan.'
                                            : `Halaman ini tidak memuat kandidat, tetapi masih ada ${pagination.total} pengukuran yang menunggu penetapan pada halaman lain. Gunakan tautan Sebelumnya di bawah untuk kembali ke halaman berisi kandidat.`}
                                    />
                                ) : kandidat.map((baris) => (
                                    <TableRow key={baris.id} className={barisKartu}>
                                        {selIdentitas(baris)}
                                        <TableCell className={selKanan}>
                                            <TableCellLabel>Realisasi</TableCellLabel>
                                            <span className="font-semibold tabular-nums text-ink md:whitespace-nowrap">{angka(baris, baris.nilai)}</span>
                                            <span className="block text-xs tabular-nums text-muted md:whitespace-nowrap">Target {angka(baris, baris.target)}</span>
                                        </TableCell>
                                        {selCapaian(baris)}
                                        <TableCell className={selKanan}>
                                            <div className="inline-flex items-center gap-2">
                                                {tautanRincian(baris)}
                                                {can.tetapkan && baris.can.tetapkan ? (
                                                    <Button type="button" size="sm" onClick={() => setDialog({ baris, mode: 'tetapkan' })} aria-label={`Tetapkan status capaian ${sebutan(baris)}`}>
                                                        <ClipboardCheck className="h-3.5 w-3.5" aria-hidden="true" />
                                                        Tetapkan
                                                    </Button>
                                                ) : (
                                                    <span className="text-xs text-muted md:whitespace-nowrap">{can.tetapkan ? 'Tidak memenuhi syarat' : 'Tanpa izin'}</span>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}

                    <Pagination pagination={tabDitetapkan ? pagination_status : pagination} />
                </Card>
            </div>

            {dialog && (
                <DialogStatusCapaian
                    key={`${dialog.mode}-${dialog.baris.id}-${dialog.baris.versi}`}
                    baris={dialog.baris}
                    mode={dialog.mode}
                    onClose={() => setDialog(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
